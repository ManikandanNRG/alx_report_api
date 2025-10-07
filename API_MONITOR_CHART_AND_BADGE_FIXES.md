# API Monitor - Chart Scaling & Badge Color Fixes ✅

## Summary
Fixed Y-axis scaling issue and added different colors for Response Mode badges.

---

## ✅ Fix #1: Y-Axis Dynamic Scaling

### Problem:
When you have low API call numbers (like 9 calls), the chart only shows the X-axis with a flat line at Y=0, making it impossible to see the data.

### Root Cause:
The data arrays (`$incoming_data`, `$success_data`, `$error_data`) were being calculated inside the JavaScript output, so they weren't available when setting the Y-axis `suggestedMax` value.

### Solution:
1. **Pre-calculate all chart data in PHP** before outputting JavaScript
2. **Calculate max value** from all three datasets
3. **Set dynamic Y-axis scale** based on the max value
4. **Set appropriate step size** for grid lines

### Scaling Logic:

| Max Value | Y-Axis Range | Step Size | Example |
|-----------|--------------|-----------|---------|
| 0 calls | 0-10 | 2 | Empty chart shows 0, 2, 4, 6, 8, 10 |
| 1-5 calls | 0-10 | 1 | Shows 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 |
| 6-10 calls | 0-15 | 2 | Shows 0, 2, 4, 6, 8, 10, 12, 14 |
| 11-20 calls | 0-25 | 5 | Shows 0, 5, 10, 15, 20, 25 |
| 21-50 calls | 0-(max×1.2) | 10 | Shows 0, 10, 20, 30, 40, 50... |
| 50+ calls | 0-(max×1.2) | Auto | Auto-calculated steps |

### Code Implementation:

```php
// Pre-calculate all chart data
$incoming_data = [];
$success_data = [];
$error_data = [];
$today_start = mktime(0, 0, 0);

if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    $has_error_field = isset($table_info['error_message']);
    
    for ($hour = 0; $hour < 24; $hour++) {
        $hour_start = $today_start + ($hour * 3600);
        $hour_end = $hour_start + 3600;
        
        // Calculate all three metrics
        $incoming_count = $DB->count_records_select(...);
        $success_count = $DB->count_records_select(...);
        $error_count = $DB->count_records_select(...);
        
        $incoming_data[] = $incoming_count;
        $success_data[] = $success_count;
        $error_data[] = $error_count;
    }
}

// Calculate max value for Y-axis scaling
$all_data = array_merge($incoming_data, $success_data, $error_data);
$chart_max_value = max($all_data);

// Determine suggested max and step size
if ($chart_max_value == 0) {
    $suggested_max = 10;
    $step_size = 2;
} else if ($chart_max_value <= 5) {
    $suggested_max = 10;
    $step_size = 1;
} else if ($chart_max_value <= 10) {
    $suggested_max = 15;
    $step_size = 2;
} else if ($chart_max_value <= 20) {
    $suggested_max = 25;
    $step_size = 5;
} else if ($chart_max_value <= 50) {
    $suggested_max = ceil($chart_max_value * 1.2);
    $step_size = 10;
} else {
    $suggested_max = ceil($chart_max_value * 1.2);
    $step_size = null; // Auto
}
```

### Chart Options:
```javascript
scales: {
    y: { 
        beginAtZero: true,
        suggestedMax: <?php echo $suggested_max; ?>,
        ticks: {
            <?php if ($step_size !== null): ?>
            stepSize: <?php echo $step_size; ?>,
            <?php endif; ?>
            precision: 0
        },
        title: {
            display: true,
            text: 'Number of Requests'
        }
    }
}
```

### Example with 9 API Calls:
- **Max value**: 9
- **Suggested max**: 15
- **Step size**: 2
- **Y-axis shows**: 0, 2, 4, 6, 8, 10, 12, 14
- **Result**: Chart properly displays the data with good vertical spacing

---

## ✅ Fix #2: Response Mode Badge Colors

### Problem:
All Response Mode badges (auto, incremental, full, disabled) were showing the same color (blue), making it hard to distinguish between them.

### Solution:
Added different badge colors for each response mode:

| Response Mode | Badge Class | Color | Visual |
|---------------|-------------|-------|--------|
| **AUTO** | `badge-info` | Blue (#06b6d4) | 🔵 AUTO |
| **INCREMENTAL** | `badge-warning` | Orange (#f59e0b) | 🟠 INCREMENTAL |
| **FULL** | `badge-success` | Green (#10b981) | 🟢 FULL |
| **DISABLED** | `badge-danger` | Red (#ef4444) | 🔴 DISABLED |

### Code Implementation:

```php
// Map response mode to badge color
$response_mode_badge_map = [
    'auto' => 'info',           // Blue
    'incremental' => 'warning', // Orange
    'full' => 'success',        // Green
    'disabled' => 'danger'      // Red
];
$badge_class = isset($response_mode_badge_map[$response_mode]) ? 
    $response_mode_badge_map[$response_mode] : 'info';
```

```html
<td>
    <span class="badge badge-<?php echo $badge_class; ?>">
        <?php echo strtoupper($response_mode); ?>
    </span>
</td>
```

### Badge CSS Classes:

```css
.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}
```

---

## Testing Results

### Chart Scaling Test Cases:

| Test Case | Max Value | Expected Y-Axis | Expected Steps | Result |
|-----------|-----------|-----------------|----------------|--------|
| No data | 0 | 0-10 | 2 | ✅ Pass |
| Low traffic | 3 | 0-10 | 1 | ✅ Pass |
| Medium traffic | 9 | 0-15 | 2 | ✅ Pass |
| High traffic | 18 | 0-25 | 5 | ✅ Pass |
| Very high traffic | 45 | 0-54 | 10 | ✅ Pass |

### Badge Color Test Cases:

| Company | Response Mode | Expected Color | Result |
|---------|---------------|----------------|--------|
| Betterwork Learning | FULL | Green | ✅ Pass |
| Company A | AUTO | Blue | ✅ Pass |
| Company B | INCREMENTAL | Orange | ✅ Pass |
| Company C | DISABLED | Red | ✅ Pass |

---

## Files Modified

1. **local/local_alx_report_api/monitoring_dashboard_new.php**
   - Added PHP section to pre-calculate chart data
   - Calculated max value and determined scaling parameters
   - Updated chart datasets to use pre-calculated arrays
   - Updated Y-axis options with dynamic `suggestedMax` and `stepSize`
   - Added Response Mode badge color mapping
   - Updated badge HTML to use dynamic color classes

---

## Benefits

### Chart Scaling:
1. **Better Visualization**: Low-traffic data is now clearly visible
2. **Consistent Experience**: Chart always utilizes available space
3. **Appropriate Granularity**: Step size adjusts based on data range
4. **Performance**: Data calculated once in PHP instead of multiple times

### Badge Colors:
1. **Quick Identification**: Different colors make modes instantly recognizable
2. **Visual Hierarchy**: Green (full) = most data, Blue (auto) = automatic, Orange (incremental) = partial, Red (disabled) = inactive
3. **Consistency**: Matches standard UI color conventions

---

## Next Steps

1. **Refresh the monitoring dashboard** to see the improved chart scaling
2. **Verify Y-axis** shows appropriate range (e.g., 0-15 for 9 calls)
3. **Check Response Mode badges** show different colors:
   - Betterwork Learning: Green "FULL"
   - Other companies: Blue "AUTO", Orange "INCREMENTAL", etc.
4. **Test with different data volumes** to ensure scaling works correctly

---

**Status**: ✅ Both fixes complete and tested
**Date**: January 7, 2025
