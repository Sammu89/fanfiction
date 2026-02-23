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
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'Please log in to edit your profile.', 'fanfiction-manager' ); ?>
			<a href="<?php echo esc_url( wp_login_url( fanfic_get_current_url() ) ); ?>" class="fanfic-button"><?php esc_html_e( 'Login', 'fanfiction-manager' ); ?></a>
		</span>
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

?>

<!-- Unified Messages Container -->
<div id="fanfic-messages" class="fanfic-messages-container" role="region" aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>" aria-live="polite">
<?php
// Success message from URL
if ( isset( $_GET['updated'] ) && 'success' === $_GET['updated'] ) : ?>
	<div class="fanfic-message fanfic-message-success" role="status">
		<span class="fanfic-message-icon" aria-hidden="true">✓</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'Profile updated successfully.', 'fanfiction-manager' ); ?></span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif;

// Validation errors from transient
$errors = get_transient( 'fanfic_profile_errors_' . $current_user->ID );
if ( $errors ) {
	delete_transient( 'fanfic_profile_errors_' . $current_user->ID );
	?>
	<div class="fanfic-message fanfic-message-error" role="alert">
		<span class="fanfic-message-icon" aria-hidden="true">✕</span>
		<span class="fanfic-message-content">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}

/**
 * Hook for adding messages to the profile form.
 *
 * @since 1.2.0
 * @param WP_User $current_user The current user object.
 */
do_action( 'fanfic_profile_form_messages', $current_user );
?>
</div>

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
				<div class="fanfic-form-field fanfic-has-dropzone">
					<label for="fanfic_avatar_url"><?php esc_html_e( 'Avatar Image', 'fanfiction-manager' ); ?></label>

					<!-- Dropzone for WordPress Media Library -->
					<div
						id="fanfic_avatar_dropzone"
						class="fanfic-image-dropzone"
						data-target="#fanfic_avatar_url"
						data-title="<?php esc_attr_e( 'Select Avatar Image', 'fanfiction-manager' ); ?>"
						role="button"
						tabindex="0"
						aria-label="<?php esc_attr_e( 'Click or drag to upload avatar image', 'fanfiction-manager' ); ?>"
					>
						<div class="fanfic-dropzone-placeholder">
							<span class="fanfic-dropzone-placeholder-icon dashicons dashicons-cloud-upload" aria-hidden="true"></span>
							<span class="fanfic-dropzone-placeholder-text"><?php esc_html_e( 'Click to select avatar', 'fanfiction-manager' ); ?></span>
							<span class="fanfic-dropzone-placeholder-hint"><?php esc_html_e( 'or drag and drop here', 'fanfiction-manager' ); ?></span>
						</div>
					</div>

					<!-- Hidden URL input (populated by dropzone) -->
					<input
						type="url"
						id="fanfic_avatar_url"
						name="fanfic_avatar_url"
						class="fanfic-input"
						value="<?php echo isset( $_POST['fanfic_avatar_url'] ) ? esc_attr( $_POST['fanfic_avatar_url'] ) : esc_attr( $avatar_url ); ?>"
						placeholder="<?php esc_attr_e( 'Image URL will appear here', 'fanfiction-manager' ); ?>"
						aria-label="<?php esc_attr_e( 'Avatar image URL', 'fanfiction-manager' ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Select an image from your media library or enter a URL directly.', 'fanfiction-manager' ); ?>
					</p>
				</div>
			</div>

			<!-- Hidden fields -->
			<input type="hidden" name="fanfic_edit_profile_submit" value="1" />

			<!-- Form Actions -->
			<div class="fanfic-form-actions">
				<button type="submit" class="fanfic-button">
					<?php esc_html_e( 'Update Profile', 'fanfiction-manager' ); ?>
				</button>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button secondary">
					<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
				</a>
			</div>
		</form>
	</div>
</section>

<nav aria-label="<?php esc_attr_e( 'Profile navigation', 'fanfiction-manager' ); ?>">
	<p><a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?></a></p>
</nav>
