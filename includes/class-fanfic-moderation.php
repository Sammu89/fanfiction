<?php
/**
 * Moderation Queue Interface
 *
 * Handles the admin interface for content moderation and reports management.
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
 * Class Fanfic_Moderation
 *
 * Manages the moderation queue interface.
 *
 * @since 1.0.0
 */
class Fanfic_Moderation {

	/**
	 * Initialize the moderation class
	 *
	 * Sets up WordPress hooks for moderation functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_fanfic_moderate_report', array( __CLASS__, 'moderate_report' ) );
		add_action( 'admin_post_fanfic_moderate_comment', array( __CLASS__, 'moderate_comment_action' ) );
	}

	/**
	 * Render moderation queue page
	 *
	 * Uses WP_List_Table implementation for enhanced table functionality.
	 * Now includes Queue and Log tabs.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added Log tab for viewing moderation history.
	 * @return void
	 */
	public static function render() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current main tab
		$allowed_tabs = array( 'queue', 'log' );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'queue';
		$current_tab = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'queue';

		// Display admin notices
		Fanfic_Moderation_Table::display_notices();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Main Tabs -->
			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-moderation&tab=queue" class="nav-tab <?php echo 'queue' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Queue', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-moderation&tab=log" class="nav-tab <?php echo 'log' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Log', 'fanfiction-manager' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $current_tab ) {
					case 'log':
						self::render_log_tab();
						break;
					case 'queue':
					default:
						self::render_queue_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Queue tab content
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_queue_tab() {
		global $wpdb;

		// Count reports by status (using standardized status values)
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$pending_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'pending' ) );
		$reviewed_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'reviewed' ) );
		$dismissed_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'dismissed' ) );
		$total_count = $pending_count + $reviewed_count + $dismissed_count;

		// Get current status filter (standardized values)
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'pending';
		$allowed_statuses = array( 'all', 'pending', 'reviewed', 'dismissed' );
		$status_filter = in_array( $status_filter, $allowed_statuses, true ) ? $status_filter : 'pending';

		?>
		<p><?php esc_html_e( 'Review and moderate reported content from users. Take action on reports to maintain community standards.', 'fanfiction-manager' ); ?></p>

		<!-- Status Filter Tabs -->
		<ul class="subsubsub">
			<li>
				<a href="?page=fanfiction-moderation&tab=queue&status=pending" <?php echo 'pending' === $status_filter ? 'class="current"' : ''; ?>>
					<?php
					printf(
						/* translators: %d: Number of pending reports */
						esc_html__( 'Pending (%d)', 'fanfiction-manager' ),
						absint( $pending_count )
					);
					?>
				</a> |
			</li>
			<li>
				<a href="?page=fanfiction-moderation&tab=queue&status=reviewed" <?php echo 'reviewed' === $status_filter ? 'class="current"' : ''; ?>>
					<?php
					printf(
						/* translators: %d: Number of reviewed reports */
						esc_html__( 'Reviewed (%d)', 'fanfiction-manager' ),
						absint( $reviewed_count )
					);
					?>
				</a> |
			</li>
			<li>
				<a href="?page=fanfiction-moderation&tab=queue&status=dismissed" <?php echo 'dismissed' === $status_filter ? 'class="current"' : ''; ?>>
					<?php
					printf(
						/* translators: %d: Number of dismissed reports */
						esc_html__( 'Dismissed (%d)', 'fanfiction-manager' ),
						absint( $dismissed_count )
					);
					?>
				</a> |
			</li>
			<li>
				<a href="?page=fanfiction-moderation&tab=queue&status=all" <?php echo 'all' === $status_filter ? 'class="current"' : ''; ?>>
					<?php
					printf(
						/* translators: %d: Total number of reports */
						esc_html__( 'All (%d)', 'fanfiction-manager' ),
						absint( $total_count )
					);
					?>
				</a>
			</li>
		</ul>

		<!-- WP_List_Table Implementation -->
		<div class="fanfic-moderation-table" style="margin-top: 20px; clear: both;">
			<?php
			// Create and display the WP_List_Table
			$moderation_table = new Fanfic_Moderation_Table();
			$moderation_table->prepare_items();
			?>
			<form method="get">
				<input type="hidden" name="page" value="fanfiction-moderation" />
				<input type="hidden" name="tab" value="queue" />
				<?php
				$moderation_table->display();
				?>
			</form>
		</div>

		<!-- Information Box -->
		<div class="fanfic-info-box box-warning" style="background: #e7f3ff; border: 1px solid #2196F3; border-radius: 4px; padding: 20px; margin-top: 30px;">
			<h3><?php esc_html_e( 'Moderation Guidelines', 'fanfiction-manager' ); ?></h3>
			<ul style="margin-left: 20px; list-style-type: disc;">
				<li><?php esc_html_e( 'Review each report carefully before taking action.', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Mark reports as "Reviewed" after you have taken appropriate action on the content.', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Dismiss reports that are invalid or do not violate community guidelines.', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'After reviewing a report, take appropriate action on the reported content (edit, delete, or suspend user).', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'All moderation actions are logged with moderator stamps for accountability.', 'fanfiction-manager' ); ?></li>
			</ul>
		</div>

		<style>
			.fanfic-moderation-table .notice.inline {
				display: inline-block;
				padding: 2px 8px;
				margin: 0;
			}

			.fanfic-info-box.box-warning h3 {
				margin-top: 0;
				color: #1976D2;
			}

			.fanfic-info-box.box-warning ul {
				margin: 15px 0;
			}

			.fanfic-info-box.box-warning li {
				margin-bottom: 8px;
			}

			/* Status badge styles */
			.status-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
			}

			.status-badge.status-warning {
				background: #fff8e5;
				color: #f0b429;
				border: 1px solid #f0b429;
			}

			.status-badge.status-success {
				background: #e7f7ec;
				color: #46b450;
				border: 1px solid #46b450;
			}

			.status-badge.status-info {
				background: #e5f5fa;
				color: #00a0d2;
				border: 1px solid #00a0d2;
			}
		</style>
		<?php
	}

	/**
	 * Render Log tab content
	 *
	 * Displays moderation log entries with filtering.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_log_tab() {
		// Get filter parameters
		$action_filter = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
		$target_filter = isset( $_GET['target_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['target_filter'] ) ) : '';
		$per_page = 25;
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		// Build query args
		$args = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);

		if ( '' !== $action_filter ) {
			$args['action'] = $action_filter;
		}

		if ( '' !== $target_filter ) {
			$args['target_type'] = $target_filter;
		}

		// Get log entries
		$logs = Fanfic_Moderation_Log::get_logs( $args );
		$total_logs = Fanfic_Moderation_Log::count( $args );
		$total_pages = ceil( $total_logs / $per_page );

		?>
		<p><?php esc_html_e( 'View the history of moderation actions including bans, unbans, story blocks, and unblocks.', 'fanfiction-manager' ); ?></p>

		<!-- Filters -->
		<div class="fanfic-log-filters" style="margin: 15px 0;">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<input type="hidden" name="page" value="fanfiction-moderation">
				<input type="hidden" name="tab" value="log">

				<select name="action_filter">
					<option value=""><?php esc_html_e( 'All Actions', 'fanfiction-manager' ); ?></option>
					<option value="ban" <?php selected( $action_filter, 'ban' ); ?>><?php esc_html_e( 'Ban', 'fanfiction-manager' ); ?></option>
					<option value="unban" <?php selected( $action_filter, 'unban' ); ?>><?php esc_html_e( 'Unban', 'fanfiction-manager' ); ?></option>
					<option value="block" <?php selected( $action_filter, 'block' ); ?>><?php esc_html_e( 'Block', 'fanfiction-manager' ); ?></option>
					<option value="unblock" <?php selected( $action_filter, 'unblock' ); ?>><?php esc_html_e( 'Unblock', 'fanfiction-manager' ); ?></option>
				</select>

				<select name="target_filter">
					<option value=""><?php esc_html_e( 'All Targets', 'fanfiction-manager' ); ?></option>
					<option value="user" <?php selected( $target_filter, 'user' ); ?>><?php esc_html_e( 'Users', 'fanfiction-manager' ); ?></option>
					<option value="story" <?php selected( $target_filter, 'story' ); ?>><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'fanfiction-manager' ); ?></button>
				<?php if ( $action_filter || $target_filter ) : ?>
					<a href="?page=fanfiction-moderation&tab=log" class="button"><?php esc_html_e( 'Clear', 'fanfiction-manager' ); ?></a>
				<?php endif; ?>
			</form>
		</div>

		<!-- Log Table -->
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-date" style="width: 150px;"><?php esc_html_e( 'Date', 'fanfiction-manager' ); ?></th>
					<th scope="col" class="manage-column column-actor" style="width: 150px;"><?php esc_html_e( 'Moderator', 'fanfiction-manager' ); ?></th>
					<th scope="col" class="manage-column column-action" style="width: 100px;"><?php esc_html_e( 'Action', 'fanfiction-manager' ); ?></th>
					<th scope="col" class="manage-column column-target"><?php esc_html_e( 'Target', 'fanfiction-manager' ); ?></th>
					<th scope="col" class="manage-column column-reason"><?php esc_html_e( 'Reason', 'fanfiction-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No moderation log entries found.', 'fanfiction-manager' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<?php
						// Get moderator info
						$actor = get_userdata( $log['actor_id'] );
						$actor_name = $actor ? $actor->display_name : __( 'Unknown', 'fanfiction-manager' );

						// Get target info
						$target_name = '';
						$target_link = '';
						if ( 'user' === $log['target_type'] ) {
							$target_user = get_userdata( $log['target_id'] );
							$target_name = $target_user ? $target_user->display_name : __( 'Unknown User', 'fanfiction-manager' );
							if ( $target_user ) {
								$target_link = admin_url( 'user-edit.php?user_id=' . $log['target_id'] );
							}
						} elseif ( 'story' === $log['target_type'] ) {
							$story = get_post( $log['target_id'] );
							$target_name = $story ? $story->post_title : __( 'Unknown Story', 'fanfiction-manager' );
							if ( $story ) {
								$target_link = get_edit_post_link( $log['target_id'] );
							}
						}

						// Format action badge
						$action_badges = array(
							'ban'     => array( 'label' => __( 'Ban', 'fanfiction-manager' ), 'class' => 'fanfic-log-action-ban' ),
							'unban'   => array( 'label' => __( 'Unban', 'fanfiction-manager' ), 'class' => 'fanfic-log-action-unban' ),
							'block'   => array( 'label' => __( 'Block', 'fanfiction-manager' ), 'class' => 'fanfic-log-action-block' ),
							'unblock' => array( 'label' => __( 'Unblock', 'fanfiction-manager' ), 'class' => 'fanfic-log-action-unblock' ),
						);
						$badge = isset( $action_badges[ $log['action'] ] ) ? $action_badges[ $log['action'] ] : array( 'label' => ucfirst( $log['action'] ), 'class' => '' );
						?>
						<tr>
							<td class="column-date">
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?>
							</td>
							<td class="column-actor">
								<?php echo esc_html( $actor_name ); ?>
							</td>
							<td class="column-action">
								<span class="fanfic-log-action <?php echo esc_attr( $badge['class'] ); ?>">
									<?php echo esc_html( $badge['label'] ); ?>
								</span>
							</td>
							<td class="column-target">
								<strong><?php echo esc_html( ucfirst( $log['target_type'] ) ); ?>:</strong>
								<?php if ( $target_link ) : ?>
									<a href="<?php echo esc_url( $target_link ); ?>"><?php echo esc_html( $target_name ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $target_name ); ?>
								<?php endif; ?>
							</td>
							<td class="column-reason">
								<?php echo esc_html( $log['reason'] ?: '-' ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: Number of items */
							esc_html( _n( '%s item', '%s items', $total_logs, 'fanfiction-manager' ) ),
							number_format_i18n( $total_logs )
						);
						?>
					</span>
					<span class="pagination-links">
						<?php
						$base_url = add_query_arg(
							array(
								'page'          => 'fanfiction-moderation',
								'tab'           => 'log',
								'action_filter' => $action_filter,
								'target_filter' => $target_filter,
							),
							admin_url( 'admin.php' )
						);

						if ( $paged > 1 ) : ?>
							<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
								<span aria-hidden="true">&laquo;</span>
							</a>
							<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>">
								<span aria-hidden="true">&lsaquo;</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
						<?php endif; ?>

						<span class="paging-input">
							<?php echo esc_html( $paged ); ?> / <?php echo esc_html( $total_pages ); ?>
						</span>

						<?php if ( $paged < $total_pages ) : ?>
							<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>">
								<span aria-hidden="true">&rsaquo;</span>
							</a>
							<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>">
								<span aria-hidden="true">&raquo;</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>

		<style>
			.fanfic-log-action {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.fanfic-log-action-ban {
				background: #ffebee;
				color: #c62828;
			}
			.fanfic-log-action-unban {
				background: #e8f5e9;
				color: #2e7d32;
			}
			.fanfic-log-action-block {
				background: #fff3e0;
				color: #e65100;
			}
			.fanfic-log-action-unblock {
				background: #e3f2fd;
				color: #1565c0;
			}
		</style>
		<?php
	}

	/**
	 * Moderate a report (approve or reject)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function moderate_report() {
		global $wpdb;

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get report ID and action
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$mod_action = isset( $_POST['mod_action'] ) ? sanitize_text_field( wp_unslash( $_POST['mod_action'] ) ) : '';

		// Verify nonce
		if ( ! isset( $_POST['fanfic_moderate_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_moderate_nonce'] ) ), 'fanfic_moderate_' . $report_id ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Validate action
		if ( ! in_array( $mod_action, array( 'approve', 'reject' ), true ) ) {
			wp_die( __( 'Invalid moderation action.', 'fanfiction-manager' ) );
		}

		// Update report status (using standardized status values)
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$new_status = 'approve' === $mod_action ? 'reviewed' : 'dismissed';
		$current_user_id = get_current_user_id();

		$result = $wpdb->update(
			$reports_table,
			array(
				'status'       => $new_status,
				'moderator_id' => $current_user_id,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $report_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-moderation',
						'error' => 'update_failed',
					),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'fanfiction-moderation',
						'success' => $new_status,
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function display_notices() {
		// Success notices (using standardized status values)
		if ( isset( $_GET['success'] ) ) {
			$success_code = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			$messages = array(
				'reviewed'         => __( 'Report marked as reviewed successfully.', 'fanfiction-manager' ),
				'dismissed'        => __( 'Report dismissed successfully.', 'fanfiction-manager' ),
				'comment_approved' => __( 'Comment approved successfully.', 'fanfiction-manager' ),
				'comment_rejected' => __( 'Comment rejected successfully.', 'fanfiction-manager' ),
				'comment_spam'     => __( 'Comment marked as spam.', 'fanfiction-manager' ),
				'comment_deleted'  => __( 'Comment deleted successfully.', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $success_code ] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $success_code ] ); ?></p>
				</div>
				<?php
			}
		}

		// Error notices
		if ( isset( $_GET['error'] ) ) {
			$error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$messages = array(
				'update_failed'      => __( 'Error: Failed to update report status.', 'fanfiction-manager' ),
				'comment_not_found'  => __( 'Error: Comment not found.', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $error_code ] ) ) {
				?>
				<div class="notice error-message is-dismissible">
					<p><?php echo esc_html( $messages[ $error_code ] ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Moderate comment action (approve, reject, delete, edit)
	 *
	 * Handles moderator actions on reported comments.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function moderate_comment_action() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get comment ID and action
		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		$mod_action = isset( $_POST['mod_action'] ) ? sanitize_text_field( wp_unslash( $_POST['mod_action'] ) ) : '';

		// Verify nonce
		if ( ! isset( $_POST['fanfic_moderate_comment_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_moderate_comment_nonce'] ) ), 'fanfic_moderate_comment_' . $comment_id ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Validate action
		$allowed_actions = array( 'approve', 'reject', 'delete', 'spam' );
		if ( ! in_array( $mod_action, $allowed_actions, true ) ) {
			wp_die( __( 'Invalid moderation action.', 'fanfiction-manager' ) );
		}

		// Get comment
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-moderation',
						'error' => 'comment_not_found',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$success_code = '';

		// Perform action
		switch ( $mod_action ) {
			case 'approve':
				wp_set_comment_status( $comment_id, 'approve' );
				$success_code = 'comment_approved';
				break;

			case 'reject':
				wp_set_comment_status( $comment_id, 'hold' );
				$success_code = 'comment_rejected';
				break;

			case 'spam':
				wp_spam_comment( $comment_id );
				$success_code = 'comment_spam';
				break;

			case 'delete':
				wp_delete_comment( $comment_id, true );
				$success_code = 'comment_deleted';
				break;
		}

		// Add moderator stamp
		if ( 'delete' !== $mod_action ) {
			update_comment_meta( $comment_id, 'fanfic_moderated_at', current_time( 'mysql' ) );
			update_comment_meta( $comment_id, 'fanfic_moderated_by', get_current_user_id() );
			update_comment_meta( $comment_id, 'fanfic_moderation_action', $mod_action );
		}

		// Redirect back to moderation queue
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-moderation',
					'success' => $success_code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
