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
	 * Cache reviewable-change checks per target to avoid repeated comparisons.
	 *
	 * @since 2.3.0
	 * @var array<string,bool>
	 */
	private $reviewable_changes_cache = array();

	/**
	 * Whether a message target has a stored blocked-story snapshot.
	 *
	 * @since 2.3.0
	 * @param string $target_type Target type.
	 * @param int    $target_id Target ID.
	 * @return bool
	 */
	private function target_has_block_snapshot( $target_type, $target_id ) {
		$target_id = absint( $target_id );
		if ( 'story' !== $target_type || $target_id <= 0 ) {
			return false;
		}

		if ( ! empty( get_post_meta( $target_id, '_fanfic_block_snapshot', true ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether a target has reviewable saved modifications vs snapshot.
	 *
	 * @since 2.3.0
	 * @param string $target_type Target type.
	 * @param int    $target_id Target ID.
	 * @return bool
	 */
	private function target_has_reviewable_modifications( $target_type, $target_id ) {
		$target_id = absint( $target_id );
		$cache_key = sanitize_key( $target_type ) . ':' . $target_id;

		if ( isset( $this->reviewable_changes_cache[ $cache_key ] ) ) {
			return (bool) $this->reviewable_changes_cache[ $cache_key ];
		}

		$has_changes = false;
		if (
			'story' === $target_type
			&& $target_id > 0
		) {
			if ( function_exists( 'fanfic_story_has_reviewable_modifications' ) ) {
				$has_changes = fanfic_story_has_reviewable_modifications( $target_id );
			} elseif ( function_exists( 'fanfic_story_has_block_snapshot_changes' ) ) {
				$has_changes = fanfic_story_has_block_snapshot_changes( $target_id );
			}
		}

		$this->reviewable_changes_cache[ $cache_key ] = (bool) $has_changes;
		return (bool) $has_changes;
	}

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
			'date'             => __( 'Date', 'fanfiction-manager' ),
			'title'            => __( 'Title', 'fanfiction-manager' ),
			'author'           => __( 'Author', 'fanfiction-manager' ),
			'view_message'     => __( 'View message', 'fanfiction-manager' ),
			'review_submitted' => __( 'Review submitted', 'fanfiction-manager' ),
			'actions'          => __( 'Actions', 'fanfiction-manager' ),
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
			'date' => array( 'created_at', true ),
		);
	}

	protected function get_default_primary_column_name() {
		return 'title';
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
		if ( 'unread' === $status_filter ) {
			$filter_args['unread_for_moderator'] = 1;
		}

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
		$existing_reply = isset( $item['author_reply'] ) ? $item['author_reply'] : '';
		$fields_readonly = 'unread' !== $status;
		$admin_restriction_summary = function_exists( 'fanfic_get_admin_restriction_summary' ) ? fanfic_get_admin_restriction_summary( $target_type, $target_id ) : '';

		// Hidden detail row.
		?>
		<tr class="fanfic-message-detail-row"
			id="fanfic-msg-detail-<?php echo esc_attr( $message_id ); ?>"
			data-message-id="<?php echo esc_attr( $message_id ); ?>"
			style="display:none;">
			<td colspan="<?php echo esc_attr( count( $this->get_columns() ) ); ?>">
				<div class="fanfic-message-detail-inner">

					<?php if ( '' !== trim( (string) ( isset( $item['message'] ) ? $item['message'] : '' ) ) ) : ?>
						<blockquote class="fanfic-message-full-text">
							<?php echo esc_html( isset( $item['message'] ) ? $item['message'] : '' ); ?>
						</blockquote>
					<?php endif; ?>

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

					<div class="fanfic-message-action-prompt<?php echo $fields_readonly ? ' is-readonly' : ''; ?>" data-message-id="<?php echo esc_attr( $message_id ); ?>" style="display:none;">
						<p class="fanfic-message-action-prompt-title"></p>
						<div class="fanfic-message-detail-notes-grid<?php echo $fields_readonly ? ' is-readonly' : ''; ?>">
							<div class="fanfic-message-detail-note-field">
								<p>
									<label for="fanfic-msg-internal-note-<?php echo esc_attr( $message_id ); ?>">
										<?php esc_html_e( 'Internal Note:', 'fanfiction-manager' ); ?>
									</label>
								</p>
								<textarea
									id="fanfic-msg-internal-note-<?php echo esc_attr( $message_id ); ?>"
									name="internal_note"
									class="fanfic-msg-internal-note-input"
									data-message-id="<?php echo esc_attr( $message_id ); ?>"
									rows="3"
									<?php echo $fields_readonly ? ' readonly aria-readonly="true"' : ''; ?>
								><?php echo esc_textarea( $existing_note ); ?></textarea>
							</div>

							<div class="fanfic-message-detail-note-field">
								<p>
									<label for="fanfic-msg-author-reply-<?php echo esc_attr( $message_id ); ?>">
										<?php esc_html_e( 'Reply to Author:', 'fanfiction-manager' ); ?>
									</label>
								</p>
								<textarea
									id="fanfic-msg-author-reply-<?php echo esc_attr( $message_id ); ?>"
									name="author_reply"
									class="fanfic-msg-author-reply-input"
									data-message-id="<?php echo esc_attr( $message_id ); ?>"
									rows="3"
									<?php echo $fields_readonly ? ' readonly aria-readonly="true"' : ''; ?>
								><?php echo esc_textarea( $existing_reply ); ?></textarea>
							</div>
						</div>
						<div class="fanfic-report-action-buttons fanfic-msg-prompt-actions">
							<button type="button" class="button button-primary fanfic-msg-confirm-action" data-message-id="<?php echo esc_attr( $message_id ); ?>"></button>
							<button type="button" class="button fanfic-msg-cancel-action" data-message-id="<?php echo esc_attr( $message_id ); ?>"><?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?></button>
						</div>
					</div>

					<div class="fanfic-block-comparison-container" data-message-id="<?php echo esc_attr( $message_id ); ?>"></div>

				</div>
			</td>
		</tr>
		<?php
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

		$output = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $edit_url ),
			esc_html( $user->display_name )
		);

		$is_blacklisted = Fanfic_Blacklist::is_message_sender_blacklisted( $author_id );
		if ( $is_blacklisted ) {
			$output .= ' <span class="fanfic-blacklist-badge">' . esc_html__( 'Blacklisted', 'fanfiction-manager' ) . '</span>';
		} else {
			$output .= sprintf(
				' <button type="button" class="button button-small fanfic-blacklist-message-sender" data-user-id="%1$d">%2$s</button>',
				$author_id,
				esc_html__( 'Blacklist', 'fanfiction-manager' )
			);
		}

		return $output;
	}

	private function get_message_target_context( $item ) {
		$target_type = isset( $item['target_type'] ) ? $item['target_type'] : '';
		$target_id   = absint( isset( $item['target_id'] ) ? $item['target_id'] : 0 );
		$context     = array(
			'prefix'        => '',
			'title'         => __( '(Deleted)', 'fanfiction-manager' ),
			'url'           => '',
			'edit_url'      => '',
			'is_restricted' => false,
			'badge_label'   => '',
			'badge_class'   => '',
		);

		if ( 'story' === $target_type || 'chapter' === $target_type ) {
			$post                    = get_post( $target_id );
			$context['prefix']       = 'story' === $target_type ? __( 'Story:', 'fanfiction-manager' ) : __( 'Chapter:', 'fanfiction-manager' );
			$context['title']        = $post && '' !== $post->post_title ? $post->post_title : __( '(No Title)', 'fanfiction-manager' );
			$context['url']          = $post ? get_permalink( $target_id ) : '';
			$context['edit_url']     = $post ? get_edit_post_link( $target_id ) : '';
			$context['is_restricted'] = ( 'story' === $target_type )
				? fanfic_is_story_blocked( $target_id )
				: fanfic_is_chapter_blocked( $target_id );
			$context['badge_label'] = __( 'Blocked', 'fanfiction-manager' );
			$context['badge_class'] = 'fanfic-badge-blocked';

			return $context;
		}

		if ( 'user' === $target_type ) {
			$user = get_userdata( $target_id );

			$context['prefix']    = __( 'User:', 'fanfiction-manager' );
			$context['title']     = $user ? $user->display_name : __( '(Deleted User)', 'fanfiction-manager' );
			$context['edit_url']  = $user ? add_query_arg(
				array( 'user_id' => $target_id ),
				admin_url( 'user-edit.php' )
			) : '';
			$context['is_restricted'] = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
			$context['badge_label']   = __( 'Suspended', 'fanfiction-manager' );
			$context['badge_class']   = 'fanfic-badge-suspended';

			return $context;
		}

		$context['prefix'] = ucfirst( $target_type ) . ':';
		$context['title']  = '#' . $target_id;

		return $context;
	}

	protected function column_title( $item ) {
		$context = $this->get_message_target_context( $item );
		$link    = $context['url'] ? $context['url'] : $context['edit_url'];
		$output  = '<strong>' . esc_html( $context['prefix'] ) . '</strong> ';

		if ( $link ) {
			$output .= sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>%2$s</strong></a>',
				esc_url( $link ),
				esc_html( $context['title'] )
			);
		} else {
			$output .= '<strong>' . esc_html( $context['title'] ) . '</strong>';
		}

		if ( ! empty( $context['is_restricted'] ) && ! empty( $context['badge_label'] ) ) {
			$output .= ' <span class="fanfic-restriction-badge ' . esc_attr( $context['badge_class'] ) . '">' . esc_html( $context['badge_label'] ) . '</span>';
		}

		return $output;
	}

	protected function column_view_message( $item ) {
		$message_id  = absint( $item['id'] );
		$has_message = '' !== trim( (string) ( isset( $item['message'] ) ? $item['message'] : '' ) );

		return sprintf(
			'<button type="button" class="button button-small fanfic-msg-view-message" data-message-id="%1$d" aria-expanded="false" %2$s>%3$s</button>',
			$message_id,
			$has_message ? '' : 'disabled aria-disabled="true"',
			esc_html__( 'View message', 'fanfiction-manager' )
		);
	}

	protected function column_review_submitted( $item ) {
		$target_type = isset( $item['target_type'] ) ? $item['target_type'] : '';
		$target_id   = absint( isset( $item['target_id'] ) ? $item['target_id'] : 0 );

		return $this->target_has_reviewable_modifications( $target_type, $target_id )
			? esc_html__( 'Yes', 'fanfiction-manager' )
			: esc_html__( 'No', 'fanfiction-manager' );
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
		$has_snapshot = $this->target_has_block_snapshot( $target_type, $target_id );
		$has_reviewable_modifications = $this->target_has_reviewable_modifications( $target_type, $target_id );

		$actions = array();

		if ( $has_snapshot ) {
			$actions[] = sprintf(
				'<button type="button" class="button fanfic-msg-review-changes" data-message-id="%d" %s>%s</button>',
				$message_id,
				$has_reviewable_modifications ? '' : 'disabled aria-disabled="true"',
				esc_html__( 'Review modifications', 'fanfiction-manager' )
			);
		}

		if ( 'story' === $target_type ) {
			$is_restricted = fanfic_is_story_blocked( $target_id );
		} elseif ( 'chapter' === $target_type ) {
			$is_restricted = fanfic_is_chapter_blocked( $target_id );
		} elseif ( 'user' === $target_type ) {
			$is_restricted = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
		}

		if ( $is_restricted && ! in_array( $status, array( 'resolved', 'deleted' ), true ) ) {
			$actions[] = sprintf(
				'<button type="button" class="button button-primary fanfic-msg-action-unblock" data-message-id="%d" data-target-type="%s" data-target-id="%d">%s</button>',
				$message_id,
				esc_attr( $target_type ),
				$target_id,
				esc_html__( 'Unblock', 'fanfiction-manager' )
			);
		}

		if ( ! in_array( $status, array( 'ignored', 'resolved', 'deleted' ), true ) ) {
			$actions[] = sprintf(
				'<button type="button" class="button fanfic-msg-action-ignore" data-message-id="%d">%s</button>',
				$message_id,
				esc_html__( 'Ignore', 'fanfiction-manager' )
			);
		}

		if ( 'deleted' !== $status ) {
			$actions[] = sprintf(
				'<button type="button" class="button button-link-delete fanfic-msg-action-delete" data-message-id="%d">%s</button>',
				$message_id,
				esc_html__( 'Delete', 'fanfiction-manager' )
			);
		}

		if ( empty( $actions ) ) {
			return '—';
		}

		return '<div class="fanfic-report-action-stack"><div class="fanfic-report-action-buttons fanfic-msg-action-buttons">' . implode( '', $actions ) . '</div></div>';
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
