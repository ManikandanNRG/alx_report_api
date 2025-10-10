<?php
/**
 * Debug Access Control Status
 * Check why access control shows "Unknown"
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/local_alx_report_api/debug_access_control.php');
$PAGE->set_title('Debug Access Control');

echo $OUTPUT->header();
echo $OUTPUT->heading('Access Control Status Debug');

echo '<div style="max-width: 900px; margin: 0 auto; padding: 20px;">';

// 1. Check Web Services
echo '<h3>1. Web Services Status</h3>';
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<strong>$CFG->enablewebservices:</strong> ';
if (empty($CFG->enablewebservices)) {
    echo '<span style="color: red;">❌ DISABLED</span>';
} else {
    echo '<span style="color: green;">✅ ENABLED</span>';
}
echo '<br><strong>Value:</strong> ' . var_export($CFG->enablewebservices, true);
echo '</div>';

// 2. Check REST Protocol
echo '<h3>2. REST Protocol Status</h3>';
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
try {
    $rest_protocol = $DB->get_record('webservice_protocol', ['name' => 'rest']);
    if ($rest_protocol) {
        echo '<strong>REST Protocol Found:</strong> ✅<br>';
        echo '<strong>Enabled:</strong> ' . ($rest_protocol->enabled ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . '<br>';
        echo '<strong>Record:</strong> <pre>' . print_r($rest_protocol, true) . '</pre>';
    } else {
        echo '<span style="color: red;">❌ REST Protocol NOT FOUND in database</span>';
    }
} catch (Exception $e) {
    echo '<span style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
echo '</div>';

// 3. Check External Services
echo '<h3>3. External Services Status</h3>';
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';

// Check for primary service name
echo '<strong>Checking for "alx_report_api_custom":</strong><br>';
try {
    $service1 = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if ($service1) {
        echo '<span style="color: green;">✅ FOUND</span><br>';
        echo '<strong>Name:</strong> ' . htmlspecialchars($service1->name) . '<br>';
        echo '<strong>Enabled:</strong> ' . ($service1->enabled ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . '<br>';
        echo '<strong>Record:</strong> <pre>' . print_r($service1, true) . '</pre>';
    } else {
        echo '<span style="color: orange;">⚠️ NOT FOUND</span><br>';
    }
} catch (Exception $e) {
    echo '<span style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
}

echo '<br><strong>Checking for "alx_report_api" (fallback):</strong><br>';
try {
    $service2 = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
    if ($service2) {
        echo '<span style="color: green;">✅ FOUND</span><br>';
        echo '<strong>Name:</strong> ' . htmlspecialchars($service2->name) . '<br>';
        echo '<strong>Enabled:</strong> ' . ($service2->enabled ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . '<br>';
        echo '<strong>Record:</strong> <pre>' . print_r($service2, true) . '</pre>';
    } else {
        echo '<span style="color: orange;">⚠️ NOT FOUND</span><br>';
    }
} catch (Exception $e) {
    echo '<span style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
}

echo '<br><strong>All External Services:</strong><br>';
try {
    $all_services = $DB->get_records('external_services');
    if ($all_services) {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>ID</th><th>Name</th><th>Shortname</th><th>Enabled</th></tr>';
        foreach ($all_services as $svc) {
            $enabled_badge = $svc->enabled ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>';
            echo '<tr>';
            echo '<td>' . $svc->id . '</td>';
            echo '<td>' . htmlspecialchars($svc->name) . '</td>';
            echo '<td><strong>' . htmlspecialchars($svc->shortname) . '</strong></td>';
            echo '<td>' . $enabled_badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<span style="color: red;">No services found</span>';
    }
} catch (Exception $e) {
    echo '<span style="color: red;">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
echo '</div>';

// 4. Run the actual check logic
echo '<h3>4. Running Actual Check Logic</h3>';
echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';

$access_control_status = 'Enabled';
$access_control_color = '#10b981';
$access_issues = [];

try {
    // Check if web services are enabled
    if (empty($CFG->enablewebservices)) {
        $access_issues[] = 'Web services disabled';
    }
    
    // Check if REST protocol is enabled
    $rest_enabled = $DB->record_exists('webservice_protocol', ['name' => 'rest', 'enabled' => 1]);
    if (!$rest_enabled) {
        $access_issues[] = 'REST protocol disabled';
    }
    
    // Check if service exists and is enabled
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$service) {
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
    }
    
    if (!$service) {
        $access_issues[] = 'Service not found';
    } else if (empty($service->enabled)) {
        $access_issues[] = 'Service disabled';
    }
    
    // Set status based on issues found
    if (count($access_issues) > 0) {
        $access_control_status = 'Issues';
        $access_control_color = '#ef4444';
    }
    
    echo '<strong>Final Status:</strong> <span style="background: ' . $access_control_color . '; color: white; padding: 4px 12px; border-radius: 12px;">' . $access_control_status . '</span><br>';
    echo '<strong>Issues Found:</strong> ' . (count($access_issues) > 0 ? implode(', ', $access_issues) : 'None') . '<br>';
    
} catch (Exception $e) {
    $access_control_status = 'Unknown';
    $access_control_color = '#6b7280';
    echo '<span style="color: red;"><strong>❌ EXCEPTION CAUGHT:</strong><br>';
    echo 'Message: ' . htmlspecialchars($e->getMessage()) . '<br>';
    echo 'File: ' . $e->getFile() . '<br>';
    echo 'Line: ' . $e->getLine() . '<br>';
    echo 'Trace:<br><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></span>';
}

echo '</div>';

// 5. Recommendations
echo '<h3>5. Recommendations</h3>';
echo '<div style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<ul>';
echo '<li>If web services are disabled, enable them at: <a href="' . $CFG->wwwroot . '/admin/search.php?query=enablewebservices">Site Administration → Advanced Features</a></li>';
echo '<li>If REST protocol is disabled, enable it at: <a href="' . $CFG->wwwroot . '/admin/settings.php?section=webserviceprotocols">Site Administration → Server → Web Services → Manage Protocols</a></li>';
echo '<li>If service is not found or disabled, check: <a href="' . $CFG->wwwroot . '/admin/settings.php?section=externalservices">Site Administration → Server → Web Services → External Services</a></li>';
echo '</ul>';
echo '</div>';

echo '<div style="text-align: center; margin: 20px 0;">';
echo '<a href="control_center.php" class="btn btn-primary">← Back to Control Center</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
