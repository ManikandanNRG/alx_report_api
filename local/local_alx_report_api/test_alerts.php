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
 * ALX Report API Alert Testing Page
 * 
 * Allows administrators to test the alerting system configuration.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login and use proper admin page setup
admin_externalpage_setup('local_alx_report_api_test_alerts');

// Page setup
$PAGE->set_url('/local/alx_report_api/test_alerts.php');
$PAGE->set_title('ALX Report API - Test Alerts');
$PAGE->set_heading('Test Alert System');
$PAGE->set_pagelayout('admin');

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$alert_type = optional_param('alert_type', 'health', PARAM_ALPHA);
$severity = optional_param('severity', 'medium', PARAM_ALPHA);

$message = '';
$message_type = '';

if ($action === 'send_test') {
    // Validate CSRF token
    require_sesskey();
    
    // Send test alert
    $test_data = [
        'test_mode' => true,
        'admin_user' => fullname($USER),
        'test_time' => date('Y-m-d H:i:s'),
        'server_info' => php_uname(),
        'moodle_version' => $CFG->version
    ];
    
    $test_message = "This is a test alert from ALX Report API monitoring system. If you received this, your alert configuration is working correctly!";
    
    $success = local_alx_report_api_send_alert(
        $alert_type,
        $severity,
        $test_message,
        $test_data
    );
    
    if ($success) {
        $message = "✅ Test alert sent successfully! Check your email inbox.";
        $message_type = 'success';
    } else {
        $message = "❌ Failed to send test alert. Check your configuration and try again.";
        $message_type = 'error';
        
        // Check if alerting is disabled
        $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
        if (!$alerting_enabled) {
            $message .= " (Alerting is currently disabled in settings)";
        }
    }
}

echo $OUTPUT->header();

?>

<style>
.test-alerts-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
}

.alert-form {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid;
}

.alert-success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

.config-status {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.config-item:last-child {
    border-bottom: none;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-enabled {
    background: #d4edda;
    color: #155724;
}

.status-disabled {
    background: #f8d7da;
    color: #721c24;
}

.help-text {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.info-box {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border: 1px solid #2196f3;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.info-box h3 {
    margin: 0 0 10px 0;
    color: #1565c0;
}
</style>

<div class="test-alerts-container">
    <div class="info-box">
        <h3>🧪 Alert System Testing</h3>
        <p>Use this page to test your alert configuration. A test alert will be sent to all configured recipients using the selected alert type and severity level.</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- Current Configuration Status -->
    <div class="config-status">
        <h3>📋 Current Alert Configuration</h3>
        
        <?php
        $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
        $email_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
        $sms_enabled = get_config('local_alx_report_api', 'enable_sms_alerts');
        $alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
        $alert_emails = get_config('local_alx_report_api', 'alert_emails');
        $sms_service = get_config('local_alx_report_api', 'sms_service') ?: 'disabled';
        ?>
        
        <div class="config-item">
            <span><strong>Alert System:</strong></span>
            <span class="status-badge <?php echo $alerting_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                <?php echo $alerting_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
        </div>
        
        <div class="config-item">
            <span><strong>Email Alerts:</strong></span>
            <span class="status-badge <?php echo $email_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                <?php echo $email_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
        </div>
        
        <div class="config-item">
            <span><strong>SMS Alerts:</strong></span>
            <span class="status-badge <?php echo $sms_enabled && $sms_service !== 'disabled' ? 'status-enabled' : 'status-disabled'; ?>">
                <?php echo $sms_enabled && $sms_service !== 'disabled' ? 'Enabled' : 'Disabled'; ?>
            </span>
        </div>
        
        <div class="config-item">
            <span><strong>Alert Threshold:</strong></span>
            <span><?php echo ucfirst($alert_threshold); ?></span>
        </div>
        
        <div class="config-item">
            <span><strong>Email Recipients:</strong></span>
            <span><?php echo $alert_emails ? count(explode(',', $alert_emails)) . ' configured' : 'None configured'; ?></span>
        </div>
        
        <div class="config-item">
            <span><strong>SMS Service:</strong></span>
            <span><?php echo ucfirst(str_replace('_', ' ', $sms_service)); ?></span>
        </div>
    </div>

    <!-- Test Form -->
    <div class="alert-form">
        <h3>🚀 Send Test Alert</h3>
        
        <form method="post" action="">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="send_test">
            
            <div class="form-group">
                <label for="alert_type">Alert Type:</label>
                <select name="alert_type" id="alert_type" class="form-control">
                    <option value="health" <?php echo $alert_type === 'health' ? 'selected' : ''; ?>>System Health</option>
                    <option value="performance" <?php echo $alert_type === 'performance' ? 'selected' : ''; ?>>Performance</option>
                    <option value="security" <?php echo $alert_type === 'security' ? 'selected' : ''; ?>>Security</option>
                    <option value="rate_limit" <?php echo $alert_type === 'rate_limit' ? 'selected' : ''; ?>>Rate Limiting</option>
                </select>
                <div class="help-text">Select the type of alert to test</div>
            </div>
            
            <div class="form-group">
                <label for="severity">Severity Level:</label>
                <select name="severity" id="severity" class="form-control">
                    <option value="low" <?php echo $severity === 'low' ? 'selected' : ''; ?>>🔵 Low</option>
                    <option value="medium" <?php echo $severity === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                    <option value="high" <?php echo $severity === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                    <option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                </select>
                <div class="help-text">Choose severity level (affects who receives the alert and formatting)</div>
            </div>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <button type="submit" class="btn btn-primary">
                    📧 Send Test Alert
                </button>
                
                <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api_settings" class="btn btn-secondary">
                    ⚙️ Configure Settings
                </a>
                
                <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php" class="btn btn-secondary">
                    🎛️ Control Center
                </a>
            </div>
        </form>
    </div>

    <!-- Alert Examples -->
    <div class="config-status">
        <h3>📋 Alert Examples</h3>
        <p style="margin-bottom: 15px;">Here's what different types of alerts look like:</p>
        
        <div style="margin-bottom: 15px;">
            <strong>🔴 Critical Health Alert:</strong><br>
            <span style="color: #666;">Subject: [ALX Report API] 🔴 Critical Alert: Health</span><br>
            <span style="color: #666;">Message: "System health critical (Score: 45/100)"</span>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>🟠 High Security Alert:</strong><br>
            <span style="color: #666;">Subject: [ALX Report API] 🟠 High Alert: Security</span><br>
            <span style="color: #666;">Message: "User John Doe accessing 5 companies (87 requests)"</span>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>🟡 Medium Performance Alert:</strong><br>
            <span style="color: #666;">Subject: [ALX Report API] 🟡 Medium Alert: Performance</span><br>
            <span style="color: #666;">Message: "High API usage detected: 250 calls in the last hour"</span>
        </div>
    </div>

    <!-- Recipients Information -->
    <?php if ($alert_emails || $alerting_enabled): ?>
    <div class="config-status">
        <h3>📧 Alert Recipients</h3>
        
        <?php if ($alert_emails): ?>
        <div style="margin-bottom: 15px;">
            <strong>Configured Email Recipients:</strong><br>
            <?php 
            $emails = array_filter(array_map('trim', explode(',', $alert_emails)));
            foreach ($emails as $email) {
                echo "<span style='background: #e9ecef; padding: 2px 8px; border-radius: 12px; margin: 2px; display: inline-block;'>{$email}</span> ";
            }
            ?>
        </div>
        <?php endif; ?>
        
        <div>
            <strong>Site Administrators (for critical alerts):</strong><br>
            <?php 
            $admins = get_admins();
            foreach ($admins as $admin) {
                echo "<span style='background: #d4edda; padding: 2px 8px; border-radius: 12px; margin: 2px; display: inline-block;'>" . fullname($admin) . " ({$admin->email})</span> ";
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
?> 