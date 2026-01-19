<?php
/**
 * Forms Shortcodes Class
 *
 * Handles all form-related shortcodes (login, register, password reset, ratings).
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Forms
 *
 * Form display and handling shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Forms {

	/**
	 * Register forms shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfic-login-form', array( __CLASS__, 'login_form' ) );
		add_shortcode( 'fanfic-register-form', array( __CLASS__, 'register_form' ) );
		add_shortcode( 'fanfic-password-reset-form', array( __CLASS__, 'password_reset_form' ) );
		add_shortcode( 'story-rating-form', array( __CLASS__, 'story_rating_form' ) );
		add_shortcode( 'chapter-rating-form', array( __CLASS__, 'chapter_rating_form' ) );
		add_shortcode( 'report-content', array( __CLASS__, 'report_content_form' ) );

		// Register form submission handlers
		add_action( 'init', array( __CLASS__, 'handle_login_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_register_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_password_reset_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_report_content_submission' ) );
	}

	/**
	 * Login form shortcode
	 *
	 * [fanfic-login-form]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Login form HTML.
	 */
	public static function login_form( $atts ) {
		// If user is already logged in, show message
		if ( is_user_logged_in() ) {
			return '<div class="fanfic-info-box fanfic-info">' .
				esc_html__( 'You are already logged in.', 'fanfiction-manager' ) .
				'</div>';
		}

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'redirect' => '',
			),
			'fanfic-login-form'
		);

		// Get redirect URL
		$redirect_to = ! empty( $atts['redirect'] ) ? $atts['redirect'] : home_url();

		// Check for error/success messages
		$message = '';
		if ( isset( $_GET['login'] ) ) {
			if ( 'failed' === $_GET['login'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'Login failed. Please check your username and password.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'empty' === $_GET['login'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'Please enter your username and password.', 'fanfiction-manager' ) .
					'</div>';
			}
		}

		ob_start();
		?>
		<div class="fanfic-login-form-wrapper">
			<?php echo $message; ?>
			<form class="fanfic-login-form" method="post" action="" novalidate>
				<?php wp_nonce_field( 'fanfic_login_action', 'fanfic_login_nonce' ); ?>

				<div class="fanfic-form-field">
					<label for="fanfic_username">
						<?php esc_html_e( 'Username or Email', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="text"
						name="fanfic_username"
						id="fanfic_username"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="username"
						value="<?php echo isset( $_POST['fanfic_username'] ) ? esc_attr( $_POST['fanfic_username'] ) : ''; ?>"
					/>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_password">
						<?php esc_html_e( 'Password', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="password"
						name="fanfic_password"
						id="fanfic_password"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="current-password"
					/>
				</div>

				<div class="fanfic-form-field fanfic-checkbox-field">
					<label>
						<input
							type="checkbox"
							name="fanfic_remember"
							id="fanfic_remember"
							value="1"
						/>
						<?php esc_html_e( 'Remember Me', 'fanfiction-manager' ); ?>
					</label>
				</div>

				<input type="hidden" name="fanfic_redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
				<input type="hidden" name="fanfic_login_submit" value="1" />

				<div class="fanfic-form-actions">
					<button type="submit" class="fanfic-button fanfic-button-primary">
						<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Registration form shortcode
	 *
	 * [fanfic-register-form]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Registration form HTML.
	 */
	public static function register_form( $atts ) {
		// If user is already logged in, show message
		if ( is_user_logged_in() ) {
			return '<div class="fanfic-info-box fanfic-info">' .
				esc_html__( 'You are already registered and logged in.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check if registration is enabled
		if ( ! get_option( 'users_can_register' ) ) {
			return '<div class="fanfic-info-box fanfic-error">' .
				esc_html__( 'User registration is currently disabled.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check for error/success messages
		$message = '';
		if ( isset( $_GET['register'] ) ) {
			if ( 'success' === $_GET['register'] ) {
				$message = '<div class="fanfic-info-box fanfic-success" role="alert">' .
					esc_html__( 'Registration successful! You can now log in.', 'fanfiction-manager' ) .
					'</div>';
			}
		}

		// Get validation errors from transient
		$errors = array();
		$errors = get_transient( 'fanfic_register_errors' );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		delete_transient( 'fanfic_register_errors' );

		ob_start();
		?>
		<div class="fanfic-register-form-wrapper">
			<?php echo $message; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="fanfic-info-box fanfic-error" role="alert">
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form class="fanfic-register-form" method="post" action="" novalidate>
				<?php wp_nonce_field( 'fanfic_register_action', 'fanfic_register_nonce' ); ?>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_username">
						<?php esc_html_e( 'Username', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="text"
						name="fanfic_reg_username"
						id="fanfic_reg_username"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="username"
						value="<?php echo isset( $_POST['fanfic_reg_username'] ) ? esc_attr( $_POST['fanfic_reg_username'] ) : ''; ?>"
					/>
					<p class="fanfic-field-description"><?php esc_html_e( 'Username cannot be changed later.', 'fanfiction-manager' ); ?></p>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_email">
						<?php esc_html_e( 'Email', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="email"
						name="fanfic_reg_email"
						id="fanfic_reg_email"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="email"
						value="<?php echo isset( $_POST['fanfic_reg_email'] ) ? esc_attr( $_POST['fanfic_reg_email'] ) : ''; ?>"
					/>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_password">
						<?php esc_html_e( 'Password', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="password"
						name="fanfic_reg_password"
						id="fanfic_reg_password"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="new-password"
					/>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_password_confirm">
						<?php esc_html_e( 'Confirm Password', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="password"
						name="fanfic_reg_password_confirm"
						id="fanfic_reg_password_confirm"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="new-password"
					/>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_display_name">
						<?php esc_html_e( 'Display Name', 'fanfiction-manager' ); ?>
					</label>
					<input
						type="text"
						name="fanfic_reg_display_name"
						id="fanfic_reg_display_name"
						class="fanfic-input"
						autocomplete="name"
						value="<?php echo isset( $_POST['fanfic_reg_display_name'] ) ? esc_attr( $_POST['fanfic_reg_display_name'] ) : ''; ?>"
					/>
					<p class="fanfic-field-description"><?php esc_html_e( 'If left empty, your username will be used.', 'fanfiction-manager' ); ?></p>
				</div>

				<div class="fanfic-form-field">
					<label for="fanfic_reg_bio">
						<?php esc_html_e( 'Bio (Optional)', 'fanfiction-manager' ); ?>
					</label>
					<textarea
						name="fanfic_reg_bio"
						id="fanfic_reg_bio"
						class="fanfic-textarea"
						rows="4"
						maxlength="3000"
					><?php echo isset( $_POST['fanfic_reg_bio'] ) ? esc_textarea( $_POST['fanfic_reg_bio'] ) : ''; ?></textarea>
					<p class="fanfic-field-description"><?php esc_html_e( 'Plain text only, max 3000 characters.', 'fanfiction-manager' ); ?></p>
				</div>

				<input type="hidden" name="fanfic_register_submit" value="1" />

				<div class="fanfic-form-actions">
					<button type="submit" class="fanfic-button fanfic-button-primary">
						<?php esc_html_e( 'Register', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Password reset form shortcode
	 *
	 * [fanfic-password-reset-form]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Password reset form HTML.
	 */
	public static function password_reset_form( $atts ) {
		// If user is already logged in, show message
		if ( is_user_logged_in() ) {
			return '<div class="fanfic-info-box fanfic-info">' .
				esc_html__( 'You are already logged in. If you need to change your password, please use your profile settings.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check for error/success messages
		$message = '';
		if ( isset( $_GET['password-reset'] ) ) {
			if ( 'sent' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-info-box fanfic-success" role="alert">' .
					esc_html__( 'Password reset instructions have been sent to your email address.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'invalid' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'Invalid email address.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'empty' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'Please enter your email address.', 'fanfiction-manager' ) .
					'</div>';
			}
		}

		ob_start();
		?>
		<div class="fanfic-password-reset-form-wrapper">
			<?php echo $message; ?>
			<form class="fanfic-password-reset-form" method="post" action="" novalidate>
				<?php wp_nonce_field( 'fanfic_password_reset_action', 'fanfic_password_reset_nonce' ); ?>

				<div class="fanfic-form-field">
					<label for="fanfic_reset_email">
						<?php esc_html_e( 'Email Address', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<input
						type="email"
						name="fanfic_reset_email"
						id="fanfic_reset_email"
						class="fanfic-input"
						required
						aria-required="true"
						autocomplete="email"
						value="<?php echo isset( $_POST['fanfic_reset_email'] ) ? esc_attr( $_POST['fanfic_reset_email'] ) : ''; ?>"
					/>
					<p class="fanfic-field-description"><?php esc_html_e( 'Enter your email address and we will send you instructions to reset your password.', 'fanfiction-manager' ); ?></p>
				</div>

				<input type="hidden" name="fanfic_password_reset_submit" value="1" />

				<div class="fanfic-form-actions">
					<button type="submit" class="fanfic-button fanfic-button-primary">
						<?php esc_html_e( 'Reset Password', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Story rating form shortcode
	 *
	 * [story-rating-form]
	 * Displays the mean of all chapter ratings (read-only)
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Updated to use new rating system
	 * @param array $atts Shortcode attributes.
	 * @return string Story rating display HTML.
	 */
	public static function story_rating_form( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		// Get story rating data from new rating system
		$rating_data = Fanfic_Rating_System::get_story_rating( $story_id );

		if ( ! $rating_data || $rating_data->total_votes === 0 ) {
			return '';
		}

		$avg_rating = $rating_data->average_rating;
		$total_ratings = $rating_data->total_votes;

		ob_start();
		?>
		<div class="fanfic-story-rating" aria-label="<?php esc_attr_e( 'Story rating', 'fanfiction-manager' ); ?>">
			<div class="fanfic-rating-stars fanfic-rating-readonly" data-rating="<?php echo esc_attr( $avg_rating ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<?php
					$star_class = 'fanfic-star';
					if ( $i <= floor( $avg_rating ) ) {
						$star_class .= ' active';
					}
					?>
					<span class="<?php echo esc_attr( $star_class ); ?>" data-value="<?php echo esc_attr( $i ); ?>" aria-hidden="true">&#9734;</span>
				<?php endfor; ?>
			</div>
			<div class="fanfic-rating-info">
				<span class="fanfic-rating-average"><?php echo esc_html( number_format_i18n( $avg_rating, 1 ) ); ?></span>
				<span class="fanfic-rating-count">
					<?php
					printf(
						/* translators: %s: number of ratings */
						esc_html( _n( '(%s rating)', '(%s ratings)', $total_ratings, 'fanfiction-manager' ) ),
						esc_html( number_format_i18n( $total_ratings ) )
					);
					?>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Chapter rating form shortcode
	 *
	 * [chapter-rating-form]
	 * 1-5 star rating (new system v2.0)
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Updated to use new rating system with browser fingerprinting
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter rating form HTML.
	 */
	public static function chapter_rating_form( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		// Get chapter rating data from new rating system
		$rating_data = Fanfic_Rating_System::get_chapter_rating_stats( $chapter_id );

		$avg_rating = $rating_data ? $rating_data->average_rating : 0;
		$total_ratings = $rating_data ? $rating_data->total_votes : 0;

		ob_start();
		?>
		<div class="fanfic-rating-widget" data-chapter-id="<?php echo esc_attr( $chapter_id ); ?>">
			<div class="fanfic-rating-stars">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span class="fanfic-star star" data-rating="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d stars', 'fanfiction-manager' ), $i ) ); ?>">&#9734;</span>
				<?php endfor; ?>
			</div>
			<div class="fanfic-rating-info">
				<span class="fanfic-rating-average"><?php echo esc_html( number_format_i18n( $avg_rating, 1 ) ); ?></span>
				<span class="fanfic-rating-count">
					<?php
					if ( $total_ratings === 0 ) {
						esc_html_e( '(No ratings yet)', 'fanfiction-manager' );
					} else {
						printf(
							/* translators: %s: number of ratings */
							esc_html( _n( '(%s rating)', '(%s ratings)', $total_ratings, 'fanfiction-manager' ) ),
							esc_html( number_format_i18n( $total_ratings ) )
						);
					}
					?>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle login form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_login_submission() {
		if ( ! isset( $_POST['fanfic_login_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_login_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_login_nonce'], 'fanfic_login_action' ) ) {
			return;
		}

		// Get form data
		$username = isset( $_POST['fanfic_username'] ) ? sanitize_text_field( $_POST['fanfic_username'] ) : '';
		$password = isset( $_POST['fanfic_password'] ) ? $_POST['fanfic_password'] : '';
		$remember = isset( $_POST['fanfic_remember'] ) ? true : false;
		$redirect_to = isset( $_POST['fanfic_redirect_to'] ) ? esc_url_raw( $_POST['fanfic_redirect_to'] ) : home_url();

		// Validate
		if ( empty( $username ) || empty( $password ) ) {
			wp_redirect( add_query_arg( 'login', 'empty', wp_get_referer() ) );
			exit;
		}

		// Attempt login
		$credentials = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_redirect( add_query_arg( 'login', 'failed', wp_get_referer() ) );
			exit;
		}

		// Success - redirect
		wp_redirect( $redirect_to );
		exit;
	}

	/**
	 * Handle registration form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_register_submission() {
		if ( ! isset( $_POST['fanfic_register_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_register_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_register_nonce'], 'fanfic_register_action' ) ) {
			return;
		}

		// Check if registration is enabled
		if ( ! get_option( 'users_can_register' ) ) {
			return;
		}

		// Initialize error collection (will be stored in transient)

		$errors = array();

		// Get form data
		$username = isset( $_POST['fanfic_reg_username'] ) ? sanitize_user( $_POST['fanfic_reg_username'] ) : '';
		$email = isset( $_POST['fanfic_reg_email'] ) ? sanitize_email( $_POST['fanfic_reg_email'] ) : '';
		$password = isset( $_POST['fanfic_reg_password'] ) ? $_POST['fanfic_reg_password'] : '';
		$password_confirm = isset( $_POST['fanfic_reg_password_confirm'] ) ? $_POST['fanfic_reg_password_confirm'] : '';
		$display_name = isset( $_POST['fanfic_reg_display_name'] ) ? sanitize_text_field( $_POST['fanfic_reg_display_name'] ) : '';
		$bio = isset( $_POST['fanfic_reg_bio'] ) ? sanitize_textarea_field( $_POST['fanfic_reg_bio'] ) : '';

		// Validate username
		if ( empty( $username ) ) {
			$errors[] = __( 'Username is required.', 'fanfiction-manager' );
		} elseif ( username_exists( $username ) ) {
			$errors[] = __( 'Username already exists.', 'fanfiction-manager' );
		}

		// Validate email
		if ( empty( $email ) ) {
			$errors[] = __( 'Email is required.', 'fanfiction-manager' );
		} elseif ( ! is_email( $email ) ) {
			$errors[] = __( 'Invalid email address.', 'fanfiction-manager' );
		} elseif ( email_exists( $email ) ) {
			$errors[] = __( 'Email already registered.', 'fanfiction-manager' );
		}

		// Validate password
		if ( empty( $password ) ) {
			$errors[] = __( 'Password is required.', 'fanfiction-manager' );
		} elseif ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters.', 'fanfiction-manager' );
		} elseif ( $password !== $password_confirm ) {
			$errors[] = __( 'Passwords do not match.', 'fanfiction-manager' );
		}

		// Validate bio length
		if ( ! empty( $bio ) && strlen( $bio ) > 3000 ) {
			$errors[] = __( 'Bio must be less than 3000 characters.', 'fanfiction-manager' );
		}

		// If errors, store in transient and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_register_errors', $errors, HOUR_IN_SECONDS );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			set_transient( 'fanfic_register_errors', array( $user_id->get_error_message() ), HOUR_IN_SECONDS );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Set display name
		if ( ! empty( $display_name ) ) {
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $display_name,
			) );
		}

		// Set bio
		if ( ! empty( $bio ) ) {
			update_user_meta( $user_id, 'description', $bio );
		}

		// Set default role to Fanfiction Reader
		$user = new WP_User( $user_id );
		$user->set_role( 'fanfiction_reader' );

		// Success - redirect to login with success message
		wp_redirect( add_query_arg( 'register', 'success', wp_get_referer() ) );
		exit;
	}

	/**
	 * Handle password reset form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_password_reset_submission() {
		if ( ! isset( $_POST['fanfic_password_reset_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_password_reset_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_password_reset_nonce'], 'fanfic_password_reset_action' ) ) {
			return;
		}

		// Get email
		$email = isset( $_POST['fanfic_reset_email'] ) ? sanitize_email( $_POST['fanfic_reset_email'] ) : '';

		// Validate
		if ( empty( $email ) ) {
			wp_redirect( add_query_arg( 'password-reset', 'empty', wp_get_referer() ) );
			exit;
		}

		if ( ! is_email( $email ) ) {
			wp_redirect( add_query_arg( 'password-reset', 'invalid', wp_get_referer() ) );
			exit;
		}

		// Get user by email
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			// For security, don't reveal if email exists or not
			wp_redirect( add_query_arg( 'password-reset', 'sent', wp_get_referer() ) );
			exit;
		}

		// Send password reset email
		$result = retrieve_password( $user->user_login );

		if ( is_wp_error( $result ) ) {
			wp_redirect( add_query_arg( 'password-reset', 'invalid', wp_get_referer() ) );
			exit;
		}

		// Success
		wp_redirect( add_query_arg( 'password-reset', 'sent', wp_get_referer() ) );
		exit;
	}

	/**
	 * Report content form shortcode
	 *
	 * [report-content content_id="123" content_type="story"]
	 *
	 * Displays a form to report stories, chapters, or comments.
	 * Auto-detects content type from current post if not specified.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Report form HTML.
	 */
	public static function report_content_form( $atts ) {
		global $post;

		// Parse attributes
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'content_id'   => 0,
				'content_type' => '',
			),
			'report-content'
		);

		// Get content ID - from attribute or current post
		$content_id = absint( $atts['content_id'] );
		if ( ! $content_id && $post ) {
			$content_id = $post->ID;
		}

		if ( ! $content_id ) {
			return '<div class="fanfic-info-box fanfic-error">' .
				esc_html__( 'No content specified for reporting.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Auto-detect content type if not specified
		$content_type = sanitize_text_field( $atts['content_type'] );
		if ( empty( $content_type ) && $post ) {
			if ( 'fanfiction_story' === $post->post_type ) {
				$content_type = 'story';
			} elseif ( 'fanfiction_chapter' === $post->post_type ) {
				$content_type = 'chapter';
			}
		}

		// Validate content type
		if ( ! in_array( $content_type, array( 'story', 'chapter', 'comment' ), true ) ) {
			return '<div class="fanfic-info-box fanfic-error">' .
				esc_html__( 'Invalid content type. Must be story, chapter, or comment.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Get content details for display
		$content_title = '';
		$content_link = '';

		if ( 'comment' === $content_type ) {
			$comment = get_comment( $content_id );
			if ( ! $comment ) {
				return '<div class="fanfic-info-box fanfic-error">' .
					esc_html__( 'Comment not found.', 'fanfiction-manager' ) .
					'</div>';
			}
			$content_title = sprintf(
				/* translators: 1: comment author name, 2: comment date */
				__( 'Comment by %1$s on %2$s', 'fanfiction-manager' ),
				$comment->comment_author,
				date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) )
			);
			$content_link = get_comment_link( $comment );
		} else {
			$content = get_post( $content_id );
			if ( ! $content ) {
				return '<div class="fanfic-info-box fanfic-error">' .
					esc_html__( 'Content not found.', 'fanfiction-manager' ) .
					'</div>';
			}

			// Validate post type matches content type
			$expected_type = ( 'story' === $content_type ) ? 'fanfiction_story' : 'fanfiction_chapter';
			if ( $content->post_type !== $expected_type ) {
				return '<div class="fanfic-info-box fanfic-error">' .
					esc_html__( 'Content type mismatch.', 'fanfiction-manager' ) .
					'</div>';
			}

			$content_title = get_the_title( $content );
			$content_link = get_permalink( $content );
		}

		// Check for success/error messages
		$message = '';
		if ( isset( $_GET['report'] ) ) {
			if ( 'success' === $_GET['report'] ) {
				$message = '<div class="fanfic-info-box fanfic-success" role="alert">' .
					esc_html__( 'Thank you for your report. Our moderation team will review it shortly.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'duplicate' === $_GET['report'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'You have already reported this content.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'recaptcha_failed' === $_GET['report'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'reCAPTCHA verification failed. Please try again.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'error' === $_GET['report'] ) {
				$message = '<div class="fanfic-info-box fanfic-error" role="alert">' .
					esc_html__( 'Failed to submit report. Please try again.', 'fanfiction-manager' ) .
					'</div>';
			}
		}

		// Get validation errors from transient
		$errors = get_transient( 'fanfic_report_errors_' . get_current_user_id() );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		delete_transient( 'fanfic_report_errors_' . get_current_user_id() );

		// Get reCAPTCHA configuration
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
		$recaptcha_secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
		$recaptcha_require_logged_in = get_option( 'fanfic_settings', array() );
		$recaptcha_require_logged_in = isset( $recaptcha_require_logged_in['recaptcha_require_logged_in'] ) ? $recaptcha_require_logged_in['recaptcha_require_logged_in'] : false;

		// Determine if reCAPTCHA should be shown
		$show_recaptcha = ! empty( $recaptcha_site_key ) && ! empty( $recaptcha_secret_key );
		if ( ! $recaptcha_require_logged_in && is_user_logged_in() ) {
			$show_recaptcha = false;
		}

		// Enqueue reCAPTCHA script if needed
		if ( $show_recaptcha ) {
			wp_enqueue_script(
				'google-recaptcha',
				'https://www.google.com/recaptcha/api.js',
				array(),
				null,
				true
			);
		}

		ob_start();
		?>
		<div class="fanfic-report-form-wrapper">
			<?php echo $message; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="fanfic-info-box fanfic-error" role="alert">
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( empty( $recaptcha_site_key ) || empty( $recaptcha_secret_key ) ) : ?>
				<div class="fanfic-info-box fanfic-info" role="alert">
					<?php
					if ( current_user_can( 'manage_options' ) ) {
						printf(
							/* translators: %s: URL to settings page */
							esc_html__( 'Note: reCAPTCHA is not configured. Please configure it in %s to protect this form from spam.', 'fanfiction-manager' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=fanfiction-settings&tab=general' ) ) . '">' . esc_html__( 'Settings', 'fanfiction-manager' ) . '</a>'
						);
					}
					?>
				</div>
			<?php endif; ?>

			<form class="fanfic-report-form" method="post" action="" novalidate>
				<?php wp_nonce_field( 'fanfic_report_content', 'fanfic_report_nonce' ); ?>

				<input type="hidden" name="fanfic_report_content_id" value="<?php echo esc_attr( $content_id ); ?>" />
				<input type="hidden" name="fanfic_report_content_type" value="<?php echo esc_attr( $content_type ); ?>" />

				<!-- Content being reported -->
				<div class="fanfic-form-field fanfic-report-content-display">
					<label><?php esc_html_e( 'Reporting:', 'fanfiction-manager' ); ?></label>
					<div class="fanfic-reported-content">
						<strong>
							<?php if ( ! empty( $content_link ) ) : ?>
								<a href="<?php echo esc_url( $content_link ); ?>" target="_blank">
									<?php echo esc_html( $content_title ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $content_title ); ?>
							<?php endif; ?>
						</strong>
					</div>
				</div>

				<!-- Reason dropdown -->
				<div class="fanfic-form-field">
					<label for="fanfic_report_reason">
						<?php esc_html_e( 'Reason', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<select
						name="fanfic_report_reason"
						id="fanfic_report_reason"
						class="fanfic-select"
						required
						aria-required="true"
					>
						<option value=""><?php esc_html_e( 'Select a reason...', 'fanfiction-manager' ); ?></option>
						<option value="spam" <?php selected( isset( $_POST['fanfic_report_reason'] ) ? $_POST['fanfic_report_reason'] : '', 'spam' ); ?>>
							<?php esc_html_e( 'Spam', 'fanfiction-manager' ); ?>
						</option>
						<option value="inappropriate" <?php selected( isset( $_POST['fanfic_report_reason'] ) ? $_POST['fanfic_report_reason'] : '', 'inappropriate' ); ?>>
							<?php esc_html_e( 'Inappropriate Content', 'fanfiction-manager' ); ?>
						</option>
						<option value="copyright" <?php selected( isset( $_POST['fanfic_report_reason'] ) ? $_POST['fanfic_report_reason'] : '', 'copyright' ); ?>>
							<?php esc_html_e( 'Copyright Violation', 'fanfiction-manager' ); ?>
						</option>
						<option value="harassment" <?php selected( isset( $_POST['fanfic_report_reason'] ) ? $_POST['fanfic_report_reason'] : '', 'harassment' ); ?>>
							<?php esc_html_e( 'Harassment or Bullying', 'fanfiction-manager' ); ?>
						</option>
						<option value="other" <?php selected( isset( $_POST['fanfic_report_reason'] ) ? $_POST['fanfic_report_reason'] : '', 'other' ); ?>>
							<?php esc_html_e( 'Other', 'fanfiction-manager' ); ?>
						</option>
					</select>
				</div>

				<!-- Additional details -->
				<div class="fanfic-form-field">
					<label for="fanfic_report_details">
						<?php esc_html_e( 'Additional Details', 'fanfiction-manager' ); ?>
						<span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
					</label>
					<textarea
						name="fanfic_report_details"
						id="fanfic_report_details"
						class="fanfic-textarea"
						rows="5"
						maxlength="2000"
						required
						aria-required="true"
						placeholder="<?php esc_attr_e( 'Please provide specific details about why you are reporting this content...', 'fanfiction-manager' ); ?>"
					><?php echo isset( $_POST['fanfic_report_details'] ) ? esc_textarea( $_POST['fanfic_report_details'] ) : ''; ?></textarea>
					<p class="fanfic-field-description"><?php esc_html_e( 'Max 2000 characters. Please be specific.', 'fanfiction-manager' ); ?></p>
				</div>

				<!-- reCAPTCHA -->
				<?php if ( $show_recaptcha ) : ?>
					<div class="fanfic-form-field fanfic-recaptcha-field">
						<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $recaptcha_site_key ); ?>"></div>
					</div>
				<?php endif; ?>

				<input type="hidden" name="fanfic_report_submit" value="1" />

				<div class="fanfic-form-actions">
					<button type="submit" class="fanfic-button fanfic-button-primary">
						<?php esc_html_e( 'Submit Report', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle report content form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_report_content_submission() {
		if ( ! isset( $_POST['fanfic_report_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_report_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_report_nonce'], 'fanfic_report_content' ) ) {
			return;
		}

		// Get form data
		$content_id = isset( $_POST['fanfic_report_content_id'] ) ? absint( $_POST['fanfic_report_content_id'] ) : 0;
		$content_type = isset( $_POST['fanfic_report_content_type'] ) ? sanitize_text_field( $_POST['fanfic_report_content_type'] ) : '';
		$reason = isset( $_POST['fanfic_report_reason'] ) ? sanitize_text_field( $_POST['fanfic_report_reason'] ) : '';
		$details = isset( $_POST['fanfic_report_details'] ) ? wp_kses_post( $_POST['fanfic_report_details'] ) : '';

		// Initialize errors array
		$errors = array();

		// Validate content ID
		if ( ! $content_id ) {
			$errors[] = __( 'Invalid content ID.', 'fanfiction-manager' );
		}

		// Validate content type
		if ( ! in_array( $content_type, array( 'story', 'chapter', 'comment' ), true ) ) {
			$errors[] = __( 'Invalid content type.', 'fanfiction-manager' );
		}

		// Validate reason
		$valid_reasons = array( 'spam', 'inappropriate', 'copyright', 'harassment', 'other' );
		if ( empty( $reason ) || ! in_array( $reason, $valid_reasons, true ) ) {
			$errors[] = __( 'Please select a reason for reporting.', 'fanfiction-manager' );
		}

		// Validate details
		if ( empty( $details ) ) {
			$errors[] = __( 'Please provide additional details.', 'fanfiction-manager' );
		} elseif ( strlen( $details ) > 2000 ) {
			$errors[] = __( 'Details must be less than 2000 characters.', 'fanfiction-manager' );
		}

		// Verify content exists
		if ( empty( $errors ) ) {
			if ( 'comment' === $content_type ) {
				$comment = get_comment( $content_id );
				if ( ! $comment ) {
					$errors[] = __( 'Comment not found.', 'fanfiction-manager' );
				}
			} else {
				$post_type = ( 'story' === $content_type ) ? 'fanfiction_story' : 'fanfiction_chapter';
				$content = get_post( $content_id );
				if ( ! $content || $post_type !== $content->post_type ) {
					$errors[] = __( 'Content not found.', 'fanfiction-manager' );
				}
			}
		}

		// Verify reCAPTCHA if configured
		$recaptcha_secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
		$recaptcha_settings = get_option( 'fanfic_settings', array() );
		$recaptcha_require_logged_in = isset( $recaptcha_settings['recaptcha_require_logged_in'] ) ? $recaptcha_settings['recaptcha_require_logged_in'] : false;

		$should_verify_recaptcha = ! empty( $recaptcha_secret_key ) && ! empty( $recaptcha_site_key );
		if ( ! $recaptcha_require_logged_in && is_user_logged_in() ) {
			$should_verify_recaptcha = false;
		}

		if ( $should_verify_recaptcha ) {
			$recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( $_POST['g-recaptcha-response'] ) : '';

			if ( empty( $recaptcha_response ) ) {
				$errors[] = __( 'Please complete the reCAPTCHA verification.', 'fanfiction-manager' );
			} else {
				// Verify with Google
				$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
				$user_ip = self::get_user_ip();

				$response = wp_remote_post( $verify_url, array(
					'body' => array(
						'secret'   => $recaptcha_secret_key,
						'response' => $recaptcha_response,
						'remoteip' => $user_ip,
					),
				) );

				if ( is_wp_error( $response ) ) {
					$errors[] = __( 'reCAPTCHA verification failed. Please try again.', 'fanfiction-manager' );
				} else {
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( empty( $response_body['success'] ) ) {
						wp_redirect( add_query_arg( 'report', 'recaptcha_failed', wp_get_referer() ) );
						exit;
					}
				}
			}
		}

		// If errors, store in transient and redirect back
		if ( ! empty( $errors ) ) {
			$user_id = is_user_logged_in() ? get_current_user_id() : 0;
			if ( $user_id ) {
				set_transient( 'fanfic_report_errors_' . $user_id, $errors, MINUTE_IN_SECONDS * 5 );
			}
			wp_redirect( wp_get_referer() );
			exit;
		}

		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';

		// Get reporter info
		$reporter_id = is_user_logged_in() ? get_current_user_id() : 0;
		$reporter_ip = self::get_user_ip();

		// Check for duplicate reports within 24 hours
		$time_24h_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

		if ( $reporter_id ) {
			// Check for logged-in user
			$duplicate = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$reports_table}
				WHERE content_id = %d
				AND content_type = %s
				AND reporter_id = %d
				AND created_at > %s",
				$content_id,
				$content_type,
				$reporter_id,
				$time_24h_ago
			) );
		} else {
			// Check for anonymous user by IP
			$duplicate = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$reports_table}
				WHERE content_id = %d
				AND content_type = %s
				AND reporter_ip = %s
				AND created_at > %s",
				$content_id,
				$content_type,
				$reporter_ip,
				$time_24h_ago
			) );
		}

		if ( $duplicate ) {
			wp_redirect( add_query_arg( 'report', 'duplicate', wp_get_referer() ) );
			exit;
		}

		// Combine reason and details for storage
		$full_reason = sprintf(
			"[%s]\n\n%s",
			self::get_reason_label( $reason ),
			$details
		);

		// Insert report
		$inserted = $wpdb->insert(
			$reports_table,
			array(
				'content_id'   => $content_id,
				'content_type' => $content_type,
				'reporter_id'  => $reporter_id,
				'reporter_ip'  => $reporter_ip,
				'reason'       => $full_reason,
				'details'      => $details,
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_redirect( add_query_arg( 'report', 'error', wp_get_referer() ) );
			exit;
		}

		// Send email notification to moderators and administrators
		self::send_report_notification( $content_id, $content_type, $reason, $details, $reporter_id );

		// Success - redirect with success message
		wp_redirect( add_query_arg( 'report', 'success', wp_get_referer() ) );
		exit;
	}

	/**
	 * Get human-readable label for report reason
	 *
	 * @since 1.0.0
	 * @param string $reason Reason code.
	 * @return string Human-readable reason.
	 */
	private static function get_reason_label( $reason ) {
		$labels = array(
			'spam'          => __( 'Spam', 'fanfiction-manager' ),
			'inappropriate' => __( 'Inappropriate Content', 'fanfiction-manager' ),
			'copyright'     => __( 'Copyright Violation', 'fanfiction-manager' ),
			'harassment'    => __( 'Harassment or Bullying', 'fanfiction-manager' ),
			'other'         => __( 'Other', 'fanfiction-manager' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * Send email notification to moderators about new report
	 *
	 * @since 1.0.0
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content type.
	 * @param string $reason       Report reason.
	 * @param string $details      Report details.
	 * @param int    $reporter_id  Reporter user ID.
	 * @return void
	 */
	private static function send_report_notification( $content_id, $content_type, $reason, $details, $reporter_id ) {
		// Get content title and link
		$content_title = '';
		$content_link = '';

		if ( 'comment' === $content_type ) {
			$comment = get_comment( $content_id );
			if ( $comment ) {
				$content_title = sprintf(
					/* translators: 1: comment author, 2: comment date */
					__( 'Comment by %1$s on %2$s', 'fanfiction-manager' ),
					$comment->comment_author,
					date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) )
				);
				$content_link = get_comment_link( $comment );
			}
		} else {
			$content = get_post( $content_id );
			if ( $content ) {
				$content_title = get_the_title( $content );
				$content_link = get_permalink( $content );
			}
		}

		// Get reporter info
		$reporter_name = __( 'Anonymous', 'fanfiction-manager' );
		if ( $reporter_id ) {
			$reporter = get_userdata( $reporter_id );
			if ( $reporter ) {
				$reporter_name = $reporter->display_name;
			}
		}

		// Get all users who can moderate
		$moderators = get_users( array(
			'role__in' => array( 'administrator', 'fanfiction_moderator' ),
			'fields'   => array( 'user_email', 'display_name' ),
		) );

		if ( empty( $moderators ) ) {
			return;
		}

		// Build email content
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] New Content Report', 'fanfiction-manager' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: reporter name, 2: content type, 3: content title */
			__( 'A new content report has been submitted by %1$s for a %2$s: %3$s', 'fanfiction-manager' ),
			$reporter_name,
			$content_type,
			$content_title
		) . "\n\n";

		$message .= __( 'Report Details:', 'fanfiction-manager' ) . "\n";
		$message .= '---' . "\n";
		$message .= __( 'Reason:', 'fanfiction-manager' ) . ' ' . self::get_reason_label( $reason ) . "\n\n";
		$message .= __( 'Details:', 'fanfiction-manager' ) . "\n" . $details . "\n\n";
		$message .= '---' . "\n\n";

		if ( ! empty( $content_link ) ) {
			$message .= __( 'Content Link:', 'fanfiction-manager' ) . "\n" . $content_link . "\n\n";
		}

		$message .= __( 'Moderation Queue:', 'fanfiction-manager' ) . "\n";
		$message .= admin_url( 'admin.php?page=fanfiction-moderation' ) . "\n";

		// Send to each moderator
		foreach ( $moderators as $moderator ) {
			wp_mail( $moderator->user_email, $subject, $message );
		}
	}

	/**
	 * Get user IP address (unhashed for reCAPTCHA verification)
	 *
	 * @since 1.0.0
	 * @return string IP address.
	 */
	private static function get_user_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
