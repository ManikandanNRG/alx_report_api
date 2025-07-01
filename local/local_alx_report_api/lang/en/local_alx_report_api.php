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
 * Language strings for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'ALX Report API';
$string['privacy:metadata'] = 'The ALX Report API plugin does not store any personal data.';

// General section.
$string['general'] = 'General';

// Error messages.
$string['invaliduser'] = 'Invalid user or user not authenticated';
$string['nocompanyassociation'] = 'User is not associated with any company';
$string['accessdenied'] = 'Access denied';
$string['invalidparameters'] = 'Invalid parameters provided';

// General strings.
$string['apiendpoint'] = 'API Endpoint';
$string['apidescription'] = 'Provides secure access to course progress data for Power BI integration';
$string['company'] = 'Company';
$string['courseProgress'] = 'Course Progress';
$string['lastAccessed'] = 'Last Accessed';

// Settings page strings.
$string['maxrecords'] = 'Maximum records per request';
$string['maxrecords_desc'] = 'Set the maximum number of records that can be returned in a single API request (1-1000)';
$string['logretention'] = 'Log retention (days)';
$string['logretention_desc'] = 'Number of days to keep API access logs (0 = keep forever)';
$string['ratelimit'] = 'Rate limit (requests per day)';
$string['ratelimit_desc'] = 'Maximum number of API requests per day per user (0 = no limit)';
$string['allow_get_method'] = 'Allow GET Method (Development Only)';
$string['allow_get_method_desc'] = 'Enable GET method for API requests (for testing/development). DISABLE this for production use - only POST method should be used for security.';
$string['apistatus'] = 'API Status';
$string['webservicesstatus'] = 'Web Services Status';
$string['restprotocolstatus'] = 'REST Protocol Status';
$string['apiservicestatus'] = 'API Service Status';
$string['quicklinks'] = 'Quick Links';
$string['webservicesoverview'] = 'Web Services Overview';
$string['managetokens'] = 'Manage Tokens';
$string['manageservices'] = 'Manage Services';
$string['apidocumentation'] = 'API Documentation';

// Field visibility settings.
$string['fieldheading'] = 'API Field Controls';
$string['fieldheading_desc'] = 'Configure which fields are included in the API response. Uncheck fields to hide them from clients.';

// User fields.
$string['field_userid'] = 'User ID';
$string['field_userid_desc'] = 'Include the numeric user ID in the response';
$string['field_firstname'] = 'First Name';
$string['field_firstname_desc'] = 'Include the user\'s first name in the response';
$string['field_lastname'] = 'Last Name';
$string['field_lastname_desc'] = 'Include the user\'s last name in the response';
$string['field_email'] = 'Email Address';
$string['field_email_desc'] = 'Include the user\'s email address in the response';

// Course fields.
$string['field_courseid'] = 'Course ID';
$string['field_courseid_desc'] = 'Include the numeric course ID in the response';
$string['field_coursename'] = 'Course Name';
$string['field_coursename_desc'] = 'Include the course name in the response';

// Progress fields.
$string['field_timecompleted'] = 'Completion Time (Human Readable)';
$string['field_timecompleted_desc'] = 'Include completion time in readable format (YYYY-MM-DD HH:MM:SS)';
$string['field_timecompleted_unix'] = 'Completion Time (Unix Timestamp)';
$string['field_timecompleted_unix_desc'] = 'Include completion time as Unix timestamp for calculations';
$string['field_timestarted'] = 'Start Time (Human Readable)';
$string['field_timestarted_desc'] = 'Include course start time in readable format (YYYY-MM-DD HH:MM:SS)';
$string['field_timestarted_unix'] = 'Start Time (Unix Timestamp)';
$string['field_timestarted_unix_desc'] = 'Include course start time as Unix timestamp for calculations';
$string['field_percentage'] = 'Completion Percentage';
$string['field_percentage_desc'] = 'Include completion percentage (0-100) in the response';
$string['field_status'] = 'Completion Status';
$string['field_status_desc'] = 'Include completion status (completed, in_progress, not_started, not_enrolled)';

// Company-specific settings.
$string['company_settings_title'] = 'ALX Report API - Company Settings';
$string['select_company'] = 'Select Company';
$string['company'] = 'Company';
$string['choose_company'] = 'Choose a company...';
$string['no_companies'] = 'No companies found. Please ensure IOMAD is properly installed and companies are created.';
$string['settings_for_company'] = 'API Settings for: {$a}';
$string['field_controls'] = 'Field Controls';
$string['save_settings'] = 'Save Settings';
$string['copy_from_template'] = 'Copy from Global Template';
$string['settings_saved'] = 'Company settings saved successfully';
$string['template_copied'] = 'Global template copied to company settings';
$string['go'] = 'Go';
$string['course_controls'] = 'Course Controls';
$string['course_controls_desc'] = 'Select which courses are available via API for this company. Unchecked courses will not appear in API responses.';
$string['no_courses_for_company'] = 'No courses found for this company. Please ensure courses are assigned to this company via IOMAD.';
$string['quick_course_actions'] = 'Quick actions:';
$string['select_all_courses'] = 'Select All';
$string['deselect_all_courses'] = 'Deselect All';

// Security error messages
$string['invalidrequestmethod'] = 'Only POST method is allowed for security reasons';
$string['invalidcontenttype'] = 'Invalid Content-Type header';
$string['missingauthheader'] = 'Authorization header is required';
$string['missingtoken'] = 'Authorization token is required';
$string['invalidtokenformat'] = 'Invalid token format';
$string['invalidtoken'] = 'Invalid or expired token';
$string['expiredtoken'] = 'Token has expired'; 

// Rate limiting and validation error messages
$string['ratelimitexceeded'] = 'Daily rate limit exceeded';
$string['limittoolarge'] = 'Requested limit is too large. Maximum allowed records per request is {$a}. Please reduce your limit parameter and try again.'; 

// Scheduled task strings
$string['sync_reporting_data_task'] = 'Sync reporting data incrementally';
$string['auto_sync_hours'] = 'Auto sync hours';
$string['auto_sync_hours_desc'] = 'Number of hours to look back for changes during automatic sync (default: 1 hour)';
$string['max_sync_time'] = 'Maximum sync execution time';
$string['max_sync_time_desc'] = 'Maximum time in seconds for sync task execution (default: 300 seconds)'; 

// API Response Status Messages
$string['api_no_data_full_sync'] = 'No course progress data found for this company. This could mean: 1) No users are enrolled in courses, 2) No course completions have occurred yet, 3) Reporting table needs to be populated with historical data, or 4) Company course settings exclude all courses.';
$string['api_no_data_incremental'] = 'No new course progress changes since last sync at {$a->last_sync_time}. This is normal when there are no recent course completions or user activity. Your dashboard will continue showing existing data.';
$string['api_no_data_reporting_empty'] = 'Reporting table is empty for this company. Please run the historical data population process first, or check if users are properly assigned to this company and enrolled in courses.';
$string['api_no_data_courses_filtered'] = 'No data available because all courses are disabled in company settings. Please enable at least one course in the Company Settings page to see course progress data.';
$string['api_no_data_first_sync'] = 'No course activity found in the specified time window (last {$a->hours} hours). This is normal for new companies or during periods of low activity. Try increasing the first sync time window or check if users are enrolled in courses.';
$string['api_status_incremental_success'] = 'Incremental sync completed successfully. Found {$a->count} changes since {$a->last_sync_time}.';
$string['api_status_full_sync_success'] = 'Full sync completed successfully. Retrieved {$a->count} total course progress records.';
$string['api_status_fallback_used'] = 'Used fallback query method due to reporting table issues. Performance may be slower than normal.';
$string['api_debug_sync_mode'] = 'Sync mode: {$a->mode}, Company: {$a->company}, Last sync: {$a->last_sync}';
$string['api_debug_no_changes'] = 'No changes detected since last sync. This is expected behavior when there are no new course completions or user activities.'; 

$string['verify_table_status'] = 'Verification Table Status';
$string['verify_table_status_desc'] = 'Shows the current status of the reporting table verification and data validation.';

// Alerting system language strings  
$string['alerting_settings'] = 'Alert System Configuration';
$string['alerting_settings_desc'] = 'Configure email and SMS alerts for monitoring events, system health, security issues, and performance problems.';
$string['enable_alerting'] = 'Enable Alert System';
$string['enable_alerting_desc'] = 'Enable email and SMS alerts for system monitoring events';
$string['alert_threshold'] = 'Alert Severity Threshold';
$string['alert_threshold_desc'] = 'Minimum severity level for sending alerts';
$string['alert_emails'] = 'Alert Email Recipients';
$string['alert_emails_desc'] = 'Comma-separated list of email addresses to receive alerts';
$string['enable_email_alerts'] = 'Enable Email Alerts';
$string['enable_email_alerts_desc'] = 'Send alerts via email using Moodle\'s email system';
$string['enable_sms_alerts'] = 'Enable SMS Alerts';
$string['enable_sms_alerts_desc'] = 'Send high and critical alerts via SMS';
$string['sms_service'] = 'SMS Service Provider';
$string['sms_service_desc'] = 'Select SMS service for sending alerts';
$string['alert_cooldown'] = 'Alert Cooldown Period';
$string['alert_cooldown_desc'] = 'Minimum time between alerts of the same type (in minutes)';
$string['high_api_usage_threshold'] = 'High API Usage Alert Threshold';
$string['high_api_usage_threshold_desc'] = 'Send alert when API calls per hour exceed this number';
$string['health_score_threshold'] = 'Health Score Alert Threshold';
$string['health_score_threshold_desc'] = 'Send alert when system health score drops below this value';
$string['db_response_time_threshold'] = 'Database Response Time Alert Threshold';
$string['db_response_time_threshold_desc'] = 'Send alert when database response time exceeds this value (in milliseconds)'; 
