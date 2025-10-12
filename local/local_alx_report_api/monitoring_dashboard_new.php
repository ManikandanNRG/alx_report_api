<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Unified Monitoring Dashboard for ALX Report API plugin.
 * Combines Auto-Sync, Performance, and Security monitoring in one page with tabs.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Check permissions
admin_externalpage_setup('local_alx_report_api_monitoring');
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/monitoring_dashboard.php');
$PAGE->set_title('Monitoring Dashboard - ALX Report API');
$PAGE->set_heading('Monitoring Dashboard');

// Get active tab from URL parameter (default to performance since it's first)
$active_tab = optional_param('tab', 'performance', PARAM_ALPHA);

// Include modern fonts and icons
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

echo $OUTPUT->header();

// Get live data from database
global $DB;

// Get sync data
$sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
$last_sync = get_config('local_alx_report_api', 'last_auto_sync');
$last_stats = get_config('local_alx_report_api', 'last_sync_stats');
$sync_statistics = $last_stats ? json_decode($last_stats, true) : [];

// Get task info
$task_record = $DB->get_record('task_scheduled', ['classname' => '\local_alx_report_api\task\sync_reporting_data_task']);
$next_sync_time = $last_sync ? $last_sync + ($sync_hours * 3600) : null;

// Get companies
$companies = local_alx_report_api_get_companies();
$total_companies = count($companies);


// Get API performance data
$api_calls_today = 0;
$avg_response_time = 0;
$success_rate = 100;
$cache_hit_rate = 0;
$total_records = 0;

if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
    $today_start = mktime(0, 0, 0);
    // Use standard Moodle field name
    $time_field = 'timecreated';
    
    // Get table structure to check for optional fields
    $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
    
    $api_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    
    if (isset($table_info['response_time_ms'])) {
        $avg_result = $DB->get_record_sql("SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} WHERE {$time_field} >= ?", [$today_start]);
        $avg_response_time = $avg_result ? round($avg_result->avg_time / 1000, 2) : 0;
    }
    
    if (isset($table_info['error_message'])) {
        $success_count = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ? AND error_message IS NULL", [$today_start]);
        $success_rate = $api_calls_today > 0 ? round(($success_count / $api_calls_today) * 100, 1) : 100;
    }
}

if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_CACHE)) {
    $total_cache = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE);
    $active_cache = $DB->count_records_select(\local_alx_report_api\constants::TABLE_CACHE, 'expires_at > ?', [time()]);
    $cache_hit_rate = $total_cache > 0 ? round(($active_cache / $total_cache) * 100, 1) : 0;
}

if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
    $total_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['is_deleted' => 0]);
}

// Get security data - LIVE DATA with proper error handling
$active_tokens = 0;
$rate_limit_violations = 0;
$failed_auth = 0;
$today_start = mktime(0, 0, 0);

try {
    // Count ACTIVE tokens only (not expired)
    $active_tokens = $DB->count_records_select('external_tokens', 
        'validuntil IS NULL OR validuntil > ?', [time()]);
    
    // Calculate rate limit violations by checking company-specific limits
    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
        // Use standard Moodle field name
        $time_field = 'timecreated';
        
        // Get table structure to check for optional fields
        $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
        
        // Get all companies and check each one's usage against their specific limit
        $companies = local_alx_report_api_get_companies();
        
        foreach ($companies as $company) {
            // Get company-specific rate limit
            $company_settings = local_alx_report_api_get_company_settings($company->id);
            $company_rate_limit = isset($company_settings['rate_limit']) ? $company_settings['rate_limit'] : get_config('local_alx_report_api', 'rate_limit');
            
            if (empty($company_rate_limit)) {
                $company_rate_limit = 100; // Default fallback
            }
            
            // Count today's API calls for this company
            $company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                "{$time_field} >= ? AND company_shortname = ?",
                [$today_start, $company->shortname]
            );
            
            // Check if company exceeded their specific limit
            if ($company_calls_today > $company_rate_limit) {
                $rate_limit_violations++;
            }
        }
        
        // Also count failed authentication attempts from error_message
        if (isset($table_info['error_message'])) {
            $failed_auth = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                "{$time_field} >= ? AND error_message IS NOT NULL AND (
                    error_message LIKE ? OR 
                    error_message LIKE ? OR 
                    error_message LIKE ? OR 
                    error_message LIKE ? OR
                    error_message LIKE ?
                )",
                [$today_start, '%auth%', '%unauthorized%', '%forbidden%', '%authentication%', '%permission%']
            );
        }
    }
    
    // Also check alerts table as backup/supplement
    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_ALERTS)) {
        $alert_violations = $DB->count_records_select(\local_alx_report_api\constants::TABLE_ALERTS, 
            "(alert_type = ? OR alert_type = ?) AND timecreated >= ?", 
            ['rate_limit_exceeded', 'rate_limit', $today_start]);
        $rate_limit_violations = max($rate_limit_violations, $alert_violations);
        
        $alert_auth_failures = $DB->count_records_select(\local_alx_report_api\constants::TABLE_ALERTS, 
            "(alert_type = ? OR alert_type = ?) AND timecreated >= ?", 
            ['auth_failed', 'authentication_failed', $today_start]);
        $failed_auth = max($failed_auth, $alert_auth_failures);
    }
    
} catch (Exception $e) {
    error_log('Security data calculation error: ' . $e->getMessage());
    // Keep safe defaults (0 values already set)
}

?>

<link rel="stylesheet" href="<?php echo new moodle_url('/local/alx_report_api/styles/monitoring-dashboard-new.css'); ?>"

<div class="monitoring-container">
    <!-- Header -->
    <div class="monitoring-header">
        <div>
            <h1>📊 Monitoring Dashboard</h1>
            <p>Comprehensive monitoring for Auto-Sync, Performance, and Security</p>
        </div>
        <a href="control_center.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Control Center
        </a>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button <?php echo $active_tab === 'performance' ? 'active' : ''; ?>" onclick="switchTab('performance', event)">
            <i class="fas fa-tachometer-alt"></i> API Monitor
        </button>
        <button class="tab-button <?php echo $active_tab === 'autosync' ? 'active' : ''; ?>" onclick="switchTab('autosync', event)">
            <i class="fas fa-sync"></i> Data Sync Monitor
        </button>
        <button class="tab-button <?php echo $active_tab === 'security' ? 'active' : ''; ?>" onclick="switchTab('security', event)">
            <i class="fas fa-shield-alt"></i> Security Monitor
        </button>
    </div>

    <!-- AUTO-SYNC TAB -->
    <div id="autosync-tab" class="tab-content <?php echo $active_tab === 'autosync' ? 'active' : ''; ?>">
        <!-- Metric Cards -->
        <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="metric-card">
                <div class="metric-icon">🕐</div>
                <div class="metric-value"><?php echo $last_sync ? date('H:i', $last_sync) : 'Never'; ?></div>
                <div class="metric-label">Last Sync</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">⏰</div>
                <div class="metric-value"><?php echo $next_sync_time ? date('H:i', $next_sync_time) : 'N/A'; ?></div>
                <div class="metric-label">Next Sync</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">➕</div>
                <div class="metric-value"><?php 
                    // Get records created today
                    $records_created = 0;
                    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
                        $today_start = mktime(0, 0, 0);
                        $records_created = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING, 
                            'timecreated >= ?', [$today_start]);
                    }
                    echo number_format($records_created);
                ?></div>
                <div class="metric-label">Records Created</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🔄</div>
                <div class="metric-value"><?php 
                    // Get records updated today (where timemodified is different from timecreated)
                    $records_updated = 0;
                    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
                        $today_start = mktime(0, 0, 0);
                        // Count records where timemodified is today AND timemodified != timecreated
                        $records_updated = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING, 
                            'timemodified >= ? AND timemodified != timecreated', [$today_start]);
                    }
                    echo number_format($records_updated);
                ?></div>
                <div class="metric-label">Records Updated</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🏢</div>
                <div class="metric-value"><?php echo $total_companies; ?></div>
                <div class="metric-label">Total Companies</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">✅</div>
                <div class="metric-value"><?php echo $task_record && !$task_record->disabled ? 'Active' : 'Inactive'; ?></div>
                <div class="metric-label">Sync Status</div>
            </div>
        </div>

        <!-- Sync Trend Chart -->
        <div class="chart-container" style="margin-bottom: 30px;">
            <h3>📈 Sync Trends (Last 24 Hours)</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="syncTrendChart"></canvas>
            </div>
        </div>

        <!-- Company Sync Status Table -->
        <div class="monitoring-table">
            <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">Company Sync Status</h3>
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Total Records</th>
                        <th>Created Today</th>
                        <th>Updated Today</th>
                        <th>Last Sync</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($companies)) {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #718096;">No companies found</td></tr>';
                    } else {
                        foreach ($companies as $company): 
                            try {
                                // Get company-specific data with error handling
                                $company_records = 0;
                                $created_today = 0;
                                $updated_today = 0;
                                $company_last_sync = 'Never';
                                $sync_status = 'Unknown';
                                
                                if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
                                    $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_REPORTING);
                                    $today_start = mktime(0, 0, 0);
                                    
                                    // Check if required fields exist
                                    if (isset($table_info['companyid'])) {
                                        // Total records
                                        if (isset($table_info['is_deleted'])) {
                                            $company_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, 
                                                ['companyid' => $company->id, 'is_deleted' => 0]);
                                        } else {
                                            $company_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, 
                                                ['companyid' => $company->id]);
                                        }
                                        
                                        // Created today
                                        $created_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING, 
                                            'companyid = ? AND timecreated >= ?', [$company->id, $today_start]);
                                        
                                        // Updated today (where timemodified is different from timecreated)
                                        $updated_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING, 
                                            'companyid = ? AND timemodified >= ? AND timemodified != timecreated', 
                                            [$company->id, $today_start]);
                                        
                                        // Last sync time for this company - try multiple fields
                                        if (isset($table_info['last_updated'])) {
                                            $last_record = $DB->get_record_sql(
                                                "SELECT MAX(last_updated) as last_time FROM {local_alx_api_reporting} WHERE companyid = ?",
                                                [$company->id]
                                            );
                                            if ($last_record && $last_record->last_time) {
                                                $company_last_sync = date('H:i', $last_record->last_time);
                                            }
                                        } else {
                                            $last_record = $DB->get_record_sql(
                                                "SELECT MAX(timemodified) as last_time FROM {local_alx_api_reporting} WHERE companyid = ?",
                                                [$company->id]
                                            );
                                            if ($last_record && $last_record->last_time) {
                                                $company_last_sync = date('H:i', $last_record->last_time);
                                            }
                                        }
                                        
                                        // Determine sync status
                                        if ($created_today > 0 || $updated_today > 0) {
                                            $sync_status = 'Active';
                                        } elseif ($company_records > 0) {
                                            $sync_status = 'Synced';
                                        } else {
                                            $sync_status = 'No Data';
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                // If error, set defaults
                                $company_records = 0;
                                $created_today = 0;
                                $updated_today = 0;
                                $company_last_sync = 'Error';
                                $sync_status = 'Error';
                                error_log('Company sync data error for ' . $company->name . ': ' . $e->getMessage());
                            }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($company->name); ?></strong></td>
                        <td><?php echo number_format($company_records); ?></td>
                        <td><?php echo $created_today; ?></td>
                        <td><?php echo $updated_today; ?></td>
                        <td><?php echo $company_last_sync; ?></td>
                        <td><span class="badge badge-<?php 
                            echo $sync_status === 'Active' ? 'success' : 
                                 ($sync_status === 'Synced' ? 'info' : 
                                 ($sync_status === 'Error' ? 'danger' : 'warning')); 
                        ?>"><?php echo $sync_status; ?></span></td>
                    </tr>
                    <?php 
                        endforeach;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PERFORMANCE TAB -->
    <div id="performance-tab" class="tab-content <?php echo $active_tab === 'performance' ? 'active' : ''; ?>">
        <!-- Metric Cards -->
        <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <div class="metric-card">
                <div class="metric-icon">📞</div>
                <div class="metric-value"><?php echo number_format($api_calls_today); ?></div>
                <div class="metric-label">API Calls (24h)</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">⚡</div>
                <div class="metric-value"><?php echo $avg_response_time; ?>s</div>
                <div class="metric-label">Avg Response Time</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">✅</div>
                <div class="metric-value"><?php echo $success_rate; ?>%</div>
                <div class="metric-label">Success Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">💾</div>
                <div class="metric-value"><?php echo $cache_hit_rate; ?>%</div>
                <div class="metric-label">Cache Hit Rate</div>
            </div>
        </div>

        <!-- 24h API Request Flow Chart -->
        <div class="chart-container" style="margin-bottom: 30px;">
            <h3>📊 24h API Request Flow (3-Line Chart)</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Company Performance Table (Updated with all required columns) -->
        <div class="monitoring-table">
            <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">Company API Performance</h3>
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Response Mode</th>
                        <th>Max Req/Day</th>
                        <th>No of Req (Today)</th>
                        <th>Response Time</th>
                        <th>Data Source</th>
                        <th>Success Rate</th>
                        <th>Last Request</th>
                        <th>Total Request</th>
                        <th>Average Request</th>
                        <th>Error Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($companies)) {
                        echo '<tr><td colspan="11" style="text-align: center; padding: 40px; color: #718096;">No companies found</td></tr>';
                    } else {
                        foreach ($companies as $company):
                        try { 
                        // Get company performance data
                        $today_start = mktime(0, 0, 0);
                        $company_calls = 0;
                        $company_response_time = '0.0s';
                        $last_request_time = 'Never';
                        $error_count = 0;
                        
                        if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
                            // Use standard Moodle field name
                            $time_field = 'timecreated';
                            
                            // Get table structure to check for optional fields
                            $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
                            
                            // Get calls today for this company
                            $company_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
                                "{$time_field} >= ? AND company_shortname = ?", [$today_start, $company->shortname]);
                            
                            // Get average response time
                            if (isset($table_info['response_time_ms'])) {
                                $avg_result = $DB->get_record_sql(
                                    "SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} 
                                     WHERE {$time_field} >= ? AND company_shortname = ? AND response_time_ms > 0", 
                                    [$today_start, $company->shortname]
                                );
                                if ($avg_result && $avg_result->avg_time && $avg_result->avg_time > 0) {
                                    $company_response_time = round($avg_result->avg_time / 1000, 2) . 's';
                                } else {
                                    // Try to get any response time from all time
                                    $all_time_avg = $DB->get_record_sql(
                                        "SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} 
                                         WHERE company_shortname = ? AND response_time_ms > 0", 
                                        [$company->shortname]
                                    );
                                    $company_response_time = ($all_time_avg && $all_time_avg->avg_time > 0) ? 
                                        round($all_time_avg->avg_time / 1000, 2) . 's' : 'N/A';
                                }
                            }
                            
                            // Get last request time
                            $last_log = $DB->get_record_sql(
                                "SELECT MAX({$time_field}) as last_time FROM {local_alx_api_logs} WHERE company_shortname = ?",
                                [$company->shortname]
                            );
                            if ($last_log && $last_log->last_time) {
                                $minutes_ago = round((time() - $last_log->last_time) / 60);
                                $last_request_time = $minutes_ago . 'm ago';
                            }
                            
                            // Get error count
                            if (isset($table_info['error_message'])) {
                                $error_count = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                                    "{$time_field} >= ? AND company_shortname = ? AND error_message IS NOT NULL",
                                    [$today_start, $company->shortname]
                                );
                            }
                        }
                        
                        // Calculate success rate
                        $company_success_rate = $company_calls > 0 ? 
                            round((($company_calls - $error_count) / $company_calls) * 100, 1) . '%' : '100%';
                        
                        // Get response mode from company settings
                        $company_settings = local_alx_report_api_get_company_settings($company->id);
                        $sync_mode_value = isset($company_settings['sync_mode']) ? $company_settings['sync_mode'] : 0;
                        
                        // Map sync_mode values: 0=Auto, 1=Incremental, 2=Full, 3=Disabled
                        $response_mode_map = [
                            0 => 'auto',
                            1 => 'incremental',
                            2 => 'full',
                            3 => 'disabled'
                        ];
                        $response_mode = isset($response_mode_map[$sync_mode_value]) ? $response_mode_map[$sync_mode_value] : 'auto';
                        
                        // Get max requests per day from company settings (rate_limit)
                        $max_req_per_day = isset($company_settings['rate_limit']) ? $company_settings['rate_limit'] : get_config('local_alx_report_api', 'rate_limit');
                        if (empty($max_req_per_day)) {
                            $max_req_per_day = 1000; // Default fallback
                        }
                        
                        // Get total requests (all time) for this company
                        $total_requests = 0;
                        if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
                            $total_requests = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
                                'company_shortname = ?', [$company->shortname]);
                        }
                        
                        // Calculate average requests per day (last 30 days)
                        $avg_requests = 0;
                        if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
                            $thirty_days_ago = time() - (30 * 86400);
                            // Use standard Moodle field name
                            $time_field = 'timecreated';
                            $recent_requests = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
                                "{$time_field} >= ? AND company_shortname = ?", [$thirty_days_ago, $company->shortname]);
                            $avg_requests = round($recent_requests / 30);
                        }
                        
                        // Check data source (cache or direct)
                        $data_source = 'Direct';
                        if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_CACHE)) {
                            $cache_exists = $DB->record_exists_select(\local_alx_report_api\constants::TABLE_CACHE,
                                'companyid = ? AND expires_at > ?', [$company->id, time()]);
                            if ($cache_exists) {
                                $data_source = 'Cache';
                            }
                        }
                        
                        // Map response mode to badge color
                        $response_mode_badge_map = [
                            'auto' => 'info',           // Blue
                            'incremental' => 'warning', // Orange
                            'full' => 'success',        // Green
                            'disabled' => 'danger'      // Red
                        ];
                        $badge_class = isset($response_mode_badge_map[$response_mode]) ? 
                            $response_mode_badge_map[$response_mode] : 'info';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($company->name); ?></strong></td>
                        <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo strtoupper($response_mode); ?></span></td>
                        <td><?php echo number_format($max_req_per_day); ?></td>
                        <td><?php echo number_format($company_calls); ?></td>
                        <td><?php echo $company_response_time; ?></td>
                        <td><span class="badge badge-<?php echo $data_source === 'Cache' ? 'success' : 'default'; ?>"><?php echo $data_source; ?></span></td>
                        <td><?php echo $company_success_rate; ?></td>
                        <td><?php echo $last_request_time; ?></td>
                        <td><?php echo number_format($total_requests); ?></td>
                        <td><?php echo number_format($avg_requests); ?>/day</td>
                        <td>
                            <?php if ($error_count > 0): 
                                // Get actual error types for this company
                                $error_types = [];
                                if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
                                    // Use standard Moodle field name
                                    $time_field = 'timecreated';
                                    $errors = $DB->get_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                                        "{$time_field} >= ? AND company_shortname = ? AND error_message IS NOT NULL",
                                        [$today_start, $company->shortname],
                                        'timecreated DESC',
                                        'error_message',
                                        0,
                                        3 // Get last 3 errors
                                    );
                                    foreach ($errors as $error) {
                                        if (!empty($error->error_message)) {
                                            // Shorten error message if too long
                                            $msg = strlen($error->error_message) > 40 ? 
                                                substr($error->error_message, 0, 40) . '...' : 
                                                $error->error_message;
                                            $error_types[] = $msg;
                                        }
                                    }
                                }
                            ?>
                            <span class="error-eye" title="View error details">
                                👁️
                                <div class="error-tooltip">
                                    <strong><?php echo $error_count; ?> Error<?php echo $error_count > 1 ? 's' : ''; ?> Today</strong><br>
                                    <?php if (!empty($error_types)): ?>
                                        <small style="opacity: 0.9; display: block; margin-top: 4px;">
                                            <?php echo implode('<br>', array_slice($error_types, 0, 2)); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </span>
                            <?php else: ?>
                            <span style="color: #10b981; font-size: 18px;" title="No errors">✓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        } catch (Exception $e) {
                            // Show error row (11 columns total)
                            echo '<tr><td colspan="11" style="text-align: center; padding: 20px; color: #ef4444;">Error loading data for ' . htmlspecialchars($company->name) . '</td></tr>';
                            error_log('Company performance data error: ' . $e->getMessage());
                        }
                        endforeach;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECURITY TAB -->
    <div id="security-tab" class="tab-content <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
        <!-- Metric Cards -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon">🔑</div>
                <div class="metric-value"><?php echo $active_tokens; ?></div>
                <div class="metric-label">Active Tokens</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">⚠️</div>
                <div class="metric-value"><?php echo $rate_limit_violations; ?></div>
                <div class="metric-label">Rate Limit Violations</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🚫</div>
                <div class="metric-value"><?php echo $failed_auth; ?></div>
                <div class="metric-label">Failed Auth Attempts</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🛡️</div>
                <div class="metric-value"><?php echo $rate_limit_violations + $failed_auth === 0 ? 'Secure' : 'Alert'; ?></div>
                <div class="metric-label">Security Status</div>
            </div>
        </div>

        <!-- Alert System Configuration Status -->
        <div class="monitoring-table">
            <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">🔔 Alert System Configuration</h3>
            <table>
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
                    $email_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
                    $alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
                    $alert_emails = get_config('local_alx_report_api', 'alert_emails');
                    ?>
                    <tr>
                        <td><strong>Alert System</strong></td>
                        <td>
                            <span class="badge badge-<?php echo $alerting_enabled ? 'success' : 'danger'; ?>">
                                <?php echo $alerting_enabled ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td><?php echo $alerting_enabled ? 'System is monitoring and sending alerts' : 'Alerts are disabled - configure in settings'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email Alerts</strong></td>
                        <td>
                            <span class="badge badge-<?php echo $email_enabled ? 'success' : 'warning'; ?>">
                                <?php echo $email_enabled ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td><?php echo $alert_emails ? count(array_filter(array_map('trim', explode(',', $alert_emails)))) . ' recipients configured' : 'No recipients configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Alert Threshold</strong></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $alert_threshold === 'critical' ? 'danger' : 
                                     ($alert_threshold === 'high' ? 'warning' : 
                                     ($alert_threshold === 'medium' ? 'info' : 'default')); 
                            ?>">
                                <?php echo ucfirst($alert_threshold); ?>
                            </span>
                        </td>
                        <td>Only alerts at <?php echo $alert_threshold; ?> level or higher will be sent</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Alert Recipients -->
        <?php if ($alert_emails || $alerting_enabled): ?>
        <div class="monitoring-table">
            <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">📧 Alert Recipients</h3>
            <div style="padding: 20px;">
                <?php if ($alert_emails): ?>
                <div style="margin-bottom: 15px;">
                    <strong>Configured Email Recipients:</strong><br>
                    <div style="margin-top: 8px;">
                    <?php 
                    $emails = array_filter(array_map('trim', explode(',', $alert_emails)));
                    foreach ($emails as $email) {
                        echo "<span class='badge badge-info' style='margin: 2px; padding: 6px 12px;'>{$email}</span> ";
                    }
                    ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div>
                    <strong>Site Administrators (receive critical alerts):</strong><br>
                    <div style="margin-top: 8px;">
                    <?php 
                    $admins = get_admins();
                    foreach ($admins as $admin) {
                        echo "<span class='badge badge-success' style='margin: 2px; padding: 6px 12px;'>" . fullname($admin) . " ({$admin->email})</span> ";
                    }
                    ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Security Events & Alerts (Combined) -->
        <div class="monitoring-table">
            <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">🔒 Security Events & Alerts</h3>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event Type</th>
                        <th>Severity</th>
                        <th>User/IP</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get all security events (recent 20)
                    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_ALERTS)) {
                        $alerts = $DB->get_records(\local_alx_report_api\constants::TABLE_ALERTS, null, 'timecreated DESC', '*', 0, 20);
                        if (empty($alerts)) {
                            echo '<tr><td colspan="6" style="text-align: center; color: #10b981;">✅ No security events - All systems operating normally</td></tr>';
                        } else {
                            foreach ($alerts as $alert):
                                $severity_badge = $alert->severity === 'high' ? 'danger' : ($alert->severity === 'medium' ? 'warning' : 'info');
                                $status_badge = $alert->resolved ? 'success' : 'warning';
                                $status_text = $alert->resolved ? 'Resolved' : 'Active';
                    ?>
                    <tr>
                        <td><?php echo date('M d, H:i', $alert->timecreated); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $alert->alert_type)); ?></td>
                        <td><span class="badge badge-<?php echo $severity_badge; ?>">
                            <?php echo ucfirst($alert->severity); ?>
                        </span></td>
                        <td><?php echo htmlspecialchars($alert->hostname ?: 'N/A'); ?></td>
                        <td><?php 
                            // Simple color highlighting: username in blue, company in orange
                            $message = htmlspecialchars($alert->message);
                            $message = preg_replace('/User\s+(.+?)\s+from\s+(.+?)\s+exceeded/', 
                                'User <span style="color: #3b82f6; font-weight: 600;">$1</span> from <span style="color: #f59e0b; font-weight: 600;">$2</span> exceeded', 
                                $message);
                            echo $message;
                        ?></td>
                        <td><span class="badge badge-<?php echo $status_badge; ?>">
                            <?php echo $status_text; ?>
                        </span></td>
                    </tr>
                    <?php 
                            endforeach;
                        }
                    } else {
                        echo '<tr><td colspan="6" style="text-align: center; color: #718096;">Security alerts table not available</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Generate hourly performance data for charts - LIVE DATA (copied from advanced_monitoring.php)
$hourly_data = [];
$hourly_incoming = [];
$hourly_success = [];
$hourly_errors = [];

if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
    // Use standard Moodle field name
    $time_field = 'timecreated';
    
    for ($i = 23; $i >= 0; $i--) {
        // Create clean hourly timestamps (00:00, 01:00, 02:00, etc.)
        $current_hour = date('H') - $i;
        if ($current_hour < 0) {
            $current_hour += 24;
        }
        
        $hour_start = mktime($current_hour, 0, 0);
        $hour_end = $hour_start + 3600;
        
        // Get hourly request counts
        $hour_total = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
            "{$time_field} >= ? AND {$time_field} < ?", [$hour_start, $hour_end]);
        
        $hour_success = 0;
        $hour_errors = 0;
        
        if (isset($table_info['status'])) {
            $hour_success = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
                "{$time_field} >= ? AND {$time_field} < ? AND status = ?", 
                [$hour_start, $hour_end, 'success']);
            $hour_errors = $hour_total - $hour_success;
        } else {
            $hour_success = $hour_total;
            $hour_errors = 0;
        }
        
        $hourly_data[] = [
            'hour' => sprintf('%02d:00', $current_hour),
            'timestamp' => $hour_start,
            'incoming' => $hour_total,
            'success' => $hour_success,
            'errors' => $hour_errors
        ];
        
        $hourly_incoming[] = $hour_total;
        $hourly_success[] = $hour_success;
        $hourly_errors[] = $hour_errors;
    }
} else {
    // No API logs table - initialize empty data
    for ($i = 23; $i >= 0; $i--) {
        $current_hour = date('H') - $i;
        if ($current_hour < 0) {
            $current_hour += 24;
        }
        
        $hourly_data[] = [
            'hour' => sprintf('%02d:00', $current_hour),
            'timestamp' => mktime($current_hour, 0, 0),
            'incoming' => 0,
            'success' => 0,
            'errors' => 0
        ];
        
        $hourly_incoming[] = 0;
        $hourly_success[] = 0;
        $hourly_errors[] = 0;
    }
}
?>

<script>
// Tab switching function
function switchTab(tabName, event) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
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
    
    // Add active class to clicked button
    if (event) {
        event.target.closest('.tab-button').classList.add('active');
    }
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Sync Trend Chart (Two-Line: Created vs Updated)
    const syncCtx = document.getElementById('syncTrendChart');
    if (syncCtx) {
        new Chart(syncCtx, {
            type: 'line',
            data: {
                labels: <?php 
                    // Generate hourly labels for last 24 hours (not today's hours, but last 24h)
                    $hours = [];
                    for ($i = 23; $i >= 0; $i--) {
                        $current_hour = date('H') - $i;
                        if ($current_hour < 0) {
                            $current_hour += 24;
                        }
                        $hours[] = sprintf('%02d:00', $current_hour);
                    }
                    echo json_encode($hours);
                ?>,
                datasets: [
                    {
                        label: '➕ Records Created',
                        data: <?php 
                            // Get REAL hourly created data from database (LAST 24 HOURS, not just today)
                            $sync_created_data = [];
                            
                            if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
                                // Get data for last 24 hours (not just today)
                                for ($i = 23; $i >= 0; $i--) {
                                    $current_hour = date('H') - $i;
                                    if ($current_hour < 0) {
                                        $current_hour += 24;
                                    }
                                    
                                    $hour_start = mktime($current_hour, 0, 0);
                                    $hour_end = $hour_start + 3600;
                                    
                                    $count = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING,
                                        'timecreated >= ? AND timecreated < ?',
                                        [$hour_start, $hour_end]
                                    );
                                    $sync_created_data[] = $count;
                                }
                            } else {
                                // No table = no data
                                $sync_created_data = array_fill(0, 24, 0);
                            }
                            
                            echo json_encode($sync_created_data);
                        ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: '🔄 Records Updated',
                        data: <?php 
                            // Get REAL hourly updated data from database (LAST 24 HOURS)
                            $sync_updated_data = [];
                            
                            if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
                                // Get data for last 24 hours
                                for ($i = 23; $i >= 0; $i--) {
                                    $current_hour = date('H') - $i;
                                    if ($current_hour < 0) {
                                        $current_hour += 24;
                                    }
                                    
                                    $hour_start = mktime($current_hour, 0, 0);
                                    $hour_end = $hour_start + 3600;
                                    
                                    // Count records that were updated (not created) in this hour
                                    $count = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING,
                                        'timemodified >= ? AND timemodified < ? AND timecreated < ?',
                                        [$hour_start, $hour_end, $hour_start]
                                    );
                                    $sync_updated_data[] = $count;
                                }
                            } else {
                                // No table = no data
                                $sync_updated_data = array_fill(0, 24, 0);
                            }
                            
                            echo json_encode($sync_updated_data);
                        ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        suggestedMax: <?php 
                            // Calculate max value for Y-axis auto-scaling
                            $max_created = !empty($sync_created_data) ? max($sync_created_data) : 0;
                            $max_updated = !empty($sync_updated_data) ? max($sync_updated_data) : 0;
                            $max_value = max($max_created, $max_updated);
                            // Add 20% padding to max value for better visualization
                            echo $max_value > 0 ? ceil($max_value * 1.2) : 10;
                        ?>
                    }
                }
            }
        });
    }
    
    // 24h API Request Flow Chart (3-Line Chart) - Copied from advanced_monitoring.php
    const perfCtx = document.getElementById('performanceChart');
    if (perfCtx) {
        new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($hourly_data, 'hour')); ?>,
                datasets: [
                    {
                        label: '📥 Incoming Requests',
                        data: <?php echo json_encode($hourly_incoming); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4
                    },
                    {
                        label: '📤 Successful Responses',
                        data: <?php echo json_encode($hourly_success); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4
                    },
                    {
                        label: '❌ Error Responses',
                        data: <?php echo json_encode($hourly_errors); ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0,
                        title: {
                            display: true,
                            text: 'Number of Requests',
                            font: {
                                size: 12
                            }
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time (24h)',
                            font: {
                                size: 12
                            }
                        },
                        ticks: {
                            font: {
                                size: 10
                            },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                }
            }
        });
    }
});

// Send Test Security Alert Function
function sendTestSecurityAlert() {
    if (confirm('Send a test security alert to all configured recipients?')) {
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('action', 'send_test');
        formData.append('alert_type', 'security');
        formData.append('severity', 'medium');
        formData.append('sesskey', M.cfg.sesskey);
        
        // Send to test_alerts.php
        fetch('<?php echo $CFG->wwwroot; ?>/local/alx_report_api/test_alerts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert('✅ Test security alert sent successfully! Check your email inbox.');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        })
        .catch(error => {
            alert('❌ Error sending test alert. Please check your alert configuration.');
            console.error('Error:', error);
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
    }
}
</script>

<?php
echo $OUTPUT->footer();
?>
