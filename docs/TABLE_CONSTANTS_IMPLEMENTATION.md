# Table Name Constants - Implementation Guide

## Status: PARTIAL - Constants Created, Migration In Progress

## Overview
Created centralized table name constants to prevent typos and make refactoring easier.

## Constants Class Created ✅
**File:** `local/local_alx_report_api/classes/constants.php`

```php
namespace local_alx_report_api;

class constants {
    const TABLE_REPORTING = 'local_alx_api_reporting';
    const TABLE_LOGS = 'local_alx_api_logs';
    const TABLE_SETTINGS = 'local_alx_api_settings';
    const TABLE_SYNC_STATUS = 'local_alx_api_sync_status';
    const TABLE_CACHE = 'local_alx_api_cache';
    const TABLE_ALERTS = 'local_alx_api_alerts';
}
```

## Current Usage Statistics
Total hardcoded table name occurrences: **271**

Breakdown by table:
- `local_alx_api_reporting`: 86 occurrences
- `local_alx_api_logs`: 111 occurrences
- `local_alx_api_settings`: 14 occurrences
- `local_alx_api_sync_status`: 17 occurrences
- `local_alx_api_cache`: 27 occurrences
- `local_alx_api_alerts`: 16 occurrences

## Migration Strategy

### Phase 1: Core Files (Priority)
- [ ] lib.php (~40 occurrences)
- [ ] externallib.php (~30 occurrences)
- [ ] classes/task/*.php (~20 occurrences)

### Phase 2: Dashboard Files
- [ ] control_center.php
- [ ] monitoring_dashboard_new.php
- [ ] monitoring_dashboard.php

### Phase 3: Utility Files
- [ ] sync_reporting_data.php
- [ ] populate_reporting_table.php
- [ ] export_data.php

### Phase 4: Other Files
- [ ] settings.php
- [ ] test_*.php files
- [ ] debug_*.php files

## How to Use Constants

### Step 1: Add use statement at top of file
```php
<?php
defined('MOODLE_INTERNAL') || die();

use local_alx_report_api\constants;
```

### Step 2: Replace hardcoded strings

**Before:**
```php
$DB->get_records('local_alx_api_logs');
$DB->count_records('local_alx_api_reporting');
if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
```

**After:**
```php
$DB->get_records(constants::TABLE_LOGS);
$DB->count_records(constants::TABLE_REPORTING);
if ($DB->get_manager()->table_exists(constants::TABLE_CACHE)) {
```

## Benefits

1. **Single Source of Truth** - Change table name in one place
2. **No Typos** - IDE autocomplete prevents mistakes
3. **Easier Refactoring** - Find all usages instantly
4. **Better Maintainability** - Clear what tables exist
5. **Documentation** - Constants serve as table inventory

## Testing Checklist

After migrating each file:
- [ ] No PHP syntax errors
- [ ] File loads without errors
- [ ] Database queries work correctly
- [ ] No broken functionality

## Notes

- This is a **non-breaking change** - both old and new code work
- Can be done incrementally without risk
- Existing hardcoded strings still work fine
- New code should use constants from now on

## Timeline

- **Phase 1:** Next development session (1-2 hours)
- **Phase 2:** Following week
- **Phase 3-4:** As files are touched for other reasons

---

**Created:** 2025-10-12  
**Status:** Constants class ready, migration pending  
**Priority:** Medium (improves code quality, not urgent)
