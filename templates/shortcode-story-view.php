<?php
/**
 * Default Shortcode Template: Story View
 *
 * This template is loaded into the database when the plugin is activated.
 * Users can customize this via Admin Settings > Page Templates.
 *
 * Available shortcodes:
 * - [story-title] - The story title
 * - [story-author-link] - Link to the author's profile
 * - [story-status] - Story status (Complete, In Progress, etc.)
 * - [story-featured-image] - Featured image for the story
 * - [story-intro] - Story summary/description
 * - [story-genres] - List of genres
 * - [story-word-count-estimate] - Total word count
 * - [story-chapters] - Number of chapters
 * - [story-views] - View count
 * - [story-rating-form] - Rating form/display
 * - [story-actions] - Action buttons (bookmark, subscribe, etc.)
 * - [edit-story-button] - Edit button (shown to author/admin)
 * - [story-chapters-dropdown] - Dropdown for chapter navigation
 * - [chapters-list] - List of all chapters
 * - [story-comments] - Comments section
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fanfic-story-single">
	<header class="fanfic-story-header">
		<h1>[story-title]</h1>
		<div class="fanfic-story-meta">
			<span class="fanfic-story-author"><?php esc_html_e( 'by', 'fanfiction-manager' ); ?> [story-author-link]</span>
			<span class="fanfic-story-status">[story-status]</span>
		</div>
	</header>

	<div class="fanfic-story-featured-image">
		[story-featured-image]
	</div>

	<div class="fanfic-story-content">
		<div class="fanfic-story-intro">
			<h2><?php esc_html_e( 'Summary', 'fanfiction-manager' ); ?></h2>
			[story-intro]
		</div>

		<div class="fanfic-story-taxonomies">
			<div class="fanfic-story-genres">
				<strong><?php esc_html_e( 'Genres:', 'fanfiction-manager' ); ?></strong> [story-genres]
			</div>
		</div>

		<div class="fanfic-story-stats">
			<span class="fanfic-story-word-count"><strong><?php esc_html_e( 'Words:', 'fanfiction-manager' ); ?></strong> [story-word-count-estimate]</span>
			<span class="fanfic-story-chapters-count"><strong><?php esc_html_e( 'Chapters:', 'fanfiction-manager' ); ?></strong> [story-chapters]</span>
			<span class="fanfic-story-views"><strong><?php esc_html_e( 'Views:', 'fanfiction-manager' ); ?></strong> [story-views]</span>
			<span class="fanfic-story-rating"><strong><?php esc_html_e( 'Rating:', 'fanfiction-manager' ); ?></strong> [story-rating-form]</span>
		</div>
	</div>

	<div class="fanfic-story-actions">
		[story-actions]
		[edit-story-button]
	</div>

	<div class="fanfic-story-navigation">
		<div class="fanfic-story-chapters-dropdown">
			[story-chapters-dropdown]
		</div>
	</div>

	<div class="fanfic-story-chapters-list">
		<h2><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
		[chapters-list]
	</div>

	<div class="fanfic-story-comments">
		[story-comments]
	</div>
</div>
