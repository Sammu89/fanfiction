<?php
/**
 * Media Cleanup Class
 *
 * Deletes image attachments that are not referenced by the uploader's
 * fanfiction stories, chapters, or avatar.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Media_Cleanup
 *
 * Schedules and runs media cleanup tasks.
 *
 * @since 2.0.0
 */
class Fanfic_Media_Cleanup {

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'fanfic_cleanup_orphaned_media';

	/**
	 * Initialize media cleanup hooks
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_orphaned_media' ) );
		add_action( 'fanfic_daily_maintenance', array( __CLASS__, 'cleanup_orphaned_media' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_cron();
		}
	}

	/**
	 * Schedule daily cleanup
	 *
	 * @since 2.0.0
	 * @return bool True if scheduled, false otherwise.
	 */
	public static function schedule_cron() {
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
		$next_run = self::calculate_next_run_time( $cron_hour );
		return wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
	}

	/**
	 * Unschedule daily cleanup
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Re-schedule cron when settings change
	 *
	 * @since 2.0.0
	 * @param array $old_value Previous settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public static function reschedule_on_settings_change( $old_value, $new_value ) {
		$old_hour = isset( $old_value['cron_hour'] ) ? absint( $old_value['cron_hour'] ) : 3;
		$new_hour = isset( $new_value['cron_hour'] ) ? absint( $new_value['cron_hour'] ) : 3;

		if ( $old_hour === $new_hour ) {
			return;
		}

		self::unschedule_cron();
		self::schedule_cron();
	}

	/**
	 * Calculate next run time based on the cron hour setting.
	 *
	 * @since 2.0.0
	 * @param int $cron_hour Hour (0-23).
	 * @return int Timestamp for next run.
	 */
	private static function calculate_next_run_time( $cron_hour ) {
		$current_time = current_time( 'timestamp' );
		$today = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $cron_hour ) );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Cleanup orphaned image attachments for each user.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function cleanup_orphaned_media() {
		$start = microtime( true );
		$max_runtime = 20;
		$deleted = 0;
		$page = 1;
		$per_page = 200;

		do {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				$author_id = (int) get_post_field( 'post_author', $attachment_id );
				if ( $author_id <= 0 ) {
					continue;
				}

				if ( self::is_attachment_referenced_by_user( $attachment_id, $author_id ) ) {
					continue;
				}

				if ( wp_delete_attachment( $attachment_id, true ) ) {
					$deleted++;
				}

				if ( microtime( true ) - $start > $max_runtime ) {
					break 2;
				}
			}

			$page++;
		} while ( true );

		if ( $deleted > 0 ) {
			error_log( sprintf( 'Fanfic Cleanup: Deleted %d unreferenced image attachments.', $deleted ) );
		}
	}

	/**
	 * Check if an attachment is referenced by the uploader's fanfic data.
	 *
	 * @since 2.0.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id User ID.
	 * @return bool True if referenced.
	 */
	private static function is_attachment_referenced_by_user( $attachment_id, $user_id ) {
		global $wpdb;

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( empty( $attachment_url ) ) {
			return false;
		}

		$story_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_fanfic_featured_image'
				AND pm.meta_value = %s
				AND p.post_type = 'fanfiction_story'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_url,
				$user_id
			)
		);
		if ( $story_url_match ) {
			return true;
		}

		$story_thumbnail_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d
				AND p.post_type = 'fanfiction_story'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_id,
				$user_id
			)
		);
		if ( $story_thumbnail_match ) {
			return true;
		}

		$chapter_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_fanfic_chapter_image_url'
				AND pm.meta_value = %s
				AND p.post_type = 'fanfiction_chapter'
				AND p.post_author = %d
				LIMIT 1",
				$attachment_url,
				$user_id
			)
		);
		if ( $chapter_url_match ) {
			return true;
		}

		$avatar_url_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT umeta_id
				FROM {$wpdb->usermeta}
				WHERE user_id = %d
				AND meta_key = '_fanfic_avatar_url'
				AND meta_value = %s
				LIMIT 1",
				$user_id,
				$attachment_url
			)
		);

		return ! empty( $avatar_url_match );
	}
}
