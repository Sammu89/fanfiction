<?php
/**
 * Users Management Admin Interface - WP_List_Table Implementation
 *
 * Provides a comprehensive admin UI for managing fanfiction users with advanced
 * features including role management, banning, promoting, demoting, and filtering.
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
 * Class Fanfic_Users_List_Table
 *
 * Extends WP_List_Table to display and manage fanfiction users.
 *
 * @since 1.0.0
 */
class Fanfic_Users_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'user',
				'plural'   => 'users',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get a list of columns
	 *
	 * @since 1.0.0
	 * @return array Column names and labels
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'username'      => __( 'Username', 'fanfiction-manager' ),
			'display_name'  => __( 'Display Name', 'fanfiction-manager' ),
			'email'         => __( 'Email', 'fanfiction-manager' ),
			'role'          => __( 'Role', 'fanfiction-manager' ),
			'story_count'   => __( 'Story Count', 'fanfiction-manager' ),
			'registered'    => __( 'Registration Date', 'fanfiction-manager' ),
			'last_login'    => __( 'Last Login', 'fanfiction-manager' ),
			'actions'       => __( 'Actions', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 * @return array Sortable columns
	 */
	protected function get_sortable_columns() {
		return array(
			'username'      => array( 'user_login', false ),
			'display_name'  => array( 'display_name', false ),
			'role'          => array( 'role', false ),
			'story_count'   => array( 'story_count', false ),
			'registered'    => array( 'user_registered', true ), // Default sort
			'last_login'    => array( 'last_login', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 * @return array Bulk actions
	 */
	protected function get_bulk_actions() {
		return array(
			'ban'                => __( 'Ban Selected Users', 'fanfiction-manager' ),
			'unban'              => __( 'Unban Selected Users', 'fanfiction-manager' ),
			'remove_fanfic_roles' => __( 'Remove Fanfic Roles', 'fanfiction-manager' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Checkbox HTML
	 */
	protected function column_cb( $item ) {
		// Prevent current user from being selectable
		if ( $item->ID === get_current_user_id() ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" name="user[]" value="%d" />',
			absint( $item->ID )
		);
	}

	/**
	 * Render username column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Username HTML with link
	 */
	protected function column_username( $item ) {
		$edit_url = get_edit_user_link( $item->ID );

		return sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $edit_url ),
			esc_html( $item->user_login )
		);
	}

	/**
	 * Render display name column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Display name HTML
	 */
	protected function column_display_name( $item ) {
		return esc_html( $item->display_name );
	}

	/**
	 * Render email column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Email HTML
	 */
	protected function column_email( $item ) {
		return sprintf(
			'<a href="mailto:%s">%s</a>',
			esc_attr( $item->user_email ),
			esc_html( $item->user_email )
		);
	}

	/**
	 * Render role column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Role badge HTML
	 */
	protected function column_role( $item ) {
		$roles = $item->roles;

		if ( empty( $roles ) ) {
			return '&mdash;';
		}

		// Roles to display (fanfiction custom roles + WordPress administrator)
		$displayable_roles = array(
			'fanfiction_admin',
			'fanfiction_moderator',
			'fanfiction_author',
			'fanfiction_reader',
			'fanfiction_banned_user',
			'administrator',
		);

		// Filter roles to only show fanfiction roles + WordPress administrator
		$filtered_roles = array_intersect( $roles, $displayable_roles );

		if ( empty( $filtered_roles ) ) {
			return '&mdash;';
		}

		// Map role slugs to pretty names
		$role_names = array(
			'fanfiction_admin'       => __( 'Administrator', 'fanfiction-manager' ),
			'fanfiction_moderator'   => __( 'Moderator', 'fanfiction-manager' ),
			'fanfiction_author'      => __( 'Author', 'fanfiction-manager' ),
			'fanfiction_reader'      => __( 'Reader', 'fanfiction-manager' ),
			'fanfiction_banned_user' => __( 'Banned', 'fanfiction-manager' ),
			'administrator'          => __( 'WordPress Administrator', 'fanfiction-manager' ),
		);

		// Convert roles to pretty names
		$pretty_roles = array();
		foreach ( $filtered_roles as $role ) {
			$pretty_roles[] = isset( $role_names[ $role ] ) ? $role_names[ $role ] : $role;
		}

		$role_display = implode( ', ', $pretty_roles );

		return sprintf(
			'<span class="fanfic-role-badge">%s</span>',
			esc_html( $role_display )
		);
	}

	/**
	 * Render story count column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Story count HTML
	 */
	protected function column_story_count( $item ) {
		$count = $this->get_published_story_count( $item->ID );

		if ( $count > 0 ) {
			$stories_url = add_query_arg(
				array(
					'post_type' => 'fanfiction_story',
					'author'    => $item->ID,
				),
				admin_url( 'edit.php' )
			);

			return sprintf(
				'<a href="%s">%d</a>',
				esc_url( $stories_url ),
				absint( $count )
			);
		}

		return '0';
	}

	/**
	 * Render registration date column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Registration date HTML
	 */
	protected function column_registered( $item ) {
		$date = strtotime( $item->user_registered );
		$time_diff = human_time_diff( $date, current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s %s</abbr>',
			esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) ),
			$time_diff,
			__( 'ago', 'fanfiction-manager' )
		);
	}

	/**
	 * Render last login column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Last login HTML
	 */
	protected function column_last_login( $item ) {
		$last_login = $this->get_user_last_login( $item->ID );

		if ( ! $last_login ) {
			return '&mdash;';
		}

		$time_diff = human_time_diff( $last_login, current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s %s</abbr>',
			esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_login ) ),
			$time_diff,
			__( 'ago', 'fanfiction-manager' )
		);
	}

	/**
	 * Render actions column
	 *
	 * @since 1.0.0
	 * @param WP_User $item User object
	 * @return string Actions dropdown HTML
	 */
	protected function column_actions( $item ) {
		$nonce = wp_create_nonce( 'fanfic_user_action_' . $item->ID );
		$roles = $item->roles;
		$is_banned = in_array( 'fanfiction_banned_user', $roles, true );
		$is_wp_admin = in_array( 'administrator', $roles, true );
		$is_fanfic_admin = in_array( 'fanfiction_admin', $roles, true );
		$is_moderator = in_array( 'fanfiction_moderator', $roles, true );
		$is_author = in_array( 'fanfiction_author', $roles, true );

		// Prevent actions on current user or super admins
		if ( $item->ID === get_current_user_id() || ( is_multisite() && is_super_admin( $item->ID ) ) ) {
			return '&mdash;';
		}

		// Check current user's permissions
		$current_user_is_wp_admin = current_user_can( 'manage_options' );
		$current_user_is_fanfic_admin = in_array( 'fanfiction_admin', wp_get_current_user()->roles, true );
		$current_user_is_moderator = in_array( 'fanfiction_moderator', wp_get_current_user()->roles, true );

		// Determine if current user can take actions on target user
		$can_ban_target = true;
		$can_demote_target = true;

		// Only WordPress admins can ban/demote fanfiction_admins
		if ( $is_fanfic_admin && ! $current_user_is_wp_admin ) {
			$can_ban_target = false;
			$can_demote_target = false;
		}

		// Only fanfiction_admins or WordPress admins can ban/demote moderators
		if ( $is_moderator && ! $current_user_is_fanfic_admin && ! $current_user_is_wp_admin ) {
			$can_ban_target = false;
			$can_demote_target = false;
		}

		ob_start();
		?>
		<div class="fanfic-actions-dropdown">
			<button type="button" class="button button-small fanfic-actions-toggle" data-user-id="<?php echo absint( $item->ID ); ?>">
				<?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<ul class="fanfic-actions-menu" style="display: none;">
				<?php if ( $is_banned ) : ?>
					<?php if ( $can_ban_target ) : ?>
						<li>
							<a href="#" class="fanfic-action-unban" data-user-id="<?php echo absint( $item->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<span class="dashicons dashicons-unlock"></span> <?php esc_html_e( 'Unban User', 'fanfiction-manager' ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php else : ?>
					<?php if ( $can_ban_target ) : ?>
						<li>
							<a href="#" class="fanfic-action-ban" data-user-id="<?php echo absint( $item->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Ban User', 'fanfiction-manager' ); ?>
							</a>
						</li>
					<?php endif; ?>
					<?php if ( $is_fanfic_admin && $can_demote_target && $current_user_is_wp_admin ) : ?>
						<li>
							<a href="#" class="fanfic-action-remove-admin" data-user-id="<?php echo absint( $item->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<span class="dashicons dashicons-arrow-down"></span> <?php esc_html_e( 'Remove Administrator Role', 'fanfiction-manager' ); ?>
							</a>
						</li>
					<?php elseif ( $is_moderator && $can_demote_target ) : ?>
						<li>
							<a href="#" class="fanfic-action-remove-moderator" data-user-id="<?php echo absint( $item->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<span class="dashicons dashicons-arrow-down"></span> <?php esc_html_e( 'Remove Moderator Role', 'fanfiction-manager' ); ?>
							</a>
						</li>
					<?php elseif ( $is_author ) : ?>
						<?php if ( $current_user_is_fanfic_admin || $current_user_is_wp_admin ) : ?>
							<li>
								<a href="#" class="fanfic-action-promote" data-user-id="<?php echo absint( $item->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-target="fanfiction_moderator">
									<span class="dashicons dashicons-arrow-up"></span> <?php esc_html_e( 'Promote to Moderator', 'fanfiction-manager' ); ?>
								</a>
							</li>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Default column output
	 *
	 * @since 1.0.0
	 * @param WP_User $item        User object
	 * @param string  $column_name Column name
	 * @return string Column content
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items() {
		// Register columns
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get filter parameters
		$role_filter       = isset( $_GET['role_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['role_filter'] ) ) : '';
		$story_count_filter = isset( $_GET['story_count_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['story_count_filter'] ) ) : '';
		$date_from         = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to           = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$search            = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Get sorting parameters
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'user_registered';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Items per page
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		// Build filters array
		$filters = array(
			'role'        => $role_filter,
			'story_count' => $story_count_filter,
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'search'      => $search,
		);

		// Get users
		$result = $this->get_users( $offset, $per_page, $filters, $orderby, $order );

		// Set items
		$this->items = $result['users'];

		// Set pagination
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * Get users with filters
	 *
	 * @since 1.0.0
	 * @param int    $offset  Offset for pagination
	 * @param int    $per_page Items per page
	 * @param array  $filters  Filter parameters
	 * @param string $orderby  Order by column
	 * @param string $order    Order direction (ASC/DESC)
	 * @return array Array with 'users' and 'total'
	 */
	protected function get_users( $offset, $per_page, $filters, $orderby, $order ) {
		global $wpdb;

		// Build WP_User_Query args
		$args = array(
			'number'  => $per_page,
			'offset'  => $offset,
			'orderby' => 'registered',
			'order'   => $order,
		);

		// Filter for fanfiction users - include users with ANY of these fanfic roles or WordPress admin
		$args['role__in'] = array( 'fanfiction_author', 'fanfiction_reader', 'fanfiction_moderator', 'fanfiction_admin', 'fanfiction_banned_user', 'administrator' );

		// Additional role filter (if user selects a specific role)
		if ( ! empty( $filters['role'] ) ) {
			$args['role'] = $filters['role'];
			// Remove the role__in when a specific role is selected
			unset( $args['role__in'] );
		}

		// Search filter
		if ( ! empty( $filters['search'] ) ) {
			$args['search']         = '*' . $filters['search'] . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		// Date range filter
		if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
			$args['date_query'] = array();

			if ( ! empty( $filters['date_from'] ) ) {
				$args['date_query']['after'] = $filters['date_from'];
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$args['date_query']['before'] = $filters['date_to'];
			}

			$args['date_query']['inclusive'] = true;
		}

		// Handle sorting
		switch ( $orderby ) {
			case 'user_login':
				$args['orderby'] = 'login';
				break;
			case 'display_name':
				$args['orderby'] = 'display_name';
				break;
			case 'user_registered':
				$args['orderby'] = 'registered';
				break;
			case 'last_login':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = 'fanfic_last_login';
				break;
			case 'story_count':
				// Custom sorting handled after query
				$args['orderby'] = 'ID';
				break;
			default:
				$args['orderby'] = 'registered';
				break;
		}

		// Execute query
		$user_query = new WP_User_Query( $args );
		$users = $user_query->get_results();

		// Filter out users who don't actually have fanfiction roles (cleanup for orphaned users)
		$fanfic_roles = array( 'fanfiction_author', 'fanfiction_reader', 'fanfiction_moderator', 'fanfiction_admin', 'fanfiction_banned_user' );
		$users = array_filter(
			$users,
			function( $user ) use ( $fanfic_roles ) {
				// Administrator can be shown without fanfic roles
				if ( in_array( 'administrator', $user->roles, true ) ) {
					return true;
				}
				// Otherwise, user must have at least one fanfiction role
				foreach ( $fanfic_roles as $fanfic_role ) {
					if ( in_array( $fanfic_role, $user->roles, true ) ) {
						return true;
					}
				}
				return false;
			}
		);

		// Apply story count filter
		if ( ! empty( $filters['story_count'] ) ) {
			$users = array_filter(
				$users,
				function( $user ) use ( $filters ) {
					$count = $this->get_published_story_count( $user->ID );
					switch ( $filters['story_count'] ) {
						case '0':
							return 0 === $count;
						case '1-5':
							return $count >= 1 && $count <= 5;
						case '6-10':
							return $count >= 6 && $count <= 10;
						case '11+':
							return $count >= 11;
						default:
							return true;
					}
				}
			);
		}

		// Custom sorting for story count
		if ( 'story_count' === $orderby ) {
			usort(
				$users,
				function( $a, $b ) use ( $order ) {
					$count_a = $this->get_published_story_count( $a->ID );
					$count_b = $this->get_published_story_count( $b->ID );

					if ( 'ASC' === $order ) {
						return $count_a - $count_b;
					} else {
						return $count_b - $count_a;
					}
				}
			);
		}

		return array(
			'users' => $users,
			'total' => $user_query->get_total(),
		);
	}

	/**
	 * Get published story count for a user
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return int Story count
	 */
	protected function get_published_story_count( $user_id ) {
		// Check cache first
		$cache_key = 'fanfic_user_story_count_' . $user_id;
		$count = wp_cache_get( $cache_key, 'fanfic_users' );

		if ( false === $count ) {
			$count = count_user_posts( $user_id, 'fanfiction_story', true );
			wp_cache_set( $cache_key, $count, 'fanfic_users', HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Get user last login timestamp
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return int|false Last login timestamp or false
	 */
	protected function get_user_last_login( $user_id ) {
		$last_login = get_user_meta( $user_id, 'fanfic_last_login', true );

		if ( $last_login ) {
			return (int) $last_login;
		}

		// Fallback to last activity (comment or post modification)
		global $wpdb;

		$last_activity = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT UNIX_TIMESTAMP(MAX(post_modified))
				FROM {$wpdb->posts}
				WHERE post_author = %d
				AND post_type IN ('fanfiction_story', 'fanfiction_chapter')",
				$user_id
			)
		);

		return $last_activity ? (int) $last_activity : false;
	}

	/**
	 * Display extra table navigation
	 *
	 * @since 1.0.0
	 * @param string $which Top or bottom
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$role_filter        = isset( $_GET['role_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['role_filter'] ) ) : '';
		$story_count_filter = isset( $_GET['story_count_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['story_count_filter'] ) ) : '';
		$date_from          = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to            = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		?>
		<div class="alignleft actions">
			<!-- Role filter -->
			<select name="role_filter" id="role-filter">
				<option value=""><?php esc_html_e( 'All Roles', 'fanfiction-manager' ); ?></option>
				<option value="subscriber" <?php selected( $role_filter, 'subscriber' ); ?>><?php esc_html_e( 'Reader', 'fanfiction-manager' ); ?></option>
				<option value="fanfiction_author" <?php selected( $role_filter, 'fanfiction_author' ); ?>><?php esc_html_e( 'Author', 'fanfiction-manager' ); ?></option>
				<option value="fanfiction_moderator" <?php selected( $role_filter, 'fanfiction_moderator' ); ?>><?php esc_html_e( 'Moderator', 'fanfiction-manager' ); ?></option>
				<option value="administrator" <?php selected( $role_filter, 'administrator' ); ?>><?php esc_html_e( 'Admin', 'fanfiction-manager' ); ?></option>
				<option value="fanfiction_banned_user" <?php selected( $role_filter, 'fanfiction_banned_user' ); ?>><?php esc_html_e( 'Banned', 'fanfiction-manager' ); ?></option>
			</select>

			<!-- Story count filter -->
			<select name="story_count_filter" id="story-count-filter">
				<option value=""><?php esc_html_e( 'All Story Counts', 'fanfiction-manager' ); ?></option>
				<option value="0" <?php selected( $story_count_filter, '0' ); ?>><?php esc_html_e( '0 Stories', 'fanfiction-manager' ); ?></option>
				<option value="1-5" <?php selected( $story_count_filter, '1-5' ); ?>><?php esc_html_e( '1-5 Stories', 'fanfiction-manager' ); ?></option>
				<option value="6-10" <?php selected( $story_count_filter, '6-10' ); ?>><?php esc_html_e( '6-10 Stories', 'fanfiction-manager' ); ?></option>
				<option value="11+" <?php selected( $story_count_filter, '11+' ); ?>><?php esc_html_e( '11+ Stories', 'fanfiction-manager' ); ?></option>
			</select>

			<!-- Date range filter -->
			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'fanfiction-manager' ); ?>" />
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'fanfiction-manager' ); ?>" />

			<?php submit_button( __( 'Filter', 'fanfiction-manager' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Message to display when no items found
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No users found.', 'fanfiction-manager' );
	}
}

/**
 * Class Fanfic_Users_Admin
 *
 * Manages the users administration interface and actions.
 *
 * @since 1.0.0
 */
class Fanfic_Users_Admin {

	/**
	 * Initialize the users admin class
	 *
	 * Sets up WordPress hooks for user management.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// AJAX handlers
		add_action( 'wp_ajax_fanfic_ban_user', array( __CLASS__, 'ajax_ban_user' ) );
		add_action( 'wp_ajax_fanfic_unban_user', array( __CLASS__, 'ajax_unban_user' ) );
		add_action( 'wp_ajax_fanfic_promote_user', array( __CLASS__, 'ajax_promote_user' ) );
		add_action( 'wp_ajax_fanfic_promote_global_user', array( __CLASS__, 'ajax_promote_global_user' ) );
		add_action( 'wp_ajax_fanfic_remove_moderator_role', array( __CLASS__, 'ajax_remove_moderator_role' ) );
		add_action( 'wp_ajax_fanfic_remove_admin_role', array( __CLASS__, 'ajax_remove_admin_role' ) );

		// Bulk actions
		add_action( 'admin_init', array( __CLASS__, 'process_bulk_actions' ) );

		// Track last login
		add_action( 'wp_login', array( __CLASS__, 'track_last_login' ), 10, 2 );

		// Auto role adjustments
		// Reader -> Author promotion remains event-driven on publish.
		// Daily author demotion is handled by Fanfic_Author_Demotion (cron_hour-aware).
		add_action( 'transition_post_status', array( __CLASS__, 'auto_promote_to_author' ), 10, 3 );

		// Remove legacy duplicate demotion cron from older versions.
		if ( wp_next_scheduled( 'fanfic_daily_user_role_check' ) ) {
			wp_clear_scheduled_hook( 'fanfic_daily_user_role_check' );
		}
	}

	/**
	 * Render users management page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render() {
		// Check user capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current tab
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'fanfiction-users';
		$allowed_tabs = array( 'fanfiction-users', 'global-users' );
		$active_tab = in_array( $active_tab, $allowed_tabs, true ) ? $active_tab : 'fanfiction-users';

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php self::display_notices(); ?>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-users&tab=fanfiction-users" class="nav-tab <?php echo 'fanfiction-users' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Fanfiction Users', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-users&tab=global-users" class="nav-tab <?php echo 'global-users' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Global Users', 'fanfiction-manager' ); ?>
				</a>
			</nav>

			<!-- Fanfiction Users Tab -->
			<?php if ( 'fanfiction-users' === $active_tab ) : ?>
				<div class="tab-content">
					<p><?php esc_html_e( 'Manage fanfiction users: authors, readers, moderators. You can ban, promote, or demote users.', 'fanfiction-manager' ); ?></p>

					<?php
					// Create table instance for fanfiction users
					$table = new Fanfic_Users_List_Table();
					$table->prepare_items();
					?>

					<form method="get" action="">
						<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
						<input type="hidden" name="tab" value="fanfiction-users" />
						<?php
						$table->search_box( __( 'Search Users', 'fanfiction-manager' ), 'user' );
						$table->display();
						?>
					</form>

					<!-- User Management Guidelines -->
					<div class="notice notice-info inline">
						<h3><?php esc_html_e( 'User Roles', 'fanfiction-manager' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Reader:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Default role for registered users. Can bookmark stories, follow authors, rate chapters, comment, and manage their profile.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Author:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Automatically assigned when a user publishes their first story (cannot be manually promoted). Can create, edit, and delete their own stories and chapters. Inherits all Reader permissions.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Moderator:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Can edit any story, access the moderation queue, suspend users, and manage the platform user list. Inherits all Author and Reader permissions.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Administrator:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Full access to plugin settings, custom taxonomies, notification templates, URL configuration, and custom CSS. Inherits all Moderator permissions.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Banned:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Suspended users can log in but cannot create/edit content. Their stories are marked as blocked and hidden from public view. A notice displays "Your account has been suspended."', 'fanfiction-manager' ); ?></li>
						</ul>
						<h3><?php esc_html_e( 'User Management Guidelines', 'fanfiction-manager' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Ban:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Banned users can still log in but cannot create/edit content. Their stories are marked as blocked and hidden from public view. Fanfiction admins cannot ban other admins; moderators cannot ban other moderators.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Unban:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Restores user role based on published story count. Users with stories become Authors; users without stories become Readers.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Promote:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Only Moderator role can be manually promoted. Reader → Author promotion is automatic when a user publishes their first story. Only administrators can promote to Moderator role.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Remove Role:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Remove Administrator or Moderator roles. Only WordPress administrators can remove Administrator roles. Only administrators can remove Moderator roles.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Auto-Demotion:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Authors with 0 published stories are automatically demoted to Reader role daily via WP-Cron.', 'fanfiction-manager' ); ?></li>
							<li><strong><?php esc_html_e( 'Content Preservation:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Banning preserves all user content - it just hides stories from public view. Stories remain in the database.', 'fanfiction-manager' ); ?></li>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<!-- Global Users Tab -->
			<?php if ( 'global-users' === $active_tab ) : ?>
				<div class="tab-content">
					<p><?php esc_html_e( 'Search and manage WordPress users without fanfiction roles. Promote users to moderator or admin roles for fanfiction management.', 'fanfiction-manager' ); ?></p>

					<?php self::render_global_users_table(); ?>
				</div>
			<?php endif; ?>

		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Toggle actions dropdown
			$('.fanfic-actions-toggle').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $menu = $(this).siblings('.fanfic-actions-menu');
				$('.fanfic-actions-menu').not($menu).hide();
				$menu.toggle();
			});

			// Close dropdown when clicking outside
			$(document).on('click', function() {
				$('.fanfic-actions-menu').hide();
			});

			// Ban user
			$(document).on('click', '.fanfic-action-ban', function(e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var nonce = $(this).data('nonce');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to ban this user? They will still be able to log in but cannot create or edit content.', 'fanfiction-manager' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_ban_user',
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error banning user.', 'fanfiction-manager' ) ); ?>');
						}
					}
				});
			});

			// Unban user
			$(document).on('click', '.fanfic-action-unban', function(e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var nonce = $(this).data('nonce');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to unban this user?', 'fanfiction-manager' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_unban_user',
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error unbanning user.', 'fanfiction-manager' ) ); ?>');
						}
					}
				});
			});

			// Promote user
			$(document).on('click', '.fanfic-action-promote', function(e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var nonce = $(this).data('nonce');
				var targetRole = $(this).data('target');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to promote this user?', 'fanfiction-manager' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_promote_user',
						user_id: userId,
						target_role: targetRole,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error promoting user.', 'fanfiction-manager' ) ); ?>');
						}
					}
				});
			});

			// Remove moderator role
			$(document).on('click', '.fanfic-action-remove-moderator', function(e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var nonce = $(this).data('nonce');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to remove the moderator role from this user? They will become an author if they have published stories, otherwise they will become a reader.', 'fanfiction-manager' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: (typeof fanfictionAdmin !== 'undefined' && fanfictionAdmin.ajaxUrl) ? fanfictionAdmin.ajaxUrl : ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_remove_moderator_role',
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error removing moderator role.', 'fanfiction-manager' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'An error occurred.', 'fanfiction-manager' ) ); ?>');
					}
				});
			});

			// Remove admin role
			$(document).on('click', '.fanfic-action-remove-admin', function(e) {
				e.preventDefault();
				var userId = $(this).data('user-id');
				var nonce = $(this).data('nonce');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to remove the admin role from this user? They will become a moderator if they had no previous WordPress role, or revert to their previous role.', 'fanfiction-manager' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: (typeof fanfictionAdmin !== 'undefined' && fanfictionAdmin.ajaxUrl) ? fanfictionAdmin.ajaxUrl : ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_remove_admin_role',
						user_id: userId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error removing admin role.', 'fanfiction-manager' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'An error occurred.', 'fanfiction-manager' ) ); ?>');
					}
				});
			});

		// Promote global user to moderator or admin
		$(document).on('click', '.fanfic-promote-global', function(e) {
			e.preventDefault();
			var userId = $(this).data('user-id');
			var nonce = $(this).data('nonce');
			var targetRole = $(this).data('role');
			var roleLabel = (targetRole === 'fanfiction_admin') ? 'Admin' : 'Moderator';

			console.log('Promote button clicked', { userId: userId, nonce: nonce, targetRole: targetRole });

			if (!confirm('<?php echo esc_js( __( 'Are you sure you want to promote this user to ', 'fanfiction-manager' ) ); ?>' + roleLabel + '?')) {
				return;
			}

			$.ajax({
				url: (typeof fanfictionAdmin !== 'undefined' && fanfictionAdmin.ajaxUrl) ? fanfictionAdmin.ajaxUrl : ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_promote_global_user',
					user_id: userId,
					target_role: targetRole,
					nonce: nonce
				},
				success: function(response) {
					console.log('AJAX success response:', response);
					if (response.success) {
						alert(response.data.message || '<?php echo esc_js( __( 'User promoted successfully!', 'fanfiction-manager' ) ); ?>');
						location.reload();
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'Error promoting user.', 'fanfiction-manager' ) ); ?>');
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error('AJAX error:', { textStatus: textStatus, errorThrown: errorThrown, response: jqXHR.responseText });
					alert('<?php echo esc_js( __( 'An error occurred while promoting the user.', 'fanfiction-manager' ) ); ?>');
				}
			});
		});
		});
		</script>
		<?php
	}

	/**
	 * Render global users table
	 *
	 * Displays all WordPress users with at least one WordPress role, regardless of fanfiction roles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_global_users_table() {
		global $wpdb;

		// Get search term
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Roles to exclude from global users table
		// Only exclude fanfiction_author and fanfiction_banned_user
		// Keep moderators and admins visible so they can be managed here too
		$exclude_roles = array( 'fanfiction_author', 'fanfiction_banned_user' );

		// Query all users
		$args = array(
			'number'   => 999,
			'offset'   => 0,
			'search'   => $search ? '*' . $search . '*' : '',
			'orderby'  => 'user_login',
			'order'    => 'ASC',
		);

		$users = get_users( $args );

		// Filter to only show users with at least one WordPress role
		// Include any user with a WordPress role, even if they have fanfic roles
		// Exclude users with no WordPress roles (i.e., only fanfic roles or no roles)
		$wordpress_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$global_users = array();
		foreach ( $users as $user ) {
			$has_wp_role = false;
			foreach ( $wordpress_roles as $wp_role ) {
				if ( in_array( $wp_role, $user->roles, true ) ) {
					$has_wp_role = true;
					break;
				}
			}
			if ( $has_wp_role ) {
				$global_users[] = $user;
			}
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Username', 'fanfiction-manager' ); ?></th>
					<th><?php esc_html_e( 'Display Name', 'fanfiction-manager' ); ?></th>
					<th><?php esc_html_e( 'Email', 'fanfiction-manager' ); ?></th>
					<th><?php esc_html_e( 'Current Roles', 'fanfiction-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $global_users ) ) : ?>
					<?php foreach ( $global_users as $user ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $user->user_login ); ?></strong></td>
							<td><?php echo esc_html( $user->display_name ); ?></td>
							<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
							<td>
								<?php
								$roles = $user->roles;
								if ( empty( $roles ) ) {
									esc_html_e( 'No roles', 'fanfiction-manager' );
								} else {
									echo esc_html( implode( ', ', $roles ) );
								}
								?>
							</td>
							<td>
								<button type="button" class="button button-small fanfic-promote-global" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-role="fanfiction_moderator" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_promote_' . $user->ID ) ); ?>">
									<?php esc_html_e( 'Moderator', 'fanfiction-manager' ); ?>
								</button>
								<button type="button" class="button button-small fanfic-promote-global" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-role="fanfiction_admin" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_promote_' . $user->ID ) ); ?>">
									<?php esc_html_e( 'Admin', 'fanfiction-manager' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5">
							<?php esc_html_e( 'No users found without fanfiction roles.', 'fanfiction-manager' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Search Form -->
		<form method="get" action="" style="margin-top: 20px;">
			<input type="hidden" name="page" value="fanfiction-users" />
			<input type="hidden" name="tab" value="global-users" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search users...', 'fanfiction-manager' ); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'fanfiction-manager' ); ?>" />
		</form>
		<?php
	}

	/**
	 * AJAX handler to ban a user
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_ban_user() {
		// Get user ID
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_user_action_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Prevent banning current user or super admin
		if ( $user_id === get_current_user_id() || ( is_multisite() && is_super_admin( $user_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot ban this user.', 'fanfiction-manager' ) ) );
		}

		// Permission checks for banning admins and moderators
		$target_roles = $user->roles;
		$current_user_id = get_current_user_id();
		$is_wp_admin = current_user_can( 'manage_options' );
		$is_fanfic_admin = in_array( 'fanfiction_admin', wp_get_current_user()->roles, true );
		$is_moderator = in_array( 'fanfiction_moderator', wp_get_current_user()->roles, true );

		// Only WordPress admins can ban fanfiction_admins
		if ( in_array( 'fanfiction_admin', $target_roles, true ) && ! $is_wp_admin ) {
			wp_send_json_error( array( 'message' => __( 'Only WordPress administrators can ban fanfiction administrators.', 'fanfiction-manager' ) ) );
		}

		// Only fanfiction_admins or WordPress admins can ban moderators
		if ( in_array( 'fanfiction_moderator', $target_roles, true ) && ! $is_fanfic_admin && ! $is_wp_admin ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators can ban moderators.', 'fanfiction-manager' ) ) );
		}

		// Store original role
		$current_roles = $user->roles;
		if ( ! empty( $current_roles ) ) {
			update_user_meta( $user_id, 'fanfic_original_role', $current_roles[0] );
		}

		// Change to banned role
		$user->set_role( 'fanfiction_banned_user' );

		// Track ban metadata
		update_user_meta( $user_id, 'fanfic_banned', '1' );
		update_user_meta( $user_id, 'fanfic_banned_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_banned_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'fanfic_user_banned', $user_id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'User banned successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * AJAX handler to unban a user
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_unban_user() {
		// Get user ID
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_user_action_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Determine role to restore
		$original_role = get_user_meta( $user_id, 'fanfic_original_role', true );

		// Check published story count
		$story_count = count_user_posts( $user_id, 'fanfiction_story', true );

		// Determine appropriate role based on original role and current story count
		$restored_role = '';

		// If original role was moderator or admin, restore that role regardless of story count
		if ( 'fanfiction_moderator' === $original_role || 'fanfiction_admin' === $original_role ) {
			$restored_role = $original_role;
		} else {
			// For author, reader, or no original role - verify based on current story count
			if ( $story_count > 0 ) {
				$restored_role = 'fanfiction_author';
			} else {
				$restored_role = 'fanfiction_reader';
			}
		}

		// Restore role
		$user->set_role( $restored_role );

		// Clean up ban metadata
		delete_user_meta( $user_id, 'fanfic_banned' );
		delete_user_meta( $user_id, 'fanfic_original_role' );
		update_user_meta( $user_id, 'fanfic_unbanned_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_unbanned_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'fanfic_user_unbanned', $user_id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'User unbanned successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * AJAX handler to promote a user
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_promote_user() {
		// Get user ID and target role
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$target_role = isset( $_POST['target_role'] ) ? sanitize_text_field( wp_unslash( $_POST['target_role'] ) ) : '';

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_user_action_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Validate target role - author role is automatically assigned, not manually promoted
		$allowed_roles = array( 'fanfiction_moderator', 'administrator' );
		if ( ! in_array( $target_role, $allowed_roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target role. Author role is automatically assigned when a user publishes their first story.', 'fanfiction-manager' ) ) );
		}

		// Permission check: only fanfiction_admins or WordPress admins can promote to moderator role
		if ( 'fanfiction_moderator' === $target_role ) {
			$is_wp_admin = current_user_can( 'manage_options' );
			$is_fanfic_admin = in_array( 'fanfiction_admin', wp_get_current_user()->roles, true );

			if ( ! $is_fanfic_admin && ! $is_wp_admin ) {
				wp_send_json_error( array( 'message' => __( 'Only administrators can promote users to moderator role.', 'fanfiction-manager' ) ) );
			}
		}

		// Store previous role
		$previous_roles = $user->roles;
		if ( ! empty( $previous_roles ) ) {
			update_user_meta( $user_id, 'fanfic_previous_role', $previous_roles[0] );
		}

		// Set new role
		$user->set_role( $target_role );

		// Track promotion
		update_user_meta( $user_id, 'fanfic_promoted_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_promoted_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'fanfic_promoted_to', $target_role );

		// Fire action hook
		do_action( 'fanfic_user_promoted', $user_id, $target_role, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'User promoted successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * AJAX handler to promote a global user to moderator
	 *
	 * Promotes a WordPress user without fanfiction roles to the fanfiction_moderator role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_promote_global_user() {
		// Get user ID and target role
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$target_role = isset( $_POST['target_role'] ) ? sanitize_text_field( wp_unslash( $_POST['target_role'] ) ) : 'fanfiction_moderator';

		// Validate target role
		$allowed_roles = array( 'fanfiction_moderator', 'fanfiction_admin' );
		if ( ! in_array( $target_role, $allowed_roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target role.', 'fanfiction-manager' ) ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_promote_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities based on target role
		if ( 'fanfiction_admin' === $target_role ) {
			// Only WordPress admin or fanfiction_admin can promote to admin
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_fanfiction_settings' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to promote to Admin.', 'fanfiction-manager' ) ) );
			}
		} else {
			// Moderator can promote to moderator
			if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
			}
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Check if user already has the target role
		if ( in_array( $target_role, $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This user already has that role.', 'fanfiction-manager' ) ) );
		}

		// Also reject if user has fanfiction_author or fanfiction_banned_user roles
		// as these users should not appear in the global users table
		if ( in_array( 'fanfiction_author', $user->roles, true ) || in_array( 'fanfiction_banned_user', $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This user cannot be promoted from the global users table.', 'fanfiction-manager' ) ) );
		}

		// Store previous roles (for reference/audit trail)
		$previous_roles = $user->roles;
		if ( ! empty( $previous_roles ) ) {
			update_user_meta( $user_id, 'fanfic_previous_roles', $previous_roles );
		}

		// Add the target fanfiction role (WordPress roles are kept as-is, plugin only cares about fanfiction roles)
		$user->add_role( $target_role );

		// Verify role was added by reloading user
		$user = get_userdata( $user_id );
		if ( ! in_array( $target_role, $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to add role. Role may not exist in the database.', 'fanfiction-manager' ) ) );
		}

		// Track promotion
		update_user_meta( $user_id, 'fanfic_promoted_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_promoted_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'fanfic_promoted_to', $target_role );

		// Fire action hook
		do_action( 'fanfic_global_user_promoted', $user_id, $target_role, get_current_user_id() );

		// Generate success message with role name
		$role_label = ( 'fanfiction_admin' === $target_role ) ? 'Admin' : 'Moderator';
		$message = sprintf( __( 'User promoted to %s successfully.', 'fanfiction-manager' ), $role_label );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX handler to remove moderator role
	 *
	 * Removes the fanfiction_moderator role from a user.
	 * If user has published stories, they become author. Otherwise, they become reader.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_remove_moderator_role() {
		// Get user ID
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_user_action_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Check if user has moderator role
		if ( ! in_array( 'fanfiction_moderator', $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This user does not have the moderator role.', 'fanfiction-manager' ) ) );
		}

		// Permission check: only fanfiction_admins or WordPress admins can remove moderator role
		$is_wp_admin = current_user_can( 'manage_options' );
		$is_fanfic_admin = in_array( 'fanfiction_admin', wp_get_current_user()->roles, true );

		if ( ! $is_fanfic_admin && ! $is_wp_admin ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators can remove moderator roles.', 'fanfiction-manager' ) ) );
		}

		// Remove moderator role
		$user->remove_role( 'fanfiction_moderator' );

		// Determine new role
		// Check if user has published stories
		$story_count = count_user_posts( $user_id, 'fanfiction_story', true );

		if ( $story_count > 0 ) {
			// User has published stories, make them author if not already
			if ( ! in_array( 'fanfiction_author', $user->roles, true ) ) {
				$user->add_role( 'fanfiction_author' );
			}
		} else {
			// No published stories, check for WordPress roles
			$wp_roles = array_diff( $user->roles, array( 'fanfiction_author' ) );

			if ( empty( $wp_roles ) ) {
				// No WordPress roles, make them author
				$user->add_role( 'fanfiction_author' );
			}
			// If they have WordPress roles, they stay with those
		}

		// Track removal
		update_user_meta( $user_id, 'fanfic_moderator_removed_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_moderator_removed_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'fanfic_moderator_role_removed', $user_id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'Moderator role removed successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * AJAX handler to remove admin role
	 *
	 * Removes the fanfiction_admin role from a user.
	 * If user has no previous WordPress role, they become moderator. Otherwise, they revert to previous role.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_remove_admin_role() {
		// Get user ID
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_user_action_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_fanfiction_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Validate user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'fanfiction-manager' ) ) );
		}

		// Check if user has admin role
		if ( ! in_array( 'fanfiction_admin', $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This user does not have the admin role.', 'fanfiction-manager' ) ) );
		}

		// Remove admin role
		$user->remove_role( 'fanfiction_admin' );

		// Check for previous WordPress role stored during promotion
		$previous_roles = get_user_meta( $user_id, 'fanfic_previous_roles', true );

		if ( ! empty( $previous_roles ) && is_array( $previous_roles ) ) {
			// Restore previous WordPress roles
			foreach ( $previous_roles as $role ) {
				if ( ! in_array( $role, $user->roles, true ) ) {
					$user->add_role( $role );
				}
			}
			// Remove the stored previous roles
			delete_user_meta( $user_id, 'fanfic_previous_roles' );
		} else {
			// No previous role stored, make them moderator if not already
			if ( ! in_array( 'fanfiction_moderator', $user->roles, true ) ) {
				$user->add_role( 'fanfiction_moderator' );
			}
		}

		// Track removal
		update_user_meta( $user_id, 'fanfic_admin_removed_by', get_current_user_id() );
		update_user_meta( $user_id, 'fanfic_admin_removed_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'fanfic_admin_role_removed', $user_id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'Admin role removed successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * Process bulk actions
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function process_bulk_actions() {
		// Check if we're on the users page
		if ( ! isset( $_GET['page'] ) || 'fanfiction-users' !== $_GET['page'] ) {
			return;
		}

		// Check if action is set
		if ( ! isset( $_GET['action'] ) && ! isset( $_GET['action2'] ) ) {
			return;
		}

		$action = ! empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : sanitize_text_field( wp_unslash( $_GET['action2'] ) );

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-users' ) ) {
			return;
		}

		// Check capabilities
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get selected users
		$user_ids = isset( $_GET['user'] ) ? array_map( 'absint', $_GET['user'] ) : array();

		if ( empty( $user_ids ) ) {
			return;
		}

		// Check permission level for bulk actions
		$is_wp_admin = current_user_can( 'manage_options' );
		$is_fanfic_admin = in_array( 'fanfiction_admin', wp_get_current_user()->roles, true );

		switch ( $action ) {
			case 'ban':
				$banned_count = 0;
				foreach ( $user_ids as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user && $user_id !== get_current_user_id() ) {
						// Permission checks for banning admins and moderators
						$target_roles = $user->roles;

						// Only WordPress admins can ban fanfiction_admins
						if ( in_array( 'fanfiction_admin', $target_roles, true ) && ! $is_wp_admin ) {
							continue; // Skip this user
						}

						// Only fanfiction_admins or WordPress admins can ban moderators
						if ( in_array( 'fanfiction_moderator', $target_roles, true ) && ! $is_fanfic_admin && ! $is_wp_admin ) {
							continue; // Skip this user
						}

						// Store original role
						$current_roles = $user->roles;
						if ( ! empty( $current_roles ) ) {
							update_user_meta( $user_id, 'fanfic_original_role', $current_roles[0] );
						}

						// Ban user
						$user->set_role( 'fanfiction_banned_user' );
						update_user_meta( $user_id, 'fanfic_banned', '1' );
						update_user_meta( $user_id, 'fanfic_banned_by', get_current_user_id() );
						update_user_meta( $user_id, 'fanfic_banned_at', current_time( 'mysql' ) );

						do_action( 'fanfic_user_banned', $user_id, get_current_user_id() );
						$banned_count++;
					}
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'fanfiction-users',
							'success' => 'bulk_ban',
							'count'   => $banned_count,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;

			case 'unban':
				foreach ( $user_ids as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user && in_array( 'fanfiction_banned_user', $user->roles, true ) ) {
						// Determine role to restore
						$original_role = get_user_meta( $user_id, 'fanfic_original_role', true );

						// Check published story count
						$story_count = count_user_posts( $user_id, 'fanfiction_story', true );

						// Determine appropriate role based on original role and current story count
						$restored_role = '';

						// If original role was moderator or admin, restore that role regardless of story count
						if ( 'fanfiction_moderator' === $original_role || 'fanfiction_admin' === $original_role ) {
							$restored_role = $original_role;
						} else {
							// For author, reader, or no original role - verify based on current story count
							if ( $story_count > 0 ) {
								$restored_role = 'fanfiction_author';
							} else {
								$restored_role = 'fanfiction_reader';
							}
						}

						$user->set_role( $restored_role );
						delete_user_meta( $user_id, 'fanfic_banned' );
						delete_user_meta( $user_id, 'fanfic_original_role' );
						update_user_meta( $user_id, 'fanfic_unbanned_by', get_current_user_id() );
						update_user_meta( $user_id, 'fanfic_unbanned_at', current_time( 'mysql' ) );

						do_action( 'fanfic_user_unbanned', $user_id, get_current_user_id() );
					}
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'fanfiction-users',
							'success' => 'bulk_unban',
							'count'   => count( $user_ids ),
						),
						admin_url( 'admin.php' )
					)
				);
				exit;

			case 'remove_fanfic_roles':
				$removed_count = 0;
				foreach ( $user_ids as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user && $user_id !== get_current_user_id() ) {
						// Check if user is banned - if so, skip them completely
						if ( in_array( 'fanfiction_banned_user', $user->roles, true ) ) {
							continue;
						}

						// List of fanfiction-specific roles to remove (excluding banned_user)
						$fanfic_roles = array(
							'fanfiction_author',
							'fanfiction_reader',
							'fanfiction_moderator',
							'fanfiction_admin',
						);

						// Remove all fanfiction roles
						$had_fanfic_role = false;
						foreach ( $fanfic_roles as $role ) {
							if ( in_array( $role, $user->roles, true ) ) {
								$user->remove_role( $role );
								$had_fanfic_role = true;
							}
						}

						// Only proceed if user had fanfic roles to remove
						if ( $had_fanfic_role ) {
							// Reload user data to get fresh roles after removal
							$user = get_userdata( $user_id );

							// Check if user has any WordPress roles left (admin, editor, subscriber, etc.)
							$has_wp_roles = false;
							$wp_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
							foreach ( $wp_roles as $wp_role ) {
								if ( in_array( $wp_role, $user->roles, true ) ) {
									$has_wp_roles = true;
									break;
								}
							}

							// If user has no WordPress roles, assign fanfiction_reader as default
						if ( ! $has_wp_roles ) {
							// Remove any unrecognized roles (handles "Unknown" role case)
							foreach ( $user->roles as $role ) {
								$user->remove_role( $role );
							}
							$user->add_role( 'fanfiction_reader' );
						}

							// Track removal
							update_user_meta( $user_id, 'fanfic_roles_removed_by', get_current_user_id() );
							update_user_meta( $user_id, 'fanfic_roles_removed_at', current_time( 'mysql' ) );

							// Fire action hook
							do_action( 'fanfic_user_fanfic_roles_removed', $user_id, get_current_user_id() );

							$removed_count++;
						}
					}
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'fanfiction-users',
							'success' => 'bulk_remove_fanfic_roles',
							'count'   => $removed_count,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
		}
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function display_notices() {
		// Success notices
		if ( isset( $_GET['success'] ) ) {
			$success_code = sanitize_text_field( wp_unslash( $_GET['success'] ) );
			$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

			$messages = array(
				'bulk_ban'   => sprintf(
					/* translators: %d: Number of users */
					_n( '%d user banned successfully.', '%d users banned successfully.', $count, 'fanfiction-manager' ),
					$count
				),
				'bulk_unban' => sprintf(
					/* translators: %d: Number of users */
					_n( '%d user unbanned successfully.', '%d users unbanned successfully.', $count, 'fanfiction-manager' ),
					$count
				),
			'bulk_remove_fanfic_roles' => sprintf(
				/* translators: %d: Number of users */
				_n( 'Fanfiction roles removed from %d user.', 'Fanfiction roles removed from %d users.', $count, 'fanfiction-manager' ),
				$count
			),
		);

			if ( isset( $messages[ $success_code ] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $success_code ] ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Track user last login
	 *
	 * @since 1.0.0
	 * @param string  $user_login Username
	 * @param WP_User $user       User object
	 * @return void
	 */
	public static function track_last_login( $user_login, $user ) {
		update_user_meta( $user->ID, 'fanfic_last_login', time() );
	}

	/**
	 * Auto-demote authors with 0 published stories (daily WP-Cron)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function auto_demote_authors() {
		// Get all fanfiction authors
		$authors = get_users(
			array(
				'role'   => 'fanfiction_author',
				'fields' => 'ID',
			)
		);

		foreach ( $authors as $author_id ) {
			// Count published stories
			$story_count = count_user_posts( $author_id, 'fanfiction_story', true );

			// Demote if no published stories
			if ( 0 === $story_count ) {
				$user = get_userdata( $author_id );
				if ( $user ) {
					$user->set_role( 'fanfiction_reader' );
					update_user_meta( $author_id, 'fanfic_auto_demoted', '1' );
					update_user_meta( $author_id, 'fanfic_auto_demoted_at', current_time( 'mysql' ) );

					do_action( 'fanfic_user_auto_demoted', $author_id );
				}
			}
		}
	}

	/**
	 * Auto-promote reader to author when first story is published
	 *
	 * @since 1.0.0
	 * @param string  $new_status New post status
	 * @param string  $old_status Old post status
	 * @param WP_Post $post       Post object
	 * @return void
	 */
	public static function auto_promote_to_author( $new_status, $old_status, $post ) {
		// Only for fanfiction stories
		if ( 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		// Only when transitioning to published
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Get author
		$author = get_userdata( $post->post_author );
		if ( ! $author ) {
			return;
		}

		// Check if user is a reader (fanfiction_reader role)
		if ( ! in_array( 'fanfiction_reader', $author->roles, true ) ) {
			return;
		}

		// Count total published stories
		$story_count = count_user_posts( $post->post_author, 'fanfiction_story', true );

		// Promote to author if this is their first published story
		if ( 1 === $story_count ) {
			$author->set_role( 'fanfiction_author' );
			update_user_meta( $post->post_author, 'fanfic_auto_promoted', '1' );
			update_user_meta( $post->post_author, 'fanfic_auto_promoted_at', current_time( 'mysql' ) );

			do_action( 'fanfic_user_auto_promoted', $post->post_author );
		}
	}
}
