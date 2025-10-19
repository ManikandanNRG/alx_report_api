<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Test page to view and process unresolved alerts
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/test_alert_processing.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Alert Processing Test');
$PAGE->set_heading('Alert Processing Test');

// Process form submission
$process = optional_param('process', 0, PARAM_INT);
$processed = false;
$error_message = '';
$output = '';

if ($process && confirm_sesskey()) {
    try {
        ob_start();
        local_alx_report_api_check_and_alert();
        $output = ob_get_clean();
        $processed = true;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

echo $OUTPUT->header();

// Get current settings
$alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
$email_alerts_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
$alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
$alert_emails = get_config('local_alx_report_api', 'alert_emails');
$alert_cooldown = get_config('local_alx_report_api', 'alert_cooldown') ?: 60;

echo '<style>
.alert-test-container {
    max-width: 1200px;
    margin: 20px auto;
}
.settings-box, .alerts-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.settings-box h3, .alerts-box h3 {
    margin-top: 0;
    color: #333;
}
.setting-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.setting-label {
    font-weight: bold;
    color: #555;
}
.setting-value {
    color: #333;
}
.status-enabled {
    color: #28a745;
    font-weight: bold;
}
.status-disabled {
    color: #dc3545;
    font-weight: bold;
}
.alert-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}
.alert-severity {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
    text-transform: uppercase;
}
.severity-low { background: #17a2b8; color: white; }
.severity-medium { background: #ffc107; color: black; }
.severity-high { background: #fd7e14; color: white; }
.severity-critical { background: #dc3545; color: white; }
.process-btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
}
.process-btn:hover {
    background: #0056b3;
}
.success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.error-message {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.output-box {
    background: #f4f4f4;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
    font-family: monospace;
    white-space: pre-wrap;
    margin-top: 10px;
}
</style>';

echo '<div class="alert-test-container">';
echo '<h2>🔔 Email Alert System Test</h2>';

if ($processed && empty($error_message)) {
    echo '<div class="success-message">';
    echo '✅ <strong>Alert processing completed successfully!</strong>';
    if (!empty($output)) {
        echo '<div class="output-box">' . htmlspecialchars($output) . '</div>';
    }
    echo '</div>';
}

if (!empty($error_message)) {
    echo '<div class="error-message">';
    echo '❌ <strong>Error:</strong> ' . htmlspecialchars($error_message);
    echo '</div>';
}

echo '<div class="settings-box">';
echo '<h3>⚙️ Current Alert Settings</h3>';

echo '<div class="setting-row">';
echo '<span class="setting-label">Alerting System:</span>';
echo '<span class="setting-value ' . ($alerting_enabled ? 'status-enabled' : 'status-disabled') . '">';
echo $alerting_enabled ? '✅ ENABLED' : '❌ DISABLED';
echo '</span></div>';

echo '<div class="setting-row">';
echo '<span class="setting-label">Email Alerts:</span>';
echo '<span class="setting-value ' . ($email_alerts_enabled ? 'status-enabled' : 'status-disabled') . '">';
echo $email_alerts_enabled ? '✅ ENABLED' : '❌ DISABLED';
echo '</span></div>';

echo '<div class="setting-row">';
echo '<span class="setting-label">Alert Threshold:</span>';
echo '<span class="setting-value">' . strtoupper($alert_threshold) . '</span>';
echo '</div>';

echo '<div class="setting-row">';
echo '<span class="setting-label">Recipients:</span>';
echo '<span class="setting-value">' . ($alert_emails ?: '❌ NOT CONFIGURED') . '</span>';
echo '</div>';

echo '<div class="setting-row">';
echo '<span class="setting-label">Cooldown Period:</span>';
echo '<span class="setting-value">' . $alert_cooldown . ' minutes</span>';
echo '</div>';

echo '</div>'; // settings-box

echo '<div class="alerts-box">';
echo '<h3>📋 Unresolved Alerts</h3>';

// Get unresolved alerts
$table_name = \local_alx_report_api\constants::TABLE_ALERTS;
$unresolved_alerts = $DB->get_records($table_name, ['resolved' => 0], 'timecreated DESC');

if (empty($unresolved_alerts)) {
    echo '<p>✅ No unresolved alerts found.</p>';
    echo '<p><em>To test the system, trigger a rate limit violation by making API calls exceeding the daily limit.</em></p>';
} else {
    echo '<p>Found <strong>' . count($unresolved_alerts) . '</strong> unresolved alert(s):</p>';
    
    foreach ($unresolved_alerts as $alert) {
        $age_minutes = floor((time() - $alert->timecreated) / 60);
        $age_display = $age_minutes < 60 ? "$age_minutes minutes ago" : floor($age_minutes / 60) . " hours ago";
        
        echo '<div class="alert-item">';
        echo '<div><span class="alert-severity severity-' . $alert->severity . '">' . $alert->severity . '</span></div>';
        echo '<div style="margin-top: 10px;"><strong>Type:</strong> ' . htmlspecialchars($alert->alert_type) . '</div>';
        echo '<div><strong>Message:</strong> ' . htmlspecialchars($alert->message) . '</div>';
        echo '<div><strong>Hostname:</strong> ' . htmlspecialchars($alert->hostname) . '</div>';
        echo '<div><strong>Created:</strong> ' . date('Y-m-d H:i:s', $alert->timecreated) . ' (' . $age_display . ')</div>';
        echo '<div><strong>Alert ID:</strong> ' . $alert->id . '</div>';
        echo '</div>';
    }
    
    // Show process button
    if ($alerting_enabled && $email_alerts_enabled && !empty($alert_emails)) {
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="process" value="1">';
        echo '<button type="submit" class="process-btn">🚀 Process Alerts & Send Emails</button>';
        echo '</form>';
        echo '<p style="margin-top: 10px; color: #666;"><em>This will send emails to: ' . htmlspecialchars($alert_emails) . '</em></p>';
    } else {
        echo '<div class="error-message" style="margin-top: 20px;">';
        echo '❌ Cannot process alerts. Please check:';
        echo '<ul>';
        if (!$alerting_enabled) {
            echo '<li>Alerting system is disabled</li>';
        }
        if (!$email_alerts_enabled) {
            echo '<li>Email alerts are disabled</li>';
        }
        if (empty($alert_emails)) {
            echo '<li>No recipients configured</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

echo '</div>'; // alerts-box

echo '<div class="settings-box">';
echo '<h3>ℹ️ How It Works</h3>';
echo '<ol>';
echo '<li>When rate limits are exceeded, alerts are created in the database</li>';
echo '<li>The scheduled task runs every 15 minutes to check for unresolved alerts</li>';
echo '<li>Emails are sent to configured recipients for alerts matching the severity threshold</li>';
echo '<li>After sending, alerts are marked as resolved</li>';
echo '<li>Cooldown period prevents duplicate emails for the same alert type</li>';
echo '</ol>';
echo '<p><strong>Scheduled Task:</strong> The task runs automatically every 15 minutes, or you can trigger it manually using the button above.</p>';
echo '</div>';

echo '</div>'; // alert-test-container

echo $OUTPUT->footer();
