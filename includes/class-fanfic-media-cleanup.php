<?php
/**
 * Media Cleanup Class
 *
 * Deletes image attachments that are not referenced by the uploader's
 * fanfiction stories, chapters, or avatar.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Media_Cleanup
 *
 * Schedules and runs media cleanup tasks.
 *
 * @since 2.0.0
 */
class Fanfic_Media_Cleanup {

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'fanfic_cleanup_orphaned_media';

	/**
	 * Continuation hook name.
	 *
	 * @var string
	 */
	const CONTINUATION_HOOK = 'fanfic_cleanup_orphaned_media_continue';

	/**
	 * Daily start offset from cron_hour (minutes).
	 *
	 * @var int
	 */
	const CRON_OFFSET_MINUTES = 20;

	/**
	 * Batch size per query.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 200;

	/**
	 * Max runtime per request.
	 *
	 * @var int
	 */
	const MAX_RUNTIME_SECONDS = 45;

	/**
	 * Lock transient key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'fanfic_lock_media_cleanup';

	/**
	 * State option key.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'fanfic_media_cleanup_state';

	/**
	 * Initialize media cleanup hooks
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'start_cleanup_run' ) );
		add_action( self::CONTINUATION_HOOK, array( __CLASS__, 'run_cleanup_batch' ) );
		add_action( 'fanfic_daily_maintenance', array( __CLASS__, 'start_cleanup_run' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_cron();
		}
	}

	/**
	 * Schedule daily cleanup
	 *
	 * @since 2.0.0
	 * @return bool True if scheduled, false otherwise.
	 */
	public static function schedule_cron() {
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
		$next_run = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );
		return wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
	}

	/**
	 * Unschedule daily cleanup
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
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
	 * Calculate next run time based on the cron hour setting.
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
	 * Cleanup orphaned image attachments for each user.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function cleanup_orphaned_media() {
		return self::start_cleanup_run();
	}

	/**
	 * Start a new cleanup cycle.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function start_cleanup_run() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		update_option(
			self::STATE_OPTION,
			array(
				'last_attachment_id' => 0,
			),
			false
		);

		return self::run_cleanup_batch();
	}

	/**
	 * Run one resumable cleanup worker batch.
	 *
	 * @since 2.0.0
	 * @return array{processed:int,deleted:int}
	 */
	public static function run_cleanup_batch() {
		$result = array(
			'processed' => 0,
			'deleted'   => 0,
		);

		if ( ! self::acquire_lock() ) {
			return $result;
		}

		$state = get_option( self::STATE_OPTION, array( 'last_attachment_id' => 0 ) );
		$last_attachment_id = isset( $state['last_attachment_id'] ) ? max( 0, absint( $state['last_attachment_id'] ) ) : 0;
		$start = microtime( true );
		$time_budget = self::get_time_budget_seconds();
		$has_more = false;
		$timed_out = false;

		do {
			$attachments = self::get_attachment_batch( $last_attachment_id, self::BATCH_SIZE );
			if ( empty( $attachments ) ) {
				$has_more = false;
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				if ( ! $attachment_id ) {
					continue;
				}

				$last_attachment_id = max( $last_attachment_id, $attachment_id );
				$result['processed']++;

				$author_id = (int) get_post_field( 'post_author', $attachment_id );
				if ( $author_id > 0 && ! self::is_attachment_referenced_by_user( $attachment_id, $author_id ) ) {
					if ( wp_delete_attachment( $attachment_id, true ) ) {
						$result['deleted']++;
					}
				}

				if ( microtime( true ) - $start >= $time_budget ) {
					$timed_out = true;
					break;
				}
			}

			if ( $timed_out ) {
				$has_more = true;
				break;
			}

			$has_more = count( $attachments ) === self::BATCH_SIZE;
		} while ( $has_more );

		if ( $has_more ) {
			update_option( self::STATE_OPTION, array( 'last_attachment_id' => $last_attachment_id ), false );
			self::schedule_continuation();
		} else {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		}

		self::release_lock();

		if ( $result['deleted'] > 0 ) {
			error_log( sprintf( 'Fanfic Cleanup: Deleted %d unreferenced image attachments.', $result['deleted'] ) );
		}

		return $result;
	}

	/**
	 * Get one attachment batch by ascending ID.
	 *
	 * @since 2.0.0
	 * @param int $after_id Last processed attachment ID.
	 * @param int $limit Batch size.
	 * @return int[] Attachment IDs.
	 */
	private static function get_attachment_batch( $after_id, $limit ) {
		global $wpdb;

		$after_id = max( 0, absint( $after_id ) );
		$limit = max( 1, absint( $limit ) );

		$sql = $wpdb->prepare(
			"SELECT ID
			FROM {$wpdb->posts}
			WHERE ID > %d
				AND post_type = %s
				AND post_status = %s
				AND post_mime_type LIKE %s
			ORDER BY ID ASC
			LIMIT %d",
			$after_id,
			'attachment',
			'inherit',
			'image/%',
			$limit
		);

		$rows = $wpdb->get_col( $sql );
		return is_array( $rows ) ? array_map( 'absint', $rows ) : array();
	}

	/**
	 * Check if an attachment is referenced by the uploader's fanfic data.
	 *
	 * @since 2.0.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id User ID.
	 * @return bool True if referenced.
	 */
	private static function is_attachment_referenced_by_user( $attachment_id, $user_id ) {
		global $wpdb;

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( empty( $attachment_url ) ) {
			return false;
		}

		$story_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_fanfic_featured_image'
				AND pm.meta_value = %s
				AND p.post_type = 'fanfiction_story'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_url,
				$user_id
			)
		);
		if ( $story_url_match ) {
			return true;
		}

		$story_thumbnail_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d
				AND p.post_type = 'fanfiction_story'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_id,
				$user_id
			)
		);
		if ( $story_thumbnail_match ) {
			return true;
		}

		$chapter_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_fanfic_chapter_image_url'
				AND pm.meta_value = %s
				AND p.post_type = 'fanfiction_chapter'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_url,
				$user_id
			)
		);
		if ( $chapter_url_match ) {
			return true;
		}

		$avatar_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT umeta_id
				FROM {$wpdb->usermeta}
				WHERE user_id = %d
				AND meta_key = '_fanfic_avatar_url'
				AND meta_value = %s
				LIMIT 1",
				$user_id,
				$attachment_url
			)
		);

		return ! empty( $avatar_url_match );
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
