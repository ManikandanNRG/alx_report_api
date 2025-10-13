<?php
/**
 * Database Verification Script for ALX Report API Plugin
 * 
 * This script verifies that all tables are properly installed with correct field names
 * after the Bug 2 fix (timeaccessed → timecreated).
 * 
 * Run this from: yoursite.com/local/alx_report_api/verify_database.php
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/verify_database.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Database Verification');
$PAGE->set_heading('Database Verification');

echo $OUTPUT->header();

echo '<h2>ALX Report API Plugin - Database Verification</h2>';

// Check plugin version
echo '<h3>1. Plugin Version Check</h3>';
$version = get_config('local_alx_report_api', 'version');
echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;">';
echo '<strong>Current Version:</strong> ' . ($version ? $version : 'Not installed') . '<br>';
echo '<strong>Expected Version:</strong> 2024100803 or higher (includes Bug 2 fix)<br>';
if ($version >= 2024100803) {
    echo '<span style="color: green;">✅ Version is correct - Bug 2 fix should be applied</span>';
} else {
    echo '<span style="color: red;">❌ Version is outdated - Bug 2 fix may not be applied</span>';
}
echo '</div>';

// Expected tables
$expected_tables = [
    'local_alx_api_logs' => 'API access logging',
    'local_alx_api_settings' => 'Company-specific settings',
    'local_alx_api_reporting' => 'Pre-built reporting data',
    'local_alx_api_sync_status' => 'Sync status tracking',
    'local_alx_api_cache' => 'Performance caching',
    'local_alx_api_alerts' => 'Security and performance alerts'
];

echo '<h3>2. Table Existence Check</h3>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>Table Name</th><th>Description</th><th>Status</th><th>Record Count</th></tr>';

$all_tables_exist = true;
foreach ($expected_tables as $table => $description) {
    echo '<tr>';
    echo '<td>' . $table . '</td>';
    echo '<td>' . $description . '</td>';
    
    if ($DB->get_manager()->table_exists($table)) {
        $count = $DB->count_records($table);
        echo '<td style="color: green;">✅ Exists</td>';
        echo '<td>' . number_format($count) . ' records</td>';
    } else {
        echo '<td style="color: red;">❌ Missing</td>';
        echo '<td>N/A</td>';
        $all_tables_exist = false;
    }
    echo '</tr>';
}
echo '</table>';

if (!$all_tables_exist) {
    echo '<div style="background: #ffebee; padding: 10px; margin: 10px 0; border-radius: 5px; color: #c62828;">';
    echo '<strong>⚠️ Some tables are missing!</strong><br>';
    echo 'Go to Site Administration → Notifications to trigger plugin installation/upgrade.';
    echo '</div>';
}

// Check critical field names (Bug 2 verification)
echo '<h3>3. Critical Field Names Check (Bug 2 Verification)</h3>';

$field_checks = [
    'local_alx_api_logs' => [
        'should_have' => ['timecreated'],
        'should_not_have' => ['timeaccessed'],
        'description' => 'Main logging table - Bug 2 fix'
    ],
    'local_alx_api_reporting' => [
        'should_have' => ['timecreated', 'timemodified'],
        'should_not_have' => ['created_at', 'updated_at'],
        'description' => 'Reporting table - standardized field names'
    ],
    'local_alx_api_cache' => [
        'should_have' => ['timecreated', 'timeaccessed'],
        'should_not_have' => ['cache_timestamp', 'last_accessed'],
        'description' => 'Cache table - timeaccessed is correct here'
    ]
];

echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>Table</th><th>Field Check</th><th>Status</th><th>Notes</th></tr>';

$bug2_fixed = true;
foreach ($field_checks as $table => $checks) {
    if (!$DB->get_manager()->table_exists($table)) {
        continue; // Skip if table doesn't exist
    }
    
    echo '<tr>';
    echo '<td rowspan="2">' . $table . '</td>';
    
    // Check required fields
    echo '<td>Required: ' . implode(', ', $checks['should_have']) . '</td>';
    $all_required_exist = true;
    $missing_fields = [];
    
    foreach ($checks['should_have'] as $field) {
        if (!$DB->get_manager()->field_exists($table, $field)) {
            $all_required_exist = false;
            $missing_fields[] = $field;
            $bug2_fixed = false;
        }
    }
    
    if ($all_required_exist) {
        echo '<td style="color: green;">✅ All present</td>';
    } else {
        echo '<td style="color: red;">❌ Missing: ' . implode(', ', $missing_fields) . '</td>';
    }
    echo '<td rowspan="2">' . $checks['description'] . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    // Check forbidden fields
    echo '<td>Should NOT have: ' . implode(', ', $checks['should_not_have']) . '</td>';
    $forbidden_found = [];
    
    foreach ($checks['should_not_have'] as $field) {
        if ($DB->get_manager()->field_exists($table, $field)) {
            $forbidden_found[] = $field;
            $bug2_fixed = false;
        }
    }
    
    if (empty($forbidden_found)) {
        echo '<td style="color: green;">✅ None found</td>';
    } else {
        echo '<td style="color: red;">❌ Found: ' . implode(', ', $forbidden_found) . '</td>';
    }
    echo '</tr>';
}
echo '</table>';

// Overall status
echo '<h3>4. Overall Status</h3>';
echo '<div style="background: ' . ($bug2_fixed && $all_tables_exist ? '#e8f5e8' : '#ffebee') . '; padding: 15px; margin: 10px 0; border-radius: 5px;">';

if ($bug2_fixed && $all_tables_exist) {
    echo '<h4 style="color: green;">✅ SUCCESS - Everything looks good!</h4>';
    echo '<ul>';
    echo '<li>All 6 tables exist</li>';
    echo '<li>Bug 2 fix is properly applied (timecreated field names)</li>';
    echo '<li>API logging should work correctly</li>';
    echo '<li>Monitoring dashboards should show accurate data</li>';
    echo '</ul>';
} else {
    echo '<h4 style="color: red;">❌ ISSUES FOUND</h4>';
    echo '<ul>';
    if (!$all_tables_exist) {
        echo '<li>Some tables are missing - plugin may not be fully installed</li>';
    }
    if (!$bug2_fixed) {
        echo '<li>Bug 2 fix not applied - field names are incorrect</li>';
        echo '<li>API logging may not work properly</li>';
    }
    echo '</ul>';
    
    echo '<h4>Recommended Actions:</h4>';
    echo '<ol>';
    echo '<li>Go to <strong>Site Administration → Notifications</strong></li>';
    echo '<li>If upgrade is available, run it</li>';
    echo '<li>If problems persist, consider reinstalling the plugin</li>';
    echo '</ol>';
}
echo '</div>';

// Recent API logs test
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    echo '<h3>5. Recent API Logs Test</h3>';
    $recent_logs = $DB->get_records('local_alx_api_logs', null, 'timecreated DESC', 'userid, company_shortname, endpoint, timecreated', 0, 5);
    
    if ($recent_logs) {
        echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>User ID</th><th>Company</th><th>Endpoint</th><th>Time Created</th><th>Formatted Date</th></tr>';
        foreach ($recent_logs as $log) {
            echo '<tr>';
            echo '<td>' . $log->userid . '</td>';
            echo '<td>' . htmlspecialchars($log->company_shortname) . '</td>';
            echo '<td>' . htmlspecialchars($log->endpoint) . '</td>';
            echo '<td>' . $log->timecreated . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', $log->timecreated) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p style="color: green;">✅ API logging is working - timecreated field is functional</p>';
    } else {
        echo '<p style="color: orange;">⚠️ No API logs found yet - this is normal for new installations</p>';
    }
}

echo $OUTPUT->footer();
?>