# Developer Instructions - Cache Fix for Manual Sync

**Issue:** API not showing new completions after manual sync  
**Fix:** Add cache clearing to manual sync function

---

## 📋 QUICK SUMMARY

**Problem:** Manual sync updates database but API returns old cached data  
**Solution:** Clear cache after manual sync completes  
**Files Changed:** `local/local_alx_report_api/lib.php` (2 changes)

---

## 🔧 CHANGE 1: Add Cache Clear Function

**Location:** After line 1363 (after `local_alx_report_api_cache_cleanup()` function)

**Add this complete function:**

```php
/**
 * Clear all cache entries for a specific company.
 * Used after manual sync or data updates to ensure API returns fresh data.
 *
 * @param int $companyid Company ID
 * @return int Number of cache entries cleared
 */
function local_alx_report_api_cache_clear_company($companyid) {
    global $DB;
    
    try {
        // Check if cache table exists
        if (!$DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_CACHE)) {
            return 0;
        }
        
        // Validate company ID
        if (empty($companyid) || $companyid <= 0) {
            return 0;
        }
        
        // Delete all cache entries for this company
        return $DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
        
    } catch (Exception $e) {
        error_log('ALX Report API: Error clearing company cache - ' . $e->getMessage());
        return 0;
    }
}
```

---

## 🔧 CHANGE 2: Update Manual Sync Function

**Location:** Around line 1127 in `local_alx_report_api_sync_recent_changes()` function

**Find this code:**
```php
$stats['duration_seconds'] = time() - $start_time;

if (!empty($stats['errors'])) {
    $stats['success'] = false;
}

return $stats;
```

**Replace with:**
```php
$stats['duration_seconds'] = time() - $start_time;

if (!empty($stats['errors'])) {
    $stats['success'] = false;
}

// Clear cache for this company so API returns fresh data
if ($stats['total_processed'] > 0) {
    $cache_cleared = local_alx_report_api_cache_clear_company($company->id);
    $stats['cache_cleared'] = $cache_cleared;
}

return $stats;
```

---

## ✅ TESTING

### Test Steps:
1. Mark a user as completed in Moodle
2. Run manual sync from Control Center
3. Check sync result shows "cache_cleared: N"
4. Refresh API call immediately
5. **Expected:** New completion appears in API response

### Before Fix:
- ❌ API shows old data (no new completions)
- ❌ Need to wait 1 hour for cache to expire

### After Fix:
- ✅ API shows new completions immediately
- ✅ Cache cleared automatically

---

## 📝 NOTES

- Only clears cache when records are actually processed
- Safe error handling - won't break if cache table doesn't exist
- No impact on other functions
- Cache still works normally for regular API calls

---

## 🆘 TROUBLESHOOTING

### If API still shows old data:
1. Check PHP error log for cache errors
2. Verify both changes were applied correctly
3. Check sync statistics shows "cache_cleared" count
4. Try clearing browser cache

### If sync fails:
1. Check function was added correctly (no syntax errors)
2. Verify function name is exact: `local_alx_report_api_cache_clear_company`
3. Check Moodle error logs

---

**Questions?** See full documentation in `docs/MANUAL_SYNC_CACHE_FIX.md`
