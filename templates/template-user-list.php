<?php
/**
 * Template: Members Directory (Authors List)
 *
 * Displays a list of all authors with:
 * - Author display name with link to profile
 * - Published story count
 * - Special roles (Admin, Moderator)
 *
 * Uses optimized WP_User_Query for performance
 *
 * @package Fanfiction_Manager
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fanfic-template-wrapper">
	<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

	<!-- Breadcrumb Navigation -->
	<?php fanfic_render_breadcrumb( 'members' ); ?>

	<!-- Page Header -->
	<header class="fanfic-page-header" id="fanfic-main-content">
		<h1 class="fanfic-page-title"><?php esc_html_e( 'Authors Directory', 'fanfiction-manager' ); ?></h1>
		<p class="fanfic-page-description"><?php esc_html_e( 'Browse all authors and discover their stories', 'fanfiction-manager' ); ?></p>
	</header>

	<!-- Authors List -->
	<section class="fanfic-content-section fanfic-members-directory" aria-labelledby="fanfic-main-content">
		<?php
		// Get current page for pagination
		$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

		// Optimized WP_User_Query - only get users with plugin roles
		$args = array(
			'role__in' => array(
				'fanfiction_author',
				'fanfiction_moderator',
				'fanfiction_admin',
				'administrator', // WordPress admins
			),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'number'   => 20, // Authors per page
			'paged'    => $paged,
			// Only fetch fields we need for performance
			'fields'   => array( 'ID', 'display_name', 'user_login' ),
		);

		$user_query = new WP_User_Query( $args );
		$authors    = $user_query->get_results();

		if ( ! empty( $authors ) ) :
			?>
			<div class="fanfic-members-grid">
				<?php
				foreach ( $authors as $author ) :
					// Get user object with roles (fields parameter above doesn't include roles)
					$user_obj = get_userdata( $author->ID );

					// Get published story count (optimized query)
					$story_count = count_user_posts( $author->ID, 'fanfiction_story', true );

					// Determine special role
					$special_role = '';
					if ( in_array( 'administrator', (array) $user_obj->roles, true ) ) {
						$special_role = __( 'Admin', 'fanfiction-manager' );
					} elseif ( in_array( 'fanfiction_admin', (array) $user_obj->roles, true ) ) {
						$special_role = __( 'Admin', 'fanfiction-manager' );
					} elseif ( in_array( 'fanfiction_moderator', (array) $user_obj->roles, true ) ) {
						$special_role = __( 'Moderator', 'fanfiction-manager' );
					}

					// Get profile URL
					$url_manager = Fanfic_URL_Manager::get_instance();
					$profile_url = $url_manager->get_page_url( 'members', array( 'member_name' => $author->user_login ) );
					?>
					<div class="fanfic-member-card">
						<div class="fanfic-member-avatar">
							<a href="<?php echo esc_url( $profile_url ); ?>" aria-hidden="true" tabindex="-1">
								<?php echo get_avatar( $author->ID, 80, '', $author->display_name ); ?>
							</a>
						</div>
						<div class="fanfic-member-info">
							<h2 class="fanfic-member-name">
								<a href="<?php echo esc_url( $profile_url ); ?>">
									<?php echo esc_html( $author->display_name ); ?>
								</a>
								<?php if ( $special_role ) : ?>
									<span class="fanfic-role-badge fanfic-role-<?php echo esc_attr( strtolower( $special_role ) ); ?>">
										<?php echo esc_html( $special_role ); ?>
									</span>
								<?php endif; ?>
							</h2>
							<p class="fanfic-member-stats">
								<?php
								printf(
									esc_html( _n( '%d published story', '%d published stories', $story_count, 'fanfiction-manager' ) ),
									absint( $story_count )
								);
								?>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			// Pagination
			$total_users = $user_query->get_total();
			$total_pages = ceil( $total_users / 20 );

			if ( $total_pages > 1 ) :
				?>
				<nav class="fanfic-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Authors pagination', 'fanfiction-manager' ); ?>">
					<?php
					echo paginate_links(
						array(
							'base'      => get_pagenum_link( 1 ) . '%_%',
							'format'    => 'page/%#%/',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => __( '&laquo; Previous', 'fanfiction-manager' ),
							'next_text' => __( 'Next &raquo;', 'fanfiction-manager' ),
							'mid_size'  => 2,
							'end_size'  => 1,
						)
					);
					?>
				</nav>
				<?php
			endif;
		else :
			?>
			<div class="fanfic-empty-state" role="status">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No authors found.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		endif;
		?>
	</section>

	<!-- Breadcrumb Navigation (Bottom) -->
	<?php fanfic_render_breadcrumb( 'members', array( 'position' => 'bottom' ) ); ?>
</div>
