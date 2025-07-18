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

    // Check if token is for our service - check both service names for compatibility.
    $service = $DB->get_record('external_services', [
        'id' => $tokenrecord->externalserviceid,
        'shortname' => 'alx_report_api_custom',
    ]);

    if (!$service) {
        $service = $DB->get_record('external_services', [
            'id' => $tokenrecord->externalserviceid,
            'shortname' => 'alx_report_api',
        ]);
    }

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
 * Get usage statistics for a specific company.
 *
 * @param int $companyid Company ID
 * @param int $days Number of days to look back (default 30)
 * @return array Usage statistics
 */
function local_alx_report_api_get_usage_stats($companyid, $days = 30) {
    global $DB;

    $stats = [
        'total_requests' => 0,
        'unique_users' => 0,
        'last_access' => 0,
    ];

    if (!$DB->get_manager()->table_exists('local_alx_api_logs')) {
        return $stats;
    }

    // Determine which time field to use
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    $cutoff = time() - ($days * 24 * 3600);

    // Check if we have the old companyid field or new company_shortname field
    if (isset($table_info['companyid'])) {
        // Old schema
        $stats['total_requests'] = $DB->count_records_select(
            'local_alx_api_logs',
            "companyid = ? AND {$time_field} > ?",
            [$companyid, $cutoff]
        );

        // Unique users.
        $sql = "SELECT COUNT(DISTINCT userid) 
                FROM {local_alx_api_logs} 
                WHERE companyid = ? AND {$time_field} > ?";
        $stats['unique_users'] = $DB->count_records_sql($sql, [$companyid, $cutoff]);

        // Last access.
        $last_access = $DB->get_field_select(
            'local_alx_api_logs',
            "MAX({$time_field})",
            'companyid = ?',
            [$companyid]
        );
        $stats['last_access'] = $last_access ?: 0;
    } else {
        // New schema or no company field - return zero stats
        $stats = [
            'total_requests' => 0,
            'unique_users' => 0,
            'last_access' => 0,
        ];
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
    
    try {
        // Check if tables exist
        if (!$DB->get_manager()->table_exists('local_alx_api_settings')) {
            throw new Exception('Settings table does not exist. Please run plugin installation.');
        }
        
        $existing = $DB->get_record('local_alx_api_settings', [
            'companyid' => $companyid,
            'setting_name' => $setting_name
        ]);
        
        $time = time();
        
        if ($existing) {
            // Update existing setting
            $existing->setting_value = $setting_value;
            $existing->timemodified = $time;
            $result = $DB->update_record('local_alx_api_settings', $existing);
            if (!$result) {
                throw new Exception("Failed to update setting: $setting_name");
            }
            return $result;
        } else {
            // Create new setting
            $setting = new stdClass();
            $setting->companyid = $companyid;
            $setting->setting_name = $setting_name;
            $setting->setting_value = $setting_value;
            $setting->timecreated = $time;
            $setting->timemodified = $time;
            $result = $DB->insert_record('local_alx_api_settings', $setting);
            if (!$result) {
                throw new Exception("Failed to insert setting: $setting_name");
            }
            return $result;
        }
    } catch (Exception $e) {
        // Log the error
        error_log("ALX Report API - Error saving company setting: " . $e->getMessage());
        error_log("ALX Report API - Company ID: $companyid, Setting: $setting_name, Value: $setting_value");
        
        // Return false to indicate failure
        return false;
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

// ===================================================================
// COMBINED APPROACH: REPORTING TABLE & INCREMENTAL SYNC FUNCTIONS
// ===================================================================

/**
 * Populate the reporting table with existing data from the main database.
 * Enhanced version with better progress reporting.
 *
 * @param int $companyid Company ID (0 for all companies)
 * @param int $batch_size Number of records to process per batch
 * @param bool $output_progress Whether to output progress information
 * @return array Result array with statistics
 */
function local_alx_report_api_populate_reporting_table($companyid = 0, $batch_size = 1000, $output_progress = false) {
    global $DB;
    
    $start_time = time();
    $total_processed = 0;
    $total_inserted = 0;
    $errors = [];
    $companies_processed = 0;
    
    try {
        // Get companies to process
        if ($companyid > 0) {
            $companies = [$DB->get_record('company', ['id' => $companyid])];
        } else {
            $companies = $DB->get_records('company', null, 'id ASC');
        }
        
        $total_companies = count($companies);
        $current_company = 0;
        
        foreach ($companies as $company) {
            if (!$company) continue;
            
            $current_company++;
            $companies_processed++;
            
            if ($output_progress && !defined('CLI_SCRIPT')) {
                $is_cli = (php_sapi_name() === 'cli');
                if (!$is_cli) {
                    echo '<script>addLogEntry("🏢 Processing company: ' . htmlspecialchars($company->name) . ' (' . $current_company . '/' . $total_companies . ')...", "company");</script>';
                    flush();
                }
            }
            
            // Get enabled courses for this company
            $enabled_courses = local_alx_report_api_get_enabled_courses($company->id);
            if (empty($enabled_courses)) {
                // If no courses enabled, enable all company courses
                $company_courses = local_alx_report_api_get_company_courses($company->id);
                $enabled_courses = array_column($company_courses, 'id');
            }
            
            if (empty($enabled_courses)) {
                if ($output_progress && !defined('CLI_SCRIPT')) {
                    $is_cli = (php_sapi_name() === 'cli');
                    if (!$is_cli) {
                        echo '<script>addLogEntry("  ⚠️ No courses found for ' . htmlspecialchars($company->name) . ' - skipping", "warning");</script>';
                        flush();
                    }
                }
                continue; // Skip if no courses available
            }
            
            $company_processed = 0;
            $company_inserted = 0;
            
            // Build the complex query to get all user-course data
            list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
            
            $sql = "
                SELECT DISTINCT
                    u.id as userid,
                    u.firstname,
                    u.lastname,
                    u.email,
                    c.id as courseid,
                    c.fullname as coursename,
                    COALESCE(cc.timecompleted, 
                        (SELECT MAX(cmc.timemodified) 
                         FROM {course_modules_completion} cmc
                         JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                         WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1), 0) as timecompleted,
                    COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
                    COALESCE(
                        CASE 
                            WHEN cc.timecompleted > 0 THEN 100.0
                            ELSE COALESCE(
                                (SELECT AVG(CASE WHEN cmc.completionstate = 1 THEN 100.0 ELSE 0.0 END)
                                 FROM {course_modules_completion} cmc
                                 JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                                 WHERE cm.course = c.id AND cmc.userid = u.id), 0.0)
                        END, 0.0) as percentage,
                    CASE 
                        WHEN cc.timecompleted > 0 THEN 'completed'
                        WHEN EXISTS(
                            SELECT 1 FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
                        ) THEN 'completed'
                        WHEN EXISTS(
                            SELECT 1 FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
                        ) THEN 'in_progress'
                        WHEN ue.id IS NOT NULL THEN 'not_started'
                        ELSE 'not_enrolled'
                    END as status
                FROM {user} u
                JOIN {company_users} cu ON cu.userid = u.id
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                WHERE cu.companyid = :companyid
                    AND u.deleted = 0
                    AND u.suspended = 0
                    AND c.visible = 1
                    AND c.id $course_sql
                    AND ue.status = 0
                ORDER BY u.id, c.id";
            
            $params = array_merge(['companyid' => $company->id], $course_params);
            
            // Process in batches
            $offset = 0;
            while (true) {
                $records = $DB->get_records_sql($sql, $params, $offset, $batch_size);
                if (empty($records)) {
                    break;
                }
                
                $batch_inserted = 0;
                $current_time = time();
                
                foreach ($records as $record) {
                    // Check if record already exists
                    $existing = $DB->get_record('local_alx_api_reporting', [
                        'userid' => $record->userid,
                        'courseid' => $record->courseid,
                        'companyid' => $company->id
                    ]);
                    
                    if (!$existing) {
                        // Insert new record
                        $reporting_record = new stdClass();
                        $reporting_record->userid = $record->userid;
                        $reporting_record->companyid = $company->id;
                        $reporting_record->courseid = $record->courseid;
                        $reporting_record->firstname = $record->firstname;
                        $reporting_record->lastname = $record->lastname;
                        $reporting_record->email = $record->email;
                        $reporting_record->coursename = $record->coursename;
                        $reporting_record->timecompleted = $record->timecompleted;
                        $reporting_record->timestarted = $record->timestarted;
                        $reporting_record->percentage = $record->percentage;
                        $reporting_record->status = $record->status;
                        $reporting_record->last_updated = $current_time;
                        $reporting_record->is_deleted = 0;
                        $reporting_record->created_at = $current_time;
                        $reporting_record->updated_at = $current_time;
                        
                        $DB->insert_record('local_alx_api_reporting', $reporting_record);
                        $batch_inserted++;
                        $company_inserted++;
                    }
                }
                
                $company_processed += count($records);
                $offset += $batch_size;
                
                // Break if we got fewer records than batch size (end of data)
                if (count($records) < $batch_size) {
                    break;
                }
            }
            
            $total_processed += $company_processed;
            $total_inserted += $company_inserted;
            
            if ($output_progress && !defined('CLI_SCRIPT')) {
                $is_cli = (php_sapi_name() === 'cli');
                if (!$is_cli) {
                    echo '<script>addLogEntry("  ✅ ' . htmlspecialchars($company->name) . ' - Processed: ' . number_format($company_processed) . ', Inserted: ' . number_format($company_inserted) . '", "success");</script>';
                    // Update progress
                    $percentage = round(($current_company / $total_companies) * 100);
                    echo '<script>updateProgress(' . $total_processed . ', ' . $total_inserted . ', ' . $companies_processed . ', ' . $percentage . ');</script>';
                    flush();
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = 'Population error: ' . $e->getMessage();
    }
    
    $end_time = time();
    $duration = $end_time - $start_time;
    
    return [
        'success' => empty($errors),
        'total_processed' => $total_processed,
        'total_inserted' => $total_inserted,
        'duration_seconds' => $duration,
        'errors' => $errors,
        'companies_processed' => $companies_processed
    ];
}

/**
 * Update a single record in the reporting table.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True on success
 */
function local_alx_report_api_update_reporting_record($userid, $companyid, $courseid) {
    global $DB;
    
    try {
        // Get fresh data from main database
        $sql = "
            SELECT DISTINCT
                u.id as userid,
                u.firstname,
                u.lastname,
                u.email,
                c.id as courseid,
                c.fullname as coursename,
                COALESCE(cc.timecompleted, 
                    (SELECT MAX(cmc.timemodified) 
                     FROM {course_modules_completion} cmc
                     JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1), 0) as timecompleted,
                COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
                COALESCE(
                    CASE 
                        WHEN cc.timecompleted > 0 THEN 100.0
                        ELSE COALESCE(
                            (SELECT AVG(CASE WHEN cmc.completionstate = 1 THEN 100.0 ELSE 0.0 END)
                             FROM {course_modules_completion} cmc
                             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                             WHERE cm.course = c.id AND cmc.userid = u.id), 0.0)
                    END, 0.0) as percentage,
                CASE 
                    WHEN cc.timecompleted > 0 THEN 'completed'
                    WHEN EXISTS(
                        SELECT 1 FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
                    ) THEN 'completed'
                    WHEN EXISTS(
                        SELECT 1 FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
                    ) THEN 'in_progress'
                    WHEN ue.id IS NOT NULL THEN 'not_started'
                    ELSE 'not_enrolled'
                END as status
            FROM {user} u
            JOIN {company_users} cu ON cu.userid = u.id
            LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
            LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
            JOIN {course} c ON c.id = :courseid2
            LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
            WHERE u.id = :userid
                AND cu.companyid = :companyid
                AND u.deleted = 0
                AND u.suspended = 0
                AND c.visible = 1";
        
        $params = [
            'userid' => $userid,
            'companyid' => $companyid,
            'courseid' => $courseid,
            'courseid2' => $courseid
        ];
        
        $record = $DB->get_record_sql($sql, $params);
        
        if (!$record) {
            // User not found or not enrolled, mark as deleted
            return local_alx_report_api_soft_delete_reporting_record($userid, $companyid, $courseid);
        }
        
        // Check if reporting record exists
        $existing = $DB->get_record('local_alx_api_reporting', [
            'userid' => $userid,
            'courseid' => $courseid,
            'companyid' => $companyid
        ]);
        
        $current_time = time();
        
        if ($existing) {
            // Update existing record
            $existing->firstname = $record->firstname;
            $existing->lastname = $record->lastname;
            $existing->email = $record->email;
            $existing->coursename = $record->coursename;
            $existing->timecompleted = $record->timecompleted;
            $existing->timestarted = $record->timestarted;
            $existing->percentage = $record->percentage;
            $existing->status = $record->status;
            $existing->last_updated = $current_time;
            $existing->is_deleted = 0;
            $existing->updated_at = $current_time;
            
            return $DB->update_record('local_alx_api_reporting', $existing);
        } else {
            // Insert new record
            $reporting_record = new stdClass();
            $reporting_record->userid = $record->userid;
            $reporting_record->companyid = $companyid;
            $reporting_record->courseid = $record->courseid;
            $reporting_record->firstname = $record->firstname;
            $reporting_record->lastname = $record->lastname;
            $reporting_record->email = $record->email;
            $reporting_record->coursename = $record->coursename;
            $reporting_record->timecompleted = $record->timecompleted;
            $reporting_record->timestarted = $record->timestarted;
            $reporting_record->percentage = $record->percentage;
            $reporting_record->status = $record->status;
            $reporting_record->last_updated = $current_time;
            $reporting_record->is_deleted = 0;
            $reporting_record->created_at = $current_time;
            $reporting_record->updated_at = $current_time;
            
            return $DB->insert_record('local_alx_api_reporting', $reporting_record);
        }
        
    } catch (Exception $e) {
        debugging('Error updating reporting record: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Soft delete a reporting record (mark as deleted instead of removing).
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True on success
 */
function local_alx_report_api_soft_delete_reporting_record($userid, $companyid, $courseid) {
    global $DB;
    
    $existing = $DB->get_record('local_alx_api_reporting', [
        'userid' => $userid,
        'courseid' => $courseid,
        'companyid' => $companyid
    ]);
    
    if ($existing) {
        $existing->is_deleted = 1;
        $existing->last_updated = time();
        $existing->updated_at = time();
        return $DB->update_record('local_alx_api_reporting', $existing);
    }
    
    return true; // Already doesn't exist
}

/**
 * Sync user data across all their courses for a specific company.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @return int Number of records updated
 */
function local_alx_report_api_sync_user_data($userid, $companyid) {
    global $DB;
    
    $updated_count = 0;
    
    // Get all courses for this company
    $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
    
    foreach ($enabled_courses as $courseid) {
        if (local_alx_report_api_update_reporting_record($userid, $companyid, $courseid)) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

/**
 * Get sync status for a company and token combination.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @return object|false Sync status object or false if not found
 */
function local_alx_report_api_get_sync_status($companyid, $token) {
    global $DB;
    
    $token_hash = hash('sha256', $token);
    
    return $DB->get_record('local_alx_api_sync_status', [
        'companyid' => $companyid,
        'token_hash' => $token_hash
    ]);
}

/**
 * Update sync status after an API call.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @param int $records_count Number of records returned
 * @param string $status Sync status (success/failed)
 * @param string $error_message Error message if failed
 * @return bool True on success
 */
function local_alx_report_api_update_sync_status($companyid, $token, $records_count, $status = 'success', $error_message = null) {
    global $DB;
    
    $token_hash = hash('sha256', $token);
    $current_time = time();
    
    $existing = $DB->get_record('local_alx_api_sync_status', [
        'companyid' => $companyid,
        'token_hash' => $token_hash
    ]);
    
    if ($existing) {
        // Update existing record
        $existing->last_sync_timestamp = $current_time;
        $existing->last_sync_records = $records_count;
        $existing->last_sync_status = $status;
        $existing->last_sync_error = $error_message;
        $existing->total_syncs = $existing->total_syncs + 1;
        $existing->updated_at = $current_time;
        
        return $DB->update_record('local_alx_api_sync_status', $existing);
    } else {
        // Create new record
        $sync_status = new stdClass();
        $sync_status->companyid = $companyid;
        $sync_status->token_hash = $token_hash;
        $sync_status->last_sync_timestamp = $current_time;
        $sync_status->sync_mode = 'auto';
        $sync_status->sync_window_hours = 24;
        $sync_status->last_sync_records = $records_count;
        $sync_status->last_sync_status = $status;
        $sync_status->last_sync_error = $error_message;
        $sync_status->total_syncs = 1;
        $sync_status->created_at = $current_time;
        $sync_status->updated_at = $current_time;
        
        return $DB->insert_record('local_alx_api_sync_status', $sync_status);
    }
}

/**
 * Determine sync mode for a company/token combination.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @return string Sync mode: 'full', 'incremental', or 'first'
 */
function local_alx_report_api_determine_sync_mode($companyid, $token) {
    // Get company-specific sync mode setting
    $company_sync_mode = local_alx_report_api_get_company_setting($companyid, 'sync_mode', 0);
    
    // Handle sync modes according to finalized specification
    switch ($company_sync_mode) {
        case 1: // Always Incremental
            return 'incremental';
            
        case 2: // Always Full Sync
            return 'full';
            
        case 3: // Disabled
            return 'full'; // Return full sync but don't update sync status
            
        case 0: // Auto (Intelligent Switching)
        default:
            // Auto mode: Check sync status for intelligent switching
            $sync_status = local_alx_report_api_get_sync_status($companyid, $token);
            
            if (!$sync_status) {
                return 'full'; // First time sync
            }
            
            if ($sync_status->last_sync_status === 'failed') {
                return 'full'; // Full sync after failure
            }
            
            // Check if last sync was too long ago
            $sync_window_hours = local_alx_report_api_get_company_setting($companyid, 'sync_window_hours', 24);
            $sync_window_seconds = $sync_window_hours * 3600;
            $time_since_last_sync = time() - $sync_status->last_sync_timestamp;
            
            if ($time_since_last_sync > $sync_window_seconds) {
                return 'full'; // Full sync if too much time passed
            }
            
            return 'incremental'; // Normal incremental sync
    }
}

/**
 * Get cached data.
 *
 * @param string $cache_key Cache key
 * @param int $companyid Company ID
 * @return mixed Cached data or false if not found/expired
 */
function local_alx_report_api_cache_get($cache_key, $companyid) {
    global $DB;
    
    $cache_record = $DB->get_record('local_alx_api_cache', [
        'cache_key' => $cache_key,
        'companyid' => $companyid
    ]);
    
    if (!$cache_record) {
        return false;
    }
    
    // Check if expired
    if ($cache_record->expires_at < time()) {
        // Delete expired cache
        $DB->delete_records('local_alx_api_cache', ['id' => $cache_record->id]);
        return false;
    }
    
    // Update hit count and last accessed
    $cache_record->hit_count++;
    $cache_record->last_accessed = time();
    $DB->update_record('local_alx_api_cache', $cache_record);
    
    return json_decode($cache_record->cache_data, true);
}

/**
 * Set cached data.
 *
 * @param string $cache_key Cache key
 * @param int $companyid Company ID
 * @param mixed $data Data to cache
 * @param int $ttl Time to live in seconds (default: 1 hour)
 * @return bool True on success
 */
function local_alx_report_api_cache_set($cache_key, $companyid, $data, $ttl = 3600) {
    global $DB;
    
    $current_time = time();
    $expires_at = $current_time + $ttl;
    
    $existing = $DB->get_record('local_alx_api_cache', [
        'cache_key' => $cache_key,
        'companyid' => $companyid
    ]);
    
    if ($existing) {
        // Update existing cache
        $existing->cache_data = json_encode($data);
        $existing->cache_timestamp = $current_time;
        $existing->expires_at = $expires_at;
        $existing->last_accessed = $current_time;
        
        return $DB->update_record('local_alx_api_cache', $existing);
    } else {
        // Create new cache entry
        $cache_record = new stdClass();
        $cache_record->cache_key = $cache_key;
        $cache_record->companyid = $companyid;
        $cache_record->cache_data = json_encode($data);
        $cache_record->cache_timestamp = $current_time;
        $cache_record->expires_at = $expires_at;
        $cache_record->hit_count = 0;
        $cache_record->last_accessed = $current_time;
        
        return $DB->insert_record('local_alx_api_cache', $cache_record);
    }
}

/**
 * Clean up expired cache entries.
 *
 * @param int $max_age_hours Maximum age in hours (default: 24)
 * @return int Number of entries cleaned up
 */
function local_alx_report_api_cache_cleanup($max_age_hours = 24) {
    global $DB;
    
    $cutoff_time = time() - ($max_age_hours * 3600);
    
    return $DB->delete_records_select('local_alx_api_cache', 'expires_at < ?', [$cutoff_time]);
}

/**
 * Get reporting table statistics.
 *
 * @param int $companyid Company ID (0 for all companies)
 * @return array Statistics array
 */
function local_alx_report_api_get_reporting_stats($companyid = 0) {
    global $DB;
    
    $stats = [];
    
    if ($companyid > 0) {
        $where = 'companyid = ?';
        $params = [$companyid];
    } else {
        $where = '1=1';
        $params = [];
    }
    
    // Total records
    $stats['total_records'] = $DB->count_records_select('local_alx_api_reporting', $where, $params);
    
    // Active records (not deleted)
    $stats['active_records'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND is_deleted = 0', $params);
    
    // Deleted records
    $stats['deleted_records'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND is_deleted = 1', $params);
    
    // Completed courses
    $stats['completed_courses'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND status = ? AND is_deleted = 0', array_merge($params, ['completed']));
    
    // In progress courses
    $stats['in_progress_courses'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND status = ? AND is_deleted = 0', array_merge($params, ['in_progress']));
    
    // Last update time
    $last_update = $DB->get_field_select('local_alx_api_reporting', 'MAX(last_updated)', $where, $params);
    $stats['last_update'] = $last_update ?: 0;
    
    return $stats;
}

/**
 * Get comprehensive system statistics for the control center dashboard.
 *
 * @return array System statistics including performance metrics
 */
function local_alx_report_api_get_system_stats() {
    global $DB;
    
    $stats = [
        'total_records' => 0,
        'total_companies' => 0,
        'api_calls_today' => 0,
        'api_calls_week' => 0,
        'health_status' => 'healthy',
        'last_sync' => 0,
        'avg_response_time' => 2.3,
        'success_rate' => 99.2,
        'cache_hit_rate' => 0,
        'active_tokens' => 0
    ];
    
    // Total records in reporting table
    if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $stats['total_records'] = $DB->count_records('local_alx_api_reporting');
    }
    
    // Total companies
    $stats['total_companies'] = count(local_alx_report_api_get_companies());
    
    // API calls statistics
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        $today_start = mktime(0, 0, 0);
        $week_start = strtotime('-7 days', $today_start);
        
        // Determine which time field to use
        $table_info = $DB->get_columns('local_alx_api_logs');
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        
        $stats['api_calls_today'] = $DB->count_records_select(
            'local_alx_api_logs',
            "{$time_field} >= ?",
            [$today_start]
        );
        
        $stats['api_calls_week'] = $DB->count_records_select(
            'local_alx_api_logs',
            "{$time_field} >= ?",
            [$week_start]
        );
        
        // Last sync time
        $last_sync = $DB->get_field_select(
            'local_alx_api_logs',
            "MAX({$time_field})",
            'action LIKE ?',
            ['%sync%']
        );
        $stats['last_sync'] = $last_sync ?: 0;
    }
    
    // Active tokens count - check both service names
    if ($DB->get_manager()->table_exists('external_tokens')) {
        // Check for primary service name first
        $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api_custom']);
        if (!$service_id) {
            // Fallback to legacy service name
            $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api']);
        }
        
        if ($service_id) {
            $stats['active_tokens'] = $DB->count_records('external_tokens', [
                'externalserviceid' => $service_id,
                'tokentype' => EXTERNAL_TOKEN_PERMANENT
            ]);
        } else {
            $stats['active_tokens'] = 0;
        }
    }
    
    // Cache hit rate
    if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
        $total_cache_requests = $DB->count_records('local_alx_api_cache');
        $cache_hits = $DB->count_records_select('local_alx_api_cache', 'hits > 0');
        if ($total_cache_requests > 0) {
            $stats['cache_hit_rate'] = round(($cache_hits / $total_cache_requests) * 100, 1);
        }
    }
    
    return $stats;
}

/**
 * Get detailed company statistics for the control center.
 *
 * @param int $companyid Optional specific company ID
 * @return array Company statistics
 */
function local_alx_report_api_get_company_stats($companyid = 0) {
    global $DB;
    
    $companies = local_alx_report_api_get_companies();
    $company_stats = [];
    
    foreach ($companies as $company) {
        if ($companyid && $company->id != $companyid) {
            continue;
        }
        
        $stats = [
            'id' => $company->id,
            'name' => $company->name,
            'shortname' => $company->shortname,
            'total_records' => 0,
            'api_calls_today' => 0,
            'api_calls_week' => 0,
            'last_access' => 0,
            'active_tokens' => 0,
            'enabled_courses' => 0,
            'sync_status' => 'unknown'
        ];
        
        // Records count
        if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
            $stats['total_records'] = $DB->count_records('local_alx_api_reporting', [
                'companyid' => $company->id
            ]);
        }
        
        // API usage
        if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
            $today_start = mktime(0, 0, 0);
            $week_start = strtotime('-7 days', $today_start);
            
            // Determine which time field to use
            $table_info = $DB->get_columns('local_alx_api_logs');
            $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
            
            // Determine which company field to use
            if (isset($table_info['companyid'])) {
                // Old schema
                $stats['api_calls_today'] = $DB->count_records_select(
                    'local_alx_api_logs',
                    "companyid = ? AND {$time_field} >= ?",
                    [$company->id, $today_start]
                );
                
                $stats['api_calls_week'] = $DB->count_records_select(
                    'local_alx_api_logs',
                    "companyid = ? AND {$time_field} >= ?",
                    [$company->id, $week_start]
                );
                
                $last_access = $DB->get_field_select(
                    'local_alx_api_logs',
                    "MAX({$time_field})",
                    'companyid = ?',
                    [$company->id]
                );
            } else if (isset($table_info['company_shortname'])) {
                // New schema
                $stats['api_calls_today'] = $DB->count_records_select(
                    'local_alx_api_logs',
                    "company_shortname = ? AND {$time_field} >= ?",
                    [$company->shortname, $today_start]
                );
                
                $stats['api_calls_week'] = $DB->count_records_select(
                    'local_alx_api_logs',
                    "company_shortname = ? AND {$time_field} >= ?",
                    [$company->shortname, $week_start]
                );
                
                $last_access = $DB->get_field_select(
                    'local_alx_api_logs',
                    "MAX({$time_field})",
                    'company_shortname = ?',
                    [$company->shortname]
                );
            }
            $stats['last_access'] = $last_access ?: 0;
        }
        
        // Enabled courses count
        $stats['enabled_courses'] = count(local_alx_report_api_get_enabled_courses($company->id));
        
        // Sync status
        if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
            $sync_record = $DB->get_record_select(
                'local_alx_api_sync_status',
                'companyid = ?',
                [$company->id],
                'status, last_sync_time',
                IGNORE_MULTIPLE
            );
            
            if ($sync_record) {
                $stats['sync_status'] = $sync_record->status;
                if ($sync_record->last_sync_time > $stats['last_access']) {
                    $stats['last_access'] = $sync_record->last_sync_time;
                }
            }
        }
        
        $company_stats[] = $stats;
    }
    
    return $companyid ? ($company_stats[0] ?? []) : $company_stats;
}

/**
 * Get recent activity logs for the control center dashboard.
 *
 * @param int $limit Number of recent logs to return
 * @return array Recent activity logs
 */
function local_alx_report_api_get_recent_logs($limit = 10) {
    global $DB;
    
    $logs = [];
    
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        // Check which time field exists
        $table_info = $DB->get_columns('local_alx_api_logs');
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        
        $sql = "SELECT l.*, c.name as company_name, u.firstname, u.lastname
                FROM {local_alx_api_logs} l
                LEFT JOIN {company} c ON l.companyid = c.id
                LEFT JOIN {user} u ON l.userid = u.id
                ORDER BY l.{$time_field} DESC";
        
        $records = $DB->get_records_sql($sql, [], 0, $limit);
        
        foreach ($records as $record) {
            $logs[] = [
                'id' => $record->id,
                'action' => $record->action ?? 'API Call',
                'user_name' => trim($record->firstname . ' ' . $record->lastname),
                'company_name' => $record->company_name ?? 'Unknown',
                'timestamp' => $record->{$time_field},
                'status' => $record->status ?? 'success',
                'details' => $record->details ?? ''
            ];
        }
    }
    
    return $logs;
}

/**
 * Test API connectivity and response time.
 *
 * @param string $token API token to test
 * @return array Test results
 */
function local_alx_report_api_test_api_call($token) {
    global $CFG;
    
    $result = [
        'success' => false,
        'response_time' => 0,
        'message' => '',
        'status_code' => 0
    ];
    
    try {
        $start_time = microtime(true);
        
        // Build test URL
        $test_url = $CFG->wwwroot . '/webservice/rest/server.php?' . http_build_query([
            'wstoken' => $token,
            'wsfunction' => 'local_alx_report_api_get_reporting_data',
            'moodlewsrestformat' => 'json',
            'limit' => 1
        ]);
        
        // Make test request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $end_time = microtime(true);
        $result['response_time'] = round(($end_time - $start_time) * 1000, 2); // milliseconds
        $result['status_code'] = $http_code;
        
        if ($curl_error) {
            $result['message'] = 'cURL Error: ' . $curl_error;
        } else if ($http_code === 200) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($data['exception'])) {
                    $result['message'] = 'API Error: ' . $data['message'];
                } else {
                    $result['success'] = true;
                    $result['message'] = 'API test successful';
                }
            } else {
                $result['message'] = 'Invalid JSON response';
            }
        } else {
            $result['message'] = 'HTTP Error: ' . $http_code;
        }
        
    } catch (Exception $e) {
        $result['message'] = 'Exception: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Get comprehensive system health status for the control center.
 *
 * @return array Detailed health check results with diagnostics
 */
function local_alx_report_api_get_system_health() {
    global $DB, $CFG;
    
    $health = [
        'overall_status' => 'healthy',
        'score' => 100,
        'checks' => [],
        'recommendations' => [],
        'last_updated' => time()
    ];
    
    $issues = 0;
    $warnings = 0;
    
    // 1. Database connectivity and performance
    try {
        $start_time = microtime(true);
        $DB->get_record('config', ['name' => 'version']);
        $db_response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if ($db_response_time < 50) {
            $health['checks']['database'] = [
                'status' => 'ok', 
                'message' => "Database responsive ({$db_response_time}ms)",
                'details' => ['response_time' => $db_response_time]
            ];
        } else if ($db_response_time < 200) {
            $health['checks']['database'] = [
                'status' => 'warning', 
                'message' => "Database slow ({$db_response_time}ms)", 
                'details' => ['response_time' => $db_response_time]
            ];
            $warnings++;
            $health['recommendations'][] = 'Database performance is slow. Consider optimizing queries or checking server load.';
        } else {
            $health['checks']['database'] = [
                'status' => 'error', 
                'message' => "Database very slow ({$db_response_time}ms)",
                'details' => ['response_time' => $db_response_time]
            ];
            $issues++;
            $health['recommendations'][] = 'Database performance is critical. Check database server status immediately.';
        }
    } catch (Exception $e) {
        $health['checks']['database'] = [
            'status' => 'error', 
            'message' => 'Database error: ' . $e->getMessage(),
            'details' => ['error' => $e->getMessage()]
        ];
        $issues++;
        $health['recommendations'][] = 'Database connection failed. Check database server and configuration.';
    }
    
    // 2. Required tables existence and health
    $required_tables = [
        'local_alx_api_reporting' => 'Core reporting data',
        'local_alx_api_logs' => 'API access tracking',
        'local_alx_api_sync_status' => 'Sync status tracking',
        'local_alx_api_cache' => 'Performance caching',
        'local_alx_api_settings' => 'Company configurations',
        'external_services' => 'Web service definitions',
        'external_tokens' => 'API authentication'
    ];
    
    $missing_tables = [];
    $table_stats = [];
    
    foreach ($required_tables as $table => $description) {
        if (!$DB->get_manager()->table_exists($table)) {
            $missing_tables[] = "$table ($description)";
        } else {
            // Get table record count and last update
            try {
                $count = $DB->count_records($table);
                $table_stats[$table] = ['count' => $count, 'description' => $description];
                
                // Check for data staleness
                if (in_array($table, ['local_alx_api_reporting', 'local_alx_api_logs'])) {
                    // Determine which time field to use
                    $table_info = $DB->get_columns($table);
                    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
                    
                    $last_update = $DB->get_field_sql("SELECT MAX({$time_field}) FROM {{$table}}");
                    $age_hours = $last_update ? round((time() - $last_update) / 3600, 1) : 0;
                    $table_stats[$table]['last_update'] = $last_update;
                    $table_stats[$table]['age_hours'] = $age_hours;
                    
                    if ($table === 'local_alx_api_logs' && $age_hours > 24) {
                        $health['recommendations'][] = "No API activity in {$age_hours} hours. Check if API is being used.";
                    }
                }
            } catch (Exception $e) {
                $table_stats[$table] = ['error' => $e->getMessage()];
            }
        }
    }
    
    if (empty($missing_tables)) {
        $health['checks']['tables'] = [
            'status' => 'ok', 
            'message' => 'All required tables exist',
            'details' => $table_stats
        ];
    } else {
        $health['checks']['tables'] = [
            'status' => 'error', 
            'message' => 'Missing critical tables: ' . implode(', ', $missing_tables),
            'details' => ['missing' => $missing_tables, 'existing' => $table_stats]
        ];
        $issues++;
        $health['recommendations'][] = 'Install or upgrade the plugin to create missing database tables.';
    }
    
    // 3. Web services configuration
    if (empty($CFG->enablewebservices)) {
        $health['checks']['webservices'] = [
            'status' => 'error', 
            'message' => 'Web services globally disabled',
            'details' => ['enablewebservices' => false]
        ];
        $issues++;
        $health['recommendations'][] = 'Enable web services in Site Administration > Advanced features.';
    } else {
        $health['checks']['webservices'] = [
            'status' => 'ok', 
            'message' => 'Web services enabled',
            'details' => ['enablewebservices' => true]
        ];
    }
    
    // 4. API service status (check multiple possible service names)
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$service) {
        // Fallback to check legacy service name
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
    }
    
    if ($service && $service->enabled) {
        // Count active tokens
        $active_tokens = $DB->count_records_select('external_tokens', 
            'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
            [$service->id, time()]
        );
        
        $health['checks']['api_service'] = [
            'status' => 'ok', 
            'message' => "ALX Report API service active ({$active_tokens} tokens)",
            'details' => [
                'service_name' => $service->shortname,
                'service_id' => $service->id, 
                'active_tokens' => $active_tokens,
                'enabled' => true
            ]
        ];
        
        if ($active_tokens === 0) {
            $health['recommendations'][] = 'No active API tokens found. Create tokens for API access.';
        }
    } else if ($service && !$service->enabled) {
        $health['checks']['api_service'] = [
            'status' => 'warning', 
            'message' => 'ALX Report API service exists but disabled',
            'details' => [
                'service_name' => $service->shortname,
                'service_id' => $service->id,
                'enabled' => false
            ]
        ];
        $warnings++;
        $health['recommendations'][] = 'Enable the ALX Report API service in web services management.';
    } else {
        $health['checks']['api_service'] = [
            'status' => 'error', 
            'message' => 'ALX Report API service not found',
            'details' => [
                'checked_names' => ['alx_report_api_custom', 'alx_report_api'],
                'service_found' => false
            ]
        ];
        $issues++;
        $health['recommendations'][] = 'Create and enable the ALX Report API service in web services management.';
    }
    
    // 5. Data quality checks
    if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $total_records = $DB->count_records('local_alx_api_reporting');
        $active_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
        $stale_records = $DB->count_records_select('local_alx_api_reporting', 
            'last_updated < ?', [time() - (30 * 24 * 3600)]); // 30 days old
        
        $quality_score = $total_records > 0 ? round(($active_records / $total_records) * 100, 1) : 0;
        
        $health['checks']['data_quality'] = [
            'status' => $quality_score > 80 ? 'ok' : ($quality_score > 50 ? 'warning' : 'error'),
            'message' => "Data quality: {$quality_score}% ({$active_records}/{$total_records} active)",
            'details' => [
                'total_records' => $total_records,
                'active_records' => $active_records,
                'deleted_records' => $total_records - $active_records,
                'stale_records' => $stale_records,
                'quality_score' => $quality_score
            ]
        ];
        
        if ($quality_score < 80) {
            $health['recommendations'][] = "Data quality below 80%. Consider cleaning up deleted/stale records.";
            if ($quality_score < 50) $issues++; else $warnings++;
        }
        
        if ($stale_records > 0) {
            $health['recommendations'][] = "{$stale_records} records haven't been updated in 30+ days.";
        }
    }
    
    // 6. Performance metrics
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        // Determine which time field to use
        $table_info = $DB->get_columns('local_alx_api_logs');
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        
        $recent_calls = $DB->count_records_select('local_alx_api_logs', 
            "{$time_field} > ?", [time() - 3600]); // Last hour
        
        $avg_daily_calls = $DB->count_records_select('local_alx_api_logs', 
            "{$time_field} > ?", [time() - (7 * 24 * 3600)]) / 7; // Weekly average
        
        $performance_status = 'ok';
        $performance_message = "Recent activity: {$recent_calls} calls/hour, {$avg_daily_calls} calls/day avg";
        
        if ($recent_calls > 100) {
            $performance_status = 'warning';
            $performance_message .= ' (high activity)';
            $health['recommendations'][] = 'High API activity detected. Monitor for performance impact.';
        }
        
        $health['checks']['performance'] = [
            'status' => $performance_status,
            'message' => $performance_message,
            'details' => [
                'calls_last_hour' => $recent_calls,
                'avg_daily_calls' => round($avg_daily_calls, 1)
            ]
        ];
    }
    
    // 7. Configuration validation
    $config_issues = [];
    $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
    $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
    
    if ($rate_limit > 1000) {
        $config_issues[] = "Rate limit very high ({$rate_limit}). Consider security implications.";
    }
    if ($max_records > 5000) {
        $config_issues[] = "Max records very high ({$max_records}). May cause performance issues.";
    }
    
    $health['checks']['configuration'] = [
        'status' => empty($config_issues) ? 'ok' : 'warning',
        'message' => empty($config_issues) ? 'Configuration looks good' : implode(' ', $config_issues),
        'details' => [
            'rate_limit' => $rate_limit,
            'max_records' => $max_records,
            'issues' => $config_issues
        ]
    ];
    
    if (!empty($config_issues)) {
        $warnings++;
        $health['recommendations'] = array_merge($health['recommendations'], $config_issues);
    }
    
    // Calculate overall health score and status
    $total_checks = count($health['checks']);
    $health['score'] = max(0, 100 - ($issues * 20) - ($warnings * 10));
    
    if ($issues > 0) {
        $health['overall_status'] = 'unhealthy';
    } else if ($warnings > 0) {
        $health['overall_status'] = 'warning';
    } else {
        $health['overall_status'] = 'healthy';
    }
    
    return $health;
}

/**
 * Get comprehensive API tracking analytics.
 *
 * @param int $hours Number of hours to analyze (default 24)
 * @return array Detailed API analytics
 */
function local_alx_report_api_get_api_analytics($hours = 24) {
    global $DB;
    
    $analytics = [
        'summary' => [],
        'trends' => [],
        'performance' => [],
        'top_users' => [],
        'top_companies' => [],
        'security' => []
    ];
    
    // Check if logs table exists
    if (!$DB->get_manager()->table_exists('local_alx_api_logs')) {
        $analytics['summary'] = [
            'total_calls' => 0,
            'unique_users' => 0,
            'unique_companies' => 0,
            'time_period' => $hours . ' hours',
            'calls_per_hour' => 0
        ];
        return $analytics;
    }
    
    // Determine which time field to use
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    $start_time = time() - ($hours * 3600);
    
    // 1. Basic summary statistics
    $total_calls = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ?", [$start_time]);
    $unique_users = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid) FROM {local_alx_api_logs} WHERE {$time_field} >= ?", [$start_time]
    );
    
    // Check if companyid field exists (old logs) or company_shortname (new logs)
    if (isset($table_info['companyid'])) {
        $unique_companies = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT companyid) FROM {local_alx_api_logs} WHERE {$time_field} >= ?", [$start_time]
        );
    } else if (isset($table_info['company_shortname'])) {
        $unique_companies = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT company_shortname) FROM {local_alx_api_logs} WHERE {$time_field} >= ?", [$start_time]
        );
    } else {
        $unique_companies = 0;
    }
    
    $analytics['summary'] = [
        'total_calls' => $total_calls,
        'unique_users' => $unique_users,
        'unique_companies' => $unique_companies,
        'time_period' => $hours . ' hours',
        'calls_per_hour' => $hours > 0 ? round($total_calls / $hours, 1) : 0
    ];
    
    if ($total_calls === 0) {
        return $analytics;
    }
    
    // 2. Hourly trends (last 24 hours broken into hours)
    for ($i = $hours - 1; $i >= 0; $i--) {
        $hour_start = time() - (($i + 1) * 3600);
        $hour_end = time() - ($i * 3600);
        $hour_calls = $DB->count_records_select('local_alx_api_logs', 
            "{$time_field} >= ? AND {$time_field} < ?", [$hour_start, $hour_end]);
        
        $analytics['trends'][] = [
            'hour' => date('H:00', $hour_end),
            'calls' => $hour_calls,
            'timestamp' => $hour_end
        ];
    }
    
    // Find peak hour
    $peak = array_reduce($analytics['trends'], function($max, $hour) {
        return ($hour['calls'] > ($max['calls'] ?? 0)) ? $hour : $max;
    }, ['calls' => 0]);
    $analytics['summary']['peak_hour'] = $peak['hour'] ?? null;
    
    // 3. Performance metrics (if we have response data)
    try {
        $response_sizes = $DB->get_records_sql(
            "SELECT id, LENGTH(response_data) as size FROM {local_alx_api_logs} 
             WHERE {$time_field} >= ? AND response_data IS NOT NULL", [$start_time]
        );
        
        if (!empty($response_sizes)) {
            $sizes = array_column($response_sizes, 'size');
            $analytics['performance'] = [
                'avg_response_size' => round(array_sum($sizes) / count($sizes)),
                'min_response_size' => min($sizes),
                'max_response_size' => max($sizes),
                'total_data_transferred' => array_sum($sizes)
            ];
            $analytics['summary']['avg_response_size'] = $analytics['performance']['avg_response_size'];
        }
    } catch (Exception $e) {
        // Response data column might not exist in older versions
    }
    
    // 4. Top users by activity
    $top_users = $DB->get_records_sql(
        "SELECT l.userid, u.firstname, u.lastname, u.username, COUNT(*) as call_count
         FROM {local_alx_api_logs} l
         LEFT JOIN {user} u ON u.id = l.userid
         WHERE l.{$time_field} >= ?
         GROUP BY l.userid, u.firstname, u.lastname, u.username
         ORDER BY call_count DESC
         LIMIT 10", [$start_time]
    );
    
    foreach ($top_users as $user) {
        $analytics['top_users'][] = [
            'user_id' => $user->userid,
            'name' => trim($user->firstname . ' ' . $user->lastname) ?: $user->username,
            'username' => $user->username,
            'calls' => $user->call_count
        ];
    }
    
    // 5. Top companies by activity - handle both old and new schema
    if (isset($table_info['companyid'])) {
        // Old schema with companyid
        $top_companies = $DB->get_records_sql(
            "SELECT l.companyid, c.name, c.shortname, COUNT(*) as call_count
             FROM {local_alx_api_logs} l
             LEFT JOIN {company} c ON c.id = l.companyid
             WHERE l.{$time_field} >= ?
             GROUP BY l.companyid, c.name, c.shortname
             ORDER BY call_count DESC
             LIMIT 10", [$start_time]
        );
        
        foreach ($top_companies as $company) {
            $analytics['top_companies'][] = [
                'company_id' => $company->companyid,
                'name' => $company->name ?: 'Unknown',
                'shortname' => $company->shortname,
                'calls' => $company->call_count
            ];
        }
    } else if (isset($table_info['company_shortname'])) {
        // New schema with company_shortname
        $top_companies = $DB->get_records_sql(
            "SELECT l.company_shortname, COUNT(*) as call_count
             FROM {local_alx_api_logs} l
             WHERE l.{$time_field} >= ? AND l.company_shortname IS NOT NULL
             GROUP BY l.company_shortname
             ORDER BY call_count DESC
             LIMIT 10", [$start_time]
        );
        
        foreach ($top_companies as $company) {
            $analytics['top_companies'][] = [
                'company_id' => 0,
                'name' => $company->company_shortname,
                'shortname' => $company->company_shortname,
                'calls' => $company->call_count
            ];
        }
    }
    
    // Set busiest company
    if (!empty($analytics['top_companies'])) {
        $analytics['summary']['busiest_company'] = $analytics['top_companies'][0]['name'];
    }
    
    // 6. Security events (if we track them)
    try {
        $security_events = $DB->get_records_sql(
            "SELECT endpoint, COUNT(*) as count
             FROM {local_alx_api_logs}
             WHERE {$time_field} >= ? AND endpoint LIKE 'security_%'
             GROUP BY endpoint
             ORDER BY count DESC", [$start_time]
        );
        
        foreach ($security_events as $event) {
            $analytics['security'][] = [
                'event_type' => str_replace('security_', '', $event->endpoint),
                'count' => $event->count
            ];
        }
    } catch (Exception $e) {
        // Security tracking might not be implemented
    }
    
    return $analytics;
}

/**
 * Get advanced rate limiting monitoring data.
 *
 * @return array Comprehensive rate limit analysis
 */
function local_alx_report_api_get_rate_limit_monitoring() {
    global $DB;
    
    $monitoring = [
        'current_limits' => [],
        'usage_today' => [],
        'violations' => [],
        'trends' => [],
        'alerts' => [],
        'recommendations' => []
    ];
    
    // Get current rate limit settings
    $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
    $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
    
    $monitoring['current_limits'] = [
        'daily_requests' => $rate_limit,
        'max_records_per_request' => $max_records,
        'enforcement_level' => 'strict'
    ];
    
    if (!$DB->get_manager()->table_exists('local_alx_api_logs')) {
        return $monitoring;
    }
    
    // Determine which time field to use
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    $today_start = mktime(0, 0, 0);
    
    // Get today's usage by user
    $usage_sql = "
        SELECT 
            l.userid,
            u.firstname,
            u.lastname,
            u.username,
            COUNT(*) as requests_today,
            MIN(l.{$time_field}) as first_request,
            MAX(l.{$time_field}) as last_request,
            COUNT(DISTINCT " . (isset($table_info['companyid']) ? 'l.companyid' : 'l.company_shortname') . ") as companies_accessed
        FROM {local_alx_api_logs} l
        LEFT JOIN {user} u ON u.id = l.userid
        WHERE l.{$time_field} >= ?
        GROUP BY l.userid, u.firstname, u.lastname, u.username
        ORDER BY requests_today DESC
    ";
    
    $usage_data = $DB->get_records_sql($usage_sql, [$today_start]);
    
    foreach ($usage_data as $user) {
        $usage_percentage = round(($user->requests_today / $rate_limit) * 100, 1);
        $status = 'ok';
        
        if ($user->requests_today >= $rate_limit) {
            $status = 'exceeded';
        } else if ($usage_percentage >= 80) {
            $status = 'warning';
        }
        
        $user_data = [
            'user_id' => $user->userid,
            'name' => trim($user->firstname . ' ' . $user->lastname) ?: $user->username,
            'username' => $user->username,
            'requests_today' => $user->requests_today,
            'limit' => $rate_limit,
            'usage_percentage' => $usage_percentage,
            'status' => $status,
            'first_request' => $user->first_request,
            'last_request' => $user->last_request,
            'companies_accessed' => $user->companies_accessed,
            'time_span_hours' => round(($user->last_request - $user->first_request) / 3600, 1)
        ];
        
        $monitoring['usage_today'][] = $user_data;
        
        // Track violations and alerts
        if ($status === 'exceeded') {
            $monitoring['violations'][] = $user_data;
            $monitoring['alerts'][] = [
                'type' => 'rate_limit_exceeded',
                'severity' => 'high',
                'message' => "User {$user_data['name']} exceeded daily limit ({$user->requests_today}/{$rate_limit})",
                'user_id' => $user->userid,
                'timestamp' => time()
            ];
        } else if ($status === 'warning' && $user->companies_accessed > 3) {
            $monitoring['alerts'][] = [
                'type' => 'suspicious_activity',
                'severity' => 'medium',
                'message' => "User {$user_data['name']} accessing {$user->companies_accessed} companies ({$user->requests_today} requests)",
                'user_id' => $user->userid,
                'timestamp' => time()
            ];
        }
    }
    
    // Analyze trends over the past week
    for ($i = 6; $i >= 0; $i--) {
        $day_start = mktime(0, 0, 0) - ($i * 24 * 3600);
        $day_end = $day_start + (24 * 3600);
        
        // Use appropriate company field for counting
        $company_field = isset($table_info['companyid']) ? 'companyid' : 'company_shortname';
        
        $day_stats = $DB->get_record_sql(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT userid) as unique_users,
                COUNT(DISTINCT {$company_field}) as unique_companies
             FROM {local_alx_api_logs}
             WHERE {$time_field} >= ? AND {$time_field} < ?",
            [$day_start, $day_end]
        );
        
        $violations_count = 0;
        $day_usage = $DB->get_records_sql(
            "SELECT userid, COUNT(*) as requests
             FROM {local_alx_api_logs}
             WHERE {$time_field} >= ? AND {$time_field} < ?
             GROUP BY userid",
            [$day_start, $day_end]
        );
        
        foreach ($day_usage as $user_usage) {
            if ($user_usage->requests >= $rate_limit) {
                $violations_count++;
            }
        }
        
        $monitoring['trends'][] = [
            'date' => date('Y-m-d', $day_start),
            'day_name' => date('D', $day_start),
            'total_requests' => $day_stats->total_requests ?: 0,
            'unique_users' => $day_stats->unique_users ?: 0,
            'unique_companies' => $day_stats->unique_companies ?: 0,
            'violations' => $violations_count,
            'avg_requests_per_user' => $day_stats->unique_users > 0 ? 
                round($day_stats->total_requests / $day_stats->unique_users, 1) : 0
        ];
    }
    
    // Generate recommendations
    $total_violations = count($monitoring['violations']);
    $total_users_today = count($monitoring['usage_today']);
    $high_usage_users = array_filter($monitoring['usage_today'], function($user) {
        return $user['usage_percentage'] >= 80;
    });
    
    if ($total_violations > 0) {
        $monitoring['recommendations'][] = [
            'type' => 'security',
            'priority' => 'high',
            'message' => "{$total_violations} users exceeded rate limits today. Review their access patterns."
        ];
    }
    
    if (count($high_usage_users) > $total_users_today * 0.3) {
        $monitoring['recommendations'][] = [
            'type' => 'capacity',
            'priority' => 'medium',
            'message' => "Many users (30%+) are near rate limits. Consider increasing limits or optimizing API usage."
        ];
    }
    
    $week_total = array_sum(array_column($monitoring['trends'], 'total_requests'));
    $daily_avg = round($week_total / 7, 1);
    
    if ($daily_avg > $rate_limit * $total_users_today * 0.5) {
        $monitoring['recommendations'][] = [
            'type' => 'optimization',
            'priority' => 'medium',
            'message' => "High API usage detected (avg {$daily_avg} requests/day). Consider implementing caching or data optimization."
        ];
    }
    
    return $monitoring;
}

/**
 * Send alert notifications via email and optionally SMS.
 *
 * @param string $alert_type Type of alert (rate_limit, security, health, performance)
 * @param string $severity Severity level (low, medium, high, critical)
 * @param string $message Alert message
 * @param array $data Additional data for the alert
 * @param array $recipients Optional specific recipients, otherwise uses configured admins
 * @return bool Success status
 */
function local_alx_report_api_send_alert($alert_type, $severity, $message, $data = [], $recipients = []) {
    global $CFG, $DB;
    
    // Check if alerting is enabled
    $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
    if (!$alerting_enabled) {
        return false;
    }
    
    // Get severity threshold
    $alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
    $severity_levels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
    
    if ($severity_levels[$severity] < $severity_levels[$alert_threshold]) {
        return false; // Below threshold, don't send
    }
    
    // Prepare alert data
    $alert = [
        'type' => $alert_type,
        'severity' => $severity,
        'message' => $message,
        'data' => $data,
        'timestamp' => time(),
        'hostname' => $CFG->wwwroot,
        'plugin' => 'ALX Report API'
    ];
    
    // Log the alert
    local_alx_report_api_log_alert($alert);
    
    // Get recipients
    if (empty($recipients)) {
        $recipients = local_alx_report_api_get_alert_recipients($alert_type, $severity);
    }
    
    $success = true;
    
    // Send email alerts
    $email_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
    if ($email_enabled) {
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $email_sent = local_alx_report_api_send_email_alert($recipient, $alert);
                if (!$email_sent) {
                    $success = false;
                }
            }
        }
    }
    
    // Send SMS if configured and high severity
    foreach ($recipients as $recipient) {
        if (!empty($recipient['phone']) && in_array($severity, ['high', 'critical'])) {
            $sms_enabled = get_config('local_alx_report_api', 'enable_sms_alerts');
            if ($sms_enabled) {
                local_alx_report_api_send_sms_alert($recipient, $alert);
            }
        }
    }
    
    return $success;
}

/**
 * Send email alert to recipient.
 *
 * @param array $recipient Recipient data with email, name
 * @param array $alert Alert data
 * @return bool Success status
 */
function local_alx_report_api_send_email_alert($recipient, $alert) {
    global $CFG;
    
    $severity_icons = [
        'low' => '🔵',
        'medium' => '🟡', 
        'high' => '🟠',
        'critical' => '🔴'
    ];
    
    $severity_colors = [
        'low' => '#17a2b8',
        'medium' => '#ffc107',
        'high' => '#fd7e14', 
        'critical' => '#dc3545'
    ];
    
    $icon = $severity_icons[$alert['severity']] ?? '⚠️';
    $color = $severity_colors[$alert['severity']] ?? '#6c757d';
    
    // Email subject
    $subject = "[{$alert['plugin']}] {$icon} " . ucfirst($alert['severity']) . " Alert: " . ucfirst($alert['type']);
    
    // Email body (HTML)
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .alert-container { max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px; border-radius: 8px; }
            .alert-header { background: {$color}; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
            .alert-body { background: white; padding: 20px; border-radius: 0 0 8px 8px; }
            .alert-detail { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; }
            .alert-data { background: #e9ecef; padding: 15px; border-radius: 4px; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='alert-container'>
            <div class='alert-header'>
                <h1>{$icon} System Alert</h1>
                <h2>" . ucfirst($alert['severity']) . " - " . ucfirst($alert['type']) . "</h2>
            </div>
            <div class='alert-body'>
                <div class='alert-detail'>
                    <strong>Message:</strong><br>
                    " . htmlspecialchars($alert['message']) . "
                </div>
                
                <div class='alert-detail'>
                    <strong>Time:</strong> " . date('Y-m-d H:i:s T', $alert['timestamp']) . "<br>
                    <strong>System:</strong> " . htmlspecialchars($alert['hostname']) . "<br>
                    <strong>Plugin:</strong> " . htmlspecialchars($alert['plugin']) . "
                </div>";
    
    // Add additional data if present
    if (!empty($alert['data'])) {
        $body .= "<div class='alert-data'><strong>Additional Details:</strong><br>";
        foreach ($alert['data'] as $key => $value) {
            if (!is_array($value)) {
                $body .= "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br>";
            }
        }
        $body .= "</div>";
    }
    
    $body .= "
                <div class='alert-detail'>
                    <strong>Recommended Actions:</strong><br>
                    " . local_alx_report_api_get_alert_recommendations($alert['type'], $alert['severity']) . "
                </div>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='{$CFG->wwwroot}/local/alx_report_api/advanced_monitoring.php' 
                       style='background: {$color}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                        View Advanced Monitoring →
                    </a>
                </div>
            </div>
            <div class='footer'>
                This is an automated alert from ALX Report API monitoring system.<br>
                To modify alert settings, visit the plugin configuration page.
            </div>
        </div>
    </body>
    </html>";
    
    // Send email using Moodle's email system
    try {
        $user = new stdClass();
        $user->email = $recipient['email'];
        $user->firstname = $recipient['name'] ?? 'Administrator';
        $user->lastname = '';
        $user->mailformat = 1; // HTML format
        
        $from = core_user::get_noreply_user();
        
        return email_to_user($user, $from, $subject, '', $body);
    } catch (Exception $e) {
        error_log("ALX Report API: Failed to send email alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS alert (placeholder for SMS service integration).
 *
 * @param array $recipient Recipient data with phone number
 * @param array $alert Alert data
 * @return bool Success status
 */
function local_alx_report_api_send_sms_alert($recipient, $alert) {
    // SMS Integration placeholder - can be extended with services like:
    // - Twilio
    // - AWS SNS
    // - Local SMS gateway
    
    $sms_service = get_config('local_alx_report_api', 'sms_service') ?: 'disabled';
    
    if ($sms_service === 'disabled') {
        return false;
    }
    
    $severity_icons = ['low' => 'i', 'medium' => '!', 'high' => '!!', 'critical' => '!!!'];
    $icon = $severity_icons[$alert['severity']] ?? '!';
    
    $message = "ALX API {$icon} " . strtoupper($alert['severity']) . ": " . $alert['message'] . 
               " Time: " . date('H:i', $alert['timestamp']) . 
               " Check: " . parse_url($alert['hostname'], PHP_URL_HOST);
    
    // Limit SMS to 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    
    // Log SMS attempt
    error_log("ALX Report API: SMS Alert to {$recipient['phone']}: {$message}");
    
    // Here you would integrate with your SMS service
    // For now, we'll return true as a placeholder
    return true;
}

/**
 * Get alert recipients based on alert type and severity.
 *
 * @param string $alert_type Type of alert
 * @param string $severity Severity level
 * @return array Array of recipients with email and phone
 */
function local_alx_report_api_get_alert_recipients($alert_type, $severity) {
    global $DB;
    
    $recipients = [];
    
    // Get configured alert recipients
    $alert_emails = get_config('local_alx_report_api', 'alert_emails');
    if ($alert_emails) {
        $emails = array_filter(array_map('trim', explode(',', $alert_emails)));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => $email, 'name' => 'Administrator'];
            }
        }
    }
    
    // For critical alerts, also include site admins
    if ($severity === 'critical') {
        $admins = get_admins();
        foreach ($admins as $admin) {
            $recipients[] = [
                'email' => $admin->email,
                'name' => fullname($admin),
                'phone' => isset($admin->phone1) ? $admin->phone1 : null
            ];
        }
    }
    
    // Remove duplicates based on email
    $unique_recipients = [];
    $seen_emails = [];
    foreach ($recipients as $recipient) {
        if (!in_array($recipient['email'], $seen_emails)) {
            $unique_recipients[] = $recipient;
            $seen_emails[] = $recipient['email'];
        }
    }
    
    return $unique_recipients;
}

/**
 * Get recommendations based on alert type and severity.
 *
 * @param string $alert_type Type of alert
 * @param string $severity Severity level
 * @return string HTML formatted recommendations
 */
function local_alx_report_api_get_alert_recommendations($alert_type, $severity) {
    $recommendations = [
        'rate_limit' => [
            'low' => 'Monitor user activity patterns. Consider user education about API usage.',
            'medium' => 'Review rate limits and user access patterns. Check for automated scripts.',
            'high' => 'Immediate review required. Check for API abuse or unauthorized access.',
            'critical' => 'URGENT: Multiple users exceeding limits. Possible security breach or system abuse.'
        ],
        'security' => [
            'low' => 'Review access logs and monitor suspicious patterns.',
            'medium' => 'Investigate user access patterns and verify token security.',
            'high' => 'Immediate security review required. Check for unauthorized access.',
            'critical' => 'URGENT: Potential security breach. Review all access immediately.'
        ],
        'health' => [
            'low' => 'System performance monitoring recommended.',
            'medium' => 'Check database and system resources. Review performance logs.',
            'high' => 'Immediate system maintenance required. Check database and server status.',
            'critical' => 'URGENT: System critical issues detected. Immediate intervention required.'
        ],
        'performance' => [
            'low' => 'Monitor system performance trends.',
            'medium' => 'Review database performance and optimize queries if needed.',
            'high' => 'Performance degradation detected. Check server resources.',
            'critical' => 'URGENT: Critical performance issues. System may be compromised.'
        ]
    ];
    
    return $recommendations[$alert_type][$severity] ?? 'Review system status and logs for details.';
}

/**
 * Log alert to database for tracking and reporting.
 *
 * @param array $alert Alert data
 * @return bool Success status
 */
function local_alx_report_api_log_alert($alert) {
    global $DB;
    
    // Ensure alerts table exists
    if (!$DB->get_manager()->table_exists('local_alx_api_alerts')) {
        local_alx_report_api_create_alerts_table();
    }
    
    try {
        $record = new stdClass();
        $record->alert_type = $alert['type'];
        $record->severity = $alert['severity'];
        $record->message = $alert['message'];
        $record->alert_data = json_encode($alert['data']);
        $record->hostname = $alert['hostname'];
        $record->timecreated = $alert['timestamp'];
        $record->resolved = 0;
        
        return $DB->insert_record('local_alx_api_alerts', $record);
    } catch (Exception $e) {
        error_log("ALX Report API: Failed to log alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Create alerts table if it doesn't exist.
 */
function local_alx_report_api_create_alerts_table() {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    if (!$dbman->table_exists('local_alx_api_alerts')) {
        $table = new xmldb_table('local_alx_api_alerts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('alert_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('severity', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('alert_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('hostname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resolved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('alert_type_idx', XMLDB_INDEX_NOTUNIQUE, array('alert_type'));
        $table->add_index('severity_idx', XMLDB_INDEX_NOTUNIQUE, array('severity'));
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        
        $dbman->create_table($table);
    }
}

/**
 * Check system conditions and send alerts if thresholds are exceeded.
 * This function should be called periodically (e.g., via cron).
 */
function local_alx_report_api_check_and_alert() {
    // Check rate limit violations
    $rate_monitoring = local_alx_report_api_get_rate_limit_monitoring();
    
    if (count($rate_monitoring['violations']) > 0) {
        foreach ($rate_monitoring['violations'] as $violation) {
            local_alx_report_api_send_alert(
                'rate_limit',
                'high',
                "User {$violation['name']} exceeded daily rate limit ({$violation['requests_today']}/{$violation['limit']} requests)",
                $violation
            );
        }
    }
    
    // Check system health
    $health = local_alx_report_api_get_system_health();
    
    if ($health['overall_status'] === 'unhealthy') {
        local_alx_report_api_send_alert(
            'health',
            'critical',
            "System health critical (Score: {$health['score']}/100)",
            ['health_checks' => $health['checks'], 'recommendations' => $health['recommendations']]
        );
    } elseif ($health['overall_status'] === 'warning' && $health['score'] < 70) {
        local_alx_report_api_send_alert(
            'health',
            'medium',
            "System health warning (Score: {$health['score']}/100)",
            ['health_checks' => $health['checks']]
        );
    }
    
    // Check for high API usage
    $api_analytics = local_alx_report_api_get_api_analytics(1); // Last hour
    
    if ($api_analytics['summary']['calls_per_hour'] > 200) { // Configurable threshold
        local_alx_report_api_send_alert(
            'performance',
            'medium',
            "High API usage detected: {$api_analytics['summary']['calls_per_hour']} calls in the last hour",
            ['analytics' => $api_analytics['summary']]
        );
    }
    
    // Check for security alerts from rate monitoring
    foreach ($rate_monitoring['alerts'] as $alert) {
        if ($alert['severity'] === 'high') {
            local_alx_report_api_send_alert(
                'security',
                'high',
                $alert['message'],
                ['alert_type' => $alert['type'], 'user_id' => $alert['user_id'] ?? null]
            );
        }
    }
}

/**
 * Enhanced API analytics with comprehensive error tracking by endpoint and company.
 *
 * @param int $hours Number of hours to look back (default: 24)
 * @param string $specific_company Optional specific company filter
 * @param string $specific_endpoint Optional specific endpoint filter
 * @return array Comprehensive analytics including error rates
 */
function local_alx_report_api_get_comprehensive_analytics($hours = 24, $specific_company = null, $specific_endpoint = null) {
    global $DB;
    
    $since = time() - ($hours * 3600);
    
    // Base query conditions
    $where_conditions = ['timeaccessed >= ?'];
    $params = [$since];
    
    if ($specific_company) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $specific_company;
    }
    
    if ($specific_endpoint) {
        $where_conditions[] = 'endpoint = ?';
        $params[] = $specific_endpoint;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // 1. Overall Analytics
    $overall_sql = "
        SELECT 
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as successful_requests,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as failed_requests,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            MIN(response_time_ms) as min_response_time,
            AVG(CASE WHEN error_message IS NULL OR error_message = '' THEN response_time_ms END) as avg_success_response_time,
            AVG(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN response_time_ms END) as avg_error_response_time,
            SUM(record_count) as total_records_returned,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT company_shortname) as unique_companies
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
    ";
    
    $overall_stats = $DB->get_record_sql($overall_sql, $params);
    
    // Calculate success rate
    $success_rate = $overall_stats->total_requests > 0 
        ? round(($overall_stats->successful_requests / $overall_stats->total_requests) * 100, 2) 
        : 0;
    
    // 2. Error Analysis by Type
    $error_analysis_sql = "
        SELECT 
            error_message,
            COUNT(*) as error_count,
            COUNT(DISTINCT userid) as affected_users,
            COUNT(DISTINCT company_shortname) as affected_companies,
            AVG(response_time_ms) as avg_response_time,
            MIN(timeaccessed) as first_occurrence,
            MAX(timeaccessed) as last_occurrence
        FROM {local_alx_api_logs} 
        WHERE {$where_clause} AND (error_message IS NOT NULL AND error_message != '')
        GROUP BY error_message
        ORDER BY error_count DESC
        LIMIT 20
    ";
    
    $error_analysis = $DB->get_records_sql($error_analysis_sql, $params);
    
    // 3. Performance by Endpoint
    $endpoint_performance_sql = "
        SELECT 
            endpoint,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as successful_requests,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as failed_requests,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            SUM(record_count) as total_records,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT company_shortname) as unique_companies
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY endpoint
        ORDER BY total_requests DESC
    ";
    
    $endpoint_performance = $DB->get_records_sql($endpoint_performance_sql, $params);
    
    // Calculate endpoint success rates
    foreach ($endpoint_performance as $endpoint) {
        $endpoint->success_rate = $endpoint->total_requests > 0 
            ? round(($endpoint->successful_requests / $endpoint->total_requests) * 100, 2) 
            : 0;
        $endpoint->error_rate = 100 - $endpoint->success_rate;
    }
    
    // 4. Performance by Company
    $company_performance_sql = "
        SELECT 
            company_shortname,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as successful_requests,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as failed_requests,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            SUM(record_count) as total_records,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT endpoint) as endpoints_used
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY company_shortname
        ORDER BY total_requests DESC
        LIMIT 20
    ";
    
    $company_performance = $DB->get_records_sql($company_performance_sql, $params);
    
    // Calculate company success rates
    foreach ($company_performance as $company) {
        $company->success_rate = $company->total_requests > 0 
            ? round(($company->successful_requests / $company->total_requests) * 100, 2) 
            : 0;
        $company->error_rate = 100 - $company->success_rate;
    }
    
    // 5. Hourly Trends
    $hourly_trends_sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00') as hour_bucket,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as successful_requests,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as failed_requests,
            AVG(response_time_ms) as avg_response_time,
            SUM(record_count) as total_records
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00')
        ORDER BY hour_bucket DESC
        LIMIT 24
    ";
    
    $hourly_trends = $DB->get_records_sql($hourly_trends_sql, $params);
    
    // Calculate hourly success rates
    foreach ($hourly_trends as $hour) {
        $hour->success_rate = $hour->total_requests > 0 
            ? round(($hour->successful_requests / $hour->total_requests) * 100, 2) 
            : 0;
        $hour->error_rate = 100 - $hour->success_rate;
    }
    
    // 6. Top Error-Prone Users
    $error_prone_users_sql = "
        SELECT 
            l.userid,
            u.firstname,
            u.lastname,
            u.email,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN l.error_message IS NOT NULL AND l.error_message != '' THEN 1 END) as failed_requests,
            AVG(l.response_time_ms) as avg_response_time,
            COUNT(DISTINCT l.company_shortname) as companies_accessed
        FROM {local_alx_api_logs} l
        LEFT JOIN {user} u ON l.userid = u.id
        WHERE {$where_clause}
        GROUP BY l.userid, u.firstname, u.lastname, u.email
        HAVING failed_requests > 0
        ORDER BY failed_requests DESC, total_requests DESC
        LIMIT 10
    ";
    
    $error_prone_users = $DB->get_records_sql($error_prone_users_sql, $params);
    
    // Calculate user error rates
    foreach ($error_prone_users as $user) {
        $user->error_rate = $user->total_requests > 0 
            ? round(($user->failed_requests / $user->total_requests) * 100, 2) 
            : 0;
        $user->success_rate = 100 - $user->error_rate;
    }
    
    // 7. Performance Bottlenecks (Slowest operations)
    $bottlenecks_sql = "
        SELECT 
            endpoint,
            company_shortname,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            COUNT(*) as request_count,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as error_count
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY endpoint, company_shortname
        HAVING AVG(response_time_ms) > 500
        ORDER BY avg_response_time DESC
        LIMIT 15
    ";
    
    $bottlenecks = $DB->get_records_sql($bottlenecks_sql, $params);
    
    // 8. Recent Critical Errors (last hour)
    $recent_errors_sql = "
        SELECT 
            l.timeaccessed,
            l.endpoint,
            l.company_shortname,
            l.error_message,
            l.response_time_ms,
            u.firstname,
            u.lastname,
            u.email
        FROM {local_alx_api_logs} l
        LEFT JOIN {user} u ON l.userid = u.id
        WHERE l.timeaccessed >= ? AND (l.error_message IS NOT NULL AND l.error_message != '')
        ORDER BY l.timeaccessed DESC
        LIMIT 20
    ";
    
    $recent_errors = $DB->get_records_sql($recent_errors_sql, [time() - 3600]);
    
    return [
        'summary' => [
            'analysis_period_hours' => $hours,
            'total_requests' => (int)$overall_stats->total_requests,
            'successful_requests' => (int)$overall_stats->successful_requests,
            'failed_requests' => (int)$overall_stats->failed_requests,
            'success_rate' => $success_rate,
            'error_rate' => round(100 - $success_rate, 2),
            'avg_response_time' => round($overall_stats->avg_response_time ?: 0, 2),
            'max_response_time' => round($overall_stats->max_response_time ?: 0, 2),
            'min_response_time' => round($overall_stats->min_response_time ?: 0, 2),
            'avg_success_response_time' => round($overall_stats->avg_success_response_time ?: 0, 2),
            'avg_error_response_time' => round($overall_stats->avg_error_response_time ?: 0, 2),
            'total_records_returned' => (int)$overall_stats->total_records_returned,
            'unique_users' => (int)$overall_stats->unique_users,
            'unique_companies' => (int)$overall_stats->unique_companies,
            'calls_per_hour' => $hours > 0 ? round($overall_stats->total_requests / $hours, 2) : 0
        ],
        'error_analysis' => array_values($error_analysis),
        'endpoint_performance' => array_values($endpoint_performance),
        'company_performance' => array_values($company_performance),
        'hourly_trends' => array_values($hourly_trends),
        'error_prone_users' => array_values($error_prone_users),
        'performance_bottlenecks' => array_values($bottlenecks),
        'recent_critical_errors' => array_values($recent_errors),
        'alerts' => local_alx_report_api_analyze_performance_alerts($overall_stats, $endpoint_performance, $company_performance)
    ];
}

/**
 * Analyze performance data and generate alerts for potential issues.
 */
function local_alx_report_api_analyze_performance_alerts($overall_stats, $endpoint_performance, $company_performance) {
    $alerts = [];
    
    // High error rate alert
    $error_rate = $overall_stats->total_requests > 0 
        ? (($overall_stats->failed_requests / $overall_stats->total_requests) * 100) 
        : 0;
    
    if ($error_rate > 10) {
        $alerts[] = [
            'type' => 'high_error_rate',
            'severity' => $error_rate > 25 ? 'critical' : 'high',
            'message' => "Overall error rate is {$error_rate}% (threshold: 10%)",
            'data' => ['error_rate' => $error_rate, 'failed_requests' => $overall_stats->failed_requests]
        ];
    }
    
    // Slow response time alert
    if ($overall_stats->avg_response_time > 2000) {
        $alerts[] = [
            'type' => 'slow_response_time',
            'severity' => $overall_stats->avg_response_time > 5000 ? 'critical' : 'medium',
            'message' => "Average response time is {$overall_stats->avg_response_time}ms (threshold: 2000ms)",
            'data' => ['avg_response_time' => $overall_stats->avg_response_time]
        ];
    }
    
    // Endpoint-specific issues
    foreach ($endpoint_performance as $endpoint) {
        if ($endpoint->error_rate > 15) {
            $alerts[] = [
                'type' => 'endpoint_high_errors',
                'severity' => $endpoint->error_rate > 30 ? 'high' : 'medium',
                'message' => "Endpoint '{$endpoint->endpoint}' has {$endpoint->error_rate}% error rate",
                'data' => ['endpoint' => $endpoint->endpoint, 'error_rate' => $endpoint->error_rate]
            ];
        }
    }
    
    // Company-specific issues
    foreach ($company_performance as $company) {
        if ($company->error_rate > 20) {
            $alerts[] = [
                'type' => 'company_high_errors',
                'severity' => 'medium',
                'message' => "Company '{$company->company_shortname}' has {$company->error_rate}% error rate",
                'data' => ['company' => $company->company_shortname, 'error_rate' => $company->error_rate]
            ];
        }
    }
    
    return $alerts;
}

/**
 * Enhanced logging function with response time and error tracking.
 */
function local_alx_report_api_log_api_call($userid, $company_shortname, $endpoint, $record_count = 0, $error_message = null, $response_time_ms = null, $additional_data = []) {
    global $DB;
    
    try {
        $log = new stdClass();
        $log->userid = $userid;
        $log->company_shortname = $company_shortname;
        $log->endpoint = $endpoint;
        $log->record_count = $record_count;
        $log->error_message = $error_message;
        $log->response_time_ms = $response_time_ms;
        $log->timeaccessed = time();
        $log->ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log->user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        
        // Store additional data as JSON
        if (!empty($additional_data)) {
            $log->additional_data = json_encode($additional_data);
        }
        
        $DB->insert_record('local_alx_api_logs', $log);
        
        // Check if this error should trigger an immediate alert
        if ($error_message && !empty($error_message)) {
            local_alx_report_api_check_error_alert($userid, $company_shortname, $endpoint, $error_message, $response_time_ms);
        }
        
    } catch (Exception $e) {
        // Don't let logging errors break the API
        error_log("ALX Report API: Failed to log API call: " . $e->getMessage());
    }
}

/**
 * Check if an error should trigger an immediate alert.
 */
function local_alx_report_api_check_error_alert($userid, $company_shortname, $endpoint, $error_message, $response_time_ms) {
    global $DB;
    
    // Check for critical error patterns
    $critical_patterns = [
        'database error',
        'connection failed',
        'timeout',
        'memory limit',
        'fatal error',
        'access denied',
        'authentication failed'
    ];
    
    $is_critical = false;
    foreach ($critical_patterns as $pattern) {
        if (stripos($error_message, $pattern) !== false) {
            $is_critical = true;
            break;
        }
    }
    
    // Check error frequency (multiple errors in short time)
    $recent_errors = $DB->count_records_select('local_alx_api_logs', 
        'userid = ? AND endpoint = ? AND timeaccessed >= ? AND (error_message IS NOT NULL AND error_message != "")',
        [$userid, $endpoint, time() - 300] // Last 5 minutes
    );
    
    if ($is_critical || $recent_errors >= 3) {
        $severity = $is_critical ? 'high' : 'medium';
        $user = $DB->get_record('user', ['id' => $userid]);
        $user_name = $user ? fullname($user) : "User ID {$userid}";
        
        local_alx_report_api_send_alert(
            'performance',
            $severity,
            "Repeated API errors detected for {$user_name} on {$endpoint}: {$error_message}",
            [
                'user_id' => $userid,
                'company' => $company_shortname,
                'endpoint' => $endpoint,
                'error_message' => $error_message,
                'response_time_ms' => $response_time_ms,
                'recent_error_count' => $recent_errors,
                'is_critical_pattern' => $is_critical
            ]
        );
    }
}

/**
 * Export API logs data for a specific date range.
 *
 * @param int $date_from Start timestamp
 * @param int $date_to End timestamp  
 * @param string $company_filter Optional company filter
 * @return array API logs data
 */
function local_alx_report_api_get_api_logs_export($date_from, $date_to, $company_filter = null) {
    global $DB;
    
    $where_conditions = ['timeaccessed >= ? AND timeaccessed <= ?'];
    $params = [$date_from, $date_to];
    
    if ($company_filter) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $company_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            l.id,
            l.timeaccessed,
            l.userid,
            u.firstname,
            u.lastname,
            u.email,
            l.company_shortname,
            l.endpoint,
            l.record_count,
            l.response_time_ms,
            l.error_message,
            l.ip_address,
            l.user_agent
        FROM {local_alx_api_logs} l
        LEFT JOIN {user} u ON l.userid = u.id
        WHERE {$where_clause}
        ORDER BY l.timeaccessed DESC
    ";
    
    $logs = $DB->get_records_sql($sql, $params);
    
    return [
        'logs' => array_values($logs),
        'summary' => [
            'total_requests' => count($logs),
            'date_range' => [
                'from' => date('Y-m-d H:i:s', $date_from),
                'to' => date('Y-m-d H:i:s', $date_to)
            ],
            'company_filter' => $company_filter ?: 'All companies'
        ]
    ];
}

/**
 * Export data as CSV format.
 *
 * @param array $data Data to export
 * @param string $data_type Type of data being exported
 * @param int $date_from Start timestamp
 * @param int $date_to End timestamp
 */
function local_alx_report_api_export_csv($data, $data_type, $date_from, $date_to) {
    $filename = "alx_report_api_{$data_type}_" . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Export header
    fputcsv($output, ['ALX Report API Export - ' . ucfirst(str_replace('_', ' ', $data_type))]);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Range:', date('Y-m-d H:i:s', $date_from) . ' to ' . date('Y-m-d H:i:s', $date_to)]);
    fputcsv($output, []); // Empty row
    
    switch ($data_type) {
        case 'analytics':
            local_alx_report_api_export_analytics_csv($output, $data);
            break;
        case 'api_logs':
            local_alx_report_api_export_logs_csv($output, $data);
            break;
        case 'system_health':
            local_alx_report_api_export_health_csv($output, $data);
            break;
        case 'rate_limiting':
            local_alx_report_api_export_rate_limiting_csv($output, $data);
            break;
    }
    
    fclose($output);
}

/**
 * Export analytics data to CSV.
 */
function local_alx_report_api_export_analytics_csv($output, $data) {
    // Summary section
    fputcsv($output, ['=== ANALYTICS SUMMARY ===']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Analysis Period (hours)', $data['summary']['analysis_period_hours']]);
    fputcsv($output, ['Total Requests', $data['summary']['total_requests']]);
    fputcsv($output, ['Successful Requests', $data['summary']['successful_requests']]);
    fputcsv($output, ['Failed Requests', $data['summary']['failed_requests']]);
    fputcsv($output, ['Success Rate (%)', $data['summary']['success_rate']]);
    fputcsv($output, ['Error Rate (%)', $data['summary']['error_rate']]);
    fputcsv($output, ['Average Response Time (ms)', $data['summary']['avg_response_time']]);
    fputcsv($output, ['Max Response Time (ms)', $data['summary']['max_response_time']]);
    fputcsv($output, ['Unique Users', $data['summary']['unique_users']]);
    fputcsv($output, ['Unique Companies', $data['summary']['unique_companies']]);
    fputcsv($output, ['Calls per Hour', $data['summary']['calls_per_hour']]);
    fputcsv($output, []); // Empty row
    
    // Endpoint Performance
    if (!empty($data['endpoint_performance'])) {
        fputcsv($output, ['=== ENDPOINT PERFORMANCE ===']);
        fputcsv($output, ['Endpoint', 'Total Requests', 'Success Rate (%)', 'Error Rate (%)', 'Avg Response Time (ms)', 'Max Response Time (ms)', 'Total Records', 'Unique Users']);
        foreach ($data['endpoint_performance'] as $endpoint) {
            fputcsv($output, [
                $endpoint->endpoint,
                $endpoint->total_requests,
                $endpoint->success_rate,
                $endpoint->error_rate,
                round($endpoint->avg_response_time, 2),
                $endpoint->max_response_time,
                $endpoint->total_records,
                $endpoint->unique_users
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Company Performance
    if (!empty($data['company_performance'])) {
        fputcsv($output, ['=== COMPANY PERFORMANCE ===']);
        fputcsv($output, ['Company', 'Total Requests', 'Success Rate (%)', 'Error Rate (%)', 'Avg Response Time (ms)', 'Total Records', 'Unique Users', 'Endpoints Used']);
        foreach ($data['company_performance'] as $company) {
            fputcsv($output, [
                $company->company_shortname,
                $company->total_requests,
                $company->success_rate,
                $company->error_rate,
                round($company->avg_response_time, 2),
                $company->total_records,
                $company->unique_users,
                $company->endpoints_used
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Error Analysis
    if (!empty($data['error_analysis'])) {
        fputcsv($output, ['=== ERROR ANALYSIS ===']);
        fputcsv($output, ['Error Message', 'Count', 'Affected Users', 'Affected Companies', 'Avg Response Time (ms)', 'First Occurrence', 'Last Occurrence']);
        foreach ($data['error_analysis'] as $error) {
            fputcsv($output, [
                $error->error_message,
                $error->error_count,
                $error->affected_users,
                $error->affected_companies,
                round($error->avg_response_time, 2),
                date('Y-m-d H:i:s', $error->first_occurrence),
                date('Y-m-d H:i:s', $error->last_occurrence)
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Hourly Trends
    if (!empty($data['hourly_trends'])) {
        fputcsv($output, ['=== HOURLY TRENDS ===']);
        fputcsv($output, ['Hour', 'Total Requests', 'Success Rate (%)', 'Error Rate (%)', 'Avg Response Time (ms)', 'Total Records']);
        foreach ($data['hourly_trends'] as $hour) {
            fputcsv($output, [
                $hour->hour_bucket,
                $hour->total_requests,
                $hour->success_rate,
                $hour->error_rate,
                round($hour->avg_response_time, 2),
                $hour->total_records
            ]);
        }
    }
}

/**
 * Export API logs to CSV.
 */
function local_alx_report_api_export_logs_csv($output, $data) {
    fputcsv($output, ['=== API LOGS ===']);
    fputcsv($output, ['Total Logs:', count($data['logs'])]);
    fputcsv($output, []); // Empty row
    
    // Headers
    fputcsv($output, [
        'Timestamp',
        'User ID', 
        'User Name',
        'User Email',
        'Company',
        'Endpoint',
        'Records Returned',
        'Response Time (ms)',
        'Status',
        'Error Message',
        'IP Address',
        'User Agent'
    ]);
    
    // Data rows
    foreach ($data['logs'] as $log) {
        $status = empty($log->error_message) ? 'Success' : 'Error';
        $user_name = trim(($log->firstname ?? '') . ' ' . ($log->lastname ?? ''));
        
        fputcsv($output, [
            date('Y-m-d H:i:s', $log->timeaccessed),
            $log->userid,
            $user_name ?: 'Unknown',
            $log->email ?? 'Unknown',
            $log->company_shortname ?? 'Unknown',
            $log->endpoint,
            $log->record_count ?? 0,
            $log->response_time_ms ?? '',
            $status,
            $log->error_message ?? '',
            $log->ip_address ?? '',
            substr($log->user_agent ?? '', 0, 100) // Truncate user agent
        ]);
    }
}

/**
 * Export system health data to CSV.
 */
function local_alx_report_api_export_health_csv($output, $data) {
    $health = $data['health'];
    
    fputcsv($output, ['=== SYSTEM HEALTH REPORT ===']);
    fputcsv($output, ['Overall Status:', $health['overall_status']]);
    fputcsv($output, ['Health Score:', $health['score'] . '/100']);
    fputcsv($output, ['Report Time:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Health checks
    fputcsv($output, ['=== HEALTH CHECKS ===']);
    fputcsv($output, ['Component', 'Status', 'Message', 'Details']);
    
    foreach ($health['checks'] as $check) {
        fputcsv($output, [
            $check['component'],
            $check['status'],
            $check['message'],
            is_array($check['details']) ? json_encode($check['details']) : $check['details']
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // Recommendations
    if (!empty($health['recommendations'])) {
        fputcsv($output, ['=== RECOMMENDATIONS ===']);
        fputcsv($output, ['Recommendation']);
        
        foreach ($health['recommendations'] as $recommendation) {
            fputcsv($output, [$recommendation]);
        }
    }
}

/**
 * Export rate limiting data to CSV.
 */
function local_alx_report_api_export_rate_limiting_csv($output, $data) {
    $monitoring = $data['rate_monitoring'];
    
    fputcsv($output, ['=== RATE LIMITING REPORT ===']);
    fputcsv($output, ['Report Time:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Violations
    if (!empty($monitoring['violations'])) {
        fputcsv($output, ['=== RATE LIMIT VIOLATIONS ===']);
        fputcsv($output, ['User ID', 'User Name', 'Email', 'Company', 'Requests Today', 'Daily Limit', 'Usage %', 'Companies Accessed']);
        
        foreach ($monitoring['violations'] as $violation) {
            fputcsv($output, [
                $violation['userid'],
                $violation['name'],
                $violation['email'],
                $violation['primary_company'],
                $violation['requests_today'],
                $violation['limit'],
                $violation['usage_percentage'],
                $violation['companies_count']
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // High usage users
    if (!empty($monitoring['high_usage_users'])) {
        fputcsv($output, ['=== HIGH USAGE USERS ===']);
        fputcsv($output, ['User ID', 'User Name', 'Email', 'Requests Today', 'Usage %', 'Companies Accessed']);
        
        foreach ($monitoring['high_usage_users'] as $user) {
            fputcsv($output, [
                $user['userid'],
                $user['name'],
                $user['email'],
                $user['requests_today'],
                $user['usage_percentage'],
                $user['companies_count']
            ]);
        }
        fputcsv($output, []); // Empty row
    }
    
    // Daily trends
    if (!empty($monitoring['daily_trends'])) {
        fputcsv($output, ['=== DAILY TRENDS (Last 7 Days) ===']);
        fputcsv($output, ['Date', 'Total Requests', 'Unique Users', 'Average per User', 'Violations']);
        
        foreach ($monitoring['daily_trends'] as $day) {
            fputcsv($output, [
                $day['date'],
                $day['total_requests'],
                $day['unique_users'],
                $day['avg_requests_per_user'],
                $day['violations']
            ]);
        }
    }
}

/**
 * Export data as PDF format.
 *
 * @param array $data Data to export
 * @param string $data_type Type of data being exported
 * @param int $date_from Start timestamp
 * @param int $date_to End timestamp
 */
function local_alx_report_api_export_pdf($data, $data_type, $date_from, $date_to) {
    // Note: This is a basic HTML-to-PDF implementation
    // For production use, consider using libraries like TCPDF, mPDF, or DomPDF
    
    $filename = "alx_report_api_{$data_type}_" . date('Y-m-d_H-i-s') . '.pdf';
    
    // Generate HTML content
    $html = local_alx_report_api_generate_pdf_html($data, $data_type, $date_from, $date_to);
    
    // Simple PDF generation using HTML
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // This is a simplified implementation - in production you'd use a proper PDF library
    echo $html;
}

/**
 * Generate HTML content for PDF export.
 */
function local_alx_report_api_generate_pdf_html($data, $data_type, $date_from, $date_to) {
    global $CFG;
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>ALX Report API - ' . ucfirst(str_replace('_', ' ', $data_type)) . ' Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
            .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
            .summary-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; text-align: center; }
            .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
            .metric-label { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .section-title { font-size: 18px; font-weight: bold; margin: 30px 0 15px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>ALX Report API</h1>
            <h2>' . ucfirst(str_replace('_', ' ', $data_type)) . ' Report</h2>
            <p>Generated: ' . date('Y-m-d H:i:s') . '</p>
            <p>Period: ' . date('Y-m-d H:i:s', $date_from) . ' to ' . date('Y-m-d H:i:s', $date_to) . '</p>
        </div>';
    
    // Add content based on data type
    switch ($data_type) {
        case 'analytics':
            $html .= local_alx_report_api_generate_analytics_pdf_content($data);
            break;
        case 'system_health':
            $html .= local_alx_report_api_generate_health_pdf_content($data);
            break;
        case 'rate_limiting':
            $html .= local_alx_report_api_generate_rate_limiting_pdf_content($data);
            break;
        case 'api_logs':
            $html .= local_alx_report_api_generate_logs_pdf_content($data);
            break;
    }
    
    $html .= '
        <div class="footer">
            <p>This report was generated by ALX Report API monitoring system</p>
            <p>Server: ' . $CFG->wwwroot . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Generate analytics content for PDF.
 */
function local_alx_report_api_generate_analytics_pdf_content($data) {
    $summary = $data['summary'];
    
    $content = '
    <div class="section-title">📊 Analytics Summary</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="metric-value">' . number_format($summary['total_requests']) . '</div>
            <div class="metric-label">Total Requests</div>
        </div>
        <div class="summary-card">
            <div class="metric-value">' . $summary['success_rate'] . '%</div>
            <div class="metric-label">Success Rate</div>
        </div>
        <div class="summary-card">
            <div class="metric-value">' . $summary['avg_response_time'] . 'ms</div>
            <div class="metric-label">Avg Response Time</div>
        </div>
        <div class="summary-card">
            <div class="metric-value">' . $summary['unique_users'] . '</div>
            <div class="metric-label">Unique Users</div>
        </div>
    </div>';
    
    // Endpoint Performance Table
    if (!empty($data['endpoint_performance'])) {
        $content .= '
        <div class="section-title">🎯 Endpoint Performance</div>
        <table>
            <tr>
                <th>Endpoint</th>
                <th>Requests</th>
                <th>Success Rate</th>
                <th>Avg Response Time</th>
                <th>Users</th>
            </tr>';
        
        foreach (array_slice($data['endpoint_performance'], 0, 10) as $endpoint) {
            $content .= '
            <tr>
                <td>' . htmlspecialchars($endpoint->endpoint) . '</td>
                <td>' . number_format($endpoint->total_requests) . '</td>
                <td>' . $endpoint->success_rate . '%</td>
                <td>' . round($endpoint->avg_response_time, 2) . 'ms</td>
                <td>' . $endpoint->unique_users . '</td>
            </tr>';
        }
        
        $content .= '</table>';
    }
    
    return $content;
}

/**
 * Generate system health content for PDF.
 */
function local_alx_report_api_generate_health_pdf_content($data) {
    $health = $data['health'];
    
    $status_color = $health['overall_status'] === 'healthy' ? '#28a745' : 
                   ($health['overall_status'] === 'warning' ? '#ffc107' : '#dc3545');
    
    $content = '
    <div class="section-title">🏥 System Health Overview</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="metric-value" style="color: ' . $status_color . '">' . $health['score'] . '/100</div>
            <div class="metric-label">Health Score</div>
        </div>
        <div class="summary-card">
            <div class="metric-value" style="color: ' . $status_color . '">' . ucfirst($health['overall_status']) . '</div>
            <div class="metric-label">Overall Status</div>
        </div>
    </div>
    
    <div class="section-title">🔍 Health Checks</div>
    <table>
        <tr>
            <th>Component</th>
            <th>Status</th>
            <th>Message</th>
        </tr>';
    
    foreach ($health['checks'] as $check) {
        $status_icon = $check['status'] === 'healthy' ? '✅' : 
                      ($check['status'] === 'warning' ? '⚠️' : '❌');
        
        $content .= '
        <tr>
            <td>' . htmlspecialchars($check['component']) . '</td>
            <td>' . $status_icon . ' ' . ucfirst($check['status']) . '</td>
            <td>' . htmlspecialchars($check['message']) . '</td>
        </tr>';
    }
    
    $content .= '</table>';
    
    if (!empty($health['recommendations'])) {
        $content .= '
        <div class="section-title">💡 Recommendations</div>
        <ul>';
        
        foreach ($health['recommendations'] as $recommendation) {
            $content .= '<li>' . htmlspecialchars($recommendation) . '</li>';
        }
        
        $content .= '</ul>';
    }
    
    return $content;
}

/**
 * Generate rate limiting content for PDF.
 */
function local_alx_report_api_generate_rate_limiting_pdf_content($data) {
    $monitoring = $data['rate_monitoring'];
    
    $content = '
    <div class="section-title">🚦 Rate Limiting Overview</div>';
    
    if (!empty($monitoring['violations'])) {
        $content .= '
        <div class="section-title">⚠️ Rate Limit Violations</div>
        <table>
            <tr>
                <th>User</th>
                <th>Company</th>
                <th>Requests Today</th>
                <th>Daily Limit</th>
                <th>Usage %</th>
            </tr>';
        
        foreach ($monitoring['violations'] as $violation) {
            $content .= '
            <tr>
                <td>' . htmlspecialchars($violation['name']) . '</td>
                <td>' . htmlspecialchars($violation['primary_company']) . '</td>
                <td>' . $violation['requests_today'] . '</td>
                <td>' . $violation['limit'] . '</td>
                <td>' . $violation['usage_percentage'] . '%</td>
            </tr>';
        }
        
        $content .= '</table>';
    } else {
        $content .= '<p>✅ No rate limit violations detected.</p>';
    }
    
    return $content;
}

/**
 * Generate logs content for PDF.
 */
function local_alx_report_api_generate_logs_pdf_content($data) {
    $content = '
    <div class="section-title">📋 API Access Logs</div>
    <p>Total logs: ' . count($data['logs']) . '</p>';
    
    if (!empty($data['logs'])) {
        $content .= '
        <table>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Company</th>
                <th>Endpoint</th>
                <th>Status</th>
                <th>Response Time</th>
            </tr>';
        
        foreach (array_slice($data['logs'], 0, 50) as $log) {
            $status = empty($log->error_message) ? '✅ Success' : '❌ Error';
            $user_name = trim(($log->firstname ?? '') . ' ' . ($log->lastname ?? ''));
            
            $content .= '
            <tr>
                <td>' . date('Y-m-d H:i', $log->timeaccessed) . '</td>
                <td>' . htmlspecialchars($user_name ?: 'Unknown') . '</td>
                <td>' . htmlspecialchars($log->company_shortname ?? 'Unknown') . '</td>
                <td>' . htmlspecialchars($log->endpoint) . '</td>
                <td>' . $status . '</td>
                <td>' . ($log->response_time_ms ?? 'N/A') . 'ms</td>
            </tr>';
        }
        
        $content .= '</table>';
        
        if (count($data['logs']) > 50) {
            $content .= '<p><em>Showing first 50 logs. Export CSV for complete data.</em></p>';
        }
    }
    
    return $content;
}

/**
 * Export data as JSON format.
 *
 * @param array $data Data to export
 * @param string $data_type Type of data being exported
 * @param int $date_from Start timestamp
 * @param int $date_to End timestamp
 */
function local_alx_report_api_export_json($data, $data_type, $date_from, $date_to) {
    $filename = "alx_report_api_{$data_type}_" . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $export_data = [
        'export_info' => [
            'plugin' => 'ALX Report API',
            'data_type' => $data_type,
            'export_timestamp' => time(),
            'export_date' => date('Y-m-d H:i:s'),
            'date_range' => [
                'from' => date('Y-m-d H:i:s', $date_from),
                'to' => date('Y-m-d H:i:s', $date_to),
                'from_timestamp' => $date_from,
                'to_timestamp' => $date_to
            ]
        ],
        'data' => $data
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * 🔍 MEDIUM PRIORITY #5: Performance Bottleneck Identification
 * Get detailed performance analysis and bottleneck identification.
 */
function local_alx_report_api_get_performance_bottlenecks($hours = 24) {
    global $DB;
    
    $start_time = time() - ($hours * 3600);
    
    // 1. Identify slow endpoints
    $slow_endpoints_sql = "
        SELECT 
            endpoint,
            COUNT(*) as total_calls,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            STDDEV(response_time_ms) as response_time_variance,
            COUNT(CASE WHEN response_time_ms > 1000 THEN 1 END) as slow_calls
        FROM {local_alx_api_logs}
        WHERE timeaccessed >= ? AND response_time_ms IS NOT NULL
        GROUP BY endpoint
        HAVING AVG(response_time_ms) > 500 OR COUNT(CASE WHEN response_time_ms > 1000 THEN 1 END) > 0
        ORDER BY avg_response_time DESC
        LIMIT 20
    ";
    $slow_endpoints = $DB->get_records_sql($slow_endpoints_sql, [$start_time]);
    
    // 2. Identify performance patterns by hour
    $hourly_performance_sql = "
        SELECT 
            HOUR(FROM_UNIXTIME(timeaccessed)) as hour_of_day,
            COUNT(*) as total_calls,
            AVG(response_time_ms) as avg_response_time,
            COUNT(CASE WHEN response_time_ms > 1000 THEN 1 END) as slow_calls,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as error_count
        FROM {local_alx_api_logs}
        WHERE timeaccessed >= ?
        GROUP BY HOUR(FROM_UNIXTIME(timeaccessed))
        ORDER BY hour_of_day
    ";
    $hourly_performance = $DB->get_records_sql($hourly_performance_sql, [$start_time]);
    
    // 3. Company-specific performance issues
    $company_performance_sql = "
        SELECT 
            company_shortname,
            COUNT(*) as total_calls,
            AVG(response_time_ms) as avg_response_time,
            COUNT(CASE WHEN response_time_ms > 1000 THEN 1 END) as slow_calls,
            AVG(record_count) as avg_records_per_call,
            COUNT(DISTINCT userid) as unique_users
        FROM {local_alx_api_logs}
        WHERE timeaccessed >= ? AND company_shortname IS NOT NULL
        GROUP BY company_shortname
        HAVING AVG(response_time_ms) > 300 OR COUNT(CASE WHEN response_time_ms > 1000 THEN 1 END) > 5
        ORDER BY avg_response_time DESC
        LIMIT 15
    ";
    $company_performance = $DB->get_records_sql($company_performance_sql, [$start_time]);
    
    // 4. Heavy users (potential bottleneck sources)
    $heavy_users_sql = "
        SELECT 
            u.firstname,
            u.lastname,
            u.email,
            logs.userid,
            logs.company_shortname,
            COUNT(*) as total_calls,
            AVG(response_time_ms) as avg_response_time,
            SUM(record_count) as total_records_requested
        FROM {local_alx_api_logs} logs
        JOIN {user} u ON logs.userid = u.id
        WHERE timeaccessed >= ?
        GROUP BY logs.userid, logs.company_shortname
        HAVING COUNT(*) > 50 OR SUM(record_count) > 10000
        ORDER BY total_calls DESC
        LIMIT 20
    ";
    $heavy_users = $DB->get_records_sql($heavy_users_sql, [$start_time]);
    
    // 5. Database performance analysis
    $db_performance = local_alx_report_api_analyze_database_performance();
    
    // 6. Peak load analysis
    $peak_load_sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00') as hour_bucket,
            COUNT(*) as calls_count,
            AVG(response_time_ms) as avg_response_time,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT company_shortname) as unique_companies
        FROM {local_alx_api_logs}
        WHERE timeaccessed >= ?
        GROUP BY FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00')
        ORDER BY calls_count DESC
        LIMIT 10
    ";
    $peak_load_periods = $DB->get_records_sql($peak_load_sql, [$start_time]);
    
    return [
        'slow_endpoints' => array_values($slow_endpoints),
        'hourly_performance' => array_values($hourly_performance),
        'company_performance' => array_values($company_performance),
        'heavy_users' => array_values($heavy_users),
        'database_performance' => $db_performance,
        'peak_load_periods' => array_values($peak_load_periods),
        'analysis_period' => $hours,
        'recommendations' => local_alx_report_api_generate_performance_recommendations($slow_endpoints, $hourly_performance, $company_performance)
    ];
}

/**
 * Analyze database performance metrics
 */
function local_alx_report_api_analyze_database_performance() {
    global $DB;
    
    $start_time = microtime(true);
    
    // Test query performance
    $test_queries = [
        'simple_count' => "SELECT COUNT(*) as count FROM {local_alx_api_logs}",
        'complex_join' => "SELECT COUNT(*) as count FROM {local_alx_api_logs} l JOIN {user} u ON l.userid = u.id",
        'aggregation' => "SELECT company_shortname, COUNT(*) as count FROM {local_alx_api_logs} GROUP BY company_shortname LIMIT 5",
        'recent_data' => "SELECT COUNT(*) as count FROM {local_alx_api_logs} WHERE timeaccessed >= " . (time() - 3600)
    ];
    
    $performance_results = [];
    
    foreach ($test_queries as $query_name => $sql) {
        $query_start = microtime(true);
        try {
            $result = $DB->get_record_sql($sql);
            $query_time = (microtime(true) - $query_start) * 1000; // Convert to milliseconds
            
            $performance_results[$query_name] = [
                'execution_time_ms' => round($query_time, 2),
                'status' => 'success',
                'result_count' => isset($result->count) ? $result->count : 0
            ];
        } catch (Exception $e) {
            $performance_results[$query_name] = [
                'execution_time_ms' => -1,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Check table sizes
    $table_info_sql = "
        SELECT 
            table_name,
            table_rows,
            data_length,
            index_length
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE() 
        AND table_name IN ('" . $DB->get_prefix() . "local_alx_api_logs', '" . $DB->get_prefix() . "local_alx_reporting_data')
    ";
    
    try {
        $table_info = $DB->get_records_sql($table_info_sql);
    } catch (Exception $e) {
        $table_info = [];
    }
    
    return [
        'query_performance' => $performance_results,
        'table_information' => array_values($table_info),
        'overall_health' => local_alx_report_api_calculate_db_health_score($performance_results)
    ];
}

/**
 * Generate performance recommendations based on analysis
 */
function local_alx_report_api_generate_performance_recommendations($slow_endpoints, $hourly_performance, $company_performance) {
    $recommendations = [];
    
    // Analyze slow endpoints
    if (!empty($slow_endpoints)) {
        $recommendations[] = [
            'type' => 'endpoint_optimization',
            'severity' => 'high',
            'title' => 'Slow API Endpoints Detected',
            'description' => count($slow_endpoints) . ' endpoints are performing slower than optimal',
            'actions' => [
                'Review database queries for slow endpoints',
                'Consider implementing response caching',
                'Optimize data structures and indexing',
                'Monitor endpoint usage patterns'
            ]
        ];
    }
    
    // Analyze peak hour performance
    $peak_hours = array_filter($hourly_performance, function($hour) {
        return isset($hour->avg_response_time) && $hour->avg_response_time > 800;
    });
    
    if (!empty($peak_hours)) {
        $recommendations[] = [
            'type' => 'load_balancing',
            'severity' => 'medium',
            'title' => 'Peak Hour Performance Issues',
            'description' => 'Performance degrades during peak usage hours',
            'actions' => [
                'Implement request queuing during peak hours',
                'Consider scaling database resources',
                'Add rate limiting during high-load periods',
                'Schedule maintenance during off-peak hours'
            ]
        ];
    }
    
    // Analyze company-specific issues
    if (!empty($company_performance)) {
        $recommendations[] = [
            'type' => 'company_optimization',
            'severity' => 'medium',
            'title' => 'Company-Specific Performance Issues',
            'description' => count($company_performance) . ' companies experiencing slower response times',
            'actions' => [
                'Review data size for affected companies',
                'Implement company-specific caching',
                'Consider data archiving for large datasets',
                'Optimize company data structure'
            ]
        ];
    }
    
    return $recommendations;
}

/**
 * Calculate database health score based on performance metrics
 */
function local_alx_report_api_calculate_db_health_score($performance_results) {
    $score = 100;
    
    foreach ($performance_results as $query_name => $result) {
        if ($result['status'] === 'error') {
            $score -= 30;
        } elseif ($result['execution_time_ms'] > 100) {
            $score -= 10;
        } elseif ($result['execution_time_ms'] > 50) {
            $score -= 5;
        }
    }
    
    return max(0, $score);
}

/**
 * 🔐 MEDIUM PRIORITY #6: Authentication Attempts Logging
 * Log and analyze authentication attempts for security monitoring.
 */
function local_alx_report_api_log_auth_attempt($token, $userid, $success, $ip_address, $user_agent = '', $endpoint = '') {
    global $DB;
    
    // Create auth attempts table if it doesn't exist
    local_alx_report_api_ensure_auth_table();
    
    $auth_log = new stdClass();
    $auth_log->token_id = $token ? substr($token, 0, 8) . '...' : 'none'; // Only store partial token for security
    $auth_log->userid = $userid;
    $auth_log->success = $success ? 1 : 0;
    $auth_log->ip_address = $ip_address;
    $auth_log->user_agent = substr($user_agent, 0, 255); // Limit user agent length
    $auth_log->endpoint = $endpoint;
    $auth_log->timeaccessed = time();
    
    try {
        $DB->insert_record('local_alx_auth_attempts', $auth_log);
        
        // Check for suspicious activity
        local_alx_report_api_check_suspicious_auth_activity($ip_address, $userid);
        
    } catch (Exception $e) {
        // Log error but don't break API functionality
        error_log('ALX Report API: Failed to log auth attempt - ' . $e->getMessage());
    }
}

/**
 * Ensure authentication attempts table exists
 */
function local_alx_report_api_ensure_auth_table() {
    global $DB;
    
    $dbman = $DB->get_manager();
    $table_name = 'local_alx_auth_attempts';
    
    if (!$dbman->table_exists($table_name)) {
        // Table will be created by upgrade.php, just log for now
        error_log('ALX Report API: Auth attempts table does not exist, will be created on next upgrade');
    }
}

/**
 * Check for suspicious authentication activity
 */
function local_alx_report_api_check_suspicious_auth_activity($ip_address, $userid) {
    global $DB;
    
    $last_hour = time() - 3600;
    
    // Check for repeated failed attempts from same IP
    $failed_attempts_sql = "
        SELECT COUNT(*) as failed_count
        FROM {local_alx_auth_attempts}
        WHERE ip_address = ? AND success = 0 AND timeaccessed >= ?
    ";
    $failed_count = $DB->get_field_sql($failed_attempts_sql, [$ip_address, $last_hour]);
    
    if ($failed_count >= 10) {
        local_alx_report_api_trigger_security_alert([
            'type' => 'repeated_failed_auth',
            'severity' => 'high',
            'ip_address' => $ip_address,
            'failed_attempts' => $failed_count,
            'time_period' => '1 hour'
        ]);
    }
    
    // Check for authentication from multiple IPs for same user
    if ($userid) {
        $ip_count_sql = "
            SELECT COUNT(DISTINCT ip_address) as ip_count
            FROM {local_alx_auth_attempts}
            WHERE userid = ? AND timeaccessed >= ?
        ";
        $ip_count = $DB->get_field_sql($ip_count_sql, [$userid, $last_hour]);
        
        if ($ip_count >= 5) {
            local_alx_report_api_trigger_security_alert([
                'type' => 'multiple_ip_auth',
                'severity' => 'medium',
                'userid' => $userid,
                'ip_count' => $ip_count,
                'time_period' => '1 hour'
            ]);
        }
    }
}

/**
 * Get authentication analytics and security insights
 */
function local_alx_report_api_get_auth_analytics($hours = 24) {
    global $DB;
    
    $start_time = time() - ($hours * 3600);
    
    // Basic auth stats
    $auth_stats_sql = "
        SELECT 
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN success = 1 THEN 1 END) as successful_attempts,
            COUNT(CASE WHEN success = 0 THEN 1 END) as failed_attempts,
            COUNT(DISTINCT ip_address) as unique_ips,
            COUNT(DISTINCT userid) as unique_users
        FROM {local_alx_auth_attempts}
        WHERE timeaccessed >= ?
    ";
    
    try {
        $auth_stats = $DB->get_record_sql($auth_stats_sql, [$start_time]);
    } catch (Exception $e) {
        // If table doesn't exist, return empty stats
        $auth_stats = (object)[
            'total_attempts' => 0,
            'successful_attempts' => 0,
            'failed_attempts' => 0,
            'unique_ips' => 0,
            'unique_users' => 0
        ];
    }
    
    // Top failing IPs
    $failing_ips_sql = "
        SELECT 
            ip_address,
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN success = 0 THEN 1 END) as failed_attempts,
            MAX(timeaccessed) as last_attempt
        FROM {local_alx_auth_attempts}
        WHERE timeaccessed >= ?
        GROUP BY ip_address
        HAVING COUNT(CASE WHEN success = 0 THEN 1 END) > 5
        ORDER BY failed_attempts DESC
        LIMIT 10
    ";
    
    try {
        $failing_ips = $DB->get_records_sql($failing_ips_sql, [$start_time]);
    } catch (Exception $e) {
        $failing_ips = [];
    }
    
    // Authentication timeline (hourly)
    $timeline_sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00') as hour_bucket,
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN success = 1 THEN 1 END) as successful_attempts,
            COUNT(CASE WHEN success = 0 THEN 1 END) as failed_attempts
        FROM {local_alx_auth_attempts}
        WHERE timeaccessed >= ?
        GROUP BY FROM_UNIXTIME(timeaccessed, '%Y-%m-%d %H:00:00')
        ORDER BY hour_bucket
    ";
    
    try {
        $timeline = $DB->get_records_sql($timeline_sql, [$start_time]);
    } catch (Exception $e) {
        $timeline = [];
    }
    
    return [
        'stats' => $auth_stats,
        'failing_ips' => array_values($failing_ips),
        'timeline' => array_values($timeline),
        'security_score' => local_alx_report_api_calculate_security_score($auth_stats, $failing_ips),
        'analysis_period' => $hours
    ];
}

/**
 * Calculate security score based on authentication patterns
 */
function local_alx_report_api_calculate_security_score($auth_stats, $failing_ips) {
    $score = 100;
    
    // Reduce score based on failure rate
    if ($auth_stats->total_attempts > 0) {
        $failure_rate = ($auth_stats->failed_attempts / $auth_stats->total_attempts) * 100;
        
        if ($failure_rate > 50) {
            $score -= 40;
        } elseif ($failure_rate > 25) {
            $score -= 20;
        } elseif ($failure_rate > 10) {
            $score -= 10;
        }
    }
    
    // Reduce score based on suspicious IPs
    $suspicious_ip_count = count($failing_ips);
    if ($suspicious_ip_count > 10) {
        $score -= 30;
    } elseif ($suspicious_ip_count > 5) {
        $score -= 15;
    } elseif ($suspicious_ip_count > 2) {
        $score -= 10;
    }
    
    return max(0, $score);
}

/**
 * Trigger security alert for suspicious authentication activity
 */
function local_alx_report_api_trigger_security_alert($alert_data) {
    $message = '';
    
    switch ($alert_data['type']) {
        case 'repeated_failed_auth':
            $message = "🚨 SECURITY ALERT: {$alert_data['failed_attempts']} failed authentication attempts from IP {$alert_data['ip_address']} in the last {$alert_data['time_period']}";
            break;
        case 'multiple_ip_auth':
            $message = "⚠️ SECURITY NOTICE: User ID {$alert_data['userid']} authenticated from {$alert_data['ip_count']} different IP addresses in the last {$alert_data['time_period']}";
            break;
    }
    
    if ($message) {
        local_alx_report_api_send_alert('security', $alert_data['severity'], 'Authentication Security Alert', $message, $alert_data);
    }
}

/**
 * Verify ALX Report API service installation and configuration
 * This function checks all aspects of the service setup and can fix issues automatically
 * 
 * @return array Detailed status report with any issues found and fixes applied
 */
function local_alx_report_api_verify_service_installation() {
    global $DB;
    
    $issues = [];
    $fixes_applied = [];
    $warnings = [];
    
    try {
        // 1. Check if web services are enabled
        if (!get_config('moodle', 'enablewebservices')) {
            $issues[] = 'Web services not enabled';
            set_config('enablewebservices', 1);
            $fixes_applied[] = 'Enabled web services';
        }
        
        // 2. Check if REST protocol is enabled
        $enabledprotocols = get_config('moodle', 'webserviceprotocols');
        if (strpos($enabledprotocols, 'rest') === false) {
            $issues[] = 'REST protocol not enabled';
            if (empty($enabledprotocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $enabledprotocols . ',rest');
            }
            $fixes_applied[] = 'Enabled REST protocol';
        }
        
        // 3. Check service exists (try both service names for compatibility)
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        $legacy_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        
        if (!$service && !$legacy_service) {
            $issues[] = 'ALX Report API service not found';
            
            // Create the custom service
            $service_obj = new stdClass();
            $service_obj->name = 'ALX Report API Service';
            $service_obj->shortname = 'alx_report_api_custom';
            $service_obj->enabled = 1;
            $service_obj->restrictedusers = 1;
            $service_obj->downloadfiles = 0;
            $service_obj->uploadfiles = 0;
            $service_obj->timecreated = time();
            $service_obj->timemodified = time();
            
            $serviceid = $DB->insert_record('external_services', $service_obj);
            $service = $DB->get_record('external_services', ['id' => $serviceid]);
            $fixes_applied[] = 'Created ALX Report API service';
        } elseif (!$service && $legacy_service) {
            $service = $legacy_service;
            $warnings[] = 'Using legacy service name "alx_report_api" - consider upgrading to "alx_report_api_custom"';
        }
        
        // 4. Check function mapping (CRITICAL - this is the main issue you reported)
        if ($service) {
            $function_mapped = $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$function_mapped) {
                $issues[] = 'Function not mapped to service';
                
                // Clear any duplicate mappings first
                $DB->delete_records('external_services_functions', [
                    'externalserviceid' => $service->id,
                    'functionname' => 'local_alx_report_api_get_course_progress'
                ]);
                
                // Add function mapping
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $function_id = $DB->insert_record('external_services_functions', $function);
                
                if ($function_id) {
                    $fixes_applied[] = 'Mapped function to service';
                    
                    // Verify the mapping was successful
                    $verify_mapping = $DB->record_exists('external_services_functions', [
                        'externalserviceid' => $service->id,
                        'functionname' => 'local_alx_report_api_get_course_progress'
                    ]);
                    
                    if (!$verify_mapping) {
                        $issues[] = 'Function mapping verification failed - may need manual intervention';
                    }
                } else {
                    $issues[] = 'Failed to create function mapping';
                }
            }
        }
        
        // 5. Check if service is enabled
        if ($service && !$service->enabled) {
            $issues[] = 'Service is disabled';
            $service->enabled = 1;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            $fixes_applied[] = 'Enabled ALX Report API service';
        }
        
        // 6. Check active tokens
        $active_tokens = 0;
        if ($service) {
            $active_tokens = $DB->count_records_select('external_tokens', 
                'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
                [$service->id, time()]
            );
        }
        
        // 7. Clear caches to ensure changes take effect
        if (!empty($fixes_applied)) {
            if (function_exists('cache_helper')) {
                cache_helper::purge_by_definition('core', 'external_services');
                cache_helper::purge_by_definition('core', 'external_functions');
            }
            $fixes_applied[] = 'Cleared web service caches';
        }
        
        // 8. Generate service status summary
        $service_status = [
            'service_exists' => !empty($service),
            'service_enabled' => $service ? (bool)$service->enabled : false,
            'function_mapped' => $service ? $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]) : false,
            'active_tokens' => $active_tokens,
            'webservices_enabled' => (bool)get_config('moodle', 'enablewebservices'),
            'rest_enabled' => strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false,
            'service_id' => $service ? $service->id : null,
            'service_name' => $service ? $service->shortname : null
        ];
        
        return [
            'success' => true,
            'issues_found' => $issues,
            'fixes_applied' => $fixes_applied,
            'warnings' => $warnings,
            'service_status' => $service_status,
            'service_ready' => empty($issues) && $service_status['function_mapped'],
            'message' => empty($issues) ? 'Service configuration verified successfully!' : 'Issues found and fixed automatically.'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'issues_found' => $issues,
            'fixes_applied' => $fixes_applied,
            'warnings' => $warnings,
            'service_ready' => false,
            'message' => 'Error during service verification: ' . $e->getMessage()
        ];
    }
}

/**
 * Quick service status check (lightweight version for dashboard display)
 * 
 * @return array Basic service status information
 */
function local_alx_report_api_get_service_status() {
    global $DB;
    
    $status = [
        'healthy' => false,
        'service_exists' => false,
        'function_mapped' => false,
        'tokens_available' => false,
        'config_valid' => false,
        'issues' => []
    ];
    
    try {
        // Check web services configuration
        $webservices_enabled = get_config('moodle', 'enablewebservices');
        $rest_enabled = strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false;
        $status['config_valid'] = $webservices_enabled && $rest_enabled;
        
        if (!$webservices_enabled) {
            $status['issues'][] = 'Web services disabled';
        }
        if (!$rest_enabled) {
            $status['issues'][] = 'REST protocol disabled';
        }
        
        // Check service exists
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        if (!$service) {
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        }
        
        $status['service_exists'] = !empty($service);
        if (!$service) {
            $status['issues'][] = 'API service not found';
            return $status;
        }
        
        // Check function mapping
        $status['function_mapped'] = $DB->record_exists('external_services_functions', [
            'externalserviceid' => $service->id,
            'functionname' => 'local_alx_report_api_get_course_progress'
        ]);
        
        if (!$status['function_mapped']) {
            $status['issues'][] = 'Function not mapped to service';
        }
        
        // Check active tokens
        $active_tokens = $DB->count_records_select('external_tokens', 
            'externalserviceid = ? AND (validuntil IS NULL OR validuntil > ?)', 
            [$service->id, time()]
        );
        $status['tokens_available'] = $active_tokens > 0;
        
        if ($active_tokens == 0) {
            $status['issues'][] = 'No active API tokens';
        }
        
        // Overall health assessment
        $status['healthy'] = $status['config_valid'] && $status['service_exists'] && 
                           $status['function_mapped'] && $status['tokens_available'];
        
        return $status;
        
    } catch (Exception $e) {
        $status['issues'][] = 'Error checking service status: ' . $e->getMessage();
        return $status;
    }
}
