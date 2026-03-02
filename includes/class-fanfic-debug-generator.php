<?php
/**
 * Debug Data Generator
 *
 * Creates random test users, stories, and chapters for development/debugging.
 * Only available to administrators on Settings > General tab.
 *
 * @since 2.3.0
 * @package Fanfiction_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanfic_Debug_Generator {

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 */
	public static function init() {
		add_action( 'admin_post_fanfic_debug_generate', array( __CLASS__, 'handle_generate' ) );
	}

	/**
	 * Handle the generate form submission.
	 *
	 * @since 2.3.0
	 */
	public static function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_POST['fanfic_debug_generate_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_debug_generate_nonce'] ) ), 'fanfic_debug_generate_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		$result = self::generate_user_with_story();

		$query_args = array(
			'page' => 'fanfiction-settings',
			'tab'  => 'general',
		);

		if ( is_wp_error( $result ) ) {
			$query_args['debug_gen']       = 'error';
			$query_args['debug_gen_error'] = urlencode( $result->get_error_message() );
		} else {
			$query_args['debug_gen']      = 'success';
			$query_args['debug_gen_user'] = $result['username'];
		}

		wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Generate a random user with a story and 3 chapters.
	 *
	 * @since 2.3.0
	 * @return array|WP_Error Array with user/story info or error.
	 */
	public static function generate_user_with_story() {
		$username = 'testing';
		$email    = 'testing@test.local';

		// If user already exists, reuse it
		$existing = get_user_by( 'login', $username );
		if ( $existing ) {
			$user_id = $existing->ID;

			if ( ! in_array( 'fanfiction_author', $existing->roles, true ) ) {
				$existing->add_role( 'fanfiction_author' );
			}

			$story_id = self::create_random_story( $user_id );
			if ( is_wp_error( $story_id ) ) {
				return $story_id;
			}

			for ( $i = 1; $i <= 3; $i++ ) {
				$chapter_id = self::create_random_chapter( $user_id, $story_id, $i );
				if ( is_wp_error( $chapter_id ) ) {
					return $chapter_id;
				}
			}

			return array(
				'user_id'  => $user_id,
				'username' => $username,
				'story_id' => $story_id,
			);
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_pass'    => '123456',
			'user_email'   => $email,
			'display_name' => 'Testing',
			'role'         => 'subscriber',
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$user->add_role( 'fanfiction_author' );
		}

		$story_id = self::create_random_story( $user_id );
		if ( is_wp_error( $story_id ) ) {
			return $story_id;
		}

		for ( $i = 1; $i <= 3; $i++ ) {
			$chapter_id = self::create_random_chapter( $user_id, $story_id, $i );
			if ( is_wp_error( $chapter_id ) ) {
				return $chapter_id;
			}
		}

		return array(
			'user_id'  => $user_id,
			'username' => $username,
			'story_id' => $story_id,
		);
	}

	/**
	 * Create a random story with taxonomy assignments.
	 *
	 * @since 2.3.0
	 * @param int $user_id Author user ID.
	 * @return int|WP_Error Story post ID or error.
	 */
	private static function create_random_story( $user_id ) {
		$adjectives = array( 'Dark', 'Bright', 'Lost', 'Hidden', 'Eternal', 'Broken', 'Silver', 'Golden', 'Crimson', 'Silent', 'Frozen', 'Burning', 'Shadowed', 'Ancient', 'Forgotten' );
		$nouns      = array( 'Kingdom', 'Heart', 'Dream', 'Blade', 'Throne', 'Star', 'Storm', 'Echo', 'Flame', 'Veil', 'Crown', 'Oath', 'Tide', 'Wings', 'Ashes' );

		$title       = $adjectives[ array_rand( $adjectives ) ] . ' ' . $nouns[ array_rand( $nouns ) ];
		$description = self::generate_gibberish( 3 );

		$story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => 'publish',
			'post_author'  => $user_id,
		), true );

		if ( is_wp_error( $story_id ) ) {
			return $story_id;
		}

		self::assign_random_genre( $story_id );
		self::assign_random_status( $story_id );
		self::assign_random_licence( $story_id );
		self::assign_random_fandoms( $story_id );
		self::assign_random_warnings( $story_id );
		self::assign_random_language( $story_id );

		return $story_id;
	}

	/**
	 * Create a random chapter.
	 *
	 * @since 2.3.0
	 * @param int $user_id    Author user ID.
	 * @param int $story_id   Parent story ID.
	 * @param int $chapter_num Chapter number.
	 * @return int|WP_Error Chapter post ID or error.
	 */
	private static function create_random_chapter( $user_id, $story_id, $chapter_num ) {
		$title   = ucfirst( self::random_word() ) . ' of ' . ucfirst( self::random_word() );
		$content = self::generate_gibberish( wp_rand( 5, 10 ) );

		$chapter_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_chapter',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_parent'  => $story_id,
		), true );

		if ( is_wp_error( $chapter_id ) ) {
			return $chapter_id;
		}

		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_num );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', 'chapter' );
		update_post_meta( $chapter_id, '_fanfic_author_notes_enabled', '0' );
		update_post_meta( $chapter_id, '_fanfic_chapter_comments_enabled', '1' );

		return $chapter_id;
	}

	/**
	 * Assign random genre(s) to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_genre( $story_id ) {
		$terms = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		shuffle( $terms );
		$pick = array_slice( $terms, 0, wp_rand( 1, min( 3, count( $terms ) ) ) );
		wp_set_object_terms( $story_id, $pick, 'fanfiction_genre' );
	}

	/**
	 * Assign random status to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_status( $story_id ) {
		$terms = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$pick = $terms[ array_rand( $terms ) ];
		wp_set_object_terms( $story_id, array( $pick ), 'fanfiction_status' );
	}

	/**
	 * Assign random licence to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_licence( $story_id ) {
		if ( ! class_exists( 'Fanfic_Licence' ) || ! Fanfic_Licence::is_enabled() ) {
			return;
		}

		$slugs = array( 'all-rights-reserved', 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa', 'cc-by-nd', 'cc-by-nc-nd', 'public-domain' );
		$pick  = $slugs[ array_rand( $slugs ) ];
		update_post_meta( $story_id, '_fanfic_licence', $pick );
	}

	/**
	 * Assign random fandom(s) to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_fandoms( $story_id ) {
		if ( ! class_exists( 'Fanfic_Fandoms' ) || ! Fanfic_Fandoms::is_enabled() ) {
			return;
		}

		$all = Fanfic_Fandoms::get_all_active();
		if ( empty( $all ) ) {
			return;
		}

		// 20% chance of original work
		if ( wp_rand( 1, 5 ) === 1 ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, array(), true );
			return;
		}

		shuffle( $all );
		$pick = array_slice( $all, 0, wp_rand( 1, min( 3, count( $all ) ) ) );
		$ids  = array_map( function( $f ) { return absint( $f['id'] ); }, $pick );
		Fanfic_Fandoms::save_story_fandoms( $story_id, $ids, false );
	}

	/**
	 * Assign random warning(s) to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_warnings( $story_id ) {
		if ( ! class_exists( 'Fanfic_Warnings' ) ) {
			return;
		}

		$all = Fanfic_Warnings::get_all( true );
		if ( empty( $all ) ) {
			return;
		}

		// 40% chance of no warnings
		if ( wp_rand( 1, 5 ) <= 2 ) {
			Fanfic_Warnings::save_story_warnings( $story_id, array() );
			return;
		}

		shuffle( $all );
		$pick = array_slice( $all, 0, wp_rand( 1, min( 3, count( $all ) ) ) );
		$ids  = array_map( function( $w ) { return absint( $w['id'] ); }, $pick );
		Fanfic_Warnings::save_story_warnings( $story_id, $ids );
	}

	/**
	 * Assign random language to a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 */
	private static function assign_random_language( $story_id ) {
		if ( ! class_exists( 'Fanfic_Languages' ) || ! Fanfic_Languages::is_enabled() ) {
			return;
		}

		$all = Fanfic_Languages::get_active_languages();
		if ( empty( $all ) ) {
			return;
		}

		$pick = $all[ array_rand( $all ) ];
		Fanfic_Languages::save_story_language( $story_id, absint( $pick['id'] ) );
	}

	/**
	 * Generate gibberish paragraphs.
	 *
	 * @since 2.3.0
	 * @param int $paragraphs Number of paragraphs.
	 * @return string
	 */
	private static function generate_gibberish( $paragraphs = 3 ) {
		$output = array();
		for ( $p = 0; $p < $paragraphs; $p++ ) {
			$sentences = array();
			$count     = wp_rand( 3, 7 );
			for ( $s = 0; $s < $count; $s++ ) {
				$words    = array();
				$word_count = wp_rand( 5, 15 );
				for ( $w = 0; $w < $word_count; $w++ ) {
					$words[] = self::random_word();
				}
				$words[0]    = ucfirst( $words[0] );
				$sentences[] = implode( ' ', $words ) . '.';
			}
			$output[] = '<p>' . implode( ' ', $sentences ) . '</p>';
		}
		return implode( "\n\n", $output );
	}

	/**
	 * Return a random word.
	 *
	 * @since 2.3.0
	 * @return string
	 */
	private static function random_word() {
		$words = array(
			'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
			'morbi', 'vestibulum', 'sapien', 'nulla', 'tempus', 'cursus', 'felis', 'vitae',
			'dragon', 'sword', 'magic', 'quest', 'shadow', 'light', 'realm', 'castle',
			'warrior', 'sorcerer', 'kingdom', 'portal', 'destiny', 'prophecy', 'ancient',
			'crystal', 'enchanted', 'forgotten', 'wandering', 'crimson', 'emerald', 'silver',
			'thunder', 'whisper', 'midnight', 'twilight', 'horizon', 'obsidian', 'celestial',
			'arcane', 'mystic', 'phantom', 'eternal', 'raven', 'eclipse', 'solstice',
		);
		return $words[ array_rand( $words ) ];
	}

	/**
	 * Render the debug section in the General settings tab.
	 *
	 * @since 2.3.0
	 */
	public static function render_debug_section() {
		if ( ! current_user_can( 'manage_options' ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		?>
		<hr>
		<h3 style="color: #d63638;">
			&#9881; <?php esc_html_e( 'Debug Tools', 'fanfiction-manager' ); ?>
		</h3>
		<p class="description" style="color: #d63638;">
			<?php esc_html_e( 'These tools are only visible when WP_DEBUG is enabled. For development use only.', 'fanfiction-manager' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Generate Test Data', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="fanfic_debug_generate">
							<?php wp_nonce_field( 'fanfic_debug_generate_nonce', 'fanfic_debug_generate_nonce' ); ?>
							<button type="submit" class="button button-secondary" style="border-color: #d63638; color: #d63638;" onclick="return confirm('<?php esc_attr_e( 'Create a random test user (password: 123456) with 1 story and 3 chapters?', 'fanfiction-manager' ); ?>')">
								<?php esc_html_e( 'Debug - Generate User and Story', 'fanfiction-manager' ); ?>
							</button>
						</form>
						<p class="description">
							<?php esc_html_e( 'Creates a random user (password: 123456) with fanfiction_author role, 1 published story with random taxonomies, and 3 chapters with gibberish content.', 'fanfiction-manager' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Display admin notices for debug generation results.
	 *
	 * @since 2.3.0
	 */
	public static function display_notices() {
		if ( ! isset( $_GET['debug_gen'] ) ) {
			return;
		}

		if ( 'success' === $_GET['debug_gen'] ) {
			$username = isset( $_GET['debug_gen_user'] ) ? sanitize_text_field( wp_unslash( $_GET['debug_gen_user'] ) ) : '';
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: username */
						esc_html__( 'Debug: Created user "%s" (password: 123456) with 1 story and 3 chapters.', 'fanfiction-manager' ),
						esc_html( $username )
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( 'error' === $_GET['debug_gen'] ) {
			$error = isset( $_GET['debug_gen_error'] ) ? sanitize_text_field( wp_unslash( $_GET['debug_gen_error'] ) ) : __( 'Unknown error', 'fanfiction-manager' );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( __( 'Debug generation failed: ', 'fanfiction-manager' ) . $error ); ?></p>
			</div>
			<?php
		}
	}
}
