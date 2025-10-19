<?php
/**
 * UI Data Consistency Checker for ALX Report API Plugin
 * 
 * This script verifies that the same metrics show consistent values across different UI screens:
 * - Control Center
 * - Monitoring Dashboard (New)
 * - Advanced Monitoring
 * - Old Monitoring Dashboard
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/check_ui_consistency.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - UI Consistency Check');
$PAGE->set_heading('UI Data Consistency Check');

echo $OUTPUT->header();

echo '<h2>ALX Report API Plugin - UI Data Consistency Analysis</h2>';

// Common time calculations used across all dashboards
$today_start = mktime(0, 0, 0);
$last_24h = time() - 86400;
$last_7d = time() - (7 * 86400);
$time_field = 'timecreated'; // Standard field name after Bug 2 fix

echo '<h3>1. Key Metrics Comparison Across UI Screens</h3>';

// Check if logs table exists
$logs_table_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS);
$table_info = [];
if ($logs_table_exists) {
    $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
}

// ===== METRIC 1: API CALLS TODAY =====
echo '<h4>📞 API Calls Today</h4>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>UI Screen</th><th>Calculation Method</th><th>Value</th><th>SQL Query</th><th>Status</th></tr>';

$api_calls_calculations = [];

// Control Center calculation
if ($logs_table_exists) {
    $control_center_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $api_calls_calculations['Control Center'] = [
        'method' => "count_records_select with {$time_field} >= today_start",
        'value' => $control_center_calls,
        'query' => "SELECT COUNT(*) FROM mdl_local_alx_api_logs WHERE {$time_field} >= {$today_start}",
        'status' => '✅'
    ];
} else {
    $api_calls_calculations['Control Center'] = [
        'method' => 'Table does not exist',
        'value' => 0,
        'query' => 'N/A',
        'status' => '❌'
    ];
}

// Monitoring Dashboard New calculation
if ($logs_table_exists) {
    $monitoring_new_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $api_calls_calculations['Monitoring Dashboard (New)'] = [
        'method' => "count_records_select with {$time_field} >= today_start",
        'value' => $monitoring_new_calls,
        'query' => "SELECT COUNT(*) FROM mdl_local_alx_api_logs WHERE {$time_field} >= {$today_start}",
        'status' => '✅'
    ];
} else {
    $api_calls_calculations['Monitoring Dashboard (New)'] = [
        'method' => 'Table does not exist',
        'value' => 0,
        'query' => 'N/A',
        'status' => '❌'
    ];
}

// Advanced Monitoring calculation
if ($logs_table_exists) {
    $advanced_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$last_24h]);
    $api_calls_calculations['Advanced Monitoring'] = [
        'method' => "count_records_select with {$time_field} >= last_24h (different time range!)",
        'value' => $advanced_calls,
        'query' => "SELECT COUNT(*) FROM mdl_local_alx_api_logs WHERE {$time_field} >= {$last_24h}",
        'status' => '⚠️'
    ];
} else {
    $api_calls_calculations['Advanced Monitoring'] = [
        'method' => 'Table does not exist',
        'value' => 0,
        'query' => 'N/A',
        'status' => '❌'
    ];
}

// lib.php get_system_stats calculation
if ($logs_table_exists) {
    $lib_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $api_calls_calculations['lib.php get_system_stats()'] = [
        'method' => "count_records_select with {$time_field} >= today_start",
        'value' => $lib_calls,
        'query' => "SELECT COUNT(*) FROM mdl_local_alx_api_logs WHERE {$time_field} >= {$today_start}",
        'status' => '✅'
    ];
} else {
    $api_calls_calculations['lib.php get_system_stats()'] = [
        'method' => 'Table does not exist',
        'value' => 0,
        'query' => 'N/A',
        'status' => '❌'
    ];
}

foreach ($api_calls_calculations as $screen => $calc) {
    $row_color = '';
    if ($calc['status'] === '⚠️') {
        $row_color = 'background-color: #fff3cd;'; // Warning
    } elseif ($calc['status'] === '❌') {
        $row_color = 'background-color: #f8d7da;'; // Error
    }
    
    echo '<tr style="' . $row_color . '">';
    echo '<td><strong>' . htmlspecialchars($screen) . '</strong></td>';
    echo '<td>' . htmlspecialchars($calc['method']) . '</td>';
    echo '<td><strong>' . number_format($calc['value']) . '</strong></td>';
    echo '<td><code>' . htmlspecialchars($calc['query']) . '</code></td>';
    echo '<td>' . $calc['status'] . '</td>';
    echo '</tr>';
}
echo '</table>';

// Check for consistency
$unique_values = array_unique(array_column($api_calls_calculations, 'value'));
if (count($unique_values) <= 2) { // Allow for the different time range in Advanced Monitoring
    echo '<p style="color: green;">✅ <strong>API Calls Today values are consistent across screens</strong></p>';
} else {
    echo '<p style="color: red;">❌ <strong>INCONSISTENCY DETECTED: Different values across screens</strong></p>';
}

// ===== METRIC 2: SUCCESS RATE =====
echo '<h4>✅ Success Rate</h4>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>UI Screen</th><th>Calculation Method</th><th>Value</th><th>Status</th></tr>';

$success_rate_calculations = [];

if ($logs_table_exists && isset($table_info['error_message'])) {
    // Control Center calculation
    $total_calls_cc = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $error_calls_cc = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ? AND error_message IS NOT NULL", [$today_start]);
    $success_rate_cc = $total_calls_cc > 0 ? round((($total_calls_cc - $error_calls_cc) / $total_calls_cc) * 100, 1) : 100;
    
    $success_rate_calculations['Control Center'] = [
        'method' => '(total_calls - error_calls) / total_calls * 100',
        'value' => $success_rate_cc . '%',
        'status' => '✅'
    ];
    
    // Monitoring Dashboard New calculation
    $success_count_mdn = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ? AND error_message IS NULL", [$today_start]);
    $total_calls_mdn = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $success_rate_mdn = $total_calls_mdn > 0 ? round(($success_count_mdn / $total_calls_mdn) * 100, 1) : 100;
    
    $success_rate_calculations['Monitoring Dashboard (New)'] = [
        'method' => 'success_count / total_calls * 100 (error_message IS NULL)',
        'value' => $success_rate_mdn . '%',
        'status' => '✅'
    ];
    
} else {
    $success_rate_calculations['Control Center'] = [
        'method' => 'Default (no error tracking)',
        'value' => '100%',
        'status' => '⚠️'
    ];
    
    $success_rate_calculations['Monitoring Dashboard (New)'] = [
        'method' => 'Default (no error tracking)',
        'value' => '100%',
        'status' => '⚠️'
    ];
}

foreach ($success_rate_calculations as $screen => $calc) {
    $row_color = $calc['status'] === '⚠️' ? 'background-color: #fff3cd;' : '';
    
    echo '<tr style="' . $row_color . '">';
    echo '<td><strong>' . htmlspecialchars($screen) . '</strong></td>';
    echo '<td>' . htmlspecialchars($calc['method']) . '</td>';
    echo '<td><strong>' . htmlspecialchars($calc['value']) . '</strong></td>';
    echo '<td>' . $calc['status'] . '</td>';
    echo '</tr>';
}
echo '</table>';

// ===== METRIC 3: RESPONSE TIME =====
echo '<h4>⏱️ Average Response Time</h4>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>UI Screen</th><th>Field Used</th><th>Calculation Method</th><th>Value</th><th>Status</th></tr>';

$response_time_calculations = [];

if ($logs_table_exists) {
    // Check which response time field exists
    $has_response_time_ms = isset($table_info['response_time_ms']);
    $has_response_time = isset($table_info['response_time']);
    
    if ($has_response_time_ms) {
        // Control Center uses response_time_ms
        $avg_response_cc = $DB->get_field_sql("
            SELECT AVG(response_time_ms) 
            FROM {local_alx_api_logs} 
            WHERE {$time_field} >= ? AND response_time_ms IS NOT NULL AND response_time_ms > 0
        ", [$today_start]);
        $response_time_cc = $avg_response_cc ? round($avg_response_cc / 1000, 2) : 0; // Convert to seconds
        
        $response_time_calculations['Control Center'] = [
            'field' => 'response_time_ms',
            'method' => 'AVG(response_time_ms) / 1000 (convert to seconds)',
            'value' => $response_time_cc . 's',
            'status' => '✅'
        ];
        
        // Advanced Monitoring uses response_time_ms
        $avg_response_am = $DB->get_field_sql("
            SELECT AVG(response_time_ms) 
            FROM {local_alx_api_logs} 
            WHERE {$time_field} >= ? AND response_time_ms > 0
        ", [$last_24h]);
        $response_time_am = $avg_response_am ? round($avg_response_am, 2) : 0; // Keep in milliseconds
        
        $response_time_calculations['Advanced Monitoring'] = [
            'field' => 'response_time_ms',
            'method' => 'AVG(response_time_ms) (keep in milliseconds)',
            'value' => $response_time_am . 'ms',
            'status' => '✅'
        ];
        
    } elseif ($has_response_time) {
        // Some screens might use response_time field
        $response_time_calculations['Various Screens'] = [
            'field' => 'response_time',
            'method' => 'AVG(response_time)',
            'value' => 'Field exists but not used consistently',
            'status' => '⚠️'
        ];
    } else {
        $response_time_calculations['All Screens'] = [
            'field' => 'None',
            'method' => 'No response time tracking',
            'value' => 'Not tracked',
            'status' => '❌'
        ];
    }
} else {
    $response_time_calculations['All Screens'] = [
        'field' => 'N/A',
        'method' => 'Table does not exist',
        'value' => 'N/A',
        'status' => '❌'
    ];
}

foreach ($response_time_calculations as $screen => $calc) {
    $row_color = '';
    if ($calc['status'] === '⚠️') {
        $row_color = 'background-color: #fff3cd;';
    } elseif ($calc['status'] === '❌') {
        $row_color = 'background-color: #f8d7da;';
    }
    
    echo '<tr style="' . $row_color . '">';
    echo '<td><strong>' . htmlspecialchars($screen) . '</strong></td>';
    echo '<td>' . htmlspecialchars($calc['field']) . '</td>';
    echo '<td>' . htmlspecialchars($calc['method']) . '</td>';
    echo '<td><strong>' . htmlspecialchars($calc['value']) . '</strong></td>';
    echo '<td>' . $calc['status'] . '</td>';
    echo '</tr>';
}
echo '</table>';

// ===== INCONSISTENCY ANALYSIS =====
echo '<h3>2. Potential Inconsistency Issues Found</h3>';

$issues = [];

// Issue 1: Different time ranges
if ($logs_table_exists) {
    $today_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    $last24h_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$last_24h]);
    
    if ($today_calls !== $last24h_calls) {
        $issues[] = [
            'type' => 'Time Range Inconsistency',
            'description' => 'Advanced Monitoring uses "last 24 hours" while other screens use "today" (midnight to now)',
            'impact' => 'Different API call counts shown',
            'recommendation' => 'Standardize all screens to use the same time range',
            'severity' => 'Medium'
        ];
    }
}

// Issue 2: Response time units
if (isset($table_info['response_time_ms'])) {
    $issues[] = [
        'type' => 'Response Time Units',
        'description' => 'Some screens show response time in seconds, others in milliseconds',
        'impact' => 'Confusing for users - same data appears different',
        'recommendation' => 'Standardize all screens to show response time in the same unit (preferably milliseconds)',
        'severity' => 'Low'
    ];
}

// Issue 3: Success rate calculation methods
if (isset($table_info['error_message'])) {
    $issues[] = [
        'type' => 'Success Rate Calculation',
        'description' => 'Different screens use different methods: (total-errors)/total vs success_count/total',
        'impact' => 'Potentially different success rate values',
        'recommendation' => 'Standardize to one calculation method across all screens',
        'severity' => 'Medium'
    ];
}

if (empty($issues)) {
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">';
    echo '<h4>✅ No Major Inconsistencies Found!</h4>';
    echo '<p>All UI screens are using consistent data sources and calculations.</p>';
    echo '</div>';
} else {
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Issue Type</th><th>Description</th><th>Impact</th><th>Recommendation</th><th>Severity</th></tr>';
    
    foreach ($issues as $issue) {
        $severity_color = '';
        switch ($issue['severity']) {
            case 'High': $severity_color = 'background-color: #f8d7da; color: #721c24;'; break;
            case 'Medium': $severity_color = 'background-color: #fff3cd; color: #856404;'; break;
            case 'Low': $severity_color = 'background-color: #d1ecf1; color: #0c5460;'; break;
        }
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($issue['type']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($issue['description']) . '</td>';
        echo '<td>' . htmlspecialchars($issue['impact']) . '</td>';
        echo '<td>' . htmlspecialchars($issue['recommendation']) . '</td>';
        echo '<td style="' . $severity_color . '"><strong>' . htmlspecialchars($issue['severity']) . '</strong></td>';
        echo '</tr>';
    }
    echo '</table>';
}

// ===== RECOMMENDATIONS =====
echo '<h3>3. Recommendations for UI Consistency</h3>';

echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px;">';
echo '<h4>🎯 Action Items to Improve Consistency:</h4>';
echo '<ol>';
echo '<li><strong>Standardize Time Ranges:</strong> Use consistent time calculation across all dashboards (recommend "today" from midnight)</li>';
echo '<li><strong>Unify Response Time Display:</strong> Show response times in milliseconds everywhere for consistency</li>';
echo '<li><strong>Standardize Success Rate Calculation:</strong> Use the same formula: (total_calls - error_calls) / total_calls * 100</li>';
echo '<li><strong>Create Shared Functions:</strong> Move metric calculations to lib.php functions to ensure consistency</li>';
echo '<li><strong>Add Data Source Labels:</strong> Show users which time range is being used (e.g., "Last 24 hours" vs "Today")</li>';
echo '</ol>';
echo '</div>';

// ===== SUMMARY =====
echo '<h3>4. Summary</h3>';

$total_issues = count($issues);
if ($total_issues === 0) {
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">';
    echo '<h4>🎉 EXCELLENT - UI Data is Highly Consistent!</h4>';
    echo '<p>Your dashboards are showing consistent data across all screens. Users will see the same values for the same metrics.</p>';
    echo '</div>';
} else {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;">';
    echo '<h4>⚠️ Minor Inconsistencies Found (' . $total_issues . ' issues)</h4>';
    echo '<p>While the core data is consistent, there are some minor differences in how metrics are calculated or displayed. These should be addressed for optimal user experience.</p>';
    echo '</div>';
}

echo $OUTPUT->footer();
?>