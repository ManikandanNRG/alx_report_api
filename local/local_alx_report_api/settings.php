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
 * Settings for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page in the Local plugins category.
    $settings = new admin_settingpage(
        'local_alx_report_api',
        new lang_string('pluginname', 'local_alx_report_api')
    );

    // Add to the local plugins category.
    $ADMIN->add('localplugins', $settings);

    // Add the new Control Center as the main dashboard
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_control_center',
        '🎛️ ALX Report API - Control Center',
        new moodle_url('/local/alx_report_api/control_center.php'),
        'moodle/site:config'
    ));

    // Add advanced monitoring page
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_advanced_monitoring',
        '🔍 Advanced Monitoring',
        $CFG->wwwroot . '/local/alx_report_api/advanced_monitoring.php',
        'moodle/site:config'
    ));

    // Add historical trends dashboard
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_trends',
        '📈 Historical Trends',
        $CFG->wwwroot . '/local/alx_report_api/trends_dashboard.php',
        'moodle/site:config'
    ));

    // Add other monitoring pages
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_unified_monitoring',
        '🚀 Unified Tactical Dashboard',
        $CFG->wwwroot . '/local/alx_report_api/unified_monitoring_dashboard.php',
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_monitoring',
        '📊 Standard Monitoring',
        $CFG->wwwroot . '/local/alx_report_api/monitoring_dashboard.php',
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_auto_sync_status',
        '⚡ Auto-Sync Status',
        $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php',
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_rate_limit',
        '🛡️ Rate Limit Monitor',
        $CFG->wwwroot . '/local/alx_report_api/check_rate_limit.php',
        'moodle/site:config'
    ));

    // Add company settings as a separate admin page
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_company_settings',
        get_string('company_settings_title', 'local_alx_report_api'),
        new moodle_url('/local/alx_report_api/company_settings.php'),
        'moodle/site:config'
    ));

    // Plugin configuration settings.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/generalheading',
        '🚀 ' . get_string('general', 'local_alx_report_api'),
        get_string('apidescription', 'local_alx_report_api')
    ));

    // Performance & Limits Section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/performanceheading',
        '⚡ Performance & Limits',
        'Configure API performance settings, rate limiting, and data retention policies to optimize your system performance.'
    ));

    // Maximum records per API request.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_records',
        get_string('maxrecords', 'local_alx_report_api'),
        get_string('maxrecords_desc', 'local_alx_report_api') . '<br><strong>💡 Tip:</strong> Lower values improve response time but require more API calls for large datasets.',
        1000,
        PARAM_INT,
        5
    ));

    // Log retention period.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/log_retention_days',
        get_string('logretention', 'local_alx_report_api'),
        get_string('logretention_desc', 'local_alx_report_api') . '<br><strong>⚠️ Note:</strong> Longer retention periods require more database storage space.',
        90,
        PARAM_INT,
        3
    ));

    // Rate limiting.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/rate_limit',
        get_string('ratelimit', 'local_alx_report_api'),
        get_string('ratelimit_desc', 'local_alx_report_api') . '<br><strong>🔒 Security:</strong> Rate limiting prevents API abuse and ensures fair usage across all users.',
        100,
        PARAM_INT,
        3
    ));

    // Security Section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/securityheading',
        '🔐 Security Settings',
        'Configure security options for API access. These settings affect how clients can interact with your API.'
    ));

    // GET/POST method toggle for development/testing
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/allow_get_method',
        get_string('allow_get_method', 'local_alx_report_api'),
        get_string('allow_get_method_desc', 'local_alx_report_api') . '<br><strong>⚠️ Production Warning:</strong> GET method should only be enabled for development/testing. Always use POST in production for security.',
        '0'
    ));

    // Auto-sync configuration.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/autosyncheading',
        '🤖 Automatic Sync Configuration',
        'Configure automatic background synchronization of reporting data to improve API response times and reduce database load.'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/auto_sync_hours',
        get_string('auto_sync_hours', 'local_alx_report_api'),
        get_string('auto_sync_hours_desc', 'local_alx_report_api') . '<br><strong>⏰ Frequency:</strong> Sync runs every hour and looks back this many hours for changes.',
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_sync_time',
        get_string('max_sync_time', 'local_alx_report_api'),
        get_string('max_sync_time_desc', 'local_alx_report_api') . '<br><strong>⚡ Performance:</strong> Prevents long-running sync tasks from affecting server performance.',
        300,
        PARAM_INT
    ));

    // API status information.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/statusheading',
        '📊 ' . get_string('apistatus', 'local_alx_report_api'),
        'Monitor the health and configuration status of your API system components.'
    ));

    // Web services status.
    $webservices_enabled = get_config('moodle', 'enablewebservices');
    $webservices_status = $webservices_enabled ? 
        '<span style="color: green; font-weight: bold;">✅ Enabled</span>' : 
        '<span style="color: red; font-weight: bold;">❌ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/webservices_status',
        get_string('webservicesstatus', 'local_alx_report_api'),
        $webservices_status
    ));

    // REST protocol status.
    $rest_enabled = strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false;
    $rest_status = $rest_enabled ? 
        '<span style="color: green; font-weight: bold;">✅ Enabled</span>' : 
        '<span style="color: red; font-weight: bold;">❌ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/rest_status',
        get_string('restprotocolstatus', 'local_alx_report_api'),
        $rest_status
    ));

    // Service status.
    global $DB;
    $service_exists = $DB->record_exists('external_services', ['shortname' => 'alx_report_api_custom']);
    $service_status = $service_exists ? 
        '<span style="color: green; font-weight: bold;">✅ Service Active</span>' : 
        '<span style="color: red; font-weight: bold;">❌ Service Missing</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/service_status',
        get_string('apiservicestatus', 'local_alx_report_api'),
        $service_status
    ));

    // Quick links section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/quicklinksheading',
        '🎛️ Quick Actions & Navigation',
        'Access related configuration pages and tools for managing your API.'
    ));

    // Enhanced Quick Links
            $alx_report_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
            $service_id = $alx_report_service ? $alx_report_service->id : '';
    
    $manage_services_url = $service_id ? 
        $CFG->wwwroot . '/admin/webservice/service.php?id=' . $service_id : 
        $CFG->wwwroot . '/admin/webservice/service_functions.php';

    $quicklinks_list = '<ul style="list-style: none; padding: 0; margin: 0;">
        <li style="margin: 10px 0; padding: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-left: 4px solid #667eea; border-radius: 4px;">
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/unified_monitoring_dashboard.php" style="text-decoration: none; color: white; font-weight: 600;">
                🚀 Unified Tactical Dashboard <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 8px;">NEW</span>
            </a>
        </li>
        <li style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
            <a href="' . $CFG->wwwroot . '/admin/webservice/tokens.php" style="text-decoration: none; color: #007cba; font-weight: 500;">
                🔑 ' . get_string('managetokens', 'local_alx_report_api') . '
            </a>
        </li>
        <li style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
            <a href="' . $manage_services_url . '" style="text-decoration: none; color: #007cba; font-weight: 500;">
                ⚙️ ' . get_string('manageservices', 'local_alx_report_api') . '
            </a>
        </li>
        <li style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
            <a href="' . $CFG->wwwroot . '/admin/webservice/documentation.php" style="text-decoration: none; color: #007cba; font-weight: 500;">
                📖 ' . get_string('apidocumentation', 'local_alx_report_api') . '
            </a>
        </li>
        <li style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/monitoring_dashboard.php" style="text-decoration: none; color: #007cba; font-weight: 500;">
                📈 Standard Monitoring Dashboard
            </a>
        </li>
    </ul>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/quicklinks',
        'Standard Quick Links',
        $quicklinks_list
    ));

    // Primary Actions
    $primary_actions = '<div style="margin: 20px 0; padding: 20px; background: #e8f5e8; border-radius: 8px; text-align: center;">
        <h4 style="margin: 0 0 15px 0; color: #2d5a2d;">🚀 Primary Configuration Pages</h4>
        <p style="margin: 0 0 20px 0; color: #5a5a5a;">Access the main configuration and monitoring interfaces for your API.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/company_settings.php" 
               style="display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                🏢 Company Settings
            </a>
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php" 
               style="display: inline-block; padding: 12px 24px; background: #17a2b8; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                🔄 Auto-Sync Status
            </a>
        </div>
    </div>';
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/primaryactions',
        'Main Configuration Pages',
        $primary_actions
    ));

    // Add alerting configuration section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api_alerting',
        get_string('alerting_settings', 'local_alx_report_api'),
        get_string('alerting_settings_desc', 'local_alx_report_api')
    ));

    // Enable alerting
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/enable_alerting',
        'Enable Alert System',
        'Enable email and SMS alerts for system monitoring events (rate limits, security issues, performance problems)',
        1
    ));

    // Alert threshold
    $alert_threshold_options = [
        'low' => 'Low - Send all alerts',
        'medium' => 'Medium - Send medium, high, and critical alerts',
        'high' => 'High - Send only high and critical alerts', 
        'critical' => 'Critical - Send only critical alerts'
    ];
    $settings->add(new admin_setting_configselect(
        'local_alx_report_api/alert_threshold',
        'Alert Severity Threshold',
        'Minimum severity level for sending alerts',
        'medium',
        $alert_threshold_options
    ));

    // Alert recipients (emails)
    $settings->add(new admin_setting_configtextarea(
        'local_alx_report_api/alert_emails',
        'Alert Email Recipients',
        'Comma-separated list of email addresses to receive alerts. Site administrators will automatically receive critical alerts.',
        '',
        PARAM_TEXT
    ));

    // Email alert configuration
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/enable_email_alerts',
        'Enable Email Alerts',
        'Send alerts via email using Moodle\'s email system',
        1
    ));

    // SMS alert configuration  
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/enable_sms_alerts',
        'Enable SMS Alerts',
        'Send high and critical alerts via SMS (requires SMS service configuration)',
        0
    ));

    // SMS service selection
    $sms_service_options = [
        'disabled' => 'Disabled',
        'twilio' => 'Twilio',
        'aws_sns' => 'AWS SNS',
        'custom' => 'Custom SMS Gateway'
    ];
    $settings->add(new admin_setting_configselect(
        'local_alx_report_api/sms_service',
        'SMS Service Provider',
        'Select SMS service for sending alerts',
        'disabled',
        $sms_service_options
    ));

    // Alert frequency control
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/alert_cooldown',
        'Alert Cooldown Period (minutes)',
        'Minimum time between alerts of the same type to prevent spam',
        60,
        PARAM_INT
    ));

    // Performance alert thresholds
    $settings->add(new admin_setting_heading(
        'local_alx_report_api_alert_thresholds',
        'Alert Thresholds',
        'Configure when alerts should be triggered based on system metrics'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/high_api_usage_threshold',
        'High API Usage Threshold (calls/hour)',
        'Send alert when API calls per hour exceed this number',
        200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/health_score_threshold',
        'Health Score Alert Threshold',
        'Send alert when system health score drops below this value (0-100)',
        70,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/db_response_time_threshold',
        'Database Response Time Threshold (ms)',
        'Send alert when database response time exceeds this value',
        200,
        PARAM_INT
    ));

    // Test alert functionality
    $settings->add(new admin_setting_heading(
        'local_alx_report_api_alert_testing',
        'Alert Testing',
        'Test your alert configuration'
    ));

    if (isset($CFG->wwwroot)) {
        $test_alert_url = $CFG->wwwroot . '/local/alx_report_api/test_alerts.php';
        $settings->add(new admin_setting_description(
            'local_alx_report_api/test_alerts',
            'Test Alert System',
            '<div style="margin: 10px 0;">
                <a href="' . $test_alert_url . '" class="btn btn-secondary" style="padding: 8px 16px; text-decoration: none; background: #6c757d; color: white; border-radius: 4px;">
                    🧪 Send Test Alert
                </a>
                <p style="margin-top: 10px; color: #666; font-size: 0.9em;">
                    Click to send a test alert to verify your configuration is working properly.
                </p>
            </div>'
        ));
    }
} 
