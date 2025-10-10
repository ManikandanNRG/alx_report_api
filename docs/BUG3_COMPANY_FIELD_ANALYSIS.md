# Bug #3: Company Shortname vs Company ID Inconsistency

**Date:** October 10, 2025  
**Status:** Analysis Complete - Ready to Fix  
**Priority:** CRITICAL  
**Impact:** Data inconsistency and query failures

---

## 🐛 THE PROBLEM

### What's Happening?

The `local_alx_api_logs` table has **ONLY** `company_shortname` field, but some code expects `companyid` field to exist.

### Database Schema (install.xml)
```xml
<TABLE NAME="local_alx_api_logs">
  <FIELD NAME="userid" TYPE="int"/>
  <FIELD NAME="company_shortname" TYPE="char" LENGTH="100"/>  ← Only this exists
  <FIELD NAME="endpoint" TYPE="char"/>
  ...
</TABLE>
```

**Reality:** The table has `company_shortname` (string) but NO `companyid` (integer)

---

## 📍 WHERE IT'S AFFECTED

### 1. **lib.php - Line 2950 (Log Creation)** ✅ CORRECT
```php
function local_alx_report_api_log_api_call($userid, $company_shortname, ...) {
    $log->company_shortname = $company_shortname;  // ✅ Uses company_shortname
    $DB->insert_record('local_alx_api_logs', $log);
}
```
**Status:** This is CORRECT - it uses `company_shortname`

---

### 2. **lib.php - Line 212 (Error Log)** ❌ PROBLEM
```php
error_log('ALX Report API: companyid field not found in local_alx_api_logs table');
```
**Problem:** Code expects `companyid` but it doesn't exist!

---

### 3. **lib.php - Line 1865-1872 (Analytics Function)** ⚠️ FALLBACK LOGIC
```php
if (isset($table_info['companyid'])) {
    $unique_companies = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT companyid) FROM {local_alx_api_logs} ...", ...
    );
} else if (isset($table_info['company_shortname'])) {
    $unique_companies = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT company_shortname) FROM {local_alx_api_logs} ...", ...
    );
}
```
**Problem:** Has fallback logic checking for both fields - confusing and unnecessary

---

### 4. **lib.php - Line 180-195 (Usage Stats Function)** ⚠️ FALLBACK LOGIC
```php
$table_info = $DB->get_columns('local_alx_api_logs');

if (isset($table_info['companyid'])) {
    // Query using companyid
} else {
    error_log('ALX Report API: companyid field not found in local_alx_api_logs table');
}
```
**Problem:** Expects `companyid`, logs error when not found, but should use `company_shortname`

---

### 5. **monitoring_dashboard_new.php - Line 758** ✅ CORRECT
```php
$last_log = $DB->get_record_sql(
    "SELECT MAX({$time_field}) as last_time FROM {local_alx_api_logs} 
     WHERE company_shortname = ?",
    [$company->shortname]
);
```
**Status:** This is CORRECT - uses `company_shortname`

---

## 🎯 ROOT CAUSE

### Why This Happened?

1. **Original Design:** Table was probably designed with `companyid` initially
2. **Schema Change:** Changed to `company_shortname` for easier identification
3. **Incomplete Update:** Not all code was updated to reflect the schema change
4. **Fallback Logic Added:** Instead of fixing properly, fallback logic was added

---

## 💥 IMPACT

### Current Problems:

1. **Queries Fail Silently**
   - Code tries to query `companyid` field
   - Field doesn't exist
   - Returns empty results or errors

2. **Inconsistent Data**
   - Some functions work (using `company_shortname`)
   - Some functions fail (expecting `companyid`)
   - Confusing for debugging

3. **Performance Overhead**
   - Fallback logic checks table structure every time
   - Unnecessary `get_columns()` calls
   - Slower query execution

4. **Maintenance Nightmare**
   - Developers don't know which field to use
   - Code is confusing with multiple checks
   - Hard to debug issues

---

## 🔧 FIX OPTIONS

### Option 1: Keep company_shortname (RECOMMENDED) ✅

**Pros:**
- Matches current schema
- More readable in queries
- Easier to debug (can see company name directly)
- No database migration needed

**Cons:**
- Slightly larger storage (string vs int)
- Need to update code in ~3 locations

**Changes Required:**
1. Remove all `companyid` checks from lib.php
2. Update queries to use `company_shortname` consistently
3. Remove fallback logic
4. Update error messages

---

### Option 2: Add companyid field back

**Pros:**
- Some code already expects it
- Integer is more efficient than string
- Standard Moodle pattern (use IDs)

**Cons:**
- Requires database migration (upgrade.php)
- Need to populate existing records
- More complex fix
- Breaking change for existing data

**Changes Required:**
1. Add `companyid` field to install.xml
2. Create upgrade script to add field
3. Populate field for existing records
4. Update log creation to include both fields
5. Update all queries

---

### Option 3: Use BOTH fields (NOT RECOMMENDED) ❌

**Pros:**
- Backward compatible
- Flexible

**Cons:**
- Data redundancy
- Sync issues (what if they don't match?)
- More storage
- More maintenance

---

## ✅ RECOMMENDED FIX PLAN

### **Go with Option 1: Standardize on company_shortname**

### Why?
1. ✅ Matches current schema (no migration needed)
2. ✅ Simpler fix (just code changes)
3. ✅ More readable for debugging
4. ✅ No risk of data migration issues
5. ✅ Faster to implement (30 minutes)

---

## 📋 IMPLEMENTATION PLAN

### Step 1: Update lib.php - get_usage_stats() function
**Location:** Line 165-215

**Current Code:**
```php
$table_info = $DB->get_columns('local_alx_api_logs');

if (isset($table_info['companyid'])) {
    // Query using companyid
} else {
    error_log('companyid field not found');
}
```

**Fixed Code:**
```php
// No need to check - we know company_shortname exists
$result = $DB->count_records_select('local_alx_api_logs', 
    "company_shortname = ? AND timecreated >= ?", 
    [$company_shortname, $cutoff]
);
```

---

### Step 2: Update lib.php - get_api_analytics() function
**Location:** Line 1865-1872

**Current Code:**
```php
if (isset($table_info['companyid'])) {
    $unique_companies = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT companyid) FROM {local_alx_api_logs} ...", ...
    );
} else if (isset($table_info['company_shortname'])) {
    $unique_companies = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT company_shortname) FROM {local_alx_api_logs} ...", ...
    );
}
```

**Fixed Code:**
```php
// Use company_shortname directly
$unique_companies = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT company_shortname) FROM {local_alx_api_logs} 
     WHERE timecreated >= ?", 
    [$start_time]
);
```

---

### Step 3: Remove unnecessary table structure checks

**Remove these lines:**
```php
$table_info = $DB->get_columns('local_alx_api_logs');
if (isset($table_info['companyid'])) { ... }
```

**Replace with direct queries using `company_shortname`**

---

### Step 4: Update error messages

**Change:**
```php
error_log('ALX Report API: companyid field not found in local_alx_api_logs table');
```

**To:**
```php
// Remove this error - it's not relevant anymore
```

---

### Step 5: Verify all queries

**Search for:** `companyid` in all files  
**Replace with:** `company_shortname` where appropriate  
**Exclude:** Other tables that legitimately use `companyid`

---

## 🧪 TESTING PLAN

### After Fix, Test:

1. **API Call Logging**
   - Make API call
   - Verify log is created with `company_shortname`
   - Check no errors in logs

2. **Usage Stats**
   - Call `local_alx_report_api_get_usage_stats()`
   - Verify it returns correct counts
   - Check it uses `company_shortname` in query

3. **Analytics**
   - Call `local_alx_report_api_get_api_analytics()`
   - Verify unique companies count is correct
   - Check query uses `company_shortname`

4. **Dashboard Display**
   - Load monitoring dashboard
   - Verify company stats display correctly
   - Check no PHP errors

---

## 📊 FILES TO MODIFY

1. ✅ `lib.php` - 3 locations (~30 lines)
2. ✅ Verify `monitoring_dashboard_new.php` - already correct
3. ✅ Verify `externallib.php` - already correct

**Total:** 1 file, ~30 lines of code

---

## ⏱️ ESTIMATED TIME

- Analysis: ✅ Done (30 minutes)
- Implementation: 20 minutes
- Testing: 10 minutes
- **Total: 30 minutes**

---

## 🎯 SUCCESS CRITERIA

✅ No more `companyid` references in logs table queries  
✅ All queries use `company_shortname` consistently  
✅ No fallback logic checking table structure  
✅ No error logs about missing `companyid` field  
✅ All tests pass  
✅ Dashboard displays correctly  

---

## 📝 NOTES

### Why company_shortname is Better:

1. **Readability:** Can see company name in queries
2. **Debugging:** Easier to identify which company in logs
3. **No Joins:** Don't need to join with company table
4. **Direct Access:** Can filter by shortname directly

### Trade-offs:

1. **Storage:** String takes more space than integer (acceptable)
2. **Performance:** Minimal impact with proper indexing (already indexed)

---

**Ready to implement!** 🚀
