<?php
/**
 * Template Name: Story Form (Unified Create/Edit)
 * Description: Unified form for creating new stories and editing existing ones
 *
 * This template handles both:
 * - Create mode: /fanfiction/create-story/ (no story_id)
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

// Handle POST submission
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['fanfic_story_nonce'] ) ) {
	// Verify nonce
	$nonce_action = 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' );

	if ( ! wp_verify_nonce( $_POST['fanfic_story_nonce'], $nonce_action ) ) {
		wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
	}

	$errors = array();
	$current_user = wp_get_current_user();

	// Get and sanitize form data
	$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
	$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
	$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
	$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
	$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';

	// Validate
	if ( empty( $title ) ) {
		$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
	}

	if ( empty( $introduction ) ) {
		$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
	}

	if ( ! $status ) {
		$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
	}

	// If errors, store in transient and reload page to show errors
	if ( ! empty( $errors ) ) {
		set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
		wp_safe_redirect( $_SERVER['REQUEST_URI'] );
		exit;
	}

	// ========================================================================
	// CREATE MODE: Insert new post
	// ========================================================================
	if ( ! $is_edit_mode ) {
		// Generate unique slug before creating the post
		$base_slug = sanitize_title( $title );
		$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

		// Create story as draft initially
		$new_story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_name'    => $unique_slug,
			'post_content' => $introduction,
			'post_status'  => 'draft',
			'post_author'  => $current_user->ID,
		) );

		if ( is_wp_error( $new_story_id ) ) {
			$errors[] = $new_story_id->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_safe_redirect( $_SERVER['REQUEST_URI'] );
			exit;
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $new_story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $new_story_id, $status, 'fanfiction_status' );

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $new_story_id, '_fanfic_featured_image', $image_url );
		}

		// Initialize view count
		update_post_meta( $new_story_id, '_fanfic_views', 0 );

		// Redirect to story edit page with action=edit
		$story_permalink = get_permalink( $new_story_id );
		$edit_url = add_query_arg( 'action', 'edit', $story_permalink );
		wp_safe_redirect( $edit_url );
		exit;
	}

	// ========================================================================
	// EDIT MODE: Update existing post
	// ========================================================================
	else {
		// Get save action (draft or publish)
		$save_action = isset( $_POST['fanfic_save_action'] ) ? sanitize_text_field( $_POST['fanfic_save_action'] ) : 'draft';
		$post_status = ( 'publish' === $save_action ) ? 'publish' : 'draft';

		// Update story
		$result = wp_update_post( array(
			'ID'           => $story_id,
			'post_title'   => $title,
			'post_content' => $introduction,
			'post_status'  => $post_status,
		) );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_safe_redirect( $_SERVER['REQUEST_URI'] );
			exit;
		}

		// Update genres
		wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );

		// Update status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Update featured image
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		} else {
			delete_post_meta( $story_id, '_fanfic_featured_image' );
		}

		// Redirect back with success message
		$redirect_url = add_query_arg( 'success', 'true', $_SERVER['REQUEST_URI'] );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

?>

<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

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

<!-- Page Header -->
<header class="fanfic-page-header">
	<h1 class="fanfic-page-title">
		<?php echo $is_edit_mode ? esc_html__( 'Edit Your Story', 'fanfiction-manager' ) : esc_html__( 'Create a New Story', 'fanfiction-manager' ); ?>
	</h1>
	<p class="fanfic-page-description">
		<?php echo $is_edit_mode ? esc_html__( 'Update your story details below. Changes will be saved immediately.', 'fanfiction-manager' ) : esc_html__( 'Tell us about your story! Fill out the form below to get started.', 'fanfiction-manager' ); ?>
	</p>
</header>

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
			<?php echo Fanfic_Shortcodes_Author_Forms::render_story_form( $story_id ); ?>
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
										<?php endif; ?>
											<button type="button" class="fanfic-button-small fanfic-button-danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
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

		<!-- Danger Zone (Edit mode only) -->
		<section class="fanfic-content-section" class="fanfic-danger-zone" aria-labelledby="danger-heading">
			<h2 id="danger-heading" class="fanfic-danger-title">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php esc_html_e( 'Danger Zone', 'fanfiction-manager' ); ?>
			</h2>

			<div class="fanfic-danger-content">
				<div class="fanfic-danger-info">
					<h3><?php esc_html_e( 'Delete This Story', 'fanfiction-manager' ); ?></h3>
					<p><?php esc_html_e( 'Once you delete a story, there is no going back. All chapters and data will be permanently removed.', 'fanfiction-manager' ); ?></p>
				</div>
				<button type="button" id="delete-story-button" class="fanfic-button-danger" data-story-id="<?php echo absint( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story_title ); ?>">
					<?php esc_html_e( 'Delete This Story', 'fanfiction-manager' ); ?>
				</button>
			</div>

			<p class="fanfic-danger-warning">
				<strong><?php esc_html_e( 'Warning:', 'fanfiction-manager' ); ?></strong>
				<?php esc_html_e( 'This action cannot be undone.', 'fanfiction-manager' ); ?>
			</p>
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

<!-- Publish Story Prompt Modal -->
<div id="publish-prompt-modal" class="fanfic-modal" role="dialog" aria-labelledby="publish-modal-title" aria-modal="true" style="display: none;">
	<div class="fanfic-modal-overlay"></div>
	<div class="fanfic-modal-content">
		<h2 id="publish-modal-title"><?php esc_html_e( 'Ready to Publish?', 'fanfiction-manager' ); ?></h2>
		<p><?php esc_html_e( 'Great! Your story now has its first published chapter. You can now publish your story to make it visible to readers, or keep it as a draft to continue working on it.', 'fanfiction-manager' ); ?></p>
		<div class="fanfic-modal-actions">
			<button type="button" id="publish-story-now" class="fanfic-button-primary" data-story-id="<?php echo absint( $story_id ); ?>">
				<?php esc_html_e( 'Publish Story Now', 'fanfiction-manager' ); ?>
			</button>
			<button type="button" id="keep-as-draft" class="fanfic-button-secondary">
				<?php esc_html_e( 'Keep as Draft', 'fanfiction-manager' ); ?>
			</button>
		</div>
	</div>
</div>
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
		// Delete story confirmation
		var deleteStoryButton = document.getElementById('delete-story-button');
		var modal = document.getElementById('delete-confirm-modal');
		var confirmButton = document.getElementById('confirm-delete');
		var cancelButton = document.getElementById('cancel-delete');
		var modalMessage = document.getElementById('modal-message');

		if (deleteStoryButton) {
			deleteStoryButton.addEventListener('click', function() {
				var storyTitle = this.getAttribute('data-story-title');
				modalMessage.textContent = '<?php esc_html_e( 'Are you sure you want to delete', 'fanfiction-manager' ); ?> "' + storyTitle + '"? <?php esc_html_e( 'This will also delete all chapters.', 'fanfiction-manager' ); ?>';
				modal.style.display = 'block';
			});
		}

		if (cancelButton) {
			cancelButton.addEventListener('click', function() {
				modal.style.display = 'none';
			});
		}

		if (confirmButton) {
			confirmButton.addEventListener('click', function() {
				// Submit delete form (implement via AJAX or form submission)
				// For now, redirect to dashboard with delete parameter
				var storyId = deleteStoryButton.getAttribute('data-story-id');
				window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>?action=delete_story&story_id=' + storyId + '&_wpnonce=<?php echo esc_js( wp_create_nonce( 'delete_story_' . $story_id ) ); ?>';
			});
		}

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
							buttonElement.textContent = '<?php esc_html_e( 'Deleting...', 'fanfiction-manager' ); ?>';

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
									buttonElement.textContent = '<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>';
									alert(data.data.message || '<?php esc_html_e( 'Failed to delete chapter.', 'fanfiction-manager' ); ?>');
								}
							})
							.catch(function(error) {
								// Re-enable button and show error
								buttonElement.disabled = false;
								buttonElement.textContent = '<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>';
								alert('<?php esc_html_e( 'An error occurred while deleting the chapter.', 'fanfiction-manager' ); ?>');
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
							buttonElement.textContent = '<?php esc_html_e( 'Deleting...', 'fanfiction-manager' ); ?>';

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
									buttonElement.textContent = '<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>';
									alert(data.data.message || '<?php esc_html_e( 'Failed to delete chapter.', 'fanfiction-manager' ); ?>');
								}
							});
						}
					});
				});
			}
		});

		// Publish prompt modal
		var publishModal = document.getElementById('publish-prompt-modal');
		var publishNowButton = document.getElementById('publish-story-now');
		var keepDraftButton = document.getElementById('keep-as-draft');

		// Show modal if show_publish_prompt parameter is present
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('show_publish_prompt') === '1' && publishModal) {
			publishModal.style.display = 'block';
		}

		// Handle "Keep as Draft" button
		if (keepDraftButton) {
			keepDraftButton.addEventListener('click', function() {
				publishModal.style.display = 'none';
				// Remove the parameter from URL
				urlParams.delete('show_publish_prompt');
				var newUrl = window.location.pathname;
				if (urlParams.toString()) {
					newUrl += '?' + urlParams.toString();
				}
				window.history.replaceState({}, '', newUrl);
			});
		}

		// Handle "Publish Story Now" button
		if (publishNowButton) {
			publishNowButton.addEventListener('click', function() {
				var storyId = this.getAttribute('data-story-id');

				// Disable button to prevent double-clicks
				publishNowButton.disabled = true;
				publishNowButton.textContent = '<?php esc_html_e( 'Publishing...', 'fanfiction-manager' ); ?>';

				// Prepare AJAX request
				var formData = new FormData();
				formData.append('action', 'fanfic_publish_story');
				formData.append('story_id', storyId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_publish_story' ); ?>');

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
						// Close modal and reload page to show updated status
						publishModal.style.display = 'none';
						window.location.reload();
					} else {
						// Re-enable button and show error
						publishNowButton.disabled = false;
						publishNowButton.textContent = '<?php esc_html_e( 'Publish Story Now', 'fanfiction-manager' ); ?>';
						alert(data.data.message || '<?php esc_html_e( 'Failed to publish story.', 'fanfiction-manager' ); ?>');
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					publishNowButton.disabled = false;
					publishNowButton.textContent = '<?php esc_html_e( 'Publish Story Now', 'fanfiction-manager' ); ?>';
					alert('<?php esc_html_e( 'An error occurred while publishing the story.', 'fanfiction-manager' ); ?>');
					console.error('Error:', error);
				});
			});
		}
		<?php endif; ?>
	});
})();
</script>

</div>
