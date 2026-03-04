<?php
/**
 * Blocks Hub WP_List_Table implementation.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Fanfic_Blocks_Table
 *
 * @since 2.4.0
 */
class Fanfic_Blocks_Table extends WP_List_Table {

	/**
	 * Table mode key.
	 *
	 * @since 2.4.0
	 * @var string
	 */
	private $block_type = 'reporters';

	/**
	 * Allowed table modes.
	 *
	 * @since 2.4.0
	 * @var string[]
	 */
	private $allowed_types = array( 'reporters', 'authors', 'stories', 'chapters', 'comments' );

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 * @param string $block_type Table mode.
	 */
	public function __construct( $block_type = 'reporters' ) {
		$block_type = sanitize_key( (string) $block_type );
		if ( ! in_array( $block_type, $this->allowed_types, true ) ) {
			$block_type = 'reporters';
		}
		$this->block_type = $block_type;

		parent::__construct(
			array(
				'singular' => __( 'Block', 'fanfiction-manager' ),
				'plural'   => __( 'Blocks', 'fanfiction-manager' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Count rows for a block sub-tab.
	 *
	 * @since 2.4.0
	 * @param string $block_type Tab key.
	 * @return int
	 */
	public static function count_for_type( $block_type ) {
		$block_type = sanitize_key( (string) $block_type );

		switch ( $block_type ) {
			case 'reporters':
				return Fanfic_Blacklist::count_entries( 'report' );
			case 'authors':
				return Fanfic_Blacklist::count_entries( 'message' );
			case 'stories':
				return self::count_blocked_posts( 'fanfiction_story', '_fanfic_story_blocked' );
			case 'chapters':
				return self::count_blocked_posts( 'fanfiction_chapter', '_fanfic_chapter_blocked' );
			case 'comments':
				return self::count_blocked_comments();
			default:
				return 0;
		}
	}

	/**
	 * Count blocked posts by post type and block flag.
	 *
	 * @since 2.4.0
	 * @param string $post_type Post type.
	 * @param string $meta_key Block flag key.
	 * @return int
	 */
	private static function count_blocked_posts( $post_type, $meta_key ) {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => $meta_key,
						'value' => '1',
					),
				),
				'no_found_rows'  => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Count blocked comments.
	 *
	 * @since 2.4.0
	 * @return int
	 */
	private static function count_blocked_comments() {
		$query = new WP_Comment_Query();

		return (int) $query->query(
			array(
				'status'     => 'spam',
				'count'      => true,
				'meta_query' => array(
					array(
						'key'   => 'fanfic_moderation_action',
						'value' => 'spam',
					),
				),
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @since 2.4.0
	 * @return array
	 */
	public function get_columns() {
		if ( 'reporters' === $this->block_type ) {
			return array(
				'target'     => __( 'Target', 'fanfiction-manager' ),
				'blocked_by' => __( 'Blacklisted By', 'fanfiction-manager' ),
				'date'       => __( 'Date', 'fanfiction-manager' ),
				'actions'    => __( 'Actions', 'fanfiction-manager' ),
			);
		}

		if ( 'authors' === $this->block_type ) {
			return array(
				'target'     => __( 'Author', 'fanfiction-manager' ),
				'blocked_by' => __( 'Blacklisted By', 'fanfiction-manager' ),
				'date'       => __( 'Date', 'fanfiction-manager' ),
				'actions'    => __( 'Actions', 'fanfiction-manager' ),
			);
		}

		if ( 'comments' === $this->block_type ) {
			return array(
				'comment'      => __( 'Comment', 'fanfiction-manager' ),
				'on'           => __( 'On', 'fanfiction-manager' ),
				'moderated_by' => __( 'Moderated By', 'fanfiction-manager' ),
				'date'         => __( 'Date', 'fanfiction-manager' ),
				'actions'      => __( 'Actions', 'fanfiction-manager' ),
			);
		}

		return array(
			'title'      => __( 'Title', 'fanfiction-manager' ),
			'author'     => __( 'Author', 'fanfiction-manager' ),
			'reason'     => __( 'Block Reason', 'fanfiction-manager' ),
			'blocked_by' => __( 'Blocked By', 'fanfiction-manager' ),
			'date'       => __( 'Date', 'fanfiction-manager' ),
			'actions'    => __( 'Actions', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get primary column.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		if ( in_array( $this->block_type, array( 'reporters', 'authors' ), true ) ) {
			return 'target';
		}

		if ( 'comments' === $this->block_type ) {
			return 'comment';
		}

		return 'title';
	}

	/**
	 * No sortable columns.
	 *
	 * @since 2.4.0
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
	}

	/**
	 * No items message.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public function no_items() {
		$labels = array(
			'reporters' => __( 'No blacklisted reporters found.', 'fanfiction-manager' ),
			'authors'   => __( 'No blacklisted authors found.', 'fanfiction-manager' ),
			'stories'   => __( 'No blocked stories found.', 'fanfiction-manager' ),
			'chapters'  => __( 'No blocked chapters found.', 'fanfiction-manager' ),
			'comments'  => __( 'No blocked comments found.', 'fanfiction-manager' ),
		);

		echo esc_html( isset( $labels[ $this->block_type ] ) ? $labels[ $this->block_type ] : __( 'No entries found.', 'fanfiction-manager' ) );
	}

	/**
	 * Prepare items.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$results = $this->get_items_for_current_type( $offset, $per_page );
		$this->items = isset( $results['items'] ) ? $results['items'] : array();
		$total_items = isset( $results['total'] ) ? (int) $results['total'] : 0;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Get rows for the active block type.
	 *
	 * @since 2.4.0
	 * @param int $offset Row offset.
	 * @param int $per_page Rows per page.
	 * @return array
	 */
	private function get_items_for_current_type( $offset, $per_page ) {
		switch ( $this->block_type ) {
			case 'reporters':
				return array(
					'items' => Fanfic_Blacklist::get_entries(
						'report',
						array(
							'limit'  => $per_page,
							'offset' => $offset,
						)
					),
					'total' => Fanfic_Blacklist::count_entries( 'report' ),
				);

			case 'authors':
				return array(
					'items' => Fanfic_Blacklist::get_entries(
						'message',
						array(
							'limit'  => $per_page,
							'offset' => $offset,
						)
					),
					'total' => Fanfic_Blacklist::count_entries( 'message' ),
				);

			case 'stories':
				return $this->get_blocked_posts( 'fanfiction_story', '_fanfic_story_blocked', $offset, $per_page );

			case 'chapters':
				return $this->get_blocked_posts( 'fanfiction_chapter', '_fanfic_chapter_blocked', $offset, $per_page );

			case 'comments':
				return $this->get_blocked_comments( $offset, $per_page );

			default:
				return array(
					'items' => array(),
					'total' => 0,
				);
		}
	}

	/**
	 * Query blocked stories/chapters.
	 *
	 * @since 2.4.0
	 * @param string $post_type Post type.
	 * @param string $meta_key Block flag meta key.
	 * @param int    $offset Row offset.
	 * @param int    $per_page Rows per page.
	 * @return array
	 */
	private function get_blocked_posts( $post_type, $meta_key, $offset, $per_page ) {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => $per_page,
				'offset'         => $offset,
				'meta_query'     => array(
					array(
						'key'   => $meta_key,
						'value' => '1',
					),
				),
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_fanfic_blocked_timestamp',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			)
		);

		$items = array();
		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $post ) {
				$target_type = ( 'fanfiction_story' === $post->post_type ) ? 'story' : 'chapter';
				$items[]     = array(
					'post_id'          => $post->ID,
					'post'             => $post,
					'author_id'        => absint( $post->post_author ),
					'blocked_by'       => $this->get_latest_block_actor( $target_type, $post->ID ),
					'block_reason'     => get_post_meta( $post->ID, '_fanfic_block_reason', true ),
					'block_reason_text'=> get_post_meta( $post->ID, '_fanfic_block_reason_text', true ),
					'blocked_at'       => get_post_meta( $post->ID, '_fanfic_blocked_timestamp', true ),
					'target_type'      => $target_type,
				);
			}
		}

		wp_reset_postdata();

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Query blocked comments.
	 *
	 * @since 2.4.0
	 * @param int $offset Row offset.
	 * @param int $per_page Rows per page.
	 * @return array
	 */
	private function get_blocked_comments( $offset, $per_page ) {
		$comments_query = new WP_Comment_Query();
		$comments       = $comments_query->query(
			array(
				'status'     => 'spam',
				'number'     => $per_page,
				'offset'     => $offset,
				'orderby'    => 'comment_date_gmt',
				'order'      => 'DESC',
				'meta_query' => array(
					array(
						'key'   => 'fanfic_moderation_action',
						'value' => 'spam',
					),
				),
			)
		);

		$items = array();
		if ( ! empty( $comments ) ) {
			foreach ( $comments as $comment ) {
				$items[] = array(
					'comment'      => $comment,
					'comment_id'   => absint( $comment->comment_ID ),
					'moderator_id' => absint( get_comment_meta( $comment->comment_ID, 'fanfic_moderated_by', true ) ),
					'blocked_at'   => $comment->comment_date_gmt,
				);
			}
		}

		return array(
			'items' => $items,
			'total' => self::count_blocked_comments(),
		);
	}

	/**
	 * Get latest blocking actor from moderation log.
	 *
	 * @since 2.4.0
	 * @param string $target_type story|chapter.
	 * @param int    $target_id Target ID.
	 * @return int
	 */
	private function get_latest_block_actor( $target_type, $target_id ) {
		if ( ! class_exists( 'Fanfic_Moderation_Log' ) ) {
			return 0;
		}

		$actions = ( 'story' === $target_type )
			? array( 'block_manual', 'block_ban', 'block_rule' )
			: array( 'chapter_block_manual', 'chapter_block_ban', 'chapter_block_rule' );
		$logs = Fanfic_Moderation_Log::get_logs(
			array(
				'target_type' => $target_type,
				'target_id'   => absint( $target_id ),
				'action'      => $actions,
				'limit'       => 1,
			)
		);

		return ! empty( $logs[0]['actor_id'] ) ? absint( $logs[0]['actor_id'] ) : 0;
	}

	/**
	 * Render a user name linked to user edit screen.
	 *
	 * @since 2.4.0
	 * @param int    $user_id User ID.
	 * @param string $fallback Fallback label.
	 * @return string
	 */
	private function render_user_label( $user_id, $fallback = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return $fallback ? esc_html( $fallback ) : '—';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $fallback ? esc_html( $fallback ) : esc_html__( 'Unknown user', 'fanfiction-manager' );
		}

		$edit_url = add_query_arg(
			array( 'user_id' => $user_id ),
			admin_url( 'user-edit.php' )
		);

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $edit_url ),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Render target column for blacklist rows.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_target( $item ) {
		$user_id    = isset( $item['user_id'] ) ? absint( $item['user_id'] ) : 0;
		$ip_address = isset( $item['ip_address'] ) ? sanitize_text_field( $item['ip_address'] ) : '';

		if ( 'authors' === $this->block_type ) {
			if ( ! $user_id ) {
				return esc_html__( 'Unknown author', 'fanfiction-manager' );
			}

			return $this->render_user_label(
				$user_id,
				sprintf( __( 'Author #%d', 'fanfiction-manager' ), $user_id )
			);
		}

		if ( $user_id ) {
			$output = $this->render_user_label(
				$user_id,
				sprintf( __( 'User #%d', 'fanfiction-manager' ), $user_id )
			);
			if ( '' !== $ip_address ) {
				$output .= '<br><small>' . esc_html( $ip_address ) . '</small>';
			}
			return $output;
		}

		if ( '' !== $ip_address ) {
			return esc_html( $ip_address );
		}

		return esc_html__( 'Guest', 'fanfiction-manager' );
	}

	/**
	 * Render title column for blocked stories/chapters.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_title( $item ) {
		$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$post    = isset( $item['post'] ) ? $item['post'] : null;

		if ( ! $post || ! $post_id ) {
			return esc_html__( '(Deleted)', 'fanfiction-manager' );
		}

		$title = '' !== $post->post_title ? $post->post_title : __( '(No Title)', 'fanfiction-manager' );
		$link  = get_edit_post_link( $post_id );
		$out   = $link
			? sprintf( '<a href="%1$s"><strong>%2$s</strong></a>', esc_url( $link ), esc_html( $title ) )
			: '<strong>' . esc_html( $title ) . '</strong>';

		if ( 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
			$story_title = get_the_title( $post->post_parent );
			if ( '' === $story_title ) {
				$story_title = __( '(No Story Title)', 'fanfiction-manager' );
			}
			$out .= '<br><small>' . esc_html( sprintf( __( 'Story: %s', 'fanfiction-manager' ), $story_title ) ) . '</small>';
		}

		return $out;
	}

	/**
	 * Render author column for blocked stories/chapters.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_author( $item ) {
		$author_id = isset( $item['author_id'] ) ? absint( $item['author_id'] ) : 0;
		return $this->render_user_label( $author_id, __( 'Unknown author', 'fanfiction-manager' ) );
	}

	/**
	 * Render reason column for blocked stories/chapters.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_reason( $item ) {
		$reason      = isset( $item['block_reason'] ) ? sanitize_text_field( $item['block_reason'] ) : '';
		$reason_text = isset( $item['block_reason_text'] ) ? sanitize_text_field( $item['block_reason_text'] ) : '';

		$label = '';
		if ( '' !== $reason ) {
			$label = function_exists( 'fanfic_get_block_reason_label' )
				? fanfic_get_block_reason_label( $reason )
				: $reason;
		}

		if ( '' === $label ) {
			$label = __( 'Manual', 'fanfiction-manager' );
		}

		if ( '' !== $reason_text ) {
			return esc_html( $label . ' - ' . $reason_text );
		}

		return esc_html( $label );
	}

	/**
	 * Render blocked-by column.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_blocked_by( $item ) {
		$moderator_id = isset( $item['moderator_id'] ) ? absint( $item['moderator_id'] ) : 0;
		if ( ! $moderator_id && isset( $item['blocked_by'] ) ) {
			$moderator_id = absint( $item['blocked_by'] );
		}

		return $this->render_user_label( $moderator_id, __( 'Unknown moderator', 'fanfiction-manager' ) );
	}

	/**
	 * Render comment excerpt column.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_comment( $item ) {
		$comment = isset( $item['comment'] ) ? $item['comment'] : null;
		if ( ! $comment ) {
			return esc_html__( '(Deleted)', 'fanfiction-manager' );
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 15, '...' );
		if ( '' === $excerpt ) {
			$excerpt = __( '(No content)', 'fanfiction-manager' );
		}

		$edit_link = get_edit_comment_link( $comment->comment_ID );
		if ( $edit_link ) {
			return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_link ), esc_html( $excerpt ) );
		}

		return esc_html( $excerpt );
	}

	/**
	 * Render context column for blocked comments.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_on( $item ) {
		$comment = isset( $item['comment'] ) ? $item['comment'] : null;
		if ( ! $comment ) {
			return esc_html__( '(Deleted)', 'fanfiction-manager' );
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return esc_html__( '(Deleted post)', 'fanfiction-manager' );
		}

		$title = '' !== $post->post_title ? $post->post_title : __( '(No Title)', 'fanfiction-manager' );
		$link  = get_edit_post_link( $post->ID );
		if ( $link ) {
			return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html( $title ) );
		}

		return esc_html( $title );
	}

	/**
	 * Render moderated-by column for blocked comments.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_moderated_by( $item ) {
		$moderator_id = isset( $item['moderator_id'] ) ? absint( $item['moderator_id'] ) : 0;
		return $this->render_user_label( $moderator_id, __( 'Unknown moderator', 'fanfiction-manager' ) );
	}

	/**
	 * Render date column.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_date( $item ) {
		$raw_date = '';

		if ( isset( $item['created_at'] ) ) {
			$raw_date = (string) $item['created_at'];
		} elseif ( isset( $item['blocked_at'] ) ) {
			$raw_date = (string) $item['blocked_at'];
		}

		if ( '' === $raw_date ) {
			return '—';
		}

		$timestamp = is_numeric( $raw_date ) ? (int) $raw_date : strtotime( $raw_date );
		if ( ! $timestamp ) {
			return '—';
		}

		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
	}

	/**
	 * Render actions column.
	 *
	 * @since 2.4.0
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_actions( $item ) {
		if ( in_array( $this->block_type, array( 'reporters', 'authors' ), true ) ) {
			$blacklist_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $blacklist_id ) {
				return '—';
			}

			return sprintf(
				'<button type="button" class="button button-small fanfic-unblacklist" data-blacklist-id="%d">%s</button>',
				$blacklist_id,
				esc_html__( 'Unblacklist', 'fanfiction-manager' )
			);
		}

		if ( 'comments' === $this->block_type ) {
			$comment_id = isset( $item['comment_id'] ) ? absint( $item['comment_id'] ) : 0;
			if ( ! $comment_id ) {
				return '—';
			}

			return sprintf(
				'<button type="button" class="button button-small fanfic-unblock-content" data-target-type="comment" data-target-id="%1$d">%2$s</button>',
				$comment_id,
				esc_html__( 'Unblock', 'fanfiction-manager' )
			);
		}

		$post_id     = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
		$target_type = isset( $item['target_type'] ) ? sanitize_key( $item['target_type'] ) : '';
		if ( ! $post_id || ! in_array( $target_type, array( 'story', 'chapter' ), true ) ) {
			return '—';
		}

		return sprintf(
			'<button type="button" class="button button-small fanfic-unblock-content" data-target-type="%1$s" data-target-id="%2$d">%3$s</button>',
			esc_attr( $target_type ),
			$post_id,
			esc_html__( 'Unblock', 'fanfiction-manager' )
		);
	}

	/**
	 * Default renderer.
	 *
	 * @since 2.4.0
	 * @param array  $item Row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
	}

	/**
	 * Remove default row actions.
	 *
	 * @since 2.4.0
	 * @param array  $item Row.
	 * @param string $column_name Current column.
	 * @param string $primary Primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return '';
	}
}
