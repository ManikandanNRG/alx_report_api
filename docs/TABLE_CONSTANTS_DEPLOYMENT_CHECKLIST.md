# Table Constants - Deployment Checklist ✅

## Status: Ready to Deploy

**Date:** 2025-10-12  
**Control Center:** ✅ TESTED AND WORKING

---

## Files to Copy to Server (14 files)

### ✅ Already Copied (Working):
1. **classes/constants.php** - NEW FILE
2. **lib.php** - MODIFIED
3. **control_center.php** - MODIFIED ✅ TESTED

### 📋 Remaining Files to Copy (11 files):

4. **externallib.php** - MODIFIED
5. **monitoring_dashboard.php** - MODIFIED
6. **monitoring_dashboard_new.php** - MODIFIED
7. **advanced_monitoring.php** - MODIFIED
8. **auto_sync_status.php** - MODIFIED
9. **test_connection.php** - MODIFIED
10. **test_email_alert.php** - MODIFIED
11. **populate_reporting_table.php** - MODIFIED
12. **sync_reporting_data.php** - MODIFIED
13. **export_data.php** - MODIFIED
14. **ajax_stats.php** - MODIFIED
15. **company_settings.php** - MODIFIED

---

## What Changed in Each File

### All Files Have:
1. **Replaced hardcoded table names with constants:**
   - `'local_alx_api_reporting'` → `\local_alx_report_api\constants::TABLE_REPORTING`
   - `'local_alx_api_logs'` → `\local_alx_report_api\constants::TABLE_LOGS`
   - `'local_alx_api_settings'` → `\local_alx_report_api\constants::TABLE_SETTINGS`
   - `'local_alx_api_sync_status'` → `\local_alx_report_api\constants::TABLE_SYNC_STATUS`
   - `'local_alx_api_cache'` → `\local_alx_report_api\constants::TABLE_CACHE`
   - `'local_alx_api_alerts'` → `\local_alx_report_api\constants::TABLE_ALERTS`

### lib.php Special Changes:
```php
// Added at top (after defined check):
if (!class_exists('local_alx_report_api\constants')) {
    require_once($CFG->dirroot . '/local/alx_report_api/classes/constants.php');
}

use local_alx_report_api\constants;
```

---

## Deployment Steps

### Step 1: Backup Current Files ✅
Create backup of all 14 files on server before copying

### Step 2: Copy Files
Copy all 14 files from local machine to server:
- Source: `D:\kilo\tenantReport\local\local_alx_report_api\`
- Destination: `/var/www/html/local/alx_report_api/`

### Step 3: Clear Moodle Cache
After copying all files:
1. Go to: Site administration → Development → Purge all caches
2. Or run: `php admin/cli/purge_caches.php`

### Step 4: Test Each Page
Test these pages to verify they work:
- ✅ Control Center (already tested)
- [ ] Monitoring Dashboard
- [ ] Advanced Monitoring
- [ ] Populate Reporting Table
- [ ] Sync Reporting Data
- [ ] Export Data
- [ ] Company Settings

---

## Verification Checklist

After deployment, verify:
- [ ] No "Class not found" errors
- [ ] No "Failed opening required" errors
- [ ] All pages load correctly
- [ ] Database queries work (check for data display)
- [ ] No PHP errors in error logs

---

## Rollback Plan (If Needed)

If anything goes wrong:
1. Restore the 14 backup files
2. Clear Moodle cache
3. Site should return to previous working state

---

## Key Technical Details

### File Path (in lib.php):
```php
$CFG->dirroot . '/local/alx_report_api/classes/constants.php'
```
- Uses `/local/alx_report_api/` (server folder name)
- NOT `/local/local_alx_report_api/` (local dev folder name)

### Namespace (in code):
```php
\local_alx_report_api\constants::TABLE_REPORTING
```
- Uses `local_alx_report_api` (plugin component name)
- Defined in constants.php file
- Has nothing to do with folder name

---

## Benefits After Deployment

✅ **Single Source of Truth** - All table names in one place  
✅ **No Typos** - IDE autocomplete prevents mistakes  
✅ **Easier Refactoring** - Change table name in one place  
✅ **Better Maintainability** - Clear inventory of all tables  
✅ **Professional Code** - Industry best practice

---

## Support

If you encounter any issues:
1. Check Moodle error logs
2. Verify all 14 files were copied
3. Ensure cache was cleared
4. Check file permissions on server

---

**Status:** ✅ READY TO DEPLOY  
**Risk Level:** LOW (Control Center already tested)  
**Estimated Time:** 10 minutes

🎉 **You're ready to deploy the remaining 11 files!**
