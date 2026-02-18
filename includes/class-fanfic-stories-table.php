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
	 * A cached list of potential authors.
	 * @var array|null
	 */
	protected $potential_authors = null;
	
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
			'block'          => __( 'Block', 'fanfiction-manager' ),
			'unblock'        => __( 'Unblock', 'fanfiction-manager' ),
			'apply_genre'    => __( 'Change Genre', 'fanfiction-manager' ),
			'change_status'  => __( 'Change Status', 'fanfiction-manager' ),
			'change_author'  => __( 'Change Author', 'fanfiction-manager' ),
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
	 * @since 1.2.0 Added block reason display
	 * @param object $item Story item
	 * @return string Publication status badge with icon
	 */
	public function column_publication( $item ) {
		$post_status = $item->post_status;
		$is_blocked = (bool) get_post_meta( $item->ID, '_fanfic_story_blocked', true );

		if ( $is_blocked ) {
			$block_reason = get_post_meta( $item->ID, '_fanfic_story_blocked_reason', true );
			$reason_labels = self::get_block_reason_labels();
			$reason_label = isset( $reason_labels[ $block_reason ] ) ? $reason_labels[ $block_reason ] : $block_reason;

			$output = '<span class="status-badge status-blocked"><span class="dashicons dashicons-lock"></span> ' . __( 'Blocked', 'fanfiction-manager' ) . '</span>';
			if ( $block_reason && 'manual' !== $block_reason ) {
				$output .= '<br><small class="block-reason" title="' . esc_attr( $reason_label ) . '">' . esc_html( $reason_label ) . '</small>';
			}
			return $output;
		}

		if ( 'publish' === $post_status ) {
			return '<span class="status-badge status-published"><span class="dashicons dashicons-yes-alt"></span> ' . __( 'Published', 'fanfiction-manager' ) . '</span>';
		}

		return '<span class="status-badge status-draft"><span class="dashicons dashicons-edit"></span> ' . __( 'Draft', 'fanfiction-manager' ) . '</span>';
	}

	/**
	 * Get block reason labels
	 *
	 * @since 1.2.0
	 * @return array Associative array of reason codes to labels
	 */
	public static function get_block_reason_labels() {
		return array(
			'manual'              => __( 'Manual Block', 'fanfiction-manager' ),
			'tos_violation'       => __( 'Terms of Service Violation', 'fanfiction-manager' ),
			'copyright'           => __( 'Copyright Infringement', 'fanfiction-manager' ),
			'inappropriate'       => __( 'Inappropriate Content', 'fanfiction-manager' ),
			'spam'                => __( 'Spam or Advertising', 'fanfiction-manager' ),
			'harassment'          => __( 'Harassment or Bullying', 'fanfiction-manager' ),
			'illegal'             => __( 'Illegal Content', 'fanfiction-manager' ),
			'underage'            => __( 'Underage Content', 'fanfiction-manager' ),
			'rating_mismatch'     => __( 'Rating/Warning Mismatch', 'fanfiction-manager' ),
			'user_request'        => __( 'Author Request', 'fanfiction-manager' ),
			'pending_review'      => __( 'Pending Review', 'fanfiction-manager' ),
			'other'               => __( 'Other', 'fanfiction-manager' ),
		);
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
		$views = Fanfic_Interactions::get_story_views( $story_id );
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
		$rating_data = Fanfic_Interactions::get_story_rating( $story_id );

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

		// Pre-fetch potential authors to avoid repeated queries
		if ( null === $this->potential_authors ) {
			$this->potential_authors = get_users( array(
				'role__in' => array(
					'administrator',
					'fanfiction_admin',
					'fanfiction_moderator',
					'fanfiction_author',
					'fanfiction_reader',
				),
				'orderby' => 'display_name',
				'order'   => 'ASC',
			) );
		}

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
		if ( isset( $_REQUEST['publication_filter'] ) && 'published_only' === $_REQUEST['publication_filter'] ) {
			$args['post_status'] = 'publish';
		} else {
			$args['post_status'] = array( 'publish', 'draft' );
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
					$story->views_count = Fanfic_Interactions::get_story_views( $story_id );
				}

				if ( 'average_rating' === $orderby ) {
					$rating_data = Fanfic_Interactions::get_story_rating( $story_id );
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
		?>
		<div class="alignleft actions">
			<button type="button" id="bulk-publish-<?php echo esc_attr($which); ?>" class="button bulk-publish"><?php esc_html_e( 'Publish', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-set-draft-<?php echo esc_attr($which); ?>" class="button bulk-set-draft"><?php esc_html_e( 'Set to Draft', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-block-<?php echo esc_attr($which); ?>" class="button bulk-block"><?php esc_html_e( 'Block', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-unblock-<?php echo esc_attr($which); ?>" class="button bulk-unblock"><?php esc_html_e( 'Unblock', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-change-author-<?php echo esc_attr($which); ?>" class="button bulk-change-author"><?php esc_html_e( 'Change Author', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-apply-genre-<?php echo esc_attr($which); ?>" class="button bulk-apply-genre"><?php esc_html_e( 'Change Genre', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-change-status-<?php echo esc_attr($which); ?>" class="button bulk-change-status"><?php esc_html_e( 'Change Status', 'fanfiction-manager' ); ?></button>
			<button type="button" id="bulk-delete-<?php echo esc_attr($which); ?>" class="button button-link-delete bulk-delete"><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
		</div>
		<div class="alignleft actions" style="display:none;">
			<?php $this->bulk_actions( $which ); ?>
		</div>
		<?php
		if ( 'top' === $which ) {
			// Display dropdowns for legacy bulk actions that need additional input
			$this->genre_bulk_dropdown();
			$this->status_bulk_dropdown();
			// Render the modals
			$this->render_change_author_modal();
			$this->render_apply_genre_modal();
			$this->render_change_status_modal();
			$this->render_block_reason_modal();
		}
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
		$current_filter = isset( $_REQUEST['publication_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['publication_filter'] ) ) : 'include_drafts';

		?>
		<select name="publication_filter" id="publication-filter">
			<option value="published_only" <?php selected( $current_filter, 'published_only' ); ?>><?php esc_html_e( 'Published Only', 'fanfiction-manager' ); ?></option>
			<option value="include_drafts" <?php selected( $current_filter, 'include_drafts' ); ?>><?php esc_html_e( 'All (Published & Drafts)', 'fanfiction-manager' ); ?></option>
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
	 * Render the modal for the bulk change author action.
	 *
	 * @since 1.2.0
	 */
	public function render_change_author_modal() {
		?>
		<div id="fanfic-change-author-modal" style="display:none;" class="fanfic-admin-modal">
			<div class="fanfic-admin-modal-overlay"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Change Story Author', 'fanfiction-manager' ); ?></h2>
				<p class="fanfic-modal-warning"><?php esc_html_e( 'Warning: Changing the author will remove the original author\'s access to edit this story.', 'fanfiction-manager' ); ?></p>
				
				<div class="fanfic-admin-modal-form">
					<label for="fanfic-author-search-input"><?php esc_html_e( 'Search for an author:', 'fanfiction-manager' ); ?></label>
					<input type="text" id="fanfic-author-search-input" placeholder="<?php esc_attr_e( 'Type to search...', 'fanfiction-manager' ); ?>" style="width: 100%; margin-bottom: 10px;">
					<div class="fanfic-author-suggestions" style="display:none;"></div>

					<label for="fanfic-new-author-select"><?php esc_html_e( 'Or select a new author from the list:', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-new-author-select" style="width: 100%;">
						<option value=""><?php esc_html_e( '-- Select Author --', 'fanfiction-manager' ); ?></option>
						<?php if ( ! empty( $this->potential_authors ) ) : ?>
							<?php foreach ( $this->potential_authors as $author ) : ?>
								<option value="<?php echo esc_attr( $author->ID ); ?>" data-name="<?php echo esc_attr( strtolower( $author->display_name ) ); ?>">
									<?php echo esc_html( $author->display_name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<div class="fanfic-admin-modal-message" style="margin-top:10px;"></div>
				</div>

				<div class="fanfic-admin-modal-actions">
					<button type="button" class="button fanfic-admin-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button button-primary" id="fanfic-confirm-author-change"><?php esc_html_e( 'OK', 'fanfiction-manager' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the modal for the bulk apply genre action.
	 *
	 * @since 1.2.0
	 */
	public function render_apply_genre_modal() {
		$genres = get_terms( array( 'taxonomy' => 'fanfiction_genre', 'hide_empty' => false ) );
		?>
		<div id="fanfic-apply-genre-modal" style="display:none;" class="fanfic-admin-modal">
			<div class="fanfic-admin-modal-overlay"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Change Genre for Stories', 'fanfiction-manager' ); ?></h2>
				
				<div class="fanfic-admin-modal-form">
					<label for="fanfic-genre-select"><?php esc_html_e( 'Select one or more genres:', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-genre-select" style="width: 100%;" multiple size="6">
						<?php if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) : ?>
							<?php foreach ( $genres as $genre ) : ?>
								<option value="<?php echo esc_attr( $genre->term_id ); ?>">
									<?php echo esc_html( $genre->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<div class="fanfic-admin-modal-message" style="margin-top:10px;"></div>
				</div>

				<div class="fanfic-admin-modal-actions">
					<button type="button" class="button fanfic-admin-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button button-primary" id="fanfic-confirm-apply-genre"><?php esc_html_e( 'Change Genre', 'fanfiction-manager' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the modal for the bulk change status action.
	 *
	 * @since 1.2.0
	 */
	public function render_change_status_modal() {
		$statuses = get_terms( array( 'taxonomy' => 'fanfiction_status', 'hide_empty' => false ) );
		?>
		<div id="fanfic-change-status-modal" style="display:none;" class="fanfic-admin-modal">
			<div class="fanfic-admin-modal-overlay"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Change Story Status', 'fanfiction-manager' ); ?></h2>

				<div class="fanfic-admin-modal-form">
					<label for="fanfic-status-select"><?php esc_html_e( 'Select a new status:', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-status-select" style="width: 100%;">
						<option value=""><?php esc_html_e( '-- Select Status --', 'fanfiction-manager' ); ?></option>
						<?php if ( ! is_wp_error( $statuses ) && ! empty( $statuses ) ) : ?>
							<?php foreach ( $statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status->term_id ); ?>">
									<?php echo esc_html( $status->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<div class="fanfic-admin-modal-message" style="margin-top:10px;"></div>
				</div>

				<div class="fanfic-admin-modal-actions">
					<button type="button" class="button fanfic-admin-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button button-primary" id="fanfic-confirm-change-status"><?php esc_html_e( 'Change Status', 'fanfiction-manager' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the modal for the bulk block action with reason selection.
	 *
	 * @since 1.2.0
	 */
	public function render_block_reason_modal() {
		$reasons = self::get_block_reason_labels();
		?>
		<div id="fanfic-block-reason-modal" style="display:none;" class="fanfic-admin-modal">
			<div class="fanfic-admin-modal-overlay"></div>
			<div class="fanfic-admin-modal-content">
				<h2><?php esc_html_e( 'Block Stories', 'fanfiction-manager' ); ?></h2>
				<p class="fanfic-modal-warning"><?php esc_html_e( 'Warning: Blocking stories will hide them from the public and prevent authors from editing. This action logs to the moderation log.', 'fanfiction-manager' ); ?></p>

				<div class="fanfic-admin-modal-form">
					<label for="fanfic-block-reason-select"><?php esc_html_e( 'Select a reason for blocking:', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-block-reason-select" style="width: 100%;">
						<option value=""><?php esc_html_e( '-- Select Reason --', 'fanfiction-manager' ); ?></option>
						<?php foreach ( $reasons as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="fanfic-admin-modal-message" style="margin-top:10px;"></div>
				</div>

				<div class="fanfic-admin-modal-actions">
					<button type="button" class="button fanfic-admin-modal-cancel"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
					<button type="button" class="button button-primary" id="fanfic-confirm-block"><?php esc_html_e( 'Block Stories', 'fanfiction-manager' ); ?></button>
				</div>
			</div>
		</div>
		<input type="hidden" name="block_reason" id="fanfic-block-reason-value" value="">
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
		
		                function run_bulk_action(action, $button) {
		                    var checked_ids = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();

		                    if (checked_ids.length === 0) {
		                        alert('<?php esc_html_e( 'Please select at least one story.', 'fanfiction-manager' ); ?>');
		                        return;
		                    }

		                    if (action === 'delete') {
		                        if (!confirm('<?php esc_html_e( 'Are you sure you want to delete the selected stories? This action cannot be undone.', 'fanfiction-manager' ); ?>')) {
		                            return;
		                        }
		                    }

		                    // Block action now uses modal, skip confirm
		                    if (action === 'block') {
		                        return; // Handled by modal workflow
		                    }

		                    if (action === 'unblock') {
		                        if (!confirm('<?php esc_html_e( 'Unblock selected stories? Authors will regain access.', 'fanfiction-manager' ); ?>')) {
		                            return;
		                        }
		                    }
		
		                    var $form = $button ? $button.closest('form') : $(document).closest('form');
		                    $form.find('#bulk-action-selector-top, #bulk-action-selector-bottom').val(action);
		                    $form.find('#doaction, #doaction2').first().trigger('click');
		                }

		                $('.bulk-publish').on('click', function(e) { e.preventDefault(); run_bulk_action('publish', $(this)); });
		                $('.bulk-set-draft').on('click', function(e) { e.preventDefault(); run_bulk_action('set_draft', $(this)); });
		                $('.bulk-unblock').on('click', function(e) { e.preventDefault(); run_bulk_action('unblock', $(this)); });
		                $('.bulk-delete').on('click', function(e) { e.preventDefault(); run_bulk_action('delete', $(this)); });

		                // Handle "Block" bulk action - show modal for reason selection
		                var $blockButton = null;
		                $('.bulk-block').on('click', function(e) {
		                    e.preventDefault();
		                    var checked_ids = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();

		                    if (checked_ids.length === 0) {
		                        alert('<?php esc_html_e( 'Please select at least one story to block.', 'fanfiction-manager' ); ?>');
		                        return;
		                    }

		                    $blockButton = $(this);
		                    $('#fanfic-block-reason-select').val('');
		                    $('#fanfic-block-reason-modal').fadeIn(200);
		                    $('body').addClass('fanfic-admin-modal-open');
		                });

		                // Confirm block with reason
		                $('#fanfic-confirm-block').on('click', function() {
		                    var reason = $('#fanfic-block-reason-select').val();
		                    if (!reason) {
		                        $('#fanfic-block-reason-modal .fanfic-admin-modal-message')
		                            .html('<span style="color: red;"><?php esc_html_e( 'Please select a reason for blocking.', 'fanfiction-manager' ); ?></span>');
		                        return;
		                    }

		                    // Store the reason in hidden field
		                    $('#fanfic-block-reason-value').val(reason);

		                    // Close modal
		                    $('#fanfic-block-reason-modal').fadeOut(200);
		                    $('body').removeClass('fanfic-admin-modal-open');

		                    // Run the block action
		                    var $form = $blockButton ? $blockButton.closest('form') : $(document).closest('form');
		                    $form.find('#bulk-action-selector-top, #bulk-action-selector-bottom').val('block');
		                    $form.find('#doaction, #doaction2').first().trigger('click');
		                });
		
		                // Handle "Change Author" bulk action
		                $('.bulk-change-author').on('click', function(e) {
		                    e.preventDefault();
		                    var checked_ids = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (checked_ids.length === 0) {
		                        alert('<?php esc_html_e( 'Please select at least one story to change the author.', 'fanfiction-manager' ); ?>');
		                        return;
		                    }
		                    
		                    $('#fanfic-change-author-modal').fadeIn(200);
		                    $('body').addClass('fanfic-admin-modal-open');
		                });
		                
		                // Handle "Change Genre" bulk action
		                $('.bulk-apply-genre').on('click', function(e) {
		                    e.preventDefault();
		                    var checked_ids = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (checked_ids.length === 0) {
		                        alert('<?php esc_html_e( 'Please select at least one story to apply a genre to.', 'fanfiction-manager' ); ?>');
		                        return;
		                    }
		                    
		                    $('#fanfic-apply-genre-modal').fadeIn(200);
		                    $('body').addClass('fanfic-admin-modal-open');
		                });
		
		                // Handle "Change Status" bulk action
		                $('.bulk-change-status').on('click', function(e) {
		                    e.preventDefault();
		                    var checked_ids = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (checked_ids.length === 0) {
		                        alert('<?php esc_html_e( 'Please select at least one story to change the status of.', 'fanfiction-manager' ); ?>');
		                        return;
		                    }
		                    
		                    $('#fanfic-change-status-modal').fadeIn(200);
		                    $('body').addClass('fanfic-admin-modal-open');
		                });
		
		                // Close modals
		                $(document).on('click', '.fanfic-admin-modal-cancel, .fanfic-admin-modal-overlay', function(e) {
		                    if (e.target !== this) return;
		                    e.preventDefault();
		                    $(this).closest('.fanfic-admin-modal').fadeOut(200);
		                    $('body').removeClass('fanfic-admin-modal-open');
		                });
		
		                // Search/filter authors in modal (suggestions)
		                var $authorInput = $('#fanfic-author-search-input');
		                var $authorSuggestions = $('.fanfic-author-suggestions');
		                var $authorSelect = $('#fanfic-new-author-select');

		                function renderAuthorSuggestions(query) {
		                    var q = query.toLowerCase();
		                    $authorSuggestions.empty();

		                    if (q.length < 2) {
		                        $authorSuggestions.hide();
		                        return;
		                    }

		                    var matches = $authorSelect.find('option[value!=""]').filter(function() {
		                        var name = $(this).data('name') || '';
		                        return name.indexOf(q) !== -1;
		                    });

		                    if (!matches.length) {
		                        $authorSuggestions.hide();
		                        return;
		                    }

		                    matches.each(function() {
		                        var $option = $(this);
		                        var $btn = $('<button type="button" class="fanfic-author-suggestion"></button>');
		                        $btn.text($option.text());
		                        $btn.attr('data-id', $option.val());
		                        $authorSuggestions.append($btn);
		                    });

		                    $authorSuggestions.show();
		                }

		                $authorInput.on('input', function() {
		                    renderAuthorSuggestions($(this).val().trim());
		                });

		                $authorSuggestions.on('click', '.fanfic-author-suggestion', function() {
		                    var id = $(this).attr('data-id');
		                    $authorSelect.val(id);
		                    $authorSuggestions.hide().empty();
		                });

		                $authorInput.on('blur', function() {
		                    setTimeout(function() {
		                        $authorSuggestions.hide().empty();
		                    }, 150);
		                });
		
		                // Handle the confirmation click in the author modal
		                $('#fanfic-confirm-author-change').on('click', function(e) {
		                    e.preventDefault();
		                    var $button = $(this);
		                    var $message = $('#fanfic-change-author-modal .fanfic-admin-modal-message');
		                    var newAuthorId = $('#fanfic-new-author-select').val();
		                    var storyIds = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (!newAuthorId) {
		                        $message.html('<p class="error"><?php esc_html_e( 'Please select a new author.', 'fanfiction-manager' ); ?></p>');
		                        return;
		                    }
		
		                    $button.prop('disabled', true);
		                    $message.html('<p class="info"><?php esc_html_e( 'Saving...', 'fanfiction-manager' ); ?></p>');
		
		                    $.ajax({
		                        url: ajaxurl,
		                        type: 'POST',
		                        data: {
		                            action: 'fanfic_bulk_change_author',
		                            story_ids: storyIds,
		                            new_author_id: newAuthorId,
		                            nonce: '<?php echo wp_create_nonce( 'fanfic_bulk_change_author' ); ?>'
		                        },
		                        success: function(response) {
		                            if (response.success) {
		                                $message.html('<p class="success">' + response.data.message + '</p>');
		                                setTimeout(function() { location.reload(); }, 1000);
		                            } else {
		                                $message.html('<p class="error">' + response.data.message + '</p>');
		                                $button.prop('disabled', false);
		                            }
		                        },
		                        error: function() {
		                            $message.html('<p class="error"><?php esc_html_e( 'An unexpected error occurred. Please try again.', 'fanfiction-manager' ); ?></p>');
		                            $button.prop('disabled', false);
		                        }
		                    });
		                });
		
		                // Handle the confirmation click in the genre modal
		                $('#fanfic-confirm-apply-genre').on('click', function(e) {
		                    e.preventDefault();
		                    var $button = $(this);
		                    var $message = $('#fanfic-apply-genre-modal .fanfic-admin-modal-message');
		                    var genreIds = $('#fanfic-genre-select').val() || [];
		                    var storyIds = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (!genreIds.length) {
		                        $message.html('<p class="error"><?php esc_html_e( 'Please select at least one genre.', 'fanfiction-manager' ); ?></p>');
		                        return;
		                    }
		
		                    $button.prop('disabled', true);
		                    $message.html('<p class="info"><?php esc_html_e( 'Changing genre...', 'fanfiction-manager' ); ?></p>');
		
		                    $.ajax({
		                        url: ajaxurl,
		                        type: 'POST',
		                        data: {
		                            action: 'fanfic_bulk_apply_genre',
		                            story_ids: storyIds,
		                            genre_ids: genreIds,
		                            nonce: '<?php echo wp_create_nonce( 'fanfic_bulk_apply_genre' ); ?>'
		                        },
		                        success: function(response) {
		                            if (response.success) {
		                                $message.html('<p class="success">' + response.data.message + '</p>');
		                                setTimeout(function() { location.reload(); }, 1000);
		                            } else {
		                                $message.html('<p class="error">' + response.data.message + '</p>');
		                                $button.prop('disabled', false);
		                            }
		                        },
		                        error: function() {
		                            $message.html('<p class="error"><?php esc_html_e( 'An unexpected error occurred. Please try again.', 'fanfiction-manager' ); ?></p>');
		                            $button.prop('disabled', false);
		                        }
		                    });
		                });
		
		                // Handle the confirmation click in the status modal
		                $('#fanfic-confirm-change-status').on('click', function(e) {
		                    e.preventDefault();
		                    var $button = $(this);
		                    var $message = $('#fanfic-change-status-modal .fanfic-admin-modal-message');
		                    var statusId = $('#fanfic-status-select').val();
		                    var storyIds = $('input[name="story[]"]:checked').map(function() {
		                        return $(this).val();
		                    }).get();
		
		                    if (!statusId) {
		                        $message.html('<p class="error"><?php esc_html_e( 'Please select a status.', 'fanfiction-manager' ); ?></p>');
		                        return;
		                    }
		
		                    $button.prop('disabled', true);
		                    $message.html('<p class="info"><?php esc_html_e( 'Changing status...', 'fanfiction-manager' ); ?></p>');
		
		                    $.ajax({
		                        url: ajaxurl,
		                        type: 'POST',
		                        data: {
		                            action: 'fanfic_bulk_change_status',
		                            story_ids: storyIds,
		                            status_id: statusId,
		                            nonce: '<?php echo wp_create_nonce( 'fanfic_bulk_change_status' ); ?>'
		                        },
		                        success: function(response) {
		                            if (response.success) {
		                                $message.html('<p class="success">' + response.data.message + '</p>');
		                                setTimeout(function() { location.reload(); }, 1000);
		                            } else {
		                                $message.html('<p class="error">' + response.data.message + '</p>');
		                                $button.prop('disabled', false);
		                            }
		                        },
		                        error: function() {
		                            $message.html('<p class="error"><?php esc_html_e( 'An unexpected error occurred. Please try again.', 'fanfiction-manager' ); ?></p>');
		                            $button.prop('disabled', false);
		                        }
		                    });
		                });
		            });
		        </script>		<?php
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
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
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
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
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

			case 'block':
				// Get block reason from form
				$block_reason = isset( $_REQUEST['block_reason'] ) ? sanitize_key( $_REQUEST['block_reason'] ) : 'manual';
				$valid_reasons = array_keys( self::get_block_reason_labels() );
				if ( ! in_array( $block_reason, $valid_reasons, true ) ) {
					$block_reason = 'manual';
				}

				foreach ( $story_ids as $story_id ) {
					$story_status = get_post_status( $story_id );
					if ( $story_status && ! get_post_meta( $story_id, '_fanfic_story_blocked_prev_status', true ) ) {
						update_post_meta( $story_id, '_fanfic_story_blocked_prev_status', $story_status );
					}
					update_post_meta( $story_id, '_fanfic_story_blocked', 1 );
					update_post_meta( $story_id, '_fanfic_story_blocked_reason', $block_reason );
					wp_update_post( array(
						'ID'          => $story_id,
						'post_status' => 'draft',
					) );

					// Fire story blocked hook for moderation log
					$reason_labels = self::get_block_reason_labels();
					$reason_label = isset( $reason_labels[ $block_reason ] ) ? $reason_labels[ $block_reason ] : $block_reason;
					do_action( 'fanfic_story_blocked', $story_id, get_current_user_id(), $block_reason, $reason_label );

					$chapters = get_posts( array(
						'post_type'      => 'fanfiction_chapter',
						'post_parent'    => $story_id,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					) );
					foreach ( $chapters as $chapter_id ) {
						$chapter_status = get_post_status( $chapter_id );
						if ( $chapter_status && ! get_post_meta( $chapter_id, '_fanfic_story_blocked_prev_status', true ) ) {
							update_post_meta( $chapter_id, '_fanfic_story_blocked_prev_status', $chapter_status );
						}
						update_post_meta( $chapter_id, '_fanfic_story_blocked', 1 );
						update_post_meta( $chapter_id, '_fanfic_story_blocked_reason', $block_reason );
						wp_update_post( array(
							'ID'          => $chapter_id,
							'post_status' => 'draft',
						) );
					}
				}
				$this->add_notice(
					sprintf(
						/* translators: %d: Number of stories */
						_n( '%d story blocked.', '%d stories blocked.', count( $story_ids ), 'fanfiction-manager' ),
						count( $story_ids )
					),
					'success'
				);
				break;

			case 'unblock':
				foreach ( $story_ids as $story_id ) {
					$story_prev_status = get_post_meta( $story_id, '_fanfic_story_blocked_prev_status', true );
					$story_restore_status = $story_prev_status ? $story_prev_status : 'draft';
					if ( $story_restore_status ) {
						wp_update_post( array(
							'ID'          => $story_id,
							'post_status' => $story_restore_status,
						) );
					}
					delete_post_meta( $story_id, '_fanfic_story_blocked_prev_status' );
					delete_post_meta( $story_id, '_fanfic_story_blocked' );
					delete_post_meta( $story_id, '_fanfic_story_blocked_reason' );

					// Fire story unblocked hook for moderation log
					do_action( 'fanfic_story_unblocked', $story_id, get_current_user_id() );

					$chapters = get_posts( array(
						'post_type'      => 'fanfiction_chapter',
						'post_parent'    => $story_id,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					) );
					foreach ( $chapters as $chapter_id ) {
						$chapter_prev_status = get_post_meta( $chapter_id, '_fanfic_story_blocked_prev_status', true );
						$chapter_restore_status = $chapter_prev_status ? $chapter_prev_status : 'draft';
						if ( $chapter_restore_status ) {
							wp_update_post( array(
								'ID'          => $chapter_id,
								'post_status' => $chapter_restore_status,
							) );
						}
						delete_post_meta( $chapter_id, '_fanfic_story_blocked_prev_status' );
						delete_post_meta( $chapter_id, '_fanfic_story_blocked' );
						delete_post_meta( $chapter_id, '_fanfic_story_blocked_reason' );
					}
				}
				$this->add_notice(
					sprintf(
						/* translators: %d: Number of stories */
						_n( '%d story unblocked.', '%d stories unblocked.', count( $story_ids ), 'fanfiction-manager' ),
						count( $story_ids )
					),
					'success'
				);
				break;

			case 'publish':
				$published_count = 0;
				$failed_stories_details = array();

				foreach ( $story_ids as $story_id ) {
					$story = get_post( $story_id );
					if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
						$failed_stories_details[] = sprintf( '%s (ID: %d) - %s', __( 'Story not found or invalid type', 'fanfiction-manager' ), $story_id, __( 'Skipped', 'fanfiction-manager' ) );
						continue;
					}

					// Use the existing validation function
					$validation_result = Fanfic_Validation::can_publish_story( $story_id );

					if ( $validation_result['can_publish'] ) {
						wp_update_post( array(
							'ID'          => $story_id,
							'post_status' => 'publish',
						) );
						$published_count++;
					} else {
						$missing_reasons = implode( ', ', $validation_result['missing_fields'] );
						$failed_stories_details[] = sprintf( '%s (%s)', esc_html( $story->post_title ), $missing_reasons );
					}
				}

				if ( $published_count > 0 ) {
					$this->add_notice(
						sprintf(
							/* translators: %d: Number of stories */
							_n( '%d story published successfully.', '%d stories published successfully.', $published_count, 'fanfiction-manager' ),
							$published_count
						),
						'success'
					);
				}

				if ( ! empty( $failed_stories_details ) ) {
					$error_message = sprintf(
						/* translators: %s: Comma-separated list of failed story titles and reasons */
						__( 'The following stories could not be published because they do not meet the requirements: %s', 'fanfiction-manager' ),
						implode( '; ', $failed_stories_details )
					);
					$this->add_notice( $error_message, 'error' );
				}
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

		// No redirect is needed as the page will reload and show the notices.
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

		// Delete all chapters (and their interactions)
		foreach ( $chapters as $chapter_id ) {
			// Delete all interactions for this chapter (likes, dislikes, ratings, views, reads, follows)
			$wpdb->delete(
				$wpdb->prefix . 'fanfic_interactions',
				array( 'chapter_id' => $chapter_id ),
				array( '%d' )
			);

			// Delete chapter post
			wp_delete_post( $chapter_id, true );
		}

		// Delete follow interactions for this story
		$wpdb->delete(
			$wpdb->prefix . 'fanfic_interactions',
			array( 'chapter_id' => $story_id, 'interaction_type' => 'follow' ),
			array( '%d', '%s' )
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
