<?php
/**
 * Rate Limit Checker for ALX Report API
 * 
 * This script helps you check:
 * 1. Current rate limit settings
 * 2. How many requests you've made today
 * 3. Recent API logs
 * 4. When the rate limit will reset
 */

// Include Moodle configuration
require_once('../../config.php');

// Require login to access this script
require_login();

// Check if user is admin or has appropriate permissions
if (!is_siteadmin()) {
    die('Access denied. Only site administrators can access this script.');
}

echo "<h1>ALX Report API - Rate Limit Status</h1>";

// Get current settings
$rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
$max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
$allow_get = get_config('local_alx_report_api', 'allow_get_method') ? 'Yes' : 'No';

echo "<h2>Current Plugin Settings</h2>";
echo "<ul>";
echo "<li><strong>Daily Rate Limit:</strong> $rate_limit requests per day</li>";
echo "<li><strong>Max Records per Request:</strong> $max_records</li>";
echo "<li><strong>Allow GET Method:</strong> $allow_get</li>";
echo "</ul>";

// Calculate today's start time
$today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
$today_start_readable = date('Y-m-d 00:00:00', $today_start);
$tomorrow_start = $today_start + (24 * 60 * 60);
$tomorrow_start_readable = date('Y-m-d 00:00:00', $tomorrow_start);

echo "<h2>Rate Limit Period</h2>";
echo "<ul>";
echo "<li><strong>Today Started:</strong> $today_start_readable</li>";
echo "<li><strong>Rate Limit Resets:</strong> $tomorrow_start_readable</li>";
echo "<li><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</li>";
echo "</ul>";

// Check if log table exists
if (!$DB->get_manager()->table_exists('local_alx_api_logs')) {
    echo "<p><strong>Warning:</strong> Log table 'local_alx_api_logs' does not exist yet. It will be created on first API call.</p>";
    exit;
}

// Check which time field exists in the logs table
$table_info = $DB->get_columns('local_alx_api_logs');
$time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';

echo "<p><small><em>Debug: Using time field: $time_field</em></small></p>";

try {
    // Get all users who made API calls today
    $sql = "SELECT userid, COUNT(*) as request_count, 
                   MIN($time_field) as first_request, 
                   MAX($time_field) as last_request
            FROM {local_alx_api_logs} 
            WHERE $time_field >= :today_start
            GROUP BY userid
            ORDER BY request_count DESC, last_request DESC";

    $user_requests = $DB->get_records_sql($sql, ['today_start' => $today_start]);

    echo "<h2>Today's API Usage by User</h2>";

    if (empty($user_requests)) {
        echo "<p>No API requests made today.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<thead>";
        echo "<tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Requests Today</th><th>Rate Limit Status</th><th>First Request</th><th>Last Request</th></tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($user_requests as $user_request) {
            $user = $DB->get_record('user', ['id' => $user_request->userid]);
            $username = $user ? $user->username : 'Unknown';
            $fullname = $user ? fullname($user) : 'Unknown User';
            
            $status_color = $user_request->request_count >= $rate_limit ? 'red' : 
                           ($user_request->request_count >= ($rate_limit * 0.8) ? 'orange' : 'green');
            
            $status_text = $user_request->request_count >= $rate_limit ? 'EXCEEDED' : 
                          ($user_request->request_count >= ($rate_limit * 0.8) ? 'WARNING' : 'OK');
            
            echo "<tr>";
            echo "<td>{$user_request->userid}</td>";
            echo "<td>$username</td>";
            echo "<td>$fullname</td>";
            echo "<td>{$user_request->request_count} / $rate_limit</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>$status_text</td>";
            echo "<td>" . date('H:i:s', $user_request->first_request) . "</td>";
            echo "<td>" . date('H:i:s', $user_request->last_request) . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }

    // Show recent API logs (last 20 requests)
    echo "<h2>Recent API Logs (Last 20 Requests)</h2>";

    $recent_logs_sql = "SELECT l.*, u.username, u.firstname, u.lastname 
                        FROM {local_alx_api_logs} l
                        LEFT JOIN {user} u ON u.id = l.userid
                        ORDER BY l.$time_field DESC";

    $recent_logs = $DB->get_records_sql($recent_logs_sql, [], 0, 20);

    if (empty($recent_logs)) {
        echo "<p>No API logs found.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<thead>";
        echo "<tr><th>Time</th><th>User</th><th>Company</th><th>Endpoint</th><th>IP Address</th><th>User Agent</th><th>Records</th><th>Response Time</th></tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($recent_logs as $log) {
            $time = date('Y-m-d H:i:s', $log->$time_field);
            $user_info = $log->username ? "{$log->firstname} {$log->lastname} ({$log->username})" : "User ID: {$log->userid}";
            
            // Handle company field - check if company_shortname exists, fallback to companyid
            $company_display = 'N/A';
            if (isset($table_info['company_shortname']) && !empty($log->company_shortname)) {
                $company_display = $log->company_shortname;
            } elseif (isset($log->companyid)) {
                $company_display = "ID: {$log->companyid}";
            }
            
            $user_agent = !empty($log->useragent) ? substr($log->useragent, 0, 50) . '...' : 'N/A';
            $record_count = isset($log->record_count) ? $log->record_count : 'N/A';
            $response_time = isset($log->response_time_ms) ? $log->response_time_ms . 'ms' : 'N/A';
            
            echo "<tr>";
            echo "<td>$time</td>";
            echo "<td>$user_info</td>";
            echo "<td>$company_display</td>";
            echo "<td>{$log->endpoint}</td>";
            echo "<td>{$log->ipaddress}</td>";
            echo "<td>$user_agent</td>";
            echo "<td>$record_count</td>";
            echo "<td>$response_time</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #ffeeee;'>";
    echo "<strong>Database Error:</strong> " . $e->getMessage();
    echo "<br><small>This may indicate a table structure mismatch. Please check if the plugin was properly installed.</small>";
    echo "</div>";
}

// Show how to reset rate limit for testing
echo "<h2>Testing Options</h2>";
echo "<div style='background-color: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
echo "<h3>To Reset Rate Limit for Testing:</h3>";
echo "<p>1. <strong>Wait until tomorrow:</strong> Rate limit automatically resets at midnight</p>";
echo "<p>2. <strong>Clear today's logs:</strong> Run this SQL query in your database:</p>";
echo "<code>DELETE FROM mdl_local_alx_api_logs WHERE $time_field >= $today_start;</code>";
echo "<p>3. <strong>Increase rate limit:</strong> Go to Site Administration > Plugins > Local plugins > ALX Report API and increase the rate limit</p>";
echo "</div>";

// Show current token info if available - check both service names
echo "<h2>Active Tokens</h2>";

// Check for both service names (custom first, then fallback)
$service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
if (!$service) {
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
}

if ($service) {
    echo "<p><em>Service found: {$service->shortname}</em></p>";
    
    try {
        $tokens = $DB->get_records_sql(
            "SELECT t.*, u.username, u.firstname, u.lastname 
             FROM {external_tokens} t
             JOIN {user} u ON u.id = t.userid
             WHERE t.externalserviceid = :serviceid
             ORDER BY t.timecreated DESC",
            ['serviceid' => $service->id]
        );
        
        if (empty($tokens)) {
            echo "<p>No active tokens found.</p>";
        } else {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<thead>";
            echo "<tr><th>Token</th><th>User</th><th>Created</th><th>Valid Until</th><th>Status</th></tr>";
            echo "</thead>";
            echo "<tbody>";
            
            foreach ($tokens as $token) {
                $user_info = "{$token->firstname} {$token->lastname} ({$token->username})";
                $created = date('Y-m-d H:i:s', $token->timecreated);
                $valid_until = $token->validuntil ? date('Y-m-d H:i:s', $token->validuntil) : 'Never expires';
                $status = (!$token->validuntil || $token->validuntil > time()) ? 'Active' : 'Expired';
                $status_color = $status === 'Active' ? 'green' : 'red';
                
                echo "<tr>";
                echo "<td>" . substr($token->token, 0, 8) . "...</td>";
                echo "<td>$user_info</td>";
                echo "<td>$created</td>";
                echo "<td>$valid_until</td>";
                echo "<td style='color: $status_color; font-weight: bold;'>$status</td>";
                echo "</tr>";
            }
            
            echo "</tbody>";
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<div style='color: red;'>";
        echo "<strong>Error loading tokens:</strong> " . $e->getMessage();
        echo "</div>";
    }
} else {
    echo "<p><strong>ALX Report API service not found.</strong></p>";
    echo "<p><em>This could mean:</em></p>";
    echo "<ul>";
    echo "<li>The plugin hasn't been properly installed</li>";
    echo "<li>The web service hasn't been created yet</li>";
    echo "<li>The service has a different name</li>";
    echo "</ul>";
    
    // Show available services for debugging
    echo "<h3>Available External Services (for debugging):</h3>";
    $all_services = $DB->get_records_sql(
        "SELECT shortname, name FROM {external_services} WHERE shortname LIKE '%alx%' OR shortname LIKE '%report%'"
    );
    
    if ($all_services) {
        echo "<ul>";
        foreach ($all_services as $svc) {
            echo "<li><strong>{$svc->shortname}</strong> - {$svc->name}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No ALX-related services found.</p>";
    }
}

// Show system information
echo "<h2>System Information</h2>";
echo "<ul>";
echo "<li><strong>Moodle Version:</strong> " . $CFG->version . "</li>";
echo "<li><strong>Plugin Version:</strong> " . (get_config('local_alx_report_api', 'version') ?: 'Unknown') . "</li>";
echo "<li><strong>Web Services Enabled:</strong> " . (empty($CFG->enablewebservices) ? 'No' : 'Yes') . "</li>";
echo "<li><strong>Log Table Exists:</strong> " . ($DB->get_manager()->table_exists('local_alx_api_logs') ? 'Yes' : 'No') . "</li>";
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $log_count = $DB->count_records('local_alx_api_logs');
    echo "<li><strong>Total Log Records:</strong> " . number_format($log_count) . "</li>";
    echo "<li><strong>Time Field Used:</strong> $time_field</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><small>Generated at: " . date('Y-m-d H:i:s') . " | Page refreshes automatically show current data</small></p>";
?> 