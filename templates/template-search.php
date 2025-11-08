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

<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

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

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
		</li>
	</ol>
</nav>

</div>

<?php get_footer(); ?>
