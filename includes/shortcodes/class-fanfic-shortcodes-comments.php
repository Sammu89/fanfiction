<?php
/**
 * Comments Shortcodes Class
 *
 * Handles all comment-related shortcodes for displaying comments, comment forms, and counts.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Comments
 *
 * Comment display and interaction shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Comments {

	/**
	 * Register comment shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'comments-list', array( __CLASS__, 'comments_list' ) );
		add_shortcode( 'comment-form', array( __CLASS__, 'comment_form' ) );
		add_shortcode( 'comment-count', array( __CLASS__, 'comment_count' ) );
		add_shortcode( 'comments-section', array( __CLASS__, 'comments_section' ) );
		add_shortcode( 'story-comments', array( __CLASS__, 'story_comments' ) );
		add_shortcode( 'chapter-comments', array( __CLASS__, 'chapter_comments' ) );
		add_shortcode( 'story-comments-count', array( __CLASS__, 'story_comments_count' ) );
		add_shortcode( 'chapter-comments-count', array( __CLASS__, 'chapter_comments_count' ) );
	}

	/**
	 * Display threaded comments list
	 *
	 * [comments-list]
	 * [comments-list post_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Comments list HTML.
	 */
	public static function comments_list( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'post_id'   => 0,
				'max_depth' => 4,
			),
			'comments-list'
		);

		// Get post ID
		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No post specified for comments.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Check if comments are open
		if ( ! comments_open( $post_id ) ) {
			return '<p class="fanfic-no-comments">' .
				esc_html__( 'Comments are closed for this content.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Get comments
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);

		if ( empty( $comments ) ) {
			return '<p class="fanfic-no-comments">' .
				esc_html__( 'No comments yet. Be the first to comment!', 'fanfiction-manager' ) .
				'</p>';
		}

		ob_start();
		?>
		<div class="fanfic-comments-list-wrapper">
			<h3 class="fanfic-comments-title">
				<?php
				printf(
					/* translators: %s: Number of comments */
					esc_html( _n( '%s Comment', '%s Comments', count( $comments ), 'fanfiction-manager' ) ),
					esc_html( number_format_i18n( count( $comments ) ) )
				);
				?>
			</h3>

			<ol class="fanfic-comment-list">
				<?php
				wp_list_comments(
					array(
						'style'       => 'ol',
						'short_ping'  => true,
						'avatar_size' => 64,
						'max_depth'   => absint( $atts['max_depth'] ),
						'callback'    => 'fanfic_custom_comment_template',
					),
					$comments
				);
				?>
			</ol>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display comment form
	 *
	 * [comment-form]
	 * [comment-form post_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Comment form HTML.
	 */
	public static function comment_form( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'post_id' => 0,
			),
			'comment-form'
		);

		// Get post ID
		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No post specified for comments.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Check if comments are open
		if ( ! comments_open( $post_id ) ) {
			return '<p class="fanfic-no-comments">' .
				esc_html__( 'Comments are closed for this content.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Check if user must be logged in
		if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
			return '<p class="must-log-in">' .
				sprintf(
					/* translators: %s: Login URL */
					__( 'You must be <a href="%s">logged in</a> to post a comment.', 'fanfiction-manager' ),
					esc_url( wp_login_url( get_permalink( $post_id ) ) )
				) .
				'</p>';
		}

		ob_start();
		comment_form(
			array(
				'post_id'                => $post_id,
				'title_reply'            => __( 'Leave a Comment', 'fanfiction-manager' ),
				'title_reply_to'         => __( 'Reply to %s', 'fanfiction-manager' ),
				'cancel_reply_link'      => __( 'Cancel Reply', 'fanfiction-manager' ),
				'label_submit'           => __( 'Post Comment', 'fanfiction-manager' ),
				'comment_field'          => '<p class="comment-form-comment"><label for="comment">' . __( 'Comment', 'fanfiction-manager' ) . ' <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" aria-required="true"></textarea></p>',
				'comment_notes_before'   => '',
				'comment_notes_after'    => '<p class="comment-notes">' . __( 'Your comment will be visible immediately after posting.', 'fanfiction-manager' ) . '</p>',
			)
		);
		return ob_get_clean();
	}

	/**
	 * Display comment count
	 *
	 * [comment-count]
	 * [comment-count post_id="123"]
	 * [comment-count post_id="123" format="number"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Comment count.
	 */
	public static function comment_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'post_id' => 0,
				'format'  => 'text', // 'text' or 'number'
			),
			'comment-count'
		);

		// Get post ID
		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '0';
		}

		// Get comment count
		$count = get_comments_number( $post_id );

		if ( 'number' === $atts['format'] ) {
			return esc_html( number_format_i18n( $count ) );
		}

		// Text format
		if ( 0 === $count ) {
			return esc_html__( 'No comments', 'fanfiction-manager' );
		} elseif ( 1 === $count ) {
			return esc_html__( '1 comment', 'fanfiction-manager' );
		} else {
			return sprintf(
				/* translators: %s: Number of comments */
				esc_html__( '%s comments', 'fanfiction-manager' ),
				esc_html( number_format_i18n( $count ) )
			);
		}
	}

	/**
	 * Display complete comments section (list + form)
	 *
	 * [comments-section]
	 * [comments-section post_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Complete comments section HTML.
	 */
	public static function comments_section( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'post_id' => 0,
			),
			'comments-section'
		);

		// Get post ID
		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No post specified for comments.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Load the comments template
		ob_start();

		// Set global post
		global $post;
		$original_post = $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		// Include comments template
		$template_path = FANFIC_PLUGIN_DIR . 'templates/template-comments.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// Fallback to WordPress default
			comments_template();
		}

		// Restore original post
		$post = $original_post;
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Display story-specific comments section
	 *
	 * [story-comments]
	 * [story-comments story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story comments section HTML.
	 */
	public static function story_comments( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-comments'
		);

		// Get story ID
		$story_id = absint( $atts['story_id'] );
		if ( ! $story_id ) {
			global $post;
			if ( $post && 'fanfiction_story' === get_post_type( $post ) ) {
				$story_id = $post->ID;
			}
		}

		if ( ! $story_id ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No story specified for comments.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Verify it's a story post type
		if ( 'fanfiction_story' !== get_post_type( $story_id ) ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'Invalid story ID.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Check if comments are open
		if ( ! comments_open( $story_id ) ) {
			return '<div class="fanfic-story-comments-closed" role="status" aria-live="polite">' .
				'<p>' . esc_html__( 'Comments are closed for this story.', 'fanfiction-manager' ) . '</p>' .
				'</div>';
		}

		// Get comments
		$comments = get_comments(
			array(
				'post_id' => $story_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);

		ob_start();
		?>
		<div class="fanfic-story-comments-wrapper" role="region" aria-label="<?php esc_attr_e( 'Story Comments', 'fanfiction-manager' ); ?>">
			<div id="comments" class="fanfic-comments-area">
				<?php if ( ! empty( $comments ) ) : ?>
					<h3 class="fanfic-comments-title">
						<?php
						printf(
							/* translators: %s: Number of comments */
							esc_html( _n( '%s Comment on this Story', '%s Comments on this Story', count( $comments ), 'fanfiction-manager' ) ),
							esc_html( number_format_i18n( count( $comments ) ) )
						);
						?>
					</h3>

					<ol class="fanfic-comment-list">
						<?php
						wp_list_comments(
							array(
								'style'       => 'ol',
								'short_ping'  => true,
								'avatar_size' => 64,
								'max_depth'   => 4,
								'callback'    => 'fanfic_custom_comment_template',
							),
							$comments
						);
						?>
					</ol>
				<?php else : ?>
					<p class="fanfic-no-comments" role="status">
						<?php esc_html_e( 'No comments yet. Be the first to comment on this story!', 'fanfiction-manager' ); ?>
					</p>
				<?php endif; ?>

				<?php
				// Check if user must be logged in
				if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
					?>
					<p class="must-log-in">
						<?php
						printf(
							/* translators: %s: Login URL */
							__( 'You must be <a href="%s">logged in</a> to post a comment.', 'fanfiction-manager' ),
							esc_url( wp_login_url( get_permalink( $story_id ) ) )
						);
						?>
					</p>
					<?php
				} else {
					comment_form(
						array(
							'post_id'              => $story_id,
							'title_reply'          => __( 'Leave a Comment on this Story', 'fanfiction-manager' ),
							'title_reply_to'       => __( 'Reply to %s', 'fanfiction-manager' ),
							'cancel_reply_link'    => __( 'Cancel Reply', 'fanfiction-manager' ),
							'label_submit'         => __( 'Post Comment', 'fanfiction-manager' ),
							'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . __( 'Comment', 'fanfiction-manager' ) . ' <span class="required" aria-label="' . esc_attr__( 'required', 'fanfiction-manager' ) . '">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" aria-required="true" aria-describedby="comment-notes"></textarea></p>',
							'comment_notes_before' => '',
							'comment_notes_after'  => '<p id="comment-notes" class="comment-notes">' . __( 'Your comment will be visible immediately after posting.', 'fanfiction-manager' ) . '</p>',
						)
					);
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display chapter-specific comments section
	 *
	 * [chapter-comments]
	 * [chapter-comments chapter_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter comments section HTML.
	 */
	public static function chapter_comments( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'chapter_id' => 0,
			),
			'chapter-comments'
		);

		// Get chapter ID
		$chapter_id = absint( $atts['chapter_id'] );
		if ( ! $chapter_id ) {
			global $post;
			if ( $post && 'fanfiction_chapter' === get_post_type( $post ) ) {
				$chapter_id = $post->ID;
			}
		}

		if ( ! $chapter_id ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No chapter specified for comments.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Verify it's a chapter post type
		if ( 'fanfiction_chapter' !== get_post_type( $chapter_id ) ) {
			return '<p class="fanfic-info-box fanfic-error">' .
				esc_html__( 'Invalid chapter ID.', 'fanfiction-manager' ) .
				'</p>';
		}

		// Check if comments are open
		if ( ! comments_open( $chapter_id ) ) {
			return '<div class="fanfic-chapter-comments-closed" role="status" aria-live="polite">' .
				'<p>' . esc_html__( 'Comments are closed for this chapter.', 'fanfiction-manager' ) . '</p>' .
				'</div>';
		}

		// Get comments
		$comments = get_comments(
			array(
				'post_id' => $chapter_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);

		ob_start();
		?>
		<div class="fanfic-chapter-comments-wrapper" role="region" aria-label="<?php esc_attr_e( 'Chapter Comments', 'fanfiction-manager' ); ?>">
			<div id="comments" class="fanfic-comments-area">
				<?php if ( ! empty( $comments ) ) : ?>
					<h3 class="fanfic-comments-title">
						<?php
						printf(
							/* translators: %s: Number of comments */
							esc_html( _n( '%s Comment on this Chapter', '%s Comments on this Chapter', count( $comments ), 'fanfiction-manager' ) ),
							esc_html( number_format_i18n( count( $comments ) ) )
						);
						?>
					</h3>

					<ol class="fanfic-comment-list">
						<?php
						wp_list_comments(
							array(
								'style'       => 'ol',
								'short_ping'  => true,
								'avatar_size' => 64,
								'max_depth'   => 4,
								'callback'    => 'fanfic_custom_comment_template',
							),
							$comments
						);
						?>
					</ol>
				<?php else : ?>
					<p class="fanfic-no-comments" role="status">
						<?php esc_html_e( 'No comments yet. Be the first to comment on this chapter!', 'fanfiction-manager' ); ?>
					</p>
				<?php endif; ?>

				<?php
				// Check if user must be logged in
				if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
					?>
					<p class="must-log-in">
						<?php
						printf(
							/* translators: %s: Login URL */
							__( 'You must be <a href="%s">logged in</a> to post a comment.', 'fanfiction-manager' ),
							esc_url( wp_login_url( get_permalink( $chapter_id ) ) )
						);
						?>
					</p>
					<?php
				} else {
					comment_form(
						array(
							'post_id'              => $chapter_id,
							'title_reply'          => __( 'Leave a Comment on this Chapter', 'fanfiction-manager' ),
							'title_reply_to'       => __( 'Reply to %s', 'fanfiction-manager' ),
							'cancel_reply_link'    => __( 'Cancel Reply', 'fanfiction-manager' ),
							'label_submit'         => __( 'Post Comment', 'fanfiction-manager' ),
							'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . __( 'Comment', 'fanfiction-manager' ) . ' <span class="required" aria-label="' . esc_attr__( 'required', 'fanfiction-manager' ) . '">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" aria-required="true" aria-describedby="comment-notes"></textarea></p>',
							'comment_notes_before' => '',
							'comment_notes_after'  => '<p id="comment-notes" class="comment-notes">' . __( 'Your comment will be visible immediately after posting.', 'fanfiction-manager' ) . '</p>',
						)
					);
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display story comment count
	 *
	 * [story-comments-count]
	 * [story-comments-count story_id="123"]
	 * [story-comments-count story_id="123" format="number"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story comment count.
	 */
	public static function story_comments_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
				'format'   => 'text', // 'text' or 'number'
			),
			'story-comments-count'
		);

		// Get story ID
		$story_id = absint( $atts['story_id'] );
		if ( ! $story_id ) {
			global $post;
			if ( $post && 'fanfiction_story' === get_post_type( $post ) ) {
				$story_id = $post->ID;
			}
		}

		if ( ! $story_id ) {
			return '0';
		}

		// Verify it's a story post type
		if ( 'fanfiction_story' !== get_post_type( $story_id ) ) {
			return '0';
		}

		// Get comment count
		$count = get_comments_number( $story_id );

		if ( 'number' === $atts['format'] ) {
			return esc_html( number_format_i18n( $count ) );
		}

		// Text format
		if ( 0 === $count ) {
			return esc_html__( 'No story comments', 'fanfiction-manager' );
		} elseif ( 1 === $count ) {
			return esc_html__( '1 story comment', 'fanfiction-manager' );
		} else {
			return sprintf(
				/* translators: %s: Number of story comments */
				esc_html__( '%s story comments', 'fanfiction-manager' ),
				esc_html( number_format_i18n( $count ) )
			);
		}
	}

	/**
	 * Display chapter comment count
	 *
	 * [chapter-comments-count]
	 * [chapter-comments-count chapter_id="123"]
	 * [chapter-comments-count chapter_id="123" format="number"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter comment count.
	 */
	public static function chapter_comments_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'chapter_id' => 0,
				'format'     => 'text', // 'text' or 'number'
			),
			'chapter-comments-count'
		);

		// Get chapter ID
		$chapter_id = absint( $atts['chapter_id'] );
		if ( ! $chapter_id ) {
			global $post;
			if ( $post && 'fanfiction_chapter' === get_post_type( $post ) ) {
				$chapter_id = $post->ID;
			}
		}

		if ( ! $chapter_id ) {
			return '0';
		}

		// Verify it's a chapter post type
		if ( 'fanfiction_chapter' !== get_post_type( $chapter_id ) ) {
			return '0';
		}

		// Get comment count
		$count = get_comments_number( $chapter_id );

		if ( 'number' === $atts['format'] ) {
			return esc_html( number_format_i18n( $count ) );
		}

		// Text format
		if ( 0 === $count ) {
			return esc_html__( 'No chapter comments', 'fanfiction-manager' );
		} elseif ( 1 === $count ) {
			return esc_html__( '1 chapter comment', 'fanfiction-manager' );
		} else {
			return sprintf(
				/* translators: %s: Number of chapter comments */
				esc_html__( '%s chapter comments', 'fanfiction-manager' ),
				esc_html( number_format_i18n( $count ) )
			);
		}
	}
}
