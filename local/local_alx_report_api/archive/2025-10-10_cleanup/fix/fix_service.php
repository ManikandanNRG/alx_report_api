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
 * ALX Report API Service Fix Utility
 * 
 * Creates or fixes the ALX Report API web service configuration.
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
$PAGE->set_url('/local/alx_report_api/fix_service.php');
$PAGE->set_title('ALX Report API - Service Fix');
$PAGE->set_heading('Fix ALX Report API Service');

$action = optional_param('action', '', PARAM_ALPHA);
$fixed = false;
$messages = [];

if ($action === 'fix') {
    require_sesskey();
    
    try {
        // 1. Ensure web services are enabled
        if (!get_config('moodle', 'enablewebservices')) {
            set_config('enablewebservices', 1);
            $messages[] = "✅ Enabled web services";
        }
        
        // 2. Ensure REST protocol is enabled
        $enabledprotocols = get_config('moodle', 'webserviceprotocols');
        if (strpos($enabledprotocols, 'rest') === false) {
            if (empty($enabledprotocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $enabledprotocols . ',rest');
            }
            $messages[] = "✅ Enabled REST protocol";
        }
        
        // 3. Check if service exists
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        
        if (!$service) {
            // Create new service
            $service = new stdClass();
            $service->name = 'ALX Report API Service';
            $service->shortname = 'alx_report_api_custom';
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timecreated = time();
            $service->timemodified = time();
            
            $serviceid = $DB->insert_record('external_services', $service);
            $messages[] = "✅ Created ALX Report API service (ID: {$serviceid})";
            
            // Add function to service
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $function);
            $messages[] = "✅ Added function to service";
            
        } else {
            // Update existing service
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            $messages[] = "✅ Updated existing service (ID: {$service->id})";
            
            // Ensure function is added
            $function_exists = $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$function_exists) {
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->insert_record('external_services_functions', $function);
                $messages[] = "✅ Added missing function to service";
            }
        }
        
        // 4. Clear caches
        cache_helper::purge_all();
        $messages[] = "✅ Cleared all caches";
        
        $fixed = true;
        
    } catch (Exception $e) {
        $messages[] = "❌ Error: " . $e->getMessage();
    }
}

echo $OUTPUT->header();

?>

<style>
.fix-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
}

.fix-header {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.fix-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
}

.status-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 600;
    color: #333;
}

.status-value {
    font-weight: 500;
}

.status-ok { color: #28a745; }
.status-error { color: #dc3545; }
.status-warning { color: #ffc107; }

.fix-button {
    background: #dc3545;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.fix-button:hover {
    background: #c82333;
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

.fix-button:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.messages {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.messages ul {
    margin: 0;
    padding-left: 20px;
}

.back-link {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 500;
    margin-right: 10px;
}

.back-link:hover {
    background: #545b62;
    text-decoration: none;
    color: white;
}
</style>

<div class="fix-container">
    <div class="fix-header">
        <h1>🔧 Service Fix Utility</h1>
        <p>Diagnose and fix ALX Report API service configuration issues</p>
    </div>

    <?php if (!empty($messages)): ?>
    <div class="messages">
        <h4><?php echo $fixed ? '✅ Fix Applied Successfully!' : '⚠️ Fix Results'; ?></h4>
        <ul>
            <?php foreach ($messages as $message): ?>
                <li><?php echo htmlspecialchars($message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="status-card">
        <h3>🔍 Current Service Status</h3>
        
        <?php
        // Check current status
        $webservices_enabled = get_config('moodle', 'enablewebservices');
        $rest_enabled = strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false;
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        $legacy_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        
        $has_function = false;
        $active_tokens = 0;
        
        if ($service) {
            $has_function = $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            $active_tokens = $DB->count_records_select('external_tokens', 
                'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
                [$service->id, time()]
            );
        }
        ?>
        
        <div class="status-item">
            <span class="status-label">Web Services:</span>
            <span class="status-value <?php echo $webservices_enabled ? 'status-ok' : 'status-error'; ?>">
                <?php echo $webservices_enabled ? '✅ Enabled' : '❌ Disabled'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="status-label">REST Protocol:</span>
            <span class="status-value <?php echo $rest_enabled ? 'status-ok' : 'status-error'; ?>">
                <?php echo $rest_enabled ? '✅ Enabled' : '❌ Disabled'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="status-label">ALX Report API Service:</span>
            <span class="status-value <?php echo ($service && $service->enabled) ? 'status-ok' : 'status-error'; ?>">
                <?php 
                if ($service && $service->enabled) {
                    echo "✅ Active (ID: {$service->id})";
                } elseif ($service && !$service->enabled) {
                    echo "⚠️ Exists but Disabled (ID: {$service->id})";
                } else {
                    echo "❌ Not Found";
                }
                ?>
            </span>
        </div>
        
        <?php if ($legacy_service): ?>
        <div class="status-item">
            <span class="status-label">Legacy Service:</span>
            <span class="status-value status-warning">
                ⚠️ Found legacy service "alx_report_api" (ID: <?php echo $legacy_service->id; ?>)
            </span>
        </div>
        <?php endif; ?>
        
        <div class="status-item">
            <span class="status-label">Service Function:</span>
            <span class="status-value <?php echo $has_function ? 'status-ok' : 'status-error'; ?>">
                <?php echo $has_function ? '✅ Configured' : '❌ Missing'; ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="status-label">Active Tokens:</span>
            <span class="status-value <?php echo $active_tokens > 0 ? 'status-ok' : 'status-warning'; ?>">
                <?php echo $active_tokens > 0 ? "✅ {$active_tokens} tokens" : '⚠️ No tokens'; ?>
            </span>
        </div>
    </div>

    <?php 
    $needs_fix = !$webservices_enabled || !$rest_enabled || !$service || !$service->enabled || !$has_function;
    ?>

    <div style="text-align: center; margin-top: 30px;">
        <?php if ($needs_fix && !$fixed): ?>
            <form method="post" action="" style="display: inline;">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="fix">
                <button type="submit" class="fix-button">
                    🔧 Fix Service Configuration
                </button>
            </form>
        <?php elseif ($fixed): ?>
            <button class="fix-button" disabled>
                ✅ Service Fixed Successfully
            </button>
        <?php else: ?>
            <button class="fix-button" disabled>
                ✅ Service Configuration OK
            </button>
        <?php endif; ?>
        
        <br><br>
        
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php" class="back-link">
            🎛️ Back to Control Center
        </a>
        
        <a href="<?php echo $CFG->wwwroot; ?>/admin/webservice/tokens.php" class="back-link">
            🔑 Manage Tokens
        </a>
        
        <a href="<?php echo $CFG->wwwroot; ?>/admin/webservice/service.php" class="back-link">
            ⚙️ Web Services
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?> 