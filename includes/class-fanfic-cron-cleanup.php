<?php
/**
 * Cron Cleanup Class
 *
 * Handles scheduled tasks for anonymizing old votes and likes.
 * Runs daily to remove identifier hashes from votes older than 30 days.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Cron_Cleanup
 *
 * Manages anonymization of old anonymous votes.
 *
 * @since 2.0.0
 */
class Fanfic_Cron_Cleanup {

	/**
	 * Anonymization window in days
	 *
	 * @var int
	 */
	const ANONYMIZATION_DAYS = 30;

	/**
	 * Initialize cron cleanup
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		// Register cron hook
		add_action( 'fanfic_anonymize_old_data', array( __CLASS__, 'anonymize_old_votes_and_likes' ) );

		// Schedule cron on plugin activation (done in core class)
	}

	/**
	 * Schedule anonymization cron job
	 *
	 * Called during plugin activation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'fanfic_anonymize_old_data' ) ) {
			// Schedule daily at 3 AM server time
			wp_schedule_event( strtotime( '03:00:00' ), 'daily', 'fanfic_anonymize_old_data' );
		}
	}

	/**
	 * Unschedule anonymization cron job
	 *
	 * Called during plugin deactivation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( 'fanfic_anonymize_old_data' );
	}

	/**
	 * Anonymize old votes and likes
	 *
	 * Removes identifier_hash from votes and likes older than 30 days.
	 * Anonymous users (user_id = 0) only.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function anonymize_old_votes_and_likes() {
		global $wpdb;

		// === ANONYMIZE RATINGS ===

		// Get affected chapters (for cache rebuild)
		$affected_chapters = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT chapter_id
				FROM {$wpdb->prefix}fanfic_ratings
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND user_id = 0
				AND identifier_hash IS NOT NULL",
				self::ANONYMIZATION_DAYS
			)
		);

		// Anonymize ratings
		$ratings_anonymized = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fanfic_ratings
				SET identifier_hash = NULL
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND user_id = 0
				AND identifier_hash IS NOT NULL",
				self::ANONYMIZATION_DAYS
			)
		);

		// Rebuild rating caches for affected chapters
		foreach ( $affected_chapters as $chapter_id ) {
			// Rebuild chapter cache
			$cache_key = 'fanfic_chapter_' . $chapter_id . '_rating';
			delete_transient( $cache_key );

			// Rebuild story cache
			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				$story_cache_key = 'fanfic_story_' . $story_id . '_rating';
				delete_transient( $story_cache_key );
			}
		}

		// === ANONYMIZE LIKES ===

		// Get affected chapters (for cache rebuild)
		$affected_like_chapters = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT chapter_id
				FROM {$wpdb->prefix}fanfic_likes
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND user_id = 0
				AND identifier_hash IS NOT NULL",
				self::ANONYMIZATION_DAYS
			)
		);

		// Anonymize likes
		$likes_anonymized = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fanfic_likes
				SET identifier_hash = NULL
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND user_id = 0
				AND identifier_hash IS NOT NULL",
				self::ANONYMIZATION_DAYS
			)
		);

		// Rebuild like caches for affected chapters
		foreach ( $affected_like_chapters as $chapter_id ) {
			// Rebuild chapter cache
			$cache_key = 'fanfic_chapter_' . $chapter_id . '_likes';
			delete_transient( $cache_key );

			// Rebuild story cache
			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				$story_cache_key = 'fanfic_story_' . $story_id . '_likes';
				delete_transient( $story_cache_key );
			}
		}

		// Log cleanup results
		if ( $ratings_anonymized || $likes_anonymized ) {
			error_log( sprintf(
				'Fanfic Cleanup: Anonymized %d ratings and %d likes (chapters affected: %d ratings, %d likes)',
				$ratings_anonymized,
				$likes_anonymized,
				count( $affected_chapters ),
				count( $affected_like_chapters )
			) );
		}
	}

	/**
	 * Manually trigger anonymization (for testing/admin)
	 *
	 * @since 2.0.0
	 * @return array Results of anonymization.
	 */
	public static function manual_anonymize() {
		self::anonymize_old_votes_and_likes();

		return array(
			'success' => true,
			'message' => __( 'Anonymization completed successfully.', 'fanfiction-manager' ),
		);
	}
}
