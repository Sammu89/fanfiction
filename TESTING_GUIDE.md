# Phase 9: Testing & Validation Guide

## Overview

This guide helps you verify that the fanfiction plugin is working correctly after completing Phases 1-8.

**Prerequisites:**
- ✅ Phases 1-7 completed (code migration)
- ✅ Phase 8 executed (database migration)
- ✅ Migration status shows completed

---

## 1. Database Migration Verification

### Check Migration Status
```sql
SELECT option_value FROM wp_options WHERE option_name = 'fanfic_migration_status';
```

**Expected:** JSON with `completed: "1.0.1"` and timestamp

### Verify Table Structures

**Bookmarks Table:**
```sql
SHOW COLUMNS FROM wp_fanfic_bookmarks;
```
Expected columns: `id`, `user_id`, `post_id`, `bookmark_type`, `created_at`

**Follows Table:**
```sql
SHOW COLUMNS FROM wp_fanfic_follows;
```
Expected columns: `id`, `user_id`, `target_id`, `follow_type`, `email_enabled`, `created_at`

**Reading Progress Table:**
```sql
SHOW COLUMNS FROM wp_fanfic_reading_progress;
```
Expected columns: `id`, `user_id`, `story_id`, `chapter_number`, `marked_at`

**Email Subscriptions Table:**
```sql
SHOW COLUMNS FROM wp_fanfic_email_subscriptions;
```
Expected columns: `id`, `email`, `target_id`, `subscription_type`, `token`, `verified`, `created_at`

### Verify Data Integrity

**Check bookmark counts:**
```sql
SELECT bookmark_type, COUNT(*) as count FROM wp_fanfic_bookmarks GROUP BY bookmark_type;
```
Expected: All existing bookmarks should show `bookmark_type = 'story'`

**Check follow counts:**
```sql
SELECT follow_type, COUNT(*) as count FROM wp_fanfic_follows GROUP BY follow_type;
```
Expected: All existing follows should show `follow_type = 'author'`

**Check reading progress:**
```sql
SELECT COUNT(*) as total_chapters_marked FROM wp_fanfic_reading_progress;
```
Expected: Number should be >= old count (each position converted to multiple chapters)

---

## 2. Frontend Testing

### Test Story Pages

**Visit a story page and verify:**
- [ ] Story title and metadata display correctly
- [ ] Story follow button appears (NEW!)
- [ ] Bookmark button appears and works
- [ ] Subscribe button appears and works
- [ ] Story rating display shows correctly
- [ ] Story like count displays

**Test interactions:**
1. Click "Follow" button → should toggle to "Following"
2. Click "Bookmark" button → should toggle to "Bookmarked"
3. Click "Subscribe" button → should show email form or confirmation

### Test Chapter Pages

**Visit a chapter page and verify:**
- [ ] Chapter content displays
- [ ] Like button appears and works
- [ ] Bookmark chapter button appears
- [ ] Mark as read button appears (NEW!)
- [ ] Rating stars appear (1-5)
- [ ] Navigation to next/previous chapter works

**Test interactions:**
1. Click a star rating (1-5) → should submit and update average
2. Click "Like" button → should toggle heart and update count
3. Click "Mark as Read" → should show as read (NEW!)
4. Bookmark the chapter → should save to bookmarks

### Test Author Pages

**Visit an author profile and verify:**
- [ ] Author follow button appears
- [ ] List of stories by author displays
- [ ] Author statistics show correctly

---

## 3. AJAX Endpoint Testing

### Test Rating System

**Test anonymous rating:**
1. Log out
2. Visit a chapter
3. Click a star rating
4. Check browser cookies → should see `fanfic_rate_{chapter_id}`
5. Refresh page → rating should persist

**Test authenticated rating:**
1. Log in
2. Rate a chapter
3. Check database: `SELECT * FROM wp_fanfic_ratings WHERE chapter_id = X`
4. Should see your user_id

### Test Like System

**Test anonymous like:**
1. Log out
2. Click like button on chapter
3. Check cookies → should see `fanfic_like_{chapter_id}`
4. Refresh → like should persist

**Test authenticated like:**
1. Log in
2. Click like button
3. Check database: `SELECT * FROM wp_fanfic_likes WHERE chapter_id = X`
4. Unlike → should remove from database

### Test Follow System (NEW!)

**Test story follow:**
1. Log in
2. Go to a story page
3. Click "Follow" button
4. Check database:
```sql
SELECT * FROM wp_fanfic_follows WHERE user_id = YOUR_ID AND follow_type = 'story';
```
5. Should see the story_id in target_id column

**Test author follow:**
1. Go to author profile
2. Click "Follow" button
3. Check database for `follow_type = 'author'`

### Test Reading Progress (NEW!)

**Test mark as read:**
1. Log in
2. Go to a chapter page
3. Click "Mark as Read" button
4. Check database:
```sql
SELECT * FROM wp_fanfic_reading_progress WHERE user_id = YOUR_ID;
```
5. Should see entry with chapter_number

**Test batch loading:**
1. Go to a story with multiple chapters
2. Mark several chapters as read
3. Refresh page
4. All marked chapters should show "Read" status

### Test Bookmark System

**Test story bookmark:**
1. Log in
2. Bookmark a story
3. Check database:
```sql
SELECT * FROM wp_fanfic_bookmarks WHERE user_id = YOUR_ID AND bookmark_type = 'story';
```

**Test chapter bookmark:**
1. Bookmark a chapter
2. Check database for `bookmark_type = 'chapter'`

### Test Email Subscriptions

**Test story subscription:**
1. Enter email on story page
2. Submit subscription form
3. Check database:
```sql
SELECT * FROM wp_fanfic_email_subscriptions WHERE subscription_type = 'story';
```
4. Check email for verification link
5. Click verification link
6. Check `verified = 1` in database

---

## 4. Security Testing

### Test AJAX Security

**Test nonce verification:**
1. Open browser console
2. Try to send AJAX request without nonce:
```javascript
jQuery.post('/wp-admin/admin-ajax.php', {
    action: 'fanfic_toggle_like',
    chapter_id: 123
});
```
3. Should receive error about invalid nonce

**Test rate limiting:**
1. Rapidly click like button 10+ times
2. Should see rate limit error after several attempts

**Test authentication:**
1. Log out
2. Try to access authenticated endpoints (bookmark, follow)
3. Should receive "must be logged in" error

### Test Input Validation

**Test invalid chapter ID:**
```javascript
jQuery.post('/wp-admin/admin-ajax.php', {
    action: 'fanfic_submit_rating',
    nonce: 'your-nonce',
    chapter_id: 999999,
    rating: 5
});
```
Expected: "Chapter not found" error

**Test invalid rating value:**
```javascript
jQuery.post('/wp-admin/admin-ajax.php', {
    action: 'fanfic_submit_rating',
    nonce: 'your-nonce',
    chapter_id: 123,
    rating: 10  // Invalid (should be 1-5)
});
```
Expected: Validation error

---

## 5. Performance Testing

### Test Batch Loading

**Reading progress batch load:**
1. Mark 20 chapters as read in one story
2. Visit story page
3. Check browser network tab
4. Should see ONLY 1 AJAX call to load all read chapters (not 20!)

**Bookmark batch load:**
1. Bookmark 10 stories
2. Visit bookmarks page
3. Should load all bookmarks in single paginated request

### Test Cache Performance

**Test reading progress cache:**
1. Mark chapter as read
2. Check if cache is set:
```php
wp_cache_get( 'read_chapters_{user_id}_{story_id}', 'fanfic' );
```
3. Subsequent loads should use cache (faster)

**Test rating/like counts cache:**
1. Submit rating
2. Check if counts are cached
3. Verify incremental updates work

---

## 6. Error Handling Testing

### Test Database Errors

**Simulate database error:**
1. Temporarily rename a table
2. Try to perform action (like, rate, etc.)
3. Should see graceful error message (not fatal error)
4. Check error logs for proper logging

### Test Network Errors

**Simulate timeout:**
1. Use browser dev tools → Network tab → Throttling
2. Set to "Slow 3G"
3. Try to submit rating
4. Should handle timeout gracefully with retry or error message

---

## 7. Compatibility Testing

### Test with Different Users

**Test as anonymous user:**
- [ ] Can view stories/chapters
- [ ] Can rate (cookie-based)
- [ ] Can like (cookie-based)
- [ ] Cannot bookmark
- [ ] Cannot follow
- [ ] Cannot mark as read

**Test as logged-in user:**
- [ ] Can do everything
- [ ] Ratings saved to database
- [ ] Likes saved to database
- [ ] Can manage bookmarks
- [ ] Can follow stories/authors
- [ ] Can track reading progress

### Test with Multiple Browsers

Test in:
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

Verify:
- [ ] Cookies work across browsers
- [ ] AJAX requests succeed
- [ ] UI displays correctly

---

## 8. Integration Testing

### Test Notification System

**Test follow notifications:**
1. User A follows User B (author)
2. User B publishes new story
3. Check database:
```sql
SELECT * FROM wp_fanfic_notifications WHERE user_id = A_ID;
```
4. User A should receive notification

**Test email notifications:**
1. User subscribes to story
2. New chapter published
3. Check email queue:
```sql
SELECT * FROM wp_fanfic_email_queue WHERE status = 'pending';
```
4. Email should be queued and sent

### Test Dashboard Integration

**Visit user dashboard and verify:**
- [ ] Bookmarked stories list displays
- [ ] Reading progress shows correctly
- [ ] Followed authors list displays
- [ ] Followed stories list displays (NEW!)
- [ ] Notification badge shows count
- [ ] All interactions work

---

## 9. Regression Testing

### Verify Old Functionality Still Works

**Story management:**
- [ ] Can create new story
- [ ] Can edit existing story
- [ ] Can delete story
- [ ] Can add chapters
- [ ] Can edit chapters
- [ ] Chapter numbering works correctly

**Search and filtering:**
- [ ] Search stories works
- [ ] Filter by rating works
- [ ] Filter by genre works
- [ ] Sort by date/popularity works

**Permissions:**
- [ ] Authors can only edit own stories
- [ ] Admins can edit all stories
- [ ] Proper capability checks in place

---

## 10. Final Checklist

### Code Quality
- [ ] No PHP errors in error log
- [ ] No JavaScript console errors
- [ ] All AJAX requests return proper JSON
- [ ] Proper HTTP status codes (200, 400, 404, etc.)

### Data Integrity
- [ ] No orphaned records in database
- [ ] Foreign key relationships intact
- [ ] Unique constraints working
- [ ] Timestamps being set correctly

### User Experience
- [ ] All buttons respond to clicks
- [ ] Loading states show during AJAX
- [ ] Success/error messages display
- [ ] No broken layouts
- [ ] Mobile responsive

### Performance
- [ ] Page load time < 3 seconds
- [ ] AJAX responses < 1 second
- [ ] No N+1 query problems
- [ ] Caching working effectively

### Security
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces verified
- [ ] Capabilities checked
- [ ] Rate limiting active

---

## Common Issues & Solutions

### Issue: "Migration already completed" error
**Solution:** Migration is idempotent and already ran. Check `fanfic_migration_status` option.

### Issue: Bookmarks not showing after migration
**Solution:** Check if `bookmark_type` column exists and is set to 'story':
```sql
SELECT * FROM wp_fanfic_bookmarks WHERE bookmark_type IS NULL;
```

### Issue: Follow button not appearing on story pages
**Solution:** Clear template cache, verify shortcode updated in Phase 6:
```php
[fanfiction-action-buttons context="story"]
```

### Issue: Reading progress not saving
**Solution:** Verify chapter_number is being passed in AJAX call. Check browser network tab for POST data.

### Issue: Anonymous likes/ratings not persisting
**Solution:** Check browser cookies. Ensure `setcookie()` is being called correctly with proper domain/path.

---

## Success Criteria

Phase 9 is considered **COMPLETE** when:

1. ✅ All database tables have correct schema
2. ✅ All existing data migrated successfully
3. ✅ All frontend interactions work
4. ✅ All AJAX endpoints respond correctly
5. ✅ Anonymous functionality works (cookies)
6. ✅ Authenticated functionality works (database)
7. ✅ No PHP/JS errors in logs/console
8. ✅ Performance is acceptable
9. ✅ Security measures are active
10. ✅ User experience is smooth

---

## Reporting Issues

If you find issues during testing:

1. **Document the issue:**
   - Steps to reproduce
   - Expected vs actual behavior
   - Browser/environment details
   - Error messages (PHP/JS)

2. **Check logs:**
   - PHP error log
   - JavaScript console
   - Database query log
   - AJAX request/response

3. **Verify data:**
   - Check database state
   - Check browser cookies
   - Check wp_options for settings

4. **Create bug report with:**
   - Issue description
   - Steps to reproduce
   - Actual results
   - Expected results
   - Relevant code/queries
   - Screenshots if applicable

---

**Ready to begin testing!** 🧪

Follow each section systematically and check off items as you verify them.
