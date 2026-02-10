# Story Translation Linking System — Full Implementation Plan

## Context & Goal

 Authors who write stories in multiple languages currently have no way to connect them. We need a system where an author can link their stories as translations of each other. This creates bidirectional "translation groups" — if Story A (French) links to Story B (English), both pages show "Also available in: [other language]". In search results, JavaScript hides duplicate translations and shows only the version matching the user's browser language.

**Design Decisions:**
- Same author only — only the story author's own stories can be linked as translations
- Automatic bidirectional — linking A→B also links B→A (they share a `group_id`)
- JS client-side deduplication — all translation variants are in the HTML, JS hides duplicates based on `navigator.language`
- Enforce different languages — no two stories with the same language in a translation group
- Feature is conditional on `enable_language_classification` setting being enabled

---

## Existing Architecture Reference

### Database Tables (in `includes/class-fanfic-database-setup.php`)
- `wp_fanfic_languages` — `id, slug, name, native_name, is_active` (language definitions)
- `wp_fanfic_story_languages` — `story_id, language_id` (one language per story)
- Current `DB_VERSION` is `'1.4.2'` (line 50)

### Key Existing Classes
- **`Fanfic_Languages`** (`includes/class-fanfic-languages.php`) — All-static class with REST API (`fanfic/v1/languages/search`), CRUD methods. Key methods: `is_enabled()`, `get_story_language_id($story_id)`, `get_story_language($story_id)`, `get_by_id($id)`, `get_by_slug($slug)`, `save_story_language($story_id, $language_id)`, `tables_ready()`. The new `Fanfic_Translations` class must follow this exact pattern.
- **`Fanfic_Fandoms`** (`includes/class-fanfic-fandoms.php`) — Similar static class with REST search endpoint. Its story form UI pattern (search input → results → selected items with hidden inputs) is the pattern to clone for translations.
- **`Fanfic_Core`** (`includes/class-fanfic-core.php`) — Singleton entry point. Loads all dependencies in `load_dependencies()`, initializes hooks in `init_hooks()`, enqueues frontend assets in `enqueue_frontend_assets()`.

### Story Form (`templates/template-story-form.php`)
- Unified create/edit form. Edit mode detected by `is_singular('fanfiction_story')`.
- Language field at lines 465-504: simple `<select>` dropdown
- Fandom field at lines 442-462: search input + REST API results + selected items with hidden inputs + remove buttons
- Change detection system: `$data_attrs` string (line 290-301) sets `data-original-*` attributes on the `<form>` tag (line 337). JavaScript `checkForChanges()` function (line 1136) compares current values to originals to enable/disable save buttons. After AJAX save, `updateStoryOriginalState()` (line 1714) resets all originals.
- The `<form>` tag at line 337: `<form method="post" class="fanfic-story-form" id="fanfic-story-form" <?php echo $data_attrs; ?>...>`

### Story Handler (`includes/handlers/class-fanfic-story-handler.php`)
- `handle_unified_story_form()` — main handler for both create and edit
- Language parsing at line 223: `$language_id = isset( $_POST['fanfic_story_language'] ) ? absint( $_POST['fanfic_story_language'] ) : 0;`
- Language save in CREATE mode at lines 317-319
- Language save in EDIT mode at lines 507-509
- Both follow pattern: `if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) { Fanfic_Languages::save_story_language( $story_id, $language_id ); }`

### Story Card (`includes/functions.php`, line 2796)
- `fanfic_get_story_card_html($story_id)` renders each story card for search results
- `<article>` tag at line 2839: `<article id="story-<?php echo esc_attr( $story_id ); ?>" <?php post_class( 'fanfic-story-card', $story_id ); ?>>`
- Footer section at lines 2878-2913 shows genres, tags, custom taxonomies, stats
- Closing `</footer></div></article>` at lines 2913-2915

### Story View Template (`templates/template-story-view.php`)
- `fanfic_get_default_story_view_template()` (line 27) returns the default shortcode-based template
- Template uses shortcodes like `[story-author-link]`, `[story-status]`, `[story-age-badge]`, `[story-genres]`, `[story-intro]`, `[story-chapters]`, etc.
- Template content stored in DB option `fanfic_shortcode_story_view`; user-customizable
- Default header section (lines 30-38): title, author, status, age badge

### Story Shortcodes (`includes/shortcodes/class-fanfic-shortcodes-story.php`)
- `register()` at line 32 registers all story shortcodes via `add_shortcode()`
- `story_language()` at line 293 renders `<div class="fanfic-story-language">Language: Name (Native)</div>`
- Pattern for getting story ID: `$story_id = Fanfic_Shortcodes::get_current_story_id();`

### Search Results
- Search form is `<form method="get">` — regular page navigation, NOT AJAX
- Results rendered in `class-fanfic-shortcodes-search.php` method `stories_story_archive()` (line 551)
- Results container: `<div class="fanfic-stories-results" data-fanfic-stories-results>` (line 592)
- Story grid: `<div class="fanfic-story-grid">` (line 594)
- Each card rendered by `fanfic_get_story_card_html( get_the_ID() )` (line 598)
- Search bar JS (`assets/js/fanfic-search-bar-frontend.js`) has existing objects: `SmartFilterManager`, `PillsManager` — add `TranslationDeduplicator` alongside these

### Fandom JS Pattern (`assets/js/fanfiction-fandoms.js`)
- IIFE wrapper: `(function() { 'use strict'; ... })();`
- `debounce(fn, delay)` utility
- `initFandomField(container)` — targets `.fanfic-fandoms-field` containers
- REST fetch: `fetch(url, { method: 'GET', headers: { 'X-WP-Nonce': fanficFandoms.restNonce } })`
- Search threshold: 2+ characters
- Result rendering: `<button class="fanfic-fandom-result" data-id="..." data-label="...">Label</button>`
- Selection: `<span class="fanfic-selected-fandom" data-id="...">Label <button class="fanfic-remove-fandom">×</button><input type="hidden" name="fanfic_story_fandoms[]" value="..."></span>`
- Localized via `wp_localize_script()` as `fanficFandoms` with `restUrl`, `restNonce`, `strings.remove`

### Core Integration Points (`includes/class-fanfic-core.php`)
- **Dependencies loaded at line 123:** `require_once FANFIC_INCLUDES_DIR . 'class-fanfic-languages.php';` (line 123), then `class-fanfic-custom-taxonomies.php` (124), `class-fanfic-search-index.php` (125)
- **Init hooks at line 293:** `Fanfic_Languages::init();` (line 293), then `Fanfic_Custom_Taxonomies::init()` (294), `Fanfic_Search_Index::init()` (295)
- **Activation requires at line 1339:** `require_once FANFIC_INCLUDES_DIR . 'class-fanfic-languages.php';` (1339), then `class-fanfic-search-index.php` (1340)
- **Asset enqueue at line 1128:** Fandom JS enqueued for story form template: `if ( 'template-story-form.php' === $current_template && class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) { ... }` (lines 1128-1149). `$current_template` is set at line 1100 from global `$fanfic_content_template`.

### Multi-Select Dropdown Pattern (Search Bar)
```html
<div class="multi-select" data-placeholder="All Statuses">
    <button type="button" class="multi-select__trigger" aria-haspopup="listbox">All Statuses</button>
    <div class="multi-select__dropdown">
        <label><input type="checkbox" name="status[]" value="slug" /> Label (count)</label>
    </div>
</div>
```

### Cache Hooks (`includes/class-fanfic-cache-hooks.php`)
- Pattern: hook into WordPress actions → call `Fanfic_Cache::delete()` for relevant cache keys
- Example: `add_action('save_post', array(__CLASS__, 'invalidate_on_post_save'))`

---

## IMPLEMENTATION STEPS

### STEP 1: Database Table

**File:** `includes/class-fanfic-database-setup.php`

#### 1a. Add table creation in `create_tables()` method

Insert the following **after** the `$table_story_languages` block (after line 349, BEFORE the `} // end if ( $include_classification )` at line 351):

```php
		// 14. Story Translation Groups Table
		$table_story_translations = $prefix . 'fanfic_story_translations';
		$sql_story_translations   = "CREATE TABLE IF NOT EXISTS {$table_story_translations} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_story (story_id),
			KEY idx_group (group_id),
			KEY idx_group_story (group_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_translations );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_translations ) ) {
			$errors[] = 'Failed to create story_translations table';
		}
```

**Note:** This shifts the "Custom Taxonomies Table" comment from `// 14.` to `// 15.` — update that comment number too.

#### 1b. Add table creation in `create_classification_tables()` method

Insert **after** the `$table_story_languages` block (after line 608, BEFORE `self::migrate_warnings_age_schema();` at line 610):

```php
		// 7. Story Translation Groups Table
		$table_story_translations = $prefix . 'fanfic_story_translations';
		$sql_story_translations   = "CREATE TABLE IF NOT EXISTS {$table_story_translations} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_story (story_id),
			KEY idx_group (group_id),
			KEY idx_group_story (group_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_translations );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_translations ) ) {
			$errors[] = 'Failed to create story_translations table';
		}
```

#### 1c. Add to `classification_tables_exist()` (line 632)

Add `$prefix . 'fanfic_story_translations',` after `$prefix . 'fanfic_story_languages',` (after line 638).

#### 1d. Add to ALL other table arrays

In every method that lists all tables (`drop_tables()`, `tables_exist()`, `get_table_info()`, `optimize_tables()`, `repair_tables()`, `truncate_tables()`), add `$prefix . 'fanfic_story_translations'` right after `$prefix . 'fanfic_story_languages'`. There are approximately 6 such arrays in the file — search for `fanfic_story_languages` to find them all.

In `drop_tables()` (line 982 area), add it BEFORE `$prefix . 'fanfic_story_languages'` (because it references story_id which could have FK implications):
```php
$prefix . 'fanfic_story_translations',
```

#### 1e. Bump DB_VERSION

Change line 50 from `const DB_VERSION = '1.4.2';` to `const DB_VERSION = '1.5.0';`

---

### STEP 2: Backend Class — New File

**New file:** `includes/class-fanfic-translations.php`

Create this file following the exact pattern of `class-fanfic-languages.php`. Here is the complete class:

```php
<?php
/**
 * Translations Class
 *
 * Handles story translation group linking: bidirectional translation groups,
 * REST API for searching author's stories, and display helpers.
 *
 * @package FanfictionManager
 * @since 1.5.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Translations
 */
class Fanfic_Translations {

	const REST_NAMESPACE = 'fanfic/v1';

	/**
	 * Runtime cache for preloaded translation data.
	 *
	 * @var array
	 */
	private static $preloaded = array();

	/**
	 * Initialize translations feature
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Check if translation linking is enabled (requires language classification)
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function is_enabled() {
		return class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled();
	}

	// =========================================================================
	// REST API
	// =========================================================================

	/**
	 * Register REST routes
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/translations/search-stories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_story_search' ),
				'permission_callback' => array( __CLASS__, 'can_search' ),
				'args'                => array(
					'q'        => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'story_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'limit'    => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for search
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function can_search() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Handle story search for translation linking
	 *
	 * Returns the current user's published stories with language info,
	 * excluding: the current story, stories without a language, stories
	 * with the same language as the current story.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_story_search( $request ) {
		$query_text = trim( (string) $request->get_param( 'q' ) );
		$story_id   = absint( $request->get_param( 'story_id' ) );
		$limit      = min( 50, max( 1, absint( $request->get_param( 'limit' ) ) ) );

		if ( ! $story_id ) {
			return rest_ensure_response( array() );
		}

		$current_user_id = get_current_user_id();

		// Get current story's language
		$current_lang_id = null;
		if ( class_exists( 'Fanfic_Languages' ) ) {
			$current_lang_id = Fanfic_Languages::get_story_language_id( $story_id );
		}

		// Get current group members (if any) to identify already-linked stories
		$current_group_id   = self::get_group_id( $story_id );
		$current_group_lang_ids = array();
		if ( $current_group_id ) {
			$group_stories = self::get_group_stories( $current_group_id );
			foreach ( $group_stories as $gs_id ) {
				if ( (int) $gs_id === $story_id ) {
					continue;
				}
				$lang = Fanfic_Languages::get_story_language_id( $gs_id );
				if ( $lang ) {
					$current_group_lang_ids[ $gs_id ] = $lang;
				}
			}
		}

		// Query user's published stories
		$args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'author'         => $current_user_id,
			'post__not_in'   => array( $story_id ),
			'posts_per_page' => $limit * 2, // Fetch extra to compensate for filtering
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $query_text ) ) {
			$args['s'] = $query_text;
		}

		$query   = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() && count( $results ) < $limit ) {
				$query->the_post();
				$sid = get_the_ID();

				// Get this story's language
				$lang = Fanfic_Languages::get_story_language( $sid );

				// Skip stories without a language
				if ( ! $lang ) {
					continue;
				}

				$lang_id = (int) $lang['id'];

				// Skip stories with same language as current story
				if ( $current_lang_id && $lang_id === (int) $current_lang_id ) {
					continue;
				}

				// Skip stories whose language already exists in the current group
				// (from a different story)
				if ( ! empty( $current_group_lang_ids ) && in_array( $lang_id, array_values( $current_group_lang_ids ), true ) ) {
					// But allow if this story IS the one already in the group with that language
					if ( ! array_key_exists( $sid, $current_group_lang_ids ) ) {
						continue;
					}
				}

				// Check if story is already in a DIFFERENT translation group
				$other_group = self::get_group_id( $sid );
				if ( $other_group && $current_group_id && $other_group !== $current_group_id ) {
					continue; // Already belongs to another group
				}
				if ( $other_group && ! $current_group_id ) {
					continue; // Story is in a group but current story isn't — would need merge logic
				}

				$lang_label = $lang['name'];
				if ( ! empty( $lang['native_name'] ) && $lang['native_name'] !== $lang['name'] ) {
					$lang_label .= ' (' . $lang['native_name'] . ')';
				}

				$results[] = array(
					'id'             => $sid,
					'title'          => get_the_title( $sid ),
					'label'          => get_the_title( $sid ) . ' — ' . $lang_label,
					'language_id'    => $lang_id,
					'language_name'  => $lang['name'],
					'language_native' => $lang['native_name'],
					'language_slug'  => $lang['slug'],
					'language_label' => $lang_label,
				);
			}
			wp_reset_postdata();
		}

		return rest_ensure_response( $results );
	}

	// =========================================================================
	// GROUP MANAGEMENT
	// =========================================================================

	/**
	 * Get translation group ID for a story
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return int|null Group ID or null if not in a group.
	 */
	public static function get_group_id( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return null;
		}

		// Check preloaded cache
		if ( isset( self::$preloaded[ $story_id ] ) ) {
			return self::$preloaded[ $story_id ]['group_id'];
		}

		global $wpdb;
		$table = self::get_translations_table();

		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT group_id FROM {$table} WHERE story_id = %d LIMIT 1",
				$story_id
			)
		);

		return $group_id ? absint( $group_id ) : null;
	}

	/**
	 * Get all story IDs in a translation group
	 *
	 * @since 1.5.0
	 * @param int $group_id Group ID.
	 * @return int[] Story IDs.
	 */
	public static function get_group_stories( $group_id ) {
		$group_id = absint( $group_id );
		if ( ! $group_id || ! self::table_ready() ) {
			return array();
		}

		global $wpdb;
		$table = self::get_translations_table();

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT story_id FROM {$table} WHERE group_id = %d",
				$group_id
			)
		);

		return array_map( 'absint', (array) $results );
	}

	/**
	 * Get translation sibling stories (all other stories in the same group)
	 *
	 * Returns rich data for each sibling: story_id, title, permalink, language info.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return array Array of sibling data arrays.
	 */
	public static function get_translation_siblings( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$group_id = self::get_group_id( $story_id );
		if ( ! $group_id ) {
			return array();
		}

		$group_stories = self::get_group_stories( $group_id );
		$siblings = array();

		foreach ( $group_stories as $sibling_id ) {
			if ( $sibling_id === $story_id ) {
				continue;
			}

			$sibling_post = get_post( $sibling_id );
			if ( ! $sibling_post || 'publish' !== $sibling_post->post_status ) {
				continue;
			}

			$lang = Fanfic_Languages::get_story_language( $sibling_id );
			$lang_label = '';
			$lang_slug  = '';
			if ( $lang ) {
				$lang_label = $lang['name'];
				if ( ! empty( $lang['native_name'] ) && $lang['native_name'] !== $lang['name'] ) {
					$lang_label .= ' (' . $lang['native_name'] . ')';
				}
				$lang_slug = $lang['slug'];
			}

			$siblings[] = array(
				'story_id'       => $sibling_id,
				'title'          => get_the_title( $sibling_id ),
				'permalink'      => get_permalink( $sibling_id ),
				'language_label' => $lang_label,
				'language_slug'  => $lang_slug,
				'language_name'  => $lang ? $lang['name'] : '',
				'language_native' => $lang ? $lang['native_name'] : '',
			);
		}

		return $siblings;
	}

	/**
	 * Save story translations from form submission
	 *
	 * Compares current group members with desired linked_story_ids.
	 * Adds new links, removes unlinked stories.
	 *
	 * @since 1.5.0
	 * @param int   $story_id        Current story ID.
	 * @param int[] $linked_story_ids Desired linked story IDs (from form).
	 * @return true|WP_Error
	 */
	public static function save_story_translations( $story_id, $linked_story_ids ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return true;
		}

		$linked_story_ids = array_map( 'absint', array_filter( (array) $linked_story_ids ) );
		$current_user_id  = get_current_user_id();

		// Get current siblings
		$current_siblings = self::get_translation_siblings( $story_id );
		$current_sibling_ids = wp_list_pluck( $current_siblings, 'story_id' );
		$current_sibling_ids = array_map( 'absint', $current_sibling_ids );

		// Determine additions and removals
		$to_add    = array_diff( $linked_story_ids, $current_sibling_ids );
		$to_remove = array_diff( $current_sibling_ids, $linked_story_ids );

		// Process removals first
		foreach ( $to_remove as $remove_id ) {
			self::remove_from_group( $remove_id );
		}

		// If all siblings removed and no new ones, remove current story from group too
		if ( empty( $linked_story_ids ) ) {
			self::remove_from_group( $story_id );
			return true;
		}

		// Process additions
		foreach ( $to_add as $add_id ) {
			$result = self::add_to_group( $story_id, $add_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Add a story to another story's translation group
	 *
	 * @since 1.5.0
	 * @param int $story_a_id First story ID.
	 * @param int $story_b_id Second story ID to add.
	 * @return true|WP_Error
	 */
	public static function add_to_group( $story_a_id, $story_b_id ) {
		$story_a_id = absint( $story_a_id );
		$story_b_id = absint( $story_b_id );

		if ( ! $story_a_id || ! $story_b_id || ! self::table_ready() ) {
			return new WP_Error( 'invalid_input', __( 'Invalid story IDs.', 'fanfiction-manager' ) );
		}

		// Validate both are fanfiction_story posts
		$post_a = get_post( $story_a_id );
		$post_b = get_post( $story_b_id );
		if ( ! $post_a || 'fanfiction_story' !== $post_a->post_type || ! $post_b || 'fanfiction_story' !== $post_b->post_type ) {
			return new WP_Error( 'invalid_post', __( 'One or both stories not found.', 'fanfiction-manager' ) );
		}

		// Validate same author
		$current_user_id = get_current_user_id();
		if ( (int) $post_a->post_author !== $current_user_id || (int) $post_b->post_author !== $current_user_id ) {
			return new WP_Error( 'not_author', __( 'You can only link your own stories.', 'fanfiction-manager' ) );
		}

		// Validate both have languages
		$lang_a = Fanfic_Languages::get_story_language_id( $story_a_id );
		$lang_b = Fanfic_Languages::get_story_language_id( $story_b_id );
		if ( ! $lang_a || ! $lang_b ) {
			return new WP_Error( 'no_language', __( 'Both stories must have a language set.', 'fanfiction-manager' ) );
		}

		// Validate different languages
		if ( (int) $lang_a === (int) $lang_b ) {
			return new WP_Error( 'same_language', __( 'Stories in the same language cannot be linked as translations.', 'fanfiction-manager' ) );
		}

		global $wpdb;
		$table = self::get_translations_table();

		$group_a = self::get_group_id( $story_a_id );
		$group_b = self::get_group_id( $story_b_id );

		if ( $group_a && $group_b && $group_a === $group_b ) {
			// Already in the same group
			return true;
		}

		if ( ! $group_a && ! $group_b ) {
			// Neither has a group — create new group
			$new_group_id = self::get_next_group_id();

			$wpdb->insert( $table, array( 'group_id' => $new_group_id, 'story_id' => $story_a_id ), array( '%d', '%d' ) );
			$wpdb->insert( $table, array( 'group_id' => $new_group_id, 'story_id' => $story_b_id ), array( '%d', '%d' ) );

		} elseif ( $group_a && ! $group_b ) {
			// A has group, B doesn't — validate language not duplicate in group
			$error = self::validate_language_unique_in_group( $group_a, $lang_b, $story_b_id );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$wpdb->insert( $table, array( 'group_id' => $group_a, 'story_id' => $story_b_id ), array( '%d', '%d' ) );

		} elseif ( ! $group_a && $group_b ) {
			// B has group, A doesn't — validate language not duplicate in group
			$error = self::validate_language_unique_in_group( $group_b, $lang_a, $story_a_id );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$wpdb->insert( $table, array( 'group_id' => $group_b, 'story_id' => $story_a_id ), array( '%d', '%d' ) );

		} else {
			// Both have different groups — merge: move all from group_b into group_a
			// First validate no language conflicts
			$stories_in_a = self::get_group_stories( $group_a );
			$stories_in_b = self::get_group_stories( $group_b );

			$langs_in_a = array();
			foreach ( $stories_in_a as $sid ) {
				$l = Fanfic_Languages::get_story_language_id( $sid );
				if ( $l ) {
					$langs_in_a[] = (int) $l;
				}
			}

			foreach ( $stories_in_b as $sid ) {
				$l = Fanfic_Languages::get_story_language_id( $sid );
				if ( $l && in_array( (int) $l, $langs_in_a, true ) ) {
					return new WP_Error( 'duplicate_language', __( 'Cannot merge: both groups contain a story in the same language.', 'fanfiction-manager' ) );
				}
			}

			// Merge: update all group_b stories to group_a
			$wpdb->update(
				$table,
				array( 'group_id' => $group_a ),
				array( 'group_id' => $group_b ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Clear preload cache
		self::$preloaded = array();

		return true;
	}

	/**
	 * Remove a story from its translation group
	 *
	 * If the group then has fewer than 2 members, delete remaining entries.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function remove_from_group( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return;
		}

		$group_id = self::get_group_id( $story_id );
		if ( ! $group_id ) {
			return;
		}

		global $wpdb;
		$table = self::get_translations_table();

		// Remove this story
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		// Check remaining members
		$remaining = self::get_group_stories( $group_id );
		if ( count( $remaining ) < 2 ) {
			// Group has 0 or 1 members — delete remaining entries
			$wpdb->delete( $table, array( 'group_id' => $group_id ), array( '%d' ) );
		}

		// Clear preload cache
		self::$preloaded = array();
	}

	/**
	 * Cleanup story relations on deletion
	 *
	 * @since 1.5.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function cleanup_story_relations( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}
		self::remove_from_group( $post_id );
	}

	// =========================================================================
	// BATCH PRELOADING (performance for search results)
	// =========================================================================

	/**
	 * Preload translation group data for multiple story IDs
	 *
	 * Call this before rendering a list of story cards to avoid N+1 queries.
	 *
	 * @since 1.5.0
	 * @param int[] $story_ids Array of story IDs.
	 * @return void
	 */
	public static function preload_groups( $story_ids ) {
		if ( ! self::table_ready() || empty( $story_ids ) ) {
			return;
		}

		$story_ids = array_map( 'absint', array_filter( (array) $story_ids ) );
		if ( empty( $story_ids ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_translations_table();
		$placeholders = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT story_id, group_id FROM {$table} WHERE story_id IN ({$placeholders})",
				$story_ids
			),
			ARRAY_A
		);

		// Store in preload cache
		foreach ( $story_ids as $sid ) {
			self::$preloaded[ $sid ] = array( 'group_id' => null );
		}
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				self::$preloaded[ (int) $row['story_id'] ] = array(
					'group_id' => absint( $row['group_id'] ),
				);
			}
		}
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Validate that a language is unique within a group
	 *
	 * @since 1.5.0
	 * @param int $group_id    Group ID.
	 * @param int $language_id Language ID to check.
	 * @param int $exclude_story_id Story ID to exclude from check.
	 * @return true|WP_Error
	 */
	private static function validate_language_unique_in_group( $group_id, $language_id, $exclude_story_id = 0 ) {
		$group_stories = self::get_group_stories( $group_id );

		foreach ( $group_stories as $existing_id ) {
			if ( $exclude_story_id && (int) $existing_id === (int) $exclude_story_id ) {
				continue;
			}
			$existing_lang = Fanfic_Languages::get_story_language_id( $existing_id );
			if ( $existing_lang && (int) $existing_lang === (int) $language_id ) {
				return new WP_Error(
					'duplicate_language',
					__( 'A story in this language already exists in this translation group.', 'fanfiction-manager' )
				);
			}
		}

		return true;
	}

	/**
	 * Get next available group ID
	 *
	 * @since 1.5.0
	 * @return int
	 */
	private static function get_next_group_id() {
		global $wpdb;
		$table = self::get_translations_table();

		$max = $wpdb->get_var( "SELECT COALESCE(MAX(group_id), 0) FROM {$table}" );

		return absint( $max ) + 1;
	}

	/**
	 * Check if translations table exists
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function table_ready() {
		global $wpdb;
		$table = self::get_translations_table();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Get translations table name
	 *
	 * @since 1.5.0
	 * @return string
	 */
	private static function get_translations_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_story_translations';
	}
}
```

---

### STEP 3: Core Integration

**File:** `includes/class-fanfic-core.php`

#### 3a. Load dependency (after line 123)

After `require_once FANFIC_INCLUDES_DIR . 'class-fanfic-languages.php';` (line 123), add:

```php
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-translations.php';
```

#### 3b. Init hook (after line 293)

After `Fanfic_Languages::init();` (line 293), add:

```php
		Fanfic_Translations::init();
```

#### 3c. Activation require (after line 1339)

After `require_once FANFIC_INCLUDES_DIR . 'class-fanfic-languages.php';` (line 1339), add:

```php
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-translations.php';
```

#### 3d. Enqueue translations JS (after line 1149)

After the fandoms enqueue block closing `}` at line 1149, add:

```php
		// Translations autocomplete for story form
		if ( 'template-story-form.php' === $current_template && class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
			wp_enqueue_script(
				'fanfiction-translations',
				FANFIC_PLUGIN_URL . 'assets/js/fanfiction-translations.js',
				array(),
				FANFIC_VERSION,
				true
			);

			wp_localize_script(
				'fanfiction-translations',
				'fanficTranslations',
				array(
					'restUrl'   => esc_url_raw( rest_url( Fanfic_Translations::REST_NAMESPACE . '/translations/search-stories' ) ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'strings'   => array(
						'remove' => __( 'Remove translation link', 'fanfiction-manager' ),
					),
				)
			);
		}
```

---

### STEP 4: New JS — Story Form Search UI

**New file:** `assets/js/fanfiction-translations.js`

Clone the exact pattern from `assets/js/fanfiction-fandoms.js`:

```javascript
/* global fanficTranslations */
(function() {
	'use strict';

	function debounce(fn, delay) {
		var timer = null;
		return function() {
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function() {
				fn.apply(null, args);
			}, delay);
		};
	}

	function initTranslationField(container) {
		if (typeof fanficTranslations === 'undefined') {
			return;
		}

		var searchInput = container.querySelector('input[type="text"]:not([type="hidden"])');
		var resultsBox = container.querySelector('.fanfic-translation-results');
		var selectedBox = container.querySelector('.fanfic-selected-translations');
		var storyId = container.getAttribute('data-story-id') || '0';

		if (!searchInput || !resultsBox || !selectedBox) {
			return;
		}

		function dispatchChange() {
			var event = new CustomEvent('fanfic-translations-changed');
			document.dispatchEvent(event);

			// Also trigger generic change detection for the story form
			var form = container.closest('form');
			if (form && typeof form.dispatchEvent === 'function') {
				form.dispatchEvent(new Event('input', { bubbles: true }));
			}
		}

		function getSelectedIds() {
			return Array.from(selectedBox.querySelectorAll('input[name="fanfic_story_translations[]"]')).map(function(input) {
				return input.value;
			});
		}

		function addSelected(id, label) {
			var existing = getSelectedIds();
			if (existing.indexOf(String(id)) !== -1) {
				return;
			}

			var wrapper = document.createElement('span');
			wrapper.className = 'fanfic-selected-translation';
			wrapper.setAttribute('data-id', id);
			wrapper.textContent = label + ' ';

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'fanfic-remove-translation';
			remove.setAttribute('aria-label', fanficTranslations.strings.remove);
			remove.textContent = '\u00d7';

			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'fanfic_story_translations[]';
			hidden.value = id;

			wrapper.appendChild(remove);
			wrapper.appendChild(hidden);
			selectedBox.appendChild(wrapper);
			dispatchChange();
		}

		function renderResults(items) {
			resultsBox.innerHTML = '';
			if (!items.length) {
				return;
			}

			items.forEach(function(item) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'fanfic-translation-result';
				btn.setAttribute('data-id', item.id);
				btn.setAttribute('data-label', item.label);
				btn.textContent = item.label;

				// Disable if already selected
				if (getSelectedIds().indexOf(String(item.id)) !== -1) {
					btn.disabled = true;
					btn.classList.add('is-disabled');
				}

				resultsBox.appendChild(btn);
			});
		}

		function searchStories(query) {
			var url = fanficTranslations.restUrl +
				'?story_id=' + encodeURIComponent(storyId) +
				'&limit=20';

			if (query.length >= 2) {
				url += '&q=' + encodeURIComponent(query);
			}

			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficTranslations.restNonce
				}
			}).then(function(response) {
				return response.ok ? response.json() : [];
			}).then(function(data) {
				renderResults(Array.isArray(data) ? data : []);
			}).catch(function() {
				resultsBox.innerHTML = '';
			});
		}

		var debouncedSearch = debounce(function(e) {
			searchStories(e.target.value.trim());
		}, 250);

		searchInput.addEventListener('input', debouncedSearch);

		// Also show results on focus if input is empty (show all linkable stories)
		searchInput.addEventListener('focus', function() {
			if (searchInput.value.trim().length < 2) {
				searchStories('');
			}
		});

		resultsBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-translation-result')) {
				return;
			}
			if (target.disabled || target.classList.contains('is-disabled')) {
				return;
			}
			addSelected(target.getAttribute('data-id'), target.getAttribute('data-label') || target.textContent);
			searchInput.value = '';
			resultsBox.innerHTML = '';
		});

		selectedBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-remove-translation')) {
				return;
			}
			var wrapper = target.closest('.fanfic-selected-translation');
			if (wrapper) {
				wrapper.remove();
				dispatchChange();
			}
		});

		// Close results when clicking outside
		document.addEventListener('click', function(e) {
			if (!container.contains(e.target)) {
				resultsBox.innerHTML = '';
			}
		});
	}

	function init() {
		var containers = document.querySelectorAll('.fanfic-translations-field');
		containers.forEach(function(container) {
			initTranslationField(container);
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
```

---

### STEP 5: Story Form — Translation Field HTML

**File:** `templates/template-story-form.php`

#### 5a. Add the translation field (after line 504, before line 507)

After the language field closing `<?php endif; ?>` at line 505, and before the custom taxonomies `<?php` block, insert:

```php
						<?php if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() && $is_edit_mode && $current_language_id ) : ?>
							<?php
							$current_translation_siblings = Fanfic_Translations::get_translation_siblings( $story_id );
							?>
							<!-- Translation Links -->
							<div class="fanfic-form-field fanfic-translations-field" data-story-id="<?php echo esc_attr( $story_id ); ?>" data-story-language="<?php echo esc_attr( $current_language_id ); ?>">
								<label for="fanfic_translation_search"><?php esc_html_e( 'Linked Translations', 'fanfiction-manager' ); ?></label>
								<input
									type="text"
									id="fanfic_translation_search"
									class="fanfic-input"
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search your stories to link as translation...', 'fanfiction-manager' ); ?>"
								/>
								<div class="fanfic-translation-results" role="listbox" aria-label="<?php esc_attr_e( 'Translation search results', 'fanfiction-manager' ); ?>"></div>
								<div class="fanfic-selected-translations" aria-live="polite">
									<?php foreach ( $current_translation_siblings as $sibling ) : ?>
										<span class="fanfic-selected-translation" data-id="<?php echo esc_attr( $sibling['story_id'] ); ?>">
											<?php echo esc_html( $sibling['title'] . ' — ' . $sibling['language_label'] ); ?>
											<button type="button" class="fanfic-remove-translation" aria-label="<?php esc_attr_e( 'Remove translation link', 'fanfiction-manager' ); ?>">&times;</button>
											<input type="hidden" name="fanfic_story_translations[]" value="<?php echo esc_attr( $sibling['story_id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Link other stories you wrote in different languages as translations of this story.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>
```

#### 5b. Add to change detection — `$data_attrs` (line 292-301)

In the `$data_attrs` `sprintf()` at line 292, extend the format string and add the translations value. After `data-original-image="%s"`, add ` data-original-translations="%s"`. Add the value:

```php
esc_attr( implode( ',', wp_list_pluck(
    class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled()
        ? Fanfic_Translations::get_translation_siblings( $story_id )
        : array(),
    'story_id'
) ) )
```

#### 5c. Add to `checkForChanges()` function (around line 1136)

After the line that gets `fandomInputs` (line 1143), add:
```javascript
				var translationInputs = document.querySelectorAll('input[name="fanfic_story_translations[]"]');
```

After `currentFandoms` (line 1155), add:
```javascript
				var currentTranslations = Array.from(translationInputs).map(function(input) { return input.value; }).sort().join(',');
```

After `originalLanguage` (line 1169), add:
```javascript
				var originalTranslations = form.getAttribute('data-original-translations');
```

After the `originalLanguage` null check (line 1177-1178), add:
```javascript
				if (null === originalTranslations) {
					originalTranslations = currentTranslations;
				}
```

In the `hasChanges` comparison (line 1190-1201), add a new line:
```javascript
								(currentTranslations !== originalTranslations) ||
```

#### 5d. Add to event listeners section (around line 1227)

After `if (languageField) languageField.addEventListener('change', checkForChanges);` (line 1238), add:
```javascript
			document.addEventListener('fanfic-translations-changed', checkForChanges);
```

#### 5e. Add to `updateStoryOriginalState()` (around line 1714)

After the `fandomInputs` line (1721), add:
```javascript
				var translationInputs = document.querySelectorAll('input[name="fanfic_story_translations[]"]');
```

After `storyForm.setAttribute('data-original-fandoms', ...)` (line 1771), add:
```javascript
				storyForm.setAttribute('data-original-translations', Array.from(translationInputs).map(function(input) { return input.value; }).sort().join(','));
```

---

### STEP 6: Story Handler — Save Translations

**File:** `includes/handlers/class-fanfic-story-handler.php`

#### 6a. Parse form data (after line 223)

After the `$language_id` parsing at line 223, add:

```php
		// Get translation links (Phase 5 - translation groups)
		$translation_ids = isset( $_POST['fanfic_story_translations'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_translations'] ) : array();
```

#### 6b. Save in EDIT mode (after line 509)

After `Fanfic_Languages::save_story_language( $story_id, $language_id );` block (line 507-509), add:

```php
			// Save translation links
			if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
				Fanfic_Translations::save_story_translations( $story_id, $translation_ids );
			}
```

**DO NOT** add translation saving in CREATE mode (lines 317-319 area) — translations only work after a story has an ID and language set.

---

### STEP 7: Shortcode — `[story-translations]`

**File:** `includes/shortcodes/class-fanfic-shortcodes-story.php`

#### 7a. Register shortcode (in `register()` method, after line 51)

After `add_shortcode( 'story-age-badge', array( __CLASS__, 'story_age_badge' ) );` (line 51), add:

```php
		add_shortcode( 'story-translations', array( __CLASS__, 'story_translations' ) );
```

#### 7b. Add the method (after the `story_language()` method, after line 315)

Insert after line 315:

```php
	/**
	 * Story translations shortcode
	 *
	 * [story-translations]
	 *
	 * Displays available translations as inline link (1 translation)
	 * or dropdown (2+ translations) with globe icon.
	 *
	 * @since 1.5.0
	 * @return string Translations HTML or empty string.
	 */
	public static function story_translations() {
		if ( ! class_exists( 'Fanfic_Translations' ) || ! Fanfic_Translations::is_enabled() ) {
			return '';
		}

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$siblings = Fanfic_Translations::get_translation_siblings( $story_id );
		if ( empty( $siblings ) ) {
			return '';
		}

		$count = count( $siblings );

		ob_start();
		?>
		<div class="fanfic-story-translations" aria-label="<?php esc_attr_e( 'Available translations', 'fanfiction-manager' ); ?>">
			<span class="fanfic-translations-icon" aria-hidden="true">&#127760;</span>
			<strong><?php esc_html_e( 'Also available in:', 'fanfiction-manager' ); ?></strong>
			<?php if ( 1 === $count ) : ?>
				<?php $sibling = $siblings[0]; ?>
				<a href="<?php echo esc_url( $sibling['permalink'] ); ?>" class="fanfic-translation-link">
					<?php echo esc_html( $sibling['language_label'] ); ?>
				</a>
			<?php else : ?>
				<select class="fanfic-translations-dropdown" onchange="if(this.value)window.location.href=this.value">
					<option value=""><?php esc_html_e( 'Select language...', 'fanfiction-manager' ); ?></option>
					<?php foreach ( $siblings as $sibling ) : ?>
						<option value="<?php echo esc_url( $sibling['permalink'] ); ?>">
							<?php echo esc_html( $sibling['language_label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
```

---

### STEP 8: Default Story View Template

**File:** `templates/template-story-view.php`

In `fanfic_get_default_story_view_template()` function, add `[story-translations]` after the meta div closing `</div>` (after line 37, before `</header>`). The modified header section should look like:

```html
	<header class="fanfic-story-header">
		[fanfic-story-title]
		<div class="fanfic-story-meta">
			<span class="fanfic-story-author"><?php esc_html_e( 'by', 'fanfiction-manager' ); ?> [story-author-link]</span>
			<span class="fanfic-story-status">[story-status]</span>
			<span class="fanfic-story-age">[story-age-badge]</span>
		</div>
		[story-translations]
	</header>
```

---

### STEP 9: Story Card — Data Attributes + Translation Indicator

**File:** `includes/functions.php`

#### 9a. Gather translation data (before `ob_start()` at line 2837)

After the `$visible_tags` block (lines 2832-2835) and before `ob_start();` at line 2837, add:

```php
	// Translation group data for search deduplication
	$translation_group_id = 0;
	$language_slug = '';
	$story_views = absint( get_post_meta( $story_id, '_fanfic_views', true ) );

	if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
		$group_id = Fanfic_Translations::get_group_id( $story_id );
		if ( $group_id ) {
			$translation_group_id = $group_id;
		}
	}
	if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
		$lang_data = Fanfic_Languages::get_story_language( $story_id );
		if ( $lang_data ) {
			$language_slug = $lang_data['slug'];
		}
	}
```

#### 9b. Modify the `<article>` tag (line 2839)

Replace line 2839:
```php
	<article id="story-<?php echo esc_attr( $story_id ); ?>" <?php post_class( 'fanfic-story-card', $story_id ); ?>>
```

With:
```php
	<article id="story-<?php echo esc_attr( $story_id ); ?>" <?php post_class( 'fanfic-story-card', $story_id ); ?> data-language="<?php echo esc_attr( $language_slug ); ?>" data-translation-group="<?php echo esc_attr( $translation_group_id ); ?>" data-views="<?php echo esc_attr( $story_views ); ?>">
```

#### 9c. Add translation indicator in footer (before `</footer>` at line 2913)

Before the `</footer>` closing tag at line 2913, add:

```php
				<?php if ( $translation_group_id ) :
					$translation_siblings = Fanfic_Translations::get_translation_siblings( $story_id );
					$translation_count = count( $translation_siblings );
					if ( $translation_count > 0 ) : ?>
						<div class="fanfic-story-translations-indicator">
							<span class="fanfic-translations-icon" aria-hidden="true">&#127760;</span>
							<?php
							printf(
								esc_html( _n( '%d translation', '%d translations', $translation_count, 'fanfiction-manager' ) ),
								$translation_count
							);
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
```

#### 9d. Add batch preloading in search results rendering

**File:** `includes/shortcodes/class-fanfic-shortcodes-search.php`

In `stories_story_archive()` method, after the `WP_Query` is executed (after line 587 `$stories_query = new WP_Query( $args );`), add preloading:

```php
			// Preload translation groups for all results to avoid N+1 queries
			if ( $stories_query instanceof WP_Query && $stories_query->have_posts() && class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
				$preload_ids = wp_list_pluck( $stories_query->posts, 'ID' );
				Fanfic_Translations::preload_groups( $preload_ids );
			}
```

Also add preloading in the AJAX handler:

**File:** `includes/class-fanfic-ajax-handlers.php`

After `$stories_query = new WP_Query( $query_args );` (line 1079), add:

```php
		// Preload translation groups for all results to avoid N+1 queries
		if ( $stories_query->have_posts() && class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
			$preload_ids = wp_list_pluck( $stories_query->posts, 'ID' );
			Fanfic_Translations::preload_groups( $preload_ids );
		}
```

---

### STEP 10: Search Deduplication JS

**File:** `assets/js/fanfic-search-bar-frontend.js`

Add the `TranslationDeduplicator` object. Find the end of the file where `$(document).ready()` or the IIFE initialization block is. Add the object definition before the init calls, then call its `init()` in the document ready block.

Add this object definition alongside the existing `SmartFilterManager` and `PillsManager` objects (around the top of the file, inside the IIFE):

```javascript
    // ===== TRANSLATION DEDUPLICATOR =====
    var TranslationDeduplicator = {
        /**
         * Initialize deduplication
         */
        init: function() {
            this.deduplicate();
        },

        /**
         * Get the user's browser language (2-letter code)
         */
        getBrowserLanguage: function() {
            var lang = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
            return lang.split('-')[0];
        },

        /**
         * Run deduplication on all story cards in the results
         */
        deduplicate: function() {
            var browserLang = this.getBrowserLanguage();
            var groups = {};

            // Collect all cards that belong to a translation group
            var cards = document.querySelectorAll('.fanfic-story-card[data-translation-group]');
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var groupId = card.getAttribute('data-translation-group');
                if (!groupId || groupId === '0' || groupId === '') {
                    continue;
                }
                if (!groups[groupId]) {
                    groups[groupId] = [];
                }
                groups[groupId].push(card);
            }

            // For each group with 2+ cards, show preferred version and hide others
            var self = this;
            Object.keys(groups).forEach(function(groupId) {
                var groupCards = groups[groupId];
                if (groupCards.length <= 1) {
                    return;
                }

                var preferred = self.selectPreferred(groupCards, browserLang);
                for (var j = 0; j < groupCards.length; j++) {
                    if (groupCards[j] === preferred) {
                        groupCards[j].style.display = '';
                        groupCards[j].classList.remove('fanfic-translation-hidden');
                    } else {
                        groupCards[j].style.display = 'none';
                        groupCards[j].classList.add('fanfic-translation-hidden');
                    }
                }
            });
        },

        /**
         * Select the preferred card from a group based on browser language
         * Priority: browser language > English > most views
         */
        selectPreferred: function(cards, browserLang) {
            // Priority 1: exact browser language match
            for (var i = 0; i < cards.length; i++) {
                if (cards[i].getAttribute('data-language') === browserLang) {
                    return cards[i];
                }
            }

            // Priority 2: English
            for (var j = 0; j < cards.length; j++) {
                if (cards[j].getAttribute('data-language') === 'en') {
                    return cards[j];
                }
            }

            // Priority 3: most views
            var sorted = cards.slice().sort(function(a, b) {
                return parseInt(b.getAttribute('data-views') || '0', 10) - parseInt(a.getAttribute('data-views') || '0', 10);
            });
            return sorted[0];
        }
    };
```

Then find the document ready / initialization section of the file and add:

```javascript
    // Initialize translation deduplication
    TranslationDeduplicator.init();
```

This should be called inside the existing `$(document).ready(function() { ... })` block or at the end of the IIFE initialization, wherever other initializers like `PillsManager.init()` are called.

---

### STEP 11: CSS Styles

**File:** `assets/css/fanfiction-frontend.css`

Add at the end of the file (before any closing media queries):

```css
/* ============================================
   TRANSLATION LINKS
   ============================================ */

/* Story View - Translation Banner */
.fanfic-story-translations {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 0.5em;
	padding: 0.75em 1em;
	margin: 0.75em 0;
	background: var(--fanfic-info-bg, #f0f6ff);
	border: 1px solid var(--fanfic-info-border, #c3d9f0);
	border-radius: 6px;
	font-size: 0.95em;
}

.fanfic-translations-icon {
	font-size: 1.2em;
	line-height: 1;
}

.fanfic-translation-link {
	font-weight: 600;
	text-decoration: none;
	color: var(--fanfic-link-color, #2271b1);
}

.fanfic-translation-link:hover {
	text-decoration: underline;
}

.fanfic-translations-dropdown {
	padding: 0.3em 0.5em;
	border: 1px solid var(--fanfic-info-border, #c3d9f0);
	border-radius: 4px;
	background: #fff;
	cursor: pointer;
	font-size: 0.95em;
}

/* Story Card - Translation Indicator */
.fanfic-story-translations-indicator {
	display: inline-flex;
	align-items: center;
	gap: 0.3em;
	font-size: 0.85em;
	color: #666;
	margin-top: 0.5em;
}

.fanfic-story-translations-indicator .fanfic-translations-icon {
	font-size: 1em;
}

/* Story Form - Translation Field */
.fanfic-translations-field .fanfic-translation-results {
	max-height: 200px;
	overflow-y: auto;
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fff;
	display: none;
	margin-top: 0.25em;
}

.fanfic-translations-field .fanfic-translation-results:not(:empty) {
	display: block;
}

.fanfic-translation-result {
	display: block;
	width: 100%;
	padding: 0.5em 0.75em;
	text-align: left;
	border: none;
	border-bottom: 1px solid #f0f0f0;
	background: none;
	cursor: pointer;
	font-size: 0.9em;
}

.fanfic-translation-result:last-child {
	border-bottom: none;
}

.fanfic-translation-result:hover {
	background: #f0f6ff;
}

.fanfic-translation-result.is-disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.fanfic-selected-translations {
	display: flex;
	flex-wrap: wrap;
	gap: 0.3em;
	margin-top: 0.5em;
}

.fanfic-selected-translation {
	display: inline-flex;
	align-items: center;
	gap: 0.3em;
	padding: 0.3em 0.6em;
	background: #e8f0fe;
	border-radius: 4px;
	font-size: 0.9em;
}

.fanfic-remove-translation {
	background: none;
	border: none;
	cursor: pointer;
	font-size: 1.1em;
	line-height: 1;
	color: #999;
	padding: 0 0.2em;
}

.fanfic-remove-translation:hover {
	color: #c00;
}

/* Hidden translation variant in search results (JS deduplication) */
.fanfic-translation-hidden {
	display: none !important;
}
```

---

### STEP 12: Cache Invalidation (Optional Enhancement)

**File:** `includes/class-fanfic-cache-hooks.php`

This is a nice-to-have. In `Fanfic_Translations`, after any group modification (in `add_to_group`, `remove_from_group`, `save_story_translations`), fire:

```php
do_action( 'fanfic_translations_updated', $story_id, $group_id );
```

Then in `class-fanfic-cache-hooks.php`, add a hook to clear relevant caches:

```php
add_action( 'fanfic_translations_updated', array( __CLASS__, 'invalidate_translation_caches' ), 10, 2 );

public static function invalidate_translation_caches( $story_id, $group_id ) {
    // Clear any story-related transients for all stories in the group
    if ( class_exists( 'Fanfic_Translations' ) && $group_id ) {
        $stories = Fanfic_Translations::get_group_stories( $group_id );
        foreach ( $stories as $sid ) {
            clean_post_cache( $sid );
        }
    }
}
```

---

## Verification Checklist

1. **Database:** Deactivate and reactivate plugin. Check that `wp_fanfic_story_translations` table exists in the database with correct schema (id, group_id, story_id columns).

2. **Story Form Field:** Create two stories with different languages (e.g., English and French). Edit the English story — verify "Linked Translations" field appears below the Language dropdown. It should NOT appear on the create form or when no language is set.

3. **REST Search:** In the translations field, type in the search box. It should show the French story. It should NOT show the current story or stories with the same language.

4. **Linking:** Select the French story and save. Both stories should now be in the same translation group.

5. **Bidirectional:** Edit the French story — the English story should appear pre-populated in the "Linked Translations" field.

6. **Same-language rejection:** Try to link two English stories — the REST endpoint should not return stories with the same language.

7. **Story View:** View either linked story — should see "Also available in: [Language]" with globe icon in the header area. Clicking the link should navigate to the other story.

8. **Story View (2+ translations):** Link a third story (e.g., Spanish). View any of the three — should see a dropdown instead of an inline link.

9. **Story Card:** On the browse/search page, linked stories should show "X translation(s)" indicator in the card footer.

10. **Search Dedup:** With browser set to French, browse stories — should see the French version of linked stories; English and Spanish versions should be hidden. With an unsupported language, should default to English version. If no english: translation with most views is chosn

11. **Unlinking:** Edit a story, remove a translation link, save. The removed story should no longer show as linked.

12. **Deletion:** Delete a linked story. The other stories in the group should still work (no errors, group cleaned up if < 2 members).

13. **No language set:** Verify that a story without a language set does NOT show the translations field and cannot be linked by other stories.
