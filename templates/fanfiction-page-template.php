<?php
/**
 * Fanfiction Page Template
 *
 * Captures and analyzes header output to detect semantic tags (<main>, <article>, <h1>).
 * Ensures proper HTML5 semantics without duplicates.
 * Caches detections per theme and version with proper invalidation.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

// Get current theme and version for caching
$current_theme = get_template();
$theme_version = wp_get_theme()->get( 'Version' );
$transient_key = 'fanfic_theme_detections_' . md5( $current_theme . $theme_version );
$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
$block_header_markup = '';
$block_footer_markup = '';
$use_block_shell = false;

if ( $is_block_theme ) {
	$block_header_markup = trim( (string) do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ) );
	$block_footer_markup = trim( (string) do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ) );
	$use_block_shell = ( '' !== $block_header_markup && '' !== $block_footer_markup );
}

// Render theme header/footer in a way that works for both classic and block themes.
$fanfic_render_theme_header = static function() use ( $use_block_shell, $block_header_markup ) {
	if ( $use_block_shell ) {
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<?php wp_head(); ?>
		</head>
		<body <?php body_class(); ?>>
		<?php
		if ( function_exists( 'wp_body_open' ) ) {
			wp_body_open();
		}
		echo $block_header_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	get_header();
};

$fanfic_render_theme_footer = static function() use ( $use_block_shell, $block_footer_markup ) {
	if ( $use_block_shell ) {
		echo $block_footer_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_footer();
		?>
		</body>
		</html>
		<?php
		return;
	}

	get_footer();
};

$has_main = false;
$has_article = false;
$has_h1 = false;
$need_header_modification = false;
$header_output = null;

if ( $use_block_shell ) {
	// For block themes, render once with proper document shell.
	$fanfic_render_theme_header();
} else {
	// Check for cached detections
	$detections = get_transient( $transient_key );

	if ( false === $detections ) {
		// Capture header output for detection
		ob_start();
		$fanfic_render_theme_header();
		$header_output = ob_get_clean();

		// Detect tags in header using proper regex
		$has_main    = (bool) preg_match( '/<main\b/i', $header_output );
		$has_article = (bool) preg_match( '/<article\b/i', $header_output );
		$has_h1      = (bool) preg_match( '/<h1\b/i', $header_output );

		// Cache detections
		$detections = array(
			'has_main'    => $has_main,
			'has_article' => $has_article,
			'has_h1'      => $has_h1,
		);
		set_transient( $transient_key, $detections, YEAR_IN_SECONDS );

		// Store the transient key for invalidation
		$all_keys = get_option( 'fanfic_detection_transients', array() );
		if ( ! in_array( $transient_key, $all_keys, true ) ) {
			$all_keys[] = $transient_key;
			update_option( 'fanfic_detection_transients', $all_keys );
		}

		$need_header_modification = $has_h1;
	} else {
		// Use cached detections
		$has_main    = $detections['has_main'];
		$has_article = $detections['has_article'];
		$has_h1      = $detections['has_h1'];

		$need_header_modification = $has_h1;
		$header_output = null; // Will capture if needed
	}

	// Only capture and modify header if we need to strip H1s
	if ( $need_header_modification ) {
		if ( null === $header_output ) {
			ob_start();
			$fanfic_render_theme_header();
			$header_output = ob_get_clean();
		}

		// Remove H1 tags that match the page title (fast regex approach)
		$page_title = preg_quote( get_the_title(), '/' );
		$header_output = preg_replace(
			'/<h1[^>]*>\s*' . $page_title . '\s*<\/h1>/i',
			'',
			$header_output
		);

		echo $header_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		// No modification needed, just output header directly
		$fanfic_render_theme_header();
	}
}

// Check if we should show sidebar
$show_sidebar = get_option( 'fanfic_show_sidebar', '1' );
$layout_class = ( '1' === $show_sidebar ) ? 'fanfiction-with-sidebar' : 'fanfiction-no-sidebar';

// Decide main tag and attributes
$main_tag = $has_main ? 'div' : 'main';
if ( $has_main ) {
    $main_id = 'primary';
    $main_class = 'fanfiction-page-content';
    $main_role = '';
} else {
    $main_id = 'primary';
    $main_class = 'fanfiction-page-content';
    $main_role = ' role="main"';
}
?>
<<?php echo esc_html( $main_tag ); ?> id="<?php echo esc_attr( $main_id ); ?>" class="<?php echo esc_attr( $main_class ); ?>"<?php echo $main_role; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <div id="fanfiction-wrapper" class="fanfiction-page-wrapper <?php echo esc_attr( $layout_class ); ?>">
        <div class="fanfiction-page-main">
            <?php
            while ( have_posts() ) :
                the_post();

                // Open article if not in header
                if ( ! $has_article ) :
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class( 'fanfiction-page' ); ?>>
            <?php
                endif;
            ?>
                <div class="entry-content">
                    <?php
                    // Load custom content template via filter (no globals)
                    $content_template = apply_filters( 'fanfic_content_template', '' );

                    if ( ! empty( $content_template ) ) {
                        $content_template_path = '';

                        // Check theme first
                        $theme_template = locate_template( array(
                            'fanfiction-manager/' . $content_template,
                            $content_template,
                        ) );

                        if ( $theme_template ) {
                            $content_template_path = $theme_template;
                        } else {
                            // Check plugin directory
                            $plugin_template = FANFIC_PLUGIN_DIR . 'templates/' . $content_template;
                            if ( file_exists( $plugin_template ) ) {
                                $content_template_path = $plugin_template;
                            }
                        }

                        if ( $content_template_path ) {
                            // Set flag to allow template to load its content
                            $fanfic_load_template = true;
                            include $content_template_path;
                        } else {
                            the_content();
                        }
                    } else {
                        the_content();
                        wp_link_pages(
                            array(
                                'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'fanfiction-manager' ),
                                'after'  => '</div>',
                            )
                        );
                    }
                    ?>
                </div>
            <?php
                // Close article if we opened it
                if ( ! $has_article ) :
            ?>
            </article>
            <?php
                endif;
            endwhile;
            ?>
        </div>
        
        <?php if ( '1' === $show_sidebar && is_active_sidebar( 'fanfiction-sidebar' ) ) : ?>
        <aside id="secondary" class="widget-area fanfiction-sidebar" role="complementary">
            <?php dynamic_sidebar( 'fanfiction-sidebar' ); ?>
        </aside>
        <?php endif; ?>
    </div>
</<?php echo esc_html( $main_tag ); ?>>
<?php
$fanfic_render_theme_footer();
