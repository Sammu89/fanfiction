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
<!-- Chapter header with hierarchical titles -->
<header class="fanfic-chapter-header">
	<!-- Chapter title as primary heading with bookmark + read badges -->
	<h1 class="fanfic-chapter-title">[fanfic-chapter-title with-badge]</h1>

	<!-- Story context: title link + author (secondary) -->
	[fanfic-story-title]

	<!-- Chapter Cover Image -->
[fanfic-chapter-image]

	<!-- Meta information (dates) -->
	<div class="fanfic-chapter-meta">
		<span class="fanfic-published">
			<?php esc_html_e( 'Visible since:', 'fanfiction-manager' ); ?> [fanfic-chapter-published]
		</span>
		<?php
		// Only show "Updated:" label if there's been an actual update
		$chapter_id = get_the_ID();
		if ( $chapter_id ) {
			$published_timestamp = get_post_time( 'U', false, $chapter_id );
			$updated_datetime    = fanfic_get_chapter_content_updated_date( $chapter_id );
			$modified_timestamp  = $updated_datetime ? strtotime( $updated_datetime ) : false;

			// Only show if modified date is different from published date (more than 1 day difference)
			if ( false !== $modified_timestamp && abs( $modified_timestamp - $published_timestamp ) >= DAY_IN_SECONDS ) {
				?>
				<span class="fanfic-updated">
					<?php esc_html_e( 'Updated:', 'fanfiction-manager' ); ?> [fanfic-chapter-updated]
				</span>
				<?php
			}
		}
		?>
	</div>

	[chapter-translations]
</header>

<!-- Main chapter content -->
<div class="fanfic-chapter-content" itemprop="text">
	[fanfic-chapter-content]
</div>
<!-- Visual separator -->
<hr class="fanfic-content-separator" aria-hidden="true">
<!-- Rating & Like/Dislike section -->
<section class="fanfic-chapter-rating" aria-labelledby="rating-heading">
	<h3 id="rating-heading"><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
	<div class="fanfic-rating-row">
		<div class="fanfic-rating-left">[chapter-rating-form]</div>
		<div class="fanfic-rating-right">[fanfic-like-dislike]</div>
	</div>
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

		// If story is hidden, check if user has permission to view restricted content.
		if ( $story && 'publish' !== $story->post_status ) {
			if ( ! fanfic_current_user_can_view_post( $chapter_post->ID ) ) {
				// Show access denied message
				?>
				<div class="fanfic-content-wrapper fanfic-fullpage-message">
					<div class="fanfic-message fanfic-message-error fanfic-message-fullpage" role="alert" aria-live="assertive">
						<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
						<span class="fanfic-message-content">
							<strong class="fanfic-message-title"><?php esc_html_e( 'Access Denied', 'fanfiction-manager' ); ?></strong>
							<span class="fanfic-message-text"><?php esc_html_e( 'This chapter is part of a story that is not currently visible to the public.', 'fanfiction-manager' ); ?></span>
							<span class="fanfic-message-actions">
								<a href="<?php echo esc_url( home_url( '/fanfiction/' ) ); ?>" class="fanfic-button">
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

$enable_comments = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_comments', true ) : true;
if ( ! $enable_comments ) {
	$template = preg_replace( '/<section[^>]*fanfic-chapter-comments[^>]*>.*?<\/section>/is', '', (string) $template );
	$template = str_replace( '[chapter-comments]', '', (string) $template );
}
$template = str_replace( '[fanfiction-action-buttons]', '', (string) $template );
$template = preg_replace( '/<div[^>]*fanfic-chapter-actions[^>]*>\s*<\/div>/is', '', (string) $template );

$parent_story_id      = $chapter_post ? absint( $chapter_post->post_parent ) : 0;
$is_chapter_author    = false;
$current_user_id      = get_current_user_id();
if ( $chapter_post && is_user_logged_in() ) {
	$is_chapter_author = (int) $chapter_post->post_author === $current_user_id;
	if ( ! $is_chapter_author && $parent_story_id && function_exists( 'fanfic_user_is_story_author_or_coauthor' ) ) {
		$is_chapter_author = fanfic_user_is_story_author_or_coauthor( $parent_story_id, $current_user_id );
	}
}
$parent_story_blocked = $parent_story_id ? fanfic_is_story_blocked( $parent_story_id ) : false;
$chapter_blocked      = $chapter_post ? fanfic_is_chapter_blocked( $chapter_post->ID ) : false;
$parent_story         = $parent_story_id ? get_post( $parent_story_id ) : null;

add_action( 'fanfic_page_alerts', function( $context ) use ( $chapter_post, $is_chapter_author, $parent_story_id, $parent_story_blocked, $chapter_blocked, $parent_story ) {
	if ( 'view-chapter' !== $context || ! $chapter_post || 'fanfiction_chapter' !== $chapter_post->post_type ) {
		return;
	}
	if ( $is_chapter_author && $parent_story_blocked && function_exists( 'fanfic_render_restriction_notice' ) ) {
		fanfic_render_restriction_notice(
			'story',
			$parent_story_id,
			'view-chapter',
			array(
				array( 'label' => __( 'Back to Dashboard', 'fanfiction-manager' ), 'url' => fanfic_get_dashboard_url() ),
			)
		);
	} elseif ( $is_chapter_author && $chapter_blocked && function_exists( 'fanfic_render_restriction_notice' ) ) {
		fanfic_render_restriction_notice(
			'chapter',
			$chapter_post->ID,
			'view-chapter',
			array(
				array( 'label' => __( 'Back to Story', 'fanfiction-manager' ), 'url' => get_permalink( $parent_story_id ) ),
			)
		);
	} else {
		$warning_parts = array();

		if ( 'publish' !== $chapter_post->post_status ) {
			$chapter_status_labels = array(
				'publish' => __( 'Visible', 'fanfiction-manager' ),
				'draft'   => __( 'Hidden', 'fanfiction-manager' ),
			);
			$chapter_status_obj   = get_post_status_object( $chapter_post->post_status );
			$chapter_status_label = isset( $chapter_status_labels[ $chapter_post->post_status ] )
				? $chapter_status_labels[ $chapter_post->post_status ]
				: ( $chapter_status_obj && ! empty( $chapter_status_obj->label ) ? $chapter_status_obj->label : $chapter_post->post_status );
			$warning_parts[] = sprintf(
				esc_html__( 'this chapter is %s', 'fanfiction-manager' ),
				esc_html( $chapter_status_label )
			);
		}

		if ( $chapter_blocked ) {
			$warning_parts[] = esc_html__( 'this chapter is blocked', 'fanfiction-manager' );
		}

		if ( $parent_story && 'publish' !== $parent_story->post_status ) {
			$story_status_labels = array(
				'publish' => __( 'Visible', 'fanfiction-manager' ),
				'draft'   => __( 'Hidden', 'fanfiction-manager' ),
			);
			$story_status_obj   = get_post_status_object( $parent_story->post_status );
			$story_status_label = isset( $story_status_labels[ $parent_story->post_status ] )
				? $story_status_labels[ $parent_story->post_status ]
				: ( $story_status_obj && ! empty( $story_status_obj->label ) ? $story_status_obj->label : $parent_story->post_status );
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
} );

fanfic_render_page_header( 'view-chapter', array(
	'story_id'   => $parent_story_id,
	'chapter_id' => $chapter_post ? absint( $chapter_post->ID ) : 0,
) );
fanfic_render_moderation_controls( 'view-chapter', array(
	'story_id'   => $parent_story_id,
	'chapter_id' => $chapter_post ? absint( $chapter_post->ID ) : 0,
) );
fanfic_render_dynamic_action_buttons();

// Process shortcodes in the template
$rendered_template = do_shortcode( $template );
echo function_exists( 'fanfic_wrap_story_age_confirmation_gate' )
	? fanfic_wrap_story_age_confirmation_gate( $rendered_template, $parent_story_id, 'chapter' )
	: $rendered_template;
