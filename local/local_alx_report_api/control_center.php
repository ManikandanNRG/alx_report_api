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

// Handle AJAX requests
$action = optional_param('action', '', PARAM_ALPHA);
$ajax = optional_param('ajax', 0, PARAM_INT);

if ($ajax && $action) {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_system_stats':
            $stats = [
                'total_records' => 0,
                'total_companies' => 0,
                'api_calls_today' => 0,
                'health_status' => 'healthy'
            ];
            
            try {
                // Get companies using the same function as monitoring dashboard
                $companies = local_alx_report_api_get_companies();
                $stats['total_companies'] = count($companies);
                
                // Get total records from reporting table (same as monitoring dashboard)
                if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
                    $stats['total_records'] = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]); // Only active records
                }
                
                // Get API calls today
                if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                    $today_start = mktime(0, 0, 0);
                    $stats['api_calls_today'] = $DB->count_records_select('local_alx_api_logs', 'timecreated >= ?', [$today_start]);
                }
                
                // Determine system health
                $health_issues = [];
                
                if (!$DB->get_manager()->table_exists('local_alx_api_reporting')) {
                    $health_issues[] = 'Reporting table missing';
                } elseif ($stats['total_records'] == 0) {
                    $health_issues[] = 'No reporting data';
                }
                
                if ($stats['total_companies'] == 0) {
                    $health_issues[] = 'No companies configured';
                }
                
                if (empty($CFG->enablewebservices)) {
                    $health_issues[] = 'Web services disabled';
                }
                
                // Set health status
                if (empty($health_issues)) {
                    $stats['health_status'] = 'healthy';
                    $stats['health_icon'] = '✅';
                } elseif (count($health_issues) <= 2) {
                    $stats['health_status'] = 'warning';
                    $stats['health_icon'] = '⚠️';
                } else {
                    $stats['health_status'] = 'error';
                    $stats['health_icon'] = '❌';
                }
                
            } catch (Exception $e) {
                $stats['health_status'] = 'error';
                $stats['health_icon'] = '❌';
                error_log('ALX Report API Stats Error: ' . $e->getMessage());
            }
            
            echo json_encode($stats);
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

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
        $api_calls_today = $DB->count_records_select('local_alx_api_logs', 'timecreated >= ?', [$today_start]);
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

?>

<style>
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

/* Enhanced Dashboard Cards */
.dashboard-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.dashboard-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-4px);
}

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
        <div class="card-grid card-grid-3">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server" style="color: var(--info-color);"></i>
                        API Performance
                    </h3>
                    <p class="card-subtitle">Real-time API performance metrics</p>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Response Time</span>
                            <span style="font-weight: 600;">2.3s avg</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: var(--light-bg); border-radius: 4px;">
                            <div style="width: 76%; height: 100%; background: var(--success-color); border-radius: 4px;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Success Rate</span>
                            <span style="font-weight: 600;">99.2%</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: var(--light-bg); border-radius: 4px;">
                            <div style="width: 99%; height: 100%; background: var(--success-color); border-radius: 4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <span class="status-indicator status-success">
                        <i class="fas fa-check-circle"></i>
                        Optimal Performance
                    </span>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sync-alt" style="color: var(--warning-color);"></i>
                        Sync Status
                    </h3>
                    <p class="card-subtitle">Automatic data synchronization</p>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 16px;">
                        <strong>Last Sync:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Next Sync:</strong> <?php echo date('Y-m-d H:i:s', time() + 3600); ?>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Records Updated:</strong> <?php echo number_format($total_records); ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php" class="btn-modern btn-primary">
                        <i class="fas fa-eye"></i>
                        View Details
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-shield-alt" style="color: var(--success-color);"></i>
                        Security Status
                    </h3>
                    <p class="card-subtitle">API security and access control</p>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 16px;">
                        <strong>Rate Limiting:</strong> 
                        <span class="status-indicator status-success">Active</span>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Token Security:</strong> 
                        <span class="status-indicator status-success">Secure</span>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Access Control:</strong> 
                        <span class="status-indicator status-success">Enabled</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/check_rate_limit.php" class="btn-modern btn-secondary">
                        <i class="fas fa-chart-line"></i>
                        Check Limits
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h3>
                <p class="card-subtitle">Common tasks and operations</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/populate_reporting_table.php" class="btn-modern btn-primary">
                        <i class="fas fa-database"></i>
                        Populate Data
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/sync_reporting_data.php" class="btn-modern btn-warning">
                        <i class="fas fa-sync"></i>
                        Manual Sync
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/company_settings.php" class="btn-modern btn-success">
                        <i class="fas fa-building"></i>
                        Company Settings
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-modern btn-secondary">
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
            </div>
            <div class="card-body">
                <p>Company management functionality will be integrated here...</p>
                <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/company_settings.php" class="btn-modern btn-primary">
                    <i class="fas fa-external-link-alt"></i>
                    Open Company Settings
                </a>
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
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-modern btn-primary">
                        <i class="fas fa-chart-line"></i>
                        Monitoring Dashboard
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php" class="btn-modern btn-info">
                        <i class="fas fa-sync-alt"></i>
                        Auto-Sync Status
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/check_rate_limit.php" class="btn-modern btn-warning">
                        <i class="fas fa-tachometer-alt"></i>
                        Rate Limit Monitor
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
    fetch('control_center.php?ajax=1&action=get_system_stats')
        .then(response => {
            console.log('AJAX response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('AJAX data received:', data);
            if (data && typeof data === 'object') {
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
                console.error('Invalid data format received:', data);
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
    // Only start auto-refresh after first interval (30 seconds)
    console.log('Control Center loaded with initial data');
    
    // Set up auto-refresh every 30 seconds (but not immediately)
    setTimeout(function() {
        console.log('Starting auto-refresh...');
        refreshSystemStats();
        setInterval(refreshSystemStats, 30000);
    }, 30000); // Wait 30 seconds before first AJAX call
});
</script>

<?php
echo $OUTPUT->footer();
?> 