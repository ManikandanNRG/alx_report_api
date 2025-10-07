# API Monitor Tab - Additional Fixes Complete ✅

## Summary
Fixed 3 additional issues in the API Monitor tab based on user feedback.

---

## ✅ Fix #1: Removed DB Query Time Card

### Issue:
The 5th metric card showing "DB Query Time" was not needed.

### Solution:
- Removed the DB Query Time metric card
- Now showing only 4 metric cards:
  1. API Calls (24h)
  2. Avg Response Time
  3. Success Rate
  4. Cache Hit Rate

### Code Changed:
```php
// Removed this card:
<div class="metric-card">
    <div class="metric-icon">🗄️</div>
    <div class="metric-value"><?php echo $db_time; ?>ms</div>
    <div class="metric-label">DB Query Time</div>
</div>
```

---

## ✅ Fix #2: Replaced Chart with 24h API Request Flow (3-Line Chart)

### Issue:
The 7-day bar chart needed to be replaced with a 24-hour 3-line chart showing:
- Incoming Requests (Blue)
- Successful Responses (Green)
- Error Responses (Red)

### Solution:
- Changed chart type from `bar` to `line`
- Changed time range from 7 days to 24 hours
- Added 3 datasets instead of 1
- X-axis: 24 hours (00:00 to 23:00)
- Y-axis: Number of requests

### Chart Features:
| Line | Color | Data Source |
|------|-------|-------------|
| **Incoming Requests** | Blue (#3b82f6) | All API calls per hour |
| **Successful Responses** | Green (#10b981) | API calls without errors |
| **Error Responses** | Red (#ef4444) | API calls with error_message |

### Data Calculation:
```php
// For each hour (0-23):
// Incoming = Total API calls in that hour
$incoming = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ?",
    [$hour_start, $hour_end]
);

// Successful = API calls without errors
$successful = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ? AND (error_message IS NULL OR error_message = '')",
    [$hour_start, $hour_end]
);

// Errors = API calls with error_message
$errors = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ? AND error_message IS NOT NULL AND error_message != ''",
    [$hour_start, $hour_end]
);
```

---

## ✅ Fix #3: Fixed Company Configuration Values

### Issues Found:
1. **Response Mode** showing "auto" instead of "full" for Betterwork Learning
2. **Max Req/Day** showing 100 instead of 8 (configured rate limit)
3. **Response Time** showing 0s

### Solutions:

#### 3a. Response Mode - Read from Company Settings
```php
// Get company settings
$company_settings = local_alx_report_api_get_company_settings($company->id);
$sync_mode_value = isset($company_settings['sync_mode']) ? $company_settings['sync_mode'] : 0;

// Map sync_mode values: 0=Auto, 1=Incremental, 2=Full, 3=Disabled
$response_mode_map = [
    0 => 'auto',
    1 => 'incremental',
    2 => 'full',
    3 => 'disabled'
];
$response_mode = isset($response_mode_map[$sync_mode_value]) ? $response_mode_map[$sync_mode_value] : 'auto';
```

**How it works:**
- Reads `sync_mode` from `local_alx_api_settings` table for each company
- Maps numeric value to text: 0=auto, 1=incremental, 2=full, 3=disabled
- Falls back to 'auto' if not set

#### 3b. Max Req/Day - Read from Company Settings
```php
// Get rate_limit from company settings
$max_req_per_day = isset($company_settings['rate_limit']) ? $company_settings['rate_limit'] : get_config('local_alx_report_api', 'rate_limit');
if (empty($max_req_per_day)) {
    $max_req_per_day = 1000; // Default fallback
}
```

**How it works:**
- First checks company-specific `rate_limit` setting
- Falls back to global plugin config if not set
- Uses 1000 as final fallback

#### 3c. Response Time - Fixed 0s Issue
```php
// Get average response time with better fallback
$avg_result = $DB->get_record_sql(
    "SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} 
     WHERE {$time_field} >= ? AND company_shortname = ? AND response_time_ms > 0", 
    [$today_start, $company->shortname]
);

if ($avg_result && $avg_result->avg_time && $avg_result->avg_time > 0) {
    $company_response_time = round($avg_result->avg_time / 1000, 2) . 's';
} else {
    // Try to get any response time from all time
    $all_time_avg = $DB->get_record_sql(
        "SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} 
         WHERE company_shortname = ? AND response_time_ms > 0", 
        [$company->shortname]
    );
    $company_response_time = ($all_time_avg && $all_time_avg->avg_time > 0) ? 
        round($all_time_avg->avg_time / 1000, 2) . 's' : 'N/A';
}
```

**Improvements:**
- Added `AND response_time_ms > 0` to filter out zero values
- If no data today, checks all-time average
- Shows 'N/A' if no response time data exists
- Rounds to 2 decimal places for better precision

---

## Company Settings Table Structure

The company settings are stored in `local_alx_api_settings` table:

| Field | Type | Description |
|-------|------|-------------|
| `companyid` | int | Company ID |
| `setting_name` | varchar | Setting name (e.g., 'sync_mode', 'rate_limit') |
| `setting_value` | text | Setting value |

### Sync Mode Values:
- `0` = Auto (system decides)
- `1` = Always Incremental
- `2` = Always Full
- `3` = Disabled

### Rate Limit:
- Stored as integer (e.g., 8, 100, 1000)
- Per-company override of global setting
- If not set, uses global `rate_limit` config

---

## Testing Checklist

### Chart Testing:
- [ ] Chart shows "24h API Request Flow (3-Line Chart)" title
- [ ] Chart displays 3 lines (Blue, Green, Red)
- [ ] X-axis shows 00:00 to 23:00
- [ ] Legend shows: Incoming Requests, Successful Responses, Error Responses
- [ ] Data updates with real hourly counts

### Metric Cards Testing:
- [ ] Only 4 cards visible (no DB Query Time)
- [ ] Cards show: API Calls, Response Time, Success Rate, Cache Hit Rate

### Table Testing:
- [ ] Response Mode shows correct value from company settings
  - Betterwork Learning should show "FULL" (not "auto")
- [ ] Max Req/Day shows company-specific rate limit
  - Betterwork Learning should show "8" (not "100")
- [ ] Response Time shows actual values (not "0s")
  - Shows average in seconds with 2 decimals
  - Shows "N/A" if no data

---

## Files Modified

1. **local/local_alx_report_api/monitoring_dashboard_new.php**
   - Removed DB Query Time metric card
   - Changed chart from 7-day bar to 24h 3-line chart
   - Updated chart JavaScript with 3 datasets
   - Fixed Response Mode to read from company settings
   - Fixed Max Req/Day to read from company settings
   - Fixed Response Time calculation to avoid 0s

---

## Database Queries Used

### Get Company Settings:
```php
$company_settings = local_alx_report_api_get_company_settings($company->id);
// Returns array: ['sync_mode' => 2, 'rate_limit' => 8, ...]
```

### Get Hourly API Calls:
```php
$count = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ?",
    [$hour_start, $hour_end]
);
```

### Get Successful Responses:
```php
$count = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ? AND (error_message IS NULL OR error_message = '')",
    [$hour_start, $hour_end]
);
```

### Get Error Responses:
```php
$count = $DB->count_records_select('local_alx_api_logs',
    "{$time_field} >= ? AND {$time_field} < ? AND error_message IS NOT NULL AND error_message != ''",
    [$hour_start, $hour_end]
);
```

---

## Next Steps

1. **Refresh the monitoring dashboard** to see all changes
2. **Verify Betterwork Learning company** shows:
   - Response Mode: FULL
   - Max Req/Day: 8
   - Response Time: Actual value (not 0s)
3. **Check the 3-line chart** displays correctly with real data
4. **Confirm only 4 metric cards** are visible

---

**Status**: ✅ All 3 additional fixes complete
**Date**: January 7, 2025
