<?php
/**
 * Homepage State Helper Class
 *
 * Centralizes all homepage state management logic.
 * Handles the mapping between fanfiction plugin settings and WordPress front page settings.
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Fanfic_Homepage_State
 *
 * Provides methods to:
 * - Get current normalized homepage state
 * - Resolve expected WordPress front page settings
 * - Apply WordPress front page settings
 * - Check synchronization status
 * - Perform one-call sync
 */
class Fanfic_Homepage_State {

    /**
     * Get current normalized homepage state from options.
     *
     * @return array {
     *     @type int    $use_base_slug      Whether base slug is used (0 or 1)
     *     @type string $main_page_mode     Main page mode ('custom_homepage' or 'stories_homepage')
     *     @type string $homepage_source    Homepage source ('stories', 'fanfiction_page', 'existing_page', 'wordpress_archive')
     *     @type int    $homepage_source_id Page ID if homepage_source is 'existing_page'
     *     @type int    $main_page_id       Main fanfiction page ID
     * }
     */
    public static function get_current_state() {
        $page_ids = get_option( 'fanfic_system_page_ids', array() );

        $state = array(
            'use_base_slug'      => (int) get_option( 'fanfic_use_base_slug', 1 ),
            'main_page_mode'     => get_option( 'fanfic_main_page_mode', 'custom_homepage' ),
            'homepage_source'    => get_option( 'fanfic_homepage_source', 'fanfiction_page' ),
            'homepage_source_id' => (int) get_option( 'fanfic_homepage_source_id', 0 ),
            'main_page_id'       => isset( $page_ids['main'] ) ? (int) $page_ids['main'] : 0,
            'stories_page_id'    => isset( $page_ids['stories'] ) ? (int) $page_ids['stories'] : 0,
        );

        self::log_debug( 'Current state retrieved', $state );

        return $state;
    }

    /**
     * Resolve expected WordPress front page target based on state.
     *
     * @param array $state Homepage state from get_current_state()
     * @return array|null {
     *     @type string $show_on_front WordPress 'show_on_front' option ('posts' or 'page')
     *     @type int    $page_on_front WordPress 'page_on_front' option (page ID or 0)
     * } or null if WP homepage should be independent (use_base_slug=1)
     */
    public static function resolve_wp_front_page_target( $state ) {
        // If use_base_slug=1, WP homepage is independent
        if ( $state['use_base_slug'] == 1 ) {
            self::log_debug( 'use_base_slug=1, WP homepage independent' );
            return null;
        }

        // use_base_slug=0, determine target based on main_page_mode and homepage_source
        $target = null;

        if ( 'stories_homepage' === $state['main_page_mode'] && $state['stories_page_id'] > 0 ) {
            // Stories archive as homepage — use the plugin's stories page (user-customizable) as static front page
            $target = array(
                'show_on_front' => 'page',
                'page_on_front' => $state['stories_page_id'],
            );
            self::log_debug( "main_page_mode='stories_homepage', setting page_on_front to stories_page_id {$state['stories_page_id']}" );
        } elseif ( 'existing_page' === $state['homepage_source'] && $state['homepage_source_id'] > 0 ) {
            // Existing page as homepage
            $target = array(
                'show_on_front' => 'page',
                'page_on_front' => $state['homepage_source_id'],
            );
            self::log_debug( "homepage_source='existing_page', setting page_on_front to homepage_source_id {$state['homepage_source_id']}" );
        } elseif ( 'wordpress_archive' === $state['homepage_source'] ) {
            // WordPress posts archive as homepage
            $target = array(
                'show_on_front' => 'posts',
                'page_on_front' => 0,
            );
            self::log_debug( "homepage_source='wordpress_archive', setting show_on_front to 'posts'" );
        } elseif ( 'fanfiction_page' === $state['homepage_source'] && $state['main_page_id'] > 0 ) {
            // Fanfiction main page as homepage
            $target = array(
                'show_on_front' => 'page',
                'page_on_front' => $state['main_page_id'],
            );
            self::log_debug( "homepage_source='fanfiction_page', setting page_on_front to main_page_id {$state['main_page_id']}" );
        } else {
            self::log_debug( "No matching condition for target resolution, main_page_mode='{$state['main_page_mode']}', homepage_source='{$state['homepage_source']}'" );
        }

        return $target;
    }

    /**
     * Apply WordPress front page target to WP options.
     *
     * @param array $target Target from resolve_wp_front_page_target()
     * @return bool True on success, false on failure or null target
     */
    public static function apply_wp_front_page_target( $target ) {
        if ( $target === null ) {
            self::log_debug( 'Target is null, not applying' );
            return false;
        }

        $show_on_front = isset( $target['show_on_front'] ) ? $target['show_on_front'] : '';
        $page_on_front = isset( $target['page_on_front'] ) ? (int) $target['page_on_front'] : 0;

        self::log_debug( "Applying WP front page settings: show_on_front={$show_on_front}, page_on_front={$page_on_front}", null, true );

        update_option( 'show_on_front', $show_on_front );
        update_option( 'page_on_front', $page_on_front );

        return true;
    }

    /**
     * Check if WordPress front page settings match expected target.
     *
     * @param array|null $state Optional. State to check. If null, retrieves current state.
     * @return bool True if in sync, false otherwise
     */
    public static function is_wp_front_page_in_sync( $state = null ) {
        if ( $state === null ) {
            $state = self::get_current_state();
        }

        $target = self::resolve_wp_front_page_target( $state );

        // If target is null (use_base_slug=1), always considered in sync
        if ( $target === null ) {
            self::log_debug( 'Target is null (independent mode), considered in sync' );
            return true;
        }

        // Get current WordPress settings
        $current_show_on_front = get_option( 'show_on_front', 'posts' );
        $current_page_on_front = (int) get_option( 'page_on_front', 0 );

        $expected_show_on_front = $target['show_on_front'];
        $expected_page_on_front = $target['page_on_front'];

        $in_sync = (
            $current_show_on_front === $expected_show_on_front &&
            $current_page_on_front === $expected_page_on_front
        );

        if ( $in_sync ) {
            self::log_debug( 'WP front page settings are in sync' );
        } else {
            self::log_debug(
                "WP front page settings OUT OF SYNC: current(show_on_front={$current_show_on_front}, page_on_front={$current_page_on_front}) vs expected(show_on_front={$expected_show_on_front}, page_on_front={$expected_page_on_front})",
                null,
                true
            );
        }

        return $in_sync;
    }

    /**
     * One-call sync: get state, resolve target, apply if needed.
     *
     * @return bool True if sync was performed or not needed, false on error
     */
    public static function sync_homepage_settings() {
        self::log_debug( '=== Starting homepage sync ===', null, true );

        $state = self::get_current_state();
        $target = self::resolve_wp_front_page_target( $state );

        // If target is null (independent mode), nothing to sync
        if ( $target === null ) {
            self::log_debug( 'Independent mode, no sync needed', null, true );
            return true;
        }

        // Check if already in sync
        if ( self::is_wp_front_page_in_sync( $state ) ) {
            self::log_debug( 'Already in sync, no action needed', null, true );
            return true;
        }

        // Apply target
        $result = self::apply_wp_front_page_target( $target );

        if ( $result ) {
            self::log_debug( '=== Homepage sync completed successfully ===', $target, true );
        } else {
            self::log_debug( '=== Homepage sync failed ===', null, true );
        }

        return $result;
    }

    /**
     * Log debug message with [Fanfic Homepage] prefix.
     *
     * Always logs critical path messages.
     * Logs detailed messages only when WP_DEBUG is enabled.
     *
     * @param string $message Debug message
     * @param mixed  $data    Optional data to log
     * @param bool   $critical Whether this is a critical path message (always log)
     */
    private static function log_debug( $message, $data = null, $critical = false ) {
        // Critical messages are always logged
        $should_log = $critical || ( defined( 'WP_DEBUG' ) && WP_DEBUG );

        if ( $should_log ) {
            $log_message = '[Fanfic Homepage] ' . $message;

            if ( $data !== null ) {
                $log_message .= ' | Data: ' . wp_json_encode( $data );
            }

            error_log( $log_message );
        }
    }
}
