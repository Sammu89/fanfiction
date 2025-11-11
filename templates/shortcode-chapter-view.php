<?php
/**
 * Default Shortcode Template: Chapter View
 *
 * This template is loaded into the database when the plugin is activated.
 * Users can customize this via Admin Settings > Page Templates.
 *
 * Available shortcodes:
 * - [fanfic-breadcrumbs] - Breadcrumb navigation
 * - [fanfic-story-title] - Parent story title
 * - [fanfic-chapter-title] - Chapter title
 * - [fanfic-chapter-published] - Publication date
 * - [fanfic-chapter-updated] - Last updated date (if applicable)
 * - [chapter-actions] - Action buttons (edit, bookmark, share, report)
 * - [fanfic-chapter-content] - Chapter content
 * - [chapter-rating-form] - Rating form for this chapter
 * - [chapters-nav] - Previous/Next chapter navigation
 * - [chapter-comments] - Comments section
 *
 * Note: This template renders inside <div class="entry-content">
 * which is inside an <article> tag provided by the page template.
 *
 * @package FanfictionManager
 * @since 1.0.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Breadcrumb navigation for context -->
[fanfic-breadcrumbs]

<!-- Chapter header with hierarchical titles -->
<header class="fanfic-chapter-header">
	<!-- Story title as primary heading (parent context) -->
	<h1 class="fanfic-story-title">[fanfic-story-title]</h1>

	<!-- Chapter title as secondary heading -->
	<h2 class="fanfic-chapter-title">[fanfic-chapter-title]</h2>

	<!-- Meta information (dates) -->
	<div class="fanfic-chapter-meta">
		[fanfic-chapter-published]
		[fanfic-chapter-updated]
	</div>
</header>

<!-- Main chapter content -->
<div class="fanfic-chapter-content" itemprop="text">
	[fanfic-chapter-content]
</div>

<!-- Visual separator -->
<hr class="fanfic-content-separator" aria-hidden="true">

<!-- Action buttons (edit, bookmark, share, report) -->
[chapter-actions]

<!-- Rating section -->
<section class="fanfic-chapter-rating" aria-labelledby="rating-heading">
	<h3 id="rating-heading"><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
	[chapter-rating-form]
</section>

<!-- Chapter navigation (previous/next) -->
<nav class="fanfic-chapter-navigation" aria-label="<?php esc_attr_e( 'Chapter navigation', 'fanfiction-manager' ); ?>">
	[chapters-nav]
</nav>

<!-- Comments section -->
<section class="fanfic-chapter-comments" aria-labelledby="comments-heading">
	<h3 id="comments-heading"><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h3>
	[chapter-comments]
</section>
