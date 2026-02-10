<?php
/**
 * Cron Cleanup Class
 *
 * Handles scheduled tasks for anonymizing old votes and likes.
 * Runs daily to remove identifier hashes from votes older than 30 days.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Cron_Cleanup
 *
 * Manages anonymization of old anonymous votes.
 *
 * @since 2.0.0
 */
class Fanfic_Cron_Cleanup {

	/**
	 * Anonymization window in days
	 *
	 * @var int
	 */
	const ANONYMIZATION_DAYS = 30;

	/**
	 * Continuation hook name.
	 *
	 * @var string
	 */
	const CONTINUATION_HOOK = 'fanfic_anonymize_old_data_continue';

	/**
	 * Daily start offset from cron_hour (minutes).
	 *
	 * @var int
	 */
	const CRON_OFFSET_MINUTES = 50;

	/**
	 * Batch size.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 400;

	/**
	 * Max runtime per request.
	 *
	 * @var int
	 */
	const MAX_RUNTIME_SECONDS = 45;

	/**
	 * Lock key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'fanfic_lock_anonymize_old_data';

	/**
	 * State option key.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'fanfic_anonymize_old_data_state';

	/**
	 * Initialize cron cleanup
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		// Register cron hook
		add_action( 'fanfic_anonymize_old_data', array( __CLASS__, 'start_anonymization_run' ) );
		add_action( self::CONTINUATION_HOOK, array( __CLASS__, 'run_anonymization_batch' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		// Schedule cron on plugin activation (done in core class)
	}

	/**
	 * Schedule anonymization cron job
	 *
	 * Called during plugin activation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'fanfic_anonymize_old_data' ) ) {
			$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
			$next_run = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );
			wp_schedule_event( $next_run, 'daily', 'fanfic_anonymize_old_data' );
		}
	}

	/**
	 * Unschedule anonymization cron job
	 *
	 * Called during plugin deactivation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( 'fanfic_anonymize_old_data' );
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
	}

	/**
	 * Re-schedule cron when settings change
	 *
	 * @since 2.0.0
	 * @param array $old_value Previous settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public static function reschedule_on_settings_change( $old_value, $new_value ) {
		$old_hour = isset( $old_value['cron_hour'] ) ? absint( $old_value['cron_hour'] ) : 3;
		$new_hour = isset( $new_value['cron_hour'] ) ? absint( $new_value['cron_hour'] ) : 3;

		if ( $old_hour === $new_hour ) {
			return;
		}

		self::unschedule_cron();
		self::schedule_cron();
	}

	/**
	 * Calculate next run time based on cron hour setting.
	 *
	 * @since 2.0.0
	 * @param int $cron_hour Hour (0-23).
	 * @return int Timestamp for next run.
	 */
	private static function calculate_next_run_time( $cron_hour, $offset_minutes = 0 ) {
		$cron_hour = min( 23, max( 0, absint( $cron_hour ) ) );
		$offset_minutes = max( 0, absint( $offset_minutes ) );
		$current_time = current_time( 'timestamp' );
		$today = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $cron_hour ) );
		$scheduled_time = strtotime( '+' . $offset_minutes . ' minutes', $scheduled_time );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Anonymize old votes and likes
	 *
	 * Removes identifier_hash from votes and likes older than 30 days.
	 * Anonymous users (user_id = 0) only.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function anonymize_old_votes_and_likes() {
		return self::start_anonymization_run();
	}

	/**
	 * Start a new anonymization cycle.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function start_anonymization_run() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		update_option(
			self::STATE_OPTION,
			array(
				'phase'          => 'ratings',
				'last_rating_id' => 0,
				'last_like_id'   => 0,
			),
			false
		);

		return self::run_anonymization_batch();
	}

	/**
	 * Run one anonymization batch with continuation support.
	 *
	 * @since 2.0.0
	 * @return array{ratings:int,likes:int}
	 */
	public static function run_anonymization_batch() {
		global $wpdb;
		$result = array(
			'ratings' => 0,
			'likes'   => 0,
		);

		if ( ! self::acquire_lock() ) {
			return $result;
		}

		$state = get_option(
			self::STATE_OPTION,
			array(
				'phase'          => 'ratings',
				'last_rating_id' => 0,
				'last_like_id'   => 0,
			)
		);

		$phase = ( isset( $state['phase'] ) && 'likes' === $state['phase'] ) ? 'likes' : 'ratings';
		$last_rating_id = isset( $state['last_rating_id'] ) ? max( 0, absint( $state['last_rating_id'] ) ) : 0;
		$last_like_id = isset( $state['last_like_id'] ) ? max( 0, absint( $state['last_like_id'] ) ) : 0;
		$affected_rating_chapters = array();
		$affected_like_chapters = array();
		$start = microtime( true );
		$time_budget = self::get_time_budget_seconds();
		$has_more = false;

		do {
			if ( 'ratings' === $phase ) {
				$batch = self::get_old_rows_batch( 'ratings', $last_rating_id, self::BATCH_SIZE );
				if ( empty( $batch ) ) {
					$phase = 'likes';
					continue;
				}

				$ids = array();
				foreach ( $batch as $row ) {
					$ids[] = absint( $row['id'] );
					$last_rating_id = max( $last_rating_id, absint( $row['id'] ) );
					$chapter_id = absint( $row['chapter_id'] );
					if ( $chapter_id > 0 ) {
						$affected_rating_chapters[ $chapter_id ] = true;
					}
				}

				$updated = self::anonymize_rows_by_ids( 'ratings', $ids );
				$result['ratings'] += $updated;
				$has_more = count( $batch ) === self::BATCH_SIZE;
				if ( ! $has_more ) {
					$phase = 'likes';
				}
			} else {
				$batch = self::get_old_rows_batch( 'likes', $last_like_id, self::BATCH_SIZE );
				if ( empty( $batch ) ) {
					$has_more = false;
					break;
				}

				$ids = array();
				foreach ( $batch as $row ) {
					$ids[] = absint( $row['id'] );
					$last_like_id = max( $last_like_id, absint( $row['id'] ) );
					$chapter_id = absint( $row['chapter_id'] );
					if ( $chapter_id > 0 ) {
						$affected_like_chapters[ $chapter_id ] = true;
					}
				}

				$updated = self::anonymize_rows_by_ids( 'likes', $ids );
				$result['likes'] += $updated;
				$has_more = count( $batch ) === self::BATCH_SIZE;
			}
		} while ( $has_more && ( microtime( true ) - $start ) < $time_budget );

		self::invalidate_chapter_caches( array_keys( $affected_rating_chapters ), 'rating' );
		self::invalidate_chapter_caches( array_keys( $affected_like_chapters ), 'likes' );

		if ( $has_more || 'likes' !== $phase ) {
			update_option(
				self::STATE_OPTION,
				array(
					'phase'          => $phase,
					'last_rating_id' => $last_rating_id,
					'last_like_id'   => $last_like_id,
				),
				false
			);
			self::schedule_continuation();
		} else {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		}

		self::release_lock();

		if ( $result['ratings'] || $result['likes'] ) {
			error_log(
				sprintf(
					'Fanfic Cleanup: Anonymized %d ratings and %d likes (batch run).',
					$result['ratings'],
					$result['likes']
				)
			);
		}

		return $result;
	}

	/**
	 * Manually trigger anonymization (for testing/admin)
	 *
	 * @since 2.0.0
	 * @return array Results of anonymization.
	 */
	public static function manual_anonymize() {
		self::anonymize_old_votes_and_likes();

		return array(
			'success' => true,
			'message' => __( 'Anonymization completed successfully.', 'fanfiction-manager' ),
		);
	}

	/**
	 * Fetch one anonymization batch for table type.
	 *
	 * @since 2.0.0
	 * @param string $type `ratings` or `likes`.
	 * @param int    $after_id Last processed ID.
	 * @param int    $limit Batch size.
	 * @return array[] Rows with id and chapter_id.
	 */
	private static function get_old_rows_batch( $type, $after_id, $limit ) {
		global $wpdb;

		$table = 'ratings' === $type ? $wpdb->prefix . 'fanfic_ratings' : $wpdb->prefix . 'fanfic_likes';
		$after_id = max( 0, absint( $after_id ) );
		$limit = max( 1, absint( $limit ) );

		$sql = $wpdb->prepare(
			"SELECT id, chapter_id
			FROM {$table}
			WHERE id > %d
				AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND user_id = 0
				AND identifier_hash IS NOT NULL
			ORDER BY id ASC
			LIMIT %d",
			$after_id,
			self::ANONYMIZATION_DAYS,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Anonymize rows by IDs in one query.
	 *
	 * @since 2.0.0
	 * @param string $type `ratings` or `likes`.
	 * @param int[]  $ids IDs.
	 * @return int Updated rows.
	 */
	private static function anonymize_rows_by_ids( $type, $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table = 'ratings' === $type ? $wpdb->prefix . 'fanfic_ratings' : $wpdb->prefix . 'fanfic_likes';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "UPDATE {$table} SET identifier_hash = NULL WHERE id IN ({$placeholders})";

		$result = $wpdb->query( $wpdb->prepare( $sql, $ids ) );
		return $result ? absint( $result ) : 0;
	}

	/**
	 * Invalidate chapter and story cache keys for affected chapters.
	 *
	 * @since 2.0.0
	 * @param int[]  $chapter_ids Chapter IDs.
	 * @param string $suffix Cache suffix (`rating` or `likes`).
	 * @return void
	 */
	private static function invalidate_chapter_caches( $chapter_ids, $suffix ) {
		$chapter_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $chapter_ids ) ) ) );
		foreach ( $chapter_ids as $chapter_id ) {
			delete_transient( 'fanfic_chapter_' . $chapter_id . '_' . $suffix );

			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				delete_transient( 'fanfic_story_' . $story_id . '_' . $suffix );
			}
		}
	}

	/**
	 * Schedule continuation soon.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function schedule_continuation() {
		if ( ! wp_next_scheduled( self::CONTINUATION_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CONTINUATION_HOOK );
		}
	}

	/**
	 * Acquire run lock.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private static function acquire_lock() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::LOCK_KEY, 1, self::MAX_RUNTIME_SECONDS + 120 );
		return true;
	}

	/**
	 * Release run lock.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function release_lock() {
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Get safe worker budget based on server max_execution_time.
	 *
	 * @since 2.0.0
	 * @return int Seconds.
	 */
	private static function get_time_budget_seconds() {
		$budget = self::MAX_RUNTIME_SECONDS;
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 ) {
			$budget = max( 10, min( $budget, $max_exec - 5 ) );
		}
		return $budget;
	}
}
