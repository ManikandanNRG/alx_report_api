# Violation Count Fix - Control Center

**Date:** 2025-10-14  
**Status:** ✅ FIXED

---

## 🐛 The Real Problem

**User Report:**
- There were 2 violation events today (Oct 14 at 16:11 and 13:09)
- Control Center showed: 1 violation
- Monitoring Dashboard showed: 2 violations

**Root Cause:** Control Center was counting **companies that violated** (1 company = Brillio), not **violation events** (2 events).

---

## 🔍 What Was Wrong

### **Control Center (BEFORE):**
```php
// Counted COMPANIES that exceeded limit
foreach ($companies as $company) {
    if ($company_calls_today > $company_rate_limit) {
        $violations_today++;  // ← Counts 1 per company
    }
}
```

**Result:**
- Brillio user violated twice (16:11 and 13:09)
- But it's the SAME company
- Count: 1 (one company violated)

### **Monitoring Dashboard:**
```php
// Counted violation EVENTS from alerts table
$rate_limit_violations = $DB->count_records_select(TABLE_ALERTS,
    "alert_type = 'rate_limit_exceeded' AND timecreated >= ?",
    [$today_start]
);
```

**Result:**
- 2 alerts logged (one for each violation event)
- Count: 2 (two violation events)

---

## ✅ The Fix

### **Control Center (AFTER):**
```php
// Count actual violation EVENTS from alerts table (same as Monitoring Dashboard)
if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_ALERTS)) {
    $violations_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_ALERTS,
        "alert_type = ? AND timecreated >= ?",
        ['rate_limit_exceeded', $today_start]
    );
}
```

**Result:**
- Counts from alerts table (each violation is logged)
- Count: 2 (two violation events) ✅ CORRECT

---

## 📊 What Changed

| Metric | Before | After | Status |
|--------|--------|-------|--------|
| **What it counts** | Companies that violated | Violation events | ✅ Fixed |
| **Data source** | Calculated from logs | Alerts table | ✅ Changed |
| **Example** | 1 company = 1 count | 2 events = 2 count | ✅ Correct |

---

## 🧪 Testing

### **Scenario:**
- User "api" from Brillio exceeds limit at 16:11
- Same user exceeds limit again at 13:09
- Total: 2 violation events

### **Expected Results:**
- Control Center: Shows 2 violations ✅
- Monitoring Dashboard: Shows 2 violations ✅
- Both match the Security Events table ✅

---

## 📝 Files Modified

**File:** `control_center.php` (Line ~494-530)

**Changed:**
- From: Counting companies that exceeded limits
- To: Counting violation events from alerts table

**Also improved:**
- Active users count now uses DISTINCT userid (more accurate)
- Better error handling
- Clearer debug logging

---

## ✅ Result

Now both dashboards count violation **EVENTS** (not companies), so they show the same accurate number!

- ✅ Control Center: Counts from alerts table
- ✅ Monitoring Dashboard: Counts from alerts table
- ✅ Both show: 2 violations (matching reality)

---

**Implementation Date:** 2025-10-14  
**Status:** ✅ COMPLETE - Violation counts now accurate
