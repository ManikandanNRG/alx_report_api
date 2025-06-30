<?php
// Dedicated AJAX endpoint for Control Center stats
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set JSON header
header('Content-Type: application/json');

try {
    $stats = [
        'total_records' => 0,
        'total_companies' => 0,
        'api_calls_today' => 0,
        'health_status' => 'healthy'
    ];
    
    // Get companies using the same function as monitoring dashboard
    $companies = local_alx_report_api_get_companies();
    $stats['total_companies'] = count($companies);
    
    // Get total records from reporting table (same as monitoring dashboard)
    if ($DB->get_manager()->table_exists('local_alx_api_reporting')) {
        $stats['total_records'] = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
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
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'total_records' => 0,
        'total_companies' => 0,
        'api_calls_today' => 0,
        'health_status' => 'error',
        'health_icon' => '❌'
    ]);
}
?> 