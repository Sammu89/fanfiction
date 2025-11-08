<?php
/**
 * Template: View User Profile
 *
 * Displays an individual user's profile page
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the user being viewed
$member_name = get_query_var( 'member_name' );
$user = get_user_by( 'login', $member_name );

if ( ! $user ) {
	?>
	<div class="fanfic-error-notice" role="alert">
		<p><?php esc_html_e( 'User not found.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
	return;
}
?>

<div class="fanfic-profile-single">
	<header class="fanfic-profile-header">
		<div class="fanfic-profile-avatar">
			<?php echo do_shortcode( '[author-avatar user_id="' . $user->ID . '"]' ); ?>
		</div>
		<div class="fanfic-profile-info">
			<h1><?php echo do_shortcode( '[author-display-name user_id="' . $user->ID . '"]' ); ?></h1>
			<div class="fanfic-profile-meta">
				<span class="fanfic-profile-joined">
					<?php
					printf(
						esc_html__( 'Joined: %s', 'fanfiction-manager' ),
						do_shortcode( '[author-registration-date user_id="' . $user->ID . '"]' )
					);
					?>
				</span>
				<span class="fanfic-profile-stories">
					<?php
					printf(
						esc_html__( 'Stories: %s', 'fanfiction-manager' ),
						do_shortcode( '[author-story-count user_id="' . $user->ID . '"]' )
					);
					?>
				</span>
			</div>
		</div>
	</header>

	<div class="fanfic-profile-actions">
		<?php echo do_shortcode( '[author-actions user_id="' . $user->ID . '"]' ); ?>
		<?php
		// Show edit profile button if current user is viewing their own profile or has edit_users capability
		if ( is_user_logged_in() && ( get_current_user_id() === $user->ID || current_user_can( 'edit_users' ) ) ) {
			$edit_url = add_query_arg( 'action', 'edit', get_permalink() );
			echo '<a href="' . esc_url( $edit_url ) . '" class="fanfic-button fanfic-button-secondary">' . esc_html__( 'Edit Profile', 'fanfiction-manager' ) . '</a>';
		}
		?>
	</div>

	<div class="fanfic-profile-bio">
		<h2><?php esc_html_e( 'About', 'fanfiction-manager' ); ?></h2>
		<?php echo do_shortcode( '[author-bio user_id="' . $user->ID . '"]' ); ?>
	</div>

	<div class="fanfic-profile-stories">
		<h2><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></h2>
		<?php echo do_shortcode( '[author-story-list user_id="' . $user->ID . '"]' ); ?>
	</div>
</div>
