<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Fix Critical Issues Found in Token Functionality Test
 *
 * This script addresses the critical issues found in the test report:
 * 1. Missing fallback service 'alx_report_api'
 * 2. No active tokens for testing
 * 3. Service configuration issues
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/fix_test_issues.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Fix Critical Issues');
$PAGE->set_heading('Fix Critical Issues');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

?>

<style>
.fix-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
}

.issue-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.critical-issue {
    border-left: 4px solid #dc3545;
    background: #f8d7da;
    color: #721c24;
}

.warning-issue {
    border-left: 4px solid #ffc107;
    background: #fff3cd;
    color: #856404;
}

.success-fix {
    border-left: 4px solid #28a745;
    background: #d4edda;
    color: #155724;
}

.fix-action {
    background: #007bff;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin: 10px 5px;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.fix-action:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.fix-action.danger {
    background: #dc3545;
}

.fix-action.danger:hover {
    background: #c82333;
}

.code-block {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 15px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    margin: 10px 0;
}
</style>

<div class="fix-container">
    <h1>🔧 Fix Critical Issues</h1>
    <p>This script will address the critical issues found in the Token Functionality Test.</p>

    <?php
    
    // Process actions
    $message = '';
    $message_type = '';
    
    if ($action === 'create_fallback_service') {
        require_sesskey();
        
        try {
            // Create fallback service 'alx_report_api'
            $service = new stdClass();
            $service->name = 'ALX Report API (Legacy)';
            $service->shortname = 'alx_report_api';
            $service->component = 'local_alx_report_api';
            $service->timecreated = time();
            $service->timemodified = time();
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            
            $service_id = $DB->insert_record('external_services', $service);
            
            if ($service_id) {
                // Add the function to the service
                $function = new stdClass();
                $function->externalserviceid = $service_id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                
                $DB->insert_record('external_services_functions', $function);
                
                $message = "✅ Successfully created fallback service 'alx_report_api' (ID: {$service_id})";
                $message_type = 'success';
            } else {
                $message = "❌ Failed to create fallback service";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = "❌ Error creating fallback service: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    if ($action === 'create_test_token') {
        require_sesskey();
        
        try {
            // Get available service
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
            if (!$service) {
                $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
            }
            
            if ($service) {
                // Create a test token for the admin user
                $token = new stdClass();
                $token->token = md5(uniqid(rand(), true));
                $token->userid = $USER->id;
                $token->externalserviceid = $service->id;
                $token->contextid = context_system::instance()->id;
                $token->creatorid = $USER->id;
                $token->iprestriction = '';
                $token->validuntil = null; // Never expires
                $token->timecreated = time();
                $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
                $token->name = 'Test Token - ' . date('Y-m-d H:i:s');
                
                $token_id = $DB->insert_record('external_tokens', $token);
                
                if ($token_id) {
                    $message = "✅ Successfully created test token: " . $token->token;
                    $message_type = 'success';
                } else {
                    $message = "❌ Failed to create test token";
                    $message_type = 'error';
                }
            } else {
                $message = "❌ No service found to create token for";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = "❌ Error creating test token: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    if ($action === 'run_diagnostics') {
        require_sesskey();
        
        // Run comprehensive diagnostics
        echo '<div class="issue-section">';
        echo '<h2>🔍 System Diagnostics</h2>';
        
        // Check services
        $service_custom = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        $service_legacy = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        
        echo '<h3>Service Status:</h3>';
        if ($service_custom) {
            echo "<p>✅ Primary service 'alx_report_api_custom' exists (ID: {$service_custom->id})</p>";
        } else {
            echo "<p>❌ Primary service 'alx_report_api_custom' missing</p>";
        }
        
        if ($service_legacy) {
            echo "<p>✅ Fallback service 'alx_report_api' exists (ID: {$service_legacy->id})</p>";
        } else {
            echo "<p>❌ Fallback service 'alx_report_api' missing</p>";
        }
        
        // Check tokens
        $active_service = $service_custom ?: $service_legacy;
        if ($active_service) {
            $token_count = $DB->count_records_select('external_tokens', 
                'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
                [$active_service->id, time()]
            );
            
            echo "<h3>Token Status:</h3>";
            echo "<p>Active tokens: {$token_count}</p>";
            
            if ($token_count > 0) {
                $tokens = $DB->get_records_select('external_tokens', 
                    'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
                    [$active_service->id, time()], 
                    '', 'token, userid, name', 0, 3);
                
                echo "<h4>Sample Tokens:</h4>";
                foreach ($tokens as $token) {
                    $user = $DB->get_record('user', ['id' => $token->userid], 'firstname, lastname');
                    $username = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown';
                    echo "<p>• " . substr($token->token, 0, 8) . "... (User: {$username})</p>";
                }
            }
        }
        
        // Check web services
        echo "<h3>Web Services Status:</h3>";
        if (!empty($CFG->enablewebservices)) {
            echo "<p>✅ Web services enabled globally</p>";
        } else {
            echo "<p>❌ Web services disabled globally</p>";
        }
        
        // Check functions
        if ($active_service) {
            $functions = $DB->get_records('external_services_functions', 
                ['externalserviceid' => $active_service->id]);
            
            echo "<h3>Available Functions:</h3>";
            foreach ($functions as $func) {
                echo "<p>• {$func->functionname}</p>";
            }
        }
        
        echo '</div>';
    }
    
    // Display message if any
    if ($message) {
        $class = $message_type === 'success' ? 'success-fix' : 'critical-issue';
        echo "<div class=\"issue-section {$class}\">";
        echo "<p>{$message}</p>";
        echo "</div>";
    }
    
    // Current Issues Analysis
    echo '<div class="issue-section critical-issue">';
    echo '<h2>🚨 Critical Issues Found</h2>';
    echo '<p><strong>From Token Functionality Test Report:</strong></p>';
    echo '<ul>';
    echo '<li><strong>Service alx_report_api exists:</strong> No fallback service found</li>';
    echo '<li><strong>Token validation test:</strong> No active tokens found for testing</li>';
    echo '<li><strong>API call test:</strong> Skipped - no active tokens available</li>';
    echo '</ul>';
    echo '</div>';
    
    // Check current service status
    $service_custom = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    $service_legacy = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
    
    echo '<div class="issue-section">';
    echo '<h2>📊 Current System Status</h2>';
    
    echo '<h3>Service Configuration:</h3>';
    if ($service_custom) {
        echo "<p>✅ Primary service 'alx_report_api_custom' exists (ID: {$service_custom->id})</p>";
        
        // Count tokens for primary service
        $token_count_custom = $DB->count_records_select('external_tokens', 
            'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
            [$service_custom->id, time()]
        );
        echo "<p>• Active tokens: {$token_count_custom}</p>";
    } else {
        echo "<p>❌ Primary service 'alx_report_api_custom' missing</p>";
    }
    
    if ($service_legacy) {
        echo "<p>✅ Fallback service 'alx_report_api' exists (ID: {$service_legacy->id})</p>";
        
        // Count tokens for legacy service
        $token_count_legacy = $DB->count_records_select('external_tokens', 
            'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
            [$service_legacy->id, time()]
        );
        echo "<p>• Active tokens: {$token_count_legacy}</p>";
    } else {
        echo "<p>❌ Fallback service 'alx_report_api' missing</p>";
    }
    
    echo '</div>';
    
    // Fix Actions
    echo '<div class="issue-section">';
    echo '<h2>🔧 Recommended Fix Actions</h2>';
    
    if (!$service_legacy) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<h3>1. Create Missing Fallback Service</h3>';
        echo '<p>The fallback service "alx_report_api" is missing. This is critical for backward compatibility.</p>';
        echo '<a href="?action=create_fallback_service&sesskey=' . sesskey() . '" class="fix-action">';
        echo '🛠️ Create Fallback Service';
        echo '</a>';
        echo '</div>';
    }
    
    $active_service = $service_custom ?: $service_legacy;
    $total_tokens = 0;
    if ($active_service) {
        $total_tokens = $DB->count_records_select('external_tokens', 
            'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
            [$active_service->id, time()]
        );
    }
    
    if ($total_tokens === 0) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<h3>2. Create Test Token</h3>';
        echo '<p>No active tokens found for testing. Create a test token to validate functionality.</p>';
        echo '<a href="?action=create_test_token&sesskey=' . sesskey() . '" class="fix-action">';
        echo '🔑 Create Test Token';
        echo '</a>';
        echo '</div>';
    }
    
    echo '<div style="margin-bottom: 20px;">';
    echo '<h3>3. Run Full Diagnostics</h3>';
    echo '<p>Get detailed information about the current system configuration.</p>';
    echo '<a href="?action=run_diagnostics&sesskey=' . sesskey() . '" class="fix-action">';
    echo '🔍 Run Diagnostics';
    echo '</a>';
    echo '</div>';
    
    echo '</div>';
    
    // Manual Instructions
    echo '<div class="issue-section warning-issue">';
    echo '<h2>📝 Manual Setup Instructions</h2>';
    echo '<p>If automated fixes don\'t work, follow these manual steps:</p>';
    
    echo '<h3>Create Service Manually:</h3>';
    echo '<ol>';
    echo '<li>Go to Site Administration → Server → Web Services → External Services</li>';
    echo '<li>Click "Add" to create a new service</li>';
    echo '<li>Set name: "ALX Report API (Legacy)"</li>';
    echo '<li>Set short name: "alx_report_api"</li>';
    echo '<li>Enable the service</li>';
    echo '<li>Add function: "local_alx_report_api_get_course_progress"</li>';
    echo '</ol>';
    
    echo '<h3>Create Token Manually:</h3>';
    echo '<ol>';
    echo '<li>Go to Site Administration → Server → Web Services → Manage Tokens</li>';
    echo '<li>Click "Create Token"</li>';
    echo '<li>Select a user (admin recommended for testing)</li>';
    echo '<li>Select service: "ALX Report API" or "alx_report_api_custom"</li>';
    echo '<li>Save the token</li>';
    echo '</ol>';
    
    echo '</div>';
    
    // Test Again
    echo '<div class="issue-section success-fix">';
    echo '<h2>🧪 Test Again</h2>';
    echo '<p>After applying fixes, run the token functionality test again to verify everything works.</p>';
    echo '<a href="test_token_functionality.php" class="fix-action">';
    echo '🔄 Run Token Functionality Test';
    echo '</a>';
    echo '</div>';
    
    ?>
    
    <div class="issue-section">
        <h2>🔗 Additional Tools</h2>
        <p>Access other management tools:</p>
        
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php" class="fix-action">
            🎛️ Control Center
        </a>
        
        <a href="<?php echo $CFG->wwwroot; ?>/admin/webservice/service.php" class="fix-action">
            ⚙️ Manage Web Services
        </a>
        
        <a href="<?php echo $CFG->wwwroot; ?>/admin/webservice/tokens.php" class="fix-action">
            🔑 Manage Tokens
        </a>
    </div>
</div>

<?php

echo $OUTPUT->footer();
?> 