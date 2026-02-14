<?php
/**
 * Fanfiction Manager SEO Class
 *
 * Handles all SEO functionality including meta tags, structured data,
 * OpenGraph, Twitter Cards, and sitemap integration.
 *
 * @package FanfictionManager
 * @subpackage SEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Fanfic_SEO
 *
 * Manages SEO features for fanfiction stories and chapters including:
 * - Meta tags (description, keywords, robots)
 * - Canonical URLs
 * - Structured data (JSON-LD)
 * - OpenGraph meta tags
 * - Twitter Cards
 * - WordPress sitemap integration
 *
 * @since 1.0.0
 */
class Fanfic_SEO {

    /**
     * Initialize SEO functionality
     *
     * Registers all hooks and filters for SEO features.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        // Output meta tags early in head
        add_action('wp_head', array(__CLASS__, 'output_meta_tags'), 5);

        // Output canonical tag
        add_action('wp_head', array(__CLASS__, 'output_canonical_tag'), 6);

        // Output OpenGraph meta tags
        add_action('wp_head', array(__CLASS__, 'generate_og_meta'), 7);

        // Output Twitter Card meta tags
        add_action('wp_head', array(__CLASS__, 'generate_twitter_meta'), 8);

        // Output structured data later in head
        add_action('wp_head', array(__CLASS__, 'output_structured_data'), 15);

        // WordPress sitemap integration (WP 5.5+)
        add_filter('wp_sitemaps_post_types', array(__CLASS__, 'add_to_sitemap'), 10, 1);
        add_filter('wp_sitemaps_posts_entry', array(__CLASS__, 'filter_sitemap_entry'), 10, 3);
        add_filter('wp_sitemaps_posts_query_args', array(__CLASS__, 'sitemap_query_args'), 10, 2);
    }

    /**
     * Output basic SEO meta tags
     *
     * Generates description, keywords, author, and robots meta tags
     * for fanfiction stories and chapters.
     *
     * @since 1.0.0
     * @return void
     */
    public static function output_meta_tags() {
        // Only process for fanfiction post types
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // Meta description
        $description = self::get_description($post);
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Meta keywords (from genres and custom taxonomies)
        $keywords = self::get_post_keywords($post->ID);
        if (!empty($keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        }

        // Meta author
        $author_name = get_the_author_meta('display_name', $post->post_author);
        if (!empty($author_name)) {
            echo '<meta name="author" content="' . esc_attr($author_name) . '">' . "\n";
        }

        // Robots meta tag
        $robots = self::get_robots_meta($post);
        if (!empty($robots)) {
            echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
        }

        // Article publication date
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post)) . '">' . "\n";

        // Article modification date
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post)) . '">' . "\n";
    }

    /**
     * Output canonical tag
     *
     * Generates canonical URL for stories and chapters,
     * removing tracking parameters.
     *
     * @since 1.0.0
     * @return void
     */
    public static function output_canonical_tag() {
        // Only process for fanfiction post types
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // Get permalink (respects URL rules)
        $canonical_url = get_permalink($post);

        if (!$canonical_url) {
            return;
        }

        // Remove tracking parameters
        $tracking_params = array(
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'fbclid',
            'gclid',
            'ref'
        );

        $canonical_url = remove_query_arg($tracking_params, $canonical_url);

        echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
    }

    /**
     * Output structured data (JSON-LD)
     *
     * Generates Schema.org structured data for stories and chapters
     * to improve search engine understanding.
     *
     * @since 1.0.0
     * @return void
     */
    public static function output_structured_data() {
        // Only process for fanfiction post types
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // Check for cached schema data
        $cache_key = 'fanfic_schema_' . $post->ID . '_' . get_the_modified_time('U', $post);
        $schema_data = get_transient($cache_key);

        if (false === $schema_data) {
            $schema_data = self::generate_schema_data($post);

            // Cache for 1 hour
            set_transient($cache_key, $schema_data, HOUR_IN_SECONDS);
        }

        if (!empty($schema_data)) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
        }
    }

    /**
     * Generate structured data for a post
     *
     * Creates Schema.org structured data object for stories or chapters.
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return array Schema.org structured data array.
     */
    private static function generate_schema_data($post) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'description' => self::get_description($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'url' => get_permalink($post),
        );

        // Author information
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $author_url = fanfic_get_user_profile_url($author_id);

        $schema['author'] = array(
            '@type' => 'Person',
            'name' => $author_name,
            'url' => $author_url,
        );

        // Publisher information
        $schema['publisher'] = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
        );

        // Add logo if available
        $logo_url = self::get_site_logo_url();
        if ($logo_url) {
            $schema['publisher']['logo'] = array(
                '@type' => 'ImageObject',
                'url' => $logo_url,
            );
        }

        // Image
        $image_url = self::get_og_image($post);
        if ($image_url) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $image_url,
            );
        }

        // Keywords from genres
        $genres = self::get_post_genres($post->ID);
        if (!empty($genres)) {
            $schema['keywords'] = $genres;
        }

        // Word count for chapters
        if ($post->post_type === 'fanfiction_chapter') {
            $word_count = self::get_word_count($post->post_content);
            if ($word_count > 0) {
                $schema['wordCount'] = $word_count;
            }

            // Reference to parent story
            $parent_id = $post->post_parent;
            if ($parent_id) {
                $schema['isPartOf'] = array(
                    '@type' => 'CreativeWork',
                    'name' => get_the_title($parent_id),
                    'url' => get_permalink($parent_id),
                );
            }
        }

        // Story-specific data
        if ($post->post_type === 'fanfiction_story') {
            // Total word count (sum of all chapters)
            $total_word_count = self::get_story_word_count($post->ID);
            if ($total_word_count > 0) {
                $schema['wordCount'] = $total_word_count;
            }

            // Number of chapters
            $chapter_count = self::get_chapter_count($post->ID);
            if ($chapter_count > 0) {
                /* translators: %d: number of chapters */
                $schema['numberOfPages'] = $chapter_count;
            }

            // Story status
            $status_terms = get_the_terms($post->ID, 'fanfiction_status');
            if (!empty($status_terms) && !is_wp_error($status_terms)) {
                $schema['creativeWorkStatus'] = $status_terms[0]->name;
            }
        }

        /**
         * Filter the structured data before output.
         *
         * @since 1.0.0
         * @param array   $schema Schema.org structured data array.
         * @param WP_Post $post   The post object.
         */
        return apply_filters('fanfic_seo_structured_data', $schema, $post);
    }

    /**
     * Generate OpenGraph meta tags
     *
     * Outputs OpenGraph meta tags for social media sharing.
     *
     * @since 1.0.0
     * @return void
     */
    public static function generate_og_meta() {
        // Only process for fanfiction post types
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // og:title
        $title = get_the_title($post);
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";

        // og:description
        $description = self::get_description($post);
        if (!empty($description)) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        }

        // og:type
        echo '<meta property="og:type" content="article" />' . "\n";

        // og:url (canonical)
        $url = get_permalink($post);
        if ($url) {
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        }

        // og:site_name
        $site_name = get_bloginfo('name');
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";

        // og:image
        $image_url = self::get_og_image($post);
        if ($image_url) {
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";

            // og:image:alt
            $image_alt = $title;
            if (has_post_thumbnail($post)) {
                $thumbnail_id = get_post_thumbnail_id($post);
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if (!empty($alt_text)) {
                    $image_alt = $alt_text;
                }
            }
            echo '<meta property="og:image:alt" content="' . esc_attr($image_alt) . '" />' . "\n";
        }

        // article:published_time
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post)) . '" />' . "\n";

        // article:modified_time
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post)) . '" />' . "\n";

        // article:author
        $author_url = fanfic_get_user_profile_url($post->post_author);
        echo '<meta property="article:author" content="' . esc_url($author_url) . '" />' . "\n";

        // article:section (primary genre)
        $genres = get_the_terms($post->ID, 'fanfiction_genre');
        if (!empty($genres) && !is_wp_error($genres)) {
            $primary_genre = reset($genres);
            echo '<meta property="article:section" content="' . esc_attr($primary_genre->name) . '" />' . "\n";

            // article:tag for each genre
            foreach ($genres as $genre) {
                echo '<meta property="article:tag" content="' . esc_attr($genre->name) . '" />' . "\n";
            }
        }
    }

    /**
     * Generate Twitter Card meta tags
     *
     * Outputs Twitter Card meta tags for Twitter sharing.
     *
     * @since 1.0.0
     * @return void
     */
    public static function generate_twitter_meta() {
        // Only process for fanfiction post types
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // twitter:card
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";

        // twitter:title
        $title = get_the_title($post);
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";

        // twitter:description
        $description = self::get_description($post);
        if (!empty($description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        }

        // twitter:image
        $image_url = self::get_og_image($post);
        if ($image_url) {
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";

            // twitter:image:alt
            $image_alt = $title;
            if (has_post_thumbnail($post)) {
                $thumbnail_id = get_post_thumbnail_id($post);
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if (!empty($alt_text)) {
                    $image_alt = $alt_text;
                }
            }
            echo '<meta name="twitter:image:alt" content="' . esc_attr($image_alt) . '" />' . "\n";
        }

        // twitter:site (from settings if available)
        $twitter_site = get_option('fanfic_twitter_site', '');
        if (!empty($twitter_site)) {
            // Ensure @ prefix
            if (strpos($twitter_site, '@') !== 0) {
                $twitter_site = '@' . $twitter_site;
            }
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '" />' . "\n";
        }

        // twitter:creator (author's Twitter if available)
        $twitter_creator = get_the_author_meta('twitter', $post->post_author);
        if (!empty($twitter_creator)) {
            // Ensure @ prefix
            if (strpos($twitter_creator, '@') !== 0) {
                $twitter_creator = '@' . $twitter_creator;
            }
            echo '<meta name="twitter:creator" content="' . esc_attr($twitter_creator) . '" />' . "\n";
        }
    }

    /**
     * Get meta description for a post
     *
     * Extracts and formats a meta description (max 160 chars).
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return string Meta description.
     */
    public static function get_description($post) {
        if (!$post) {
            return '';
        }

        $description = '';

        // Use excerpt if available
        if (!empty($post->post_excerpt)) {
            $description = $post->post_excerpt;
        } else {
            // For chapters, extract from content
            if ($post->post_type === 'fanfiction_chapter' && !empty($post->post_content)) {
                $description = wp_strip_all_tags($post->post_content);
            }
        }

        // If still empty, try parent story excerpt for chapters
        if (empty($description) && $post->post_type === 'fanfiction_chapter' && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent && !empty($parent->post_excerpt)) {
                $description = $parent->post_excerpt;
            }
        }

        // Clean and truncate
        if (!empty($description)) {
            $description = wp_strip_all_tags($description);
            $description = preg_replace('/\s+/', ' ', $description); // Normalize whitespace
            $description = trim($description);

            // Truncate to 160 characters at word boundary
            if (strlen($description) > 160) {
                $description = wp_trim_words($description, 25, '...');

                // Ensure we don't exceed 160 chars even with ellipsis
                if (strlen($description) > 160) {
                    $description = substr($description, 0, 157) . '...';
                }
            }
        }

        /**
         * Filter the meta description.
         *
         * @since 1.0.0
         * @param string  $description The generated description.
         * @param WP_Post $post        The post object.
         */
        return apply_filters('fanfic_seo_description', $description, $post);
    }

    /**
     * Get keywords for a post
     *
     * Extracts keywords from genres and custom taxonomies.
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return string Comma-separated keywords.
     */
    private static function get_post_keywords($post_id) {
        $keywords = array();

        // Get genres
        $genres = get_the_terms($post_id, 'fanfiction_genre');
        if (!empty($genres) && !is_wp_error($genres)) {
            foreach ($genres as $genre) {
                $keywords[] = $genre->name;
            }
        }

        // Get custom taxonomies (taxonomy_1 through taxonomy_10)
        for ($i = 1; $i <= 10; $i++) {
            $taxonomy = 'fanfiction_taxonomy_' . $i;

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_the_terms($post_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $keywords[] = $term->name;
                }
            }
        }

        // Remove duplicates and limit
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, 15); // Limit to 15 keywords

        return implode(', ', $keywords);
    }

    /**
     * Get robots meta tag content
     *
     * Determines appropriate robots directive based on post status.
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return string Robots directive.
     */
    private static function get_robots_meta($post) {
        if (!$post) {
            return '';
        }

        // Check if post should be indexed
        if (!self::should_index_post($post)) {
            return 'noindex, nofollow';
        }

        // Published and public posts
		return 'index, follow, max-snippet:-1, max-image-preview:large';
	}

    /**
     * Get image for social sharing
     *
     * Gets the best available image for OpenGraph/Twitter cards.
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return string|false Image URL or false if none available.
     */
    public static function get_og_image($post) {
        if (!$post) {
            return false;
        }

        // Priority 1: Featured image
        if (has_post_thumbnail($post)) {
            $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($post), 'large');
            if ($image_url) {
                return $image_url;
            }
        }

        // Priority 2: Parent story featured image (for chapters)
        if ($post->post_type === 'fanfiction_chapter' && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent && has_post_thumbnail($parent)) {
                $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($parent), 'large');
                if ($image_url) {
                    return $image_url;
                }
            }
        }

        // Priority 3: Site logo
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $image_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($image_url) {
                return $image_url;
            }
        }

        // Priority 4: Site icon
        $site_icon_url = get_site_icon_url(512);
        if ($site_icon_url) {
            return $site_icon_url;
        }

        // Priority 5: Plugin default image (if exists)
        $default_image = plugins_url('assets/images/default-og-image.jpg', dirname(__FILE__));
        if (file_exists(dirname(dirname(__FILE__)) . '/assets/images/default-og-image.jpg')) {
            return $default_image;
        }

        return false;
    }

    /**
     * Get site logo URL
     *
     * Gets the site logo for schema markup.
     *
     * @since 1.0.0
     * @return string|false Logo URL or false if none available.
     */
    public static function get_site_logo_url() {
        // Check custom logo first
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                return $logo_url;
            }
        }

        // Fall back to site icon
        $site_icon_url = get_site_icon_url(512);
        if ($site_icon_url) {
            return $site_icon_url;
        }

        return false;
    }

    /**
     * Get post genres as comma-separated string
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return string Comma-separated genre names.
     */
    public static function get_post_genres($post_id) {
        $genres = get_the_terms($post_id, 'fanfiction_genre');

        if (empty($genres) || is_wp_error($genres)) {
            return '';
        }

        $genre_names = array();
        foreach ($genres as $genre) {
            $genre_names[] = $genre->name;
        }

        return implode(', ', $genre_names);
    }

    /**
     * Determine if post should be indexed
     *
     * Checks post status and visibility to determine indexing.
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return bool True if should be indexed, false otherwise.
     */
    public static function should_index_post($post) {
        if (!$post) {
            return false;
        }

        // Don't index non-published posts
        if ($post->post_status !== 'publish') {
            return false;
        }

        // Don't index password-protected posts
        if (post_password_required($post)) {
            return false;
        }

        // Don't index if noindex is set in post meta
        $noindex = get_post_meta($post->ID, '_fanfic_noindex', true);
        if ($noindex === '1' || $noindex === 'yes') {
            return false;
        }

        return true;
    }

    /**
     * Add fanfiction post types to sitemap
     *
     * Adds stories and chapters to WordPress core sitemap.
     *
     * @since 1.0.0
     * @param array $post_types Array of post type objects.
     * @return array Modified post types array.
     */
    public static function add_to_sitemap($post_types) {
        // Add fanfiction_story
        if (post_type_exists('fanfiction_story')) {
            $post_types['fanfiction_story'] = get_post_type_object('fanfiction_story');
        }

        // Add fanfiction_chapter
        if (post_type_exists('fanfiction_chapter')) {
            $post_types['fanfiction_chapter'] = get_post_type_object('fanfiction_chapter');
        }

        return $post_types;
    }

    /**
     * Filter sitemap entry
     *
     * Adjusts priority and change frequency for sitemap entries.
     *
     * @since 1.0.0
     * @param array   $entry     Sitemap entry.
     * @param WP_Post $post      Post object.
     * @param string  $post_type Post type name.
     * @return array|false Modified entry or false to exclude.
     */
    public static function filter_sitemap_entry($entry, $post, $post_type) {
        // Only process fanfiction post types
        if (!in_array($post_type, array('fanfiction_story', 'fanfiction_chapter'), true)) {
            return $entry;
        }

        // Exclude non-published posts
        if (!self::should_index_post($post)) {
            return false;
        }

        // Adjust priority and changefreq based on post type
        if ($post_type === 'fanfiction_story') {
            $entry['priority'] = 0.8;

            // Determine changefreq based on story status
            $status_terms = get_the_terms($post->ID, 'fanfiction_status');
            if (!empty($status_terms) && !is_wp_error($status_terms)) {
                $status_slug = $status_terms[0]->slug;

                switch ($status_slug) {
                    case 'finished':
                        $entry['changefreq'] = 'monthly';
                        break;
                    case 'ongoing':
                        $entry['changefreq'] = 'weekly';
                        break;
                    case 'on-hiatus':
                        $entry['changefreq'] = 'monthly';
                        break;
                    case 'abandoned':
                        $entry['changefreq'] = 'yearly';
                        break;
                    default:
                        $entry['changefreq'] = 'weekly';
                }
            } else {
                $entry['changefreq'] = 'weekly';
            }
        } elseif ($post_type === 'fanfiction_chapter') {
            $entry['priority'] = 0.6;
            $entry['changefreq'] = 'weekly';
        }

        return $entry;
    }

    /**
     * Filter sitemap query arguments
     *
     * Modifies the query used to fetch posts for sitemap.
     *
     * @since 1.0.0
     * @param array  $args      WP_Query arguments.
     * @param string $post_type Post type name.
     * @return array Modified query arguments.
     */
    public static function sitemap_query_args($args, $post_type) {
        // Only process fanfiction post types
        if (!in_array($post_type, array('fanfiction_story', 'fanfiction_chapter'), true)) {
            return $args;
        }

        // Only include published posts
        $args['post_status'] = 'publish';

        // Exclude password-protected posts
        $args['has_password'] = false;

        // Order by modified date
        $args['orderby'] = 'modified';
        $args['order'] = 'DESC';

        // Exclude posts with noindex meta
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => '_fanfic_noindex',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_fanfic_noindex',
                'value' => array('1', 'yes'),
                'compare' => 'NOT IN',
            ),
        );

        return $args;
    }

    /**
     * Get word count from content
     *
     * Calculates word count from post content.
     *
     * @since 1.0.0
     * @param string $content Post content.
     * @return int Word count.
     */
    private static function get_word_count($content) {
        if (empty($content)) {
            return 0;
        }

        // Strip HTML and shortcodes
        $text = wp_strip_all_tags(strip_shortcodes($content));

        // Count words
        $word_count = str_word_count($text);

        return intval($word_count);
    }

    /**
     * Get total word count for a story
     *
     * Sums word counts from all published chapters.
     *
     * @since 1.0.0
     * @param int $story_id Story post ID.
     * @return int Total word count.
     */
    private static function get_story_word_count($story_id) {
        // Check cache
        $cache_key = 'fanfic_word_count_' . $story_id;
        $cached_count = get_transient($cache_key);

        if (false !== $cached_count) {
            return intval($cached_count);
        }

        // Query all published chapters
        $chapters = get_posts(array(
            'post_type' => 'fanfiction_chapter',
            'post_parent' => $story_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        $total_words = 0;

        foreach ($chapters as $chapter_id) {
            $chapter = get_post($chapter_id);
            if ($chapter) {
                $total_words += self::get_word_count($chapter->post_content);
            }
        }

        // Cache for 1 hour
        set_transient($cache_key, $total_words, HOUR_IN_SECONDS);

        return $total_words;
    }

    /**
     * Get chapter count for a story
     *
     * Counts published chapters for a story.
     *
     * @since 1.0.0
     * @param int $story_id Story post ID.
     * @return int Chapter count.
     */
    private static function get_chapter_count($story_id) {
        // Check cache
        $cache_key = 'fanfic_chapter_count_' . $story_id;
        $cached_count = get_transient($cache_key);

        if (false !== $cached_count) {
            return intval($cached_count);
        }

        // Query published chapters
        $chapter_count = get_posts(array(
            'post_type' => 'fanfiction_chapter',
            'post_parent' => $story_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $count = count($chapter_count);

        // Cache for 1 hour
        set_transient($cache_key, $count, HOUR_IN_SECONDS);

        return $count;
    }

    /**
     * Clear SEO caches for a post
     *
     * Clears all SEO-related transients when a post is updated.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return void
     */
    public static function clear_seo_cache($post_id) {
        // Clear schema data cache
        delete_transient('fanfic_schema_' . $post_id . '_' . get_the_modified_time('U', $post_id));

        // Clear word count cache
        delete_transient('fanfic_word_count_' . $post_id);

        // Clear chapter count cache
        delete_transient('fanfic_chapter_count_' . $post_id);

        // If this is a chapter, clear parent story caches
        $post = get_post($post_id);
        if ($post && $post->post_type === 'fanfiction_chapter' && $post->post_parent) {
            self::clear_seo_cache($post->post_parent);
        }
    }

    /**
     * Get breadcrumb schema
     *
     * Generates breadcrumb structured data for stories and chapters.
     *
     * @since 1.0.0
     * @param WP_Post $post The post object.
     * @return array|null Breadcrumb schema array or null.
     */
    public static function get_breadcrumb_schema($post) {
        if (!$post || !in_array($post->post_type, array('fanfiction_story', 'fanfiction_chapter'), true)) {
            return null;
        }

        $breadcrumbs = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(),
        );

        $position = 1;

        // Home
        $breadcrumbs['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Home', 'fanfiction-manager'),
            'item' => home_url('/'),
        );

        // Stories archive
        $archive_url = function_exists( 'fanfic_get_story_archive_url' ) ? fanfic_get_story_archive_url() : get_post_type_archive_link('fanfiction_story');
        if ($archive_url) {
            $breadcrumbs['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => __('Stories', 'fanfiction-manager'),
                'item' => $archive_url,
            );
        }

        // Parent story (for chapters)
        if ($post->post_type === 'fanfiction_chapter' && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                $breadcrumbs['itemListElement'][] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => get_the_title($parent),
                    'item' => get_permalink($parent),
                );
            }
        }

        // Current page
        $breadcrumbs['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title($post),
            'item' => get_permalink($post),
        );

        return $breadcrumbs;
    }

    /**
     * Output breadcrumb structured data
     *
     * Outputs breadcrumb schema for stories and chapters.
     *
     * @since 1.0.0
     * @return void
     */
    public static function output_breadcrumb_schema() {
        if (!is_singular(array('fanfiction_story', 'fanfiction_chapter'))) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        $breadcrumb_schema = self::get_breadcrumb_schema($post);

        if ($breadcrumb_schema) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo "\n" . '</script>' . "\n";
        }
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', array('Fanfic_SEO', 'init'), 10);

// Clear caches on post save
add_action('save_post_fanfiction_story', array('Fanfic_SEO', 'clear_seo_cache'), 10, 1);
add_action('save_post_fanfiction_chapter', array('Fanfic_SEO', 'clear_seo_cache'), 10, 1);
