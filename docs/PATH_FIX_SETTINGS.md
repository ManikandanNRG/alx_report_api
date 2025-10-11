# Path Fix: settings.php Admin Menu Links

## Date: 2025-10-10

## Problem
Getting 404 errors when clicking menu links to:
- Control Center
- Monitoring & Analytics

Error in console:
```
GET https://target.betterworklearning.com/local/alx_report_api/export_data.php 404 (Not Found)
```

## Root Cause
The admin menu links in `settings.php` had incorrect folder paths:
- ❌ **Wrong**: `/local/alx_report_api/`
- ✅ **Correct**: `/local/local_alx_report_api/`

The plugin folder is named `local_alx_report_api` (with "local_" prefix), not just `alx_report_api`.

## Files Fixed

### settings.php (Lines 48 & 54)

**Before:**
```php
new moodle_url('/local/alx_report_api/control_center.php'),
$CFG->wwwroot . '/local/alx_report_api/monitoring_dashboard_new.php',
```

**After:**
```php
new moodle_url('/local/local_alx_report_api/control_center.php'),
$CFG->wwwroot . '/local/local_alx_report_api/monitoring_dashboard_new.php',
```

## What Was Fixed
1. ✅ Control Center menu link path
2. ✅ Monitoring & Analytics menu link path

## Testing
After uploading the fixed `settings.php`:

1. **Clear Moodle cache**: Site Administration → Development → Purge all caches
2. **Test Control Center link**: Click "🎛️ Control Center" in admin menu
3. **Test Monitoring link**: Click "📊 Monitoring & Analytics" in admin menu
4. Both should load without 404 errors

## Related Files Also Fixed
- `export_data.php` - CSS path and page URL (already fixed earlier)

## Note
This was a naming inconsistency issue. The folder is `local_alx_report_api` but some links were using `alx_report_api` without the "local_" prefix.
