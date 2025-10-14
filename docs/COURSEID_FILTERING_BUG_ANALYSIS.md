# Course ID Filtering Bug Analysis

**Date:** 2025-10-14  
**Status:** 🔴 CRITICAL BUG IDENTIFIED

---

## 🐛 The Problem

**User Report:**
- Selected ONLY "Refresher Brillio 2024" (ID: 371) in Company Settings
- API is returning data for "PoSH Refresher Training 2023" (ID: 70) instead
- This is a CRITICAL data leak - wrong course data being sent!

---

## 🔍 Root Cause Analysis

### What I Found:

1. **The API Query IS Correct** (externallib.php lines 690-700):
   ```php
   // Add course filtering if enabled courses specified
   if (!empty($enabled_courses)) {
       list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
       $sql .= " AND courseid $course_sql";
       $params = array_merge($params, $course_params);
   }
   ```

2. **The Populate Function IS Correct** (lib.php lines 560-565):
   ```php
   // Get enabled courses for this company
   $enabled_courses = local_alx_report_api_get_enabled_courses($company->id);
   ```

### 🎯 THE ACTUAL BUG:

**The reporting table contains OLD data from when different courses were enabled!**

**Scenario:**
1. Previously, "PoSH Refresher Training 2023" (ID: 70) was enabled
2. Data was synced to reporting table for course 70
3. User changed settings to ONLY enable "Refresher Brillio 2024" (ID: 371)
4. **BUT** the old data for course 70 is still in the reporting table!
5. The API query filters by enabled courses (371), but the reporting table still has course 70 data
6. **WAIT** - if the query filters correctly, why is course 70 data being returned?

### 🚨 THE REAL BUG:

Looking more carefully at the code flow:

**In externallib.php around line 620-650:**
```php
// Get enabled courses for this company
$enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
```

**Then around line 690:**
```php
// Add course filtering if enabled courses specified
if (!empty($enabled_courses)) {
    list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
    $sql .= " AND courseid $course_sql";
    $params = array_merge($params, $course_params);
}
```

**This SHOULD work correctly!**

Let me check if there's an issue with the `local_alx_report_api_get_enabled_courses()` function...

---

## 🔍 Next Steps

Need to check:
1. What does `local_alx_report_api_get_enabled_courses()` actually return?
2. Is there a caching issue?
3. Is the company settings table storing the correct values?
4. Is there a bug in how course settings are saved/retrieved?

