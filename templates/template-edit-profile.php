<?php
/**
 * Template: Edit Profile
 * Description: User profile editing form
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert">
		<p>Please log in to edit your profile.</p>
		<p><a href="<?php echo esc_url( wp_login_url( fanfic_get_current_url() ) ); ?>" class="fanfic-button fanfic-button-primary">Login</a></p>
	</div>
	<?php
	return;
}

// Get current user data
$current_user = wp_get_current_user();
$display_name = $current_user->display_name;
$user_email = $current_user->user_email;
$user_url = $current_user->user_url;
$bio = $current_user->description;
$avatar_url = get_user_meta( $current_user->ID, '_fanfic_avatar_url', true );
$image_upload_settings = function_exists( 'fanfic_get_image_upload_settings' ) ? fanfic_get_image_upload_settings() : array( 'enabled' => false, 'max_value' => 1, 'max_unit' => 'mb' );
$image_upload_enabled = ! empty( $image_upload_settings['enabled'] );

// Get user stats for persistent header
$story_count = count( get_posts( array(
	'post_type'      => 'fanfiction_story',
	'author'         => $current_user->ID,
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) ) );
$published_story_count = count( get_posts( array(
	'post_type'      => 'fanfiction_story',
	'author'         => $current_user->ID,
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) ) );
?>

<!-- Persistent Header Container for System Messages -->
<div class="fanfic-persistent-header" id="fanfic-profile-header" role="region" aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>" aria-live="polite">
	<div class="fanfic-header-info">
		<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
		<span>
			<?php
			if ( $story_count === 0 ) {
				esc_html_e( 'Manage your author profile. You haven\'t written any stories yet.', 'fanfiction-manager' );
			} elseif ( $published_story_count === 0 ) {
				printf(
					/* translators: %d: Number of draft stories */
					esc_html( _n( 'Manage your author profile. You have %d unpublished story.', 'Manage your author profile. You have %d unpublished stories.', $story_count, 'fanfiction-manager' ) ),
					$story_count
				);
			} else {
				printf(
					/* translators: 1: Number of published stories, 2: Total number of stories */
					esc_html__( 'Manage your author profile. You have %1$d published of %2$d total stories.', 'fanfiction-manager' ),
					$published_story_count,
					$story_count
				);
			}
			?>
		</span>
	</div>

	<?php
	/**
	 * Hook for adding persistent header messages to the profile form.
	 *
	 * @since 1.2.0
	 * @param WP_User $current_user The current user object.
	 */
	do_action( 'fanfic_profile_form_header', $current_user );
	?>
</div>

<?php
// Success/error messages
$message = '';
if ( isset( $_GET['updated'] ) && 'success' === $_GET['updated'] ) {
	?>
	<div class="fanfic-info-box fanfic-success" role="alert">
		<?php esc_html_e( 'Profile updated successfully.', 'fanfiction-manager' ); ?>
	</div>
	<?php
}

$errors = get_transient( 'fanfic_profile_errors_' . $current_user->ID );
if ( $errors ) {
	delete_transient( 'fanfic_profile_errors_' . $current_user->ID );
	?>
	<div class="fanfic-info-box fanfic-error" role="alert">
		<ul>
			<?php foreach ( $errors as $error ) : ?>
				<li><?php echo esc_html( $error ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
?>

<p><?php esc_html_e( 'Update your author profile information.', 'fanfiction-manager' ); ?></p>

<section class="fanfic-content-section" role="region" aria-label="<?php esc_attr_e( 'Profile edit form', 'fanfiction-manager' ); ?>">
	<div class="fanfic-form-wrapper fanfic-profile-form">
		<div class="fanfic-form-header">
			<h2><?php esc_html_e( 'Edit Profile', 'fanfiction-manager' ); ?></h2>
		</div>

		<form method="post" class="fanfic-profile-form" id="fanfic-profile-form"<?php echo $image_upload_enabled ? ' enctype="multipart/form-data"' : ''; ?>>
			<div class="fanfic-form-content">
				<?php wp_nonce_field( 'fanfic_edit_profile_action', 'fanfic_edit_profile_nonce' ); ?>

				<!-- Display Name -->
				<div class="fanfic-form-field">
					<label for="display_name"><?php esc_html_e( 'Display Name', 'fanfiction-manager' ); ?></label>
					<input
						type="text"
						id="display_name"
						name="display_name"
						class="fanfic-input"
						required
						value="<?php echo isset( $_POST['display_name'] ) ? esc_attr( $_POST['display_name'] ) : esc_attr( $display_name ); ?>"
					/>
				</div>

				<!-- Email -->
				<div class="fanfic-form-field">
					<label for="user_email"><?php esc_html_e( 'Email Address', 'fanfiction-manager' ); ?></label>
					<input
						type="email"
						id="user_email"
						name="user_email"
						class="fanfic-input"
						required
						value="<?php echo isset( $_POST['user_email'] ) ? esc_attr( $_POST['user_email'] ) : esc_attr( $user_email ); ?>"
					/>
				</div>

				<!-- Website -->
				<div class="fanfic-form-field">
					<label for="user_url"><?php esc_html_e( 'Website', 'fanfiction-manager' ); ?></label>
					<input
						type="url"
						id="user_url"
						name="user_url"
						class="fanfic-input"
						value="<?php echo isset( $_POST['user_url'] ) ? esc_attr( $_POST['user_url'] ) : esc_attr( $user_url ); ?>"
					/>
				</div>

				<!-- Bio -->
				<div class="fanfic-form-field">
					<label for="description"><?php esc_html_e( 'Bio', 'fanfiction-manager' ); ?></label>
					<textarea
						id="description"
						name="description"
						class="fanfic-textarea"
						rows="6"
						maxlength="5000"
					><?php echo isset( $_POST['description'] ) ? esc_textarea( $_POST['description'] ) : esc_textarea( $bio ); ?></textarea>
					<p class="fanfic-field-description">
						<span class="char-count"><?php echo esc_html( strlen( $bio ) ); ?></span> / 5000
						<?php esc_html_e( 'characters', 'fanfiction-manager' ); ?>
					</p>
				</div>

				<!-- Avatar URL -->
				<div class="fanfic-form-field">
					<label for="fanfic_avatar_url"><?php esc_html_e( 'Avatar Image URL', 'fanfiction-manager' ); ?></label>
					<input
						type="url"
						id="fanfic_avatar_url"
						name="fanfic_avatar_url"
						class="fanfic-input"
						value="<?php echo isset( $_POST['fanfic_avatar_url'] ) ? esc_attr( $_POST['fanfic_avatar_url'] ) : esc_attr( $avatar_url ); ?>"
					/>
					<?php if ( $image_upload_enabled ) : ?>
						<label for="fanfic_avatar_file" style="margin-top: 10px; display: block;">
							<?php esc_html_e( 'Or upload an avatar', 'fanfiction-manager' ); ?>
						</label>
						<input
							type="file"
							id="fanfic_avatar_file"
							name="fanfic_avatar_file"
							class="fanfic-input"
							accept="image/*"
							data-url-target="#fanfic_avatar_url"
						/>
						<button
							type="button"
							class="button fanfic-ajax-upload-button"
							data-file-input="#fanfic_avatar_file"
							data-nonce="<?php echo wp_create_nonce( 'fanfic_ajax_image_upload' ); ?>"
							data-context="<?php esc_attr_e( 'Avatar', 'fanfiction-manager' ); ?>"
						>
							<?php esc_html_e( 'Upload', 'fanfiction-manager' ); ?>
						</button>
						<span class="fanfic-upload-status"></span>
						<p class="description">
							<?php esc_html_e( 'Larger images will be resized to 1024px and converted to WEBP format.', 'fanfiction-manager' ); ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $avatar_url ) ) : ?>
						<div class="fanfic-avatar-preview">
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Avatar preview', 'fanfiction-manager' ); ?>" />
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Hidden fields -->
			<input type="hidden" name="fanfic_edit_profile_submit" value="1" />

			<!-- Form Actions -->
			<div class="fanfic-form-actions">
				<button type="submit" class="fanfic-button fanfic-button-primary">
					<?php esc_html_e( 'Update Profile', 'fanfiction-manager' ); ?>
				</button>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-secondary">
					<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
				</a>
			</div>
		</form>
	</div>
</section>

<nav aria-label="<?php esc_attr_e( 'Profile navigation', 'fanfiction-manager' ); ?>">
	<p><a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?></a></p>
</nav>
