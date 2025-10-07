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

// Get active tab from URL parameter
$active_tab = optional_param('tab', 'autosync', PARAM_ALPHA);

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

if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $today_start = mktime(0, 0, 0);
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    $api_calls_today = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ?", [$today_start]);
    
    if (isset($table_info['response_time_ms'])) {
        $avg_result = $DB->get_record_sql("SELECT AVG(response_time_ms) as avg_time FROM {local_alx_api_logs} WHERE {$time_field} >= ?", [$today_start]);
        $avg_response_time = $avg_result ? round($avg_result->avg_time / 1000, 2) : 0;
    }
    
    if (isset($table_info['error_message'])) {
        $success_count = $DB->count_records_select('local_alx_api_logs', "{$time_field} >= ? AND error_message IS NULL", [$today_start]);
        $success_rate = $api_calls_today > 0 ? round(($success_count / $api_calls_today) * 100, 1) : 100;
    }
}

if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
    $total_cache = $DB->count_records('local_alx_api_cache');
    $active_cache = $DB->count_records_select('local_alx_api_cache', 'expires_at > ?', [time()]);
    $cache_hit_rate = $total_cache > 0 ? round(($active_cache / $total_cache) * 100, 1) : 0;
}

if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
    $total_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
}

// Get security data
$active_tokens = $DB->count_records('external_tokens');
$rate_limit_violations = 0;
$failed_auth = 0;

if ($DB->get_manager()->table_exists('local_alx_api_alerts')) {
    $today_start = mktime(0, 0, 0);
    $rate_limit_violations = $DB->count_records_select('local_alx_api_alerts', 
        "alert_type = 'rate_limit_exceeded' AND timecreated >= ?", [$today_start]);
}

?>

<style>
* { font-family: 'Inter', sans-serif; }

.monitoring-container {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 20px;
}

/* Header */
.monitoring-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.monitoring-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.monitoring-header p {
    margin: 8px 0 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.back-button {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.back-button:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

/* Tab Navigation */
.tab-navigation {
    background: white;
    border-radius: 12px;
    padding: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    gap: 8px;
}

.tab-button {
    flex: 1;
    padding: 16px 24px;
    border: none;
    background: transparent;
    color: #4a5568;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab-button:hover {
    background: #f7fafc;
}

.tab-button.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
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
