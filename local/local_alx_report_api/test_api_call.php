<?php
// Simple test script to make an API call and verify response time logging
// Run this from your browser: http://localhost/moodle/local/alx_report_api/test_api_call.php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Check if user is logged in and has admin access
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$PAGE->set_url('/local/alx_report_api/test_api_call.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test API Response Time Logging');
$PAGE->set_heading('Test API Response Time Logging');

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
echo '<h2>Response Time Logging Test</h2>';

// Check database schema
$dbman = $DB->get_manager();
$table = new xmldb_table('local_alx_api_logs');

if (!$dbman->table_exists($table)) {
    echo '<div class="alert alert-danger">❌ ERROR: local_alx_api_logs table does not exist!</div>';
    echo $OUTPUT->footer();
    exit;
}

$table_info = $DB->get_columns('local_alx_api_logs');

echo '<h3>Database Schema Check</h3>';
echo '<div class="table-responsive">';
echo '<table class="table table-striped">';
echo '<thead><tr><th>Field</th><th>Status</th></tr></thead><tbody>';

$required_fields = [
    'response_time_ms' => 'Response time tracking',
    'company_shortname' => 'Company identification',
    'error_message' => 'Error tracking',
    'record_count' => 'Record count',
    'timeaccessed' => 'Enhanced time field',
    'ip_address' => 'IP tracking',
    'user_agent' => 'User agent tracking',
    'additional_data' => 'Additional context data'
];

$schema_ok = true;
foreach ($required_fields as $field => $description) {
    if (isset($table_info[$field])) {
        echo "<tr><td>$field</td><td><span class='badge badge-success'>✅ Present</span> - $description</td></tr>";
    } else {
        echo "<tr><td>$field</td><td><span class='badge badge-danger'>❌ Missing</span> - $description</td></tr>";
        $schema_ok = false;
    }
}

echo '</tbody></table>';
echo '</div>';

if (!$schema_ok) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Database schema needs updating!</strong><br>';
    echo 'Visit: <a href="' . $CFG->wwwroot . '/admin/index.php">Site administration > Notifications</a> to trigger database upgrades.';
    echo '</div>';
}

// Test the logging function
echo '<h3>Test Enhanced Logging Function</h3>';
try {
    $start_time = microtime(true);
    
    // Simulate some processing time
    usleep(50000); // 50ms
    
    $end_time = microtime(true);
    $response_time_ms = round(($end_time - $start_time) * 1000, 2);
    
    local_alx_report_api_log_api_call(
        $USER->id,
        'test_company',
        'test_response_time_logging',
        25,
        null,
        $response_time_ms,
        [
            'test_mode' => true,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'test_timestamp' => time()
        ]
    );
    
    echo '<div class="alert alert-success">';
    echo "✅ Test log entry created successfully!<br>";
    echo "Simulated response time: {$response_time_ms}ms<br>";
    echo "User ID: {$USER->id}<br>";
    echo "Company: test_company<br>";
    echo "Endpoint: test_response_time_logging";
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">❌ ERROR creating test log entry: ' . $e->getMessage() . '</div>';
}

// Show recent logs
echo '<h3>Recent API Logs (Last 10)</h3>';

$recent_logs = $DB->get_records_sql("
    SELECT id, userid, company_shortname, endpoint, response_time_ms, 
           timeaccessed, error_message, record_count
    FROM {local_alx_api_logs} 
    ORDER BY timeaccessed DESC 
    LIMIT 10
");

if (empty($recent_logs)) {
    echo '<div class="alert alert-info">No log entries found.</div>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-sm">';
    echo '<thead><tr>';
    echo '<th>Time</th><th>User</th><th>Company</th><th>Endpoint</th>';
    echo '<th>Response Time</th><th>Records</th><th>Status</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($recent_logs as $log) {
        $time = date('Y-m-d H:i:s', $log->timeaccessed);
        $response_time = $log->response_time_ms ? $log->response_time_ms . 'ms' : '<em>Not tracked</em>';
        $status = $log->error_message ? '<span class="badge badge-danger">Error</span>' : '<span class="badge badge-success">Success</span>';
        $company = $log->company_shortname ?: '<em>Unknown</em>';
        
        echo "<tr>";
        echo "<td style='font-size: 11px;'>$time</td>";
        echo "<td>$log->userid</td>";
        echo "<td>$company</td>";
        echo "<td style='font-size: 11px;'>$log->endpoint</td>";
        echo "<td><strong>$response_time</strong></td>";
        echo "<td>$log->record_count</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    echo '</tbody></table>';
    echo '</div>';
}

// Show statistics
echo '<h3>Response Time Statistics</h3>';

$stats = $DB->get_record_sql("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(response_time_ms) as logs_with_response_time,
        AVG(response_time_ms) as avg_response_time,
        MIN(response_time_ms) as min_response_time,
        MAX(response_time_ms) as max_response_time
    FROM {local_alx_api_logs} 
    WHERE response_time_ms IS NOT NULL AND response_time_ms > 0
");

if ($stats) {
    echo '<div class="row">';
    echo '<div class="col-md-3"><div class="card text-center"><div class="card-body">';
    echo '<h5 class="card-title">' . $DB->count_records('local_alx_api_logs') . '</h5>';
    echo '<p class="card-text">Total Logs</p>';
    echo '</div></div></div>';
    
    echo '<div class="col-md-3"><div class="card text-center"><div class="card-body">';
    echo '<h5 class="card-title">' . $stats->logs_with_response_time . '</h5>';
    echo '<p class="card-text">With Response Time</p>';
    echo '</div></div></div>';
    
    if ($stats->avg_response_time) {
        echo '<div class="col-md-3"><div class="card text-center"><div class="card-body">';
        echo '<h5 class="card-title">' . round($stats->avg_response_time, 1) . 'ms</h5>';
        echo '<p class="card-text">Average Response Time</p>';
        echo '</div></div></div>';
        
        echo '<div class="col-md-3"><div class="card text-center"><div class="card-body">';
        echo '<h5 class="card-title">' . $stats->min_response_time . '-' . $stats->max_response_time . 'ms</h5>';
        echo '<p class="card-text">Min-Max Range</p>';
        echo '</div></div></div>';
    }
    
    echo '</div>';
}

// Test actual API call
echo '<h3>Make Real API Call Test</h3>';
echo '<p>You can test the actual API endpoint to see if response time logging works:</p>';

$token = 'dcb2393a31ed38a49c298ddd930d2c79'; // Your working token

echo '<div class="card">';
echo '<div class="card-body">';
echo '<h5>API Test Call</h5>';
echo '<p>Token: <code>' . substr($token, 0, 8) . '...</code></p>';
echo '<p>Endpoint: <code>local_alx_report_api_get_course_progress</code></p>';

echo '<form method="post" style="margin-bottom: 15px;">';
echo '<button type="submit" name="test_api" class="btn btn-primary">Make Test API Call</button>';
echo '</form>';

if (isset($_POST['test_api'])) {
    $api_url = $CFG->wwwroot . '/webservice/rest/server.php';
    $post_data = [
        'wstoken' => $token,
        'wsfunction' => 'local_alx_report_api_get_course_progress',
        'moodlewsrestformat' => 'json',
        'limit' => 5,
        'offset' => 0
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($post_data)
        ]
    ]);
    
    $start_time = microtime(true);
    $result = file_get_contents($api_url, false, $context);
    $end_time = microtime(true);
    $actual_response_time = round(($end_time - $start_time) * 1000, 2);
    
    echo '<div class="alert alert-info">';
    echo "<strong>API call completed!</strong><br>";
    echo "Actual response time: {$actual_response_time}ms<br>";
    echo "Check the logs above to see if the response time was recorded.";
    echo '</div>';
    
    if ($result) {
        $data = json_decode($result, true);
        if ($data && !isset($data['exception'])) {
            echo '<div class="alert alert-success">✅ API call successful! Response time should now be logged.</div>';
        } else {
            echo '<div class="alert alert-warning">⚠️ API call had an error, but response time should still be logged.</div>';
        }
    }
}

echo '</div>';
echo '</div>';

echo '<div style="margin-top: 30px;">';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/control_center.php" class="btn btn-secondary">View Control Center</a>';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/monitoring_dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">View Monitoring Dashboard</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
?> 