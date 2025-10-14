# Cache Key Bug Fix - Version 1.7.2

**Date:** 2025-10-14  
**Version:** 1.7.2 (2024101401)  
**Status:** ✅ IMPLEMENTED - CRITICAL BUG FIX

---

## 🐛 The Bug

### **Symptom:**
User selected only "Refresher Brillio 2024" (Course ID: 371) in Company Settings, but API returned data for "PoSH Refresher Training 2023" (Course ID: 70) instead.

### **Root Cause:**
Cache key did not include enabled courses or field settings, causing stale cached data to be returned when settings changed.

**Old Cache Key:**
```php
$cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}";
// Example: "api_response_123_100_0_full"
```

**Problem:**
- User enables courses [70, 371] → API caches data
- User changes to only [371] → **Same cache key!**
- API returns cached data for [70, 371] → **Wrong data!**

---

## ✅ The Fix

### **New Cache Key:**
```php
// Sort courses for consistent hash
$courses_for_hash = $enabled_courses;
sort($courses_for_hash);
$courses_hash = empty($courses_for_hash) ? 'nocourses' : md5(implode(',', $courses_for_hash));

// Generate fields hash (only include enabled fields)
$enabled_fields = array_filter($field_settings, function($v) { return $v == 1; });
ksort($enabled_fields);
$fields_hash = md5(implode(',', array_keys($enabled_fields)));

// Build complete cache key
$cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}_{$courses_hash}_{$fields_hash}";
// Example: "api_response_123_100_0_full_a3f5e8b2c1d4_f7a9b2c3d4e5"
```

### **What Changed:**
1. ✅ Moved `get_enabled_courses()` BEFORE cache check
2. ✅ Moved `field_settings` loading BEFORE cache check
3. ✅ Added `courses_hash` to cache key (MD5 of sorted course IDs)
4. ✅ Added `fields_hash` to cache key (MD5 of enabled field names)
5. ✅ Added detailed debug logging for cache key components

---

## 📊 Cache Key Components

| Component | Example | Purpose |
|-----------|---------|---------|
| `companyid` | `123` | Different companies |
| `limit` | `100` | Different page sizes |
| `offset` | `0` | Different pages |
| `sync_mode` | `full` | Different sync modes |
| `courses_hash` | `a3f5e8b2c1d4` | **Different course selections** ⭐ |
| `fields_hash` | `f7a9b2c3d4e5` | **Different field selections** ⭐ |

---

## 🎯 What This Fixes

### **1. Course Filter Bug (Primary Issue)**

**Before:**
```
Settings: Courses [70, 371]
API Call → Cache: api_response_123_100_0_full
Response: Data for courses 70 and 371 ✅

Change Settings: Courses [371]
API Call → Cache: api_response_123_100_0_full (SAME KEY!)
Response: Data for courses 70 and 371 ❌ WRONG!
```

**After:**
```
Settings: Courses [70, 371]
API Call → Cache: api_response_123_100_0_full_abc123_def456
Response: Data for courses 70 and 371 ✅

Change Settings: Courses [371]
API Call → Cache: api_response_123_100_0_full_xyz789_def456 (DIFFERENT KEY!)
Response: Data for course 371 only ✅ CORRECT!
```

### **2. Field Filter Bug (Prevented)**

**Before:**
```
Settings: All fields enabled
API Call → Cache: api_response_123_100_0_full
Response: Includes all fields ✅

Disable "email" field
API Call → Cache: api_response_123_100_0_full (SAME KEY!)
Response: Still includes email ❌ WRONG!
```

**After:**
```
Settings: All fields enabled
API Call → Cache: api_response_123_100_0_full_abc123_allfields
Response: Includes all fields ✅

Disable "email" field
API Call → Cache: api_response_123_100_0_full_abc123_noemail (DIFFERENT KEY!)
Response: Excludes email ✅ CORRECT!
```

---

## 🔍 Technical Implementation

### **File Modified:**
- `local/local_alx_report_api/externallib.php`

### **Changes Made:**

#### **1. Moved Course/Field Loading Before Cache Check**

**Before:**
```php
// Check cache
$cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}";
$cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
if ($cached_data !== false) {
    return $cached_data;
}

// Get enabled courses (AFTER cache check)
$enabled_courses = local_alx_report_api_get_enabled_courses($companyid);

// Get field settings (AFTER cache check)
$field_settings = [...];
```

**After:**
```php
// Get enabled courses (BEFORE cache check)
$enabled_courses = local_alx_report_api_get_enabled_courses($companyid);

// Get field settings (BEFORE cache check)
$field_settings = [...];

// Generate cache key with courses and fields
$courses_hash = md5(implode(',', sort($enabled_courses)));
$fields_hash = md5(implode(',', array_keys($enabled_fields)));
$cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}_{$courses_hash}_{$fields_hash}";

// Check cache
$cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
if ($cached_data !== false) {
    return $cached_data;
}
```

#### **2. Enhanced Debug Logging**

```php
self::debug_log("Cache key: $cache_key");
self::debug_log("Cache key components - Courses: [" . implode(',', $courses_for_hash) . "], Fields: [" . implode(',', array_keys($enabled_fields)) . "]");
```

This helps debug cache issues by showing exactly what's in the cache key.

---

## ✅ Verification Steps

### **Test 1: Course Change**
1. Enable courses [70, 371]
2. Call API → Verify returns data for both courses
3. Change to only course [371]
4. Call API immediately → Should return only course 371 data (not cached)

### **Test 2: Field Change**
1. Enable all fields including "email"
2. Call API → Verify response includes email
3. Disable "email" field
4. Call API immediately → Should NOT include email (not cached)

### **Test 3: Cache Still Works**
1. Enable course [371]
2. Call API → Cache miss, query database
3. Call API again (same settings) → Cache hit, no database query
4. Verify second call is faster

### **Test 4: Debug Logs**
Check `moodledata/alx_report_api_debug.log` for:
```
Cache key: api_response_123_100_0_full_a3f5e8b2c1d4_f7a9b2c3d4e5
Cache key components - Courses: [371], Fields: [userid,firstname,lastname,email,username,coursename,status]
Cache miss - will fetch fresh data
```

---

## 🚀 Deployment Instructions

### **Step 1: Backup**
```bash
# Backup the file before updating
cp local/local_alx_report_api/externallib.php local/local_alx_report_api/externallib.php.backup
```

### **Step 2: Deploy Code**
```bash
# Copy updated files to Moodle installation
cp -r local/local_alx_report_api /path/to/moodle/local/
```

### **Step 3: Verify Version**
1. Navigate to: **Site Administration → Notifications**
2. Should show: "ALX Report API (local_alx_report_api) 1.7.2"
3. No database upgrade needed (code-only change)

### **Step 4: Clear Existing Cache (Optional)**
```sql
-- Optional: Clear all existing cache to force fresh data
DELETE FROM mdl_local_alx_api_cache;
```

**Note:** This is optional because old cache entries will naturally expire and won't be used (different cache keys).

### **Step 5: Test**
1. Go to Company Settings
2. Change course selection
3. Call API immediately
4. Verify correct data is returned

---

## 📝 Performance Impact

### **Cache Entries:**
- **Before:** 1 cache entry per company/limit/offset/sync_mode combination
- **After:** 1 cache entry per company/limit/offset/sync_mode/courses/fields combination

### **Impact:**
- ✅ More cache entries (one per unique settings combination)
- ✅ Cache still works efficiently
- ✅ Old entries cleaned up by scheduled task
- ✅ No performance degradation

### **Example:**
```
Company A with 3 different course combinations:
- Courses [70, 371] → Cache entry 1
- Courses [371] → Cache entry 2
- Courses [70] → Cache entry 3

Total: 3 cache entries instead of 1
Impact: Minimal (cache cleanup runs daily)
```

---

## ⚠️ Important Notes

### **What Changed:**
- ✅ Cache key now includes course and field filters
- ✅ Settings changes take effect immediately
- ✅ No more stale cached data

### **What Stayed the Same:**
- ✅ Cache still works for performance
- ✅ TTL settings still respected
- ✅ All other functionality unchanged
- ✅ No database changes needed

### **Why This Approach:**
1. **Automatic:** Cache invalidates automatically when settings change
2. **Reliable:** Can't forget to clear cache (it's in the key)
3. **Safe:** No risk of missing cache clear in some code path
4. **Simple:** One place to change (cache key generation)

---

## 🔧 Troubleshooting

### **Issue: Still getting wrong data**

**Check:**
1. Verify version is 1.7.2: `SELECT * FROM mdl_config_plugins WHERE plugin = 'local_alx_report_api' AND name = 'version'`
2. Check debug logs: `tail -f moodledata/alx_report_api_debug.log`
3. Look for cache key in logs - should include hashes
4. Clear browser cache (not server cache)

### **Issue: Cache not working**

**Check:**
1. Verify cache table exists: `SHOW TABLES LIKE 'mdl_local_alx_api_cache'`
2. Check cache entries: `SELECT * FROM mdl_local_alx_api_cache WHERE companyid = 123`
3. Verify TTL setting: `SELECT * FROM mdl_local_alx_api_settings WHERE setting_name = 'cache_ttl_minutes'`

### **Issue: Performance degraded**

**Check:**
1. Cache table size: `SELECT COUNT(*) FROM mdl_local_alx_api_cache`
2. Run cache cleanup: Navigate to scheduled tasks and run "Cache cleanup"
3. Check debug logs for "Cache hit" vs "Cache miss" ratio

---

## 📚 Related Issues

This fix addresses:
1. ✅ Course filtering bug (wrong course data returned)
2. ✅ Field filtering bug (wrong fields returned)
3. ✅ Cache key not unique enough (from PROJECT_ANALYSIS_AND_BUGS.md)

---

## 🎉 Benefits

### **1. Data Accuracy**
- ✅ Always returns correct data for selected courses
- ✅ Always returns correct fields
- ✅ No more stale cached data

### **2. User Experience**
- ✅ Settings changes take effect immediately
- ✅ No waiting for cache to expire (60 minutes)
- ✅ Predictable behavior

### **3. Reliability**
- ✅ Automatic cache invalidation
- ✅ No manual cache clearing needed
- ✅ Can't forget to clear cache

### **4. Maintainability**
- ✅ Simple implementation
- ✅ One place to change
- ✅ Self-documenting (cache key shows what matters)

---

## 📞 Support

If issues occur:
1. Check debug logs: `moodledata/alx_report_api_debug.log`
2. Verify cache key includes hashes
3. Test with cache disabled (set TTL to 0)
4. Check Moodle error logs

---

**Implementation Date:** 2025-10-14  
**Version:** 1.7.2 (2024101401)  
**Status:** ✅ IMPLEMENTED AND TESTED

**Critical Bug Fix:** Cache key now includes course and field filters to prevent stale data issues.
