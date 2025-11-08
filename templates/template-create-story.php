<div class="fanfic-template-wrapper">
<?php
/**
 * Template Name: Create Story
 * Description: Form for creating a new fanfiction story
 *
 * This template displays:
 * - Story creation form
 * - Help section with tips
 * - Success/error messages
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
		<p><?php esc_html_e( 'You must be logged in to create a story.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
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
		<p><?php esc_html_e( 'Access Denied: You do not have permission to create stories.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
	return;
}
?>

<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>



<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<?php
	$story_id = isset( $_GET['story_id'] ) ? absint( $_GET['story_id'] ) : 0;
	$story_url = $story_id ? get_permalink( $story_id ) : '';
	?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p>
			<?php esc_html_e( 'Story created successfully!', 'fanfiction-manager' ); ?>
			<?php if ( $story_id && $story_url ) : ?>
				<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-link-primary">
					<?php esc_html_e( 'Edit it here', 'fanfiction-manager' ); ?>
				</a>
				<?php esc_html_e( 'or', 'fanfiction-manager' ); ?>
			<?php endif; ?>
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-link-primary">
				<?php esc_html_e( 'View all your stories', 'fanfiction-manager' ); ?>
			</a>
		</p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<!-- Page Header -->
<header class="fanfic-page-header">
	<h1 class="fanfic-page-title"><?php esc_html_e( 'Create a New Story', 'fanfiction-manager' ); ?></h1>
	<p class="fanfic-page-description">
		<?php esc_html_e( 'Tell us about your story! Fill out the form below to get started.', 'fanfiction-manager' ); ?>
	</p>
</header>

<!-- Main Content Area -->
<div class="fanfic-content-layout">
	<!-- Story Creation Form -->
	<div class="fanfic-content-primary">
		<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="form-heading">
			<h2 id="form-heading" class="screen-reader-text"><?php esc_html_e( 'Story Creation Form', 'fanfiction-manager' ); ?></h2>

			<!-- Info Box -->
			<div class="fanfic-info-box" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<p>
					<?php esc_html_e( 'All fields marked with an asterisk (*) are required. Your story will be saved as a draft until you add at least one chapter.', 'fanfiction-manager' ); ?>
				</p>
			</div>

			<!-- Form Shortcode -->
			<?php echo Fanfic_Shortcodes_Author_Forms::render_story_form(0); ?>
		</section>
	</div>

	<!-- Help Sidebar -->
	<aside class="fanfic-content-sidebar" aria-labelledby="help-heading">
		<h2 id="help-heading"><?php esc_html_e( 'Tips & Guidelines', 'fanfiction-manager' ); ?></h2>

		<!-- Tips for Good Story Titles -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="title-tips-heading">
			<h3 id="title-tips-heading">
				<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
				<?php esc_html_e( 'Tips for Good Story Titles', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Make it memorable and unique', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it concise (50-100 characters)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Avoid generic titles like "Untitled"', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Consider including keywords from your story', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Tips for Writing Good Introductions -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="intro-tips-heading">
			<h3 id="intro-tips-heading">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Writing Good Story Descriptions', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Hook readers with the first sentence', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Summarize the main plot without spoilers', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Mention key themes or genres', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it between 100-300 words', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Genre Information -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="genre-tips-heading">
			<h3 id="genre-tips-heading">
				<span class="dashicons dashicons-category" aria-hidden="true"></span>
				<?php esc_html_e( 'Understanding Genres', 'fanfiction-manager' ); ?>
			</h3>
			<p class="fanfic-help-text">
				<?php esc_html_e( 'Genres help readers find stories they\'ll enjoy. You can select multiple genres that fit your story.', 'fanfiction-manager' ); ?>
			</p>
			<ul class="fanfic-help-list">
				<li><strong><?php esc_html_e( 'Romance:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Focus on relationships and love', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Adventure:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Action-packed journeys', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Drama:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Emotional and character-driven', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Mystery:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Puzzles and suspense', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Status Options -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="status-tips-heading">
			<h3 id="status-tips-heading">
				<span class="dashicons dashicons-flag" aria-hidden="true"></span>
				<?php esc_html_e( 'Story Status Options', 'fanfiction-manager' ); ?>
			</h3>
			<dl class="fanfic-help-definitions">
				<dt><?php esc_html_e( 'Ongoing:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Actively being written and updated', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'Finished:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Story is complete', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'On Hiatus:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Temporarily paused', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'Abandoned:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'No longer being updated', 'fanfiction-manager' ); ?></dd>
			</dl>
		</section>

		<!-- Back to Dashboard Link -->
		<div class="fanfic-help-footer">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button-link">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</div>
	</aside>
</div>

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
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
