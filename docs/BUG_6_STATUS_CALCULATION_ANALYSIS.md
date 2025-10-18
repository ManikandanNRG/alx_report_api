# BUG #6: Wrong Completion Status Analysis
**Date:** October 18, 2025  
**Severity:** CRITICAL  
**Status:** Analysis Complete - Awaiting Approval

---

## 🔍 PROBLEM STATEMENT

The system shows **inconsistent completion status** across different code paths. Sometimes a course shows "completed" when it should show "in_progress" or vice versa.

**Example Scenario:**
- User completes 5 out of 10 activities in a course
- Moodle UI shows: "In Progress" (50% complete)
- API returns: "completed" ❌ WRONG!

---

## 📊 CURRENT STATUS CALCULATION LOGIC

### **Location 1: populate_reporting_table() - Line 611-624**
```sql
CASE 
    WHEN cc.timecompleted > 0 THEN 'completed'
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
    ) THEN 'completed'  ← PROBLEM HERE!
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
    ) THEN 'in_progress'
    WHEN ue.id IS NOT NULL THEN 'not_started'
    ELSE 'not_enrolled'
END as status
```

### **Location 2: update_reporting_record() - Line 805-818**
```sql
CASE 
    WHEN cc.timecompleted > 0 THEN 'completed'
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
    ) THEN 'completed'  ← PROBLEM HERE!
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
    ) THEN 'in_progress'
    WHEN ue.id IS NOT NULL THEN 'not_started'
    ELSE 'not_enrolled'
END as status
```

### **Location 3: externallib.php fallback - Line 940-953**
```sql
CASE 
    WHEN cc.timecompleted > 0 THEN 'completed'
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
    ) THEN 'completed'  ← PROBLEM HERE!
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
    ) THEN 'in_progress'
    WHEN ue.id IS NOT NULL THEN 'not_started'
    ELSE 'not_enrolled'
END as status
```

---

## ❌ THE PROBLEM

### **Issue: Premature "completed" Status**

The second condition checks:
```sql
WHEN EXISTS(... AND cmc.completionstate = 1) THEN 'completed'
```

**What this means:**
- `completionstate = 1` means "activity completed"
- If user completes **ANY ONE activity**, this returns TRUE
- Status becomes "completed" even if course is not fully complete!

**Example:**
```
Course has 10 activities
User completes 1 activity → completionstate = 1 for that activity
Query returns: 'completed' ❌ WRONG!
Should return: 'in_progress' ✅ CORRECT
```

---

## 🎯 ROOT CAUSE ANALYSIS

### **The Logic Flow:**

1. **Check 1:** `cc.timecompleted > 0` → Course completion record exists
   - ✅ This is correct (Moodle sets this when course is truly complete)

2. **Check 2:** `EXISTS(... completionstate = 1)` → ANY activity completed
   - ❌ **THIS IS WRONG!** 
   - Should check if **ALL REQUIRED** activities are completed
   - Currently checks if **ANY** activity is completed

3. **Check 3:** `EXISTS(... completionstate > 0)` → ANY activity started
   - ✅ This is correct for "in_progress"

### **Why This Happens:**

The code assumes:
- If ANY activity is completed (completionstate = 1) → Course is completed

But reality is:
- Course completion requires **ALL REQUIRED** activities to be completed
- Moodle tracks this in `course_completions.timecompleted`
- Individual activity completion ≠ Course completion

---

## 🔧 PROPOSED FIX

### **Option 1: Remove the Problematic Check (RECOMMENDED)**

**Remove the second WHEN clause entirely:**

```sql
CASE 
    WHEN cc.timecompleted > 0 THEN 'completed'
    -- REMOVE THIS:
    -- WHEN EXISTS(... completionstate = 1) THEN 'completed'
    WHEN EXISTS(
        SELECT 1 FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
    ) THEN 'in_progress'
    WHEN ue.id IS NOT NULL THEN 'not_started'
    ELSE 'not_enrolled'
END as status
```

**Why this works:**
- ✅ Relies on Moodle's official `course_completions.timecompleted`
- ✅ Only shows "completed" when course is truly complete
- ✅ Shows "in_progress" for partial completion
- ✅ Matches Moodle UI behavior

### **Option 2: Check ALL Required Activities (COMPLEX)**

Check if ALL required activities are completed:

```sql
CASE 
    WHEN cc.timecompleted > 0 THEN 'completed'
    WHEN (
        SELECT COUNT(*) 
        FROM {course_modules} cm
        WHERE cm.course = c.id 
        AND cm.completion > 0
    ) = (
        SELECT COUNT(*) 
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id 
        AND cmc.userid = u.id 
        AND cmc.completionstate = 1
        AND cm.completion > 0
    ) THEN 'completed'
    WHEN EXISTS(...) THEN 'in_progress'
    ...
END
```

**Why NOT recommended:**
- ❌ Complex and slow
- ❌ Doesn't account for optional activities
- ❌ Moodle already does this calculation
- ❌ Reinventing the wheel

---

## ✅ RECOMMENDED SOLUTION

**Use Option 1: Remove the problematic check**

### **Changes Required:**

**3 locations need to be fixed:**

1. **lib.php** - `populate_reporting_table()` (Line ~611-624)
2. **lib.php** - `update_reporting_record()` (Line ~805-818)
3. **externallib.php** - Fallback query (Line ~940-953)

### **The Fix:**

Remove these lines from all 3 locations:
```sql
WHEN EXISTS(
    SELECT 1 FROM {course_modules_completion} cmc
    JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
    WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
) THEN 'completed'
```

### **Result:**

**Before Fix:**
```
User completes 1/10 activities
Status: 'completed' ❌
```

**After Fix:**
```
User completes 1/10 activities
Status: 'in_progress' ✅

User completes 10/10 activities (Moodle sets cc.timecompleted)
Status: 'completed' ✅
```

---

## 🧪 TESTING PLAN

### **Test Case 1: Partial Completion**
1. Enroll student in course with 10 activities
2. Student completes 3 activities
3. Run sync
4. **Expected:** Status = 'in_progress', Percentage = 30%
5. **Before fix:** Status = 'completed' ❌
6. **After fix:** Status = 'in_progress' ✅

### **Test Case 2: Full Completion**
1. Student completes all 10 activities
2. Moodle sets `course_completions.timecompleted`
3. Run sync
4. **Expected:** Status = 'completed', Percentage = 100%
5. **Before fix:** Status = 'completed' ✅
6. **After fix:** Status = 'completed' ✅

### **Test Case 3: No Activities Completed**
1. Student enrolled but hasn't started
2. Run sync
3. **Expected:** Status = 'not_started', Percentage = 0%
4. **Before fix:** Status = 'not_started' ✅
5. **After fix:** Status = 'not_started' ✅

---

## ⚠️ IMPACT ANALYSIS

### **What Will Change:**

| Scenario | Before Fix | After Fix | Impact |
|----------|-----------|-----------|--------|
| 0/10 activities done | not_started | not_started | ✅ No change |
| 1/10 activities done | **completed** ❌ | in_progress | ✅ Fixed |
| 5/10 activities done | **completed** ❌ | in_progress | ✅ Fixed |
| 10/10 done (no cc record) | **completed** ❌ | in_progress | ⚠️ See note |
| 10/10 done (cc.timecompleted set) | completed | completed | ✅ No change |

**Note:** If Moodle hasn't set `course_completions.timecompleted` yet, status will be "in_progress" until Moodle's cron runs. This is correct behavior - we should trust Moodle's completion tracking.

### **Functions Affected:**

- ✅ `populate_reporting_table()` - Will populate with correct status
- ✅ `update_reporting_record()` - Will update with correct status
- ✅ `sync_recent_changes()` - Will sync correct status
- ✅ API fallback query - Will return correct status

### **Risk Level:** LOW
- Simple change (remove lines)
- Makes code trust Moodle's official completion tracking
- Aligns with Moodle UI behavior

---

## 📋 IMPLEMENTATION CHECKLIST

- [ ] Remove problematic WHEN clause from `populate_reporting_table()`
- [ ] Remove problematic WHEN clause from `update_reporting_record()`
- [ ] Remove problematic WHEN clause from `externallib.php` fallback
- [ ] Test with partial completion
- [ ] Test with full completion
- [ ] Verify status matches Moodle UI
- [ ] User confirmation

---

## 🎯 RECOMMENDATION

**Proceed with Option 1: Remove the problematic check**

**Reasoning:**
1. ✅ Simple and clean
2. ✅ Trusts Moodle's official completion tracking
3. ✅ Fixes the bug completely
4. ✅ Low risk
5. ✅ Matches Moodle UI behavior

**Estimated Time:** 15 minutes  
**Risk Level:** LOW  
**Complexity:** SIMPLE

---

**Ready to implement?** Awaiting your approval to proceed.
