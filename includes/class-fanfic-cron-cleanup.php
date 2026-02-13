<?php
/**
 * Cron Cleanup Class
 *
 * Keeps the interactions table light by trimming anonymous interaction rows when
 * they exceed a hard cap. Aggregate counters are intentionally untouched.
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
 * @since 2.0.0
 */
class Fanfic_Cron_Cleanup {

	/**
	 * Daily cleanup hook.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'fanfic_anonymize_old_data';

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
	 * Hard cap for anonymous interaction rows.
	 *
	 * @var int
	 */
	const ANON_ROWS_CAP = 150000;

	/**
	 * Target size after cleanup.
	 *
	 * @var int
	 */
	const ANON_ROWS_TARGET = 100000;

	/**
	 * Rows to delete per SQL batch.
	 *
	 * @var int
	 */
	const DELETE_BATCH_SIZE = 1000;

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
	const LOCK_KEY = 'fanfic_lock_interactions_trim';

	/**
	 * State option key.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'fanfic_interactions_trim_state';

	/**
	 * Initialize cleanup hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'start_anonymization_run' ) );
		add_action( self::CONTINUATION_HOOK, array( __CLASS__, 'run_anonymization_batch' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );
	}

	/**
	 * Schedule daily cleanup cron.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
			$next_run  = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );
			wp_schedule_event( $next_run, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule cleanup hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
	}

	/**
	 * Re-schedule cron when settings change.
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
	 * Compatibility alias.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function anonymize_old_votes_and_likes() {
		return self::start_anonymization_run();
	}

	/**
	 * Start a new cleanup cycle.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function start_anonymization_run() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		$current_count = self::count_anonymous_interactions();
		if ( $current_count <= self::ANON_ROWS_CAP ) {
			delete_option( self::STATE_OPTION );
			return array(
				'deleted'   => 0,
				'remaining' => $current_count,
				'max'       => self::ANON_ROWS_CAP,
				'target'    => self::ANON_ROWS_TARGET,
				'scheduled' => 0,
			);
		}

		$rows_to_delete = max( 0, $current_count - self::ANON_ROWS_TARGET );
		$batches_needed = (int) ceil( $rows_to_delete / self::DELETE_BATCH_SIZE );

		for ( $i = 1; $i <= $batches_needed; $i++ ) {
			wp_schedule_single_event( time() + ( $i * MINUTE_IN_SECONDS ), self::CONTINUATION_HOOK );
		}

		update_option(
			self::STATE_OPTION,
			array(
				'started_at'      => current_time( 'mysql' ),
				'remaining'       => $current_count,
				'scheduled_jobs'  => $batches_needed,
			),
			false
		);

		return array(
			'deleted'   => 0,
			'remaining' => $current_count,
			'max'       => self::ANON_ROWS_CAP,
			'target'    => self::ANON_ROWS_TARGET,
			'scheduled' => $batches_needed,
		);
	}

	/**
	 * Run one cleanup worker batch.
	 *
	 * Deletes oldest anonymous interaction rows in chunks of 1000 rows until
	 * table is trimmed down to the target size, using continuation events.
	 *
	 * @since 2.0.0
	 * @return array{deleted:int,remaining:int,max:int,target:int}
	 */
	public static function run_anonymization_batch() {
		$result = array(
			'deleted'   => 0,
			'remaining' => 0,
			'max'       => self::ANON_ROWS_CAP,
			'target'    => self::ANON_ROWS_TARGET,
		);

		if ( ! self::acquire_lock() ) {
			return $result;
		}

		$current_count = self::count_anonymous_interactions();
		$result['remaining'] = $current_count;

		if ( $current_count <= self::ANON_ROWS_TARGET ) {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
			self::release_lock();
			return $result;
		}

		$deleted = self::delete_oldest_anonymous_rows( self::DELETE_BATCH_SIZE );
		$result['deleted'] = $deleted;
		$current_count = self::count_anonymous_interactions();
		$result['remaining'] = $current_count;

		if ( $current_count <= self::ANON_ROWS_TARGET ) {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		} else {
			$state = get_option( self::STATE_OPTION, array() );
			$scheduled_jobs = max( 0, absint( $state['scheduled_jobs'] ?? 0 ) - 1 );
			update_option(
				self::STATE_OPTION,
				array(
					'remaining'      => $current_count,
					'updated_at'     => current_time( 'mysql' ),
					'scheduled_jobs' => $scheduled_jobs,
				),
				false
			);
			if ( 0 === $scheduled_jobs ) {
				self::schedule_continuation();
			}
		}

		self::release_lock();

		if ( $result['deleted'] > 0 ) {
			error_log(
				sprintf(
					'Fanfic Cleanup: Deleted %d anonymous interaction rows; remaining=%d.',
					$result['deleted'],
					$result['remaining']
				)
			);
		}

		return $result;
	}

	/**
	 * Manual cleanup trigger.
	 *
	 * @since 2.0.0
	 * @return array<string,mixed>
	 */
	public static function manual_anonymize() {
		$result = self::start_anonymization_run();
		return array(
			'success' => true,
			'message' => __( 'Interaction cleanup executed.', 'fanfiction-manager' ),
			'result'  => $result,
		);
	}

	/**
	 * Count anonymous interaction rows.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	private static function count_anonymous_interactions() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE user_id IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return absint( $count );
	}

	/**
	 * Delete oldest anonymous rows.
	 *
	 * Uses direct SQL with LIMIT to avoid loading IDs into memory.
	 *
	 * @since 2.0.0
	 * @param int $limit Batch size.
	 * @return int
	 */
	private static function delete_oldest_anonymous_rows( $limit ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$table = $wpdb->prefix . 'fanfic_interactions';

		$sql = $wpdb->prepare(
			"DELETE FROM {$table}
			WHERE id IN (
				SELECT id FROM (
					SELECT id
					FROM {$table}
					WHERE user_id IS NULL
					ORDER BY updated_at ASC, id ASC
					LIMIT %d
				) AS trim_batch
			)",
			$limit
		);

		$deleted = $wpdb->query( $sql );
		return $deleted ? absint( $deleted ) : 0;
	}

	/**
	 * Calculate next run time based on configured cron hour.
	 *
	 * @since 2.0.0
	 * @param int $cron_hour Hour (0-23).
	 * @param int $offset_minutes Offset in minutes.
	 * @return int
	 */
	private static function calculate_next_run_time( $cron_hour, $offset_minutes = 0 ) {
		$cron_hour      = min( 23, max( 0, absint( $cron_hour ) ) );
		$offset_minutes = max( 0, absint( $offset_minutes ) );
		$current_time   = current_time( 'timestamp' );
		$today          = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $cron_hour ) );
		$scheduled_time = strtotime( '+' . $offset_minutes . ' minutes', $scheduled_time );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Schedule continuation worker.
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

}
