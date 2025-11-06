<?php
/**
 * Template: Members Directory & User Profiles
 *
 * Displays:
 * - /members/ → List of all authors (using WordPress native WP_User_Query)
 * - /members/username/ → Individual user profile
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get member_name query var to determine if showing list or profile
$member_name = get_query_var( 'member_name' );
$is_profile_view = ! empty( $member_name );
?>

<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="fanfic-main-content" class="fanfic-main-content" role="main">

<?php if ( $is_profile_view ) : ?>
    <!-- INDIVIDUAL USER PROFILE -->
    <article class="fanfic-page-members fanfic-user-profile">
        <?php
        // Get user by username
        $user = get_user_by( 'login', $member_name );

        if ( ! $user ) {
            ?>
            <div class="fanfic-error-notice" role="alert">
                <p><?php esc_html_e( 'User not found.', 'fanfiction-manager' ); ?></p>
            </div>
            <?php
        } else {
            // Check if editing
            $is_editing = isset( $_GET['action'] ) && $_GET['action'] === 'edit';

            if ( $is_editing ) {
                // Check permissions
                if ( ! is_user_logged_in() || ( get_current_user_id() !== $user->ID && ! current_user_can( 'edit_users' ) ) ) {
                    ?>
                    <div class="fanfic-error-notice" role="alert">
                        <p><?php esc_html_e( 'You do not have permission to edit this profile.', 'fanfiction-manager' ); ?></p>
                    </div>
                    <?php
                } else {
                    // Load edit profile template
                    echo do_shortcode( '[edit-profile-form]' );
                }
            } else {
                // Display profile using template from settings
                $template = get_option( 'fanfic_profile_view_template', '' );

                // If no custom template, use default
                if ( empty( $template ) && class_exists( 'Fanfic_Settings' ) ) {
                    $reflection = new ReflectionClass( 'Fanfic_Settings' );
                    if ( $reflection->hasMethod( 'get_default_profile_template' ) ) {
                        $method = $reflection->getMethod( 'get_default_profile_template' );
                        $method->setAccessible( true );
                        $template = $method->invoke( null );
                    }
                }

                // Process shortcodes in template
                echo do_shortcode( $template );
            }
        }
        ?>
    </article>

<?php else : ?>
    <!-- AUTHOR DIRECTORY LIST -->
    <article class="fanfic-page-members fanfic-members-directory">
        <header class="fanfic-page-header">
            <h1 class="fanfic-page-title"><?php esc_html_e( 'Authors Directory', 'fanfiction-manager' ); ?></h1>
            <p class="fanfic-page-description"><?php esc_html_e( 'Browse all authors and their works', 'fanfiction-manager' ); ?></p>
        </header>

        <div class="fanfic-members-list">
            <?php
            // Use WordPress native WP_User_Query
            $paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

            $args = array(
                'role__in' => array( 'fanfiction_author', 'fanfiction_moderator', 'administrator' ),
                'orderby'  => 'display_name',
                'order'    => 'ASC',
                'number'   => 20, // Authors per page
                'paged'    => $paged,
            );

            $user_query = new WP_User_Query( $args );
            $authors = $user_query->get_results();

            if ( ! empty( $authors ) ) {
                ?>
                <div class="fanfic-members-grid">
                    <?php foreach ( $authors as $author ) :
                        // Get author stats
                        $story_count = count_user_posts( $author->ID, 'fanfiction_story', true );
                        $profile_url = Fanfic_URL_Manager::get_instance()->get_page_url( 'members', array( 'member_name' => $author->user_login ) );
                    ?>
                        <div class="fanfic-member-card">
                            <div class="fanfic-member-avatar">
                                <?php echo get_avatar( $author->ID, 80 ); ?>
                            </div>
                            <div class="fanfic-member-info">
                                <h2 class="fanfic-member-name">
                                    <a href="<?php echo esc_url( $profile_url ); ?>">
                                        <?php echo esc_html( $author->display_name ); ?>
                                    </a>
                                </h2>
                                <p class="fanfic-member-stats">
                                    <?php
                                    printf(
                                        esc_html( _n( '%d story', '%d stories', $story_count, 'fanfiction-manager' ) ),
                                        $story_count
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

                if ( $total_pages > 1 ) {
                    echo '<nav class="fanfic-pagination" role="navigation" aria-label="' . esc_attr__( 'Authors pagination', 'fanfiction-manager' ) . '">';
                    echo paginate_links( array(
                        'base'      => get_pagenum_link( 1 ) . '%_%',
                        'format'    => 'page/%#%/',
                        'current'   => $paged,
                        'total'     => $total_pages,
                        'prev_text' => __( '&laquo; Previous', 'fanfiction-manager' ),
                        'next_text' => __( 'Next &raquo;', 'fanfiction-manager' ),
                    ) );
                    echo '</nav>';
                }
            } else {
                ?>
                <div class="fanfic-no-results">
                    <p><?php esc_html_e( 'No authors found.', 'fanfiction-manager' ); ?></p>
                </div>
                <?php
            }
            ?>
        </div>
    </article>
<?php endif; ?>

</main>
</div>

<?php get_footer(); ?>
