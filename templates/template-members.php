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
            <h1><?php esc_html_e( 'User Profile', 'fanfiction-manager' ); ?></h1>
        </header>

        <section class="page-content">
            <?php echo do_shortcode( '[user-profile]' ); ?>
        </section>
    </article>
</main>
