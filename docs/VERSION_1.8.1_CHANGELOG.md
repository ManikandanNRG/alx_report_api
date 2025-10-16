# Version 1.8.1 Changelog

**Release Date:** October 16, 2025  
**Version:** 1.8.1  
**Moodle Version:** 2024101600

---

## 🎯 Overview

This release fixes a critical bug where user profile changes (firstname, lastname, email, username) were not being detected and updated by manual sync or auto sync.

---

## 🐛 Bug Fixes

### Critical: User Profile Changes Not Syncing

**Issue:**
- When users changed their firstname, lastname, email, or username in Moodle
- Manual sync did not detect these changes
- Auto sync (cron) did not detect these changes
- Reporting table showed outdated user information

**Root Cause:**
- Both sync functions only detected 3 types of changes:
  1. Course completions
  2. Module completions
  3. Enrollments
- Missing detection for user profile changes via `{user}.timemodified`

**Fix:**
- Added 4th detection query to both manual sync and auto sync
- Now detects when `{user}.timemodified > {local_alx_api_reporting}.last_updated`
- Updates all fields in reporting table when user profile changes

**Files Modified:**
1. `local/local_alx_report_api/lib.php`
   - Function: `local_alx_report_api_manual_sync_recent_changes()`
   - Added user profile detection query

2. `local/local_alx_report_api/classes/task/sync_reporting_data_task.php`
   - Function: `sync_company_changes()`
   - Added user profile detection query

---

## ✅ What's Fixed

### Fields That Now Update Correctly:

**User Profile Fields:**
- ✅ firstname
- ✅ lastname
- ✅ email
- ✅ username

**Course Fields:**
- ✅ coursename (already worked, now confirmed)

**Completion Fields:**
- ✅ timecompleted (already worked)
- ✅ timestarted (already worked)
- ✅ percentage (already worked)
- ✅ status (already worked)

---

## 🔄 How It Works Now

### Manual Sync (Control Center)
Detects 4 types of changes:
1. ✅ Course completions
2. ✅ Module completions
3. ✅ Enrollments
4. ✅ **User profile changes** ← NEW!

### Auto Sync (Cron Task)
Detects 4 types of changes:
1. ✅ Course completions
2. ✅ Module completions
3. ✅ Enrollments
4. ✅ **User profile changes** ← NEW!

---

## 🧪 Testing Performed

### Test Case 1: Lastname Change
- Changed lastname for users 5 and 6
- Ran manual sync
- ✅ Both users updated in reporting table
- ✅ Shows "Records Updated: 2+"

### Test Case 2: Email Change
- Changed user email
- Waited for auto sync (cron)
- ✅ Email updated automatically

### Test Case 3: Multiple Field Changes
- Changed firstname, lastname, and email
- Ran manual sync
- ✅ All fields updated correctly

---

## 📊 Impact

### Before Version 1.8.1:
- ❌ User profile changes ignored by sync
- ❌ Reporting table showed outdated user info
- ❌ Only "Populate Reporting Table" could fix it (slow)

### After Version 1.8.1:
- ✅ User profile changes detected automatically
- ✅ Reporting table stays up-to-date
- ✅ Both manual and auto sync work correctly

---

## 🚀 Upgrade Instructions

### Step 1: Backup
```bash
# Backup your current plugin
cp -r /path/to/moodle/local/local_alx_report_api /path/to/backup/
```

### Step 2: Upload New Version
1. Upload version 1.8.1 files to your Moodle server
2. Replace existing files in `local/local_alx_report_api/`

### Step 3: Run Upgrade
1. Go to: **Site Administration → Notifications**
2. Moodle will detect the version change (2024101500 → 2024101600)
3. Click "Upgrade Moodle database now"
4. Wait for upgrade to complete

### Step 4: Verify
1. Go to **Control Center**
2. Change a user's lastname in Moodle
3. Run **Manual Sync**
4. Check that the lastname updated in reporting table

---

## 📝 Technical Details

### New SQL Query Added (Both Syncs)

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
- Finds users whose profile was modified after their reporting record
- Joins with existing reporting records
- Filters by company and cutoff time
- Excludes deleted/suspended users

---

## 🔗 Related Issues

- Issue #3: User field changes not updating in manual sync
- Previous fixes in v1.8.0:
  - Manual sync duplicate prevention
  - Cache clearing after sync
  - Enable cache setting
  - Orphaned records email display

---

## 📚 Documentation

New documentation files:
- `docs/USER_PROFILE_SYNC_COMPLETE_SUMMARY.md` - Complete overview
- `docs/USER_PROFILE_CHANGES_DETECTION_FIX.md` - Technical details

---

## ⚠️ Breaking Changes

None. This is a bug fix release with no breaking changes.

---

## 🎯 Next Steps

After upgrading:
1. Test user profile changes sync
2. Monitor auto sync logs
3. Verify reporting table accuracy
4. Check manual sync statistics

---

## 📞 Support

If you encounter any issues:
1. Check Moodle error logs
2. Review sync statistics in Control Center
3. Verify version number: **1.8.1 (2024101600)**
4. Contact support with error details

---

## ✅ Checklist for Deployment

- [ ] Backup current plugin
- [ ] Upload version 1.8.1 files
- [ ] Run Moodle upgrade
- [ ] Verify version shows 1.8.1
- [ ] Test user profile change sync
- [ ] Monitor first auto sync run
- [ ] Confirm reporting table updates correctly

---

**Version:** 1.8.1  
**Build:** 2024101600  
**Status:** Ready for Production  
**Tested:** ✅ Passed
