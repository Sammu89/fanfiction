<div class="fanfic-template-wrapper">
<?php
/**
 * Template Name: Edit Story
 * Description: Form for editing an existing fanfiction story
 *
 * This template displays:
 * - Story edit form
 * - Chapters management section
 * - Delete story option (danger zone)
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You must be logged in to edit stories.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// Get story ID from URL parameter
$story_id = isset( $_GET['story_id'] ) ? absint( $_GET['story_id'] ) : 0;

// Validate story exists and user has permission
if ( ! $story_id || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Access Denied: You do not have permission to edit this story, or the story does not exist.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// Get story title for breadcrumb
$story = get_post( $story_id );
$story_title = $story ? $story->post_title : __( 'Unknown Story', 'fanfiction-manager' );
?>

<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="fanfic-main-content" class="fanfic-main-content" role="main">

<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Story updated successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['chapter_added'] ) && $_GET['chapter_added'] === 'true' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Chapter added successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<!-- Page Header -->
<header class="fanfic-page-header">
	<h1 class="fanfic-page-title"><?php esc_html_e( 'Edit Your Story', 'fanfiction-manager' ); ?></h1>
	<p class="fanfic-page-description">
		<?php esc_html_e( 'Update your story details below. Changes will be saved immediately.', 'fanfiction-manager' ); ?>
	</p>
</header>

<!-- Info Box -->
<div class="fanfic-info-box" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<p>
		<?php esc_html_e( 'Your story must have an introduction, at least one chapter, a genre, and a status to be published.', 'fanfiction-manager' ); ?>
	</p>
</div>

<!-- Story Edit Form -->
<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="edit-form-heading">
	<h2 id="edit-form-heading"><?php esc_html_e( 'Story Details', 'fanfiction-manager' ); ?></h2>

	<!-- Form Shortcode -->
	[author-edit-story-form story_id="<?php echo absint( $story_id ); ?>"]
</section>

<!-- Chapters Management Section -->
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
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
		);

		$chapters_query = new WP_Query( $chapters_args );

		if ( $chapters_query->have_posts() ) :
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
					$chapter_number = 0;
					while ( $chapters_query->have_posts() ) :
						$chapters_query->the_post();
						$chapter_id = get_the_ID();
						$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );

						// Display chapter number or type
						if ( $chapter_type === 'prologue' ) {
							$display_number = __( 'Prologue', 'fanfiction-manager' );
						} elseif ( $chapter_type === 'epilogue' ) {
							$display_number = __( 'Epilogue', 'fanfiction-manager' );
						} else {
							$chapter_number++;
							$display_number = sprintf( __( 'Chapter %d', 'fanfiction-manager' ), $chapter_number );
						}

						// Get word count
						$content = get_the_content();
						$word_count = str_word_count( wp_strip_all_tags( $content ) );

						// Get status
						$status = get_post_status();
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
								<?php the_title(); ?>
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
									<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button-small" aria-label="<?php esc_attr_e( 'View chapter', 'fanfiction-manager' ); ?>">
										<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
									</a>
									<button type="button" class="fanfic-button-small fanfic-button-danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( get_the_title() ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
										<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
									</button>
								</div>
							</td>
						</tr>
					<?php endwhile; ?>
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

<!-- Danger Zone -->
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

<!-- Delete Confirmation Modal -->
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

		// Chapter delete buttons
		var chapterDeleteButtons = document.querySelectorAll('[data-chapter-id]');
		chapterDeleteButtons.forEach(function(button) {
			if (button.classList.contains('fanfic-button-danger')) {
				button.addEventListener('click', function() {
					var chapterTitle = this.getAttribute('data-chapter-title');
					var chapterId = this.getAttribute('data-chapter-id');
					if (confirm('<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
						window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>?action=delete_chapter&chapter_id=' + chapterId + '&story_id=<?php echo absint( $story_id ); ?>&_wpnonce=<?php echo esc_js( wp_create_nonce( 'delete_chapter' ) ); ?>';
					}
				});
			}
		});
	});
})();
</script>

</main>
</div>
