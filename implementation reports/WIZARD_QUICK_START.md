# Setup Wizard - Quick Start Guide

## What Was Implemented

A complete 4-step setup wizard that runs on first plugin activation to configure:
1. Welcome and introduction
2. URL settings (base slug and secondary paths)
3. User role assignments (moderators and admins)
4. System page creation

## Files Created

```
includes/class-fanfic-wizard.php          (947 lines - Core wizard logic)
assets/css/fanfic-wizard.css              (426 lines - Wizard styling)
assets/js/fanfic-wizard.js                (322 lines - AJAX & validation)
```

## Files Modified

```
includes/class-fanfic-core.php            (Added wizard loading & init)
includes/class-fanfic-settings.php        (Added "Re-run Wizard" button)
```

## How to Test

### First Time Setup
1. Deactivate the plugin if already active
2. Delete these options from database (phpMyAdmin or WP-CLI):
   - `fanfic_wizard_completed`
   - `fanfic_show_wizard`
   - `fanfic_system_page_ids`
3. Activate the plugin
4. You should be **automatically redirected** to the wizard
5. Complete all 4 steps
6. Verify 12 pages created in **Pages → All Pages**

### Re-running the Wizard
1. Go to **Fanfiction → Settings**
2. Click **General** tab
3. Scroll to **Maintenance Actions** section
4. Click **Run Setup Wizard** button
5. Complete the wizard again

## Wizard Steps

### Step 1: Welcome
- Informational only
- Click "Next" to continue

### Step 2: URL Settings
- **Base Slug:** Default is `fanfiction` (e.g., `/fanfiction/`)
- **Secondary Paths:** Customize dashboard, user, search, author paths
- **Live Preview:** URLs update as you type
- Click "Next" (saves via AJAX)

### Step 3: User Roles
- Select users to assign as **Moderators** (optional)
- Select users to assign as **Admins** (optional)
- Hold Ctrl (Windows) or Cmd (Mac) for multiple selection
- Click "Next" (saves via AJAX)

### Step 4: Complete
- Review summary
- Click **Complete Setup**
- Confirm in dialog
- Wait for page creation (2-5 seconds)
- Automatic redirect to plugin dashboard

## Pages Created

After wizard completion, these pages are created:

1. `/fanfiction/` - Main page
2. `/fanfiction/login/` - Login page
3. `/fanfiction/register/` - Registration page
4. `/fanfiction/password-reset/` - Password reset
5. `/fanfiction/archive/` - Story archive
6. `/fanfiction/dashboard/` - User dashboard
7. `/fanfiction/create-story/` - Create story form
8. `/fanfiction/edit-profile/` - Edit profile page
9. `/fanfiction/search/` - Search results
10. `/fanfiction/error/` - Error page
11. `/fanfiction/maintenance/` - Maintenance page

## Database Options

### Permanent Options
- `fanfic_wizard_completed` - Set to `true` when wizard completes
- `fanfic_base_slug` - Stores base slug (e.g., "fanfiction")
- `fanfic_secondary_paths` - Stores array of secondary paths
- `fanfic_system_page_ids` - Stores created page IDs

### Temporary Options (deleted after completion)
- `fanfic_show_wizard` - Flag to trigger wizard redirect
- `fanfic_wizard_moderators` - User IDs to assign as moderators
- `fanfic_wizard_admins` - User IDs to assign as admins

## Security Features

- ✅ Nonce verification on all forms
- ✅ Capability checks (`manage_options`)
- ✅ Input sanitization (`sanitize_title()`)
- ✅ Output escaping (`esc_html()`, `esc_attr()`, `esc_url()`)
- ✅ AJAX CSRF protection

## Troubleshooting

### Wizard doesn't appear after activation
```bash
# Manually visit the wizard page:
wp-admin/admin.php?page=fanfic-setup-wizard

# Or reset the wizard:
DELETE FROM wp_options WHERE option_name = 'fanfic_wizard_completed';
```

### Pages not created
1. Check WordPress debug.log
2. Verify database write permissions
3. Check `wp_posts` table exists

### AJAX errors
1. Open browser console (F12)
2. Check for JavaScript errors
3. Verify `admin-ajax.php` is accessible

## WP-CLI Commands (Optional)

```bash
# Check wizard status
wp option get fanfic_wizard_completed

# Reset wizard
wp option delete fanfic_wizard_completed
wp option update fanfic_show_wizard 1

# Check created pages
wp option get fanfic_system_page_ids --format=json

# List all fanfic options
wp option list --search="fanfic_*" --fields=option_name,option_value
```

## Next Steps

1. ✅ Test wizard flow end-to-end
2. ✅ Verify all pages created
3. ✅ Check user roles assigned correctly
4. ✅ Test on different browsers
5. ✅ Test mobile responsiveness
6. Deploy to production

## Support

For issues or questions:
- Check browser console for JavaScript errors
- Check `debug.log` for PHP errors
- Review `WIZARD_IMPLEMENTATION_REPORT.md` for detailed documentation

## Key URLs

- **Wizard Page:** `wp-admin/admin.php?page=fanfic-setup-wizard`
- **Settings:** `wp-admin/admin.php?page=fanfiction-settings`
- **AJAX Endpoint:** `wp-admin/admin-ajax.php`

---

**Status:** ✅ Ready for Testing
**Date:** October 31, 2025
