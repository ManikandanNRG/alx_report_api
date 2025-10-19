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
 * Background sync script for ALX Report API reporting table.
 * 
 * This script maintains the reporting table by detecting and syncing changes
 * from the main database. Can be run manually or via cron.
 *
 * Usage:
 * - Via web browser: /local/alx_report_api/sync_reporting_data.php
 * - Via CLI: php sync_reporting_data.php
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include Moodle config
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set longer execution time for large syncs
set_time_limit(300); // 5 minutes

// Disable output buffering for real-time updates
if (ob_get_level()) {
    ob_end_flush();
}

// Security check - use admin setup like control_center.php
require_once($CFG->libdir . '/adminlib.php');

// Suppress theme warnings for undefined $company variable (iomadmoon theme issue)
$company = null;

admin_externalpage_setup('local_alx_report_api_sync');

// Set up page
$PAGE->set_url('/local/alx_report_api/sync_reporting_data.php');
$PAGE->set_title('ALX Report API - Manual Data Sync');
$PAGE->set_heading('ALX Report API - Manual Data Sync');

// Check if this is a CLI request
$is_cli = (php_sapi_name() === 'cli');

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$sync_type = optional_param('sync_type', 'changed', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$hours_back = optional_param('hours_back', 24, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Debug: Log what we received
if ($action) {
    error_log("Sync action received: action=$action, confirm=$confirm, companyid=$companyid, hours_back=$hours_back");
}

/**
 * Sync changes from the last N hours
 */
function sync_recent_changes($hours_back = 24, $companyid = 0) {
    global $DB;
    
    $cutoff_time = time() - ($hours_back * 3600);
    $stats = [
        'users_updated' => 0,
        'records_updated' => 0,
        'records_created' => 0,
        'errors' => []
    ];
    
    try {
        echo "Looking for changes since " . date('Y-m-d H:i:s', $cutoff_time) . "...\n";
        flush();
        
        // Find users with recent course completion changes
        $completion_sql = "
            SELECT DISTINCT cc.userid, cu.companyid, cc.course as courseid
            FROM {course_completions} cc
            JOIN {company_users} cu ON cu.userid = cc.userid
            WHERE cc.timemodified > :cutoff_time";
        
        $params = ['cutoff_time' => $cutoff_time];
        
        if ($companyid > 0) {
            $completion_sql .= " AND cu.companyid = :companyid";
            $params['companyid'] = $companyid;
        }
        
        $completion_changes = $DB->get_records_sql($completion_sql, $params);
        echo "Found " . count($completion_changes) . " course completion changes\n";
        flush();
        
        // Find users with recent module completion changes
        $module_sql = "
            SELECT DISTINCT cmc.userid, cu.companyid, cm.course as courseid
            FROM {course_modules_completion} cmc
            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
            JOIN {company_users} cu ON cu.userid = cmc.userid
            WHERE cmc.timemodified > :cutoff_time";
        
        if ($companyid > 0) {
            $module_sql .= " AND cu.companyid = :companyid";
        }
        
        $module_changes = $DB->get_records_sql($module_sql, $params);
        echo "Found " . count($module_changes) . " module completion changes\n";
        flush();
        
        // Find users with recent enrollment changes
        $enrollment_sql = "
            SELECT DISTINCT ue.userid, cu.companyid, e.courseid
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {company_users} cu ON cu.userid = ue.userid
            WHERE ue.timemodified > :cutoff_time";
        
        if ($companyid > 0) {
            $enrollment_sql .= " AND cu.companyid = :companyid";
        }
        
        $enrollment_changes = $DB->get_records_sql($enrollment_sql, $params);
        echo "Found " . count($enrollment_changes) . " enrollment changes\n";
        flush();
        
        // Combine all changes
        $all_changes = [];
        foreach ([$completion_changes, $module_changes, $enrollment_changes] as $changes) {
            foreach ($changes as $change) {
                $key = $change->userid . '_' . $change->companyid . '_' . $change->courseid;
                $all_changes[$key] = $change;
            }
        }
        
        echo "Processing " . count($all_changes) . " unique records...\n";
        flush();
        
        // Process each change
        $processed = 0;
        foreach ($all_changes as $change) {
            // Check if record exists
            $exists = $DB->record_exists('local_alx_api_reporting', [
                'userid' => $change->userid,
                'companyid' => $change->companyid,
                'courseid' => $change->courseid
            ]);
            
            if (local_alx_report_api_update_reporting_record($change->userid, $change->companyid, $change->courseid)) {
                if ($exists) {
                    $stats['records_updated']++;
                } else {
                    $stats['records_created']++;
                }
            }
            
            $processed++;
            if ($processed % 10 == 0) {
                echo "Processed $processed/" . count($all_changes) . " records...\n";
                flush();
            }
        }
        
        $stats['users_updated'] = count($all_changes);
        echo "Sync completed successfully!\n";
        flush();
        
    } catch (Exception $e) {
        $error_msg = 'Sync error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $stats['errors'][] = $error_msg;
        echo "ERROR: " . $error_msg . "\n";
        flush();
    }
    
    return $stats;
}

/**
 * Full sync for a specific company
 */
function sync_company_full($companyid) {
    global $DB;
    
    $stats = [
        'records_processed' => 0,
        'records_updated' => 0,
        'records_created' => 0,
        'errors' => []
    ];
    
    try {
        // Get company info
        $company = $DB->get_record('company', ['id' => $companyid], 'id, name');
        if (!$company) {
            throw new Exception("Company not found with ID: $companyid");
        }
        
        echo "Starting full sync for company: " . $company->name . "\n";
        flush();
        
        // Get all users for this company
        $users = $DB->get_records('company_users', ['companyid' => $companyid], '', 'userid');
        echo "Found " . count($users) . " users in company\n";
        flush();
        
        if (empty($users)) {
            echo "No users found for this company\n";
            return $stats;
        }
        
        $user_count = 0;
        foreach ($users as $user) {
            $user_count++;
            
            // Get all courses for this user
            $sql = "SELECT DISTINCT e.courseid
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE ue.userid = ? AND ue.status = 0";
            
            $courses = $DB->get_records_sql($sql, [$user->userid]);
            
            foreach ($courses as $course) {
                // Check if record exists
                $exists = $DB->record_exists('local_alx_api_reporting', [
                    'userid' => $user->userid,
                    'companyid' => $companyid,
                    'courseid' => $course->courseid
                ]);
                
                if (local_alx_report_api_update_reporting_record($user->userid, $companyid, $course->courseid)) {
                    if ($exists) {
                        $stats['records_updated']++;
                    } else {
                        $stats['records_created']++;
                    }
                }
            }
            
            $stats['records_processed']++;
            
            if ($user_count % 10 == 0) {
                echo "Processed $user_count/" . count($users) . " users...\n";
                flush();
            }
        }
        
        echo "Full sync completed successfully!\n";
        flush();
        
    } catch (Exception $e) {
        $error_msg = 'Full sync error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $stats['errors'][] = $error_msg;
        echo "ERROR: " . $error_msg . "\n";
        flush();
    }
    
    return $stats;
}

/**
 * Clean up orphaned records (users/courses that no longer exist)
 */
function cleanup_orphaned_records($companyid = 0) {
    global $DB;
    
    $stats = [
        'orphaned_users' => 0,
        'orphaned_courses' => 0,
        'orphaned_enrollments' => 0,
        'errors' => []
    ];
    
    try {
        echo "Starting cleanup of orphaned records...\n";
        flush();
        
        // Find reporting records for users who are no longer in the company
        $orphaned_users_sql = "
            SELECT r.id
            FROM {local_alx_api_reporting} r
            LEFT JOIN {company_users} cu ON cu.userid = r.userid AND cu.companyid = r.companyid
            WHERE cu.id IS NULL AND r.is_deleted = 0";
        
        $params = [];
        if ($companyid > 0) {
            $orphaned_users_sql .= " AND r.companyid = :companyid";
            $params['companyid'] = $companyid;
        }
        
        $orphaned_user_records = $DB->get_records_sql($orphaned_users_sql, $params);
        echo "Found " . count($orphaned_user_records) . " records with users no longer in company\n";
        flush();
        
        foreach ($orphaned_user_records as $record) {
            $DB->set_field('local_alx_api_reporting', 'is_deleted', 1, ['id' => $record->id]);
            $DB->set_field('local_alx_api_reporting', 'last_updated', time(), ['id' => $record->id]);
            $stats['orphaned_users']++;
        }
        
        // Find reporting records for courses no longer available to the company
        $orphaned_courses_sql = "
            SELECT r.id
            FROM {local_alx_api_reporting} r
            LEFT JOIN {company_course} cc ON cc.courseid = r.courseid AND cc.companyid = r.companyid
            WHERE cc.id IS NULL AND r.is_deleted = 0";
        
        if ($companyid > 0) {
            $orphaned_courses_sql .= " AND r.companyid = :companyid";
        }
        
        $orphaned_course_records = $DB->get_records_sql($orphaned_courses_sql, $params);
        echo "Found " . count($orphaned_course_records) . " records with courses no longer available\n";
        flush();
        
        foreach ($orphaned_course_records as $record) {
            $DB->set_field('local_alx_api_reporting', 'is_deleted', 1, ['id' => $record->id]);
            $DB->set_field('local_alx_api_reporting', 'last_updated', time(), ['id' => $record->id]);
            $stats['orphaned_courses']++;
        }
        
        // Find reporting records for users no longer enrolled in courses
        $orphaned_enrollments_sql = "
            SELECT r.id
            FROM {local_alx_api_reporting} r
            LEFT JOIN {user_enrolments} ue ON ue.userid = r.userid
            LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = r.courseid
            WHERE (ue.id IS NULL OR ue.status != 0) AND r.is_deleted = 0";
        
        if ($companyid > 0) {
            $orphaned_enrollments_sql .= " AND r.companyid = :companyid";
        }
        
        $orphaned_enrollment_records = $DB->get_records_sql($orphaned_enrollments_sql, $params);
        echo "Found " . count($orphaned_enrollment_records) . " records with inactive enrollments\n";
        flush();
        
        foreach ($orphaned_enrollment_records as $record) {
            $DB->set_field('local_alx_api_reporting', 'is_deleted', 1, ['id' => $record->id]);
            $DB->set_field('local_alx_api_reporting', 'last_updated', time(), ['id' => $record->id]);
            $stats['orphaned_enrollments']++;
        }
        
        echo "Cleanup completed successfully!\n";
        flush();
        
    } catch (Exception $e) {
        $error_msg = 'Cleanup error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $stats['errors'][] = $error_msg;
        echo "ERROR: " . $error_msg . "\n";
        flush();
    }
    
    return $stats;
}

// Handle CLI execution
if ($is_cli) {
    echo "ALX Report API - Background Data Sync Tool\n";
    echo "=========================================\n\n";
    
    $options = getopt('', ['type:', 'companyid:', 'hours:', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php sync_reporting_data.php [options]\n\n";
        echo "Options:\n";
        echo "  --type=TYPE       Sync type: 'changed', 'full', 'cleanup' (default: changed)\n";
        echo "  --companyid=ID    Company ID to sync (default: all companies)\n";
        echo "  --hours=N         Hours back to check for changes (default: 24)\n";
        echo "  --help           Show this help message\n\n";
        echo "Examples:\n";
        echo "  php sync_reporting_data.php --type=changed --hours=6\n";
        echo "  php sync_reporting_data.php --type=full --companyid=5\n";
        echo "  php sync_reporting_data.php --type=cleanup\n\n";
        exit;
    }
    
    $cli_type = isset($options['type']) ? $options['type'] : 'changed';
    $cli_companyid = isset($options['companyid']) ? (int)$options['companyid'] : 0;
    $cli_hours = isset($options['hours']) ? (int)$options['hours'] : 24;
    
    echo "Running sync with:\n";
    echo "Type: $cli_type\n";
    echo "Company ID: " . ($cli_companyid > 0 ? $cli_companyid : 'All companies') . "\n";
    echo "Hours back: $cli_hours\n\n";
    
    $start_time = time();
    
    switch ($cli_type) {
        case 'changed':
            $result = sync_recent_changes($cli_hours, $cli_companyid);
            echo "Changed records sync completed:\n";
            echo "Users updated: " . $result['users_updated'] . "\n";
            echo "Records updated: " . $result['records_updated'] . "\n";
            break;
            
        case 'full':
            if ($cli_companyid > 0) {
                $result = sync_company_full($cli_companyid);
                echo "Full company sync completed:\n";
                echo "Records processed: " . $result['records_processed'] . "\n";
                echo "Records updated: " . $result['records_updated'] . "\n";
            } else {
                echo "Full sync requires a specific company ID\n";
                exit(1);
            }
            break;
            
        case 'cleanup':
            $result = cleanup_orphaned_records($cli_companyid);
            echo "Cleanup completed:\n";
            echo "Orphaned users: " . $result['orphaned_users'] . "\n";
            echo "Orphaned courses: " . $result['orphaned_courses'] . "\n";
            echo "Orphaned enrollments: " . $result['orphaned_enrollments'] . "\n";
            break;
            
        default:
            echo "Invalid sync type: $cli_type\n";
            exit(1);
    }
    
    $duration = time() - $start_time;
    echo "Duration: $duration seconds\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    exit;
}

// Handle web form submission
if ($action && $confirm) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading('Running Manual Sync...');
    echo '<div class="alert alert-info">Processing sync operation. Please wait...</div>';
    echo '<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto;">';
    
    // Force output
    flush();
    
    $start_time = time();
    $result = [];
    
    try {
        switch ($action) {
            case 'sync_changes':
                echo "=== SYNC RECENT CHANGES ===\n";
                echo "Hours back: $hours_back\n";
                echo "Company ID: " . ($companyid > 0 ? $companyid : 'All companies') . "\n";
                echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                flush();
                
                $result = sync_recent_changes($hours_back, $companyid);
                
                echo "\n=== SUMMARY ===\n";
                echo "Total unique records: " . $result['users_updated'] . "\n";
                echo "Records created: " . $result['records_created'] . "\n";
                echo "Records updated: " . $result['records_updated'] . "\n";
                break;
                
            case 'sync_full':
                if ($companyid > 0) {
                    echo "=== FULL COMPANY SYNC ===\n";
                    echo "Company ID: $companyid\n";
                    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                    flush();
                    
                    $result = sync_company_full($companyid);
                    
                    echo "\n=== SUMMARY ===\n";
                    echo "Users processed: " . $result['records_processed'] . "\n";
                    echo "Records created: " . $result['records_created'] . "\n";
                    echo "Records updated: " . $result['records_updated'] . "\n";
                } else {
                    echo "ERROR: Full sync requires a specific company\n";
                    $result = ['errors' => ['Full sync requires a specific company']];
                }
                break;
                
            case 'cleanup':
                echo "=== CLEANUP ORPHANED RECORDS ===\n";
                echo "Company ID: " . ($companyid > 0 ? $companyid : 'All companies') . "\n";
                echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                flush();
                
                $result = cleanup_orphaned_records($companyid);
                
                echo "\n=== SUMMARY ===\n";
                echo "Orphaned users marked deleted: " . $result['orphaned_users'] . "\n";
                echo "Orphaned courses marked deleted: " . $result['orphaned_courses'] . "\n";
                echo "Orphaned enrollments marked deleted: " . $result['orphaned_enrollments'] . "\n";
                break;
                
            default:
                echo "ERROR: Invalid action\n";
        }
        
        $duration = time() - $start_time;
        echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
        echo "Duration: $duration seconds\n";
        
        if (!empty($result['errors'])) {
            echo "\n=== ERRORS ===\n";
            foreach ($result['errors'] as $error) {
                echo "- $error\n";
            }
        }
        
    } catch (Exception $e) {
        echo "\n=== FATAL ERROR ===\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    echo '</pre>';
    echo '<div class="mt-3">';
    echo '<a href="sync_reporting_data.php" class="btn btn-primary">Back to Sync Tool</a>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Web interface
echo $OUTPUT->header();
echo $OUTPUT->heading('ALX Report API - Manual Data Sync');

// Check if reporting table exists
if (!$DB->get_manager()->table_exists('local_alx_api_reporting')) {
    echo $OUTPUT->notification('Reporting table does not exist. Please upgrade the plugin first.', 'error');
    echo $OUTPUT->footer();
    exit;
}

$companies = local_alx_report_api_get_companies();
$total_records = $DB->count_records('local_alx_api_reporting');

echo '<div class="alert alert-info">';
echo '<h4>About Manual Data Sync</h4>';
echo '<p>This tool helps maintain the reporting table by manually syncing changes from the main database. ';
echo 'Use this for regular maintenance or when you notice data discrepancies.</p>';
echo '</div>';

// Current status
echo '<div class="card mb-4">';
echo '<div class="card-header"><h5>Current Status</h5></div>';
echo '<div class="card-body">';
echo '<p><strong>Total Reporting Records:</strong> ' . number_format($total_records) . '</p>';
$last_update = $DB->get_field_select('local_alx_api_reporting', 'MAX(last_updated)', '1=1');
echo '<p><strong>Last Update:</strong> ' . ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</p>';
echo '</div>';
echo '</div>';

// Sync recent changes
echo '<div class="card mb-3">';
echo '<div class="card-header"><h5>Sync Recent Changes</h5></div>';
echo '<div class="card-body">';
echo '<p>Sync changes from the last few hours (course completions, enrollments, etc.)</p>';
echo '<form method="post" class="sync-form">';
echo '<input type="hidden" name="action" value="sync_changes">';
echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<label>Company:</label>';
echo '<select name="companyid" class="form-control">';
echo '<option value="0">All Companies</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div class="col-md-6">';
echo '<label>Hours Back:</label>';
echo '<input type="number" name="hours_back" class="form-control" value="24" min="1" max="168">';
echo '</div>';
echo '</div>';
echo '<div class="form-check mt-3">';
echo '<input type="checkbox" name="confirm" value="1" class="form-check-input" required>';
echo '<label class="form-check-label">Confirm sync operation</label>';
echo '</div>';
echo '<button type="submit" class="btn btn-primary mt-3">Sync Recent Changes</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Full company sync
echo '<div class="card mb-3">';
echo '<div class="card-header"><h5>Full Company Sync</h5></div>';
echo '<div class="card-body">';
echo '<p>Perform a complete sync for all users in a specific company</p>';
echo '<form method="post" class="sync-form">';
echo '<input type="hidden" name="action" value="sync_full">';
echo '<div class="form-group">';
echo '<label>Company (Required):</label>';
echo '<select name="companyid" class="form-control" required>';
echo '<option value="">Select a company...</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div class="form-check">';
echo '<input type="checkbox" name="confirm" value="1" class="form-check-input" required>';
echo '<label class="form-check-label">Confirm full sync operation</label>';
echo '</div>';
echo '<button type="submit" class="btn btn-warning mt-3">Full Company Sync</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Cleanup orphaned records
echo '<div class="card mb-3">';
echo '<div class="card-header"><h5>Cleanup Orphaned Records</h5></div>';
echo '<div class="card-body">';
echo '<p>Remove records for users/courses that no longer exist or are no longer enrolled</p>';
echo '<form method="post" class="sync-form">';
echo '<input type="hidden" name="action" value="cleanup">';
echo '<div class="form-group">';
echo '<label>Company:</label>';
echo '<select name="companyid" class="form-control">';
echo '<option value="0">All Companies</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div class="form-check">';
echo '<input type="checkbox" name="confirm" value="1" class="form-check-input" required>';
echo '<label class="form-check-label">Confirm cleanup operation</label>';
echo '</div>';
echo '<button type="submit" class="btn btn-danger mt-3">Cleanup Orphaned Records</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer(); 