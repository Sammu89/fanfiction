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
	}

	/**
	 * Get list of columns
	 *
	 * @since 1.0.0
	 * @return array Associative array of column slug => title.
	 */
	public function get_columns() {
		return array(
			'title'        => __( 'Title', 'fanfiction-manager' ),
			'reported_by'  => __( 'Reported by', 'fanfiction-manager' ),
			'reason'       => __( 'Report reason', 'fanfiction-manager' ),
			'view_message' => __( 'View message', 'fanfiction-manager' ),
			'actions'      => __( 'Action', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get list of sortable columns
	 *
	 * @since 1.0.0
	 * @return array Associative array of column slug => array( db field, default sort ).
	 */
	protected function get_sortable_columns() {
		return array();
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

	public function single_row( $item ) {
		$report_id = absint( $item['id'] );
		$status    = isset( $item['status'] ) ? $item['status'] : 'pending';
		$context   = self::get_report_target_context_data( $item );

		echo '<tr id="fanfic-report-row-' . esc_attr( $report_id ) . '" data-report-id="' . esc_attr( $report_id ) . '" data-status="' . esc_attr( $status ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';

		if ( 'pending' === $status && 'comment' !== $context['target_type'] ) {
			echo '<tr id="fanfic-report-panel-row-' . esc_attr( $report_id ) . '" class="fanfic-report-detail-row" data-report-id="' . esc_attr( $report_id ) . '" style="display:none;">';
			echo '<td colspan="' . esc_attr( count( $this->get_columns() ) ) . '">';
			echo $this->render_block_panel( $item );
			echo '</td>';
			echo '</tr>';
		}
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
			$allowed_statuses = array( 'pending', 'blocked', 'dismissed' );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$filters['status'] = $status;
			}
		}

		// Post type filter
		if ( ! empty( $_GET['post_type_filter'] ) ) {
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ) );
			$allowed_types = array( 'fanfiction_story', 'fanfiction_chapter', 'comment' );
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
				r.reporter_ip,
				r.reason,
				r.details,
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

	protected function get_default_primary_column_name() {
		return 'title';
	}

	public static function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'fanfiction-manager' ),
			'blocked'   => __( 'Blocked', 'fanfiction-manager' ),
			'dismissed' => __( 'Dismissed', 'fanfiction-manager' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );
	}

	public static function get_report_target_context_data( $report ) {
		$report_type = isset( $report['reported_item_type'] ) ? (string) $report['reported_item_type'] : '';
		$target_id   = isset( $report['reported_item_id'] ) ? absint( $report['reported_item_id'] ) : 0;
		$context     = array(
			'target_type'   => 'story',
			'target_id'     => $target_id,
			'title'         => __( '(Deleted)', 'fanfiction-manager' ),
			'display_title' => __( '(Deleted)', 'fanfiction-manager' ),
			'url'           => '',
			'edit_url'      => '',
			'author_id'     => 0,
		);

		if ( 'fanfiction_story' === $report_type ) {
			$post                     = get_post( $target_id );
			$context['target_type']   = 'story';
			$context['title']         = $post ? $post->post_title : __( '(Deleted story)', 'fanfiction-manager' );
			$context['title']         = '' !== $context['title'] ? $context['title'] : __( '(No Title)', 'fanfiction-manager' );
			$context['display_title'] = sprintf( __( 'Story: %s', 'fanfiction-manager' ), $context['title'] );
			$context['url']           = $post ? get_permalink( $target_id ) : '';
			$context['edit_url']      = $post ? get_edit_post_link( $target_id ) : '';
			$context['author_id']     = $post ? absint( $post->post_author ) : 0;
			return $context;
		}

		if ( 'fanfiction_chapter' === $report_type ) {
			$post                     = get_post( $target_id );
			$context['target_type']   = 'chapter';
			$context['title']         = $post ? $post->post_title : __( '(Deleted chapter)', 'fanfiction-manager' );
			$context['title']         = '' !== $context['title'] ? $context['title'] : __( '(No Title)', 'fanfiction-manager' );
			$context['display_title'] = sprintf( __( 'Chapter: %s', 'fanfiction-manager' ), $context['title'] );
			$context['url']           = $post ? get_permalink( $target_id ) : '';
			$context['edit_url']      = $post ? get_edit_post_link( $target_id ) : '';
			$context['author_id']     = $post ? absint( $post->post_author ) : 0;
			return $context;
		}

		$comment                   = get_comment( $target_id );
		$context['target_type']    = 'comment';
		$context['title']          = __( '(Deleted context)', 'fanfiction-manager' );
		$context['display_title']  = sprintf( __( 'Comment on: %s', 'fanfiction-manager' ), $context['title'] );
		$context['url']           = $comment ? get_comment_link( $target_id ) : '';
		$context['edit_url']       = $comment ? get_edit_comment_link( $target_id ) : '';
		$context['author_id']      = $comment ? absint( $comment->user_id ) : 0;

		if ( $comment ) {
			$post = get_post( $comment->comment_post_ID );
			$context['title'] = $post ? $post->post_title : __( '(Deleted context)', 'fanfiction-manager' );
			$context['title'] = '' !== $context['title'] ? $context['title'] : __( '(No Title)', 'fanfiction-manager' );
			$context['display_title'] = sprintf( __( 'Comment on: %s', 'fanfiction-manager' ), $context['title'] );
		}

		return $context;
	}

	public static function get_default_block_reason( $report_reason ) {
		$reason = strtolower( trim( wp_strip_all_tags( (string) $report_reason ) ) );

		if ( false !== strpos( $reason, 'spam' ) ) {
			return 'spam';
		}
		if ( false !== strpos( $reason, 'harassment' ) || false !== strpos( $reason, 'bully' ) ) {
			return 'harassment';
		}
		if ( false !== strpos( $reason, 'inappropriate' ) ) {
			return 'inappropriate';
		}
		if ( false !== strpos( $reason, 'copyright' ) ) {
			return 'copyright';
		}
		if ( false !== strpos( $reason, 'illegal' ) ) {
			return 'illegal';
		}
		if ( false !== strpos( $reason, 'minor' ) ) {
			return 'underage';
		}
		if ( false !== strpos( $reason, 'other' ) ) {
			return 'other';
		}

		return 'manual';
	}

	public static function get_block_reason_options() {
		$labels = function_exists( 'fanfic_get_block_reason_labels' ) ? fanfic_get_block_reason_labels() : array();
		$keys   = array( 'manual', 'tos_violation', 'copyright', 'inappropriate', 'spam', 'harassment', 'illegal', 'underage', 'rating_mismatch', 'other' );
		$options = array();

		foreach ( $keys as $key ) {
			if ( isset( $labels[ $key ] ) ) {
				$options[ $key ] = $labels[ $key ];
			}
		}

		return $options;
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
			'<button type="button" class="button button-small fanfic-view-report-button" data-report-id="%d">%s</button>',
			$report_id,
			esc_html__( 'View Details', 'fanfiction-manager' )
		);
	}

	protected function column_title( $item ) {
		$context = self::get_report_target_context_data( $item );
		$link    = ! empty( $context['url'] ) ? $context['url'] : $context['edit_url'];
		$prefix  = '';

		if ( 'story' === $context['target_type'] ) {
			$prefix = __( 'Story:', 'fanfiction-manager' );
		} elseif ( 'chapter' === $context['target_type'] ) {
			$prefix = __( 'Chapter:', 'fanfiction-manager' );
		} elseif ( 'comment' === $context['target_type'] ) {
			$prefix = __( 'Comment on:', 'fanfiction-manager' );
		}

		if ( $link ) {
			return sprintf(
				'<strong>%1$s</strong> <a href="%2$s" target="_blank" rel="noopener noreferrer"><strong>%3$s</strong></a>',
				esc_html( $prefix ),
				esc_url( $link ),
				esc_html( $context['title'] )
			);
		}

		if ( $prefix ) {
			return sprintf(
				'<strong>%1$s</strong> <strong>%2$s</strong>',
				esc_html( $prefix ),
				esc_html( $context['title'] )
			);
		}

		return sprintf(
			'<strong>%s</strong>',
			esc_html( $context['title'] )
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
		$reporter_ip = isset( $item['reporter_ip'] ) ? sanitize_text_field( (string) $item['reporter_ip'] ) : '';

		if ( $reporter_id > 0 ) {
			$user = get_userdata( $reporter_id );
			$output = '';

			if ( $user ) {
				$output = sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_user_link( $reporter_id ) ),
					esc_html( $user->display_name )
				);
			} else {
				$output = sprintf(
					'<span>%s</span>',
					esc_html( sprintf( __( 'User #%d', 'fanfiction-manager' ), $reporter_id ) )
				);
			}

			$is_blacklisted = Fanfic_Blacklist::is_reporter_blacklisted( $reporter_id );
			if ( $is_blacklisted ) {
				$output .= ' <span class="fanfic-blacklist-badge">' . esc_html__( 'Blacklisted', 'fanfiction-manager' ) . '</span>';
			} else {
				$output .= sprintf(
					' <button type="button" class="button button-small fanfic-blacklist-reporter" data-user-id="%1$d" data-ip="%2$s">%3$s</button>',
					$reporter_id,
					esc_attr( $reporter_ip ),
					esc_html__( 'Blacklist', 'fanfiction-manager' )
				);
			}

			return $output;
		}

		if ( '' !== $reporter_ip ) {
			$output = esc_html( $reporter_ip );

			$is_blacklisted = Fanfic_Blacklist::is_reporter_blacklisted_by_ip( $reporter_ip );
			if ( $is_blacklisted ) {
				$output .= ' <span class="fanfic-blacklist-badge">' . esc_html__( 'Blacklisted', 'fanfiction-manager' ) . '</span>';
			} else {
				$output .= sprintf(
					' <button type="button" class="button button-small fanfic-blacklist-reporter" data-user-id="0" data-ip="%1$s">%2$s</button>',
					esc_attr( $reporter_ip ),
					esc_html__( 'Blacklist', 'fanfiction-manager' )
				);
			}

			return $output;
		}

		return esc_html__( 'Guest', 'fanfiction-manager' );
	}

	protected function column_reason( $item ) {
		return esc_html( $item['reason'] );
	}

	protected function column_view_message( $item ) {
		$is_comment  = 'comment' === (string) $item['reported_item_type'];
		$has_message = $is_comment || '' !== trim( (string) $item['details'] );
		$label       = $is_comment ? __( 'View comment', 'fanfiction-manager' ) : __( 'View message', 'fanfiction-manager' );

		return sprintf(
			'<button type="button" class="button button-small fanfic-report-view-message" data-report-id="%1$d" %2$s>%3$s</button>',
			absint( $item['id'] ),
			$has_message ? '' : 'disabled aria-disabled="true"',
			esc_html( $label )
		);
	}

	private function get_handled_action_summary( $item ) {
		$status          = isset( $item['status'] ) ? (string) $item['status'] : '';
		$moderator_id    = isset( $item['moderator_id'] ) ? absint( $item['moderator_id'] ) : 0;
		$moderator       = $moderator_id ? get_userdata( $moderator_id ) : false;
		$moderator_name  = $moderator && ! empty( $moderator->display_name ) ? $moderator->display_name : __( 'Unknown moderator', 'fanfiction-manager' );
		$summary_formats = array(
			'blocked'   => __( 'Blocked by %s', 'fanfiction-manager' ),
			'dismissed' => __( 'Dismissed by %s', 'fanfiction-manager' ),
		);

		if ( ! isset( $summary_formats[ $status ] ) ) {
			return esc_html( self::get_status_label( $status ) );
		}

		return sprintf( $summary_formats[ $status ], esc_html( $moderator_name ) );
	}

	protected function column_actions( $item ) {
		$report_id = absint( $item['id'] );
		$status    = isset( $item['status'] ) ? $item['status'] : 'pending';
		$context   = self::get_report_target_context_data( $item );
		$output    = '<div class="fanfic-report-action-stack">';

		if ( 'pending' === $status ) {
			$output .= '<div class="fanfic-report-action-buttons">';

			if ( 'comment' === $context['target_type'] ) {
				$output .= sprintf(
					'<button type="button" class="button button-primary fanfic-report-block-comment" data-report-id="%1$d">%2$s</button>',
					$report_id,
					esc_html__( 'Block', 'fanfiction-manager' )
				);
			} else {
				$output .= sprintf(
					'<button type="button" class="button button-primary fanfic-report-toggle-block" data-report-id="%1$d" aria-expanded="false">%2$s</button>',
					$report_id,
					esc_html__( 'Block', 'fanfiction-manager' )
				);
			}

			$output .= sprintf(
				'<button type="button" class="button fanfic-report-dismiss" data-report-id="%1$d">%2$s</button>',
				$report_id,
				esc_html__( 'Dismiss', 'fanfiction-manager' )
			);
			$output .= sprintf(
				'<button type="button" class="button button-link-delete fanfic-report-delete" data-report-id="%1$d">%2$s</button>',
				$report_id,
				esc_html__( 'Delete report', 'fanfiction-manager' )
			);
			$output .= '</div>';
		} else {
			$output .= sprintf(
				'<span class="fanfic-report-action-note status-%1$s">%2$s</span>',
				esc_attr( $status ),
				$this->get_handled_action_summary( $item )
			);
		}

		$output .= '</div>';

		return $output;
	}

	private function render_block_panel( $item ) {
		$report_id      = absint( $item['id'] );
		$default_reason = self::get_default_block_reason( $item['reason'] );
		$options_markup = '';

		foreach ( self::get_block_reason_options() as $value => $label ) {
			$options_markup .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $default_reason, $value, false ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<div class="fanfic-report-block-panel" data-report-id="%1$d">' .
				'<p class="fanfic-report-block-panel-title">%2$s</p>' .
				'<p><label for="fanfic-report-reason-%1$d">%3$s</label><select id="fanfic-report-reason-%1$d" class="fanfic-report-block-reason widefat">%4$s</select></p>' .
				'<div class="fanfic-report-note-grid">' .
					'<div class="fanfic-report-note-field"><label for="fanfic-report-note-%1$d">%5$s</label><textarea id="fanfic-report-note-%1$d" class="fanfic-report-internal-note" rows="4"></textarea></div>' .
					'<div class="fanfic-report-note-field"><label for="fanfic-report-message-%1$d">%6$s</label><textarea id="fanfic-report-message-%1$d" class="fanfic-report-author-message" rows="4"></textarea></div>' .
				'</div>' .
				'<div class="fanfic-report-panel-actions">' .
					'<button type="button" class="button button-primary fanfic-report-confirm-block" data-report-id="%1$d">%7$s</button>' .
					'<button type="button" class="button fanfic-report-cancel-block" data-report-id="%1$d">%8$s</button>' .
				'</div>' .
				'<div class="fanfic-report-panel-message" aria-live="polite"></div>' .
			'</div>',
			$report_id,
			esc_html__( 'Block content', 'fanfiction-manager' ),
			esc_html__( 'Block reason', 'fanfiction-manager' ),
			$options_markup,
			esc_html__( 'Internal Note', 'fanfiction-manager' ),
			esc_html__( 'Send Author Message', 'fanfiction-manager' ),
			esc_html__( 'Confirm Block', 'fanfiction-manager' ),
			esc_html__( 'Cancel', 'fanfiction-manager' )
		);
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
			'blocked'   => __( 'Blocked', 'fanfiction-manager' ),
			'dismissed' => __( 'Dismissed', 'fanfiction-manager' ),
		);

		$status_classes = array(
			'pending'   => 'status-badge status-warning',
			'blocked'   => 'status-badge status-blocked',
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
		return '';
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
				<option value=""><?php esc_html_e( 'All types', 'fanfiction-manager' ); ?></option>
				<option value="fanfiction_story" <?php selected( $current_post_type, 'fanfiction_story' ); ?>>
					<?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?>
				</option>
				<option value="fanfiction_chapter" <?php selected( $current_post_type, 'fanfiction_chapter' ); ?>>
					<?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?>
				</option>
				<option value="comment" <?php selected( $current_post_type, 'comment' ); ?>>
					<?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?>
				</option>
			</select>

			<!-- Reporter Filter -->
			<select name="reporter_filter" id="reporter-filter">
				<option value=""><?php esc_html_e( 'All registered reporters', 'fanfiction-manager' ); ?></option>
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
	 * Get a report row
	 *
	 * @since 1.0.0
	 * @param int $report_id Report ID.
	 * @return array|null Report row.
	 */
	public static function get_report( $report_id ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$reports_table} WHERE id = %d", absint( $report_id ) ),
			ARRAY_A
		);
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
		$report = self::get_report( $report_id );
		if ( ! $report ) {
			return null;
		}

		$context           = self::get_report_target_context_data( $report );
		$reporter_id       = absint( $report['reporter_id'] );
		$reporter          = $reporter_id ? get_userdata( $reporter_id ) : false;
		$message           = (string) $report['details'];
		$message_label     = __( 'Reporter message', 'fanfiction-manager' );
		$secondary_message = '';

		if ( 'comment' === $context['target_type'] ) {
			$comment = get_comment( $context['target_id'] );
			if ( $comment ) {
				$message = (string) $comment->comment_content;
			}
			$message_label     = __( 'Comment content', 'fanfiction-manager' );
			$secondary_message = (string) $report['details'];
		}

		return array(
			'id'                => absint( $report['id'] ),
			'title'             => $context['display_title'],
			'target_type'       => $context['target_type'],
			'reported_by'       => $reporter ? $reporter->display_name : ( ! empty( $report['reporter_ip'] ) ? $report['reporter_ip'] : __( 'Guest', 'fanfiction-manager' ) ),
			'reason'            => (string) $report['reason'],
			'message'           => $message,
			'message_label'     => $message_label,
			'secondary_message' => $secondary_message,
			'status'            => (string) $report['status'],
			'status_label'      => self::get_status_label( $report['status'] ),
		);
	}

	public static function update_report_status( $report_id, $status, $moderator_id, $moderator_notes = '' ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';

		$result = $wpdb->update(
			$reports_table,
			array(
				'status'          => sanitize_key( $status ),
				'moderator_id'    => absint( $moderator_id ),
				'moderator_notes' => sanitize_textarea_field( $moderator_notes ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => absint( $report_id ) ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public static function delete_report_record( $report_id ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$result        = $wpdb->delete( $reports_table, array( 'id' => absint( $report_id ) ), array( '%d' ) );

		return ! empty( $result );
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
	public static function add_admin_notice( $type, $message ) {
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

class Fanfic_Moderation_Ajax {

	public static function init() {
		add_action( 'wp_ajax_fanfic_get_report_details', array( __CLASS__, 'ajax_get_report_details' ) );
		add_action( 'wp_ajax_fanfic_block_report', array( __CLASS__, 'ajax_block_report' ) );
		add_action( 'wp_ajax_fanfic_dismiss_report', array( __CLASS__, 'ajax_dismiss_report' ) );
		add_action( 'wp_ajax_fanfic_delete_report', array( __CLASS__, 'ajax_delete_report' ) );
		add_action( 'wp_ajax_fanfic_blacklist_reporter', array( __CLASS__, 'ajax_blacklist_reporter' ) );
		add_action( 'wp_ajax_fanfic_blacklist_message_sender', array( __CLASS__, 'ajax_blacklist_message_sender' ) );
		add_action( 'wp_ajax_fanfic_remove_blacklist', array( __CLASS__, 'ajax_remove_blacklist' ) );
		add_action( 'wp_ajax_fanfic_unblock_content', array( __CLASS__, 'ajax_unblock_content' ) );
	}

	private static function verify_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_moderation_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fanfiction-manager' ) ) );
		}
	}

	public static function ajax_get_report_details() {
		self::verify_request();

		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'fanfiction-manager' ) ) );
		}

		$report = Fanfic_Moderation_Table::get_report_details( $report_id );

		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'fanfiction-manager' ) ) );
		}

		wp_send_json_success( array( 'report' => $report ) );
	}

	private static function build_log_reason( $report, $block_reason = '', $internal_note = '', $author_message = '' ) {
		$parts = array();
		$parts[] = sprintf( __( 'Report: %s.', 'fanfiction-manager' ), (string) $report['reason'] );
		if ( '' !== $block_reason ) {
			$parts[] = sprintf( __( 'Block: %s.', 'fanfiction-manager' ), function_exists( 'fanfic_get_block_reason_label' ) ? fanfic_get_block_reason_label( $block_reason ) : $block_reason );
		}
		if ( '' !== $internal_note ) {
			$parts[] = sprintf( __( 'Internal note: %s', 'fanfiction-manager' ), $internal_note );
		}
		if ( '' !== $author_message ) {
			$parts[] = sprintf( __( 'Author message: %s', 'fanfiction-manager' ), $author_message );
		}
		return implode( ' ', $parts );
	}

	public static function ajax_block_report() {
		self::verify_request();
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$block_reason   = isset( $_POST['block_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['block_reason'] ) ) : 'manual';
		$internal_note  = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';
		$author_message = isset( $_POST['author_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['author_message'] ) ) : '';
		$moderator_id   = get_current_user_id();
		$report         = Fanfic_Moderation_Table::get_report( $report_id );

		if ( ! $report || 'pending' !== $report['status'] ) {
			wp_send_json_error( array( 'message' => __( 'This report cannot be blocked.', 'fanfiction-manager' ) ) );
		}

		$context = Fanfic_Moderation_Table::get_report_target_context_data( $report );
		$notes   = '';

		if ( 'comment' === $context['target_type'] ) {
			$comment = get_comment( $context['target_id'] );
			if ( ! $comment ) {
				wp_send_json_error( array( 'message' => __( 'Comment not found.', 'fanfiction-manager' ) ) );
			}
			wp_spam_comment( $context['target_id'] );
			update_comment_meta( $context['target_id'], 'fanfic_moderated_at', current_time( 'mysql' ) );
			update_comment_meta( $context['target_id'], 'fanfic_moderated_by', $moderator_id );
			update_comment_meta( $context['target_id'], 'fanfic_moderation_action', 'spam' );
			$notes = self::build_log_reason( $report );
			if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
				Fanfic_Moderation_Log::insert( $moderator_id, 'comment_blocked', 'comment', $context['target_id'], $notes );
			}
			Fanfic_Moderation_Table::update_report_status( $report_id, 'blocked', $moderator_id, $notes );
			Fanfic_Moderation_Table::add_admin_notice( 'success', __( 'Comment blocked successfully.', 'fanfiction-manager' ) );
			wp_send_json_success( array( 'message' => __( 'Comment blocked successfully.', 'fanfiction-manager' ) ) );
		}

		$normalized_reason = function_exists( 'fanfic_normalize_block_reason_code' ) ? fanfic_normalize_block_reason_code( $block_reason, Fanfic_Moderation_Table::get_default_block_reason( $report['reason'] ) ) : $block_reason;
		$result = 'story' === $context['target_type']
			? ( function_exists( 'fanfic_block_story' ) ? fanfic_block_story( $context['target_id'], $normalized_reason, $moderator_id, '' ) : false )
			: ( function_exists( 'fanfic_block_chapter' ) ? fanfic_block_chapter( $context['target_id'], $normalized_reason, $moderator_id, '' ) : false );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to block the reported content.', 'fanfiction-manager' ) ) );
		}
		if ( function_exists( 'fanfic_set_restriction_reply_message' ) ) {
			fanfic_set_restriction_reply_message( $context['target_type'], $context['target_id'], $author_message );
		}
		if ( $author_message && $context['author_id'] > 0 && class_exists( 'Fanfic_Notifications' ) ) {
			Fanfic_Notifications::create_notification( $context['author_id'], Fanfic_Notifications::TYPE_MOD_CONTENT_BLOCKED, sprintf( __( 'Your %1$s "%2$s" has been blocked. Moderator message: %3$s', 'fanfiction-manager' ), $context['target_type'], $context['title'], $author_message ), array(), true );
		}
		$notes = self::build_log_reason( $report, $normalized_reason, $internal_note, $author_message );
		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			$log_action = 'story' === $context['target_type'] ? 'block_manual' : 'chapter_block_manual';
			if ( ! Fanfic_Moderation_Log::update_latest_reason( $moderator_id, $log_action, $context['target_type'], $context['target_id'], $notes ) ) {
				Fanfic_Moderation_Log::insert( $moderator_id, $log_action, $context['target_type'], $context['target_id'], $notes );
			}
		}
		Fanfic_Moderation_Table::update_report_status( $report_id, 'blocked', $moderator_id, $notes );
		$message = 'story' === $context['target_type'] ? __( 'Story blocked successfully.', 'fanfiction-manager' ) : __( 'Chapter blocked successfully.', 'fanfiction-manager' );
		Fanfic_Moderation_Table::add_admin_notice( 'success', $message );
		wp_send_json_success( array( 'message' => $message ) );
	}

	public static function ajax_dismiss_report() {
		self::verify_request();
		$report_id    = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$moderator_id = get_current_user_id();
		$report       = Fanfic_Moderation_Table::get_report( $report_id );
		if ( ! $report || 'pending' !== $report['status'] ) {
			wp_send_json_error( array( 'message' => __( 'This report cannot be dismissed.', 'fanfiction-manager' ) ) );
		}
		$context = Fanfic_Moderation_Table::get_report_target_context_data( $report );
		$notes   = self::build_log_reason( $report );
		Fanfic_Moderation_Table::update_report_status( $report_id, 'dismissed', $moderator_id, $notes );
		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			Fanfic_Moderation_Log::insert( $moderator_id, 'report_dismissed', $context['target_type'], $context['target_id'], $notes );
		}
		Fanfic_Moderation_Table::add_admin_notice( 'success', __( 'Report dismissed successfully.', 'fanfiction-manager' ) );
		wp_send_json_success( array( 'message' => __( 'Report dismissed successfully.', 'fanfiction-manager' ) ) );
	}

	public static function ajax_delete_report() {
		self::verify_request();
		$report_id    = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$moderator_id = get_current_user_id();
		$report       = Fanfic_Moderation_Table::get_report( $report_id );
		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Report not found.', 'fanfiction-manager' ) ) );
		}
		$context = Fanfic_Moderation_Table::get_report_target_context_data( $report );
		$notes   = self::build_log_reason( $report );
		if ( ! Fanfic_Moderation_Table::delete_report_record( $report_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete the report.', 'fanfiction-manager' ) ) );
		}
		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			Fanfic_Moderation_Log::insert( $moderator_id, 'report_deleted', $context['target_type'], $context['target_id'], $notes );
		}
		Fanfic_Moderation_Table::add_admin_notice( 'success', __( 'Report deleted successfully.', 'fanfiction-manager' ) );
		wp_send_json_success( array( 'message' => __( 'Report deleted successfully.', 'fanfiction-manager' ) ) );
	}

	public static function ajax_blacklist_reporter() {
		self::verify_request();

		$user_id      = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$ip_address   = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		$moderator_id = get_current_user_id();

		if ( ! $user_id && '' === trim( $ip_address ) ) {
			wp_send_json_error( array( 'message' => __( 'No reporter data was provided.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Blacklist::add( 'report', $user_id, $ip_address, $moderator_id, '' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$reason = $user_id
			? sprintf( __( 'Reporter user ID: %d', 'fanfiction-manager' ), $user_id )
			: sprintf( __( 'Reporter IP: %s', 'fanfiction-manager' ), $ip_address );
		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			Fanfic_Moderation_Log::insert( $moderator_id, 'reporter_blacklisted', 'user', $user_id, $reason );
		}

		$message = __( 'Reporter blacklisted successfully.', 'fanfiction-manager' );
		Fanfic_Moderation_Table::add_admin_notice( 'success', $message );
		wp_send_json_success( array( 'message' => $message ) );
	}

	public static function ajax_blacklist_message_sender() {
		self::verify_request();

		$user_id      = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$moderator_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Blacklist::add( 'message', $user_id, '', $moderator_id, '' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			Fanfic_Moderation_Log::insert(
				$moderator_id,
				'message_sender_blacklisted',
				'user',
				$user_id,
				sprintf( __( 'Message sender user ID: %d', 'fanfiction-manager' ), $user_id )
			);
		}

		$message = __( 'Author blacklisted successfully.', 'fanfiction-manager' );
		Fanfic_Moderation_Table::add_admin_notice( 'success', $message );
		wp_send_json_success( array( 'message' => $message ) );
	}

	public static function ajax_remove_blacklist() {
		self::verify_request();

		$blacklist_id = isset( $_POST['blacklist_id'] ) ? absint( $_POST['blacklist_id'] ) : 0;
		$moderator_id = get_current_user_id();

		if ( ! $blacklist_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid blacklist entry.', 'fanfiction-manager' ) ) );
		}

		$entry = Fanfic_Blacklist::get_entry( $blacklist_id );
		if ( ! $entry ) {
			wp_send_json_error( array( 'message' => __( 'Blacklist entry not found.', 'fanfiction-manager' ) ) );
		}

		if ( ! Fanfic_Blacklist::remove( $blacklist_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to remove blacklist entry.', 'fanfiction-manager' ) ) );
		}

		$entry_type = isset( $entry['blacklist_type'] ) ? sanitize_key( $entry['blacklist_type'] ) : '';
		$user_id    = isset( $entry['user_id'] ) ? absint( $entry['user_id'] ) : 0;
		$ip_address = isset( $entry['ip_address'] ) ? sanitize_text_field( $entry['ip_address'] ) : '';

		$log_action = 'report' === $entry_type ? 'reporter_unblacklisted' : 'message_sender_unblacklisted';
		$message    = 'report' === $entry_type
			? __( 'Reporter removed from blacklist.', 'fanfiction-manager' )
			: __( 'Author removed from blacklist.', 'fanfiction-manager' );
		$reason     = $user_id
			? sprintf( __( 'User ID: %d', 'fanfiction-manager' ), $user_id )
			: sprintf( __( 'IP: %s', 'fanfiction-manager' ), $ip_address );

		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			Fanfic_Moderation_Log::insert( $moderator_id, $log_action, 'user', $user_id, $reason );
		}

		Fanfic_Moderation_Table::add_admin_notice( 'success', $message );
		wp_send_json_success( array( 'message' => $message ) );
	}

	public static function ajax_unblock_content() {
		self::verify_request();

		$target_type  = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : '';
		$target_id    = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$moderator_id = get_current_user_id();

		if ( ! $target_id || ! in_array( $target_type, array( 'story', 'chapter', 'comment' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid unblock target.', 'fanfiction-manager' ) ) );
		}

		$message = '';
		if ( 'story' === $target_type ) {
			$post = get_post( $target_id );
			if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
			}

			$result = function_exists( 'fanfic_remove_post_block' )
				? fanfic_remove_post_block(
					$target_id,
					array(
						'actor_id'       => $moderator_id,
						'restore_status' => false,
					)
				)
				: false;
			if ( ! $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to unblock story.', 'fanfiction-manager' ) ) );
			}
			$message = __( 'Story unblocked successfully.', 'fanfiction-manager' );
		} elseif ( 'chapter' === $target_type ) {
			$post = get_post( $target_id );
			if ( ! $post || 'fanfiction_chapter' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
			}

			$result = function_exists( 'fanfic_remove_post_block' )
				? fanfic_remove_post_block(
					$target_id,
					array(
						'actor_id'       => $moderator_id,
						'restore_status' => false,
					)
				)
				: false;
			if ( ! $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to unblock chapter.', 'fanfiction-manager' ) ) );
			}
			$message = __( 'Chapter unblocked successfully.', 'fanfiction-manager' );
		} else {
			$comment = get_comment( $target_id );
			if ( ! $comment ) {
				wp_send_json_error( array( 'message' => __( 'Comment not found.', 'fanfiction-manager' ) ) );
			}

			wp_set_comment_status( $target_id, 'approve' );
			delete_comment_meta( $target_id, 'fanfic_moderation_action' );
			$message = __( 'Comment unblocked successfully.', 'fanfiction-manager' );
		}

		Fanfic_Moderation_Table::add_admin_notice( 'success', $message );
		wp_send_json_success( array( 'message' => $message ) );
	}
}

// Initialize AJAX handlers
Fanfic_Moderation_Ajax::init();
