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
     * Option name for storing dashboard slug
     *
     * @var string
     */
    const OPTION_DASHBOARD_SLUG = 'fanfic_dashboard_slug';

    /**
     * Option name for storing members slug
     *
     * @var string
     */
    const OPTION_MEMBERS_SLUG = 'fanfic_members_slug';

    /**
     * Option name for storing search slug
     *
     * @var string
     */

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
        add_action( 'admin_post_fanfic_switch_url_mode', array( self::$instance, 'switch_url_mode' ) );
        add_action( 'template_redirect', array( self::$instance, 'handle_301_redirects' ) );
    }

    /**
     * Helper method to render a slug input field row
     *
     * @since 1.0.0
     * @param array $args {
     *     Input field arguments
     *     @type string $id             Input field ID
     *     @type string $name           Input field name attribute
     *     @type string $label          Label text
     *     @type string $value          Current value
     *     @type string $preview_id     Preview code element ID
     *     @type string $preview_html   Preview HTML content
     *     @type bool   $required       Whether field is required (default: true)
     *     @type string $data_slug_type Data attribute for slug type
     *     @type string $description    Optional description text (default: '')
     * }
     * @return void
     */
    private static function render_slug_input_row( $args ) {
        $defaults = array(
            'id'             => '',
            'name'           => '',
            'label'          => '',
            'value'          => '',
            'preview_id'     => '',
            'preview_html'   => '',
            'required'       => true,
            'data_slug_type' => '',
            'description'    => '',
        );

        $args = wp_parse_args( $args, $defaults );
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr( $args['id'] ); ?>">
                    <?php echo esc_html( $args['label'] ); ?>
                    <?php if ( $args['required'] ) : ?>
                        <span class="fanfic-required">*</span>
                    <?php endif; ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="<?php echo esc_attr( $args['id'] ); ?>"
                    name="<?php echo esc_attr( $args['name'] ); ?>"
                    value="<?php echo esc_attr( $args['value'] ); ?>"
                    class="regular-text fanfic-slug-input"
                    pattern="[a-z0-9\-]+"
                    maxlength="50"
                    <?php echo $args['required'] ? 'required' : ''; ?>
                    data-slug-type="<?php echo esc_attr( $args['data_slug_type'] ); ?>"
                >
                <?php if ( ! empty( $args['description'] ) ) : ?>
                    <p class="description">
                        <?php echo esc_html( $args['description'] ); ?>
                    </p>
                <?php endif; ?>
                <p class="description">
                    <code id="<?php echo esc_attr( $args['preview_id'] ); ?>"><?php echo wp_kses_post( $args['preview_html'] ); ?></code>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render just the form fields (reusable in wizard and settings page)
     *
     * @since 1.0.0
     * @param bool $in_wizard Whether this is being rendered in the wizard context.
     * @return void
     */
    public static function render_form_fields( $in_wizard = false, $wizard_control_base_slug = false ) {
        $current_slugs      = self::get_current_slugs();
        $page_slugs         = get_option( 'fanfic_system_page_ids', array() ); // Corrected to fetch system page IDs
        $use_base_slug      = get_option( 'fanfic_use_base_slug', true );
		$members_slug  = get_option( self::OPTION_MEMBERS_SLUG, 'members' );


        // Determine nonce and action based on context
        if ( $in_wizard ) {
            wp_nonce_field( 'fanfic_wizard_step_2', 'fanfic_wizard_nonce_step_2' );
        } else {
            wp_nonce_field( 'fanfic_save_url_config', 'fanfic_url_config_nonce' );
            echo '<input type="hidden" name="action" value="fanfic_save_url_config">';
        }

        // Determine base URL path for previews
        $base_url_for_preview = esc_html( home_url( '/' ) );
        if ( $use_base_slug ) {
            $base_url_for_preview .= esc_html( $current_slugs['base'] ) . '/';
        }
        ?>

                <!-- ============================================ -->
                <!-- SECTION 2: PRIMARY URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h3><?php echo esc_html__( 'Primary URLs', 'fanfiction-manager' ); ?></h3>
                        <p class="description">
                            <?php echo esc_html__( 'These are the main URL slugs that define your site structure. They appear in all story and archive URLs.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php if ( ! $wizard_control_base_slug ) : ?>
                                    <?php if ( $use_base_slug ) : ?>
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
                                    <?php else : ?>
                                    <!-- Hidden input to maintain empty base slug -->
                                    <input type="hidden" name="fanfic_base_slug" value="">
                                    <?php endif; ?>
                                <?php endif; // End if ( ! $wizard_control_base_slug ) ?>

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
                                            id="fanfic_story_path_slug"
                                            name="fanfic_story_path"
                                            value="<?php echo esc_attr( $current_slugs['story_path'] ); ?>"
                                            class="regular-text fanfic-slug-input"
                                            pattern="[a-z0-9\-]+"
                                            maxlength="50"
                                            required
                                            data-slug-type="story_path"
                                        >
                                        <p class="description">
                                            <?php esc_html_e( 'The subdirectory where individual stories are placed. Used in all story URLs.', 'fanfiction-manager' ); ?>
                                        </p>
                                        <p class="description">
                                            <code id="story-path-preview-code"><?php echo $base_url_for_preview; ?><span class="fanfic-dynamic-slug"><?php echo esc_html( $current_slugs['story_path'] ); ?></span>/my-story-title/</code>
                                        </p>
                                        <div class="fanfic-slug-validation" id="story-path-validation"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 3: CHAPTER URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h3><?php echo esc_html__( 'Chapter URLs', 'fanfiction-manager' ); ?></h3>
                        <p class="description">
                            <?php echo esc_html__( 'Define URL slugs for different chapter types. These must be unique from each other.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php
                                // Prologue
                                self::render_slug_input_row( array(
                                    'id'             => 'fanfic_prologue_slug',
                                    'name'           => 'fanfic_prologue_slug',
                                    'label'          => __( 'Prologue', 'fanfiction-manager' ),
                                    'value'          => isset( $current_slugs['prologue'] ) ? $current_slugs['prologue'] : 'prologue',
                                    'preview_id'     => 'prologue-preview-code',
                                    'preview_html'   => esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ) . '<span class="fanfic-dynamic-slug">' . esc_html( $current_slugs['story_path'] ) . '</span>/my-story-title/<span class="fanfic-dynamic-slug">prologue</span>/',
                                    'required'       => true,
                                    'data_slug_type' => 'prologue',
                                ) );

                                // Chapter
                                self::render_slug_input_row( array(
                                    'id'             => 'fanfic_chapter_slug',
                                    'name'           => 'fanfic_chapter_slug',
                                    'label'          => __( 'Chapter', 'fanfiction-manager' ),
                                    'value'          => isset( $current_slugs['chapter'] ) ? $current_slugs['chapter'] : 'chapter',
                                    'preview_id'     => 'chapter-preview-code',
                                    'preview_html'   => esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ) . '<span class="fanfic-dynamic-slug">' . esc_html( $current_slugs['story_path'] ) . '</span>/my-story-title/<span class="fanfic-dynamic-slug">chapter</span>-1/',
                                    'required'       => true,
                                    'data_slug_type' => 'chapter',
                                ) );

                                // Epilogue
                                self::render_slug_input_row( array(
                                    'id'             => 'fanfic_epilogue_slug',
                                    'name'           => 'fanfic_epilogue_slug',
                                    'label'          => __( 'Epilogue', 'fanfiction-manager' ),
                                    'value'          => isset( $current_slugs['epilogue'] ) ? $current_slugs['epilogue'] : 'epilogue',
                                    'preview_id'     => 'epilogue-preview-code',
                                    'preview_html'   => esc_html( home_url( '/' . $current_slugs['base'] . '/' ) ) . '<span class="fanfic-dynamic-slug">' . esc_html( $current_slugs['story_path'] ) . '</span>/my-story-title/<span class="fanfic-dynamic-slug">epilogue</span>/',
                                    'required'       => true,
                                    'data_slug_type' => 'epilogue',
                                ) );
                                ?>
                            </tbody>
                        </table>

                        <div class="fanfic-info-box box-warning">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php echo esc_html__( 'All chapter type slugs must be unique. Numbers will be automatically appended to chapter URLs (e.g., chapter-1, chapter-2).', 'fanfiction-manager' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 4: USER & SYSTEM URLs -->
                <!-- ============================================ -->
                <div class="fanfic-config-section">
                    <div class="fanfic-section-header">
                        <h3><?php echo esc_html__( 'User & System URLs', 'fanfiction-manager' ); ?></h3>
                        <p class="description">
                            <?php echo esc_html__( 'Configure URLs for user profiles, dashboard, and system pages.', 'fanfiction-manager' ); ?>
                        </p>
                    </div>

                    <div class="fanfic-section-content">
                        <table class="form-table" role="presentation">
                            <tbody>
                         <?php
$base_url_path = $use_base_slug ? '/' . $current_slugs['base'] . '/' : '/';
$base_url = esc_url( home_url( $base_url_path ) );

$definitions = array(
	'dashboard' => array(
		'label'    => __( 'Dashboard', 'fanfiction-manager' ),
		'default'  => 'dashboard',
		'required' => true,
	),
	'members' => array(
		'label'       => __( 'Members directory', 'fanfiction-manager' ),
		'default'     => $members_slug,
		'required'    => true,
		'suffix'      => '/username/',
		'description' => __( 'Handles URLs for author profiles', 'fanfiction-manager' ),
	),
	'login' => array(
		'label'   => __( 'Login', 'fanfiction-manager' ),
		'default' => 'login',
	),
	'search' => array(
		'label'   => __( 'Browse Page', 'fanfiction-manager' ),
		'default' => 'browse',
	),
	'register' => array(
		'label'   => __( 'Register', 'fanfiction-manager' ),
		'default' => 'register',
	),
	'password_reset' => array(
		'label'   => __( 'Password Reset', 'fanfiction-manager' ),
		'default' => 'password-reset',
	),
	'error' => array(
		'label'   => __( 'Error Page', 'fanfiction-manager' ),
		'default' => 'error',
	),
	'maintenance' => array(
		'label'   => __( 'Maintenance Page', 'fanfiction-manager' ),
		'default' => 'maintenance',
	),
);

foreach ( $definitions as $key => $def ) {

	// Resolve slug source
	if ( $key === 'members' ) {
		$slug = $members_slug;
	} elseif ( isset( $page_slugs[ $key ] ) ) {
		$slug = $page_slugs[ $key ];
	} elseif ( isset( $current_slugs[ $key ] ) ) {
		$slug = $current_slugs[ $key ];
	} else {
		$slug = $def['default'];
	}

	$preview_html = $base_url
		. '<span class="fanfic-dynamic-slug">'
		. esc_html( $slug )
		. '</span>';
	
	if (isset($def['suffix'])) {
		$preview_html .= '<span>' . esc_html( $def['suffix'] ) . '</span>';
	} else {
		$preview_html .= '/';
	}
	
	if ($key === 'members') {
		$preview_html = $base_url . '<span class="fanfic-dynamic-slug">' . esc_html( $slug ) . '</span>/username/';
	}

	self::render_slug_input_row( array(
		'id'             => 'fanfic_' . $key . '_slug',
		'name'           => $key === 'members'
			? 'fanfic_members_slug'
			: 'fanfic_system_page_slugs[' . $key . ']',
		'label'          => $def['label'],
		'value'          => $slug,
		'preview_id'     => $key . '-preview-code',
		'preview_html'   => $preview_html,
		'required'       => $def['required'] ?? false,
		'data_slug_type' => 'system_' . $key,
		'description'    => $def['description'] ?? '',
	) );
}
?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 5: REDIRECT INFORMATION -->
                <!-- ============================================ -->
                <?php if ( ! $in_wizard ) : ?>
                <div class="fanfic-config-section fanfic-section-redirects">
                    <div class="fanfic-section-header">
                        <h3><?php echo esc_html__( 'Active URL Redirects', 'fanfiction-manager' ); ?></h3>
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
                                                       class="button button-small delete-redirect-button"
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
            .fanfic-info-box box-warning {
                display: flex;
                gap: 12px;
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 15px;
                margin-top: 20px;
                align-items: flex-start;
            }

            .fanfic-info-box.box-warning .dashicons {
                color: #2271b1;
                flex-shrink: 0;
                margin-top: 2px;
            }

            .fanfic-info-box.box-warning p {
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

            .delete-redirect-button {
                color: #b32d2e;
                border-color: #b32d2e;
            }

            .delete-redirect-button:hover {
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
             * All inputs now use consistent ID pattern: #fanfic_{key}_slug
             */
            function getSlugValue(key) {
                // ONE standard pattern for ALL inputs
                var $input = $('#fanfic_' + key + '_slug');

                if ($input.length) {
                    var value = $input.val();
                    if (value && value.trim()) {
                        return value.trim();
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
             * All preview elements now use consistent ID pattern: #{key-with-hyphens}-preview-code
             */
            function updatePreviews() {
                $.each(previewConfig, function(key, config) {
                    // Convert underscores to hyphens for HTML ID (story_path → story-path)
                    var keyWithHyphens = key.replace(/_/g, '-');
                    var $preview = $('#' + keyWithHyphens + '-preview-code');

                    if ($preview.length) {
                        var previewUrl = buildPreviewUrl(config.template);
                        $preview.html(previewUrl);
                    }
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

        $use_base_slug = get_option( 'fanfic_use_base_slug', true );

        ?>
        <div class="wrap fanfic-url-config-wrap">
            <h1><?php echo esc_html__( 'URL Configuration', 'fanfiction-manager' ); ?></h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">
                <?php echo esc_html__( 'Configure how your fanfiction site URLs are structured. Changes are applied immediately and old URLs will redirect automatically.', 'fanfiction-manager' ); ?>
            </p>

            <?php self::display_notices(); ?>

            <!-- URL Strategy Mode Switcher -->
            <div class="fanfic-config-section" style="margin-bottom: 25px;">
                <div class="fanfic-section-header" style="background: #2271b1; border-bottom: 1px solid #135e96;">
                    <h2 style="color: #fff; margin: 0;">
                        <?php echo esc_html__( 'URL Strategy Mode', 'fanfiction-manager' ); ?>
                    </h2>
                    <p class="description" style="color: #fff; margin: 5px 0 0 0;">
                        <?php echo esc_html__( 'Choose how your plugin URLs are structured.', 'fanfiction-manager' ); ?>
                    </p>
                </div>
                <div class="fanfic-section-content">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-url-mode-form">
                        <?php wp_nonce_field( 'fanfic_switch_url_mode', 'fanfic_url_mode_nonce' ); ?>
                        <input type="hidden" name="action" value="fanfic_switch_url_mode">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php echo esc_html__( 'Current Mode', 'fanfiction-manager' ); ?>
                                </th>
                                <td>
                                    <label style="display: block; margin-bottom: 15px;">
                                        <input type="radio" name="fanfic_use_base_slug" value="1" <?php checked( $use_base_slug, true ); ?>>
                                        <strong><?php echo esc_html__( 'WordPress URL with Base Slug (Recommended)', 'fanfiction-manager' ); ?></strong>
                                        <br>
                                        <span class="description" style="margin-left: 24px;">
                                            <?php echo esc_html__( 'URLs like: yoursite.com/fanfiction/... - Isolates plugin from WordPress pages, avoiding conflicts.', 'fanfiction-manager' ); ?>
                                        </span>
                                    </label>

                                    <label style="display: block;">
                                        <input type="radio" name="fanfic_use_base_slug" value="0" <?php checked( $use_base_slug, false ); ?>>
                                        <strong><?php echo esc_html__( 'WordPress Main URL Root', 'fanfiction-manager' ); ?></strong>
                                        <br>
                                        <span class="description" style="margin-left: 24px;">
                                            <?php echo esc_html__( 'URLs like: yoursite.com/... - Overwrites WordPress homepage settings, may conflict with other plugins.', 'fanfiction-manager' ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div class="notice notice-warning inline" style="margin: 20px 0;">
                            <p>
                                <strong><?php echo esc_html__( 'Warning:', 'fanfiction-manager' ); ?></strong>
                                <?php echo esc_html__( 'Switching URL modes will rebuild all system pages and change your site structure. This will result in:', 'fanfiction-manager' ); ?>
                            </p>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li><?php echo esc_html__( 'Broken bookmarked links for users', 'fanfiction-manager' ); ?></li>
                                <li><?php echo esc_html__( 'Search engines will need to re-index your entire site', 'fanfiction-manager' ); ?></li>
                                <li><?php echo esc_html__( 'Temporary disruption to site navigation', 'fanfiction-manager' ); ?></li>
                            </ul>
                            <p>
                                <?php echo esc_html__( 'Only switch modes if absolutely necessary.', 'fanfiction-manager' ); ?>
                            </p>
                        </div>

                        <p>
                            <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to switch URL modes? This will rebuild all pages and may temporarily break site navigation.', 'fanfiction-manager' ) ); ?>');">
                                <?php echo esc_html__( 'Switch Mode and Rebuild Pages', 'fanfiction-manager' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

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

        // 1. Save base slug (with special handling for main page)
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

        // 4. Save dynamic page slugs individually (same pattern as story_path)

        // Dashboard
        if ( isset( $_POST['fanfic_dashboard_slug'] ) ) {
            $result = $this->save_slug_field( 'fanfic_dashboard_slug', 'dashboard', self::OPTION_DASHBOARD_SLUG, __( 'Dashboard slug', 'fanfiction-manager' ) );
            if ( $result ) {
                if ( isset( $result['error'] ) ) {
                    $errors[] = $result['error'];
                } elseif ( isset( $result['success'] ) ) {
                    $success_messages[] = $result['success'];
                }
            }
        }

        // Members
        if ( isset( $_POST['fanfic_members_slug'] ) ) {
            $result = $this->save_slug_field( 'fanfic_members_slug', 'members', self::OPTION_MEMBERS_SLUG, __( 'Members slug', 'fanfiction-manager' ) );
            if ( $result ) {
                if ( isset( $result['error'] ) ) {
                    $errors[] = $result['error'];
                } elseif ( isset( $result['success'] ) ) {
                    $success_messages[] = $result['success'];
                }
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
                        if ( $key === 'dashboard' && ! empty( $current_dynamic_slugs[ $key ] ) && $current_dynamic_slugs[ $key ] !== $slug ) {
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
     * Flush all rewrite rules immediately
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
            Fanfic_Post_Types::register();
        }
        if ( class_exists( 'Fanfic_Taxonomies' ) ) {
            Fanfic_Taxonomies::register();
        }
        if ( class_exists( 'Fanfic_URL_Manager' ) ) {
            Fanfic_URL_Manager::get_instance()->register_rewrite_rules();
        }

        // Flush immediately
        flush_rewrite_rules();
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

        // Display warning message
        $warning = get_transient( 'fanfic_url_config_warning' );
        if ( $warning ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php echo wp_kses_post( $warning ); ?></p>
            </div>
            <?php
            delete_transient( 'fanfic_url_config_warning' );
        }

        // Display error message
        $error = get_transient( 'fanfic_url_config_error' );
        if ( $error ) {
            ?>
            <div class="notice error-message is-dismissible">
                <p><?php echo wp_kses_post( $error ); ?></p>
            </div>
            <?php
            delete_transient( 'fanfic_url_config_error' );
        }
    }

    /**
     * Handle URL mode switching
     *
     * Switches between base slug and no base slug modes, rebuilds pages, and flushes rules.
     *
     * @since 1.0.0
     * @return void
     */
    public function switch_url_mode() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Denied', array( 'response' => 403 ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['fanfic_url_mode_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_url_mode_nonce'] ) ), 'fanfic_switch_url_mode' ) ) {
            wp_die( 'Security check failed.', 'Security Error', array( 'response' => 403 ) );
        }

        // Get the new mode
        $new_use_base_slug = isset( $_POST['fanfic_use_base_slug'] ) && '1' === $_POST['fanfic_use_base_slug'];
        $old_use_base_slug = get_option( 'fanfic_use_base_slug', true );

        // Only proceed if mode is actually changing
        if ( $new_use_base_slug !== $old_use_base_slug ) {
            // Update the option
            update_option( 'fanfic_use_base_slug', $new_use_base_slug );

            // Rebuild all system pages with new hierarchy
            $base_slug = get_option( self::OPTION_BASE_SLUG, 'fanfiction' );
            $rebuild_result = Fanfic_Templates::create_system_pages( $base_slug );

            // Flush rewrite rules
            $this->flush_all_rewrite_rules();

            // Set success message
            $message = sprintf(
                /* translators: %s: new mode name */
                __( 'URL mode switched successfully to "%s". All pages have been rebuilt.', 'fanfiction-manager' ),
                $new_use_base_slug ? __( 'Base Slug Mode', 'fanfiction-manager' ) : __( 'Root URL Mode', 'fanfiction-manager' )
            );
            set_transient( 'fanfic_url_config_success', $message, 30 );

            // Add warning about broken links
            $warning = __( 'Note: Old bookmarked links may be broken. Search engines will need to re-index your site.', 'fanfiction-manager' );
            set_transient( 'fanfic_url_config_warning', $warning, 30 );
        } else {
            set_transient( 'fanfic_url_config_success', __( 'No changes made - mode is already set to the selected value.', 'fanfiction-manager' ), 30 );
        }

        // Redirect back
        wp_safe_redirect( add_query_arg( array( 'page' => 'fanfiction-settings', 'tab' => 'url-name' ), admin_url( 'admin.php' ) ) );
        exit;
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

    public static function save_wizard_url_settings() {
        // This function is called via AJAX from the wizard.
        // Nonce is verified in the AJAX handler in the wizard class.

        // Save base slug choice
        if ( isset( $_POST['fanfic_use_base_slug'] ) ) {
            $use_base_slug = '1' === $_POST['fanfic_use_base_slug'];
            update_option( 'fanfic_use_base_slug', $use_base_slug );
        }

        // --- The rest of this is the validation logic from the original wizard save function ---

        // 1. Validate and save base slug
        if ( isset( $_POST['fanfic_base_slug'] ) ) {
            $base_slug = sanitize_title( wp_unslash( $_POST['fanfic_base_slug'] ) );
            // Only require base slug if the mode is enabled.
            if ( ! empty($_POST['fanfic_use_base_slug']) && empty($base_slug) ) {
                 wp_send_json_error( array( 'message' => __( 'Base Slug cannot be empty when it is enabled.', 'fanfiction-manager' ) ) );
            }
            $validation = Fanfic_URL_Schema::validate_slug( $base_slug, array( 'base' ) );

            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => __( 'Base Slug: ', 'fanfiction-manager' ) . $validation->get_error_message() ) );
            }
            update_option( 'fanfic_base_slug', $base_slug );
        }

        // 2. Validate and save story path
        if ( isset( $_POST['fanfic_story_path'] ) ) {
            $story_path = sanitize_title( wp_unslash( $_POST['fanfic_story_path'] ) );
            $validation = Fanfic_URL_Schema::validate_slug( $story_path, array( 'story_path' ) );

            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => __( 'Story Path: ', 'fanfiction-manager' ) . $validation->get_error_message() ) );
            }
            update_option( 'fanfic_story_path', $story_path );
        }
    }

    public static function render_url_mode_selection( $in_wizard = false ) {
        $use_base_slug = get_option( 'fanfic_use_base_slug', true );
        ?>
        <div class="fanfic-config-section">
            <div class="fanfic-section-header">
                <h3><?php esc_html_e( 'URL Strategy Mode', 'fanfiction-manager' ); ?></h3>
                <p class="description">
                    <?php esc_html_e( 'Choose the fundamental structure for your fanfiction URLs.', 'fanfiction-manager' ); ?>
                </p>
            </div>
            <div class="fanfic-section-content">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'URL Mode', 'fanfiction-manager' ); ?></th>
                        <td>
                            <label style="display: block; margin-bottom: 15px;">
                                <input type="radio" name="fanfic_use_base_slug" value="1" <?php checked( $use_base_slug, true ); ?>>
                                <strong><?php esc_html_e( 'Use a Base Slug (Recommended)', 'fanfiction-manager' ); ?></strong>
                                <br>
                                <span class="description" style="margin-left: 24px;">
                                    <?php esc_html_e( 'Isolates plugin pages under a common path like /fanfiction/...', 'fanfiction-manager' ); ?>
                                </span>
                            </label>

                            <label style="display: block;">
                                <input type="radio" name="fanfic_use_base_slug" value="0" <?php checked( $use_base_slug, false ); ?>>
                                <strong><?php esc_html_e( 'Use Root URLs', 'fanfiction-manager' ); ?></strong>
                                <br>
                                <span class="description" style="margin-left: 24px;">
                                    <?php esc_html_e( 'Plugin pages live at the site root, like /login/. This may conflict with other pages.', 'fanfiction-manager' ); ?>
                                </span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_homepage_settings( $in_wizard = false ) {
        $main_page_mode     = get_option( 'fanfic_main_page_mode', 'custom_homepage' );
        $homepage_source    = get_option( 'fanfic_homepage_source', 'fanfiction_page' );
        $homepage_source_id = (int) get_option( 'fanfic_homepage_source_id', 0 );

        // Determine currently selected dropdown value
        $selected_value = 'fanfiction_page';
        if ( 'wordpress_archive' === $homepage_source ) {
            $selected_value = 'wordpress_archive';
        } elseif ( 'existing_page' === $homepage_source && $homepage_source_id > 0 ) {
            $selected_value = 'page:' . $homepage_source_id;
        }

        // Query existing pages for dropdown (exclude plugin-managed pages)
        $system_page_ids = array_values( get_option( 'fanfic_system_page_ids', array() ) );
        $pages           = get_posts(
            array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'exclude'        => $system_page_ids,
            )
        );
        ?>
		<div class="fanfic-wizard-welcome">
			<?php if ( $in_wizard ) : ?>
			<div class="fanfic-wizard-welcome-icon">
				<span class="dashicons dashicons-book-alt" style="font-size: 80px; width: 80px; height: 80px;"></span>
			</div>
			<?php endif; ?>

			<div style="margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Homepage', 'fanfiction-manager' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Choose whether your homepage shows the story archive or a custom homepage.', 'fanfiction-manager' ); ?>
				</p>

				<div style="margin-top: 15px; display: flex; gap: 20px;">
					<label style="flex: 1; display: block; padding: 15px; border: 2px solid #ddd; background: #fff; cursor: pointer;">
						<input
							type="radio"
							name="fanfic_main_page_mode"
							value="custom_homepage"
							<?php checked( $main_page_mode, 'custom_homepage' ); ?>
							style="margin-right: 10px;"
						>
						<strong><?php esc_html_e( 'A Custom Page', 'fanfiction-manager' ); ?></strong>
						<br>
						<span class="description" style="margin-left: 24px;">
							<?php esc_html_e( 'Select a specific page to act as the homepage.', 'fanfiction-manager' ); ?>
						</span>
						<div id="fanfic-homepage-source-wrapper" style="margin-left: 24px; margin-top: 10px; margin-bottom: 15px;">
							<label for="fanfic_homepage_source_select" style="font-weight: bold; margin-bottom: 5px; display: block;">
								<?php esc_html_e( 'Homepage Source:', 'fanfiction-manager' ); ?>
							</label>
							<select id="fanfic_homepage_source_select" name="fanfic_homepage_source_select" style="margin-top: 8px; min-width: 300px;">
								<option value="fanfiction_page" <?php selected( $selected_value, 'fanfiction_page' ); ?>>
									<?php esc_html_e( 'Fanfiction Homepage (default)', 'fanfiction-manager' ); ?>
								</option>
								<option value="wordpress_archive" <?php selected( $selected_value, 'wordpress_archive' ); ?>>
									<?php esc_html_e( 'WordPress Post Archive (Native)', 'fanfiction-manager' ); ?>
								</option>
								<?php if ( ! empty( $pages ) ) : ?>
								<optgroup label="<?php esc_attr_e( 'Existing Page', 'fanfiction-manager' ); ?>">
									<?php foreach ( $pages as $page ) : ?>
									<option value="page:<?php echo esc_attr( $page->ID ); ?>" <?php selected( $selected_value, 'page:' . $page->ID ); ?>>
										<?php echo esc_html( $page->post_title ); ?>
									</option>
									<?php endforeach; ?>
								</optgroup>
								<?php endif; ?>
							</select>
						</div>
					</label>

					<label style="flex: 1; display: block; padding: 15px; border: 2px solid #ddd; background: #fff; cursor: pointer;">
						<input
							type="radio"
							name="fanfic_main_page_mode"
							value="stories_homepage"
							<?php checked( $main_page_mode, 'stories_homepage' ); ?>
							style="margin-right: 10px;"
						>
						<strong><?php esc_html_e( 'Stories Archive', 'fanfiction-manager' ); ?></strong>
						<br>
						<span class="description" style="margin-left: 24px;">
							<?php esc_html_e( 'The homepage will directly display the story listing archive.', 'fanfiction-manager' ); ?>
						</span>
					</label>
				</div>
			</div>
		</div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleHomepageSource() {
                var mode = $('input[name="fanfic_main_page_mode"]:checked').val();
                $('#fanfic-homepage-source-wrapper').toggle( mode === 'custom_homepage' );
                // Disable the select when not in custom mode so its value isn't submitted
                $('#fanfic_homepage_source_select').prop('disabled', mode !== 'custom_homepage');
            }
            $('input[name="fanfic_main_page_mode"]').on('change', toggleHomepageSource);
            toggleHomepageSource();
        });
        </script>
        <?php
    }

    /**
     * Save homepage settings from POST data.
     *
     * @since 1.0.0
     */
    public static function save_homepage_settings() {
        if ( isset( $_POST['fanfic_main_page_mode'] ) ) {
            $main_page_mode = sanitize_text_field( wp_unslash( $_POST['fanfic_main_page_mode'] ) );
            if ( in_array( $main_page_mode, array( 'stories_homepage', 'custom_homepage' ), true ) ) {
                update_option( 'fanfic_main_page_mode', $main_page_mode );
            }
        }

        // Only save the source if custom homepage mode is active
        if ( isset( $_POST['fanfic_main_page_mode'] ) && 'custom_homepage' === $_POST['fanfic_main_page_mode'] ) {
            if ( isset( $_POST['fanfic_homepage_source_select'] ) ) {
                $source = sanitize_text_field( wp_unslash( $_POST['fanfic_homepage_source_select'] ) );
                if ( 'fanfiction_page' === $source ) {
                    update_option( 'fanfic_homepage_source', 'fanfiction_page' );
                    update_option( 'fanfic_homepage_source_id', 0 );
                } elseif ( 'wordpress_archive' === $source ) {
                    update_option( 'fanfic_homepage_source', 'wordpress_archive' );
                    update_option( 'fanfic_homepage_source_id', 0 );
                } elseif ( preg_match( '/^page:(\d+)$/', $source, $matches ) ) {
                    $post_id   = (int) $matches[1];
                    $post      = get_post( $post_id );
                    if ( $post && 'publish' === $post->post_status && 'page' === $post->post_type ) {
                        update_option( 'fanfic_homepage_source', 'existing_page' );
                        update_option( 'fanfic_homepage_source_id', $post_id );
                    }
                }
            }
        }
    }
} // Class closing brace - THIS WAS MISSING
