<?php
/**
 * Template: Search Page
 *
 * Displays story search form and results
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>


    <article class="fanfic-page-content">
        <header class="fanfic-page-header">
            <h1 class="fanfic-page-title"><?php esc_html_e( 'Search Stories', 'fanfiction-manager' ); ?></h1>
        </header>

        <section class="fanfic-content-section" role="search" aria-label="<?php esc_attr_e( 'Search form', 'fanfiction-manager' ); ?>">
            <?php echo do_shortcode( '[search-form]' ); ?>
        </section>

        <section class="fanfic-content-section" role="region" aria-label="<?php esc_attr_e( 'Search results', 'fanfiction-manager' ); ?>">
            <?php echo do_shortcode( '[search-results]' ); ?>
        </section>
    </article>

</div>

<?php get_footer(); ?>
