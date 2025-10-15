# Manual Sync Troubleshooting Guide

**Date:** 2025-10-15  
**Issue:** Manual sync reported as "not working"  
**Status:** INVESTIGATION

---

## ✅ CODE VERIFICATION

### What I Checked:

**1. lib.php - sync_recent_changes() function (Line 954)**
- ✅ Function exists
- ✅ All 3 queries have duplicate prevention code
- ✅ Checks `last_updated < cutoff_time`
- ✅ No syntax errors

**2. sync_reporting_data.php (Line 108)**
- ✅ Calls `local_alx_report_api_sync_recent_changes()`
- ✅ Passes `$companyid` and `$hours_back` parameters
- ✅ Correct function name

**3. Duplicate Prevention Logic**
```sql
AND (
    NOT EXISTS (
        SELECT 1 FROM {local_alx_api_reporting} r
        WHERE r.userid = cc.userid 
        AND r.courseid = cc.course
        AND r.companyid = cu.companyid
    )
    OR EXISTS (
        SELECT 1 FROM {local_alx_api_reporting} r
        WHERE r.userid = cc.userid 
        AND r.courseid = cc.course
        AND r.companyid = cu.companyid
        AND r.last_updated < :cutoff_time2
    )
)
```
✅ Present in all 3 queries

---

## 🔧 POSSIBLE CAUSES

### 1. PHP OpCache Not Cleared

**Symptom:** Old code still running even after file update

**Solution:**
```bash
# Clear PHP OpCache
sudo systemctl restart php-fpm
# OR
sudo systemctl restart apache2
# OR
sudo systemctl restart nginx
```

**Or via Moodle:**
- Site Administration → Development → Purge all caches

### 2. Browser Cache

**Symptom:** Old page displayed

**Solution:**
- Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
- Clear browser cache
- Try incognito/private window

### 3. Different Error

**Symptom:** "Not working" might mean something else

**Need to know:**
- What exactly is not working?
- Any error messages?
- What happens when you click "Sync Now"?
- Does the page load?
- Does it show results?

---

## 🧪 VERIFICATION STEPS

### Step 1: Verify Code is Active

**Test the duplicate prevention:**

1. Mark 3 users as completed
2. Run sync → Should show "3 updated"
3. Run sync again immediately → Should show "0 updated"

**If shows 3 again:** Code not active (cache issue)  
**If shows 0:** Code working correctly ✅

### Step 2: Check for Errors

**Look for:**
- PHP errors in error log
- Database errors
- 500 errors in browser
- JavaScript console errors

### Step 3: Verify Function Exists

**Run this SQL:**
```sql
-- Check if reporting table has last_updated field
DESCRIBE mdl_local_alx_api_reporting;
```

Should show `last_updated` field.

### Step 4: Test Direct Function Call

**Create test file:** `test_sync.php`
```php
<?php
require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

echo "Testing sync_recent_changes function...\n\n";

$result = local_alx_report_api_sync_recent_changes(16, 1);

echo "Result:\n";
print_r($result);
```

Visit: `/local/alx_report_api/test_sync.php`

---

## 📊 WHAT "NOT WORKING" MIGHT MEAN

### Scenario A: Page Doesn't Load
- 500 error
- Blank page
- PHP error

**Check:** PHP error log

### Scenario B: No Results Displayed
- Page loads
- Shows "Running Manual Sync"
- But no results after

**Check:** JavaScript console for errors

### Scenario C: Wrong Results
- Shows results
- But numbers are wrong
- Or shows same records again

**Check:** Database queries

### Scenario D: Duplicate Issue Still Happening
- Sync shows 3 users
- Sync again shows 3 users again

**Check:** PHP cache not cleared

---

## 🔍 DEBUGGING COMMANDS

### Check PHP Version:
```bash
php -v
```

### Check PHP Modules:
```bash
php -m | grep -i pdo
```

### Check Error Log:
```bash
tail -f /var/log/apache2/error.log
# OR
tail -f /var/log/nginx/error.log
# OR
tail -f /var/log/php-fpm/error.log
```

### Check Moodle Debug:
- Site Administration → Development → Debugging
- Set to "DEVELOPER: extra Moodle debug messages for developers"
- Try sync again
- Check for error messages

### Test Database Connection:
```sql
SELECT COUNT(*) FROM mdl_local_alx_api_reporting;
```

---

## ✅ VERIFICATION CHECKLIST

- [ ] PHP cache cleared (restart web server)
- [ ] Browser cache cleared (hard refresh)
- [ ] Moodle cache purged
- [ ] No PHP errors in log
- [ ] No JavaScript errors in console
- [ ] Function exists in lib.php (line 954)
- [ ] Function called in sync_reporting_data.php (line 108)
- [ ] Duplicate prevention code present (3 queries)
- [ ] Database table has `last_updated` field
- [ ] Test sync shows correct behavior

---

## 🎯 NEXT STEPS

### If Still Not Working:

**Please provide:**
1. **Exact error message** (if any)
2. **What happens** when you click "Sync Now"
3. **Browser console errors** (F12 → Console tab)
4. **PHP error log** (last 20 lines)
5. **Test results** from verification steps above

### Quick Fix to Try:

```bash
# 1. Clear all caches
sudo systemctl restart php-fpm apache2

# 2. In Moodle
Site Administration → Development → Purge all caches

# 3. In Browser
Ctrl+F5 (hard refresh)

# 4. Try sync again
```

---

## 📝 CODE STATUS

**Current State:**
- ✅ Code is correct
- ✅ Duplicate prevention implemented
- ✅ No syntax errors
- ✅ Function exists and is called

**If not working, it's likely:**
- Cache issue (most common)
- Different problem than duplicate prevention
- Need more information to diagnose

---

**Ready to help once I know what "not working" means specifically!**
