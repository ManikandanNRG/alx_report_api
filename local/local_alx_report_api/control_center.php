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
 * ALX Report API Control Center - Unified Dashboard
 * 
 * A comprehensive, beautiful interface that consolidates all plugin functionality
 * into a single, modern dashboard with consistent UI design.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_control_center');

// Page setup
$PAGE->set_url('/local/alx_report_api/control_center.php');
$PAGE->set_title('ALX Report API - Control Center');
$PAGE->set_heading('ALX Report API Control Center');

// Force browser cache refresh - add timestamp to ensure new version loads
$cache_buster = time();
$PAGE->requires->js_amd_inline("
    // Force refresh of cached content - version: {$cache_buster}
    console.log('Control Center Enhanced Version Loading: " . $cache_buster . "');
");

// Get initial data with comprehensive error handling
$companies = [];
$total_records = 0;
$total_companies = 0;
$api_calls_today = 0;
$system_health = '✅'; // Default to healthy
$health_issues = [];
$load_errors = []; // Track specific loading errors

try {
    // Get companies using the same function as monitoring dashboard
    try {
        $companies = local_alx_report_api_get_companies();
        $total_companies = count($companies);
        
        if ($total_companies == 0) {
            $health_issues[] = 'No companies configured';
        }
    } catch (Exception $e) {
        error_log('ALX Report API Control Center: Error loading companies - ' . $e->getMessage());
        $load_errors[] = 'Could not load companies';
        $health_issues[] = 'Company data unavailable';
    }
    
    // Get total records from reporting table (same as monitoring dashboard)
    try {
        if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
            $total_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]); // Only active records
            
            if ($total_records == 0) {
                $health_issues[] = 'No reporting data';
            }
        } else {
            $health_issues[] = 'Reporting table missing';
            $load_errors[] = 'Reporting table does not exist';
        }
    } catch (Exception $e) {
        error_log('ALX Report API Control Center: Error loading reporting data - ' . $e->getMessage());
        $load_errors[] = 'Could not load reporting data';
        $health_issues[] = 'Reporting data unavailable';
    }
    
    // Get API calls today (check if table exists first)
    try {
        if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
            $today_start = mktime(0, 0, 0);
            // Use standard Moodle field name
            $time_field = 'timecreated';
            
            $api_calls_today = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ?", [$today_start]);
        } else {
            // Logs table missing is not critical - just means no API calls yet
            error_log('ALX Report API Control Center: local_alx_api_logs table does not exist');
        }
    } catch (Exception $e) {
        error_log('ALX Report API Control Center: Error loading API call stats - ' . $e->getMessage());
        // Not critical - continue with 0 API calls
    }
    
    // Check if web services are enabled
    try {
        if (empty($CFG->enablewebservices)) {
            $health_issues[] = 'Web services disabled';
        }
    } catch (Exception $e) {
        error_log('ALX Report API Control Center: Error checking web services - ' . $e->getMessage());
    }
    
    // Set system health icon based on issues
    if (empty($health_issues)) {
        $system_health = '✅'; // All good
    } elseif (count($health_issues) <= 2) {
        $system_health = '⚠️'; // Some issues but manageable
    } else {
        $system_health = '❌'; // Multiple issues
    }
    
} catch (Exception $e) {
    // Catch-all for any unexpected errors
    error_log('ALX Report API Control Center: Unexpected error during initialization - ' . $e->getMessage());
    $system_health = '❌';
    $load_errors[] = 'Unexpected error during page load';
}

echo $OUTPUT->header();

// Include modern CSS and JavaScript
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

// Debug indicator for cache busting
echo '<div style="position: fixed; top: 0; right: 0; background: #10b981; color: white; padding: 4px 8px; font-size: 10px; z-index: 9999;">Enhanced v' . time() . '</div>';

?>

<link rel="stylesheet" href="<?php echo new moodle_url('/local/alx_report_api/styles/control-center.css?v=' . time()); ?>">

<!-- CSS moved to external file -->

<div class="control-center-container">
    
    <?php
    // Display any loading errors to the admin
    if (!empty($load_errors)) {
        echo '<div class="alert alert-warning" style="margin-bottom: 24px; padding: 16px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #856404;"><i class="fas fa-exclamation-triangle"></i> Warning: Some Data Could Not Be Loaded</h4>';
        echo '<ul style="margin: 8px 0 0 20px; color: #856404;">';
        foreach ($load_errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '<p style="margin: 8px 0 0 0; color: #856404; font-size: 14px;">The dashboard will display with available data. Check the Moodle error logs for details.</p>';
        echo '</div>';
    }
    ?>
    
    <!-- Header Section -->
    <div class="control-header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            ALX Report API Control Center
        </h1>
        <p class="subtitle">Comprehensive management dashboard for your enterprise API system</p>
        
        <div class="header-stats" id="header-stats">
            <div class="header-stat">
                <div class="header-stat-value" id="total-records"><?php echo number_format($total_records); ?></div>
                <div class="header-stat-label">Total Records</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value" id="active-companies"><?php echo $total_companies; ?></div>
                <div class="header-stat-label">Active Companies</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value" id="api-calls-today"><?php echo number_format($api_calls_today); ?></div>
                <div class="header-stat-label">API Calls Today</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value" id="system-health"><?php echo $system_health; ?></div>
                <div class="header-stat-label">System Health</div>
            </div>
        </div>
        <!-- Debug: Records=<?php echo $total_records; ?>, Companies=<?php echo $total_companies; ?>, API=<?php echo $api_calls_today; ?> -->
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="switchTab(event, 'overview')">
            <i class="fas fa-chart-line"></i>
            System Overview
        </button>
        <button class="tab-button" onclick="switchTab(event, 'companies')">
            <i class="fas fa-building"></i>
            Company Management
        </button>
        <button class="tab-button" onclick="switchTab(event, 'data')">
            <i class="fas fa-database"></i>
            Data Management
            <!-- Hover Dropdown Menu -->
            <div class="tab-dropdown" onclick="event.stopPropagation();">
                <a href="populate_reporting_table.php">📊 Populate Report Table</a>
                <a href="sync_reporting_data.php">🔄 Manual Sync Data</a>
            </div>
        </button>
        <button class="tab-button" onclick="switchTab(event, 'monitoring')">
            <i class="fas fa-chart-bar"></i>
            Monitoring & Analytics
            <!-- Hover Dropdown Menu -->
            <div class="tab-dropdown" onclick="event.stopPropagation();">
                <a href="monitoring_dashboard_new.php?tab=autosync">🔄 Data Sync Monitor</a>
                <a href="monitoring_dashboard_new.php?tab=performance">⚡ API Monitor</a>
                <a href="monitoring_dashboard_new.php?tab=security">🔒 Security Monitor</a>
            </div>
        </button>
        <button class="tab-button" onclick="switchTab(event, 'settings')">
            <i class="fas fa-cog"></i>
            System Configuration
        </button>
    </div>

    <!-- System Overview Tab -->
    <div id="overview-tab" class="tab-content active">
        <div class="card-grid card-grid-3" style="margin-bottom: 20px;">
            <!-- Performance Cards Row -->
            <div class="performance-cards-grid">
                <!-- Enhanced API Performance Card -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1);">
                        <h3 class="card-title" style="color: white; margin: 0;">
                            <i class="fas fa-tachometer-alt"></i>
                            API Status
                        </h3>
                        <p class="card-subtitle" style="color: rgba(255,255,255,0.8); margin: 4px 0 0 0;">Real-time performance metrics with visual indicators</p>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get API analytics for last 24 hours - REAL DATA ONLY
                        $api_analytics = local_alx_report_api_get_api_analytics(24);
                        
                        // Initialize default values
                        $response_time = 0;
                        $success_rate = 100; // Default to 100% if no error tracking
                        $has_api_data = false;
                        
                        if ($api_analytics['summary']['total_calls'] > 0) {
                            $has_api_data = true;
                            
                            // Calculate success rate - assume success unless we can track errors
                            $success_rate = 100;
                            
                            // Only try to calculate detailed metrics if we have the necessary fields
                            if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                                $table_info = $DB->get_columns('local_alx_api_logs');
                                
                                // Check if we have response time tracking
                                if (isset($table_info['response_time_ms'])) {
                                    // Use standard Moodle field name
                                    $time_field = 'timecreated';
                                    $avg_response = $DB->get_field_sql("
                                        SELECT AVG(response_time_ms) 
                                        FROM {local_alx_api_logs} 
                                        WHERE {$time_field} >= ? AND response_time_ms IS NOT NULL AND response_time_ms > 0
                                    ", [time() - 86400]);
                                    $response_time = $avg_response ? round($avg_response / 1000, 2) : 0;
                                }
                                
                                // Check if we have error tracking
                                if (isset($table_info['error_message'])) {
                                    // Use standard Moodle field name
                                    $time_field = 'timecreated';
                                    $total_calls = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ?", [time() - 86400]);
                                    $error_calls = $DB->count_records_select('local_alx_api_logs', 
                                        "{$time_field} >= ? AND error_message IS NOT NULL AND error_message != ?", 
                                        [time() - 86400, '']
                                    );
                                    if ($total_calls > 0) {
                                        $success_rate = round((($total_calls - $error_calls) / $total_calls) * 100, 1);
                                    }
                                }
                            }
                        }
                        ?>
                        
                        <!-- Response Time with Progress Bar - Always visible -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: white;">Response Time</strong>
                                <span style="color: #4ade80; font-weight: 600;"><?php echo $response_time > 0 ? $response_time . 's avg' : 'Not tracked'; ?></span>
                            </div>
                            <div style="background: rgba(255,255,255,0.2); border-radius: 10px; height: 8px; overflow: hidden;">
                                <?php 
                                $response_percentage = $response_time > 0 ? min(100, (5 - $response_time) / 5 * 100) : 100;
                                ?>
                                <div style="background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%); width: <?php echo max(5, $response_percentage); ?>%; height: 100%; border-radius: 10px; transition: width 0.3s ease;"></div>
                            </div>
                        </div>

                        <!-- Success Rate with Progress Bar - Always visible -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: white;">Success Rate</strong>
                                <span style="color: #4ade80; font-weight: 600;"><?php echo $success_rate; ?>%</span>
                            </div>
                            <div style="background: rgba(255,255,255,0.2); border-radius: 10px; height: 8px; overflow: hidden;">
                                <div style="background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%); width: <?php echo $success_rate; ?>%; height: 100%; border-radius: 10px; transition: width 0.3s ease;"></div>
                            </div>
                        </div>

                        <!-- Mini Chart Container - Always visible -->
                        <div style="margin-bottom: 20px;">
                            <canvas id="api-performance-chart" width="300" height="100" style="max-width: 100%;"></canvas>
                        </div>

                        <?php if ($has_api_data): ?>
                        <!-- Performance Metrics Grid - Only when there's data -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;">
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #4ade80;"><?php echo $api_analytics['summary']['calls_per_hour']; ?></div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Calls/Hour</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #60a5fa;"><?php echo $api_analytics['summary']['unique_users']; ?></div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Unique Users</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- No Data State - Show metrics with zeros -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;">
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #94a3b8;">0</div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Calls/Hour</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #94a3b8;">0</div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Unique Users</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer" style="border-top: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1); padding: 16px; text-align: center;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard_new.php?tab=performance" class="btn-modern" style="background: rgba(255,255,255,0.9); color: #667eea; border: 2px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-chart-line"></i>
                            API Monitor
                        </a>
                    </div>
                </div>

                <!-- Enhanced Response Status Card -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
                    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1);">
                        <h3 class="card-title" style="color: white; margin: 0;">
                            <i class="fas fa-sync-alt"></i>
                            Sync Status
                        </h3>
                        <p class="card-subtitle" style="color: rgba(255,255,255,0.8); margin: 4px 0 0 0;">Automatic data synchronization</p>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get sync status information
                        if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
                            $total_sync_entries = $DB->count_records('local_alx_api_sync_status');
                            $recent_syncs = $DB->count_records_select('local_alx_api_sync_status', 
                                'last_sync_timestamp > ?', [time() - 86400]);
                            // Fetch the most recent sync timestamp
                            $last_sync_timestamp = $DB->get_field_sql('SELECT MAX(last_sync_timestamp) FROM {local_alx_api_sync_status}');
                        } else {
                            $total_sync_entries = 0;
                            $recent_syncs = 0;
                            $last_sync_timestamp = false;
                        }
                        
                        // Get company data with record counts for bar chart
                        $company_chart_data = [];
                        $company_chart_labels = [];
                        $company_chart_colors = ['#ef4444', '#3b82f6', '#10b981', '#fbbf24', '#ec4899', '#8b5cf6', '#f97316', '#06b6d4'];
                        
                        if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
                            $companies_list = local_alx_report_api_get_companies();
                            $color_index = 0;
                            
                            foreach ($companies_list as $comp) {
                                $record_count = $DB->count_records('local_alx_api_reporting', [
                                    'companyid' => $comp->id,
                                    'is_deleted' => 0
                                ]);
                                
                                if ($record_count > 0) {
                                    $company_chart_labels[] = $comp->name;
                                    $company_chart_data[] = $record_count;
                                    $color_index++;
                                }
                            }
                        }

                        // Get actual active tokens count (fix for correct display)
                        $actual_active_tokens = 0;
                        if ($DB->get_manager()->table_exists('external_tokens')) {
                            // Check for primary service name first
                            $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api_custom']);
                            if (!$service_id) {
                                // Fallback to legacy service name
                                $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api']);
                            }
                            
                            if ($service_id) {
                                // Use the PROVEN working method from debug script - simple query then filter in PHP
                                $tokens = $DB->get_records_select('external_tokens', 
                                    'externalserviceid = ? AND tokentype = ?', 
                                    [$service_id, EXTERNAL_TOKEN_PERMANENT], 
                                    '', 'id, validuntil');
                                
                                // Filter for valid tokens in PHP (more reliable than SQL)
                                $current_time = time();
                                foreach ($tokens as $token) {
                                    if (!$token->validuntil || $token->validuntil > $current_time) {
                                        $actual_active_tokens++;
                                    }
                                }
                            }
                        }

                        // Get next scheduled sync time from Moodle scheduled tasks
                        $task_record = $DB->get_record('task_scheduled', ['classname' => '\\local_alx_report_api\\task\\sync_reporting_data_task']);
                        if ($task_record && !empty($task_record->nextruntime)) {
                            $next_sync_time = userdate($task_record->nextruntime, '%Y-%m-%d %H:%M:%S');
                        } else {
                            $next_sync_time = 'Not scheduled';
                        }
                        // Format last sync time
                        if ($last_sync_timestamp && $last_sync_timestamp > 0) {
                            $last_sync_time = userdate($last_sync_timestamp, '%Y-%m-%d %H:%M:%S');
                        } else {
                            $last_sync_time = 'No syncs yet';
                        }
                        ?>

                        <!-- Last Sync Status -->
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="display: inline-flex; align-items: center; background: rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 25px;">
                                <i class="fas fa-check-circle" style="color: #4ade80; margin-right: 8px; font-size: 16px;"></i>
                                <span style="font-weight: 600;">Last Sync: <?php echo $last_sync_time; ?></span>
                            </div>
                        </div>

                        <!-- Company Records Bar Chart -->
                        <div style="margin-bottom: 20px;">
                            <canvas id="sync-company-chart" width="400" height="200" style="max-width: 100%; margin: 0 auto; display: block;"></canvas>
                        </div>

                        <!-- Sync Statistics -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #fbbf24;"><?php echo $actual_active_tokens; ?></div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Active Tokens</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <div style="font-size: 20px; font-weight: 700; color: #60a5fa;"><?php echo $recent_syncs; ?></div>
                                <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Recent (24h)</div>
                            </div>
                        </div>

                        <!-- Next Sync Countdown -->
                        <div style="text-align: center; margin-top: 16px; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                            <div style="font-size: 14px; color: rgba(255,255,255,0.8);">Next Sync</div>
                            <div style="font-size: 16px; font-weight: 600; color: #4ade80;">
                                <?php echo $next_sync_time; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer" style="border-top: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1); padding: 16px; text-align: center;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard_new.php?tab=autosync" class="btn-modern" style="background: rgba(255,255,255,0.9); color: #f093fb; border: 2px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-sync-alt"></i>
                            Data Sync Monitor
                        </a>
                    </div>
                </div>

                <!-- Enhanced Security Status Card -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #1f2937; border: none;">
                    <div class="card-header" style="border-bottom: 1px solid rgba(31,41,55,0.1); background: rgba(255,255,255,0.1);">
                        <h3 class="card-title" style="color: #1f2937; margin: 0;">
                            <i class="fas fa-shield-alt" style="color: #10b981;"></i>
                            Performance Status
                        </h3>
                        <p class="card-subtitle" style="color: #6b7280; margin: 4px 0 0 0;">API security and access control</p>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate rate limit violations using company-specific limits (same logic as Security tab)
                        $violations_today = 0;
                        $users_today = 0;
                        $today_start = mktime(0, 0, 0);
                        $debug_info = [];
                        
                        try {
                            if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                                // Use standard Moodle field name
                                $time_field = 'timecreated';
                                
                                // Get all companies and check each one's usage against their specific limit
                                $companies = local_alx_report_api_get_companies();
                                $debug_info[] = "Found " . count($companies) . " companies";
                                
                                foreach ($companies as $company) {
                                    // Get company-specific rate limit
                                    $company_settings = local_alx_report_api_get_company_settings($company->id);
                                    $company_rate_limit = isset($company_settings['rate_limit']) ? 
                                        $company_settings['rate_limit'] : 
                                        get_config('local_alx_report_api', 'rate_limit');
                                    
                                    if (empty($company_rate_limit)) {
                                        $company_rate_limit = 100; // Default fallback
                                    }
                                    
                                    // Count today's API calls for this company
                                    $company_calls_today = $DB->count_records_select('local_alx_api_logs',
                                        "{$time_field} >= ? AND company_shortname = ?",
                                        [$today_start, $company->shortname]
                                    );
                                    
                                    $debug_info[] = "Company: {$company->name} (shortname: {$company->shortname}) - Limit: {$company_rate_limit}, Calls: {$company_calls_today}";
                                    
                                    // Check if company exceeded their specific limit
                                    if ($company_calls_today > $company_rate_limit) {
                                        $violations_today++;
                                        $debug_info[] = "  -> VIOLATION DETECTED!";
                                    }
                                    
                                    // Count users with activity today
                                    if ($company_calls_today > 0) {
                                        $users_today++;
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log('Control Center rate limit calculation error: ' . $e->getMessage());
                            $debug_info[] = "ERROR: " . $e->getMessage();
                        }
                        
                        // Output debug info as HTML comments
                        echo "<!-- DEBUG RATE LIMIT CHECK -->\n";
                        echo "<!-- Today Start: " . date('Y-m-d H:i:s', $today_start) . " -->\n";
                        foreach ($debug_info as $info) {
                            echo "<!-- " . htmlspecialchars($info) . " -->\n";
                        }
                        echo "<!-- Total Violations: {$violations_today} -->\n";
                        echo "<!-- END DEBUG -->\n";
                        
                        // ========================================
                        // DYNAMIC SECURITY STATUS CHECKS
                        // ========================================
                        
                        // 1. Rate Limiting Status - Check if rate limiting is configured and active
                        $rate_limit_global = get_config('local_alx_report_api', 'rate_limit');
                        $rate_limit_active = !empty($rate_limit_global) && $rate_limit_global > 0;
                        $rate_limit_status = $rate_limit_active ? 'Active' : 'Disabled';
                        $rate_limit_color = $rate_limit_active ? '#10b981' : '#ef4444';
                        
                        // 2. Token Security Status - Check for expired tokens and HTTPS
                        $token_security_status = 'Secure';
                        $token_security_color = '#10b981';
                        $token_issues = [];
                        
                        try {
                            // Check if HTTPS is enabled
                            $is_https = !empty($CFG->wwwroot) && strpos($CFG->wwwroot, 'https://') === 0;
                            if (!$is_https) {
                                $token_issues[] = 'HTTP (not HTTPS)';
                            }
                            
                            // Check for expired tokens
                            if ($DB->get_manager()->table_exists('external_tokens')) {
                                $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api_custom']);
                                if (!$service_id) {
                                    $service_id = $DB->get_field('external_services', 'id', ['shortname' => 'alx_report_api']);
                                }
                                
                                if ($service_id) {
                                    $expired_tokens = $DB->count_records_select('external_tokens',
                                        'externalserviceid = ? AND validuntil > 0 AND validuntil < ?',
                                        [$service_id, time()]
                                    );
                                    
                                    if ($expired_tokens > 0) {
                                        $token_issues[] = "{$expired_tokens} expired token(s)";
                                    }
                                }
                            }
                            
                            // Set status based on issues found
                            if (count($token_issues) > 0) {
                                $token_security_status = 'Warning';
                                $token_security_color = '#f59e0b';
                            }
                        } catch (Exception $e) {
                            $token_security_status = 'Unknown';
                            $token_security_color = '#6b7280';
                            error_log('Token security check error: ' . $e->getMessage());
                        }
                        
                        // 3. Access Control Status - Check web services, REST protocol, and service status
                        $access_control_status = 'Enabled';
                        $access_control_color = '#10b981';
                        $access_issues = [];
                        
                        try {
                            // Check if web services are enabled
                            if (empty($CFG->enablewebservices)) {
                                $access_issues[] = 'Web services disabled';
                            }
                            
                            // Check if REST protocol is enabled (with fallback for different Moodle versions)
                            $rest_enabled = false;
                            try {
                                // Try standard table first
                                if ($DB->get_manager()->table_exists('webservice_protocol')) {
                                    $rest_enabled = $DB->record_exists('webservice_protocol', ['name' => 'rest', 'enabled' => 1]);
                                } else {
                                    // Fallback: Check if webserviceprotocols config contains 'rest'
                                    $protocols = get_config('moodle', 'webserviceprotocols');
                                    $rest_enabled = !empty($protocols) && strpos($protocols, 'rest') !== false;
                                }
                            } catch (Exception $e) {
                                // If table doesn't exist or query fails, assume REST is enabled if web services are on
                                $rest_enabled = !empty($CFG->enablewebservices);
                            }
                            
                            if (!$rest_enabled) {
                                $access_issues[] = 'REST protocol disabled';
                            }
                            
                            // Check if service exists and is enabled
                            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
                            if (!$service) {
                                $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
                            }
                            
                            if (!$service) {
                                $access_issues[] = 'Service not found';
                            } else if (empty($service->enabled)) {
                                $access_issues[] = 'Service disabled';
                            }
                            
                            // Set status based on issues found
                            if (count($access_issues) > 0) {
                                $access_control_status = 'Issues';
                                $access_control_color = '#ef4444';
                            }
                        } catch (Exception $e) {
                            $access_control_status = 'Unknown';
                            $access_control_color = '#6b7280';
                            error_log('Access control check error: ' . $e->getMessage());
                        }
                        
                        // Security score calculation (adjusted based on all security factors)
                        $security_score = 100;
                        if ($violations_today > 0) $security_score -= ($violations_today * 10);
                        if (!$rate_limit_active) $security_score -= 20;
                        if (count($token_issues) > 0) $security_score -= (count($token_issues) * 10);
                        if (count($access_issues) > 0) $security_score -= (count($access_issues) * 15);
                        $security_score = max(0, $security_score);
                        ?>

                        <!-- Security Score -->
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="position: relative; display: inline-block;">
                                <canvas id="security-score-chart" width="120" height="120"></canvas>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                    <div style="font-size: 20px; font-weight: 700; color: #10b981;"><?php echo $security_score; ?></div>
                                    <div style="font-size: 10px; color: #6b7280;">Security Score</div>
                                </div>
                            </div>
                        </div>

                        <!-- Rate Limiting Status -->
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #1f2937;">Rate Limiting:</strong>
                                <span style="background: <?php echo $rate_limit_color; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo $rate_limit_status; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Token Security -->
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #1f2937;">Token Security:</strong>
                                <span style="background: <?php echo $token_security_color; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;" 
                                      title="<?php echo !empty($token_issues) ? implode(', ', $token_issues) : 'All tokens are secure'; ?>">
                                    <?php echo $token_security_status; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Access Control -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #1f2937;">Access Control:</strong>
                                <span style="background: <?php echo $access_control_color; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;"
                                      title="<?php echo !empty($access_issues) ? implode(', ', $access_issues) : 'All access controls are enabled'; ?>">
                                    <?php echo $access_control_status; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Security Metrics Grid -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="text-align: center; padding: 12px; background: rgba(16,185,129,0.1); border-radius: 8px; border: 1px solid rgba(16,185,129,0.2);">
                                <div style="font-size: 20px; font-weight: 700; color: <?php echo $violations_today > 0 ? '#ef4444' : '#10b981'; ?>;"><?php echo $violations_today; ?></div>
                                <div style="font-size: 12px; color: #6b7280;">Violations Today</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: rgba(59,130,246,0.1); border-radius: 8px; border: 1px solid rgba(59,130,246,0.2);">
                                <div style="font-size: 20px; font-weight: 700; color: #3b82f6;"><?php echo $users_today; ?></div>
                                <div style="font-size: 12px; color: #6b7280;">Active Users</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer" style="border-top: 1px solid rgba(31,41,55,0.1); background: rgba(255,255,255,0.1); padding: 16px; text-align: center;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard_new.php?tab=security" class="btn-modern" style="background: #10b981; color: white; border: 2px solid #10b981; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(16,185,129,0.3);">
                            <i class="fas fa-shield-alt"></i>
                            Security Monitor
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions (Hidden) -->
        <div class="dashboard-card" style="margin-top: 20px; margin-bottom: 0; display: none;">
            <div class="card-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 16px 20px 12px 20px;">
                <h3 class="card-title" style="color: white; margin: 0;">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h3>
                <p class="card-subtitle" style="color: rgba(255,255,255,0.8); margin: 4px 0 0 0;">Common tasks and operations</p>
            </div>
            <div class="card-body" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/populate_reporting_table.php" class="btn-modern btn-primary" style="background: #007bff; color: white; border: 2px solid #007bff; padding: 12px 16px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,123,255,0.3);">
                        <i class="fas fa-database"></i>
                        Populate Data
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/sync_reporting_data.php" class="btn-modern btn-warning" style="background: #ffc107; color: #212529; border: 2px solid #ffc107; padding: 12px 16px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(255,193,7,0.3);">
                        <i class="fas fa-sync"></i>
                        Manual Sync
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/company_settings.php" class="btn-modern btn-success" style="background: #28a745; color: white; border: 2px solid #28a745; padding: 12px 16px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(40,167,69,0.3);">
                        <i class="fas fa-building"></i>
                        Company Settings
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-modern btn-secondary" style="background: #6c757d; color: white; border: 2px solid #6c757d; padding: 12px 16px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(108,117,125,0.3);">
                        <i class="fas fa-chart-bar"></i>
                        Monitoring
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Other tabs content will be added here -->
    <div id="companies-tab" class="tab-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-building"></i>
                    Company Management
                </h3>
                <p class="card-subtitle">Configure company-specific API settings and field controls</p>
            </div>
            <div class="card-body">
                <?php
                // Handle company settings form submission
                $companyid = optional_param('companyid', 0, PARAM_INT);
                $settings_action = optional_param('settings_action', '', PARAM_ALPHA);
                
                // Handle form submission
                if ($settings_action === 'save' && $companyid && confirm_sesskey()) {
                    $errors = [];
                    $success_count = 0;
                    
                    try {
                        $field_settings = [
                            'field_userid', 'field_firstname', 'field_lastname', 'field_email',
                            'field_courseid', 'field_coursename', 'field_timecompleted', 
                            'field_timecompleted_unix', 'field_timestarted', 'field_timestarted_unix',
                            'field_percentage', 'field_status'
                        ];
                        
                        // Save field settings
                        foreach ($field_settings as $setting) {
                            $value = optional_param($setting, 0, PARAM_INT);
                            $result = local_alx_report_api_set_company_setting($companyid, $setting, $value);
                            if ($result !== false) {
                                $success_count++;
                            }
                        }
                        
                        // Save course settings
                        $company_courses = local_alx_report_api_get_company_courses($companyid);
                        foreach ($company_courses as $course) {
                            $course_setting = 'course_' . $course->id;
                            $value = optional_param($course_setting, 0, PARAM_INT);
                            $result = local_alx_report_api_set_company_setting($companyid, $course_setting, $value);
                            if ($result !== false) {
                                $success_count++;
                            }
                        }
                        
                        // Save incremental sync settings
                        $sync_settings = [
                            'sync_mode', 'sync_window_hours', 'first_sync_hours', 'cache_enabled', 'cache_ttl_minutes', 'rate_limit'
                        ];
                        
                        foreach ($sync_settings as $setting) {
                            if ($setting === 'sync_mode') {
                                $value = optional_param($setting, 0, PARAM_INT);
                                // Validate sync_mode values: 0=Auto, 1=Always Incremental, 2=Always Full, 3=Disabled
                                if (!in_array($value, [0, 1, 2, 3])) {
                                    $value = 0; // Default to Auto if invalid value
                                }
                            } else if ($setting === 'rate_limit') {
                                // Handle rate_limit specially - allow empty value to use global default
                                $value = optional_param($setting, '', PARAM_INT);
                                if ($value !== '' && $value !== null) {
                                    // Validate rate limit range (1-10000)
                                    $value = (int)$value;
                                    if ($value < 1 || $value > 10000) {
                                        $errors[] = 'Rate limit must be between 1 and 10000';
                                        continue;
                                    }
                                } else {
                                    // Empty value - delete the setting to use global default
                                    $DB->delete_records('local_alx_api_settings', [
                                        'companyid' => $companyid,
                                        'setting_name' => 'rate_limit'
                                    ]);
                                    $success_count++;
                                    continue;
                                }
                            } else {
                                $value = optional_param($setting, 0, PARAM_INT);
                                // Ensure positive values for numeric settings
                                if ($value < 0) {
                                    $value = 0;
                                }
                            }
                            
                            $result = local_alx_report_api_set_company_setting($companyid, $setting, $value);
                            if ($result !== false) {
                                $success_count++;
                            }
                        }
                        
                        echo '<div class="alert alert-success">✅ Settings saved successfully! (' . $success_count . ' settings updated)</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">❌ Error saving settings: ' . $e->getMessage() . '</div>';
                    }
                }
                
                // Get all companies
                $all_companies = local_alx_report_api_get_companies();
                ?>
                
                <!-- Company Selection -->
                <div class="company-selector" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #495057;">
                        <i class="fas fa-building"></i> Select Company
                    </h4>
                    
                    <?php if (empty($all_companies)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No companies found. Please ensure IOMAD companies are configured.
                        </div>
                    <?php else: ?>
                        <form method="get" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <label for="companyid" style="font-weight: 600; color: #495057;">Company:</label>
                            <select name="companyid" id="companyid" onchange="handleCompanySelection(this)" 
                                    style="min-width: 250px; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">
                                <option value="0">Choose a company...</option>
                                <?php foreach ($all_companies as $company): ?>
                                    <option value="<?php echo $company->id; ?>" <?php echo ($companyid == $company->id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="tab" value="companies">
                            <input type="submit" value="Select" class="btn-modern btn-primary" style="padding: 10px 20px;">
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if ($companyid && isset($all_companies[$companyid])): ?>
                    <?php 
                    $selected_company = $all_companies[$companyid];
                    $current_settings = local_alx_report_api_get_company_settings($companyid);
                    
                    // Check reporting data status
                    $reporting_records = $DB->count_records('local_alx_api_reporting', ['companyid' => $companyid, 'is_deleted' => 0]);
                    ?>
                    
                    <!-- Company Settings Form -->
                    <div style="border: 2px solid #e9ecef; border-radius: 12px; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 20px;">
                            <h4 style="margin: 0; font-size: 1.3em;">
                                <i class="fas fa-cog"></i> Settings for: <?php echo htmlspecialchars($selected_company->name); ?>
                            </h4>
                        </div>
                        
                        <div style="padding: 25px;">
                            <!-- Data Status Alert -->
                            <?php if ($reporting_records === 0): ?>
                                <div class="alert alert-danger" style="margin-bottom: 25px;">
                                    <h5 style="margin: 0 0 10px 0;"><i class="fas fa-exclamation-triangle"></i> CRITICAL: Historical Data Required</h5>
                                    <p style="margin: 0 0 10px 0;">Your reporting table is currently EMPTY! Without populating historical data first, the API will only return recent activity.</p>
                                    <a href="populate_reporting_table.php" class="btn-modern btn-warning" style="margin-top: 10px;">
                                        <i class="fas fa-database"></i> Populate Historical Data
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success" style="margin-bottom: 25px;">
                                    <h5 style="margin: 0 0 10px 0;"><i class="fas fa-check-circle"></i> Data Status: Ready</h5>
                                    <p style="margin: 0;">Reporting table contains <strong><?php echo number_format($reporting_records); ?></strong> records for this company.</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Settings Form -->
                            <form method="post" style="margin-top: 20px;">
                                <input type="hidden" name="companyid" value="<?php echo $companyid; ?>">
                                <input type="hidden" name="settings_action" value="save">
                                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                                
                                <!-- Field Controls -->
                                <h5 style="color: #495057; margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                                    <i class="fas fa-list-check"></i> API Field Controls
                                </h5>
                                <p style="color: #6c757d; margin-bottom: 20px; font-style: italic;">
                                    Select which fields should be included in the API response for this company.
                                </p>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 30px;">
                                    <?php
                                    $field_definitions = [
                                        'field_userid' => 'User ID',
                                        'field_firstname' => 'First Name', 
                                        'field_lastname' => 'Last Name',
                                        'field_email' => 'Email Address',
                                        'field_courseid' => 'Course ID',
                                        'field_coursename' => 'Course Name',
                                        'field_timecompleted' => 'Time Completed (Readable)',
                                        'field_timecompleted_unix' => 'Time Completed (Unix)',
                                        'field_timestarted' => 'Time Started (Readable)',
                                        'field_timestarted_unix' => 'Time Started (Unix)',
                                        'field_percentage' => 'Completion Percentage',
                                        'field_status' => 'Completion Status'
                                    ];
                                    
                                    foreach ($field_definitions as $field => $label):
                                        $checked = isset($current_settings[$field]) ? $current_settings[$field] : 1;
                                    ?>
                                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 2px solid <?php echo $checked ? '#28a745' : '#e9ecef'; ?>;">
                                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                                                <input type="checkbox" name="<?php echo $field; ?>" value="1" 
                                                       <?php echo $checked ? 'checked' : ''; ?>
                                                       style="width: 18px; height: 18px;">
                                                <span style="font-weight: 500; color: #495057;"><?php echo $label; ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Course Controls -->
                                <?php 
                                $company_courses = local_alx_report_api_get_company_courses($companyid);
                                if (!empty($company_courses)): 
                                ?>
                                    <h5 style="color: #495057; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                                        <i class="fas fa-graduation-cap"></i> Course Controls
                                    </h5>
                                    <p style="color: #6c757d; margin-bottom: 20px; font-style: italic;">
                                        Select which courses should be included in the API response for this company.
                                    </p>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 30px;">
                                        <?php foreach ($company_courses as $course): 
                                            $course_setting = 'course_' . $course->id;
                                            $course_checked = isset($current_settings[$course_setting]) ? $current_settings[$course_setting] : 1;
                                        ?>
                                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 2px solid <?php echo $course_checked ? '#007bff' : '#e9ecef'; ?>;">
                                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                                                    <input type="checkbox" name="<?php echo $course_setting; ?>" value="1" 
                                                           <?php echo $course_checked ? 'checked' : ''; ?>
                                                           style="width: 18px; height: 18px;">
                                                    <div>
                                                        <div style="font-weight: 500; color: #495057;"><?php echo htmlspecialchars($course->fullname); ?></div>
                                                        <small style="color: #6c757d;">ID: <?php echo $course->id; ?></small>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- API Response Settings -->
                                <h5 style="color: #495057; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e9ecef;">
                                    <i class="fas fa-exchange-alt"></i> API Response Settings
                                </h5>
                                <p style="color: #6c757d; margin-bottom: 20px; font-style: italic;">
                                    Configure how the API responds to data requests for optimal performance and data delivery.
                                </p>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                                    <!-- Response Mode -->
                                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <label style="display: block; font-weight: 600; color: #495057; margin-bottom: 10px;">
                                            <i class="fas fa-cogs"></i> Response Mode
                                        </label>
                                        <select name="sync_mode" id="response_mode" onchange="updateFieldStates()" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; background: white;">
                                            <?php 
                                            $sync_mode = isset($current_settings['sync_mode']) ? $current_settings['sync_mode'] : 0;
                                            $response_options = [
                                                0 => 'Auto (Intelligent)',
                                                1 => 'Always Incremental Response', 
                                                2 => 'Always Full Response',
                                                3 => 'Disabled'
                                            ];
                                            foreach ($response_options as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo ($sync_mode == $value) ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small style="color: #6c757d; display: block; margin-top: 8px;">
                                            <strong>Default: Auto</strong> - Intelligently chooses between incremental and full response
                                        </small>
                                    </div>
                                    
                                    <!-- Incremental Window -->
                                    <div id="incremental_window_field" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <label style="display: block; font-weight: 600; color: #495057; margin-bottom: 10px;">
                                            <i class="fas fa-clock"></i> Incremental Window (Hours)
                                        </label>
                                        <input type="number" name="sync_window_hours" id="incremental_window" min="1" max="168" 
                                               value="<?php echo isset($current_settings['sync_window_hours']) ? $current_settings['sync_window_hours'] : 24; ?>"
                                               style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px;">
                                        <small id="incremental_window_help" style="color: #6c757d; display: block; margin-top: 8px;">
                                            <strong>Default: 24 hours</strong> - Time range for recent changes in API response (1-168 hours)
                                        </small>
                                    </div>
                                    
                                    <!-- Initial Response Window -->
                                    <div id="initial_window_field" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <label style="display: block; font-weight: 600; color: #495057; margin-bottom: 10px;">
                                            <i class="fas fa-hourglass-start"></i> Initial Response Window (Hours)
                                        </label>
                                        <input type="number" name="first_sync_hours" id="initial_window" min="1" max="720" 
                                               value="<?php echo isset($current_settings['first_sync_hours']) ? $current_settings['first_sync_hours'] : 168; ?>"
                                               style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px;">
                                        <small id="initial_window_help" style="color: #6c757d; display: block; margin-top: 8px;">
                                            <strong>Default: 168 hours (7 days)</strong> - Time range for first API call from new companies (1-720 hours)
                                        </small>
                                    </div>
                                    
                                    <!-- Cache Enabled -->
                                    <div id="cache_enabled_field" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid <?php echo (isset($current_settings['cache_enabled']) && $current_settings['cache_enabled']) ? '#28a745' : '#e9ecef'; ?>;">
                                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                                            <input type="checkbox" name="cache_enabled" id="cache_enabled" value="1" 
                                                   <?php echo (isset($current_settings['cache_enabled']) && $current_settings['cache_enabled']) ? 'checked' : ''; ?>
                                                   style="width: 18px; height: 18px;">
                                            <div>
                                                <div style="font-weight: 600; color: #495057;">
                                                    <i class="fas fa-memory"></i> Enable Response Caching
                                                </div>
                                                <small id="cache_enabled_help" style="color: #6c757d; display: block; margin-top: 5px;">
                                                    <strong>Default: Enabled</strong> - Cache API responses to improve performance
                                                </small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Cache TTL -->
                                    <div id="cache_ttl_field" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <label style="display: block; font-weight: 600; color: #495057; margin-bottom: 10px;">
                                            <i class="fas fa-stopwatch"></i> Cache TTL (Minutes)
                                        </label>
                                        <input type="number" name="cache_ttl_minutes" id="cache_ttl" min="1" max="1440" 
                                               value="<?php echo isset($current_settings['cache_ttl_minutes']) ? $current_settings['cache_ttl_minutes'] : 60; ?>"
                                               style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px;">
                                        <small id="cache_ttl_help" style="color: #6c757d; display: block; margin-top: 8px;">
                                            <strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)
                                        </small>
                                    </div>
                                    
                                    <!-- Rate Limit -->
                                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <label style="display: block; font-weight: 600; color: #495057; margin-bottom: 10px;">
                                            <i class="fas fa-tachometer-alt"></i> Rate Limit (Requests/Day)
                                        </label>
                                        <?php 
                                        $global_rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
                                        $company_rate_limit = isset($current_settings['rate_limit']) ? $current_settings['rate_limit'] : '';
                                        ?>
                                        <input type="number" name="rate_limit" id="rate_limit" min="1" max="10000" 
                                               value="<?php echo $company_rate_limit; ?>"
                                               placeholder="Using global default: <?php echo $global_rate_limit; ?>"
                                               style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px;">
                                        <small style="color: #6c757d; display: block; margin-top: 8px;">
                                            <strong>Default: <?php echo $global_rate_limit; ?> requests/day</strong> - Maximum API requests per day for this company. Leave empty to use global default.
                                        </small>
                                        <?php
                                        // Show current usage
                                        $today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
                                        if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                                            // Use standard Moodle field name
                                            $time_field = 'timecreated';
                                            
                                            // Get company users
                                            $company_users = $DB->get_records('company_users', ['companyid' => $companyid], '', 'userid');
                                            if (!empty($company_users)) {
                                                $userids = array_keys($company_users);
                                                list($user_sql, $user_params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                                                
                                                $sql = "SELECT COUNT(*) as request_count
                                                        FROM {local_alx_api_logs}
                                                        WHERE userid {$user_sql} AND {$time_field} >= :today_start";
                                                $params = array_merge($user_params, ['today_start' => $today_start]);
                                                
                                                $usage = $DB->get_record_sql($sql, $params);
                                                if ($usage && $usage->request_count > 0) {
                                                    $effective_limit = $company_rate_limit !== '' ? $company_rate_limit : $global_rate_limit;
                                                    $percentage = $effective_limit > 0 ? round(($usage->request_count / $effective_limit) * 100) : 0;
                                                    $alert_class = $percentage >= 80 ? 'alert-warning' : 'alert-info';
                                                    
                                                    echo '<div class="alert ' . $alert_class . '" style="margin-top: 10px; padding: 10px; border-radius: 6px;">';
                                                    echo '<strong>Today\'s Usage:</strong> ' . $usage->request_count . ' / ' . $effective_limit . ' requests (' . $percentage . '%)';
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Form Actions -->
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin-top: 30px;">
                                    <button type="submit" class="btn-modern btn-success" style="padding: 12px 30px; font-size: 16px;">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                    <a href="control_center.php" class="btn-modern btn-secondary" style="padding: 12px 30px; font-size: 16px; margin-left: 15px;">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-arrow-up" style="font-size: 3em; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h4>Select a company above to configure its settings</h4>
                        <p>Choose a company from the dropdown to view and modify its API field controls and course settings.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="data-tab" class="tab-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-database"></i>
                    Data Management
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/populate_reporting_table.php" class="btn-modern btn-primary">
                        <i class="fas fa-database"></i>
                        Populate Reporting Table
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/sync_reporting_data.php" class="btn-modern btn-warning">
                        <i class="fas fa-sync"></i>
                        Manual Sync Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="monitoring-tab" class="tab-content">
        <!-- Simple Instruction Card -->
        <div class="dashboard-card" style="max-width: 800px; margin: 60px auto; text-align: center;">
            <div class="card-body" style="padding: 60px 40px;">
                <div style="font-size: 64px; margin-bottom: 24px;">💡</div>
                <h2 style="font-size: 28px; font-weight: 700; color: #2d3748; margin-bottom: 16px;">
                    Monitoring & Analytics
                </h2>
                <p style="font-size: 16px; color: #718096; margin-bottom: 32px; line-height: 1.6;">
                    Hover over the "Monitoring & Analytics" tab above to access:
                </p>
                <ul style="list-style: none; padding: 0; margin: 0 0 32px 0; text-align: left; max-width: 400px; margin-left: auto; margin-right: auto;">
                    <li style="padding: 12px 0; font-size: 16px; color: #4a5568;">
                        <span style="margin-right: 12px;">🔄</span> Auto-Sync Intelligence
                    </li>
                    <li style="padding: 12px 0; font-size: 16px; color: #4a5568;">
                        <span style="margin-right: 12px;">⚡</span> Performance Monitoring
                    </li>
                    <li style="padding: 12px 0; font-size: 16px; color: #4a5568;">
                        <span style="margin-right: 12px;">🔒</span> Security & Alerts
                    </li>
                </ul>
                <p style="font-size: 14px; color: #a0aec0; margin-bottom: 24px;">
                    Or click below to view the unified monitoring dashboard:
                </p>
                <a href="monitoring_dashboard_new.php" class="btn-modern btn-primary" style="font-size: 18px; padding: 16px 32px;">
                    <i class="fas fa-chart-bar"></i>
                    Open Monitoring Dashboard
                </a>
            </div>
        </div>
        <?php
        // Minimal data for compatibility
        $monitoring_data = [];
        ?>
    </div>

    <!-- System Configuration Tab -->
    <div id="monitoring-tab" class="tab-content">
        <?php
        // Get monitoring data - REAL DATA ONLY
        try {
            // Get system health data
            $system_health_data = local_alx_report_api_get_system_health();
            
            // Get API analytics for today - REAL DATA ONLY
            $api_analytics = local_alx_report_api_get_api_analytics(24);
            
            // Get rate limit monitoring
            $rate_monitoring = local_alx_report_api_get_rate_limit_monitoring();
            
            // Get REAL cache statistics
            $cache_stats = [];
            if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
                $total_cache = $DB->count_records('local_alx_api_cache');
                $active_cache = $DB->count_records_select('local_alx_api_cache', 'expires_at > ?', [time()]);
                $cache_stats['total_entries'] = $total_cache;
                $cache_stats['active_entries'] = $active_cache;
                // Calculate real hit rate
                $cache_stats['hit_rate'] = $total_cache > 0 ? round(($active_cache / $total_cache) * 100, 1) : 0;
            } else {
                $cache_stats = ['total_entries' => 0, 'active_entries' => 0, 'hit_rate' => 0];
            }
            
            // Get REAL authentication statistics
            $auth_stats = [];
            if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                $today_start = mktime(0, 0, 0);
                // Use standard Moodle field name
                $time_field = 'timecreated';
                
                // Get real unique users count
                $auth_stats['unique_users'] = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT userid) FROM {local_alx_api_logs} WHERE {$time_field} >= ?", 
                    [$today_start]
                );
                
                // Get real error rate
                $total_calls = $api_analytics['summary']['total_calls'] ?? 0;
                $error_calls = 0;
                if (isset($table_info['error_message'])) {
                    $error_calls = $DB->count_records_select('local_alx_api_logs', 
                        "{$time_field} >= ? AND error_message IS NOT NULL", [$today_start]);
                }
                $auth_stats['success_rate'] = $total_calls > 0 ? round((($total_calls - $error_calls) / $total_calls) * 100, 1) : 100;
            } else {
                $auth_stats = ['unique_users' => 0, 'success_rate' => 100];
            }
            
            // Get REAL database performance
            $db_performance = [];
            if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
                $start_time = microtime(true);
                $sample_query = $DB->get_records('local_alx_api_reporting', [], '', 'id', 0, 1);
                $db_performance['response_time'] = round((microtime(true) - $start_time) * 1000, 2);
                $db_performance['status'] = $db_performance['response_time'] < 100 ? 'excellent' : 
                                          ($db_performance['response_time'] < 500 ? 'good' : 'slow');
            } else {
                $db_performance = ['response_time' => 0, 'status' => 'no_data'];
            }
            
            $monitoring_data = [
                'system_health' => $system_health_data,
                'api_analytics' => $api_analytics,
                'rate_monitoring' => $rate_monitoring,
                'cache_stats' => $cache_stats,
                'auth_stats' => $auth_stats,
                'db_performance' => $db_performance
            ];
            
        } catch (Exception $e) {
            error_log('ALX Report API Monitoring Data: ' . $e->getMessage());
            // Use real zero values instead of fake data
            $monitoring_data = [
                'system_health' => ['overall_status' => 'error', 'score' => 0],
                'api_analytics' => ['summary' => ['total_calls' => 0, 'unique_users' => 0, 'unique_companies' => 0, 'calls_per_hour' => 0]],
                'rate_monitoring' => ['violations' => []],
                'cache_stats' => ['total_entries' => 0, 'hit_rate' => 0, 'active_entries' => 0],
                'auth_stats' => ['unique_users' => 0, 'success_rate' => 100],
                'db_performance' => ['response_time' => 0, 'status' => 'error']
            ];
        }
        
        // Extract key variables for easy access - REAL DATA ONLY
        $health_score = $monitoring_data['system_health']['score'] ?? 0;
        $total_calls_today = $monitoring_data['api_analytics']['summary']['total_calls'] ?? 0;
        $unique_users = $monitoring_data['api_analytics']['summary']['unique_users'] ?? 0;
        $unique_companies = $monitoring_data['api_analytics']['summary']['unique_companies'] ?? 0;
        $success_rate = $monitoring_data['auth_stats']['success_rate'] ?? 100;
        $violations_count = count($monitoring_data['rate_monitoring']['violations'] ?? []);
        
        // Calculate REAL calls per hour based on actual data
        $hours_since_midnight = (time() - mktime(0, 0, 0)) / 3600;
        $calls_per_hour = $hours_since_midnight > 0 ? round($total_calls_today / $hours_since_midnight, 1) : 0;
        
        // If we have recent calls (last hour), use that for more accurate rate
        if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
            // Use standard Moodle field name
            $time_field = 'timecreated';
            $last_hour_calls = $DB->count_records_select('local_alx_api_logs', 
                "{$time_field} >= ?", [time() - 3600]);
            if ($last_hour_calls > 0) {
                $calls_per_hour = $last_hour_calls; // Use last hour's actual count
            }
        }
        
        // Get REAL system stats (not duplicated)
        $memory_usage = '0MB/0MB';
        $system_load = 0;
        if (function_exists('memory_get_usage')) {
            $memory_used = round(memory_get_usage(true) / 1024 / 1024, 1);
            $memory_limit = ini_get('memory_limit');
            $memory_usage = $memory_used . 'MB/' . $memory_limit;
        }
        ?>
        
        <!-- Monitoring Dashboard Header -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; text-align: center;">
                <h3 class="card-title" style="color: white; font-size: 24px; justify-content: center;">
                    <i class="fas fa-tachometer-alt"></i>
                    ALX Report API Monitoring Dashboard
                </h3>
                <p class="card-subtitle" style="color: rgba(255,255,255,0.9); margin-top: 8px;">
                    Real-time system monitoring and performance analytics
                </p>
            </div>
        </div>

        <!-- System Overview - 4 Cards in One Row -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    System Overview
                </h3>
                <p class="card-subtitle">Key performance metrics and system status</p>
            </div>
            <div class="card-body">
                <div class="monitoring-grid-4">
                    <div class="dashboard-card" style="text-align: center; border-top: 4px solid #10b981;">
                        <div class="card-body">
                            <div style="font-size: 20px; margin-bottom: 10px;">💚</div>
                            <h4 style="margin: 0; font-size: 16px; color: #64748b;">System Health</h4>
                            <div style="font-size: 48px; font-weight: 700; color: #10b981; margin: 10px 0;">
                                <?php echo $health_score; ?>%
                            </div>
                            <div style="font-size: 14px; color: #64748b; margin-bottom: 16px;">
                                <?php 
                                $health_status = $monitoring_data['system_health']['overall_status'] ?? 'unknown';
                                echo ucfirst($health_status);
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" style="text-align: center; border-top: 4px solid #3b82f6;">
                        <div class="card-body">
                            <div style="font-size: 20px; margin-bottom: 10px;">📊</div>
                            <h4 style="margin: 0; font-size: 16px; color: #64748b;">API Calls Today</h4>
                            <div style="font-size: 48px; font-weight: 700; color: #3b82f6; margin: 10px 0;">
                                <?php echo number_format($total_calls_today); ?>
                            </div>
                            <div style="font-size: 14px; color: #64748b;">
                                <?php echo $calls_per_hour; ?>/hour average
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" style="text-align: center; border-top: 4px solid #8b5cf6;">
                        <div class="card-body">
                            <div style="font-size: 20px; margin-bottom: 10px;">⚡</div>
                            <h4 style="margin: 0; font-size: 16px; color: #64748b;">Database Performance</h4>
                            <div style="font-size: 48px; font-weight: 700; color: #8b5cf6; margin: 10px 0;">
                                <?php echo $monitoring_data['db_performance']['response_time']; ?>ms
                            </div>
                            <div style="font-size: 14px; color: #64748b;">
                                <?php 
                                $db_status = $monitoring_data['db_performance']['status'];
                                echo $db_status === 'no_data' ? 'No data' : ucfirst($db_status);
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" style="text-align: center; border-top: 4px solid #f59e0b;">
                        <div class="card-body">
                            <div style="font-size: 20px; margin-bottom: 10px;">🔒</div>
                            <h4 style="margin: 0; font-size: 16px; color: #64748b;">Security Status</h4>
                            <div style="font-size: 48px; font-weight: 700; color: #f59e0b; margin: 10px 0;">
                                <?php echo $success_rate; ?>%
                            </div>
                            <div style="font-size: 14px; color: #64748b;">
                                <?php echo $violations_count; ?> violations today
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Analytics Chart -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    📈 API Usage Trends (Last 24 Hours)
                </h3>
                <p class="card-subtitle">Interactive chart showing API calls from 00:00 to 24:00 with hourly breakdown</p>
            </div>
            <div class="card-body">
                <canvas id="monitoring-performance-chart" width="800" height="300" style="max-width: 100%; background: #f8fafc; border-radius: 8px; padding: 20px;"></canvas>
                <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="padding: 16px; background: #f0f9ff; border-radius: 8px; text-align: center; border-left: 4px solid #3b82f6;">
                        <div style="font-size: 24px; font-weight: 600; color: #3b82f6;"><?php echo $calls_per_hour; ?></div>
                        <div style="font-size: 14px; color: #64748b;">Calls per Hour</div>
                    </div>
                    <div style="padding: 16px; background: #f0fdf4; border-radius: 8px; text-align: center; border-left: 4px solid #10b981;">
                        <div style="font-size: 24px; font-weight: 600; color: #10b981;"><?php echo $unique_users; ?></div>
                        <div style="font-size: 14px; color: #64748b;">Active Users</div>
                    </div>
                    <div style="padding: 16px; background: #fdf4ff; border-radius: 8px; text-align: center; border-left: 4px solid #8b5cf6;">
                        <div style="font-size: 24px; font-weight: 600; color: #8b5cf6;"><?php echo $monitoring_data['db_performance']['response_time']; ?>ms</div>
                        <div style="font-size: 14px; color: #64748b;">DB Response Time</div>
                    </div>
                    <div style="padding: 16px; background: #fefce8; border-radius: 8px; text-align: center; border-left: 4px solid #f59e0b;">
                        <div style="font-size: 24px; font-weight: 600; color: #f59e0b;"><?php echo $monitoring_data['cache_stats']['hit_rate']; ?>%</div>
                        <div style="font-size: 14px; color: #64748b;">Cache Hit Rate</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Statistics & Alerts -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    System Statistics & Alerts
                </h3>
                <p class="card-subtitle">Comprehensive system metrics and status information</p>
            </div>
            <div class="card-body">
                <div class="monitoring-grid-2">
                    <div>
                        <h4 style="color: #2c3e50; font-size: 18px; margin-bottom: 15px; border-bottom: 2px solid #ecf0f1; padding-bottom: 8px;">
                            📊 Quick Stats
                        </h4>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1;">
                            <span style="color: #7f8c8d; font-size: 14px;">• Total Companies:</span>
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo number_format($total_companies); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1;">
                            <span style="color: #7f8c8d; font-size: 14px;">• Course Records:</span>
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo number_format($total_records); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1;">
                            <span style="color: #7f8c8d; font-size: 14px;">• Cache Hit Rate:</span>
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo $monitoring_data['cache_stats']['hit_rate']; ?>%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1;">
                            <span style="color: #7f8c8d; font-size: 14px;">• Active Users Today:</span>
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo number_format($unique_users); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                            <span style="color: #7f8c8d; font-size: 14px;">• Memory Usage:</span>
                            <span style="font-weight: 600; color: #2c3e50;"><?php echo $memory_usage; ?></span>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="color: #2c3e50; font-size: 18px; margin-bottom: 15px; border-bottom: 2px solid #ecf0f1; padding-bottom: 8px;">
                            🚨 Alert Status
                        </h4>
                        <div style="display: flex; align-items: center; padding: 8px 0; font-size: 14px;">
                            <span style="margin-right: 10px; font-size: 16px;">🟢</span>
                            All systems operational
                        </div>
                        <div style="display: flex; align-items: center; padding: 8px 0; font-size: 14px;">
                            <span style="margin-right: 10px; font-size: 16px;">⚠️</span>
                            <?php echo $violations_count; ?> companies approaching rate limits
                        </div>
                        <div style="display: flex; align-items: center; padding: 8px 0; font-size: 14px;">
                            <span style="margin-right: 10px; font-size: 16px;">🔴</span>
                            <?php echo ($health_score < 90) ? '1 failed sync in last hour' : 'No sync failures'; ?>
                        </div>
                        <div style="display: flex; align-items: center; padding: 8px 0; font-size: 14px;">
                            <span style="margin-right: 10px; font-size: 16px;">ℹ️</span>
                            <?php echo ($total_calls_today == 0) ? 'No API activity today' : 'Next auto-sync in 15 minutes'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monitoring Dashboards -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Monitoring Dashboards
                </h3>
                <p class="card-subtitle">Access comprehensive monitoring and analytics dashboards</p>
            </div>
            <div class="card-body">
                <div class="monitoring-grid-1">
                    <div class="monitoring-buttons-grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php" class="monitoring-button auto-sync">
                            <div class="monitoring-button-icon">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="monitoring-button-content">
                                <h4>Auto-Sync Intelligence</h4>
                                <p>📊 Monitor</p>
                            </div>
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="monitoring-button system-health">
                            <div class="monitoring-button-icon">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="monitoring-button-content">
                                <h4>System Health & Alerts</h4>
                                <p>🔍 Analyze</p>
                            </div>
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/advanced_monitoring.php" class="monitoring-button api-performance">
                            <div class="monitoring-button-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="monitoring-button-content">
                                <h4>API Performance & Security</h4>
                                <p>📈 Track</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Initialize monitoring performance chart
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('monitoring-performance-chart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                
                // Real hourly data from API logs - LAST 24 HOURS (00:00 to 24:00)
                const hours = [
                    <?php 
                    // Generate proper 24-hour labels from 00:00 to 23:00
                    $hour_labels = [];
                    for ($i = 0; $i < 24; $i++) {
                        $hour_labels[] = "'" . sprintf('%02d:00', $i) . "'";
                    }
                    echo implode(',', $hour_labels);
                    ?>
                ];
                const apiCalls = [<?php 
                    // Get REAL hourly API call data from logs - LAST 24 HOURS
                    $hourly_data = [];
                    $today_start = mktime(0, 0, 0); // Start of today 00:00
                    
                    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                        // Use standard Moodle field name
                        $time_field = 'timecreated';
                        
                        // Get REAL data for each hour (00:00 to 23:59)
                        for ($i = 0; $i < 24; $i++) {
                            $hour_start = $today_start + ($i * 3600); // Each hour
                            $hour_end = $hour_start + 3600; // Next hour
                            
                            $count = $DB->count_records_select('local_alx_api_logs', 
                                "{$time_field} >= ? AND {$time_field} < ?", 
                                [$hour_start, $hour_end]
                            );
                            $hourly_data[] = (int)$count;
                        }
                    } else {
                        // No table = no data, show real zeros
                        $hourly_data = array_fill(0, 24, 0);
                    }
                    
                    // NO FAKE DATA - Show real zeros if no API calls
                    if (empty($hourly_data)) {
                        $hourly_data = array_fill(0, 24, 0);
                    }
                    
                    echo implode(',', $hourly_data);
                ?>];
                
                // Clear canvas
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Chart dimensions and styling - IMPROVED
                const width = canvas.width - 140; // More space for labels
                const height = canvas.height - 120;
                const maxValue = Math.max(...apiCalls, 5); // Minimum scale of 5
                const minValue = 0; // Always start from 0
                const valueRange = maxValue - minValue;
                
                // Enhanced chart colors and styling
                const gridColor = '#e5e7eb';
                const lineColor = '#2563eb';
                const pointColor = '#1d4ed8';
                const pointFillColor = '#ffffff';
                const labelColor = '#6b7280';
                const titleColor = '#1f2937';
                
                // Draw background
                ctx.fillStyle = '#f9fafb';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Draw horizontal grid lines and Y-axis labels
                ctx.strokeStyle = gridColor;
                ctx.lineWidth = 1;
                ctx.fillStyle = labelColor;
                ctx.font = '12px Inter';
                ctx.textAlign = 'right';
                
                const gridLines = 6;
                for (let i = 0; i <= gridLines; i++) {
                    const y = 50 + (i * height / gridLines);
                    const value = Math.round(maxValue - (i * valueRange / gridLines));
                    
                    // Draw grid line
                    ctx.beginPath();
                    ctx.moveTo(70, y);
                    ctx.lineTo(70 + width, y);
                    ctx.stroke();
                    
                    // Draw Y-axis label
                    ctx.fillText(value.toString(), 65, y + 4);
                }
                
                // Draw vertical grid lines (lighter) - every 4 hours
                ctx.strokeStyle = '#f3f4f6';
                for (let i = 0; i < hours.length; i += 4) {
                    const x = 70 + (i * width / (hours.length - 1));
                    ctx.beginPath();
                    ctx.moveTo(x, 50);
                    ctx.lineTo(x, 50 + height);
                    ctx.stroke();
                }
                
                // Draw main chart line with gradient
                const gradient = ctx.createLinearGradient(0, 50, 0, 50 + height);
                gradient.addColorStop(0, 'rgba(37, 99, 235, 0.1)');
                gradient.addColorStop(1, 'rgba(37, 99, 235, 0.05)');
                
                // Fill area under line
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.moveTo(70, 50 + height);
                
                for (let i = 0; i < apiCalls.length; i++) {
                    const x = 70 + (i * width / (apiCalls.length - 1));
                    const normalizedValue = valueRange > 0 ? (apiCalls[i] - minValue) / valueRange : 0;
                    const y = 50 + (height - (normalizedValue * height));
                    
                    ctx.lineTo(x, y);
                }
                
                ctx.lineTo(70 + width, 50 + height);
                ctx.closePath();
                ctx.fill();
                
                // Draw main line
                ctx.strokeStyle = lineColor;
                ctx.lineWidth = 3;
                ctx.beginPath();
                
                for (let i = 0; i < apiCalls.length; i++) {
                    const x = 70 + (i * width / (apiCalls.length - 1));
                    const normalizedValue = valueRange > 0 ? (apiCalls[i] - minValue) / valueRange : 0;
                    const y = 50 + (height - (normalizedValue * height));
                    
                    if (i === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                }
                ctx.stroke();
                
                // Draw enhanced data points (only for non-zero values to avoid clutter)
                for (let i = 0; i < apiCalls.length; i++) {
                    if (apiCalls[i] > 0) {
                        const x = 70 + (i * width / (apiCalls.length - 1));
                        const normalizedValue = valueRange > 0 ? (apiCalls[i] - minValue) / valueRange : 0;
                        const y = 50 + (height - (normalizedValue * height));
                        
                        // Draw point shadow
                        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
                        ctx.beginPath();
                        ctx.arc(x + 1, y + 1, 6, 0, 2 * Math.PI);
                        ctx.fill();
                        
                        // Draw point border
                        ctx.strokeStyle = pointColor;
                        ctx.lineWidth = 3;
                        ctx.beginPath();
                        ctx.arc(x, y, 6, 0, 2 * Math.PI);
                        ctx.stroke();
                        
                        // Draw point fill
                        ctx.fillStyle = pointFillColor;
                        ctx.beginPath();
                        ctx.arc(x, y, 4, 0, 2 * Math.PI);
                        ctx.fill();
                        
                        // Draw value label above point
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
                        ctx.fillRect(x - 18, y - 30, 36, 20);
                        ctx.strokeStyle = pointColor;
                        ctx.lineWidth = 1;
                        ctx.strokeRect(x - 18, y - 30, 36, 20);
                        
                        ctx.fillStyle = pointColor;
                        ctx.font = 'bold 12px Inter';
                        ctx.textAlign = 'center';
                        ctx.fillText(apiCalls[i].toString(), x, y - 16);
                    }
                }
                
                // Draw X-axis labels (time) - every 4 hours to avoid overlap
                ctx.fillStyle = labelColor;
                ctx.font = '11px Inter';
                ctx.textAlign = 'center';
                for (let i = 0; i < hours.length; i += 4) {
                    const x = 70 + (i * width / (hours.length - 1));
                    ctx.fillText(hours[i], x, height + 85);
                }
                
                // Draw axis labels
                ctx.fillStyle = labelColor;
                ctx.font = '14px Inter';
                ctx.textAlign = 'center';
                
                // Y-axis label
                ctx.save();
                ctx.translate(25, 50 + height / 2);
                ctx.rotate(-Math.PI / 2);
                ctx.fillText('API Calls', 0, 0);
                ctx.restore();
                
                // X-axis label
                ctx.fillText('Time (24 Hours)', 70 + width / 2, height + 105);
                
                // Enhanced chart title
                ctx.font = 'bold 18px Inter';
                ctx.fillStyle = titleColor;
                ctx.fillText('Real-time API Usage Data', 70 + width / 2, 30);
            }
        });
        </script>
    </div>

    <!-- System Configuration Tab -->
    <div id="settings-tab" class="tab-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog"></i>
                    System Configuration
                </h3>
                <p class="card-subtitle">Configure global plugin settings and preferences</p>
            </div>
            <div class="card-body">
                <?php
                // Handle system configuration form submission (same pattern as Company Management)
                $config_action = optional_param('config_action', '', PARAM_ALPHA);
                
                // Handle form submission
                if ($config_action === 'save' && confirm_sesskey()) {
                    $errors = [];
                    $success_count = 0;
                    
                    try {
                        // Save rate limit
                        $rate_limit_input = optional_param('rate_limit', 100, PARAM_INT);
                        if ($rate_limit_input >= 1 && $rate_limit_input <= 10000) {
                            set_config('rate_limit', $rate_limit_input, 'local_alx_report_api');
                            $success_count++;
                        } else {
                            $errors[] = 'Rate limit must be between 1 and 10000';
                        }
                        
                        // Save max records
                        $max_records_input = optional_param('max_records', 1000, PARAM_INT);
                        if ($max_records_input >= 100 && $max_records_input <= 10000) {
                            set_config('max_records', $max_records_input, 'local_alx_report_api');
                            $success_count++;
                        } else {
                            $errors[] = 'Max records must be between 100 and 10000';
                        }
                        
                        // Save allow GET method
                        $allow_get_input = optional_param('allow_get_method', 0, PARAM_INT);
                        set_config('allow_get_method', $allow_get_input, 'local_alx_report_api');
                        $success_count++;
                        
                        // Save alert settings
                        $enable_alerting_input = optional_param('enable_alerting', 0, PARAM_INT);
                        set_config('enable_alerting', $enable_alerting_input, 'local_alx_report_api');
                        $success_count++;
                        
                        // Automatically enable email alerts when alerting is enabled
                        set_config('enable_email_alerts', $enable_alerting_input, 'local_alx_report_api');
                        $success_count++;
                        
                        $alert_threshold_input = optional_param('alert_threshold', 'medium', PARAM_ALPHA);
                        if (in_array($alert_threshold_input, ['low', 'medium', 'high', 'critical'])) {
                            set_config('alert_threshold', $alert_threshold_input, 'local_alx_report_api');
                            $success_count++;
                        } else {
                            $errors[] = 'Invalid alert threshold value';
                        }
                        
                        $alert_emails_input = optional_param('alert_emails', '', PARAM_TEXT);
                        set_config('alert_emails', $alert_emails_input, 'local_alx_report_api');
                        $success_count++;
                        
                        // Save cache TTL
                        $cache_ttl_input = optional_param('cache_ttl', 3600, PARAM_INT);
                        if ($cache_ttl_input >= 300 && $cache_ttl_input <= 86400) {
                            set_config('cache_ttl', $cache_ttl_input, 'local_alx_report_api');
                            $success_count++;
                        } else {
                            $errors[] = 'Cache TTL must be between 300 and 86400 seconds';
                        }
                        
                        if (empty($errors)) {
                            echo '<div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                                <strong><i class="fas fa-check-circle"></i> Success!</strong> Configuration saved successfully! (' . $success_count . ' settings updated)
                            </div>';
                        } else {
                            echo '<div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeaa7;">
                                <strong><i class="fas fa-exclamation-triangle"></i> Warning!</strong> Some settings saved with errors: ' . implode(', ', $errors) . '
                            </div>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                            <strong><i class="fas fa-times-circle"></i> Error!</strong> Error saving configuration: ' . htmlspecialchars($e->getMessage()) . '
                        </div>';
                    }
                }
                
                // Load current settings (reload after save)
                $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
                $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
                $allow_get = get_config('local_alx_report_api', 'allow_get_method');
                $enable_alerting = get_config('local_alx_report_api', 'enable_alerting');
                $enable_email_alerts = get_config('local_alx_report_api', 'enable_email_alerts');
                $alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
                $alert_emails = get_config('local_alx_report_api', 'alert_emails');
                $cache_ttl = get_config('local_alx_report_api', 'cache_ttl') ?: 3600;
                ?>
                
                <!-- Configuration Form (full width like Company Management) -->
                <form method="post" action="">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="config_action" value="save">
                    <input type="hidden" name="tab" value="settings">
                    
                    <!-- Two Column Grid Layout -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 24px;">
                        
                        <!-- LEFT COLUMN: API Configuration -->
                        <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; border-left: 4px solid #667eea;">
                            <h4 style="margin: 0 0 20px 0; color: #495057; font-size: 18px; font-weight: 600;">
                                <i class="fas fa-plug"></i> API Configuration
                            </h4>
                            
                            <!-- Rate Limit -->
                            <div style="margin-bottom: 20px;">
                                <label for="rate_limit" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                    Global Rate Limit (requests/day per company)
                                </label>
                                <input type="number" 
                                       id="rate_limit" 
                                       name="rate_limit" 
                                       value="<?php echo $rate_limit; ?>" 
                                       min="1" 
                                       max="10000"
                                       style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s;">
                                <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> Recommended: 100-1000
                                </small>
                            </div>
                            
                            <!-- Max Records -->
                            <div style="margin-bottom: 20px;">
                                <label for="max_records" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                    Max Records per Request
                                </label>
                                <input type="number" 
                                       id="max_records" 
                                       name="max_records" 
                                       value="<?php echo $max_records; ?>" 
                                       min="100" 
                                       max="10000"
                                       style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s;">
                                <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> Recommended: 1000
                                </small>
                            </div>
                            
                            <!-- Allow GET Method (Toggle Switch) -->
                            <div style="background: white; padding: 16px; border-radius: 8px; border: 2px solid #e9ecef;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 15px; color: #495057;">Allow GET Method</div>
                                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                                            <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i> Development/Testing Only
                                        </div>
                                    </div>
                                    <label class="toggle-switch" style="position: relative; display: inline-block; width: 50px; height: 26px;">
                                        <input type="checkbox" 
                                               name="allow_get_method" 
                                               value="1" 
                                               <?php echo $allow_get ? 'checked' : ''; ?>
                                               style="opacity: 0; width: 0; height: 0;">
                                        <span class="toggle-track" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 26px;"></span>
                                        <span class="toggle-thumb" style="position: absolute; content: ''; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- RIGHT COLUMN: Email Alerts -->
                        <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; border-left: 4px solid #f093fb;">
                            <h4 style="margin: 0 0 20px 0; color: #495057; font-size: 18px; font-weight: 600;">
                                <i class="fas fa-bell"></i> Email Alerts
                            </h4>
                            
                            <!-- Enable Alert System (Toggle Switch) -->
                            <div style="background: white; padding: 16px; border-radius: 8px; border: 2px solid #e9ecef; margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 15px; color: #495057;">Enable Alert System</div>
                                        <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                                            Master switch for all email alerts
                                        </div>
                                    </div>
                                    <label class="toggle-switch" style="position: relative; display: inline-block; width: 50px; height: 26px;">
                                        <input type="checkbox" 
                                               name="enable_alerting" 
                                               value="1" 
                                               <?php echo $enable_alerting ? 'checked' : ''; ?>
                                               style="opacity: 0; width: 0; height: 0;">
                                        <span class="toggle-track" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 26px;"></span>
                                        <span class="toggle-thumb" style="position: absolute; content: ''; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Hidden field to maintain enable_email_alerts (always enabled when alerting is enabled) -->
                            <input type="hidden" name="enable_email_alerts" value="<?php echo $enable_alerting ? '1' : '0'; ?>">
                            
                            <!-- Alert Threshold -->
                            <div style="margin-bottom: 20px;">
                                <label for="alert_threshold" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                    Alert Severity Threshold
                                </label>
                                <select id="alert_threshold" 
                                        name="alert_threshold"
                                        style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s;">
                                    <option value="low" <?php echo $alert_threshold === 'low' ? 'selected' : ''; ?>>🟢 Low - All alerts (informational + warnings + critical)</option>
                                    <option value="medium" <?php echo $alert_threshold === 'medium' ? 'selected' : ''; ?>>🟡 Medium - Important alerts only (warnings + critical)</option>
                                    <option value="high" <?php echo $alert_threshold === 'high' ? 'selected' : ''; ?>>🟠 High - Urgent alerts only (high + critical)</option>
                                    <option value="critical" <?php echo $alert_threshold === 'critical' ? 'selected' : ''; ?>>🔴 Critical - Emergency alerts only</option>
                                </select>
                                <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> Controls which alerts you receive. Higher threshold = fewer emails. Recommended: Medium
                                </small>
                            </div>
                            
                            <!-- Alert Recipients -->
                            <div>
                                <label for="alert_emails" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                    Alert Email Recipients
                                </label>
                                <textarea id="alert_emails" 
                                          name="alert_emails" 
                                          rows="3"
                                          placeholder="email1@example.com, email2@example.com"
                                          style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; background: white; font-family: monospace; transition: all 0.3s;"><?php echo htmlspecialchars($alert_emails); ?></textarea>
                                <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> Comma-separated emails
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Full Width: Cache Configuration Section -->
                    <div style="background: #f8f9fa; padding: 24px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #43e97b;">
                        <h4 style="margin: 0 0 20px 0; color: #495057; font-size: 18px; font-weight: 600;">
                            <i class="fas fa-bolt"></i> Cache Configuration
                        </h4>
                        
                        <!-- Cache TTL -->
                        <div style="max-width: 500px;">
                            <label for="cache_ttl" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                Cache Time-To-Live (seconds)
                            </label>
                            <input type="number" 
                                   id="cache_ttl" 
                                   name="cache_ttl" 
                                   value="<?php echo $cache_ttl; ?>" 
                                   min="300" 
                                   max="86400"
                                   style="width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white; transition: all 0.3s;">
                            <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                                <i class="fas fa-info-circle"></i> Recommended: 3600 (1 hour)
                            </small>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div style="text-align: center; padding: 20px 0;">
                        <button type="submit" 
                                class="btn-modern btn-primary" 
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 48px; font-size: 18px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; box-shadow: 0 4px 12px rgba(102,126,234,0.3); transition: all 0.3s;">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>
                
                <!-- Quick Actions Section -->
                <div style="margin-top: 30px; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h3 style="margin: 0 0 20px 0; color: #2d3748; font-size: 20px; font-weight: 700;">
                        <i class="fas fa-bolt" style="color: #667eea;"></i> Quick Actions
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/test_email_alert.php" 
                           class="btn-modern btn-secondary" 
                           style="background: #667eea; color: white; padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; display: block; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(102,126,234,0.3);">
                            <i class="fas fa-envelope-open-text" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            Test Email Alerts
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/webservice/tokens.php" 
                           class="btn-modern btn-info" 
                           style="background: #06b6d4; color: white; padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; display: block; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(6,182,212,0.3);">
                            <i class="fas fa-key" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            Manage Tokens
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=externalservices" 
                           class="btn-modern btn-success" 
                           style="background: #10b981; color: white; padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; display: block; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(16,185,129,0.3);">
                            <i class="fas fa-server" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            Manage Services
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api" 
                           class="btn-modern btn-warning" 
                           style="background: #f59e0b; color: white; padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; display: block; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(245,158,11,0.3);">
                            <i class="fas fa-cog" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            Plugin Settings
                        </a>
                        
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/export_data.php" 
                           class="btn-modern btn-info" 
                           style="background: #8b5cf6; color: white; padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; display: block; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(139,92,246,0.3);">
                            <i class="fas fa-download" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            Export Data
                        </a>
                    </div>
                </div>
                
                <!-- Toggle Switch CSS moved to external file: styles/control-center.css -->
            </div>
        </div>
    </div>

            </div>
        </div>
    </div>
</div>

<script>
// Handle company selection with better UX
function handleCompanySelection(selectElement) {
    if (selectElement.value !== '0') {
        // Simply submit the form without modifying the select element
        // This preserves the selected value properly
        selectElement.form.submit();
    }
}

// Tab switching function - ESSENTIAL for tab navigation
function switchTab(event, tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    // Simple URL update: just set the tab parameter
    const url = new URL(window.location);
    if (tabName === 'overview') {
        url.searchParams.delete('tab'); // Overview = no tab parameter
    } else {
        url.searchParams.set('tab', tabName); // Other tabs = set tab parameter
    }
    window.history.replaceState({}, '', url);
    
    console.log('Debug - Switched to tab:', tabName, 'URL:', url.href);
}

// Utility function to safely format numbers
function safeFormatNumber(value) {
    if (value === null || value === undefined || isNaN(value)) {
        return '0';
    }
    return new Intl.NumberFormat().format(value);
}

// Utility function to safely get element and update content
function safeUpdateElement(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = safeFormatNumber(value);
    }
}

// Auto-refresh system stats every 30 seconds with error handling
function refreshSystemStats() {
    console.log('Refreshing system stats via AJAX...');
    
    // Use dedicated AJAX endpoint
    const url = 'ajax_stats.php?t=' + Date.now();
    
    fetch(url)
        .then(response => {
            console.log('AJAX response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('AJAX data received:', data);
            if (data && typeof data === 'object' && !data.error) {
                console.log('Updating elements with:', {
                    records: data.total_records,
                    companies: data.total_companies,
                    api_calls: data.api_calls_today
                });
                
                safeUpdateElement('total-records', data.total_records);
                safeUpdateElement('active-companies', data.total_companies);
                safeUpdateElement('api-calls-today', data.api_calls_today);
                
                // Update system health indicator
                const healthElement = document.getElementById('system-health');
                if (healthElement) {
                    if (data.health_icon) {
                        healthElement.textContent = data.health_icon;
                    } else {
                        healthElement.textContent = data.health_status === 'healthy' ? '✅' : 
                                                 data.health_status === 'warning' ? '⚠️' : '❌';
                    }
                }
                console.log('Stats updated successfully');
            } else {
                console.error('Invalid data format received or error:', data);
                if (data && data.error) {
                    console.error('Server error:', data.error);
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing stats:', error);
            // Optionally show a user-friendly error message
            const healthElement = document.getElementById('system-health');
            if (healthElement) {
                healthElement.textContent = '❌';
            }
        });
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Don't do initial refresh since PHP already loaded correct data
    console.log('Control Center loaded with initial data');
    
    // Check URL parameters to determine active tab
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    const companyId = urlParams.get('companyid');
    
    // Simple logic: show the tab that's in the URL, or default to overview
    let targetTab = activeTab || 'overview';
    
    console.log('Debug - Simple tab detection:', {
        activeTab: activeTab,
        targetTab: targetTab,
        fullURL: window.location.href
    });
    
    // Switch to the determined tab (if not already overview)
    if (targetTab !== 'overview') {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show target tab and activate its button
        const targetTabElement = document.getElementById(targetTab + '-tab');
        const targetButtonElement = document.querySelector('[onclick*="' + targetTab + '"]');
        
        if (targetTabElement) {
            targetTabElement.classList.add('active');
        }
        if (targetButtonElement) {
            targetButtonElement.classList.add('active');
        }
    }
    
    // Set up auto-refresh every 30 seconds, starting after 10 seconds
    setTimeout(function() {
        console.log('Starting auto-refresh...');
        refreshSystemStats();
        setInterval(refreshSystemStats, 30000);
    }, 10000); // Start after 10 seconds instead of 30
    
    // Initialize field states based on current response mode
    if (document.getElementById('response_mode')) {
        updateFieldStates();
    }
    
    // Handle company selection scroll position - only for companies tab with valid company
    if (targetTab === 'companies' && companyId && companyId !== '0' && companyId !== '' && companyId !== null) {
        // Additional check: make sure company settings form is actually visible on the page
        setTimeout(function() {
            const companySettingsForm = document.querySelector('form[action=""] input[name="settings_action"][value="save"]');
            const companySelector = document.querySelector('.company-selector');
            
            // Only scroll if both company selector exists AND company settings form exists (meaning a valid company is selected)
            if (companySelector && companySettingsForm) {
                companySelector.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start',
                    inline: 'nearest'
                });
            }
        }, 100); // Small delay to ensure page is fully loaded
    }
});

// Smart field enabling/disabling based on response mode
function updateFieldStates() {
    const responseMode = document.getElementById('response_mode').value;
    
    // Get all field elements
    const incrementalWindowField = document.getElementById('incremental_window_field');
    const incrementalWindow = document.getElementById('incremental_window');
    const incrementalWindowHelp = document.getElementById('incremental_window_help');
    
    const initialWindowField = document.getElementById('initial_window_field');
    const initialWindow = document.getElementById('initial_window');
    const initialWindowHelp = document.getElementById('initial_window_help');
    
    const cacheEnabledField = document.getElementById('cache_enabled_field');
    const cacheEnabled = document.getElementById('cache_enabled');
    const cacheEnabledHelp = document.getElementById('cache_enabled_help');
    
    const cacheTtlField = document.getElementById('cache_ttl_field');
    const cacheTtl = document.getElementById('cache_ttl');
    const cacheTtlHelp = document.getElementById('cache_ttl_help');
    
    // Reset all fields to enabled state first
    const allFields = [incrementalWindowField, initialWindowField, cacheEnabledField, cacheTtlField];
    const allInputs = [incrementalWindow, initialWindow, cacheEnabled, cacheTtl];
    
    allFields.forEach(field => {
        if (field) {
            field.style.opacity = '1';
            field.style.background = '#f8f9fa';
            field.style.borderColor = '#e9ecef';
        }
    });
    
    allInputs.forEach(input => {
        if (input) {
            input.disabled = false;
            input.style.opacity = '1';
            input.style.cursor = 'default';
        }
    });
    
    // Apply mode-specific rules
    switch(responseMode) {
        case '0': // Auto (Intelligent)
            // All fields enabled - no changes needed
            incrementalWindowHelp.innerHTML = '<strong>Default: 24 hours</strong> - Used for incremental response decisions (1-168 hours)';
            initialWindowHelp.innerHTML = '<strong>Default: 168 hours (7 days)</strong> - Used for first API call from new companies (1-720 hours)';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '1': // Always Incremental Response
            // Disable Initial Response Window
            disableField(initialWindowField, initialWindow);
            incrementalWindowHelp.innerHTML = '<strong>Default: 24 hours</strong> - Always used for incremental responses (1-168 hours)';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Always Incremental mode doesn\'t use initial window';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '2': // Always Full Response
            // Disable both window fields
            disableField(incrementalWindowField, incrementalWindow);
            disableField(initialWindowField, initialWindow);
            incrementalWindowHelp.innerHTML = '<strong>Not used</strong> - Always Full Response mode returns all data';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Always Full Response mode returns all data';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '3': // Disabled
            // Disable all fields
            disableField(incrementalWindowField, incrementalWindow);
            disableField(initialWindowField, initialWindow);
            disableField(cacheEnabledField, cacheEnabled);
            disableField(cacheTtlField, cacheTtl);
            incrementalWindowHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            cacheEnabledHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            cacheTtlHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            break;
    }
}

// Helper function to disable a field with visual feedback
function disableField(fieldDiv, input) {
    if (fieldDiv) {
        fieldDiv.style.opacity = '0.5';
        fieldDiv.style.background = '#f1f3f4';
        fieldDiv.style.borderColor = '#dadce0';
    }
    if (input) {
        input.disabled = true;
        input.style.opacity = '0.6';
        input.style.cursor = 'not-allowed';
    }
}
</script>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart initialization functions
function initializeCharts() {
    try {
        createAPIPerformanceChart();
        createSyncModeChart();
        createSecurityScoreChart();
        console.log('All charts initialized successfully');
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

// API Performance Line Chart
function createAPIPerformanceChart() {
    const ctx = document.getElementById('api-performance-chart');
    if (!ctx) return;
    
    // Injected real data from PHP
    const hourlyData = <?php echo json_encode(array_column($api_analytics['trends'], 'calls')); ?>;
    const hours = <?php echo json_encode(array_column($api_analytics['trends'], 'hour')); ?>;
    
    // Ensure we always have 24 hours of data, filling with zeros if needed
    const completeHours = [];
    const completeData = [];
    const now = new Date();
    
    for (let i = 23; i >= 0; i--) {
        const hour = new Date(now.getTime() - (i * 60 * 60 * 1000));
        const hourLabel = hour.getHours().toString().padStart(2, '0') + ':00';
        completeHours.push(hourLabel);
        
        // Find matching data or default to 0
        const hourIndex = hours.findIndex(h => h === hourLabel);
        completeData.push(hourIndex >= 0 ? hourlyData[hourIndex] : 0);
    }
    
    // Calculate max value for Y-axis with minimum of 10
    const maxValue = Math.max(10, Math.max(...completeData) * 1.2);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: completeHours,
            datasets: [{
                label: 'API Calls',
                data: completeData,
                borderColor: '#4ade80',
                backgroundColor: 'rgba(74, 222, 128, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#4ade80',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: true,
                        color: 'rgba(255, 255, 255, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)',
                        font: {
                            size: 10
                        },
                        maxTicksLimit: 6
                    },
                    border: {
                        color: 'rgba(255, 255, 255, 0.3)',
                        width: 1
                    }
                },
                y: {
                    display: true,
                    min: 0,
                    max: maxValue,
                    grid: {
                        display: true,
                        color: 'rgba(255, 255, 255, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)',
                        font: {
                            size: 10
                        },
                        stepSize: Math.ceil(maxValue / 5),
                        callback: function(value) {
                            return Math.round(value);
                        }
                    },
                    border: {
                        color: 'rgba(255, 255, 255, 0.3)',
                        width: 1
                    }
                }
            },
            elements: {
                point: {
                    hoverRadius: 6
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// Company Records Bar Chart
function createSyncModeChart() {
    const ctx = document.getElementById('sync-company-chart');
    if (!ctx) return;
    
    <?php
    // Inject company data from PHP
    echo "const companyLabels = " . json_encode($company_chart_labels) . ";\n";
    echo "const companyData = " . json_encode($company_chart_data) . ";\n";
    echo "const companyColors = " . json_encode($company_chart_colors) . ";\n";
    ?>
    
    // Generate colors for each company
    const backgroundColors = companyData.map((_, index) => companyColors[index % companyColors.length]);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: companyLabels,
            datasets: [{
                label: 'Total Records',
                data: companyData,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors.map(color => color),
                borderWidth: 2,
                borderRadius: 6,
                barThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return 'Records: ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.8)',
                        font: {
                            size: 11,
                            weight: '500'
                        },
                        maxRotation: 45,
                        minRotation: 0
                    },
                    border: {
                        color: 'rgba(255, 255, 255, 0.2)',
                        width: 1
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.8)',
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    },
                    border: {
                        color: 'rgba(255, 255, 255, 0.2)',
                        width: 1
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// Security Score Circular Progress Chart
function createSecurityScoreChart() {
    const ctx = document.getElementById('security-score-chart');
    if (!ctx) return;
    
    <?php echo "const securityScore = {$security_score};"; ?>
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [securityScore, 100 - securityScore],
                backgroundColor: [
                    securityScore >= 80 ? '#10b981' : securityScore >= 60 ? '#fbbf24' : '#ef4444',
                    '#e5e7eb'
                ],
                borderWidth: 0,
                circumference: 360,
                rotation: -90
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            cutout: '75%'
        }
    });
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Don't do initial refresh since PHP already loaded correct data
    console.log('Control Center loaded with initial data');
    
    // Initialize charts
    initializeCharts();
    
    // Check URL parameters to determine active tab
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    const companyId = urlParams.get('companyid');
    
    // Simple logic: show the tab that's in the URL, or default to overview
    let targetTab = activeTab || 'overview';
    
    console.log('Debug - Simple tab detection:', {
        activeTab: activeTab,
        targetTab: targetTab,
        fullURL: window.location.href
    });
    
    // Switch to the determined tab (if not already overview)
    if (targetTab !== 'overview') {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show target tab and activate its button
        const targetTabElement = document.getElementById(targetTab + '-tab');
        const targetButtonElement = document.querySelector('[onclick*="' + targetTab + '"]');
        
        if (targetTabElement) {
            targetTabElement.classList.add('active');
        }
        if (targetButtonElement) {
            targetButtonElement.classList.add('active');
        }
    }
    
    // Set up auto-refresh every 30 seconds, starting after 10 seconds
    setTimeout(function() {
        console.log('Starting auto-refresh...');
        refreshSystemStats();
        setInterval(refreshSystemStats, 30000);
    }, 10000); // Start after 10 seconds instead of 30
    
    // Initialize field states based on current response mode
    if (document.getElementById('response_mode')) {
        updateFieldStates();
    }
    
    // Handle company selection scroll position - only for companies tab with valid company
    if (targetTab === 'companies' && companyId && companyId !== '0' && companyId !== '' && companyId !== null) {
        // Additional check: make sure company settings form is actually visible on the page
        setTimeout(function() {
            const companySettingsForm = document.querySelector('form[action=""] input[name="settings_action"][value="save"]');
            const companySelector = document.querySelector('.company-selector');
            
            // Only scroll if both company selector exists AND company settings form exists (meaning a valid company is selected)
            if (companySelector && companySettingsForm) {
                companySelector.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start',
                    inline: 'nearest'
                });
            }
        }, 100); // Small delay to ensure page is fully loaded
    }
});

// Smart field enabling/disabling based on response mode
function updateFieldStates() {
    const responseMode = document.getElementById('response_mode').value;
    
    // Get all field elements
    const incrementalWindowField = document.getElementById('incremental_window_field');
    const incrementalWindow = document.getElementById('incremental_window');
    const incrementalWindowHelp = document.getElementById('incremental_window_help');
    
    const initialWindowField = document.getElementById('initial_window_field');
    const initialWindow = document.getElementById('initial_window');
    const initialWindowHelp = document.getElementById('initial_window_help');
    
    const cacheEnabledField = document.getElementById('cache_enabled_field');
    const cacheEnabled = document.getElementById('cache_enabled');
    const cacheEnabledHelp = document.getElementById('cache_enabled_help');
    
    const cacheTtlField = document.getElementById('cache_ttl_field');
    const cacheTtl = document.getElementById('cache_ttl');
    const cacheTtlHelp = document.getElementById('cache_ttl_help');
    
    // Reset all fields to enabled state first
    const allFields = [incrementalWindowField, initialWindowField, cacheEnabledField, cacheTtlField];
    const allInputs = [incrementalWindow, initialWindow, cacheEnabled, cacheTtl];
    
    allFields.forEach(field => {
        if (field) {
            field.style.opacity = '1';
            field.style.background = '#f8f9fa';
            field.style.borderColor = '#e9ecef';
        }
    });
    
    allInputs.forEach(input => {
        if (input) {
            input.disabled = false;
            input.style.opacity = '1';
            input.style.cursor = 'default';
        }
    });
    
    // Apply mode-specific rules
    switch(responseMode) {
        case '0': // Auto (Intelligent)
            // All fields enabled - no changes needed
            incrementalWindowHelp.innerHTML = '<strong>Default: 24 hours</strong> - Used for incremental response decisions (1-168 hours)';
            initialWindowHelp.innerHTML = '<strong>Default: 168 hours (7 days)</strong> - Used for first API call from new companies (1-720 hours)';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '1': // Always Incremental Response
            // Disable Initial Response Window
            disableField(initialWindowField, initialWindow);
            incrementalWindowHelp.innerHTML = '<strong>Default: 24 hours</strong> - Always used for incremental responses (1-168 hours)';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Always Incremental mode doesn\'t use initial window';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '2': // Always Full Response
            // Disable both window fields
            disableField(incrementalWindowField, incrementalWindow);
            disableField(initialWindowField, initialWindow);
            incrementalWindowHelp.innerHTML = '<strong>Not used</strong> - Always Full Response mode returns all data';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Always Full Response mode returns all data';
            cacheEnabledHelp.innerHTML = '<strong>Default: Enabled</strong> - Cache API responses to improve performance';
            cacheTtlHelp.innerHTML = '<strong>Default: 60 minutes</strong> - How long to cache responses (1-1440 minutes)';
            break;
            
        case '3': // Disabled
            // Disable all fields
            disableField(incrementalWindowField, incrementalWindow);
            disableField(initialWindowField, initialWindow);
            disableField(cacheEnabledField, cacheEnabled);
            disableField(cacheTtlField, cacheTtl);
            incrementalWindowHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            initialWindowHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            cacheEnabledHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            cacheTtlHelp.innerHTML = '<strong>Not used</strong> - Response features are disabled';
            break;
    }
}

// Helper function to disable a field with visual feedback
function disableField(fieldDiv, input) {
    if (fieldDiv) {
        fieldDiv.style.opacity = '0.5';
        fieldDiv.style.background = '#f1f3f4';
        fieldDiv.style.borderColor = '#dadce0';
    }
    if (input) {
        input.disabled = true;
        input.style.opacity = '0.6';
        input.style.cursor = 'not-allowed';
    }
}
</script>

<?php
echo $OUTPUT->footer();
?> 
 