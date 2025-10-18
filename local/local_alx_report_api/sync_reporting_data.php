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
 * Manual sync script for ALX Report API reporting table.
 * 
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Security check
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up page
$PAGE->set_url('/local/alx_report_api/sync_reporting_data.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Manual Data Sync');
$PAGE->set_heading('ALX Report API - Manual Data Sync');

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$hours_back = optional_param('hours_back', 1, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Generate unique token for this sync request
$sync_token = optional_param('sync_token', '', PARAM_ALPHANUMEXT);

// Process sync action - only on POST and with valid token to prevent refresh from re-running
if ($action && $confirm && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this token was already processed
    if (!empty($sync_token) && isset($_SESSION['processed_sync_tokens'][$sync_token])) {
        // This sync was already processed, redirect to form
        redirect(new moodle_url('/local/alx_report_api/sync_reporting_data.php'));
        exit;
    }
    
    // Mark this token as processed
    if (!isset($_SESSION['processed_sync_tokens'])) {
        $_SESSION['processed_sync_tokens'] = [];
    }
    if (!empty($sync_token)) {
        $_SESSION['processed_sync_tokens'][$sync_token] = time();
        
        // Clean old tokens (older than 1 hour)
        foreach ($_SESSION['processed_sync_tokens'] as $token => $timestamp) {
            if (time() - $timestamp > 3600) {
                unset($_SESSION['processed_sync_tokens'][$token]);
            }
        }
    }
    echo $OUTPUT->header();
    
    // Modern UI styling
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . new moodle_url('/local/alx_report_api/styles/sync-reporting-data.css') . '">';
    
    echo '<div class="sync-container">';
    echo '<div class="sync-header">';
    echo '<h1 style="margin: 0 0 10px 0; font-size: 28px;"><i class="fas fa-sync-alt"></i> Running Manual Sync</h1>';
    echo '<p style="margin: 0; opacity: 0.9;">Processing your data synchronization request...</p>';
    echo '</div>';
    
    echo '<div class="progress-box" id="progress-log">';
    
    // Set start_time to 1 second before to catch records updated during sync
    $start_time = time() - 1;
    $sync_details = [
        'created_records' => 0,
        'updated_records' => 0,
        'affected_courses' => [],
        'affected_users' => [],
        'company_info' => null
    ];
    
    try {
        // DEBUG: Show what action was received
        echo "DEBUG: action = '$action', companyid = $companyid, hours_back = $hours_back, confirm = $confirm\n\n";
        flush();
        
        // Get company info if specific company
        if ($companyid > 0) {
            $sync_details['company_info'] = $DB->get_record('company', ['id' => $companyid], 'id, name, shortname');
        }
        
        switch ($action) {
            case 'sync_changes':
            case 'syncchanges': // Handle both formats (with/without underscore)
                echo "=== SYNC RECENT CHANGES ===\n";
                echo "Hours back: $hours_back\n";
                if ($sync_details['company_info']) {
                    echo "Company: " . $sync_details['company_info']->name . " (ID: $companyid)\n";
                } else {
                    echo "Company: All Companies\n";
                }
                echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                flush();
                
                $cutoff_time = time() - ($hours_back * 3600);
                echo "🔍 Looking for changes since " . date('Y-m-d H:i:s', $cutoff_time) . "...\n";
                flush();
                
                // Track before state
                if ($companyid > 0) {
                    $before_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $companyid]);
                } else {
                    $before_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
                }
                
                // Call INCREMENTAL sync function (only processes recent changes)
                echo "🔄 Calling incremental sync function for recent changes...\n";
                flush();
                $result = local_alx_report_api_sync_recent_changes($companyid, $hours_back);
                echo "📊 Sync function returned: " . print_r($result, true) . "\n";
                flush();
                
                // Track after state and get details
                if ($companyid > 0) {
                    $after_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $companyid]);
                } else {
                    $after_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
                }
                echo "📈 Before count: $before_count, After count: $after_count\n";
                flush();
                
                // Get affected courses
                $course_sql = "SELECT c.id, c.fullname, COUNT(r.id) as record_count
                              FROM {local_alx_api_reporting} r
                              JOIN {course} c ON c.id = r.courseid
                              WHERE r.last_updated >= ?";
                $course_params = [$start_time];
                if ($companyid > 0) {
                    $course_sql .= " AND r.companyid = ?";
                    $course_params[] = $companyid;
                }
                $course_sql .= " GROUP BY c.id, c.fullname ORDER BY record_count DESC LIMIT 10";
                $sync_details['affected_courses'] = $DB->get_records_sql($course_sql, $course_params);
                
                // Get all affected users - use reporting table email (same as API)
                $all_users_sql = "SELECT r.userid as id, 
                                        MAX(r.firstname) as firstname, 
                                        MAX(r.lastname) as lastname, 
                                        MAX(r.email) as email, 
                                        COUNT(r.id) as course_count,
                                        MIN(r.timecreated) as first_created
                                FROM {local_alx_api_reporting} r
                                WHERE r.last_updated >= ?";
                $all_users_params = [$start_time];
                if ($companyid > 0) {
                    $all_users_sql .= " AND r.companyid = ?";
                    $all_users_params[] = $companyid;
                }
                $all_users_sql .= " GROUP BY r.userid ORDER BY course_count DESC";
                $sync_details['affected_users'] = $DB->get_records_sql($all_users_sql, $all_users_params);
                
                $sync_details['created_records'] = max(0, $after_count - $before_count);
                $sync_details['updated_records'] = $result['total_processed'] - $sync_details['created_records'];
                
                echo "\n✅ Sync completed successfully!\n";
                echo "\n=== SUMMARY ===\n";
                echo "Total processed: " . $result['total_processed'] . "\n";
                echo "Records created: " . $sync_details['created_records'] . "\n";
                echo "Records updated: " . $sync_details['updated_records'] . "\n";
                echo "Companies processed: " . $result['companies_processed'] . "\n";
                break;
                
            case 'sync_full':
            case 'syncfull': // Handle both formats (with/without underscore)
                if ($companyid > 0) {
                    echo "=== FULL COMPANY SYNC ===\n";
                    echo "Company: " . $sync_details['company_info']->name . " (ID: $companyid)\n";
                    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                    flush();
                    
                    $before_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $companyid]);
                    $result = local_alx_report_api_populate_reporting_table($companyid, 1000, false);
                    $after_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $companyid]);
                    
                    // Get details (same as above)
                    $course_sql = "SELECT c.id, c.fullname, COUNT(r.id) as record_count
                                  FROM {local_alx_api_reporting} r
                                  JOIN {course} c ON c.id = r.courseid
                                  WHERE r.companyid = ? AND r.last_updated >= ?
                                  GROUP BY c.id, c.fullname ORDER BY record_count DESC LIMIT 10";
                    $sync_details['affected_courses'] = $DB->get_records_sql($course_sql, [$companyid, $start_time]);
                    
                    // Get all affected users - use reporting table email (same as API)
                    $all_users_sql = "SELECT r.userid as id, 
                                            MAX(r.firstname) as firstname, 
                                            MAX(r.lastname) as lastname, 
                                            MAX(r.email) as email, 
                                            COUNT(r.id) as course_count,
                                            MIN(r.timecreated) as first_created
                                    FROM {local_alx_api_reporting} r
                                    WHERE r.companyid = ? AND r.last_updated >= ?
                                    GROUP BY r.userid ORDER BY course_count DESC";
                    $sync_details['affected_users'] = $DB->get_records_sql($all_users_sql, [$companyid, $start_time]);
                    
                    $sync_details['created_records'] = max(0, $after_count - $before_count);
                    $sync_details['updated_records'] = $result['total_processed'] - $sync_details['created_records'];
                    
                    echo "\n✅ Full sync completed successfully!\n";
                    echo "\n=== SUMMARY ===\n";
                    echo "Total processed: " . $result['total_processed'] . "\n";
                    echo "Records created: " . $sync_details['created_records'] . "\n";
                    echo "Records updated: " . $sync_details['updated_records'] . "\n";
                } else {
                    echo "ERROR: Full sync requires a specific company\n";
                    $result = ['errors' => ['Full sync requires a specific company']];
                }
                break;
                
            case 'cleanup':
                echo "=== CLEANUP ORPHANED RECORDS ===\n";
                if ($companyid > 0) {
                    $sync_details['company_info'] = $DB->get_record('company', ['id' => $companyid], 'id, name, shortname');
                    echo "Company: " . $sync_details['company_info']->name . " (ID: $companyid)\n";
                } else {
                    echo "Company: All Companies\n";
                }
                echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
                flush();
                
                // Get detailed information about orphaned records BEFORE deleting
                // IMPORTANT: Get email from user table (u.email), NOT from reporting table (r.email)
                // The reporting table may have hashed emails, but user table has real emails
                $sql = "SELECT r.id, r.userid, r.courseid, r.companyid,
                               COALESCE(u.firstname, r.firstname) as firstname,
                               COALESCE(u.lastname, r.lastname) as lastname,
                               CASE WHEN u.email IS NOT NULL THEN u.email ELSE 'User Deleted' END as email,
                               COALESCE(c.fullname, r.coursename) as coursename,
                               comp.name as companyname
                        FROM {local_alx_api_reporting} r
                        LEFT JOIN {company_users} cu ON cu.userid = r.userid AND cu.companyid = r.companyid
                        LEFT JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                        LEFT JOIN {course} c ON c.id = r.courseid
                        LEFT JOIN {company} comp ON comp.id = r.companyid
                        WHERE cu.id IS NULL AND r.is_deleted = 0";
                $params = [];
                if ($companyid > 0) {
                    $sql .= " AND r.companyid = ?";
                    $params[] = $companyid;
                }
                
                echo "🔍 Searching for orphaned records...\n";
                flush();
                
                $orphaned = $DB->get_records_sql($sql, $params);
                $deleted_count = count($orphaned);
                
                if ($deleted_count > 0) {
                    echo "Found $deleted_count orphaned record(s)\n";
                    echo "🗑️ Permanently deleting records...\n";
                    flush();
                    
                    // Store details for display
                    $sync_details['deleted_users'] = [];
                    $sync_details['deleted_by_company'] = [];
                    $sync_details['deleted_courses'] = [];
                    
                    foreach ($orphaned as $record) {
                        // Hard delete - physically remove the record
                        $DB->delete_records(\local_alx_report_api\constants::TABLE_REPORTING, ['id' => $record->id]);
                        
                        // Track user details
                        $user_key = $record->userid;
                        if (!isset($sync_details['deleted_users'][$user_key])) {
                            $sync_details['deleted_users'][$user_key] = (object)[
                                'userid' => $record->userid,
                                'firstname' => $record->firstname ?: 'Unknown',
                                'lastname' => $record->lastname ?: 'User',
                                'email' => $record->email ?: 'N/A',
                                'record_count' => 0
                            ];
                        }
                        $sync_details['deleted_users'][$user_key]->record_count++;
                        
                        // Track by company
                        if (!isset($sync_details['deleted_by_company'][$record->companyid])) {
                            $sync_details['deleted_by_company'][$record->companyid] = (object)[
                                'companyid' => $record->companyid,
                                'companyname' => $record->companyname ?: 'Unknown Company',
                                'count' => 0
                            ];
                        }
                        $sync_details['deleted_by_company'][$record->companyid]->count++;
                        
                        // Track courses
                        $course_key = $record->courseid;
                        if (!isset($sync_details['deleted_courses'][$course_key])) {
                            $sync_details['deleted_courses'][$course_key] = (object)[
                                'courseid' => $record->courseid,
                                'fullname' => $record->coursename ?: 'Unknown Course',
                                'record_count' => 0
                            ];
                        }
                        $sync_details['deleted_courses'][$course_key]->record_count++;
                    }
                    
                    echo "✅ Successfully marked $deleted_count record(s) as deleted\n";
                } else {
                    echo "✅ No orphaned records found - database is clean!\n";
                }
                
                echo "\n=== SUMMARY ===\n";
                echo "Orphaned records marked deleted: $deleted_count\n";
                if (!empty($sync_details['deleted_by_company'])) {
                    echo "\nBy Company:\n";
                    foreach ($sync_details['deleted_by_company'] as $comp) {
                        echo "  - {$comp->companyname}: {$comp->count} record(s)\n";
                    }
                }
                
                $result = [
                    'success' => true,
                    'deleted' => $deleted_count,
                    'total_processed' => $deleted_count
                ];
                break;
                
            default:
                echo "❌ ERROR: Unknown action '$action'\n";
                echo "Valid actions are: sync_changes, sync_full, cleanup\n";
                $result = ['total_processed' => 0, 'total_inserted' => 0, 'errors' => ["Unknown action: $action"]];
                break;
        }
        
        $duration = time() - $start_time;
        echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
        echo "Duration: $duration seconds\n";
        
    } catch (Exception $e) {
        echo "\n❌ FATAL ERROR\n";
        echo "Message: " . $e->getMessage() . "\n";
    }
    
    echo '</div>'; // Close progress-box
    
    // Display detailed results
    if ($action === 'cleanup') {
        // Cleanup-specific results display
        echo '<div class="results-grid">';
        
        // Company Information Card
        if ($sync_details['company_info']) {
            echo '<div class="result-card">';
            echo '<h3><i class="fas fa-building"></i> Company Information</h3>';
            echo '<div class="stat-row"><span class="stat-label">Company Name:</span><span class="stat-value">' . htmlspecialchars($sync_details['company_info']->name) . '</span></div>';
            echo '<div class="stat-row"><span class="stat-label">Company ID:</span><span class="stat-value">' . $companyid . '</span></div>';
            echo '</div>';
        }
        
        // Cleanup Statistics Card
        echo '<div class="result-card">';
        echo '<h3><i class="fas fa-trash-alt"></i> Cleanup Statistics</h3>';
        echo '<div class="stat-row"><span class="stat-label">Orphaned Records Found:</span><span class="stat-value" style="color: #ef4444;">' . $result['deleted'] . '</span></div>';
        echo '<div class="stat-row"><span class="stat-label">Records Marked Deleted:</span><span class="stat-value" style="color: #10b981;">' . $result['deleted'] . '</span></div>';
        echo '<div class="stat-row"><span class="stat-label">Duration:</span><span class="stat-value">' . $duration . ' seconds</span></div>';
        echo '</div>';
        
        echo '</div>'; // Close results-grid
        
        // Deleted Records by Company
        if (!empty($sync_details['deleted_by_company'])) {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-building"></i> Deleted Records by Company</h2>';
            echo '<div class="data-table">';
            echo '<table>';
            echo '<thead><tr><th>Company Name</th><th>Records Deleted</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            foreach ($sync_details['deleted_by_company'] as $comp) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($comp->companyname) . '</td>';
                echo '<td>' . $comp->count . '</td>';
                echo '<td><span class="badge badge-danger">✓ Cleaned</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        
        // Affected Courses
        if (!empty($sync_details['deleted_courses'])) {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-book"></i> Affected Courses</h2>';
            echo '<div class="data-table">';
            echo '<table>';
            echo '<thead><tr><th>Course Name</th><th>Records Deleted</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            foreach ($sync_details['deleted_courses'] as $course) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($course->fullname) . '</td>';
                echo '<td>' . $course->record_count . '</td>';
                echo '<td><span class="badge badge-danger">✓ Cleaned</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        
        // Affected Users
        if (!empty($sync_details['deleted_users'])) {
            $deleted_users_array = array_values($sync_details['deleted_users']);
            $total_users = count($deleted_users_array);
            
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-users"></i> Affected Users (' . $total_users . ')</h2>';
            echo '<div class="data-table">';
            echo '<table>';
            echo '<thead><tr><th>User Name</th><th>Email</th><th>Records Deleted</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($deleted_users_array as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user->firstname . ' ' . $user->lastname) . '</td>';
                echo '<td>' . htmlspecialchars($user->email) . '</td>';
                echo '<td>' . $user->record_count . '</td>';
                echo '<td><span class="badge badge-danger">✓ Removed</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        }
        
        // No orphaned records message
        if ($result['deleted'] == 0) {
            echo '<div style="background: white; padding: 40px; text-align: center; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 30px;">';
            echo '<div style="font-size: 48px; margin-bottom: 20px;">✨</div>';
            echo '<h3 style="color: #10b981; margin-bottom: 10px;">Database is Clean!</h3>';
            echo '<p style="color: #64748b; margin: 0;">No orphaned records were found. Your reporting table is in good shape.</p>';
            echo '</div>';
        }
        
    } else if ($action !== 'cleanup') {
        echo '<div class="results-grid">';
        
        // Company Information Card
        if ($sync_details['company_info']) {
            $total_users = $DB->count_records('company_users', ['companyid' => $companyid]);
            $total_courses = $DB->count_records('company_course', ['companyid' => $companyid]);
            
            echo '<div class="result-card">';
            echo '<h3><i class="fas fa-building"></i> Company Information</h3>';
            echo '<div class="stat-row"><span class="stat-label">Company Name:</span><span class="stat-value">' . htmlspecialchars($sync_details['company_info']->name) . '</span></div>';
            echo '<div class="stat-row"><span class="stat-label">Company ID:</span><span class="stat-value">' . $companyid . '</span></div>';
            echo '<div class="stat-row"><span class="stat-label">Total Users:</span><span class="stat-value">' . $total_users . '</span></div>';
            echo '<div class="stat-row"><span class="stat-label">Active Courses:</span><span class="stat-value">' . $total_courses . '</span></div>';
            echo '</div>';
        }
        
        // Sync Statistics Card
        echo '<div class="result-card">';
        echo '<h3><i class="fas fa-chart-bar"></i> Sync Statistics</h3>';
        // DEBUG: Show what $result contains
        echo '<!-- DEBUG result: ' . htmlspecialchars(print_r($result, true)) . ' -->';
        echo '<!-- DEBUG sync_details: ' . htmlspecialchars(print_r($sync_details, true)) . ' -->';
        $total_processed_value = isset($result['total_processed']) ? $result['total_processed'] : 0;
        echo '<div class="stat-row"><span class="stat-label">Records Processed:</span><span class="stat-value">' . $total_processed_value . '</span></div>';
        echo '<div class="stat-row"><span class="stat-label">Records Created:</span><span class="stat-value" style="color: #10b981;">' . $sync_details['created_records'] . '</span></div>';
        echo '<div class="stat-row"><span class="stat-label">Records Updated:</span><span class="stat-value" style="color: #3b82f6;">' . $sync_details['updated_records'] . '</span></div>';
        echo '<div class="stat-row"><span class="stat-label">Duration:</span><span class="stat-value">' . $duration . ' seconds</span></div>';
        echo '</div>';
        
        echo '</div>'; // Close results-grid
        
        // Affected Courses Table
        if (!empty($sync_details['affected_courses'])) {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-book"></i> Affected Courses</h2>';
            echo '<div class="data-table">';
            echo '<table>';
            echo '<thead><tr><th>Course Name</th><th>Records Synced</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            foreach ($sync_details['affected_courses'] as $course) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($course->fullname) . '</td>';
                echo '<td>' . $course->record_count . '</td>';
                echo '<td><span class="badge badge-success">✓ Synced</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-book"></i> Affected Courses</h2>';
            echo '<div style="background: white; padding: 40px; text-align: center; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            echo '<p style="color: #64748b; margin: 0;">No courses were affected in this sync operation.</p>';
            echo '</div>';
        }
        
        // Affected Users Table with Pagination
        if (!empty($sync_details['affected_users'])) {
            $affected_users_array = array_values($sync_details['affected_users']);
            $total_users = count($affected_users_array);
            $page_size = 20;
            $total_pages = ceil($total_users / $page_size);
            
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-users"></i> Affected Users (' . $total_users . ')</h2>';
            echo '<div class="data-table">';
            echo '<table id="affected-users-table">';
            echo '<thead><tr><th>User Name</th><th>Email</th><th>Courses Synced</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            
            // Show first page
            for ($i = 0; $i < min($page_size, $total_users); $i++) {
                $user = $affected_users_array[$i];
                // Determine if user was newly created (first_created >= start_time)
                $is_new = ($user->first_created >= $start_time);
                $badge_class = $is_new ? 'badge-success' : 'badge-primary';
                $badge_text = $is_new ? '✓ New' : '✓ Updated';
                
                echo '<tr class="user-row" data-page="1">';
                echo '<td>' . htmlspecialchars($user->firstname . ' ' . $user->lastname) . '</td>';
                echo '<td>' . htmlspecialchars($user->email) . '</td>';
                echo '<td>' . $user->course_count . '</td>';
                echo '<td><span class="badge ' . $badge_class . '">' . $badge_text . '</span></td>';
                echo '</tr>';
            }
            
            // Hide remaining rows
            for ($i = $page_size; $i < $total_users; $i++) {
                $user = $affected_users_array[$i];
                $page = floor($i / $page_size) + 1;
                $is_new = ($user->first_created >= $start_time);
                $badge_class = $is_new ? 'badge-success' : 'badge-primary';
                $badge_text = $is_new ? '✓ New' : '✓ Updated';
                
                echo '<tr class="user-row" data-page="' . $page . '" style="display:none;">';
                echo '<td>' . htmlspecialchars($user->firstname . ' ' . $user->lastname) . '</td>';
                echo '<td>' . htmlspecialchars($user->email) . '</td>';
                echo '<td>' . $user->course_count . '</td>';
                echo '<td><span class="badge ' . $badge_class . '">' . $badge_text . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            // Pagination controls
            if ($total_pages > 1) {
                echo '<div style="margin-top: 20px; text-align: center;">';
                echo '<div id="user-pagination" style="display: inline-flex; gap: 5px; align-items: center;">';
                echo '<button onclick="changeUserPage(1)" style="padding: 8px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">First</button>';
                echo '<button onclick="changeUserPage(\'prev\')" style="padding: 8px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">Previous</button>';
                echo '<span id="user-page-info" style="padding: 8px 16px;">Page 1 of ' . $total_pages . '</span>';
                echo '<button onclick="changeUserPage(\'next\')" style="padding: 8px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">Next</button>';
                echo '<button onclick="changeUserPage(' . $total_pages . ')" style="padding: 8px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">Last</button>';
                echo '</div></div>';
            }
            
            echo '</div>';
            
            // Add pagination JavaScript
            echo '<script>
            var userCurrentPage = 1;
            var userTotalPages = ' . $total_pages . ';
            
            function changeUserPage(page) {
                if (page === "prev") {
                    if (userCurrentPage > 1) userCurrentPage--;
                } else if (page === "next") {
                    if (userCurrentPage < userTotalPages) userCurrentPage++;
                } else {
                    userCurrentPage = page;
                }
                
                // Hide all rows
                document.querySelectorAll(".user-row").forEach(row => row.style.display = "none");
                // Show current page rows
                document.querySelectorAll(".user-row[data-page=\"" + userCurrentPage + "\"]").forEach(row => row.style.display = "");
                // Update page info
                document.getElementById("user-page-info").textContent = "Page " + userCurrentPage + " of " + userTotalPages;
            }
            </script>';
        } else {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-users"></i> Affected Users</h2>';
            echo '<div style="background: white; padding: 40px; text-align: center; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            echo '<p style="color: #64748b; margin: 0;">No users were affected in this sync operation.</p>';
            echo '</div>';
        }
    }
    
    echo '<div style="text-align: center; margin-top: 30px;">';
    echo '<a href="sync_reporting_data.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 8px; text-decoration: none;">← Back to Sync Tool</a>';
    echo '</div>';
    
    echo '</div>'; // Close sync-container
    echo $OUTPUT->footer();
    exit;
}

// Web interface - show form
echo $OUTPUT->header();

// Modern UI styling
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

echo '<style>
* { font-family: "Inter", sans-serif; }
.sync-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}
.info-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    overflow: hidden;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(to right, #f8f9fa, #ffffff);
}
.card-title {
    margin: 0 0 8px 0;
    color: #2d3748;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-subtitle {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    font-weight: 400;
}
.card-body {
    padding: 24px;
}
.btn-sync {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 15px;
}
.btn-sync:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
}
</style>';

// Check if reporting table exists
if (!$DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
    echo '<div class="sync-container">';
    echo '<div class="alert alert-danger">Reporting table does not exist. Please upgrade the plugin first.</div>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

$companies = local_alx_report_api_get_companies();
$total_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
$last_update = $DB->get_field_select(\local_alx_report_api\constants::TABLE_REPORTING, 'MAX(last_updated)', '1=1');

echo '<div class="sync-container">';

echo '<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">';
echo '<div>';
echo '<h1 style="margin: 0 0 10px 0; font-size: 32px;"><i class="fas fa-sync-alt"></i> Manual Data Sync</h1>';
echo '<p style="margin: 0; opacity: 0.9; font-size: 16px;">Maintain your reporting table by manually syncing changes from the main database</p>';
echo '</div>';
echo '<a href="control_center.php" style="background: rgba(255,255,255,0.2); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;" onmouseover="this.style.background=\'rgba(255,255,255,0.3)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.2)\'">';
echo '<i class="fas fa-arrow-left"></i> Back to Control Center';
echo '</a>';
echo '</div>';

echo '<div class="info-box">';
echo '<h4 style="margin: 0 0 10px 0; color: #1e40af;"><i class="fas fa-info-circle"></i> About Manual Data Sync</h4>';
echo '<p style="margin: 0; color: #1e3a8a;">This tool helps maintain the reporting table by manually syncing changes from the main database. Use this for regular maintenance or when you notice data discrepancies.</p>';
echo '</div>';

// Dashboard Card - Current Status
echo '<div class="dashboard-card" style="margin-bottom: 20px;">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-database"></i> Current Status</h3>';
echo '<p class="card-subtitle">Overview of your reporting table data</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">';
echo '<div><strong style="color: #64748b;">Total Records:</strong><br><span style="font-size: 24px; color: #2d3748; font-weight: 600;">' . number_format($total_records) . '</span></div>';
echo '<div><strong style="color: #64748b;">Last Update:</strong><br><span style="font-size: 18px; color: #2d3748;">' . ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</span></div>';
echo '<div><strong style="color: #64748b;">Companies:</strong><br><span style="font-size: 24px; color: #2d3748; font-weight: 600;">' . count($companies) . '</span></div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Dashboard Card - Sync Recent Changes
echo '<div class="dashboard-card" style="margin-bottom: 20px;">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-clock"></i> Sync Recent Changes</h3>';
echo '<p class="card-subtitle">Sync changes from the last few hours (course completions, enrollments, etc.)</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="sync_changes">';
echo '<input type="hidden" name="sync_token" value="' . md5(uniqid(rand(), true)) . '">';
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">';
echo '<div>';
echo '<label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Company:</label>';
echo '<select name="companyid" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">';
echo '<option value="0">All Companies</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div>';
echo '<label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Hours Back:</label>';
echo '<input type="number" name="hours_back" value="1" min="1" max="168" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px;">';
echo '</div>';
echo '</div>';
echo '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">';
echo '<input type="checkbox" name="confirm" value="1" required id="confirm1" style="width: 18px; height: 18px;">';
echo '<label for="confirm1" style="margin: 0; color: #495057; font-weight: 500;">I confirm this sync operation</label>';
echo '</div>';
echo '</div>';
echo '<button type="submit" class="btn-sync"><i class="fas fa-sync-alt"></i> Sync Recent Changes</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Dashboard Card - Full Company Sync
echo '<div class="dashboard-card" style="margin-bottom: 20px;">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-building"></i> Full Company Sync</h3>';
echo '<p class="card-subtitle">Perform a complete sync for all users in a specific company</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="sync_full">';
echo '<input type="hidden" name="sync_token" value="' . md5(uniqid(rand(), true)) . '">';
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Company (Required):</label>';
echo '<select name="companyid" required style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">';
echo '<option value="">Select a company...</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">';
echo '<input type="checkbox" name="confirm" value="1" required id="confirm2" style="width: 18px; height: 18px;">';
echo '<label for="confirm2" style="margin: 0; color: #495057; font-weight: 500;">I confirm this full sync operation</label>';
echo '</div>';
echo '</div>';
echo '<button type="submit" class="btn-sync" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);"><i class="fas fa-database"></i> Full Company Sync</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Dashboard Card - Cleanup Orphaned Records
echo '<div class="dashboard-card" style="margin-bottom: 20px;">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-trash-alt"></i> Cleanup Orphaned Records</h3>';
echo '<p class="card-subtitle">Remove records for users/courses that no longer exist or are no longer enrolled</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="cleanup">';
echo '<input type="hidden" name="sync_token" value="' . md5(uniqid(rand(), true)) . '">';
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Company:</label>';
echo '<select name="companyid" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">';
echo '<option value="0">All Companies</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">';
echo '<input type="checkbox" name="confirm" value="1" required id="confirm3" style="width: 18px; height: 18px;">';
echo '<label for="confirm3" style="margin: 0; color: #495057; font-weight: 500;">I confirm this cleanup operation</label>';
echo '</div>';
echo '</div>';
echo '<button type="submit" class="btn-sync" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);"><i class="fas fa-broom"></i> Cleanup Orphaned Records</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '</div>'; // Close sync-container

echo $OUTPUT->footer();
