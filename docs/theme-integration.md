# Theme Template Override System

## How Developers Customize Templates
**CSS Customization:**
- Plugin CSS classes follow BEM naming: `.fanfic-story`, `.fanfic-story__title`, `.fanfic-story--featured`.
- Themes can override via additional CSS or child theme's style.css.
- Custom CSS can be added via Settings > Custom CSS page (applied to all plugin pages).

## Developer Hooks & Filters
**Action Hooks:**
- `fanfic_story_published` - Fires when story is published.
- `fanfic_chapter_published` - Fires when chapter is published.
- `fanfic_user_suspended` - Fires when user is suspended.
- `fanfic_user_unsuspended` - Fires when suspension lifted.
- `fanfic_report_submitted` - Fires when content is reported.
- `fanfic_comment_created` - Fires when comment posted.

**Filter Hooks:**
- `fanfic_story_title` - Modify story title before display.
- `fanfic_chapter_content` - Modify chapter content before display.
- `fanfic_author_bio` - Modify author bio output.
- `fanfic_story_rating_stars` - Customize rating display.
