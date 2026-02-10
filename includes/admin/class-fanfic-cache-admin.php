<?php
/**
 * Cache Management Admin Interface
 *
 * Provides admin interface for cache management including:
 * - Clear all caches
 * - Clean up expired transients
 * - Cache statistics display
 * - Manual and scheduled cleanup
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Cache_Admin
 *
 * Manages cache-related admin functionality and UI.
 *
 * @since 1.0.0
 */
class Fanfic_Cache_Admin {

	/**
	 * Continuation hook name for cache cleanup.
	 *
	 * @var string
	 */
	const CLEANUP_CONTINUATION_HOOK = 'fanfic_cleanup_transients_continue';

	/**
	 * Daily start offset from cron_hour (minutes).
	 *
	 * @var int
	 */
	const CLEANUP_CRON_OFFSET_MINUTES = 30;

	/**
	 * Batch size for one cleanup query.
	 *
	 * @var int
	 */
	const CLEANUP_BATCH_SIZE = 500;

	/**
	 * Worker time limit.
	 *
	 * @var int
	 */
	const CLEANUP_MAX_RUNTIME_SECONDS = 45;

	/**
	 * Lock key.
	 *
	 * @var string
	 */
	const CLEANUP_LOCK_KEY = 'fanfic_lock_cleanup_transients';

	/**
	 * Initialize the cache admin class
	 *
	 * Sets up WordPress hooks for cache management.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// AJAX handlers
		add_action( 'wp_ajax_fanfic_clear_all_cache', array( __CLASS__, 'ajax_clear_all_cache' ) );
		add_action( 'wp_ajax_fanfic_cleanup_expired', array( __CLASS__, 'ajax_cleanup_expired' ) );
		add_action( 'wp_ajax_fanfic_get_cache_stats', array( __CLASS__, 'ajax_get_cache_stats' ) );

		// Admin post handlers
		add_action( 'admin_post_fanfic_run_cache_cleanup', array( __CLASS__, 'run_cache_cleanup_now' ) );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// Register cron event
		add_action( 'fanfic_cleanup_transients', array( __CLASS__, 'cron_cleanup_expired' ) );
		add_action( self::CLEANUP_CONTINUATION_HOOK, array( __CLASS__, 'cron_cleanup_expired' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		// Schedule cron on plugin activation
		if ( ! wp_next_scheduled( 'fanfic_cleanup_transients' ) ) {
			self::schedule_cleanup();
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		// Only load on settings page
		if ( 'toplevel_page_fanfiction-settings' !== $hook && 'fanfiction_page_fanfiction-settings' !== $hook ) {
			return;
		}

		// Enqueue jQuery if not already loaded
		wp_enqueue_script( 'jquery' );

		// Add inline script for cache management
		wp_add_inline_script(
			'jquery',
			self::get_inline_script(),
			'after'
		);

		// Add inline styles
		wp_add_inline_style(
			'wp-admin',
			self::get_inline_styles()
		);
	}

	/**
	 * Get inline JavaScript for cache management
	 *
	 * @since 1.0.0
	 * @return string JavaScript code
	 */
	private static function get_inline_script() {
		$nonce = wp_create_nonce( 'fanfic_cache_nonce' );

		return "
		jQuery(document).ready(function($) {
			// Clear All Cache button
			$('#fanfic-clear-all-cache').on('click', function(e) {
				e.preventDefault();
				var \$button = $(this);

				if (!confirm('" . esc_js( __( 'Are you sure you want to clear all caches? This will temporarily slow down the site until caches are rebuilt.', 'fanfiction-manager' ) ) . "')) {
					return;
				}

				\$button.prop('disabled', true).text('" . esc_js( __( 'Clearing...', 'fanfiction-manager' ) ) . "');
				$('#fanfic-cache-message').remove();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_clear_all_cache',
						nonce: '" . esc_js( $nonce ) . "'
					},
					success: function(response) {
						if (response.success) {
							var message = '<div id=\"fanfic-cache-message\" class=\"notice notice-success is-dismissible\"><p>' + response.data.message + '</p></div>';
							$('#fanfic-cache-management').before(message);
							// Refresh stats
							fanficRefreshCacheStats();
						} else {
							var message = '<div id=\"fanfic-cache-message\" class=\"notice error-message is-dismissible\"><p>' + (response.data.message || '" . esc_js( __( 'Failed to clear cache.', 'fanfiction-manager' ) ) . "') + '</p></div>';
							$('#fanfic-cache-management').before(message);
						}
					},
					error: function() {
						var message = '<div id=\"fanfic-cache-message\" class=\"notice error-message is-dismissible\"><p>" . esc_js( __( 'Request failed.', 'fanfiction-manager' ) ) . "</p></div>';
						$('#fanfic-cache-management').before(message);
					},
					complete: function() {
						\$button.prop('disabled', false).text('" . esc_js( __( 'Clear All Caches', 'fanfiction-manager' ) ) . "');
					}
				});
			});

			// Cleanup Expired button
			$('#fanfic-cleanup-expired').on('click', function(e) {
				e.preventDefault();
				var \$button = $(this);

				\$button.prop('disabled', true).text('" . esc_js( __( 'Cleaning...', 'fanfiction-manager' ) ) . "');
				$('#fanfic-cache-message').remove();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_cleanup_expired',
						nonce: '" . esc_js( $nonce ) . "'
					},
					success: function(response) {
						if (response.success) {
							var message = '<div id=\"fanfic-cache-message\" class=\"notice notice-success is-dismissible\"><p>' + response.data.message + '</p></div>';
							$('#fanfic-cache-management').before(message);
							// Refresh stats
							fanficRefreshCacheStats();
						} else {
							var message = '<div id=\"fanfic-cache-message\" class=\"notice error-message is-dismissible\"><p>' + (response.data.message || '" . esc_js( __( 'Failed to cleanup.', 'fanfiction-manager' ) ) . "') + '</p></div>';
							$('#fanfic-cache-management').before(message);
						}
					},
					error: function() {
						var message = '<div id=\"fanfic-cache-message\" class=\"notice error-message is-dismissible\"><p>" . esc_js( __( 'Request failed.', 'fanfiction-manager' ) ) . "</p></div>';
						$('#fanfic-cache-management').before(message);
					},
					complete: function() {
						\$button.prop('disabled', false).text('" . esc_js( __( 'Clean Up Expired', 'fanfiction-manager' ) ) . "');
					}
				});
			});

			// Refresh Stats button
			$('#fanfic-refresh-stats').on('click', function(e) {
				e.preventDefault();
				fanficRefreshCacheStats();
			});

			// Function to refresh cache stats
			window.fanficRefreshCacheStats = function() {
				var \$statsBox = $('#fanfic-cache-stats-content');
				\$statsBox.html('<p>" . esc_js( __( 'Loading...', 'fanfiction-manager' ) ) . "</p>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_get_cache_stats',
						nonce: '" . esc_js( $nonce ) . "'
					},
					success: function(response) {
						if (response.success) {
							\$statsBox.html(response.data.html);
						} else {
							\$statsBox.html('<p>" . esc_js( __( 'Failed to load statistics.', 'fanfiction-manager' ) ) . "</p>');
						}
					},
					error: function() {
						\$statsBox.html('<p>" . esc_js( __( 'Request failed.', 'fanfiction-manager' ) ) . "</p>');
					}
				});
			};
		});
		";
	}

	/**
	 * Get inline CSS styles
	 *
	 * @since 1.0.0
	 * @return string CSS code
	 */
	private static function get_inline_styles() {
		return "
		.fanfic-cache-stat-row {
			display: flex;
			justify-content: space-between;
			padding: 10px 0;
			border-bottom: 1px solid #e0e0e0;
		}
		.fanfic-cache-stat-row:last-child {
			border-bottom: none;
		}
		.fanfic-cache-stat-label {
			font-weight: 600;
			color: #23282d;
		}
		.fanfic-cache-stat-value {
			color: #555;
		}
		#fanfic-cache-management .button {
			margin-right: 10px;
		}
		.fanfic-cache-info-box {
			background: #f0f6fc;
			border-left: 4px solid #0073aa;
			padding: 15px;
			margin: 15px 0;
		}
		.fanfic-cache-info-box p {
			margin: 0.5em 0;
		}
		";
	}

	/**
	 * Render cache management section
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_cache_management() {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		?>
		<div id="fanfic-cache-management" class="fanfiction-settings-section">
			<h3><?php esc_html_e( 'Cache Management', 'fanfiction-manager' ); ?></h3>

			<div class="fanfic-cache-info-box">
				<p><strong><?php esc_html_e( 'About Caching:', 'fanfiction-manager' ); ?></strong></p>
				<p><?php esc_html_e( 'The Fanfiction Manager uses caching to improve performance by storing frequently accessed data temporarily. Clearing caches may temporarily slow down your site until data is cached again.', 'fanfiction-manager' ); ?></p>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- Cache Statistics -->
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Cache Statistics', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<div id="fanfic-cache-stats-content" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">
								<?php echo self::render_cache_stats(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<p class="description">
								<button type="button" id="fanfic-refresh-stats" class="button button-secondary" style="margin-top: 10px;">
									<?php esc_html_e( 'Refresh Statistics', 'fanfiction-manager' ); ?>
								</button>
							</p>
						</td>
					</tr>

					<!-- Clear All Caches -->
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Clear All Caches', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<button type="button" id="fanfic-clear-all-cache" class="button button-secondary">
								<?php esc_html_e( 'Clear All Caches', 'fanfiction-manager' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Removes all cached data created by the Fanfiction Manager plugin. Use this if you experience caching issues or after making major changes.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>

					<!-- Clean Up Expired -->
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Clean Up Expired Transients', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<button type="button" id="fanfic-cleanup-expired" class="button button-secondary">
								<?php esc_html_e( 'Clean Up Expired', 'fanfiction-manager' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Removes expired cached data from the database to reduce database size. This runs automatically daily but can be triggered manually.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>

					<!-- Scheduled Cleanup -->
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Scheduled Cleanup', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<?php
							$next_scheduled = wp_next_scheduled( 'fanfic_cleanup_transients' );
							if ( $next_scheduled ) {
								/* translators: %s: formatted date/time */
								printf(
									esc_html__( 'Next automatic cleanup: %s', 'fanfiction-manager' ),
									'<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_scheduled ) ) . '</strong>'
								);
							} else {
								esc_html_e( 'No cleanup scheduled.', 'fanfiction-manager' );
							}
							?>
							<p class="description">
								<?php esc_html_e( 'Automatic cleanup runs daily based on the Daily Cron Hour setting in General Settings.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render cache statistics
	 *
	 * @since 1.0.0
	 * @return string HTML content
	 */
	private static function render_cache_stats() {
		$stats = Fanfic_Cache::get_stats();

		$html = '';

		// Total Transients
		$html .= '<div class="fanfic-cache-stat-row">';
		$html .= '<span class="fanfic-cache-stat-label">' . esc_html__( 'Total Cached Items:', 'fanfiction-manager' ) . '</span>';
		$html .= '<span class="fanfic-cache-stat-value">' . esc_html( number_format_i18n( $stats['total_transients'] ) ) . '</span>';
		$html .= '</div>';

		// Expired Transients
		$html .= '<div class="fanfic-cache-stat-row">';
		$html .= '<span class="fanfic-cache-stat-label">' . esc_html__( 'Expired Items:', 'fanfiction-manager' ) . '</span>';
		$html .= '<span class="fanfic-cache-stat-value">' . esc_html( number_format_i18n( $stats['expired_transients'] ) ) . '</span>';
		$html .= '</div>';

		// Active Transients
		$active = $stats['total_transients'] - $stats['expired_transients'];
		$html .= '<div class="fanfic-cache-stat-row">';
		$html .= '<span class="fanfic-cache-stat-label">' . esc_html__( 'Active Items:', 'fanfiction-manager' ) . '</span>';
		$html .= '<span class="fanfic-cache-stat-value">' . esc_html( number_format_i18n( $active ) ) . '</span>';
		$html .= '</div>';

		// Object Cache Status
		$html .= '<div class="fanfic-cache-stat-row">';
		$html .= '<span class="fanfic-cache-stat-label">' . esc_html__( 'Object Cache:', 'fanfiction-manager' ) . '</span>';
		$html .= '<span class="fanfic-cache-stat-value">';
		if ( $stats['object_cache'] ) {
			$html .= '<span style="color: #46b450;">' . esc_html__( 'Active (Redis/Memcached)', 'fanfiction-manager' ) . '</span>';
		} else {
			$html .= '<span style="color: #999;">' . esc_html__( 'Not Active (Database Only)', 'fanfiction-manager' ) . '</span>';
		}
		$html .= '</span>';
		$html .= '</div>';

		// Cache Version
		$html .= '<div class="fanfic-cache-stat-row">';
		$html .= '<span class="fanfic-cache-stat-label">' . esc_html__( 'Cache Version:', 'fanfiction-manager' ) . '</span>';
		$html .= '<span class="fanfic-cache-stat-value">' . esc_html( $stats['cache_version'] ) . '</span>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * AJAX handler: Clear all caches
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_clear_all_cache() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_cache_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Clear all caches
		$count = Fanfic_Cache::clear_all();

		// Log the action
		self::log_cache_action( 'clear_all', $count );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: number of cache items cleared */
					__( 'Successfully cleared %s cache items.', 'fanfiction-manager' ),
					number_format_i18n( $count )
				),
				'count'   => $count,
			)
		);
	}

	/**
	 * AJAX handler: Clean up expired transients
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_cleanup_expired() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_cache_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Clean up expired transients
		$count = Fanfic_Cache::cleanup_expired();

		// Log the action
		self::log_cache_action( 'cleanup_expired', $count );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: number of expired items cleaned */
					__( 'Successfully cleaned up %s expired items.', 'fanfiction-manager' ),
					number_format_i18n( $count )
				),
				'count'   => $count,
			)
		);
	}

	/**
	 * AJAX handler: Get cache statistics
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_get_cache_stats() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_cache_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Get fresh statistics
		$html = self::render_cache_stats();

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Schedule automatic cache cleanup
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_cleanup() {
		// Get cron hour from settings (default 3 AM)
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
		$cron_hour = min( 23, max( 0, absint( $cron_hour ) ) );

		// Calculate next run time
		$scheduled_time = self::calculate_next_run_time( $cron_hour, self::CLEANUP_CRON_OFFSET_MINUTES );

		// Schedule the event
		wp_schedule_event( $scheduled_time, 'daily', 'fanfic_cleanup_transients' );
	}

	/**
	 * Re-schedule cleanup cron when settings change.
	 *
	 * @since 1.0.0
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

		self::unschedule_cleanup();
		self::schedule_cleanup();
	}

	/**
	 * Unschedule automatic cache cleanup
	 *
	 * Called on plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unschedule_cleanup() {
		wp_clear_scheduled_hook( 'fanfic_cleanup_transients' );
		wp_clear_scheduled_hook( self::CLEANUP_CONTINUATION_HOOK );
	}

	/**
	 * Cron callback: Clean up expired transients
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function cron_cleanup_expired() {
		if ( ! self::acquire_cleanup_lock() ) {
			return;
		}

		$total = 0;
		$start = microtime( true );
		$time_budget = self::get_cleanup_time_budget_seconds();
		$has_more = false;

		do {
			$count = Fanfic_Cache::cleanup_expired_batch( self::CLEANUP_BATCH_SIZE );
			$total += $count;
			$has_more = ( self::CLEANUP_BATCH_SIZE === $count );
		} while ( $has_more && ( microtime( true ) - $start ) < $time_budget );

		if ( $has_more ) {
			self::schedule_cleanup_continuation();
		} else {
			wp_clear_scheduled_hook( self::CLEANUP_CONTINUATION_HOOK );
		}

		self::release_cleanup_lock();

		// Log the cleanup
		self::log_cache_action( 'cron_cleanup', $total );
	}

	/**
	 * Admin post handler: Run cache cleanup now
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function run_cache_cleanup_now() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_cache_cleanup_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_cache_cleanup_nonce'] ) ), 'fanfic_cache_cleanup_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Run cleanup
		$count = Fanfic_Cache::cleanup_expired();

		// Log the action
		self::log_cache_action( 'manual_cleanup', $count );

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'fanfiction-settings',
					'tab'             => 'general',
					'cache_cleaned'   => 'true',
					'cleaned_count'   => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Log cache action to database
	 *
	 * Stores cache management actions for admin visibility.
	 *
	 * @since 1.0.0
	 * @param string $action Action type.
	 * @param int    $count  Number of items affected.
	 * @return void
	 */
	private static function log_cache_action( $action, $count ) {
		// Store last cleanup info as option
		$log_data = array(
			'action'    => $action,
			'count'     => $count,
			'timestamp' => current_time( 'timestamp' ),
			'user_id'   => get_current_user_id(),
		);

		update_option( 'fanfic_last_cache_action', $log_data );

		// Also add to a rolling log (keep last 20 entries)
		$log_history = get_option( 'fanfic_cache_action_log', array() );
		array_unshift( $log_history, $log_data );
		$log_history = array_slice( $log_history, 0, 20 );
		update_option( 'fanfic_cache_action_log', $log_history );
	}

	/**
	 * Calculate next run time for cron hour + offset.
	 *
	 * @since 2.0.0
	 * @param int $cron_hour Hour (0-23).
	 * @param int $offset_minutes Offset in minutes.
	 * @return int Timestamp.
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
	 * Schedule continuation worker.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function schedule_cleanup_continuation() {
		if ( ! wp_next_scheduled( self::CLEANUP_CONTINUATION_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CLEANUP_CONTINUATION_HOOK );
		}
	}

	/**
	 * Acquire cleanup lock.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private static function acquire_cleanup_lock() {
		if ( get_transient( self::CLEANUP_LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::CLEANUP_LOCK_KEY, 1, self::CLEANUP_MAX_RUNTIME_SECONDS + 120 );
		return true;
	}

	/**
	 * Release cleanup lock.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function release_cleanup_lock() {
		delete_transient( self::CLEANUP_LOCK_KEY );
	}

	/**
	 * Get safe cleanup budget based on server max_execution_time.
	 *
	 * @since 2.0.0
	 * @return int Seconds.
	 */
	private static function get_cleanup_time_budget_seconds() {
		$budget = self::CLEANUP_MAX_RUNTIME_SECONDS;
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 ) {
			$budget = max( 10, min( $budget, $max_exec - 5 ) );
		}
		return $budget;
	}

	/**
	 * Get last cache action info
	 *
	 * @since 1.0.0
	 * @return array|false Last action data or false if none.
	 */
	public static function get_last_cache_action() {
		return get_option( 'fanfic_last_cache_action', false );
	}
}
