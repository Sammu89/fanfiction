<?php
/**
 * Slug Change Tracker
 *
 * Detects when system pages have their slugs changed and stores
 * redirect mappings for 301 redirects with 3-month expiry.
 *
 * @package Fanfiction_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Fanfic_Slug_Tracker {

    /**
     * Initialize the class
     */
    public static function init() {
        add_action( 'post_updated', array( __CLASS__, 'detect_slug_change' ), 10, 3 );
        add_action( 'fanfic_cleanup_expired_redirects', array( __CLASS__, 'cleanup_expired_redirects' ) );

        // Schedule daily cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'fanfic_cleanup_expired_redirects' ) ) {
            wp_schedule_event( time(), 'daily', 'fanfic_cleanup_expired_redirects' );
        }
    }

    /**
     * Detect when a system page slug changes
     *
     * @param int $post_id Post ID
     * @param WP_Post $post_after Post object after update
     * @param WP_Post $post_before Post object before update
     */
    public static function detect_slug_change( $post_id, $post_after, $post_before ) {
        // Only track system pages
        if ( 'page' !== $post_after->post_type ) {
            return;
        }

        // Get system page IDs
        $page_ids = get_option( 'fanfic_system_page_ids', array() );

        if ( ! in_array( $post_id, $page_ids, true ) ) {
            return;
        }

        // Check if slug changed
        $old_slug = $post_before->post_name;
        $new_slug = $post_after->post_name;

        if ( $old_slug === $new_slug ) {
            return;
        }

        // Store redirect mapping
        self::add_redirect( $old_slug, $new_slug );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Add a redirect mapping
     *
     * @param string $old_slug Old page slug
     * @param string $new_slug New page slug
     */
    public static function add_redirect( $old_slug, $new_slug ) {
        $redirects = get_option( 'fanfic_slug_redirects', array() );

        $redirects[ $old_slug ] = array(
            'new_slug'  => $new_slug,
            'timestamp' => time(),
        );

        update_option( 'fanfic_slug_redirects', $redirects );
    }

    /**
     * Manually add redirect (used by wizard/settings page)
     *
     * @param string $old_slug Old slug
     * @param string $new_slug New slug
     */
    public static function add_manual_redirect( $old_slug, $new_slug ) {
        if ( $old_slug !== $new_slug ) {
            self::add_redirect( $old_slug, $new_slug );
        }
    }

    /**
     * Get all active redirects
     *
     * @return array Redirect mappings
     */
    public static function get_redirects() {
        return get_option( 'fanfic_slug_redirects', array() );
    }

    /**
     * Cleanup redirects older than 3 months
     */
    public static function cleanup_expired_redirects() {
        $redirects = get_option( 'fanfic_slug_redirects', array() );
        $three_months_ago = strtotime( '-3 months' );

        $cleaned = false;

        foreach ( $redirects as $old_slug => $data ) {
            if ( isset( $data['timestamp'] ) && $data['timestamp'] < $three_months_ago ) {
                unset( $redirects[ $old_slug ] );
                $cleaned = true;
            }
        }

        if ( $cleaned ) {
            update_option( 'fanfic_slug_redirects', $redirects );
        }
    }

    /**
     * Delete a specific redirect
     *
     * @param string $old_slug The old slug to remove from redirects
     * @return bool True if deleted, false otherwise
     */
    public static function delete_redirect( $old_slug ) {
        $redirects = get_option( 'fanfic_slug_redirects', array() );

        if ( ! isset( $redirects[ $old_slug ] ) ) {
            return false;
        }

        unset( $redirects[ $old_slug ] );
        update_option( 'fanfic_slug_redirects', $redirects );

        return true;
    }

    /**
     * Clear all redirects (admin function)
     */
    public static function clear_all_redirects() {
        delete_option( 'fanfic_slug_redirects' );
    }
}
