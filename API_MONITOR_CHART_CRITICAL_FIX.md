# API Monitor Chart - Critical Fix ✅

## Summary
Fixed the critical issue where Y-axis values were not showing on the chart.

---

## 🔴 Critical Problem Found

### Issue:
The Y-axis on the 24h API Request Flow chart was not displaying any values (0, 2, 4, 6, 8, 10, etc.).

### Root Cause:
**PHP code was placed INSIDE the JavaScript `<script>` tag**, causing a syntax error that broke the chart rendering.

```javascript
// WRONG - PHP inside JavaScript
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... other code ...
    
    <?php
    // This PHP code breaks the JavaScript!
    $incoming_data = [];
    // ... calculations ...
    ?>
    
    // Chart code here
});
</script>
```

When the browser tried to execute this, it saw:
1. JavaScript code
2. Suddenly PHP tags (`<?php`) which the browser doesn't understand
3. JavaScript breaks and chart doesn't render properly

---

## ✅ Solution

### Moved PHP Calculation Before `<script>` Tag

**Correct Structure:**
```php
</div>
</div>

<?php
// Pre-calculate all chart data for 24h API Request Flow
$incoming_data = [];
$success_data = [];
$error_data = [];
$perf_today_start = mktime(0, 0, 0);

if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    $has_error_field = isset($table_info['error_message']);
    
    for ($hour = 0; $hour < 24; $hour++) {
        $hour_start = $perf_today_start + ($hour * 3600);
        $hour_end = $hour_start + 3600;
        
        // Calculate incoming, success, and error counts
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
    $step_size = null;
}
?>

<script>
// Now JavaScript can use the PHP variables
document.addEventListener('DOMContentLoaded', function() {
    const perfCtx = document.getElementById('performanceChart');
    if (perfCtx) {
        new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($hours); ?>,
                datasets: [
                    {
                        label: 'Incoming Requests',
                        data: <?php echo json_encode($incoming_data); ?>,
                        borderColor: '#3b82f6',
                        // ... other properties
                    },
                    {
                        label: 'Successful Responses',
                        data: <?php echo json_encode($success_data); ?>,
                        borderColor: '#10b981',
                        // ... other properties
                    },
                    {
                        label: 'Error Responses',
                        data: <?php echo json_encode($error_data); ?>,
                        borderColor: '#ef4444',
                        // ... other properties
                    }
                ]
            },
            options: {
                scales: {
                    y: { 
                        beginAtZero: true,
                        max: <?php echo $suggested_max; ?>,
                        ticks: {
                            <?php if ($step_size !== null): ?>
                            stepSize: <?php echo $step_size; ?>,
                            <?php endif; ?>
                            precision: 0,
                            callback: function(value) {
                                return Number.isInteger(value) ? value : null;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
```

---

## Key Changes

### 1. **PHP Calculation Placement**
- **Before**: PHP code was inside `<script>` tag (line ~1010)
- **After**: PHP code is before `<script>` tag (line ~920)

### 2. **Variable Naming**
- Changed `$today_start` to `$perf_today_start` to avoid conflicts with the sync chart

### 3. **Clean Separation**
- PHP calculates all data first
- JavaScript uses the pre-calculated PHP variables
- No mixing of PHP and JavaScript in the same block

---

## How It Works Now

### Step 1: PHP Calculates Data (Before `<script>`)
```php
<?php
// Calculate all 24 hours of data
for ($hour = 0; $hour < 24; $hour++) {
    $incoming_data[] = /* count from database */;
    $success_data[] = /* count from database */;
    $error_data[] = /* count from database */;
}

// Calculate Y-axis scale
$chart_max_value = max(array_merge($incoming_data, $success_data, $error_data));
$suggested_max = /* calculated based on max value */;
$step_size = /* calculated based on max value */;
?>
```

### Step 2: JavaScript Uses PHP Data (Inside `<script>`)
```javascript
<script>
new Chart(perfCtx, {
    data: {
        datasets: [
            { data: <?php echo json_encode($incoming_data); ?> },
            { data: <?php echo json_encode($success_data); ?> },
            { data: <?php echo json_encode($error_data); ?> }
        ]
    },
    options: {
        scales: {
            y: {
                max: <?php echo $suggested_max; ?>,
                ticks: {
                    stepSize: <?php echo $step_size; ?>
                }
            }
        }
    }
});
</script>
```

---

## Expected Results

### For 9 API Calls:
- **Max Value**: 9
- **Suggested Max**: 15
- **Step Size**: 2
- **Y-Axis Labels**: 0, 2, 4, 6, 8, 10, 12, 14
- **Chart**: Three lines (blue, green, red) clearly visible with proper vertical spacing

### For 0 API Calls:
- **Max Value**: 0
- **Suggested Max**: 10
- **Step Size**: 2
- **Y-Axis Labels**: 0, 2, 4, 6, 8, 10
- **Chart**: Flat lines at 0, but Y-axis still shows scale

### For 25 API Calls:
- **Max Value**: 25
- **Suggested Max**: 25
- **Step Size**: 5
- **Y-Axis Labels**: 0, 5, 10, 15, 20, 25
- **Chart**: Lines with appropriate vertical spacing

---

## Testing Checklist

- [ ] Refresh the monitoring dashboard
- [ ] Navigate to API Monitor tab (`?tab=performance`)
- [ ] Check Y-axis shows values (0, 2, 4, 6, 8, 10, 12, 14 for 9 calls)
- [ ] Check three lines are visible (Blue, Green, Red)
- [ ] Check legend shows: Incoming Requests, Successful Responses, Error Responses
- [ ] Check X-axis shows 00:00 to 23:00
- [ ] Check chart utilizes vertical space properly
- [ ] Check browser console for no JavaScript errors

---

## Files Modified

1. **local/local_alx_report_api/monitoring_dashboard_new.php**
   - Moved PHP calculation block from inside `<script>` tag to before it
   - Changed `$today_start` to `$perf_today_start` to avoid variable conflicts
   - Removed duplicate PHP block that was breaking JavaScript
   - Ensured clean separation between PHP and JavaScript

---

## Why This Was Critical

### Before Fix:
```
Browser sees: <script> ... <?php ... ?> ... </script>
Browser thinks: "What is <?php? This is not JavaScript!"
Result: JavaScript error, chart doesn't render, Y-axis empty
```

### After Fix:
```
Server processes: <?php ... ?> (calculates data)
Server outputs: <script> ... var data = [1,2,3]; ... </script>
Browser sees: <script> ... var data = [1,2,3]; ... </script>
Browser thinks: "This is valid JavaScript!"
Result: Chart renders perfectly, Y-axis shows values
```

---

**Status**: ✅ Critical fix complete - Chart should now display Y-axis values
**Date**: January 7, 2025
**Priority**: CRITICAL - This was blocking the entire chart from working
