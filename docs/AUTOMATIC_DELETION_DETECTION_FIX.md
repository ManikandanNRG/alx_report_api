# Automatic Deletion Detection Fix - Complete Explanation

## 🔴 The Problem (Issue #4)

### Current Behavior:
- ✅ User creates/enrolls → Auto sync updates reporting table
- ✅ User updates profile → Auto sync updates reporting table (after v1.8.1)
- ❌ User deletes/suspends/unenrolls → **NOT automatically updated!**

### The Issue:
When you delete a user, suspend a user, unenroll from course, or hide a course in Moodle:
- The record stays in the reporting table with `is_deleted = 0`
- The API still returns this deleted data
- Only way to clean it up is manually clicking "Cleanup Orphaned Records" button
- This is not automatic and requires manual intervention

### Example Scenario:
1. User "John" completes a course
2. Record added to reporting table
3. Admin deletes John's account in Moodle
4. ❌ John's record still in reporting table
5. ❌ API still returns John's data
6. Manual cleanup needed to remove it

---

## 🎯 The Solution

### What I Implemented:
Added **automatic deletion detection** to both manual sync and auto sync (cron).

### Types of Deletions Detected:
1. **User deleted** - `user.deleted = 1`
2. **User suspended** - `user.suspended = 1`
3. **User removed from company** - No record in `company_users`
4. **User unenrolled from course** - No record in `user_enrolments`
5. **Course hidden** - `course.visible = 0`

---

## 📝 Code Changes Explained

### Change 1: Manual Sync Function (lib.php)

#### Location: `local/local_alx_report_api/lib.php`
#### Function: `local_alx_report_api_sync_recent_changes()`

#### A. Initialize Deletion Counter

**Before:**
```php
$stats = [
    'success' => true,
    'total_processed' => 0,
    'records_created' => 0,
    'records_updated' => 0,
    'companies_processed' => 0,
    'errors' => []
];
```

**After:**
```php
$stats = [
    'success' => true,
    'total_processed' => 0,
    'records_created' => 0,
    'records_updated' => 0,
    'records_deleted' => 0,  // ← NEW: Track deletions
    'companies_processed' => 0,
    'errors' => []
];
```

**Why:** We need to track how many records were marked as deleted.

---

#### B. Track Deletions from Update Function

**Before:**
```php
foreach ($unique_changes as $change) {
    try {
        $result = local_alx_report_api_update_reporting_record(
            $change->userid,
            $company->id,
            $change->courseid
        );
        
        if ($result['created'] || $result['updated']) {
            $stats['total_processed']++;
            if ($result['created']) {
                $stats['records_created']++;
            } else if ($result['updated']) {
                $stats['records_updated']++;
            }
        }
    } catch (Exception $e) {
        $stats['errors'][] = "Error updating...";
    }
}
```

**After:**
```php
foreach ($unique_changes as $change) {
    try {
        $result = local_alx_report_api_update_reporting_record(
            $change->userid,
            $company->id,
            $change->courseid
        );
        
        if ($result['created'] || $result['updated']) {
            $stats['total_processed']++;
            if ($result['created']) {
                $stats['records_created']++;
            } else if ($result['updated']) {
                $stats['records_updated']++;
            }
        } else if (isset($result['deleted']) && $result['deleted']) {  // ← NEW
            $stats['total_processed']++;
            $stats['records_deleted']++;
        }
    } catch (Exception $e) {
        $stats['errors'][] = "Error updating...";
    }
}
```

**Why:** The update function already detects if a user is deleted/suspended and returns `['deleted' => true]`. We now count these.

---

#### C. Add Proactive Deletion Detection

**NEW CODE ADDED:**
```php
// 5. Detect and mark deleted/suspended users and unenrolled courses
try {
    $deletion_sql = "
        SELECT DISTINCT r.userid, r.courseid
        FROM {local_alx_api_reporting} r
        WHERE r.companyid = :companyid
        AND r.is_deleted = 0
        AND (
            -- User is deleted or suspended
            EXISTS (
                SELECT 1 FROM {user} u
                WHERE u.id = r.userid
                AND (u.deleted = 1 OR u.suspended = 1)
            )
            -- OR user no longer in company
            OR NOT EXISTS (
                SELECT 1 FROM {company_users} cu
                WHERE cu.userid = r.userid
                AND cu.companyid = :companyid2
            )
            -- OR user no longer enrolled in course
            OR NOT EXISTS (
                SELECT 1 FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = r.userid
                AND e.courseid = r.courseid
            )
            -- OR course is hidden
            OR EXISTS (
                SELECT 1 FROM {course} c
                WHERE c.id = r.courseid
                AND c.visible = 0
            )
        )";
    
    $records_to_delete = $DB->get_records_sql($deletion_sql, [
        'companyid' => $company->id,
        'companyid2' => $company->id
    ]);
    
    foreach ($records_to_delete as $record) {
        try {
            if (local_alx_report_api_soft_delete_reporting_record($record->userid, $company->id, $record->courseid)) {
                $stats['total_processed']++;
                $stats['records_deleted']++;
            }
        } catch (Exception $e) {
            $stats['errors'][] = "Error deleting user {$record->userid}, course {$record->courseid}: " . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $stats['errors'][] = "Company {$company->id} deletion detection error: " . $e->getMessage();
}
```

**What This Does:**

1. **Finds orphaned records** - Records in reporting table where:
   - User is deleted (`user.deleted = 1`)
   - User is suspended (`user.suspended = 1`)
   - User no longer in company (missing from `company_users`)
   - User no longer enrolled (missing from `user_enrolments`)
   - Course is hidden (`course.visible = 0`)

2. **Marks them as deleted** - Calls `local_alx_report_api_soft_delete_reporting_record()` which:
   - Sets `is_deleted = 1`
   - Updates `last_updated` timestamp
   - Updates `timemodified` timestamp

3. **Tracks statistics** - Increments `records_deleted` counter

**Why:** This proactively finds ALL orphaned records, not just ones that changed recently.

---

### Change 2: Auto Sync Task (sync_reporting_data_task.php)

#### Location: `local/local_alx_report_api/classes/task/sync_reporting_data_task.php`
#### Function: `sync_company_changes()`

#### A. Track Deletions from Update Function

**Before:**
```php
foreach ($updates_to_process as $update) {
    try {
        $update_result = local_alx_report_api_update_reporting_record($update->userid, $companyid, $update->courseid);
        
        if ($update_result['created']) {
            $stats['records_created']++;
        } else if ($update_result['updated']) {
            $stats['records_updated']++;
        }
    } catch (\Exception $e) {
        $error_msg = "Error updating...";
        $stats['errors'][] = $error_msg;
    }
}
```

**After:**
```php
foreach ($updates_to_process as $update) {
    try {
        $update_result = local_alx_report_api_update_reporting_record($update->userid, $companyid, $update->courseid);
        
        if ($update_result['created']) {
            $stats['records_created']++;
        } else if ($update_result['updated']) {
            $stats['records_updated']++;
        } else if (isset($update_result['deleted']) && $update_result['deleted']) {  // ← NEW
            if (!isset($stats['records_deleted'])) {
                $stats['records_deleted'] = 0;
            }
            $stats['records_deleted']++;
        }
    } catch (\Exception $e) {
        $error_msg = "Error updating...";
        $stats['errors'][] = $error_msg;
    }
}
```

**Why:** Same as manual sync - track when update function detects deletions.

---

#### B. Add Proactive Deletion Detection

**NEW CODE ADDED:**
```php
// Detect and mark deleted/suspended users and unenrolled courses
try {
    $deletion_sql = "
        SELECT DISTINCT r.userid, r.courseid
        FROM {local_alx_api_reporting} r
        WHERE r.companyid = :companyid
        AND r.is_deleted = 0
        AND (
            -- User is deleted or suspended
            EXISTS (
                SELECT 1 FROM {user} u
                WHERE u.id = r.userid
                AND (u.deleted = 1 OR u.suspended = 1)
            )
            -- OR user no longer in company
            OR NOT EXISTS (
                SELECT 1 FROM {company_users} cu
                WHERE cu.userid = r.userid
                AND cu.companyid = :companyid2
            )
            -- OR user no longer enrolled in course
            OR NOT EXISTS (
                SELECT 1 FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = r.userid
                AND e.courseid = r.courseid
            )
            -- OR course is hidden
            OR EXISTS (
                SELECT 1 FROM {course} c
                WHERE c.id = r.courseid
                AND c.visible = 0
            )
        )";
    
    $records_to_delete = $DB->get_records_sql($deletion_sql, [
        'companyid' => $companyid,
        'companyid2' => $companyid
    ]);
    
    if (!isset($stats['records_deleted'])) {
        $stats['records_deleted'] = 0;
    }
    
    foreach ($records_to_delete as $record) {
        // Check timeout
        if (time() - $start_time > $max_execution_time - 60) {
            break;
        }
        
        try {
            if (local_alx_report_api_soft_delete_reporting_record($record->userid, $companyid, $record->courseid)) {
                $stats['records_deleted']++;
            }
        } catch (\Exception $e) {
            $stats['errors'][] = "Error deleting user {$record->userid}, course {$record->courseid}: " . $e->getMessage();
        }
    }
} catch (\Exception $e) {
    $stats['errors'][] = "Deletion detection error: " . $e->getMessage();
}
```

**What This Does:** Same as manual sync - finds and marks orphaned records.

**Additional Feature:** Includes timeout check to prevent cron from running too long.

---

## 🔄 How It Works Now

### Manual Sync Process:
```
1. Detect changes (completions, modules, enrollments, profiles)
2. Update changed records
3. ← NEW: Detect deletions (deleted users, unenrolled, hidden courses)
4. ← NEW: Mark orphaned records as deleted
5. Clear cache
6. Return statistics (created, updated, deleted)
```

### Auto Sync Process (Cron):
```
1. Run every 15 minutes
2. Detect changes (completions, modules, enrollments, profiles)
3. Update changed records
4. ← NEW: Detect deletions (deleted users, unenrolled, hidden courses)
5. ← NEW: Mark orphaned records as deleted
6. Update last sync timestamp
7. Return statistics (created, updated, deleted)
```

---

## 📊 SQL Query Explanation

### The Deletion Detection Query:

```sql
SELECT DISTINCT r.userid, r.courseid
FROM {local_alx_api_reporting} r
WHERE r.companyid = :companyid
AND r.is_deleted = 0  -- Only check active records
AND (
    -- Condition 1: User is deleted or suspended
    EXISTS (
        SELECT 1 FROM {user} u
        WHERE u.id = r.userid
        AND (u.deleted = 1 OR u.suspended = 1)
    )
    
    -- Condition 2: User no longer in company
    OR NOT EXISTS (
        SELECT 1 FROM {company_users} cu
        WHERE cu.userid = r.userid
        AND cu.companyid = :companyid2
    )
    
    -- Condition 3: User no longer enrolled in course
    OR NOT EXISTS (
        SELECT 1 FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE ue.userid = r.userid
        AND e.courseid = r.courseid
    )
    
    -- Condition 4: Course is hidden
    OR EXISTS (
        SELECT 1 FROM {course} c
        WHERE c.id = r.courseid
        AND c.visible = 0
    )
)
```

**What It Finds:**
- Records in reporting table that should be deleted
- Checks 4 different deletion scenarios
- Only processes records not already marked as deleted

---

## ✅ Benefits

### Before This Fix:
- ❌ Deletions not detected automatically
- ❌ Orphaned records stay in reporting table
- ❌ API returns deleted user data
- ❌ Manual cleanup required
- ❌ Data accuracy issues

### After This Fix:
- ✅ Deletions detected automatically
- ✅ Orphaned records marked as deleted
- ✅ API excludes deleted records
- ✅ No manual cleanup needed
- ✅ Data stays accurate

---

## 🧪 Testing Scenarios

### Test 1: Delete User
1. Delete a user in Moodle
2. Run manual sync OR wait for auto sync
3. ✅ User's records marked as `is_deleted = 1`
4. ✅ API no longer returns this user

### Test 2: Suspend User
1. Suspend a user in Moodle
2. Run manual sync OR wait for auto sync
3. ✅ User's records marked as `is_deleted = 1`
4. ✅ API no longer returns this user

### Test 3: Unenroll User
1. Unenroll user from a course
2. Run manual sync OR wait for auto sync
3. ✅ That specific user-course record marked as deleted
4. ✅ API no longer returns this enrollment

### Test 4: Hide Course
1. Hide a course (set visible = 0)
2. Run manual sync OR wait for auto sync
3. ✅ All records for that course marked as deleted
4. ✅ API no longer returns this course data

### Test 5: Remove User from Company
1. Remove user from company
2. Run manual sync OR wait for auto sync
3. ✅ All user's records for that company marked as deleted
4. ✅ API no longer returns this user for that company

---

## 📈 Statistics Tracking

### New Stat: `records_deleted`

**Manual Sync Returns:**
```php
[
    'success' => true,
    'total_processed' => 15,
    'records_created' => 5,
    'records_updated' => 7,
    'records_deleted' => 3,  // ← NEW!
    'companies_processed' => 1,
    'errors' => []
]
```

**Auto Sync Logs:**
```
Companies processed: 3
Total users updated: 25
Total records updated: 40
Total records created: 10
Total records deleted: 5  // ← NEW!
```

---

## 🎯 Summary

### Files Modified:
1. `local/local_alx_report_api/lib.php` - Manual sync
2. `local/local_alx_report_api/classes/task/sync_reporting_data_task.php` - Auto sync

### Lines Added:
- ~80 lines total (40 per file)

### What Changed:
1. Added `records_deleted` to stats tracking
2. Added deletion detection SQL query
3. Added loop to mark orphaned records as deleted
4. Added error handling for deletion process

### Result:
Both manual sync and auto sync now automatically detect and mark deletions, eliminating the need for manual "Cleanup Orphaned Records" intervention!

---

## 🚀 Next Steps

1. Review this implementation
2. Test with real deletion scenarios
3. Update version to 1.8.2
4. Deploy to production
5. Monitor deletion statistics in sync logs
