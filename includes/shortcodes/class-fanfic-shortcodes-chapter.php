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
	 * Displays the parent story title without a link
	 *
	 * [fanfic-story-title]
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

		$is_story_view = is_singular( 'fanfiction_story' );
		$is_chapter_view = is_singular( 'fanfiction_chapter' );

		if ( $is_story_view || $is_chapter_view ) {
			$actions = '';
			$edit_story = do_shortcode( '[edit-story-button story_id="' . absint( $story_id ) . '"]' );
			$actions .= $edit_story;

			if ( $is_chapter_view ) {
				$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
				if ( $chapter_id ) {
					$actions .= do_shortcode( '[edit-chapter-button chapter_id="' . absint( $chapter_id ) . '"]' );
				}
			}

			return '<div class="fanfic-story-title-row">' .
				'<h1 class="fanfic-title fanfic-story-title">' . esc_html( $story_title ) . '</h1>' .
				( '' !== $actions ? '<div class="fanfic-story-title-actions">' . $actions . '</div>' : '' ) .
			'</div>';
		}

		return esc_html( $story_title );
	}

	/**
	 * Chapter title shortcode
	 *
	 * Displays the chapter title without a link. If the chapter has no title,
	 * returns the chapter label instead (e.g., "Prologue", "Chapter 1").
	 *
	 * [fanfic-chapter-title]
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

		$chapter_title = get_the_title( $chapter_id );

		// If no title, return chapter label instead
		if ( empty( $chapter_title ) ) {
			$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

			if ( 'prologue' === $chapter_type ) {
				return esc_html__( 'Prologue', 'fanfiction-manager' );
			} elseif ( 'epilogue' === $chapter_type ) {
				return esc_html__( 'Epilogue', 'fanfiction-manager' );
			} else {
				return esc_html( sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number ) );
			}
		}

		return esc_html( $chapter_title );
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

		$published_date = get_the_date( 'F j, Y', $chapter_id );
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

		$modified_date = get_the_modified_date( 'F j, Y', $chapter_id );
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
