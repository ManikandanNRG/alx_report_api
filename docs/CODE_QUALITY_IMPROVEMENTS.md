# Code Quality Improvements - Manager Feedback

## Date: 2025-10-11
## Priority: HIGH (Before next bug fixes)

---

## Issue 1: Duplicate Functions in control_center.php

### Problem:
Two JavaScript functions are defined twice in control_center.php

### Duplicates Found:
1. **`updateFieldStates()`** - Lines 2308 and 2747
2. **`disableField()`** - Lines 2392 and 2831

### Impact:
- Wastes memory
- Confusing for developers
- Hard to maintain (fix in one place, miss the other)
- Increases file size

### Solution:
Remove the duplicate definitions (keep only one of each)

### Time Estimate: 10 minutes

---

## Issue 2: Hardcoded Table Names

### Problem:
Table names are hardcoded throughout the code instead of using constants

### Current (Bad):
```php
$DB->get_records('local_alx_api_logs');
$DB->count_records('local_alx_api_reporting');
```

### Proposed (Good):
```php
// Define constants
class local_alx_report_api_tables {
    const LOGS = 'local_alx_api_logs';
    const REPORTING = 'local_alx_api_reporting';
    const CACHE = 'local_alx_api_cache';
    const SETTINGS = 'local_alx_api_settings';
    const ALERTS = 'local_alx_api_alerts';
    const SYNC_STATUS = 'local_alx_api_sync_status';
}

// Usage
$DB->get_records(local_alx_report_api_tables::LOGS);
```

### Benefits:
- Change table name in ONE place
- No typos
- Easier to maintain
- Better for refactoring

### Files to Update:
- lib.php
- externallib.php
- control_center.php
- monitoring_dashboard_new.php
- All other PHP files

### Time Estimate: 30-45 minutes

---

## Total Time: ~1 hour

## Priority Order:
1. Fix duplicate functions (10 min) - Quick win
2. Create table name constants (45 min) - Bigger improvement

---

## Status: Ready to implement
