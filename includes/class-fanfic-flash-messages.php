<?php
/**
 * Handles flash messages stored in the session.
 *
 * @package Fanfiction_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanfic_Flash_Messages {

    /**
     * The key used to store messages in the session.
     */
    private static $session_key = 'fanfic_flash_messages';

    /**
     * Start the session if it's not already started.
     */
    private static function maybe_start_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    /**
     * Add a message to the session queue.
     *
     * @param string $type    Message type (success, error, warning, info).
     * @param string $message The message content.
     */
    public static function add_message( $type, $message ) {
        self::maybe_start_session();

        if ( ! isset( $_SESSION[ self::$session_key ] ) || ! is_array( $_SESSION[ self::$session_key ] ) ) {
            $_SESSION[ self::$session_key ] = [];
        }

        $_SESSION[ self::$session_key ][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Retrieve all messages and clear them from the session.
     *
     * @return array The array of messages.
     */
    public static function get_messages() {
        self::maybe_start_session();
        
        $messages = [];
        if ( isset( $_SESSION[ self::$session_key ] ) && is_array( $_SESSION[ self::$session_key ] ) ) {
            $messages = $_SESSION[ self::$session_key ];
            unset( $_SESSION[ self::$session_key ] );
        }
        
        return $messages;
    }
}
