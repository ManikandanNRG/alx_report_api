# Cache Behavior Analysis - Your Understanding vs Current Code

**Date:** 2025-10-15  
**Issue:** Cache enable/disable setting is being ignored

---

## 📋 YOUR UNDERSTANDING (6 POINTS)

### ✅ WHEN CACHE ENABLED:

**Point 1:** First API call returns data from report table and caches it with unique key  
**Point 2:** When company settings change (fields/courses), clear cache and return from report table with new cache key  
**Point 3:** When manual sync happens, clear cache and return updated API call  

### ✅ WHEN CACHE NOT ENABLED:

**Point 4:** API call returns data from report table (no caching)  
**Point 5:** When company settings change, API returns data based on settings from report table (no caching)  
**Point 6:** When manual sync happens, update data and return API call with updated values from report table (no caching)  

---

## 🔍 CURRENT CODE BEHAVIOR

### ❌ CRITICAL FINDING: Cache Setting is COMPLETELY IGNORED!

I searched the entire codebase for `enable_cache` and found **ZERO matches**. The code **NEVER checks** if caching is enabled or disabled for a company!

---

## 📊 ACTUAL CURRENT BEHAVIOR

### Current Behavior (WRONG):

**ALL companies (cache enabled OR disabled):**

1. **First API Call:**
   - ✅ Queries report table
   - ❌ **ALWAYS caches result** (ignores enable_cache setting)
   - Cache key includes: companyid, limit, offset, sync_mode, courses, fields

2. **When Company Settings Change:**
   - ✅ Cache key changes (includes new courses/fields hash)
   - ✅ Cache miss → queries report table
   - ❌ **ALWAYS caches new result** (ignores enable_cache setting)

3. **When Manual Sync Happens:**
   - ✅ Updates report table
   - ✅ Clears cache (our yesterday's fix)
   - ❌ **Clears cache even if caching is disabled**

4. **Subsequent API Calls:**
   - ❌ **ALWAYS checks cache first** (ignores enable_cache setting)
   - ❌ Returns cached data if found
   - Only queries DB if cache miss

---

## ✅ YOUR UNDERSTANDING IS 100% CORRECT!

Your 6 points describe **EXACTLY** how it **SHOULD** work, but the current code does **NOT** implement this logic.

### Comparison Table:

| Scenario | Your Understanding | Current Code | Status |
|----------|-------------------|--------------|--------|
| **Cache ENABLED - Point 1** | Query DB → Cache result | Query DB → Cache result | ✅ CORRECT |
| **Cache ENABLED - Point 2** | Settings change → Clear cache → Query DB → New cache | Settings change → New cache key → Query DB → Cache | ⚠️ WORKS (different method) |
| **Cache ENABLED - Point 3** | Manual sync → Clear cache → Query DB | Manual sync → Clear cache → Query DB | ✅ CORRECT |
| **Cache DISABLED - Point 4** | Query DB directly (no cache) | Query DB → **Cache result anyway** | ❌ WRONG |
| **Cache DISABLED - Point 5** | Settings change → Query DB (no cache) | Settings change → Query DB → **Cache result anyway** | ❌ WRONG |
| **Cache DISABLED - Point 6** | Manual sync → Update DB → Query DB (no cache) | Manual sync → Update DB → **Clear cache** | ❌ WRONG |

---

## 🔴 THE BUGS

### Bug 1: API Always Uses Cache (Point 4 & 5 BROKEN)

**Location:** `externallib.php` line ~638

**Current Code:**
```php
// Check cache for ALL sync modes (universal caching)
$cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
if ($cached_data !== false) {
    self::debug_log("Cache hit - returning cached data");
    return $cached_data;  // ❌ Returns cache even if disabled!
}
```

**Problem:**
- Never checks `enable_cache` setting
- Always uses cache if available
- Company with cache DISABLED still gets cached responses

**Expected Code:**
```php
// Check if caching is enabled for this company
$cache_enabled = local_alx_report_api_get_company_setting($companyid, 'enable_cache', 1);

if ($cache_enabled) {
    // Only check cache if enabled
    $cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
    if ($cached_data !== false) {
        self::debug_log("Cache hit - returning cached data");
        return $cached_data;
    }
}
```

---

### Bug 2: API Always Saves to Cache (Point 4 & 5 BROKEN)

**Location:** `externallib.php` line ~830

**Current Code:**
```php
// Universal cache storage for ALL sync modes
$cache_ttl_minutes = local_alx_report_api_get_company_setting($companyid, 'cache_ttl_minutes', 60);
$cache_ttl = $cache_ttl_minutes * 60;

// Cache all results (including empty) for all sync modes
local_alx_report_api_cache_set($cache_key, $companyid, $result, $cache_ttl);
// ❌ Saves to cache even if caching is disabled!
```

**Problem:**
- Never checks `enable_cache` setting
- Always saves results to cache
- Company with cache DISABLED still creates cache entries

**Expected Code:**
```php
// Only cache if enabled for this company
$cache_enabled = local_alx_report_api_get_company_setting($companyid, 'enable_cache', 1);

if ($cache_enabled) {
    $cache_ttl_minutes = local_alx_report_api_get_company_setting($companyid, 'cache_ttl_minutes', 60);
    $cache_ttl = $cache_ttl_minutes * 60;
    
    local_alx_report_api_cache_set($cache_key, $companyid, $result, $cache_ttl);
    self::debug_log("Cached result, TTL: {$cache_ttl} seconds");
}
```

---

### Bug 3: Manual Sync Always Clears Cache (Point 6 BROKEN)

**Location:** `lib.php` line ~1133

**Current Code:**
```php
// Clear cache for this company so API returns fresh data
if ($stats['total_processed'] > 0) {
    $cache_cleared = local_alx_report_api_cache_clear_company($company->id);
    $stats['cache_cleared'] = $cache_cleared;
    // ❌ Clears cache even if caching is disabled!
}
```

**Problem:**
- Never checks `enable_cache` setting
- Always clears cache after sync
- Wastes time clearing cache that shouldn't exist

**Expected Code:**
```php
// Clear cache ONLY if caching is enabled for this company
if ($stats['total_processed'] > 0) {
    $cache_enabled = local_alx_report_api_get_company_setting($company->id, 'enable_cache', 1);
    
    if ($cache_enabled) {
        $cache_cleared = local_alx_report_api_cache_clear_company($company->id);
        $stats['cache_cleared'] = $cache_cleared;
    }
}
```

---

## 🎯 WHAT NEEDS TO BE FIXED

### Fix Summary:

1. **externallib.php - Line ~638:** Check `enable_cache` before reading cache
2. **externallib.php - Line ~830:** Check `enable_cache` before writing cache
3. **lib.php - Line ~1133:** Check `enable_cache` before clearing cache

### The Setting Name:

Based on your screenshot, the setting should be stored as:
- **Setting name:** `enable_cache`
- **Value:** `1` (enabled) or `0` (disabled)
- **Default:** `1` (enabled by default)

---

## 📊 EXPECTED BEHAVIOR AFTER FIX

### Scenario 1: Company with Cache ENABLED

```
API Call 1:
├─ Check enable_cache setting → ENABLED ✅
├─ Check cache → MISS
├─ Query report table
├─ Save to cache ✅
└─ Return data

API Call 2:
├─ Check enable_cache setting → ENABLED ✅
├─ Check cache → HIT ✅
└─ Return cached data (fast!)

Settings Change:
├─ Cache key changes (new hash)
├─ Check enable_cache setting → ENABLED ✅
├─ Check cache → MISS (new key)
├─ Query report table
├─ Save to cache ✅
└─ Return data

Manual Sync:
├─ Update report table
├─ Check enable_cache setting → ENABLED ✅
├─ Clear cache ✅
└─ Next API call queries fresh data
```

### Scenario 2: Company with Cache DISABLED

```
API Call 1:
├─ Check enable_cache setting → DISABLED ❌
├─ Skip cache check
├─ Query report table directly
├─ Skip cache save
└─ Return data

API Call 2:
├─ Check enable_cache setting → DISABLED ❌
├─ Skip cache check
├─ Query report table directly (always fresh!)
├─ Skip cache save
└─ Return data

Settings Change:
├─ Check enable_cache setting → DISABLED ❌
├─ Skip cache check
├─ Query report table with new settings
├─ Skip cache save
└─ Return data

Manual Sync:
├─ Update report table
├─ Check enable_cache setting → DISABLED ❌
├─ Skip cache clear (no cache to clear)
└─ Next API call queries fresh data
```

---

## ✅ VERIFICATION

### Your Understanding:

| Point | Description | Your Understanding | Status |
|-------|-------------|-------------------|--------|
| 1 | Cache enabled - First call caches | ✅ Correct | Should work this way |
| 2 | Cache enabled - Settings change clears cache | ✅ Correct | Should work this way |
| 3 | Cache enabled - Manual sync clears cache | ✅ Correct | Should work this way |
| 4 | Cache disabled - API queries DB directly | ✅ Correct | **NOT working now** |
| 5 | Cache disabled - Settings change queries DB | ✅ Correct | **NOT working now** |
| 6 | Cache disabled - Manual sync queries DB | ✅ Correct | **NOT working now** |

**Result:** Your understanding is **100% CORRECT**! The current code is **WRONG** for points 4, 5, and 6.

---

## 🎯 SUMMARY

### Your Understanding: ✅ PERFECT!

You correctly understand how caching should work:
- When **ENABLED**: Use cache for performance
- When **DISABLED**: Always query database for real-time data

### Current Code: ❌ BROKEN!

The code **IGNORES** the `enable_cache` setting:
- Always uses cache (even when disabled)
- Always saves to cache (even when disabled)
- Always clears cache (even when disabled)

### The Fix:

Add 3 checks for `enable_cache` setting:
1. Before reading cache (API)
2. Before writing cache (API)
3. Before clearing cache (manual sync)

---

## 📝 NEXT STEPS

1. ✅ Confirm your understanding is correct (DONE - it is!)
2. ⏳ Implement the 3 fixes
3. ⏳ Test with cache enabled
4. ⏳ Test with cache disabled
5. ⏳ Verify all 6 points work correctly

**Your analysis was spot-on!** 🎯
