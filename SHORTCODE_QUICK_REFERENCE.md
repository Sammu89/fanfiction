# New Shortcodes Quick Reference
## Fanfiction Manager v2.0 Interaction System

---

## Shortcode 1: Story Like Count

**Shortcode:** `[fanfiction-story-like-count]`

**Usage:**
```
[fanfiction-story-like-count]
[fanfiction-story-like-count id="123"]
```

**Output Examples:**
- "154 likes"
- "1 like"
- (empty if no likes)

---

## Shortcode 2: Story Rating Compact

**Shortcode:** `[fanfiction-story-rating-compact]`

**Attributes:**
- `id` - Story ID (optional, auto-detects)
- `format` - "short" or "long" (default: short)

**Usage:**
```
<!-- Short format: "4.45 ★" -->
[fanfiction-story-rating-compact]

<!-- Long format: "4.45 stars (23 ratings)" -->
[fanfiction-story-rating-compact format="long"]

<!-- Specific story -->
[fanfiction-story-rating-compact id="123" format="long"]
```

**Output Examples:**
- Short: "4.45 ★"
- Long: "4.45 stars (23 ratings)"
- No ratings: "Not rated"

---

## Shortcode 3: Action Buttons (Context-Aware)

**Shortcode:** `[fanfiction-action-buttons]`

**Attributes:**
- `context` - "story", "chapter", or "author" (optional, auto-detects)
- `actions` - Comma-separated list of actions (optional, shows all by default)

### Story Context Buttons:
- `bookmark` - Bookmark the story
- `subscribe` - Subscribe to story updates
- `share` - Share the story
- `report` - Report the story
- `edit` - Edit the story (only shown to author)

**Usage:**
```
<!-- All buttons for current context -->
[fanfiction-action-buttons]

<!-- Only bookmark and subscribe -->
[fanfiction-action-buttons actions="bookmark,subscribe"]

<!-- Force story context -->
[fanfiction-action-buttons context="story"]
```

### Chapter Context Buttons:
- `like` - Like the chapter
- `bookmark` - Bookmark the parent story
- `mark-read` - Mark chapter as read
- `subscribe` - Subscribe to parent story
- `share` - Share the chapter
- `report` - Report the chapter
- `edit` - Edit the chapter (only shown to author)

**Usage:**
```
<!-- Chapter buttons -->
[fanfiction-action-buttons]

<!-- Only like and mark-read -->
[fanfiction-action-buttons actions="like,mark-read"]
```

### Author Context Buttons:
- `follow` - Follow the author
- `share` - Share the author's profile

**Usage:**
```
<!-- Author buttons -->
[fanfiction-action-buttons context="author"]

<!-- Only follow button -->
[fanfiction-action-buttons context="author" actions="follow"]
```

---

## CSS Classes for Styling

### Containers:
```css
.fanfic-action-buttons { /* Main container */ }
.fanfic-action-buttons-story { /* Story context */ }
.fanfic-action-buttons-chapter { /* Chapter context */ }
.fanfic-action-buttons-author { /* Author context */ }
```

### Buttons:
```css
.fanfic-action-button { /* Base button */ }
.fanfic-bookmark-button { /* Bookmark button */ }
.fanfic-like-button { /* Like button */ }
.fanfic-follow-button { /* Follow button */ }
.fanfic-subscribe-button { /* Subscribe button */ }
.fanfic-mark-read-button { /* Mark as read button */ }
.fanfic-share-button { /* Share button */ }
.fanfic-report-button { /* Report button */ }
.fanfic-edit-button { /* Edit button */ }
```

### States:
```css
.is-active { /* Button is in active state */ }
.is-bookmarked { /* Story is bookmarked */ }
.is-liked { /* Chapter is liked */ }
.is-followd { /* Author is followed */ }
```

### Stats:
```css
.fanfic-like-count { /* Like count wrapper */ }
.fanfic-rating-compact { /* Rating wrapper */ }
.fanfic-rating-short { /* Short rating format */ }
.fanfic-rating-long { /* Long rating format */ }
```

---

## Common Use Cases

### Story Page Template:
```html
<div class="story-meta">
  [fanfiction-story-rating-compact format="long"]
  [fanfiction-story-like-count]
</div>

<div class="story-actions">
  [fanfiction-action-buttons actions="bookmark,subscribe,share"]
</div>
```

### Chapter Page Template:
```html
<div class="chapter-meta">
  [fanfiction-story-rating-compact]
</div>

<div class="chapter-actions">
  [fanfiction-action-buttons actions="like,mark-read,bookmark"]
</div>
```

### Author Archive Page:
```html
<div class="author-header">
  <h1>[author-name]</h1>
  [fanfiction-action-buttons context="author"]
</div>
```

### Story Card in Archive:
```html
<div class="story-card">
  <h3>[story-title]</h3>
  <div class="story-stats">
    [fanfiction-story-rating-compact]
    [fanfiction-story-like-count]
  </div>
  [fanfiction-action-buttons actions="bookmark"]
</div>
```

---

## Notes

- All shortcodes auto-detect context from current post
- Buttons automatically show current state (bookmarked, liked, etc.)
- All strings are translation-ready
- All buttons include ARIA labels for accessibility
- Guest users see buttons but may be prompted to login on click
