<?php
/**
 * Default Shortcode Template: Profile View
 *
 * This template is loaded into the database when the plugin is activated.
 * Users can customize this via Admin Settings > Page Templates.
 *
 * Available shortcodes:
 * - [author-avatar] - User's avatar/profile picture
 * - [author-display-name] - User's display name
 * - [author-joined-date] - Registration date
 * - [author-story-count] - Number of stories published
 * - [author-actions] - Action buttons (follow, message, etc.)
 * - [edit-profile-button] - Edit button (shown to profile owner/admin)
 * - [author-bio] - User's biography/about section
 * - [author-story-list] - List of user's stories
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fanfic-profile-single">
	<header class="fanfic-profile-header">
		<div class="fanfic-profile-avatar">
			[author-avatar]
		</div>
		<div class="fanfic-profile-info">
			<h1>[author-display-name]</h1>
			<div class="fanfic-profile-meta">
				<span class="fanfic-profile-joined"><?php esc_html_e( 'Joined:', 'fanfiction-manager' ); ?> [author-joined-date]</span>
				<span class="fanfic-profile-stories"><?php esc_html_e( 'Stories:', 'fanfiction-manager' ); ?> [author-story-count]</span>
			</div>
		</div>
	</header>

	<div class="fanfic-profile-actions">
		[author-actions]
		[edit-profile-button]
	</div>

	<div class="fanfic-profile-bio">
		<h2><?php esc_html_e( 'About', 'fanfiction-manager' ); ?></h2>
		[author-bio]
	</div>

	<div class="fanfic-profile-stories">
		<h2><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></h2>
		[author-story-list]
	</div>
</div>
