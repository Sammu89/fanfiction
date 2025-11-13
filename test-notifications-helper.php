<?php
/**
 * Test Notifications Helper
 *
 * This file provides helper functions to create test notifications for testing
 * the Dashboard Notifications System.
 *
 * USAGE:
 * 1. Copy this file to your WordPress root directory or wp-content/plugins/fanfiction-manager/
 * 2. Add this line to wp-config.php (temporarily):
 *    require_once( ABSPATH . 'test-notifications-helper.php' );
 * 3. Call the functions from WordPress admin or create a custom admin page
 * 4. IMPORTANT: Remove this file and the require line when done testing!
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access not permitted.' );
}

/**
 * Create test notifications for a specific user
 *
 * @param int $user_id WordPress user ID
 * @param int $count   Number of test notifications to create (default: 15)
 * @return array Results array with success/error counts
 */
function fanfic_create_test_notifications( $user_id = null, $count = 15 ) {
	// Use current user if not specified
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	// Verify user exists
	if ( ! get_user_by( 'ID', $user_id ) ) {
		return array(
			'success' => false,
			'error'   => 'Invalid user ID',
		);
	}

	$created = 0;
	$failed = 0;

	// Notification types and sample messages
	$notification_templates = array(
		array(
			'type'    => Fanfic_Notifications::TYPE_NEW_COMMENT,
			'message' => 'John Doe commented on your story "Adventure Time"',
			'data'    => array( 'story_id' => 123, 'comment_id' => 456 ),
		),
		array(
			'type'    => Fanfic_Notifications::TYPE_NEW_FOLLOWER,
			'message' => 'Jane Smith is now following you',
			'data'    => array( 'follower_id' => 789 ),
		),
		array(
			'type'    => Fanfic_Notifications::TYPE_NEW_CHAPTER,
			'message' => 'New chapter published in "Mystery Novel"',
			'data'    => array( 'story_id' => 234, 'chapter_id' => 567 ),
		),
		array(
			'type'    => Fanfic_Notifications::TYPE_COMMENT_REPLY,
			'message' => 'Sarah replied to your comment on "Sci-Fi Chronicles"',
			'data'    => array( 'comment_id' => 890, 'story_id' => 345 ),
		),
		array(
			'type'    => Fanfic_Notifications::TYPE_FOLLOW_STORY,
			'message' => 'Bob is now following your story "The Dark Tower"',
			'data'    => array( 'story_id' => 456, 'follower_id' => 901 ),
		),
	);

	// Create notifications
	for ( $i = 0; $i < $count; $i++ ) {
		// Pick a random template
		$template = $notification_templates[ $i % count( $notification_templates ) ];

		// Add number to message for uniqueness
		$message = $template['message'] . " #$i";

		// Create notification
		$result = Fanfic_Notifications::create_notification(
			$user_id,
			$template['type'],
			$message,
			$template['data']
		);

		if ( $result ) {
			$created++;
		} else {
			$failed++;
		}
	}

	return array(
		'success' => true,
		'created' => $created,
		'failed'  => $failed,
		'user_id' => $user_id,
	);
}

/**
 * Delete all notifications for a specific user
 *
 * @param int $user_id WordPress user ID
 * @return array Results array
 */
function fanfic_delete_all_test_notifications( $user_id = null ) {
	// Use current user if not specified
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	// Verify user exists
	if ( ! get_user_by( 'ID', $user_id ) ) {
		return array(
			'success' => false,
			'error'   => 'Invalid user ID',
		);
	}

	$result = Fanfic_Notifications::delete_all_notifications( $user_id );

	return array(
		'success' => $result,
		'user_id' => $user_id,
	);
}

/**
 * Get notification stats for a user
 *
 * @param int $user_id WordPress user ID
 * @return array Stats array
 */
function fanfic_get_notification_stats( $user_id = null ) {
	// Use current user if not specified
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	// Verify user exists
	if ( ! get_user_by( 'ID', $user_id ) ) {
		return array(
			'success' => false,
			'error'   => 'Invalid user ID',
		);
	}

	$unread_count = Fanfic_Notifications::get_unread_count( $user_id );
	$total_count = Fanfic_Notifications::get_total_count( $user_id );
	$notifications = Fanfic_Notifications::get_user_notifications( $user_id, false, 5, 0 );

	return array(
		'success'          => true,
		'user_id'          => $user_id,
		'unread_count'     => $unread_count,
		'total_count'      => $total_count,
		'recent_5'         => $notifications,
	);
}

/**
 * Admin page to test notifications
 *
 * Add this action to create a testing page in WordPress admin:
 * add_action( 'admin_menu', 'fanfic_add_test_notifications_page' );
 */
function fanfic_add_test_notifications_page() {
	add_submenu_page(
		'tools.php',
		'Test Notifications',
		'Test Notifications',
		'manage_options',
		'fanfic-test-notifications',
		'fanfic_render_test_notifications_page'
	);
}

/**
 * Render the test notifications admin page
 */
function fanfic_render_test_notifications_page() {
	// Check user permissions
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	// Handle form submissions
	$message = '';
	$message_type = '';

	if ( isset( $_POST['fanfic_test_action'] ) && check_admin_referer( 'fanfic_test_notifications' ) ) {
		$action = sanitize_text_field( $_POST['fanfic_test_action'] );
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();

		switch ( $action ) {
			case 'create':
				$count = isset( $_POST['notification_count'] ) ? absint( $_POST['notification_count'] ) : 15;
				$result = fanfic_create_test_notifications( $user_id, $count );
				if ( $result['success'] ) {
					$message = sprintf( 'Created %d test notifications for user ID %d', $result['created'], $user_id );
					$message_type = 'success';
				} else {
					$message = 'Error: ' . $result['error'];
					$message_type = 'error';
				}
				break;

			case 'delete':
				$result = fanfic_delete_all_test_notifications( $user_id );
				if ( $result['success'] ) {
					$message = sprintf( 'Deleted all notifications for user ID %d', $user_id );
					$message_type = 'success';
				} else {
					$message = 'Error: ' . $result['error'];
					$message_type = 'error';
				}
				break;

			case 'stats':
				$result = fanfic_get_notification_stats( $user_id );
				if ( $result['success'] ) {
					$message = sprintf(
						'User ID %d: %d unread, %d total notifications',
						$user_id,
						$result['unread_count'],
						$result['total_count']
					);
					$message_type = 'info';
				} else {
					$message = 'Error: ' . $result['error'];
					$message_type = 'error';
				}
				break;
		}
	}

	// Get current user ID
	$current_user_id = get_current_user_id();

	?>
	<div class="wrap">
		<h1>Test Notifications</h1>

		<?php if ( $message ) : ?>
			<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
		<?php endif; ?>

		<div class="card">
			<h2>Create Test Notifications</h2>
			<form method="post">
				<?php wp_nonce_field( 'fanfic_test_notifications' ); ?>
				<input type="hidden" name="fanfic_test_action" value="create">

				<table class="form-table">
					<tr>
						<th scope="row">User ID</th>
						<td>
							<input type="number" name="user_id" value="<?php echo esc_attr( $current_user_id ); ?>" min="1" class="regular-text">
							<p class="description">WordPress user ID to create notifications for (default: current user)</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Number of Notifications</th>
						<td>
							<input type="number" name="notification_count" value="15" min="1" max="100" class="regular-text">
							<p class="description">How many test notifications to create (1-100)</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">Create Test Notifications</button>
				</p>
			</form>
		</div>

		<div class="card">
			<h2>View Notification Stats</h2>
			<form method="post">
				<?php wp_nonce_field( 'fanfic_test_notifications' ); ?>
				<input type="hidden" name="fanfic_test_action" value="stats">

				<table class="form-table">
					<tr>
						<th scope="row">User ID</th>
						<td>
							<input type="number" name="user_id" value="<?php echo esc_attr( $current_user_id ); ?>" min="1" class="regular-text">
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button">Get Stats</button>
				</p>
			</form>
		</div>

		<div class="card">
			<h2>Delete All Notifications</h2>
			<form method="post" onsubmit="return confirm('Are you sure you want to delete ALL notifications for this user? This cannot be undone.');">
				<?php wp_nonce_field( 'fanfic_test_notifications' ); ?>
				<input type="hidden" name="fanfic_test_action" value="delete">

				<table class="form-table">
					<tr>
						<th scope="row">User ID</th>
						<td>
							<input type="number" name="user_id" value="<?php echo esc_attr( $current_user_id ); ?>" min="1" class="regular-text">
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-secondary">Delete All Notifications</button>
				</p>
			</form>
		</div>

		<div class="card">
			<h2>Testing Instructions</h2>
			<ol>
				<li>Create 15-20 test notifications using the form above</li>
				<li>Navigate to your dashboard page to view notifications</li>
				<li>Test the dismiss functionality (X button)</li>
				<li>Test pagination if you have 11+ notifications</li>
				<li>Check browser console for any JavaScript errors</li>
				<li>Verify badge count updates correctly</li>
			</ol>
			<p><strong>Note:</strong> This testing page should only be available in development environments. Remove it before deploying to production!</p>
		</div>
	</div>
	<?php
}

// Uncomment this line to add the admin page to Tools menu:
// add_action( 'admin_menu', 'fanfic_add_test_notifications_page' );
