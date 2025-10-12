# Sync Task Timeout & Overlap Fix

**Date:** 2025-10-12  
**Status:** ✅ COMPLETE  
**File:** `local/local_alx_report_api/classes/task/sync_reporting_data_task.php`

---

## 🎯 Problems Fixed

### 1. Multiple Syncs Running Simultaneously ⚠️ CRITICAL
**Before:** Sync tasks could overlap if previous task hadn't finished → database deadlocks  
**After:** Lock mechanism prevents overlaps → only one sync at a time

### 2. No Timeout Enforcement ⚠️ HIGH
**Before:** Tasks could run indefinitely → server resource exhaustion  
**After:** Hard timeout checks using `max_sync_time` setting → graceful shutdown

### 3. No Cleanup on Errors ⚠️ MEDIUM
**Before:** Locks not released on crash → system stuck  
**After:** Finally block ensures cleanup → self-healing

---

## 🔧 Implementation

### New Methods Added:

```php
// Acquire lock to prevent overlapping executions
private function acquire_lock($lock_key, $timeout)

// Release lock (always called in finally block)
private function release_lock($lock_key)
```

### Key Features:

1. **Lock Mechanism**
   - Checks if another sync is running
   - Skips execution if lock exists
   - Removes stale locks (older than 1 hour)
   - Logs all lock operations

2. **Timeout Enforcement**
   - Uses `max_sync_time` setting from admin settings page
   - Checks timeout between companies (30s buffer)
   - Checks timeout during processing (60s buffer)
   - Graceful shutdown before PHP timeout

3. **Guaranteed Cleanup**
   - Finally block ensures lock always released
   - Works even on errors
   - Clean state for next run

4. **Table Constants**
   - Used `constants::TABLE_SETTINGS`
   - Used `constants::TABLE_CACHE`
   - Used `constants::TABLE_SYNC_STATUS`

---

## ⚙️ Configuration

### Uses Existing Setting:
The fix uses the **"Maximum Sync Duration (Seconds)"** setting from the admin settings page:

- **Setting:** `max_sync_time`
- **Default:** 300 seconds (5 minutes)
- **Range:** 60-3600 seconds
- **Location:** Admin settings page

No new configuration needed!

---

## 📊 Code Changes

- **Lines Changed:** ~110 lines
- **New Methods:** 2
- **Modified Methods:** 2
- **Table Constants:** 3 used

---

## 🧪 Testing

### Test Overlap Prevention:
```bash
# Run two syncs simultaneously
php admin/cli/scheduled_task.php --execute='\local_alx_report_api\task\sync_reporting_data_task' &
php admin/cli/scheduled_task.php --execute='\local_alx_report_api\task\sync_reporting_data_task' &

# Expected: Second sync skips with message "Another sync task is already running"
```

### Check Lock Status:
```sql
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' 
AND name = 'sync_task_lock';
```

### Remove Stuck Lock (if needed):
```bash
php admin/cli/cfg.php --component=local_alx_report_api --name=sync_task_lock --unset
```

---

## 🚀 Deployment

1. **Backup:** `cp sync_reporting_data_task.php sync_reporting_data_task.php.backup`
2. **Deploy:** Copy new file
3. **Clear cache:** `php admin/cli/purge_caches.php`
4. **Monitor:** Check first scheduled run

**Time:** 10 minutes  
**Risk:** LOW (easy rollback)

---

## 📈 Impact

### Before:
- ❌ Multiple syncs could run → database deadlocks
- ❌ Tasks could run forever → server issues
- ❌ Locks not cleaned up → system stuck

### After:
- ✅ Only one sync at a time → no deadlocks
- ✅ Respects `max_sync_time` setting → controlled execution
- ✅ Always cleaned up → self-healing

---

## 📝 Batch Size Investigation

**Issue Reported:** "Setting batch size to 1000 results in 3000 records"

**Result:** ✅ NOT A BUG - Working correctly!

**Explanation:**
- Batch size = 1000 **per company** (not total)
- 3 companies × 1000 = 3000 records (expected behavior)
- This is good design (scalable, prevents memory issues)

**Decision:** Left as-is (correct behavior)

---

**Status:** ✅ COMPLETE - Ready for deployment
