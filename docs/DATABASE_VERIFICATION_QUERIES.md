# Database Verification Queries for ALX Report API Plugin

## Overview
These queries will help you verify that all 6 tables are properly installed with the correct field names after your Bug 2 fix.

## Expected Tables (6 total)
1. `local_alx_api_logs` - API access logging
2. `local_alx_api_settings` - Company-specific settings  
3. `local_alx_api_reporting` - Pre-built reporting data
4. `local_alx_api_sync_status` - Sync status tracking
5. `local_alx_api_cache` - Performance caching
6. `local_alx_api_alerts` - Security and performance alerts

## Verification Queries

### 1. Check if all tables exist
```sql
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME LIKE 'mdl_local_alx_api_%'
ORDER BY TABLE_NAME;
```

### 2. Verify table structure for each table

#### Table 1: local_alx_api_logs (CRITICAL - Bug 2 fix)
```sql
DESCRIBE mdl_local_alx_api_logs;
```
**Expected key fields:**
- `timecreated` (should be present - Bug 2 fix)
- `timeaccessed` (should NOT exist)
- `company_shortname` (varchar/char)

#### Table 2: local_alx_api_settings
```sql
DESCRIBE mdl_local_alx_api_settings;
```
**Expected key fields:**
- `timecreated`, `timemodified`

#### Table 3: local_alx_api_reporting  
```sql
DESCRIBE mdl_local_alx_api_reporting;
```
**Expected key fields:**
- `timecreated`, `timemodified`, `last_updated`

#### Table 4: local_alx_api_sync_status
```sql
DESCRIBE mdl_local_alx_api_sync_status;
```
**Expected key fields:**
- `timecreated`, `timemodified`

#### Table 5: local_alx_api_cache
```sql
DESCRIBE mdl_local_alx_api_cache;
```
**Expected key fields:**
- `timecreated`, `timeaccessed` (Note: This table still uses timeaccessed for cache access tracking)

#### Table 6: local_alx_api_alerts
```sql
DESCRIBE mdl_local_alx_api_alerts;
```
**Expected key fields:**
- `timecreated`

### 3. Check for any old field names (should return empty)
```sql
-- This should return NO results if Bug 2 fix is properly applied
SELECT TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME LIKE 'mdl_local_alx_api_%'
  AND COLUMN_NAME = 'timeaccessed'
  AND TABLE_NAME != 'mdl_local_alx_api_cache';  -- Cache table legitimately uses timeaccessed
```

### 4. Verify indexes are properly created
```sql
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME LIKE 'mdl_local_alx_api_%'
ORDER BY TABLE_NAME, INDEX_NAME;
```

### 5. Check table record counts (to see if data exists)
```sql
SELECT 
  'local_alx_api_logs' as table_name, COUNT(*) as record_count FROM mdl_local_alx_api_logs
UNION ALL SELECT 
  'local_alx_api_settings', COUNT(*) FROM mdl_local_alx_api_settings  
UNION ALL SELECT 
  'local_alx_api_reporting', COUNT(*) FROM mdl_local_alx_api_reporting
UNION ALL SELECT 
  'local_alx_api_sync_status', COUNT(*) FROM mdl_local_alx_api_sync_status
UNION ALL SELECT 
  'local_alx_api_cache', COUNT(*) FROM mdl_local_alx_api_cache
UNION ALL SELECT 
  'local_alx_api_alerts', COUNT(*) FROM mdl_local_alx_api_alerts;
```

### 6. Test the critical logging functionality
```sql
-- Check recent API logs to verify timecreated field is working
SELECT userid, company_shortname, endpoint, timecreated, 
       FROM_UNIXTIME(timecreated) as created_datetime
FROM mdl_local_alx_api_logs 
ORDER BY timecreated DESC 
LIMIT 10;
```

## What to Look For

### ✅ SUCCESS Indicators:
- All 6 tables exist
- `mdl_local_alx_api_logs` has `timecreated` field (not `timeaccessed`)
- All tables have proper indexes
- No unexpected `timeaccessed` fields (except in cache table)

### ❌ FAILURE Indicators:
- Missing tables
- `mdl_local_alx_api_logs` still has `timeaccessed` field
- Missing required fields like `company_shortname`

## Plugin Version Check

First, check your current plugin version:
```sql
SELECT name, value 
FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' 
  AND name = 'version';
```

**Expected version:** `2024100803` or higher (this version includes Bug 2 fix)

## Installation Status Check

### If Plugin Version < 2024100803:
Your plugin needs to be upgraded to get the Bug 2 fix:
1. **Recommended:** Go to Site Administration → Notifications to trigger upgrade
2. **Alternative:** Run upgrade manually via CLI

### If Tables Are Missing Completely:
1. Uninstall the plugin completely
2. Reinstall with the corrected install.xml
3. This will create all tables with correct field names

### If Tables Exist But Have Wrong Field Names:
The upgrade script (version 2024100803) should automatically:
- Rename `timeaccessed` → `timecreated` in `local_alx_api_logs`
- Rename `created_at` → `timecreated` in `local_alx_api_reporting`  
- Rename `updated_at` → `timemodified` in `local_alx_api_reporting`
- Update indexes accordingly

## Manual Upgrade Trigger (if needed)
If automatic upgrade didn't run, you can trigger it manually:

```sql
-- Check current version
SELECT value FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' AND name = 'version';

-- If version is less than 2024100803, you can force upgrade by:
-- 1. Going to Site Administration → Notifications in Moodle
-- 2. Or running: php admin/cli/upgrade.php from command line
```

## Note About Cache Table
The `local_alx_api_cache` table legitimately uses `timeaccessed` for tracking when cache entries were last accessed. This is different from the logging table's `timecreated` field.

## Expected Field Names After Bug 2 Fix:
- ✅ `local_alx_api_logs.timecreated` (was timeaccessed)
- ✅ `local_alx_api_reporting.timecreated` (was created_at)  
- ✅ `local_alx_api_reporting.timemodified` (was updated_at)
- ✅ `local_alx_api_cache.timeaccessed` (legitimate use for cache access tracking)