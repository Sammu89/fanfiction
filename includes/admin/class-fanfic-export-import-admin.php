<?php
/**
 * Export/Import Admin Interface Class
 *
 * Handles the admin interface for CSV export and import functionality.
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
 * Class Fanfic_Export_Import_Admin
 *
 * Manages the export/import admin page and AJAX handlers.
 *
 * @since 1.0.0
 */
class Fanfic_Export_Import_Admin {

	/**
	 * Initialize the export/import admin class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Handle export requests
		add_action( 'admin_post_fanfic_export_stories', array( __CLASS__, 'handle_export_stories' ) );
		add_action( 'admin_post_fanfic_export_chapters', array( __CLASS__, 'handle_export_chapters' ) );
		add_action( 'admin_post_fanfic_export_taxonomies', array( __CLASS__, 'handle_export_taxonomies' ) );

		// Handle import requests
		add_action( 'admin_post_fanfic_import_upload', array( __CLASS__, 'handle_import_upload' ) );

		// AJAX handlers
		add_action( 'wp_ajax_fanfic_preview_import', array( __CLASS__, 'ajax_preview_import' ) );
	}

	/**
	 * Render the export/import admin page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get export statistics
		$stats = Fanfic_Export::get_export_stats();

		?>
		<div class="wrap fanfic-export-import-admin">
			<h1><?php esc_html_e( 'Export / Import', 'fanfiction-manager' ); ?></h1>
			<p><?php esc_html_e( 'Export your fanfiction data to CSV files for backup or migration. Import data from CSV files to add stories, chapters, or taxonomies.', 'fanfiction-manager' ); ?></p>

			<div class="fanfic-export-import-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">

				<!-- Export Section -->
				<div class="fanfic-export-section">
					<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Export Data', 'fanfiction-manager' ); ?></h2>
						<p><?php esc_html_e( 'Download your data as CSV files.', 'fanfiction-manager' ); ?></p>

						<!-- Export Statistics -->
						<div class="fanfic-export-stats" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
							<h3 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Available Data', 'fanfiction-manager' ); ?></h3>
							<ul style="margin: 0; padding-left: 20px;">
								<li>
									<?php
									printf(
										/* translators: %d: number of stories */
										esc_html__( 'Stories: %d published', 'fanfiction-manager' ),
										absint( $stats['total_stories'] )
									);
									?>
								</li>
								<li>
									<?php
									printf(
										/* translators: %d: number of chapters */
										esc_html__( 'Chapters: %d published', 'fanfiction-manager' ),
										absint( $stats['total_chapters'] )
									);
									?>
								</li>
								<li>
									<?php
									printf(
										/* translators: %d: number of genres */
										esc_html__( 'Genres: %d', 'fanfiction-manager' ),
										absint( $stats['total_genres'] )
									);
									?>
								</li>
								<li>
									<?php
									printf(
										/* translators: %d: number of statuses */
										esc_html__( 'Statuses: %d', 'fanfiction-manager' ),
										absint( $stats['total_statuses'] )
									);
									?>
								</li>
							</ul>
						</div>

						<!-- Export Stories -->
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 15px;">
							<input type="hidden" name="action" value="fanfic_export_stories">
							<?php wp_nonce_field( 'fanfic_export_stories_nonce', 'fanfic_export_stories_nonce' ); ?>

							<h3 style="font-size: 14px; margin-bottom: 10px;">
								<span class="dashicons dashicons-book" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Export Stories', 'fanfiction-manager' ); ?>
							</h3>
							<p class="description" style="margin-bottom: 10px;">
								<?php esc_html_e( 'Export all published stories with metadata, genres, status, views, and ratings.', 'fanfiction-manager' ); ?>
							</p>
							<?php submit_button( __( 'Download Stories CSV', 'fanfiction-manager' ), 'secondary', 'submit', false, array( 'style' => 'margin-top: 10px;' ) ); ?>
						</form>

						<hr style="margin: 20px 0;">

						<!-- Export Chapters -->
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 15px;">
							<input type="hidden" name="action" value="fanfic_export_chapters">
							<?php wp_nonce_field( 'fanfic_export_chapters_nonce', 'fanfic_export_chapters_nonce' ); ?>

							<h3 style="font-size: 14px; margin-bottom: 10px;">
								<span class="dashicons dashicons-media-text" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Export Chapters', 'fanfiction-manager' ); ?>
							</h3>
							<p class="description" style="margin-bottom: 10px;">
								<?php esc_html_e( 'Export all published chapters with their content, metadata, views, and ratings.', 'fanfiction-manager' ); ?>
							</p>
							<?php submit_button( __( 'Download Chapters CSV', 'fanfiction-manager' ), 'secondary', 'submit', false, array( 'style' => 'margin-top: 10px;' ) ); ?>
						</form>

						<hr style="margin: 20px 0;">

						<!-- Export Taxonomies -->
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="fanfic_export_taxonomies">
							<?php wp_nonce_field( 'fanfic_export_taxonomies_nonce', 'fanfic_export_taxonomies_nonce' ); ?>

							<h3 style="font-size: 14px; margin-bottom: 10px;">
								<span class="dashicons dashicons-tag" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Export Taxonomies', 'fanfiction-manager' ); ?>
							</h3>
							<p class="description" style="margin-bottom: 10px;">
								<?php esc_html_e( 'Export all genres, statuses, and custom taxonomies with their hierarchies.', 'fanfiction-manager' ); ?>
							</p>
							<?php submit_button( __( 'Download Taxonomies CSV', 'fanfiction-manager' ), 'secondary', 'submit', false, array( 'style' => 'margin-top: 10px;' ) ); ?>
						</form>
					</div>
				</div>

				<!-- Import Section -->
				<div class="fanfic-import-section">
					<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Import Data', 'fanfiction-manager' ); ?></h2>
						<p><?php esc_html_e( 'Upload CSV files to import stories, chapters, or taxonomies.', 'fanfiction-manager' ); ?></p>

						<!-- Import Form -->
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="fanfic-import-form">
							<input type="hidden" name="action" value="fanfic_import_upload">
							<?php wp_nonce_field( 'fanfic_import_upload_nonce', 'fanfic_import_upload_nonce' ); ?>

							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row">
											<label for="import_type"><?php esc_html_e( 'Import Type', 'fanfiction-manager' ); ?></label>
										</th>
										<td>
											<select name="import_type" id="import_type" required>
												<option value=""><?php esc_html_e( '-- Select Type --', 'fanfiction-manager' ); ?></option>
												<option value="stories"><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></option>
												<option value="chapters"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></option>
												<option value="taxonomies"><?php esc_html_e( 'Taxonomies', 'fanfiction-manager' ); ?></option>
											</select>
										</td>
									</tr>

									<tr>
										<th scope="row">
											<label for="csv_file"><?php esc_html_e( 'CSV File', 'fanfiction-manager' ); ?></label>
										</th>
										<td>
											<input type="file" name="csv_file" id="csv_file" accept=".csv" required>
											<p class="description">
												<?php esc_html_e( 'Maximum file size: 10MB. Only CSV files are allowed.', 'fanfiction-manager' ); ?>
											</p>
										</td>
									</tr>

									<tr>
										<th scope="row">
											<label for="dry_run"><?php esc_html_e( 'Dry Run', 'fanfiction-manager' ); ?></label>
										</th>
										<td>
											<label>
												<input type="checkbox" name="dry_run" id="dry_run" value="1" checked>
												<?php esc_html_e( 'Preview import without making changes (recommended)', 'fanfiction-manager' ); ?>
											</label>
											<p class="description">
												<?php esc_html_e( 'When enabled, the import will validate the CSV file and show what would be imported without actually creating any data.', 'fanfiction-manager' ); ?>
											</p>
										</td>
									</tr>
								</tbody>
							</table>

							<p class="submit">
								<?php submit_button( __( 'Upload and Process', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
							</p>
						</form>

						<!-- Sample CSV Format -->
						<hr style="margin: 30px 0;">
						<div class="fanfic-csv-format-info">
							<h3 style="font-size: 14px;"><?php esc_html_e( 'CSV Format Requirements', 'fanfiction-manager' ); ?></h3>

							<details style="margin: 15px 0;">
								<summary style="cursor: pointer; font-weight: 600; padding: 5px 0;">
									<?php esc_html_e( 'Stories CSV Format', 'fanfiction-manager' ); ?>
								</summary>
								<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
									<p><strong><?php esc_html_e( 'Required columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Title</code> - Story title</li>
										<li><code>Author ID</code> - WordPress user ID</li>
										<li><code>Introduction</code> - Story summary/excerpt</li>
										<li><code>Genres</code> - Pipe-separated (e.g., "Drama|Romance")</li>
										<li><code>Status</code> - Story status (e.g., "Ongoing")</li>
									</ul>
									<p><strong><?php esc_html_e( 'Optional columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Publication Date</code> - Publication date</li>
										<li><code>Featured</code> - "Yes" or "No"</li>
									</ul>
								</div>
							</details>

							<details style="margin: 15px 0;">
								<summary style="cursor: pointer; font-weight: 600; padding: 5px 0;">
									<?php esc_html_e( 'Chapters CSV Format', 'fanfiction-manager' ); ?>
								</summary>
								<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
									<p><strong><?php esc_html_e( 'Required columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Story ID</code> - Parent story ID</li>
										<li><code>Title</code> - Chapter title</li>
										<li><code>Content</code> - Chapter content (HTML allowed)</li>
									</ul>
									<p><strong><?php esc_html_e( 'Optional columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Chapter Number</code> - Sequential number</li>
										<li><code>Chapter Type</code> - "prologue", "chapter", or "epilogue"</li>
										<li><code>Publication Date</code> - Publication date</li>
									</ul>
								</div>
							</details>

							<details style="margin: 15px 0;">
								<summary style="cursor: pointer; font-weight: 600; padding: 5px 0;">
									<?php esc_html_e( 'Taxonomies CSV Format', 'fanfiction-manager' ); ?>
								</summary>
								<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
									<p><strong><?php esc_html_e( 'Required columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Taxonomy</code> - Taxonomy slug (e.g., "fanfiction_genre")</li>
										<li><code>Term Name</code> - Term name</li>
										<li><code>Slug</code> - Term slug</li>
									</ul>
									<p><strong><?php esc_html_e( 'Optional columns:', 'fanfiction-manager' ); ?></strong></p>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<li><code>Parent ID</code> - Parent term ID (for hierarchical)</li>
										<li><code>Description</code> - Term description</li>
									</ul>
								</div>
							</details>
						</div>
					</div>
				</div>
			</div>
		</div>

		<style>
		.fanfic-export-import-admin h2 {
			color: #1d2327;
			font-size: 18px;
		}
		.fanfic-export-import-admin h3 {
			color: #1d2327;
		}
		.fanfic-export-import-admin .button {
			display: inline-flex;
			align-items: center;
			gap: 5px;
		}
		.fanfic-export-import-admin .dashicons {
			font-size: 16px;
		}
		.fanfic-export-import-admin details summary {
			transition: color 0.2s;
		}
		.fanfic-export-import-admin details summary:hover {
			color: #2271b1;
		}
		.fanfic-export-import-admin code {
			background: #fff;
			padding: 2px 6px;
			border-radius: 3px;
			font-family: Consolas, Monaco, monospace;
			font-size: 13px;
		}
		@media (max-width: 1024px) {
			.fanfic-export-import-container {
				grid-template-columns: 1fr !important;
			}
		}
		</style>
		<?php
	}

	/**
	 * Handle export stories request
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_export_stories() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_export_stories_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_export_stories_nonce'] ) ), 'fanfic_export_stories_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Export stories
		Fanfic_Export::export_stories();
	}

	/**
	 * Handle export chapters request
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_export_chapters() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_export_chapters_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_export_chapters_nonce'] ) ), 'fanfic_export_chapters_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Export chapters
		Fanfic_Export::export_chapters();
	}

	/**
	 * Handle export taxonomies request
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_export_taxonomies() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_export_taxonomies_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_export_taxonomies_nonce'] ) ), 'fanfic_export_taxonomies_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Export taxonomies
		Fanfic_Export::export_taxonomies();
	}

	/**
	 * Handle import upload request
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_import_upload() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_import_upload_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_import_upload_nonce'] ) ), 'fanfic_import_upload_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Get import type
		$import_type = isset( $_POST['import_type'] ) ? sanitize_text_field( wp_unslash( $_POST['import_type'] ) ) : '';
		if ( ! in_array( $import_type, array( 'stories', 'chapters', 'taxonomies' ), true ) ) {
			wp_die( esc_html__( 'Invalid import type.', 'fanfiction-manager' ) );
		}

		// Check if file was uploaded
		if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			self::redirect_with_error( __( 'No file was uploaded.', 'fanfiction-manager' ) );
			return;
		}

		// Validate file
		$file_path = Fanfic_Import::validate_uploaded_file( $_FILES['csv_file'] );
		if ( is_wp_error( $file_path ) ) {
			self::redirect_with_error( $file_path->get_error_message() );
			return;
		}

		// Check if dry run
		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];

		// Process import based on type
		switch ( $import_type ) {
			case 'stories':
				$result = Fanfic_Import::import_stories( $file_path, $dry_run );
				break;
			case 'chapters':
				$result = Fanfic_Import::import_chapters( $file_path, $dry_run );
				break;
			case 'taxonomies':
				$result = Fanfic_Import::import_taxonomies( $file_path );
				break;
			default:
				$result = new WP_Error( 'invalid_type', __( 'Invalid import type.', 'fanfiction-manager' ) );
		}

		// Clean up temp file
		Fanfic_Import::cleanup_temp_file( $file_path );

		// Handle result
		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result->get_error_message() );
			return;
		}

		// Redirect with success message
		$redirect_args = array(
			'page'          => 'fanfiction-settings',
			'tab'           => 'import-export',
			'import'        => 'success',
			'type'          => $import_type,
			'success_count' => $result['success_count'],
			'error_count'   => $result['error_count'],
			'dry_run'       => $dry_run ? '1' : '0',
		);

		// Store errors in transient if any
		if ( ! empty( $result['errors'] ) ) {
			set_transient( 'fanfic_import_errors_' . get_current_user_id(), $result['errors'], 300 );
			$redirect_args['has_errors'] = '1';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Redirect with error message
	 *
	 * @since 1.0.0
	 * @param string $message Error message.
	 * @return void
	 */
	private static function redirect_with_error( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-settings',
					'tab'     => 'import-export',
					'import'  => 'error',
					'message' => urlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Display import/export notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function display_notices() {
		// Only show on our page
		if ( ! isset( $_GET['page'] ) || 'fanfiction-settings' !== $_GET['page'] ||
		     ! isset( $_GET['tab'] ) || 'import-export' !== $_GET['tab'] ) {
			return;
		}

		// Import success notice
		if ( isset( $_GET['import'] ) && 'success' === $_GET['import'] ) {
			$success_count = isset( $_GET['success_count'] ) ? absint( $_GET['success_count'] ) : 0;
			$error_count = isset( $_GET['error_count'] ) ? absint( $_GET['error_count'] ) : 0;
			$dry_run = isset( $_GET['dry_run'] ) && '1' === $_GET['dry_run'];
			$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';

			$class = $error_count > 0 ? 'notice-warning' : 'notice-success';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
				<p>
					<?php
					if ( $dry_run ) {
						printf(
							/* translators: 1: success count, 2: import type */
							esc_html__( 'Dry run complete: %1$d %2$s would be imported successfully.', 'fanfiction-manager' ),
							$success_count,
							esc_html( $type )
						);
					} else {
						printf(
							/* translators: 1: success count, 2: import type */
							esc_html__( 'Import complete: %1$d %2$s imported successfully.', 'fanfiction-manager' ),
							$success_count,
							esc_html( $type )
						);
					}

					if ( $error_count > 0 ) {
						echo ' ';
						printf(
							/* translators: %d: error count */
							esc_html__( '%d errors occurred.', 'fanfiction-manager' ),
							$error_count
						);
					}
					?>
				</p>
				<?php
				// Display errors if any
				if ( isset( $_GET['has_errors'] ) && '1' === $_GET['has_errors'] ) {
					$errors = get_transient( 'fanfic_import_errors_' . get_current_user_id() );
					if ( $errors ) {
						echo '<details style="margin-top: 10px;"><summary style="cursor: pointer; font-weight: 600;">' . esc_html__( 'Show Errors', 'fanfiction-manager' ) . '</summary>';
						echo '<ul style="margin: 10px 0; padding-left: 20px; max-height: 300px; overflow-y: auto;">';
						foreach ( $errors as $error ) {
							echo '<li>' . esc_html( $error ) . '</li>';
						}
						echo '</ul></details>';
						delete_transient( 'fanfic_import_errors_' . get_current_user_id() );
					}
				}
				?>
			</div>
			<?php
		}

		// Import error notice
		if ( isset( $_GET['import'] ) && 'error' === $_GET['import'] && isset( $_GET['message'] ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ) ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler for import preview
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_preview_import() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_preview_import', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// This would be implemented if we wanted real-time AJAX preview
		// For now, the dry run functionality handles preview
		wp_send_json_success( array( 'message' => __( 'Preview feature coming soon.', 'fanfiction-manager' ) ) );
	}
}
