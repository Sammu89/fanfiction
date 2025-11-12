<?php
/**
 * Stories Table Class
 *
 * Extends WP_List_Table to display and manage all fanfiction stories in the admin interface.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Fanfic_Stories_Table
 *
 * Handles the display and management of fanfiction stories using WP_List_Table.
 *
 * @since 1.0.0
 */
class Fanfic_Stories_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'story',
				'plural'   => 'stories',
				'ajax'     => true,
			)
		);

		// Handle bulk actions
		$this->process_bulk_action();

		// Handle individual actions
		$this->process_single_action();
	}

	/**
	 * Get table columns
	 *
	 * @since 1.0.0
	 * @return array Column names and labels
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'title'            => __( 'Story Title', 'fanfiction-manager' ),
			'author'           => __( 'Author', 'fanfiction-manager' ),
			'chapter_count'    => __( 'Chapter Count', 'fanfiction-manager' ),
			'status'           => __( 'Status', 'fanfiction-manager' ),
			'publication'      => __( 'Publication Status', 'fanfiction-manager' ),
			'views'            => __( 'Views', 'fanfiction-manager' ),
			'genre'            => __( 'Genre', 'fanfiction-manager' ),
			'average_rating'   => __( 'Average Rating', 'fanfiction-manager' ),
			'last_updated'     => __( 'Last Updated', 'fanfiction-manager' ),
			'actions'          => __( 'Actions', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 * @return array Sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'title'          => array( 'title', false ),
			'author'         => array( 'author', false ),
			'chapter_count'  => array( 'chapter_count', false ),
			'status'         => array( 'status', false ),
			'views'          => array( 'views', false ),
			'average_rating' => array( 'average_rating', false ),
			'last_updated'   => array( 'last_updated', true ), // Default sort
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 * @return array Bulk actions
	 */
	public function get_bulk_actions() {
		return array(
			'delete'         => __( 'Delete', 'fanfiction-manager' ),
			'publish'        => __( 'Publish', 'fanfiction-manager' ),
			'set_draft'      => __( 'Set to Draft', 'fanfiction-manager' ),
			'apply_genre'    => __( 'Apply Genre', 'fanfiction-manager' ),
			'change_status'  => __( 'Change Status', 'fanfiction-manager' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Checkbox HTML
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="story[]" value="%d" />',
			absint( $item->ID )
		);
	}

	/**
	 * Render Title column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Title HTML with link and row actions
	 */
	public function column_title( $item ) {
		$story_id = absint( $item->ID );
		$title = esc_html( $item->post_title );

		if ( empty( $title ) ) {
			$title = __( '(no title)', 'fanfiction-manager' );
		}

		// Get dashboard URL for editing (frontend dashboard)
		$edit_url = fanfic_get_edit_story_url( $story_id );
		$view_url = get_permalink( $story_id );

		$output = sprintf(
			'<strong><a href="%s" target="_blank">%s</a></strong>',
			esc_url( $edit_url ),
			$title
		);

		// Add row actions
		$actions = array(
			'edit' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'fanfiction-manager' )
			),
			'view' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $view_url ),
				__( 'View', 'fanfiction-manager' )
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( wp_nonce_url(
					add_query_arg(
						array(
							'page'     => 'fanfiction-manager',
							'action'   => 'delete',
							'story_id' => $story_id,
						),
						admin_url( 'admin.php' )
					),
					'fanfic_story_action'
				) ),
				esc_js( __( 'Are you sure you want to delete this story and all its chapters?', 'fanfiction-manager' ) ),
				__( 'Delete', 'fanfiction-manager' )
			),
		);

		$output .= $this->row_actions( $actions );

		return $output;
	}

	/**
	 * Render Author column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Author HTML with link to profile
	 */
	public function column_author( $item ) {
		$author_id = absint( $item->post_author );
		$author = get_user_by( 'id', $author_id );

		if ( ! $author ) {
			return sprintf(
				'<span class="deleted-user">%s</span>',
				__( '[Deleted User]', 'fanfiction-manager' )
			);
		}

		// Link to author's frontend profile
		$author_url = fanfic_get_user_profile_url( $author );

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $author_url ),
			esc_html( $author->display_name )
		);
	}

	/**
	 * Render Chapter Count column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Chapter count (excluding prologue/epilogue)
	 */
	public function column_chapter_count( $item ) {
		return absint( $item->chapter_count );
	}

	/**
	 * Render Status column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Status label from taxonomy
	 */
	public function column_status( $item ) {
		$story_id = absint( $item->ID );
		$statuses = get_the_terms( $story_id, 'fanfiction_status' );

		if ( empty( $statuses ) || is_wp_error( $statuses ) ) {
			return '<span class="status-unknown">' . __( 'Unknown', 'fanfiction-manager' ) . '</span>';
		}

		$status = reset( $statuses );
		return esc_html( $status->name );
	}

	/**
	 * Render Publication Status column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Publication status badge with icon
	 */
	public function column_publication( $item ) {
		$post_status = $item->post_status;

		if ( 'publish' === $post_status ) {
			return '<span class="status-badge status-published"><span class="dashicons dashicons-yes-alt"></span> ' . __( 'Published', 'fanfiction-manager' ) . '</span>';
		}

		return '<span class="status-badge status-draft"><span class="dashicons dashicons-edit"></span> ' . __( 'Draft', 'fanfiction-manager' ) . '</span>';
	}

	/**
	 * Render Views column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Total view count
	 */
	public function column_views( $item ) {
		$story_id = absint( $item->ID );
		$views = Fanfic_Views::get_story_views( $story_id );
		return number_format_i18n( $views );
	}

	/**
	 * Render Genre column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Comma-separated list of genres
	 */
	public function column_genre( $item ) {
		$story_id = absint( $item->ID );
		$genres = get_the_terms( $story_id, 'fanfiction_genre' );

		if ( empty( $genres ) || is_wp_error( $genres ) ) {
			return '<span class="genre-none">' . __( 'None', 'fanfiction-manager' ) . '</span>';
		}

		$genre_names = array_map( function( $genre ) {
			return esc_html( $genre->name );
		}, $genres );

		return implode( ', ', $genre_names );
	}

	/**
	 * Render Average Rating column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Average rating (mean of all chapter ratings)
	 */
	public function column_average_rating( $item ) {
		$story_id = absint( $item->ID );
		$rating_data = Fanfic_Rating_System::get_story_rating( $story_id );

		if ( ! $rating_data || $rating_data->total_votes === 0 ) {
			return '<span class="rating-none">' . __( 'No ratings', 'fanfiction-manager' ) . '</span>';
		}

		$rating = $rating_data->average_rating;
		$count = $rating_data->total_votes;

		return sprintf(
			'<span class="rating-display" title="%s">%.1f (%d)</span>',
			/* translators: %d: Number of ratings */
			esc_attr( sprintf( _n( '%d rating', '%d ratings', $count, 'fanfiction-manager' ), $count ) ),
			$rating,
			$count
		);
	}

	/**
	 * Render Last Updated column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Last updated date
	 */
	public function column_last_updated( $item ) {
		$date = strtotime( $item->post_modified );
		$time_diff = human_time_diff( $date, current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s %s</abbr>',
			esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) ),
			$time_diff,
			__( 'ago', 'fanfiction-manager' )
		);
	}

	/**
	 * Render Actions column
	 *
	 * @since 1.0.0
	 * @param object $item Story item
	 * @return string Actions dropdown HTML
	 */
	public function column_actions( $item ) {
		$story_id = absint( $item->ID );
		$edit_url = fanfic_get_edit_story_url( $story_id );
		$view_url = get_permalink( $story_id );
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'     => 'fanfiction-manager',
					'action'   => 'delete',
					'story_id' => $story_id,
				),
				admin_url( 'admin.php' )
			),
			'fanfic_story_action'
		);

		$actions = array();

		$actions[] = sprintf(
			'<a href="%s" class="button button-small" target="_blank">%s</a>',
			esc_url( $edit_url ),
			__( 'Edit', 'fanfiction-manager' )
		);

		$actions[] = sprintf(
			'<a href="%s" class="button button-small" target="_blank">%s</a>',
			esc_url( $view_url ),
			__( 'View', 'fanfiction-manager' )
		);

		$actions[] = sprintf(
			'<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $delete_url ),
			esc_js( __( 'Are you sure you want to delete this story and all its chapters?', 'fanfiction-manager' ) ),
			__( 'Delete', 'fanfiction-manager' )
		);

		return implode( ' ', $actions );
	}

	/**
	 * Default column renderer
	 *
	 * @since 1.0.0
	 * @param object $item        Story item
	 * @param string $column_name Column name
	 * @return string Column content
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Prepare table items
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		// Set column headers
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns
			$this->get_sortable_columns(),
		);

		// Get current page
		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		// Build query arguments
		$args = array(
			'post_type'      => 'fanfiction_story',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'fields'         => 'all',
		);

		// Handle search
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		// Filter by author
		if ( isset( $_REQUEST['author_filter'] ) && ! empty( $_REQUEST['author_filter'] ) && 'all' !== $_REQUEST['author_filter'] ) {
			$args['author'] = absint( $_REQUEST['author_filter'] );
		}

		// Filter by status taxonomy
		if ( isset( $_REQUEST['status_filter'] ) && ! empty( $_REQUEST['status_filter'] ) && 'all' !== $_REQUEST['status_filter'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'fanfiction_status',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ),
				),
			);
		}

		// Filter by publication status
		if ( isset( $_REQUEST['publication_filter'] ) && 'include_drafts' === $_REQUEST['publication_filter'] ) {
			$args['post_status'] = array( 'publish', 'draft' );
		} else {
			$args['post_status'] = 'publish';
		}

		// Handle sorting
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_updated';
		$order = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		// Map sortable columns to WP_Query parameters
		switch ( $orderby ) {
			case 'title':
				$args['orderby'] = 'title';
				$args['order'] = $order;
				break;

			case 'author':
				$args['orderby'] = 'author';
				$args['order'] = $order;
				break;

			case 'last_updated':
			default:
				$args['orderby'] = 'modified';
				$args['order'] = $order;
				break;
		}

		// Get stories (without special sorting first)
		if ( ! in_array( $orderby, array( 'chapter_count', 'views', 'average_rating' ), true ) ) {
			$query = new WP_Query( $args );
			$stories = $query->posts;
			$total_items = $query->found_posts;
		} else {
			// For special sorting (chapter_count, views, average_rating), we need custom logic
			// First, get all matching stories
			$args['posts_per_page'] = -1;
			$args['offset'] = 0;
			unset( $args['orderby'] );
			unset( $args['order'] );

			$query = new WP_Query( $args );
			$all_stories = $query->posts;
			$total_items = $query->found_posts;

			// Add computed data for sorting
			foreach ( $all_stories as $story ) {
				$story_id = $story->ID;

				// Count chapters (excluding prologue/epilogue)
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_fanfic_chapter_type',
							'value'   => 'chapter',
							'compare' => '=',
						),
					),
				) );
				$story->chapter_count = count( $chapters );

				if ( 'views' === $orderby ) {
					$story->views_count = Fanfic_Views::get_story_views( $story_id );
				}

				if ( 'average_rating' === $orderby ) {
					$rating_data = Fanfic_Rating_System::get_story_rating( $story_id );
					$story->rating = $rating_data ? $rating_data->average_rating : 0;
				}
			}

			// Sort based on computed values
			if ( 'chapter_count' === $orderby ) {
				usort( $all_stories, function( $a, $b ) use ( $order ) {
					return 'ASC' === $order ? $a->chapter_count - $b->chapter_count : $b->chapter_count - $a->chapter_count;
				} );
			} elseif ( 'views' === $orderby ) {
				usort( $all_stories, function( $a, $b ) use ( $order ) {
					return 'ASC' === $order ? $a->views_count - $b->views_count : $b->views_count - $a->views_count;
				} );
			} elseif ( 'average_rating' === $orderby ) {
				usort( $all_stories, function( $a, $b ) use ( $order ) {
					$diff = $a->rating - $b->rating;
					return 'ASC' === $order ? ( $diff > 0 ? 1 : -1 ) : ( $diff > 0 ? -1 : 1 );
				} );
			}

			// Apply pagination
			$stories = array_slice( $all_stories, $offset, $per_page );
		}

		// Add chapter count to all stories
		foreach ( $stories as $story ) {
			if ( ! isset( $story->chapter_count ) ) {
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story->ID,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_fanfic_chapter_type',
							'value'   => 'chapter',
							'compare' => '=',
						),
					),
				) );
				$story->chapter_count = count( $chapters );
			}
		}

		$this->items = $stories;

		// Set pagination
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Display extra table navigation (filters)
	 *
	 * @since 1.0.0
	 * @param string $which Top or bottom navigation
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php $this->author_filter_dropdown(); ?>
			<?php $this->status_filter_dropdown(); ?>
			<?php $this->publication_filter_dropdown(); ?>
			<?php submit_button( __( 'Filter', 'fanfiction-manager' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
		// Display dropdowns for bulk actions that need additional input
		$this->genre_bulk_dropdown();
		$this->status_bulk_dropdown();
	}

	/**
	 * Display author filter dropdown
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function author_filter_dropdown() {
		$current_author = isset( $_REQUEST['author_filter'] ) ? absint( $_REQUEST['author_filter'] ) : 'all';

		// Get all authors with published stories
		// Use capability parameter instead of deprecated 'who' parameter
		$authors = get_users( array(
			'capability' => array( 'edit_posts', 'publish_fanfiction_stories' ),
			'capability__compare' => 'ANY',
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		?>
		<select name="author_filter" id="author-filter">
			<option value="all" <?php selected( $current_author, 'all' ); ?>><?php esc_html_e( 'All Authors', 'fanfiction-manager' ); ?></option>
			<?php foreach ( $authors as $author ) : ?>
				<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $current_author, $author->ID ); ?>>
					<?php echo esc_html( $author->display_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Display status filter dropdown
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function status_filter_dropdown() {
		$current_status = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : 'all';

		// Get all status terms
		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );

		?>
		<select name="status_filter" id="status-filter">
			<option value="all" <?php selected( $current_status, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'fanfiction-manager' ); ?></option>
			<?php
			if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) {
				foreach ( $statuses as $status ) {
					?>
					<option value="<?php echo esc_attr( $status->slug ); ?>" <?php selected( $current_status, $status->slug ); ?>>
						<?php echo esc_html( $status->name ); ?>
					</option>
					<?php
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Display publication status filter dropdown
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function publication_filter_dropdown() {
		$current_filter = isset( $_REQUEST['publication_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['publication_filter'] ) ) : 'published_only';

		?>
		<select name="publication_filter" id="publication-filter">
			<option value="published_only" <?php selected( $current_filter, 'published_only' ); ?>><?php esc_html_e( 'Published Only', 'fanfiction-manager' ); ?></option>
			<option value="include_drafts" <?php selected( $current_filter, 'include_drafts' ); ?>><?php esc_html_e( 'Include Drafts', 'fanfiction-manager' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Display genre bulk action dropdown (hidden, shown via JS)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function genre_bulk_dropdown() {
		// Get all genre terms
		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );

		if ( empty( $genres ) || is_wp_error( $genres ) ) {
			return;
		}

		?>
		<div id="genre-bulk-dropdown" style="display: none; margin-top: 10px;">
			<label for="bulk-genre-select"><?php esc_html_e( 'Select Genre:', 'fanfiction-manager' ); ?></label>
			<select name="bulk_genre" id="bulk-genre-select">
				<option value=""><?php esc_html_e( '-- Select Genre --', 'fanfiction-manager' ); ?></option>
				<?php foreach ( $genres as $genre ) : ?>
					<option value="<?php echo esc_attr( $genre->term_id ); ?>">
						<?php echo esc_html( $genre->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Display status bulk action dropdown (hidden, shown via JS)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function status_bulk_dropdown() {
		// Get all status terms
		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );

		if ( empty( $statuses ) || is_wp_error( $statuses ) ) {
			return;
		}

		?>
		<div id="status-bulk-dropdown" style="display: none; margin-top: 10px;">
			<label for="bulk-status-select"><?php esc_html_e( 'Select Status:', 'fanfiction-manager' ); ?></label>
			<select name="bulk_status" id="bulk-status-select">
				<option value=""><?php esc_html_e( '-- Select Status --', 'fanfiction-manager' ); ?></option>
				<?php foreach ( $statuses as $status ) : ?>
					<option value="<?php echo esc_attr( $status->term_id ); ?>">
						<?php echo esc_html( $status->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Show/hide genre dropdown based on bulk action selection
				$('#bulk-action-selector-top, #bulk-action-selector-bottom').on('change', function() {
					var action = $(this).val();
					if (action === 'apply_genre') {
						$('#genre-bulk-dropdown').show();
					} else {
						$('#genre-bulk-dropdown').hide();
					}
					if (action === 'change_status') {
						$('#status-bulk-dropdown').show();
					} else {
						$('#status-bulk-dropdown').hide();
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Process single actions
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function process_single_action() {
		// Check if action is set
		if ( ! isset( $_REQUEST['action'] ) || 'delete' !== $_REQUEST['action'] ) {
			return;
		}

		$story_id = isset( $_REQUEST['story_id'] ) ? absint( $_REQUEST['story_id'] ) : 0;

		if ( ! $story_id ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'fanfic_story_action' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) );
		}

		// Delete story and all chapters
		$this->delete_story( $story_id );

		$this->add_notice( __( 'Story deleted successfully.', 'fanfiction-manager' ), 'success' );

		// Redirect to clean URL
		wp_safe_redirect( admin_url( 'admin.php?page=fanfiction-manager' ) );
		exit;
	}

	/**
	 * Process bulk actions
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function process_bulk_action() {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Check if stories are selected
		if ( ! isset( $_REQUEST['story'] ) || ! is_array( $_REQUEST['story'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'bulk-stories' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) );
		}

		$story_ids = array_map( 'absint', wp_unslash( $_REQUEST['story'] ) );

		switch ( $action ) {
			case 'delete':
				foreach ( $story_ids as $story_id ) {
					$this->delete_story( $story_id );
				}
				$this->add_notice(
					sprintf(
						/* translators: %d: Number of stories */
						_n( '%d story deleted.', '%d stories deleted.', count( $story_ids ), 'fanfiction-manager' ),
						count( $story_ids )
					),
					'success'
				);
				break;

			case 'publish':
				foreach ( $story_ids as $story_id ) {
					wp_update_post( array(
						'ID'          => $story_id,
						'post_status' => 'publish',
					) );
				}
				$this->add_notice(
					sprintf(
						/* translators: %d: Number of stories */
						_n( '%d story published.', '%d stories published.', count( $story_ids ), 'fanfiction-manager' ),
						count( $story_ids )
					),
					'success'
				);
				break;

			case 'set_draft':
				foreach ( $story_ids as $story_id ) {
					wp_update_post( array(
						'ID'          => $story_id,
						'post_status' => 'draft',
					) );
				}
				$this->add_notice(
					sprintf(
						/* translators: %d: Number of stories */
						_n( '%d story set to draft.', '%d stories set to draft.', count( $story_ids ), 'fanfiction-manager' ),
						count( $story_ids )
					),
					'success'
				);
				break;

			case 'apply_genre':
				$genre_id = isset( $_REQUEST['bulk_genre'] ) ? absint( $_REQUEST['bulk_genre'] ) : 0;
				if ( $genre_id ) {
					foreach ( $story_ids as $story_id ) {
						wp_set_post_terms( $story_id, array( $genre_id ), 'fanfiction_genre', true );
					}
					$this->add_notice(
						sprintf(
							/* translators: %d: Number of stories */
							_n( 'Genre applied to %d story.', 'Genre applied to %d stories.', count( $story_ids ), 'fanfiction-manager' ),
							count( $story_ids )
						),
						'success'
					);
				} else {
					$this->add_notice( __( 'Please select a genre.', 'fanfiction-manager' ), 'error' );
				}
				break;

			case 'change_status':
				$status_id = isset( $_REQUEST['bulk_status'] ) ? absint( $_REQUEST['bulk_status'] ) : 0;
				if ( $status_id ) {
					foreach ( $story_ids as $story_id ) {
						wp_set_post_terms( $story_id, array( $status_id ), 'fanfiction_status', false );
					}
					$this->add_notice(
						sprintf(
							/* translators: %d: Number of stories */
							_n( 'Status changed for %d story.', 'Status changed for %d stories.', count( $story_ids ), 'fanfiction-manager' ),
							count( $story_ids )
						),
						'success'
					);
				} else {
					$this->add_notice( __( 'Please select a status.', 'fanfiction-manager' ), 'error' );
				}
				break;
		}

		// Redirect to clean URL
		wp_safe_redirect( admin_url( 'admin.php?page=fanfiction-manager' ) );
		exit;
	}

	/**
	 * Delete a story and all its chapters
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID
	 * @return void
	 */
	private function delete_story( $story_id ) {
		global $wpdb;

		$story_id = absint( $story_id );

		// Get all chapters
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		// Delete all chapters (including ratings, comments, etc.)
		foreach ( $chapters as $chapter_id ) {
			// Delete ratings using prepared statement
			$wpdb->delete(
				$wpdb->prefix . 'fanfic_ratings',
				array( 'chapter_id' => $chapter_id ),
				array( '%d' )
			);

			// Delete chapter post
			wp_delete_post( $chapter_id, true );
		}

		// Delete bookmarks using prepared statement
		$wpdb->delete(
			$wpdb->prefix . 'fanfic_bookmarks',
			array( 'story_id' => $story_id ),
			array( '%d' )
		);

		// Delete story post
		wp_delete_post( $story_id, true );

		// Clear caches
		delete_transient( 'fanfic_story_views_' . $story_id );
		delete_transient( 'fanfic_story_rating_' . $story_id );
		delete_transient( 'fanfic_story_rating_count_' . $story_id );
	}

	/**
	 * Add admin notice
	 *
	 * @since 1.0.0
	 * @param string $message Notice message
	 * @param string $type    Notice type (success, error, warning, info)
	 * @return void
	 */
	private function add_notice( $message, $type = 'info' ) {
		$notices = get_transient( 'fanfic_stories_notices' );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( 'fanfic_stories_notices', $notices, 60 );
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function display_notices() {
		$notices = get_transient( 'fanfic_stories_notices' );

		if ( ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}

		delete_transient( 'fanfic_stories_notices' );
	}
}
