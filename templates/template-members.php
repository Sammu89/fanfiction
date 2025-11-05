<div class="fanfic-template-wrapper">
<?php
/**
 * Template: User Profile Page
 *
 * Displays user profile using ?member=username parameter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<main id="main-content" class="fanfic-main-content" role="main">
    <article class="fanfic-page-members">
        <header class="page-header">
            <h1 class="fanfic-page-title"><?php esc_html_e( 'User Profile', 'fanfiction-manager' ); ?></h1>
        </header>

        <section class="fanfic-content-section" class="page-content">
            <div class="fanfic-member-profile">
                <div class="fanfic-member-header">
                    <?php echo do_shortcode( '[user-profile]' ); ?>
                    <?php echo do_shortcode( '[edit-author-button]' ); ?>
                </div>

                <div class="fanfic-member-stats">
                    <div class="fanfic-member-rating">
                        <h3><?php esc_html_e( 'Author Rating', 'fanfiction-manager' ); ?></h3>
                        <?php echo do_shortcode( '[author-average-rating display="stars"]' ); ?>
                    </div>

                    <div class="fanfic-member-stats-grid">
                        <div class="fanfic-member-stat">
                            <h4><?php esc_html_e( 'Stories', 'fanfiction-manager' ); ?></h4>
                            <?php echo do_shortcode( '[author-story-count]' ); ?>
                        </div>
                        <div class="fanfic-member-stat">
                            <h4><?php esc_html_e( 'Total Words', 'fanfiction-manager' ); ?></h4>
                            <?php echo do_shortcode( '[author-total-words]' ); ?>
                        </div>
                    </div>
                </div>

                <div class="fanfic-member-stories">
                    <h2><?php esc_html_e( 'Stories by Author', 'fanfiction-manager' ); ?></h2>
                    <?php echo do_shortcode( '[author-stories-grid limit="12" paginate="true"]' ); ?>
                </div>
            </div>
        </section>
    </article>
</main>
</div>
