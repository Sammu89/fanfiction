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

		// Register AJAX handlers
		add_action( 'wp_ajax_fanfic_submit_chapter_rating', array( __CLASS__, 'ajax_submit_chapter_rating' ) );
		add_action( 'wp_ajax_nopriv_fanfic_submit_chapter_rating', array( __CLASS__, 'ajax_submit_chapter_rating' ) );

		// Register form submission handlers
		add_action( 'init', array( __CLASS__, 'handle_login_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_register_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_password_reset_submission' ) );
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
			return '<div class="fanfic-message fanfic-info">' .
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
				$message = '<div class="fanfic-message fanfic-error" role="alert">' .
					esc_html__( 'Login failed. Please check your username and password.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'empty' === $_GET['login'] ) {
				$message = '<div class="fanfic-message fanfic-error" role="alert">' .
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
			return '<div class="fanfic-message fanfic-info">' .
				esc_html__( 'You are already registered and logged in.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check if registration is enabled
		if ( ! get_option( 'users_can_register' ) ) {
			return '<div class="fanfic-message fanfic-error">' .
				esc_html__( 'User registration is currently disabled.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check for error/success messages
		$message = '';
		if ( isset( $_GET['register'] ) ) {
			if ( 'success' === $_GET['register'] ) {
				$message = '<div class="fanfic-message fanfic-success" role="alert">' .
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
				<div class="fanfic-message fanfic-error" role="alert">
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
			return '<div class="fanfic-message fanfic-info">' .
				esc_html__( 'You are already logged in. If you need to change your password, please use your profile settings.', 'fanfiction-manager' ) .
				'</div>';
		}

		// Check for error/success messages
		$message = '';
		if ( isset( $_GET['password-reset'] ) ) {
			if ( 'sent' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-message fanfic-success" role="alert">' .
					esc_html__( 'Password reset instructions have been sent to your email address.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'invalid' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-message fanfic-error" role="alert">' .
					esc_html__( 'Invalid email address.', 'fanfiction-manager' ) .
					'</div>';
			} elseif ( 'empty' === $_GET['password-reset'] ) {
				$message = '<div class="fanfic-message fanfic-error" role="alert">' .
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
	 * @param array $atts Shortcode attributes.
	 * @return string Story rating display HTML.
	 */
	public static function story_rating_form( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		global $wpdb;
		$ratings_table = $wpdb->prefix . 'fanfic_ratings';

		// Get all chapters for this story
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $chapters ) ) {
			return '';
		}

		// Get average rating across all chapters
		$chapter_ids = array_map( 'absint', $chapters );
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );
		$avg_rating = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$ratings_table} WHERE chapter_id IN ({$placeholders})",
				$chapter_ids
			)
		);

		// Get total number of ratings
		$total_ratings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ratings_table} WHERE chapter_id IN ({$placeholders})",
				$chapter_ids
			)
		);

		$avg_rating = $avg_rating ? round( floatval( $avg_rating ), 1 ) : 0;
		$total_ratings = absint( $total_ratings );

		ob_start();
		?>
		<div class="fanfic-story-rating" aria-label="<?php esc_attr_e( 'Story rating', 'fanfiction-manager' ); ?>">
			<div class="fanfic-rating-stars fanfic-rating-readonly" data-rating="<?php echo esc_attr( $avg_rating ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<?php
					$star_class = 'fanfic-star';
					if ( $i <= floor( $avg_rating ) ) {
						$star_class .= ' fanfic-star-full';
					} elseif ( $i - 0.5 <= $avg_rating ) {
						$star_class .= ' fanfic-star-half';
					} else {
						$star_class .= ' fanfic-star-empty';
					}
					?>
					<span class="<?php echo esc_attr( $star_class ); ?>" aria-hidden="true">&#9734;</span>
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
	 * 1-5 star rating with half-stars support (stored as float)
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter rating form HTML.
	 */
	public static function chapter_rating_form( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		global $wpdb;
		$ratings_table = $wpdb->prefix . 'fanfic_ratings';

		// Get current user's rating if exists
		$user_id = get_current_user_id();
		$user_ip = self::get_user_ip_hash();
		$user_rating = 0;

		if ( $user_id ) {
			// Logged-in user
			$user_rating = $wpdb->get_var( $wpdb->prepare(
				"SELECT rating FROM {$ratings_table} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$user_id
			) );
		} else {
			// Anonymous user (use IP hash)
			$user_rating = $wpdb->get_var( $wpdb->prepare(
				"SELECT rating FROM {$ratings_table} WHERE chapter_id = %d AND user_ip = %s",
				$chapter_id,
				$user_ip
			) );
		}

		$user_rating = $user_rating ? floatval( $user_rating ) : 0;

		// Get average rating for this chapter
		$avg_rating = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(rating) FROM {$ratings_table} WHERE chapter_id = %d",
			$chapter_id
		) );

		// Get total number of ratings
		$total_ratings = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ratings_table} WHERE chapter_id = %d",
			$chapter_id
		) );

		$avg_rating = $avg_rating ? round( floatval( $avg_rating ), 1 ) : 0;
		$total_ratings = absint( $total_ratings );

		// Enqueue rating script
		wp_enqueue_script( 'fanfic-rating' );

		ob_start();
		?>
		<div class="fanfic-chapter-rating" data-chapter-id="<?php echo esc_attr( $chapter_id ); ?>">
			<div class="fanfic-rating-form">
				<label for="fanfic-rating-input-<?php echo esc_attr( $chapter_id ); ?>">
					<?php esc_html_e( 'Rate this chapter:', 'fanfiction-manager' ); ?>
				</label>
				<div
					class="fanfic-rating-stars fanfic-rating-interactive"
					data-rating="<?php echo esc_attr( $user_rating ); ?>"
					role="slider"
					aria-label="<?php esc_attr_e( 'Rate from 1 to 5 stars', 'fanfiction-manager' ); ?>"
					aria-valuemin="0"
					aria-valuemax="5"
					aria-valuenow="<?php echo esc_attr( $user_rating ); ?>"
					tabindex="0"
				>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<?php
						$star_class = 'fanfic-star';
						$star_value = $i;
						if ( $user_rating >= $i ) {
							$star_class .= ' fanfic-star-full';
						} elseif ( $user_rating >= $i - 0.5 ) {
							$star_class .= ' fanfic-star-half';
						} else {
							$star_class .= ' fanfic-star-empty';
						}
						?>
						<span
							class="<?php echo esc_attr( $star_class ); ?>"
							data-value="<?php echo esc_attr( $star_value ); ?>"
							aria-hidden="true"
						>&#9734;</span>
					<?php endfor; ?>
				</div>
				<input
					type="hidden"
					id="fanfic-rating-input-<?php echo esc_attr( $chapter_id ); ?>"
					name="fanfic_rating"
					value="<?php echo esc_attr( $user_rating ); ?>"
				/>
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

			<div class="fanfic-rating-message" role="alert" aria-live="polite"></div>
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

		// Set default role to Fanfic_Reader
		$user = new WP_User( $user_id );
		$user->set_role( 'fanfic_reader' );

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
	 * AJAX handler for chapter rating submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_submit_chapter_rating() {
		// Verify nonce
		check_ajax_referer( 'fanfic_rating_nonce', 'nonce' );

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$rating = isset( $_POST['rating'] ) ? floatval( $_POST['rating'] ) : 0;

		// Validate
		if ( ! $chapter_id || $rating < 0.5 || $rating > 5 ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid rating data.', 'fanfiction-manager' ),
			) );
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid chapter.', 'fanfiction-manager' ),
			) );
		}

		// Round to nearest 0.5
		$rating = round( $rating * 2 ) / 2;

		global $wpdb;
		$ratings_table = $wpdb->prefix . 'fanfic_ratings';

		$user_id = get_current_user_id();
		$user_ip = self::get_user_ip_hash();

		// Prepare data
		$data = array(
			'chapter_id' => $chapter_id,
			'rating'     => $rating,
			'created_at' => current_time( 'mysql' ),
		);

		// Use user_id for logged-in users, user_ip for anonymous
		if ( $user_id ) {
			$data['user_id'] = $user_id;
			$data['user_ip'] = '';
		} else {
			$data['user_id'] = 0;
			$data['user_ip'] = $user_ip;
		}

		// Insert or update rating
		$existing = false;
		if ( $user_id ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$ratings_table} WHERE chapter_id = %d AND user_id = %d AND user_id > 0",
				$chapter_id,
				$user_id
			) );
		} else {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$ratings_table} WHERE chapter_id = %d AND user_ip = %s",
				$chapter_id,
				$user_ip
			) );
		}

		if ( $existing ) {
			// Update existing rating
			$wpdb->update(
				$ratings_table,
				array( 'rating' => $rating ),
				array( 'id' => $existing ),
				array( '%f' ),
				array( '%d' )
			);
		} else {
			// Insert new rating
			$wpdb->insert(
				$ratings_table,
				$data,
				array( '%d', '%f', '%d', '%s', '%s' )
			);
		}

		// Get updated average and count
		$avg_rating = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(rating) FROM {$ratings_table} WHERE chapter_id = %d",
			$chapter_id
		) );

		$total_ratings = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ratings_table} WHERE chapter_id = %d",
			$chapter_id
		) );

		$avg_rating = $avg_rating ? round( floatval( $avg_rating ), 1 ) : 0;

		// Clear any rating transients
		delete_transient( 'fanfic_chapter_rating_' . $chapter_id );

		wp_send_json_success( array(
			'message'       => __( 'Thank you for rating!', 'fanfiction-manager' ),
			'user_rating'   => $rating,
			'avg_rating'    => $avg_rating,
			'total_ratings' => absint( $total_ratings ),
		) );
	}

	/**
	 * Get hashed user IP
	 *
	 * @since 1.0.0
	 * @return string Hashed IP address.
	 */
	private static function get_user_ip_hash() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return hash( 'sha256', $ip . NONCE_SALT );
	}
}
