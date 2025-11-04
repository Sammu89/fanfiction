# Accessibility, SEO, & UI/UX

## Accessibility Standards (WCAG 2.1 AA)
The plugin targets WCAG 2.1 AA compliance:

**Keyboard Navigation:**
- All interactive elements are keyboard-accessible (buttons, links, form inputs).
- Tab order is logical and follows visual flow.
- Chapter navigation: Arrow keys (left/right) move between chapters.
- Focus indicators: Visible outline around focused elements.

**Screen Reader Support:**
- All images have alt text (or are marked as decorative).
- Form labels properly associated with inputs.
- ARIA attributes used where appropriate:
  - `role="navigation"` on [chapters-nav].
  - `role="search"` on search form.
  - `aria-label` for icon-only buttons.
  - `aria-current="page"` on current chapter in navigation.

The [story-chapters-dropdown] shortcode (and other dropdowns like sorting filters) uses ARIA attributes (aria-expanded="true" when open, aria-expanded="false" when closed) to announce “open” or “closed” states to screen readers. This ensures accessibility for keyboard and screen reader users navigating chapter selectors.

**Forms:**
- All form inputs have associated labels.
- Error messages are clear and linked to inputs.
- Required fields marked with aria-required="true".
- Password requirements clearly communicated.

## Responsive Design
**Mobile Breakpoints:**
- 320px (small phones) - single column, large touch targets.
- 768px (tablets) - two-column layout where appropriate.
- 1024px (desktops) - full layout with sidebar.

**Mobile-First Features:**
- Touch-friendly buttons (minimum 44px tap target).
- Collapsible menus on mobile.
- Readable font sizes (16px minimum).
- Single-column content on phones.

## User Experience Features
**Navigation:**
- Persistent chapter navigation (top and bottom of page).
- Breadcrumb trail showing current location.
- "Next Chapter" / "Previous Chapter" buttons clearly visible.
- Chapter dropdown for quick jumping.

**Loading & Feedback:**
- Loading spinners on AJAX requests.
- Success/error messages on form submission.
- Disabled button states when action is processing.
- Progress indicators for multi-step processes.

## SEO Features
**Meta Tags:**
- `<title>` - Story title + site name.
- `<meta name="description">` - Story intro.
- `<meta name="keywords">` - Auto-generated from genres and custom taxonomies.

**Open Graph Tags (for social media sharing):**
- `<meta property="og:title">` - Story title.
- `<meta property="og:description">` - Story introduction.
- `<meta property="og:image">` - Featured image URL (or site logo if none).
- `<meta property="og:url">` - Story URL.
- `<meta property="og:type">` - "article".

**Twitter Card Tags:**
- `<twitter:card>` - "summary_large_image".
- `<twitter:title>` - Story title.
- `<twitter:description>` - Story introduction.
- `<twitter:image>` - Featured image.

**Structured Data (Schema.org):**
- Stories marked as Schema "Article" type with:
  - author (author name).
  - datePublished (publication date).
  - dateModified (last update date).
  - image (featured image).
  - keywords (genres + taxonomies).

**Canonical Tags:**
- Each story/chapter has `<link rel="canonical">` pointing to its own URL.
- Prevents duplicate content issues across mirrors or alternate URLs.

**Indexation:**
- All public stories and chapters are indexed.
- Draft stories use `<meta name="robots" content="noindex">` to prevent indexing.
- Author archive pages are indexed.
- Archive pages are indexed but marked crawlable.

**Sitemap:**
- Plugin registers custom post types in WordPress sitemap.
- Generates sitemap entries for all public stories and chapters.
- Submitted via Search Console for faster indexing.