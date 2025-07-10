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
                return;
            }
            
            $this->log_message("Found " . count($companies) . " companies to process");
            
            foreach ($companies as $company) {
                if (time() - $start_time > $max_execution_time - 30) {
                    $this->log_message("Approaching execution time limit, stopping sync");
                    break;
                }
                
                $company_start = time();
                $this->log_message("Processing company: {$company->name} (ID: {$company->id})");
                
                try {
                    // Check if company has API settings (indicates they use the API)
                    $has_settings = $DB->record_exists('local_alx_api_settings', ['companyid' => $company->id]);
                    
                    if (!$has_settings) {
                        $this->log_message("Company {$company->id} has no API settings, skipping");
                        continue;
                    }
                    
                    // Run incremental sync for this company
                    $company_stats = $this->sync_company_changes($company->id, $sync_hours);
                    
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
            
            if ($total_stats['total_errors'] > 0) {
                $this->log_message("Total errors: {$total_stats['total_errors']}");
                $this->log_message("Companies with errors: " . implode(', ', $total_stats['companies_with_errors']));
            }
            
            // Update last sync timestamp
            set_config('last_auto_sync', time(), 'local_alx_report_api');
            set_config('last_sync_stats', json_encode($total_stats), 'local_alx_report_api');
            
        } catch (\Exception $e) {
            $this->log_message("Critical sync error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync changes for a specific company.
     *
     * @param int $companyid Company ID
     * @param int $hours_back Hours to look back for changes (fallback if no last sync)
     * @return array Statistics
     */
    private function sync_company_changes($companyid, $hours_back) {
        global $DB;
        
        // Ensure performance debugging is off, as it can interfere with cursors.
        $DB->set_debug(false);
        
        // Get the last sync timestamp for this company
        $cron_token = 'cron_task_' . $companyid;
        $last_sync = $DB->get_field('local_alx_api_sync_status', 'last_sync_timestamp', [
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
                WHERE cc.timecompleted >= :cutoff_time AND EXISTS (
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
                WHERE cmc.timemodified >= :cutoff_time AND EXISTS (
                    SELECT 1 FROM {company_users} cu
                    WHERE cu.userid = cmc.userid AND cu.companyid = :companyid
                )";
            $module_changes = $DB->get_records_sql($module_sql, $params);
        } catch (\Exception $e) {
            $stats['errors'][] = "Error querying module completions: " . $e->getMessage();
        }
            
            // Find users with recent enrollment changes
        try {
            $enrollment_sql = "
                SELECT DISTINCT ue.userid, e.courseid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.timemodified >= :cutoff_time AND EXISTS (
                    SELECT 1 FROM {company_users} cu
                    WHERE cu.userid = ue.userid AND cu.companyid = :companyid
                )";
            $enrollment_changes = $DB->get_records_sql($enrollment_sql, $params);
        } catch (\Exception $e) {
            $stats['errors'][] = "Error querying enrollments: " . $e->getMessage();
        }
            
        // Combine all changes and get unique users/courses to update
        $all_changes = array_merge($completion_changes, $module_changes, $enrollment_changes);
        
        if (empty($all_changes)) {
            return $stats;
        }

        $updates_to_process = [];
        foreach ($all_changes as $change) {
            $key = "{$change->userid}-{$change->courseid}";
            if (!isset($updates_to_process[$key])) {
                $updates_to_process[$key] = $change;
            }
        }

        foreach ($updates_to_process as $update) {
            try {
                $update_result = local_alx_report_api_update_reporting_record($update->userid, $companyid, $update->courseid);
                
                if ($update_result['created']) {
                    $stats['records_created']++;
                } else if ($update_result['updated']) {
                    $stats['records_updated']++;
                }
            } catch (\Exception $e) {
                $error_msg = "Error updating user {$update->userid}, course {$update->courseid}: " . $e->getMessage();
                $stats['errors'][] = $error_msg;
            }
        }
        
        $updated_user_ids = array_unique(array_map(function($c) { return $c->userid; }, $updates_to_process));
        $stats['users_updated'] = count($updated_user_ids);
        
        return $stats;
    }

    /**
     * Clear cache entries for a company to ensure fresh data.
     *
     * @param int $companyid Company ID
     */
    private function clear_company_cache($companyid) {
        global $DB;
        
        try {
            $deleted = $DB->delete_records('local_alx_api_cache', ['companyid' => $companyid]);
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