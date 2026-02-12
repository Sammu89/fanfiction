<?php
/**
 * Template: View User Profile
 *
 * This template contains two parts:
 * 1. Default content function (for admin settings)
 * 2. PHP logic and rendering (for frontend display)
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get default user-editable content template
 *
 * This function returns the default HTML/shortcode template.
 * This content gets saved to the database and is editable by users.
 * This function can be called safely from admin settings.
 *
 * @return string Default template HTML
 */
function fanfic_get_default_profile_view_template() {
	ob_start();
	?>
<div class="fanfic-profile-single">
	<header class="fanfic-profile-header">
		<div class="fanfic-profile-avatar">
			[author-avatar]
		</div>
		<div class="fanfic-profile-info">
			<h1>[author-display-name]</h1>
			<div class="fanfic-profile-meta">
				<span class="fanfic-profile-joined">
					<?php esc_html_e( 'Joined:', 'fanfiction-manager' ); ?> [author-registration-date]
				</span>
				<span class="fanfic-profile-stories">
					<?php esc_html_e( 'Stories:', 'fanfiction-manager' ); ?> [author-story-count]
				</span>
			</div>
		</div>
	</header>

	<div class="fanfic-profile-actions">
		[fanfiction-action-buttons]
	</div>

	<div class="fanfic-profile-bio">
		<h2><?php esc_html_e( 'About', 'fanfiction-manager' ); ?></h2>
		[author-bio]
	</div>

	<div class="fanfic-profile-stories">
		<h2><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></h2>
		[author-story-list]
	</div>

	<div class="fanfic-profile-coauthored-stories">
		[author-coauthored-stories]
	</div>
</div>
<?php
	return ob_get_clean();
}

// Stop here if we're just loading the function definition (e.g., in admin settings)
// The rest of this file is the actual template rendering logic
if ( ! isset( $fanfic_load_template ) || ! $fanfic_load_template ) {
	return;
}

/**
 * =========================================
 * TEMPLATE RENDERING (Frontend Only)
 * =========================================
 * This section only runs when displaying the profile on frontend.
 */

// Get the user being viewed
$member_name = get_query_var( 'member_name' );
$user = get_user_by( 'login', $member_name );

if ( ! $user ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'User not found.', 'fanfiction-manager' ); ?></span>
	</div>
	<?php
	return;
}

// Load user-customized template from database, or use default
$template = get_option( 'fanfic_shortcode_profile_view', '' );

if ( empty( $template ) ) {
	$template = fanfic_get_default_profile_view_template();
}

// Replace shortcodes with user_id parameter versions for proper rendering
// This allows shortcodes to work without needing explicit user_id attributes
$user_id = $user->ID;
$template = str_replace( '[author-avatar]', '[author-avatar author_id="' . $user_id . '"]', $template );
$template = str_replace( '[author-display-name]', '[author-display-name author_id="' . $user_id . '"]', $template );
$template = str_replace( '[author-registration-date]', '[author-registration-date author_id="' . $user_id . '"]', $template );
$template = str_replace( '[author-story-count]', '[author-story-count author_id="' . $user_id . '"]', $template );
// [content-actions] auto-detects context, no user_id parameter needed
$template = str_replace( '[author-bio]', '[author-bio author_id="' . $user_id . '"]', $template );
$template = str_replace( '[author-story-list]', '[author-story-list author_id="' . $user_id . '"]', $template );
$template = str_replace( '[author-coauthored-stories]', '[author-coauthored-stories author_id="' . $user_id . '"]', $template );

// Process shortcodes in the template
echo do_shortcode( $template );
