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
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You must be logged in to view this page.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// Check if user has author capability
if ( ! current_user_can( 'edit_fanfiction_stories' ) ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Access Denied: You do not have permission to view the author dashboard.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
	return;
}

$current_user = wp_get_current_user();
?>

<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>


<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'story_created' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Story created successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'profile_updated' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Profile updated successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<!-- Dashboard Header -->
<header class="fanfic-dashboard-header">
	<div class="fanfic-dashboard-hero">
		<div class="fanfic-dashboard-avatar">
			<?php echo get_avatar( $current_user->ID, 80, '', $current_user->display_name, array( 'class' => 'fanfic-avatar-image', 'loading' => 'lazy' ) ); ?>
		</div>
		<div class="fanfic-dashboard-welcome">
			<h1 class="fanfic-dashboard-title">
				<?php
				/* translators: %s: User display name */
				printf( esc_html__( 'Welcome back, %s!', 'fanfiction-manager' ), esc_html( $current_user->display_name ) );
				?>
			</h1>
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
				<p class="fanfic-stat-value"><?php echo do_shortcode( '[author-story-count]' ); ?></p>
			</div>
		</div>

		<!-- Total Chapters -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-media-document"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Total Chapters', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo do_shortcode( '[author-total-chapters]' ); ?></p>
			</div>
		</div>

		<!-- Total Views -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Total Views', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo do_shortcode( '[author-total-views]' ); ?></p>
			</div>
		</div>

		<!-- Stories Following -->
		<div class="fanfic-stat-card">
			<div class="fanfic-stat-icon" aria-hidden="true">
				<span class="dashicons dashicons-heart"></span>
			</div>
			<div class="fanfic-stat-content">
				<h3 class="fanfic-stat-label"><?php esc_html_e( 'Stories Following', 'fanfiction-manager' ); ?></h3>
				<p class="fanfic-stat-value"><?php echo do_shortcode( '[user-favorites-count]' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- Quick Actions -->
<section class="fanfic-dashboard-actions" aria-labelledby="actions-heading">
	<h2 id="actions-heading"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>

	<div class="fanfic-actions-grid">
		<a href="<?php echo esc_url( fanfic_get_create_story_url() ); ?>" class="fanfic-action-button fanfic-action-primary">
			<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></span>
		</a>

		<a href="<?php echo esc_url( fanfic_get_dashboard_url() . '#my-stories' ); ?>" class="fanfic-action-button fanfic-action-secondary">
			<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
			<span><?php esc_html_e( 'View My Stories', 'fanfiction-manager' ); ?></span>
		</a>

		<a href="<?php echo esc_url( fanfic_get_edit_profile_url() ); ?>" class="fanfic-action-button fanfic-action-secondary">
			<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Edit Profile', 'fanfiction-manager' ); ?></span>
		</a>
	</div>
</section>

<!-- Main Dashboard Content -->
<div class="fanfic-dashboard-main">
	<div class="fanfic-dashboard-primary">
		<!-- Manage Stories Section -->
		<section class="fanfic-dashboard-stories" id="my-stories" aria-labelledby="stories-heading">
			<h2 id="stories-heading"><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>
			<?php echo do_shortcode( '[author-stories-manage]' ); ?>
		</section>
	</div>

	<!-- Sidebar -->
	<aside class="fanfic-dashboard-sidebar" aria-labelledby="sidebar-heading">
		<h2 id="sidebar-heading" class="screen-reader-text"><?php esc_html_e( 'Dashboard Sidebar', 'fanfiction-manager' ); ?></h2>

		<!-- Notifications -->
		<section class="fanfic-dashboard-widget" aria-labelledby="notifications-heading">
			<h3 id="notifications-heading"><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-notifications-placeholder">
				<p><?php esc_html_e( 'Notifications system coming soon! We will implement notifications for:', 'fanfiction-manager' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'New chapters added to bookmarked stories', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Updates from bookmarked authors', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'New comments on your stories', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'New ratings on your stories', 'fanfiction-manager' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- Recent Activity -->
		<section class="fanfic-dashboard-widget" aria-labelledby="activity-heading">
			<h3 id="activity-heading"><?php esc_html_e( 'Recent Activity', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-activity-list">
				<?php echo do_shortcode( '[user-reading-history limit="5"]' ); ?>
			</div>
		</section>

		<!-- Bookmarked Stories -->
		<section class="fanfic-dashboard-widget" aria-labelledby="bookmarked-stories-heading">
			<h3 id="bookmarked-stories-heading"><?php esc_html_e( 'Bookmarked Stories', 'fanfiction-manager' ); ?></h3>
			<?php echo do_shortcode( '[most-bookmarked-stories limit="5" timeframe="week"]' ); ?>
		</section>

		<!-- Bookmarked Authors -->
		<section class="fanfic-dashboard-widget" aria-labelledby="bookmarked-authors-heading">
			<h3 id="bookmarked-authors-heading"><?php esc_html_e( 'Bookmarked Authors', 'fanfiction-manager' ); ?></h3>
			<?php echo do_shortcode( '[most-followed-authors limit="5" timeframe="week"]' ); ?>
		</section>
	</aside>
</div>

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

<!-- Inline Script for Notice Dismissal -->
<script>
(function() {
	// Close button functionality for notices
	document.addEventListener('DOMContentLoaded', function() {
		var closeButtons = document.querySelectorAll('.fanfic-notice-close');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				var notice = this.closest('.fanfic-success-notice, .fanfic-error-notice');
				if (notice) {
					notice.style.display = 'none';
				}
			});
		});
	});
})();
</script>


</div>
