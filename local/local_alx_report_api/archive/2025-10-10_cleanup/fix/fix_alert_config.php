<?php
/**
 * Quick fix script to check and update alert configuration
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/local_alx_report_api/fix_alert_config.php');
$PAGE->set_title('Fix Alert Configuration');

echo $OUTPUT->header();
echo $OUTPUT->heading('Alert Configuration Diagnostic & Fix');

// Check current values
echo html_writer::tag('h3', 'Current Configuration Values:');
echo html_writer::start_tag('ul');

$enable_alerting = get_config('local_alx_report_api', 'enable_alerting');
$enable_email_alerts = get_config('local_alx_report_api', 'enable_email_alerts');
$alert_emails = get_config('local_alx_report_api', 'alert_emails');
$alert_threshold = get_config('local_alx_report_api', 'alert_threshold');

echo html_writer::tag('li', 'enable_alerting: ' . ($enable_alerting ? '✅ TRUE (1)' : '❌ FALSE (0 or not set)'));
echo html_writer::tag('li', 'enable_email_alerts: ' . ($enable_email_alerts ? '✅ TRUE (1)' : '❌ FALSE (0 or not set)'));
echo html_writer::tag('li', 'alert_emails: ' . ($alert_emails ? htmlspecialchars($alert_emails) : '❌ Not set'));
echo html_writer::tag('li', 'alert_threshold: ' . ($alert_threshold ? htmlspecialchars($alert_threshold) : '❌ Not set (default: medium)'));

echo html_writer::end_tag('ul');

// Check if fix is requested
$fix = optional_param('fix', 0, PARAM_INT);

if (!$fix) {
    echo html_writer::tag('div', 
        'Click the button below to enable alert system and email alerts',
        ['class' => 'alert alert-info', 'style' => 'margin: 20px 0;']
    );
    
    echo html_writer::start_div('', ['style' => 'margin: 20px 0;']);
    echo html_writer::link(
        new moodle_url('/local/local_alx_report_api/fix_alert_config.php', ['fix' => 1]),
        'Enable Alert System',
        ['class' => 'btn btn-primary', 'style' => 'margin-right: 10px;']
    );
    echo html_writer::link(
        new moodle_url('/local/local_alx_report_api/test_email_alert.php'),
        'Go to Test Page',
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
} else {
    // Apply fix
    echo html_writer::tag('h3', 'Applying Fix...');
    echo html_writer::start_tag('ul');
    
    // Enable alerting
    set_config('enable_alerting', 1, 'local_alx_report_api');
    echo html_writer::tag('li', '✓ Set enable_alerting = 1', ['style' => 'color: green;']);
    
    // Enable email alerts
    set_config('enable_email_alerts', 1, 'local_alx_report_api');
    echo html_writer::tag('li', '✓ Set enable_email_alerts = 1', ['style' => 'color: green;']);
    
    // Set default threshold if not set
    if (!$alert_threshold) {
        set_config('alert_threshold', 'low', 'local_alx_report_api');
        echo html_writer::tag('li', '✓ Set alert_threshold = low', ['style' => 'color: green;']);
    }
    
    // Set default cooldown if not set
    $alert_cooldown = get_config('local_alx_report_api', 'alert_cooldown');
    if (!$alert_cooldown) {
        set_config('alert_cooldown', 60, 'local_alx_report_api');
        echo html_writer::tag('li', '✓ Set alert_cooldown = 60 minutes', ['style' => 'color: green;']);
    }
    
    echo html_writer::end_tag('ul');
    
    echo html_writer::tag('div', 
        '✓ Alert system has been enabled! Now test the email alert.',
        ['class' => 'alert alert-success', 'style' => 'margin: 20px 0;']
    );
    
    echo html_writer::start_div('', ['style' => 'margin: 20px 0;']);
    echo html_writer::link(
        new moodle_url('/local/local_alx_report_api/test_email_alert.php'),
        'Test Email Alert',
        ['class' => 'btn btn-primary', 'style' => 'margin-right: 10px;']
    );
    echo html_writer::link(
        new moodle_url('/local/local_alx_report_api/control_center.php'),
        'Go to Control Center',
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    
    // Show updated values
    echo html_writer::tag('h3', 'Updated Configuration Values:');
    echo html_writer::start_tag('ul');
    
    $enable_alerting = get_config('local_alx_report_api', 'enable_alerting');
    $enable_email_alerts = get_config('local_alx_report_api', 'enable_email_alerts');
    $alert_emails = get_config('local_alx_report_api', 'alert_emails');
    $alert_threshold = get_config('local_alx_report_api', 'alert_threshold');
    
    echo html_writer::tag('li', 'enable_alerting: ' . ($enable_alerting ? '✅ TRUE (1)' : '❌ FALSE (0)'));
    echo html_writer::tag('li', 'enable_email_alerts: ' . ($enable_email_alerts ? '✅ TRUE (1)' : '❌ FALSE (0)'));
    echo html_writer::tag('li', 'alert_emails: ' . ($alert_emails ? htmlspecialchars($alert_emails) : '⚠️ Not set - add emails in Control Center'));
    echo html_writer::tag('li', 'alert_threshold: ' . htmlspecialchars($alert_threshold));
    
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
