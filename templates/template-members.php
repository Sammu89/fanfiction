<div class="fanfic-template-wrapper">
<?php
/**
 * Template: User Profile Page
 *
 * Displays user profile
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get custom template from settings
$template = get_option( 'fanfic_profile_view_template', '' );

// If no custom template, use default from settings class
if ( empty( $template ) && class_exists( 'Fanfic_Settings' ) ) {
	// Get default via reflection since the method is private
	$reflection = new ReflectionClass( 'Fanfic_Settings' );
	if ( $reflection->hasMethod( 'get_default_profile_template' ) ) {
		$method = $reflection->getMethod( 'get_default_profile_template' );
		$method->setAccessible( true );
		$template = $method->invoke( null );
	}
}
?>

<main id="main-content" class="fanfic-main-content" role="main">
    <article class="fanfic-page-members">
        <?php
        // Process shortcodes in the template
        echo do_shortcode( $template );
        ?>
    </article>
</main>
</div>
