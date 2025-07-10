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

// Get initial data with error handling
$companies = [];
$total_records = 0;
$total_companies = 0;
$api_calls_today = 0;
$system_health = '✅'; // Default to healthy

try {
    // Get companies using the same function as monitoring dashboard
    $companies = local_alx_report_api_get_companies();
    $total_companies = count($companies);
    
    // Get total records from reporting table (same as monitoring dashboard)
    if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $total_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]); // Only active records
    }
    
    // Get API calls today (check if table exists first)
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        $today_start = mktime(0, 0, 0);
        // Use timeaccessed (new field) or fall back to timecreated (old field)
        $table_info = $DB->get_columns('local_alx_api_logs');
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        $api_calls_today = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ?", [$today_start]);
    }
    
    // Determine system health based on actual conditions
    $health_issues = [];
    
    // Check if reporting table exists and has data
    if (!$DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $health_issues[] = 'Reporting table missing';
    } elseif ($total_records == 0) {
        $health_issues[] = 'No reporting data';
    }
    
    // Check if companies exist
    if ($total_companies == 0) {
        $health_issues[] = 'No companies configured';
    }
    
    // Check if web services are enabled
    if (empty($CFG->enablewebservices)) {
        $health_issues[] = 'Web services disabled';
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
    // Log error but continue with default values
    error_log('ALX Report API Control Center: ' . $e->getMessage());
    $system_health = '❌';
}

echo $OUTPUT->header();

// Include modern CSS and JavaScript
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

// Debug indicator for cache busting
echo '<div style="position: fixed; top: 0; right: 0; background: #10b981; color: white; padding: 4px 8px; font-size: 10px; z-index: 9999;">Enhanced v' . time() . '</div>';

?>

<style>
/* Add cache-busting comment to force refresh: <?php echo time(); ?> */
/* Modern Control Center Styling */
:root {
    --primary-color: #2563eb;
    --primary-dark: #1d4ed8;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --radius-sm: 0.375rem;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
}

* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.control-center-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    background: var(--light-bg);
    min-height: 100vh;
}

/* Fix bottom spacing */
#overview-tab {
    margin-bottom: 0 !important;
    padding-bottom: 20px;
}

.card-grid {
    margin-bottom: 0 !important;
}

/* Enhanced button hover effects */
.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
    opacity: 0.9;
}

/* Ensure proper card spacing and equal heights */
.dashboard-card {
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
}

/* Make card body flex to push footer to bottom */
.dashboard-card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Ensure card footer sticks to bottom */
.dashboard-card .card-footer {
    margin-top: auto;
}

/* Header visibility improvements */
.card-header {
    padding: 16px 20px 12px 20px;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
}

.card-subtitle {
    font-size: 14px;
    opacity: 0.8;
}

/* Body spacing */
.card-body {
    padding: 20px;
}

/* Footer improvements */
.card-footer {
    text-align: center;
}

/* Header Section */
.control-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 32px;
    border-radius: var(--radius-lg);
    margin-bottom: 32px;
    box-shadow: var(--shadow-lg);
}

.control-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 16px;
}

.control-header .subtitle {
    font-size: 1.125rem;
    opacity: 0.9;
    font-weight: 400;
    margin: 0;
}

.header-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.header-stat {
    padding: 20px;
    border-radius: var(--radius-md);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.header-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}

.header-stat:nth-child(1) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.header-stat:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.header-stat:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.header-stat:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}

.header-stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 2;
}

.header-stat-label {
    font-size: 0.875rem;
    margin: 8px 0 0 0;
    opacity: 0.9;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 2;
}

/* Add subtle pattern overlay */
.header-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
    z-index: 1;
}

/* Tab Navigation */
.tab-navigation {
    display: flex;
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 8px;
    margin-bottom: 32px;
    box-shadow: var(--shadow-sm);
    overflow-x: auto;
    gap: 4px;
}

.tab-button {
    flex: 1;
    min-width: 140px;
    padding: 16px 24px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-weight: 500;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.875rem;
}

.tab-button:hover {
    background: var(--light-bg);
    color: var(--text-primary);
}

.tab-button.active {
    background: var(--primary-color);
    color: white;
    box-shadow: var(--shadow-md);
}

.tab-button i {
    font-size: 1rem;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Card Layouts */
.card-grid {
    display: grid;
    gap: 24px;
    margin-bottom: 32px;
}

.card-grid-2 { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
.card-grid-3 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
.card-grid-4 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }

/* Special gradient cards for performance metrics */
.dashboard-card.gradient-card-1 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-1 .card-header {
    background: rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-1 .card-title,
.dashboard-card.gradient-card-1 .card-subtitle,
.dashboard-card.gradient-card-1 .card-body {
    color: white;
}

.dashboard-card.gradient-card-2 {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-2 .card-header {
    background: rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-2 .card-title,
.dashboard-card.gradient-card-2 .card-subtitle,
.dashboard-card.gradient-card-2 .card-body {
    color: white;
}

.dashboard-card.gradient-card-3 {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-3 .card-header {
    background: rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-card.gradient-card-3 .card-title,
.dashboard-card.gradient-card-3 .card-subtitle,
.dashboard-card.gradient-card-3 .card-body {
    color: white;
}

.card-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 4px 0 0 0;
}

.card-body {
    padding: 24px;
}

.card-footer {
    padding: 16px 24px;
    background: var(--light-bg);
    border-top: 1px solid var(--border-color);
}

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: var(--light-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-warning {
    background: var(--warning-color);
    color: white;
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn-info {
    background: var(--info-color);
    color: white;
}

/* Status Indicators */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.status-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
}

.status-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
}

/* Responsive Design */
@media (max-width: 768px) {
    .control-center-container {
        padding: 16px;
    }
    
    .control-header {
        padding: 24px;
    }
    
    .control-header h1 {
        font-size: 2rem;
    }
    
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .tab-navigation {
        flex-direction: column;
        gap: 8px;
    }
    
    .tab-button {
        min-width: auto;
    }
    
    .card-grid-2,
    .card-grid-3,
    .card-grid-4 {
        grid-template-columns: 1fr;
    }
}

.performance-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Alert Styles */
.alert {
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin: 16px 0;
    font-weight: 500;
    border: 1px solid transparent;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-color: #ffeaa7;
}

.alert h5 {
    margin: 0 0 8px 0;
    font-weight: 600;
}

.alert p {
    margin: 0;
    line-height: 1.5;
}

/* Form Styles */
.form-control {
    padding: 10px 15px;
    border: 2px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 15px;
    background: var(--card-bg);
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Checkbox Styles */
input[type="checkbox"] {
    accent-color: var(--primary-color);
    cursor: pointer;
}

/* Field Transition Effects */
.form-control, input[type="number"], select {
    transition: all 0.3s ease;
}

input[type="number"]:disabled, select:disabled {
    background-color: #f1f3f4 !important;
    color: #9aa0a6;
    cursor: not-allowed;
}

input[type="checkbox"]:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Disabled Field Styling */
.field-disabled {
    opacity: 0.5;
    background: #f1f3f4 !important;
    border-color: #dadce0 !important;
    transition: all 0.3s ease;
}

.field-disabled label {
    cursor: not-allowed;
}

/* Responsive Grid */
@media (max-width: 768px) {
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tab-navigation {
        flex-direction: column;
        gap: 8px;
    }
    
    .tab-button {
        text-align: center;
    }
}

/* Grid container for equal height cards */
.performance-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin: 20px 0;
    align-items: stretch;
}
</style>

<div class="control-center-container">
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
        </button>
        <button class="tab-button" onclick="switchTab(event, 'monitoring')">
            <i class="fas fa-chart-bar"></i>
            Monitoring & Analytics
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
                            API Performance
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
                                    // Use timeaccessed (new field) or fall back to timecreated (old field)
                                    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
                                    $avg_response = $DB->get_field_sql("
                                        SELECT AVG(response_time_ms) 
                                        FROM {local_alx_api_logs} 
                                        WHERE {$time_field} >= ? AND response_time_ms IS NOT NULL AND response_time_ms > 0
                                    ", [time() - 86400]);
                                    $response_time = $avg_response ? round($avg_response / 1000, 2) : 0;
                                }
                                
                                // Check if we have error tracking
                                if (isset($table_info['error_message'])) {
                                    // Use timeaccessed (new field) or fall back to timecreated (old field)
                                    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
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
                        
                        <?php if ($has_api_data): ?>
                        <!-- Response Time with Progress Bar -->
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

                        <!-- Success Rate with Progress Bar -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: white;">Success Rate</strong>
                                <span style="color: #4ade80; font-weight: 600;"><?php echo $success_rate; ?>%</span>
                            </div>
                            <div style="background: rgba(255,255,255,0.2); border-radius: 10px; height: 8px; overflow: hidden;">
                                <div style="background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%); width: <?php echo $success_rate; ?>%; height: 100%; border-radius: 10px; transition: width 0.3s ease;"></div>
                            </div>
                        </div>

                        <!-- Mini Chart Container -->
                        <div style="margin-bottom: 20px;">
                            <canvas id="api-performance-chart" width="300" height="100" style="max-width: 100%;"></canvas>
                        </div>

                        <!-- Performance Metrics Grid -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 48px;">
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
                        <!-- No Data State -->
                        <div style="text-align: center; padding: 40px 20px;">
                            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">📊</div>
                            <h4 style="color: white; margin-bottom: 8px;">No API Activity Yet</h4>
                            <p style="color: rgba(255,255,255,0.7); margin-bottom: 20px; font-size: 14px;">
                                Create API tokens and start making calls to see performance metrics here.
                            </p>
                            <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                                <div style="font-size: 14px; color: rgba(255,255,255,0.8); margin-bottom: 8px;">
                                    <strong>To get started:</strong>
                                </div>
                                <div style="font-size: 13px; color: rgba(255,255,255,0.7); text-align: left;">
                                    1. Configure web services<br>
                                    2. Create API tokens<br>
                                    3. Make your first API call
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 32px;">
                                <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                    <div style="font-size: 20px; font-weight: 700; color: #94a3b8;">0</div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Calls/Hour</div>
                                </div>
                                <div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                    <div style="font-size: 20px; font-weight: 700; color: #94a3b8;">0</div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.8);">Unique Users</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer" style="border-top: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1); padding: 16px; text-align: center;">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-modern" style="background: rgba(255,255,255,0.9); color: #667eea; border: 2px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-chart-line"></i>
                            View Full Analytics
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
                            $auto_mode_count = $DB->count_records('local_alx_api_sync_status', ['sync_mode' => 'auto']);
                            $incremental_mode_count = $DB->count_records('local_alx_api_sync_status', ['sync_mode' => 'always_incremental']);
                            // Fetch the most recent sync timestamp
                            $last_sync_timestamp = $DB->get_field_sql('SELECT MAX(last_sync_timestamp) FROM {local_alx_api_sync_status}');
                        } else {
                            $total_sync_entries = 0;
                            $recent_syncs = 0;
                            $auto_mode_count = 0;
                            $incremental_mode_count = 0;
                            $last_sync_timestamp = false;
                        }
                        // Calculate percentages for visual representation
                        $auto_percentage = $total_sync_entries > 0 ? ($auto_mode_count / $total_sync_entries) * 100 : 0;
                        $incremental_percentage = $total_sync_entries > 0 ? ($incremental_mode_count / $total_sync_entries) * 100 : 0;

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

                        <!-- Sync Mode Distribution Chart -->
                        <div style="margin-bottom: 20px;">
                            <canvas id="sync-mode-chart" width="200" height="200" style="max-width: 100%; margin: 0 auto; display: block;"></canvas>
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
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php" class="btn-modern" style="background: rgba(255,255,255,0.9); color: #f093fb; border: 2px solid rgba(255,255,255,0.3); padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-cog"></i>
                            Sync Settings
                        </a>
                    </div>
                </div>

                <!-- Enhanced Security Status Card -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #1f2937; border: none;">
                    <div class="card-header" style="border-bottom: 1px solid rgba(31,41,55,0.1); background: rgba(255,255,255,0.1);">
                        <h3 class="card-title" style="color: #1f2937; margin: 0;">
                            <i class="fas fa-shield-alt" style="color: #10b981;"></i>
                            Security Status
                        </h3>
                        <p class="card-subtitle" style="color: #6b7280; margin: 4px 0 0 0;">API security and access control</p>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get rate limiting monitoring data
                        $rate_monitoring = local_alx_report_api_get_rate_limit_monitoring();
                        $violations_today = count($rate_monitoring['violations']);
                        $users_today = count($rate_monitoring['usage_today']);
                        $alerts_count = count($rate_monitoring['alerts']);
                        $high_priority_alerts = array_filter($rate_monitoring['alerts'], function($alert) {
                            return $alert['severity'] === 'high';
                        });
                        
                        // Security score calculation
                        $security_score = 100;
                        if ($violations_today > 0) $security_score -= ($violations_today * 10);
                        if (count($high_priority_alerts) > 0) $security_score -= (count($high_priority_alerts) * 15);
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
                                <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Active</span>
                            </div>
                        </div>

                        <!-- Token Security -->
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #1f2937;">Token Security:</strong>
                                <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Secure</span>
                            </div>
                        </div>

                        <!-- Access Control -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #1f2937;">Access Control:</strong>
                                <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Enabled</span>
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
                        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/check_rate_limit.php" class="btn-modern" style="background: #10b981; color: white; border: 2px solid #10b981; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(16,185,129,0.3);">
                            <i class="fas fa-chart-line"></i>
                            Security Monitor
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card" style="margin-top: 20px; margin-bottom: 0;">
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
                            'sync_mode', 'sync_window_hours', 'first_sync_hours', 'cache_enabled', 'cache_ttl_minutes'
                        ];
                        
                        foreach ($sync_settings as $setting) {
                            if ($setting === 'sync_mode') {
                                $value = optional_param($setting, 0, PARAM_INT);
                                // Validate sync_mode values: 0=Auto, 1=Always Incremental, 2=Always Full, 3=Disabled
                                if (!in_array($value, [0, 1, 2, 3])) {
                                    $value = 0; // Default to Auto if invalid value
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
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/verify_reporting_data.php" class="btn-modern btn-success">
                        <i class="fas fa-check"></i>
                        Verify Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="monitoring-tab" class="tab-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    Monitoring & Analytics
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php" class="btn-modern btn-info">
                        <i class="fas fa-sync-alt"></i>
                        Auto-Sync Intelligence
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-modern btn-success">
                        <i class="fas fa-heartbeat"></i>
                        System Health & Alerts
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/advanced_monitoring.php" class="btn-modern btn-primary">
                        <i class="fas fa-chart-line"></i>
                        API Performance & Security
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="settings-tab" class="tab-content">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog"></i>
                    System Configuration
                </h3>
            </div>
            <div class="card-body">
                <p>System configuration will be integrated here...</p>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api" class="btn-modern btn-primary">
                    <i class="fas fa-external-link-alt"></i>
                    Open Plugin Settings
                </a>
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

// Sync Mode Distribution Doughnut Chart
function createSyncModeChart() {
    const ctx = document.getElementById('sync-mode-chart');
    if (!ctx) return;
    
    <?php
    // Get sync mode data for the chart
    $auto_count = $auto_mode_count ?? 0;
    $incremental_count = $incremental_mode_count ?? 0;
    $full_count = $total_sync_entries - $auto_count - $incremental_count;
    $full_count = max(0, $full_count);
    ?>
    
    const autoCount = <?php echo $auto_count; ?>;
    const incrementalCount = <?php echo $incremental_count; ?>;
    const fullCount = <?php echo $full_count; ?>;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Auto Mode', 'Incremental', 'Full Response'],
            datasets: [{
                data: [autoCount, incrementalCount, fullCount],
                backgroundColor: [
                    '#fbbf24',
                    '#60a5fa', 
                    '#f87171'
                ],
                borderWidth: 0,
                hoverBorderWidth: 2,
                hoverBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.8)',
                        font: {
                            size: 11
                        },
                        padding: 15,
                        usePointStyle: true
                    }
                }
            },
            cutout: '60%'
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
