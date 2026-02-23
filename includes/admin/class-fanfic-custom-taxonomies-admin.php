<?php
/**
 * Custom Taxonomies Admin Interface
 *
 * Provides admin UI for managing custom taxonomies and their terms.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.4.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Custom_Taxonomies_Admin
 */
class Fanfic_Custom_Taxonomies_Admin {

	/**
	 * Initialize admin hooks
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		// Register action handlers for taxonomies.
		add_action( 'admin_post_fanfic_create_custom_taxonomy', array( __CLASS__, 'handle_create_taxonomy' ) );
		add_action( 'admin_post_fanfic_update_custom_taxonomy', array( __CLASS__, 'handle_update_taxonomy' ) );
		add_action( 'admin_post_fanfic_delete_custom_taxonomy', array( __CLASS__, 'handle_delete_taxonomy' ) );

		// Register action handlers for terms.
		add_action( 'admin_post_fanfic_add_custom_term', array( __CLASS__, 'handle_add_term' ) );
		add_action( 'admin_post_fanfic_update_custom_term', array( __CLASS__, 'handle_update_term' ) );
		add_action( 'admin_post_fanfic_toggle_custom_term', array( __CLASS__, 'handle_toggle_term' ) );
		add_action( 'admin_post_fanfic_delete_custom_term', array( __CLASS__, 'handle_delete_term' ) );
		add_action( 'admin_post_fanfic_bulk_custom_terms', array( __CLASS__, 'handle_bulk_terms' ) );
	}

	/**
	 * Get allowed tabs including custom taxonomies.
	 *
	 * @since 1.4.0
	 * @return array Array of tab slugs.
	 */
	public static function get_custom_taxonomy_tabs() {
		$taxonomies = Fanfic_Custom_Taxonomies::get_all_taxonomies();
		$tabs       = array();

		foreach ( $taxonomies as $taxonomy ) {
			$tabs[ 'custom-' . $taxonomy['slug'] ] = $taxonomy['name'];
		}

		return $tabs;
	}

	/**
	 * Check if a tab is a custom taxonomy tab.
	 *
	 * @since 1.4.0
	 * @param string $tab Tab slug.
	 * @return bool True if custom taxonomy tab.
	 */
	public static function is_custom_taxonomy_tab( $tab ) {
		return 0 === strpos( $tab, 'custom-' );
	}

	/**
	 * Get taxonomy slug from tab.
	 *
	 * @since 1.4.0
	 * @param string $tab Tab slug.
	 * @return string Taxonomy slug.
	 */
	public static function get_taxonomy_slug_from_tab( $tab ) {
		return str_replace( 'custom-', '', $tab );
	}



	/**
	 * Render terms management page for a custom taxonomy.
	 *
	 * @since 1.4.0
	 * @param string $taxonomy_slug Taxonomy slug.
	 * @return void
	 */
	public static function render_terms_page( $taxonomy_slug ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		$taxonomy = Fanfic_Custom_Taxonomies::get_taxonomy_by_slug( $taxonomy_slug );
		if ( ! $taxonomy ) {
			?>
			<div class="notice error-message">
				<p><?php esc_html_e( 'Taxonomy not found.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
			return;
		}

		self::display_notices();

		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$terms         = self::get_filtered_terms( $taxonomy['id'], $search, $filter_status );
		$total_count   = self::count_terms( $taxonomy['id'], '', '' );
		$active_count  = self::count_terms( $taxonomy['id'], '', 'active' );
		$inactive_count = self::count_terms( $taxonomy['id'], '', 'inactive' );

		$base_url = admin_url( 'admin.php?page=fanfiction-taxonomies&tab=custom-' . $taxonomy_slug );
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $taxonomy['name'] ); ?></h2>
			<div class="fanfic-terms-actions">
				<button type="button"
					class="button button-secondary fanfic-edit-taxonomy-btn"
					data-id="<?php echo esc_attr( $taxonomy['id'] ); ?>"
					data-name="<?php echo esc_attr( $taxonomy['name'] ); ?>"
					data-selection-type="<?php echo esc_attr( $taxonomy['selection_type'] ); ?>"
					data-display-format="<?php echo esc_attr( isset( $taxonomy['display_format'] ) ? $taxonomy['display_format'] : 'grid' ); ?>"
					data-active="<?php echo ! empty( $taxonomy['is_active'] ) ? '1' : '0'; ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_update_custom_taxonomy_' . $taxonomy['id'] ) ); ?>">
					<?php esc_html_e( 'Edit Taxonomy', 'fanfiction-manager' ); ?>
				</button>
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: shortcode */
					esc_html__( 'Manage terms for this taxonomy. Use the shortcode %s to display on stories.', 'fanfiction-manager' ),
					'<code>[story-' . esc_html( $taxonomy_slug ) . ']</code>'
				);
				?>
			</p>

			<!-- Actions Bar -->
			<div class="fanfic-terms-actions tablenav top">
				<div class="alignleft actions">
					<button type="button" class="button button-primary" id="fanfic-add-term-btn">
						<?php esc_html_e( 'Add Term', 'fanfiction-manager' ); ?>
					</button>
				</div>

				<!-- Search -->
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form alignright">
					<input type="hidden" name="page" value="fanfiction-taxonomies">
					<input type="hidden" name="tab" value="custom-<?php echo esc_attr( $taxonomy_slug ); ?>">
					<?php if ( $filter_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $filter_status ); ?>">
					<?php endif; ?>
					<label class="screen-reader-text" for="fanfic-term-search"><?php esc_html_e( 'Search terms', 'fanfiction-manager' ); ?></label>
					<input type="search" id="fanfic-term-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search terms...', 'fanfiction-manager' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'fanfiction-manager' ); ?></button>
				</form>
			</div>

			<!-- Filter Links -->
			<ul class="subsubsub">
				<li class="all">
					<a href="<?php echo esc_url( $base_url ); ?>" <?php echo '' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'All', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
					</a> |
				</li>
				<li class="active">
					<a href="<?php echo esc_url( $base_url . '&status=active' ); ?>" <?php echo 'active' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $active_count ); ?>)</span>
					</a> |
				</li>
				<li class="inactive">
					<a href="<?php echo esc_url( $base_url . '&status=inactive' ); ?>" <?php echo 'inactive' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Inactive', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $inactive_count ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Terms Table -->
			<form id="fanfic-terms-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_bulk_custom_terms">
				<input type="hidden" name="taxonomy_id" value="<?php echo esc_attr( $taxonomy['id'] ); ?>">
				<input type="hidden" name="taxonomy_slug" value="<?php echo esc_attr( $taxonomy_slug ); ?>">
				<?php wp_nonce_field( 'fanfic_bulk_custom_terms', 'fanfic_bulk_custom_terms_nonce' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'fanfiction-manager' ); ?></label>
						<select name="bulk_action" id="bulk-action-selector-top">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'fanfiction-manager' ); ?></option>
							<option value="activate"><?php esc_html_e( 'Activate', 'fanfiction-manager' ); ?></option>
							<option value="deactivate"><?php esc_html_e( 'Deactivate', 'fanfiction-manager' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></option>
						</select>
						<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'fanfiction-manager' ); ?></button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped fanfic-terms-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select all', 'fanfiction-manager' ); ?></label>
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $terms ) ) : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'No terms found.', 'fanfiction-manager' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $terms as $term ) : ?>
								<?php
								$is_active    = ! empty( $term['is_active'] );
								$edit_nonce   = wp_create_nonce( 'fanfic_update_custom_term_' . $term['id'] );
								$delete_nonce = wp_create_nonce( 'fanfic_delete_custom_term_' . $term['id'] );
								$toggle_nonce = wp_create_nonce( 'fanfic_toggle_custom_term_' . $term['id'] );
								?>
								<tr data-id="<?php echo esc_attr( $term['id'] ); ?>">
									<th scope="row" class="check-column">
										<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $term['id'] ); ?>">
											<?php echo esc_html( sprintf( __( 'Select %s', 'fanfiction-manager' ), $term['name'] ) ); ?>
										</label>
										<input id="cb-select-<?php echo esc_attr( $term['id'] ); ?>" type="checkbox" name="term_ids[]" value="<?php echo esc_attr( $term['id'] ); ?>">
									</th>
									<td class="column-name column-primary" data-colname="<?php esc_attr_e( 'Name', 'fanfiction-manager' ); ?>">
										<strong><?php echo esc_html( $term['name'] ); ?></strong>
										<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'fanfiction-manager' ); ?></span></button>
									</td>
									<td class="column-slug" data-colname="<?php esc_attr_e( 'Slug', 'fanfiction-manager' ); ?>">
										<code><?php echo esc_html( $term['slug'] ); ?></code>
									</td>
									<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'fanfiction-manager' ); ?>">
										<?php if ( $is_active ) : ?>
											<span class="fanfic-status-badge fanfic-status-active"><?php esc_html_e( 'Active', 'fanfiction-manager' ); ?></span>
										<?php else : ?>
											<span class="fanfic-status-badge fanfic-status-inactive"><?php esc_html_e( 'Inactive', 'fanfiction-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
										<button type="button" class="button button-small fanfic-edit-term-btn"
											data-id="<?php echo esc_attr( $term['id'] ); ?>"
											data-name="<?php echo esc_attr( $term['name'] ); ?>"
											data-slug="<?php echo esc_attr( $term['slug'] ); ?>"
											data-active="<?php echo $is_active ? '1' : '0'; ?>"
											data-nonce="<?php echo esc_attr( $edit_nonce ); ?>"
											data-delete-nonce="<?php echo esc_attr( $delete_nonce ); ?>">
											<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
										</button>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<input type="hidden" name="action" value="fanfic_toggle_custom_term">
											<input type="hidden" name="term_id" value="<?php echo esc_attr( $term['id'] ); ?>">
											<input type="hidden" name="taxonomy_slug" value="<?php echo esc_attr( $taxonomy_slug ); ?>">
											<input type="hidden" name="fanfic_toggle_custom_term_nonce" value="<?php echo esc_attr( $toggle_nonce ); ?>">
											<button type="submit" class="button button-small">
												<?php echo $is_active ? esc_html__( 'Deactivate', 'fanfiction-manager' ) : esc_html__( 'Activate', 'fanfiction-manager' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>

			<!-- Add Term Modal -->
			<div class="fanfic-admin-modal" id="fanfic-add-term-modal" aria-hidden="true">
				<div class="fanfic-admin-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Add Terms', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form">
						<input type="hidden" name="action" value="fanfic_add_custom_term">
						<input type="hidden" name="taxonomy_id" value="<?php echo esc_attr( $taxonomy['id'] ); ?>">
						<input type="hidden" name="taxonomy_slug" value="<?php echo esc_attr( $taxonomy_slug ); ?>">
						<?php wp_nonce_field( 'fanfic_add_custom_term', 'fanfic_add_custom_term_nonce' ); ?>

						<p>
							<label for="fanfic-add-term-names"><?php esc_html_e( 'Term Names (comma-separated)', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<textarea id="fanfic-add-term-names" name="term_names" rows="4" required></textarea>
							<span class="description"><?php esc_html_e( 'Add one or more terms separated by commas.', 'fanfiction-manager' ); ?></span>
						</p>

						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Terms', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
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

						<p id="fanfic-edit-taxonomy-display-format-row" style="display: none;">
							<label for="fanfic-edit-taxonomy-display-format"><?php esc_html_e( 'Display on story form as', 'fanfiction-manager' ); ?></label>
							<select id="fanfic-edit-taxonomy-display-format" name="taxonomy_display_format">
								<option value="grid"><?php esc_html_e( 'Checkbox Grid', 'fanfiction-manager' ); ?></option>
								<option value="dropdown"><?php esc_html_e( 'Dropdown Multi-Select', 'fanfiction-manager' ); ?></option>
								<option value="search"><?php esc_html_e( 'Searchable Field', 'fanfiction-manager' ); ?></option>
							</select>
							<span class="description"><?php esc_html_e( 'Grid: checkboxes (small lists). Dropdown: collapsible list (medium lists). Searchable: type-ahead (large lists).', 'fanfiction-manager' ); ?></span>
						</p>

						<p>
							<label>
								<input type="checkbox" name="taxonomy_is_active" id="fanfic-edit-taxonomy-active" value="1">
								<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?>
							</label>
						</p>

						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<!-- Edit Term Modal -->
			<div class="fanfic-admin-modal" id="fanfic-edit-term-modal" aria-hidden="true">
				<div class="fanfic-admin-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Edit Term', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-edit-term-form">
						<input type="hidden" name="action" value="fanfic_update_custom_term">
						<input type="hidden" name="term_id" id="fanfic-edit-term-id" value="">
						<input type="hidden" name="taxonomy_slug" value="<?php echo esc_attr( $taxonomy_slug ); ?>">
						<input type="hidden" name="fanfic_update_custom_term_nonce" id="fanfic-edit-term-nonce" value="">

						<p>
							<label for="fanfic-edit-term-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<input type="text" id="fanfic-edit-term-name" name="term_name" class="regular-text" required>
						</p>

						<p>
							<label for="fanfic-edit-term-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></label>
							<input type="text" id="fanfic-edit-term-slug" name="term_slug" class="regular-text" readonly>
							<span class="description"><?php esc_html_e( 'Slug cannot be changed after creation.', 'fanfiction-manager' ); ?></span>
						</p>

						<p>
							<label>
								<input type="checkbox" name="term_is_active" id="fanfic-edit-term-active" value="1">
								<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?>
							</label>
						</p>

						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button danger fanfic-delete-term-btn"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-delete-term-form" style="display: none;">
						<input type="hidden" name="action" value="fanfic_delete_custom_term">
						<input type="hidden" name="term_id" id="fanfic-delete-term-id" value="">
						<input type="hidden" name="taxonomy_slug" value="<?php echo esc_attr( $taxonomy_slug ); ?>">
						<input type="hidden" name="fanfic_delete_custom_term_nonce" id="fanfic-delete-term-nonce" value="">
					</form>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Add Term Modal
			$('#fanfic-add-term-btn').on('click', function() {
				$('#fanfic-add-term-modal').attr('aria-hidden', 'false');
			});

			// Edit Taxonomy Modal
			$('#fanfic-edit-taxonomy-selection-type').on('change', function() {
				if ($(this).val() === 'multi') {
					$('#fanfic-edit-taxonomy-display-format-row').show();
				} else {
					$('#fanfic-edit-taxonomy-display-format-row').hide();
					$('#fanfic-edit-taxonomy-display-format').val('grid');
				}
			});

			$('.fanfic-edit-taxonomy-btn').on('click', function() {
				var $btn = $(this);
				$('#fanfic-edit-taxonomy-id').val($btn.data('id'));
				$('#fanfic-edit-taxonomy-name').val($btn.data('name'));
				$('#fanfic-edit-taxonomy-selection-type').val($btn.data('selection-type'));
				$('#fanfic-edit-taxonomy-display-format').val($btn.data('display-format') || 'grid');
				if ($btn.data('selection-type') === 'multi') {
					$('#fanfic-edit-taxonomy-display-format-row').show();
				} else {
					$('#fanfic-edit-taxonomy-display-format-row').hide();
				}
				$('#fanfic-edit-taxonomy-active').prop('checked', $btn.data('active') === 1);
				$('#fanfic-edit-taxonomy-nonce').val($btn.data('nonce'));
				$('#fanfic-edit-taxonomy-modal').attr('aria-hidden', 'false');
			});

			// Edit Term Modal
			$('.fanfic-edit-term-btn').on('click', function() {
				var $btn = $(this);
				$('#fanfic-edit-term-id').val($btn.data('id'));
				$('#fanfic-edit-term-name').val($btn.data('name'));
				$('#fanfic-edit-term-slug').val($btn.data('slug'));
				$('#fanfic-edit-term-active').prop('checked', $btn.data('active') === 1);
				$('#fanfic-edit-term-nonce').val($btn.data('nonce'));
				$('#fanfic-delete-term-id').val($btn.data('id'));
				$('#fanfic-delete-term-nonce').val($btn.data('delete-nonce'));
				$('#fanfic-edit-term-modal').attr('aria-hidden', 'false');
			});

			// Delete Term
			$('.fanfic-delete-term-btn').on('click', function() {
				if (confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete this term? Stories using this term will lose their assignment.', 'fanfiction-manager' ) ); ?>)) {
					$('#fanfic-delete-term-form').submit();
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

			// Bulk action confirmation
			$('#fanfic-terms-form').on('submit', function(e) {
				var action = $('select[name="bulk_action"]').val();
				if (action === 'delete') {
					if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete the selected terms?', 'fanfiction-manager' ) ); ?>)) {
						e.preventDefault();
					}
				}
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// Taxonomy Handlers
	// =========================================================================

	/**
	 * Handle create taxonomy.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_create_taxonomy() {
		self::check_admin_nonce( 'fanfic_create_custom_taxonomy', 'fanfic_create_custom_taxonomy_nonce' );

		$name           = isset( $_POST['taxonomy_name'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_name'] ) ) : '';
		$selection_type = isset( $_POST['taxonomy_selection_type'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_selection_type'] ) ) : 'single';
		$display_format = isset( $_POST['taxonomy_display_format'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_display_format'] ) ) : 'grid';
		$name           = '' !== $name ? ucfirst( $name ) : '';

		if ( '' === $name ) {
			self::redirect_with_message( 'general', 'error', 'missing_fields' );
		}

		$result = Fanfic_Custom_Taxonomies::create_taxonomy(
			array(
				'name'           => $name,
				'selection_type' => $selection_type,
				'display_format' => $display_format,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( 'general', 'error', $result->get_error_code() );
		}

		self::redirect_with_message( 'general', 'success', 'taxonomy_created' );
	}

	/**
	 * Handle update taxonomy.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_update_taxonomy() {
		$taxonomy_id = isset( $_POST['taxonomy_id'] ) ? absint( $_POST['taxonomy_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_update_custom_taxonomy_' . $taxonomy_id, 'fanfic_update_custom_taxonomy_nonce' );

		$name           = isset( $_POST['taxonomy_name'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_name'] ) ) : '';
		$name           = '' !== $name ? ucfirst( $name ) : '';
		$selection_type = isset( $_POST['taxonomy_selection_type'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_selection_type'] ) ) : 'single';
		$display_format = isset( $_POST['taxonomy_display_format'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy_display_format'] ) ) : 'grid';
		$is_active      = isset( $_POST['taxonomy_is_active'] ) ? 1 : 0;

		if ( ! $taxonomy_id || '' === $name ) {
			self::redirect_with_message( 'general', 'error', 'missing_fields' );
		}

		$result = Fanfic_Custom_Taxonomies::update_taxonomy(
			$taxonomy_id,
			array(
				'name'           => $name,
				'selection_type' => $selection_type,
				'display_format' => $display_format,
				'is_active'      => $is_active,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( 'general', 'error', $result->get_error_code() );
		}

		self::redirect_with_message( 'general', 'success', 'taxonomy_updated' );
	}

	/**
	 * Handle delete taxonomy.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_delete_taxonomy() {
		self::check_admin_nonce( 'fanfic_delete_custom_taxonomy', 'fanfic_delete_custom_taxonomy_nonce' );

		$taxonomy_id = isset( $_POST['taxonomy_id'] ) ? absint( $_POST['taxonomy_id'] ) : 0;

		if ( ! $taxonomy_id ) {
			self::redirect_with_message( 'general', 'error', 'missing_fields' );
		}

		$result = Fanfic_Custom_Taxonomies::delete_taxonomy( $taxonomy_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( 'general', 'error', $result->get_error_code() );
		}

		self::redirect_with_message( 'general', 'success', 'taxonomy_deleted' );
	}

	// =========================================================================
	// Term Handlers
	// =========================================================================

	/**
	 * Handle add term.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_add_term() {
		self::check_admin_nonce( 'fanfic_add_custom_term', 'fanfic_add_custom_term_nonce' );

		$taxonomy_id   = isset( $_POST['taxonomy_id'] ) ? absint( $_POST['taxonomy_id'] ) : 0;
		$taxonomy_slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';
		$raw_names     = isset( $_POST['term_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['term_names'] ) ) : '';
		$name          = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
		$slug          = isset( $_POST['term_slug'] ) ? sanitize_title( wp_unslash( $_POST['term_slug'] ) ) : '';

		$names = self::split_names( $raw_names );
		if ( empty( $names ) && '' !== $name ) {
			$names = array( $name );
		}

		if ( ! $taxonomy_id || empty( $names ) ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
		}

		foreach ( $names as $term_name ) {
			$term_data = array( 'name' => $term_name );
			if ( 1 === count( $names ) && '' !== $slug ) {
				$term_data['slug'] = $slug;
			}

			$result = Fanfic_Custom_Taxonomies::create_term( $taxonomy_id, $term_data );
			if ( is_wp_error( $result ) ) {
				self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', $result->get_error_code() );
			}
		}

		self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_added' );
	}

	/**
	 * Handle update term.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_update_term() {
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_update_custom_term_' . $term_id, 'fanfic_update_custom_term_nonce' );

		$taxonomy_slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';
		$name          = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
		$is_active     = isset( $_POST['term_is_active'] ) ? 1 : 0;

		if ( ! $term_id || '' === $name ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
		}

		$result = Fanfic_Custom_Taxonomies::update_term(
			$term_id,
			array(
				'name'      => $name,
				'is_active' => $is_active,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', $result->get_error_code() );
		}

		self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_updated' );
	}

	/**
	 * Handle toggle term.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_toggle_term() {
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_toggle_custom_term_' . $term_id, 'fanfic_toggle_custom_term_nonce' );

		$taxonomy_slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';

		if ( ! $term_id ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
		}

		$term = Fanfic_Custom_Taxonomies::get_term_by_id( $term_id );
		if ( ! $term ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'not_found' );
		}

		$new_status = empty( $term['is_active'] ) ? 1 : 0;
		Fanfic_Custom_Taxonomies::update_term( $term_id, array( 'is_active' => $new_status ) );

		self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_updated' );
	}

	/**
	 * Handle delete term.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_delete_term() {
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_delete_custom_term_' . $term_id, 'fanfic_delete_custom_term_nonce' );

		$taxonomy_slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';

		if ( ! $term_id ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
		}

		$result = Fanfic_Custom_Taxonomies::delete_term( $term_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', $result->get_error_code() );
		}

		self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_deleted' );
	}

	/**
	 * Handle bulk terms.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_bulk_terms() {
		self::check_admin_nonce( 'fanfic_bulk_custom_terms', 'fanfic_bulk_custom_terms_nonce' );

		$taxonomy_slug = isset( $_POST['taxonomy_slug'] ) ? sanitize_title( wp_unslash( $_POST['taxonomy_slug'] ) ) : '';
		$action        = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$term_ids      = isset( $_POST['term_ids'] ) ? array_map( 'absint', (array) $_POST['term_ids'] ) : array();

		if ( empty( $term_ids ) || '' === $action ) {
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
		}

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$is_active = 'activate' === $action ? 1 : 0;
			foreach ( $term_ids as $term_id ) {
				Fanfic_Custom_Taxonomies::update_term( $term_id, array( 'is_active' => $is_active ) );
			}
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_updated' );
		}

		if ( 'delete' === $action ) {
			foreach ( $term_ids as $term_id ) {
				Fanfic_Custom_Taxonomies::delete_term( $term_id );
			}
			self::redirect_with_message( 'custom-' . $taxonomy_slug, 'success', 'term_deleted' );
		}

		self::redirect_with_message( 'custom-' . $taxonomy_slug, 'error', 'missing_fields' );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Display admin notices.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Display admin notices.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function display_notices() {
		$success_messages = array(
			'taxonomy_created' => __( 'Taxonomy created successfully.', 'fanfiction-manager' ),
			'taxonomy_updated' => __( 'Taxonomy updated.', 'fanfiction-manager' ),
			'term_added'       => __( 'Term added.', 'fanfiction-manager' ),
			'term_updated'     => __( 'Term updated.', 'fanfiction-manager' ),
			'term_deleted'     => __( 'Term deleted.', 'fanfiction-manager' ),
		);

		$error_messages = array(
			'missing_fields'  => __( 'Required fields are missing.', 'fanfiction-manager' ),
			'invalid_data'    => __( 'Name and slug are required.', 'fanfiction-manager' ),
			'tables_not_ready' => __( 'Custom taxonomy tables are missing. Please re-activate the plugin to create database tables.', 'fanfiction-manager' ),
			'duplicate_slug'  => __( 'A taxonomy with this slug already exists.', 'fanfiction-manager' ),
			'reserved_slug'   => __( 'This slug is reserved and cannot be used.', 'fanfiction-manager' ),
			'not_found'       => __( 'Item not found.', 'fanfiction-manager' ),
			'insert_failed'   => __( 'Failed to save. Please try again.', 'fanfiction-manager' ),
			'update_failed'   => __( 'Failed to update. Please try again.', 'fanfiction-manager' ),
			'delete_failed'   => __( 'Failed to delete. Please try again.', 'fanfiction-manager' ),
		);

		if ( isset( $_GET['success'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			if ( isset( $success_messages[ $code ] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $success_messages[ $code ] ); ?></p>
				</div>
				<?php
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			if ( isset( $error_messages[ $code ] ) ) {
				?>
				<div class="notice error-message is-dismissible">
					<p><?php echo esc_html( $error_messages[ $code ] ); ?></p>
				</div>
				<?php
			}
		}
	}
	
	private static function get_filtered_terms( $taxonomy_id, $search = '', $status = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$where = array( 'taxonomy_id = %d' );
		$args  = array( $taxonomy_id );

		if ( '' !== $search ) {
			$where[] = 'name LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( 'active' === $status ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/**
	 * Split comma-separated names.
	 *
	 * @since 1.4.0
	 * @param string $names Raw list.
	 * @return string[]
	 */
	private static function split_names( $names ) {
		if ( '' === $names ) {
			return array();
		}
		$parts = array_map( 'trim', explode( ',', $names ) );
		$parts = array_filter( $parts, function( $value ) {
			return '' !== $value;
		} );
		return array_values( $parts );
	}

	/**
	 * Count terms.
	 *
	 * @since 1.4.0
	 * @param int    $taxonomy_id Taxonomy ID.
	 * @param string $search      Search query.
	 * @param string $status      Filter status.
	 * @return int
	 */
	private static function count_terms( $taxonomy_id, $search = '', $status = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$where = array( 'taxonomy_id = %d' );
		$args  = array( $taxonomy_id );

		if ( '' !== $search ) {
			$where[] = 'name LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( 'active' === $status ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Check admin nonce and permissions.
	 *
	 * @since 1.4.0
	 * @param string $action Nonce action.
	 * @param string $field  Nonce field.
	 * @return void
	 */
	private static function check_admin_nonce( $action, $field ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}
	}

	/**
	 * Redirect with message.
	 *
	 * @since 1.4.0
	 * @param string $tab  Tab slug.
	 * @param string $type success|error.
	 * @param string $code Message code.
	 * @return void
	 */
	private static function redirect_with_message( $tab, $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'fanfiction-taxonomies',
					'tab'  => $tab,
					$type  => $code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
