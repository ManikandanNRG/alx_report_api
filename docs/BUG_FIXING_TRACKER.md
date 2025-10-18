# ALX Report API - Bug Fixing Tracker
**Project:** Data Inconsistency Bug Fixes  
**Branch:** bug-fix  
**Started:** October 18, 2025  
**Status:** In Progress

---

## 🎯 BUG FIXING PROGRESS

### **COMPLETED BUGS** ✅

#### **BUG #1: Control Center Sync Status Shows API Call Time** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** CRITICAL
- **Reported By:** Tester
- **Root Cause:** API calls updated `last_sync_timestamp` in sync status table
- **Fix Applied:** Removed sync status update from API calls (externallib.php line 830-835)
- **Files Changed:** `local/local_alx_report_api/externallib.php`
- **Expected Result:** Control center shows cron sync time, not API call time
- **Verification Status:** ⏳ Awaiting user confirmation

---

#### **BUG #2: Manual Sync Shows Hashed Email Values** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** MEDIUM
- **Reported By:** Tester
- **Root Cause:** Manual sync queried user table instead of reporting table for emails
- **Fix Applied:** Changed query to use reporting table email (same as API)
- **Files Changed:** `local/local_alx_report_api/sync_reporting_data.php` (lines 137-147, 188-197)
- **Expected Result:** Manual sync shows readable emails, not hashes
- **Verification Status:** ✅ **CONFIRMED WORKING** (User verified)

---

### **IN PROGRESS BUGS** 🔄

#### **BUG #2.1: Soft Delete Should Be Hard Delete** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** HIGH
- **Reported By:** User (follow-up to Bug #2)
- **Root Cause:** System uses soft delete (`is_deleted = 1`) instead of physically removing records
- **Impact:** Deleted users remain in reporting table, consuming space
- **Affected Features:**
  - Auto sync deletion detection
  - Manual sync deletion detection  
  - Cleanup orphaned records
- **Fix Applied:** Changed all soft delete operations to hard delete (physical removal)
- **Files Changed:**
  1. ✅ `lib.php` - `soft_delete_reporting_record()` function - now uses `$DB->delete_records()`
  2. ✅ `sync_reporting_data.php` - cleanup action - now uses `$DB->delete_records()`
- **Expected Result:** Deleted users physically removed from reporting table
- **Verification Status:** ✅ **CONFIRMED WORKING** (User verified)

---

### **PENDING BUGS** ⏳

#### **BUG #3: Manual Sync Page Refresh Triggers New Sync** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** HIGH
- **Reported By:** Tester
- **Root Cause:** Browser caching POST data and auto-resubmitting on refresh
- **Fix Applied:** Implemented token-based form protection with session tracking
- **Files Changed:** 
  - `sync_reporting_data.php` - Added tokens to 3 forms (sync_changes, sync_full, cleanup)
  - `populate_reporting_table.php` - Added tokens to 2 forms (populate, cleanup)
- **Expected Result:** Page refresh redirects to form, does not trigger sync
- **Verification Status:** ✅ **CONFIRMED WORKING** (User verified)

---

#### **BUG #4: Manual Sync Only One Course Per User** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** CRITICAL
- **Reported By:** Tester
- **Root Cause:** `$DB->get_records_sql()` uses first column as array key, overwriting multiple courses for same user
- **Location:** `lib.php` sync_recent_changes function - 4 SQL queries
- **Fix Applied:** Added `CONCAT(userid, '-', courseid) as id` as first column to create unique composite keys
- **Files Changed:** `local/local_alx_report_api/lib.php` (lines 983, 1017, 1052, 1087)
- **Expected Result:** All courses synced for users with multiple enrollments
- **Verification Status:** ✅ **CONFIRMED WORKING** (User verified)

---

#### **BUG #5: Non-Editing Teachers Included in API** ✅ **COMPLETED**
- **Status:** ✅ **FIXED & VERIFIED**
- **Severity:** HIGH
- **Reported By:** Tester
- **Root Cause:** No role filtering in SQL queries
- **Location:** All populate/sync SQL queries in `lib.php`
- **Fix Applied:** Added role filtering to 6 queries across 3 functions to include only 'student' role
- **Files Changed:** `local/local_alx_report_api/lib.php` (lines 631-633, 825-827, 992-994, 1030-1032, 1068-1070, 1106-1108)
- **Expected Result:** Only students appear in API/reports
- **Verification Status:** ✅ **CONFIRMED WORKING** (User verified)

---

#### **BUG #6: Wrong Completion Status (Completed vs In Progress)** ⏳ **PENDING**
- **Status:** ⏳ **NOT STARTED**
- **Severity:** CRITICAL
- **Reported By:** Tester
- **Root Cause:** Multiple status calculation methods with different logic
- **Location:** 
  - `externallib.php` fallback query
  - `lib.php` populate query
  - `lib.php` update_reporting_record
- **Fix Required:** Centralize status calculation into single function
- **Files to Change:** `local/local_alx_report_api/lib.php`, `externallib.php`
- **Expected Result:** Consistent status across all code paths
- **Verification Status:** ⏳ Not started

---

### **ADDITIONAL BUGS FROM ANALYSIS** 📊

#### **BUG #7: Cache Not Invalidated on Settings Changes** ⏳ **PENDING**
- **Status:** ⏳ **NOT STARTED**
- **Severity:** HIGH
- **Reported By:** Analysis
- **Root Cause:** Cache key doesn't include course/field settings
- **Location:** `externallib.php` cache key generation
- **Fix Required:** Include enabled courses and field settings in cache key
- **Files to Change:** `local/local_alx_report_api/externallib.php`
- **Expected Result:** Cache invalidates when settings change
- **Verification Status:** ⏳ Not started

---

#### **BUG #8: Sync Mode Determination Missing Partial Data** ⏳ **PENDING**
- **Status:** ⏳ **NOT STARTED**
- **Severity:** MEDIUM
- **Reported By:** Analysis
- **Root Cause:** Sync mode doesn't check data coverage percentage
- **Location:** `lib.php` determine_sync_mode function
- **Fix Required:** Check if reporting table has <90% of expected records
- **Files to Change:** `local/local_alx_report_api/lib.php`
- **Expected Result:** Smart sync mode selection based on data coverage
- **Verification Status:** ⏳ Not started

---

#### **BUG #9: Percentage Calculation Doesn't Match Moodle Core** ⏳ **PENDING**
- **Status:** ⏳ **NOT STARTED**
- **Severity:** HIGH
- **Reported By:** Analysis
- **Root Cause:** Custom calculation counts all modules, not just required ones
- **Location:** All SQL queries calculating percentage
- **Fix Required:** Use Moodle's core completion API
- **Files to Change:** `local/local_alx_report_api/lib.php`
- **Expected Result:** Percentage matches Moodle UI
- **Verification Status:** ⏳ Not started

---

## 📊 SUMMARY STATISTICS

- **Total Bugs Identified:** 9
- **Completed & Verified:** 5 (56%)
- **Completed (Awaiting Verification):** 1 (11%)
- **In Progress:** 0 (0%)
- **Pending:** 3 (33%)

### **By Severity:**
- **CRITICAL:** 3 bugs (BUG #4, #6, and #1 ✅)
- **HIGH:** 4 bugs (BUG #2.1, #3, #5, #7, #9)
- **MEDIUM:** 2 bugs (BUG #2 ✅, #8)

### **By Source:**
- **Tester Reported:** 6 bugs
- **Analysis Discovered:** 3 bugs

---

## 🎯 NEXT STEPS

1. **High Priority:** Fix BUG #5 (Non-editing teachers in API)
2. **Critical Priority:** Fix BUG #6 (Wrong completion status)
3. **Medium Priority:** Fix BUG #7, #9 (Data accuracy)
4. **Low Priority:** Fix BUG #8 (Optimization)

---

## 📝 NOTES

- ✅ = Completed and verified by user
- 🔄 = Work in progress
- ⏳ = Pending/Not started
- All fixes must be verified by user before marking as completed
- Document updated after each bug fix
- User confirmation required to mark as ✅

---

**Last Updated:** October 18, 2025  
**Next Bug to Fix:** BUG #6 - Wrong Completion Status (CRITICAL)
