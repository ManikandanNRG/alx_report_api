<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Test email alert functionality
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use local_alx_report_api\constants;

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/test_email_alert.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test Email Alert');
$PAGE->set_heading('Test Email Alert System');

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
echo '<h2>🧪 Email Alert System Test</h2>';

// Check if alerting is enabled
$alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
$alert_emails = get_config('local_alx_report_api', 'alert_emails');

if (!$alerting_enabled) {
    echo '<div class="alert alert-danger">
        <strong>❌ Alert System Disabled</strong><br>
        Please enable the alert system in Control Center → System Configuration
    </div>';
} else if (empty($alert_emails)) {
    echo '<div class="alert alert-warning">
        <strong>⚠️ No Recipients Configured</strong><br>
        Please add email recipients in Control Center → System Configuration
    </div>';
} else {
    echo '<div class="alert alert-success">
        <strong>✅ Alert System Enabled</strong><br>
        Recipients: ' . htmlspecialchars($alert_emails) . '
    </div>';
}

// Test button clicked
if (optional_param('send_test', false, PARAM_BOOL) && confirm_sesskey()) {
    echo '<h3>📧 Sending Test Alert...</h3>';
    
    // For manual tests, bypass cooldown by sending directly
    // Get recipients
    $recipients = local_alx_report_api_get_alert_recipients('performance', 'medium');
    
    // Debug: Show what we're working with
    echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #2196F3;">
        <h4 style="margin-top: 0;">🔍 Debug Information:</h4>
        <ul>
            <li><strong>Alert System Enabled:</strong> ' . (get_config('local_alx_report_api', 'enable_alerting') ? 'YES' : 'NO') . '</li>
            <li><strong>Email Alerts Enabled:</strong> ' . (get_config('local_alx_report_api', 'enable_email_alerts') ? 'YES' : 'NO') . '</li>
            <li><strong>Recipients Found:</strong> ' . count($recipients) . '</li>';
    
    if (!empty($recipients)) {
        echo '<li><strong>Recipient Emails:</strong><ul>';
        foreach ($recipients as $r) {
            echo '<li>' . htmlspecialchars($r['email']) . '</li>';
        }
        echo '</ul></li>';
    }
    echo '</ul></div>';
    
    if (empty($recipients)) {
        echo '<div class="alert alert-danger">
            <strong>❌ No Recipients Found</strong><br>
            Please add email recipients in Control Center → System Configuration
        </div>';
        $result = false;
    } else {
        // Prepare alert data
        $alert = [
            'type' => 'performance',
            'severity' => 'medium',
            'message' => 'This is a TEST alert from ALX Report API. If you receive this email, the alert system is working correctly!',
            'data' => [
                'test_type' => 'Manual test',
                'triggered_by' => fullname($USER),
                'timestamp' => date('Y-m-d H:i:s'),
                'system' => $CFG->wwwroot
            ],
            'timestamp' => time(),
            'hostname' => $CFG->wwwroot,
            'plugin' => 'ALX Report API'
        ];
        
        // Log the alert
        local_alx_report_api_log_alert($alert);
        
        // Send email directly (bypass cooldown for manual tests)
        $result = true;
        $email_results = [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $email_sent = local_alx_report_api_send_email_alert($recipient, $alert);
                $email_results[] = [
                    'email' => $recipient['email'],
                    'success' => $email_sent
                ];
                if (!$email_sent) {
                    $result = false;
                }
            }
        }
        
        // Show detailed results
        echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107;">
            <h4 style="margin-top: 0;">📧 Email Sending Results:</h4>
            <ul>';
        foreach ($email_results as $er) {
            $status = $er['success'] ? '✅ Sent' : '❌ Failed';
            echo '<li>' . htmlspecialchars($er['email']) . ': ' . $status . '</li>';
        }
        echo '</ul></div>';
    }
    
    if ($result) {
        echo '<div class="alert alert-success">
            <strong>✅ Test Alert Sent Successfully!</strong><br>
            Check your email inbox (and spam folder) for the test alert.<br><br>
            <strong>Recipients:</strong> ' . htmlspecialchars($alert_emails) . '<br>
            <strong>Subject:</strong> [ALX Report API] 🟡 Medium Alert: Performance<br>
            <strong>Time:</strong> ' . date('Y-m-d H:i:s') . '
        </div>';
        
        // Show email details
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4>📋 Email Details:</h4>
            <ul>
                <li><strong>From:</strong> ' . htmlspecialchars($CFG->noreplyaddress ?: 'noreply@' . parse_url($CFG->wwwroot, PHP_URL_HOST)) . '</li>
                <li><strong>To:</strong> ' . htmlspecialchars($alert_emails) . '</li>
                <li><strong>Format:</strong> HTML</li>
                <li><strong>Color:</strong> Yellow (Medium severity)</li>
            </ul>
        </div>';
        
        // Troubleshooting tips
        echo '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
            <h4>📬 If you don\'t receive the email:</h4>
            <ol>
                <li><strong>Check spam/junk folder</strong> - Automated emails often go there</li>
                <li><strong>Verify email address</strong> - Make sure it\'s correct in System Configuration</li>
                <li><strong>Check Moodle email settings</strong> - Go to Site Administration → Server → Email → Outgoing mail configuration</li>
                <li><strong>Check email logs</strong> - Look in Moodle logs for email sending errors</li>
                <li><strong>Test Moodle email</strong> - Try sending a test email from Site Administration → Server → Email → Test outgoing mail configuration</li>
            </ol>
        </div>';
        
    } else {
        echo '<div class="alert alert-danger">
            <strong>❌ Failed to Send Test Alert</strong><br>
            Possible reasons:<br>
            <ul>
                <li>Alert system is disabled</li>
                <li>No recipients configured</li>
                <li>Alert is in cooldown period (wait 60 minutes)</li>
                <li>Moodle email system not configured</li>
            </ul>
        </div>';
        
        // Show configuration status
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4>🔍 Configuration Status:</h4>
            <ul>
                <li><strong>Alert System:</strong> ' . ($alerting_enabled ? '✅ Enabled' : '❌ Disabled') . '</li>
                <li><strong>Email Alerts:</strong> ' . (get_config('local_alx_report_api', 'enable_email_alerts') ? '✅ Enabled' : '❌ Disabled') . '</li>
                <li><strong>Recipients:</strong> ' . ($alert_emails ? htmlspecialchars($alert_emails) : '❌ None configured') . '</li>
                <li><strong>Threshold:</strong> ' . (get_config('local_alx_report_api', 'alert_threshold') ?: 'medium') . '</li>
            </ul>
        </div>';
    }
}

// Show test button
echo '<div style="margin-top: 30px; text-align: center;">
    <form method="post" action="">
        <input type="hidden" name="sesskey" value="' . sesskey() . '">
        <input type="hidden" name="send_test" value="1">
        <button type="submit" class="btn btn-primary btn-lg" style="padding: 15px 40px; font-size: 18px;">
            📧 Send Test Email Alert
        </button>
    </form>
</div>';

// Show recent alerts
echo '<div style="margin-top: 40px;">
    <h3>📊 Recent Alerts (Last 10)</h3>';

global $DB;
if ($DB->get_manager()->table_exists(constants::TABLE_ALERTS)) {
    $recent_alerts = $DB->get_records(constants::TABLE_ALERTS, null, 'timecreated DESC', '*', 0, 10);
    
    if ($recent_alerts) {
        echo '<table class="table table-striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($recent_alerts as $alert) {
            $severity_colors = [
                'low' => '#17a2b8',
                'medium' => '#ffc107',
                'high' => '#fd7e14',
                'critical' => '#dc3545'
            ];
            $color = $severity_colors[$alert->severity] ?? '#6c757d';
            
            echo '<tr>
                <td>' . date('Y-m-d H:i:s', $alert->timecreated) . '</td>
                <td>' . htmlspecialchars($alert->alert_type) . '</td>
                <td><span style="background: ' . $color . '; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($alert->severity) . '</span></td>
                <td>' . htmlspecialchars($alert->message) . '</td>
            </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p style="color: #6c757d; font-style: italic;">No alerts logged yet.</p>';
    }
} else {
    echo '<p style="color: #dc3545;">Alerts table does not exist.</p>';
}

echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
