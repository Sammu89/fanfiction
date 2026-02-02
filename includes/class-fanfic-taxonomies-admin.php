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
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Handle form submissions
		add_action( 'admin_post_fanfic_save_fandom_settings', array( __CLASS__, 'save_fandom_settings' ) );
		add_action( 'admin_post_fanfic_save_taxonomy_settings', array( __CLASS__, 'save_taxonomy_settings' ) );
		add_action( 'admin_post_fanfic_save_language_settings', array( __CLASS__, 'save_language_settings' ) );
	}

	/**
	 * Render taxonomies management page with tabs
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added tabbed interface: General, Genres, Status, Warnings, Fandoms.
	 * @return void
	 */
	public static function render() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current tab with validation
		$allowed_tabs = array( 'general', 'genres', 'status', 'warnings', 'fandoms', 'languages' );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		// Check for custom taxonomy tabs (custom-{slug})
		$is_custom_tab = false;
		if ( class_exists( 'Fanfic_Custom_Taxonomies_Admin' ) && Fanfic_Custom_Taxonomies_Admin::is_custom_taxonomy_tab( $current_tab ) ) {
			$is_custom_tab = true;
		} elseif ( ! in_array( $current_tab, $allowed_tabs, true ) ) {
			$current_tab = 'general';
		}

		// Display notices
		self::display_notices();

		// Check if fandoms is enabled
		$enable_fandoms = Fanfic_Settings::get_setting( 'enable_fandom_classification', false );

		// Check if languages is enabled
		$enable_languages = Fanfic_Settings::get_setting( 'enable_language_classification', false );

		// Check if warnings/tags are enabled
		$enable_warnings = Fanfic_Settings::get_setting( 'enable_warnings', true );
		$enable_tags     = Fanfic_Settings::get_setting( 'enable_tags', true );

		// Get custom taxonomy tabs
		$custom_taxonomy_tabs = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies_Admin' ) ) {
			$custom_taxonomy_tabs = Fanfic_Custom_Taxonomies_Admin::get_custom_taxonomy_tabs();
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-taxonomies&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-taxonomies&tab=genres" class="nav-tab <?php echo $current_tab === 'genres' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-taxonomies&tab=status" class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Status', 'fanfiction-manager' ); ?>
				</a>
				<?php if ( $enable_warnings ) : ?>
					<a href="?page=fanfiction-taxonomies&tab=warnings" class="nav-tab <?php echo $current_tab === 'warnings' ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Warnings', 'fanfiction-manager' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $enable_fandoms ) : ?>
					<a href="?page=fanfiction-taxonomies&tab=fandoms" class="nav-tab <?php echo $current_tab === 'fandoms' ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $enable_languages ) : ?>
					<a href="?page=fanfiction-taxonomies&tab=languages" class="nav-tab <?php echo $current_tab === 'languages' ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Languages', 'fanfiction-manager' ); ?>
					</a>
				<?php endif; ?>

				<?php foreach ( $custom_taxonomy_tabs as $tab_slug => $tab_name ) : ?>
					<a href="?page=fanfiction-taxonomies&tab=<?php echo esc_attr( $tab_slug ); ?>" class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content">
				<?php
				if ( $is_custom_tab ) {
					// Render custom taxonomy terms page
					$taxonomy_slug = Fanfic_Custom_Taxonomies_Admin::get_taxonomy_slug_from_tab( $current_tab );
					Fanfic_Custom_Taxonomies_Admin::render_terms_page( $taxonomy_slug );
				} else {
					switch ( $current_tab ) {
						case 'genres':
							self::render_genres_tab();
							break;
						case 'status':
							self::render_status_tab();
							break;
						case 'warnings':
							if ( $enable_warnings && class_exists( 'Fanfic_Warnings_Admin' ) ) {
								Fanfic_Warnings_Admin::render();
							} else {
								self::render_warnings_placeholder();
							}
							break;
						case 'fandoms':
							if ( $enable_fandoms && class_exists( 'Fanfic_Fandoms_Admin' ) ) {
								Fanfic_Fandoms_Admin::render();
							} else {
								self::render_fandoms_placeholder();
							}
							break;
						case 'languages':
							if ( $enable_languages && class_exists( 'Fanfic_Languages_Admin' ) ) {
								Fanfic_Languages_Admin::render();
							} else {
								self::render_languages_placeholder();
							}
							break;

						case 'general':
						default:
							self::render_general_tab();
							break;
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render General tab content
	 *
	 * Contains feature toggles and custom taxonomy management.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_general_tab() {


		$enable_fandoms  = Fanfic_Settings::get_setting( 'enable_fandom_classification', false );
		$enable_languages = Fanfic_Settings::get_setting( 'enable_language_classification', false );
		$enable_warnings = Fanfic_Settings::get_setting( 'enable_warnings', true );
		$enable_tags     = Fanfic_Settings::get_setting( 'enable_tags', true );

		?>
		<!-- Feature Toggles -->
		<div class="fanfic-taxonomies-settings">
			<h2><?php esc_html_e( 'Taxonomy choices', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Enable or disable taxonomy features for your fanfiction site.', 'fanfiction-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_save_taxonomy_settings">
				<?php wp_nonce_field( 'fanfic_save_taxonomy_settings', 'fanfic_save_taxonomy_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Warnings & Age Classification', 'fanfiction-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_warnings" value="1" <?php checked( $enable_warnings, true ); ?>>
									<?php esc_html_e( 'Enable warnings and age classification', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Enable content warnings and derived age ratings.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Tags', 'fanfiction-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_tags" value="1" <?php checked( $enable_tags, true ); ?>>
									<?php esc_html_e( 'Enable visible and invisible tags', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Authors add tags and search keywords to their stories.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fandom Classification', 'fanfiction-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_fandom_classification" value="1" <?php checked( $enable_fandoms, true ); ?>>
									<?php esc_html_e( 'Enable fandom classification', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Enable the fandom classification system.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Language', 'fanfiction-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_language_classification" value="1" <?php checked( $enable_languages, true ); ?>>
									<?php esc_html_e( 'Enable language choice', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Enable the language system for stories. Authors can select the language their story is written in.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Settings', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>


		<?php
		if ( class_exists( 'Fanfic_Custom_Taxonomies_Admin' ) ) {
			Fanfic_Custom_Taxonomies_Admin::display_notices();
			
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! Fanfic_Custom_Taxonomies::tables_ready() ) {
				?>
				<div class="notice error-message">
					<p><?php esc_html_e( 'Custom taxonomy tables are missing. Please re-activate the plugin to create database tables.', 'fanfiction-manager' ); ?></p>
				</div>
				<?php
				return;
			}
	
			$taxonomies = Fanfic_Custom_Taxonomies::get_all_taxonomies();
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Custom Taxonomies', 'fanfiction-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Create your own custom taxonomies for classifying stories. Each taxonomy creates a new tab for managing its terms.', 'fanfiction-manager' ); ?></p>
	
				<!-- Create Taxonomy -->
				<div class="fanfic-custom-taxonomy-create">
					<h3><?php esc_html_e( 'Create New Taxonomy', 'fanfiction-manager' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-form-inline">
						<input type="hidden" name="action" value="fanfic_create_custom_taxonomy">
						<?php wp_nonce_field( 'fanfic_create_custom_taxonomy', 'fanfic_create_custom_taxonomy_nonce' ); ?>
	
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="taxonomy_name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></label>
								</th>
								<td>
									<input type="text" id="taxonomy_name" name="taxonomy_name" class="regular-text" required>
									<p class="description"><?php esc_html_e( 'The display name for this taxonomy (e.g., "Characters", "Pairings").', 'fanfiction-manager' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="taxonomy_selection_type"><?php esc_html_e( 'Selection Type', 'fanfiction-manager' ); ?></label>
								</th>
								<td>
									<select id="taxonomy_selection_type" name="taxonomy_selection_type">
										<option value="single"><?php esc_html_e( 'Single choice', 'fanfiction-manager' ); ?></option>
										<option value="multi"><?php esc_html_e( 'Multiple Choice', 'fanfiction-manager' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Single: user selects one term. Multiple: user can select many terms.', 'fanfiction-manager' ); ?></p>
								</td>
							</tr>
						</table>
	
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Taxonomy', 'fanfiction-manager' ); ?></button>
						</p>
					</form>
				</div>
	
				<?php if ( ! empty( $taxonomies ) ) : ?>
					<!-- Existing Taxonomies -->
					<div class="fanfic-custom-taxonomies-list">
						<h3><?php esc_html_e( 'Existing Custom Taxonomies', 'fanfiction-manager' ); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
									<th scope="col" class="manage-column column-type"><?php esc_html_e( 'Type', 'fanfiction-manager' ); ?></th>
									<th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'fanfiction-manager' ); ?></th>
									<th scope="col" class="manage-column column-stats"><?php esc_html_e( 'Stats', 'fanfiction-manager' ); ?></th>
									<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
									<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $taxonomies as $taxonomy ) : ?>
									<?php
									$stats      = Fanfic_Custom_Taxonomies::get_taxonomy_stats( $taxonomy['id'] );
									$is_active  = ! empty( $taxonomy['is_active'] );
									$tab_link   = admin_url( 'admin.php?page=fanfiction-taxonomies&tab=custom-' . $taxonomy['slug'] );
									$edit_nonce = wp_create_nonce( 'fanfic_update_custom_taxonomy_' . $taxonomy['id'] );
									?>
									<tr>
										<td class="column-name">
											<strong><a href="<?php echo esc_url( $tab_link ); ?>"><?php echo esc_html( $taxonomy['name'] ); ?></a></strong>
										</td>
										<td class="column-type">
											<?php echo 'multi' === $taxonomy['selection_type'] ? esc_html__( 'Multiple', 'fanfiction-manager' ) : esc_html__( 'Single', 'fanfiction-manager' ); ?>
										</td>
										<td class="column-shortcode">
											<code>[story-<?php echo esc_html( $taxonomy['slug'] ); ?>]</code>
										</td>
										<td class="column-stats">
											<?php
											printf(
												/* translators: 1: term count, 2: story count */
												esc_html__( '%1$d terms, %2$d stories', 'fanfiction-manager' ),
												$stats['term_count'],
												$stats['story_count']
											);
											?>
										</td>
										<td class="column-status">
											<?php if ( $is_active ) : ?>
												<span class="fanfic-status-badge fanfic-status-active"><?php esc_html_e( 'Active', 'fanfiction-manager' ); ?></span>
											<?php else : ?>
												<span class="fanfic-status-badge fanfic-status-inactive"><?php esc_html_e( 'Inactive', 'fanfiction-manager' ); ?></span>
											<?php endif; ?>
										</td>
										<td class="column-actions">
											<a href="<?php echo esc_url( $tab_link ); ?>" class="button button-small">
												<?php esc_html_e( 'Manage Terms', 'fanfiction-manager' ); ?>
											</a>
											<button type="button" class="button button-small fanfic-edit-taxonomy-btn"
												data-id="<?php echo esc_attr( $taxonomy['id'] ); ?>"
												data-name="<?php echo esc_attr( $taxonomy['name'] ); ?>"
												data-active="<?php echo $is_active ? '1' : '0'; ?>"
												data-selection-type="<?php echo esc_attr( $taxonomy['selection_type'] ); ?>"
												data-nonce="<?php echo esc_attr( $edit_nonce ); ?>"
												data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_custom_taxonomy' ) ); ?>">
												<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
	
			<!-- Edit Taxonomy Modal -->
			<div class="fanfic-admin-modal" id="fanfic-edit-taxonomy-modal" aria-hidden="true">
				<div class="fanfic-admin-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Edit Taxonomy', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-edit-taxonomy-form">
						<input type="hidden" name="action" value="fanfic_update_custom_taxonomy">
						<input type="hidden" name="taxonomy_id" id="fanfic-edit-taxonomy-id" value="">
						<input type="hidden" name="fanfic_update_custom_taxonomy_nonce" id="fanfic-edit-taxonomy-nonce" value="">
	
						<p>
							<label for="fanfic-edit-taxonomy-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<input type="text" id="fanfic-edit-taxonomy-name" name="taxonomy_name" class="regular-text" required>
						</p>

						<p>
							<label for="fanfic-edit-taxonomy-selection-type"><?php esc_html_e( 'Selection Type', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<select id="fanfic-edit-taxonomy-selection-type" name="taxonomy_selection_type" required>
								<option value="single"><?php esc_html_e( 'Single choice', 'fanfiction-manager' ); ?></option>
								<option value="multi"><?php esc_html_e( 'Multiple Choice', 'fanfiction-manager' ); ?></option>
							</select>
						</p>

						<p>
							<label>
								<input type="checkbox" name="taxonomy_is_active" id="fanfic-edit-taxonomy-active" value="1">
								<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?>
							</label>
						</p>
	
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button danger fanfic-delete-taxonomy-btn"><?php esc_html_e( 'Delete Taxonomy', 'fanfiction-manager' ); ?></button>
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-delete-taxonomy-form" style="display: none;">
						<input type="hidden" name="action" value="fanfic_delete_custom_taxonomy">
						<input type="hidden" name="taxonomy_id" id="fanfic-delete-taxonomy-id" value="">
						<input type="hidden" name="fanfic_delete_custom_taxonomy_nonce" id="fanfic-delete-taxonomy-nonce" value="">
					</form>

				</div>
			</div>
	
			<script>
			jQuery(document).ready(function($) {
				// Edit Taxonomy Modal
				$('.fanfic-edit-taxonomy-btn').on('click', function() {
					var $btn = $(this);
					$('#fanfic-edit-taxonomy-id').val($btn.data('id'));
					$('#fanfic-edit-taxonomy-name').val($btn.data('name'));
					$('#fanfic-edit-taxonomy-active').prop('checked', $btn.data('active') === 1);
					$('#fanfic-edit-taxonomy-selection-type').val($btn.data('selection-type'));
					$('#fanfic-edit-taxonomy-nonce').val($btn.data('nonce'));
					$('#fanfic-delete-taxonomy-id').val($btn.data('id'));
					$('#fanfic-delete-taxonomy-nonce').val($btn.data('delete-nonce'));
					$('#fanfic-edit-taxonomy-modal').attr('aria-hidden', 'false');
				});
	
				// Delete Taxonomy
				$('.fanfic-delete-taxonomy-btn').on('click', function() {
					if (confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete this taxonomy? All terms and story associations will be permanently deleted. This cannot be undone.', 'fanfiction-manager' ) ); ?>)) {
						$('#fanfic-delete-taxonomy-form').submit();
					}
				});
	
				// Close modals
				$('.fanfic-modal-cancel, .fanfic-admin-modal-overlay').on('click', function() {
					$(this).closest('.fanfic-admin-modal').attr('aria-hidden', 'true');
				});
	
				$(document).on('keydown', function(e) {
					if (e.key === 'Escape') {
						$('.fanfic-admin-modal').attr('aria-hidden', 'true');
					}
				});
			});
			</script>
			<?php
		}
	}

	/**
	 * Render Genres tab content
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_genres_tab() {
		$genres = get_terms(
			array(
				'taxonomy'   => 'fanfiction_genre',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$genre_count = is_array( $genres ) ? count( $genres ) : 0;

		?>
		<div class="fanfic-taxonomy-tab">
			<h2><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage story genres for categorizing fanfiction by literary genre.', 'fanfiction-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxonomy Slug', 'fanfiction-manager' ); ?></th>
						<td><code>fanfiction_genre</code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Term Count', 'fanfiction-manager' ); ?></th>
						<td><?php echo esc_html( $genre_count ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcodes', 'fanfiction-manager' ); ?></th>
						<td>
							<code>[fanfic-genre]</code> - <?php esc_html_e( 'Display genre links', 'fanfiction-manager' ); ?><br>
							<code>[fanfic-genre-title]</code> - <?php esc_html_e( 'Display genre title', 'fanfiction-manager' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=fanfiction_genre&post_type=fanfiction_story' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Manage Genre Terms', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Status tab content
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_status_tab() {
		$statuses = get_terms(
			array(
				'taxonomy'   => 'fanfiction_status',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$status_count = is_array( $statuses ) ? count( $statuses ) : 0;

		?>
		<div class="fanfic-taxonomy-tab">
			<h2><?php esc_html_e( 'Story Status', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage story status options (e.g., In Progress, Complete, On Hiatus).', 'fanfiction-manager' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxonomy Slug', 'fanfiction-manager' ); ?></th>
						<td><code>fanfiction_status</code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Term Count', 'fanfiction-manager' ); ?></th>
						<td><?php echo esc_html( $status_count ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcodes', 'fanfiction-manager' ); ?></th>
						<td>
							<code>[fanfic-status]</code> - <?php esc_html_e( 'Display status links', 'fanfiction-manager' ); ?><br>
							<code>[fanfic-status-title]</code> - <?php esc_html_e( 'Display status title', 'fanfiction-manager' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=fanfiction_status&post_type=fanfiction_story' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Manage Status Terms', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Warnings placeholder (when Warnings Admin class is not loaded)
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_warnings_placeholder() {
		?>
		<div class="fanfic-taxonomy-tab">
			<h2><?php esc_html_e( 'Content Warnings', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Content warnings and age classifications are not enabled.', 'fanfiction-manager' ); ?></p>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Enable warnings in the General tab to manage content warnings.', 'fanfiction-manager' ); ?>
					<a href="?page=fanfiction-taxonomies&tab=general"><?php esc_html_e( 'Go to General tab', 'fanfiction-manager' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Fandoms placeholder (when disabled or not loaded)
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_fandoms_placeholder() {
		?>
		<div class="fanfic-taxonomy-tab">
			<h2><?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Fandom classification is not enabled.', 'fanfiction-manager' ); ?></p>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Enable fandom classification in the General tab to manage fandoms.', 'fanfiction-manager' ); ?>
					<a href="?page=fanfiction-taxonomies&tab=general"><?php esc_html_e( 'Go to General tab', 'fanfiction-manager' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Languages placeholder (when disabled or not loaded)
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private static function render_languages_placeholder() {
		?>
		<div class="fanfic-taxonomy-tab">
			<h2><?php esc_html_e( 'Languages', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Language classification is not enabled.', 'fanfiction-manager' ); ?></p>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Enable language classification in the General tab to manage languages.', 'fanfiction-manager' ); ?>
					<a href="?page=fanfiction-taxonomies&tab=general"><?php esc_html_e( 'Go to General tab', 'fanfiction-manager' ); ?></a>
				</p>
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
				'taxonomy_added'          => __( 'Custom taxonomy added successfully. Shortcodes have been generated automatically.', 'fanfiction-manager' ),
				'taxonomy_deleted'        => __( 'Custom taxonomy deleted successfully. All terms have been removed from stories.', 'fanfiction-manager' ),
				'fandom_settings_saved'   => __( 'Fandom classification settings saved.', 'fanfiction-manager' ),
				'taxonomy_settings_saved' => __( 'Taxonomy settings saved successfully.', 'fanfiction-manager' ),
				'warning_added'           => __( 'Warning added successfully.', 'fanfiction-manager' ),
				'warning_updated'         => __( 'Warning updated successfully.', 'fanfiction-manager' ),
				'warning_deleted'         => __( 'Warning deleted successfully.', 'fanfiction-manager' ),
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

	/**
	 * Save fandom classification settings
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_fandom_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_POST['fanfic_save_fandom_settings_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_save_fandom_settings_nonce'] ) ), 'fanfic_save_fandom_settings' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		$enabled = isset( $_POST['enable_fandom_classification'] ) && '1' === $_POST['enable_fandom_classification'];
		Fanfic_Settings::update_setting( 'enable_fandom_classification', $enabled );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-taxonomies',
					'tab'     => 'general',
					'success' => 'fandom_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save taxonomy feature settings
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function save_taxonomy_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_POST['fanfic_save_taxonomy_settings_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_save_taxonomy_settings_nonce'] ) ), 'fanfic_save_taxonomy_settings' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Save fandom classification setting
		$enable_fandoms = isset( $_POST['enable_fandom_classification'] ) && '1' === $_POST['enable_fandom_classification'];
		Fanfic_Settings::update_setting( 'enable_fandom_classification', $enable_fandoms );

		// Save warnings and tags settings
		$enable_warnings = isset( $_POST['enable_warnings'] ) && '1' === $_POST['enable_warnings'];
		$enable_tags     = isset( $_POST['enable_tags'] ) && '1' === $_POST['enable_tags'];
		Fanfic_Settings::update_setting( 'enable_warnings', $enable_warnings );
		Fanfic_Settings::update_setting( 'enable_tags', $enable_tags );

		// Save language classification setting
		$enable_languages = isset( $_POST['enable_language_classification'] ) && '1' === $_POST['enable_language_classification'];
		Fanfic_Settings::update_setting( 'enable_language_classification', $enable_languages );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-taxonomies',
					'tab'     => 'general',
					'success' => 'taxonomy_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
