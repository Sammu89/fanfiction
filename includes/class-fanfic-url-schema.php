<?php
/**
 * Fanfiction Manager - URL Schema Configuration
 *
 * Centralized URL slug configuration and management.
 * This file contains the single source of truth for all URL slugs,
 * validation rules, and preview templates. Shared by URL Config page and Setup Wizard.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Fanfic_URL_Schema
 *
 * Centralized URL schema configuration and helper methods.
 * Provides a unified registry for all URL slugs with their metadata.
 *
 * @since 1.0.0
 */
class Fanfic_URL_Schema {

    /**
     * Get the complete slug configuration schema
     *
     * Returns the centralized configuration for all URL slugs including:
     * - type: Category of slug (primary, chapter, secondary, system)
     * - default: Translatable default value
     * - label: Translatable display label
     * - preview_template: URL preview pattern
     * - required: Whether the field is required
     * - option_key: Where this slug is stored
     * - group: Grouping for storage/validation
     *
     * NOTE ON EDIT URLS:
     * Edit functionality uses query parameters instead of separate slugs:
     * - Story edit: {story-url}?action=edit
     * - Chapter edit: {chapter-url}?action=edit
     * - Profile edit: {profile-url}?action=edit
     * This eliminates the need for edit-story, edit-chapter, edit-profile slugs.
     *
     * @since 1.0.0
     * @return array Complete slug configuration schema
     */
    public static function get_slug_config() {
        return array(
            // ========================================
            // PRIMARY SLUGS
            // ========================================
            'base' => array(
                'type'             => 'primary',
                'default'          => __( 'fanfiction', 'fanfiction-manager' ),
                'label'            => __( 'Base Slug', 'fanfiction-manager' ),
                'description'      => __( 'The root path for all fanfiction content. Used in all URLs.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/',
                'required'         => true,
                'option_key'       => 'fanfic_base_slug',
                'group'            => 'primary',
                'updates_page'     => 'main',
            ),

            'story_path' => array(
                'type'             => 'primary',
                'default'          => __( 'stories', 'fanfiction-manager' ),
                'label'            => __( 'Stories Slug', 'fanfiction-manager' ),
                'description'      => __( 'The subdirectory where individual stories are placed. Used in all story URLs.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{story_path}/my-story-title/',
                'required'         => true,
                'option_key'       => 'fanfic_story_path',
                'group'            => 'primary',
            ),

            // ========================================
            // CHAPTER TYPE SLUGS
            // ========================================
            'prologue' => array(
                'type'             => 'chapter',
                'default'          => __( 'prologue', 'fanfiction-manager' ),
                'label'            => __( 'Prologue', 'fanfiction-manager' ),
                'description'      => __( 'URL slug for story prologues.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{story_path}/my-story-title/{prologue}/',
                'required'         => true,
                'option_key'       => 'fanfic_chapter_slugs',
                'group'            => 'chapters',
            ),

            'chapter' => array(
                'type'             => 'chapter',
                'default'          => __( 'chapter', 'fanfiction-manager' ),
                'label'            => __( 'Chapter', 'fanfiction-manager' ),
                'description'      => __( 'URL slug for regular chapters (numbers will be appended).', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{story_path}/my-story-title/{chapter}-1/',
                'required'         => true,
                'option_key'       => 'fanfic_chapter_slugs',
                'group'            => 'chapters',
            ),

            'epilogue' => array(
                'type'             => 'chapter',
                'default'          => __( 'epilogue', 'fanfiction-manager' ),
                'label'            => __( 'Epilogue', 'fanfiction-manager' ),
                'description'      => __( 'URL slug for story epilogues.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{story_path}/my-story-title/{epilogue}/',
                'required'         => true,
                'option_key'       => 'fanfic_chapter_slugs',
                'group'            => 'chapters',
            ),


            // ========================================
            // SYSTEM PAGE SLUGS
            // ========================================
            'login' => array(
                'type'             => 'system',
                'default'          => __( 'login', 'fanfiction-manager' ),
                'label'            => __( 'Login Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the login page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{login}/',
                'option_key'       => 'fanfic_system_page_slugs',
                'group'            => 'system',
            ),

            'register' => array(
                'type'             => 'system',
                'default'          => __( 'register', 'fanfiction-manager' ),
                'label'            => __( 'Register Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the registration page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{register}/',
                'option_key'       => 'fanfic_system_page_slugs',
                'group'            => 'system',
            ),

            'password-reset' => array(
                'type'             => 'system',
                'default'          => __( 'password-reset', 'fanfiction-manager' ),
                'label'            => __( 'Password Reset', 'fanfiction-manager' ),
                'description'      => __( 'URL for password reset.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{password-reset}/',
                'option_key'       => 'fanfic_system_page_slugs',
                'group'            => 'system',
            ),

            // ========================================
            // DYNAMIC PAGE SLUGS
            // ========================================
            'dashboard' => array(
                'type'             => 'dynamic',
                'default'          => __( 'dashboard', 'fanfiction-manager' ),
                'label'            => __( 'Dashboard', 'fanfiction-manager' ),
                'description'      => __( 'URL for the author dashboard.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{dashboard}/',
                'option_key'       => 'fanfic_dashboard_slug',
                'group'            => 'dynamic',
            ),

            'members' => array(
                'type'             => 'dynamic',
                'default'          => __( 'members', 'fanfiction-manager' ),
                'label'            => __( 'Profile Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the members/profile page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{members}/',
                'option_key'       => 'fanfic_members_slug',
                'group'            => 'dynamic',
            ),

            'search' => array(
                'type'             => 'dynamic',
                'default'          => __( 'search', 'fanfiction-manager' ),
                'label'            => __( 'Search Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the search page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{search}/',
                'option_key'       => 'fanfic_search_slug',
                'group'            => 'dynamic',
            ),

            'error' => array(
                'type'             => 'system',
                'default'          => __( 'error', 'fanfiction-manager' ),
                'label'            => __( 'Default Error Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the error page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{error}/',
                'option_key'       => 'fanfic_system_page_slugs',
                'group'            => 'system',
            ),

            'maintenance' => array(
                'type'             => 'system',
                'default'          => __( 'maintenance', 'fanfiction-manager' ),
                'label'            => __( 'Maintenance Page', 'fanfiction-manager' ),
                'description'      => __( 'URL for the maintenance page.', 'fanfiction-manager' ),
                'preview_template' => '{home}{base}/{maintenance}/',
                'option_key'       => 'fanfic_system_page_slugs',
                'group'            => 'system',
            ),
        );
    }

    /**
     * Get slugs by type
     *
     * @since 1.0.0
     * @param string $type The type of slugs to retrieve (primary, chapter, secondary, dynamic, system).
     * @return array Filtered slug configuration
     */
    public static function get_slugs_by_type( $type ) {
        $all_slugs = self::get_slug_config();
        return array_filter( $all_slugs, function( $config ) use ( $type ) {
            return isset( $config['type'] ) && $config['type'] === $type;
        } );
    }

    /**
     * Get slugs by group
     *
     * @since 1.0.0
     * @param string $group The group to retrieve (primary, chapters, secondary, dynamic, system).
     * @return array Filtered slug configuration
     */
    public static function get_slugs_by_group( $group ) {
        $all_slugs = self::get_slug_config();
        return array_filter( $all_slugs, function( $config ) use ( $group ) {
            return isset( $config['group'] ) && $config['group'] === $group;
        } );
    }

    /**
     * Get all current slug values from database
     *
     * Returns an associative array of all current slug values with defaults.
     * This is the single method to retrieve all slugs consistently.
     *
     * @since 1.0.0
     * @return array Array of current slug values (key => value)
     */
    public static function get_current_slugs() {
        $config = self::get_slug_config();
        $current_slugs = array();

        // Get values from different storage locations
        $primary_base = get_option( 'fanfic_base_slug', '' );
        $primary_story = get_option( 'fanfic_story_path', '' );
        $chapter_slugs = get_option( 'fanfic_chapter_slugs', array() );
        $secondary_paths = get_option( 'fanfic_secondary_paths', array() );
        $system_page_slugs = get_option( 'fanfic_system_page_slugs', array() );

        foreach ( $config as $key => $slug_config ) {
            $group = isset( $slug_config['group'] ) ? $slug_config['group'] : '';

            // Determine current value based on storage location
            switch ( $group ) {
                case 'primary':
                    if ( $key === 'base' ) {
                        $current_slugs[ $key ] = ! empty( $primary_base ) ? $primary_base : $slug_config['default'];
                    } elseif ( $key === 'story_path' ) {
                        $current_slugs[ $key ] = ! empty( $primary_story ) ? $primary_story : $slug_config['default'];
                    }
                    break;

                case 'chapters':
                    $current_slugs[ $key ] = isset( $chapter_slugs[ $key ] ) && ! empty( $chapter_slugs[ $key ] )
                        ? $chapter_slugs[ $key ]
                        : $slug_config['default'];
                    break;

                case 'secondary':
                    $current_slugs[ $key ] = isset( $secondary_paths[ $key ] ) && ! empty( $secondary_paths[ $key ] )
                        ? $secondary_paths[ $key ]
                        : $slug_config['default'];
                    break;

                case 'dynamic':
                    // Load from individual option (same pattern as story_path)
                    $option_key = isset( $slug_config['option_key'] ) ? $slug_config['option_key'] : '';
                    if ( ! empty( $option_key ) ) {
                        $value = get_option( $option_key, '' );
                        $current_slugs[ $key ] = ! empty( $value ) ? $value : $slug_config['default'];
                    } else {
                        $current_slugs[ $key ] = $slug_config['default'];
                    }
                    break;

                case 'system':
                    $current_slugs[ $key ] = isset( $system_page_slugs[ $key ] ) && ! empty( $system_page_slugs[ $key ] )
                        ? $system_page_slugs[ $key ]
                        : $slug_config['default'];
                    break;

                default:
                    $current_slugs[ $key ] = $slug_config['default'];
                    break;
            }
        }

        return $current_slugs;
    }

    /**
     * Build preview URL from template
     *
     * Replaces template placeholders with actual slug values.
     *
     * @since 1.0.0
     * @param string $template The preview template string.
     * @param array  $slug_values Current slug values.
     * @return string The rendered preview URL
     */
    public static function build_preview_url( $template, $slug_values ) {
        $url = $template;

        // Replace {home} with home URL
        $url = str_replace( '{home}', home_url( '/' ), $url );

        // Replace all other placeholders with slug values
        foreach ( $slug_values as $key => $value ) {
            $url = str_replace( '{' . $key . '}', $value, $url );
        }

        return $url;
    }

    /**
     * Validate a slug
     *
     * Checks if a slug meets format requirements and doesn't conflict.
     *
     * @since 1.0.0
     * @param string $slug The slug to validate.
     * @param array  $exclude Array of slug keys to exclude from uniqueness check.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_slug( $slug, $exclude = array() ) {
        // Check if empty
        if ( empty( $slug ) ) {
            return new WP_Error( 'empty_slug', __( 'Slug cannot be empty.', 'fanfiction-manager' ) );
        }

        // Check length
        if ( strlen( $slug ) > 50 ) {
            return new WP_Error( 'slug_too_long', __( 'Slug must be 50 characters or less.', 'fanfiction-manager' ) );
        }

        // Check format (alphanumeric and hyphens only)
        if ( ! preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
            return new WP_Error(
                'invalid_slug_format',
                __( 'Slug must contain only lowercase letters, numbers, and hyphens.', 'fanfiction-manager' )
            );
        }

        // Check for conflicts with existing slugs
        $current_slugs = self::get_current_slugs();
        foreach ( $current_slugs as $type => $existing_slug ) {
            // Skip if this type is in the exclude list
            if ( in_array( $type, $exclude, true ) ) {
                continue;
            }

            // Check for conflict
            if ( $existing_slug === $slug ) {
                return new WP_Error(
                    'slug_conflict',
                    sprintf(
                        /* translators: %s: slug type that conflicts */
                        __( 'This slug conflicts with the existing %s slug.', 'fanfiction-manager' ),
                        $type
                    )
                );
            }
        }

        // Check for conflicts with WordPress reserved slugs
        $reserved_slugs = array(
            'wp-admin', 'wp-content', 'wp-includes', 'admin', 'login', 'register',
            'page', 'post', 'tag', 'category', 'attachment', 'feed', 'rss', 'rdf',
            'atom', 'trackback', 'comments', 'embed',
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
     * Get JavaScript configuration for preview updates
     *
     * Returns configuration array for client-side preview generation.
     *
     * @since 1.0.0
     * @return array JavaScript-ready configuration
     */
    public static function get_js_preview_config() {
        $config = self::get_slug_config();
        $js_config = array();

        foreach ( $config as $key => $slug_config ) {
            $js_config[ $key ] = array(
                'template' => $slug_config['preview_template'],
                'label'    => $slug_config['label'],
            );
        }

        return $js_config;
    }

    /**
     * Check if two slug groups have duplicates
     *
     * @since 1.0.0
     * @param array $slugs Array of slug values to check.
     * @return bool True if duplicates found, false otherwise.
     */
    public static function has_duplicates( $slugs ) {
        $slugs = array_filter( $slugs ); // Remove empty values
        return count( $slugs ) !== count( array_unique( $slugs ) );
    }

}
