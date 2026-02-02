<?php
/**
 * Languages Admin Interface
 *
 * Provides admin UI for managing the language catalogue.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Languages_Admin
 */
class Fanfic_Languages_Admin {

	/**
	 * Initialize admin hooks
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function init() {
		if ( ! Fanfic_Languages::is_enabled() ) {
			return;
		}

		add_action( 'admin_post_fanfic_add_language', array( __CLASS__, 'handle_add_language' ) );
		add_action( 'admin_post_fanfic_update_language', array( __CLASS__, 'handle_update_language' ) );
		add_action( 'admin_post_fanfic_toggle_language', array( __CLASS__, 'handle_toggle_language' ) );
		add_action( 'admin_post_fanfic_delete_language', array( __CLASS__, 'handle_delete_language' ) );
		add_action( 'admin_post_fanfic_bulk_languages', array( __CLASS__, 'handle_bulk_languages' ) );
		add_action( 'admin_post_fanfic_import_languages', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Render admin page
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		self::display_notices();

		if ( ! Fanfic_Languages::tables_ready() ) {
			?>
			<div class="notice error-message">
				<p><?php esc_html_e( 'Language tables are missing. Please re-activate the plugin to create database tables.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$languages = self::get_all_languages( $search, $filter_status );
		$total_count = self::count_languages( '', '' );
		$active_count = self::count_languages( '', 'active' );
		$inactive_count = self::count_languages( '', 'inactive' );

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Languages', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage languages available for story classification. Users can select a language when creating or editing stories.', 'fanfiction-manager' ); ?></p>

			<!-- Actions Bar -->
			<div class="fanfic-languages-actions tablenav top">
				<div class="alignleft actions">
					<button type="button" class="button button-primary" id="fanfic-add-language-btn">
						<?php esc_html_e( 'Add Language', 'fanfiction-manager' ); ?>
					</button>
					<?php if ( ! Fanfic_Languages::has_languages() ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="fanfic_import_languages">
							<?php wp_nonce_field( 'fanfic_import_languages', 'fanfic_import_languages_nonce' ); ?>
							<button type="submit" class="button">
								<?php esc_html_e( 'Import Default Languages', 'fanfiction-manager' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>

				<!-- Search -->
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form alignright">
					<input type="hidden" name="page" value="fanfiction-taxonomies">
					<input type="hidden" name="tab" value="languages">
					<?php if ( $filter_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $filter_status ); ?>">
					<?php endif; ?>
					<label class="screen-reader-text" for="fanfic-language-search"><?php esc_html_e( 'Search languages', 'fanfiction-manager' ); ?></label>
					<input type="search" id="fanfic-language-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search languages...', 'fanfiction-manager' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'fanfiction-manager' ); ?></button>
				</form>
			</div>

			<!-- Filter Links -->
			<ul class="subsubsub">
				<li class="all">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-taxonomies&tab=languages' ) ); ?>" <?php echo '' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'All', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
					</a> |
				</li>
				<li class="active">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-taxonomies&tab=languages&status=active' ) ); ?>" <?php echo 'active' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $active_count ); ?>)</span>
					</a> |
				</li>
				<li class="inactive">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-taxonomies&tab=languages&status=inactive' ) ); ?>" <?php echo 'inactive' === $filter_status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Inactive', 'fanfiction-manager' ); ?> <span class="count">(<?php echo esc_html( $inactive_count ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Languages Table -->
			<form id="fanfic-languages-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_bulk_languages">
				<?php wp_nonce_field( 'fanfic_bulk_languages', 'fanfic_bulk_languages_nonce' ); ?>

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

				<table class="wp-list-table widefat fixed striped fanfic-languages-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select all', 'fanfiction-manager' ); ?></label>
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-native"><?php esc_html_e( 'Native Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $languages ) ) : ?>
							<tr>
								<td colspan="6"><?php esc_html_e( 'No languages found.', 'fanfiction-manager' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $languages as $language ) : ?>
								<?php
								$is_active = ! empty( $language['is_active'] );
								$edit_nonce = wp_create_nonce( 'fanfic_update_language_' . $language['id'] );
								$delete_nonce = wp_create_nonce( 'fanfic_delete_language_' . $language['id'] );
								$toggle_nonce = wp_create_nonce( 'fanfic_toggle_language_' . $language['id'] );
								?>
								<tr data-id="<?php echo esc_attr( $language['id'] ); ?>">
									<th scope="row" class="check-column">
										<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $language['id'] ); ?>">
											<?php echo esc_html( sprintf( __( 'Select %s', 'fanfiction-manager' ), $language['name'] ) ); ?>
										</label>
										<input id="cb-select-<?php echo esc_attr( $language['id'] ); ?>" type="checkbox" name="language_ids[]" value="<?php echo esc_attr( $language['id'] ); ?>">
									</th>
									<td class="column-name column-primary" data-colname="<?php esc_attr_e( 'Name', 'fanfiction-manager' ); ?>">
										<strong><?php echo esc_html( $language['name'] ); ?></strong>
										<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'fanfiction-manager' ); ?></span></button>
									</td>
									<td class="column-native" data-colname="<?php esc_attr_e( 'Native Name', 'fanfiction-manager' ); ?>">
										<?php echo esc_html( $language['native_name'] ?: '—' ); ?>
									</td>
									<td class="column-slug" data-colname="<?php esc_attr_e( 'Slug', 'fanfiction-manager' ); ?>">
										<code><?php echo esc_html( $language['slug'] ); ?></code>
									</td>
									<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'fanfiction-manager' ); ?>">
										<?php if ( $is_active ) : ?>
											<span class="fanfic-status-badge fanfic-status-active"><?php esc_html_e( 'Active', 'fanfiction-manager' ); ?></span>
										<?php else : ?>
											<span class="fanfic-status-badge fanfic-status-inactive"><?php esc_html_e( 'Inactive', 'fanfiction-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
										<button type="button" class="button button-small fanfic-edit-language-btn"
											data-id="<?php echo esc_attr( $language['id'] ); ?>"
											data-name="<?php echo esc_attr( $language['name'] ); ?>"
											data-native="<?php echo esc_attr( $language['native_name'] ); ?>"
											data-slug="<?php echo esc_attr( $language['slug'] ); ?>"
											data-active="<?php echo $is_active ? '1' : '0'; ?>"
											data-nonce="<?php echo esc_attr( $edit_nonce ); ?>">
											<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
										</button>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<input type="hidden" name="action" value="fanfic_toggle_language">
											<input type="hidden" name="language_id" value="<?php echo esc_attr( $language['id'] ); ?>">
											<input type="hidden" name="fanfic_toggle_language_nonce" value="<?php echo esc_attr( $toggle_nonce ); ?>">
											<button type="submit" class="button button-small">
												<?php echo $is_active ? esc_html__( 'Deactivate', 'fanfiction-manager' ) : esc_html__( 'Activate', 'fanfiction-manager' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select all', 'fanfiction-manager' ); ?></label>
								<input id="cb-select-all-2" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-native"><?php esc_html_e( 'Native Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
						</tr>
					</tfoot>
				</table>
			</form>

			<!-- Add Language Modal -->
			<div class="fanfic-admin-modal" id="fanfic-add-language-modal" aria-hidden="true">
				<div class="fanfic-admin-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Add Language', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form">
						<input type="hidden" name="action" value="fanfic_add_language">
						<?php wp_nonce_field( 'fanfic_add_language', 'fanfic_add_language_nonce' ); ?>

						<p>
							<label for="fanfic-add-language-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<input type="text" id="fanfic-add-language-name" name="language_name" class="regular-text" required>
						</p>

						<p>
							<label for="fanfic-add-language-native"><?php esc_html_e( 'Native Name', 'fanfiction-manager' ); ?></label>
							<input type="text" id="fanfic-add-language-native" name="language_native_name" class="regular-text">
							<span class="description"><?php esc_html_e( 'The name of the language in its own script.', 'fanfiction-manager' ); ?></span>
						</p>

						<p>
							<label for="fanfic-add-language-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></label>
							<input type="text" id="fanfic-add-language-slug" name="language_slug" class="regular-text" maxlength="10">
							<span class="description"><?php esc_html_e( 'URL-friendly identifier (e.g., "en" for English). Leave blank to auto-generate.', 'fanfiction-manager' ); ?></span>
						</p>

						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Language', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<!-- Edit Language Modal -->
			<div class="fanfic-admin-modal" id="fanfic-edit-language-modal" aria-hidden="true">
				<div class="fanfic-admin-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Edit Language', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-edit-language-form">
						<input type="hidden" name="action" value="fanfic_update_language">
						<input type="hidden" name="language_id" id="fanfic-edit-language-id" value="">
						<input type="hidden" name="fanfic_update_language_nonce" id="fanfic-edit-language-nonce" value="">

						<p>
							<label for="fanfic-edit-language-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
							<input type="text" id="fanfic-edit-language-name" name="language_name" class="regular-text" required>
						</p>

						<p>
							<label for="fanfic-edit-language-native"><?php esc_html_e( 'Native Name', 'fanfiction-manager' ); ?></label>
							<input type="text" id="fanfic-edit-language-native" name="language_native_name" class="regular-text">
						</p>

						<p>
							<label for="fanfic-edit-language-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></label>
							<input type="text" id="fanfic-edit-language-slug" name="language_slug" class="regular-text" maxlength="10">
						</p>

						<p>
							<label>
								<input type="checkbox" name="language_is_active" id="fanfic-edit-language-active" value="1">
								<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?>
							</label>
						</p>

						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button danger fanfic-delete-language-btn"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
							<button type="button" class="button fanfic-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-delete-language-form" style="display: none;">
						<input type="hidden" name="action" value="fanfic_delete_language">
						<input type="hidden" name="language_id" id="fanfic-delete-language-id" value="">
						<input type="hidden" name="fanfic_delete_language_nonce" id="fanfic-delete-language-nonce" value="">
					</form>
				</div>
			</div>

		</div>

		<script>
		jQuery(document).ready(function($) {
			// Add Language Modal
			$('#fanfic-add-language-btn').on('click', function() {
				$('#fanfic-add-language-modal').attr('aria-hidden', 'false');
			});

			// Edit Language Modal
			$('.fanfic-edit-language-btn').on('click', function() {
				var $btn = $(this);
				$('#fanfic-edit-language-id').val($btn.data('id'));
				$('#fanfic-edit-language-name').val($btn.data('name'));
				$('#fanfic-edit-language-native').val($btn.data('native'));
				$('#fanfic-edit-language-slug').val($btn.data('slug'));
				$('#fanfic-edit-language-active').prop('checked', $btn.data('active') === 1);
				$('#fanfic-edit-language-nonce').val($btn.data('nonce'));
				$('#fanfic-delete-language-id').val($btn.data('id'));
				$('#fanfic-delete-language-nonce').val(<?php echo wp_json_encode( wp_create_nonce( 'fanfic_delete_language_' ) ); ?> + $btn.data('id'));
				$('#fanfic-edit-language-modal').attr('aria-hidden', 'false');
			});

			// Delete Language
			$('.fanfic-delete-language-btn').on('click', function() {
				if (confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete this language? Stories using this language will lose their language assignment.', 'fanfiction-manager' ) ); ?>)) {
					$('#fanfic-delete-language-form').submit();
				}
			});

			// Close modals
			$('.fanfic-modal-cancel, .fanfic-admin-modal-overlay').on('click', function() {
				$(this).closest('.fanfic-admin-modal').attr('aria-hidden', 'true');
			});

			// Close on escape
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('.fanfic-admin-modal').attr('aria-hidden', 'true');
				}
			});

			// Bulk action confirmation
			$('#fanfic-languages-form').on('submit', function(e) {
				var action = $('select[name="bulk_action"]').val();
				if (action === 'delete') {
					if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete the selected languages?', 'fanfiction-manager' ) ); ?>)) {
						e.preventDefault();
					}
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle add language
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_add_language() {
		self::check_admin_nonce( 'fanfic_add_language', 'fanfic_add_language_nonce' );

		$name = isset( $_POST['language_name'] ) ? sanitize_text_field( wp_unslash( $_POST['language_name'] ) ) : '';
		$native_name = isset( $_POST['language_native_name'] ) ? sanitize_text_field( wp_unslash( $_POST['language_native_name'] ) ) : '';
		$slug = isset( $_POST['language_slug'] ) ? sanitize_title( wp_unslash( $_POST['language_slug'] ) ) : '';

		if ( '' === $name ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}

		// Ensure unique slug
		$slug = self::ensure_unique_slug( $slug );

		global $wpdb;
		$table = self::get_languages_table();
		$wpdb->insert(
			$table,
			array(
				'slug'        => $slug,
				'name'        => $name,
				'native_name' => $native_name ?: null,
				'is_active'   => 1,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		self::redirect_with_message( 'success', 'language_added' );
	}

	/**
	 * Handle update language
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_update_language() {
		$language_id = isset( $_POST['language_id'] ) ? absint( $_POST['language_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_update_language_' . $language_id, 'fanfic_update_language_nonce' );

		$name = isset( $_POST['language_name'] ) ? sanitize_text_field( wp_unslash( $_POST['language_name'] ) ) : '';
		$native_name = isset( $_POST['language_native_name'] ) ? sanitize_text_field( wp_unslash( $_POST['language_native_name'] ) ) : '';
		$slug = isset( $_POST['language_slug'] ) ? sanitize_title( wp_unslash( $_POST['language_slug'] ) ) : '';
		$is_active = isset( $_POST['language_is_active'] ) ? 1 : 0;

		if ( ! $language_id || '' === $name ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}

		$slug = self::ensure_unique_slug( $slug, $language_id );

		global $wpdb;
		$table = self::get_languages_table();
		$wpdb->update(
			$table,
			array(
				'name'        => $name,
				'native_name' => $native_name ?: null,
				'slug'        => $slug,
				'is_active'   => $is_active,
			),
			array( 'id' => $language_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'language_updated' );
	}

	/**
	 * Handle toggle language status
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_toggle_language() {
		$language_id = isset( $_POST['language_id'] ) ? absint( $_POST['language_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_toggle_language_' . $language_id, 'fanfic_toggle_language_nonce' );

		if ( ! $language_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_languages_table();
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $language_id )
		);
		$new_value = (int) ( (int) $current === 1 ? 0 : 1 );

		$wpdb->update(
			$table,
			array( 'is_active' => $new_value ),
			array( 'id' => $language_id ),
			array( '%d' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'language_updated' );
	}

	/**
	 * Handle delete language
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_delete_language() {
		$language_id = isset( $_POST['language_id'] ) ? absint( $_POST['language_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_delete_language_' . $language_id, 'fanfic_delete_language_nonce' );

		if ( ! $language_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;

		// Delete language
		$table = self::get_languages_table();
		$wpdb->delete( $table, array( 'id' => $language_id ), array( '%d' ) );

		// Delete story relations
		$relations_table = self::get_story_languages_table();
		$wpdb->delete( $relations_table, array( 'language_id' => $language_id ), array( '%d' ) );

		self::redirect_with_message( 'success', 'language_deleted' );
	}

	/**
	 * Handle bulk actions for languages
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_bulk_languages() {
		self::check_admin_nonce( 'fanfic_bulk_languages', 'fanfic_bulk_languages_nonce' );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids = isset( $_POST['language_ids'] ) ? array_map( 'absint', (array) $_POST['language_ids'] ) : array();

		if ( empty( $ids ) || '' === $action ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_languages_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$is_active = 'activate' === $action ? 1 : 0;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET is_active = %d WHERE id IN ({$placeholders})",
					array_merge( array( $is_active ), $ids )
				)
			);
			self::redirect_with_message( 'success', 'language_updated' );
		}

		if ( 'delete' === $action ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id IN ({$placeholders})",
					$ids
				)
			);

			// Delete story relations
			$relations_table = self::get_story_languages_table();
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$relations_table} WHERE language_id IN ({$placeholders})",
					$ids
				)
			);

			self::redirect_with_message( 'success', 'language_deleted' );
		}

		self::redirect_with_message( 'error', 'missing_fields' );
	}

	/**
	 * Handle import
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function handle_import() {
		self::check_admin_nonce( 'fanfic_import_languages', 'fanfic_import_languages_nonce' );

		$count = Fanfic_Languages::import_from_json();
		$code = $count > 0 ? 'language_imported' : 'language_imported_none';

		self::redirect_with_message( 'success', $code );
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private static function display_notices() {
		if ( isset( $_GET['success'] ) ) {
			$success = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			$messages = array(
				'language_added'         => __( 'Language added.', 'fanfiction-manager' ),
				'language_updated'       => __( 'Language updated.', 'fanfiction-manager' ),
				'language_deleted'       => __( 'Language deleted.', 'fanfiction-manager' ),
				'language_imported'      => __( 'Languages imported.', 'fanfiction-manager' ),
				'language_imported_none' => __( 'No languages imported (file missing or already imported).', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $success ] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $success ] ); ?></p>
				</div>
				<?php
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$messages = array(
				'missing_fields' => __( 'Required fields are missing.', 'fanfiction-manager' ),
			);

			if ( isset( $messages[ $error ] ) ) {
				?>
				<div class="notice error-message is-dismissible">
					<p><?php echo esc_html( $messages[ $error ] ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Get all languages
	 *
	 * @since 1.3.0
	 * @param string $search Search query.
	 * @param string $status Filter status (active/inactive).
	 * @return array
	 */
	private static function get_all_languages( $search = '', $status = '' ) {
		global $wpdb;
		$table = self::get_languages_table();

		$where = array( '1=1' );
		$args = array();

		if ( '' !== $search ) {
			$where[] = '(name LIKE %s OR native_name LIKE %s OR slug LIKE %s)';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		if ( 'active' === $status ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT id, slug, name, native_name, is_active
			FROM {$table}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY name ASC';

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Count languages
	 *
	 * @since 1.3.0
	 * @param string $search Search query.
	 * @param string $status Filter status (active/inactive).
	 * @return int
	 */
	private static function count_languages( $search = '', $status = '' ) {
		global $wpdb;
		$table = self::get_languages_table();

		$where = array( '1=1' );
		$args = array();

		if ( '' !== $search ) {
			$where[] = '(name LIKE %s OR native_name LIKE %s OR slug LIKE %s)';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		if ( 'active' === $status ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $args ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Check admin nonce and permissions
	 *
	 * @since 1.3.0
	 * @param string $action Nonce action.
	 * @param string $field Nonce field.
	 * @return void
	 */
	private static function check_admin_nonce( $action, $field ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}
	}

	/**
	 * Redirect with message
	 *
	 * @since 1.3.0
	 * @param string $type success|error.
	 * @param string $code Code.
	 * @return void
	 */
	private static function redirect_with_message( $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'fanfiction-taxonomies',
					'tab'   => 'languages',
					$type   => $code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Ensure unique slug
	 *
	 * @since 1.3.0
	 * @param string $slug Base slug.
	 * @param int    $exclude_id ID to exclude from check.
	 * @return string
	 */
	private static function ensure_unique_slug( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::get_languages_table();
		$base = $slug ? $slug : 'lang';
		$unique = $base;
		$counter = 2;

		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", $unique, $exclude_id ) ) > 0 ) {
			$unique = $base . '-' . $counter;
			$counter++;
		}

		return $unique;
	}

	/**
	 * Get languages table name
	 *
	 * @since 1.3.0
	 * @return string
	 */
	private static function get_languages_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_languages';
	}

	/**
	 * Get story_languages table name
	 *
	 * @since 1.3.0
	 * @return string
	 */
	private static function get_story_languages_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_story_languages';
	}
}
