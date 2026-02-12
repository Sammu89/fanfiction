# Co-Author System — Full Implementation Plan

## Context

Add collaborative authorship to the Fanfiction Manager WordPress plugin. Authors can invite other users as co-authors on their stories, with an invitation/acceptance flow, granular permissions, and co-author display. The feature is toggleable via settings — when disabled, data is preserved in DB (soft disable) and restored when re-enabled.

### User Decisions
- Co-authors displayed alongside author: "by Author1, CoAuthor2, CoAuthor3"
- Pending invitations: invited user sees read-only preview in dashboard
- Chapter attribution: shows who actually wrote/last edited each chapter
- Notifications: original author notified on both accept and refuse
- Feature toggle: on/off in fanfiction settings with warning popup and notifications

---

## 1. Database Table

**File:** `includes/class-fanfic-database-setup.php`

Add `wp_fanfic_coauthors` table in `create_tables()` method (after existing tables):

```sql
CREATE TABLE IF NOT EXISTS {$prefix}fanfic_coauthors (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    story_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    invited_by bigint(20) UNSIGNED NOT NULL,
    status enum('pending','accepted','refused') NOT NULL DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    responded_at datetime DEFAULT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_story_user (story_id, user_id),
    KEY idx_user_status (user_id, status),
    KEY idx_story_status (story_id, status)
) $charset_collate;
```

**Additional changes in same file:**
- Add `$prefix . 'fanfic_coauthors'` to: `tables_exist()`, `drop_tables()`, `get_table_info()`, `optimize_tables()`, `repair_tables()`, `truncate_tables()` arrays
- Add `'total_coauthors'` key to `get_table_stats()`
- Bump `DB_VERSION` constant

---

## 2. Settings Toggle

**File:** `includes/class-fanfic-settings.php`

### Default setting
In `get_default_settings()` (~line 169), add:
```php
'enable_coauthors' => false,
```

### Sanitization
In `sanitize_settings()` (~line 254), add:
```php
$sanitized['enable_coauthors'] = isset( $settings['enable_coauthors'] ) && $settings['enable_coauthors'];
```

### Settings UI
In the General Settings tab rendering method (`render_general_settings_tab()`, around the Content Actions Features section ~line 1508), add a checkbox:

```html
<tr>
    <th scope="row"><?php esc_html_e( 'Co-Authors', 'fanfiction-manager' ); ?></th>
    <td>
        <label>
            <input type="checkbox" name="fanfic_settings[enable_coauthors]" value="1"
                id="fanfic_enable_coauthors"
                <?php checked( $settings['enable_coauthors'] ); ?> />
            <?php esc_html_e( 'Enable co-author functionality', 'fanfiction-manager' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Allow authors to invite co-authors to collaborate on stories.', 'fanfiction-manager' ); ?>
        </p>
    </td>
</tr>
```

### JavaScript warning popup on disable
In the admin settings page JS (or inline in the settings template), add:

```javascript
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById('fanfic_enable_coauthors');
    if (!checkbox) return;

    var wasChecked = checkbox.checked;

    checkbox.addEventListener('change', function() {
        if (wasChecked && !this.checked) {
            var confirmed = confirm(
                'Warning: Turning off co-authors will remove all co-author access to their stories. ' +
                'Every affected author and co-author will receive a notification. ' +
                'Co-author data will be preserved and can be restored by re-enabling this feature.'
            );
            if (!confirmed) {
                this.checked = true;
            }
        }
    });
});
```

### Notification dispatch on disable/enable
In `sanitize_settings()` or via a hook on `update_option_fanfic_settings`, detect when `enable_coauthors` changes:

```php
$old_settings = get_option( self::OPTION_NAME, self::get_default_settings() );
$was_enabled = ! empty( $old_settings['enable_coauthors'] );
$is_enabled  = ! empty( $sanitized['enable_coauthors'] );

if ( $was_enabled && ! $is_enabled && class_exists( 'Fanfic_Coauthors' ) ) {
    Fanfic_Coauthors::notify_feature_disabled();
}
if ( ! $was_enabled && $is_enabled && class_exists( 'Fanfic_Coauthors' ) ) {
    Fanfic_Coauthors::notify_feature_enabled();
}
```

---

## 3. New Class: `class-fanfic-coauthors.php`

**File:** `includes/class-fanfic-coauthors.php` (NEW FILE)

Static utility class following `Fanfic_Follows` / `Fanfic_Fandoms` patterns.

### Constants
```php
const STATUS_PENDING  = 'pending';
const STATUS_ACCEPTED = 'accepted';
const STATUS_REFUSED  = 'refused';
const MAX_COAUTHORS   = 5;
const REST_NAMESPACE  = 'fanfic/v1';
```

### Feature toggle
```php
public static function is_enabled() {
    if ( ! class_exists( 'Fanfic_Settings' ) ) {
        return false;
    }
    return (bool) Fanfic_Settings::get_setting( 'enable_coauthors', false );
}
```

### Initialization
```php
public static function init() {
    // Always register REST routes and cleanup hooks (data persists regardless of toggle)
    add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_coauthors' ) );

    // Only register interactive features when enabled
    if ( ! self::is_enabled() ) {
        return;
    }

    // WP_Query filter for dashboard
    add_filter( 'posts_where', array( __CLASS__, 'filter_dashboard_query' ), 10, 2 );

    // AJAX handlers for invitation management
    Fanfic_AJAX_Security::register_ajax_handler(
        'fanfic_invite_coauthor',
        array( __CLASS__, 'ajax_invite_coauthor' ),
        true,
        array( 'rate_limit' => true, 'capability' => 'edit_fanfiction_stories' )
    );
    Fanfic_AJAX_Security::register_ajax_handler(
        'fanfic_respond_coauthor',
        array( __CLASS__, 'ajax_respond_invitation' ),
        true,
        array( 'rate_limit' => true, 'capability' => 'read' )
    );
    Fanfic_AJAX_Security::register_ajax_handler(
        'fanfic_remove_coauthor',
        array( __CLASS__, 'ajax_remove_coauthor' ),
        true,
        array( 'rate_limit' => true, 'capability' => 'edit_fanfiction_stories' )
    );
}
```

### Core data methods

#### `invite_coauthor( $story_id, $user_id, $invited_by )`
- Insert row with `status='pending'`
- Create `coauthor_invite` notification for invited user
- Validations:
  - Story exists and is `fanfiction_story` post type
  - User exists via `get_user_by('ID', $user_id)`
  - Max coauthors not exceeded (count existing non-refused for story)
  - No self-invite (`$user_id !== $invited_by`)
  - No duplicate (UNIQUE KEY handles this, but check first for better error message)
  - Inviter is original author OR accepted co-author
  - Don't invite the original author (they're already the author)
- Use `$wpdb->insert()` with format specifiers `array('%d', '%d', '%d', '%s', '%s')`
- Return `array('success' => bool, 'message' => string)`

#### `accept_invitation( $story_id, $user_id )`
- Update: `SET status = 'accepted', responded_at = current_time('mysql') WHERE story_id = %d AND user_id = %d AND status = 'pending'`
- Send `coauthor_accepted` notification to original author (get via `get_post_field('post_author', $story_id)`)
- Notification data: `array('story_id' => $story_id, 'user_id' => $user_id, 'user_name' => $user->display_name)`
- Clear static cache
- Return success/failure

#### `refuse_invitation( $story_id, $user_id )`
- Update: `SET status = 'refused', responded_at = current_time('mysql') WHERE story_id = %d AND user_id = %d AND status = 'pending'`
- Send `coauthor_refused` notification to original author
- Clear static cache
- Return success/failure

#### `remove_coauthor( $story_id, $user_id, $removed_by )`
- **CRITICAL CHECK**: Block if `$user_id` equals `get_post_field('post_author', $story_id)` — cannot remove original author
- Delete row: `$wpdb->delete( $table, array('story_id' => $story_id, 'user_id' => $user_id) )`
- Send `coauthor_removed` notification to removed user
- Clear static cache
- Return success/failure

#### `get_story_coauthors( $story_id, $status = 'accepted' )`
```php
global $wpdb;
$table = $wpdb->prefix . 'fanfic_coauthors';
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT c.user_id, u.display_name, u.user_email
     FROM {$table} c
     INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
     WHERE c.story_id = %d AND c.status = %s
     ORDER BY c.created_at ASC",
    $story_id, $status
) );
// Convert to simple objects with ID, display_name properties
```

#### `get_all_story_coauthors( $story_id )`
Same as above but `WHERE c.status IN ('pending', 'accepted')` — excludes refused.

#### `get_user_coauthored_stories( $user_id, $status = 'accepted' )`
```php
$wpdb->get_col( $wpdb->prepare(
    "SELECT story_id FROM {$table} WHERE user_id = %d AND status = %s",
    $user_id, $status
) );
```
Returns array of story IDs (integers).

#### `get_pending_invitations( $user_id )`
```php
$wpdb->get_results( $wpdb->prepare(
    "SELECT c.story_id, c.invited_by, c.created_at,
            p.post_title AS story_title,
            u.display_name AS inviter_name
     FROM {$table} c
     INNER JOIN {$wpdb->posts} p ON c.story_id = p.ID
     INNER JOIN {$wpdb->users} u ON c.invited_by = u.ID
     WHERE c.user_id = %d AND c.status = 'pending'
     ORDER BY c.created_at DESC",
    $user_id
) );
```

#### `is_coauthor( $story_id, $user_id )`
**Uses static cache** to avoid repeated queries (critical for `map_meta_cap`):
```php
private static $coauthor_cache = array();

public static function is_coauthor( $story_id, $user_id ) {
    $key = $story_id . ':' . $user_id;
    if ( isset( self::$coauthor_cache[ $key ] ) ) {
        return self::$coauthor_cache[ $key ];
    }
    global $wpdb;
    $table = $wpdb->prefix . 'fanfic_coauthors';
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE story_id = %d AND user_id = %d AND status = 'accepted' LIMIT 1",
        absint( $story_id ), absint( $user_id )
    ) );
    self::$coauthor_cache[ $key ] = ! empty( $exists );
    return self::$coauthor_cache[ $key ];
}
```

#### `is_pending_coauthor( $story_id, $user_id )`
Same pattern but `status = 'pending'`. Use separate static cache.

#### `is_original_author( $story_id, $user_id )`
```php
return (int) get_post_field( 'post_author', $story_id ) === (int) $user_id;
```

#### `can_manage_coauthors( $story_id, $user_id )`
```php
return self::is_original_author( $story_id, $user_id ) || self::is_coauthor( $story_id, $user_id );
```

#### `cleanup_story_coauthors( $story_id )`
Called on `before_delete_post` hook. Only acts on `fanfiction_story` posts:
```php
$post = get_post( $story_id );
if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
    return;
}
$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );
```

### Feature toggle notification methods

#### `notify_feature_disabled()`
```php
global $wpdb;
$table = $wpdb->prefix . 'fanfic_coauthors';
// Get all unique user IDs affected (both co-authors and inviters)
$user_ids = $wpdb->get_col(
    "SELECT DISTINCT user_id FROM {$table} WHERE status IN ('pending', 'accepted')
     UNION
     SELECT DISTINCT invited_by FROM {$table} WHERE status IN ('pending', 'accepted')"
);
$message = __( 'Co-author functionality has been disabled by the site administrator. Your co-author relationships are preserved and will be restored if the feature is re-enabled.', 'fanfiction-manager' );
foreach ( $user_ids as $uid ) {
    Fanfic_Notifications::create_notification( absint( $uid ), 'coauthor_disabled', $message );
}
```

#### `notify_feature_enabled()`
Same pattern but only for `status = 'accepted'` rows, with re-enabled message.

### REST endpoint

```php
public static function register_rest_routes() {
    register_rest_route( self::REST_NAMESPACE, '/users/search', array(
        'methods'             => 'GET',
        'callback'            => array( __CLASS__, 'handle_user_search' ),
        'permission_callback' => function() {
            return is_user_logged_in() && current_user_can( 'edit_fanfiction_stories' );
        },
        'args' => array(
            'q'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            'story_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
            'limit'    => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
        ),
    ) );
}

public static function handle_user_search( $request ) {
    $query = $request->get_param( 'q' );
    $story_id = $request->get_param( 'story_id' );
    $limit = min( $request->get_param( 'limit' ), 50 );

    if ( strlen( $query ) < 2 ) {
        return new WP_REST_Response( array(), 200 );
    }

    // Build exclusion list
    $exclude = array( get_current_user_id() );

    if ( $story_id ) {
        // Exclude original author
        $original_author = (int) get_post_field( 'post_author', $story_id );
        if ( $original_author ) {
            $exclude[] = $original_author;
        }
        // Exclude already-invited users (any status)
        $existing = self::get_all_story_coauthors( $story_id );
        foreach ( $existing as $ca ) {
            $exclude[] = $ca->ID;
        }
    }

    $user_query = new WP_User_Query( array(
        'search'         => '*' . esc_attr( $query ) . '*',
        'search_columns' => array( 'display_name', 'user_login' ),
        'exclude'        => array_unique( $exclude ),
        'number'         => $limit,
        'orderby'        => 'display_name',
        'order'          => 'ASC',
    ) );

    $results = array();
    foreach ( $user_query->get_results() as $user ) {
        $results[] = array(
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
        );
    }

    return new WP_REST_Response( $results, 200 );
}
```

### AJAX handlers

#### `ajax_invite_coauthor()`
```php
public static function ajax_invite_coauthor() {
    $params = Fanfic_AJAX_Security::get_ajax_parameters(
        array( 'story_id', 'user_id' ),
        array()
    );
    if ( is_wp_error( $params ) ) {
        Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
    }

    $story_id = absint( $params['story_id'] );
    $user_id  = absint( $params['user_id'] );
    $current  = get_current_user_id();

    // Verify caller can manage co-authors
    if ( ! self::can_manage_coauthors( $story_id, $current ) ) {
        Fanfic_AJAX_Security::send_error_response( 'forbidden', __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ), 403 );
    }

    $result = self::invite_coauthor( $story_id, $user_id, $current );

    if ( $result['success'] ) {
        Fanfic_AJAX_Security::send_success_response( $result, __( 'Co-author invitation sent.', 'fanfiction-manager' ) );
    } else {
        Fanfic_AJAX_Security::send_error_response( 'invite_failed', $result['message'], 400 );
    }
}
```

#### `ajax_respond_invitation()`
```php
public static function ajax_respond_invitation() {
    $params = Fanfic_AJAX_Security::get_ajax_parameters(
        array( 'story_id', 'response' ),
        array()
    );
    if ( is_wp_error( $params ) ) {
        Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
    }

    $story_id = absint( $params['story_id'] );
    $response = sanitize_text_field( $params['response'] );
    $user_id  = get_current_user_id();

    if ( 'accept' === $response ) {
        $result = self::accept_invitation( $story_id, $user_id );
    } elseif ( 'refuse' === $response ) {
        $result = self::refuse_invitation( $story_id, $user_id );
    } else {
        Fanfic_AJAX_Security::send_error_response( 'invalid_response', __( 'Invalid response.', 'fanfiction-manager' ), 400 );
    }

    if ( $result ) {
        Fanfic_AJAX_Security::send_success_response( array(), __( 'Response recorded.', 'fanfiction-manager' ) );
    } else {
        Fanfic_AJAX_Security::send_error_response( 'response_failed', __( 'Failed to process response.', 'fanfiction-manager' ), 500 );
    }
}
```

#### `ajax_remove_coauthor()`
```php
public static function ajax_remove_coauthor() {
    $params = Fanfic_AJAX_Security::get_ajax_parameters(
        array( 'story_id', 'user_id' ),
        array()
    );
    if ( is_wp_error( $params ) ) {
        Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
    }

    $story_id = absint( $params['story_id'] );
    $user_id  = absint( $params['user_id'] );
    $current  = get_current_user_id();

    if ( ! self::can_manage_coauthors( $story_id, $current ) ) {
        Fanfic_AJAX_Security::send_error_response( 'forbidden', __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ), 403 );
    }

    $result = self::remove_coauthor( $story_id, $user_id, $current );

    if ( $result['success'] ) {
        Fanfic_AJAX_Security::send_success_response( $result, __( 'Co-author removed.', 'fanfiction-manager' ) );
    } else {
        Fanfic_AJAX_Security::send_error_response( 'remove_failed', $result['message'], 400 );
    }
}
```

### WP_Query filter for dashboard
```php
public static function filter_dashboard_query( $where, $query ) {
    if ( ! $query->get( 'fanfic_include_coauthored' ) ) {
        return $where;
    }
    global $wpdb;
    $coauthored_ids = $query->get( 'fanfic_include_coauthored' );
    if ( empty( $coauthored_ids ) ) {
        return $where;
    }
    $ids_str = implode( ',', array_map( 'absint', $coauthored_ids ) );
    $author_id = absint( $query->get( 'author' ) );
    $where = str_replace(
        "{$wpdb->posts}.post_author = {$author_id}",
        "({$wpdb->posts}.post_author = {$author_id} OR {$wpdb->posts}.ID IN ({$ids_str}))",
        $where
    );
    return $where;
}
```

---

## 4. Notification Types

**File:** `includes/class-fanfic-notifications.php`

### Add constants (~line 36, after existing TYPE_ constants):
```php
const TYPE_COAUTHOR_INVITE   = 'coauthor_invite';
const TYPE_COAUTHOR_ACCEPTED = 'coauthor_accepted';
const TYPE_COAUTHOR_REFUSED  = 'coauthor_refused';
const TYPE_COAUTHOR_REMOVED  = 'coauthor_removed';
const TYPE_COAUTHOR_DISABLED = 'coauthor_disabled';
const TYPE_COAUTHOR_ENABLED  = 'coauthor_enabled';
```

### Add to `$valid_types` array in `create_notification()` (~line 125):
Add all 6 new types to the existing array:
```php
$valid_types = array(
    self::TYPE_NEW_COMMENT,
    self::TYPE_NEW_FOLLOWER,
    self::TYPE_NEW_CHAPTER,
    self::TYPE_NEW_STORY,
    self::TYPE_COMMENT_REPLY,
    self::TYPE_STORY_UPDATE,
    self::TYPE_FOLLOW_STORY,
    // Co-author types
    self::TYPE_COAUTHOR_INVITE,
    self::TYPE_COAUTHOR_ACCEPTED,
    self::TYPE_COAUTHOR_REFUSED,
    self::TYPE_COAUTHOR_REMOVED,
    self::TYPE_COAUTHOR_DISABLED,
    self::TYPE_COAUTHOR_ENABLED,
);
```

### Notification messages (created from `Fanfic_Coauthors` class):
- **Invite**: "{UserName} is asking you to co-author '{StoryName}'" -> sent to invited user
- **Accepted**: "{UserName} accepted your co-author invitation for '{StoryName}'" -> sent to original author
- **Refused**: "{UserName} refused your co-author invitation for '{StoryName}'" -> sent to original author
- **Removed**: "You have been removed as co-author from '{StoryName}'" -> sent to removed user
- **Disabled**: "Co-author functionality has been disabled. Your co-author relationships are preserved." -> sent to all affected users
- **Enabled**: "Co-author functionality has been re-enabled. Your co-author relationships have been restored." -> sent to all affected users

---

## 5. Permission System

**File:** `includes/class-fanfic-roles-caps.php` — `map_meta_cap()` method

**IMPORTANT**: All co-author permission checks must be guarded with `Fanfic_Coauthors::is_enabled()`. When feature is disabled, co-authors have no special permissions.

### `edit_post` on `fanfiction_story` (~line 308):
After checking `post_author`, before falling through to `edit_others`:
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
     && Fanfic_Coauthors::is_coauthor( $post->ID, $user_id ) ) {
    return array( 'edit_fanfiction_stories' );
}
```

### `edit_post` on `fanfiction_chapter` (same block):
Check co-author of parent story:
```php
if ( 'fanfiction_chapter' === $post->post_type ) {
    $parent_story_id = $post->post_parent;
    if ( $parent_story_id && class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
         && Fanfic_Coauthors::is_coauthor( $parent_story_id, $user_id ) ) {
        return array( 'edit_fanfiction_chapters' );
    }
}
```

### `delete_post` on `fanfiction_story`:
**No change** — co-authors are not `post_author`, so they route to `delete_others_fanfiction_stories` which they don't have. Story deletion stays exclusive to original author.

### `delete_post` on `fanfiction_chapter`:
Co-authors CAN delete chapters. Add co-author check for parent story:
```php
if ( 'fanfiction_chapter' === $post->post_type ) {
    $parent_story_id = $post->post_parent;
    if ( $parent_story_id && class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
         && Fanfic_Coauthors::is_coauthor( $parent_story_id, $user_id ) ) {
        return array( 'delete_fanfiction_chapters' );
    }
}
```

### `read_post` on draft stories (~line 381):
Allow pending co-authors to read (preview):
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    if ( Fanfic_Coauthors::is_coauthor( $post->ID, $user_id )
         || Fanfic_Coauthors::is_pending_coauthor( $post->ID, $user_id ) ) {
        return array( 'read' );
    }
    // Also check for chapters of co-authored stories
    if ( 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
        if ( Fanfic_Coauthors::is_coauthor( $post->post_parent, $user_id )
             || Fanfic_Coauthors::is_pending_coauthor( $post->post_parent, $user_id ) ) {
            return array( 'read' );
        }
    }
}
```

---

## 6. Chapter Handler

**File:** `includes/handlers/class-fanfic-chapter-handler.php`

4 permission checks to update. Each currently checks:
```php
$story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' )
```

Change to (at lines ~215, ~451, ~748, ~805):
```php
$story->post_author != $current_user->ID
&& ! current_user_can( 'edit_others_posts' )
&& ! ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
       && Fanfic_Coauthors::is_coauthor( $story_id, $current_user->ID ) )
```

**Line references:**
- `handle_create_chapter_submission()` — line ~215
- `handle_edit_chapter_submission()` — line ~451
- `handle_delete_chapter()` — line ~748
- `ajax_delete_chapter()` — line ~805

---

## 7. Story Handler

**File:** `includes/handlers/class-fanfic-story-handler.php`

### Edit permission
Add co-author check alongside post_author check (same pattern as chapter handler).

### Delete permission
**Leave as-is** — existing check correctly prevents non-authors from deleting. Co-authors are not `post_author`.

### Co-author form processing
In `handle_create_story_submission()` — after story is created (after `wp_insert_post` succeeds), process co-authors:
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
     && ! empty( $_POST['fanfic_story_coauthors'] ) ) {
    $coauthor_ids = array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] );
    foreach ( $coauthor_ids as $ca_id ) {
        Fanfic_Coauthors::invite_coauthor( $story_id, $ca_id, get_current_user_id() );
    }
}
```

In `handle_edit_story_submission()` — diff current vs submitted:
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $submitted_ids = ! empty( $_POST['fanfic_story_coauthors'] )
        ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] )
        : array();

    $current_coauthors = Fanfic_Coauthors::get_all_story_coauthors( $story_id );
    $current_ids = wp_list_pluck( $current_coauthors, 'ID' );

    // New invitations
    $to_invite = array_diff( $submitted_ids, $current_ids );
    foreach ( $to_invite as $ca_id ) {
        Fanfic_Coauthors::invite_coauthor( $story_id, $ca_id, get_current_user_id() );
    }

    // Removals (but NEVER remove original author)
    $to_remove = array_diff( $current_ids, $submitted_ids );
    $original_author = (int) get_post_field( 'post_author', $story_id );
    foreach ( $to_remove as $ca_id ) {
        if ( (int) $ca_id !== $original_author ) {
            Fanfic_Coauthors::remove_coauthor( $story_id, $ca_id, get_current_user_id() );
        }
    }
}
```

---

## 8. Story Form Template

**File:** `templates/template-story-form.php`

### PHP data preparation (at top, where edit mode data is loaded):
```php
$current_coauthors = array();
if ( $is_edit_mode && class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $accepted = Fanfic_Coauthors::get_story_coauthors( $story_id, 'accepted' );
    $pending  = Fanfic_Coauthors::get_story_coauthors( $story_id, 'pending' );
    foreach ( $accepted as $ca ) {
        $current_coauthors[] = array( 'id' => $ca->ID, 'display_name' => $ca->display_name, 'status' => 'accepted' );
    }
    foreach ( $pending as $ca ) {
        $current_coauthors[] = array( 'id' => $ca->ID, 'display_name' => $ca->display_name, 'status' => 'pending' );
    }
}
```

### HTML field (after language section, ~line 549):
Guarded with `<?php if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) : ?>`:

```html
<!-- Co-Authors -->
<div class="fanfic-form-field fanfic-coauthors-field" data-max-coauthors="<?php echo esc_attr( Fanfic_Coauthors::MAX_COAUTHORS ); ?>">
    <label for="fanfic_coauthor_search"><?php esc_html_e( 'Co-Authors', 'fanfiction-manager' ); ?></label>
    <input type="text" id="fanfic_coauthor_search" class="fanfic-input" autocomplete="off"
        placeholder="<?php esc_attr_e( 'Search users...', 'fanfiction-manager' ); ?>" />
    <div class="fanfic-coauthor-results" role="listbox" aria-label="<?php esc_attr_e( 'User search results', 'fanfiction-manager' ); ?>"></div>
    <div class="fanfic-selected-coauthors" aria-live="polite">
        <?php foreach ( $current_coauthors as $coauthor ) : ?>
            <span class="fanfic-selected-coauthor" data-id="<?php echo esc_attr( $coauthor['id'] ); ?>">
                <?php echo get_avatar( $coauthor['id'], 20 ); ?>
                <?php echo esc_html( $coauthor['display_name'] ); ?>
                <?php if ( $coauthor['status'] === 'pending' ) : ?>
                    <span class="fanfic-coauthor-status-badge"><?php esc_html_e( 'Pending', 'fanfiction-manager' ); ?></span>
                <?php endif; ?>
                <button type="button" class="fanfic-remove-coauthor" aria-label="<?php esc_attr_e( 'Remove co-author', 'fanfiction-manager' ); ?>">&times;</button>
                <input type="hidden" name="fanfic_story_coauthors[]" value="<?php echo esc_attr( $coauthor['id'] ); ?>">
            </span>
        <?php endforeach; ?>
    </div>
    <p class="description"><?php esc_html_e( 'Invite co-authors to collaborate. They will receive an invitation notification. Search requires at least 2 characters.', 'fanfiction-manager' ); ?></p>
</div>
<?php endif; ?>
```

---

## 9. JavaScript: `fanfiction-coauthors.js`

**File:** `assets/js/fanfiction-coauthors.js` (NEW FILE)

Structural clone of `assets/js/fanfiction-fandoms.js`. Key differences:

| Fandoms | Co-Authors |
|---------|------------|
| `.fanfic-fandoms-field` | `.fanfic-coauthors-field` |
| `#fanfic_fandom_search` | `#fanfic_coauthor_search` |
| `.fanfic-fandom-results` | `.fanfic-coauthor-results` |
| `.fanfic-selected-fandoms` | `.fanfic-selected-coauthors` |
| `fanfic_story_fandoms[]` | `fanfic_story_coauthors[]` |
| `.fanfic-selected-fandom` | `.fanfic-selected-coauthor` |
| `.fanfic-remove-fandom` | `.fanfic-remove-coauthor` |
| `fanficFandoms.restUrl` | `fanficCoauthors.restUrl` |
| `fanficFandoms.restNonce` | `fanficCoauthors.restNonce` |
| `data-max-fandoms` | `data-max-coauthors` |

**Additional differences from fandoms.js:**
- REST URL points to `fanfic/v1/users/search`
- Pass `story_id` as query param: `url + '?q=' + query + '&story_id=' + storyId + '&limit=20'`
- Search results render with avatar: `<img src="item.avatar_url" class="fanfic-coauthor-avatar" /> <span>item.display_name</span>` (no count)
- Selected items include avatar via `get_avatar` (server-rendered for existing, JS-created for new)
- No "original work" checkbox logic (that's fandom-specific)
- `data-label` attribute stores `display_name` for selected items

### Localized data object:
```javascript
fanficCoauthors = {
    restUrl: '/wp-json/fanfic/v1/users/search',
    restNonce: 'wp_rest_nonce_value',
    maxCoauthors: 5,
    storyId: 0,  // current story ID in edit mode, 0 for create
    strings: {
        remove: 'Remove co-author'
    }
}
```

---

## 10. Dashboard JS: `fanfiction-coauthors-dashboard.js`

**File:** `assets/js/fanfiction-coauthors-dashboard.js` (NEW FILE)

Handles Accept/Refuse buttons on the dashboard invitation section:

```javascript
/* global fanficCoauthorsDashboard */
(function() {
    'use strict';

    function handleInvitation(storyId, response) {
        var formData = new FormData();
        formData.append('action', 'fanfic_respond_coauthor');
        formData.append('story_id', storyId);
        formData.append('response', response);
        formData.append('nonce', fanficCoauthorsDashboard.nonce);

        fetch(fanficCoauthorsDashboard.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var item = document.querySelector('.fanfic-invitation-item[data-story-id="' + storyId + '"]');
                if (item) item.remove();

                var list = document.querySelector('.fanfic-invitations-list');
                if (list && !list.children.length) {
                    var section = document.querySelector('.fanfic-dashboard-invitations');
                    if (section) section.remove();
                }
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('fanfic-accept-invitation')) {
            handleInvitation(e.target.dataset.storyId, 'accept');
        }
        if (e.target.classList.contains('fanfic-refuse-invitation')) {
            handleInvitation(e.target.dataset.storyId, 'refuse');
        }
    });
})();
```

Localized data: `fanficCoauthorsDashboard = { ajaxUrl, nonce }`

---

## 11. Script Enqueue

**File:** `includes/class-fanfic-core.php`

### Load class in `load_dependencies()` (~line 229, after `class-fanfic-follows.php`):
```php
require_once FANFIC_INCLUDES_DIR . 'class-fanfic-coauthors.php';
```

### Initialize in `init_hooks()`:
```php
Fanfic_Coauthors::init();
```

### Enqueue story form JS (in `enqueue_frontend_assets()`, after fandoms enqueue block):
Only on story form pages:
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    wp_enqueue_script( 'fanfiction-coauthors', FANFIC_PLUGIN_URL . 'assets/js/fanfiction-coauthors.js', array(), FANFIC_VERSION, true );

    $story_id = isset( $_GET['story_id'] ) ? absint( $_GET['story_id'] ) : 0;
    wp_localize_script( 'fanfiction-coauthors', 'fanficCoauthors', array(
        'restUrl'      => esc_url_raw( rest_url( 'fanfic/v1/users/search' ) ),
        'restNonce'    => wp_create_nonce( 'wp_rest' ),
        'maxCoauthors' => Fanfic_Coauthors::MAX_COAUTHORS,
        'storyId'      => $story_id,
        'strings'      => array( 'remove' => __( 'Remove co-author', 'fanfiction-manager' ) ),
    ) );
}
```

### Enqueue dashboard JS (on dashboard template pages):
```php
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    wp_enqueue_script( 'fanfiction-coauthors-dashboard', FANFIC_PLUGIN_URL . 'assets/js/fanfiction-coauthors-dashboard.js', array(), FANFIC_VERSION, true );
    wp_localize_script( 'fanfiction-coauthors-dashboard', 'fanficCoauthorsDashboard', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'fanfic_ajax_nonce' ),
    ) );
}
```

---

## 12. Dashboard Template

**File:** `templates/template-dashboard.php`

### 12a. Pending invitations section (BEFORE "Your Stories" section, ~line 183):

```php
<?php
$pending_invitations = array();
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $pending_invitations = Fanfic_Coauthors::get_pending_invitations( $user_id );
}
if ( ! empty( $pending_invitations ) ) :
?>
<section class="fanfic-dashboard-invitations" aria-labelledby="invitations-heading">
    <h2 id="invitations-heading"><?php esc_html_e( 'Co-Author Invitations', 'fanfiction-manager' ); ?></h2>
    <div class="fanfic-invitations-list">
        <?php foreach ( $pending_invitations as $invitation ) : ?>
            <div class="fanfic-invitation-item" data-story-id="<?php echo esc_attr( $invitation->story_id ); ?>">
                <p><?php printf(
                    /* translators: %1$s: inviter name, %2$s: story title link */
                    esc_html__( '%1$s is asking you to co-author "%2$s"', 'fanfiction-manager' ),
                    '<strong>' . esc_html( $invitation->inviter_name ) . '</strong>',
                    '<a href="' . esc_url( get_permalink( $invitation->story_id ) ) . '">' . esc_html( $invitation->story_title ) . '</a>'
                ); ?></p>
                <div class="fanfic-invitation-actions">
                    <button type="button" class="fanfic-button fanfic-accept-invitation" data-story-id="<?php echo esc_attr( $invitation->story_id ); ?>">
                        <?php esc_html_e( 'Accept', 'fanfiction-manager' ); ?>
                    </button>
                    <button type="button" class="fanfic-button danger fanfic-refuse-invitation" data-story-id="<?php echo esc_attr( $invitation->story_id ); ?>">
                        <?php esc_html_e( 'Refuse', 'fanfiction-manager' ); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
```

### 12b. Co-authored stories in "Your Stories" table

Modify the WP_Query (~line 193) to include co-authored stories:

```php
$coauthored_ids = array();
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $coauthored_ids = Fanfic_Coauthors::get_user_coauthored_stories( $user_id, 'accepted' );
}

$query_args = array(
    'post_type'      => 'fanfiction_story',
    'post_status'    => array( 'publish', 'draft', 'pending' ),
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'orderby'        => 'modified',
    'order'          => 'DESC',
);

if ( ! empty( $coauthored_ids ) ) {
    $query_args['author'] = $user_id;
    $query_args['fanfic_include_coauthored'] = $coauthored_ids;
} else {
    $query_args['author'] = $user_id;
}

$query = new WP_Query( $query_args );
```

### 12c. "Co-author" badge in story row (~line 260, after title):
```php
<?php if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled()
           && (int) get_post_field( 'post_author', $story_id ) !== $user_id ) : ?>
    <span class="fanfic-badge fanfic-badge-coauthor"><?php esc_html_e( 'Co-author', 'fanfiction-manager' ); ?></span>
<?php endif; ?>
```

### 12d. Hide Delete button for co-authored stories (~line 292):
Wrap the existing delete form with:
```php
<?php if ( (int) get_post_field( 'post_author', $story_id ) === $user_id ) : ?>
    <!-- existing delete form here -->
<?php endif; ?>
```

---

## 13. Author Profile — Separate Story Lists

**File:** `includes/shortcodes/class-fanfic-shortcodes-author.php`

### New shortcode: `[author-coauthored-stories]`
Register alongside existing shortcodes (~line 32):
```php
add_shortcode( 'author-coauthored-stories', array( __CLASS__, 'coauthored_stories' ) );
```

Implementation:
```php
public static function coauthored_stories( $atts ) {
    if ( ! class_exists( 'Fanfic_Coauthors' ) || ! Fanfic_Coauthors::is_enabled() ) {
        return '';
    }

    $atts = shortcode_atts( array(
        'author_id'       => 0,
        'author_username' => '',
        'limit'           => 10,
        'paginate'        => 'true',
    ), $atts );

    $author_id = self::resolve_author_id( $atts );
    if ( ! $author_id ) {
        return '';
    }

    $coauthored_ids = Fanfic_Coauthors::get_user_coauthored_stories( $author_id, 'accepted' );
    if ( empty( $coauthored_ids ) ) {
        return '';
    }

    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
    $query_args = array(
        'post_type'      => 'fanfiction_story',
        'post_status'    => 'publish',
        'post__in'       => $coauthored_ids,
        'posts_per_page' => absint( $atts['limit'] ),
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $stories = new WP_Query( $query_args );

    if ( ! $stories->have_posts() ) {
        wp_reset_postdata();
        return '';
    }

    $output = '<h2>' . esc_html__( 'Co-Authored Stories', 'fanfiction-manager' ) . '</h2>';
    $output .= '<div class="author-stories-list author-coauthored-stories-list">';

    while ( $stories->have_posts() ) {
        $stories->the_post();
        $output .= self::render_story_item( get_the_ID() );
    }

    $output .= '</div>';

    if ( 'true' === $atts['paginate'] ) {
        $output .= self::render_story_pagination( $stories );
    }

    wp_reset_postdata();
    return $output;
}
```

### Modify default profile template
**File:** `templates/template-profile-view.php`

In `fanfic_get_default_profile_view_template()`, add the co-authored stories section after the stories section:

```html
<div class="fanfic-profile-stories">
    <h2>Stories</h2>
    [author-story-list]
</div>

<div class="fanfic-profile-coauthored-stories">
    [author-coauthored-stories]
</div>
```

The `[author-coauthored-stories]` shortcode renders nothing when disabled or no results, so it's safe to always include.

**Note:** The shortcode renders its own `<h2>Co-Authored Stories</h2>` header only when there are results.

---

## 14. Display Changes

### 14a. Story view — `[story-author-link]` shortcode

**File:** `includes/shortcodes/class-fanfic-shortcodes-story.php`

Modify `story_author_link()` (~line 132):

```php
public static function story_author_link( $atts ) {
    $story_id = Fanfic_Shortcodes::get_current_story_id();
    if ( ! $story_id ) {
        return '';
    }

    $author_id = get_post_field( 'post_author', $story_id );
    $author_name = get_the_author_meta( 'display_name', $author_id );
    $author_url = fanfic_get_user_profile_url( $author_id );
    $links = array();
    $links[] = sprintf( '<a href="%s" class="story-author-link">%s</a>', esc_url( $author_url ), esc_html( $author_name ) );

    // Append co-authors when feature is enabled
    if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
        $coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, 'accepted' );
        foreach ( $coauthors as $ca ) {
            $ca_url = fanfic_get_user_profile_url( $ca->ID );
            $links[] = sprintf( '<a href="%s" class="story-author-link story-coauthor-link">%s</a>', esc_url( $ca_url ), esc_html( $ca->display_name ) );
        }
    }

    return implode( ', ', $links );
}
```

### 14b. Story card (search results / browse)

**File:** `includes/functions.php` — `fanfic_get_story_card_html()` (~line 2960)

Co-author data for story cards comes from the **preloaded search index** (see Section 16e) — NOT from per-card DB queries. The `fanfic_preload_story_card_index_data()` function batch-loads `coauthor_ids` and `coauthor_names` for all stories on the page in a single query, then `fanfic_get_story_card_html()` renders them from cache. See Section 16e for the full implementation.

### 14c. Chapter attribution

Chapters already use `post_author` which is set to `$current_user->ID` during creation/edit. This naturally shows who actually wrote each chapter. No code change needed.

---

## 15. CSS

**File:** `assets/css/fanfiction-frontend.css`

```css
/* === Co-Author Field (story form) === */
.fanfic-coauthor-results {
    /* Mirror .fanfic-fandom-results */
}
.fanfic-coauthor-result {
    display: flex;
    align-items: center;
    gap: 0.5em;
    cursor: pointer;
}
.fanfic-coauthor-result img {
    border-radius: 50%;
    width: 24px;
    height: 24px;
}
.fanfic-selected-coauthor {
    /* Mirror .fanfic-selected-fandom */
    display: inline-flex;
    align-items: center;
    gap: 0.25em;
}
.fanfic-selected-coauthor img {
    border-radius: 50%;
    width: 20px;
    height: 20px;
}
.fanfic-coauthor-status-badge {
    font-size: 0.75em;
    opacity: 0.7;
    font-style: italic;
}
.fanfic-remove-coauthor {
    /* Mirror .fanfic-remove-fandom */
}

/* === Co-Author Badge (dashboard) === */
.fanfic-badge-coauthor {
    background-color: #2271b1;
    color: #fff;
    font-size: 0.75em;
    padding: 0.1em 0.4em;
    border-radius: 3px;
    margin-left: 0.5em;
}

/* === Invitation Section (dashboard) === */
.fanfic-dashboard-invitations {
    margin-bottom: 2em;
    padding: 1em;
    background: #fff9e5;
    border: 1px solid #ffcc00;
    border-radius: 4px;
}
.fanfic-invitation-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75em 0;
    border-bottom: 1px solid #eee;
}
.fanfic-invitation-item:last-child {
    border-bottom: none;
}
.fanfic-invitation-actions {
    display: flex;
    gap: 0.5em;
    flex-shrink: 0;
}
```

---

## 16. Search Index Integration (CRITICAL)

The `wp_fanfic_story_search_index` table is the **single source of truth** for rendering story cards and executing searches. Co-author data MUST be indexed there.

### 16a. Schema Change — Search Index Table

**File:** `includes/class-fanfic-database-setup.php` (~line 430, in the search index CREATE TABLE)

Add two columns after `author_id`:
```sql
coauthor_ids VARCHAR(500) DEFAULT '',
coauthor_names VARCHAR(1000) DEFAULT '',
```

- `coauthor_ids`: comma-separated user IDs (e.g., `"12,45,78"`) — for building profile links
- `coauthor_names`: comma-separated display names (e.g., `"Jane Doe, Bob Smith"`) — for card rendering without extra queries

### 16b. Index Building — `build_index_text()`

**File:** `includes/class-fanfic-search-index.php` — `build_index_text()` (~line 234)

After the primary author name is added to `$parts`, append co-author names:
```php
// Add co-author names to indexed text (for FULLTEXT search by co-author name)
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, 'accepted' );
    foreach ( $coauthors as $ca ) {
        $parts[] = $ca->display_name;
    }
}
```

This ensures searching for a co-author's name finds the story.

### 16c. Index Update — `update_index()`

**File:** `includes/class-fanfic-search-index.php` — `update_index()` (~line 667)

In the data array built for `$wpdb->replace()`, add:
```php
$coauthor_ids_str   = '';
$coauthor_names_str = '';
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
    $coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, 'accepted' );
    if ( ! empty( $coauthors ) ) {
        $coauthor_ids_str   = implode( ',', wp_list_pluck( $coauthors, 'ID' ) );
        $coauthor_names_str = implode( ', ', wp_list_pluck( $coauthors, 'display_name' ) );
    }
}

// Add to the $data array for $wpdb->replace():
'coauthor_ids'   => $coauthor_ids_str,
'coauthor_names' => $coauthor_names_str,
```

### 16d. Story Card Preloading

**File:** `includes/functions.php` — `fanfic_preload_story_card_index_data()` (~line 2790)

Add `coauthor_ids` and `coauthor_names` to the SELECT query:
```sql
SELECT story_id, language_slug, translation_group_id, translation_count, view_count,
       coauthor_ids, coauthor_names
FROM {$table}
WHERE story_id IN (...)
```

Add to the cached array per story:
```php
'coauthor_ids'   => (string) ($row['coauthor_ids'] ?? ''),
'coauthor_names' => (string) ($row['coauthor_names'] ?? ''),
```

Update defaults in `fanfic_get_story_card_index_data()`:
```php
$defaults = array(
    'language_slug'        => '',
    'translation_group_id' => 0,
    'translation_count'    => 0,
    'view_count'           => 0,
    'coauthor_ids'         => '',
    'coauthor_names'       => '',
);
```

### 16e. Story Card Rendering

**File:** `includes/functions.php` — `fanfic_get_story_card_html()` (~line 2960)

Use preloaded index data (NOT per-card DB queries):
```php
$card_index_data = fanfic_get_story_card_index_data( $story_id );
$coauthor_names = $card_index_data['coauthor_names'];
$coauthor_ids_str = $card_index_data['coauthor_ids'];

// Build co-author links from preloaded data
$coauthor_html = '';
if ( ! empty( $coauthor_names ) && ! empty( $coauthor_ids_str ) ) {
    $ca_ids   = array_map( 'absint', explode( ',', $coauthor_ids_str ) );
    $ca_names = array_map( 'trim', explode( ',', $coauthor_names ) );
    foreach ( $ca_ids as $i => $ca_id ) {
        if ( isset( $ca_names[ $i ] ) ) {
            $coauthor_html .= ', <a href="' . esc_url( fanfic_get_user_profile_url( $ca_id ) ) . '">'
                . esc_html( $ca_names[ $i ] ) . '</a>';
        }
    }
}
```

Append `$coauthor_html` after the primary author link in the card output. This avoids N+1 queries.

### 16f. Re-indexing Hooks

**File:** `includes/class-fanfic-coauthors.php` — triggered from data methods

When co-author status changes, trigger search index update:
```php
// After accept_invitation(), remove_coauthor():
if ( class_exists( 'Fanfic_Search_Index' ) ) {
    Fanfic_Search_Index::update_index( $story_id );
}
```

Trigger re-indexing in:
- `accept_invitation()` — co-author added, re-index story
- `remove_coauthor()` — co-author removed, re-index story
- `notify_feature_disabled()` — re-index ALL stories with co-authors (clears co-author data from index)
- `notify_feature_enabled()` — re-index ALL stories with co-authors (restores co-author data)
- Co-author `profile_update` hook — if a co-author changes their display_name, re-index all their co-authored stories

For the profile_update hook, add in `init()`:
```php
add_action( 'profile_update', array( __CLASS__, 'on_coauthor_profile_update' ), 10, 2 );
```

```php
public static function on_coauthor_profile_update( $user_id, $old_user_data ) {
    if ( ! self::is_enabled() ) {
        return;
    }
    $new_name = get_the_author_meta( 'display_name', $user_id );
    $old_name = $old_user_data->display_name;
    if ( $new_name === $old_name ) {
        return;
    }
    // Re-index all stories where this user is a co-author
    $story_ids = self::get_user_coauthored_stories( $user_id, 'accepted' );
    foreach ( $story_ids as $sid ) {
        Fanfic_Search_Index::update_index( $sid );
    }
}
```

### 16g. Bulk Re-index on Feature Toggle

In `notify_feature_disabled()` and `notify_feature_enabled()`, after sending notifications:
```php
global $wpdb;
$table = $wpdb->prefix . 'fanfic_coauthors';
$story_ids = $wpdb->get_col(
    "SELECT DISTINCT story_id FROM {$table} WHERE status IN ('pending', 'accepted')"
);
if ( class_exists( 'Fanfic_Search_Index' ) ) {
    foreach ( $story_ids as $sid ) {
        Fanfic_Search_Index::update_index( absint( $sid ) );
    }
}
```

---

## 17. Performance Considerations

- **Static cache in `is_coauthor()`**: Use `private static $coauthor_cache = array()` to avoid repeated DB queries within a single request. Key: `"$story_id:$user_id"`. Critical because `map_meta_cap` is called frequently.
- **Story cards use preloaded index data**: Co-author names/IDs come from `fanfic_preload_story_card_index_data()` — zero additional queries per card.
- **Dashboard query**: Single `WP_Query` with `OR` clause via `posts_where` filter instead of two separate queries.

---

## Implementation Order

1. Database — co-authors table + search index columns (`class-fanfic-database-setup.php`)
2. Settings toggle (`class-fanfic-settings.php`)
3. Core class (`class-fanfic-coauthors.php` — new file)
4. Class loading + init (`class-fanfic-core.php`)
5. Notification types (`class-fanfic-notifications.php`)
6. Search index integration (`class-fanfic-search-index.php`)
7. Permission system (`class-fanfic-roles-caps.php`)
7. Chapter handler (`class-fanfic-chapter-handler.php`)
8. Story handler (`class-fanfic-story-handler.php`)
9. JavaScript — story form autocomplete (`fanfiction-coauthors.js` — new file)
10. JavaScript — dashboard invitations (`fanfiction-coauthors-dashboard.js` — new file)
11. Story form template (`template-story-form.php`)
12. Script enqueue (`class-fanfic-core.php`)
13. Dashboard template (`template-dashboard.php`)
14. Author profile shortcode + template (`class-fanfic-shortcodes-author.php`, `template-profile-view.php`)
15. Display changes — story view shortcode + story card (`class-fanfic-shortcodes-story.php`, `functions.php`)
16. CSS (`fanfiction-frontend.css`)

---

## Files Summary

### New Files (3)
| File | Purpose |
|------|---------|
| `includes/class-fanfic-coauthors.php` | Core co-author class with all data methods, REST endpoint, AJAX handlers |
| `assets/js/fanfiction-coauthors.js` | Autocomplete search for co-author selection in story form |
| `assets/js/fanfiction-coauthors-dashboard.js` | Accept/Refuse invitation buttons on dashboard |

### Modified Files (14)
| File | Changes |
|------|---------|
| `includes/class-fanfic-database-setup.php` | Add `wp_fanfic_coauthors` table + add `coauthor_ids`/`coauthor_names` columns to `wp_fanfic_story_search_index` |
| `includes/class-fanfic-settings.php` | Add `enable_coauthors` setting, sanitization, UI checkbox, disable warning JS, toggle notification dispatch |
| `includes/class-fanfic-core.php` | Load class, call init, enqueue JS files |
| `includes/class-fanfic-notifications.php` | Add 6 type constants to class and `$valid_types` array |
| `includes/class-fanfic-roles-caps.php` | Extend `map_meta_cap()` for co-author edit/delete/read permissions |
| `includes/handlers/class-fanfic-chapter-handler.php` | Update 4 permission checks to include co-author check |
| `includes/handlers/class-fanfic-story-handler.php` | Update edit permission + add co-author form processing in create/edit |
| `includes/class-fanfic-search-index.php` | Add co-author names to `build_index_text()` + store co-author IDs/names in `update_index()` |
| `templates/template-story-form.php` | Add co-author autocomplete field UI |
| `templates/template-dashboard.php` | Add invitation section + include co-authored stories in table + badge + hide delete |
| `includes/shortcodes/class-fanfic-shortcodes-author.php` | Add `[author-coauthored-stories]` shortcode |
| `includes/shortcodes/class-fanfic-shortcodes-story.php` | Modify `story_author_link()` to include co-authors |
| `includes/functions.php` | Update story card preload + card rendering to use index-cached co-author data |
| `assets/css/fanfiction-frontend.css` | Co-author field, badge, and invitation styling |
| `templates/template-profile-view.php` | Add `[author-coauthored-stories]` to default template |

---

## Verification Checklist

1. Activate plugin -> `wp_fanfic_coauthors` table is created
2. Settings -> Enable co-authors toggle -> feature activates
3. Create story as Author A -> co-author field appears -> search for Author B -> select -> save
4. Author B's dashboard -> pending invitation appears with Accept/Refuse
5. Author B accepts -> notification sent to Author A -> story appears in B's dashboard with "Co-author" badge
6. Author B can add/edit/delete chapters on the story
7. Author B CANNOT delete the story (no Delete button, server-side protection)
8. Author B can add Author C as co-author via story edit form
9. Author B CANNOT remove Author A (original author protection)
10. Story page shows "by Author A, Author B"
11. Story cards in search/browse show all co-authors
12. Author B refuses a new invitation -> notification sent to Author A
13. Author A removes Author B -> notification sent to Author B
14. Author profile page -> "Stories" section shows own stories, "Co-Authored Stories" section shows collaborations
15. Settings -> Disable co-authors -> warning popup -> confirm -> all affected users notified -> co-author fields hidden -> permissions revoked
16. Settings -> Re-enable co-authors -> all relationships restored -> notifications sent
