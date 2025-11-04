<?php
/**
 * User Profile Shortcodes
 *
 * @package Fanfiction_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanfic_Shortcodes_Profile {

	/**
	 * Register shortcodes
	 */
	public static function init() {
		add_shortcode( 'user-profile', array( __CLASS__, 'user_profile' ) );
	}

	/**
	 * Display user profile
	 * Reads ?member=username parameter
	 */
	public static function user_profile( $atts ) {
		// Get member from URL parameter
		$member_username = isset( $_GET['member'] ) ? sanitize_user( $_GET['member'] ) : '';

		if ( empty( $member_username ) ) {
			return '<p>' . esc_html__( 'No user specified.', 'fanfiction-manager' ) . '</p>';
		}

		// Get user by username
		$user = get_user_by( 'login', $member_username );

		if ( ! $user ) {
			return '<p>' . esc_html__( 'User not found.', 'fanfiction-manager' ) . '</p>';
		}

		// Check if user is fanfiction author
		$is_author = in_array( 'fanfiction_author', (array) $user->roles, true ) ||
		             in_array( 'administrator', (array) $user->roles, true );

		ob_start();
		?>
		<div class="fanfic-user-profile">
			<div class="profile-header">
				<?php echo get_avatar( $user->ID, 150, '', $user->display_name, array( 'class' => 'profile-avatar' ) ); ?>
				<h2 class="profile-name"><?php echo esc_html( $user->display_name ); ?></h2>
				<p class="profile-username">@<?php echo esc_html( $user->user_login ); ?></p>
			</div>

			<?php if ( $is_author ) : ?>
				<div class="profile-bio">
					<?php
					$bio = get_user_meta( $user->ID, 'fanfic_author_bio', true );
					if ( $bio ) {
						echo '<h3>' . esc_html__( 'About', 'fanfiction-manager' ) . '</h3>';
						echo '<p>' . wp_kses_post( nl2br( $bio ) ) . '</p>';
					}
					?>
				</div>

				<div class="profile-stats">
					<h3><?php esc_html_e( 'Statistics', 'fanfiction-manager' ); ?></h3>
					<?php echo do_shortcode( '[author-stats user_id="' . $user->ID . '"]' ); ?>
				</div>

				<div class="profile-actions">
					<?php echo do_shortcode( '[author-follow-button author_id="' . $user->ID . '"]' ); ?>
				</div>

				<div class="profile-stories">
					<h3><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></h3>
					<?php echo do_shortcode( '[story-list author_id="' . $user->ID . '"]' ); ?>
				</div>
			<?php else : ?>
				<div class="profile-message">
					<p><?php esc_html_e( 'This user is not an author.', 'fanfiction-manager' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
