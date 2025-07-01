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

?>

<style>
:root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --info-color: #17a2b8;
    --light-bg: #f8f9fa;
    --border-color: #dee2e6;
}

.monitoring-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.monitoring-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.monitoring-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.monitoring-header p {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

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

.health-status-card {
    display: flex;
    align-items: center;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid transparent;
}

.health-status-card.healthy {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-color: var(--success-color);
}

.health-status-card.warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-color: var(--warning-color);
}

.health-status-card.unhealthy {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-color: var(--danger-color);
}

.health-icon {
    font-size: 4rem;
    margin-right: 24px;
}

.health-details h3 {
    margin: 0 0 8px 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.health-meta {
    color: #666;
    font-size: 1rem;
}

.checks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.check-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
}

.check-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.check-header {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.check-status {
    font-size: 1.2rem;
    margin-right: 12px;
}

.check-title {
    font-weight: 600;
    font-size: 1.1rem;
    text-transform: capitalize;
}

.check-message {
    color: #666;
    margin-bottom: 12px;
}

.check-details {
    font-size: 0.9rem;
    color: #888;
}

.check-details span {
    display: inline-block;
    margin-right: 16px;
    background: var(--light-bg);
    padding: 4px 8px;
    border-radius: 4px;
}

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

.recommendations {
    background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.recommendations h4 {
    margin: 0 0 16px 0;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recommendations ul {
    margin: 0;
    padding-left: 20px;
}

.recommendations li {
    margin-bottom: 8px;
    color: #495057;
}

.analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.table-container {
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--light-bg);
    font-weight: 600;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-ok {
    background: var(--success-color);
    color: white;
}

.status-warning {
    background: var(--warning-color);
    color: black;
}

.status-error {
    background: var(--danger-color);
    color: white;
}

.alert-box {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border-left: 4px solid;
}

.alert-high {
    background: #f8d7da;
    border-color: var(--danger-color);
    color: #721c24;
}

.alert-medium {
    background: #fff3cd;
    border-color: var(--warning-color);
    color: #856404;
}

.nav-links {
    text-align: center;
    margin: 30px 0;
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
    background: #0056b3;
    transform: translateY(-2px);
}
</style>

<div class="monitoring-container">
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
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="nav-links">
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php">
            <i class="fas fa-tachometer-alt"></i> Control Center
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/monitoring_dashboard.php">
            <i class="fas fa-chart-bar"></i> Standard Monitoring
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/check_rate_limit.php">
            <i class="fas fa-shield-alt"></i> Rate Limit Details
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/auto_sync_status.php">
            <i class="fas fa-sync-alt"></i> Sync Status
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?> 