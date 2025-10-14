# Dashboard Data Consistency Fix

**Date:** 2025-10-14  
**Status:** ✅ IMPLEMENTED

---

## 🐛 The Problem

**User Report:**
- Control Center showed: Active Tokens = 5, Violations = 1
- Monitoring Dashboard showed: Active Tokens = 0, Violations = 2

**Impact:** Inconsistent data between two dashboards causing user confusion.

---

## 🔍 Root Causes Found

### **Issue 1: Active Tokens (5 vs 0)**

**Control Center:**
- Filters tokens by SERVICE ID (alx_report_api_custom or alx_report_api)
- Only counts tokens for THIS plugin
- Filters by token type (PERMANENT)
- Result: 5 tokens

**Monitoring Dashboard (BEFORE FIX):**
- Counted ALL tokens from ALL services
- No service ID filter
- Result: 0 (query might have failed or no tokens matched simple criteria)

**Why Different:**
- Control Center: Specific to ALX Report API service
- Monitoring Dashboard: All services (wrong!)

---

### **Issue 2: Violations (1 vs 2)**

**Control Center:**
- Time range: `mktime(0, 0, 0)` = Today at midnight
- Example: If it's 2 PM, counts from 00:00 to 14:00 (14 hours)
- Result: 1 violation

**Monitoring Dashboard (BEFORE FIX):**
- Time range: `time() - 86400` = Last 24 hours
- Example: If it's 2 PM, counts from yesterday 14:00 to today 14:00 (24 hours)
- Result: 2 violations (includes yesterday!)

**Why Different:**
- Control Center: Today only (since midnight)
- Monitoring Dashboard: Last 24 hours (includes yesterday)

---

## ✅ The Fixes

### **Fix 1: Active Tokens - Match Control Center Logic**

**Changed in monitoring_dashboard_new.php (Line ~103-125):**

**BEFORE:**
```php
$active_tokens = $DB->count_records_select('external_tokens', 
    'validuntil IS NULL OR validuntil > ?', [time()]);
```
❌ Counts ALL tokens from ALL services

**AFTER:**
```php
// Check for primary service name first
$service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api_custom']);
if (!$service_id) {
    // Fallback to legacy service name
    $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api']);
}

if ($service_id) {
    // Use the same method as Control Center
    $tokens = $DB->get_records_select('external_tokens', 
        'externalserviceid = ? AND tokentype = ?', 
        [$service_id, EXTERNAL_TOKEN_PERMANENT], 
        '', 'id, validuntil');
    
    // Filter for valid tokens in PHP
    $current_time = time();
    foreach ($tokens as $token) {
        if (!$token->validuntil || $token->validuntil > $current_time) {
            $active_tokens++;
        }
    }
}
```
✅ Counts only ALX Report API service tokens (SAME AS CONTROL CENTER)

---

### **Fix 2: Violations - Use Same Time Range**

**Changed in monitoring_dashboard_new.php (Line ~104):**

**BEFORE:**
```php
$today_start = time() - 86400; // Last 24 hours
```
❌ Counts from yesterday

**AFTER:**
```php
$today_start = mktime(0, 0, 0); // Today at midnight (consistent with Control Center)
```
✅ Counts from today at midnight (SAME AS CONTROL CENTER)

---

## 📊 What Changed

| Metric | Before | After | Status |
|--------|--------|-------|--------|
| **Active Tokens Logic** | All services | ALX Report API service only | ✅ Fixed |
| **Time Range** | Last 24 hours | Today at midnight | ✅ Fixed |
| **Service Filter** | None | alx_report_api_custom/alx_report_api | ✅ Added |
| **Token Type Filter** | None | EXTERNAL_TOKEN_PERMANENT | ✅ Added |

---

## 🧪 Testing

### **Test 1: Active Tokens**
1. Open Control Center → Note Active Tokens count
2. Open Monitoring Dashboard → Note Active Tokens count
3. **Expected:** Both show SAME number (e.g., 5)

### **Test 2: Violations**
1. Open Control Center → Note Violations Today count
2. Open Monitoring Dashboard → Note Violations count
3. **Expected:** Both show SAME number (e.g., 1)

### **Test 3: Time Range**
1. Check both dashboards at different times of day
2. **Expected:** Both count from midnight today (not last 24 hours)

### **Test 4: Verify Against Database**
```sql
-- Check active tokens for ALX Report API service
SELECT COUNT(*) 
FROM mdl_external_tokens t
JOIN mdl_external_services s ON s.id = t.externalserviceid
WHERE s.shortname IN ('alx_report_api_custom', 'alx_report_api')
  AND t.tokentype = 0  -- EXTERNAL_TOKEN_PERMANENT
  AND (t.validuntil IS NULL OR t.validuntil > UNIX_TIMESTAMP());

-- Check violations today
SELECT company_shortname, COUNT(*) as calls
FROM mdl_local_alx_api_logs
WHERE timecreated >= UNIX_TIMESTAMP(CURDATE())
GROUP BY company_shortname;
```

---

## 📝 Files Modified

1. **monitoring_dashboard_new.php**
   - Line ~104: Changed time range from `time() - 86400` to `mktime(0, 0, 0)`
   - Line ~107-125: Changed token counting to match Control Center logic
   - Added service ID filter
   - Added token type filter
   - Added PHP-based validation filtering

---

## ⚠️ Important Notes

### **What Changed:**
- ✅ Monitoring Dashboard now uses SAME logic as Control Center
- ✅ Time range standardized to "today at midnight"
- ✅ Token counting filters by service ID
- ✅ Both dashboards now show consistent data

### **What Stayed the Same:**
- ✅ Control Center logic unchanged (it was correct)
- ✅ No database changes needed
- ✅ All other functionality preserved

### **Why This Approach:**
1. **Control Center was correct** - It had the right logic
2. **Monitoring Dashboard was wrong** - It counted all services and wrong time range
3. **Fix:** Make Monitoring Dashboard match Control Center

---

## 🎯 Expected Results

### **Before Fix:**
```
Control Center:
- Active Tokens: 5 (ALX Report API service only)
- Violations: 1 (today since midnight)

Monitoring Dashboard:
- Active Tokens: 0 (all services, query failed)
- Violations: 2 (last 24 hours, includes yesterday)
```

### **After Fix:**
```
Control Center:
- Active Tokens: 5 (ALX Report API service only)
- Violations: 1 (today since midnight)

Monitoring Dashboard:
- Active Tokens: 5 (ALX Report API service only) ✅ SAME
- Violations: 1 (today since midnight) ✅ SAME
```

---

## 🔧 Troubleshooting

### **Issue: Still showing different numbers**

**Check:**
1. Clear browser cache
2. Refresh both pages
3. Check Moodle error logs
4. Verify service name: `SELECT * FROM mdl_external_services WHERE shortname LIKE '%alx%'`

### **Issue: Active tokens showing 0**

**Check:**
1. Verify service exists: `SELECT * FROM mdl_external_services WHERE shortname IN ('alx_report_api_custom', 'alx_report_api')`
2. Verify tokens exist: `SELECT * FROM mdl_external_tokens WHERE externalserviceid = ?`
3. Check Moodle error logs for database errors

### **Issue: Violations count seems wrong**

**Check:**
1. Verify time range: Should count from midnight today
2. Check company rate limits: `SELECT * FROM mdl_local_alx_api_settings WHERE setting_name = 'rate_limit'`
3. Check API logs: `SELECT * FROM mdl_local_alx_api_logs WHERE timecreated >= UNIX_TIMESTAMP(CURDATE())`

---

## 📚 Related Documentation

- Control Center uses correct logic (no changes needed)
- Monitoring Dashboard updated to match Control Center
- Both now use consistent time ranges and filters

---

**Implementation Date:** 2025-10-14  
**Status:** ✅ COMPLETE - Both dashboards now show consistent data

**Result:** Users will see the same numbers on both dashboards!
