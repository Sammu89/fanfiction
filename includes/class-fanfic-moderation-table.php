<?php
/**
 * Moderation Queue WP_List_Table Implementation
 *
 * Implements WP_List_Table for the moderation queue with filtering,
 * sorting, and action handling capabilities. Displays reported content
 * with detailed information, moderation stamps, and actions.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Fanfic_Moderation_Table
 *
 * Extends WP_List_Table to display and manage the moderation queue.
 *
 * @since 1.0.0
 */
class Fanfic_Moderation_Table extends WP_List_Table {

	/**
	 * Total number of items
	 *
	 * @var int
	 */
	private $total_items = 0;

	/**
	 * Constructor
	 *
	 * Sets up the list table and processes actions.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Report', 'fanfiction-manager' ),
			'plural'   => __( 'Reports', 'fanfiction-manager' ),
			'ajax'     => false,
		) );

		// Handle actions before headers are sent
		$this->process_bulk_actions();
		$this->process_single_actions();
	}

	/**
	 * Get list of columns
	 *
	 * @since 1.0.0
	 * @return array Associative array of column slug => title.
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'view_report'   => __( 'View Report', 'fanfiction-manager' ),
			'post_title'    => __( 'Post Title', 'fanfiction-manager' ),
			'post_type'     => __( 'Post Type', 'fanfiction-manager' ),
			'reported_by'   => __( 'Reported By', 'fanfiction-manager' ),
			'date'          => __( 'Date', 'fanfiction-manager' ),
			'status'        => __( 'Status', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get list of sortable columns
	 *
	 * @since 1.0.0
	 * @return array Associative array of column slug => array( db field, default sort ).
	 */
	protected function get_sortable_columns() {
		return array(
			'post_title' => array( 'post_title', false ),
			'post_type'  => array( 'post_type', false ),
			'date'       => array( 'date', true ), // true = already sorted by this column
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 * @return array Associative array of bulk action slug => title.
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk_dismiss' => __( 'Dismiss', 'fanfiction-manager' ),
			'bulk_delete'  => __( 'Delete Reports', 'fanfiction-manager' ),
		);
	}

	/**
	 * Display when no items are found
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No reports found.', 'fanfiction-manager' );
	}

	/**
	 * Prepare items for display
	 *
	 * Queries the database, handles pagination, and prepares report data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		// Set up columns
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get pagination parameters
		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		// Get filters from URL
		$filters = $this->get_filters();

		// Query reports
		$results = $this->get_reports( $offset, $per_page, $filters );
		$this->items = $results['items'];
		$this->total_items = $results['total'];

		// Set up pagination
		$this->set_pagination_args( array(
			'total_items' => $this->total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $this->total_items / $per_page ),
		) );
	}

	/**
	 * Get current filters from URL parameters
	 *
	 * @since 1.0.0
	 * @return array Array of filter parameters.
	 */
	private function get_filters() {
		$filters = array();

		// Status filter
		if ( isset( $_GET['status'] ) && 'all' !== $_GET['status'] ) {
			$status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
			$allowed_statuses = array( 'pending', 'reviewed', 'dismissed' );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$filters['status'] = $status;
			}
		}

		// Post type filter
		if ( ! empty( $_GET['post_type_filter'] ) ) {
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ) );
			$allowed_types = array( 'fanfiction_story', 'fanfiction_chapter' );
			if ( in_array( $post_type, $allowed_types, true ) ) {
				$filters['post_type'] = $post_type;
			}
		}

		// Reporter filter
		if ( ! empty( $_GET['reporter_filter'] ) ) {
			$reporter_id = absint( $_GET['reporter_filter'] );
			if ( $reporter_id > 0 ) {
				$filters['reporter_id'] = $reporter_id;
			}
		}

		// Date range filter
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}

		// Sorting
		$filters['orderby'] = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$filters['order'] = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		return $filters;
	}

	/**
	 * Query reports from database
	 *
	 * Retrieves reports with proper joins, filtering, sorting, and pagination.
	 *
	 * @since 1.0.0
	 * @param int   $offset  Offset for pagination.
	 * @param int   $per_page Number of items per page.
	 * @param array $filters Array of filter parameters.
	 * @return array Array with 'items' and 'total' keys.
	 */
	private function get_reports( $offset, $per_page, $filters ) {
		global $wpdb;

		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$posts_table = $wpdb->posts;

		// Build WHERE clause
		$where_clauses = array( '1=1' );
		$where_values = array();

		// Status filter
		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'r.status = %s';
			$where_values[] = $filters['status'];
		}

		// Post type filter
		if ( ! empty( $filters['post_type'] ) ) {
			$where_clauses[] = 'r.reported_item_type = %s';
			$where_values[] = $filters['post_type'];
		}

		// Reporter filter
		if ( ! empty( $filters['reporter_id'] ) ) {
			$where_clauses[] = 'r.reporter_id = %d';
			$where_values[] = $filters['reporter_id'];
		}

		// Date range filter
		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'DATE(r.created_at) >= %s';
			$where_values[] = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'DATE(r.created_at) <= %s';
			$where_values[] = $filters['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build ORDER BY clause
		$allowed_orderby = array( 'date', 'status', 'post_title', 'post_type' );
		$orderby = in_array( $filters['orderby'], $allowed_orderby, true ) ? $filters['orderby'] : 'date';
		$order = 'ASC' === $filters['order'] ? 'ASC' : 'DESC';

		// Map orderby to actual column
		$orderby_map = array(
			'date'       => 'r.created_at',
			'status'     => 'r.status',
			'post_title' => 'p.post_title',
			'post_type'  => 'r.reported_item_type',
		);
		$orderby_column = isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : 'r.created_at';

		// Get total count
		$count_sql = "SELECT COUNT(*) FROM {$reports_table} r WHERE {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get reports with post data
		$query = "
			SELECT
				r.id,
				r.reported_item_id,
				r.reported_item_type,
				r.reporter_id,
				r.reason,
				r.status,
				r.moderator_id,
				r.moderator_notes,
				r.created_at,
				r.updated_at,
				p.post_title,
				p.post_author
			FROM {$reports_table} r
			LEFT JOIN {$posts_table} p ON r.reported_item_id = p.ID
			WHERE {$where_sql}
			ORDER BY {$orderby_column} {$order}
			LIMIT %d OFFSET %d
		";

		$values = array_merge( $where_values, array( $per_page, $offset ) );
		$prepared_query = $wpdb->prepare( $query, $values );
		$items = $wpdb->get_results( $prepared_query, ARRAY_A );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Render the checkbox column
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="reports[]" value="%s" />',
			esc_attr( $item['id'] )
		);
	}

	/**
	 * Render the "View Report" column
	 *
	 * Displays a button to expand report details.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_view_report( $item ) {
		$report_id = absint( $item['id'] );
		return sprintf(
			'<button type="button" class="button button-small fanfic-view-report-btn" data-report-id="%d">%s</button>',
			$report_id,
			esc_html__( 'View Details', 'fanfiction-manager' )
		);
	}

	/**
	 * Render the "Post Title" column
	 *
	 * Displays the reported content title with a link.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_post_title( $item ) {
		$post_id = absint( $item['reported_item_id'] );
		$post_title = ! empty( $item['post_title'] ) ? $item['post_title'] : __( '(No Title)', 'fanfiction-manager' );

		// Check if post still exists
		$post = get_post( $post_id );
		if ( $post ) {
			$edit_url = get_edit_post_link( $post_id );
			return sprintf(
				'<a href="%s" target="_blank"><strong>%s</strong></a>',
				esc_url( $edit_url ),
				esc_html( $post_title )
			);
		} else {
			return sprintf(
				'<span style="color: #d63638;">%s</span> <em>%s</em>',
				esc_html( $post_title ),
				esc_html__( '(Deleted)', 'fanfiction-manager' )
			);
		}
	}

	/**
	 * Render the "Post Type" column
	 *
	 * Displays an icon/label for the post type.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_post_type( $item ) {
		$post_type = $item['reported_item_type'];

		if ( 'fanfiction_story' === $post_type ) {
			return '<span class="dashicons dashicons-book" style="color: #2196F3;"></span> ' .
			       esc_html__( 'Story', 'fanfiction-manager' );
		} elseif ( 'fanfiction_chapter' === $post_type ) {
			return '<span class="dashicons dashicons-media-document" style="color: #4CAF50;"></span> ' .
			       esc_html__( 'Chapter', 'fanfiction-manager' );
		}

		return esc_html( $post_type );
	}

	/**
	 * Render the "Reported By" column
	 *
	 * Displays reporter username or anonymous indicator with IP if available.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_reported_by( $item ) {
		$reporter_id = absint( $item['reporter_id'] );

		if ( $reporter_id > 0 ) {
			$user = get_userdata( $reporter_id );
			if ( $user ) {
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_user_link( $reporter_id ) ),
					esc_html( $user->display_name )
				);
			}
		}

		// Anonymous reporter - could be extended to show IP from meta data
		return '<em>' . esc_html__( 'Anonymous', 'fanfiction-manager' ) . '</em>';
	}

	/**
	 * Render the "Date" column
	 *
	 * Displays the report submission date with human-readable time difference.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_date( $item ) {
		$date = $item['created_at'];
		$formatted_date = mysql2date( get_option( 'date_format' ), $date );
		$formatted_time = mysql2date( get_option( 'time_format' ), $date );
		$time_diff = human_time_diff( strtotime( $date ), current_time( 'timestamp' ) );

		return sprintf(
			'<span title="%s %s">%s</span><br><small style="color: #646970;">%s</small>',
			esc_attr( $formatted_date ),
			esc_attr( $formatted_time ),
			esc_html( $formatted_date ),
			esc_html( sprintf( __( '%s ago', 'fanfiction-manager' ), $time_diff ) )
		);
	}

	/**
	 * Render the "Status" column
	 *
	 * Displays a colored badge for the report status.
	 *
	 * @since 1.0.0
	 * @param array $item Report item.
	 * @return string Column HTML.
	 */
	protected function column_status( $item ) {
		$status = $item['status'];

		$status_labels = array(
			'pending'   => __( 'Pending', 'fanfiction-manager' ),
			'reviewed'  => __( 'Reviewed', 'fanfiction-manager' ),
			'dismissed' => __( 'Dismissed', 'fanfiction-manager' ),
		);

		$status_classes = array(
			'pending'   => 'status-badge status-warning',
			'reviewed'  => 'status-badge status-success',
			'dismissed' => 'status-badge status-info',
		);

		$label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
		$class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'status-badge';

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Default column renderer
	 *
	 * Handles any columns not specifically defined.
	 *
	 * @since 1.0.0
	 * @param array  $item Report item.
	 * @param string $column_name Column name.
	 * @return string Column HTML.
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
	}

	/**
	 * Generate row actions
	 *
	 * Displays action links in the primary column.
	 *
	 * @since 1.0.0
	 * @param array  $item Report item.
	 * @param string $column_name Column name.
	 * @param string $primary Primary column name.
	 * @return string Row actions HTML.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$report_id = absint( $item['id'] );
		$status = $item['status'];
		$actions = array();

		// View report details
		$actions['view'] = sprintf(
			'<a href="#" class="fanfic-view-report-link" data-report-id="%d">%s</a>',
			$report_id,
			esc_html__( 'View Details', 'fanfiction-manager' )
		);

		// Resolve/Review action (only for pending reports)
		if ( 'pending' === $status ) {
			$actions['resolve'] = sprintf(
				'<a href="#" class="fanfic-resolve-report" data-report-id="%d">%s</a>',
				$report_id,
				esc_html__( 'Mark Reviewed', 'fanfiction-manager' )
			);
		}

		// Dismiss action (only for pending reports)
		if ( 'pending' === $status ) {
			$dismiss_url = wp_nonce_url(
				add_query_arg( array(
					'action'    => 'dismiss_report',
					'report_id' => $report_id,
				) ),
				'dismiss_report_' . $report_id
			);
			$actions['dismiss'] = sprintf(
				'<a href="%s" class="fanfic-dismiss-report" data-report-id="%d">%s</a>',
				esc_url( $dismiss_url ),
				$report_id,
				esc_html__( 'Dismiss', 'fanfiction-manager' )
			);
		}

		// Delete report action
		$delete_url = wp_nonce_url(
			add_query_arg( array(
				'action'    => 'delete_report',
				'report_id' => $report_id,
			) ),
			'delete_report_' . $report_id
		);
		$actions['delete'] = sprintf(
			'<a href="%s" class="fanfic-delete-report submitdelete" data-report-id="%d">%s</a>',
			esc_url( $delete_url ),
			$report_id,
			esc_html__( 'Delete Report', 'fanfiction-manager' )
		);

		return $this->row_actions( $actions );
	}

	/**
	 * Display extra tablenav
	 *
	 * Adds custom filters above the table.
	 *
	 * @since 1.0.0
	 * @param string $which Position: 'top' or 'bottom'.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_post_type = isset( $_GET['post_type_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ) ) : '';
		$current_reporter = isset( $_GET['reporter_filter'] ) ? absint( $_GET['reporter_filter'] ) : 0;
		?>
		<div class="alignleft actions">
			<!-- Post Type Filter -->
			<select name="post_type_filter" id="post-type-filter">
				<option value=""><?php esc_html_e( 'All Post Types', 'fanfiction-manager' ); ?></option>
				<option value="fanfiction_story" <?php selected( $current_post_type, 'fanfiction_story' ); ?>>
					<?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?>
				</option>
				<option value="fanfiction_chapter" <?php selected( $current_post_type, 'fanfiction_chapter' ); ?>>
					<?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?>
				</option>
			</select>

			<!-- Reporter Filter -->
			<select name="reporter_filter" id="reporter-filter">
				<option value=""><?php esc_html_e( 'All Reporters', 'fanfiction-manager' ); ?></option>
				<?php
				// Get unique reporters
				$reporters = $this->get_reporters();
				foreach ( $reporters as $reporter ) {
					printf(
						'<option value="%d" %s>%s</option>',
						absint( $reporter['reporter_id'] ),
						selected( $current_reporter, $reporter['reporter_id'], false ),
						esc_html( $reporter['display_name'] )
					);
				}
				?>
			</select>

			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'fanfiction-manager' ); ?>" />
		</div>
		<?php
	}

	/**
	 * Get list of unique reporters
	 *
	 * Retrieves all users who have submitted reports.
	 *
	 * @since 1.0.0
	 * @return array Array of reporter data.
	 */
	private function get_reporters() {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$users_table = $wpdb->users;

		$query = "
			SELECT DISTINCT r.reporter_id, u.display_name
			FROM {$reports_table} r
			LEFT JOIN {$users_table} u ON r.reporter_id = u.ID
			WHERE r.reporter_id > 0
			ORDER BY u.display_name ASC
		";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Process single row actions
	 *
	 * Handles resolve, dismiss, and delete actions for individual reports.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function process_single_actions() {
		// Check if action is set
		if ( empty( $_GET['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$report_id = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0;

		if ( ! $report_id ) {
			return;
		}

		// Verify capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle different actions
		switch ( $action ) {
			case 'dismiss_report':
				// Verify nonce
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dismiss_report_' . $report_id ) ) {
					wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
				}
				$this->dismiss_report( $report_id );
				break;

			case 'delete_report':
				// Verify nonce
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_report_' . $report_id ) ) {
					wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
				}
				$this->delete_report( $report_id );
				break;
		}
	}

	/**
	 * Process bulk actions
	 *
	 * Handles bulk dismiss and delete actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function process_bulk_actions() {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Verify capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get selected reports
		$report_ids = isset( $_REQUEST['reports'] ) ? array_map( 'absint', (array) $_REQUEST['reports'] ) : array();

		if ( empty( $report_ids ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-reports' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Process bulk actions
		switch ( $action ) {
			case 'bulk_dismiss':
				foreach ( $report_ids as $report_id ) {
					$this->dismiss_report( $report_id, true );
				}
				$this->add_admin_notice( 'success', sprintf(
					/* translators: %d: Number of reports dismissed */
					_n( '%d report dismissed.', '%d reports dismissed.', count( $report_ids ), 'fanfiction-manager' ),
					count( $report_ids )
				) );
				break;

			case 'bulk_delete':
				foreach ( $report_ids as $report_id ) {
					$this->delete_report( $report_id, true );
				}
				$this->add_admin_notice( 'success', sprintf(
					/* translators: %d: Number of reports deleted */
					_n( '%d report deleted.', '%d reports deleted.', count( $report_ids ), 'fanfiction-manager' ),
					count( $report_ids )
				) );
				break;
		}

		// Redirect to remove action from URL
		if ( $action ) {
			wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'reports', '_wpnonce', '_wp_http_referer' ) ) );
			exit;
		}
	}

	/**
	 * Dismiss a report
	 *
	 * Changes report status to 'dismissed'.
	 *
	 * @since 1.0.0
	 * @param int  $report_id Report ID.
	 * @param bool $bulk_action Whether this is a bulk action.
	 * @return void
	 */
	private function dismiss_report( $report_id, $bulk_action = false ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$current_user_id = get_current_user_id();

		$result = $wpdb->update(
			$reports_table,
			array(
				'status'       => 'dismissed',
				'moderator_id' => $current_user_id,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $report_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( ! $bulk_action ) {
			if ( false !== $result ) {
				$this->add_admin_notice( 'success', __( 'Report dismissed successfully.', 'fanfiction-manager' ) );
			} else {
				$this->add_admin_notice( 'error', __( 'Failed to dismiss report.', 'fanfiction-manager' ) );
			}

			// Redirect to remove action from URL
			wp_safe_redirect( remove_query_arg( array( 'action', 'report_id', '_wpnonce' ) ) );
			exit;
		}
	}

	/**
	 * Delete a report
	 *
	 * Permanently removes the report from the database.
	 * NOTE: This deletes the report entry, NOT the reported content.
	 *
	 * @since 1.0.0
	 * @param int  $report_id Report ID.
	 * @param bool $bulk_action Whether this is a bulk action.
	 * @return void
	 */
	private function delete_report( $report_id, $bulk_action = false ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';

		$result = $wpdb->delete(
			$reports_table,
			array( 'id' => $report_id ),
			array( '%d' )
		);

		if ( ! $bulk_action ) {
			if ( false !== $result && $result > 0 ) {
				$this->add_admin_notice( 'success', __( 'Report deleted successfully.', 'fanfiction-manager' ) );
			} else {
				$this->add_admin_notice( 'error', __( 'Failed to delete report.', 'fanfiction-manager' ) );
			}

			// Redirect to remove action from URL
			wp_safe_redirect( remove_query_arg( array( 'action', 'report_id', '_wpnonce' ) ) );
			exit;
		}
	}

	/**
	 * Resolve a report
	 *
	 * Marks a report as reviewed with moderator action description.
	 * This is called via AJAX from the resolve modal.
	 *
	 * @since 1.0.0
	 * @param int    $report_id Report ID.
	 * @param string $action_description What action was taken.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function resolve_report( $report_id, $action_description ) {
		global $wpdb;

		// Verify capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'no_permission', __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) );
		}

		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$current_user_id = get_current_user_id();

		$result = $wpdb->update(
			$reports_table,
			array(
				'status'          => 'reviewed',
				'moderator_id'    => $current_user_id,
				'moderator_notes' => sanitize_textarea_field( $action_description ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => absint( $report_id ) ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update report.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Get report details by ID
	 *
	 * Retrieves full report information including related post data.
	 *
	 * @since 1.0.0
	 * @param int $report_id Report ID.
	 * @return array|null Report data or null if not found.
	 */
	public static function get_report_details( $report_id ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$posts_table = $wpdb->posts;

		$query = $wpdb->prepare(
			"
			SELECT
				r.*,
				p.post_title,
				p.post_content,
				p.post_excerpt,
				p.post_author
			FROM {$reports_table} r
			LEFT JOIN {$posts_table} p ON r.reported_item_id = p.ID
			WHERE r.id = %d
			",
			absint( $report_id )
		);

		return $wpdb->get_row( $query, ARRAY_A );
	}

	/**
	 * Add admin notice
	 *
	 * Stores notice in transient for display after redirect.
	 *
	 * @since 1.0.0
	 * @param string $type Notice type: 'success', 'error', 'warning', 'info'.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function add_admin_notice( $type, $message ) {
		set_transient( 'fanfic_moderation_notice', array(
			'type'    => $type,
			'message' => $message,
		), 30 );
	}

	/**
	 * Display admin notices
	 *
	 * Shows notices stored in transients.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function display_notices() {
		$notice = get_transient( 'fanfic_moderation_notice' );

		if ( $notice && is_array( $notice ) ) {
			$type = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$message = isset( $notice['message'] ) ? $notice['message'] : '';

			if ( $message ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}

			delete_transient( 'fanfic_moderation_notice' );
		}
	}
}

/**
 * Initialize Moderation Table AJAX handlers
 *
 * Handles AJAX requests for report details and marking reports as reviewed.
 *
 * @since 1.0.0
 */
class Fanfic_Moderation_Ajax {

	/**
	 * Initialize AJAX handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_fanfic_get_report_details', array( __CLASS__, 'ajax_get_report_details' ) );
		add_action( 'wp_ajax_fanfic_mark_reviewed', array( __CLASS__, 'ajax_mark_reviewed' ) );
	}

	/**
	 * AJAX handler to get report details
	 *
	 * Returns full report information for display in modal/expanded view.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_get_report_details() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_moderation_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fanfiction-manager' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'fanfiction-manager' ) ) );
		}

		$report = Fanfic_Moderation_Table::get_report_details( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'fanfiction-manager' ) ) );
		}

		// Format report data
		$reporter = get_user_by( 'id', $report['reporter_id'] );
		$reporter_name = $reporter ? $reporter->display_name : __( 'Anonymous', 'fanfiction-manager' );

		$moderator_name = '';
		if ( ! empty( $report['moderator_id'] ) ) {
			$moderator = get_user_by( 'id', $report['moderator_id'] );
			$moderator_name = $moderator ? $moderator->display_name : '';
		}

		// Get content details
		$post = get_post( $report['reported_item_id'] );
		$content_title = $post ? $post->post_title : __( '[Deleted Content]', 'fanfiction-manager' );
		$content_excerpt = $post && ! empty( $post->post_excerpt ) ? wp_trim_words( $post->post_excerpt, 50, '...' ) : '';
		$content_link = $post ? get_permalink( $post->ID ) : '';

		$data = array(
			'id'              => $report['id'],
			'post_title'      => $content_title,
			'post_excerpt'    => $content_excerpt,
			'post_link'       => $content_link,
			'post_type'       => $report['reported_item_type'],
			'reporter_name'   => $reporter_name,
			'reason'          => $report['reason'],
			'status'          => $report['status'],
			'moderator_name'  => $moderator_name,
			'moderator_notes' => $report['moderator_notes'],
			'created_at'      => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report['created_at'] ),
			'updated_at'      => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report['updated_at'] ),
		);

		wp_send_json_success( array( 'report' => $data ) );
	}

	/**
	 * AJAX handler to mark report as reviewed
	 *
	 * Updates report status to 'reviewed' with moderator action description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_mark_reviewed() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_moderation_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fanfiction-manager' ) ) );
		}

		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Moderation_Table::resolve_report( $report_id, $notes );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Report marked as reviewed.', 'fanfiction-manager' ) ) );
	}
}

// Initialize AJAX handlers
Fanfic_Moderation_Ajax::init();
