<?php
/**
 * Fandoms Admin Interface
 *
 * Provides admin UI for managing the fandom catalogue.
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
 * Class Fanfic_Fandoms_Admin
 */
class Fanfic_Fandoms_Admin {

	/**
	 * Initialize admin hooks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		if ( ! Fanfic_Fandoms::is_enabled() ) {
			return;
		}

		add_action( 'admin_post_fanfic_add_fandom', array( __CLASS__, 'handle_add_fandom' ) );
		add_action( 'admin_post_fanfic_add_category', array( __CLASS__, 'handle_add_category' ) );
		add_action( 'admin_post_fanfic_bulk_add_fandoms', array( __CLASS__, 'handle_bulk_add_fandoms' ) );
		add_action( 'admin_post_fanfic_update_fandom', array( __CLASS__, 'handle_update_fandom' ) );
		add_action( 'admin_post_fanfic_toggle_fandom', array( __CLASS__, 'handle_toggle_fandom' ) );
		add_action( 'admin_post_fanfic_delete_fandom', array( __CLASS__, 'handle_delete_fandom' ) );
		add_action( 'admin_post_fanfic_bulk_fandoms', array( __CLASS__, 'handle_bulk_fandoms' ) );
		add_action( 'admin_post_fanfic_bulk_move_fandoms', array( __CLASS__, 'handle_bulk_move_fandoms' ) );
		add_action( 'admin_post_fanfic_bulk_categories', array( __CLASS__, 'handle_bulk_categories' ) );
		add_action( 'admin_post_fanfic_move_fandom', array( __CLASS__, 'handle_move_fandom' ) );
		add_action( 'admin_post_fanfic_category_action', array( __CLASS__, 'handle_category_action' ) );
		add_action( 'admin_post_fanfic_import_fandoms', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Render admin page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		self::display_notices();

		if ( ! Fanfic_Fandoms::tables_ready() ) {
			?>
			<div class="notice error-message">
				<p><?php esc_html_e( 'Fandom tables are missing. Please re-activate the plugin to create database tables.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$categories = self::get_categories();
		$fandoms = self::get_all_fandoms();
		$fandoms_by_category = self::group_fandoms_by_category( $fandoms );
		$search_results = '' !== $search ? self::search_fandoms( $search ) : array();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Manage your fandom catalogue with category cards, drag-and-drop, and bulk actions.', 'fanfiction-manager' ); ?></p>

			<div class="fanfic-fandoms-manager">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="fanfic-fandoms-search">
					<input type="hidden" name="page" value="fanfiction-fandoms">
					<label class="screen-reader-text" for="fanfic-fandom-search"><?php esc_html_e( 'Search fandoms', 'fanfiction-manager' ); ?></label>
					<input type="search" id="fanfic-fandom-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search fandoms...', 'fanfiction-manager' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'fanfiction-manager' ); ?></button>
				</form>

				<div class="fanfic-fandoms-actions">
					<button type="button" class="button button-primary fanfic-action-add-category"><?php esc_html_e( 'Add Category', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button fanfic-action-add-fandom"><?php esc_html_e( 'Add Fandom', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button fanfic-action-move" disabled><?php esc_html_e( 'Move Category', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button fanfic-action-activate" disabled><?php esc_html_e( 'Activate', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button fanfic-action-deactivate" disabled><?php esc_html_e( 'Deactivate', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button fanfic-action-rename" disabled><?php esc_html_e( 'Rename', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button danger fanfic-action-delete" disabled><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
				</div>

				<?php if ( '' !== $search ) : ?>
					<div class="fanfic-fandoms-search-results">
						<h2><?php esc_html_e( 'Search Results', 'fanfiction-manager' ); ?></h2>
						<div class="fanfic-category-tags fanfic-category-tags--results">
							<?php if ( empty( $search_results ) ) : ?>
								<p class="description"><?php esc_html_e( 'No fandoms match your search.', 'fanfiction-manager' ); ?></p>
							<?php else : ?>
								<?php foreach ( $search_results as $fandom ) : ?>
									<?php self::render_fandom_tag( $fandom ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="fanfic-fandoms-grid">
					<?php foreach ( $categories as $category_slug ) : ?>
						<?php
						$category_label = self::format_category_label( $category_slug );
						$category_fandoms = isset( $fandoms_by_category[ $category_slug ] ) ? $fandoms_by_category[ $category_slug ] : array();
						?>
						<section class="fanfic-category-card is-collapsed" data-category="<?php echo esc_attr( $category_slug ); ?>">
							<div class="fanfic-category-header">
								<input type="checkbox" class="fanfic-category-select" data-category="<?php echo esc_attr( $category_slug ); ?>" aria-label="<?php esc_attr_e( 'Select category', 'fanfiction-manager' ); ?>">
								<h2><?php echo esc_html( $category_label ); ?></h2>
								<span class="fanfic-category-count"><?php echo esc_html( count( $category_fandoms ) ); ?></span>
								<button type="button" class="button-link fanfic-category-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle category', 'fanfiction-manager' ); ?>">></button>
							</div>
							<div class="fanfic-category-tags" data-category="<?php echo esc_attr( $category_slug ); ?>">
								<?php if ( empty( $category_fandoms ) ) : ?>
									<p class="description"><?php esc_html_e( 'Drop fandoms here or use Add Fandom.', 'fanfiction-manager' ); ?></p>
								<?php else : ?>
									<?php foreach ( $category_fandoms as $fandom ) : ?>
										<?php self::render_fandom_tag( $fandom ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</section>
					<?php endforeach; ?>
				</div>
			</div>

			<form id="fanfic-fandom-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_bulk_fandoms">
				<?php wp_nonce_field( 'fanfic_bulk_fandoms', 'fanfic_bulk_fandoms_nonce' ); ?>
				<input type="hidden" name="bulk_action" value="">
				<input type="hidden" name="fandom_ids" value="">
			</form>

			<form id="fanfic-category-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_bulk_categories">
				<?php wp_nonce_field( 'fanfic_bulk_categories', 'fanfic_bulk_categories_nonce' ); ?>
				<input type="hidden" name="category_action" value="">
				<input type="hidden" name="category_slugs" value="">
				<input type="hidden" name="category_name" value="">
			</form>

			<form id="fanfic-fandom-move-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_move_fandom">
				<?php wp_nonce_field( 'fanfic_move_fandom', 'fanfic_move_fandom_nonce' ); ?>
				<input type="hidden" name="fandom_id" value="">
				<input type="hidden" name="new_category" value="">
			</form>

			<div class="fanfic-admin-modal fanfic-fandoms-modal" id="fanfic-bulk-move-modal" aria-hidden="true">
				<div class="fanfic-fandoms-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Move to Category', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-bulk-move-form">
						<input type="hidden" name="action" value="fanfic_bulk_move_fandoms">
						<?php wp_nonce_field( 'fanfic_bulk_move_fandoms', 'fanfic_bulk_move_fandoms_nonce' ); ?>
						<input type="hidden" name="fandom_ids" value="">
						<label for="fanfic-bulk-move-category"><?php esc_html_e( 'Category', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<select id="fanfic-bulk-move-category" name="new_category" required>
							<option value=""><?php esc_html_e( 'Select a category', 'fanfiction-manager' ); ?></option>
							<?php foreach ( $categories as $category_slug ) : ?>
								<option value="<?php echo esc_attr( $category_slug ); ?>"><?php echo esc_html( self::format_category_label( $category_slug ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-fandoms-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Move', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="fanfic-admin-modal fanfic-fandoms-modal" id="fanfic-category-rename-modal" aria-hidden="true">
				<div class="fanfic-fandoms-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Rename Category', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-category-rename-form">
						<input type="hidden" name="action" value="fanfic_bulk_categories">
						<?php wp_nonce_field( 'fanfic_bulk_categories', 'fanfic_bulk_categories_nonce' ); ?>
						<input type="hidden" name="category_action" value="rename">
						<input type="hidden" name="category_slugs" value="">
						<label for="fanfic-category-rename-input"><?php esc_html_e( 'New Category Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="fanfic-category-rename-input" name="category_name" class="regular-text" required>
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-fandoms-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Rename', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="fanfic-admin-modal fanfic-fandoms-modal" id="fanfic-add-category-modal" aria-hidden="true">
				<div class="fanfic-fandoms-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Add Category', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form">
						<input type="hidden" name="action" value="fanfic_add_category">
						<?php wp_nonce_field( 'fanfic_add_category', 'fanfic_add_category_nonce' ); ?>
						<label for="fanfic-category-name"><?php esc_html_e( 'Category Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="fanfic-category-name" name="category_name" class="regular-text" required>
						<label for="fanfic-category-fandoms"><?php esc_html_e( 'Add Fandoms (comma-separated)', 'fanfiction-manager' ); ?></label>
						<textarea id="fanfic-category-fandoms" name="fandom_names" rows="4"></textarea>
						<p class="description"><?php esc_html_e( 'Optional: add fandom names separated by commas.', 'fanfiction-manager' ); ?></p>
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-fandoms-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="fanfic-admin-modal fanfic-fandoms-modal" id="fanfic-add-fandom-modal" aria-hidden="true">
				<div class="fanfic-fandoms-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2><?php esc_html_e( 'Add Fandoms', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form">
						<input type="hidden" name="action" value="fanfic_bulk_add_fandoms">
						<?php wp_nonce_field( 'fanfic_bulk_add_fandoms', 'fanfic_bulk_add_fandoms_nonce' ); ?>
						<label for="fanfic-fandom-names"><?php esc_html_e( 'Fandom Names (comma-separated)', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<textarea id="fanfic-fandom-names" name="fandom_names" rows="4" required></textarea>
						<label for="fanfic-fandom-category"><?php esc_html_e( 'Category', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<select id="fanfic-fandom-category" name="fandom_category" required>
							<option value=""><?php esc_html_e( 'Select a category', 'fanfiction-manager' ); ?></option>
							<?php foreach ( $categories as $category_slug ) : ?>
								<option value="<?php echo esc_attr( $category_slug ); ?>"><?php echo esc_html( self::format_category_label( $category_slug ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-fandoms-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="fanfic-admin-modal fanfic-fandoms-modal" id="fanfic-fandom-edit-modal" aria-hidden="true">
				<div class="fanfic-fandoms-modal-overlay"></div>
				<div class="fanfic-admin-modal-content">
					<h2 class="fanfic-fandom-edit-title"><?php esc_html_e( 'Edit Fandom', 'fanfiction-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-fandom-edit-form">
						<input type="hidden" name="action" value="fanfic_update_fandom">
						<input type="hidden" name="fandom_id" value="">
						<input type="hidden" name="fanfic_update_fandom_nonce" value="">
						<div class="fanfic-fandom-edit-grid">
							<div class="fanfic-fandom-edit-left">
								<label for="fanfic-edit-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
								<input type="text" id="fanfic-edit-name" name="fandom_name" class="regular-text" required>
								<label for="fanfic-edit-slug"><?php esc_html_e( 'Slug', 'fanfiction-manager' ); ?></label>
								<input type="text" id="fanfic-edit-slug" name="fandom_slug" class="regular-text">
							</div>
							<div class="fanfic-fandom-edit-right">
								<label for="fanfic-edit-category"><?php esc_html_e( 'Category', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
								<select id="fanfic-edit-category" name="fandom_category" required>
									<?php foreach ( $categories as $category_slug ) : ?>
										<option value="<?php echo esc_attr( $category_slug ); ?>"><?php echo esc_html( self::format_category_label( $category_slug ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="fanfic-fandom-edit-footer">
							<label class="fanfic-fandom-edit-active">
								<input type="checkbox" name="fandom_is_active" value="1">
								<?php esc_html_e( 'Active', 'fanfiction-manager' ); ?>
							</label>
							<button type="submit" form="fanfic-fandom-delete-form" class="button danger"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
						</div>
						<div class="fanfic-admin-modal-actions">
							<button type="button" class="button fanfic-fandoms-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-fandom-delete-form">
						<input type="hidden" name="action" value="fanfic_delete_fandom">
						<input type="hidden" name="fandom_id" value="">
						<input type="hidden" name="fanfic_delete_fandom_nonce" value="">
					</form>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Render a fandom tag
	 *
	 * @since 1.0.0
	 * @param array $fandom Fandom data.
	 * @return void
	 */
	private static function render_fandom_tag( $fandom ) {
		$is_active = ! empty( $fandom['is_active'] );
		$classes = array( 'fanfic-fandom-tag' );
		if ( ! $is_active ) {
			$classes[] = 'fanfic-fandom-tag--inactive';
		}
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-id="<?php echo esc_attr( $fandom['id'] ); ?>"
			data-name="<?php echo esc_attr( $fandom['name'] ); ?>"
			data-slug="<?php echo esc_attr( $fandom['slug'] ); ?>"
			data-category="<?php echo esc_attr( $fandom['category'] ); ?>"
			data-active="<?php echo $is_active ? '1' : '0'; ?>"
			data-update-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_update_fandom_' . $fandom['id'] ) ); ?>"
			data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_fandom_' . $fandom['id'] ) ); ?>"
			role="button"
			tabindex="0"
		>
			<span class="fanfic-fandom-label"><?php echo esc_html( $fandom['name'] ); ?></span>
		</div>
		<?php
	}

	/**
	 * Handle add fandom
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_add_fandom() {
		self::check_admin_nonce( 'fanfic_add_fandom', 'fanfic_add_fandom_nonce' );

		$name = isset( $_POST['fandom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fandom_name'] ) ) : '';
		$category = isset( $_POST['fandom_category'] ) ? sanitize_title( wp_unslash( $_POST['fandom_category'] ) ) : '';

		if ( '' === $name || '' === $category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $category );

		$slug = sanitize_title( $name );
		$slug = self::ensure_unique_slug( $slug );

		global $wpdb;
		$table = self::get_fandoms_table();
		$wpdb->insert(
			$table,
			array(
				'slug'      => $slug,
				'name'      => $name,
				'category'  => $category,
				'is_active' => 1,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		self::redirect_with_message( 'success', 'fandom_added' );
	}

	/**
	 * Handle add category
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_add_category() {
		self::check_admin_nonce( 'fanfic_add_category', 'fanfic_add_category_nonce' );

		$name = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$fandom_names = isset( $_POST['fandom_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fandom_names'] ) ) : '';
		$category = sanitize_title( $name );

		if ( '' === $category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $category );

		$names = self::split_names( $fandom_names );
		if ( ! empty( $names ) ) {
			foreach ( $names as $fandom_name ) {
				self::insert_fandom( $fandom_name, $category );
			}
		}

		self::redirect_with_message( 'success', 'category_added' );
	}

	/**
	 * Handle bulk add fandoms
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_bulk_add_fandoms() {
		self::check_admin_nonce( 'fanfic_bulk_add_fandoms', 'fanfic_bulk_add_fandoms_nonce' );

		$category = isset( $_POST['fandom_category'] ) ? sanitize_title( wp_unslash( $_POST['fandom_category'] ) ) : '';
		$fandom_names = isset( $_POST['fandom_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fandom_names'] ) ) : '';

		if ( '' === $category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		$names = self::split_names( $fandom_names );
		if ( empty( $names ) ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $category );

		foreach ( $names as $fandom_name ) {
			self::insert_fandom( $fandom_name, $category );
		}

		self::redirect_with_message( 'success', 'fandom_added' );
	}

	/**
	 * Handle update fandom
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_update_fandom() {
		$fandom_id = isset( $_POST['fandom_id'] ) ? absint( $_POST['fandom_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_update_fandom_' . $fandom_id, 'fanfic_update_fandom_nonce' );

		$name = isset( $_POST['fandom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fandom_name'] ) ) : '';
		$slug = isset( $_POST['fandom_slug'] ) ? sanitize_title( wp_unslash( $_POST['fandom_slug'] ) ) : '';
		$category = isset( $_POST['fandom_category'] ) ? sanitize_title( wp_unslash( $_POST['fandom_category'] ) ) : '';
		$is_active = isset( $_POST['fandom_is_active'] ) ? 1 : 0;

		if ( ! $fandom_id || '' === $name || '' === $category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $category );
		$slug = '' !== $slug ? $slug : sanitize_title( $name );
		$slug = self::ensure_unique_slug( $slug, $fandom_id );

		global $wpdb;
		$table = self::get_fandoms_table();
		$wpdb->update(
			$table,
			array(
				'name'     => $name,
				'slug'     => $slug,
				'category' => $category,
				'is_active' => $is_active,
			),
			array( 'id' => $fandom_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'fandom_updated' );
	}

	/**
	 * Handle toggle fandom status
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_toggle_fandom() {
		$fandom_id = isset( $_POST['fandom_id'] ) ? absint( $_POST['fandom_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_toggle_fandom_' . $fandom_id, 'fanfic_toggle_fandom_nonce' );

		if ( ! $fandom_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $fandom_id )
		);
		$new_value = (int) ( (int) $current === 1 ? 0 : 1 );

		$wpdb->update(
			$table,
			array( 'is_active' => $new_value ),
			array( 'id' => $fandom_id ),
			array( '%d' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'fandom_updated' );
	}

	/**
	 * Handle delete fandom
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_delete_fandom() {
		$fandom_id = isset( $_POST['fandom_id'] ) ? absint( $_POST['fandom_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_delete_fandom_' . $fandom_id, 'fanfic_delete_fandom_nonce' );

		if ( ! $fandom_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$wpdb->delete( $table, array( 'id' => $fandom_id ), array( '%d' ) );

		self::redirect_with_message( 'success', 'fandom_deleted' );
	}

	/**
	 * Handle bulk actions for fandoms
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_bulk_fandoms() {
		self::check_admin_nonce( 'fanfic_bulk_fandoms', 'fanfic_bulk_fandoms_nonce' );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids_raw = isset( $_POST['fandom_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['fandom_ids'] ) ) : '';
		$ids = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );

		if ( empty( $ids ) || '' === $action ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$is_active = 'activate' === $action ? 1 : 0;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET is_active = %d WHERE id IN ({$placeholders})",
					array_merge( array( $is_active ), $ids )
				)
			);
			self::redirect_with_message( 'success', 'fandom_updated' );
		}

		if ( 'delete' === $action ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id IN ({$placeholders})",
					$ids
				)
			);
			self::redirect_with_message( 'success', 'fandom_deleted' );
		}

		self::redirect_with_message( 'error', 'missing_fields' );
	}

	/**
	 * Handle bulk move for fandoms
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_bulk_move_fandoms() {
		self::check_admin_nonce( 'fanfic_bulk_move_fandoms', 'fanfic_bulk_move_fandoms_nonce' );

		$ids_raw = isset( $_POST['fandom_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['fandom_ids'] ) ) : '';
		$new_category = isset( $_POST['new_category'] ) ? sanitize_title( wp_unslash( $_POST['new_category'] ) ) : '';
		$ids = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );

		if ( empty( $ids ) || '' === $new_category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $new_category );

		global $wpdb;
		$table = self::get_fandoms_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET category = %s WHERE id IN ({$placeholders})",
				array_merge( array( $new_category ), $ids )
			)
		);

		self::redirect_with_message( 'success', 'fandom_updated' );
	}

	/**
	 * Handle bulk category actions
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_bulk_categories() {
		self::check_admin_nonce( 'fanfic_bulk_categories', 'fanfic_bulk_categories_nonce' );

		$action = isset( $_POST['category_action'] ) ? sanitize_text_field( wp_unslash( $_POST['category_action'] ) ) : '';
		$slugs_raw = isset( $_POST['category_slugs'] ) ? sanitize_text_field( wp_unslash( $_POST['category_slugs'] ) ) : '';
		$new_name = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$slugs = array_filter( array_map( 'sanitize_title', explode( ',', $slugs_raw ) ) );

		if ( empty( $slugs ) || '' === $action ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$is_active = 'activate' === $action ? 1 : 0;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET is_active = %d WHERE category IN ({$placeholders})",
					array_merge( array( $is_active ), $slugs )
				)
			);
			self::redirect_with_message( 'success', 'category_updated' );
		}

		if ( 'delete' === $action ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE category IN ({$placeholders})",
					$slugs
				)
			);
			foreach ( $slugs as $slug ) {
				self::remove_category( $slug );
			}
			self::redirect_with_message( 'success', 'category_deleted' );
		}

		if ( 'rename' === $action ) {
			if ( count( $slugs ) !== 1 ) {
				self::redirect_with_message( 'error', 'missing_fields' );
			}
			$new_category = sanitize_title( $new_name );
			if ( '' === $new_category ) {
				self::redirect_with_message( 'error', 'missing_fields' );
			}
			$old = $slugs[0];
			$wpdb->update(
				$table,
				array( 'category' => $new_category ),
				array( 'category' => $old ),
				array( '%s' ),
				array( '%s' )
			);
			self::rename_category( $old, $new_category );
			self::redirect_with_message( 'success', 'category_updated' );
		}

		self::redirect_with_message( 'error', 'missing_fields' );
	}

	/**
	 * Handle move fandom between categories
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_move_fandom() {
		self::check_admin_nonce( 'fanfic_move_fandom', 'fanfic_move_fandom_nonce' );

		$fandom_id = isset( $_POST['fandom_id'] ) ? absint( wp_unslash( $_POST['fandom_id'] ) ) : 0;
		$new_category = isset( $_POST['new_category'] ) ? sanitize_title( wp_unslash( $_POST['new_category'] ) ) : '';

		if ( ! $fandom_id || '' === $new_category ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		self::store_category( $new_category );

		global $wpdb;
		$table = self::get_fandoms_table();
		$wpdb->update(
			$table,
			array( 'category' => $new_category ),
			array( 'id' => $fandom_id ),
			array( '%s' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'fandom_updated' );
	}

	/**
	 * Handle category actions
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_category_action() {
		self::check_admin_nonce( 'fanfic_category_action', 'fanfic_category_action_nonce' );

		$category = isset( $_POST['category_slug'] ) ? sanitize_title( wp_unslash( $_POST['category_slug'] ) ) : '';
		$action = isset( $_POST['category_action'] ) ? sanitize_text_field( wp_unslash( $_POST['category_action'] ) ) : '';
		$new_name = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';

		if ( '' === $category || '' === $action ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = self::get_fandoms_table();

		if ( 'rename' === $action ) {
			$new_category = sanitize_title( $new_name );
			if ( '' === $new_category ) {
				self::redirect_with_message( 'error', 'missing_fields' );
			}

			$wpdb->update(
				$table,
				array( 'category' => $new_category ),
				array( 'category' => $category ),
				array( '%s' ),
				array( '%s' )
			);

			self::rename_category( $category, $new_category );
			self::redirect_with_message( 'success', 'category_updated' );
		}

		if ( 'activate' === $action || 'deactivate' === $action ) {
			$is_active = 'activate' === $action ? 1 : 0;
			$wpdb->update(
				$table,
				array( 'is_active' => $is_active ),
				array( 'category' => $category ),
				array( '%d' ),
				array( '%s' )
			);
			self::redirect_with_message( 'success', 'category_updated' );
		}

		if ( 'delete' === $action ) {
			$wpdb->delete( $table, array( 'category' => $category ), array( '%s' ) );
			self::remove_category( $category );
			self::redirect_with_message( 'success', 'category_deleted' );
		}

		self::redirect_with_message( 'error', 'missing_fields' );
	}

	/**
	 * Handle import
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_import() {
		self::check_admin_nonce( 'fanfic_import_fandoms', 'fanfic_import_fandoms_nonce' );

		$count = 0;
		if ( ! Fanfic_Fandoms::has_fandoms() ) {
			$count = Fanfic_Fandoms::import_from_json();
		}
		$code = $count > 0 ? 'fandom_imported' : 'fandom_imported_none';

		self::redirect_with_message( 'success', $code );
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function display_notices() {
		if ( isset( $_GET['success'] ) ) {
			$success = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			$messages = array(
				'category_added'       => __( 'Category added.', 'fanfiction-manager' ),
				'category_updated'     => __( 'Category updated.', 'fanfiction-manager' ),
				'category_deleted'     => __( 'Category deleted.', 'fanfiction-manager' ),
				'fandom_added'         => __( 'Fandom added.', 'fanfiction-manager' ),
				'fandom_updated'       => __( 'Fandom updated.', 'fanfiction-manager' ),
				'fandom_deleted'       => __( 'Fandom deleted.', 'fanfiction-manager' ),
				'fandom_imported'      => __( 'Fandoms imported.', 'fanfiction-manager' ),
				'fandom_imported_none' => __( 'No fandoms imported (file missing or already imported).', 'fanfiction-manager' ),
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
	 * Get distinct categories
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	private static function get_categories() {
		$db_categories = self::get_category_slugs_from_db();
		$stored = self::get_stored_categories();
		$merged = array_unique( array_merge( $db_categories, $stored ) );
		sort( $merged, SORT_STRING );
		return $merged;
	}

	/**
	 * Get category slugs from database
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	private static function get_category_slugs_from_db() {
		global $wpdb;
		$table = self::get_fandoms_table();
		$rows = $wpdb->get_col( "SELECT DISTINCT category FROM {$table} ORDER BY category ASC" );
		return array_filter( array_map( 'sanitize_title', $rows ) );
	}

	/**
	 * Get stored category list
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	private static function get_stored_categories() {
		$stored = get_option( 'fanfic_fandom_categories', array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return array_filter( array_map( 'sanitize_title', $stored ) );
	}

	/**
	 * Store category slug
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @return void
	 */
	private static function store_category( $category ) {
		if ( '' === $category ) {
			return;
		}
		$stored = self::get_stored_categories();
		if ( in_array( $category, $stored, true ) ) {
			return;
		}
		$stored[] = $category;
		update_option( 'fanfic_fandom_categories', array_values( array_unique( $stored ) ) );
	}

	/**
	 * Remove category slug
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @return void
	 */
	private static function remove_category( $category ) {
		$stored = self::get_stored_categories();
		$stored = array_values( array_filter( $stored, function( $item ) use ( $category ) {
			return $item !== $category;
		} ) );
		update_option( 'fanfic_fandom_categories', $stored );
	}

	/**
	 * Rename category slug
	 *
	 * @since 1.0.0
	 * @param string $old Old slug.
	 * @param string $new New slug.
	 * @return void
	 */
	private static function rename_category( $old, $new ) {
		if ( $old === $new ) {
			self::store_category( $new );
			return;
		}
		$stored = self::get_stored_categories();
		$stored = array_values( array_filter( $stored, function( $item ) use ( $old ) {
			return $item !== $old;
		} ) );
		$stored[] = $new;
		update_option( 'fanfic_fandom_categories', array_values( array_unique( $stored ) ) );
	}

	/**
	 * Fetch all fandoms
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private static function get_all_fandoms() {
		global $wpdb;
		$table = self::get_fandoms_table();
		$sql = "SELECT id, name, slug, category, is_active
			FROM {$table}
			ORDER BY category ASC, name ASC";
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Search fandoms
	 *
	 * @since 1.0.0
	 * @param string $search Search query.
	 * @return array
	 */
	private static function search_fandoms( $search ) {
		global $wpdb;
		$table = self::get_fandoms_table();
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$sql = "SELECT id, name, slug, category, is_active
			FROM {$table}
			WHERE name LIKE %s
			ORDER BY name ASC
			LIMIT 200";
		return $wpdb->get_results( $wpdb->prepare( $sql, $like ), ARRAY_A );
	}

	/**
	 * Group fandoms by category
	 *
	 * @since 1.0.0
	 * @param array $fandoms Fandom list.
	 * @return array
	 */
	private static function group_fandoms_by_category( $fandoms ) {
		$grouped = array();
		foreach ( $fandoms as $fandom ) {
			$category = $fandom['category'];
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][] = $fandom;
		}
		return $grouped;
	}

	/**
	 * Insert fandom record
	 *
	 * @since 1.0.0
	 * @param string $name Fandom name.
	 * @param string $category Category slug.
	 * @return void
	 */
	private static function insert_fandom( $name, $category ) {
		$clean_name = sanitize_text_field( $name );
		if ( '' === $clean_name || '' === $category ) {
			return;
		}
		$slug = sanitize_title( $clean_name );
		$slug = self::ensure_unique_slug( $slug );

		global $wpdb;
		$table = self::get_fandoms_table();
		$wpdb->insert(
			$table,
			array(
				'slug'      => $slug,
				'name'      => $clean_name,
				'category'  => $category,
				'is_active' => 1,
			),
			array( '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Split comma-separated names
	 *
	 * @since 1.0.0
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
	 * Fetch fandoms
	 *
	 * @since 1.0.0
	 * @param string $search Search query.
	 * @param string $category Category slug.
	 * @param int    $per_page Items per page.
	 * @param int    $paged Current page.
	 * @return array
	 */
	private static function get_fandoms( $search, $category, $per_page, $paged ) {
		global $wpdb;
		$table = self::get_fandoms_table();
		$where = array( '1=1' );
		$args = array();

		if ( '' !== $category ) {
			$where[] = 'category = %s';
			$args[] = $category;
		}

		if ( '' !== $search ) {
			$where[] = 'name LIKE %s';
			$args[] = $wpdb->esc_like( $search ) . '%';
		}

		$offset = ( $paged - 1 ) * $per_page;
		$args[] = $per_page;
		$args[] = $offset;

		$sql = "SELECT id, name, slug, category, is_active
			FROM {$table}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY name ASC
			LIMIT %d OFFSET %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/**
	 * Count fandoms
	 *
	 * @since 1.0.0
	 * @param string $search Search query.
	 * @param string $category Category slug.
	 * @return int
	 */
	private static function count_fandoms( $search, $category ) {
		global $wpdb;
		$table = self::get_fandoms_table();
		$where = array( '1=1' );
		$args = array();

		if ( '' !== $category ) {
			$where[] = 'category = %s';
			$args[] = $category;
		}

		if ( '' !== $search ) {
			$where[] = 'name LIKE %s';
			$args[] = $wpdb->esc_like( $search ) . '%';
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Check admin nonce and permissions
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @param string $type success|error.
	 * @param string $code Code.
	 * @return void
	 */
	private static function redirect_with_message( $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-fandoms',
					$type     => $code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Ensure unique slug
	 *
	 * @since 1.0.0
	 * @param string $slug Base slug.
	 * @return string
	 */
	private static function ensure_unique_slug( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::get_fandoms_table();
		$base = $slug ? $slug : 'fandom';
		$unique = $base;
		$counter = 2;

		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", $unique, $exclude_id ) ) > 0 ) {
			$unique = $base . '-' . $counter;
			$counter++;
		}

		return $unique;
	}

	/**
	 * Format category label
	 *
	 * @since 1.0.0
	 * @param string $slug Category slug.
	 * @return string
	 */
	private static function format_category_label( $slug ) {
		$label = str_replace( array( '-', '_' ), ' ', $slug );
		return ucwords( $label );
	}

	/**
	 * Get fandoms table name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_fandoms_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_fandoms';
	}
}
