<?php
/**
 * Story Status Automation Class
 *
 * Automatically transitions story status by inactivity windows:
 * - ongoing -> on-hiatus after 4 months without significant content updates
 * - on-hiatus -> abandoned after 10 months without significant content updates
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Story_Status_Automation
 *
 * @since 2.0.0
 */
class Fanfic_Story_Status_Automation {

	/**
	 * Scheduled hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'fanfic_auto_transition_story_statuses';

	/**
	 * Continuation hook name.
	 *
	 * @var string
	 */
	const CONTINUATION_HOOK = 'fanfic_auto_transition_story_statuses_continue';

	/**
	 * Max stories to process per batch query.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 200;

	/**
	 * Max runtime in seconds for one cron execution.
	 *
	 * @var int
	 */
	const MAX_RUNTIME_SECONDS = 45;

	/**
	 * Daily start offset in minutes from cron_hour.
	 *
	 * @var int
	 */
	const CRON_OFFSET_MINUTES = 10;

	/**
	 * Lock key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'fanfic_lock_story_status_automation';

	/**
	 * State option key.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'fanfic_story_status_automation_state';

	/**
	 * Inactivity threshold for ongoing -> on-hiatus.
	 *
	 * @var int
	 */
	const ONGOING_TO_HIATUS_MONTHS = 4;

	/**
	 * Inactivity threshold for on-hiatus -> abandoned.
	 *
	 * @var int
	 */
	const HIATUS_TO_ABANDONED_MONTHS = 10;

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'start_daily_run' ) );
		add_action( self::CONTINUATION_HOOK, array( __CLASS__, 'run_automation_batch' ) );
		add_action( 'fanfic_daily_maintenance', array( __CLASS__, 'start_daily_run' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		// Keep the search-index facet_value in sync when a status slug is renamed.
		add_action( 'edit_terms', array( __CLASS__, 'capture_status_slug_before_edit' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'sync_facet_value_after_slug_change' ), 10, 3 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_cron();
		}
	}

	/**
	 * Schedule daily automation at configured cron hour.
	 *
	 * @since 2.0.0
	 * @return bool True if scheduled, false otherwise.
	 */
	public static function schedule_cron() {
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
		$next_run  = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );
		return wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
	}

	/**
	 * Unschedule automation cron.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
	}

	/**
	 * Re-schedule when cron hour changes.
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
	 * Calculate next run timestamp for a target hour.
	 *
	 * @since 2.0.0
	 * @param int $hour Hour (0-23).
	 * @return int Timestamp.
	 */
	private static function calculate_next_run_time( $hour, $offset_minutes = 0 ) {
		$hour = min( 23, max( 0, absint( $hour ) ) );
		$offset_minutes = max( 0, absint( $offset_minutes ) );

		$current_time   = current_time( 'timestamp' );
		$today          = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $hour ) );
		$scheduled_time = strtotime( '+' . $offset_minutes . ' minutes', $scheduled_time );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Run status transition automation.
	 *
	 * Uses precomputed search tables to avoid per-story taxonomy/meta query fan-out.
	 *
	 * @since 2.0.0
	 * @return array{
	 *   scanned:int,
	 *   transitioned:int,
	 *   to_hiatus:int,
	 *   to_abandoned:int,
	 *   errors:int
	 * }
	 */
	public static function run_automation() {
		return self::start_daily_run();
	}

	/**
	 * Start a new daily automation cycle.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function start_daily_run() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		update_option(
			self::STATE_OPTION,
			array(
				'last_story_id' => 0,
			),
			false
		);

		return self::run_automation_batch();
	}

	/**
	 * Process automation in resumable batches.
	 *
	 * @since 2.0.0
	 * @return array{
	 *   scanned:int,
	 *   transitioned:int,
	 *   to_hiatus:int,
	 *   to_abandoned:int,
	 *   errors:int
	 * }
	 */
	public static function run_automation_batch() {
		$result = array(
			'scanned'      => 0,
			'transitioned' => 0,
			'to_hiatus'    => 0,
			'to_abandoned' => 0,
			'errors'       => 0,
		);

		if ( ! self::acquire_lock() ) {
			return $result;
		}

		if ( ! taxonomy_exists( 'fanfiction_status' ) || ! self::tables_ready() ) {
			self::release_lock();
			return $result;
		}

		$term_ids = self::get_transition_term_ids();
		if ( 0 === $term_ids['on-hiatus'] || 0 === $term_ids['abandoned'] ) {
			self::release_lock();
			return $result;
		}

		$state         = get_option( self::STATE_OPTION, array( 'last_story_id' => 0 ) );
		$last_story_id = isset( $state['last_story_id'] ) ? max( 0, absint( $state['last_story_id'] ) ) : 0;
		$start_time    = microtime( true );
		$time_budget   = self::get_time_budget_seconds();
		$has_more      = false;

		do {
			$candidates = self::get_transition_candidates( $last_story_id, self::BATCH_SIZE );
			if ( empty( $candidates ) ) {
				$has_more = false;
				break;
			}

			foreach ( $candidates as $candidate ) {
				$story_id = absint( $candidate->story_id );
				if ( ! $story_id ) {
					continue;
				}

				$last_story_id = max( $last_story_id, $story_id );
				$result['scanned']++;

				$target_status = sanitize_title( (string) $candidate->target_status );
				if ( '' === $target_status || empty( $term_ids[ $target_status ] ) ) {
					$result['errors']++;
					continue;
				}

				$set_result = wp_set_post_terms(
					$story_id,
					array( $term_ids[ $target_status ] ),
					'fanfiction_status',
					false
				);

				if ( is_wp_error( $set_result ) || false === $set_result ) {
					$result['errors']++;
					continue;
				}

				$result['transitioned']++;
				if ( 'on-hiatus' === $target_status ) {
					$result['to_hiatus']++;
				} elseif ( 'abandoned' === $target_status ) {
					$result['to_abandoned']++;
				}

				if ( class_exists( 'Fanfic_Search_Index' ) ) {
					Fanfic_Search_Index::update_index( $story_id );
				}
			}
			$has_more = count( $candidates ) === self::BATCH_SIZE;
		} while ( $has_more && ( microtime( true ) - $start_time ) < $time_budget );

		if ( $has_more ) {
			update_option( self::STATE_OPTION, array( 'last_story_id' => $last_story_id ), false );
			self::schedule_continuation();
		} else {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		}

		self::release_lock();

		return $result;
	}

	/**
	 * Check required tables.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private static function tables_ready() {
		global $wpdb;
		$index_table = $wpdb->prefix . 'fanfic_story_search_index';
		$map_table   = $wpdb->prefix . 'fanfic_story_filter_map';

		$index_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table ) );
		if ( $index_exists !== $index_table ) {
			return false;
		}

		$map_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $map_table ) );
		return $map_exists === $map_table;
	}

	/**
	 * Resolve canonical status term IDs used as transition targets.
	 *
	 * @since 2.0.0
	 * @return array{on-hiatus:int,abandoned:int}
	 */
	private static function get_transition_term_ids() {
		$map = get_option( 'fanfic_default_status_term_ids', array() );
		$map = is_array( $map ) ? array_map( 'absint', $map ) : array();

		return array(
			'on-hiatus' => isset( $map['on-hiatus'] ) ? $map['on-hiatus'] : 0,
			'abandoned' => isset( $map['abandoned'] ) ? $map['abandoned'] : 0,
		);
	}

	/**
	 * Resolve the current slugs for the three transition statuses from the ID map.
	 *
	 * @since 2.1.0
	 * @return array{ongoing:string,on-hiatus:string,abandoned:string}
	 */
	private static function get_transition_slugs() {
		$map  = get_option( 'fanfic_default_status_term_ids', array() );
		$map  = is_array( $map ) ? array_map( 'absint', $map ) : array();
		$keys = array( 'ongoing', 'on-hiatus', 'abandoned' );
		$out  = array();

		foreach ( $keys as $canonical ) {
			$slug = $canonical; // fallback to canonical if term missing
			if ( ! empty( $map[ $canonical ] ) ) {
				$term = get_term( $map[ $canonical ], 'fanfiction_status' );
				if ( $term && ! is_wp_error( $term ) ) {
					$slug = $term->slug;
				}
			}
			$out[ $canonical ] = $slug;
		}

		return $out;
	}

	/**
	 * Fetch stories that currently meet transition criteria.
	 *
	 * @since 2.0.0
	 * @param int $after_story_id Only fetch stories with ID greater than this.
	 * @param int $limit Batch size.
	 * @return object[] Candidate rows.
	 */
	private static function get_transition_candidates( $after_story_id, $limit ) {
		global $wpdb;

		$after_story_id = absint( $after_story_id );
		$limit          = max( 1, absint( $limit ) );

		$cutoff_hiatus    = date_i18n( 'Y-m-d H:i:s', self::get_hiatus_cutoff_timestamp() );
		$cutoff_abandoned = date_i18n( 'Y-m-d H:i:s', self::get_abandoned_cutoff_timestamp() );

		$table_index = $wpdb->prefix . 'fanfic_story_search_index';
		$table_map   = $wpdb->prefix . 'fanfic_story_filter_map';

		// Resolve current slugs from the ID map so renamed slugs still work.
		$slugs = self::get_transition_slugs();

		$sql = $wpdb->prepare(
			"SELECT i.story_id,
			        f.facet_value AS current_status,
			        CASE
			            WHEN f.facet_value = %s THEN 'on-hiatus'
			            WHEN f.facet_value = %s THEN 'abandoned'
			            ELSE ''
			        END AS target_status
			FROM {$table_index} i
			INNER JOIN {$table_map} f
				ON f.story_id = i.story_id
				AND f.facet_type = 'status'
			WHERE i.story_id > %d
				AND i.story_status = %s
				AND (
					( f.facet_value = %s AND i.updated_date <= %s )
					OR
					( f.facet_value = %s AND i.updated_date <= %s )
				)
			ORDER BY i.story_id ASC
			LIMIT %d",
			$slugs['ongoing'],
			$slugs['on-hiatus'],
			$after_story_id,
			'publish',
			$slugs['ongoing'],
			$cutoff_hiatus,
			$slugs['on-hiatus'],
			$cutoff_abandoned,
			$limit
		);

		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get local-site cutoff datetime string for "N months ago".
	 *
	 * @since 2.0.0
	 * @param int $months Number of months.
	 * @return string Datetime in `Y-m-d H:i:s`.
	 */
	private static function get_cutoff_mysql_datetime( $months ) {
		$months = max( 1, absint( $months ) );
		$now    = current_time( 'timestamp' );
		$cutoff = strtotime( sprintf( '-%d months', $months ), $now );
		return date_i18n( 'Y-m-d H:i:s', $cutoff );
	}

	/**
	 * Compute a Unix timestamp for "N units ago" using the configured value and unit.
	 *
	 * @since 2.2.0
	 * @param int    $value Positive integer.
	 * @param string $unit  'days', 'weeks', or 'months'.
	 * @return int Unix timestamp.
	 */
	private static function get_cutoff_timestamp( $value, $unit ) {
		$value = max( 1, absint( $value ) );
		$unit  = in_array( $unit, array( 'days', 'weeks', 'months' ), true ) ? $unit : 'months';
		return strtotime( sprintf( '-%d %s', $value, $unit ), current_time( 'timestamp' ) );
	}

	/**
	 * Get the cutoff timestamp for the ongoing → on-hiatus transition.
	 *
	 * Reads from settings, falls back to ONGOING_TO_HIATUS_MONTHS constant.
	 *
	 * @since 2.2.0
	 * @return int Unix timestamp.
	 */
	public static function get_hiatus_cutoff_timestamp() {
		$value = (int) Fanfic_Settings::get_setting( 'hiatus_threshold_value', self::ONGOING_TO_HIATUS_MONTHS );
		$unit  = (string) Fanfic_Settings::get_setting( 'hiatus_threshold_unit', 'months' );
		return self::get_cutoff_timestamp( $value, $unit );
	}

	/**
	 * Get the cutoff timestamp for the on-hiatus → abandoned transition.
	 *
	 * Reads from settings, falls back to HIATUS_TO_ABANDONED_MONTHS constant.
	 *
	 * @since 2.2.0
	 * @return int Unix timestamp.
	 */
	public static function get_abandoned_cutoff_timestamp() {
		$value = (int) Fanfic_Settings::get_setting( 'abandoned_threshold_value', self::HIATUS_TO_ABANDONED_MONTHS );
		$unit  = (string) Fanfic_Settings::get_setting( 'abandoned_threshold_unit', 'months' );
		return self::get_cutoff_timestamp( $value, $unit );
	}

	/**
	 * Schedule continuation soon for background processing.
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
	 * Acquire lock.
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
	 * Release lock.
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

	/**
	 * Capture the current slug of a required status term before it is edited.
	 *
	 * Stores it in a transient so that sync_facet_value_after_slug_change()
	 * can detect and propagate the change to the search-index filter map.
	 *
	 * @param int    $term_id  Term ID being edited.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function capture_status_slug_before_edit( $term_id, $taxonomy ) {
		if ( 'fanfiction_status' !== $taxonomy ) {
			return;
		}

		$map     = get_option( 'fanfic_default_status_term_ids', array() );
		$map     = is_array( $map ) ? array_map( 'absint', $map ) : array();
		if ( ! in_array( absint( $term_id ), $map, true ) ) {
			return;
		}

		$term = get_term( $term_id, 'fanfiction_status' );
		if ( $term && ! is_wp_error( $term ) ) {
			set_transient( 'fanfic_old_status_slug_' . $term_id, $term->slug, 120 );
		}
	}

	/**
	 * After a status term is saved, update facet_value rows in the filter map
	 * if the slug changed so the automation SQL still finds the correct stories.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID (unused).
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function sync_facet_value_after_slug_change( $term_id, $tt_id, $taxonomy ) {
		if ( 'fanfiction_status' !== $taxonomy ) {
			return;
		}

		$old_slug = get_transient( 'fanfic_old_status_slug_' . $term_id );
		delete_transient( 'fanfic_old_status_slug_' . $term_id );

		if ( ! $old_slug ) {
			return;
		}

		$term = get_term( $term_id, 'fanfiction_status' );
		if ( ! $term || is_wp_error( $term ) || $term->slug === $old_slug ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'fanfic_story_filter_map',
			array( 'facet_value' => $term->slug ),
			array( 'facet_type' => 'status', 'facet_value' => $old_slug ),
			array( '%s' ),
			array( '%s', '%s' )
		);
	}
}
