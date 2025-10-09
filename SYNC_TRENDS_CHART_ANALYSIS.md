# Sync Trends Chart Analysis

## Issue Report
**Problem:** "Sync Trends (Last 24 Hours)" chart shows flat line (no spikes) even after running population twice today.

## Investigation Results

### Chart Data Source
The chart is **NOT hardcoded** - it pulls **REAL data** from the database.

**Location:** `monitoring_dashboard_new.php` (Lines ~1210-1240)

**SQL Query:**
```php
for ($hour = 0; $hour < 24; $hour++) {
    $hour_start = $today_start + ($hour * 3600);
    $hour_end = $hour_start + 3600;
    
    $count = $DB->count_records_select('local_alx_api_reporting',
        'timecreated >= ? AND timecreated < ?',
        [$hour_start, $hour_end]
    );
    $sync_data[] = $count;
}
```

### What the Chart Shows
- **X-Axis:** 24 hours (00:00 to 23:00)
- **Y-Axis:** Number of records created per hour
- **Data Source:** `local_alx_api_reporting.timecreated` field
- **Time Range:** Today only (from midnight 00:00 to now)

## Why You Don't See Spikes

### Root Cause
The chart counts records by their **`timecreated`** timestamp, which is set when records are **first inserted** into the database.

### Scenario 1: Re-population (Most Likely)
If you're running "Populate Report Table" on data that **already exists**:

```
First Population (Yesterday):
- Records inserted with timecreated = yesterday's timestamp
- Chart shows spike yesterday

Second Population (Today):
- Records ALREADY EXIST in database
- No new records created (just updated)
- Chart shows NO spike today
```

**The chart only counts NEW records, not updates!**

### Scenario 2: Historical Data
If you're populating historical data:

```
Population Run (Today at 12:00):
- Fetches user course data from ALX API
- Data shows user completed course on Jan 15, 2024
- Record inserted with timecreated = Jan 15, 2024 (historical date)
- Chart shows NO spike today (spike would be on Jan 15)
```

## How to Verify

### Check 1: Look at Records Created Today
Run this SQL query:
```sql
SELECT COUNT(*) as total,
       DATE_FORMAT(FROM_UNIXTIME(timecreated), '%Y-%m-%d %H:00') as hour
FROM mdl_local_alx_api_reporting
WHERE timecreated >= UNIX_TIMESTAMP(CURDATE())
GROUP BY hour
ORDER BY hour;
```

### Check 2: Check if Records Were Updated (Not Created)
Run this SQL query:
```sql
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN timecreated >= UNIX_TIMESTAMP(CURDATE()) THEN 1 ELSE 0 END) as created_today,
    SUM(CASE WHEN timemodified >= UNIX_TIMESTAMP(CURDATE()) AND timecreated < UNIX_TIMESTAMP(CURDATE()) THEN 1 ELSE 0 END) as updated_today
FROM mdl_local_alx_api_reporting;
```

### Check 3: View Population Results Page
After running "Populate Report Table", check the results page:
- **Records Created:** Shows NEW records added today
- **Records Updated:** Shows EXISTING records modified today

**If "Records Created" = 0**, that's why the chart is flat!

## Solutions

### Option 1: Show Updates Too (Recommended)
Modify the chart to show BOTH created AND updated records:

```php
// Current code (only shows created)
$count = $DB->count_records_select('local_alx_api_reporting',
    'timecreated >= ? AND timecreated < ?',
    [$hour_start, $hour_end]
);

// Better code (shows created + updated)
$created = $DB->count_records_select('local_alx_api_reporting',
    'timecreated >= ? AND timecreated < ?',
    [$hour_start, $hour_end]
);

$updated = $DB->count_records_select('local_alx_api_reporting',
    'timemodified >= ? AND timemodified < ? AND timecreated < ?',
    [$hour_start, $hour_end, $hour_start]
);

$count = $created + $updated; // Total sync activity
```

### Option 2: Add Separate Lines for Created vs Updated
Show two lines on the chart:
- Blue line: Records Created
- Green line: Records Updated

### Option 3: Track Population Events
Create a new table to track when population runs:
```sql
CREATE TABLE mdl_local_alx_api_population_log (
    id BIGINT PRIMARY KEY,
    run_time INT,
    records_created INT,
    records_updated INT,
    companies_processed INT
);
```

Then chart shows actual population runs, not individual records.

## Current Chart Behavior

### What It Shows
✅ **Records created for the first time today**
- New users enrolled
- New course completions
- First-time data sync

### What It Doesn't Show
❌ **Records updated today** (existing records modified)
❌ **Re-population runs** (if data already exists)
❌ **Historical data imports** (old timestamps)

## Recommended Fix

### Change Chart to Show "Sync Activity"
Instead of just "Records Created", show total sync activity:

**Chart Title:** "Sync Activity (Last 24 Hours)"
**Data:** Created + Updated records per hour

This will show spikes whenever you run population, regardless of whether records are new or updated.

## Quick Test

To see if the chart works, try this:

1. **Delete some records:**
   ```sql
   DELETE FROM mdl_local_alx_api_reporting WHERE companyid = 1 LIMIT 100;
   ```

2. **Run population again**

3. **Check chart** - You should now see a spike!

If you see a spike after deleting records, it confirms the chart works but only shows NEW records, not updates.

## Summary

| Aspect | Status |
|--------|--------|
| **Is chart hardcoded?** | ❌ No - pulls real data |
| **Does it work?** | ✅ Yes - works correctly |
| **Why no spikes?** | Records already exist (updates, not creates) |
| **Solution** | Modify chart to include updated records |

---

**Conclusion:** The chart is working correctly, but it only shows NEW records. Since you're re-populating existing data, the records are being UPDATED (not created), so the chart stays flat.

**Recommendation:** Modify the chart to show both created AND updated records to reflect actual sync activity.

Would you like me to implement this fix?
