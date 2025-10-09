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
 * Settings for the ALX Report API plugin - Reorganized and Enhanced
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page in the Local plugins category
    $settings = new admin_settingpage(
        'local_alx_report_api',
        new lang_string('pluginname', 'local_alx_report_api')
    );

    // Add to the local plugins category
    $ADMIN->add('localplugins', $settings);

    // ========================================
    // SECTION 1: MAIN DASHBOARDS & TOOLS
    // ========================================
    
    // Control Center (Primary Dashboard)
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_control_center',
        '🎛️ Control Center',
        new moodle_url('/local/alx_report_api/control_center.php'),
        'moodle/site:config'
    ));

    // Monitoring & Analytics
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_monitoring',
        '📊 Monitoring & Analytics',
        $CFG->wwwroot . '/local/alx_report_api/monitoring_dashboard_new.php',
        'moodle/site:config'
    ));

    // Note: Company Settings, Data Management tools are accessible via Control Center
    // No need for separate navigation menu items

    // ========================================
    // PLUGIN CONFIGURATION SETTINGS
    // ========================================

    // Introduction Section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/intro',
        '🚀 ALX Report API Configuration',
        '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; color: white;">Welcome to ALX Report API Settings</h3>
            <p style="margin: 0; opacity: 0.9;">Configure API performance, security, alerts, and system behavior. Changes take effect immediately.</p>
        </div>'
    ));

    // ========================================
    // 1. PERFORMANCE & LIMITS
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/performance_section',
        '⚡ Performance & Limits',
        '<p style="color: #666; margin: 10px 0;">Optimize API performance and set usage limits to ensure system stability.</p>'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_records',
        'Maximum Records Per Request',
        '<strong>Default: 1000</strong><br>Maximum number of records returned in a single API call (100-10000).<br>
        💡 <em>Lower values = faster response, more API calls needed</em>',
        1000,
        PARAM_INT,
        5
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/rate_limit',
        'Rate Limit (Requests/Day)',
        '<strong>Default: 100</strong><br>Maximum API requests per day per company (0 = unlimited).<br>
        🔒 <em>Prevents API abuse and ensures fair usage</em>',
        100,
        PARAM_INT,
        3
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/log_retention_days',
        'Log Retention Period (Days)',
        '<strong>Default: 90</strong><br>How long to keep API access logs (0 = forever).<br>
        ⚠️ <em>Longer retention requires more storage space</em>',
        90,
        PARAM_INT,
        3
    ));

    // ========================================
    // 2. SECURITY SETTINGS
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/security_section',
        '🔐 Security Settings',
        '<p style="color: #666; margin: 10px 0;">Configure security options for API access and authentication.</p>'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/allow_get_method',
        'Allow GET Method (Development Only)',
        '<strong>⚠️ Production Warning:</strong> Enable GET method for testing/development only.<br>
        Always use POST method in production for security.<br>
        <span style="color: #dc3545;">Recommended: DISABLED for production</span>',
        '0'
    ));

    // ========================================
    // 3. AUTO-SYNC CONFIGURATION
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/autosync_section',
        '🤖 Automatic Sync Configuration',
        '<p style="color: #666; margin: 10px 0;">Configure background data synchronization for improved API performance.</p>'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/auto_sync_hours',
        'Sync Lookback Window (Hours)',
        '<strong>Default: 1 hour</strong><br>How far back to check for changes during automatic sync (1-168 hours).<br>
        ⏰ <em>Sync runs every hour via scheduled task</em>',
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_sync_time',
        'Maximum Sync Duration (Seconds)',
        '<strong>Default: 300 seconds (5 minutes)</strong><br>Maximum time allowed for sync task execution (60-3600 seconds).<br>
        ⚡ <em>Prevents long-running tasks from affecting performance</em>',
        300,
        PARAM_INT
    ));

    // ========================================
    // 4. ALERT SYSTEM
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/alerts_section',
        '🔔 Alert System Configuration',
        '<p style="color: #666; margin: 10px 0;">Configure email alerts for system monitoring and security events.</p>'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/enable_alerting',
        'Enable Alert System',
        'Enable email notifications for monitoring events (rate limits, security issues, performance problems).<br>
        ✅ <em>Recommended: ENABLED for production systems</em>',
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/enable_email_alerts',
        'Enable Email Alerts',
        'Send alerts via email using Moodle\'s email system.<br>
        📧 <em>Requires Moodle email to be configured</em>',
        1
    ));

    $settings->add(new admin_setting_configselect(
        'local_alx_report_api/alert_threshold',
        'Alert Severity Threshold',
        'Minimum severity level for sending alerts',
        'medium',
        [
            'low' => '🔵 Low - Send all alerts',
            'medium' => '🟡 Medium - Send medium, high, and critical alerts (Recommended)',
            'high' => '🟠 High - Send only high and critical alerts',
            'critical' => '🔴 Critical - Send only critical alerts'
        ]
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_alx_report_api/alert_emails',
        'Alert Recipients',
        '<strong>Required:</strong> Comma-separated email addresses to receive alerts.<br>
        Example: admin@example.com, manager@example.com<br>
        📧 <em>These emails will receive all alerts matching the threshold</em>',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/alert_cooldown',
        'Alert Cooldown Period (Minutes)',
        '<strong>Default: 60 minutes</strong><br>Minimum time between alerts of the same type to prevent spam (5-1440 minutes).<br>
        ⏱️ <em>Prevents alert flooding during ongoing issues</em>',
        60,
        PARAM_INT
    ));

    // Alert Thresholds Sub-section
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/alert_thresholds_subsection',
        '📊 Alert Trigger Thresholds',
        '<p style="color: #666; margin: 10px 0; font-style: italic;">Configure when alerts should be triggered based on system metrics.</p>'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/high_api_usage_threshold',
        'High API Usage Threshold (Calls/Hour)',
        '<strong>Default: 200</strong><br>Trigger alert when API calls per hour exceed this number.',
        200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/health_score_threshold',
        'Health Score Alert Threshold',
        '<strong>Default: 70</strong><br>Trigger alert when system health score drops below this value (0-100).',
        70,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/db_response_time_threshold',
        'Database Response Time Threshold (ms)',
        '<strong>Default: 200ms</strong><br>Trigger alert when database response time exceeds this value.',
        200,
        PARAM_INT
    ));

    // ========================================
    // 5. SYSTEM STATUS
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/status_section',
        '📊 System Status',
        '<p style="color: #666; margin: 10px 0;">Monitor the health and configuration of your API system components.</p>'
    ));

    // Web Services Status
    $webservices_enabled = get_config('moodle', 'enablewebservices');
    $webservices_status = $webservices_enabled ? 
        '<span style="display: inline-block; padding: 6px 12px; background: #d4edda; color: #155724; border-radius: 4px; font-weight: 600;">✅ Enabled</span>' : 
        '<span style="display: inline-block; padding: 6px 12px; background: #f8d7da; color: #721c24; border-radius: 4px; font-weight: 600;">❌ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/webservices_status',
        'Web Services',
        $webservices_status . ($webservices_enabled ? '' : '<br><a href="' . $CFG->wwwroot . '/admin/search.php?query=enablewebservices">Enable Web Services</a>')
    ));

    // REST Protocol Status
    $rest_enabled = strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false;
    $rest_status = $rest_enabled ? 
        '<span style="display: inline-block; padding: 6px 12px; background: #d4edda; color: #155724; border-radius: 4px; font-weight: 600;">✅ Enabled</span>' : 
        '<span style="display: inline-block; padding: 6px 12px; background: #f8d7da; color: #721c24; border-radius: 4px; font-weight: 600;">❌ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/rest_status',
        'REST Protocol',
        $rest_status . ($rest_enabled ? '' : '<br><a href="' . $CFG->wwwroot . '/admin/settings.php?section=webserviceprotocols">Enable REST Protocol</a>')
    ));

    // Service Status
    global $DB;
    $service_exists = $DB->record_exists('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$service_exists) {
        $service_exists = $DB->record_exists('external_services', ['shortname' => 'alx_report_api']);
    }
    $service_status = $service_exists ? 
        '<span style="display: inline-block; padding: 6px 12px; background: #d4edda; color: #155724; border-radius: 4px; font-weight: 600;">✅ Active</span>' : 
        '<span style="display: inline-block; padding: 6px 12px; background: #f8d7da; color: #721c24; border-radius: 4px; font-weight: 600;">❌ Missing</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/service_status',
        'API Service',
        $service_status
    ));

    // ========================================
    // 6. QUICK ACTIONS
    // ========================================
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/quickactions_section',
        '⚡ Quick Actions',
        '<p style="color: #666; margin: 10px 0;">Access commonly used tools and configuration pages.</p>'
    ));

    // Simple list of quick action links
    $quickactions_list = '<ul style="list-style: none; padding: 0; margin: 15px 0;">
        <li style="margin: 8px 0;"><a href="' . $CFG->wwwroot . '/admin/webservice/tokens.php"><strong>🔑 Manage Tokens</strong> - Create and manage API tokens</a></li>
        <li style="margin: 8px 0;"><a href="' . $CFG->wwwroot . '/admin/settings.php?section=externalservices"><strong>⚙️ Manage Services</strong> - Configure external services</a></li>
        <li style="margin: 8px 0;"><a href="' . $CFG->wwwroot . '/local/alx_report_api/test_email_alert.php"><strong>🧪 Test Email Alerts</strong> - Send a test alert email</a></li>
        <li style="margin: 8px 0;"><a href="' . $CFG->wwwroot . '/admin/webservice/documentation.php"><strong>📖 API Documentation</strong> - View API reference docs</a></li>
    </ul>';

    $settings->add(new admin_setting_description(
        'local_alx_report_api/quickactions_links',
        '',
        $quickactions_list
    ));
}
