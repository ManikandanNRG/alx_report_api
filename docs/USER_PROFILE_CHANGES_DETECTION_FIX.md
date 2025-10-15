# User Profile Changes Detection Fix

## 🔴 The Problem

When you changed user fields like firstname, lastname, email, or username in Moodle and ran manual sync, the reporting table was NOT updated.

**Test Case:**
- Changed lastname for users 5 and 6
- Ran manual sync
- ❌ No updates detected
- ❌ Reporting table still showed old lastname

## 🔍 Root Cause Analysis

The manual sync function only detected 3 types of changes:

1. ✅ **Course completions** - Detected via `{course_completions}.timecompleted`
2. ✅ **Module completions** - Detected via `{course_modules_completion}.timemodified`
3. ✅ **Enrollments** - Detected via `{user_enrolments}.timemodified`
4. ❌ **User profile changes** - NOT DETECTED!

The function was missing a query to detect when user profile fields change.

## ✅ The Solution

Added a 4th detection query to find users with recent profile changes:

```sql
SELECT DISTINCT u.id as userid, r.courseid
FROM {user} u
JOIN {company_users} cu ON cu.userid = u.id
JOIN {local_alx_api_reporting} r ON r.userid = u.id AND r.companyid = cu.companyid
WHERE u.timemodified >= :cutoff_time
AND cu.companyid = :companyid
AND u.deleted = 0
AND u.suspended = 0
AND u.timemodified > r.last_updated
```

This query:
- Checks the `{user}.timemodified` field (updated when profile changes)
- Compares it with `{local_alx_api_reporting}.last_updated`
- Finds all user-course combinations that need updating

## 📝 What Gets Updated Now

When you change any of these user fields:
- ✅ firstname
- ✅ lastname
- ✅ email
- ✅ username

And run manual sync, it will:
1. Detect the user profile change via `timemodified`
2. Call `local_alx_report_api_update_reporting_record()`
3. Fetch fresh data from Moodle
4. Update ALL fields in the reporting table
5. Update the `last_updated` timestamp

## 🎯 Testing Instructions

1. **Change a user's lastname** in Moodle (e.g., users 5 and 6)
2. **Run manual sync** from Control Center
3. **Check the results:**
   - Should show "Records Updated: 2" (or more if they have multiple courses)
   - Should show which users were updated
4. **Verify in reporting table:**
   - Lastname should be updated
   - `last_updated` timestamp should be current

## 📊 Files Modified

**File:** `local/local_alx_report_api/lib.php`
**Function:** `local_alx_report_api_manual_sync_recent_changes()`
**Lines:** Added ~15 lines after enrollment detection query

## 🔄 How It Works Now

```
Manual Sync Process:
1. Get cutoff time (default: last 7 days)
2. Find changes:
   ✅ Course completions (timecompleted > last_updated)
   ✅ Module completions (timemodified > last_updated)
   ✅ Enrollments (timemodified > last_updated)
   ✅ User profiles (timemodified > last_updated) ← NEW!
3. Remove duplicates
4. Update each changed record
5. Clear cache if enabled
```

## ✅ Result

Now when you:
- Change user firstname, lastname, email, or username
- Run manual sync
- ✅ Changes are detected and updated in reporting table!

The same logic also applies to the auto sync (cron task) since it uses the same update function.
