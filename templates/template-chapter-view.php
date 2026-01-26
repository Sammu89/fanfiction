<?php
/**
 * Template for single chapter display
 *
 * This template contains two parts:
 * 1. Default content function (for admin settings)
 * 2. PHP logic and rendering (for frontend display)
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

// Security check - prevent direct access
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
function fanfic_get_default_chapter_view_template() {
	ob_start();
	?>
<!-- Breadcrumb navigation for context -->
[fanfic-breadcrumbs]

<!-- Chapter header with hierarchical titles -->
<header class="fanfic-chapter-header">
	<!-- Story title as primary heading (parent context) -->
	[fanfic-story-title]

	<!-- Chapter title as secondary heading -->
	<h2 class="fanfic-chapter-title">[fanfic-chapter-title]</h2>

	<!-- Chapter Cover Image -->
	[fanfic-chapter-image class="fanfic-chapter-cover-image"]

	<!-- Meta information (dates) -->
	<div class="fanfic-chapter-meta">
		<span class="fanfic-published">
			<?php esc_html_e( 'Published:', 'fanfiction-manager' ); ?> [fanfic-chapter-published]
		</span>
		<?php
		// Only show "Updated:" label if there's been an actual update
		$chapter_id = get_the_ID();
		if ( $chapter_id ) {
			$published_timestamp = get_post_time( 'U', false, $chapter_id );
			$modified_timestamp  = get_post_modified_time( 'U', false, $chapter_id );

			// Only show if modified date is different from published date (more than 1 day difference)
			if ( abs( $modified_timestamp - $published_timestamp ) >= DAY_IN_SECONDS ) {
				?>
				<span class="fanfic-updated">
					<?php esc_html_e( 'Updated:', 'fanfiction-manager' ); ?> [fanfic-chapter-updated]
				</span>
				<?php
			}
		}
		?>
	</div>
</header>

<!-- Main chapter content -->
<div class="fanfic-chapter-content" itemprop="text">
	[fanfic-chapter-content]
</div>

<!-- Visual separator -->
<hr class="fanfic-content-separator" aria-hidden="true">

<!-- Rating section -->
<section class="fanfic-chapter-rating" aria-labelledby="rating-heading">
	<h3 id="rating-heading"><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
	[chapter-rating-form]
</section>

<!-- Action buttons (like, bookmark, mark-read, subscribe, share, report, edit) -->
<div class="fanfic-chapter-actions">
	[fanfiction-action-buttons context="chapter" actions="follow,like,bookmark,mark-read,subscribe,share,report"]
</div>

<!-- Chapter navigation (previous/next) -->
<nav class="fanfic-chapter-navigation" aria-label="<?php esc_attr_e( 'Chapter navigation', 'fanfiction-manager' ); ?>">
	[chapters-nav]
</nav>

<!-- Comments section -->
<section class="fanfic-chapter-comments" aria-labelledby="comments-heading">
	<h3 id="comments-heading"><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h3>
	[chapter-comments]
</section>
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
 * This section only runs when displaying the chapter on frontend.
 * It handles filters, permissions, and rendering.
 *
 * Note: This template is loaded through fanfiction-page-template.php,
 * so get_header() and get_footer() are NOT needed here.
 */

// Check if parent story is draft and user has permission to view
$chapter_post = get_post();
if ( $chapter_post && 'fanfiction_chapter' === $chapter_post->post_type ) {
	$story_id = $chapter_post->post_parent;

	if ( $story_id ) {
		$story = get_post( $story_id );

		// If story is draft, check if user has permission
		if ( $story && 'draft' === $story->post_status ) {
			// Check if user can edit this story (uses your custom role permissions)
			if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
				// Show access denied message
				?>
				<div class="fanfic-content-wrapper fanfic-fullpage-message">
					<div class="fanfic-message fanfic-message-error fanfic-message-fullpage" role="alert" aria-live="assertive">
						<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
						<span class="fanfic-message-content">
							<strong class="fanfic-message-title"><?php esc_html_e( 'Access Denied', 'fanfiction-manager' ); ?></strong>
							<span class="fanfic-message-text"><?php esc_html_e( 'This chapter is part of a story that is currently in draft status and not publicly available.', 'fanfiction-manager' ); ?></span>
							<span class="fanfic-message-actions">
								<a href="<?php echo esc_url( home_url( '/fanfiction/' ) ); ?>" class="fanfic-button fanfic-button-primary">
									<?php esc_html_e( 'Back to Stories', 'fanfiction-manager' ); ?>
								</a>
							</span>
						</span>
					</div>
				</div>
				<?php
				return;
			}
		}
	}
}

// Load user-customized template from database, or use default
$template = get_option( 'fanfic_shortcode_chapter_view', '' );

if ( empty( $template ) ) {
	$template = fanfic_get_default_chapter_view_template();
}

// Show a discreet warning if this chapter or its parent story is not published
if ( $chapter_post && 'fanfiction_chapter' === $chapter_post->post_type ) {
	$warning_parts = array();

	if ( 'publish' !== $chapter_post->post_status ) {
		$chapter_status_obj = get_post_status_object( $chapter_post->post_status );
		$chapter_status_label = $chapter_status_obj && ! empty( $chapter_status_obj->label ) ? $chapter_status_obj->label : $chapter_post->post_status;
		$warning_parts[] = sprintf(
			esc_html__( 'this chapter is %s', 'fanfiction-manager' ),
			esc_html( $chapter_status_label )
		);
	}

	if ( ! empty( $story ) && 'publish' !== $story->post_status ) {
		$story_status_obj = get_post_status_object( $story->post_status );
		$story_status_label = $story_status_obj && ! empty( $story_status_obj->label ) ? $story_status_obj->label : $story->post_status;
		$warning_parts[] = sprintf(
			esc_html__( 'the parent story is %s', 'fanfiction-manager' ),
			esc_html( $story_status_label )
		);
	}

	if ( ! empty( $warning_parts ) ) {
		$text = sprintf(
			esc_html__( 'This chapter is not visible to the public because %s.', 'fanfiction-manager' ),
			esc_html( implode( esc_html__( ' and ', 'fanfiction-manager' ), $warning_parts ) )
		);

		// Normalize case
		$text = mb_strtolower( $text, 'UTF-8' );
		$text = mb_strtoupper( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' );
		?>
		<div class="fanfic-message fanfic-message-warning fanfic-draft-warning" role="status" aria-live="polite">
			<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
			<span class="fanfic-message-content"><?php echo $text; ?></span>
		</div>
		<?php
	}
}

// Process shortcodes in the template
echo do_shortcode( $template );
