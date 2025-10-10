<?php
/**
 * Debug script to check rate limit data
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

require_login();

if (!is_siteadmin()) {
    die('Access denied. Only site administrators can access this script.');
}

echo "<h1>Rate Limit Debug Information</h1>";

$today_start = mktime(0, 0, 0);

echo "<h2>1. Companies from local_alx_report_api_get_companies()</h2>";
$companies = local_alx_report_api_get_companies();
echo "<p>Found " . count($companies) . " companies</p>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Shortname</th><th>Rate Limit</th></tr>";
foreach ($companies as $company) {
    $settings = local_alx_report_api_get_company_settings($company->id);
    $rate_limit = isset($settings['rate_limit']) ? $settings['rate_limit'] : 'Not set (using global)';
    echo "<tr>";
    echo "<td>{$company->id}</td>";
    echo "<td>{$company->name}</td>";
    echo "<td>{$company->shortname}</td>";
    echo "<td>{$rate_limit}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>2. API Logs Table Structure</h2>";
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    echo "<p>Table exists. Columns:</p>";
    echo "<ul>";
    foreach ($table_info as $column_name => $column_info) {
        echo "<li><strong>{$column_name}</strong>: {$column_info->meta_type}</li>";
    }
    echo "</ul>";
    
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    echo "<p>Using time field: <strong>{$time_field}</strong></p>";
} else {
    echo "<p style='color:red;'>Table local_alx_api_logs does NOT exist!</p>";
}

echo "<h2>3. API Logs Data (Today)</h2>";
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    $logs = $DB->get_records_select('local_alx_api_logs', 
        "{$time_field} >= ?", 
        [$today_start], 
        "{$time_field} DESC", 
        '*', 
        0, 
        20
    );
    
    echo "<p>Found " . count($logs) . " log entries today (showing first 20)</p>";
    
    if (!empty($logs)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Company Shortname</th><th>Endpoint</th><th>Time</th></tr>";
        foreach ($logs as $log) {
            $time = isset($log->timeaccessed) ? $log->timeaccessed : $log->timecreated;
            echo "<tr>";
            echo "<td>{$log->id}</td>";
            echo "<td>{$log->userid}</td>";
            echo "<td>" . ($log->company_shortname ?? '<em>NULL</em>') . "</td>";
            echo "<td>{$log->endpoint}</td>";
            echo "<td>" . date('Y-m-d H:i:s', $time) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>No API logs found for today.</p>";
    }
    
    // Count by company
    echo "<h3>API Calls by Company (Today)</h3>";
    $sql = "SELECT company_shortname, COUNT(*) as call_count
            FROM {local_alx_api_logs}
            WHERE {$time_field} >= ?
            GROUP BY company_shortname";
    $counts = $DB->get_records_sql($sql, [$today_start]);
    
    if (!empty($counts)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Company Shortname</th><th>Call Count</th></tr>";
        foreach ($counts as $count) {
            $shortname = $count->company_shortname ?? '<em>NULL</em>';
            echo "<tr>";
            echo "<td>{$shortname}</td>";
            echo "<td>{$count->call_count}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<h2>4. Rate Limit Violation Check</h2>";
$violations = 0;
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Company</th><th>Shortname</th><th>Rate Limit</th><th>Calls Today</th><th>Status</th></tr>";

foreach ($companies as $company) {
    $settings = local_alx_report_api_get_company_settings($company->id);
    $company_rate_limit = isset($settings['rate_limit']) ? $settings['rate_limit'] : get_config('local_alx_report_api', 'rate_limit');
    
    if (empty($company_rate_limit)) {
        $company_rate_limit = 100;
    }
    
    $company_calls_today = $DB->count_records_select('local_alx_api_logs',
        "{$time_field} >= ? AND company_shortname = ?",
        [$today_start, $company->shortname]
    );
    
    $status = $company_calls_today > $company_rate_limit ? '<strong style="color:red;">VIOLATION</strong>' : '<span style="color:green;">OK</span>';
    
    if ($company_calls_today > $company_rate_limit) {
        $violations++;
    }
    
    echo "<tr>";
    echo "<td>{$company->name}</td>";
    echo "<td>{$company->shortname}</td>";
    echo "<td>{$company_rate_limit}</td>";
    echo "<td>{$company_calls_today}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Total Violations: <strong style='color:red; font-size:24px;'>{$violations}</strong></h3>";

echo "<h2>5. Global Settings</h2>";
$global_rate_limit = get_config('local_alx_report_api', 'rate_limit');
echo "<p>Global Rate Limit: <strong>" . ($global_rate_limit ?: '100 (default)') . "</strong></p>";
