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
 * Scheduled task for syncing reporting data from main database.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_alx_report_api\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

use local_alx_report_api\constants;

/**
 * Scheduled task to sync reporting data incrementally.
 */
class sync_reporting_data_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown in admin screens).
     *
     * @return string
     */
    public function get_name() {
        return get_string('sync_reporting_data_task', 'local_alx_report_api');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $start_time = time();
        $this->log_message("=== ALX Report API Incremental Sync Started ===");
        
        // Get sync configuration
        $sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
        $max_execution_time = get_config('local_alx_report_api', 'max_sync_time') ?: 300; // 5 minutes default
        
        $this->log_message("Sync configuration: {$sync_hours} hours back, max execution time: {$max_execution_time} seconds");
        
        // Check if another sync task is already running (prevent overlaps)
        $lock_key = 'sync_task_lock';
        $lock_timeout = 3600; // 1 hour max lock time
        
        if (!$this->acquire_lock($lock_key, $lock_timeout)) {
            $this->log_message("Another sync task is already running. Skipping this execution to prevent overlap.");
            return;
        }
        
        // Set execution time limit
        set_time_limit($max_execution_time);
        
        $total_stats = [
            'companies_processed' => 0,
            'total_users_updated' => 0,
            'total_records_updated' => 0,
            'total_records_created' => 0,
            'total_errors' => 0,
            'companies_with_errors' => []
        ];

        try {
            // Get all companies with API access
            $companies = local_alx_report_api_get_companies();
            
            if (empty($companies)) {
                $this->log_message("No companies found for sync");
                $this->release_lock($lock_key);
                return;
            }
            
            $this->log_message("Found " . count($companies) . " companies to process");
            
            foreach ($companies as $company) {
                // Hard timeout check - stop gracefully before time limit
                if (time() - $start_time > $max_execution_time - 30) {
                    $this->log_message("Approaching execution time limit ({$max_execution_time}s), stopping sync gracefully");
                    $total_stats['timeout_reached'] = true;
                    break;
                }
                
                $company_start = time();
                $this->log_message("Processing company: {$company->name} (ID: {$company->id})");
                
                try {
                    // Check if company has API settings (indicates they use the API)
                    $has_settings = $DB->record_exists(constants::TABLE_SETTINGS, ['companyid' => $company->id]);
                    
                    if (!$has_settings) {
                        $this->log_message("Company {$company->id} has no API settings, skipping");
                        continue;
                    }
                    
                    // Run incremental sync for this company
                    $company_stats = $this->sync_company_changes($company->id, $sync_hours, $start_time, $max_execution_time);
                    
                    $total_stats['companies_processed']++;
                    $total_stats['total_users_updated'] += $company_stats['users_updated'];
                    $total_stats['total_records_updated'] += $company_stats['records_updated'];
                    $total_stats['total_records_created'] += $company_stats['records_created'];
                    
                    if (!empty($company_stats['errors'])) {
                        $total_stats['total_errors'] += count($company_stats['errors']);
                        $total_stats['companies_with_errors'][] = $company->id;
                        
                        foreach ($company_stats['errors'] as $error) {
                            $this->log_message("Company {$company->id} error: {$error}");
                        }
                    }
                    
                    $company_duration = time() - $company_start;
                    $this->log_message("Company {$company->id} completed in {$company_duration}s: " .
                        "{$company_stats['users_updated']} users, {$company_stats['records_updated']} records updated");
                    
                    // Clear cache entries for this company to ensure fresh data
                    $this->clear_company_cache($company->id);
                    
                    // Update sync status for this company
                    $this->update_company_sync_status($company->id, $company_stats);
                    
                } catch (\Exception $e) {
                    $total_stats['total_errors']++;
                    $total_stats['companies_with_errors'][] = $company->id;
                    $this->log_message("Company {$company->id} failed: " . $e->getMessage());
                }
            }
            
            // Log final statistics
            $total_duration = time() - $start_time;
            $this->log_message("=== Sync Completed in {$total_duration} seconds ===");
            $this->log_message("Companies processed: {$total_stats['companies_processed']}");
            $this->log_message("Total users updated: {$total_stats['total_users_updated']}");
            $this->log_message("Total records updated: {$total_stats['total_records_updated']}");
            $this->log_message("Total records created: {$total_stats['total_records_created']}");
            
            if (isset($total_stats['timeout_reached']) && $total_stats['timeout_reached']) {
                $this->log_message("WARNING: Sync stopped due to timeout. Some companies may not have been processed.");
            }
            
            if ($total_stats['total_errors'] > 0) {
                $this->log_message("Total errors: {$total_stats['total_errors']}");
                $this->log_message("Companies with errors: " . implode(', ', $total_stats['companies_with_errors']));
            }
            
            // Update last sync timestamp
            set_config('last_auto_sync', time(), 'local_alx_report_api');
            set_config('last_sync_stats', json_encode($total_stats), 'local_alx_report_api');
            
        } catch (\Exception $e) {
            $this->log_message("Critical sync error: " . $e->getMessage());
            // Always release lock on error
            $this->release_lock($lock_key);
            throw $e;
        } finally {
            // Always release lock when done (cleanup on exit)
            $this->release_lock($lock_key);
            $this->log_message("Sync task lock released");
        }
    }

    /**
     * Sync changes for a specific company.
     *
     * @param int $companyid Company ID
     * @param int $hours_back Hours to look back for changes (fallback if no last sync)
     * @param int $start_time Task start time for timeout checking
     * @param int $max_execution_time Maximum execution time in seconds
     * @return array Statistics
     */
    private function sync_company_changes($companyid, $hours_back, $start_time, $max_execution_time) {
        global $DB;
        
        // Ensure performance debugging is off, as it can interfere with cursors.
        $DB->set_debug(false);
        
        // Get the last sync timestamp for this company
        $cron_token = 'cron_task_' . $companyid;
        $last_sync = $DB->get_field(constants::TABLE_SYNC_STATUS, 'last_sync_timestamp', [
            'companyid' => $companyid,
            'token_hash' => hash('sha256', $cron_token)
        ]);
        
        // If no last sync found, use the hours_back parameter
        $cutoff_time = $last_sync ? $last_sync : (time() - ($hours_back * 3600));
        
        $stats = [
            'users_updated' => 0,
            'records_updated' => 0,
            'records_created' => 0,
            'errors' => []
        ];
        
        $completion_changes = [];
        $module_changes = [];
        $enrollment_changes = [];
        $params = ['cutoff_time' => $cutoff_time, 'companyid' => $companyid];

            // Find users with recent course completion changes
        try {
            $completion_sql = "
                SELECT DISTINCT cc.userid, cc.course as courseid
                FROM {course_completions} cc
                WHERE cc.timecompleted > :cutoff_time AND EXISTS (
                    SELECT 1 FROM {company_users} cu
                    WHERE cu.userid = cc.userid AND cu.companyid = :companyid
                )";
            $completion_changes = $DB->get_records_sql($completion_sql, $params);
        } catch (\Exception $e) {
            $error_message = "Error querying course completions: " . $e->getMessage();
            if (property_exists($e, 'debuginfo')) {
                $error_message .= " | Debug Info: " . $e->debuginfo;
            }
            $stats['errors'][] = $error_message;
        }
            
            // Find users with recent module completion changes
        try {
            $module_sql = "
                SELECT DISTINCT cmc.userid, cm.course as courseid
                FROM {course_modules_completion} cmc
                JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                WHERE cmc.timemodified > :cutoff_time AND EXISTS (
                    SELECT 1 FROM {company_users} cu
                    WHERE cu.userid = cmc.userid AND cu.companyid = :companyid
                )";
            $module_changes = $DB->get_records_sql($module_sql, $params);
        } catch (\Exception $e) {
            $stats['errors'][] = "Error querying module completions: " . $e->getMessage();
        }
            
            // Find users with recent enrollment changes
        // BUG FIX #12: When a user has ANY enrollment change, sync ALL their courses
        try {
            // Step 1: Find users who have recent enrollment changes
            $users_with_enrollment_changes_sql = "
                SELECT DISTINCT ue.userid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.timemodified > :cutoff_time AND EXISTS (
                    SELECT 1 FROM {company_users} cu
                    WHERE cu.userid = ue.userid AND cu.companyid = :companyid
                )";
            $users_with_changes = $DB->get_records_sql($users_with_enrollment_changes_sql, $params);
            
            // Step 2: For each user with enrollment changes, get ALL their course enrollments
            if (!empty($users_with_changes)) {
                $user_ids = array_keys($users_with_changes);
                list($user_sql, $user_params) = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'user');
                
                $all_enrollments_sql = "
                    SELECT DISTINCT CONCAT(ue.userid, '-', e.courseid) as id, ue.userid, e.courseid
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = e.courseid
                    JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                    WHERE ue.userid $user_sql 
                    AND ue.status = 0
                    AND EXISTS (
                        SELECT 1 FROM {company_users} cu
                        WHERE cu.userid = ue.userid AND cu.companyid = :companyid
                    )";
                
                $all_params = array_merge($user_params, ['companyid' => $companyid]);
                $enrollment_changes = $DB->get_records_sql($all_enrollments_sql, $all_params);
            } else {
                $enrollment_changes = [];
            }
        } catch (\Exception $e) {
            $stats['errors'][] = "Error querying enrollments: " . $e->getMessage();
        }
        
        // Find users with recent profile changes (firstname, lastname, email, username)
        $user_profile_changes = [];
        try {
            $user_profile_sql = "
                SELECT DISTINCT u.id as userid, r.courseid
                FROM {user} u
                JOIN {company_users} cu ON cu.userid = u.id
                JOIN {local_alx_api_reporting} r ON r.userid = u.id AND r.companyid = cu.companyid
                WHERE u.timemodified > :cutoff_time
                AND cu.companyid = :companyid
                AND u.deleted = 0
                AND u.suspended = 0
                AND u.timemodified > r.last_updated";
            $user_profile_changes = $DB->get_records_sql($user_profile_sql, $params);
        } catch (\Exception $e) {
            $stats['errors'][] = "Error querying user profile changes: " . $e->getMessage();
        }
            
        // Combine all changes and get unique users/courses to update
        $all_changes = array_merge($completion_changes, $module_changes, $enrollment_changes, $user_profile_changes);
        
        // BUG FIX #11: Don't return early - always run deletion detection even if no updates
        // Process updates only if there are changes
        if (!empty($all_changes)) {
            $updates_to_process = [];
            foreach ($all_changes as $change) {
                $key = "{$change->userid}-{$change->courseid}";
                if (!isset($updates_to_process[$key])) {
                    $updates_to_process[$key] = $change;
                }
            }

            foreach ($updates_to_process as $update) {
                // Check timeout during processing
                if (time() - $start_time > $max_execution_time - 60) {
                    $this->log_message("Timeout approaching during company {$companyid} processing, stopping early");
                    $stats['errors'][] = "Processing stopped early due to timeout";
                    break;
                }
                
                try {
                    $update_result = local_alx_report_api_update_reporting_record($update->userid, $companyid, $update->courseid);
                    
                    if ($update_result['created']) {
                        $stats['records_created']++;
                    } else if ($update_result['updated']) {
                        $stats['records_updated']++;
                    } else if (isset($update_result['deleted']) && $update_result['deleted']) {
                        if (!isset($stats['records_deleted'])) {
                            $stats['records_deleted'] = 0;
                        }
                        $stats['records_deleted']++;
                    }
                } catch (\Exception $e) {
                    $error_msg = "Error updating user {$update->userid}, course {$update->courseid}: " . $e->getMessage();
                    $stats['errors'][] = $error_msg;
                }
            }
        }
        
        // Detect and mark deleted/suspended users and unenrolled courses
        try {
            $deletion_sql = "
                SELECT DISTINCT r.userid, r.courseid
                FROM {local_alx_api_reporting} r
                WHERE r.companyid = :companyid
                AND r.is_deleted = 0
                AND (
                    -- User is deleted or suspended
                    EXISTS (
                        SELECT 1 FROM {user} u
                        WHERE u.id = r.userid
                        AND (u.deleted = 1 OR u.suspended = 1)
                    )
                    -- OR user no longer in company
                    OR NOT EXISTS (
                        SELECT 1 FROM {company_users} cu
                        WHERE cu.userid = r.userid
                        AND cu.companyid = :companyid2
                    )
                    -- OR user no longer enrolled in course
                    OR NOT EXISTS (
                        SELECT 1 FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        WHERE ue.userid = r.userid
                        AND e.courseid = r.courseid
                    )
                    -- OR course is hidden
                    OR EXISTS (
                        SELECT 1 FROM {course} c
                        WHERE c.id = r.courseid
                        AND c.visible = 0
                    )
                )";
            
            $records_to_delete = $DB->get_records_sql($deletion_sql, [
                'companyid' => $companyid,
                'companyid2' => $companyid
            ]);
            
            if (!isset($stats['records_deleted'])) {
                $stats['records_deleted'] = 0;
            }
            
            foreach ($records_to_delete as $record) {
                // Check timeout
                if (time() - $start_time > $max_execution_time - 60) {
                    break;
                }
                
                try {
                    if (local_alx_report_api_soft_delete_reporting_record($record->userid, $companyid, $record->courseid)) {
                        $stats['records_deleted']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = "Error deleting user {$record->userid}, course {$record->courseid}: " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $stats['errors'][] = "Deletion detection error: " . $e->getMessage();
        }
        
        // Calculate users updated (only if updates were processed)
        if (!empty($all_changes)) {
            $updated_user_ids = array_unique(array_map(function($c) { return $c->userid; }, $updates_to_process));
            $stats['users_updated'] = count($updated_user_ids);
        } else {
            $stats['users_updated'] = 0;
        }
        
        return $stats;
    }

    /**
     * Acquire a lock to prevent overlapping task executions.
     *
     * @param string $lock_key Lock identifier
     * @param int $timeout Maximum lock age in seconds
     * @return bool True if lock acquired, false if another task is running
     */
    private function acquire_lock($lock_key, $timeout) {
        global $DB;
        
        try {
            // Check for existing lock
            $existing_lock = get_config('local_alx_report_api', $lock_key);
            
            if ($existing_lock) {
                $lock_age = time() - $existing_lock;
                
                // If lock is older than timeout, it's stale - remove it
                if ($lock_age > $timeout) {
                    $this->log_message("Found stale lock (age: {$lock_age}s), removing it");
                    unset_config($lock_key, 'local_alx_report_api');
                } else {
                    // Lock is still valid, another task is running
                    $this->log_message("Active lock found (age: {$lock_age}s), another task is running");
                    return false;
                }
            }
            
            // Acquire the lock
            set_config($lock_key, time(), 'local_alx_report_api');
            $this->log_message("Lock acquired successfully");
            return true;
            
        } catch (\Exception $e) {
            $this->log_message("Error acquiring lock: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Release the task lock.
     *
     * @param string $lock_key Lock identifier
     */
    private function release_lock($lock_key) {
        try {
            unset_config($lock_key, 'local_alx_report_api');
        } catch (\Exception $e) {
            $this->log_message("Error releasing lock: " . $e->getMessage());
        }
    }
    
    /**
     * Clear cache entries for a company to ensure fresh data.
     *
     * @param int $companyid Company ID
     */
    private function clear_company_cache($companyid) {
        global $DB;
        
        try {
            $deleted = $DB->delete_records(constants::TABLE_CACHE, ['companyid' => $companyid]);
            if ($deleted > 0) {
                $this->log_message("Cleared {$deleted} cache entries for company {$companyid}");
            }
        } catch (\Exception $e) {
            $this->log_message("Failed to clear cache for company {$companyid}: " . $e->getMessage());
        }
    }

    /**
     * Update sync status for a company.
     *
     * @param int $companyid Company ID
     * @param array $stats Company sync statistics
     */
    private function update_company_sync_status($companyid, $stats) {
        // Use a special token for cron tasks to maintain unique constraint
        $cron_token = 'cron_task_' . $companyid;
        $total_records = $stats['records_updated'] + $stats['records_created'];
        $status = empty($stats['errors']) ? 'success' : 'failed';
        $error_message = empty($stats['errors']) ? null : implode('; ', $stats['errors']);
        
        local_alx_report_api_update_sync_status(
            $companyid,
            $cron_token,
            $total_records,
            $status,
            $error_message
        );
    }

    /**
     * Log a message with timestamp.
     *
     * @param string $message Message to log
     */
    private function log_message($message) {
        $timestamp = date('Y-m-d H:i:s');
        mtrace("[{$timestamp}] ALX Sync: {$message}");
    }
}