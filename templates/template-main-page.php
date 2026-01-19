<!-- wp:heading {"level":1} -->
<h1 class="fanfic-title  wp-block-heading"><?php esc_html_e( 'Welcome to the Fanfiction Archive', 'fanfiction-manager' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Discover amazing stories written by talented authors in our community.', 'fanfiction-manager' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Browse Stories', 'fanfiction-manager' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Explore our collection of fanfiction stories across multiple genres and fandoms. Find your next favorite read!', 'fanfiction-manager' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( get_post_type_archive_link( 'fanfiction_story' ) ); ?>"><?php esc_html_e( 'View All Stories', 'fanfiction-manager' ); ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Join Our Community', 'fanfiction-manager' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Become part of our creative community! Share your own stories, get feedback from readers, and connect with fellow writers.', 'fanfiction-manager' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create Account', 'fanfiction-manager' ); ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
