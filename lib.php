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
 * Library functions for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the settings navigation with the ALX Report API settings
 *
 * This function is called when the settings navigation is being built.
 *
 * @param settings_navigation $settingsnav The settings navigation
 * @param navigation_node $context The context node
 */
function local_alx_report_api_extend_settings_navigation($settingsnav, $context) {
    // This function is intentionally left empty but prevents navigation conflicts
    // The plugin settings are already added via settings.php
    return;
}

/**
 * Get company information for API access logging and validation.
 *
 * @param int $companyid Company ID
 * @return object|false Company object or false if not found
 */
function local_alx_report_api_get_company_info($companyid) {
    global $DB;

    if ($DB->get_manager()->table_exists('company')) {
        return $DB->get_record('company', ['id' => $companyid], 'id, name, shortname');
    }

    return false;
}

/**
 * Check if a user has API access permissions.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @return bool True if user has access, false otherwise
 */
function local_alx_report_api_has_api_access($userid, $companyid) {
    global $DB;

    // Check if user belongs to the company.
    if ($DB->get_manager()->table_exists('company_users')) {
        $company_user = $DB->get_record('company_users', [
            'userid' => $userid,
            'companyid' => $companyid,
        ]);
        
        return !empty($company_user);
    }

    return false;
}

/**
 * Validate API token and get associated user and company.
 *
 * @param string $token API token
 * @return array|false Array with userid and companyid or false if invalid
 */
function local_alx_report_api_validate_token($token) {
    global $DB;

    // Get external token record.
    $tokenrecord = $DB->get_record('external_tokens', [
        'token' => $token,
        'tokentype' => EXTERNAL_TOKEN_PERMANENT,
    ]);

    if (!$tokenrecord) {
        return false;
    }

    // Check if token is for our service.
    $service = $DB->get_record('external_services', [
        'id' => $tokenrecord->externalserviceid,
        'shortname' => 'alx_report_api',
    ]);

    if (!$service) {
        return false;
    }

    // Get user's company.
    if ($DB->get_manager()->table_exists('company_users')) {
        $company_user = $DB->get_record('company_users', [
            'userid' => $tokenrecord->userid,
        ]);

        if ($company_user) {
            return [
                'userid' => $tokenrecord->userid,
                'companyid' => $company_user->companyid,
            ];
        }
    }

    return false;
}

/**
 * Clean up old API logs (for maintenance).
 *
 * @param int $days Number of days to keep logs (default: 90)
 * @return int Number of records deleted
 */
function local_alx_report_api_cleanup_logs($days = 90) {
    global $DB;

    $cutoff = time() - ($days * 24 * 60 * 60);
    
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        return $DB->delete_records_select('local_alx_api_logs', 'timecreated < ?', [$cutoff]);
    }

    return 0;
}

/**
 * Get API usage statistics for a company.
 *
 * @param int $companyid Company ID
 * @param int $days Number of days to look back (default: 30)
 * @return array Usage statistics
 */
function local_alx_report_api_get_usage_stats($companyid, $days = 30) {
    global $DB;

    $cutoff = time() - ($days * 24 * 60 * 60);
    $stats = [
        'total_requests' => 0,
        'unique_users' => 0,
        'last_access' => 0,
    ];

    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        // Total requests.
        $stats['total_requests'] = $DB->count_records_select(
            'local_alx_api_logs',
            'companyid = ? AND timecreated > ?',
            [$companyid, $cutoff]
        );

        // Unique users.
        $sql = "SELECT COUNT(DISTINCT userid) 
                FROM {local_alx_api_logs} 
                WHERE companyid = ? AND timecreated > ?";
        $stats['unique_users'] = $DB->count_records_sql($sql, [$companyid, $cutoff]);

        // Last access.
        $last_access = $DB->get_field_select(
            'local_alx_api_logs',
            'MAX(timecreated)',
            'companyid = ?',
            [$companyid]
        );
        $stats['last_access'] = $last_access ?: 0;
    }

    return $stats;
}

/**
 * Get all companies available for API configuration.
 *
 * @return array Array of company objects
 */
function local_alx_report_api_get_companies() {
    global $DB;
    
    if ($DB->get_manager()->table_exists('company')) {
        return $DB->get_records('company', null, 'name ASC', 'id, name, shortname');
    }
    
    return [];
}

/**
 * Get company-specific setting value.
 *
 * @param int $companyid Company ID
 * @param string $setting_name Setting name (e.g., 'field_email', 'course_10')
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed Setting value
 */
function local_alx_report_api_get_company_setting($companyid, $setting_name, $default = 0) {
    global $DB;
    
    $setting = $DB->get_record('local_alx_api_settings', [
        'companyid' => $companyid,
        'setting_name' => $setting_name
    ]);
    
    return $setting ? $setting->setting_value : $default;
}

/**
 * Set company-specific setting value.
 *
 * @param int $companyid Company ID
 * @param string $setting_name Setting name
 * @param mixed $setting_value Setting value
 * @return bool True on success
 */
function local_alx_report_api_set_company_setting($companyid, $setting_name, $setting_value) {
    global $DB;
    
    $existing = $DB->get_record('local_alx_api_settings', [
        'companyid' => $companyid,
        'setting_name' => $setting_name
    ]);
    
    $time = time();
    
    if ($existing) {
        // Update existing setting
        $existing->setting_value = $setting_value;
        $existing->timemodified = $time;
        return $DB->update_record('local_alx_api_settings', $existing);
    } else {
        // Create new setting
        $setting = new stdClass();
        $setting->companyid = $companyid;
        $setting->setting_name = $setting_name;
        $setting->setting_value = $setting_value;
        $setting->timecreated = $time;
        $setting->timemodified = $time;
        return $DB->insert_record('local_alx_api_settings', $setting);
    }
}

/**
 * Get all settings for a specific company.
 *
 * @param int $companyid Company ID
 * @return array Array of settings keyed by setting name
 */
function local_alx_report_api_get_company_settings($companyid) {
    global $DB;
    
    $settings = $DB->get_records('local_alx_api_settings', 
        ['companyid' => $companyid], '', 'setting_name, setting_value');
    
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting->setting_name] = $setting->setting_value;
    }
    
    return $result;
}

/**
 * Copy settings from one company to another (or from global defaults).
 *
 * @param int $from_companyid Source company ID (0 for global defaults)
 * @param int $to_companyid Target company ID
 * @return bool True on success
 */
function local_alx_report_api_copy_company_settings($from_companyid, $to_companyid) {
    global $DB;
    
    if ($from_companyid == 0) {
        // Copy from global defaults
        $global_settings = [
            'field_userid' => get_config('local_alx_report_api', 'field_userid') ?: 1,
            'field_firstname' => get_config('local_alx_report_api', 'field_firstname') ?: 1,
            'field_lastname' => get_config('local_alx_report_api', 'field_lastname') ?: 1,
            'field_email' => get_config('local_alx_report_api', 'field_email') ?: 1,
            'field_courseid' => get_config('local_alx_report_api', 'field_courseid') ?: 1,
            'field_coursename' => get_config('local_alx_report_api', 'field_coursename') ?: 1,
            'field_timecompleted' => get_config('local_alx_report_api', 'field_timecompleted') ?: 1,
            'field_timecompleted_unix' => get_config('local_alx_report_api', 'field_timecompleted_unix') ?: 1,
            'field_timestarted' => get_config('local_alx_report_api', 'field_timestarted') ?: 1,
            'field_timestarted_unix' => get_config('local_alx_report_api', 'field_timestarted_unix') ?: 1,
            'field_percentage' => get_config('local_alx_report_api', 'field_percentage') ?: 1,
            'field_status' => get_config('local_alx_report_api', 'field_status') ?: 1,
        ];
        
        // Copy field settings
        foreach ($global_settings as $setting_name => $setting_value) {
            local_alx_report_api_set_company_setting($to_companyid, $setting_name, $setting_value);
        }
        
        // Copy course settings (enable all courses by default)
        $company_courses = local_alx_report_api_get_company_courses($to_companyid);
        foreach ($company_courses as $course) {
            $course_setting = 'course_' . $course->id;
            local_alx_report_api_set_company_setting($to_companyid, $course_setting, 1);
        }
    } else {
        // Copy from another company
        $source_settings = local_alx_report_api_get_company_settings($from_companyid);
        foreach ($source_settings as $setting_name => $setting_value) {
            local_alx_report_api_set_company_setting($to_companyid, $setting_name, $setting_value);
        }
    }
    
    return true;
}

/**
 * Get all courses available to a specific company.
 *
 * @param int $companyid Company ID
 * @return array Array of course objects
 */
function local_alx_report_api_get_company_courses($companyid) {
    global $DB;
    
    if (!$DB->get_manager()->table_exists('company_course')) {
        return [];
    }
    
    $sql = "SELECT c.id, c.fullname, c.shortname, c.visible
            FROM {course} c
            JOIN {company_course} cc ON cc.courseid = c.id
            WHERE cc.companyid = :companyid
                AND c.visible = 1
                AND c.id != 1
            ORDER BY c.fullname ASC";
    
    return $DB->get_records_sql($sql, ['companyid' => $companyid]);
}

/**
 * Get enabled courses for a company based on settings.
 *
 * @param int $companyid Company ID
 * @return array Array of enabled course IDs
 */
function local_alx_report_api_get_enabled_courses($companyid) {
    global $DB;
    
    $enabled_courses = [];
    $company_settings = local_alx_report_api_get_company_settings($companyid);
    
    foreach ($company_settings as $setting_name => $setting_value) {
        if (strpos($setting_name, 'course_') === 0 && $setting_value == 1) {
            $course_id = (int)str_replace('course_', '', $setting_name);
            if ($course_id > 0) {
                $enabled_courses[] = $course_id;
            }
        }
    }
    
    return $enabled_courses;
}

/**
 * Check if a course is enabled for a company.
 *
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True if enabled, false otherwise
 */
function local_alx_report_api_is_course_enabled($companyid, $courseid) {
    $setting_name = 'course_' . $courseid;
    return local_alx_report_api_get_company_setting($companyid, $setting_name, 1) == 1;
} 
