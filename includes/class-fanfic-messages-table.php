<?php
/**
 * Moderation Messages WP_List_Table Implementation
 *
 * Implements WP_List_Table for the moderation messages tab, displaying
 * blocked-author messages with inline expansion, moderator notes, and
 * per-message actions (Unblock, Ignore, Delete).
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Fanfic_Messages_Table
 *
 * Extends WP_List_Table to display and manage blocked-author moderation messages.
 * Each row can be expanded inline to reveal the full message body, a moderator
 * note field, and contextual action buttons.
 *
 * @package FanfictionManager
 * @since 2.3.0
 */
class Fanfic_Messages_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * Initialises the list table with singular/plural labels and no AJAX mode.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'Message',
			'plural'   => 'Messages',
			'ajax'     => false,
		) );
	}

	/**
	 * Returns the list of columns for this table.
	 *
	 * @since 2.3.0
	 * @return array Associative array of column_key => label.
	 */
	public function get_columns() {
		return array(
			'expand'          => '',
			'author'          => __( 'Author', 'fanfiction-manager' ),
			'target'          => __( 'Blocked Item', 'fanfiction-manager' ),
			'message_preview' => __( 'Message', 'fanfiction-manager' ),
			'date'            => __( 'Date', 'fanfiction-manager' ),
			'status'          => __( 'Status', 'fanfiction-manager' ),
			'actions'         => __( 'Actions', 'fanfiction-manager' ),
		);
	}

	/**
	 * Returns the list of sortable columns.
	 *
	 * @since 2.3.0
	 * @return array Associative array of column_key => array( db_field, is_currently_sorted ).
	 */
	protected function get_sortable_columns() {
		return array(
			'date'   => array( 'created_at', true ),
			'status' => array( 'status', false ),
		);
	}

	/**
	 * No bulk actions are defined for this table.
	 *
	 * @since 2.3.0
	 * @return array Empty array.
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * Prepares rows for display.
	 *
	 * Fetches the total count and a paginated page of messages from
	 * Fanfic_Moderation_Messages, then sets pagination args and $this->items.
	 *
	 * @since 2.3.0
	 *
	 * @param string $status_filter Status to filter by. Default 'unread'.
	 * @return void
	 */
	public function prepare_items( $status_filter = 'unread' ) {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 25;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'DESC';

		$filter_args = array(
			'status'  => 'all' === $status_filter ? '' : $status_filter,
			'orderby' => $orderby,
			'order'   => $order,
		);

		$total = Fanfic_Moderation_Messages::count_messages( $filter_args );

		$this->items = Fanfic_Moderation_Messages::get_messages( array_merge( $filter_args, array(
			'limit'  => $per_page,
			'offset' => $offset,
		) ) );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );
	}

	/**
	 * Renders a single table row plus its hidden detail row.
	 *
	 * The standard data row is output first. A second hidden row follows
	 * immediately; it contains the full message text, a moderator note
	 * textarea, and contextual action buttons.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row as an associative array.
	 * @return void
	 */
	public function single_row( $item ) {
		$message_id  = absint( $item['id'] );
		$target_type = isset( $item['target_type'] ) ? $item['target_type'] : '';
		$target_id   = absint( isset( $item['target_id'] ) ? $item['target_id'] : 0 );
		$status      = isset( $item['status'] ) ? $item['status'] : '';

		// Standard data row.
		echo '<tr id="fanfic-msg-row-' . esc_attr( $message_id ) . '" class="fanfic-message-row" data-message-id="' . esc_attr( $message_id ) . '" data-status="' . esc_attr( $status ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';

		// Determine whether the target is still restricted so we know
		// whether to show the Unblock button.
		$is_restricted = false;
		if ( 'story' === $target_type ) {
			$is_restricted = fanfic_is_story_blocked( $target_id );
		} elseif ( 'chapter' === $target_type ) {
			$is_restricted = fanfic_is_chapter_blocked( $target_id );
		} elseif ( 'user' === $target_type ) {
			$is_restricted = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
		}

		$existing_note = isset( $item['moderator_note'] ) ? $item['moderator_note'] : '';
		$can_take_actions = ! in_array( $status, array( 'resolved', 'deleted' ), true );
		$restriction_context = function_exists( 'fanfic_get_restriction_context' ) ? fanfic_get_restriction_context( $target_type, $target_id ) : array();
		$admin_restriction_summary = function_exists( 'fanfic_get_admin_restriction_summary' ) ? fanfic_get_admin_restriction_summary( $target_type, $target_id ) : '';

		// Hidden detail row.
		?>
		<tr class="fanfic-message-detail-row"
			id="fanfic-msg-detail-<?php echo esc_attr( $message_id ); ?>"
			data-message-id="<?php echo esc_attr( $message_id ); ?>"
			style="display:none;">
			<td colspan="7">
				<div class="fanfic-message-detail-inner">

					<blockquote class="fanfic-message-full-text">
						<?php echo esc_html( isset( $item['message'] ) ? $item['message'] : '' ); ?>
					</blockquote>

					<?php if ( ! empty( $admin_restriction_summary ) ) : ?>
						<p class="description fanfic-msg-restriction-reason">
							<?php echo esc_html( $admin_restriction_summary ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! $is_restricted ) : ?>
						<p class="description fanfic-msg-already-unblocked-note">
							<?php esc_html_e( 'This target is already unblocked. Unblock is unavailable, but the message remains for review.', 'fanfiction-manager' ); ?>
						</p>
					<?php endif; ?>

					<p>
						<label for="fanfic-msg-note-<?php echo esc_attr( $message_id ); ?>">
							<?php esc_html_e( 'Moderator Note:', 'fanfiction-manager' ); ?>
						</label>
					</p>
					<textarea
						id="fanfic-msg-note-<?php echo esc_attr( $message_id ); ?>"
						name="moderator_note"
						class="fanfic-msg-note-input"
						data-message-id="<?php echo esc_attr( $message_id ); ?>"
						rows="3"
						style="width:100%;"
					><?php echo esc_textarea( $existing_note ); ?></textarea>

					<div class="fanfic-message-detail-actions" style="margin-top:8px;">
						<?php if ( $is_restricted && $can_take_actions ) : ?>
							<button type="button"
								class="button button-primary fanfic-msg-action-unblock"
								data-message-id="<?php echo esc_attr( $message_id ); ?>"
								data-target-type="<?php echo esc_attr( $target_type ); ?>"
								data-target-id="<?php echo esc_attr( $target_id ); ?>"
							><?php esc_html_e( 'Unblock', 'fanfiction-manager' ); ?></button>
						<?php endif; ?>

						<?php if ( 'ignored' !== $status && $can_take_actions ) : ?>
							<button type="button"
								class="button fanfic-msg-action-ignore"
								data-message-id="<?php echo esc_attr( $message_id ); ?>"
							><?php esc_html_e( 'Ignore', 'fanfiction-manager' ); ?></button>
						<?php endif; ?>

						<?php if ( 'deleted' !== $status ) : ?>
							<button type="button"
								class="button fanfic-msg-action-delete"
								data-message-id="<?php echo esc_attr( $message_id ); ?>"
							><?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?></button>
						<?php endif; ?>
					</div>

				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the expand toggle column.
	 *
	 * Outputs a button that JavaScript uses to show/hide the detail row.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_expand( $item ) {
		return sprintf(
			'<button type="button" class="fanfic-expand-message button-link" data-row-id="%s" aria-expanded="false"><span class="dashicons dashicons-arrow-right-alt2 fanfic-expand-message-icon" aria-hidden="true"></span><span class="fanfic-expand-message-label">%s</span></button>',
			esc_attr( $item['id'] ),
			esc_html__( 'Expand', 'fanfiction-manager' )
		);
	}

	/**
	 * Renders the author column.
	 *
	 * Displays the author's display name linked to the WordPress user-edit screen.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_author( $item ) {
		$author_id = absint( $item['author_id'] );
		$user      = get_userdata( $author_id );

		if ( ! $user ) {
			return '<em>' . esc_html__( '(Deleted User)', 'fanfiction-manager' ) . '</em>';
		}

		$edit_url = add_query_arg(
			array( 'user_id' => $author_id ),
			admin_url( 'user-edit.php' )
		);

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $edit_url ),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Renders the target column.
	 *
	 * Shows the blocked item type and title (or user name) with a link to the
	 * relevant edit screen, plus a restriction-status badge.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_target( $item ) {
		$target_type = isset( $item['target_type'] ) ? $item['target_type'] : '';
		$target_id   = absint( isset( $item['target_id'] ) ? $item['target_id'] : 0 );
		$output      = '';

		if ( 'story' === $target_type || 'chapter' === $target_type ) {
			$post = get_post( $target_id );

			if ( 'story' === $target_type ) {
				$type_label = __( 'Story', 'fanfiction-manager' );
			} else {
				$type_label = __( 'Chapter', 'fanfiction-manager' );
			}

			if ( $post ) {
				$edit_url = get_edit_post_link( $target_id );
				$output  .= '<strong>' . esc_html( $type_label ) . ':</strong> ';
				$output  .= sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( $edit_url ),
					esc_html( $post->post_title ? $post->post_title : __( '(No Title)', 'fanfiction-manager' ) )
				);
			} else {
				$output .= '<strong>' . esc_html( $type_label ) . ':</strong> ';
				$output .= '<em>' . esc_html__( '(Deleted)', 'fanfiction-manager' ) . '</em>';
			}

			// Restriction status badge.
			$is_blocked = ( 'story' === $target_type )
				? fanfic_is_story_blocked( $target_id )
				: fanfic_is_chapter_blocked( $target_id );

			if ( $is_blocked ) {
				$output .= ' <span class="fanfic-restriction-badge fanfic-badge-blocked">' .
					esc_html__( 'Blocked', 'fanfiction-manager' ) .
					'</span>';
			}
		} elseif ( 'user' === $target_type ) {
			$user = get_userdata( $target_id );

			$output .= '<strong>' . esc_html__( 'User:', 'fanfiction-manager' ) . '</strong> ';

			if ( $user ) {
				$edit_url = add_query_arg(
					array( 'user_id' => $target_id ),
					admin_url( 'user-edit.php' )
				);
				$output  .= sprintf(
					'<a href="%s">%s</a>',
					esc_url( $edit_url ),
					esc_html( $user->display_name )
				);
			} else {
				$output .= '<em>' . esc_html__( '(Deleted User)', 'fanfiction-manager' ) . '</em>';
			}

			// Suspension status badge.
			$is_banned = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
			if ( $is_banned ) {
				$output .= ' <span class="fanfic-restriction-badge fanfic-badge-suspended">' .
					esc_html__( 'Suspended', 'fanfiction-manager' ) .
					'</span>';
			}
		} else {
			$output = esc_html( $target_type ) . ' #' . esc_html( (string) $target_id );
		}

		return $output;
	}

	/**
	 * Renders the message preview column.
	 *
	 * Displays a trimmed excerpt of the message body (15 words).
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_message_preview( $item ) {
		$full_message = isset( $item['message'] ) ? $item['message'] : '';
		return esc_html( wp_trim_words( $full_message, 15 ) );
	}

	/**
	 * Renders the date column.
	 *
	 * Formats the created_at timestamp using the site's configured date format.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_date( $item ) {
		$created_at = isset( $item['created_at'] ) ? $item['created_at'] : '';
		if ( ! $created_at ) {
			return '—';
		}

		return esc_html( wp_date( get_option( 'date_format' ), strtotime( $created_at ) ) );
	}

	/**
	 * Renders the status column.
	 *
	 * Outputs a styled badge span reflecting the message's current status.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_status( $item ) {
		$status = isset( $item['status'] ) ? $item['status'] : '';

		$status_labels = array(
			'unread'   => __( 'Unread', 'fanfiction-manager' ),
			'ignored'  => __( 'Ignored', 'fanfiction-manager' ),
			'resolved' => __( 'Resolved', 'fanfiction-manager' ),
			'deleted'  => __( 'Deleted', 'fanfiction-manager' ),
		);

		$label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );

		return sprintf(
			'<span class="fanfic-msg-status-badge status-%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Renders the actions column.
	 *
	 * Provides quick inline action links directly in the row without requiring
	 * the detail row to be expanded first.
	 *
	 * @since 2.3.0
	 *
	 * @param array $item Message row.
	 * @return string Column HTML.
	 */
	protected function column_actions( $item ) {
		$message_id  = absint( $item['id'] );
		$target_type = isset( $item['target_type'] ) ? $item['target_type'] : '';
		$target_id   = absint( isset( $item['target_id'] ) ? $item['target_id'] : 0 );
		$status      = isset( $item['status'] ) ? $item['status'] : '';
		$is_restricted = false;

		$actions = array();

		if ( 'story' === $target_type ) {
			$is_restricted = fanfic_is_story_blocked( $target_id );
		} elseif ( 'chapter' === $target_type ) {
			$is_restricted = fanfic_is_chapter_blocked( $target_id );
		} elseif ( 'user' === $target_type ) {
			$is_restricted = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
		}

		if ( $is_restricted && ! in_array( $status, array( 'resolved', 'deleted' ), true ) ) {
			$actions[] = sprintf(
				'<a href="#" class="fanfic-msg-action-unblock" data-message-id="%d" data-target-type="%s" data-target-id="%d">%s</a>',
				$message_id,
				esc_attr( $target_type ),
				$target_id,
				esc_html__( 'Unblock', 'fanfiction-manager' )
			);
		}

		if ( ! in_array( $status, array( 'ignored', 'resolved', 'deleted' ), true ) ) {
			$actions[] = sprintf(
				'<a href="#" class="fanfic-msg-action-ignore" data-message-id="%d">%s</a>',
				$message_id,
				esc_html__( 'Ignore', 'fanfiction-manager' )
			);
		}

		if ( 'deleted' !== $status ) {
			$actions[] = sprintf(
				'<a href="#" class="fanfic-msg-action-delete submitdelete" data-message-id="%d">%s</a>',
				$message_id,
				esc_html__( 'Delete', 'fanfiction-manager' )
			);
		}

		return '<div class="fanfic-msg-actions-inline">' . implode( ' | ', $actions ) . '</div>';
	}

	/**
	 * Fallback renderer for any column without a dedicated method.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $item        Message row.
	 * @param string $column_name Column key.
	 * @return string Empty string.
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Outputs the message shown when no messages match the current filter.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No moderation messages found.', 'fanfiction-manager' );
	}
}
