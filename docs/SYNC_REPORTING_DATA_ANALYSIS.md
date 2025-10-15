# Sync Reporting Data - Complete Analysis

**Date:** 2025-10-14  
**File:** `local/local_alx_report_api/sync_reporting_data.php`  
**Purpose:** Manual immediate sync without waiting for cron  
**Status:** ANALYSIS COMPLETE

---

## 🎯 YOUR INTENT

You created this page for **immediate manual sync** so admins don't have to wait for the scheduled task (cron) to run.

**Use Cases:**
1. Admin makes changes to course completions → Wants to sync NOW
2. New users enrolled → Wants reporting table updated immediately
3. Testing/debugging → Needs to see changes right away
4. After bulk operations → Sync all changes at once

---

## 🔍 HOW IT CURRENTLY WORKS

### The Page Offers 3 Actions:

#### 1. **Sync Recent Changes** (`sync_changes`)
```
Purpose: Sync only recent changes (last X hours)
Default: Last 1 hour
Process: Calls local_alx_report_api_populate_reporting_table()
Result: Updates/creates records for changed data
```

#### 2. **Full Company Sync** (`sync_full`)
```
Purpose: Re-sync ALL data for a specific company
Requirement: Must select a company
Process: Calls local_alx_report_api_populate_reporting_table()
Result: Processes all records for that company
```

#### 3. **Cleanup Orphaned Records** (`cleanup`)
```
Purpose: Mark deleted records (users no longer in company)
Process: Finds records where user not in company_users
Result: Sets is_deleted = 1 for orphaned records
```

---

## ⚠️ THE PROBLEM YOU'RE FACING

Looking at the code, I can see **THE ISSUE**:

### Line 108-109:
```php
// Call sync function (false = no progress output to avoid JS errors)
$result = local_alx_report_api_populate_reporting_table($companyid, 1000, false);
```

**The Problem:**
- It calls `populate_reporting_table()` which is designed for **FULL population**
- It does NOT call a **sync** function that looks for recent changes
- It processes ALL records, not just changed ones

### What Should Happen:

**For "Sync Recent Changes":**
```php
// Should call a sync function that:
1. Finds records modified in last X hours
2. Updates only those records
3. Fast and efficient
```

**What Actually Happens:**
```php
// Currently calls populate function that:
1. Processes ALL records for the company
2. Checks each one (exists? update : create)
3. Slow and inefficient for "recent changes"
```

---

## 🔬 DETAILED CODE ANALYSIS

### Function Called: `local_alx_report_api_populate_reporting_table()`

**Location:** `lib.php` line 523

**What it does:**
1. Gets ALL users in company
2. Gets ALL courses for company
3. Builds complex query for ALL user-course combinations
4. Processes in batches of 1000
5. For each record: Check if exists → Update or Insert

**Problem:**
- This is a **FULL POPULATION** function
- NOT a **SYNC CHANGES** function
- Processes everything, not just changes

### What's Missing: A True Sync Function

**You need a function like:**
```php
function local_alx_report_api_sync_recent_changes($companyid, $hours_back) {
    // 1. Find course completions modified in last X hours
    $sql = "SELECT * FROM {course_completions} 
            WHERE timecompleted >= ?";
    
    // 2. Find module completions modified in last X hours
    $sql = "SELECT * FROM {course_modules_completion} 
            WHERE timemodified >= ?";
    
    // 3. Find enrollment changes in last X hours
    $sql = "SELECT * FROM {user_enrolments} 
            WHERE timemodified >= ?";
    
    // 4. Update ONLY those specific records in reporting table
    // Much faster than processing everything!
}
```

---

## 📊 COMPARISON

### Current Behavior:

| Action | What Happens | Performance |
|--------|--------------|-------------|
| Sync Recent Changes | Processes ALL records | ❌ Slow (processes 3,313 records) |
| Full Company Sync | Processes ALL records | ✅ Correct (meant to be full) |
| Cleanup | Marks orphaned records | ✅ Correct |

### Expected Behavior:

| Action | What Should Happen | Performance |
|--------|-------------------|-------------|
| Sync Recent Changes | Process ONLY changed records | ✅ Fast (maybe 10-50 records) |
| Full Company Sync | Processes ALL records | ✅ Correct |
| Cleanup | Marks orphaned records | ✅ Correct |

---

## 🎯 THE REAL ISSUE

### Why "Sync Recent Changes" Doesn't Work as Expected:

1. **No Time Filter Applied**
   - The `$hours_back` parameter is captured (line 43)
   - But it's NOT passed to the populate function
   - The populate function doesn't accept a time filter parameter

2. **Wrong Function Called**
   - `populate_reporting_table()` = Full population
   - Should call: `sync_recent_changes()` = Incremental sync

3. **Scheduled Task Has It Right**
   - Look at `sync_reporting_data_task.php` line 200-250
   - It has `sync_company_changes()` function
   - That function DOES look for recent changes
   - **But the manual sync page doesn't use it!**

---

## 💡 THE SOLUTION

### Option 1: Use the Scheduled Task Logic (Recommended)

The scheduled task already has the correct logic in `sync_company_changes()`:

```php
// In sync_reporting_data_task.php line 200
private function sync_company_changes($companyid, $hours_back, ...) {
    // Find users with recent course completion changes
    $completion_sql = "SELECT DISTINCT cc.userid, cc.course as courseid
                      FROM {course_completions} cc
                      WHERE cc.timecompleted >= :cutoff_time ...";
    
    // Find users with recent module completion changes
    $module_sql = "SELECT DISTINCT cmc.userid, cm.course as courseid
                  FROM {course_modules_completion} cmc
                  WHERE cmc.timemodified >= :cutoff_time ...";
    
    // Update ONLY those specific records
    foreach ($updates_to_process as $update) {
        local_alx_report_api_update_reporting_record(
            $update->userid, 
            $companyid, 
            $update->courseid
        );
    }
}
```

**This is the RIGHT way to sync recent changes!**

### Option 2: Create a Public Sync Function

Move the scheduled task logic to `lib.php` as a public function:

```php
// In lib.php
function local_alx_report_api_sync_recent_changes($companyid, $hours_back) {
    // Copy logic from sync_reporting_data_task.php
    // Make it public so sync_reporting_data.php can call it
}
```

Then in `sync_reporting_data.php`:
```php
case 'sync_changes':
    // Call the NEW sync function
    $result = local_alx_report_api_sync_recent_changes($companyid, $hours_back);
    break;
```

---

## 🔧 WHAT NEEDS TO BE FIXED

### File: `sync_reporting_data.php`

**Line 108-109 (Current):**
```php
// Call sync function (false = no progress output to avoid JS errors)
$result = local_alx_report_api_populate_reporting_table($companyid, 1000, false);
```

**Should Be:**
```php
// Call ACTUAL sync function that looks for recent changes
$result = local_alx_report_api_sync_recent_changes($companyid, $hours_back);
```

### File: `lib.php`

**Need to Add:**
```php
function local_alx_report_api_sync_recent_changes($companyid, $hours_back) {
    // Implementation from sync_reporting_data_task.php
    // But made public and callable from web interface
}
```

---

## 📝 ABOUT THE "6 HOURS" MISTAKE

### Where I Got It Wrong:

I said: "Scheduled task runs every 6 hours"

**Truth:**
- The scheduled task frequency is configured in Moodle admin
- NOT hardcoded in the task file
- Could be 1 hour, 6 hours, 12 hours, or any interval
- Default is whatever admin sets in: Site Administration → Server → Scheduled tasks

**The Code Shows:**
```php
// Line 62 in sync_reporting_data_task.php
$sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
```

This is the **lookback period** (how far back to check for changes), NOT the task frequency.

**Apologies for the confusion!**

---

## 🎓 SUMMARY

### What You Built:
✅ Manual sync page for immediate updates  
✅ Three sync options (changes, full, cleanup)  
✅ Nice UI with progress display  

### What's Not Working:
❌ "Sync Recent Changes" processes ALL records instead of just changed ones  
❌ Uses populate function instead of sync function  
❌ Doesn't utilize the `$hours_back` parameter effectively  

### Why It's Not Working:
- Wrong function called (`populate_reporting_table` vs `sync_recent_changes`)
- The correct sync logic exists in scheduled task but isn't reused
- No public sync function in lib.php for manual sync to call

### The Fix:
1. Extract sync logic from scheduled task
2. Make it a public function in lib.php
3. Call that function from sync_reporting_data.php
4. Pass the `$hours_back` parameter properly

---

## 🚀 NEXT STEPS

1. **Confirm Understanding** - Is this analysis correct?
2. **Decide Approach** - Option 1 (reuse task logic) or Option 2 (new function)?
3. **Implement Fix** - Create the proper sync function
4. **Test** - Verify it only processes changed records
5. **Document** - Update help text to explain the difference

---

**Ready to implement the fix when you approve!**
