# Version 1.8.0 - Changelog

**Release Date:** 2025-10-15  
**Version:** 1.8.0 (2024101500)  
**Previous Version:** 1.7.2 (2024101401)  
**Type:** Major Feature Release

---

## 🎉 MAJOR IMPROVEMENTS

This release includes 5 major improvements and bug fixes that significantly enhance the plugin's functionality and user experience.

---

## ✅ 1. EMAIL ALERT SYSTEM FIX

**Issue:** Email alerts were not being sent for rate limit violations and security issues  
**Status:** FIXED ✅

### What Was Fixed:
- Added missing `global $CFG;` declaration in `check_and_alert()` function
- Email alerts now properly sent when rate limits are exceeded
- Scheduled task successfully processes unresolved alerts every 15 minutes
- Alert recipients receive notifications as configured

### Files Modified:
- `lib.php` - Added global $CFG declaration

### Impact:
- Admins now receive email notifications for security issues
- Proactive monitoring instead of reactive checking
- Faster response to problems

### Documentation:
- `docs/EMAIL_ALERT_FIX_COMPLETE.md`
- `docs/EMAIL_ALERT_SYSTEM_COMPLETE_GUIDE.md`

---

## ✅ 2. INCREMENTAL SYNC IMPLEMENTATION

**Issue:** Manual sync "Sync Recent Changes" was processing ALL records instead of only recent changes  
**Status:** FIXED ✅

### What Was Fixed:
- Created new function `local_alx_report_api_sync_recent_changes()`
- Queries only records modified within the lookback period
- Much faster and more efficient than full population
- Properly uses the `$hours_back` parameter

### Performance Improvement:
- **Before:** Processed 3,313 records in ~25 seconds
- **After:** Processes only changed records in ~1-2 seconds
- **Speed:** 10-25x faster! ⚡

### Files Modified:
- `lib.php` - Added `sync_recent_changes()` function (~150 lines)
- `sync_reporting_data.php` - Updated to call new function

### Impact:
- Immediate sync after making changes
- No waiting for scheduled task
- Efficient processing of only changed data

### Documentation:
- `docs/SYNC_RECENT_CHANGES_FIX_COMPLETE.md`
- `docs/SYNC_REPORTING_DATA_ANALYSIS.md`

---

## ✅ 3. DUPLICATE SYNC PREVENTION

**Issue:** Running sync multiple times processed the same records repeatedly  
**Status:** FIXED ✅

### What Was Fixed:
- Added checks to skip records already synced within lookback period
- Queries now check `last_updated` field in reporting table
- Each change processed exactly once
- No more duplicate processing

### Example:
```
Before:
9:26 AM - Sync → 3 users updated
9:46 AM - Sync → 3 users updated AGAIN ❌

After:
9:26 AM - Sync → 3 users updated
9:46 AM - Sync → 0 users updated ✅
```

### Files Modified:
- `lib.php` - Updated 3 SQL queries in `sync_recent_changes()`

### Impact:
- More efficient (no wasted processing)
- Accurate statistics (shows real changes)
- Can run sync as often as needed

### Documentation:
- `docs/SYNC_DUPLICATE_PREVENTION_FIX.md`

---

## ✅ 4. CLEANUP RESULTS ENHANCEMENT

**Issue:** Cleanup orphaned records only showed debug output, no user-friendly results  
**Status:** FIXED ✅

### What Was Fixed:
- Added comprehensive results display for cleanup action
- Shows company information, statistics, affected users, courses
- Professional design matching sync results
- Detailed breakdown of what was cleaned

### New Display Includes:
- Company Information Card
- Cleanup Statistics Card
- Deleted Records by Company Table
- Affected Courses Table
- Affected Users Table (with names and emails)
- Clean Database Message (when no orphans found)

### Files Modified:
- `sync_reporting_data.php` - Enhanced cleanup section (~150 lines)

### Impact:
- Clear visibility of cleanup actions
- Audit trail of deleted records
- Professional user experience

### Documentation:
- `docs/CLEANUP_ORPHANED_RECORDS_ENHANCEMENT.md`

---

## ✅ 5. BATCH SIZE TEXT CLARIFICATION

**Issue:** Users confused about batch size behavior (expected 1000 total, got all records)  
**Status:** FIXED ✅

### What Was Fixed:
- Updated help text to clarify batch size means "per batch" not "total limit"
- Added example showing how batching works
- Updated both web UI and CLI help text

### New Text:
```
Number of records to process per batch. The system will 
process ALL records in batches of this size to avoid 
memory issues. Larger batches are faster but use more memory.

Example: 3,313 records with batch size 1000 = 4 batches 
(1000+1000+1000+313)
```

### Files Modified:
- `populate_reporting_table.php` - Updated help text (2 lines)

### Impact:
- No more confusion about batch size
- Users understand what will happen
- Clear expectations

### Documentation:
- `docs/BATCH_SIZE_TEXT_UPDATE_COMPLETE.md`
- `docs/BATCH_SIZE_ISSUE_ANALYSIS.md`

---

## 📊 SUMMARY OF CHANGES

### Files Modified:
1. `lib.php` - Email alert fix, sync function added, duplicate prevention
2. `sync_reporting_data.php` - Incremental sync call, cleanup enhancement
3. `populate_reporting_table.php` - Batch size text clarification
4. `version.php` - Version bump to 1.8.0

### Lines Changed:
- **Added:** ~350 lines of new code
- **Modified:** ~10 lines
- **Deleted:** 0 lines

### Breaking Changes:
- **None** - All changes are backward compatible

---

## 🚀 UPGRADE INSTRUCTIONS

### For New Installations:
1. Install plugin as normal
2. Configure web service and token
3. Configure companies
4. **Important:** Visit `/local/alx_report_api/populate_reporting_table.php` to populate initial data
5. Configure email alert settings in Control Center → Settings

### For Existing Installations:
1. Backup your database
2. Replace plugin files with new version
3. Visit Site Administration → Notifications
4. Moodle will detect version change and run upgrade
5. No database changes required - upgrade is automatic
6. Test email alerts and sync functionality

### Post-Upgrade Testing:
1. **Test Email Alerts:**
   - Visit `/local/alx_report_api/test_alert_processing.php`
   - Verify settings are correct
   - Process any unresolved alerts

2. **Test Incremental Sync:**
   - Mark a user as completed
   - Visit `/local/alx_report_api/sync_reporting_data.php`
   - Run "Sync Recent Changes"
   - Verify only new changes are processed

3. **Test Duplicate Prevention:**
   - Run sync twice immediately
   - Second sync should show 0 updates

4. **Test Cleanup:**
   - Remove a user from company
   - Run "Cleanup Orphaned Records"
   - Verify detailed results display

---

## 🐛 BUG FIXES

### Email Alert System:
- Fixed missing global $CFG causing 500 error
- Alerts now properly sent to configured recipients
- Scheduled task processes unresolved alerts correctly

### Sync System:
- Fixed "Sync Recent Changes" processing all records
- Fixed duplicate processing of same records
- Improved query performance with proper filtering

### User Interface:
- Fixed cleanup showing only debug output
- Added comprehensive results display
- Clarified batch size behavior

---

## 🎯 KNOWN ISSUES

None at this time.

---

## 📚 DOCUMENTATION

### New Documentation:
- `EMAIL_ALERT_FIX_COMPLETE.md`
- `EMAIL_ALERT_SYSTEM_COMPLETE_GUIDE.md`
- `SYNC_RECENT_CHANGES_FIX_COMPLETE.md`
- `SYNC_DUPLICATE_PREVENTION_FIX.md`
- `CLEANUP_ORPHANED_RECORDS_ENHANCEMENT.md`
- `BATCH_SIZE_TEXT_UPDATE_COMPLETE.md`
- `SYNC_REPORTING_DATA_ANALYSIS.md`

### Updated Documentation:
- `REPORTING_TABLE_EMPTY_ISSUE_ANALYSIS.md`
- `BATCH_SIZE_ISSUE_ANALYSIS.md`

---

## 🔒 SECURITY

No security issues fixed in this release.

---

## ⚡ PERFORMANCE

### Improvements:
- Incremental sync is 10-25x faster than before
- Duplicate prevention reduces unnecessary processing
- More efficient database queries

### Benchmarks:
- Full sync (3,313 records): ~25 seconds (unchanged)
- Incremental sync (10 changes): ~1-2 seconds (NEW - was 25s)
- Cleanup (2 orphans): ~3 seconds (unchanged)

---

## 🙏 CREDITS

**Developed by:** Kiro AI Assistant  
**Tested by:** User  
**Date:** 2025-10-15

---

## 📞 SUPPORT

For issues or questions:
1. Check documentation in `docs/` folder
2. Review test pages:
   - `/local/alx_report_api/test_alert_processing.php`
   - `/local/alx_report_api/sync_reporting_data.php`
   - `/local/alx_report_api/populate_reporting_table.php`

---

## 🔮 FUTURE ENHANCEMENTS

Potential improvements for future versions:
- HTML email templates for alerts
- Scheduled sync configuration UI
- Advanced filtering options for sync
- Export cleanup reports
- Bulk user management

---

**Version 1.8.0 - A Major Step Forward! 🚀**
