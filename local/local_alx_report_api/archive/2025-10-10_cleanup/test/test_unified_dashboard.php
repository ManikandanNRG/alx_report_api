<?php
// Test version of unified monitoring dashboard to debug HTTP 500 error

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_unified_monitoring');

// Page setup
$PAGE->set_url('/local/alx_report_api/test_unified_dashboard.php');
$PAGE->set_title('ALX Report API - Test Dashboard');
$PAGE->set_heading('ALX Report API Test Dashboard');

echo $OUTPUT->header();

echo '<h1>Test Dashboard</h1>';
echo '<p>If you can see this message, the basic setup is working.</p>';

// Test basic data retrieval
echo '<h2>Testing Data Functions...</h2>';

try {
    echo '<p>✅ Testing system health...</p>';
    $system_health = local_alx_report_api_get_system_health();
    echo '<p>✅ System health retrieved successfully</p>';
    
    echo '<p>✅ Testing companies...</p>';
    $companies = local_alx_report_api_get_companies();
    echo '<p>✅ Companies retrieved: ' . count($companies) . '</p>';
    
    echo '<p>✅ Testing reporting stats...</p>';
    $reporting_stats = local_alx_report_api_get_reporting_stats();
    echo '<p>✅ Reporting stats retrieved successfully</p>';
    
    echo '<p>✅ Testing API analytics...</p>';
    $api_analytics = local_alx_report_api_get_api_analytics(24);
    echo '<p>✅ API analytics retrieved successfully</p>';
    
    echo '<p>✅ Testing rate monitoring...</p>';
    $rate_monitoring = local_alx_report_api_get_rate_limit_monitoring();
    echo '<p>✅ Rate monitoring retrieved successfully</p>';
    
    echo '<p>✅ Testing auth analytics...</p>';
    $auth_analytics = local_alx_report_api_get_auth_analytics(24);
    echo '<p>✅ Auth analytics retrieved successfully</p>';
    
    echo '<h2>✅ ALL TESTS PASSED!</h2>';
    echo '<p>The unified monitoring dashboard should work. The error might be in the HTML/CSS or a specific section.</p>';
    
} catch (Exception $e) {
    echo '<h2>❌ ERROR FOUND!</h2>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . $e->getFile() . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
}

echo $OUTPUT->footer();
?> 