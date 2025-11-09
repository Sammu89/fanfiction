<?php
/**
 * Default Shortcode Template: Chapter View
 *
 * This template is loaded into the database when the plugin is activated.
 * Users can customize this via Admin Settings > Page Templates.
 *
 * Available shortcodes:
 * - [chapter-breadcrumb] - Breadcrumb navigation
 * - [chapter-title] - Chapter title
 * - [chapter-story] - Link to parent story
 * - [chapters-nav] - Previous/Next chapter navigation
 * - [chapter-actions] - Action buttons (bookmark, subscribe, etc.)
 * - [edit-chapter-button] - Edit button (shown to author/admin)
 * - [chapter-rating-form] - Rating form for this chapter
 * - [chapter-comments] - Comments section
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fanfic-chapter-single">
	<nav class="fanfic-chapter-breadcrumb">
		[chapter-breadcrumb]
	</nav>

	<header class="fanfic-chapter-header">
		<h1 class="fanfic-chapter-title">[chapter-title]</h1>
		<div class="fanfic-chapter-meta">
			<span class="fanfic-chapter-story">[chapter-story]</span>
		</div>
	</header>

	<nav class="fanfic-chapter-navigation chapter-navigation-top">
		[chapters-nav]
	</nav>

	<div class="fanfic-chapter-actions">
		[chapter-actions]
		[edit-chapter-button]
	</div>

	<div class="fanfic-chapter-content">
		<!-- Chapter content is automatically displayed here -->
	</div>

	<div class="fanfic-chapter-rating">
		<h3><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
		[chapter-rating-form]
	</div>

	<nav class="fanfic-chapter-navigation chapter-navigation-bottom">
		[chapters-nav]
	</nav>

	<div class="fanfic-chapter-comments">
		<h2><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h2>
		[chapter-comments]
	</div>
</div>
