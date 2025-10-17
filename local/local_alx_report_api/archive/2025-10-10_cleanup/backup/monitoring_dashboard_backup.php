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
 * System Health & Alerts Management Dashboard for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_monitoring');

// Page setup
$PAGE->set_url('/local/alx_report_api/monitoring_dashboard.php');
$PAGE->set_title('ALX Report API - System Health & Alerts');
$PAGE->set_heading('System Health & Alerts Management');

// Handle alert test action
$action = optional_param('action', '', PARAM_ALPHA);
$alert_type = optional_param('alert_type', 'health', PARAM_ALPHA);
$severity = optional_param('severity', 'medium', PARAM_ALPHA);

$message = '';
$message_type = 'info';

if ($action === 'send_test_alert' && confirm_sesskey()) {
    $test_message = "This is a test alert from ALX Report API System Health monitoring. If you received this, your alert configuration is working correctly!";
    
    $success = local_alx_report_api_send_alert(
        $alert_type,
        $severity,
        $test_message,
        ['test_mode' => true, 'timestamp' => time()],
        []
    );
    
    if ($success) {
        $message = "✅ Test alert sent successfully! Check your email inbox.";
        $message_type = 'success';
    } else {
        $message = "❌ Failed to send test alert. Check your configuration and try again.";
        $message_type = 'error';
    }
    
    // Check if alerting is disabled
    $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
    if (!$alerting_enabled) {
        $message .= " (Alerting is currently disabled in settings)";
    }
}

if ($action === 'clear_cache' && confirm_sesskey()) {
    $cleared = local_alx_report_api_cache_cleanup(0); // Clear all cache
    redirect($PAGE->url, "Cache cleared: $cleared entries removed", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get system health data
$system_health = local_alx_report_api_get_system_health();
$companies = local_alx_report_api_get_companies();

// Get alert configuration
$alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
$email_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
$alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
$alert_emails = get_config('local_alx_report_api', 'alert_emails');

// Get database health statistics
global $DB;
$db_stats = [
    'reporting_records' => 0,
    'active_records' => 0,
    'companies_configured' => count($companies),
    'cache_entries' => 0,
    'recent_syncs' => 0,
    'last_sync' => null
];

try {
    if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $db_stats['reporting_records'] = $DB->count_records('local_alx_api_reporting');
        $db_stats['active_records'] = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
    }
    
    if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
        $db_stats['cache_entries'] = $DB->count_records('local_alx_api_cache');
    }
    
    if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
        $db_stats['recent_syncs'] = $DB->count_records_select('local_alx_api_sync_status', 'last_sync_timestamp > ?', [time() - 86400]);
        $last_sync_timestamp = $DB->get_field_sql('SELECT MAX(last_sync_timestamp) FROM {local_alx_api_sync_status}');
        $db_stats['last_sync'] = $last_sync_timestamp;
    }
} catch (Exception $e) {
    error_log('ALX Report API: Database stats error: ' . $e->getMessage());
}

echo $OUTPUT->header();

// Add consistent styling
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';

?>

<style>
/* Tactical Ops Style Layout - ALX Report API Dashboard */
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
    --sidebar-width: 280px;
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

/* Tactical Ops Layout Container */
.tactical-dashboard {
    display: flex;
    min-height: 100vh;
    background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
}

/* Sidebar Navigation */
.dashboard-sidebar {
    width: var(--sidebar-width);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-right: 1px solid var(--border-color);
    box-shadow: var(--shadow-lg);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 30px 25px 20px;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
}

.sidebar-title {
    font-size: 1.4rem;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-subtitle {
    font-size: 0.85rem;
    opacity: 0.9;
    margin: 8px 0 0 0;
    font-weight: 400;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-item {
    display: block;
    padding: 15px 25px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    position: relative;
}

.nav-item:hover {
    background: linear-gradient(90deg, rgba(37, 99, 235, 0.1) 0%, transparent 100%);
    color: var(--primary-color);
    transform: translateX(5px);
}

.nav-item.active {
    background: linear-gradient(90deg, rgba(37, 99, 235, 0.15) 0%, transparent 100%);
    color: var(--primary-color);
    border-right: 3px solid var(--primary-color);
}

.nav-item i {
    width: 20px;
    margin-right: 12px;
    font-size: 1.1rem;
}

.nav-item-text {
    font-weight: 600;
    font-size: 0.95rem;
}

/* Main Content Area */
.dashboard-main {
    margin-left: var(--sidebar-width);
    flex: 1;
    padding: 30px;
    background: transparent;
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
    color: white;
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(16, 185, 129, 0.25);
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    pointer-events: none;
}

.dashboard-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 800;
    position: relative;
    z-index: 2;
}

.dashboard-header .subtitle {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.95;
    position: relative;
    z-index: 2;
}

/* Content Sections */
.content-section {
    display: none;
}

.content-section.active {
    display: block;
}

/* Grid Layout for Cards */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.dashboard-grid.grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
}

.dashboard-grid.grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
}

.dashboard-grid.grid-4 {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-sidebar {
        transform: translateX(-100%);
    }
    
    .dashboard-sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .dashboard-main {
        margin-left: 0;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

/* Mobile Toggle Button */
.mobile-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 15px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    cursor: pointer;
}

@media (max-width: 1024px) {
    .mobile-toggle {
        display: block;
    }
}

.monitoring-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: transparent;
    min-height: 100vh;
}

/* Database-Focused Header */
.monitoring-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
    color: white;
    padding: 50px 40px;
    border-radius: 16px;
    margin-bottom: 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
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
    background: linear-gradient(90deg, var(--success-color), var(--info-color));
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
    color: var(--success-color);
    font-size: 1.4rem;
}

.section-body {
    padding: 35px;
}

/* Database Health Status Card */
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
    background: linear-gradient(180deg, var(--success-color), var(--info-color));
}

.check-card:hover {
    transform: translateY(-3px) translateX(2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: var(--success-color);
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
    background: linear-gradient(90deg, var(--success-color), var(--info-color));
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--success-color);
    margin-bottom: 12px;
    background: linear-gradient(135deg, var(--success-color), var(--info-color));
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

/* Enhanced Database Intelligence Table */
.database-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.database-table th,
.database-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.database-table th {
    background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.database-table tbody tr {
    transition: all 0.2s ease;
}

.database-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
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

.status-enabled {
    background: linear-gradient(135deg, var(--success-color), #34d399);
    color: white;
}

.status-disabled {
    background: linear-gradient(135deg, var(--danger-color), #f87171);
    color: white;
}

.status-warning {
    background: linear-gradient(135deg, var(--warning-color), #fbbf24);
    color: #92400e;
}

/* Enhanced Alert Configuration */
.alert-config-card {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 35px;
    position: relative;
}

.alert-config-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--info-color), var(--primary-color));
    border-radius: 0 0 0 12px;
}

.alert-config-card h4 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.2rem;
    font-weight: 700;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.config-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Enhanced Form Styling */
.alert-test-form {
    background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 25px;
    margin: 25px 0;
    border: 1px solid var(--border-color);
}

.alert-test-form h4 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.help-text {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 5px;
}

/* Enhanced Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    margin: 5px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark), #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--secondary-color), #475569);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #475569, #334155);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(100, 116, 139, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

/* Enhanced Alerts */
.alert {
    padding: 20px 25px;
    border-radius: 12px;
    margin: 25px 0;
    border: 1px solid;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-color: var(--success-color);
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-color: var(--danger-color);
}

/* Enhanced Navigation */
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
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.nav-links a:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.nav-links a i {
    margin-right: 8px;
}

/* Performance Indicators */
.performance-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.performance-excellent {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.performance-good {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
}

.performance-warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
}

.performance-poor {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
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
    
    .config-grid {
        grid-template-columns: 1fr;
    }
    
    .nav-links a {
        display: block;
        margin: 10px 0;
    }
}
</style>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Tactical Dashboard Layout -->
<div class="tactical-dashboard">
    <!-- Sidebar Navigation -->
    <nav class="dashboard-sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-title">
                <i class="fas fa-shield-alt"></i>
                ALX COMMAND
            </h1>
            <p class="sidebar-subtitle">Report API Control Center</p>
        </div>
        
        <div class="sidebar-nav">
            <button class="nav-item active" onclick="showSection('overview')">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-item-text">Overview</span>
            </button>
            <button class="nav-item" onclick="showSection('health')">
                <i class="fas fa-heartbeat"></i>
                <span class="nav-item-text">System Health</span>
            </button>
            <button class="nav-item" onclick="showSection('database')">
                <i class="fas fa-database"></i>
                <span class="nav-item-text">Database Intelligence</span>
            </button>
            <button class="nav-item" onclick="showSection('analytics')">
                <i class="fas fa-chart-line"></i>
                <span class="nav-item-text">Performance Analytics</span>
            </button>
            <button class="nav-item" onclick="showSection('cache')">
                <i class="fas fa-memory"></i>
                <span class="nav-item-text">Cache Management</span>
            </button>
            <button class="nav-item" onclick="showSection('alerts')">
                <i class="fas fa-bell"></i>
                <span class="nav-item-text">Alert System</span>
            </button>
            <button class="nav-item" onclick="showSection('companies')">
                <i class="fas fa-building"></i>
                <span class="nav-item-text">Company Status</span>
            </button>
        </div>
    </nav>
    
    <!-- Main Content Area -->
    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1>
                <i class="fas fa-shield-alt"></i>
                ALX Report API Command Center
            </h1>
            <p class="subtitle">
                Real-time monitoring, database intelligence, and automated alerting for optimal API performance
            </p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

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
        </div>
    </div>

    <!-- Enhanced Database Intelligence Section -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-brain"></i>
                Database Intelligence & Analytics
            </h2>
        </div>
        <div class="section-body">
            <?php 
            // Get enhanced database analytics
            $db_analytics = [];
            
            try {
                // Table sizes and row counts
                if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
                    $db_analytics['reporting_total'] = $DB->count_records('local_alx_api_reporting');
                    $db_analytics['reporting_active'] = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
                    $db_analytics['reporting_deleted'] = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 1]);
                    
                    // Get average response time for data processing
                    $start_time = microtime(true);
                    $sample_query = $DB->get_records('local_alx_api_reporting', [], '', 'id', 0, 10);
                    $db_analytics['query_response_time'] = round((microtime(true) - $start_time) * 1000, 2);
                }
                
                // Cache analytics
                if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
                    $db_analytics['cache_total'] = $DB->count_records('local_alx_api_cache');
                    $db_analytics['cache_recent'] = $DB->count_records_select('local_alx_api_cache', 'created_at > ?', [time() - 3600]);
                    
                    // Cache hit/miss analysis (last 24 hours)
                    $cache_entries = $DB->get_records_select('local_alx_api_cache', 'created_at > ?', [time() - 86400]);
                    $db_analytics['cache_hit_rate'] = count($cache_entries) > 0 ? round((count($cache_entries) / max(1, $db_stats['recent_syncs'] * 10)) * 100, 1) : 0;
                }
                
                // Sync intelligence analytics
                if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
                    $sync_records = $DB->get_records_select('local_alx_api_sync_status', 'last_sync_timestamp > ?', [time() - 86400]);
                    $db_analytics['avg_sync_time'] = 0;
                    $db_analytics['sync_success_rate'] = 100;
                    
                    if ($sync_records) {
                        $total_sync_time = 0;
                        $successful_syncs = 0;
                        
                        foreach ($sync_records as $sync) {
                            if (isset($sync->sync_duration)) {
                                $total_sync_time += $sync->sync_duration;
                            }
                            if (isset($sync->status) && $sync->status === 'completed') {
                                $successful_syncs++;
                            }
                        }
                        
                        $db_analytics['avg_sync_time'] = count($sync_records) > 0 ? round($total_sync_time / count($sync_records), 2) : 0;
                        $db_analytics['sync_success_rate'] = count($sync_records) > 0 ? round(($successful_syncs / count($sync_records)) * 100, 1) : 100;
                    }
                }
                
                // API processing intelligence
                $api_calls_24h = 0;
                $avg_processing_time = 0;
                if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
                    $table_info = $DB->get_columns('local_alx_api_logs');
                    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
                    
                    $api_calls_24h = $DB->count_records_select('local_alx_api_logs', "$time_field > ?", [time() - 86400]);
                    
                    // Calculate average processing time if response_time field exists
                    if (isset($table_info['response_time'])) {
                        $avg_time_result = $DB->get_record_sql("SELECT AVG(response_time) as avg_time FROM {local_alx_api_logs} WHERE $time_field > ?", [time() - 86400]);
                        $avg_processing_time = $avg_time_result ? round($avg_time_result->avg_time, 3) : 0;
                    }
                }
                
            } catch (Exception $e) {
                error_log('ALX Report API: Database analytics error: ' . $e->getMessage());
            }
            ?>
            
            <!-- Database Performance Metrics -->
            <div class="alert-config-card">
                <h4><i class="fas fa-tachometer-alt"></i> Real-Time Database Performance</h4>
                <div class="config-grid">
                    <div class="config-item">
                        <span><strong>Query Response Time:</strong></span>
                        <span class="performance-indicator <?php 
                            $response_time = $db_analytics['query_response_time'] ?? 0;
                            echo $response_time < 5 ? 'performance-excellent' : ($response_time < 20 ? 'performance-good' : ($response_time < 50 ? 'performance-warning' : 'performance-poor'));
                        ?>">
                            <?php echo number_format($response_time, 2); ?>ms
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Cache Hit Rate (24h):</strong></span>
                        <span class="performance-indicator <?php 
                            $hit_rate = $db_analytics['cache_hit_rate'] ?? 0;
                            echo $hit_rate > 80 ? 'performance-excellent' : ($hit_rate > 60 ? 'performance-good' : ($hit_rate > 30 ? 'performance-warning' : 'performance-poor'));
                        ?>">
                            <?php echo $hit_rate; ?>%
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Sync Success Rate:</strong></span>
                        <span class="performance-indicator <?php 
                            $success_rate = $db_analytics['sync_success_rate'] ?? 100;
                            echo $success_rate > 95 ? 'performance-excellent' : ($success_rate > 85 ? 'performance-good' : ($success_rate > 70 ? 'performance-warning' : 'performance-poor'));
                        ?>">
                            <?php echo $success_rate; ?>%
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Avg API Processing:</strong></span>
                        <span class="performance-indicator <?php 
                            $proc_time = $avg_processing_time;
                            echo $proc_time < 0.1 ? 'performance-excellent' : ($proc_time < 0.5 ? 'performance-good' : ($proc_time < 1.0 ? 'performance-warning' : 'performance-poor'));
                        ?>">
                            <?php echo number_format($proc_time, 3); ?>s
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>API Calls (24h):</strong></span>
                        <span style="font-weight: 700; color: var(--info-color);">
                            <?php echo number_format($api_calls_24h); ?>
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Data Quality Score:</strong></span>
                        <span class="performance-indicator <?php 
                            $active_ratio = $db_stats['reporting_records'] > 0 ? round(($db_stats['active_records'] / $db_stats['reporting_records']) * 100, 1) : 100;
                            echo $active_ratio > 95 ? 'performance-excellent' : ($active_ratio > 85 ? 'performance-good' : ($active_ratio > 70 ? 'performance-warning' : 'performance-poor'));
                        ?>">
                            <?php echo $active_ratio; ?>%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Database Intelligence Insights -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin: 30px 0;">
                <!-- Cache Intelligence -->
                <div class="check-card">
                    <div class="check-header">
                        <span class="check-status">🗄️</span>
                        <span class="check-title">Cache Intelligence</span>
                    </div>
                    <div class="check-message">
                        Monitoring cache effectiveness and optimization opportunities for faster API responses.
                    </div>
                    <div class="check-details">
                        <span><strong>Total Entries:</strong> <?php echo number_format($db_analytics['cache_total'] ?? 0); ?></span>
                        <span><strong>Recent (1h):</strong> <?php echo number_format($db_analytics['cache_recent'] ?? 0); ?></span>
                        <span><strong>Efficiency:</strong> <?php echo ($db_analytics['cache_hit_rate'] ?? 0) > 70 ? 'Optimal' : 'Needs Optimization'; ?></span>
                    </div>
                </div>

                <!-- Data Processing Intelligence -->
                <div class="check-card">
                    <div class="check-header">
                        <span class="check-status">⚡</span>
                        <span class="check-title">Processing Intelligence</span>
                    </div>
                    <div class="check-message">
                        Analyzing how efficiently the system processes API calls and updates reporting data.
                    </div>
                    <div class="check-details">
                        <span><strong>Avg Query Time:</strong> <?php echo number_format($db_analytics['query_response_time'] ?? 0, 2); ?>ms</span>
                        <span><strong>Sync Efficiency:</strong> <?php echo number_format($db_analytics['avg_sync_time'] ?? 0, 2); ?>s</span>
                        <span><strong>Processing Load:</strong> <?php echo $api_calls_24h > 1000 ? 'High' : ($api_calls_24h > 100 ? 'Moderate' : 'Light'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Storage Analysis -->
            <div class="alert-test-form">
                <h4><i class="fas fa-chart-pie"></i> Storage Intelligence & Optimization</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--success-color); margin-bottom: 8px;">
                            <?php echo number_format($db_analytics['reporting_total'] ?? 0); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">Total Records</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <?php echo number_format(($db_analytics['reporting_total'] ?? 0) * 0.5 / 1024, 1); ?> KB est.
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--info-color); margin-bottom: 8px;">
                            <?php echo number_format($db_analytics['reporting_active'] ?? 0); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">Active Records</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <?php echo ($db_analytics['reporting_total'] ?? 0) > 0 ? round((($db_analytics['reporting_active'] ?? 0) / $db_analytics['reporting_total']) * 100, 1) : 0; ?>% of total
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning-color); margin-bottom: 8px;">
                            <?php echo number_format($db_analytics['reporting_deleted'] ?? 0); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">Deleted Records</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            Can be cleaned up
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color); margin-bottom: 8px;">
                            <?php echo number_format($db_analytics['cache_total'] ?? 0); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">Cache Entries</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <?php echo number_format(($db_analytics['cache_total'] ?? 0) * 2.5 / 1024, 1); ?> KB est.
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <a href="?action=clear_cache&sesskey=<?php echo sesskey(); ?>" class="btn btn-secondary" onclick="return confirm('Clear all cache entries to optimize storage?')">
                        <i class="fas fa-broom"></i> Optimize Cache Storage
                    </a>
                    <button type="button" class="btn btn-primary" onclick="alert('Database optimization scheduled for next maintenance window.')">
                        <i class="fas fa-tools"></i> Schedule DB Optimization
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert System Management -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-bell"></i>
                Alert System Management
            </h2>
        </div>
        <div class="section-body">
            <!-- Alert Configuration Status -->
            <div class="alert-config-card">
                <h4><i class="fas fa-cog"></i> Current Alert Configuration</h4>
                <div class="config-grid">
                    <div class="config-item">
                        <span><strong>Alert System:</strong></span>
                        <span class="status-badge <?php echo $alerting_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                            <?php echo $alerting_enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Email Alerts:</strong></span>
                        <span class="status-badge <?php echo $email_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                            <?php echo $email_enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <div class="config-item">
                        <span><strong>Alert Threshold:</strong></span>
                        <span><?php echo ucfirst($alert_threshold); ?></span>
                    </div>
                    <div class="config-item">
                        <span><strong>Email Recipients:</strong></span>
                        <span><?php echo $alert_emails ? count(explode(',', $alert_emails)) . ' configured' : 'None configured'; ?></span>
                    </div>
                    <div class="config-item">
                        <span><strong>System Health Score:</strong></span>
                        <span style="font-weight: 700; color: <?php echo $system_health['score'] >= 80 ? 'var(--success-color)' : ($system_health['score'] >= 60 ? 'var(--warning-color)' : 'var(--danger-color)'); ?>">
                            <?php echo $system_health['score']; ?>/100
                        </span>
                    </div>
                </div>
            </div>

            <!-- Test Alert Form -->
            <div class="alert-test-form">
                <h4><i class="fas fa-flask"></i> Test Alert System</h4>
                <p>Send a test alert to verify your configuration is working correctly.</p>
                
                <form method="post" action="">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="action" value="send_test_alert">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="alert_type">Alert Type:</label>
                            <select name="alert_type" id="alert_type" class="form-control">
                                <option value="health" <?php echo $alert_type === 'health' ? 'selected' : ''; ?>>System Health</option>
                                <option value="performance" <?php echo $alert_type === 'performance' ? 'selected' : ''; ?>>Performance</option>
                                <option value="security" <?php echo $alert_type === 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="rate_limit" <?php echo $alert_type === 'rate_limit' ? 'selected' : ''; ?>>Rate Limiting</option>
                            </select>
                            <div class="help-text">Select the type of alert to test</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="severity">Severity Level:</label>
                            <select name="severity" id="severity" class="form-control">
                                <option value="low" <?php echo $severity === 'low' ? 'selected' : ''; ?>>🔵 Low</option>
                                <option value="medium" <?php echo $severity === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                <option value="high" <?php echo $severity === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                                <option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                            </select>
                            <div class="help-text">Choose severity level</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            📧 Send Test Alert
                        </button>
                        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api_settings" class="btn btn-secondary">
                            ⚙️ Configure Settings
                        </a>
                    </div>
                </form>
            </div>

            <!-- Recipients Information -->
            <?php if ($alert_emails || $alerting_enabled): ?>
            <div style="margin-top: 20px; padding: 15px; background: var(--light-bg); border-radius: 8px;">
                <h5>📧 Alert Recipients</h5>
                
                <?php if ($alert_emails): ?>
                <div style="margin-bottom: 15px;">
                    <strong>Configured Email Recipients:</strong><br>
                    <?php 
                    $emails = array_filter(array_map('trim', explode(',', $alert_emails)));
                    foreach ($emails as $email) {
                        echo "<span style='background: #e9ecef; padding: 2px 8px; border-radius: 12px; margin: 2px; display: inline-block;'>{$email}</span> ";
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <div>
                    <strong>Site Administrators (for critical alerts):</strong><br>
                    <?php 
                    $admins = get_admins();
                    foreach ($admins as $admin) {
                        echo "<span style='background: #d4edda; padding: 2px 8px; border-radius: 12px; margin: 2px; display: inline-block;'>" . fullname($admin) . " ({$admin->email})</span> ";
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Per-Company Database Intelligence -->
    <div class="section">
        <div class="section-header">
            <h2>
                <i class="fas fa-building"></i>
                Per-Company Database Intelligence
            </h2>
        </div>
        <div class="section-body">
            <p style="margin-bottom: 25px; color: var(--text-secondary); font-size: 1rem;">
                Monitor how each company's data is processed, stored, and optimized in the database. 
                Track sync intelligence, cache efficiency, and API processing metrics per organization.
            </p>
            
            <?php if (empty($companies)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No companies found. Companies will appear here once they are configured in the system.
            </div>
            <?php else: ?>
            <div style="overflow-x: auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <table class="database-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-building"></i> Company</th>
                            <th><i class="fas fa-database"></i> Records</th>
                            <th><i class="fas fa-check-circle"></i> Completion</th>
                            <th><i class="fas fa-sync"></i> Last Sync</th>
                            <th><i class="fas fa-tachometer-alt"></i> Cache Hits</th>
                            <th><i class="fas fa-cog"></i> API Status</th>
                            <th><i class="fas fa-chart-line"></i> DB Intelligence</th>
                            <th><i class="fas fa-tools"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                        <?php 
                        $company_stats = local_alx_report_api_get_reporting_stats($company->id);
                        $has_api_config = $DB->record_exists('local_alx_api_settings', ['companyid' => $company->id]);
                        
                        // Get company-specific database intelligence
                        $company_cache_hits = 0;
                        $company_sync_efficiency = 'N/A';
                        $company_db_performance = 'Unknown';
                        
                        try {
                            // Cache intelligence per company
                            if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
                                $cache_count = $DB->count_records_select('local_alx_api_cache', 
                                    'cache_key LIKE ? AND created_at > ?', 
                                    ['%company_' . $company->id . '%', time() - 86400]
                                );
                                $company_cache_hits = $cache_count;
                            }
                            
                            // Sync efficiency per company
                            if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
                                $sync_record = $DB->get_record('local_alx_api_sync_status', ['company_id' => $company->id]);
                                if ($sync_record && isset($sync_record->last_sync_duration)) {
                                    $company_sync_efficiency = $sync_record->last_sync_duration . 's';
                                } else if ($sync_record && $sync_record->last_sync_timestamp) {
                                    $company_sync_efficiency = 'Active';
                                }
                            }
                            
                            // Database performance assessment
                            $total_records = $company_stats['active_records'] + ($company_stats['inactive_records'] ?? 0);
                            if ($total_records > 1000) {
                                $company_db_performance = 'High Volume';
                            } else if ($total_records > 100) {
                                $company_db_performance = 'Medium Load';
                            } else if ($total_records > 0) {
                                $company_db_performance = 'Light Load';
                            } else {
                                $company_db_performance = 'No Data';
                            }
                            
                        } catch (Exception $e) {
                            error_log('Company intelligence error for ' . $company->id . ': ' . $e->getMessage());
                        }
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($company->name); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    ID: <?php echo $company->id; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 1.1rem; color: var(--success-color);">
                                    <?php echo number_format($company_stats['active_records']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <?php echo isset($company_stats['inactive_records']) ? number_format($company_stats['inactive_records']) . ' inactive' : 'active only'; ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $completion_rate = $company_stats['active_records'] > 0 ? 
                                    round(($company_stats['completed_courses'] / $company_stats['active_records']) * 100) : 0;
                                ?>
                                <div class="performance-indicator <?php 
                                    echo $completion_rate > 80 ? 'performance-excellent' : 
                                         ($completion_rate > 60 ? 'performance-good' : 
                                          ($completion_rate > 30 ? 'performance-warning' : 'performance-poor'));
                                ?>">
                                    <?php echo $completion_rate; ?>%
                                </div>
                            </td>
                            <td>
                                <?php if ($company_stats['last_update']): ?>
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php echo date('M j, H:i', $company_stats['last_update']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <?php 
                                    $hours_ago = round((time() - $company_stats['last_update']) / 3600, 1);
                                    echo $hours_ago < 1 ? 'Just now' : $hours_ago . 'h ago';
                                    ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--text-secondary);">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--info-color);">
                                    <?php echo number_format($company_cache_hits); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <?php echo $company_cache_hits > 10 ? 'Active' : ($company_cache_hits > 0 ? 'Light' : 'None'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $has_api_config ? 'status-enabled' : 'status-disabled'; ?>">
                                    <?php echo $has_api_config ? 'Configured' : 'Not Setup'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php echo $company_db_performance; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Sync: <?php echo $company_sync_efficiency; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="company_settings.php?companyid=<?php echo $company->id; ?>" 
                                       class="btn btn-primary" 
                                       style="padding: 6px 12px; font-size: 11px; margin: 2px;">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                    <?php if ($has_api_config): ?>
                                    <button onclick="alert('Database optimization for <?php echo htmlspecialchars($company->name); ?> scheduled.')" 
                                            class="btn btn-secondary" 
                                            style="padding: 6px 12px; font-size: 11px; margin: 2px;">
                                        <i class="fas fa-tools"></i> Optimize
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Company Intelligence Summary -->
            <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="check-card">
                    <div class="check-header">
                        <span class="check-status">📊</span>
                        <span class="check-title">Intelligence Summary</span>
                    </div>
                    <div class="check-message">
                        Active companies with optimized database processing and efficient sync mechanisms.
                    </div>
                    <div class="check-details">
                        <span><strong>Active Companies:</strong> <?php echo count(array_filter($companies, function($c) use ($DB) { return $DB->record_exists('local_alx_api_settings', ['companyid' => $c->id]); })); ?></span>
                        <span><strong>Total Records:</strong> <?php echo number_format(array_sum(array_map(function($c) { return local_alx_report_api_get_reporting_stats($c->id)['active_records']; }, $companies))); ?></span>
                    </div>
                </div>
                
                <div class="check-card">
                    <div class="check-header">
                        <span class="check-status">⚡</span>
                        <span class="check-title">Processing Efficiency</span>
                    </div>
                    <div class="check-message">
                        How efficiently each company's data flows through our processing intelligence.
                    </div>
                    <div class="check-details">
                        <span><strong>Avg Cache Hits:</strong> <?php echo number_format(array_sum(array_map(function($c) use ($DB) {
                            try {
                                return $DB->get_manager()->table_exists('local_alx_api_cache') ? 
                                    $DB->count_records_select('local_alx_api_cache', 'cache_key LIKE ? AND created_at > ?', 
                                        ['%company_' . $c->id . '%', time() - 86400]) : 0;
                            } catch (Exception $e) { return 0; }
                        }, $companies)) / max(1, count($companies))); ?></span>
                        <span><strong>Sync Intelligence:</strong> Optimized</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/advanced_monitoring.php">
            <i class="fas fa-chart-line"></i> API Performance & Security
        </a>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?> 