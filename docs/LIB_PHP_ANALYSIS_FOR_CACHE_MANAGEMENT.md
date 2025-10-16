# lib.php Analysis for Cache Management Implementation

## 📋 Current Understanding

### File Structure
- **Location:** `local/local_alx_report_api/lib.php`
- **Purpose:** Library functions for ALX Report API plugin
- **Uses constants class:** `local_alx_report_api\constants`
- **Global variable:** `$DB` (Moodle database object)

### Function Naming Convention
All functions follow this pattern:
```
local_alx_report_api_{action}_{object}()
```

Examples:
- `local_alx_report_api_get_company_info()`
- `local_alx_report_api_get_company_setting()`
- `local_alx_report_api_set_company_setting()`
- `local_alx_report_api_get_enabled_courses()`
- `local_alx_report_api_populate_reporting_table()`

### Existing Functions (Relevant to Cache Management)

#### 1. `local_alx_report_api_get_company_setting($companyid, $setting_name, $default = 0)`
- Gets a single setting for a company
- Returns setting value or default
- Used to check if cache is enabled

#### 2. `local_alx_report_api_set_company_setting($companyid, $setting_name, $setting_value)`
- Sets a single setting for a company
- Used to enable/disable cache

#### 3. `local_alx_report_api_get_company_settings($companyid)`
- Gets all settings for a company
- Returns array of settings

### Database Table Constants
From `local_alx_report_api\constants`:
```php
const TABLE_REPORTING = 'local_alx_api_reporting';
const TABLE_LOGS = 'local_alx_api_logs';
const TABLE_SETTINGS = 'local_alx_api_settings';
const TABLE_SYNC_STATUS = 'local_alx_api_sync_status';
const TABLE_CACHE = 'local_alx_api_cache';
const TABLE_ALERTS = 'local_alx_api_alerts';
```

### Cache Table Schema (from install.xml)
```xml
<TABLE NAME="local_alx_api_cache">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="companyid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="cache_key" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="cache_data" TYPE="text" NOTNULL="true"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="companyid" UNIQUE="false" FIELDS="companyid"/>
    <INDEX NAME="cache_key" UNIQUE="false" FIELDS="cache_key"/>
  </INDEXES>
</TABLE>
```

**Important Field Names:**
- `timecreated` (NOT `created_at`)
- `companyid`
- `cache_key`
- `cache_data`

### Settings Table Schema
```xml
<TABLE NAME="local_alx_api_settings">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="companyid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="setting_name" TYPE="char" LENGTH="100" NOTNULL="true"/>
    <FIELD NAME="setting_value" TYPE="text" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
</TABLE>
```

**Important Field Names:**
- `setting_name`
- `setting_value` (NOT `value`)
- `timemodified`

### How Cache is Currently Cleared

#### In populate_reporting_table.php (Cleanup Action):
```php
// Clear specific company
$DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $cleanup_companyid]);

// Clear all companies
$DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE);
```

#### Pattern Used:
- Direct `$DB->delete_records()` calls
- Uses `constants::TABLE_CACHE`
- No helper function exists yet

### Moodle Database API Patterns Used

#### Count Records:
```php
$count = $DB->count_records('table_name', ['field' => $value]);
```

#### Delete Records:
```php
$DB->delete_records('table_name', ['field' => $value]);
```

#### Get Record:
```php
$record = $DB->get_record('table_name', ['field' => $value], 'field1, field2');
```

#### Get Record SQL:
```php
$sql = "SELECT MAX(field) as max_value FROM {table_name} WHERE companyid = :companyid";
$result = $DB->get_record_sql($sql, ['companyid' => $companyid]);
```

### Cache TTL (Time To Live)
- Default: **3600 seconds (1 hour)**
- Defined in externallib.php
- Cache expires after 1 hour from `timecreated`

---

## 🎯 What Functions Need to Be Created

### 1. `local_alx_report_api_cache_clear_company($companyid)`
**Purpose:** Clear all cache entries for a specific company

**Parameters:**
- `$companyid` (int) - Company ID

**Returns:**
- `int` - Number of cache entries deleted

**Implementation:**
```php
function local_alx_report_api_cache_clear_company($companyid) {
    global $DB;
    
    // Count entries before deleting
    $count = $DB->count_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    
    // Delete all cache entries for this company
    $DB->delete_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    
    return $count;
}
```

### 2. `local_alx_report_api_get_cache_stats($companyid)`
**Purpose:** Get cache statistics for a company

**Parameters:**
- `$companyid` (int) - Company ID

**Returns:**
- `object` - Cache statistics object with properties:
  - `total_entries` (int) - Number of cache entries
  - `last_update` (int|null) - Timestamp of most recent cache entry
  - `expires_at` (int|null) - When cache will expire
  - `is_expired` (bool) - Whether cache is expired
  - `cache_enabled` (int) - Whether caching is enabled (0 or 1)

**Implementation:**
```php
function local_alx_report_api_get_cache_stats($companyid) {
    global $DB;
    
    $stats = new stdClass();
    
    // Get total cache entries
    $stats->total_entries = $DB->count_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    
    // Get last update time
    if ($stats->total_entries > 0) {
        $sql = "SELECT MAX(timecreated) as last_update 
                FROM {" . constants::TABLE_CACHE . "} 
                WHERE companyid = :companyid";
        $result = $DB->get_record_sql($sql, ['companyid' => $companyid]);
        $stats->last_update = $result->last_update;
        
        // Calculate expiry time (1 hour TTL)
        $stats->expires_at = $stats->last_update + 3600;
        $stats->is_expired = (time() > $stats->expires_at);
    } else {
        $stats->last_update = null;
        $stats->expires_at = null;
        $stats->is_expired = true;
    }
    
    // Check if cache is enabled
    $stats->cache_enabled = local_alx_report_api_get_company_setting($companyid, 'enable_cache', 1);
    
    return $stats;
}
```

---

## 🔍 Key Insights from Analysis

### 1. **NO Cache Functions Exist Yet**
- lib.php has NO cache-related functions
- Cache is only cleared directly in populate_reporting_table.php
- This is the FIRST time adding cache helper functions

### 2. **Correct Field Names (CRITICAL)**
- Cache table: `timecreated` (NOT `created_at`)
- Settings table: `setting_value` (NOT `value`)
- Using wrong field names causes 500 errors

### 3. **Function Already Exists**
- `local_alx_report_api_get_company_setting()` EXISTS
- Can be used to check if cache is enabled
- No need to query settings table directly

### 4. **Constants Usage**
- Always use `constants::TABLE_CACHE`
- Always use `constants::TABLE_SETTINGS`
- Never hardcode table names

### 5. **Moodle Patterns**
- Use `global $DB;` at start of function
- Use `$DB->count_records()` for counting
- Use `$DB->delete_records()` for deleting
- Use `$DB->get_record_sql()` for complex queries
- Use `{table_name}` in SQL (Moodle replaces with prefix)

### 6. **Return Types**
- Count functions return `int`
- Stats functions return `object` (stdClass)
- Boolean checks return `bool`

---

## ✅ Why Previous Attempts Failed

### Attempt 1 & 2 Failures:
1. ❌ Called `local_alx_report_api_get_company_setting()` - **This function EXISTS, so this was correct**
2. ❌ Used `created_at` instead of `timecreated` - **WRONG field name**
3. ❌ Used `value` instead of `setting_value` - **WRONG field name**
4. ❌ Didn't use `constants::TABLE_CACHE` - **Should use constants**

### The Real Issue:
The error "Call to undefined function local_alx_report_api_get_cache_stats()" means:
- The UI code was calling the function
- But the function wasn't added to lib.php yet
- OR the function had a syntax error preventing it from being defined

---

## 🎯 Correct Implementation Strategy

### Step 1: Add Functions to lib.php
- Add both functions at the END of lib.php (before closing `?>` if it exists)
- Use correct field names
- Use constants for table names
- Follow existing function patterns

### Step 2: Add Handler to populate_reporting_table.php
- Add AFTER cleanup handler
- Use `required_param()` for parameters
- Use `redirect()` with success message
- Include `sesskey()` check

### Step 3: Add UI Section
- Add AFTER "Clear Reporting Table Data" section
- Use company dropdown (same pattern as cleanup section)
- Display stats using the function
- Add clear button with confirmation

### Step 4: Test
- No syntax errors
- Function is defined
- UI calls function correctly
- Stats display correctly
- Clear button works

---

## 📝 Final Implementation Code

### lib.php (Add at end, around line 4740)

```php
/**
 * Clear all cache entries for a specific company.
 *
 * @param int $companyid Company ID
 * @return int Number of cache entries deleted
 */
function local_alx_report_api_cache_clear_company($companyid) {
    global $DB;
    
    $count = $DB->count_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    $DB->delete_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    
    return $count;
}

/**
 * Get cache statistics for a company.
 *
 * @param int $companyid Company ID
 * @return object Cache statistics
 */
function local_alx_report_api_get_cache_stats($companyid) {
    global $DB;
    
    $stats = new stdClass();
    $stats->total_entries = $DB->count_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
    
    if ($stats->total_entries > 0) {
        $sql = "SELECT MAX(timecreated) as last_update 
                FROM {" . constants::TABLE_CACHE . "} 
                WHERE companyid = :companyid";
        $result = $DB->get_record_sql($sql, ['companyid' => $companyid]);
        $stats->last_update = $result->last_update;
        $stats->expires_at = $stats->last_update + 3600;
        $stats->is_expired = (time() > $stats->expires_at);
    } else {
        $stats->last_update = null;
        $stats->expires_at = null;
        $stats->is_expired = true;
    }
    
    $stats->cache_enabled = local_alx_report_api_get_company_setting($companyid, 'enable_cache', 1);
    
    return $stats;
}
```

---

## ✅ Confidence Level: HIGH

This implementation will work because:
1. ✅ Uses correct field names (`timecreated`, `setting_value`)
2. ✅ Uses constants for table names
3. ✅ Follows existing function patterns
4. ✅ Uses existing `local_alx_report_api_get_company_setting()` function
5. ✅ Uses proper Moodle database API
6. ✅ Returns correct types
7. ✅ Has proper documentation
8. ✅ No syntax errors

**Ready to implement!**
