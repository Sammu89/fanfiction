<?php
/**
 * Taxonomies Admin Interface
 *
 * Provides the admin UI for managing fanfiction taxonomies including built-in
 * (Genre, Status) and custom admin-created taxonomies.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Taxonomies_Admin
 *
 * Manages the admin interface for taxonomy management including custom taxonomies.
 *
 * @since 1.0.0
 */
class Fanfic_Taxonomies_Admin {

	/**
	 * Maximum number of custom taxonomies allowed
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_CUSTOM_TAXONOMIES = 10;

	/**
	 * Option name for storing custom taxonomies
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CUSTOM_TAXONOMIES_OPTION = 'fanfic_custom_taxonomies';

	/**
	 * Initialize the taxonomies admin class
	 *
	 * Sets up WordPress hooks for taxonomy management.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register custom taxonomies stored in options
		add_action( 'init', array( __CLASS__, 'register_custom_taxonomies' ), 11 );

		// Handle form submissions
		add_action( 'admin_post_fanfic_add_custom_taxonomy', array( __CLASS__, 'add_custom_taxonomy' ) );
		add_action( 'admin_post_fanfic_delete_custom_taxonomy', array( __CLASS__, 'delete_custom_taxonomy' ) );

		// Hook into fanfic_register_custom_taxonomies action used by Fanfic_Taxonomies class
		add_action( 'fanfic_register_custom_taxonomies', array( __CLASS__, 'register_custom_taxonomies' ) );
	}

	/**
	 * Register custom taxonomies from options
	 *
	 * Loads custom taxonomies from wp_options and registers them with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_custom_taxonomies() {
		$custom_taxonomies = self::get_custom_taxonomies();

		if ( empty( $custom_taxonomies ) ) {
			return;
		}

		foreach ( $custom_taxonomies as $taxonomy ) {
			if ( ! isset( $taxonomy['slug'] ) || ! isset( $taxonomy['name'] ) ) {
				continue;
			}

			self::register_custom_taxonomy( $taxonomy['name'], $taxonomy['slug'] );
		}
	}

	/**
	 * Get all custom taxonomies from options
	 *
	 * Returns array of custom taxonomies stored in WordPress options.
	 *
	 * @since 1.0.0
	 * @return array Array of custom taxonomies with 'name' and 'slug' keys.
	 */
	public static function get_custom_taxonomies() {
		$taxonomies = get_option( self::CUSTOM_TAXONOMIES_OPTION, array() );

		// Ensure it's an array
		if ( ! is_array( $taxonomies ) ) {
			return array();
		}

		return $taxonomies;
	}

	/**
	 * Register a custom taxonomy in WordPress
	 *
	 * Creates a hierarchical taxonomy for the fanfiction_story post type.
	 *
	 * @since 1.0.0
	 * @param string $name The taxonomy display name.
	 * @param string $slug The taxonomy slug.
	 * @return void
	 */
	private static function register_custom_taxonomy( $name, $slug ) {
		// Ensure taxonomy is not already registered
		if ( taxonomy_exists( $slug ) ) {
			return;
		}

		$labels = array(
			'name'                       => $name,
			'singular_name'              => $name,
			'menu_name'                  => $name,
			'all_items'                  => sprintf( __( 'All %s', 'fanfiction-manager' ), $name ),
			'parent_item'                => sprintf( __( 'Parent %s', 'fanfiction-manager' ), $name ),
			'parent_item_colon'          => sprintf( __( 'Parent %s:', 'fanfiction-manager' ), $name ),
			'new_item_name'              => sprintf( __( 'New %s Name', 'fanfiction-manager' ), $name ),
			'add_new_item'               => sprintf( __( 'Add New %s', 'fanfiction-manager' ), $name ),
			'edit_item'                  => sprintf( __( 'Edit %s', 'fanfiction-manager' ), $name ),
			'update_item'                => sprintf( __( 'Update %s', 'fanfiction-manager' ), $name ),
			'view_item'                  => sprintf( __( 'View %s', 'fanfiction-manager' ), $name ),
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'fanfiction-manager' ), strtolower( $name ) ),
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'fanfiction-manager' ), strtolower( $name ) ),
			'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'fanfiction-manager' ), strtolower( $name ) ),
			'popular_items'              => sprintf( __( 'Popular %s', 'fanfiction-manager' ), $name ),
			'search_items'               => sprintf( __( 'Search %s', 'fanfiction-manager' ), $name ),
			'not_found'                  => sprintf( __( 'No %s found', 'fanfiction-manager' ), strtolower( $name ) ),
			'no_terms'                   => sprintf( __( 'No %s', 'fanfiction-manager' ), strtolower( $name ) ),
			'items_list'                 => sprintf( __( '%s list', 'fanfiction-manager' ), $name ),
			'items_list_navigation'      => sprintf( __( '%s list navigation', 'fanfiction-manager' ), $name ),
		);

		$args = array(
			'labels'                     => $labels,
			'description'                => sprintf( __( 'Custom taxonomy: %s', 'fanfiction-manager' ), $name ),
			'hierarchical'               => true,
			'public'                     => true,
			'publicly_queryable'         => true,
			'show_ui'                    => true,
			'show_in_menu'               => true,
			'show_in_nav_menus'          => true,
			'show_in_rest'               => true,
			'show_tagcloud'              => true,
			'show_in_quick_edit'         => true,
			'show_admin_column'          => true,
			'query_var'                  => true,
			'rewrite'                    => array(
				'slug'                   => $slug,
				'with_front'             => false,
				'hierarchical'           => true,
			),
			'meta_box_cb'                => 'post_categories_meta_box',
		);

		register_taxonomy( $slug, array( 'fanfiction_story' ), $args );
	}

	/**
	 * Validate taxonomy slug
	 *
	 * Checks if a slug is unique and doesn't conflict with built-in taxonomies.
	 *
	 * @since 1.0.0
	 * @param string $slug The slug to validate.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	private static function validate_taxonomy_slug( $slug ) {
		// Check if slug is empty
		if ( empty( $slug ) ) {
			return new WP_Error( 'empty_slug', __( 'Taxonomy slug cannot be empty.', 'fanfiction-manager' ) );
		}

		// Check if slug matches built-in taxonomies
		$reserved_slugs = array( 'fanfiction_genre', 'fanfiction_status', 'genre', 'status' );
		if ( in_array( $slug, $reserved_slugs, true ) ) {
			return new WP_Error( 'reserved_slug', __( 'Taxonomy slug already exists.', 'fanfiction-manager' ) );
		}

		// Check if slug matches an existing custom taxonomy
		$custom_taxonomies = self::get_custom_taxonomies();
		foreach ( $custom_taxonomies as $taxonomy ) {
			if ( isset( $taxonomy['slug'] ) && $taxonomy['slug'] === $slug ) {
				return new WP_Error( 'duplicate_slug', __( 'Taxonomy slug already exists.', 'fanfiction-manager' ) );
			}
		}

		// Check if taxonomy is already registered in WordPress
		if ( taxonomy_exists( $slug ) ) {
			return new WP_Error( 'taxonomy_exists', __( 'Taxonomy slug already exists.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Add a new custom taxonomy
	 *
	 * Handles form submission to create a new custom taxonomy.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_custom_taxonomy() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_add_custom_taxonomy_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_add_custom_taxonomy_nonce'] ) ), 'fanfic_add_custom_taxonomy_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Get form data
		$name = isset( $_POST['taxonomy_name'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_name'] ) ) : '';
		$slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';

		// Validate name
		if ( empty( $name ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'empty_name',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Limit name to 50 characters
		if ( strlen( $name ) > 50 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'name_too_long',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Auto-generate slug from name if not provided
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		}

		// Limit slug to 50 characters
		if ( strlen( $slug ) > 50 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'slug_too_long',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Get existing custom taxonomies
		$custom_taxonomies = self::get_custom_taxonomies();

		// Check if limit reached
		if ( count( $custom_taxonomies ) >= self::MAX_CUSTOM_TAXONOMIES ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'limit_reached',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Validate slug
		$validation = self::validate_taxonomy_slug( $slug );
		if ( is_wp_error( $validation ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => $validation->get_error_code(),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Add new taxonomy to array
		$custom_taxonomies[] = array(
			'name' => $name,
			'slug' => $slug,
		);

		// Save to options
		update_option( self::CUSTOM_TAXONOMIES_OPTION, $custom_taxonomies );

		// Register the taxonomy immediately
		self::register_custom_taxonomy( $name, $slug );

		// Flush rewrite rules to update URLs
		flush_rewrite_rules();

		// Invalidate cache
		if ( class_exists( 'Fanfic_Cache' ) ) {
			Fanfic_Cache::invalidate_taxonomies();
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-taxonomies',
					'success' => 'taxonomy_added',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a custom taxonomy
	 *
	 * Handles deletion of a custom taxonomy and removes its terms from stories.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function delete_custom_taxonomy() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get taxonomy slug
		$slug = isset( $_GET['taxonomy_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy_slug'] ) ) : '';

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_delete_custom_taxonomy_' . $slug ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Validate slug
		if ( empty( $slug ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'invalid_slug',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Get custom taxonomies
		$custom_taxonomies = self::get_custom_taxonomies();
		$found = false;

		// Find and remove the taxonomy
		foreach ( $custom_taxonomies as $index => $taxonomy ) {
			if ( isset( $taxonomy['slug'] ) && $taxonomy['slug'] === $slug ) {
				unset( $custom_taxonomies[ $index ] );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'fanfiction-taxonomies',
						'error' => 'taxonomy_not_found',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Reindex array
		$custom_taxonomies = array_values( $custom_taxonomies );

		// Save updated list
		update_option( self::CUSTOM_TAXONOMIES_OPTION, $custom_taxonomies );

		// Get all stories and remove terms from this taxonomy
		if ( taxonomy_exists( $slug ) ) {
			$stories = get_posts(
				array(
					'post_type'      => 'fanfiction_story',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);

			foreach ( $stories as $story_id ) {
				wp_set_object_terms( $story_id, array(), $slug );
			}

			// Unregister taxonomy (will take effect on next page load)
			unregister_taxonomy( $slug );
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Bulk cache invalidation
		if ( class_exists( 'Fanfic_Cache' ) ) {
			Fanfic_Cache::invalidate_taxonomies();
			Fanfic_Cache::invalidate_lists();
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-taxonomies',
					'success' => 'taxonomy_deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render taxonomies management page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Display notices
		self::display_notices();

		// Get built-in taxonomies
		$genres = get_terms(
			array(
				'taxonomy'   => 'fanfiction_genre',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$statuses = get_terms(
			array(
				'taxonomy'   => 'fanfiction_status',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		// Get custom taxonomies
		$custom_taxonomies = self::get_custom_taxonomies();
		$custom_count = count( $custom_taxonomies );
		$can_add_more = $custom_count < self::MAX_CUSTOM_TAXONOMIES;

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Manage all content taxonomies for organizing fanfiction stories. Built-in taxonomies (Genre, Status) and custom taxonomies are listed below.', 'fanfiction-manager' ); ?></p>

			<!-- Content Taxonomies Table -->
			<div class="fanfic-taxonomies-table">
				<h2><?php esc_html_e( 'Content Taxonomies', 'fanfiction-manager' ); ?></h2>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></th>
							<th><?php esc_html_e( 'Term Count', 'fanfiction-manager' ); ?></th>
							<th><?php esc_html_e( 'Generated Shortcodes', 'fanfiction-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Built-in: Genre -->
						<tr>
							<td><strong><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></strong></td>
							<td><code>fanfiction_genre</code></td>
							<td>
								<?php
								$genre_count = is_array( $genres ) ? count( $genres ) : 0;
								echo esc_html( $genre_count );
								?>
							</td>
							<td>
								<code>[fanfic-genre]</code><br>
								<code>[fanfic-genre-title]</code>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=fanfiction_genre&post_type=fanfiction_story' ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Manage Terms', 'fanfiction-manager' ); ?>
								</a>
							</td>
						</tr>

						<!-- Built-in: Status -->
						<tr>
							<td><strong><?php esc_html_e( 'Story Status', 'fanfiction-manager' ); ?></strong></td>
							<td><code>fanfiction_status</code></td>
							<td>
								<?php
								$status_count = is_array( $statuses ) ? count( $statuses ) : 0;
								echo esc_html( $status_count );
								?>
							</td>
							<td>
								<code>[fanfic-status]</code><br>
								<code>[fanfic-status-title]</code>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=fanfiction_status&post_type=fanfiction_story' ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Manage Terms', 'fanfiction-manager' ); ?>
								</a>
							</td>
						</tr>

						<!-- Custom Taxonomies -->
						<?php if ( ! empty( $custom_taxonomies ) ) : ?>
							<?php foreach ( $custom_taxonomies as $taxonomy ) : ?>
								<?php
								$slug = isset( $taxonomy['slug'] ) ? $taxonomy['slug'] : '';
								$name = isset( $taxonomy['name'] ) ? $taxonomy['name'] : '';

								// Get term count
								$terms = get_terms(
									array(
										'taxonomy'   => $slug,
										'hide_empty' => false,
									)
								);
								$term_count = is_array( $terms ) ? count( $terms ) : 0;

								// Generate shortcode names (with fanfic- prefix)
								$shortcode_base = 'fanfic-custom-taxo-' . str_replace( '_', '-', $slug );
								$shortcode_list = $shortcode_base;
								$shortcode_title = $shortcode_base . '-title';

								// Delete link
								$delete_link = wp_nonce_url(
									admin_url( 'admin-post.php?action=fanfic_delete_custom_taxonomy&taxonomy_slug=' . $slug ),
									'fanfic_delete_custom_taxonomy_' . $slug
								);
								?>
								<tr>
									<td><strong><?php echo esc_html( $name ); ?></strong></td>
									<td><code><?php echo esc_html( $slug ); ?></code></td>
									<td><?php echo esc_html( $term_count ); ?></td>
									<td>
										<code>[<?php echo esc_html( $shortcode_list ); ?>]</code><br>
										<code>[<?php echo esc_html( $shortcode_title ); ?>]</code>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . $slug . '&post_type=fanfiction_story' ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Manage Terms', 'fanfiction-manager' ); ?>
										</a>
										<a href="<?php echo esc_url( $delete_link ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this custom taxonomy? All terms will be removed from stories.', 'fanfiction-manager' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if ( empty( $custom_taxonomies ) ) : ?>
							<tr>
								<td colspan="5">
									<?php esc_html_e( 'No custom taxonomies created yet. Use the form below to add your first custom taxonomy.', 'fanfiction-manager' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Add Custom Taxonomy Form -->
			<div class="fanfic-add-custom-taxonomy">
				<h2><?php esc_html_e( 'Add Custom Taxonomy', 'fanfiction-manager' ); ?></h2>

				<?php if ( ! $can_add_more ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<strong><?php esc_html_e( 'Limit Reached:', 'fanfiction-manager' ); ?></strong>
							<?php
							printf(
								/* translators: %d: Maximum number of custom taxonomies */
								esc_html__( 'Maximum %d custom taxonomies allowed. Delete an existing custom taxonomy to add a new one.', 'fanfiction-manager' ),
								absint( self::MAX_CUSTOM_TAXONOMIES )
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: Current count, 2: Maximum count */
							esc_html__( 'Create custom taxonomies to categorize stories beyond genres and status. You have %1$d of %2$d custom taxonomies available.', 'fanfiction-manager' ),
							absint( $custom_count ),
							absint( self::MAX_CUSTOM_TAXONOMIES )
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="fanfic_add_custom_taxonomy">
						<?php wp_nonce_field( 'fanfic_add_custom_taxonomy_nonce', 'fanfic_add_custom_taxonomy_nonce' ); ?>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="taxonomy_name"><?php esc_html_e( 'Taxonomy Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
									</th>
									<td>
										<input type="text" id="taxonomy_name" name="taxonomy_name" class="regular-text" maxlength="50" required>
										<p class="description"><?php esc_html_e( 'The display name for this taxonomy (max 50 characters). Example: "Fandom", "Character", "Relationship".', 'fanfiction-manager' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="taxonomy_slug"><?php esc_html_e( 'Taxonomy Slug', 'fanfiction-manager' ); ?></label>
									</th>
									<td>
										<input type="text" id="taxonomy_slug" name="taxonomy_slug" class="regular-text" maxlength="50">
										<p class="description"><?php esc_html_e( 'URL-friendly slug (max 50 characters). Leave blank to auto-generate from name. Must be unique.', 'fanfiction-manager' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="submit">
							<?php submit_button( __( 'Add Custom Taxonomy', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
						</p>
					</form>
				<?php endif; ?>
			</div>

			<!-- Information Box -->
			<div class="fanfic-info-box notice notice-info inline">
				<h3><?php esc_html_e( 'How Custom Taxonomies Work', 'fanfiction-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Custom taxonomies are hierarchical (like categories), allowing parent/child relationships.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'When created, two shortcodes are automatically generated for displaying the taxonomy.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Example: A taxonomy with slug "fandom" generates [fanfic-custom-taxo-fandom] and [fanfic-custom-taxo-fandom-title].', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Click "Manage Terms" to add, edit, or delete terms within each taxonomy using WordPress native interface.', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Deleting a custom taxonomy removes all its terms from stories and cannot be undone.', 'fanfiction-manager' ); ?></li>
				</ul>
			</div>
		</div>

		<?php
	}

	/**
	 * Display admin notices
	 *
	 * Shows success or error messages based on URL parameters.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function display_notices() {
		// Success notices
		if ( isset( $_GET['success'] ) ) {
			$success_code = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			$messages = array(
				'taxonomy_added'   => __( 'Custom taxonomy added successfully. Shortcodes have been generated automatically.', 'fanfiction-manager' ),
				'taxonomy_deleted' => __( 'Custom taxonomy deleted successfully. All terms have been removed from stories.', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $success_code ] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $success_code ] ); ?></p>
				</div>
				<?php
			}
		}

		// Error notices
		if ( isset( $_GET['error'] ) ) {
			$error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$messages = array(
				'empty_name'          => __( 'Error: Taxonomy name is required.', 'fanfiction-manager' ),
				'name_too_long'       => __( 'Error: Taxonomy name must be 50 characters or less.', 'fanfiction-manager' ),
				'slug_too_long'       => __( 'Error: Taxonomy slug must be 50 characters or less.', 'fanfiction-manager' ),
				'empty_slug'          => __( 'Error: Taxonomy slug cannot be empty.', 'fanfiction-manager' ),
				'reserved_slug'       => __( 'Error: Taxonomy slug already exists.', 'fanfiction-manager' ),
				'duplicate_slug'      => __( 'Error: Taxonomy slug already exists.', 'fanfiction-manager' ),
				'taxonomy_exists'     => __( 'Error: Taxonomy slug already exists.', 'fanfiction-manager' ),
				'limit_reached'       => sprintf(
					/* translators: %d: Maximum number of custom taxonomies */
					__( 'Error: Maximum %d custom taxonomies allowed.', 'fanfiction-manager' ),
					self::MAX_CUSTOM_TAXONOMIES
				),
				'invalid_slug'        => __( 'Error: Invalid taxonomy slug provided.', 'fanfiction-manager' ),
				'taxonomy_not_found'  => __( 'Error: Custom taxonomy not found.', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $error_code ] ) ) {
				?>
				<div class="notice error-message is-dismissible">
					<p><?php echo esc_html( $messages[ $error_code ] ); ?></p>
				</div>
				<?php
			}
		}
	}
}
