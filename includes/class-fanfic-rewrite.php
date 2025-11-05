<?php
/**
 * Fanfiction Rewrite Rules Handler
 *
 * Handles custom URL rewriting and permalink structure for the fanfiction plugin.
 *
 * @package FanfictionManager
 * @subpackage Includes
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Rewrite
 *
 * Manages custom rewrite rules and permalink structure for fanfiction stories and chapters.
 *
 * @since 1.0.0
 */
class Fanfic_Rewrite {

	/**
	 * Default base slug for fanfiction URLs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_BASE_SLUG = 'fanfiction';

	/**
	 * Default chapter type slugs.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const DEFAULT_CHAPTER_SLUGS = array(
		'chapter'  => 'chapter',
		'prologue' => 'prologue',
		'epilogue' => 'epilogue',
	);

	/**
	 * Default secondary path slugs.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const DEFAULT_SECONDARY_SLUGS = array(
		'dashboard' => 'dashboard',
		'user'      => 'user',
		'search'    => 'search',
	);

	/**
	 * Option name for storing base slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_BASE_SLUG = 'fanfic_base_slug';

	/**
	 * Option name for storing chapter type slugs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_CHAPTER_SLUGS = 'fanfic_chapter_slugs';

	/**
	 * Option name for storing secondary path slugs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_SECONDARY_SLUGS = 'fanfic_secondary_slugs';

	/**
	 * Option name for storing previous slugs for redirects.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_PREVIOUS_SLUGS = 'fanfic_previous_slugs';

	/**
	 * Maximum length for slug validation.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_SLUG_LENGTH = 50;

	/**
	 * Initialize the rewrite handler.
	 *
	 * Sets up all necessary hooks for rewrite rules and permalink filtering.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 20 );
		add_filter( 'post_type_link', array( __CLASS__, 'filter_chapter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'filter_story_permalink' ), 10, 2 );
		add_filter( 'preview_post_link', array( __CLASS__, 'filter_preview_link' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_old_slug_redirects' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
	}

	/**
	 * Add custom rewrite rules for fanfiction URLs.
	 *
	 * Registers rewrite rules for stories, chapters, prologues, epilogues, and secondary paths.
	 *
	 * @since 1.0.0
	 */
	public static function add_rewrite_rules() {
		$base_slug = self::get_base_slug();
		$story_path = self::get_story_path();
		$chapter_slugs = self::get_chapter_type_slugs();
		$secondary_slugs = self::get_secondary_slugs();

		// Prologue URL: /fanfiction/stories/{story-slug}/prologue/
		add_rewrite_rule(
			'^' . $base_slug . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['prologue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=prologue',
			'top'
		);

		// Epilogue URL: /fanfiction/stories/{story-slug}/epilogue/
		add_rewrite_rule(
			'^' . $base_slug . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['epilogue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=epilogue',
			'top'
		);

		// Chapter URL: /fanfiction/stories/{story-slug}/chapter-{number}/
		add_rewrite_rule(
			'^' . $base_slug . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['chapter'] . '-([0-9]+)/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_number=$matches[2]',
			'top'
		);

		// Story URL: /fanfiction/stories/{story-slug}/
		add_rewrite_rule(
			'^' . $base_slug . '/' . $story_path . '/([^/]+)/?$',
			'index.php?fanfiction_story=$matches[1]&post_type=fanfiction_story',
			'top'
		);

		// Secondary paths.
		// Dashboard: /dashboard/
		add_rewrite_rule(
			'^' . $secondary_slugs['dashboard'] . '/?$',
			'index.php?fanfic_page=dashboard',
			'top'
		);

		// User profile: /user/{username}/
		add_rewrite_rule(
			'^' . $secondary_slugs['user'] . '/([^/]+)/?$',
			'index.php?fanfic_page=user&fanfic_user=$matches[1]',
			'top'
		);

		// Search: /search/
		add_rewrite_rule(
			'^' . $secondary_slugs['search'] . '/?$',
			'index.php?fanfic_page=search',
			'top'
		);
	}

	/**
	 * Add custom query variables.
	 *
	 * @since 1.0.0
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'fanfiction_story';
		$vars[] = 'fanfiction_chapter';
		$vars[] = 'chapter_number';
		$vars[] = 'chapter_type';
		$vars[] = 'fanfic_page';
		$vars[] = 'fanfic_user';
		return $vars;
	}

	/**
	 * Filter the permalink for story posts.
	 *
	 * Replaces the %fanfiction_story% placeholder with the actual story slug.
	 * Also builds pretty permalinks for draft and unpublished posts.
	 *
	 * @since 1.0.0
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post The post object.
	 * @return string The filtered permalink.
	 */
	public static function filter_story_permalink( $permalink, $post ) {
		if ( 'fanfiction_story' !== $post->post_type ) {
			return $permalink;
		}

		// Handle placeholder replacement for published posts.
		if ( false !== strpos( $permalink, '%fanfiction_story%' ) ) {
			$base_slug = self::get_base_slug();
			$story_path = self::get_story_path();
			$story_slug = $post->post_name;

			$permalink = str_replace( '%fanfiction_story%', $base_slug . '/' . $story_path . '/' . $story_slug, $permalink );
			return $permalink;
		}

		// For draft/pending posts, WordPress returns ugly URLs like ?post_type=X&p=ID.
		// Build a pretty permalink instead.
		if ( false !== strpos( $permalink, '?' ) || false !== strpos( $permalink, 'post_type=' ) ) {
			$permalink = self::build_story_permalink( $post );
		}

		return $permalink;
	}

	/**
	 * Filter the permalink for chapter posts.
	 *
	 * Builds proper hierarchical URLs for chapters based on their parent story and type.
	 * Also builds pretty permalinks for draft and unpublished chapters.
	 *
	 * @since 1.0.0
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post The post object.
	 * @return string The filtered permalink.
	 */
	public static function filter_chapter_permalink( $permalink, $post ) {
		if ( 'fanfiction_chapter' !== $post->post_type ) {
			return $permalink;
		}

		// For draft/pending posts or posts without pretty permalinks, build one.
		if ( false !== strpos( $permalink, '?' ) || false !== strpos( $permalink, 'post_type=' ) ) {
			$permalink = self::build_chapter_permalink( $post );
			if ( ! empty( $permalink ) ) {
				return $permalink;
			}
			// If we couldn't build a chapter permalink, fall through to default handling.
		}

		// Get the parent story.
		$parent_id = $post->post_parent;
		if ( ! $parent_id ) {
			return $permalink;
		}

		$parent_story = get_post( $parent_id );
		if ( ! $parent_story || 'fanfiction_story' !== $parent_story->post_type ) {
			return $permalink;
		}

		$base_slug = self::get_base_slug();
		$story_path = self::get_story_path();
		$chapter_slugs = self::get_chapter_type_slugs();
		$story_slug = $parent_story->post_name;

		// Get chapter type.
		$chapter_type = get_post_meta( $post->ID, '_fanfic_chapter_type', true );

		// Build the appropriate URL based on chapter type.
		if ( 'prologue' === $chapter_type ) {
			$permalink = home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['prologue'] . '/' );
		} elseif ( 'epilogue' === $chapter_type ) {
			$permalink = home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['epilogue'] . '/' );
		} else {
			// Regular chapter - get chapter number.
			$chapter_number = get_post_meta( $post->ID, '_fanfic_chapter_number', true );
			if ( ! $chapter_number ) {
				$chapter_number = 1;
			}
			$permalink = home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['chapter'] . '-' . $chapter_number . '/' );
		}

		return $permalink;
	}

	/**
	 * Filter preview links to use pretty permalinks for drafts and previews.
	 *
	 * By default, WordPress uses ugly URLs for draft and preview posts.
	 * This filter ensures stories and chapters use pretty URLs even when not published.
	 *
	 * @since 1.0.0
	 * @param string  $preview_link The preview link URL.
	 * @param WP_Post $post The post object.
	 * @return string The filtered preview link.
	 */
	public static function filter_preview_link( $preview_link, $post ) {
		// Only process fanfiction post types.
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return $preview_link;
		}

		// Get the post permalink (which will be filtered by our other filters).
		$permalink = get_permalink( $post );

		// If we don't have a clean permalink, try to build one.
		if ( ! $permalink || false !== strpos( $permalink, '?' ) ) {
			if ( 'fanfiction_story' === $post->post_type ) {
				$permalink = self::build_story_permalink( $post );
			} elseif ( 'fanfiction_chapter' === $post->post_type ) {
				$permalink = self::build_chapter_permalink( $post );
			}
		}

		// Add preview query parameter to the pretty URL.
		if ( $permalink ) {
			$preview_link = add_query_arg( 'preview', 'true', $permalink );
		}

		return $preview_link;
	}

	/**
	 * Build a pretty permalink for a story post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post The story post object.
	 * @return string The story permalink.
	 */
	private static function build_story_permalink( $post ) {
		$base_slug = self::get_base_slug();
		$story_path = self::get_story_path();
		$story_slug = $post->post_name;

		// If the post doesn't have a slug yet, use the post ID.
		if ( empty( $story_slug ) ) {
			$story_slug = 'story-' . $post->ID;
		}

		return home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' );
	}

	/**
	 * Build a pretty permalink for a chapter post.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post The chapter post object.
	 * @return string The chapter permalink.
	 */
	private static function build_chapter_permalink( $post ) {
		// Get the parent story.
		$parent_id = $post->post_parent;
		if ( ! $parent_id ) {
			return '';
		}

		$parent_story = get_post( $parent_id );
		if ( ! $parent_story || 'fanfiction_story' !== $parent_story->post_type ) {
			return '';
		}

		$base_slug = self::get_base_slug();
		$story_path = self::get_story_path();
		$chapter_slugs = self::get_chapter_type_slugs();
		$story_slug = $parent_story->post_name;

		// If the parent story doesn't have a slug yet, use the post ID.
		if ( empty( $story_slug ) ) {
			$story_slug = 'story-' . $parent_id;
		}

		// Get chapter type.
		$chapter_type = get_post_meta( $post->ID, '_fanfic_chapter_type', true );

		// Build the appropriate URL based on chapter type.
		if ( 'prologue' === $chapter_type ) {
			return home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['prologue'] . '/' );
		} elseif ( 'epilogue' === $chapter_type ) {
			return home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['epilogue'] . '/' );
		} else {
			// Regular chapter - get chapter number.
			$chapter_number = get_post_meta( $post->ID, '_fanfic_chapter_number', true );
			if ( ! $chapter_number ) {
				$chapter_number = 1;
			}
			return home_url( '/' . $base_slug . '/' . $story_path . '/' . $story_slug . '/' . $chapter_slugs['chapter'] . '-' . $chapter_number . '/' );
		}
	}

	/**
	 * Handle redirects from old slugs when slugs are changed.
	 *
	 * Creates 301 redirects to maintain SEO when slugs are updated.
	 *
	 * @since 1.0.0
	 */
	public static function handle_old_slug_redirects() {
		$previous_slugs = get_option( self::OPTION_PREVIOUS_SLUGS, array() );

		if ( empty( $previous_slugs ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_uri = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

		// Check if the request matches any old slug.
		foreach ( $previous_slugs as $old_slug => $new_slug ) {
			if ( 0 === strpos( $request_uri, $old_slug . '/' ) ) {
				$new_url = str_replace( $old_slug . '/', $new_slug . '/', $request_uri );
				$new_url = home_url( '/' . $new_url );
				wp_safe_redirect( $new_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Get the current base slug.
	 *
	 * @since 1.0.0
	 * @return string The base slug for fanfiction URLs.
	 */
	public static function get_base_slug() {
		$slug = get_option( self::OPTION_BASE_SLUG, self::DEFAULT_BASE_SLUG );
		return self::sanitize_slug( $slug );
	}

	/**
	 * Get the story path (subdirectory for stories).
	 *
	 * @since 1.0.0
	 * @return string The story path for fanfiction story URLs.
	 */
	public static function get_story_path() {
		$path = get_option( 'fanfic_story_path', 'stories' );
		return self::sanitize_slug( $path );
	}

	/**
	 * Set the base slug for fanfiction URLs.
	 *
	 * @since 1.0.0
	 * @param string $slug The new base slug.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function set_base_slug( $slug ) {
		$validation = self::validate_slug( $slug );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$old_slug = self::get_base_slug();
		$sanitized_slug = self::sanitize_slug( $slug );

		// Store old slug for redirects if it changed.
		if ( $old_slug !== $sanitized_slug ) {
			self::store_previous_slug( $old_slug, $sanitized_slug );
		}

		update_option( self::OPTION_BASE_SLUG, $sanitized_slug );
		self::flush_rules();

		return true;
	}

	/**
	 * Get the current chapter type slugs.
	 *
	 * @since 1.0.0
	 * @return array Array of chapter type slugs.
	 */
	public static function get_chapter_type_slugs() {
		$slugs = get_option( self::OPTION_CHAPTER_SLUGS, self::DEFAULT_CHAPTER_SLUGS );

		// Ensure all required keys exist.
		$slugs = wp_parse_args( $slugs, self::DEFAULT_CHAPTER_SLUGS );

		// Sanitize all slugs.
		foreach ( $slugs as $key => $slug ) {
			$slugs[ $key ] = self::sanitize_slug( $slug );
		}

		return $slugs;
	}

	/**
	 * Set the chapter type slugs.
	 *
	 * @since 1.0.0
	 * @param array $slugs Array of chapter type slugs (chapter, prologue, epilogue).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function set_chapter_type_slugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return new WP_Error(
				'invalid_slugs',
				__( 'Slugs must be provided as an array.', 'fanfiction-manager' )
			);
		}

		$old_slugs = self::get_chapter_type_slugs();
		$new_slugs = array();

		// Validate each slug.
		foreach ( $slugs as $type => $slug ) {
			if ( ! in_array( $type, array( 'chapter', 'prologue', 'epilogue' ), true ) ) {
				return new WP_Error(
					'invalid_slug_type',
					/* translators: %s: slug type */
					sprintf( __( 'Invalid slug type: %s', 'fanfiction-manager' ), $type )
				);
			}

			$validation = self::validate_slug( $slug );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$new_slugs[ $type ] = self::sanitize_slug( $slug );
		}

		// Check for duplicate slugs.
		if ( count( $new_slugs ) !== count( array_unique( $new_slugs ) ) ) {
			return new WP_Error(
				'duplicate_slugs',
				__( 'Chapter type slugs must be unique.', 'fanfiction-manager' )
			);
		}

		// Store old slugs for redirects.
		foreach ( $old_slugs as $type => $old_slug ) {
			if ( isset( $new_slugs[ $type ] ) && $old_slug !== $new_slugs[ $type ] ) {
				self::store_previous_slug( $old_slug, $new_slugs[ $type ] );
			}
		}

		update_option( self::OPTION_CHAPTER_SLUGS, $new_slugs );
		self::flush_rules();

		return true;
	}

	/**
	 * Get the current secondary path slugs.
	 *
	 * @since 1.0.0
	 * @return array Array of secondary path slugs.
	 */
	public static function get_secondary_slugs() {
		$slugs = get_option( self::OPTION_SECONDARY_SLUGS, self::DEFAULT_SECONDARY_SLUGS );

		// Ensure all required keys exist.
		$slugs = wp_parse_args( $slugs, self::DEFAULT_SECONDARY_SLUGS );

		// Sanitize all slugs.
		foreach ( $slugs as $key => $slug ) {
			$slugs[ $key ] = self::sanitize_slug( $slug );
		}

		return $slugs;
	}

	/**
	 * Set the secondary path slugs.
	 *
	 * @since 1.0.0
	 * @param array $slugs Array of secondary path slugs (dashboard, user, search).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function set_secondary_slugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return new WP_Error(
				'invalid_slugs',
				__( 'Slugs must be provided as an array.', 'fanfiction-manager' )
			);
		}

		$old_slugs = self::get_secondary_slugs();
		$new_slugs = array();

		// Validate each slug.
		foreach ( $slugs as $type => $slug ) {
			if ( ! in_array( $type, array( 'dashboard', 'user', 'search' ), true ) ) {
				return new WP_Error(
					'invalid_slug_type',
					/* translators: %s: slug type */
					sprintf( __( 'Invalid slug type: %s', 'fanfiction-manager' ), $type )
				);
			}

			$validation = self::validate_slug( $slug );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$new_slugs[ $type ] = self::sanitize_slug( $slug );
		}

		// Check for duplicate slugs.
		if ( count( $new_slugs ) !== count( array_unique( $new_slugs ) ) ) {
			return new WP_Error(
				'duplicate_slugs',
				__( 'Secondary path slugs must be unique.', 'fanfiction-manager' )
			);
		}

		// Store old slugs for redirects.
		foreach ( $old_slugs as $type => $old_slug ) {
			if ( isset( $new_slugs[ $type ] ) && $old_slug !== $new_slugs[ $type ] ) {
				self::store_previous_slug( $old_slug, $new_slugs[ $type ] );
			}
		}

		update_option( self::OPTION_SECONDARY_SLUGS, $new_slugs );
		self::flush_rules();

		return true;
	}

	/**
	 * Store previous slug for redirect purposes.
	 *
	 * @since 1.0.0
	 * @param string $old_slug The old slug.
	 * @param string $new_slug The new slug.
	 */
	private static function store_previous_slug( $old_slug, $new_slug ) {
		$previous_slugs = get_option( self::OPTION_PREVIOUS_SLUGS, array() );
		$previous_slugs[ $old_slug ] = $new_slug;
		update_option( self::OPTION_PREVIOUS_SLUGS, $previous_slugs );
	}

	/**
	 * Validate a slug.
	 *
	 * Checks that the slug is alphanumeric (with hyphens), unique, and within length limits.
	 *
	 * @since 1.0.0
	 * @param string $slug The slug to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_slug( $slug ) {
		if ( empty( $slug ) ) {
			return new WP_Error(
				'empty_slug',
				__( 'Slug cannot be empty.', 'fanfiction-manager' )
			);
		}

		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			return new WP_Error(
				'slug_too_long',
				/* translators: %d: maximum slug length */
				sprintf( __( 'Slug cannot exceed %d characters.', 'fanfiction-manager' ), self::MAX_SLUG_LENGTH )
			);
		}

		if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return new WP_Error(
				'invalid_slug_format',
				__( 'Slug can only contain lowercase letters, numbers, and hyphens.', 'fanfiction-manager' )
			);
		}

		// Check against reserved WordPress slugs.
		$reserved_slugs = array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'admin',
			'login',
			'register',
			'wp-login',
			'feed',
			'rss',
			'rss2',
			'atom',
			'rdf',
		);

		if ( in_array( $slug, $reserved_slugs, true ) ) {
			return new WP_Error(
				'reserved_slug',
				__( 'This slug is reserved by WordPress and cannot be used.', 'fanfiction-manager' )
			);
		}

		return true;
	}

	/**
	 * Sanitize a slug.
	 *
	 * @since 1.0.0
	 * @param string $slug The slug to sanitize.
	 * @return string The sanitized slug.
	 */
	public static function sanitize_slug( $slug ) {
		return sanitize_title( $slug );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * Should be called after changing any slug settings.
	 *
	 * @since 1.0.0
	 */
	public static function flush_rules() {
		flush_rewrite_rules();
	}

	/**
	 * Reset all slugs to defaults.
	 *
	 * @since 1.0.0
	 */
	public static function reset_to_defaults() {
		update_option( self::OPTION_BASE_SLUG, self::DEFAULT_BASE_SLUG );
		update_option( self::OPTION_CHAPTER_SLUGS, self::DEFAULT_CHAPTER_SLUGS );
		update_option( self::OPTION_SECONDARY_SLUGS, self::DEFAULT_SECONDARY_SLUGS );
		delete_option( self::OPTION_PREVIOUS_SLUGS );
		self::flush_rules();
	}

	/**
	 * Get all slugs for display or export.
	 *
	 * @since 1.0.0
	 * @return array All current slug settings.
	 */
	public static function get_all_slugs() {
		return array(
			'base'      => self::get_base_slug(),
			'chapters'  => self::get_chapter_type_slugs(),
			'secondary' => self::get_secondary_slugs(),
		);
	}

	/**
	 * Check if a slug conflicts with existing WordPress pages or posts.
	 *
	 * @since 1.0.0
	 * @param string $slug The slug to check.
	 * @return bool True if conflict exists, false otherwise.
	 */
	public static function has_slug_conflict( $slug ) {
		$post = get_page_by_path( $slug );
		if ( $post ) {
			return true;
		}

		// Check for post with the same slug.
		$args = array(
			'name'           => $slug,
			'post_type'      => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$posts = get_posts( $args );

		return ! empty( $posts );
	}
}
