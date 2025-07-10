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
 * ALX Report API Advanced Monitoring Dashboard
 * 
 * Comprehensive monitoring with detailed health diagnostics, API analytics,
 * and advanced rate limiting monitoring.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_advanced_monitoring');

// Page setup
$PAGE->set_url('/local/alx_report_api/advanced_monitoring.php');
$PAGE->set_title('ALX Report API - Advanced Monitoring');
$PAGE->set_heading('Advanced Monitoring Dashboard');

// Get monitoring data
$system_health = local_alx_report_api_get_system_health();
$api_analytics = local_alx_report_api_get_api_analytics(24);
$rate_monitoring = local_alx_report_api_get_rate_limit_monitoring();

echo $OUTPUT->header();

// Add consistent styling
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';

?>

<style>
/* Modern Design System - Matching Auto-Sync Beautiful Design */
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

.monitoring-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: transparent;
    min-height: 100vh;
}

/* Stunning Header with Enhanced Gradient */
.monitoring-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    color: white;
    padding: 50px 40px;
    border-radius: 16px;
    margin-bottom: 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
}

.monitoring-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    pointer-events: none;
}

.monitoring-header h1 {
    margin: 0 0 15px 0;
    font-size: 3rem;
    font-weight: 800;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    position: relative;
    z-index: 2;
}

.monitoring-header p {
    margin: 0;
    font-size: 1.2rem;
    opacity: 0.95;
    font-weight: 400;
    position: relative;
    z-index: 2;
}

/* Enhanced Section Design */
.section {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 40px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.section:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.section-header {
    background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
    padding: 25px 30px;
    border-bottom: 1px solid var(--border-color);
    position: relative;
}

.section-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
}

.section-header h2 {
    margin: 0;
    font-size: 1.6rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 700;
}

.section-header h2 i {
    color: var(--primary-color);
    font-size: 1.4rem;
}

.section-body {
    padding: 35px;
}

/* Beautiful Health Status Card */
.health-status-card {
    display: flex;
    align-items: center;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.health-status-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.3) 0%, transparent 70%);
    pointer-events: none;
}

.health-status-card.healthy {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 50%, #6ee7b7 100%);
    border-color: var(--success-color);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.2);
}

.health-status-card.warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 50%, #fcd34d 100%);
    border-color: var(--warning-color);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.2);
}

.health-status-card.unhealthy {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 50%, #fca5a5 100%);
    border-color: var(--danger-color);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.2);
}

.health-icon {
    font-size: 5rem;
    margin-right: 30px;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    position: relative;
    z-index: 2;
}

.health-details {
    position: relative;
    z-index: 2;
}

.health-details h3 {
    margin: 0 0 10px 0;
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
}

.health-meta {
    color: #555;
    font-size: 1.1rem;
    font-weight: 500;
}

/* Enhanced Check Cards Grid */
.checks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 25px;
    margin-bottom: 35px;
}

.check-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.check-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary-color), var(--info-color));
}

.check-card:hover {
    transform: translateY(-3px) translateX(2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: var(--primary-color);
}

.check-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.check-status {
    font-size: 1.5rem;
    margin-right: 15px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}

.check-title {
    font-weight: 700;
    font-size: 1.2rem;
    text-transform: capitalize;
    color: var(--text-primary);
}

.check-message {
    color: var(--text-secondary);
    margin-bottom: 15px;
    line-height: 1.6;
    font-size: 0.95rem;
}

.check-details {
    font-size: 0.9rem;
    color: #888;
}

.check-details span {
    display: inline-block;
    margin-right: 12px;
    margin-bottom: 8px;
    background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    font-weight: 500;
}

/* Enhanced Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
    margin-bottom: 35px;
}

.metric-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary-color);
    margin-bottom: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--info-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.metric-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 600;
}

/* Enhanced Recommendations */
.recommendations {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 35px;
    position: relative;
}

.recommendations::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--info-color), var(--primary-color));
    border-radius: 0 0 0 12px;
}

.recommendations h4 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.2rem;
    font-weight: 700;
}

.recommendations h4 i {
    color: var(--info-color);
}

.recommendations ul {
    margin: 0;
    padding-left: 25px;
}

.recommendations li {
    margin-bottom: 12px;
    color: var(--text-primary);
    line-height: 1.6;
    font-weight: 500;
}

/* Enhanced Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 35px;
    margin-bottom: 35px;
}

.analytics-grid h4 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Enhanced Tables */
.table-container {
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    background: white;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table th,
.data-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.data-table tbody tr {
    transition: all 0.2s ease;
}

.data-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
}

.data-table code {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    border: 1px solid var(--border-color);
}

/* Enhanced Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-ok {
    background: linear-gradient(135deg, var(--success-color), #34d399);
    color: white;
}

.status-warning {
    background: linear-gradient(135deg, var(--warning-color), #fbbf24);
    color: #92400e;
}

.status-error {
    background: linear-gradient(135deg, var(--danger-color), #f87171);
    color: white;
}

/* Enhanced Alert Boxes */
.alert-box {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 5px solid;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.alert-high {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-color: var(--danger-color);
    color: #991b1b;
}

.alert-medium {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-color: var(--warning-color);
    color: #92400e;
}

/* Enhanced Navigation Links */
.nav-links {
    text-align: center;
    margin: 40px 0;
    padding: 30px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.nav-links a {
    display: inline-block;
    margin: 8px 12px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
}

.nav-links a:hover {
    background: linear-gradient(135deg, var(--primary-dark), #1e40af);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
}

.nav-links a i {
    margin-right: 8px;
}

/* Progress Bar Enhancement */
.usage-progress {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    border-radius: 10px;
    height: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.usage-progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.usage-progress-fill::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%);
    background-size: 20px 20px;
    animation: progress-stripes 1s linear infinite;
}

@keyframes progress-stripes {
    from { background-position: 0 0; }
    to { background-position: 20px 0; }
}

.usage-progress-text {
    position: absolute;
    top: 2px;
    left: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #333;
    text-shadow: 0 1px 2px rgba(255,255,255,0.8);
}

/* System Information Enhancement */
.system-info {
    margin-top: 35px;
    padding: 25px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.system-info h5 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.system-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.system-info-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.system-info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .monitoring-container {
        padding: 15px;
    }
    
    .monitoring-header {
        padding: 30px 20px;
    }
    
    .monitoring-header h1 {
        font-size: 2.2rem;
    }
    
    .section-body {
        padding: 20px;
    }
    
    .health-status-card {
        flex-direction: column;
        text-align: center;
        padding: 25px 20px;
    }
    
    .health-icon {
        margin-right: 0;
        margin-bottom: 20px;
    }
    
    .checks-grid {
        grid-template-columns: 1fr;
    }
    
    .metrics-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .nav-links a {
        display: block;
        margin: 10px 0;
    }
}
</style>

<div class="monitoring-container">
    <!-- Breadcrumb Navigation -->
    <div style="margin-bottom: 30px; padding: 15px 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 10px; color: var(--text-secondary); font-size: 0.9rem;">
            <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                <i class="fas fa-home"></i> Control Center
            </a>
            <span style="color: #ccc;">›</span>
            <span style="color: var(--text-secondary);"><i class="fas fa-chart-bar"></i> Monitoring & Analytics</span>
            <span style="color: #ccc;">›</span>
            <span style="color: var(--text-primary); font-weight: 600;"><i class="fas fa-chart-line"></i> API Performance & Security</span>
        </div>
    </div>

    <div class="monitoring-header">
        <h1>🔍 Advanced Monitoring Dashboard</h1>
        <p>Comprehensive system health, API analytics, and security monitoring</p>
    </div>

    <!-- System Health Section -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-heartbeat"></i>
                System Health Diagnostics
            </h2>
        </div>
        <div class="section-body">
            <!-- Health Status Card -->
            <div class="health-status-card <?php echo $system_health['overall_status']; ?>">
                <div class="health-icon">
                    <?php echo $system_health['overall_status'] === 'healthy' ? '✅' : ($system_health['overall_status'] === 'warning' ? '⚠️' : '❌'); ?>
                </div>
                <div class="health-details">
                    <h3>System Status: <?php echo ucfirst($system_health['overall_status']); ?></h3>
                    <div class="health-meta">
                        Health Score: <?php echo $system_health['score']; ?>/100 | 
                        Last Updated: <?php echo date('Y-m-d H:i:s', $system_health['last_updated']); ?>
                    </div>
                </div>
            </div>

            <!-- Health Checks Grid -->
            <div class="checks-grid">
                <?php foreach ($system_health['checks'] as $check_name => $check): ?>
                <div class="check-card">
                    <div class="check-header">
                        <span class="check-status">
                            <?php echo $check['status'] === 'ok' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : '❌'); ?>
                        </span>
                        <span class="check-title"><?php echo str_replace('_', ' ', $check_name); ?></span>
                    </div>
                    <div class="check-message">
                        <?php echo htmlspecialchars($check['message']); ?>
                    </div>
                    <?php if (isset($check['details']) && is_array($check['details'])): ?>
                    <div class="check-details">
                        <?php 
                        foreach ($check['details'] as $key => $value) {
                            if (!is_array($value) && !empty($value)) {
                                echo "<span><strong>{$key}:</strong> {$value}</span>";
                            }
                        } 
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recommendations -->
            <?php if (!empty($system_health['recommendations'])): ?>
            <div class="recommendations">
                <h4>
                    <i class="fas fa-lightbulb"></i>
                    System Recommendations
                </h4>
                <ul>
                    <?php foreach ($system_health['recommendations'] as $recommendation): ?>
                    <li><?php echo htmlspecialchars($recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- API Analytics Section -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-chart-line"></i>
                API Analytics (Last 24 Hours)
            </h2>
        </div>
        <div class="section-body">
            <!-- API Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['total_calls']; ?></div>
                    <div class="metric-label">Total API Calls</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['unique_users']; ?></div>
                    <div class="metric-label">Unique Users</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['unique_companies']; ?></div>
                    <div class="metric-label">Active Companies</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['calls_per_hour']; ?></div>
                    <div class="metric-label">Calls/Hour Avg</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['peak_hour'] ?: 'N/A'; ?></div>
                    <div class="metric-label">Peak Hour</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $api_analytics['summary']['avg_response_size'] ? number_format($api_analytics['summary']['avg_response_size']) . ' B' : 'N/A'; ?></div>
                    <div class="metric-label">Avg Response Size</div>
                </div>
            </div>

            <!-- Top Users and Companies -->
            <div class="analytics-grid">
                <div>
                    <h4>🔥 Top API Users</h4>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>API Calls</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($api_analytics['top_users'])): ?>
                                <tr><td colspan="2">No API activity in the last 24 hours</td></tr>
                                <?php else: ?>
                                <?php foreach (array_slice($api_analytics['top_users'], 0, 10) as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo $user['calls']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div>
                    <h4>🏢 Top Companies</h4>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>API Calls</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($api_analytics['top_companies'])): ?>
                                <tr><td colspan="2">No company activity in the last 24 hours</td></tr>
                                <?php else: ?>
                                <?php foreach (array_slice($api_analytics['top_companies'], 0, 10) as $company): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($company['name']); ?></td>
                                    <td><?php echo $company['calls']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rate Limiting & Security Section -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-shield-alt"></i>
                Rate Limiting & Security Monitoring
            </h2>
        </div>
        <div class="section-body">
            <!-- Current Limits -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $rate_monitoring['current_limits']['daily_requests']; ?></div>
                    <div class="metric-label">Daily Request Limit</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $rate_monitoring['current_limits']['max_records_per_request']; ?></div>
                    <div class="metric-label">Max Records/Request</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo count($rate_monitoring['violations']); ?></div>
                    <div class="metric-label">Violations Today</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo count($rate_monitoring['usage_today']); ?></div>
                    <div class="metric-label">Active Users Today</div>
                </div>
            </div>

            <!-- Security Alerts -->
            <?php if (!empty($rate_monitoring['alerts'])): ?>
            <h4>🚨 Security Alerts</h4>
            <?php foreach ($rate_monitoring['alerts'] as $alert): ?>
            <div class="alert-box alert-<?php echo $alert['severity']; ?>">
                <strong><?php echo ucfirst($alert['type']); ?>:</strong>
                <?php echo htmlspecialchars($alert['message']); ?>
                <small>(<?php echo date('H:i:s', $alert['timestamp']); ?>)</small>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Rate Limit Usage Today -->
            <?php if (!empty($rate_monitoring['usage_today'])): ?>
            <h4>📊 Today's Usage by User</h4>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Requests</th>
                            <th>Usage %</th>
                            <th>Status</th>
                            <th>Companies</th>
                            <th>Time Span</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($rate_monitoring['usage_today'], 0, 20) as $usage): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usage['name']); ?></td>
                            <td><?php echo $usage['requests_today']; ?>/<?php echo $usage['limit']; ?></td>
                            <td><?php echo $usage['usage_percentage']; ?>%</td>
                            <td>
                                <span class="status-badge status-<?php echo $usage['status'] === 'exceeded' ? 'error' : ($usage['status'] === 'warning' ? 'warning' : 'ok'); ?>">
                                    <?php echo $usage['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $usage['companies_accessed']; ?></td>
                            <td><?php echo $usage['time_span_hours']; ?>h</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Recent API Logs -->
            <div style="margin-top: 40px;">
                <h4 style="color: var(--text-primary); font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    📝 Recent API Activity <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-secondary);">(Last 20 Requests)</span>
                </h4>
                <?php
                // Get recent API logs with enhanced error handling
                $recent_logs = [];
                try {
                    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                        // Check which time field exists
                        $table_info = $DB->get_columns('local_alx_api_logs');
                        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
                        
                        $recent_logs_sql = "SELECT l.*, u.username, u.firstname, u.lastname 
                                           FROM {local_alx_api_logs} l
                                           LEFT JOIN {user} u ON u.id = l.userid
                                           ORDER BY l.$time_field DESC";
                        
                        $recent_logs = $DB->get_records_sql($recent_logs_sql, [], 0, 20);
                    }
                } catch (Exception $e) {
                    error_log('ALX Report API: Recent logs error: ' . $e->getMessage());
                }
                ?>
                
                <?php if (empty($recent_logs)): ?>
                <div class="table-container">
                    <p style="text-align: center; padding: 20px; color: #666;">No recent API activity found.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Company</th>
                                <th>Endpoint</th>
                                <th>IP Address</th>
                                <th>Records</th>
                                <th>Response Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <?php
                            $time = date('M j H:i:s', $log->$time_field);
                            $user_info = $log->username ? "{$log->firstname} {$log->lastname}" : "User {$log->userid}";
                            
                            // Handle company field with enhanced compatibility
                            $company_display = 'N/A';
                            if (isset($table_info['company_shortname']) && !empty($log->company_shortname)) {
                                $company_display = $log->company_shortname;
                            } elseif (isset($log->companyid) && $log->companyid) {
                                $company_display = "ID: {$log->companyid}";
                            }
                            
                            $record_count = isset($log->record_count) ? number_format($log->record_count) : 'N/A';
                            $response_time = isset($log->response_time_ms) ? $log->response_time_ms . 'ms' : 'N/A';
                            ?>
                            <tr>
                                <td><?php echo $time; ?></td>
                                <td><?php echo htmlspecialchars($user_info); ?></td>
                                <td><?php echo htmlspecialchars($company_display); ?></td>
                                <td><?php echo htmlspecialchars($log->endpoint); ?></td>
                                <td><?php echo htmlspecialchars($log->ipaddress); ?></td>
                                <td><?php echo $record_count; ?></td>
                                <td><?php echo $response_time; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Active API Tokens -->
            <div style="margin-top: 40px;">
                <h4 style="color: var(--text-primary); font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    🔑 Active API Tokens
                </h4>
                <div class="table-container">
                <?php
                // Get active tokens with enhanced service name checking
                $tokens = [];
                try {
                    // Check for both service names (custom first, then fallback)
                    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
                    if (!$service) {
                        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
                    }
                    
                    if ($service) {
                        $tokens = $DB->get_records_sql(
                            "SELECT t.*, u.username, u.firstname, u.lastname 
                             FROM {external_tokens} t
                             JOIN {user} u ON u.id = t.userid
                             WHERE t.externalserviceid = :serviceid
                             ORDER BY t.timecreated DESC",
                            ['serviceid' => $service->id]
                        );
                    }
                } catch (Exception $e) {
                    error_log('ALX Report API: Token loading error: ' . $e->getMessage());
                }
                ?>
                
                <?php if (empty($tokens)): ?>
                <p style="text-align: center; padding: 20px; color: #666;">
                    <?php if (!isset($service)): ?>
                        No ALX Report API service found. Check plugin installation.
                    <?php else: ?>
                        No active API tokens found.
                    <?php endif; ?>
                </p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>User</th>
                            <th>Created</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                        <?php
                        $user_info = "{$token->firstname} {$token->lastname} ({$token->username})";
                        $created = date('M j Y H:i', $token->timecreated);
                        $valid_until = $token->validuntil ? date('M j Y H:i', $token->validuntil) : 'Never expires';
                        $is_active = (!$token->validuntil || $token->validuntil > time());
                        ?>
                        <tr>
                            <td><code><?php echo substr($token->token, 0, 12) . '...'; ?></code></td>
                            <td><?php echo htmlspecialchars($user_info); ?></td>
                            <td><?php echo $created; ?></td>
                            <td><?php echo $valid_until; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $is_active ? 'ok' : 'error'; ?>">
                                    <?php echo $is_active ? 'Active' : 'Expired'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                </div>
            </div>

            <!-- Detailed Rate Limit Analysis -->
            <div style="margin-top: 40px;">
                <h4 style="color: var(--text-primary); font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    ⏱️ Rate Limit Analysis
                </h4>
            <div class="table-container">
                <?php
                // Get detailed rate limit data
                $rate_limit_details = [];
                try {
                    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                        $rate_limit = get_config('local_alx_report_api', 'daily_rate_limit') ?: 500;
                        $today_start = mktime(0, 0, 0);
                        
                        $table_info = $DB->get_columns('local_alx_api_logs');
                        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
                        
                        $user_requests_sql = "
                            SELECT l.userid, u.username, u.firstname, u.lastname,
                                   COUNT(*) as request_count,
                                   MIN(l.$time_field) as first_request,
                                   MAX(l.$time_field) as last_request
                            FROM {local_alx_api_logs} l
                            LEFT JOIN {user} u ON u.id = l.userid
                            WHERE l.$time_field >= :today_start
                            GROUP BY l.userid, u.username, u.firstname, u.lastname
                            ORDER BY request_count DESC";
                        
                        $rate_limit_details = $DB->get_records_sql($user_requests_sql, ['today_start' => $today_start]);
                    }
                } catch (Exception $e) {
                    error_log('ALX Report API: Rate limit analysis error: ' . $e->getMessage());
                }
                ?>
                
                <?php if (empty($rate_limit_details)): ?>
                <p style="text-align: center; padding: 20px; color: #666;">No API requests made today.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Requests Today</th>
                            <th>Rate Limit Status</th>
                            <th>First Request</th>
                            <th>Last Request</th>
                            <th>Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($rate_limit_details, 0, 15) as $user_request): ?>
                        <?php
                        $user_name = $user_request->username ? 
                            "{$user_request->firstname} {$user_request->lastname} ({$user_request->username})" : 
                            "User {$user_request->userid}";
                        
                        $usage_percent = round(($user_request->request_count / $rate_limit) * 100, 1);
                        $status_class = $user_request->request_count >= $rate_limit ? 'error' : 
                                       ($user_request->request_count >= ($rate_limit * 0.8) ? 'warning' : 'ok');
                        $status_text = $user_request->request_count >= $rate_limit ? 'EXCEEDED' : 
                                      ($user_request->request_count >= ($rate_limit * 0.8) ? 'WARNING' : 'OK');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user_name); ?></td>
                            <td><?php echo $user_request->request_count; ?> / <?php echo $rate_limit; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td><?php echo date('H:i:s', $user_request->first_request); ?></td>
                            <td><?php echo date('H:i:s', $user_request->last_request); ?></td>
                            <td>
                                <div class="usage-progress">
                                    <div class="usage-progress-fill" style="background: <?php echo $status_class === 'error' ? 'linear-gradient(135deg, var(--danger-color), #f87171)' : ($status_class === 'warning' ? 'linear-gradient(135deg, var(--warning-color), #fbbf24)' : 'linear-gradient(135deg, var(--success-color), #34d399)'); ?>; width: <?php echo min(100, $usage_percent); ?>%;"></div>
                                    <span class="usage-progress-text"><?php echo $usage_percent; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Recommendations -->
            <?php if (!empty($rate_monitoring['recommendations'])): ?>
            <div class="recommendations">
                <h4>
                    <i class="fas fa-lightbulb"></i>
                    Security Recommendations
                </h4>
                <ul>
                    <?php foreach ($rate_monitoring['recommendations'] as $rec): ?>
                    <li>
                        <strong><?php echo ucfirst($rec['type']); ?> (<?php echo $rec['priority']; ?> priority):</strong>
                        <?php echo htmlspecialchars($rec['message']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="system-info">
                <h5><i class="fas fa-info-circle"></i> System Information</h5>
                <div class="system-info-grid">
                    <div class="system-info-item">
                        <strong>Web Services:</strong><br>
                        <span style="color: <?php echo empty($CFG->enablewebservices) ? 'var(--danger-color)' : 'var(--success-color)'; ?>; font-weight: 600;">
                            <?php echo empty($CFG->enablewebservices) ? '❌ Disabled' : '✅ Enabled'; ?>
                        </span>
                    </div>
                    <div class="system-info-item">
                        <strong>Log Table:</strong><br>
                        <span style="color: <?php echo $DB->get_manager()->table_exists('local_alx_api_logs') ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600;">
                            <?php echo $DB->get_manager()->table_exists('local_alx_api_logs') ? '✅ Available' : '❌ Missing'; ?>
                        </span>
                    </div>
                    <div class="system-info-item">
                        <strong>Plugin Version:</strong><br>
                        <span style="color: var(--text-primary); font-weight: 600;">
                            📦 <?php echo get_config('local_alx_report_api', 'version') ?: 'Unknown'; ?>
                        </span>
                    </div>
                    <div class="system-info-item">
                        <strong>Daily Rate Limit:</strong><br>
                        <span style="color: var(--info-color); font-weight: 600;">
                            🔒 <?php echo get_config('local_alx_report_api', 'daily_rate_limit') ?: 500; ?> requests/day
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="nav-links">
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php">
            <i class="fas fa-arrow-left"></i> Back to Control Center
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php">
            <i class="fas fa-sync-alt"></i> Auto-Sync Status
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php">
            <i class="fas fa-chart-bar"></i> Database Intelligence
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?> 