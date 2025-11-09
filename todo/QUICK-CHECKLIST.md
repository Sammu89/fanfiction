# Quick Implementation Checklist

## Phase 1: Quick Wins ✓
- [ ] Remove debug code from `template-edit-story.php` (lines 38-66)
- [ ] Remove Block Editor comments from 9 templates
- [ ] Add access control to `template-edit-profile.php`

## Phase 2: High Priority Shortcodes (Edit Buttons)
- [ ] Implement `[edit-story-button]` in `class-fanfic-shortcodes-utility.php`
- [ ] Implement `[edit-chapter-button]` in `class-fanfic-shortcodes-utility.php`
- [ ] Implement `[edit-author-button]` in `class-fanfic-shortcodes-utility.php`
- [ ] Implement `[story-chapters-dropdown]` in `class-fanfic-shortcodes-navigation.php`
- [ ] Add `[edit-story-button]` to `single-fanfiction_story.php`
- [ ] Add `[edit-chapter-button]` to `template-chapter-view.php`
- [ ] Add `[edit-author-button]` to `template-members.php`

## Phase 3: Author Profile Shortcodes
- [ ] Implement `[author-average-rating]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-story-list]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-stories-grid]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-completed-stories]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-ongoing-stories]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-featured-stories]` in `class-fanfic-shortcodes-author.php`
- [ ] Implement `[author-follow-list]` in `class-fanfic-shortcodes-author.php`
- [ ] Enhance `template-members.php` with new shortcodes

## Phase 4: Dashboard & Stats Shortcodes
- [ ] Implement `[most-bookmarked-stories]` in `class-fanfic-shortcodes-stats.php`
- [ ] Implement `[most-followed-authors]` in `class-fanfic-shortcodes-stats.php`
- [ ] Add to `template-dashboard.php`
- [ ] Add to `template-dashboard-author.php`

## Phase 5: Moderation Features
- [ ] Implement `[report-content]` in `class-fanfic-shortcodes-forms.php`
- [ ] Integrate reCAPTCHA v2
- [ ] Create report submission handler
- [ ] Add to `single-fanfiction_story.php`
- [ ] Add to `template-chapter-view.php`

## Phase 6: User Management Shortcodes
- [ ] Implement `[user-ban]` in `class-fanfic-shortcodes-user.php`
- [ ] Implement `[user-moderator]` in `class-fanfic-shortcodes-user.php`
- [ ] Implement `[user-demoderator]` in `class-fanfic-shortcodes-user.php`
- [ ] Add to admin/moderation interface

## Phase 7: Testing
- [ ] Verify all shortcodes are registered
- [ ] Test each shortcode functionality
- [ ] Test permission logic
- [ ] Test form submissions
- [ ] Verify caching
- [ ] Test responsive layouts
- [ ] Accessibility check
- [ ] Cross-browser testing

---

## Missing Shortcodes Summary

### Interactive (4)
1. `[edit-story-button]`
2. `[edit-chapter-button]`
3. `[edit-author-button]`
4. `[story-chapters-dropdown]`

### Author Profile (7)
5. `[author-average-rating]`
6. `[author-story-list]`
7. `[author-stories-grid]`
8. `[author-completed-stories]`
9. `[author-ongoing-stories]`
10. `[author-featured-stories]`
11. `[author-follow-list]`

### User Management (3)
12. `[user-ban]`
13. `[user-moderator]`
14. `[user-demoderator]`

### Dashboard (2)
15. `[most-bookmarked-stories]`
16. `[most-followed-authors]`

### Moderation (1)
17. `[report-content]`

### Possible (1)
18. `[story-chapters-list]` - Verify if already exists

**Total: 18 missing shortcodes**

---

## File Quick Reference

### Shortcode Files to Edit
```
includes/shortcodes/class-fanfic-shortcodes-utility.php      (3 shortcodes)
includes/shortcodes/class-fanfic-shortcodes-navigation.php   (1 shortcode)
includes/shortcodes/class-fanfic-shortcodes-author.php       (7 shortcodes)
includes/shortcodes/class-fanfic-shortcodes-stats.php        (2 shortcodes)
includes/shortcodes/class-fanfic-shortcodes-forms.php        (1 shortcode)
includes/shortcodes/class-fanfic-shortcodes-user.php         (3 shortcodes)
```

### Template Files to Edit
```
templates/template-edit-story.php           (cleanup)
templates/single-fanfiction_story.php       (add edit button)
templates/template-chapter-view.php         (add edit button)
templates/template-members.php              (enhance)
templates/template-dashboard.php            (add stats)
templates/template-dashboard-author.php     (add stats)
templates/template-edit-profile.php         (add access control)
+ 8 more templates for Block Editor comment removal
```

---

## Commands for Quick Navigation

```bash
# Navigate to plugin directory
cd C:\xampp\htdocs\smpt\wp-content\plugins\fanfiction

# Edit shortcode files
code includes/shortcodes/class-fanfic-shortcodes-utility.php
code includes/shortcodes/class-fanfic-shortcodes-navigation.php
code includes/shortcodes/class-fanfic-shortcodes-author.php
code includes/shortcodes/class-fanfic-shortcodes-stats.php
code includes/shortcodes/class-fanfic-shortcodes-forms.php
code includes/shortcodes/class-fanfic-shortcodes-user.php

# Edit template files
code templates/template-edit-story.php
code templates/single-fanfiction_story.php
code templates/template-chapter-view.php
code templates/template-members.php
```

---

**Last Updated**: 2025-11-05
