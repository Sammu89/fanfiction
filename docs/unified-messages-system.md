# Unified Messages System

This document describes the unified notification/message system used throughout the Fanfiction Manager plugin.

## Overview

The unified messages system provides a consistent way to display notifications, alerts, success messages, and errors across all templates. All messages flow through a single container and use consistent styling and behavior.

## HTML Structure

### Messages Container

Every page that needs to display dynamic messages should include the messages container:

```html
<div id="fanfic-messages" class="fanfic-messages-container" role="region" aria-label="System Messages" aria-live="polite">
    <!-- Messages are inserted here -->
</div>
```

The container is empty and invisible by default (uses CSS `:empty` pseudo-selector). Messages appear with fade-in transitions when added.

### Individual Message Structure

```html
<div class="fanfic-message fanfic-message-{type}" role="status">
    <span class="fanfic-message-icon" aria-hidden="true">{icon}</span>
    <span class="fanfic-message-content">{message text}</span>
    <button class="fanfic-message-close" aria-label="Dismiss message">&times;</button>
</div>
```

**Components:**
- `.fanfic-message` - Base class for all messages
- `.fanfic-message-{type}` - Type modifier (success, error, warning, info)
- `.fanfic-message-icon` - Icon container (uses HTML entities)
- `.fanfic-message-content` - Message text container
- `.fanfic-message-close` - Dismiss button (optional)

## Message Types

| Type | Class | Icon | Use Case |
|------|-------|------|----------|
| Success | `.fanfic-message-success` | &#10003; (checkmark) | Successful operations |
| Error | `.fanfic-message-error` | &#10007; (X mark) | Errors, access denied |
| Warning | `.fanfic-message-warning` | &#9888; (warning triangle) | Warnings, draft notices |
| Info | `.fanfic-message-info` | &#8505; (info symbol) | Informational messages |

## CSS Classes Reference

### Container Classes

| Class | Description |
|-------|-------------|
| `.fanfic-messages-container` | Main container, hidden when empty |
| `#fanfic-messages` | ID selector for the primary messages container |

### Message Classes

| Class | Description |
|-------|-------------|
| `.fanfic-message` | Base message styling |
| `.fanfic-message-success` | Green success styling |
| `.fanfic-message-error` | Red error styling |
| `.fanfic-message-warning` | Orange/amber warning styling |
| `.fanfic-message-info` | Blue informational styling |
| `.fanfic-message-entering` | Animation class for fade-in |
| `.fanfic-message-fullpage` | Centered fullpage variant |

### Content Classes

| Class | Description |
|-------|-------------|
| `.fanfic-message-icon` | Icon container |
| `.fanfic-message-content` | Text content wrapper |
| `.fanfic-message-close` | Dismiss button |
| `.fanfic-message-title` | Bold title within content |
| `.fanfic-message-text` | Body text within content |
| `.fanfic-message-actions` | Button container |
| `.fanfic-message-detail` | Additional detail lines |
| `.fanfic-message-admin-notice` | Admin-only notice styling |

### Utility Classes

| Class | Description |
|-------|-------------|
| `.fanfic-empty-state` | Empty placeholder styling |
| `.fanfic-fullpage-message` | Container for fullpage messages |
| `.fanfic-draft-warning` | Draft status warning modifier |
| `.fanfic-blocked-notice` | Blocked content notice modifier |

## JavaScript API

The `FanficMessages` object provides a programmatic interface for managing messages.

### Methods

#### `FanficMessages.add(type, content, options)`

Add a message to the container.

```javascript
FanficMessages.add('success', 'Your story was saved!', {
    autoDismiss: true,
    duration: 5000
});
```

**Parameters:**
- `type` (string): 'success', 'error', 'warning', or 'info'
- `content` (string): Message text (can include HTML)
- `options` (object): Optional settings
  - `autoDismiss` (boolean): Auto-remove after duration (default: true for success/info)
  - `duration` (number): Milliseconds before auto-dismiss (default: 8000)

**Returns:** Message ID (string)

#### `FanficMessages.success(content, options)`

Shorthand for success messages.

```javascript
FanficMessages.success('Profile updated successfully!');
```

#### `FanficMessages.error(content, options)`

Shorthand for error messages. Errors do not auto-dismiss by default.

```javascript
FanficMessages.error('Failed to save changes. Please try again.');
```

#### `FanficMessages.warning(content, options)`

Shorthand for warning messages.

```javascript
FanficMessages.warning('Your session will expire in 5 minutes.');
```

#### `FanficMessages.info(content, options)`

Shorthand for informational messages.

```javascript
FanficMessages.info('Tip: You can drag chapters to reorder them.');
```

#### `FanficMessages.remove(messageId)`

Remove a specific message by ID.

```javascript
const id = FanficMessages.success('Saved!');
// Later...
FanficMessages.remove(id);
```

#### `FanficMessages.clear()`

Remove all messages from the container.

```javascript
FanficMessages.clear();
```

### Example: AJAX Response Handling

```javascript
fetch('/api/save-story', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            FanficMessages.success(data.message);
        } else {
            FanficMessages.error(data.error, { autoDismiss: false });
        }
    })
    .catch(() => {
        FanficMessages.error('Network error. Please try again.');
    });
```

## PHP Usage

### Server-Side Messages via URL Parameters

Messages can be triggered via URL parameters that are checked on page load:

```php
// Redirect with success message
wp_redirect( add_query_arg( 'success', 'story_created', $dashboard_url ) );

// Redirect with error message
wp_redirect( add_query_arg( 'error', 'Permission denied', $dashboard_url ) );
```

### Template Integration

```php
<!-- Unified Messages Container -->
<div id="fanfic-messages" class="fanfic-messages-container" role="region"
     aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>"
     aria-live="polite">
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'story_created' ) : ?>
    <div class="fanfic-message fanfic-message-success" role="status">
        <span class="fanfic-message-icon" aria-hidden="true">&#10003;</span>
        <span class="fanfic-message-content">
            <?php esc_html_e( 'Story created successfully!', 'fanfiction-manager' ); ?>
        </span>
        <button class="fanfic-message-close"
                aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">
            &times;
        </button>
    </div>
<?php endif; ?>
</div>
```

### Inline Error States

For access denied or error pages that don't use the container:

```php
<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
    <span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
    <span class="fanfic-message-content">
        <?php esc_html_e( 'You must be logged in.', 'fanfiction-manager' ); ?>
        <a href="<?php echo esc_url( wp_login_url() ); ?>" class="fanfic-button fanfic-button-primary">
            <?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
        </a>
    </span>
</div>
```

## Fullpage Messages

For error pages, maintenance pages, and access denied screens, use the fullpage variant:

```php
<section class="fanfic-content-section fanfic-fullpage-message" role="alert">
    <div class="fanfic-message fanfic-message-error fanfic-message-fullpage">
        <span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
        <span class="fanfic-message-content">
            <strong class="fanfic-message-title">Error</strong>
            <span class="fanfic-message-text">Something went wrong.</span>
            <span class="fanfic-message-actions">
                <a href="/" class="fanfic-button fanfic-button-primary">Go Home</a>
            </span>
        </span>
    </div>
</section>
```

## Accessibility

The message system follows accessibility best practices:

- **`role="region"`** on container with `aria-label`
- **`aria-live="polite"`** for non-urgent messages (container)
- **`aria-live="assertive"`** for error messages
- **`role="status"`** for success/info messages
- **`role="alert"`** for error messages
- **`aria-hidden="true"`** on decorative icons
- **`aria-label`** on close buttons
- Keyboard accessible dismiss buttons
- Sufficient color contrast for all message types

## Animation

Messages use CSS transitions for smooth appearance/removal:

```css
@keyframes fanfic-message-fade-in {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fanfic-message-entering {
    animation: fanfic-message-fade-in 0.3s ease forwards;
}
```

When removing messages via JavaScript, apply fade-out before removal:

```javascript
message.style.opacity = '0';
message.style.transform = 'translateY(-10px)';
setTimeout(() => message.remove(), 300);
```

## Migration from Legacy System

The following legacy classes have been replaced:

| Legacy Class | New Class |
|--------------|-----------|
| `.fanfic-info-box` | `.fanfic-message` |
| `.fanfic-error-notice` | `.fanfic-message.fanfic-message-error` |
| `.box-success` | `.fanfic-message-success` |
| `.box-error` | `.fanfic-message-error` |
| `.box-warning` | `.fanfic-message-warning` |
| `.box-info` | `.fanfic-message-info` |
| `#fanfic-notice-stack` | `#fanfic-messages` |
| `.fanfic-floating-notice` | `.fanfic-message` |
| `.fanfic-notice-close` | `.fanfic-message-close` |

## Files

- **CSS**: `assets/css/fanfiction-frontend.css` (search for "Unified Messages System")
- **JavaScript**: `assets/js/fanfiction-frontend.js` (FanficMessages module)
- **Templates**: All templates in `templates/` directory use this system
