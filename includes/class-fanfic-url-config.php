<?php
/**
 * Fanfiction Manager - URL Configuration Class
 *
 * Handles URL rewrite rules configuration including base slug, chapter type slugs,
 * and secondary paths with validation and 301 redirect support.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Fanfic_URL_Config
 *
 * Manages all URL configuration settings for the Fanfiction Manager plugin.
 * Provides admin interface for customizing URL structures and handles validation,
 * saving, and rewrite rule flushing.
 *
 * @since 1.0.0
 */
class Fanfic_URL_Config {

    /**
     * Instance of this class
     *
     * @var Fanfic_URL_Config
     */
    private static $instance = null;

    /**
     * Option name for storing base slug
     *
     * @var string
     */
    const OPTION_BASE_SLUG = 'fanfic_base_slug';

    /**
     * Option name for storing chapter type slugs
     *
     * @var string
     */
    const OPTION_CHAPTER_SLUGS = 'fanfic_chapter_slugs';

    /**
     * Option name for storing secondary paths
     *
     * @var string
     */
    const OPTION_SECONDARY_PATHS = 'fanfic_secondary_paths';

    /**
     * Option name for storing story path
     *
     * @var string
     */
    const OPTION_STORY_PATH = 'fanfic_story_path';

    /**
     * Option name for storing old base slug (for 301 redirects)
     *
     * @var string
     */
    const OPTION_OLD_BASE_SLUG = 'fanfic_old_base_slug';

    /**
     * Initialize the URL configuration functionality
     *
     * Registers action hooks for handling form submissions.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        add_action( 'admin_post_fanfic_save_url_config', array( self::$instance, 'save_url_config' ) );
        add_action( 'admin_post_fanfic_delete_redirect', array( self::$instance, 'delete_redirect' ) );
        add_action( 'template_redirect', array( self::$instance, 'handle_301_redirects' ) );
    }

    /**
     * Render just the form fields (reusable in wizard and settings page)
     *
     * @since 1.0.0
     * @param bool $in_wizard Whether this is being rendered in the wizard context.
     * @return void
     */
    public static function render_form_fields( $in_wizard = false ) {
        $current_slugs      = self::get_current_slugs();
        $main_page_mode     = get_option( 'fanfic_main_page_mode', 'custom_homepage' );
        $page_slugs         = get_option( 'fanfic_system_page_slugs', array() );
        $page_ids           = get_option( 'fanfic_system_page_ids', array() );

        // Determine nonce and action based on context
        if ( $in_wizard ) {
            wp_nonce_field( 'fanfic_wizard_step_2', 'fanfic_wizard_nonce_step_2' );
        } else {
            wp_nonce_field( 'fanfic_save_url_config', 'fanfic_url_config_nonce' );
            echo '<input type="hidden" name="action" value="fanfic_save_url_config">';
        }
        ?>

                <!-- ============================================ -->
                <!-- SECTION 1: SITE ORGANIZATION -->
                <!-- ============================================ -->
                <div class="fanfic-config-section fanfic-section-main">
                    <div class="fanfic-section-header">
                        <h2><?php echo esc_html__( 'How do you want your site organized?', 'fanfiction-manager' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'Choose whether your homepage shows the story archive directly or allows for custom content.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <div class="fanfic-radio-cards">
                            <label class="fanfic-radio-card <?php echo $main_page_mode === 'stories_homepage' ? 'selected' : ''; ?>">
                                <input 
                                    type="radio" 
                                    name="fanfic_main_page_mode" 
                                    value="stories_homepage" 
                                    <?php checked( $main_page_mode, 'stories_homepage' ); ?>
                                >
                                <div class="fanfic-radio-card-content">
                                    <strong><?php echo esc_html__( 'Stories Archive as Homepage', 'fanfiction-manager' ); ?></strong>
                                    <span class="fanfic-radio-card-desc">
                                        <?php echo esc_html__( 'Your main page displays the story archive directly', 'fanfiction-manager' ); ?>
                                    </span>
                                </div>
                            </label>

                            <label class="fanfic-radio-card <?php echo $main_page_mode === 'custom_homepage' ? 'selected' : ''; ?>">
                                <input 
                                    type="radio" 
                                    name="fanfic_main_page_mode" 
                                    value="custom_homepage" 
                                    <?php checked( $main_page_mode, 'custom_homepage' ); ?>
                                >
                                <div class="fanfic-radio-card-content">
                                    <strong><?php echo esc_html__( 'Custom Homepage', 'fanfiction-manager' ); ?></strong>
                                    <span class="fanfic-radio-card-desc">
                                        <?php echo esc_html__( 'Create a custom homepage with separate archive page', 'fanfiction-manager' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: PRIMARY URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h2><?php echo esc_html__( 'Primary URLs', 'fanfiction-manager' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'These are the main URL slugs that define your site structure. They appear in all story and archive URLs.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <!-- Base Slug -->
                                <tr>
                                    <th scope="row">
                                        <label for="fanfic_base_slug">
                                            <?php echo esc_html__( 'Base Slug', 'fanfiction-manager' ); ?>
                                            <span class="fanfic-required">*</span>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="fanfic_base_slug"
                                            name="fanfic_base_slug"
                                            value="<?php echo esc_attr( $current_slugs['base'] ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="base"
                                        >
                                        <p class="description">
                                            <?php echo esc_html__( 'The root path for all fanfiction content. Used in all URLs.', 'fanfiction-manager' ); ?>
                                        </p>
                                        <p class="description">
                                            <code id="base-preview-code"><?php echo esc_html( home_url( '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['base'] ); ?></span>/</code>
                                        </p>
                                        <div class="fanfic-slug-validation" id="base-slug-validation"></div>
                                    </td>
                                </tr>

                                <!-- Stories Slug -->
                                <tr>
                                    <th scope="row">
                                        <label for="fanfic_story_path">
                                            <?php echo esc_html__( 'Stories Slug', 'fanfiction-manager' ); ?>
                                            <span class="fanfic-required">*</span>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="fanfic_story_path"
                                            name="fanfic_story_path"
                                            value="<?php echo esc_attr( $current_slugs['story_path'] ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="story_path"
                                        >
                                        <p class="description">
                                            <?php echo esc_html__( 'The subdirectory where individual stories are placed. Used in all story URLs.', 'fanfiction-manager' ); ?>
                                        </p>
                                        <p class="description">
                                            <code id="story-path-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['story_path'] ); ?></span>/my-story-title/</code>
                                        </p>
                                        <div class="fanfic-slug-validation" id="story-path-validation"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 3: USER & SYSTEM URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h2><?php echo esc_html__( 'User & System URLs', 'fanfiction-manager' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'Configure URLs for user profiles, dashboard, and system pages.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <fieldset class="fanfic-fieldset">
                            <legend><?php echo esc_html__( 'User-Facing Paths', 'fanfiction-manager' ); ?></legend>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <!-- Dashboard -->
                                    <tr>
                                        <th scope="row">
                                            <label for="fanfic_dashboard_slug">
                                                <?php echo esc_html__( 'Dashboard', 'fanfiction-manager' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input
                                                type="text"
                                                id="fanfic_dashboard_slug"
                                                name="fanfic_dashboard_slug"
                                                value="<?php echo esc_attr( isset( $current_slugs['dashboard'] ) ? $current_slugs['dashboard'] : 'dashboard' ); ?>"
                                                class="regular-text fanfic-slug-input"
                                                pattern="[a-z0-9\-]+"
                                                maxlength="50"
                                                required
                                                data-slug-type="dashboard"
                                            >
                                            <p class="description">
                                                <code id="dashboard-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug">dashboard</span>/</code>
                                            </p>
                                        </td>
                                    </tr>

                                    <!-- User Profile -->
                                    <tr>
                                        <th scope="row">
                                            <label for="fanfic_user_slug">
                                                <?php echo esc_html__( 'User Profile', 'fanfiction-manager' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input
                                                type="text"
                                                id="fanfic_user_slug"
                                                name="fanfic_user_slug"
                                                value="<?php echo esc_attr( isset( $current_slugs['user'] ) ? $current_slugs['user'] : 'user' ); ?>"
                                                class="regular-text fanfic-slug-input"
                                                pattern="[a-z0-9\-]+"
                                                maxlength="50"
                                                required
                                                data-slug-type="user"
                                            >
                                            <p class="description">
                                                <code id="user-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug">user</span>/username/</code>
                                            </p>
                                        </td>
                                    </tr>

                                    <!-- Search -->
                                    <tr>
                                        <th scope="row">
                                            <label for="fanfic_search_slug">
                                                <?php echo esc_html__( 'Search', 'fanfiction-manager' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input
                                                type="text"
                                                id="fanfic_search_slug"
                                                name="fanfic_search_slug"
                                                value="<?php echo esc_attr( isset( $current_slugs['search'] ) ? $current_slugs['search'] : 'search' ); ?>"
                                                class="regular-text fanfic-slug-input"
                                                pattern="[a-z0-9\-]+"
                                                maxlength="50"
                                                required
                                                data-slug-type="search"
                                            >
                                            <p class="description">
                                                <code id="search-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug">search</span>/</code>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </fieldset>

                        <fieldset class="fanfic-fieldset" style="margin-top: 25px;">
                            <legend><?php echo esc_html__( 'System Page Slugs', 'fanfiction-manager' ); ?></legend>
                            <p class="description" style="margin-bottom: 15px;">
                                <?php echo esc_html__( 'Customize URL slugs for login, registration, and management pages.', 'fanfiction-manager' ); ?>
                            </p>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <?php
                                    $system_pages = array(
                                        'login'          => __( 'Login Page', 'fanfiction-manager' ),
                                        'register'       => __( 'Register Page', 'fanfiction-manager' ),
                                        'password_reset' => __( 'Password Reset', 'fanfiction-manager' ),
                                        'dashboard'      => __( 'Dashboard Page', 'fanfiction-manager' ),
                                        'create_story'   => __( 'Create Story', 'fanfiction-manager' ),
                                        'search'         => __( 'Search Page', 'fanfiction-manager' ),
                                        'members'        => __( 'Profile Page', 'fanfiction-manager' ),
                                        'error'          => __( 'Default Error Page', 'fanfiction-manager' ),
                                        'maintenance'    => __( 'Maintenance Page', 'fanfiction-manager' ),
                                    );
                                    // Note: Edit pages removed - now using query parameters (?action=edit) instead

                                    foreach ( $system_pages as $key => $label ) :
                                        $default_slug = $key;
                                        $current_slug = isset( $page_slugs[ $key ] ) ? $page_slugs[ $key ] : $default_slug;
                                        $page_id      = isset( $page_ids[ $key ] ) ? $page_ids[ $key ] : 0;
                                        ?>
                                        <tr>
                                            <th scope="row">
                                                <label for="page_slug_<?php echo esc_attr( $key ); ?>">
                                                    <?php echo esc_html( $label ); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="fanfic_system_page_slugs[<?php echo esc_attr( $key ); ?>]"
                                                    id="page_slug_<?php echo esc_attr( $key ); ?>"
                                                    value="<?php echo esc_attr( $current_slug ); ?>"
                                                    pattern="[a-z0-9\-]+"
                                                    maxlength="50"
                                                    class="regular-text fanfic-slug-input"
                                                    data-slug-type="system_<?php echo esc_attr( $key ); ?>"
                                                />
                                                <p class="description">
                                                    <code id="system-<?php echo esc_attr( $key ); ?>-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slug ); ?></span>/</code>
                                                </p>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </fieldset>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 4: CHAPTER URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h2><?php echo esc_html__( 'Chapter URLs', 'fanfiction-manager' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'Define URL slugs for different chapter types. These must be unique from each other.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <!-- Prologue -->
                                <tr>
                                    <th scope="row">
                                        <label for="fanfic_prologue_slug">
                                            <?php echo esc_html__( 'Prologue', 'fanfiction-manager' ); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="fanfic_prologue_slug"
                                            name="fanfic_prologue_slug"
                                            value="<?php echo esc_attr( isset( $current_slugs['prologue'] ) ? $current_slugs['prologue'] : 'prologue' ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="prologue"
                                        >
                                        <p class="description">
                                            <code id="prologue-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['story_path'] ); ?></span>/my-story-title/<span class="fanfic-dynamic-slug">prologue</span>/</code>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Chapter -->
                                <tr>
                                    <th scope="row">
                                        <label for="fanfic_chapter_slug">
                                            <?php echo esc_html__( 'Chapter', 'fanfiction-manager' ); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="fanfic_chapter_slug"
                                            name="fanfic_chapter_slug"
                                            value="<?php echo esc_attr( isset( $current_slugs['chapter'] ) ? $current_slugs['chapter'] : 'chapter' ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="chapter"
                                        >
                                        <p class="description">
                                            <code id="chapter-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['story_path'] ); ?></span>/my-story-title/<span class="fanfic-dynamic-slug">chapter</span>-1/</code>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Epilogue -->
                                <tr>
                                    <th scope="row">
                                        <label for="fanfic_epilogue_slug">
                                            <?php echo esc_html__( 'Epilogue', 'fanfiction-manager' ); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="fanfic_epilogue_slug"
                                            name="fanfic_epilogue_slug"
                                            value="<?php echo esc_attr( isset( $current_slugs['epilogue'] ) ? $current_slugs['epilogue'] : 'epilogue' ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="epilogue"
                                        >
                                        <p class="description">
                                            <code id="epilogue-preview-code"><?php echo esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ); ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['story_path'] ); ?></span>/my-story-title/<span class="fanfic-dynamic-slug">epilogue</span>/</code>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="fanfic-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php echo esc_html__( 'All chapter type slugs must be unique. Numbers will be automatically appended to chapter URLs (e.g., chapter-1, chapter-2).', 'fanfiction-manager' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 5: REDIRECT INFORMATION -->
                <!-- ============================================ -->
                <?php if ( ! $in_wizard ) : ?>
                <div class="fanfic-config-section fanfic-section-redirects">
                    <div class="fanfic-section-header">
                        <h2><?php echo esc_html__( 'Active URL Redirects', 'fanfiction-manager' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'When you change URL slugs, old URLs automatically redirect to new ones for 90 days to maintain SEO and prevent broken links.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <?php
                        if ( class_exists( 'Fanfic_Redirects' ) ) {
                            $redirect_count = Fanfic_Redirects::get_redirect_count();
                            if ( $redirect_count > 0 ) {
                                $redirects = Fanfic_Redirects::get_redirect_info();
                                ?>
                                <div class="fanfic-redirect-status">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php echo esc_html( sprintf( _n( '%d active redirect', '%d active redirects', $redirect_count, 'fanfiction-manager' ), $redirect_count ) ); ?></strong>
                                </div>
                                <table class="wp-list-table widefat fixed striped fanfic-redirects-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Old Slug', 'fanfiction-manager' ); ?></th>
                                            <th><?php esc_html_e( 'New Slug', 'fanfiction-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Created', 'fanfiction-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Expires', 'fanfiction-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $redirects as $redirect ) :
                                            $delete_url = wp_nonce_url(
                                                admin_url( 'admin-post.php?action=fanfic_delete_redirect&old_slug=' . urlencode( $redirect['old_slug'] ) ),
                                                'fanfic_delete_redirect'
                                            );
                                        ?>
                                            <tr>
                                                <td><code><?php echo esc_html( $redirect['old_slug'] ); ?></code></td>
                                                <td><code><?php echo esc_html( $redirect['new_slug'] ); ?></code></td>
                                                <td><?php echo esc_html( $redirect['created'] ); ?></td>
                                                <td><?php echo esc_html( $redirect['expires'] ); ?></td>
                                                <td>
                                                    <a href="<?php echo esc_url( $delete_url ); ?>"
                                                       class="button button-small delete-redirect-btn"
                                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this redirect? Old URLs will no longer redirect to new ones.', 'fanfiction-manager' ) ); ?>');">
                                                        <?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php
                            } else {
                                ?>
                                <div class="fanfic-no-redirects">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <p><?php esc_html_e( 'No active redirects. Redirects will appear here when you change URL slugs.', 'fanfiction-manager' ); ?></p>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="fanfic-no-redirects">
                                <span class="dashicons dashicons-warning"></span>
                                <p><?php esc_html_e( 'Redirect tracking is not available.', 'fanfiction-manager' ); ?></p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <?php endif; // End redirects section ?>

                <!-- Submit Button -->
                <?php if ( ! $in_wizard ) : ?>
                <div class="fanfic-submit-wrapper">
                    <?php submit_button( __( 'Save All URL Settings', 'fanfiction-manager' ), 'primary large', 'submit_url_config' ); ?>
                    <p class="description">
                        <?php echo esc_html__( 'Changes take effect immediately. Old URLs will redirect automatically.', 'fanfiction-manager' ); ?>
                    </p>
                </div>
                <?php endif; ?>
        <?php
    }

    /**
     * Render the CSS styles for URL configuration
     *
     * @since 1.0.0
     * @return void
     */
    public static function render_styles() {
        ?>
        <style>
            .fanfic-url-config-wrap {
                max-width: 1200px;
            }

            /* Section Styling */
            .fanfic-config-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                margin-bottom: 25px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }

            .fanfic-section-header {
                background: #f6f7f7;
                border-bottom: 1px solid #c3c4c7;
                padding: 20px 25px;
            }

            .fanfic-section-header h2 {
                margin: 0 0 8px 0;
                font-size: 18px;
                font-weight: 600;
            }

            .fanfic-section-header .description {
                margin: 0;
                color: #646970;
            }

            .fanfic-section-content {
                padding: 25px;
            }

            /* Main Section - Radio Cards */
            .fanfic-section-main .fanfic-section-header {
                background: #2271b1;
                border-bottom: 1px solid #135e96;
            }

            .fanfic-section-main .fanfic-section-header h2,
            .fanfic-section-main .fanfic-section-header .description {
                color: #fff;
            }

            .fanfic-radio-cards {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            .fanfic-radio-card {
                position: relative;
                display: block;
                padding: 20px;
                border: 2px solid #c3c4c7;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                background: #fff;
            }

            .fanfic-radio-card:hover {
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
            }

            .fanfic-radio-card.selected {
                border-color: #2271b1;
                background: #f0f6fc;
                box-shadow: 0 0 0 1px #2271b1;
            }

            .fanfic-radio-card input[type="radio"] {
                position: absolute;
                top: 15px;
                right: 15px;
                width: 20px;
                height: 20px;
                margin: 0;
            }

            .fanfic-radio-card-content {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .fanfic-radio-card-content strong {
                font-size: 15px;
                color: #1d2327;
            }

            .fanfic-radio-card-desc {
                font-size: 13px;
                color: #646970;
            }

            .fanfic-url-example-small {
                display: inline-block;
                padding: 6px 10px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 12px;
                color: #2271b1;
                margin-top: 4px;
            }

            .fanfic-url-example-small .highlight {
                background: #fef7e0;
                padding: 2px 4px;
                border-radius: 2px;
            }

            /* Required Indicator */
            .fanfic-required {
                color: #d63638;
                font-weight: bold;
            }

            /* TODO Badge */
            .fanfic-todo-row {
                opacity: 0.6;
            }

            .fanfic-todo-badge {
                display: inline-block;
                background: #fcf9e8;
                color: #997404;
                border: 1px solid #f0e9c5;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                margin-left: 8px;
            }

            /* Fieldsets */
            .fanfic-fieldset {
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                background: #f9f9f9;
            }

            .fanfic-fieldset legend {
                font-weight: 600;
                font-size: 14px;
                padding: 0 10px;
            }

            /* URL Preview Box */
            .fanfic-url-preview-box {
                background: #f0f6fc;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
            }

            .fanfic-url-preview-box h4 {
                margin: 0 0 15px 0;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
            }

            .fanfic-url-preview-grid {
                display: grid;
                gap: 12px;
            }

            .fanfic-url-preview-item {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .fanfic-url-label {
                font-weight: 600;
                font-size: 13px;
                color: #646970;
                min-width: 80px;
            }

            .fanfic-url-preview-item code {
                background: #fff;
                padding: 8px 12px;
                border-radius: 3px;
                font-size: 13px;
                flex: 1;
                border: 1px solid #c3c4c7;
            }

            /* Dynamic Slug Highlighting */
            .fanfic-dynamic-slug {
                background: #fef7e0;
                padding: 2px 4px;
                border-radius: 2px;
                font-weight: 600;
            }

            /* Info Box */
            .fanfic-info-box {
                display: flex;
                gap: 12px;
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 15px;
                margin-top: 20px;
                align-items: flex-start;
            }

            .fanfic-info-box .dashicons {
                color: #2271b1;
                flex-shrink: 0;
                margin-top: 2px;
            }

            .fanfic-info-box p {
                margin: 0;
                font-size: 13px;
                color: #1d2327;
            }

            /* Slug Input Validation */
            .fanfic-slug-validation {
                margin-top: 8px;
                font-size: 13px;
            }

            .fanfic-slug-validation.error {
                color: #d63638;
            }

            .fanfic-slug-validation.success {
                color: #00a32a;
            }

            /* Redirects Section */
            .fanfic-section-redirects .fanfic-section-header {
                background: #f6f7f7;
            }

            .fanfic-redirect-status {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #dff0d8;
                border: 1px solid #d0e9c6;
                padding: 12px 15px;
                border-radius: 4px;
                margin-bottom: 15px;
            }

            .fanfic-redirect-status .dashicons {
                color: #00a32a;
            }

            .fanfic-no-redirects {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 20px;
                background: #f9f9f9;
                border: 1px dashed #c3c4c7;
                border-radius: 4px;
            }

            .fanfic-no-redirects .dashicons {
                color: #646970;
                font-size: 24px;
                width: 24px;
                height: 24px;
            }

            .fanfic-no-redirects p {
                margin: 0;
                color: #646970;
            }

            .fanfic-redirects-table {
                margin-top: 0;
            }

            .fanfic-redirects-table code {
                background: #f0f0f1;
                padding: 4px 8px;
                border-radius: 3px;
            }

            .delete-redirect-btn {
                color: #b32d2e;
                border-color: #b32d2e;
            }

            .delete-redirect-btn:hover {
                color: #fff;
                background: #b32d2e;
                border-color: #b32d2e;
            }

            /* Submit Wrapper */
            .fanfic-submit-wrapper {
                background: #f6f7f7;
                border-top: 1px solid #c3c4c7;
                padding: 20px 25px;
                margin: 0 -25px -25px -25px;
                border-radius: 0 0 4px 4px;
            }

            .fanfic-submit-wrapper .button-primary {
                height: auto;
                padding: 12px 24px;
                font-size: 14px;
            }

            .fanfic-submit-wrapper .description {
                margin: 10px 0 0 0;
                font-size: 13px;
            }

            /* Responsive */
            @media (max-width: 782px) {
                .fanfic-radio-cards {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * Render the JavaScript for URL configuration
     *
     * @since 1.0.0
     * @return void
     */
    public static function render_scripts() {
        $preview_config = Fanfic_URL_Schema::get_js_preview_config();
        $current_slugs = self::get_current_slugs();
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var homeUrl = '<?php echo esc_js( home_url( '/' ) ); ?>';
            var previewConfig = <?php echo wp_json_encode( $preview_config ); ?>;
            var currentSlugs = <?php echo wp_json_encode( $current_slugs ); ?>;

            // Radio card selection visual feedback
            $('.fanfic-radio-card input[type="radio"]').on('change', function() {
                $('.fanfic-radio-card').removeClass('selected');
                $(this).closest('.fanfic-radio-card').addClass('selected');
            });

            /**
             * Get slug value from input or current value
             * All keys use underscores consistently
             */
            function getSlugValue(key) {
                var $input;

                // Try different ID patterns in order of likelihood
                var patterns = [
                    '#fanfic_' + key + '_slug',  // Most common: base_slug, dashboard_slug, etc.
                    '#fanfic_' + key,            // Special cases: story_path (no _slug suffix)
                    '#page_slug_' + key          // System pages: page_slug_login, etc.
                ];

                for (var i = 0; i < patterns.length; i++) {
                    $input = $(patterns[i]);
                    if ($input.length) {
                        var value = $input.val();
                        if (value && value.trim()) {
                            return value.trim();
                        }
                    }
                }

                // Fallback to current slug value or key
                return currentSlugs[key] || key;
            }

            /**
             * Build preview URL from template
             */
            function buildPreviewUrl(template) {
                var url = template.replace('{home}', homeUrl);

                // Replace all slug placeholders
                $.each(currentSlugs, function(key, value) {
                    var actualValue = getSlugValue(key);
                    url = url.replace('{' + key + '}', '<span class="fanfic-dynamic-slug">' + actualValue + '</span>');
                });

                return url;
            }

            /**
             * Update all URL previews
             */
            function updatePreviews() {
                // Update main slug previews using config
                $.each(previewConfig, function(key, config) {
                    var previewId = '#' + key + '-preview-code';
                    if ($(previewId).length) {
                        var previewUrl = buildPreviewUrl(config.template);
                        $(previewId).html(previewUrl);
                    }
                });

                // Update system page slugs dynamically
                $('input[data-slug-type^="system_"]').each(function() {
                    var $input = $(this);
                    var slugValue = $input.val().trim() || 'slug';
                    var systemKey = $input.data('slug-type').replace('system_', '');
                    var previewId = '#system-' + systemKey + '-preview-code';
                    var baseSlug = getSlugValue('base');

                    $(previewId).html(homeUrl + '<span class="fanfic-dynamic-slug">' + baseSlug + '</span>/' +
                        '<span class="fanfic-dynamic-slug">' + slugValue + '</span>/');
                });
            }

            /**
             * Validate a slug input
             */
            function validateSlug($input) {
                var slug = $input.val().trim();
                var validationDiv = $input.siblings('.fanfic-slug-validation');

                if (!validationDiv.length) {
                    validationDiv = $('<div class="fanfic-slug-validation"></div>');
                    $input.parent().append(validationDiv);
                }

                // Check length
                if (slug.length > 50) {
                    validationDiv.removeClass('success').addClass('error')
                        .text('<?php echo esc_js( __( 'Too long! Maximum 50 characters.', 'fanfiction-manager' ) ); ?>');
                    return false;
                }

                // Check format
                if (!/^[a-z0-9\-]+$/.test(slug)) {
                    validationDiv.removeClass('success').addClass('error')
                        .text('<?php echo esc_js( __( 'Only lowercase letters, numbers, and hyphens allowed.', 'fanfiction-manager' ) ); ?>');
                    return false;
                }

                // Check if empty
                if (slug.length === 0) {
                    validationDiv.removeClass('success error').text('');
                    return false;
                }

                validationDiv.removeClass('error').addClass('success')
                    .text('✓ <?php echo esc_js( __( 'Valid slug', 'fanfiction-manager' ) ); ?>');
                return true;
            }

            // Attach event handlers
            $('.fanfic-slug-input').on('input', function() {
                updatePreviews();
                validateSlug($(this));
            });

            // Form submission validation
            $('#fanfic-url-config-form, #fanfic-wizard-form-step-2').on('submit', function(e) {
                var isValid = true;

                $('.fanfic-slug-input').each(function() {
                    if (!validateSlug($(this))) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('<?php echo esc_js( __( 'Please fix validation errors before saving.', 'fanfiction-manager' ) ); ?>');
                }
            });

            // Initial update on page load
            updatePreviews();
        });
        </script>
        <?php
    }

    /**
     * Render the URL configuration page
     *
     * Outputs the complete admin page with all configuration sections.
     *
     * @since 1.0.0
     * @return void
     */
    public static function render() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Denied', array( 'response' => 403 ) );
        }

        ?>
        <div class="wrap fanfic-url-config-wrap">
            <h1><?php echo esc_html__( 'URL Configuration', 'fanfiction-manager' ); ?></h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">
                <?php echo esc_html__( 'Configure how your fanfiction site URLs are structured. Changes are applied immediately and old URLs will redirect automatically.', 'fanfiction-manager' ); ?>
            </p>

            <?php self::display_notices(); ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-url-config-form">
                <?php self::render_form_fields( false ); ?>
            </form>
        </div>

        <?php
        self::render_styles();
        self::render_scripts();
    }


    /**
     * Save a single slug field with validation and redirect tracking
     *
     * Helper method to reduce code duplication in save_url_config().
     *
     * @since 1.0.0
     * @param string $field_name POST field name.
     * @param string $slug_key Slug configuration key.
     * @param string $option_key WordPress option key.
     * @param string $label Display label for error messages.
     * @param bool   $track_redirect Whether to track old->new redirect.
     * @return array|null Array with 'success' or 'error' key, or null if field not set.
     */
    private function save_slug_field( $field_name, $slug_key, $option_key, $label, $track_redirect = true ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return null;
        }

        $new_slug = sanitize_title( wp_unslash( $_POST[ $field_name ] ) );
        $validation = $this->validate_slug( $new_slug, array( $slug_key ) );

        if ( is_wp_error( $validation ) ) {
            return array( 'error' => $label . ': ' . $validation->get_error_message() );
        }

        $old_slug = get_option( $option_key, '' );
        if ( $old_slug !== $new_slug && $track_redirect && class_exists( 'Fanfic_Slug_Tracker' ) ) {
            Fanfic_Slug_Tracker::add_manual_redirect( $old_slug, $new_slug );
        }

        update_option( $option_key, $new_slug );
        return array( 'success' => $label . ' saved.' );
    }

    /**
     * Save all URL configuration settings
     *
     * Refactored to use schema-driven approach for reduced code duplication.
     *
     * @since 1.0.0
     * @return void
     */
    public function save_url_config() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Denied', array( 'response' => 403 ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['fanfic_url_config_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_url_config_nonce'] ) ), 'fanfic_save_url_config' ) ) {
            wp_die( 'Security check failed.', 'Security Error', array( 'response' => 403 ) );
        }

        $errors = array();
        $success_messages = array();
        $slug_config = Fanfic_URL_Schema::get_slug_config();

        // 1. Save main page mode
        if ( isset( $_POST['fanfic_main_page_mode'] ) ) {
            $main_page_mode = sanitize_text_field( wp_unslash( $_POST['fanfic_main_page_mode'] ) );
            if ( in_array( $main_page_mode, array( 'stories_homepage', 'custom_homepage' ), true ) ) {
                update_option( 'fanfic_main_page_mode', $main_page_mode );
                $success_messages[] = __( 'Site organization mode saved.', 'fanfiction-manager' );
            }
        }

        // 2. Save base slug (with special handling for main page)
        if ( isset( $_POST['fanfic_base_slug'] ) ) {
            $new_base_slug = sanitize_title( wp_unslash( $_POST['fanfic_base_slug'] ) );
            $validation = $this->validate_slug( $new_base_slug, array( 'base' ) );

            if ( is_wp_error( $validation ) ) {
                $errors[] = __( 'Base Slug', 'fanfiction-manager' ) . ': ' . $validation->get_error_message();
            } else {
                $old_base_slug = get_option( self::OPTION_BASE_SLUG, 'fanfiction' );
                if ( $old_base_slug !== $new_base_slug ) {
                    update_option( self::OPTION_OLD_BASE_SLUG, $old_base_slug );
                    if ( class_exists( 'Fanfic_Slug_Tracker' ) ) {
                        Fanfic_Slug_Tracker::add_manual_redirect( $old_base_slug, $new_base_slug );
                    }
                }
                update_option( self::OPTION_BASE_SLUG, $new_base_slug );

                // Update main page slug to match base slug
                $page_ids = get_option( 'fanfic_system_page_ids', array() );
                if ( isset( $page_ids['main'] ) && $page_ids['main'] > 0 ) {
                    wp_update_post( array(
                        'ID'        => $page_ids['main'],
                        'post_name' => $new_base_slug,
                    ) );
                }

                $success_messages[] = __( 'Base slug saved.', 'fanfiction-manager' );
            }
        }

        // 3. Save story path
        if ( isset( $_POST['fanfic_story_path'] ) ) {
            $result = $this->save_slug_field( 'fanfic_story_path', 'story_path', self::OPTION_STORY_PATH, __( 'Stories slug', 'fanfiction-manager' ) );
            if ( $result ) {
                if ( isset( $result['error'] ) ) {
                    $errors[] = $result['error'];
                } elseif ( isset( $result['success'] ) ) {
                    $success_messages[] = $result['success'];
                }
            }
        }

        // 4. Save secondary paths (dashboard, user, search) - grouped
        $secondary_slugs_input = array();
        $secondary_config = Fanfic_URL_Schema::get_slugs_by_group( 'secondary' );

        foreach ( $secondary_config as $key => $config ) {
            $field_name = 'fanfic_' . $key . '_slug';
            if ( isset( $_POST[ $field_name ] ) ) {
                $secondary_slugs_input[ $key ] = sanitize_title( wp_unslash( $_POST[ $field_name ] ) );
            }
        }

        // Check for duplicates
        if ( Fanfic_URL_Schema::has_duplicates( $secondary_slugs_input ) ) {
            $errors[] = __( 'User & System URLs must be unique from each other.', 'fanfiction-manager' );
        } else {
            // Validate and save each
            $all_valid = true;
            foreach ( $secondary_slugs_input as $key => $slug ) {
                $validation = $this->validate_slug( $slug, array( $key ) );
                if ( is_wp_error( $validation ) ) {
                    $errors[] = ucfirst( $key ) . ': ' . $validation->get_error_message();
                    $all_valid = false;
                }
            }

            if ( $all_valid ) {
                update_option( self::OPTION_SECONDARY_PATHS, $secondary_slugs_input );

                // Update dynamic pages if applicable
                if ( class_exists( 'Fanfic_URL_Manager' ) ) {
                    $dynamic_updates = array();
                    foreach ( $secondary_config as $key => $config ) {
                        if ( isset( $config['is_dynamic_page'] ) && $config['is_dynamic_page'] && ! empty( $secondary_slugs_input[ $key ] ) ) {
                            $dynamic_updates[ $key ] = $secondary_slugs_input[ $key ];
                        }
                    }
                    if ( ! empty( $dynamic_updates ) ) {
                        $current_dynamic = Fanfic_URL_Manager::get_instance()->get_slugs();
                        Fanfic_URL_Manager::get_instance()->update_slugs( array_merge( $current_dynamic, $dynamic_updates ) );
                    }
                }

                $success_messages[] = __( 'User & system URLs saved.', 'fanfiction-manager' );
            }
        }

        // 5. Save chapter slugs (prologue, chapter, epilogue) - grouped
        $chapter_slugs_input = array();
        $chapter_config = Fanfic_URL_Schema::get_slugs_by_group( 'chapters' );

        foreach ( $chapter_config as $key => $config ) {
            $field_name = 'fanfic_' . $key . '_slug';
            if ( isset( $_POST[ $field_name ] ) ) {
                $chapter_slugs_input[ $key ] = sanitize_title( wp_unslash( $_POST[ $field_name ] ) );
            }
        }

        // Check for duplicates
        if ( Fanfic_URL_Schema::has_duplicates( $chapter_slugs_input ) ) {
            $errors[] = __( 'Chapter type slugs must be unique from each other.', 'fanfiction-manager' );
        } else {
            // Validate and save each
            $all_valid = true;
            foreach ( $chapter_slugs_input as $key => $slug ) {
                $validation = $this->validate_slug( $slug, array( $key ) );
                if ( is_wp_error( $validation ) ) {
                    $errors[] = ucfirst( $key ) . ': ' . $validation->get_error_message();
                    $all_valid = false;
                }
            }

            if ( $all_valid ) {
                update_option( self::OPTION_CHAPTER_SLUGS, $chapter_slugs_input );
                $success_messages[] = __( 'Chapter URLs saved.', 'fanfiction-manager' );
            }
        }

        // 6. Save system page slugs
        if ( isset( $_POST['fanfic_system_page_slugs'] ) && is_array( $_POST['fanfic_system_page_slugs'] ) ) {
            $old_slugs = get_option( 'fanfic_system_page_slugs', array() );
            $page_slugs = array();
            $dynamic_page_slugs = array();

            if ( class_exists( 'Fanfic_URL_Manager' ) ) {
                $dynamic_pages = Fanfic_URL_Manager::get_instance()->get_dynamic_pages();
                $current_dynamic_slugs = Fanfic_URL_Manager::get_instance()->get_slugs();
            } else {
                $dynamic_pages = array();
                $current_dynamic_slugs = array();
            }

            foreach ( $_POST['fanfic_system_page_slugs'] as $key => $slug ) {
                $slug = sanitize_title( wp_unslash( $slug ) );
                if ( ! empty( $slug ) ) {
                    // Track redirect if changed
                    if ( isset( $old_slugs[ $key ] ) && $old_slugs[ $key ] !== $slug && class_exists( 'Fanfic_Slug_Tracker' ) ) {
                        Fanfic_Slug_Tracker::add_manual_redirect( $old_slugs[ $key ], $slug );
                    }
                    $page_slugs[ $key ] = $slug;

                    // Handle dynamic pages
                    if ( in_array( $key, $dynamic_pages, true ) ) {
                        // Skip dashboard/search if already set from secondary paths
                        if ( ( $key === 'dashboard' || $key === 'search' ) && ! empty( $current_dynamic_slugs[ $key ] ) && $current_dynamic_slugs[ $key ] !== $slug ) {
                            continue;
                        }
                        $dynamic_page_slugs[ $key ] = $slug;
                    }
                }
            }

            update_option( 'fanfic_system_page_slugs', $page_slugs );

            // Update dynamic pages
            if ( ! empty( $dynamic_page_slugs ) && class_exists( 'Fanfic_URL_Manager' ) ) {
                Fanfic_URL_Manager::get_instance()->update_slugs( array_merge( $current_dynamic_slugs, $dynamic_page_slugs ) );
            }

            // Recreate system pages with new slugs
            if ( class_exists( 'Fanfic_Templates' ) ) {
                $base_slug = get_option( self::OPTION_BASE_SLUG, 'fanfiction' );
                Fanfic_Templates::create_system_pages( $base_slug );
            }

            $success_messages[] = __( 'System page slugs saved.', 'fanfiction-manager' );
        }

        // Finalize: Flush rewrite rules if changes were made
        if ( ! empty( $success_messages ) ) {
            $this->flush_all_rewrite_rules();
        }

        // Set transient messages
        if ( ! empty( $errors ) ) {
            set_transient( 'fanfic_url_config_error', implode( '<br>', $errors ), 30 );
        }
        if ( ! empty( $success_messages ) ) {
            set_transient( 'fanfic_url_config_success', implode( ' ', $success_messages ), 30 );
        }

        // Redirect back
        wp_safe_redirect( add_query_arg( 'page', 'fanfiction-url-rules', admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Flush all rewrite rules
     *
     * Helper method to register and flush all rewrite rules.
     *
     * @since 1.0.0
     * @return void
     */
    private function flush_all_rewrite_rules() {
        // Flush URL Manager cache (reloads all slugs)
        if ( class_exists( 'Fanfic_URL_Manager' ) ) {
            Fanfic_URL_Manager::get_instance()->flush_cache();
        }

        // Register all rewrite rules before flushing
        if ( class_exists( 'Fanfic_Post_Types' ) ) {
            Fanfic_Post_Types::register_post_types();
        }
        if ( class_exists( 'Fanfic_Taxonomies' ) ) {
            Fanfic_Taxonomies::register_taxonomies();
        }
        if ( class_exists( 'Fanfic_URL_Manager' ) ) {
            Fanfic_URL_Manager::get_instance()->register_rewrite_rules();
        }

        flush_rewrite_rules();
        set_transient( 'fanfic_flush_rewrite_rules', 1, 60 );
    }

    /**
     * Delete a specific redirect
     *
     * Handles the deletion of a single redirect mapping.
     *
     * @since 1.0.0
     * @return void
     */
    public function delete_redirect() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Denied', array( 'response' => 403 ) );
        }

        // Verify nonce
        $nonce_verified = false;

        if ( isset( $_GET['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            $nonce_verified = wp_verify_nonce( $nonce, 'fanfic_delete_redirect' );
        }

        if ( ! $nonce_verified ) {
            wp_die( 'Security check failed.', 'Security Error', array( 'response' => 403 ) );
        }

        // Get the old slug to delete
        if ( ! isset( $_GET['old_slug'] ) ) {
            wp_die( 'Missing redirect identifier.', 'Invalid Request', array( 'response' => 400 ) );
        }

        $old_slug = sanitize_text_field( wp_unslash( $_GET['old_slug'] ) );

        // Delete the redirect
        if ( class_exists( 'Fanfic_Slug_Tracker' ) ) {
            $deleted = Fanfic_Slug_Tracker::delete_redirect( $old_slug );

            if ( $deleted ) {
                set_transient( 'fanfic_url_config_success', 'Redirect deleted successfully.', 30 );
            } else {
                set_transient( 'fanfic_url_config_error', 'Failed to delete redirect.', 30 );
            }
        }

        // Redirect back
        wp_safe_redirect( add_query_arg( 'page', 'fanfiction-url-rules', admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Validate a slug
     *
     * Wrapper method that delegates to Fanfic_URL_Schema.
     *
     * @since 1.0.0
     * @param string $slug The slug to validate.
     * @param array  $exclude Array of slug types to exclude from uniqueness check.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_slug( $slug, $exclude = array() ) {
        return Fanfic_URL_Schema::validate_slug( $slug, $exclude );
    }

    /**
     * Get all current slug settings
     *
     * Wrapper method that delegates to Fanfic_URL_Schema.
     *
     * @since 1.0.0
     * @return array Array of current slug settings.
     */
    public static function get_current_slugs() {
        return Fanfic_URL_Schema::get_current_slugs();
    }

    /**
     * Display admin notices
     *
     * Shows success, error, or warning messages based on transients set
     * during save operations.
     *
     * @since 1.0.0
     * @return void
     */
    public static function display_notices() {
        // Display success message
        $success = get_transient( 'fanfic_url_config_success' );
        if ( $success ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo wp_kses_post( $success ); ?></p>
            </div>
            <?php
            delete_transient( 'fanfic_url_config_success' );
        }

        // Display error message
        $error = get_transient( 'fanfic_url_config_error' );
        if ( $error ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo wp_kses_post( $error ); ?></p>
            </div>
            <?php
            delete_transient( 'fanfic_url_config_error' );
        }
    }

    /**
     * Handle 301 redirects for old base slug
     *
     * Checks if the current URL uses an old base slug and redirects to the
     * new slug with a 301 status code to maintain SEO.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_301_redirects() {
        $old_base_slug     = get_option( self::OPTION_OLD_BASE_SLUG );
        $current_base_slug = get_option( self::OPTION_BASE_SLUG, 'fanfiction' );

        // If no old slug or it's the same as current, nothing to do
        if ( empty( $old_base_slug ) || $old_base_slug === $current_base_slug ) {
            return;
        }

        // Get the current request URI
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        // Check if the request starts with the old base slug
        $old_pattern = '/' . trim( $old_base_slug, '/' ) . '/';
        if ( strpos( $request_uri, $old_pattern ) === 1 ) { // Position 1 because of leading slash
            // Replace old slug with new slug
            $new_uri = str_replace( $old_pattern, '/' . trim( $current_base_slug, '/' ) . '/', $request_uri );
            $new_url = home_url( $new_uri );

            // Perform 301 redirect
            wp_safe_redirect( $new_url, 301 );
            exit;
        }
    }

} // Class closing brace - THIS WAS MISSING