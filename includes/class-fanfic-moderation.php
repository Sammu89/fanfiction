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
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render() {
		global $wpdb;

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

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

		// Display admin notices
		Fanfic_Moderation_Table::display_notices();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Review and moderate reported content from users. Take action on reports to maintain community standards.', 'fanfiction-manager' ); ?></p>

			<!-- Status Filter Tabs -->
			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-moderation&status=pending" class="nav-tab <?php echo 'pending' === $status_filter ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: Number of pending reports */
						esc_html__( 'Pending (%d)', 'fanfiction-manager' ),
						absint( $pending_count )
					);
					?>
				</a>
				<a href="?page=fanfiction-moderation&status=reviewed" class="nav-tab <?php echo 'reviewed' === $status_filter ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: Number of reviewed reports */
						esc_html__( 'Reviewed (%d)', 'fanfiction-manager' ),
						absint( $reviewed_count )
					);
					?>
				</a>
				<a href="?page=fanfiction-moderation&status=dismissed" class="nav-tab <?php echo 'dismissed' === $status_filter ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: Number of dismissed reports */
						esc_html__( 'Dismissed (%d)', 'fanfiction-manager' ),
						absint( $dismissed_count )
					);
					?>
				</a>
				<a href="?page=fanfiction-moderation&status=all" class="nav-tab <?php echo 'all' === $status_filter ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: Total number of reports */
						esc_html__( 'All (%d)', 'fanfiction-manager' ),
						absint( $total_count )
					);
					?>
				</a>
			</nav>

			<!-- WP_List_Table Implementation -->
			<div class="fanfic-moderation-table" style="margin-top: 20px;">
				<?php
				// Create and display the WP_List_Table
				$moderation_table = new Fanfic_Moderation_Table();
				$moderation_table->prepare_items();
				?>
				<form method="get">
					<input type="hidden" name="page" value="fanfiction-moderation" />
					<?php
					$moderation_table->display();
					?>
				</form>
			</div>

			<!-- Information Box -->
			<div class="fanfic-info-box" style="background: #e7f3ff; border: 1px solid #2196F3; border-radius: 4px; padding: 20px; margin-top: 30px;">
				<h3><?php esc_html_e( 'Moderation Guidelines', 'fanfiction-manager' ); ?></h3>
				<ul style="margin-left: 20px; list-style-type: disc;">
					<li><?php esc_html_e( 'Review each report carefully before taking action.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Mark reports as "Reviewed" after you have taken appropriate action on the content.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Dismiss reports that are invalid or do not violate community guidelines.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'After reviewing a report, take appropriate action on the reported content (edit, delete, or suspend user).', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'All moderation actions are logged with moderator stamps for accountability.', 'fanfiction-manager' ); ?></li>
				</ul>
			</div>
		</div>

		<style>
			.fanfic-moderation-table .notice.inline {
				display: inline-block;
				padding: 2px 8px;
				margin: 0;
			}

			.fanfic-info-box h3 {
				margin-top: 0;
				color: #1976D2;
			}

			.fanfic-info-box ul {
				margin: 15px 0;
			}

			.fanfic-info-box li {
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
				<div class="notice notice-error is-dismissible">
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
