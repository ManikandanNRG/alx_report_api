<?php
/**
 * Fix Web Service Access Issues for ALX Report API
 * Run this script to fix "Access control exception" errors
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Page setup
$PAGE->set_url('/local/alx_report_api/fix_webservice_access.php');
$PAGE->set_title('ALX Report API - Fix Web Service Access');
$PAGE->set_heading('Fix Web Service Access Issues');
$PAGE->set_context(context_system::instance());

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
echo '<h2>🔧 ALX Report API - Fix Web Service Access</h2>';

// Check current status
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>📊 Current Web Service Status</h3>';

$issues = [];
$fixes_applied = [];

// 1. Check if web services are enabled
$webservices_enabled = get_config('moodle', 'enablewebservices');
echo '<div style="margin: 10px 0;">';
if ($webservices_enabled) {
    echo '<span style="color: green;">✅ Web services are enabled</span>';
} else {
    echo '<span style="color: red;">❌ Web services are disabled</span>';
    $issues[] = 'Web services disabled';
}
echo '</div>';

// 2. Check REST protocol
$protocols = get_config('moodle', 'webserviceprotocols');
$rest_enabled = strpos($protocols, 'rest') !== false;
echo '<div style="margin: 10px 0;">';
if ($rest_enabled) {
    echo '<span style="color: green;">✅ REST protocol is enabled</span>';
} else {
    echo '<span style="color: red;">❌ REST protocol is disabled</span>';
    $issues[] = 'REST protocol disabled';
}
echo '</div>';

// 3. Check if service exists
$service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
if (!$service) {
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
}
echo '<div style="margin: 10px 0;">';
if ($service) {
    echo '<span style="color: green;">✅ ALX Report API service exists (ID: ' . $service->id . ')</span>';
} else {
    echo '<span style="color: red;">❌ ALX Report API service is missing</span>';
    $issues[] = 'Service missing';
}
echo '</div>';

// 4. Check function mapping
if ($service) {
    $function_mapped = $DB->record_exists('external_services_functions', [
        'externalserviceid' => $service->id,
        'functionname' => 'local_alx_report_api_get_course_progress'
    ]);
    echo '<div style="margin: 10px 0;">';
    if ($function_mapped) {
        echo '<span style="color: green;">✅ Function is mapped to service</span>';
    } else {
        echo '<span style="color: red;">❌ Function is not mapped to service</span>';
        $issues[] = 'Function not mapped';
    }
    echo '</div>';
}

// 5. Check if function exists
$function_exists = $DB->record_exists('external_functions', ['name' => 'local_alx_report_api_get_course_progress']);
echo '<div style="margin: 10px 0;">';
if ($function_exists) {
    echo '<span style="color: green;">✅ External function is registered</span>';
} else {
    echo '<span style="color: red;">❌ External function is not registered</span>';
    $issues[] = 'Function not registered';
}
echo '</div>';

echo '</div>';

// Handle form submission to fix issues
if ($_POST && isset($_POST['fix_issues'])) {
    echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    echo '<h3>🚀 Fixing Web Service Issues...</h3>';
    
    try {
        // Fix 1: Enable web services
        if (!$webservices_enabled) {
            set_config('enablewebservices', 1);
            echo '<div style="color: green;">✅ Enabled web services</div>';
            $fixes_applied[] = 'Enabled web services';
        }
        
        // Fix 2: Enable REST protocol
        if (!$rest_enabled) {
            $current_protocols = get_config('moodle', 'webserviceprotocols');
            if (empty($current_protocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $current_protocols . ',rest');
            }
            echo '<div style="color: green;">✅ Enabled REST protocol</div>';
            $fixes_applied[] = 'Enabled REST protocol';
        }
        
        // Fix 3: Create service if missing
        if (!$service) {
            $servicedata = new stdClass();
            $servicedata->name = 'ALX Report API Custom Service';
            $servicedata->shortname = 'alx_report_api_custom';
            $servicedata->component = 'local_alx_report_api';
            $servicedata->timecreated = time();
            $servicedata->timemodified = time();
            $servicedata->enabled = 1;
            $servicedata->restrictedusers = 0;
            $servicedata->downloadfiles = 0;
            $servicedata->uploadfiles = 0;
            
            $serviceid = $DB->insert_record('external_services', $servicedata);
            $service = $DB->get_record('external_services', ['id' => $serviceid]);
            echo '<div style="color: green;">✅ Created ALX Report API service (ID: ' . $serviceid . ')</div>';
            $fixes_applied[] = 'Created API service';
        }
        
        // Fix 4: Map function to service
        if ($service && !$function_mapped) {
            $servicefunction = new stdClass();
            $servicefunction->externalserviceid = $service->id;
            $servicefunction->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $servicefunction);
            echo '<div style="color: green;">✅ Mapped function to service</div>';
            $fixes_applied[] = 'Mapped function to service';
        }
        
        // Success message
        if (!empty($fixes_applied)) {
            echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;">';
            echo '<h4 style="color: #155724; margin: 0;">🎉 Fixes Applied Successfully!</h4>';
            echo '<ul style="color: #155724; margin: 10px 0;">';
            foreach ($fixes_applied as $fix) {
                echo '<li>' . $fix . '</li>';
            }
            echo '</ul>';
            echo '<p style="color: #155724; margin: 10px 0 0 0;"><strong>Try your API call again now!</strong></p>';
            echo '</div>';
        }
        
        // Refresh page to show updated status
        echo '<script>setTimeout(function(){ window.location.reload(); }, 3000);</script>';
        
    } catch (Exception $e) {
        echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb;">';
        echo '<h4 style="color: #721c24; margin: 0;">❌ Error Applying Fixes</h4>';
        echo '<p style="color: #721c24; margin: 10px 0 0 0;">Error: ' . $e->getMessage() . '</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

// Show fix button if there are issues
if (!empty($issues)) {
    echo '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;">';
    echo '<h3 style="color: #856404;">⚠️ Issues Found</h3>';
    echo '<p style="color: #856404;">The following issues may be causing your "Access control exception" error:</p>';
    echo '<ul style="color: #856404;">';
    foreach ($issues as $issue) {
        echo '<li>' . $issue . '</li>';
    }
    echo '</ul>';
    
    echo '<form method="post" style="margin: 20px 0;">';
    echo '<input type="hidden" name="fix_issues" value="1">';
    echo '<button type="submit" style="background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">🔧 Fix All Issues</button>';
    echo '</form>';
    echo '</div>';
} else {
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #c3e6cb;">';
    echo '<h3 style="color: #155724;">✅ All Web Service Settings Look Good</h3>';
    echo '<p style="color: #155724;">If you\'re still getting "Access control exception", check:</p>';
    echo '<ul style="color: #155724;">';
    echo '<li>Your token is valid and not expired</li>';
    echo '<li>The user associated with the token has proper permissions</li>';
    echo '<li>The token is assigned to the correct service</li>';
    echo '</ul>';
    echo '</div>';
}

// Token verification section
echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>🔍 Token Verification</h3>';
echo '<p>Your API URL uses token: <code>2801e2d525ae404083d139035705441e</code></p>';

// Check if this specific token exists
$token_record = $DB->get_record('external_tokens', ['token' => '2801e2d525ae404083d139035705441e']);
if ($token_record) {
    echo '<div style="color: green; margin: 10px 0;">✅ Token exists in database</div>';
    
    // Check token service assignment
    if ($service && $token_record->externalserviceid == $service->id) {
        echo '<div style="color: green; margin: 10px 0;">✅ Token is assigned to ALX Report API service</div>';
    } else {
        echo '<div style="color: red; margin: 10px 0;">❌ Token is not assigned to ALX Report API service</div>';
        echo '<div style="color: orange; margin: 10px 0;">Current service ID: ' . $token_record->externalserviceid . ', Expected: ' . ($service ? $service->id : 'N/A') . '</div>';
    }
    
    // Check token expiration
    if ($token_record->validuntil == 0 || $token_record->validuntil > time()) {
        echo '<div style="color: green; margin: 10px 0;">✅ Token is not expired</div>';
    } else {
        echo '<div style="color: red; margin: 10px 0;">❌ Token has expired</div>';
    }
    
    // Show user info
    $user = $DB->get_record('user', ['id' => $token_record->userid], 'id, username, firstname, lastname');
    if ($user) {
        echo '<div style="margin: 10px 0;">Token belongs to user: <strong>' . $user->firstname . ' ' . $user->lastname . ' (' . $user->username . ')</strong></div>';
    }
    
} else {
    echo '<div style="color: red; margin: 10px 0;">❌ Token not found in database</div>';
    echo '<div style="color: orange; margin: 10px 0;">You may need to create a new token or check if the token is correct</div>';
}

echo '</div>';

// Quick links
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>🔗 Quick Links for Manual Configuration</h3>';
echo '<ul>';
echo '<li><a href="' . $CFG->wwwroot . '/admin/settings.php?section=webservicesoverview" target="_blank">Web Services Overview</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/admin/webservice/service.php" target="_blank">External Services</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/admin/webservice/tokens.php" target="_blank">Manage Tokens</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/local/alx_report_api/control_center.php" target="_blank">ALX API Control Center</a></li>';
echo '</ul>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
?>