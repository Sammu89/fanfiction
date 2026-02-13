<?php
/**
 * Template Name: Author Dashboard
 * Description: Main dashboard hub for logged-in authors
 *
 * This template displays:
 * - Welcome message with avatar
 * - Statistics cards (stories, chapters, views, following)
 * - Quick action buttons
 * - Recent stories management
 * - Notifications
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'You must be logged in to view this page.', 'fanfiction-manager' ); ?>
			<a href="<?php echo esc_url( wp_login_url( fanfic_get_current_url() ) ); ?>" class="fanfic-button">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</span>
	</div>
	<?php
	return;
}

// Check if user has author capability
if ( ! current_user_can( 'edit_fanfiction_stories' ) ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'Access Denied: You do not have permission to view the author dashboard.', 'fanfiction-manager' ); ?></span>
	</div>
	<?php
	return;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

$coauthors_enabled = class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled();
$pending_invitations = $coauthors_enabled ? Fanfic_Coauthors::get_pending_invitations( $user_id ) : array();
?>

<!-- Breadcrumb Navigation -->
<?php fanfic_render_breadcrumb( 'dashboard' ); ?>

<!-- Unified Messages Container -->
<div id="fanfic-messages" class="fanfic-messages-container" role="region" aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>" aria-live="polite">
<?php
// Display flash messages
$flash_messages = Fanfic_Flash_Messages::get_messages();
if ( ! empty( $flash_messages ) ) {
    foreach ( $flash_messages as $msg ) {
        $type = esc_attr( $msg['type'] );
        $message = esc_html( $msg['message'] );
        $icon = ( $type === 'success' ) ? '&#10003;' : '&#10007;'; // Simplified for example
        $role = ( $type === 'error' ) ? 'alert' : 'status';

        echo "<div class='fanfic-message fanfic-message-{$type}' role='{$role}'>
                <span class='fanfic-message-icon' aria-hidden='true'>{$icon}</span>
                <span class='fanfic-message-content'>{$message}</span>
                <button class='fanfic-message-close' aria-label='" . esc_attr__( 'Dismiss message', 'fanfiction-manager' ) . "'>&times;</button>
              </div>";
    }
}

// Manually display a direct error from URL if it exists (for backward compatibility or specific cases)
if ( isset( $_GET['error'] ) ) {
    echo "<div class='fanfic-message fanfic-message-error' role='alert'>
            <span class='fanfic-message-icon' aria-hidden='true'>&#10007;</span>
            <span class='fanfic-message-content'>" . esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) . "</span>
            <button class='fanfic-message-close' aria-label='" . esc_attr__( 'Dismiss message', 'fanfiction-manager' ) . "'>&times;</button>
          </div>";
}
?>
</div>

<!-- Dashboard Header -->
<header class="fanfic-dashboard-header">
	<div class="fanfic-dashboard-hero">
		<div class="fanfic-dashboard-avatar">
			<?php echo get_avatar( $current_user->ID, 80, '', $current_user->display_name, array( 'class' => 'fanfic-avatar-image', 'loading' => 'lazy' ) ); ?>
		</div>
		<div class="fanfic-dashboard-welcome">
			<h2 class="fanfic-dashboard-title">
				<?php
				/* translators: %s: User display name */
				printf( esc_html__( 'Welcome back, %s!', 'fanfiction-manager' ), esc_html( $current_user->display_name ) );
				?>
			</h2>
			<p class="fanfic-dashboard-subtitle">
				<?php esc_html_e( 'Manage your stories, track your progress, and connect with readers.', 'fanfiction-manager' ); ?>
			</p>
		</div>
	</div>
</header>

<!-- Statistics Cards -->
<section class="fanfic-dashboard-stats" aria-labelledby="stats-heading">
	<h2 id="stats-heading" class="screen-reader-text"><?php esc_html_e( 'Your Statistics', 'fanfiction-manager' ); ?></h2>

	<div class="fanfic-stats-grid">
		<!-- Total Stories -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-book"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Total Stories', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo Fanfic_Shortcodes_Author::get_story_count(); ?></p>
			</div>
		</div>

		<!-- Total Chapters -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-media-document"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Total Chapters', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo Fanfic_Shortcodes_Author::get_total_chapters(); ?></p>
			</div>
		</div>

		<!-- Total Views -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Total Views', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo Fanfic_Shortcodes_Author::get_total_views(); ?></p>
			</div>
		</div>

		<!-- Stories Following -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-heart"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Stories Following', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo Fanfic_Shortcodes_User::get_favorites_count(); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- Quick Actions -->
<section class="fanfic-dashboard-actions" aria-labelledby="actions-heading">
	<h2 id="actions-heading"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>

	<div class="fanfic-actions-grid">
		<a href="<?php echo esc_url( fanfic_get_create_story_url() ); ?>" class="fanfic-button fanfic-action-primary">
			<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></span>
		</a>

		<a href="<?php echo esc_url( fanfic_get_dashboard_url() . '#my-stories' ); ?>" class="fanfic-button fanfic-action-secondary">
			<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
			<span><?php esc_html_e( 'View My Stories', 'fanfiction-manager' ); ?></span>
		</a>

		<a href="<?php echo esc_url( fanfic_get_edit_profile_url() ); ?>" class="fanfic-button fanfic-action-secondary">
			<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Edit Profile', 'fanfiction-manager' ); ?></span>
		</a>
	</div>
</section>

<!-- Main Dashboard Content -->
<div class="fanfic-dashboard-main">
	<div class="fanfic-dashboard-primary">
		<?php if ( $coauthors_enabled && ! empty( $pending_invitations ) ) : ?>
			<section class="fanfic-dashboard-invitations" aria-labelledby="coauthor-invitations-heading">
				<h2 id="coauthor-invitations-heading"><?php esc_html_e( 'Pending Co-Author Invitations', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-invitations-list">
					<?php foreach ( $pending_invitations as $invitation ) : ?>
						<?php
						$story_id = isset( $invitation->story_id ) ? absint( $invitation->story_id ) : 0;
						if ( ! $story_id ) {
							continue;
						}
						$story_link = get_permalink( $story_id );
						$story_title = isset( $invitation->story_title ) ? (string) $invitation->story_title : '';
						$inviter_name = isset( $invitation->inviter_name ) ? (string) $invitation->inviter_name : '';
						?>
						<div class="fanfic-invitation-item">
							<p class="fanfic-invitation-text">
								<?php
								printf(
									/* translators: 1: inviter display name, 2: story title with link. */
									esc_html__( '%1$s invited you to co-author %2$s.', 'fanfiction-manager' ),
									esc_html( $inviter_name ),
									'"' . esc_html( $story_title ) . '"'
								);
								?>
								<?php if ( $story_link ) : ?>
									<a href="<?php echo esc_url( $story_link ); ?>" class="fanfic-invitation-story-link"><?php esc_html_e( 'View Story', 'fanfiction-manager' ); ?></a>
								<?php endif; ?>
							</p>
							<div class="fanfic-invitation-actions">
								<button type="button" class="fanfic-button fanfic-accept-invitation" data-story-id="<?php echo esc_attr( $story_id ); ?>">
									<?php esc_html_e( 'Accept', 'fanfiction-manager' ); ?>
								</button>
								<button type="button" class="fanfic-button danger fanfic-refuse-invitation" data-story-id="<?php echo esc_attr( $story_id ); ?>">
									<?php esc_html_e( 'Refuse', 'fanfiction-manager' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<!-- Manage Stories Section -->
		<section class="fanfic-dashboard-stories" id="my-stories" aria-labelledby="stories-heading">
			<h2 id="stories-heading"><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>

			<?php
			$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
			$posts_per_page = 10;

			$coauthored_ids = array();
			if ( $coauthors_enabled ) {
				$coauthored_ids = Fanfic_Coauthors::get_user_coauthored_stories( $user_id, 'accepted' );
			}

			$query_args = array(
				'post_type'      => 'fanfiction_story',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => $posts_per_page,
				'paged'          => $paged,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'author'         => $user_id,
			);
			if ( ! empty( $coauthored_ids ) ) {
				$query_args['fanfic_include_coauthored'] = $coauthored_ids;
			}

			// Query user's own + co-authored stories
			$query = new WP_Query( $query_args );

			// Pre-fetch chapter counts to avoid N+1 queries
			$chapter_counts = array();
			if ( $query->have_posts() ) {
				$story_ids = wp_list_pluck( $query->posts, 'ID' );

				global $wpdb;
				$story_ids_str = implode( ',', array_map( 'absint', $story_ids ) );
				$chapter_count_results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_parent, COUNT(*) as count
						FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_parent IN ({$story_ids_str})
						AND post_status IN ('publish', 'draft', 'pending')
						GROUP BY post_parent",
						'fanfiction_chapter'
					),
					OBJECT_K
				);

				foreach ( $chapter_count_results as $parent_id => $result ) {
					$chapter_counts[ $parent_id ] = (int) $result->count;
				}
			}

			?>

			<div class="fanfic-author-stories-manage">
				<?php if ( $query->have_posts() ) : ?>
					<div class="fanfic-stories-table-wrapper">
						<table class="fanfic-stories-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Title', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Views', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Updated', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php while ( $query->have_posts() ) : $query->the_post(); ?>
									<?php
									$story_id = get_the_ID();
									$is_blocked = (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
									$is_original_author = ( (int) get_post_field( 'post_author', $story_id ) === (int) $user_id );
									$chapter_count = isset( $chapter_counts[ $story_id ] ) ? $chapter_counts[ $story_id ] : 0;
									$views = class_exists( 'Fanfic_Interactions' ) ? Fanfic_Interactions::get_story_views( $story_id ) : 0;
									$status_terms = wp_get_post_terms( $story_id, 'fanfiction_status' );
									$status_name = ! empty( $status_terms ) ? $status_terms[0]->name : esc_html__( 'Unknown', 'fanfiction-manager' );
									?>
									<tr>
										<td class="fanfic-story-title">
											<?php if ( $is_blocked ) : ?>
												<span><?php the_title(); ?></span>
												<span class="fanfic-badge fanfic-badge-blocked"><?php esc_html_e( 'Blocked', 'fanfiction-manager' ); ?></span>
											<?php else : ?>
												<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
											<?php endif; ?>
											<?php if ( 'publish' !== get_post_status() ) : ?>
												<span class="fanfic-badge fanfic-badge-draft"><?php echo esc_html( ucfirst( get_post_status() ) ); ?></span>
											<?php endif; ?>
											<?php if ( $coauthors_enabled && ! $is_original_author ) : ?>
												<span class="fanfic-badge fanfic-badge-coauthor"><?php esc_html_e( 'Co-author', 'fanfiction-manager' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $is_blocked ? __( 'Blocked', 'fanfiction-manager' ) : $status_name ); ?></td>
										<td><?php echo esc_html( $chapter_count ); ?></td>
										<td><?php echo esc_html( Fanfic_Shortcodes::format_number( $views ) ); ?></td>
										<td>
											<time datetime="<?php echo esc_attr( get_the_modified_time( 'c' ) ); ?>">
												<?php echo esc_html( get_the_modified_time( get_option( 'date_format' ) ) ); ?>
											</time>
										</td>
										<td class="fanfic-story-actions">
											<?php if ( $is_blocked ) : ?>
												<span class="fanfic-button small disabled" data-tooltip="<?php echo esc_attr( fanfic_get_blocked_story_message() ); ?>">
													<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
												</span>
												<span class="fanfic-button small disabled" data-tooltip="<?php echo esc_attr( fanfic_get_blocked_story_message() ); ?>">
													<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
												</span>
												<span class="fanfic-button small danger disabled" data-tooltip="<?php echo esc_attr( fanfic_get_blocked_story_message() ); ?>">
													<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
												</span>
											<?php else : ?>
												<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button small">
													<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
												</a>
												<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ); ?>" class="fanfic-button small">
													<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
												</a>
												<?php if ( $is_original_author && current_user_can( 'delete_fanfiction_story', $story_id ) ) : ?>
													<form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this story and all its chapters? This action cannot be undone.', 'fanfiction-manager' ); ?>');">
														<?php wp_nonce_field( 'fanfic_delete_story_' . $story_id, 'fanfic_delete_story_nonce' ); ?>
														<input type="hidden" name="fanfic_story_id" value="<?php echo esc_attr( $story_id ); ?>" />
														<input type="hidden" name="fanfic_delete_story_submit" value="1" />
														<button type="submit" class="fanfic-button small danger">
															<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
														</button>
													</form>
												<?php endif; ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endwhile; ?>
							</tbody>
						</table>
					</div>

					<?php
					// Pagination
					if ( $query->max_num_pages > 1 ) {
						echo '<div class="fanfic-pagination">';
						echo paginate_links( array(
							'total'   => $query->max_num_pages,
							'current' => $paged,
							'format'  => '?paged=%#%',
							'prev_text' => esc_html__( '&laquo; Previous', 'fanfiction-manager' ),
							'next_text' => esc_html__( 'Next &raquo;', 'fanfiction-manager' ),
						) );
						echo '</div>';
					}
					?>

				<?php else : ?>
					<div class="fanfic-empty-state">
						<p><?php esc_html_e( 'You have not created any stories yet.', 'fanfiction-manager' ); ?></p>
						<a href="<?php echo esc_url( fanfic_get_create_story_url() ); ?>" class="fanfic-button">
							<?php esc_html_e( 'Create Your First Story', 'fanfiction-manager' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<?php wp_reset_postdata(); ?>
		</section>
	</div>

	<!-- Sidebar -->
	<aside class="fanfic-dashboard-sidebar" aria-labelledby="sidebar-heading">
		<h2 id="sidebar-heading" class="screen-reader-text"><?php esc_html_e( 'Dashboard Sidebar', 'fanfiction-manager' ); ?></h2>

		<!-- Notifications -->
		<section class="fanfic-dashboard-widget fanfic-notifications-widget" aria-labelledby="notifications-heading">
			<h3 id="notifications-heading">
				<?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?>
				<?php
				$unread_count = Fanfic_Notifications::get_unread_count( $current_user->ID );
				if ( $unread_count > 0 ) :
					?>
					<span class="fanfic-notification-badge" aria-label="<?php echo esc_attr( sprintf( _n( '%d unread notification', '%d unread notifications', $unread_count, 'fanfiction-manager' ), $unread_count ) ); ?>">
						<?php echo esc_html( $unread_count ); ?>
					</span>
				<?php endif; ?>
			</h3>
			<div class="fanfic-notifications-container">
				<?php
				// Get first page of notifications (10 per page)
				$notifications = Fanfic_Notifications::get_user_notifications( $current_user->ID, true, 10, 0 );

				if ( ! empty( $notifications ) ) :
					?>
					<div class="fanfic-notifications-list">
						<?php foreach ( $notifications as $notification ) : ?>
							<div class="fanfic-notification-item fanfic-notification-<?php echo esc_attr( $notification->type ); ?>" data-notification-id="<?php echo esc_attr( $notification->id ); ?>">
								<div class="fanfic-notification-icon" aria-hidden="true">
									<?php
									// Display different icons based on notification type
									switch ( $notification->type ) {
										case Fanfic_Notifications::TYPE_NEW_COMMENT:
										case Fanfic_Notifications::TYPE_COMMENT_REPLY:
											echo '<span class="dashicons dashicons-admin-comments"></span>';
											break;
										case Fanfic_Notifications::TYPE_NEW_FOLLOWER:
											echo '<span class="dashicons dashicons-heart"></span>';
											break;
										case Fanfic_Notifications::TYPE_NEW_CHAPTER:
										case Fanfic_Notifications::TYPE_NEW_STORY:
										case Fanfic_Notifications::TYPE_STORY_UPDATE:
											echo '<span class="dashicons dashicons-book-alt"></span>';
											break;
										case Fanfic_Notifications::TYPE_COAUTHOR_INVITE:
										case Fanfic_Notifications::TYPE_COAUTHOR_ACCEPTED:
										case Fanfic_Notifications::TYPE_COAUTHOR_REFUSED:
										case Fanfic_Notifications::TYPE_COAUTHOR_REMOVED:
										case Fanfic_Notifications::TYPE_COAUTHOR_DISABLED:
										case Fanfic_Notifications::TYPE_COAUTHOR_ENABLED:
											echo '<span class="dashicons dashicons-groups"></span>';
											break;
										case Fanfic_Notifications::TYPE_FOLLOW_STORY:
											echo '<span class="dashicons dashicons-star-filled"></span>';
											break;
										default:
											echo '<span class="dashicons dashicons-bell"></span>';
											break;
									}
									?>
								</div>
								<div class="fanfic-notification-content">
									<p class="fanfic-notification-message"><?php echo esc_html( $notification->message ); ?></p>
									<time class="fanfic-notification-timestamp" datetime="<?php echo esc_attr( $notification->created_at ); ?>">
										<?php echo esc_html( Fanfic_Notifications::get_relative_time( $notification->created_at ) ); ?>
									</time>
								</div>
								<div class="fanfic-notification-actions">
									<button type="button"
										class="fanfic-notification-dismiss"
										data-notification-id="<?php echo esc_attr( $notification->id ); ?>"
										aria-label="<?php esc_attr_e( 'Dismiss notification', 'fanfiction-manager' ); ?>"
										title="<?php esc_attr_e( 'Dismiss', 'fanfiction-manager' ); ?>">
										<span class="dashicons dashicons-no-alt"></span>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<?php
					// Show pagination if there are more notifications
					$total_notifications = $unread_count;
					if ( $total_notifications > 10 ) :
						$total_pages = min( ceil( $total_notifications / 10 ), 5 ); // Max 50 notifications = 5 pages
						?>
						<div class="fanfic-notifications-pagination">
							<?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?>
								<button type="button"
									class="fanfic-notification-page-button<?php echo ( 1 === $page ) ? ' active' : ''; ?>"
									data-page="<?php echo esc_attr( $page ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Page %d', 'fanfiction-manager' ), $page ) ); ?>">
									<?php echo esc_html( $page ); ?>
								</button>
							<?php endfor; ?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<div class="fanfic-notifications-empty">
						<p><?php esc_html_e( 'No unread notifications', 'fanfiction-manager' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="fanfic-notifications-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading notifications...', 'fanfiction-manager' ); ?></p>
				</div>
			</div>
		</section>

		<!-- Recent Activity -->
		<section class="fanfic-dashboard-widget" aria-labelledby="activity-heading">
			<h3 id="activity-heading"><?php esc_html_e( 'Recent Activity', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-activity-list">
				<?php echo Fanfic_Shortcodes_User::render_reading_history( array( 'limit' => 5 ) ); ?>
			</div>
		</section>

		<!-- Bookmarked Stories -->
		<section class="fanfic-dashboard-widget" aria-labelledby="bookmarks-stories-heading">
			<h3 id="bookmarks-stories-heading"><?php esc_html_e( 'Bookmarked Stories', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-user-bookmarks" data-user-id="<?php echo absint( get_current_user_id() ); ?>" data-bookmark-type="story" data-current-offset="0">
				<div class="fanfic-bookmarks-list">
					<?php
					echo Fanfic_Bookmarks::render_user_bookmarks_dashboard(
						get_current_user_id(),
						'story',
						20,
						0
					);
					?>
				</div>
				<?php
				$total_story_bookmarks = Fanfic_Bookmarks::get_bookmarks_count( get_current_user_id(), 'story' );
				if ( $total_story_bookmarks > 20 ) :
				?>
					<button class="fanfic-load-more-bookmarks" data-offset="20" data-bookmark-type="story">
						<?php esc_html_e( 'Show More', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
				<div class="fanfic-bookmarks-loading" style="display: none;">
					<?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
				</div>
			</div>
		</section>

		<!-- Bookmarked Chapters -->
		<section class="fanfic-dashboard-widget" aria-labelledby="bookmarks-chapters-heading">
			<h3 id="bookmarks-chapters-heading"><?php esc_html_e( 'Bookmarked Chapters', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-user-bookmarks" data-user-id="<?php echo absint( get_current_user_id() ); ?>" data-bookmark-type="chapter" data-current-offset="0">
				<div class="fanfic-bookmarks-list">
					<?php
					echo Fanfic_Bookmarks::render_user_bookmarks_dashboard(
						get_current_user_id(),
						'chapter',
						20,
						0
					);
					?>
				</div>
				<?php
				$total_chapter_bookmarks = Fanfic_Bookmarks::get_bookmarks_count( get_current_user_id(), 'chapter' );
				if ( $total_chapter_bookmarks > 20 ) :
				?>
					<button class="fanfic-load-more-bookmarks" data-offset="20" data-bookmark-type="chapter">
						<?php esc_html_e( 'Show More', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
				<div class="fanfic-bookmarks-loading" style="display: none;">
					<?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
				</div>
			</div>
		</section>
	</aside>
</div>

<!-- Breadcrumb Navigation (Bottom) -->
<?php fanfic_render_breadcrumb( 'dashboard', array( 'position' => 'bottom' ) ); ?>

<!-- Inline Script for Message Dismissal -->
<script>
(function() {
	// Close button functionality for messages
	document.addEventListener('DOMContentLoaded', function() {
		var closeButtons = document.querySelectorAll('.fanfic-message-close');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				var message = this.closest('.fanfic-message');
				if (message) {
					message.style.opacity = '0';
					message.style.transform = 'translateY(-10px)';
					setTimeout(function() {
						message.remove();
					}, 300);
				}
			});
		});
	});
})();
</script>
