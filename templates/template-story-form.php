<?php
/**
 * Template Name: Story Form (Unified Create/Edit)
 * Description: Unified form for creating new stories and editing existing ones
 *
 * This template handles both:
 * - Create mode: /fanfiction/?action=create-story (no story_id)
 * - Edit mode: /fanfiction/stories/{story-slug}/?action=edit (has story_id from singular context)
 *
 * @package Fanfiction_Manager
 * @since 2.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You must be logged in to create or edit stories.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// ========================================================================
// MODE DETECTION: Create vs Edit
// ========================================================================

// Detect if we're in edit mode based on whether we're viewing a singular story
$is_edit_mode = is_singular( 'fanfiction_story' );
$story_id = $is_edit_mode ? get_the_ID() : 0;
$story = null;
$story_title = '';

// For edit mode, validate story exists and user has permission
if ( $is_edit_mode ) {
	$story = get_post( $story_id );

	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		?>
		<div class="fanfic-error-notice" role="alert" aria-live="assertive">
			<p><?php esc_html_e( 'Story not found.', 'fanfiction-manager' ); ?></p>
			<p>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
					<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	}

	// Check if user has permission to edit this story
	if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
		?>
		<div class="fanfic-error-notice" role="alert" aria-live="assertive">
			<p><?php esc_html_e( 'Access Denied: You do not have permission to edit this story.', 'fanfiction-manager' ); ?></p>
			<p>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
					<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	}

	$story_title = $story->post_title;
} else {
	// Create mode - check if user has capability to create stories
	if ( ! current_user_can( 'edit_fanfiction_stories' ) ) {
		?>
		<div class="fanfic-error-notice" role="alert" aria-live="assertive">
			<p><?php esc_html_e( 'Access Denied: You do not have permission to create stories.', 'fanfiction-manager' ); ?></p>
		</div>
		<?php
		return;
	}
}

// ========================================================================
// FORM SUBMISSION HANDLING
// ========================================================================
// NOTE: Form submission is handled in includes/handlers/class-fanfic-story-handler.php
// via the handle_unified_story_form() method hooked to template_redirect.
// This ensures processing happens before any output (headers) are sent.
// ========================================================================

?>

<!-- Include Warning Modals Template -->
<?php include( plugin_dir_path( __FILE__ ) . 'modal-warnings.php' ); ?>

<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<?php if ( $is_edit_mode ) : ?>
			<li class="fanfic-breadcrumb-item">
				<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
			</li>
			<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
				<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
			</li>
		<?php else : ?>
			<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
				<?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
			</li>
		<?php endif; ?>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Story updated successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<!-- Display validation errors from transient -->
<?php
$errors = get_transient( 'fanfic_story_errors_' . get_current_user_id() );
if ( $errors ) {
	delete_transient( 'fanfic_story_errors_' . get_current_user_id() );
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<ul>
			<?php foreach ( $errors as $error ) : ?>
				<li><?php echo esc_html( $error ); ?></li>
			<?php endforeach; ?>
		</ul>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}
?>

<!-- Display validation errors from pre-save validation -->
<?php
$validation_errors = $is_edit_mode ? get_transient( 'fanfic_story_validation_errors_' . get_current_user_id() . '_' . $story_id ) : false;
if ( $validation_errors ) {
	delete_transient( 'fanfic_story_validation_errors_' . get_current_user_id() . '_' . $story_id );
	?>
	<div class="fanfic-validation-error-notice" role="alert" aria-live="assertive">
		<p><strong><?php esc_html_e( 'Story cannot be published due to the following issues:', 'fanfiction-manager' ); ?></strong></p>
		<ul>
			<?php foreach ( $validation_errors as $error ) : ?>
				<li><?php echo esc_html( $error ); ?></li>
			<?php endforeach; ?>
		</ul>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}
?>

<?php
// ========================================================================
// PREPARE FORM VARIABLES
// ========================================================================

$current_genres = array();
$current_status = '';
$featured_image = '';
$story_introduction = '';

if ( $is_edit_mode ) {
	// Pre-populate variables for edit mode
	$current_genres = wp_get_object_terms( $story->ID, 'fanfiction_genre', array( 'fields' => 'ids' ) );
	$current_status_obj = wp_get_object_terms( $story->ID, 'fanfiction_status', array( 'fields' => 'ids' ) );
	$current_status = ! empty( $current_status_obj ) ? $current_status_obj[0] : '';
	$featured_image = get_post_meta( $story->ID, '_fanfic_featured_image', true );
	$story_introduction = $story->post_excerpt;
}

$form_mode = $is_edit_mode ? 'edit' : 'create';

// Prepare data attributes for change detection (edit mode only)
$data_attrs = '';
if ( $is_edit_mode ) {
	$data_attrs = sprintf(
		'data-original-title="%s" data-original-content="%s" data-original-genres="%s" data-original-status="%s" data-original-image="%s"',
		esc_attr( $story->post_title ),
		esc_attr( $story->post_excerpt ),
		esc_attr( implode( ',', $current_genres ) ),
		esc_attr( $current_status ),
		esc_attr( $featured_image )
	);
}
?>

<p class="fanfic-page-description">
	<?php echo $is_edit_mode ? esc_html__( 'Update your story details below. Changes will be saved immediately.', 'fanfiction-manager' ) : esc_html__( 'Tell us about your story! Fill out the form below to get started.', 'fanfiction-manager' ); ?>
</p>

<!-- Main Content Area -->
<div class="fanfic-content-layout">
	<!-- Story Form -->
	<div class="fanfic-content-primary">
		<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="form-heading">
			<h2 id="form-heading" class="screen-reader-text"><?php echo $is_edit_mode ? esc_html__( 'Story Edit Form', 'fanfiction-manager' ) : esc_html__( 'Story Creation Form', 'fanfiction-manager' ); ?></h2>

			<!-- Info Box -->
			<div class="fanfic-info-box" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<p>
					<?php echo $is_edit_mode ? esc_html__( 'Your story must have an introduction, at least one chapter, a genre, and a status to be published.', 'fanfiction-manager' ) : esc_html__( 'All fields marked with an asterisk (*) are required. Your story will be saved as a draft until you add at least one chapter.', 'fanfiction-manager' ); ?>
				</p>
			</div>

			<!-- Story Form -->
			<div class="fanfic-form-wrapper fanfic-story-form-<?php echo esc_attr( $form_mode ); ?>">
				<div class="fanfic-form-header">
					<h2><?php echo $is_edit_mode ? sprintf( esc_html__( 'Edit Story: "%s"', 'fanfiction-manager' ), esc_html( $story->post_title ) ) : esc_html__( 'Create New Story', 'fanfiction-manager' ); ?></h2>
					<?php if ( $is_edit_mode ) : ?>
						<?php
						$post_status = get_post_status( $story_id );
						$status_class = 'publish' === $post_status ? 'published' : 'draft';
						// Use "Visible" instead of "Published" for stories to avoid confusing users
						$status_text = 'publish' === $post_status ? __( 'Visible', 'fanfiction-manager' ) : __( 'Draft', 'fanfiction-manager' );
						?>
						<span class="fanfic-story-status-badge fanfic-status-<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
					<?php endif; ?>
				</div>

				<form method="post" class="fanfic-story-form" id="fanfic-story-form" <?php echo $data_attrs; ?>>
					<div class="fanfic-form-content">
						<?php wp_nonce_field( 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' ), 'fanfic_story_nonce' ); ?>

						<!-- Story Title -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_title"><?php esc_html_e( 'Story Title', 'fanfiction-manager' ); ?></label>
							<input
								type="text"
								id="fanfic_story_title"
								name="fanfic_story_title"
								class="fanfic-input"
								maxlength="200"
								required
								value="<?php echo isset( $_POST['fanfic_story_title'] ) ? esc_attr( $_POST['fanfic_story_title'] ) : ( $is_edit_mode ? esc_attr( $story->post_title ) : '' ); ?>"
							/>
						</div>

						<!-- Story Introduction -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_intro"><?php esc_html_e( 'Story Introduction', 'fanfiction-manager' ); ?></label>
							<textarea
								id="fanfic_story_intro"
								name="fanfic_story_introduction"
								class="fanfic-textarea"
								rows="8"
								maxlength="10000"
							><?php echo isset( $_POST['fanfic_story_introduction'] ) ? esc_textarea( $_POST['fanfic_story_introduction'] ) : ( $is_edit_mode ? esc_textarea( $story->post_excerpt ) : '' ); ?></textarea>
						</div>

						<!-- Genres -->
						<div class="fanfic-form-field">
							<label><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
							<div class="fanfic-checkboxes fanfic-checkboxes-grid">
								<?php
								$genres = get_terms( array(
									'taxonomy' => 'fanfiction_genre',
									'hide_empty' => false,
								) );

								foreach ( $genres as $genre ) {
									$is_checked = isset( $_POST['fanfic_story_genres'] ) ?
										in_array( $genre->term_id, (array) $_POST['fanfic_story_genres'] ) :
										( $is_edit_mode && in_array( $genre->term_id, $current_genres ) );
									?>
									<label class="fanfic-checkbox-label">
										<input
											type="checkbox"
											name="fanfic_story_genres[]"
											value="<?php echo esc_attr( $genre->term_id ); ?>"
											class="fanfic-checkbox"
											<?php checked( $is_checked ); ?>
										/>
										<?php echo esc_html( $genre->name ); ?>
									</label>
									<?php
								}
								?>
							</div>
						</div>

						<!-- Status -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></label>
							<select id="fanfic_story_status" name="fanfic_story_status" class="fanfic-select" required>
								<option value=""><?php esc_html_e( 'Select a status...', 'fanfiction-manager' ); ?></option>
								<?php
								$statuses = get_terms( array(
									'taxonomy' => 'fanfiction_status',
									'hide_empty' => false,
								) );

								foreach ( $statuses as $status ) {
									$is_selected = isset( $_POST['fanfic_story_status'] ) ?
										$_POST['fanfic_story_status'] == $status->term_id :
										( $is_edit_mode && $current_status == $status->term_id );
									?>
									<option value="<?php echo esc_attr( $status->term_id ); ?>" <?php selected( $is_selected ); ?>>
										<?php echo esc_html( $status->name ); ?>
									</option>
									<?php
								}
								?>
							</select>
						</div>

						<!-- Featured Image -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_image"><?php esc_html_e( 'Featured Image URL', 'fanfiction-manager' ); ?></label>
							<input
								type="url"
								id="fanfic_story_image"
								name="fanfic_story_image"
								class="fanfic-input"
								value="<?php echo isset( $_POST['fanfic_story_image'] ) ? esc_attr( $_POST['fanfic_story_image'] ) : ( $is_edit_mode ? esc_attr( $featured_image ) : '' ); ?>"
							/>
						</div>
					</div>

					<!-- Hidden fields -->
					<?php if ( $is_edit_mode ) : ?>
						<input type="hidden" name="fanfic_story_id" value="<?php echo esc_attr( $story_id ); ?>" />
					<?php endif; ?>
					<input type="hidden" name="fanfic_story_form_mode" value="<?php echo esc_attr( $form_mode ); ?>" />

					<!-- Form Actions -->
					<div class="fanfic-form-actions">
						<?php if ( ! $is_edit_mode ) : ?>
							<!-- CREATE MODE -->
							<button type="submit" name="fanfic_form_action" value="add_chapter" class="fanfic-btn fanfic-btn-primary">
								<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
							</button>
							<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-btn fanfic-btn-secondary">
								<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-btn fanfic-btn-secondary">
								<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
							</a>
						<?php else : ?>
							<!-- EDIT MODE -->
							<?php
							// Check if story has published chapters
							$published_chapter_count = get_posts( array(
								'post_type'      => 'fanfiction_chapter',
								'post_parent'    => $story_id,
								'post_status'    => 'publish',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							) );
							$has_published_chapters = ! empty( $published_chapter_count );
							$current_post_status = get_post_status( $story_id );
							$is_published = 'publish' === $current_post_status;
							?>
							<?php if ( ! $has_published_chapters ) : ?>
								<!-- EDIT MODE - NO PUBLISHED CHAPTERS -->
								<button type="submit" name="fanfic_form_action" value="add_chapter" class="fanfic-btn fanfic-btn-primary">
									<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
								</button>
								<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-btn fanfic-btn-secondary">
									<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
								</button>
							<?php elseif ( ! $is_published ) : ?>
								<!-- EDIT MODE - HAS PUBLISHED CHAPTERS BUT STORY IS DRAFT -->
								<button type="submit" name="fanfic_form_action" value="publish" class="fanfic-btn fanfic-btn-primary">
									<?php esc_html_e( 'Make Visible', 'fanfiction-manager' ); ?>
								</button>
								<button type="submit" name="fanfic_form_action" value="update" class="fanfic-btn fanfic-btn-secondary" id="update-draft-btn" disabled>
									<?php esc_html_e( 'Update Draft', 'fanfiction-manager' ); ?>
								</button>
							<?php else : ?>
								<!-- EDIT MODE - HAS PUBLISHED CHAPTERS AND STORY IS PUBLISHED -->
								<button type="submit" name="fanfic_form_action" value="update" class="fanfic-btn fanfic-btn-primary" id="update-btn" disabled>
									<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
								</button>
								<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-btn fanfic-btn-secondary">
									<?php esc_html_e( 'Hide from readers (save as draft)', 'fanfiction-manager' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( $is_published ) : ?>
								<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-btn fanfic-btn-secondary" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
								</a>
							<?php endif; ?>
							<button type="button" id="delete-story-button" class="fanfic-btn fanfic-btn-danger" data-story-id="<?php echo absint( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story_title ); ?>">
								<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-btn fanfic-btn-secondary">
								<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</section>

		<!-- Chapters Management Section (Edit mode only) -->
		<?php if ( $is_edit_mode ) : ?>
		<section class="fanfic-content-section" class="fanfic-chapters-section" aria-labelledby="chapters-heading">
			<div class="fanfic-section-header">
				<h2 id="chapters-heading"><?php esc_html_e( 'Chapters in This Story', 'fanfiction-manager' ); ?></h2>
				<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ); ?>" class="fanfic-button-primary">
					<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
				</a>
			</div>

			<!-- Chapters List -->
			<div class="fanfic-chapters-list">
				<?php
				// Get all chapters for this story
				$chapters_args = array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'post_status'    => array( 'publish', 'draft', 'pending' ),
				);

				$chapters_query = new WP_Query( $chapters_args );
				$chapters = $chapters_query->posts;

				// Sort chapters by chapter number
				if ( ! empty( $chapters ) ) {
					usort( $chapters, function( $a, $b ) {
						$number_a = get_post_meta( $a->ID, '_fanfic_chapter_number', true );
						$number_b = get_post_meta( $b->ID, '_fanfic_chapter_number', true );

						// Convert to integers for proper comparison
						$number_a = absint( $number_a );
						$number_b = absint( $number_b );

						// Prologue (0) comes first, then regular chapters (1-999), then epilogue (1000+)
						return $number_a - $number_b;
					} );
				}

				if ( ! empty( $chapters ) ) :
					?>
					<table class="fanfic-table" role="table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Chapter #', 'fanfiction-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Title', 'fanfiction-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Word Count', 'fanfiction-manager' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $chapters as $chapter ) :
								$chapter_id = $chapter->ID;
								$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
								$stored_chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

								// Display chapter label based on type
								if ( 'prologue' === $chapter_type ) {
									$display_number = __( 'Prologue', 'fanfiction-manager' );
								} elseif ( 'epilogue' === $chapter_type ) {
									$display_number = __( 'Epilogue', 'fanfiction-manager' );
								} else {
									$display_number = sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $stored_chapter_number );
								}

								// Get word count
								$content = $chapter->post_content;
								$word_count = str_word_count( wp_strip_all_tags( $content ) );

								// Get status
								$status = $chapter->post_status;
								$status_labels = array(
									'publish' => __( 'Published', 'fanfiction-manager' ),
									'draft'   => __( 'Draft', 'fanfiction-manager' ),
									'pending' => __( 'Pending', 'fanfiction-manager' ),
								);
								$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Chapter #', 'fanfiction-manager' ); ?>">
										<strong><?php echo esc_html( $display_number ); ?></strong>
									</td>
									<td data-label="<?php esc_attr_e( 'Title', 'fanfiction-manager' ); ?>">
										<?php echo esc_html( $chapter->post_title ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Status', 'fanfiction-manager' ); ?>">
										<span class="fanfic-status-badge fanfic-status-<?php echo esc_attr( $status ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</span>
									</td>
									<td data-label="<?php esc_attr_e( 'Word Count', 'fanfiction-manager' ); ?>">
										<?php echo esc_html( number_format_i18n( $word_count ) ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
										<div class="fanfic-actions-buttons">
											<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( $chapter_id ) ); ?>" class="fanfic-button-small" aria-label="<?php esc_attr_e( 'Edit chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
											</a>
										<?php if ( 'publish' === $status ) : ?>
											<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button-small" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'View chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
											</a>
											<button type="button" class="fanfic-button-small fanfic-button-warning fanfic-unpublish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" data-story-id="<?php echo absint( $story_id ); ?>" aria-label="<?php esc_attr_e( 'Unpublish chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Unpublish', 'fanfiction-manager' ); ?>
											</button>
										<?php elseif ( 'draft' === $status ) : ?>
											<button type="button" class="fanfic-button-small fanfic-button-primary fanfic-publish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Publish chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Publish', 'fanfiction-manager' ); ?>
											</button>
										<?php endif; ?>
											<button type="button" class="fanfic-button-small fanfic-button-danger fanfic-delete-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
					wp_reset_postdata();
				else :
					?>
					<div class="fanfic-empty-state" role="status">
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
						<p><?php esc_html_e( 'No chapters yet. Add your first chapter to get started!', 'fanfiction-manager' ); ?></p>
						<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ); ?>" class="fanfic-button-primary">
							<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Add First Chapter', 'fanfiction-manager' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php endif; ?>
	</div>

	<!-- Help Sidebar (Create mode only) -->
	<?php if ( ! $is_edit_mode ) : ?>
	<aside class="fanfic-content-sidebar" aria-labelledby="help-heading">
		<h2 id="help-heading"><?php esc_html_e( 'Tips & Guidelines', 'fanfiction-manager' ); ?></h2>

		<!-- Tips for Good Story Titles -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="title-tips-heading">
			<h3 id="title-tips-heading">
				<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
				<?php esc_html_e( 'Tips for Good Story Titles', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Make it memorable and unique', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it concise (50-100 characters)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Avoid generic titles like "Untitled"', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Consider including keywords from your story', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Tips for Writing Good Introductions -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="intro-tips-heading">
			<h3 id="intro-tips-heading">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Writing Good Story Descriptions', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Hook readers with the first sentence', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Summarize the main plot without spoilers', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Mention key themes or genres', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it between 100-300 words', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Genre Information -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="genre-tips-heading">
			<h3 id="genre-tips-heading">
				<span class="dashicons dashicons-category" aria-hidden="true"></span>
				<?php esc_html_e( 'Understanding Genres', 'fanfiction-manager' ); ?>
			</h3>
			<p class="fanfic-help-text">
				<?php esc_html_e( 'Genres help readers find stories they\'ll enjoy. You can select multiple genres that fit your story.', 'fanfiction-manager' ); ?>
			</p>
			<ul class="fanfic-help-list">
				<li><strong><?php esc_html_e( 'Romance:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Focus on relationships and love', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Adventure:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Action-packed journeys', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Drama:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Emotional and character-driven', 'fanfiction-manager' ); ?></li>
				<li><strong><?php esc_html_e( 'Mystery:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Puzzles and suspense', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Status Options -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="status-tips-heading">
			<h3 id="status-tips-heading">
				<span class="dashicons dashicons-flag" aria-hidden="true"></span>
				<?php esc_html_e( 'Story Status Options', 'fanfiction-manager' ); ?>
			</h3>
			<dl class="fanfic-help-definitions">
				<dt><?php esc_html_e( 'Ongoing:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Actively being written and updated', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'Finished:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Story is complete', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'On Hiatus:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'Temporarily paused', 'fanfiction-manager' ); ?></dd>

				<dt><?php esc_html_e( 'Abandoned:', 'fanfiction-manager' ); ?></dt>
				<dd><?php esc_html_e( 'No longer being updated', 'fanfiction-manager' ); ?></dd>
			</dl>
		</section>

		<!-- Back to Dashboard Link -->
		<div class="fanfic-help-footer">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button-link">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</div>
	</aside>
	<?php endif; ?>
</div>

<!-- Delete Confirmation Modal (Edit mode only) -->
<?php if ( $is_edit_mode ) : ?>
<div id="delete-confirm-modal" class="fanfic-modal" role="dialog" aria-labelledby="modal-title" aria-modal="true" style="display: none;">
	<div class="fanfic-modal-overlay"></div>
	<div class="fanfic-modal-content">
		<h2 id="modal-title"><?php esc_html_e( 'Confirm Deletion', 'fanfiction-manager' ); ?></h2>
		<p id="modal-message"></p>
		<div class="fanfic-modal-actions">
			<button type="button" id="confirm-delete" class="fanfic-button-danger">
				<?php esc_html_e( 'Yes, Delete', 'fanfiction-manager' ); ?>
			</button>
			<button type="button" id="cancel-delete" class="fanfic-button-secondary">
				<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Make Story Visible Prompt Modal -->
<?php endif; ?>

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<?php if ( $is_edit_mode ) : ?>
			<li class="fanfic-breadcrumb-item">
				<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
			</li>
			<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
				<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
			</li>
		<?php else : ?>
			<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
				<?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
			</li>
		<?php endif; ?>
	</ol>
</nav>

<!-- Inline Script for Notice Dismissal and Delete Confirmation -->
<script>
(function() {
	// Close button functionality for notices
	document.addEventListener('DOMContentLoaded', function() {
		var closeButtons = document.querySelectorAll('.fanfic-notice-close');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				var notice = this.closest('.fanfic-success-notice, .fanfic-error-notice');
				if (notice) {
					notice.style.display = 'none';
				}
			});
		});

		<?php if ( $is_edit_mode ) : ?>
		// Change detection for Update buttons
		var form = document.getElementById('fanfic-story-form');
		var updateBtn = document.getElementById('update-btn');
		var updateDraftBtn = document.getElementById('update-draft-btn');

		if (form && (updateBtn || updateDraftBtn)) {
			var originalTitle = form.getAttribute('data-original-title') || '';
			var originalContent = form.getAttribute('data-original-content') || '';
			var originalGenres = form.getAttribute('data-original-genres') || '';
			var originalStatus = form.getAttribute('data-original-status') || '';
			var originalImage = form.getAttribute('data-original-image') || '';

			function checkForChanges() {
				var titleField = document.getElementById('fanfic_story_title');
				var contentField = document.getElementById('fanfic_story_intro');
				var statusField = document.getElementById('fanfic_story_status');
				var imageField = document.getElementById('fanfic_story_image');
				var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]:checked');

				var currentTitle = titleField ? titleField.value : '';
				var currentContent = contentField ? contentField.value : '';
				var currentStatus = statusField ? statusField.value : '';
				var currentImage = imageField ? imageField.value : '';
				var currentGenres = Array.from(genreCheckboxes).map(function(cb) { return cb.value; }).sort().join(',');

				var hasChanges = (currentTitle !== originalTitle) ||
								(currentContent !== originalContent) ||
								(currentStatus !== originalStatus) ||
								(currentImage !== originalImage) ||
								(currentGenres !== originalGenres);

				if (updateBtn) {
					updateBtn.disabled = !hasChanges;
				}
				if (updateDraftBtn) {
					updateDraftBtn.disabled = !hasChanges;
				}
			}

			// Attach event listeners
			var titleField = document.getElementById('fanfic_story_title');
			var contentField = document.getElementById('fanfic_story_intro');
			var statusField = document.getElementById('fanfic_story_status');
			var imageField = document.getElementById('fanfic_story_image');
			var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]');

			if (titleField) titleField.addEventListener('input', checkForChanges);
			if (contentField) contentField.addEventListener('input', checkForChanges);
			if (statusField) statusField.addEventListener('change', checkForChanges);
			if (imageField) imageField.addEventListener('input', checkForChanges);
			genreCheckboxes.forEach(function(checkbox) {
				checkbox.addEventListener('change', checkForChanges);
			});

			// Initial check on page load
			checkForChanges();
		}

		// Delete story confirmation
		var deleteStoryButton = document.getElementById('delete-story-button');

		if (deleteStoryButton) {
			deleteStoryButton.addEventListener('click', function() {
				var storyId = this.getAttribute('data-story-id');
				var storyTitle = this.getAttribute('data-story-title');
				var buttonElement = this;

				var confirmed = confirm(FanficMessages.deleteStory);
				if (confirmed) {
					// Disable button to prevent double-clicks
					buttonElement.disabled = true;
					buttonElement.textContent = FanficMessages.deleting;

					// Prepare AJAX request
					var formData = new FormData();
					formData.append('action', 'fanfic_story_delete');
					formData.append('story_id', storyId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_story' ); ?>');

					// Send AJAX request
					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data.success) {
							// Story deleted successfully - redirect to dashboard
							window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>';
						} else {
							// Re-enable button and show error
							buttonElement.disabled = false;
							buttonElement.textContent = FanficMessages.delete;
							alert(data.data.message || FanficMessages.errorDeletingStory);
						}
					})
					.catch(function(error) {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.delete;
						alert(FanficMessages.errorDeletingStory);
						console.error('Error deleting story:', error);
					});
				}
			});
		}

		// Chapter unpublish buttons with AJAX
		var chapterUnpublishButtons = document.querySelectorAll('.fanfic-unpublish-chapter');
		chapterUnpublishButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var chapterTitle = this.getAttribute('data-chapter-title');
				var chapterId = this.getAttribute('data-chapter-id');
				var storyId = this.getAttribute('data-story-id');
				var buttonElement = this;
				var rowElement = buttonElement.closest('tr');

				// Check if this is the last chapter via AJAX
				var checkFormData = new FormData();
				checkFormData.append('action', 'fanfic_check_last_chapter');
				checkFormData.append('chapter_id', chapterId);
				checkFormData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_check_last_chapter' ); ?>');

				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: checkFormData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(checkData) {
					var confirmMessage = '<?php esc_html_e( 'Are you sure you want to unpublish', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?';

					// If this is the last chapter, add warning
					if (checkData.success && checkData.data.is_last_chapter) {
						confirmMessage += '\n\n<?php esc_html_e( 'WARNING: This is your last published chapter/prologue. Unpublishing it will automatically hide your story from readers (Draft status).', 'fanfiction-manager' ); ?>';
					}

					if (!confirm(confirmMessage)) {
						return; // User cancelled
					}

					// User confirmed - proceed with unpublish
					buttonElement.disabled = true;
					buttonElement.textContent = FanficMessages.unpublishing;

					// Prepare AJAX request
					var formData = new FormData();
					formData.append('action', 'fanfic_unpublish_chapter');
					formData.append('chapter_id', chapterId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_unpublish_chapter' ); ?>');

					// Send AJAX request
					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data.success) {
							// Show alert if story was auto-drafted
							if (data.data.story_auto_drafted) {
								alert(FanficMessages.unpublishChapterAutoDraftAlert);
							}
							// Reload page to show updated status
							location.reload();
						} else {
							// Re-enable button and show error
							buttonElement.disabled = false;
							buttonElement.textContent = FanficMessages.unpublish;
							alert(data.data.message || FanficMessages.errorUnpublishingChapter);
						}
					})
					.catch(function(error) {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.unpublish;
						alert(FanficMessages.errorUnpublishingChapter);
						console.error('Error:', error);
					});
				})
				.catch(function(error) {
					console.error('Error checking last chapter:', error);
					// Fall back to simple confirmation
					if (confirm('<?php esc_html_e( 'Are you sure you want to unpublish', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
						// Proceed with unpublish even if check failed
						buttonElement.disabled = true;
						buttonElement.textContent = FanficMessages.unpublishing;

						var formData = new FormData();
						formData.append('action', 'fanfic_unpublish_chapter');
						formData.append('chapter_id', chapterId);
						formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_unpublish_chapter' ); ?>');

						fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							credentials: 'same-origin',
							body: formData
						})
						.then(function(response) {
							return response.json();
						})
						.then(function(data) {
							if (data.success) {
								if (data.data.story_auto_drafted) {
									alert(FanficMessages.unpublishChapterAutoDraftAlert);
								}
								location.reload();
							} else {
								buttonElement.disabled = false;
								buttonElement.textContent = FanficMessages.unpublish;
								alert(data.data.message || FanficMessages.errorUnpublishingChapter);
							}
						});
					}
				});
			});
		});

		// Chapter publish buttons with AJAX and validation
		var chapterPublishButtons = document.querySelectorAll('.fanfic-publish-chapter');
		chapterPublishButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var chapterTitle = this.getAttribute('data-chapter-title');
				var chapterId = this.getAttribute('data-chapter-id');
				var buttonElement = this;
				var rowElement = buttonElement.closest('tr');

				// Disable button to prevent double-clicks
				buttonElement.disabled = true;
				buttonElement.textContent = FanficMessages.publishing;

				// Prepare AJAX request
				var formData = new FormData();
				formData.append('action', 'fanfic_publish_chapter');
				formData.append('chapter_id', chapterId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_publish_chapter' ); ?>');

				// Send AJAX request
				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						// Check if story became publishable
						if (data.data.story_became_publishable) {
							// Redirect to edit page with publish prompt parameter
							window.location.href = '<?php echo esc_js( fanfic_get_edit_story_url( $story_id ) ); ?>?show_publish_prompt=1';
						} else {
							// Just reload to show updated chapter status
							location.reload();
						}
					} else {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.publish;

						// Show validation errors if present
						if (data.data && data.data.errors && data.data.errors.length > 0) {
							// Build error message from server-provided messages
							var errorMessage = data.data.message + '\n\n';
							data.data.errors.forEach(function(message) {
								errorMessage += '- ' + message + '\n';
							});
							errorMessage += '\n' + FanficMessages.clickEditToFix;
							alert(errorMessage);
						} else {
							alert(data.data.message || FanficMessages.errorPublishingChapter);
						}
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					buttonElement.disabled = false;
					buttonElement.textContent = FanficMessages.publish;
					alert(FanficMessages.errorPublishingChapter);
					console.error('Error:', error);
				});
			});
		});

		// Chapter delete buttons with AJAX and last chapter warning
		var chapterDeleteButtons = document.querySelectorAll('[data-chapter-id]');
		chapterDeleteButtons.forEach(function(button) {
			if (button.classList.contains('fanfic-button-danger')) {
				button.addEventListener('click', function() {
					var chapterTitle = this.getAttribute('data-chapter-title');
					var chapterId = this.getAttribute('data-chapter-id');
					var buttonElement = this;
					var rowElement = buttonElement.closest('tr');

					// First check if this is the last chapter/prologue
					var checkFormData = new FormData();
					checkFormData.append('action', 'fanfic_check_last_chapter');
					checkFormData.append('chapter_id', chapterId);
					checkFormData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: checkFormData
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(checkData) {
						var isLastChapter = checkData.success && checkData.data.is_last_chapter;
						var confirmMessage = '<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?';

						if (isLastChapter) {
							confirmMessage += '\n\n<?php esc_html_e( 'WARNING: This is your last chapter/prologue. Deleting it will automatically set your story to DRAFT status, making it invisible to readers. Epilogues alone are not enough to keep a story published.', 'fanfiction-manager' ); ?>';
						}

						if (confirm(confirmMessage)) {
							// Disable the button to prevent double-clicks
							buttonElement.disabled = true;
							buttonElement.textContent = FanficMessages.deleting;

							// Prepare AJAX request
							var formData = new FormData();
							formData.append('action', 'fanfic_delete_chapter');
							formData.append('chapter_id', chapterId);
							formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

							// Send AJAX request
							fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								credentials: 'same-origin',
								body: formData
							})
							.then(function(response) {
								return response.json();
							})
							.then(function(data) {
								if (data.success) {
									// Show message if story was auto-drafted
									if (data.data.story_auto_drafted) {
										alert('<?php esc_html_e( 'Chapter deleted. Your story has been set to DRAFT because it no longer has any chapters or prologues.', 'fanfiction-manager' ); ?>');
									}

									// Add fade-out animation
									rowElement.style.transition = 'opacity 0.5s ease-out';
									rowElement.style.opacity = '0';

									// Remove row after animation completes
									setTimeout(function() {
										rowElement.remove();

										// Check if there are any chapters left
										var tableBody = document.querySelector('.fanfic-chapters-table tbody');
										if (tableBody && tableBody.children.length === 0) {
											// Reload page to show "no chapters" state
											window.location.reload();
										}
									}, 500);
								} else {
									// Re-enable button and show error
									buttonElement.disabled = false;
									buttonElement.textContent = FanficMessages.delete;
									alert(data.data.message || FanficMessages.errorDeletingChapter);
								}
							})
							.catch(function(error) {
								// Re-enable button and show error
								buttonElement.disabled = false;
								buttonElement.textContent = '<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>';
								alert(FanficMessages.errorDeletingChapter);
								console.error('Error:', error);
							});
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						// Fall back to simple confirmation
						if (confirm('<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
							// Proceed with deletion
							buttonElement.disabled = true;
							buttonElement.textContent = FanficMessages.deleting;

							var formData = new FormData();
							formData.append('action', 'fanfic_delete_chapter');
							formData.append('chapter_id', chapterId);
							formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

							fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								credentials: 'same-origin',
								body: formData
							})
							.then(function(response) {
								return response.json();
							})
							.then(function(data) {
								if (data.success) {
									rowElement.style.transition = 'opacity 0.5s ease-out';
									rowElement.style.opacity = '0';
									setTimeout(function() {
										rowElement.remove();
										var tableBody = document.querySelector('.fanfic-chapters-table tbody');
										if (tableBody && tableBody.children.length === 0) {
											window.location.reload();
										}
									}, 500);
								} else {
									buttonElement.disabled = false;
									buttonElement.textContent = FanficMessages.delete;
									alert(data.data.message || FanficMessages.errorDeletingChapter);
								}
							});
						}
					});
				});
			}
		});

		// Handle auto-draft warning from chapter deletion redirect
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('story_auto_drafted') === '1') {
			var warningModal = document.getElementById('fanfic-story-auto-draft-warning');
			var storyTitleEl = document.getElementById('fanfic-story-warning-title');
			if (warningModal && storyTitleEl) {
				var storyTitle = urlParams.get('story_title');
				if (storyTitle) {
					storyTitleEl.textContent = decodeURIComponent(storyTitle);
				}
				warningModal.classList.add('show');
				// Remove the parameter from URL
				urlParams.delete('story_auto_drafted');
				urlParams.delete('story_title');
				urlParams.delete('chapter_deleted');
				urlParams.delete('story_id');
				var newUrl = window.location.pathname;
				if (urlParams.toString()) {
					newUrl += '?' + urlParams.toString();
				}
				window.history.replaceState({}, '', newUrl);
			}
		}
		<?php endif; ?>
	});
})();
</script>
