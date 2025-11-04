<?php
/**
 * 301 Redirect Handler
 *
 * Handles 301 redirects for renamed system pages
 *
 * @package Fanfiction_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Fanfic_Redirects {

    /**
     * Initialize the class
     */
    public static function init() {
        add_action( 'template_redirect', array( __CLASS__, 'handle_redirects' ), 1 );
    }

    /**
     * Handle 301 redirects for old slugs
     */
    public static function handle_redirects() {
        $redirects = get_option( 'fanfic_slug_redirects', array() );

        if ( empty( $redirects ) ) {
            return;
        }

        // Get current URL path
        $current_url = $_SERVER['REQUEST_URI'];
        $parsed_url = parse_url( $current_url );
        $path = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';

        if ( empty( $path ) ) {
            return;
        }

        // Split path into segments
        $path_segments = explode( '/', $path );

        // Check each segment for old slugs
        $redirect_needed = false;
        $new_segments = $path_segments;

        foreach ( $redirects as $old_slug => $data ) {
            $new_slug = isset( $data['new_slug'] ) ? $data['new_slug'] : $data;

            // Handle both array format (with timestamp) and simple string format
            if ( is_array( $new_slug ) && isset( $data['new_slug'] ) ) {
                $new_slug = $data['new_slug'];
            }

            foreach ( $path_segments as $index => $segment ) {
                if ( $segment === $old_slug ) {
                    $new_segments[ $index ] = $new_slug;
                    $redirect_needed = true;
                }
            }
        }

        if ( ! $redirect_needed ) {
            return;
        }

        // Build new URL
        $new_path = '/' . implode( '/', $new_segments );

        // Preserve query string
        if ( isset( $parsed_url['query'] ) ) {
            $new_path .= '?' . $parsed_url['query'];
        }

        // Perform 301 redirect
        wp_redirect( home_url( $new_path ), 301 );
        exit;
    }

    /**
     * Get count of active redirects
     *
     * @return int Number of active redirects
     */
    public static function get_redirect_count() {
        $redirects = get_option( 'fanfic_slug_redirects', array() );
        return count( $redirects );
    }

    /**
     * Get all redirects with formatted info
     *
     * @return array Formatted redirect info
     */
    public static function get_redirect_info() {
        $redirects = get_option( 'fanfic_slug_redirects', array() );
        $info = array();

        foreach ( $redirects as $old_slug => $data ) {
            $new_slug = isset( $data['new_slug'] ) ? $data['new_slug'] : $data;
            $timestamp = isset( $data['timestamp'] ) ? $data['timestamp'] : 0;
            $expires = $timestamp ? date( 'Y-m-d', strtotime( '+3 months', $timestamp ) ) : 'Unknown';

            $info[] = array(
                'old_slug' => $old_slug,
                'new_slug' => $new_slug,
                'created'  => $timestamp ? date( 'Y-m-d H:i:s', $timestamp ) : 'Unknown',
                'expires'  => $expires,
            );
        }

        return $info;
    }
}
