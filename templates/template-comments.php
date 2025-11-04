<?php
/**
 * Template for Comments Display
 *
 * Custom comment template with 4-level threaded display and accessibility features.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="comments" class="fanfic-comments-section" role="region" aria-label="<?php esc_attr_e( 'Comments', 'fanfiction-manager' ); ?>">
	<?php if ( have_comments() ) : ?>
		<h2 class="fanfic-comments-title">
			<?php
			$comments_number = get_comments_number();
			printf(
				/* translators: %s: Number of comments */
				esc_html( _n( '%s Comment', '%s Comments', $comments_number, 'fanfiction-manager' ) ),
				esc_html( number_format_i18n( $comments_number ) )
			);
			?>
		</h2>

		<ol class="fanfic-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 64,
					'max_depth'   => 4,
					'callback'    => 'fanfic_custom_comment_template',
				)
			);
			?>
		</ol>

		<?php
		// Comment pagination
		if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
			?>
			<nav class="fanfic-comment-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Comment navigation', 'fanfiction-manager' ); ?>">
				<div class="nav-previous">
					<?php previous_comments_link( __( '&larr; Older Comments', 'fanfiction-manager' ) ); ?>
				</div>
				<div class="nav-next">
					<?php next_comments_link( __( 'Newer Comments &rarr;', 'fanfiction-manager' ) ); ?>
				</div>
			</nav>
		<?php endif; ?>

	<?php endif; ?>

	<?php
	// Display comment form
	if ( comments_open() ) :
		comment_form();
	elseif ( is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) :
		?>
		<p class="fanfic-no-comments">
			<?php esc_html_e( 'Comments are closed for this content.', 'fanfiction-manager' ); ?>
		</p>
	<?php endif; ?>

</div>

<?php
/**
 * Custom comment template callback
 *
 * Displays a single comment with custom HTML structure and accessibility features.
 *
 * @since 1.0.0
 * @param WP_Comment $comment Comment object.
 * @param array      $args    Comment display arguments.
 * @param int        $depth   Comment depth level.
 */
function fanfic_custom_comment_template( $comment, $args, $depth ) {
	$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
	?>
	<<?php echo esc_html( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent', $comment ); ?> role="article" aria-label="<?php echo esc_attr( sprintf( __( 'Comment by %s', 'fanfiction-manager' ), get_comment_author() ) ); ?>">
		<article id="div-comment-<?php comment_ID(); ?>" class="fanfic-comment-body">
			<footer class="fanfic-comment-meta">
				<div class="fanfic-comment-author vcard">
					<?php
					// Display avatar
					if ( 0 !== $args['avatar_size'] ) {
						echo get_avatar(
							$comment,
							$args['avatar_size'],
							'',
							get_comment_author(),
							array( 'class' => 'fanfic-comment-avatar' )
						);
					}
					?>
					<b class="fn" itemprop="author"><?php echo get_comment_author_link( $comment ); ?></b>
					<span class="says screen-reader-text"><?php esc_html_e( 'says:', 'fanfiction-manager' ); ?></span>
				</div>

				<div class="fanfic-comment-metadata">
					<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>" class="fanfic-comment-permalink">
						<time datetime="<?php comment_time( 'c' ); ?>" itemprop="datePublished">
							<?php
							/* translators: 1: Comment date, 2: Comment time */
							printf(
								esc_html__( '%1$s at %2$s', 'fanfiction-manager' ),
								esc_html( get_comment_date( '', $comment ) ),
								esc_html( get_comment_time() )
							);
							?>
						</time>
					</a>

					<?php
					// Display edit stamp if comment was edited
					$edited_at = get_comment_meta( $comment->comment_ID, 'fanfic_edited_at', true );
					if ( $edited_at ) :
						?>
						<span class="fanfic-comment-edited">
							<?php esc_html_e( '(edited)', 'fanfiction-manager' ); ?>
						</span>
					<?php endif; ?>

					<?php
					// Display moderation notice if comment is not approved
					if ( '0' === $comment->comment_approved ) :
						?>
						<p class="fanfic-comment-awaiting-moderation">
							<?php esc_html_e( 'Your comment is awaiting moderation.', 'fanfiction-manager' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</footer>

			<div class="fanfic-comment-content" itemprop="text">
				<?php comment_text(); ?>
			</div>

			<div class="fanfic-comment-actions">
				<?php
				// Reply link
				comment_reply_link(
					array_merge(
						$args,
						array(
							'add_below' => 'div-comment',
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'before'    => '<div class="reply">',
							'after'     => '</div>',
						)
					)
				);

				// Report button - only show to logged-in users who are not the comment author
				if ( is_user_logged_in() && get_current_user_id() !== absint( $comment->user_id ) ) :
					?>
					<button
						type="button"
						class="fanfic-report-btn comment-report-link"
						data-item-id="<?php echo esc_attr( $comment->comment_ID ); ?>"
						data-item-type="comment"
						aria-label="<?php esc_attr_e( 'Report this comment', 'fanfiction-manager' ); ?>"
					>
						<?php esc_html_e( 'Report', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</article>
	<?php
}
