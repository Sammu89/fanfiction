<?php
/**
 * Chapter Shortcodes Class
 *
 * Handles all chapter display-related shortcodes.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.13
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Chapter
 *
 * Chapter information and display shortcodes.
 *
 * @since 1.0.13
 */
class Fanfic_Shortcodes_Chapter {

	/**
	 * Register chapter shortcodes
	 *
	 * @since 1.0.13
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfic-story-title', array( __CLASS__, 'story_title' ) );
		add_shortcode( 'fanfic-chapter-title', array( __CLASS__, 'chapter_title' ) );
		add_shortcode( 'fanfic-chapter-published', array( __CLASS__, 'chapter_published' ) );
		add_shortcode( 'fanfic-chapter-updated', array( __CLASS__, 'chapter_updated' ) );
		add_shortcode( 'fanfic-chapter-content', array( __CLASS__, 'chapter_content' ) );
		add_shortcode( 'fanfic-chapter-image', array( __CLASS__, 'fanfic_chapter_image' ) );
		add_shortcode( 'chapter-translations', array( __CLASS__, 'chapter_translations' ) );
	}

	/**
	 * Custom Chapter Image shortcode
	 *
	 * [fanfic-chapter-image]
	 *
	 * @since 2.1.0
	 * @param array $atts Shortcode attributes.
	 * @return string Image HTML tag.
	 */
	public static function fanfic_chapter_image( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
		if ( ! $chapter_id ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'alt'   => get_the_title( $chapter_id ),
			),
			$atts,
			'fanfic-chapter-image'
		);

		// Sanitize attributes
		$class = 'fanfic-chapter-image';
		$alt = esc_attr( $atts['alt'] );

		$image_url = get_post_meta( $chapter_id, '_fanfic_chapter_image_url', true );

		if ( empty( $image_url ) ) {
			return ''; // No image found
		}

		return sprintf(
			'<img src="%s" class="%s" alt="%s" loading="lazy" />',
			esc_url( $image_url ),
			esc_attr( $class ),
			esc_attr( $alt )
		);
	}

	/**
	 * Story title shortcode (for chapter view context)
	 *
	 * Displays the parent story title without a link.
	 * Pass `with-badge` attribute to prepend an inline follow-badge inside the <h1>.
	 *
	 * [fanfic-story-title]
	 * [fanfic-story-title with-badge]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Story title.
	 */
	public static function story_title( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$story_title = get_the_title( $story_id );
		if ( empty( $story_title ) ) {
			return '';
		}

		// Detect `with-badge` as either a valueless attr ([shortcode with-badge])
		// or a keyed attr ([shortcode with-badge="1"]).
		$raw = (array) $atts;
		$show_badge = isset( $raw['with-badge'] ) || in_array( 'with-badge', $raw, true );

		$badge = '';
		if ( $show_badge ) {
			$badge = sprintf(
				'<span class="fanfic-badge fanfic-badge-following" data-badge-story-id="%1$d" style="display:none;" aria-label="%2$s" title="%2$s"><span class="dashicons dashicons-heart" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></span>',
				absint( $story_id ),
				esc_attr__( 'Following', 'fanfiction-manager' ),
				esc_html__( 'Following', 'fanfiction-manager' )
			);
		}

		$is_story_view   = is_singular( 'fanfiction_story' );
		$is_chapter_view = is_singular( 'fanfiction_chapter' );

		$featured_badge = Fanfic_Featured_Stories::render_star_badge( $story_id );
		$title_markup = $featured_badge . $badge . esc_html( $story_title );

		if ( $is_story_view || $is_chapter_view ) {
			$side_content = self::build_story_title_side_content( $story_id, $is_story_view, $is_chapter_view );

			$author_id_for_avatar = (int) get_post_field( 'post_author', $story_id );

			if ( $is_story_view ) {
				$author_link      = trim( (string) do_shortcode( '[story-author-link]' ) );
				$publication_date = trim( (string) do_shortcode( '[story-publication-date]' ) );
				$avatar_html      = function_exists( 'fanfic_get_author_avatar_or_icon' )
					? fanfic_get_author_avatar_or_icon( $author_id_for_avatar, 20 )
					: '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';

				$meta_content = '<div class="fanfic-story-meta">' .
					'<span class="fanfic-story-author">' . $avatar_html . ' ' . $author_link . '</span>' .
					'<span class="fanfic-story-date">' . esc_html__( 'Created on', 'fanfiction-manager' ) . ' ' . $publication_date . '</span>' .
				'</div>';

				return '<div class="fanfic-story-title-row">' .
					'<div class="fanfic-story-title-main">' .
						'<h1 class="fanfic-title fanfic-story-title">' . $title_markup . '</h1>' .
						$meta_content .
					'</div>' .
					$side_content .
				'</div>';
			}

			// In chapter view: render story title as a secondary element (no h1, no badges).
		// Story title becomes a clickable link back to the story page.
		$story_url      = get_permalink( $story_id );
		$author_link_ch = trim( (string) do_shortcode( '[story-author-link]' ) );
			$avatar_html_ch = function_exists( 'fanfic_get_author_avatar_or_icon' )
				? fanfic_get_author_avatar_or_icon( $author_id_for_avatar, 20 )
				: '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';

			$chapter_author_meta = '<div class="fanfic-story-meta">' .
				'<span class="fanfic-story-author">' . $avatar_html_ch . ' ' . $author_link_ch . '</span>' .
			'</div>';

			$story_title_link = '<a href="' . esc_url( $story_url ) . '" class="fanfic-story-title-link">' . esc_html( $story_title ) . '</a>';

			return '<div class="fanfic-story-title-row">' .
				'<div class="fanfic-story-title-main">' .
					'<p class="fanfic-chapter-story-context">' . $story_title_link . '</p>' .
					$chapter_author_meta .
				'</div>' .
				$side_content .
			'</div>';
		}

		return $title_markup;
	}

	/**
	 * Build status/actions container for story title row.
	 *
	 * @since 2.2.0
	 * @param int  $story_id Story ID.
	 * @param bool $is_story_view Whether current context is story view.
	 * @param bool $is_chapter_view Whether current context is chapter view.
	 * @return string HTML content.
	 */
	private static function build_story_title_side_content( $story_id, $is_story_view, $is_chapter_view ) {
		$actions = '';

		if ( $is_story_view ) {
			$edit_story = trim( (string) do_shortcode( '[edit-story-button story_id="' . absint( $story_id ) . '"]' ) );
			if ( '' !== $edit_story ) {
				$actions .= $edit_story;
			}

			$add_chapter = trim( (string) do_shortcode( '[add-chapter-button story_id="' . absint( $story_id ) . '"]' ) );
			if ( '' !== $add_chapter ) {
				$actions .= $add_chapter;
			}

			$feature_button = Fanfic_Featured_Stories::render_feature_button( $story_id );
			if ( '' !== $feature_button ) {
				$actions .= $feature_button;
			}
		}

		if ( $is_chapter_view ) {
			$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
			if ( $chapter_id ) {
				$edit_chapter = trim( (string) do_shortcode( '[edit-chapter-button chapter_id="' . absint( $chapter_id ) . '"]' ) );
				if ( '' !== $edit_chapter ) {
					$actions .= $edit_chapter;
				}
			}

			$edit_story = trim( (string) do_shortcode( '[edit-story-button story_id="' . absint( $story_id ) . '"]' ) );
			if ( '' !== $edit_story ) {
				$actions .= $edit_story;
			}
		}

		$status_badge = '';
		if ( $is_story_view ) {
			$statuses = get_the_terms( $story_id, 'fanfiction_status' );
			if ( $statuses && ! is_wp_error( $statuses ) ) {
				$status      = reset( $statuses );
				$status_slug = sanitize_html_class( $status->slug );
				$status_badge = sprintf(
					'<span class="fanfic-botaozinho status status-%1$s" role="status" aria-label="%2$s"><strong>%3$s</strong></span>',
					esc_attr( $status_slug ),
					esc_attr( sprintf( __( 'Story status: %s', 'fanfiction-manager' ), $status->name ) ),
					esc_html( $status->name )
				);
			}
		}

		if ( '' === $status_badge && '' === $actions ) {
			return '';
		}

		return '<div class="fanfic-story-title-side">' .
			$status_badge .
			( '' !== $actions ? '<div class="fanfic-story-title-edit">' . $actions . '</div>' : '' ) .
		'</div>';
	}

	/**
	 * Chapter title shortcode
	 *
	 * Displays the chapter title without a link. If the chapter has no title,
	 * returns the chapter label instead (e.g., "Prologue", "Chapter 1").
	 * Pass `with-badge` attribute to prepend an inline bookmarked-badge before the title text.
	 *
	 * [fanfic-chapter-title]
	 * [fanfic-chapter-title with-badge]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter title or label.
	 */
	public static function chapter_title( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		// Detect `with-badge` as either a valueless attr or a keyed attr.
		$raw = (array) $atts;
		$show_badge = isset( $raw['with-badge'] ) || in_array( 'with-badge', $raw, true );

		$badges = '';
		if ( $show_badge ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
			if ( $story_id ) {
				// Bookmark badge — hidden by default, shown via JS localStorage
				$badges .= sprintf(
					'<span class="fanfic-badge fanfic-badge-bookmarked" data-badge-story-id="%1$d" data-badge-chapter-id="%2$d" style="display:none;" aria-label="%3$s" title="%3$s"><span class="dashicons dashicons-heart" aria-hidden="true"></span><span class="screen-reader-text">%4$s</span></span>',
					absint( $story_id ),
					absint( $chapter_id ),
					esc_attr__( 'Bookmarked', 'fanfiction-manager' ),
					esc_html__( 'Bookmarked', 'fanfiction-manager' )
				);
				// Read indicator — same as chapter nav, opacity 0 until JS marks it read
				$badges .= sprintf(
					'<span class="fanfic-read-indicator" data-story-id="%1$d" data-chapter-id="%2$d" aria-label="%3$s" title="%3$s"></span>',
					absint( $story_id ),
					absint( $chapter_id ),
					esc_attr__( 'Read', 'fanfiction-manager' )
				);
			}
		}

		$chapter_title = get_the_title( $chapter_id );

		// If no title, return chapter label instead
		if ( empty( $chapter_title ) ) {
			$chapter_type   = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

			if ( 'prologue' === $chapter_type ) {
				return $badges . esc_html__( 'Prologue', 'fanfiction-manager' );
			} elseif ( 'epilogue' === $chapter_type ) {
				return $badges . esc_html__( 'Epilogue', 'fanfiction-manager' );
			} else {
				return $badges . esc_html( sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number ) );
			}
		}

		return $badges . esc_html( $chapter_title );
	}

	/**
	 * Chapter published date shortcode
	 *
	 * Displays the chapter publication date (without time)
	 *
	 * [fanfic-chapter-published]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Published date HTML.
	 */
	public static function chapter_published( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$published_date = get_the_date( get_option( 'date_format' ), $chapter_id );
		$published_datetime = get_the_date( 'Y-m-d', $chapter_id );

		return sprintf(
			'<time class="fanfic-published-date" datetime="%s" itemprop="datePublished">%s</time>',
			esc_attr( $published_datetime ),
			esc_html( $published_date )
		);
	}

	/**
	 * Chapter updated date shortcode
	 *
	 * Displays the chapter last modified date (only if different from published date)
	 *
	 * [fanfic-chapter-updated]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Updated date HTML or empty string.
	 */
	public static function chapter_updated( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$published_timestamp = get_post_time( 'U', false, $chapter_id );
		$modified_timestamp  = get_post_modified_time( 'U', false, $chapter_id );

		// Only show if modified date is different from published date (more than 1 day difference)
		if ( abs( $modified_timestamp - $published_timestamp ) < DAY_IN_SECONDS ) {
			return '';
		}

		$modified_date = get_the_modified_date( get_option( 'date_format' ), $chapter_id );
		$modified_datetime = get_the_modified_date( 'Y-m-d', $chapter_id );

		return sprintf(
			'<time class="fanfic-updated-date" datetime="%s" itemprop="dateModified">%s</time>',
			esc_attr( $modified_datetime ),
			esc_html( $modified_date )
		);
	}

	/**
	 * Chapter content shortcode
	 *
	 * Displays the chapter content
	 *
	 * [fanfic-chapter-content]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter content.
	 */
	public static function chapter_content( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$chapter = get_post( $chapter_id );

		if ( ! $chapter ) {
			return '';
		}

		// Apply the_content filters (including wpautop, embeds, etc.)
		$content = apply_filters( 'the_content', $chapter->post_content );

		// Author's Notes
		$notes_enabled  = get_post_meta( $chapter_id, '_fanfic_author_notes_enabled', true );
		$notes_position = get_post_meta( $chapter_id, '_fanfic_author_notes_position', true ) ?: 'below';
		$notes_raw      = get_post_meta( $chapter_id, '_fanfic_author_notes', true );

		if ( '1' === $notes_enabled && ! empty( $notes_raw ) ) {
			$notes_html = '<aside class="fanfic-author-notes">'
				. '<h4 class="fanfic-author-notes-title">' . esc_html__( "Author's Notes", 'fanfiction-manager' ) . '</h4>'
				. '<div class="fanfic-author-notes-content">' . wp_kses_post( wpautop( $notes_raw ) ) . '</div>'
				. '</aside>';
			return ( 'above' === $notes_position ) ? $notes_html . $content : $content . $notes_html;
		}

		return $content;
	}

	/**
	 * Chapter translations shortcode
	 *
	 * [chapter-translations]
	 *
	 * Displays links to translated sibling chapters matched by chapter structure
	 * (type + number) across translated sibling stories.
	 *
	 * Returns empty when language/translations are disabled or no matches exist.
	 *
	 * @since 1.5.1
	 * @return string
	 */
	public static function chapter_translations() {
		if ( ! class_exists( 'Fanfic_Translations' ) || ! Fanfic_Translations::is_enabled() ) {
			return '';
		}

		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
		if ( ! $chapter_id ) {
			return '';
		}

		$siblings = Fanfic_Translations::get_chapter_translation_siblings( $chapter_id );
		if ( empty( $siblings ) ) {
			return '';
		}

		$count = count( $siblings );

		ob_start();
		?>
		<div class="fanfic-story-translations fanfic-chapter-translations" aria-label="<?php esc_attr_e( 'Available chapter translations', 'fanfiction-manager' ); ?>">
			<span class="fanfic-translations-icon" aria-hidden="true">&#127760;</span>
			<strong><?php esc_html_e( 'This chapter is also available in:', 'fanfiction-manager' ); ?></strong>
			<?php if ( 1 === $count ) : ?>
				<?php $sibling = $siblings[0]; ?>
				<a href="<?php echo esc_url( $sibling['permalink'] ); ?>" class="fanfic-translation-link">
					<?php echo esc_html( $sibling['language_label'] ); ?>
				</a>
			<?php else : ?>
				<select class="fanfic-translations-dropdown" onchange="if(this.value)window.location.href=this.value">
					<option value=""><?php esc_html_e( 'Select language...', 'fanfiction-manager' ); ?></option>
					<?php foreach ( $siblings as $sibling ) : ?>
						<option value="<?php echo esc_url( $sibling['permalink'] ); ?>">
							<?php echo esc_html( $sibling['language_label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

}
