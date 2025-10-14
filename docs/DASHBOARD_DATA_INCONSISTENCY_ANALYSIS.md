# Dashboard Data Inconsistency Analysis

**Date:** 2025-10-14  
**Status:** 🔴 CRITICAL - Data Mismatch Between Dashboards

---

## 🐛 The Problem

**User Report:**
- Control Center shows: Active Tokens = 5, Violations = 1
- Monitoring Dashboard shows: Active Tokens = 0, Violations = 2

**Impact:** Users see different data on different pages, causing confusion and trust issues.

---

## 🔍 Root Cause Analysis

### **Issue 1: Active Tokens Count**

**Control Center (Line 400-410):**
```php
// Get actual active tokens count (fix for correct display)
$actual_active_tokens = 0;
if ($DB->get_manager()->table_exists('external_tokens')) {
    $actual_active_tokens = $DB->count_records_select('external_tokens',
        'validuntil IS NULL OR validuntil > ?', [time()]);
}
```
✅ **CORRECT** - Counts tokens that are NOT expired

**Monitoring Dashboard (Line 107-109):**
```php
// Count ACTIVE tokens only (not expired)
$active_tokens = $DB->count_records_select('external_tokens', 
    'validuntil IS NULL OR validuntil > ?', [time()]);
```
✅ **CORRECT** - Same logic!

**BUT WAIT** - Let me check if there's a try-catch issue...

Looking at line 106:
```php
try {
    // Count ACTIVE tokens only (not expired)
    $active_tokens = $DB->count_records_select('external_tokens', 
        'validuntil IS NULL OR validuntil > ?', [time()]);
```

**PROBLEM:** If this query fails (exception), `$active_tokens` stays at 0 (initialized on line 103).

---

### **Issue 2: Rate Limit Violations**

**Control Center (Line 494-530):**
```php
// Calculate rate limit violations using company-specific limits
$violations_today = 0;
$today_start = mktime(0, 0, 0); // ← TODAY at midnight

foreach ($companies as $company) {
    // Get company-specific rate limit
    $company_rate_limit = local_alx_report_api_get_company_setting($company->id, 'rate_limit', null);
    if ($company_rate_limit === null) {
        $company_rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
    }
    
    // Count today's API calls
    $company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
        "{$time_field} >= ?", [$today_start]); // ← Uses today_start
    
    if ($company_calls_today > $company_rate_limit) {
        $violations_today++;
    }
}
```
✅ Uses `mktime(0, 0, 0)` = **Today at midnight**

**Monitoring Dashboard (Line 104, 113-140):**
```php
$today_start = time() - 86400; // ← LAST 24 HOURS (not today!)

foreach ($companies as $company) {
    // Get company-specific rate limit
    $company_settings = local_alx_report_api_get_company_settings($company->id);
    $company_rate_limit = isset($company_settings['rate_limit']) ? $company_settings['rate_limit'] : get_config('local_alx_report_api', 'rate_limit');
    
    if (empty($company_rate_limit)) {
        $company_rate_limit = 100;
    }
    
    // Count today's API calls
    $company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
        "{$time_field} >= ? AND company_shortname = ?",
        [$today_start, $company->shortname]); // ← Uses last 24 hours
    
    if ($company_calls_today > $company_rate_limit) {
        $rate_limit_violations++;
    }
}
```
❌ Uses `time() - 86400` = **Last 24 hours** (not today!)

---

## 🎯 The Differences

| Metric | Control Center | Monitoring Dashboard | Issue |
|--------|----------------|---------------------|-------|
| **Active Tokens** | Counts non-expired tokens | Same logic | ✅ Logic same, but may fail silently |
| **Time Range** | `mktime(0,0,0)` = Today at midnight | `time() - 86400` = Last 24 hours | ❌ **DIFFERENT!** |
| **Company Filter** | No company filter in query | Filters by `company_shortname` | ❌ **DIFFERENT!** |
| **Error Handling** | No try-catch | Has try-catch (may hide errors) | ⚠️ **DIFFERENT!** |

---

## 📊 Example Scenario

**Scenario:** It's 2:00 PM on October 14, 2025

**Control Center:**
- `today_start = mktime(0,0,0)` = October 14, 2025 00:00:00
- Counts calls from: October 14, 2025 00:00:00 to now (14 hours)
- Result: 1 violation

**Monitoring Dashboard:**
- `today_start = time() - 86400` = October 13, 2025 14:00:00
- Counts calls from: October 13, 2025 14:00:00 to now (24 hours)
- Result: 2 violations (includes yesterday afternoon!)

**Why Different:**
- Control Center: Only counts TODAY's calls (since midnight)
- Monitoring Dashboard: Counts LAST 24 HOURS (includes yesterday)

---

## 🔧 Additional Issues Found

### **Issue 3: Company Filtering**

**Control Center:**
```php
$company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
    "{$time_field} >= ?", [$today_start]); // ← NO company filter!
```
❌ **BUG!** Counts ALL calls, not just for this company!

**Monitoring Dashboard:**
```php
$company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
    "{$time_field} >= ? AND company_shortname = ?",
    [$today_start, $company->shortname]); // ← Filters by company
```
✅ **CORRECT!** Counts only this company's calls

---

### **Issue 4: Error Handling**

**Control Center:**
- No try-catch around token counting
- If query fails → PHP error → Page breaks

**Monitoring Dashboard:**
- Has try-catch around token counting
- If query fails → Silent failure → Shows 0

**Both are problematic:**
- Control Center: Fails loudly (good for debugging, bad for users)
- Monitoring Dashboard: Fails silently (bad for debugging, confusing for users)

---

## ✅ The Fix Required

### **1. Standardize Time Range**

**Decision:** Use "Today at midnight" (not last 24 hours)

**Why:**
- More intuitive ("today" means since midnight)
- Matches rate limiting logic (resets at midnight)
- Consistent with user expectations

**Change:**
```php
// BEFORE (Monitoring Dashboard)
$today_start = time() - 86400; // Last 24 hours

// AFTER (Standardized)
$today_start = mktime(0, 0, 0); // Today at midnight
```

### **2. Fix Company Filtering in Control Center**

**Change:**
```php
// BEFORE
$company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
    "{$time_field} >= ?", [$today_start]);

// AFTER
$company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
    "{$time_field} >= ? AND company_shortname = ?",
    [$today_start, $company->shortname]);
```

### **3. Standardize Error Handling**

**Add try-catch to both:**
```php
try {
    $active_tokens = $DB->count_records_select('external_tokens',
        'validuntil IS NULL OR validuntil > ?', [time()]);
} catch (Exception $e) {
    error_log('Error counting active tokens: ' . $e->getMessage());
    $active_tokens = 0; // Fallback
}
```

### **4. Add Debug Logging**

**Add to both files:**
```php
error_log("DEBUG: Active Tokens = $active_tokens");
error_log("DEBUG: Violations Today = $violations_today");
error_log("DEBUG: Today Start = " . date('Y-m-d H:i:s', $today_start));
```

---

## 📝 Files to Modify

1. **control_center.php**
   - Line ~500-530: Add company filter to query
   - Line ~400-410: Add try-catch for token counting
   - Add debug logging

2. **monitoring_dashboard_new.php**
   - Line ~104: Change `time() - 86400` to `mktime(0,0,0)`
   - Line ~107-109: Improve error handling
   - Add debug logging

---

## 🧪 Testing Plan

### **Test 1: Active Tokens**
1. Check both dashboards
2. Should show SAME number
3. Verify against database: `SELECT COUNT(*) FROM mdl_external_tokens WHERE validuntil IS NULL OR validuntil > UNIX_TIMESTAMP()`

### **Test 2: Violations**
1. Check both dashboards
2. Should show SAME number
3. Verify time range: Both should count from midnight today

### **Test 3: Company Filtering**
1. Create test data for specific company
2. Verify violations count only that company's calls
3. Check debug logs for company filtering

---

## 🎯 Success Criteria

✅ Both dashboards show identical numbers  
✅ Time range is consistent (today at midnight)  
✅ Company filtering works correctly  
✅ Error handling is consistent  
✅ Debug logs show calculation details  

---

**Next Step:** Implement fixes carefully with proper testing.
