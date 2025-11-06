# Testing Checklist for Implementation

Run these tests to verify all changes are working correctly:

## 1. Test Slug Synchronization
- [ ] Go to: WordPress Admin → Fanfiction Manager → URL Settings
- [ ] Change dashboard slug from "dashboard" to "test-dash"
- [ ] Save settings
- [ ] Visit: `/base_slug/test-dash/`
- [ ] Expected: Dashboard page loads (no 404)
- [ ] Verify database:
  ```sql
  SELECT option_value FROM wp_options WHERE option_name = 'fanfic_dynamic_page_slugs';
  -- Should show: {"dashboard":"test-dash",...}

  SELECT option_value FROM wp_options WHERE option_name = 'fanfic_secondary_paths';
  -- Should NOT exist or be empty
  ```

## 2. Test Members Directory
- [ ] Visit: `/base_slug/members/`
- [ ] Expected: List of authors with avatars and story counts
- [ ] Check: Pagination appears if more than 20 authors
- [ ] Check: Theme header/footer present

## 3. Test User Profiles
- [ ] Visit: `/base_slug/members/username/`
- [ ] Expected: User profile displays
- [ ] Check: Theme header/footer present
- [ ] Visit: `/base_slug/members/username/?action=edit` (logged in as that user)
- [ ] Expected: Edit form loads

## 4. Test Search Page
- [ ] Visit: `/base_slug/search/`
- [ ] Expected: Full page with theme header/footer
- [ ] Check: Search form displays and functions
- [ ] Verify: No `<p>[search-form]</p>` visible in source

## 5. Test Edit URLs
- [ ] Create a test story
- [ ] Click "Edit" on story
- [ ] Expected URL: `/base_slug/stories_slug/story-slug/?action=edit`
- [ ] Expected: Edit form loads correctly

## 6. Test Wizard Pages
- [ ] Run wizard to completion
- [ ] Check: No WordPress pages named "Edit Story", "Edit Chapter", "Edit Profile", "Create Chapter"
- [ ] Expected pages created: Main, Login, Register, Password Reset, Error, Maintenance (6 pages total)
- [ ] Verify database:
  ```sql
  SELECT post_title FROM wp_posts WHERE post_type = 'page';
  -- Should NOT include: Edit Story, Edit Chapter, Edit Profile, Create Chapter
  ```

## 7. Test Menu (Logged Out)
- [ ] Go to: Appearance → Menus
- [ ] View: "Fanfiction Automatic Menu"
- [ ] Expected visible items:
  - Home
  - Stories Archive
  - Members
  - Login
- [ ] Not visible:
  - Dashboard
  - Add Story
  - Logout

## 8. Test Menu (Logged In)
- [ ] Log in as author
- [ ] Refresh page
- [ ] Expected visible items:
  - Home
  - Dashboard
  - Add Story
  - Stories Archive
  - Members
  - Logout
- [ ] Not visible:
  - Login

## 9. Test Menu Custom Slugs
- [ ] Change dashboard slug to "dashboard-test"
- [ ] Run wizard again (or recreate menu)
- [ ] Expected: Dashboard menu item URL is `/base_slug/dashboard-test/`
- [ ] Click each menu item
- [ ] Expected: All links work correctly

## 10. Final Verification
- [ ] Check error logs for any PHP warnings/errors
- [ ] Test all dynamic pages work: dashboard, search, members, create-story
- [ ] Verify database shows `fanfic_dynamic_page_slugs` option exists
- [ ] Verify database shows `fanfic_secondary_paths` option does NOT exist
- [ ] Test changing slugs updates URLs immediately (no delay)

---

## Notes
- All tests should pass without errors
- If any test fails, check error logs and review the specific file changes
- Clear browser cache between tests if needed
