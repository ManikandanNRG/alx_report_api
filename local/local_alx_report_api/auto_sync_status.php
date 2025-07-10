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
 * Auto-sync status monitoring page for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/alx_report_api/lib.php');

// Check permissions.
admin_externalpage_setup('local_alx_report_api_auto_sync_status');
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/auto_sync_status.php');
$PAGE->set_title('Auto-Sync Intelligence - ALX Report API');
$PAGE->set_heading('Auto-Sync Intelligence Dashboard');

// Add consistent styling
$PAGE->requires->css('/local/alx_report_api/control_center_fix.css');

// Include modern font and icons
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

echo $OUTPUT->header();

// Get current configuration
$sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
$max_sync_time = get_config('local_alx_report_api', 'max_sync_time') ?: 300;
$last_sync = get_config('local_alx_report_api', 'last_auto_sync');
$last_stats = get_config('local_alx_report_api', 'last_sync_stats');

// Parse last sync statistics
$sync_statistics = [];
if ($last_stats) {
    $sync_statistics = json_decode($last_stats, true) ?: [];
}

// Get scheduled task info
global $DB;
$task_record = $DB->get_record('task_scheduled', ['classname' => '\local_alx_report_api\task\sync_reporting_data_task']);

// Get system health data
$companies = local_alx_report_api_get_companies();
$total_companies = count($companies);
$companies_with_api = 0;
foreach ($companies as $company) {
    if ($DB->record_exists('local_alx_api_settings', ['companyid' => $company->id])) {
        $companies_with_api++;
    }
}

// Get historical sync data (last 7 days)
$historical_data = [];
if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
    try {
        // Check what columns exist in the table
        $table_info = $DB->get_columns('local_alx_api_sync_status');
        $has_last_sync_status = isset($table_info['last_sync_status']);
        $has_last_sync_timestamp = isset($table_info['last_sync_timestamp']);
        
        if ($has_last_sync_timestamp) {
            $seven_days_ago = time() - (7 * 24 * 3600);
            
            // Get daily sync counts for the last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $day_start = time() - ($i * 24 * 3600);
                $day_end = $day_start + (24 * 3600);
                
                $daily_syncs = $DB->count_records_select('local_alx_api_sync_status', 
                    'last_sync_timestamp >= ? AND last_sync_timestamp < ?', 
                    [$day_start, $day_end]);
                
                $successful_syncs = 0;
                if ($has_last_sync_status) {
                    $successful_syncs = $DB->count_records_select('local_alx_api_sync_status', 
                        'last_sync_timestamp >= ? AND last_sync_timestamp < ? AND last_sync_status = ?', 
                        [$day_start, $day_end, 'success']);
                } else {
                    // If no status column, assume all syncs are successful
                    $successful_syncs = $daily_syncs;
                }
                
                $historical_data[] = [
                    'date' => date('M j', $day_start),
                    'total_syncs' => $daily_syncs,
                    'successful_syncs' => $successful_syncs,
                    'success_rate' => $daily_syncs > 0 ? round(($successful_syncs / $daily_syncs) * 100) : 0
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Auto-sync status query error: ' . $e->getMessage());
        // Initialize empty historical data on error
        for ($i = 6; $i >= 0; $i--) {
            $day_start = time() - ($i * 24 * 3600);
            $historical_data[] = [
                'date' => date('M j', $day_start),
                'total_syncs' => 0,
                'successful_syncs' => 0,
                'success_rate' => 0
            ];
        }
    }
}

// Check cron health
$last_cron = get_config('tool_task', 'lastcronstart');
$cron_healthy = false;
if ($last_cron) {
    $time_since_cron = time() - $last_cron;
    $cron_healthy = $time_since_cron < 3600;
}

?>

<style>
/* Import consistent design elements from control center */
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

/* Full page background coverage */
body {
    background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%) !important;
    margin: 0 !important;
    padding: 0 !important;
}

#page {
    background: transparent !important;
}

#page-content {
    background: transparent !important;
}

/* UNIQUE LAYOUT - Vertical Timeline Style */
.sync-monitor-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: transparent;
    min-height: 100vh;
}

/* Advanced Monitoring Style Sections */
.section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.section-header {
    background: var(--light-bg);
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 30px;
}

/* Metrics Grid from Advanced Monitoring */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.metric-label {
    color: #666;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-card.success .metric-value { color: var(--success-color); }
.metric-card.warning .metric-value { color: var(--warning-color); }
.metric-card.danger .metric-value { color: var(--danger-color); }

/* Trend Chart Container */
.trend-chart-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.trend-chart {
    display: flex;
    align-items: end;
    justify-content: space-between;
    height: 120px;
    margin: 20px 0;
    gap: 8px;
}

.trend-bar {
    flex: 1;
    background: linear-gradient(to top, var(--primary-color), var(--info-color));
    border-radius: 4px 4px 0 0;
    min-height: 20px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: end;
    transition: all 0.3s ease;
}

.trend-bar:hover {
    transform: scale(1.05);
    filter: brightness(1.1);
}

.trend-bar.success {
    background: linear-gradient(to top, var(--success-color), #34d399);
}

.trend-bar.warning {
    background: linear-gradient(to top, var(--warning-color), #fbbf24);
}

.trend-bar.danger {
    background: linear-gradient(to top, var(--danger-color), #f87171);
}

.trend-label {
    font-size: 0.75rem;
    color: #666;
    text-align: center;
    margin-top: 8px;
    font-weight: 500;
}

.trend-value {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.7rem;
    color: #333;
    font-weight: 600;
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.trend-bar:hover .trend-value {
    opacity: 1;
}

/* Breadcrumb Navigation */
.breadcrumb-nav {
    background: var(--card-bg);
    padding: 12px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 30px;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.breadcrumb-nav a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-nav a:hover {
    text-decoration: underline;
}

.breadcrumb-nav .separator {
    color: var(--text-secondary);
}

/* Page Title Section */
.page-title-section {
    text-align: center;
    margin-bottom: 40px;
}

.page-title {
    font-size: 3rem;
    font-weight: 300;
    color: var(--text-primary);
    margin: 0 0 15px 0;
    letter-spacing: -0.02em;
}

.page-subtitle {
    font-size: 1.2rem;
    color: var(--text-secondary);
    font-weight: 400;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Status Banner */
.status-banner {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 25px;
    margin-bottom: 40px;
    box-shadow: var(--shadow-md);
    border-left: 5px solid var(--primary-color);
}

.status-banner.healthy {
    border-left-color: var(--success-color);
    background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
}

.status-banner.warning {
    border-left-color: var(--warning-color);
    background: linear-gradient(135deg, #fffbeb, #fefce8);
}

.status-banner.error {
    border-left-color: var(--danger-color);
    background: linear-gradient(135deg, #fef2f2, #fef1f1);
}

.status-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-icon {
    font-size: 3rem;
    flex-shrink: 0;
}

.status-details h3 {
    margin: 0 0 10px 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.status-meta {
    color: var(--text-secondary);
    margin-bottom: 15px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: var(--radius-sm);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.status-label {
    font-weight: 500;
    color: var(--text-primary);
}

.status-value {
    font-weight: 600;
    color: var(--primary-color);
}

/* Timeline Section - Keep existing timeline styles here */
.timeline-section {
    margin-top: 40px;
}

.timeline-container {
    position: relative;
    padding-left: 40px;
}

.timeline-line {
    position: absolute;
    left: 18px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(to bottom, var(--primary-color), var(--info-color));
    border-radius: 2px;
}

.timeline-item {
    position: relative;
    margin-bottom: 40px;
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 25px;
    box-shadow: var(--shadow-md);
    border-left: 4px solid var(--primary-color);
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -29px;
    top: 25px;
    width: 20px;
    height: 20px;
    background: var(--primary-color);
    border: 4px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
}

.timeline-item.active::before {
    background: var(--success-color);
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
}

.timeline-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.timeline-icon {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.timeline-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-content {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Time Display Boxes */
.time-display-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.time-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: var(--radius-lg);
    text-align: center;
    box-shadow: var(--shadow-lg);
}

.time-box.success {
    background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
}

.time-box.warning {
    background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
}

.time-box.danger {
    background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
}

.time-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.time-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.time-meta {
    font-size: 0.8rem;
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sync-monitor-container {
        padding: 15px;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .status-content {
        flex-direction: column;
        text-align: center;
    }
    
    .metrics-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .timeline-container {
        padding-left: 30px;
    }
    
    .timeline-item::before {
        left: -24px;
    }
}

/* Navigation Links */
.nav-links {
    text-align: center;
    margin: 40px 0;
}

.nav-links a {
    display: inline-block;
    margin: 0 10px;
    padding: 12px 24px;
    background: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-links a:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* Info Section Styles */
.info-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 30px 0;
    padding: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.info-grid h3 {
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.process-step {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 20%);
    border-radius: 10px;
    border-left: 4px solid var(--primary-color);
    transition: all 0.3s ease;
}

.process-step:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.step-number {
    background: linear-gradient(135deg, var(--primary-color), var(--info-color));
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    margin-right: 15px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.step-number i {
    font-size: 16px;
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
    margin-bottom: 5px;
}

.step-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
}

/* Alert Styles */
.alert {
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #34d399;
    color: #065f46;
}

.alert-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    color: #92400e;
}

.alert i {
    font-size: 1.5rem;
    flex-shrink: 0;
}

/* Action Panel Styles */
.action-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 30px 0;
    padding: 30px;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-top: 25px;
}

.action-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 20%);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.action-icon {
    background: linear-gradient(135deg, var(--primary-color), var(--info-color));
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
}

.action-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.action-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 20px;
}

.action-card .btn-primary,
.action-card .btn-secondary,
.action-card .btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-card .btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
}

.action-card .btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark), #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
}

.action-card .btn-secondary {
    background: linear-gradient(135deg, var(--secondary-color), #475569);
    color: white;
}

.action-card .btn-secondary:hover {
    background: linear-gradient(135deg, #475569, #334155);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(100, 116, 139, 0.4);
}

.action-card .btn-outline {
    background: transparent;
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.action-card .btn-outline:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
}

/* Responsive Design for Info Sections */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .action-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .process-step {
        padding: 12px;
    }
    
    .step-number {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
    
    .action-card {
        padding: 20px;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
}
</style>

<div class="sync-monitor-container">
    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb-nav">
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php">
            <i class="fas fa-home"></i> Control Center
        </a>
        <span class="separator">›</span>
        <span><i class="fas fa-chart-bar"></i> Monitoring & Analytics</span>
        <span class="separator">›</span>
        <span><i class="fas fa-sync-alt"></i> Auto-Sync Intelligence</span>
    </div>

    <!-- Page Title -->
    <div class="page-title-section">
        <h1 class="page-title">🔄 Auto-Sync Intelligence</h1>
        <p class="page-subtitle">Monitor and analyze automated data synchronization across all company tenants</p>
    </div>

    <!-- Enhanced Sync Statistics Display -->
    <?php if (!empty($sync_statistics)): ?>
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-chart-line"></i>
                Last Sync Statistics
            </h2>
        </div>
        <div class="section-body">
            <div class="metrics-grid">
                <div class="metric-card success">
                    <div class="metric-value"><?php echo $sync_statistics['companies_processed'] ?? 0; ?></div>
                    <div class="metric-label">Companies Processed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $sync_statistics['total_users_updated'] ?? 0; ?></div>
                    <div class="metric-label">Users Updated</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $sync_statistics['total_records_updated'] ?? 0; ?></div>
                    <div class="metric-label">Records Updated</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $sync_statistics['total_records_created'] ?? 0; ?></div>
                    <div class="metric-label">Records Created</div>
                </div>
                <div class="metric-card <?php echo ($sync_statistics['total_errors'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                    <div class="metric-value"><?php echo $sync_statistics['total_errors'] ?? 0; ?></div>
                    <div class="metric-label">Errors</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $last_sync ? number_format(time() - $last_sync) . 's' : 'N/A'; ?></div>
                    <div class="metric-label">Time Ago</div>
                </div>
            </div>
            
            <?php if (!empty($sync_statistics['companies_with_errors'])): ?>
            <div class="trend-chart-container">
                <h4><i class="fas fa-exclamation-triangle"></i> Companies with Errors</h4>
                <p>Company IDs: <?php echo implode(', ', $sync_statistics['companies_with_errors']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Historical Sync Trends -->
    <?php if (!empty($historical_data)): ?>
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-chart-area"></i>
                Sync Trends (Last 7 Days)
            </h2>
        </div>
        <div class="section-body">
            <div class="trend-chart-container">
                <h4>Daily Sync Activity</h4>
                <div class="trend-chart">
                    <?php foreach ($historical_data as $day): ?>
                    <div class="trend-bar <?php 
                        echo $day['success_rate'] >= 90 ? 'success' : 
                             ($day['success_rate'] >= 70 ? 'warning' : 'danger'); 
                    ?>" style="height: <?php echo max(20, ($day['total_syncs'] / max(array_column($historical_data, 'total_syncs'))) * 100); ?>%;">
                        <div class="trend-value"><?php echo $day['total_syncs']; ?> syncs</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <?php foreach ($historical_data as $day): ?>
                    <div class="trend-label"><?php echo $day['date']; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="metrics-grid" style="margin-top: 20px;">
                <div class="metric-card">
                    <div class="metric-value"><?php echo array_sum(array_column($historical_data, 'total_syncs')); ?></div>
                    <div class="metric-label">Total Syncs (7 days)</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo array_sum(array_column($historical_data, 'successful_syncs')); ?></div>
                    <div class="metric-label">Successful Syncs</div>
                </div>
                <div class="metric-card success">
                    <div class="metric-value"><?php 
                        $total_syncs = array_sum(array_column($historical_data, 'total_syncs'));
                        $successful_syncs = array_sum(array_column($historical_data, 'successful_syncs'));
                        echo $total_syncs > 0 ? round(($successful_syncs / $total_syncs) * 100) . '%' : '0%';
                    ?></div>
                    <div class="metric-label">Overall Success Rate</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo round(array_sum(array_column($historical_data, 'total_syncs')) / 7, 1); ?></div>
                    <div class="metric-label">Avg Syncs/Day</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Current Status Banner -->
    <div class="status-banner <?php echo $cron_healthy ? 'healthy' : 'warning'; ?>">
        <div class="status-content">
            <div class="status-icon">
                <?php echo $cron_healthy ? '✅' : '⚠️'; ?>
            </div>
            <div class="status-details">
                <h3>Auto-Sync System Status</h3>
                <div class="status-meta">
                    <?php if ($cron_healthy): ?>
                        System is running normally and ready for automatic synchronization
                    <?php else: ?>
                        Warning: Cron system may need attention for optimal sync performance
                    <?php endif; ?>
                </div>
                <div class="status-grid">
                    <div class="status-item">
                        <span class="status-label">Total Companies</span>
                        <span class="status-value"><?php echo $total_companies; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">API Configured</span>
                        <span class="status-value"><?php echo $companies_with_api; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Sync Interval</span>
                        <span class="status-value"><?php echo $sync_hours; ?>h</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Max Sync Time</span>
                        <span class="status-value"><?php echo $max_sync_time; ?>s</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Display -->
    <div class="time-display-grid">
        <div class="time-box <?php echo $cron_healthy ? 'success' : 'warning'; ?>">
            <div class="time-label">Last Sync</div>
            <div class="time-value">
                <?php echo $last_sync ? date('H:i', $last_sync) : 'Never'; ?>
            </div>
            <div class="time-meta">
                <?php echo $last_sync ? date('M j, Y', $last_sync) : 'No sync recorded'; ?>
            </div>
        </div>
        
        <div class="time-box">
            <div class="time-label">Next Sync</div>
            <div class="time-value">
                <?php 
                if ($task_record && $last_sync) {
                    $next_sync = $last_sync + ($sync_hours * 3600);
                    echo date('H:i', $next_sync);
                } else {
                    echo 'Pending';
                }
                ?>
            </div>
            <div class="time-meta">
                <?php 
                if ($task_record && $last_sync) {
                    $next_sync = $last_sync + ($sync_hours * 3600);
                    echo date('M j, Y', $next_sync);
                } else {
                    echo 'Waiting for cron';
                }
                ?>
            </div>
        </div>
        
        <div class="time-box <?php echo $task_record && $task_record->disabled == 0 ? 'success' : 'danger'; ?>">
            <div class="time-label">Task Status</div>
            <div class="time-value">
                <?php echo $task_record && $task_record->disabled == 0 ? 'Active' : 'Disabled'; ?>
            </div>
            <div class="time-meta">
                <?php echo $task_record ? 'Task configured' : 'Task not found'; ?>
            </div>
        </div>
    </div>

    <!-- Timeline Section - Keep existing timeline content -->
    <div class="timeline-section">
        <div class="timeline-container">
            <div class="timeline-line"></div>
            
            <!-- Step 1: Scheduled Execution -->
            <div class="timeline-item active">
                <div class="timeline-header">
                    <div class="timeline-icon">⏰</div>
                    <h3 class="timeline-title">Scheduled Execution</h3>
                </div>
                <div class="timeline-content">
                    <p><strong>Automated trigger every <?php echo $sync_hours; ?> hour<?php echo $sync_hours > 1 ? 's' : ''; ?></strong></p>
                    <p>The system automatically initiates synchronization based on your configured schedule. No manual intervention required - the sync intelligence handles everything behind the scenes.</p>
                </div>
            </div>

            <!-- Step 2: Company Detection -->
            <div class="timeline-item">
                <div class="timeline-header">
                    <div class="timeline-icon">🏢</div>
                    <h3 class="timeline-title">Smart Company Detection</h3>
                </div>
                <div class="timeline-content">
                    <p><strong>Processing <?php echo $companies_with_api; ?> of <?php echo $total_companies; ?> companies with API configurations</strong></p>
                    <p>Intelligent filtering identifies only companies with active API settings, ensuring efficient resource utilization and avoiding unnecessary processing overhead.</p>
                </div>
            </div>

            <!-- Step 3: Change Detection -->
            <div class="timeline-item">
                <div class="timeline-header">
                    <div class="timeline-icon">🔍</div>
                    <h3 class="timeline-title">Incremental Change Detection</h3>
                </div>
                <div class="timeline-content">
                    <p><strong>Timestamp-based synchronization with <?php echo $sync_hours; ?>-hour lookback</strong></p>
                    <p>Advanced change detection analyzes course completions, module progress, and enrollment modifications since the last sync, ensuring only relevant updates are processed.</p>
                </div>
            </div>

            <!-- Step 4: Data Update -->
            <div class="timeline-item">
                <div class="timeline-header">
                    <div class="timeline-icon">🔄</div>
                    <h3 class="timeline-title">Intelligent Data Update</h3>
                </div>
                <div class="timeline-content">
                    <p><strong>Maximum <?php echo $max_sync_time; ?> seconds per sync operation</strong></p>
                    <p>Efficient bulk processing updates reporting records with comprehensive error handling and performance monitoring. Failed operations are logged for review.</p>
                </div>
            </div>

            <!-- Step 5: Cache Management -->
            <div class="timeline-item">
                <div class="timeline-header">
                    <div class="timeline-icon">🗄️</div>
                    <h3 class="timeline-title">Cache Optimization</h3>
                </div>
                <div class="timeline-content">
                    <p><strong>Automatic cache invalidation for updated companies</strong></p>
                    <p>Smart cache management ensures fresh data availability while maintaining optimal API response times. Cache entries are selectively cleared based on sync results.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- How Auto-Sync Works Section -->
    <div class="info-section">
        <div class="info-grid">
            <!-- How It Works -->
            <div>
                <h3><i class="fas fa-cogs"></i> How Auto-Sync Works</h3>
                
                <div class="process-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-title">Scheduled Execution</div>
                        <div class="step-description">Runs automatically every hour at minute 0 (10:00, 11:00, 12:00...)</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-title">Company Detection</div>
                        <div class="step-description">Finds all <?php echo $total_companies; ?> companies, processes <?php echo $companies_with_api; ?> with API access</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-title">Change Detection</div>
                        <div class="step-description">Looks for changes in the last <?php echo $sync_hours; ?> hour(s) using timestamps</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-title">Data Update</div>
                        <div class="step-description">Updates reporting table with fresh data from main database</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <div class="step-title">Cache Management</div>
                        <div class="step-description">Clears old cache entries to ensure fresh API responses</div>
                    </div>
                </div>
            </div>

            <!-- Benefits & Features -->
            <div>
                <h3><i class="fas fa-star"></i> Key Benefits</h3>
                
                <div class="process-step">
                    <div class="step-number"><i class="fas fa-bolt"></i></div>
                    <div class="step-content">
                        <div class="step-title">Always Fresh Data</div>
                        <div class="step-description">API responses contain the latest course completions and progress updates</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number"><i class="fas fa-rocket"></i></div>
                    <div class="step-content">
                        <div class="step-title">High Performance</div>
                        <div class="step-description">Pre-computed reporting table ensures fast API response times</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number"><i class="fas fa-sync"></i></div>
                    <div class="step-content">
                        <div class="step-title">Fully Automated</div>
                        <div class="step-description">No manual intervention required - runs 24/7 automatically</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number"><i class="fas fa-building"></i></div>
                    <div class="step-content">
                        <div class="step-title">Multi-Company Support</div>
                        <div class="step-description">Handles all companies automatically with individual error tracking</div>
                    </div>
                </div>
                
                <div class="process-step">
                    <div class="step-number"><i class="fas fa-chart-line"></i></div>
                    <div class="step-content">
                        <div class="step-title">Smart Caching</div>
                        <div class="step-description">Intelligent cache expiration balances speed with data freshness</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!$task_record || $task_record->disabled): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Auto-Sync is Currently Disabled</strong><br>
                The scheduled task is not active. You can enable it in the Task Manager or run manual syncs as needed.
            </div>
        </div>
        <?php elseif (!$cron_healthy): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Cron System Issue Detected</strong><br>
                Moodle's cron system appears to be delayed or not running. Auto-sync depends on cron for execution.
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Auto-Sync is Running Perfectly</strong><br>
                Your system is configured correctly and auto-sync is operating as expected.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Action Panel -->
    <div class="action-panel">
        <h3 style="margin: 0 0 10px 0; color: var(--text-primary); font-weight: 600;">
            <i class="fas fa-tools"></i> Quick Actions & Management
        </h3>
        <p style="color: var(--text-secondary); margin-bottom: 0;">
            Access key management tools and perform common administrative tasks
        </p>
        
        <div class="action-grid">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="action-title">Manual Sync</div>
                <div class="action-description">
                    Trigger an immediate synchronization for all companies
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/sync_reporting_data.php" class="btn-primary">
                    <i class="fas fa-sync"></i> Start Sync
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="action-title">Company Settings</div>
                <div class="action-description">
                    Configure API settings for individual companies
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/company_settings.php" class="btn-secondary">
                    <i class="fas fa-cog"></i> Configure
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-area"></i>
                </div>
                <div class="action-title">Monitoring Dashboard</div>
                <div class="action-description">
                    View detailed analytics and performance metrics
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php" class="btn-outline">
                    <i class="fas fa-chart-line"></i> View Analytics
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="action-title">Task Manager</div>
                <div class="action-description">
                    Manage scheduled tasks and execution settings
                </div>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/tool/task/scheduledtasks.php" class="btn-outline">
                    <i class="fas fa-calendar-alt"></i> Manage Tasks
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="nav-links">
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php">
            <i class="fas fa-arrow-left"></i> Back to Control Center
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/advanced_monitoring.php">
            <i class="fas fa-chart-line"></i> API Analytics
        </a>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>