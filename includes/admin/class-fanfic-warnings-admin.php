<?php
/**
 * Warnings Admin Interface
 *
 * Provides admin UI for managing content warnings and age classifications.
 * Mirrors the fandoms admin pattern for consistency.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Warnings_Admin
 *
 * @since 1.2.0
 */
class Fanfic_Warnings_Admin {

	/**
	 * Initialize admin hooks
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			return;
		}

		add_action( 'admin_post_fanfic_add_warning', array( __CLASS__, 'handle_add_warning' ) );
		add_action( 'admin_post_fanfic_update_warning', array( __CLASS__, 'handle_update_warning' ) );
		add_action( 'admin_post_fanfic_toggle_warning', array( __CLASS__, 'handle_toggle_warning' ) );
		add_action( 'admin_post_fanfic_delete_warning', array( __CLASS__, 'handle_delete_warning' ) );
		add_action( 'admin_post_fanfic_bulk_warnings', array( __CLASS__, 'handle_bulk_warnings' ) );
	}

	/**
	 * Render admin page
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Warnings are disabled. Enable them in the General tab to manage content warnings.', 'fanfiction-manager' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-taxonomies&tab=general' ) ); ?>"><?php esc_html_e( 'Go to General tab', 'fanfiction-manager' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		if ( ! self::tables_ready() && class_exists( 'Fanfic_Database_Setup' ) ) {
			Fanfic_Database_Setup::init();
		}

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			Fanfic_Warnings::maybe_seed_warnings();
		}

		if ( ! self::tables_ready() ) {
			?>
			<div class="notice error-message">
				<p><?php esc_html_e( 'Warnings tables are unavailable. Please check plugin/database setup.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_age = isset( $_GET['age_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['age_filter'] ) ) : '';
		$filter_enabled = isset( $_GET['enabled_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['enabled_filter'] ) ) : '';
		if ( '' !== $filter_age && class_exists( 'Fanfic_Warnings' ) ) {
			$filter_age = Fanfic_Warnings::normalize_age_label( $filter_age );
		}

		$warnings = self::get_all_warnings( $search, $filter_age, $filter_enabled );
		$age_counts = self::get_age_counts();
		$age_options = self::get_age_filter_options( $age_counts );
		$warnings_class_ready = class_exists( 'Fanfic_Warnings' );

		// Get content restriction settings
		$allow_sexual = Fanfic_Settings::get_setting( 'allow_sexual_content', true );
		$allow_pornographic = Fanfic_Settings::get_setting( 'allow_pornographic_content', false );

		?>
		<div class="fanfic-warnings-admin">
			<h2><?php esc_html_e( 'Content Warnings', 'fanfiction-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage content warnings that authors can apply to their stories. Warnings affect the derived age rating of stories.', 'fanfiction-manager' ); ?></p>

			<!-- Content Restriction Notice -->
			<?php if ( ! $allow_sexual || ! $allow_pornographic ) : ?>
				<div class="notice notice-info inline" style="margin: 15px 0;">
					<p>
						<strong><?php esc_html_e( 'Content Restrictions Active:', 'fanfiction-manager' ); ?></strong>
						<?php if ( ! $allow_sexual ) : ?>
							<?php esc_html_e( 'Sexual content warnings are hidden from authors.', 'fanfiction-manager' ); ?>
						<?php endif; ?>
						<?php if ( ! $allow_pornographic ) : ?>
							<?php esc_html_e( 'Pornographic content warnings are hidden from authors.', 'fanfiction-manager' ); ?>
						<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-settings&tab=general' ) ); ?>">
							<?php esc_html_e( 'Change in Settings', 'fanfiction-manager' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<!-- Filters and Search -->
			<div class="fanfic-warnings-filters" style="margin: 20px 0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
					<input type="hidden" name="page" value="fanfiction-taxonomies">
					<input type="hidden" name="tab" value="warnings">

					<label class="screen-reader-text" for="fanfic-warning-search"><?php esc_html_e( 'Search warnings', 'fanfiction-manager' ); ?></label>
					<input type="search" id="fanfic-warning-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search warnings...', 'fanfiction-manager' ); ?>" class="regular-text">

					<select name="age_filter">
						<option value=""><?php esc_html_e( 'All Ages', 'fanfiction-manager' ); ?></option>
						<?php foreach ( $age_options as $age ) : ?>
							<?php
							$age_label = $warnings_class_ready ? Fanfic_Warnings::format_age_label_for_display( $age, false ) : $age;
							if ( '' === $age_label ) {
								$age_label = $age;
							}
							?>
							<option value="<?php echo esc_attr( $age ); ?>" <?php selected( $filter_age, $age ); ?>>
								<?php echo esc_html( $age_label ); ?>
								(<?php echo esc_html( isset( $age_counts[ $age ] ) ? $age_counts[ $age ] : 0 ); ?>)
							</option>
						<?php endforeach; ?>
					</select>

					<select name="enabled_filter">
						<option value=""><?php esc_html_e( 'All Status', 'fanfiction-manager' ); ?></option>
						<option value="1" <?php selected( $filter_enabled, '1' ); ?>><?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?></option>
						<option value="0" <?php selected( $filter_enabled, '0' ); ?>><?php esc_html_e( 'Disabled', 'fanfiction-manager' ); ?></option>
					</select>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'fanfiction-manager' ); ?></button>
					<?php if ( $search || $filter_age || $filter_enabled ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-taxonomies&tab=warnings' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'fanfiction-manager' ); ?></a>
					<?php endif; ?>
				</form>

				<button type="button" class="button button-primary fanfic-action-add-warning" onclick="document.getElementById('fanfic-add-warning-modal').setAttribute('aria-hidden', 'false');">
					<?php esc_html_e( 'Add Warning', 'fanfiction-manager' ); ?>
				</button>
			</div>

			<!-- Warnings Table -->
			<form id="fanfic-warnings-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_bulk_warnings">
				<?php wp_nonce_field( 'fanfic_bulk_warnings', 'fanfic_bulk_warnings_nonce' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="bulk-action-selector">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'fanfiction-manager' ); ?></option>
							<option value="enable"><?php esc_html_e( 'Enable', 'fanfiction-manager' ); ?></option>
							<option value="disable"><?php esc_html_e( 'Disable', 'fanfiction-manager' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></option>
						</select>
						<button type="submit" class="button action" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to perform this action on the selected warnings?', 'fanfiction-manager' ) ); ?>');">
							<?php esc_html_e( 'Apply', 'fanfiction-manager' ); ?>
						</button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all">
							</td>
							<th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-age"><?php esc_html_e( 'Min Age', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-flags"><?php esc_html_e( 'Flags', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
							<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $warnings ) ) : ?>
							<tr>
								<td colspan="6"><?php esc_html_e( 'No warnings found.', 'fanfiction-manager' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $warnings as $warning ) : ?>
								<?php
								$is_enabled = ! empty( $warning['enabled'] );
								$is_sexual = ! empty( $warning['is_sexual'] );
								$is_pornographic = ! empty( $warning['is_pornographic'] );
								$row_class = $is_enabled ? '' : 'fanfic-warning-disabled';
								$age_badge_class = $warnings_class_ready ? Fanfic_Warnings::get_age_badge_class( $warning['min_age'] ?? '' ) : 'fanfic-age-badge-18-plus';
								$age_badge_label = $warnings_class_ready ? Fanfic_Warnings::format_age_label_for_display( $warning['min_age'] ?? '', false ) : (string) ( $warning['min_age'] ?? '' );
								if ( '' === $age_badge_label ) {
									$age_badge_label = (string) ( $warning['min_age'] ?? '' );
								}

								// Check if warning is restricted
								$is_restricted = ( $is_sexual && ! $allow_sexual ) || ( $is_pornographic && ! $allow_pornographic );
								?>
								<tr class="<?php echo esc_attr( $row_class ); ?>">
									<th scope="row" class="check-column">
										<input type="checkbox" name="warning_ids[]" value="<?php echo esc_attr( $warning['id'] ); ?>">
									</th>
									<td class="column-name">
										<strong>
											<a href="#" class="fanfic-edit-warning-link"
												data-id="<?php echo esc_attr( $warning['id'] ); ?>"
												data-name="<?php echo esc_attr( $warning['name'] ); ?>"
												data-min-age="<?php echo esc_attr( $warning['min_age'] ); ?>"
												data-description="<?php echo esc_attr( $warning['description'] ); ?>"
												data-is-sexual="<?php echo esc_attr( $is_sexual ? '1' : '0' ); ?>"
												data-is-pornographic="<?php echo esc_attr( $is_pornographic ? '1' : '0' ); ?>"
												data-enabled="<?php echo esc_attr( $is_enabled ? '1' : '0' ); ?>"
												data-update-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_update_warning_' . $warning['id'] ) ); ?>"
												data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_warning_' . $warning['id'] ) ); ?>">
												<?php echo esc_html( $warning['name'] ); ?>
											</a>
										</strong>
										<?php if ( ! empty( $warning['description'] ) ) : ?>
											<p class="description" style="margin: 5px 0 0;"><?php echo esc_html( $warning['description'] ); ?></p>
										<?php endif; ?>
									</td>
									<td class="column-age">
										<span class="fanfic-age-badge <?php echo esc_attr( $age_badge_class ); ?>">
											<?php echo esc_html( $age_badge_label ); ?>
										</span>
									</td>
									<td class="column-flags">
										<?php if ( $is_sexual ) : ?>
											<span class="fanfic-flag fanfic-flag-sexual" title="<?php esc_attr_e( 'Sexual Content', 'fanfiction-manager' ); ?>">S</span>
										<?php endif; ?>
										<?php if ( $is_pornographic ) : ?>
											<span class="fanfic-flag fanfic-flag-pornographic" title="<?php esc_attr_e( 'Pornographic Content', 'fanfiction-manager' ); ?>">P</span>
										<?php endif; ?>
										<?php if ( $is_restricted ) : ?>
											<span class="fanfic-flag fanfic-flag-restricted" title="<?php esc_attr_e( 'Restricted by site settings', 'fanfiction-manager' ); ?>">R</span>
										<?php endif; ?>
										<?php if ( ! $is_sexual && ! $is_pornographic && ! $is_restricted ) : ?>
											<span class="description">-</span>
										<?php endif; ?>
									</td>
									<td class="column-status">
										<?php if ( $is_enabled ) : ?>
											<span class="fanfic-status-badge fanfic-status-enabled"><?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?></span>
										<?php else : ?>
											<span class="fanfic-status-badge fanfic-status-disabled"><?php esc_html_e( 'Disabled', 'fanfiction-manager' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-actions">
										<a href="#" class="fanfic-edit-warning-link" data-id="<?php echo esc_attr( $warning['id'] ); ?>">
											<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
										</a>
										|
										<?php
										$toggle_action = $is_enabled ? 'disable' : 'enable';
										$toggle_label = $is_enabled ? __( 'Disable', 'fanfiction-manager' ) : __( 'Enable', 'fanfiction-manager' );
										$toggle_url = wp_nonce_url(
											admin_url( 'admin-post.php?action=fanfic_toggle_warning&warning_id=' . $warning['id'] ),
											'fanfic_toggle_warning_' . $warning['id']
										);
										?>
										<a href="<?php echo esc_url( $toggle_url ); ?>">
											<?php echo esc_html( $toggle_label ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>

			<!-- Legend -->
			<div class="fanfic-warnings-legend" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
				<h4 style="margin-top: 0;"><?php esc_html_e( 'Legend', 'fanfiction-manager' ); ?></h4>
				<p>
					<span class="fanfic-flag fanfic-flag-sexual">S</span> = <?php esc_html_e( 'Sexual Content', 'fanfiction-manager' ); ?> |
					<span class="fanfic-flag fanfic-flag-pornographic">P</span> = <?php esc_html_e( 'Pornographic Content', 'fanfiction-manager' ); ?> |
					<span class="fanfic-flag fanfic-flag-restricted">R</span> = <?php esc_html_e( 'Restricted (hidden from authors due to site settings)', 'fanfiction-manager' ); ?>
				</p>
			</div>
		</div>

		<!-- Add Warning Modal -->
		<div class="fanfic-admin-modal" id="fanfic-add-warning-modal" aria-hidden="true">
			<div class="fanfic-admin-modal-overlay" onclick="this.parentElement.setAttribute('aria-hidden', 'true');"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Add Warning', 'fanfiction-manager' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form">
					<input type="hidden" name="action" value="fanfic_add_warning">
					<?php wp_nonce_field( 'fanfic_add_warning', 'fanfic_add_warning_nonce' ); ?>

					<p>
						<label for="fanfic-add-warning-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="fanfic-add-warning-name" name="warning_name" class="regular-text" required>
					</p>

					<p>
						<label for="fanfic-add-warning-age"><?php esc_html_e( 'Minimum Age', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="number" id="fanfic-add-warning-age" name="warning_min_age" class="regular-text" min="3" max="99" step="1" list="fanfic-warning-age-options" required>
					</p>

					<p>
						<label for="fanfic-add-warning-description"><?php esc_html_e( 'Description', 'fanfiction-manager' ); ?></label>
						<textarea id="fanfic-add-warning-description" name="warning_description" rows="3" class="large-text"></textarea>
					</p>

					<p>
						<label>
							<input type="checkbox" name="warning_is_sexual" value="1">
							<?php esc_html_e( 'Contains sexual content', 'fanfiction-manager' ); ?>
						</label>
					</p>

					<p>
						<label>
							<input type="checkbox" name="warning_is_pornographic" value="1">
							<?php esc_html_e( 'Contains pornographic content', 'fanfiction-manager' ); ?>
						</label>
					</p>

					<div class="fanfic-admin-modal-actions">
						<button type="button" class="button" onclick="this.closest('.fanfic-admin-modal').setAttribute('aria-hidden', 'true');">
							<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Warning', 'fanfiction-manager' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<!-- Edit Warning Modal -->
		<div class="fanfic-admin-modal" id="fanfic-edit-warning-modal" aria-hidden="true">
			<div class="fanfic-admin-modal-overlay" onclick="this.parentElement.setAttribute('aria-hidden', 'true');"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Edit Warning', 'fanfiction-manager' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fanfic-admin-modal-form" id="fanfic-edit-warning-form">
					<input type="hidden" name="action" value="fanfic_update_warning">
					<input type="hidden" name="warning_id" id="fanfic-edit-warning-id" value="">
					<input type="hidden" name="fanfic_update_warning_nonce" id="fanfic-edit-warning-nonce" value="">

					<p>
						<label for="fanfic-edit-warning-name"><?php esc_html_e( 'Name', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="fanfic-edit-warning-name" name="warning_name" class="regular-text" required>
					</p>

					<p>
						<label for="fanfic-edit-warning-age"><?php esc_html_e( 'Minimum Age', 'fanfiction-manager' ); ?> <span class="required">*</span></label>
						<input type="number" id="fanfic-edit-warning-age" name="warning_min_age" class="regular-text" min="3" max="99" step="1" list="fanfic-warning-age-options" required>
					</p>

					<p>
						<label for="fanfic-edit-warning-description"><?php esc_html_e( 'Description', 'fanfiction-manager' ); ?></label>
						<textarea id="fanfic-edit-warning-description" name="warning_description" rows="3" class="large-text"></textarea>
					</p>

					<p>
						<label>
							<input type="checkbox" name="warning_is_sexual" id="fanfic-edit-warning-sexual" value="1">
							<?php esc_html_e( 'Contains sexual content', 'fanfiction-manager' ); ?>
						</label>
					</p>

					<p>
						<label>
							<input type="checkbox" name="warning_is_pornographic" id="fanfic-edit-warning-pornographic" value="1">
							<?php esc_html_e( 'Contains pornographic content', 'fanfiction-manager' ); ?>
						</label>
					</p>

					<p>
						<label>
							<input type="checkbox" name="warning_enabled" id="fanfic-edit-warning-enabled" value="1">
							<?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?>
						</label>
					</p>

					<div class="fanfic-admin-modal-actions" style="display: flex; justify-content: space-between;">
						<button type="button" class="button danger" id="fanfic-delete-warning-btn">
							<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
						</button>
						<div>
							<button type="button" class="button" onclick="this.closest('.fanfic-admin-modal').setAttribute('aria-hidden', 'true');">
								<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
							</button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'fanfiction-manager' ); ?></button>
						</div>
					</div>
				</form>

				<!-- Hidden delete form -->
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-delete-warning-form" style="display: none;">
					<input type="hidden" name="action" value="fanfic_delete_warning">
					<input type="hidden" name="warning_id" id="fanfic-delete-warning-id" value="">
					<input type="hidden" name="fanfic_delete_warning_nonce" id="fanfic-delete-warning-nonce" value="">
				</form>
			</div>
		</div>

		<datalist id="fanfic-warning-age-options">
			<?php foreach ( $age_options as $age ) : ?>
				<option value="<?php echo esc_attr( $age ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<style>
			.fanfic-warning-disabled td {
				opacity: 0.6;
			}
			.fanfic-age-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
			}
			.fanfic-age-badge-3-9 { background: #e8f5e9; color: #2e7d32; }
			.fanfic-age-badge-10-12 { background: #f1f8e9; color: #558b2f; }
			.fanfic-age-badge-13-15 { background: #fff8e1; color: #ef6c00; }
			.fanfic-age-badge-16-17 { background: #fff3e0; color: #e65100; }
			.fanfic-age-badge-18-plus { background: #ffebee; color: #c62828; }
			.fanfic-flag {
				display: inline-block;
				width: 20px;
				height: 20px;
				line-height: 20px;
				text-align: center;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				margin-right: 3px;
			}
			.fanfic-flag-sexual { background: #e3f2fd; color: #1565c0; }
			.fanfic-flag-pornographic { background: #fce4ec; color: #c2185b; }
			.fanfic-flag-restricted { background: #ffecb3; color: #ff6f00; }
			.fanfic-status-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
			}
			.fanfic-status-enabled { background: #e8f5e9; color: #2e7d32; }
			.fanfic-status-disabled { background: #f5f5f5; color: #757575; }
			.fanfic-admin-modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				z-index: 100000;
			}
			.fanfic-admin-modal[aria-hidden="false"] {
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.fanfic-admin-modal-overlay {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.5);
			}
			.fanfic-admin-modal-content {
				position: relative;
				background: #fff;
				padding: 20px;
				border-radius: 4px;
				max-width: 500px;
				width: 90%;
				max-height: 90vh;
				overflow-y: auto;
			}
			.fanfic-admin-modal-content h2 {
				margin-top: 0;
			}
			.fanfic-admin-modal-actions {
				margin-top: 20px;
				padding-top: 15px;
				border-top: 1px solid #ddd;
			}
			.danger {
				color: #a00 !important;
				border-color: #a00 !important;
			}
			.danger:hover {
				background: #a00 !important;
				color: #fff !important;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			function clampWarningAgeInput($input) {
				var min = parseInt($input.attr('min'), 10);
				var max = parseInt($input.attr('max'), 10);
				var raw = $.trim($input.val());

				if (raw === '') {
					return;
				}

				var parsed = Math.round(parseFloat(raw));
				if (isNaN(parsed)) {
					$input.val('');
					return;
				}

				if (!isNaN(min) && parsed < min) {
					parsed = min;
				}
				if (!isNaN(max) && parsed > max) {
					parsed = max;
				}

				$input.val(parsed);
			}

			var $ageInputs = $('#fanfic-add-warning-age, #fanfic-edit-warning-age');
			$ageInputs.on('change blur', function() {
				clampWarningAgeInput($(this));
			});
			$('.fanfic-admin-modal-form').on('submit', function() {
				$(this).find('input[name="warning_min_age"]').each(function() {
					clampWarningAgeInput($(this));
				});
			});

			// Edit warning modal
			$('.fanfic-edit-warning-link').on('click', function(e) {
				e.preventDefault();
				var $link = $(this);
				var $row = $link.closest('tr');

				// Get data from the link with all data attributes
				var $dataLink = $row.find('.fanfic-edit-warning-link[data-name]');
				if (!$dataLink.length) {
					$dataLink = $link;
				}

				$('#fanfic-edit-warning-id').val($dataLink.data('id'));
				$('#fanfic-edit-warning-name').val($dataLink.data('name'));
				$('#fanfic-edit-warning-age').val($dataLink.data('min-age'));
				$('#fanfic-edit-warning-description').val($dataLink.data('description'));
				$('#fanfic-edit-warning-sexual').prop('checked', $dataLink.data('is-sexual') == '1');
				$('#fanfic-edit-warning-pornographic').prop('checked', $dataLink.data('is-pornographic') == '1');
				$('#fanfic-edit-warning-enabled').prop('checked', $dataLink.data('enabled') == '1');
				$('#fanfic-edit-warning-nonce').val($dataLink.data('update-nonce'));

				// Set delete form values
				$('#fanfic-delete-warning-id').val($dataLink.data('id'));
				$('#fanfic-delete-warning-nonce').val($dataLink.data('delete-nonce'));

				$('#fanfic-edit-warning-modal').attr('aria-hidden', 'false');
			});

			// Delete warning button
			$('#fanfic-delete-warning-btn').on('click', function() {
				if (confirm('<?php echo esc_js( __( 'Are you sure you want to delete this warning? This action cannot be undone.', 'fanfiction-manager' ) ); ?>')) {
					$('#fanfic-delete-warning-form').submit();
				}
			});

			// Select all checkbox
			$('#cb-select-all').on('change', function() {
				$('input[name="warning_ids[]"]').prop('checked', this.checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Check if warnings table exists
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function tables_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Get all warnings with optional filters
	 *
	 * @since 1.2.0
	 * @param string $search Search term.
	 * @param string $filter_age Age filter.
	 * @param string $filter_enabled Enabled filter.
	 * @return array
	 */
	private static function get_all_warnings( $search = '', $filter_age = '', $filter_enabled = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$where = array( '1=1' );
		$args = array();

		if ( '' !== $search ) {
			$where[] = '(name LIKE %s OR description LIKE %s)';
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
		}

		if ( '' !== $filter_age ) {
			$normalized_age = class_exists( 'Fanfic_Warnings' ) ? Fanfic_Warnings::normalize_age_label( $filter_age ) : trim( (string) $filter_age );
			if ( '' !== $normalized_age ) {
				$where[] = 'min_age = %s';
				$args[] = $normalized_age;
			}
		}

		if ( '' !== $filter_enabled ) {
			$where[] = 'enabled = %d';
			$args[] = (int) $filter_enabled;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY name ASC';

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		$warnings = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $warnings ) || ! class_exists( 'Fanfic_Warnings' ) ) {
			return is_array( $warnings ) ? $warnings : array();
		}

		$priority = Fanfic_Warnings::get_age_priority_map( false );
		usort(
			$warnings,
			function( $left, $right ) use ( $priority ) {
				$left_age = Fanfic_Warnings::normalize_age_label( $left['min_age'] ?? '' );
				$right_age = Fanfic_Warnings::normalize_age_label( $right['min_age'] ?? '' );
				$left_rank = isset( $priority[ $left_age ] ) ? (int) $priority[ $left_age ] : 0;
				$right_rank = isset( $priority[ $right_age ] ) ? (int) $priority[ $right_age ] : 0;
				if ( $left_rank === $right_rank ) {
					return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
				}
				return ( $left_rank < $right_rank ) ? -1 : 1;
			}
		);

		return $warnings;
	}

	/**
	 * Get warning counts by age
	 *
	 * @since 1.2.0
	 * @return array
	 */
	private static function get_age_counts() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$results = $wpdb->get_results(
			"SELECT min_age, COUNT(*) as count FROM {$table} GROUP BY min_age",
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['min_age'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Build age option labels for the admin filter and forms.
	 *
	 * @since 1.2.0
	 * @param array $age_counts Counts keyed by age label.
	 * @return string[]
	 */
	private static function get_age_filter_options( $age_counts = array() ) {
		$age_options = array_keys( (array) $age_counts );

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			$priority = Fanfic_Warnings::get_age_priority_map( false );
			$default_age = Fanfic_Warnings::get_default_age_label( false );
			unset( $priority[ $default_age ] );
			$age_options = array_merge( $age_options, array_keys( $priority ) );
		}

		$age_options = array_values( array_unique( array_filter( array_map( 'trim', $age_options ) ) ) );
		if ( empty( $age_options ) ) {
			return array();
		}

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			$priority = Fanfic_Warnings::get_age_priority_map( false );
			usort(
				$age_options,
				function( $left, $right ) use ( $priority ) {
					$left_rank  = isset( $priority[ $left ] ) ? (int) $priority[ $left ] : PHP_INT_MAX;
					$right_rank = isset( $priority[ $right ] ) ? (int) $priority[ $right ] : PHP_INT_MAX;
					if ( $left_rank === $right_rank ) {
						return strcasecmp( (string) $left, (string) $right );
					}
					return ( $left_rank < $right_rank ) ? -1 : 1;
				}
			);
		} else {
			natcasesort( $age_options );
			$age_options = array_values( $age_options );
		}

		return $age_options;
	}

	/**
	 * Handle add warning
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_add_warning() {
		self::check_admin_nonce( 'fanfic_add_warning', 'fanfic_add_warning_nonce' );

		$name = isset( $_POST['warning_name'] ) ? sanitize_text_field( wp_unslash( $_POST['warning_name'] ) ) : '';
		$min_age = isset( $_POST['warning_min_age'] ) ? Fanfic_Warnings::sanitize_age_label( wp_unslash( $_POST['warning_min_age'] ) ) : '';
		$description = isset( $_POST['warning_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['warning_description'] ) ) : '';
		$is_sexual = isset( $_POST['warning_is_sexual'] ) ? 1 : 0;
		$is_pornographic = isset( $_POST['warning_is_pornographic'] ) ? 1 : 0;

		if ( '' === $name || '' === $min_age ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		// Slug is generated automatically from the warning name.
		$slug = sanitize_title( $name );
		$slug = self::ensure_unique_slug( $slug );

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$wpdb->insert(
			$table,
			array(
				'slug'           => $slug,
				'name'           => $name,
				'min_age'        => (int) $min_age,
				'description'    => $description,
				'is_sexual'      => $is_sexual,
				'is_pornographic' => $is_pornographic,
				'enabled'        => 1,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%d' )
		);

		$new_warning_id = (int) $wpdb->insert_id;
		if ( class_exists( 'Fanfic_Warnings' ) ) {
			Fanfic_Warnings::ensure_lowest_age_assignment();
			if ( $new_warning_id > 0 ) {
				Fanfic_Warnings::sync_age_ratings_for_warning( $new_warning_id, false );
			}
		}

		self::redirect_with_message( 'success', 'warning_added' );
	}

	/**
	 * Handle update warning
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_update_warning() {
		$warning_id = isset( $_POST['warning_id'] ) ? absint( $_POST['warning_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_update_warning_' . $warning_id, 'fanfic_update_warning_nonce' );

		$name = isset( $_POST['warning_name'] ) ? sanitize_text_field( wp_unslash( $_POST['warning_name'] ) ) : '';
		$min_age = isset( $_POST['warning_min_age'] ) ? Fanfic_Warnings::sanitize_age_label( wp_unslash( $_POST['warning_min_age'] ) ) : '';
		$description = isset( $_POST['warning_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['warning_description'] ) ) : '';
		$is_sexual = isset( $_POST['warning_is_sexual'] ) ? 1 : 0;
		$is_pornographic = isset( $_POST['warning_is_pornographic'] ) ? 1 : 0;
		$enabled = isset( $_POST['warning_enabled'] ) ? 1 : 0;

		if ( ! $warning_id || '' === $name || '' === $min_age ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT min_age FROM {$table} WHERE id = %d", $warning_id ),
			ARRAY_A
		);

		$wpdb->update(
			$table,
			array(
				'name'           => $name,
				'min_age'        => (int) $min_age,
				'description'    => $description,
				'is_sexual'      => $is_sexual,
				'is_pornographic' => $is_pornographic,
				'enabled'        => $enabled,
			),
			array( 'id' => $warning_id ),
			array( '%s', '%d', '%s', '%d', '%d', '%d' ),
			array( '%d' )
		);

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			Fanfic_Warnings::ensure_lowest_age_assignment();
			$min_age_changed = isset( $existing['min_age'] ) && (string) $existing['min_age'] !== (string) $min_age;
			if ( $min_age_changed ) {
				Fanfic_Warnings::sync_age_ratings_for_warning( $warning_id, false );
			}
		}

		self::redirect_with_message( 'success', 'warning_updated' );
	}

	/**
	 * Handle toggle warning
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_toggle_warning() {
		$warning_id = isset( $_GET['warning_id'] ) ? absint( $_GET['warning_id'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_toggle_warning_' . $warning_id ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		if ( ! $warning_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT enabled FROM {$table} WHERE id = %d", $warning_id )
		);

		$new_value = (int) $current === 1 ? 0 : 1;

		$wpdb->update(
			$table,
			array( 'enabled' => $new_value ),
			array( 'id' => $warning_id ),
			array( '%d' ),
			array( '%d' )
		);

		self::redirect_with_message( 'success', 'warning_updated' );
	}

	/**
	 * Handle delete warning
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_delete_warning() {
		$warning_id = isset( $_POST['warning_id'] ) ? absint( $_POST['warning_id'] ) : 0;
		self::check_admin_nonce( 'fanfic_delete_warning_' . $warning_id, 'fanfic_delete_warning_nonce' );

		if ( ! $warning_id ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$warnings_table = $wpdb->prefix . 'fanfic_warnings';
		$relations_table = $wpdb->prefix . 'fanfic_story_warnings';
		$affected_story_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT story_id FROM {$relations_table} WHERE warning_id = %d", $warning_id )
		);

		// Delete story relations first
		$wpdb->delete( $relations_table, array( 'warning_id' => $warning_id ), array( '%d' ) );

		// Delete the warning
		$wpdb->delete( $warnings_table, array( 'id' => $warning_id ), array( '%d' ) );

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			Fanfic_Warnings::ensure_lowest_age_assignment();
			Fanfic_Warnings::sync_story_age_ratings( $affected_story_ids );
		}

		self::redirect_with_message( 'success', 'warning_deleted' );
	}

	/**
	 * Handle bulk actions
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_bulk_warnings() {
		self::check_admin_nonce( 'fanfic_bulk_warnings', 'fanfic_bulk_warnings_nonce' );

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$warning_ids = isset( $_POST['warning_ids'] ) ? array_map( 'absint', $_POST['warning_ids'] ) : array();

		if ( empty( $warning_ids ) || '' === $action ) {
			self::redirect_with_message( 'error', 'missing_fields' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		$placeholders = implode( ',', array_fill( 0, count( $warning_ids ), '%d' ) );

		if ( 'enable' === $action || 'disable' === $action ) {
			$enabled = 'enable' === $action ? 1 : 0;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET enabled = %d WHERE id IN ({$placeholders})",
					array_merge( array( $enabled ), $warning_ids )
				)
			);
			self::redirect_with_message( 'success', 'warning_updated' );
		}

		if ( 'delete' === $action ) {
			$relations_table = $wpdb->prefix . 'fanfic_story_warnings';
			$affected_story_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT story_id FROM {$relations_table} WHERE warning_id IN ({$placeholders})",
					$warning_ids
				)
			);

			// Delete story relations first
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$relations_table} WHERE warning_id IN ({$placeholders})",
					$warning_ids
				)
			);

			// Delete warnings
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id IN ({$placeholders})",
					$warning_ids
				)
			);

			if ( class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::ensure_lowest_age_assignment();
				Fanfic_Warnings::sync_story_age_ratings( $affected_story_ids );
			}

			self::redirect_with_message( 'success', 'warning_deleted' );
		}

		self::redirect_with_message( 'error', 'missing_fields' );
	}

	/**
	 * Check admin nonce and permissions
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
	 * @param string $type success|error.
	 * @param string $code Code.
	 * @return void
	 */
	private static function redirect_with_message( $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-taxonomies',
					'tab'     => 'warnings',
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
	 * @since 1.2.0
	 * @param string $slug Base slug.
	 * @param int    $exclude_id ID to exclude.
	 * @return string
	 */
	private static function ensure_unique_slug( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		$base = $slug ? $slug : 'warning';
		$unique = $base;
		$counter = 2;

		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", $unique, $exclude_id ) ) > 0 ) {
			$unique = $base . '-' . $counter;
			$counter++;
		}

		return $unique;
	}
}
