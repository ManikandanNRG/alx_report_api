<?php
/**
 * Debug script for Cache Management feature
 * 
 * This script helps diagnose issues with the cache management implementation.
 * Run this script to check if all functions and database tables are working correctly.
 * 
 * Usage: https://your-moodle-site.com/local/alx_report_api/debug_cache_management.php
 * 
 * @package    local_alx_report_api
 * @copyright  2025 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

// Load constants
if (!class_exists('local_alx_report_api\constants')) {
    require_once($CFG->dirroot . '/local/alx_report_api/classes/constants.php');
}
use local_alx_report_api\constants;

// Require login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page
$PAGE->set_url(new moodle_url('/local/alx_report_api/debug_cache_management.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Cache Management Debug');
$PAGE->set_heading('Cache Management Debug');

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
echo '<h1 style="color: #2d3748; margin-bottom: 20px;">🔍 Cache Management Debug Script</h1>';
echo '<p style="color: #64748b; margin-bottom: 30px;">This script checks if the cache management feature is working correctly.</p>';

// Test counter
$test_number = 1;
$passed = 0;
$failed = 0;

/**
 * Helper function to display test results
 */
function display_test($number, $title, $result, $message, $details = '') {
    global $passed, $failed;
    
    $status_color = $result ? '#10b981' : '#ef4444';
    $status_icon = $result ? '✅' : '❌';
    $status_text = $result ? 'PASS' : 'FAIL';
    
    if ($result) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo '<div style="background: white; border-left: 4px solid ' . $status_color . '; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<div style="display: flex; align-items: center; margin-bottom: 10px;">';
    echo '<span style="font-size: 24px; margin-right: 10px;">' . $status_icon . '</span>';
    echo '<h3 style="margin: 0; color: #2d3748;">Test #' . $number . ': ' . htmlspecialchars($title) . '</h3>';
    echo '<span style="margin-left: auto; padding: 4px 12px; background: ' . $status_color . '; color: white; border-radius: 4px; font-weight: 600; font-size: 12px;">' . $status_text . '</span>';
    echo '</div>';
    echo '<p style="color: #4a5568; margin: 10px 0;">' . htmlspecialchars($message) . '</p>';
    
    if ($details) {
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 10px; font-family: monospace; font-size: 13px; overflow-x: auto;">';
        echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($details) . '</pre>';
        echo '</div>';
    }
    
    echo '</div>';
}

// ============================================================================
// TEST 1: Check if constants class exists
// ============================================================================
try {
    $result = class_exists('local_alx_report_api\constants');
    $message = $result ? 'Constants class is loaded successfully.' : 'Constants class not found!';
    $details = $result ? 'Class: local_alx_report_api\constants' : 'ERROR: Cannot find constants class';
    display_test($test_number++, 'Constants Class', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Constants Class', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 2: Check if cache table exists
// ============================================================================
try {
    $result = $DB->get_manager()->table_exists('local_alx_api_cache');
    $message = $result ? 'Cache table exists in database.' : 'Cache table does NOT exist!';
    $details = 'Table name: local_alx_api_cache' . "\n";
    $details .= 'Constant: constants::TABLE_CACHE = ' . constants::TABLE_CACHE;
    display_test($test_number++, 'Cache Table Exists', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Cache Table Exists', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 3: Check cache table structure
// ============================================================================
try {
    if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
        $columns = $DB->get_columns('local_alx_api_cache');
        $required_fields = ['id', 'companyid', 'cache_key', 'cache_data', 'timecreated'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($columns[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        $result = empty($missing_fields);
        $message = $result ? 'All required fields exist in cache table.' : 'Missing fields: ' . implode(', ', $missing_fields);
        
        $details = "Cache table structure:\n";
        foreach ($columns as $column) {
            $details .= "- {$column->name} ({$column->type})\n";
        }
        
        display_test($test_number++, 'Cache Table Structure', $result, $message, $details);
    } else {
        display_test($test_number++, 'Cache Table Structure', false, 'Cannot check structure - table does not exist', '');
    }
} catch (Exception $e) {
    display_test($test_number++, 'Cache Table Structure', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 4: Check if settings table exists
// ============================================================================
try {
    $result = $DB->get_manager()->table_exists('local_alx_api_settings');
    $message = $result ? 'Settings table exists in database.' : 'Settings table does NOT exist!';
    $details = 'Table name: local_alx_api_settings' . "\n";
    $details .= 'Constant: constants::TABLE_SETTINGS = ' . constants::TABLE_SETTINGS;
    display_test($test_number++, 'Settings Table Exists', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Settings Table Exists', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 5: Check if function local_alx_report_api_get_company_setting exists
// ============================================================================
try {
    $result = function_exists('local_alx_report_api_get_company_setting');
    $message = $result ? 'Function exists and can be called.' : 'Function does NOT exist!';
    $details = 'Function: local_alx_report_api_get_company_setting($companyid, $setting_name, $default)';
    display_test($test_number++, 'Get Company Setting Function', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Get Company Setting Function', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 6: Check if function local_alx_report_api_cache_clear_company exists
// ============================================================================
try {
    $result = function_exists('local_alx_report_api_cache_clear_company');
    $message = $result ? 'Function exists and can be called.' : 'Function does NOT exist! This is the NEW function.';
    $details = 'Function: local_alx_report_api_cache_clear_company($companyid)' . "\n";
    $details .= 'Location: local/alx_report_api/lib.php (around line 4743)';
    display_test($test_number++, 'Cache Clear Company Function', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Cache Clear Company Function', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 7: Check if function local_alx_report_api_get_cache_stats exists
// ============================================================================
try {
    $result = function_exists('local_alx_report_api_get_cache_stats');
    $message = $result ? 'Function exists and can be called.' : 'Function does NOT exist! This is the NEW function.';
    $details = 'Function: local_alx_report_api_get_cache_stats($companyid)' . "\n";
    $details .= 'Location: local/alx_report_api/lib.php (around line 4758)';
    display_test($test_number++, 'Get Cache Stats Function', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'Get Cache Stats Function', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 8: Get a test company
// ============================================================================
$test_company = null;
try {
    if ($DB->get_manager()->table_exists('company')) {
        $test_company = $DB->get_record_sql("SELECT * FROM {company} LIMIT 1");
        $result = !empty($test_company);
        $message = $result ? 'Found test company: ' . $test_company->name : 'No companies found in database.';
        $details = $result ? "Company ID: {$test_company->id}\nCompany Name: {$test_company->name}" : 'Cannot run further tests without a company.';
        display_test($test_number++, 'Get Test Company', $result, $message, $details);
    } else {
        display_test($test_number++, 'Get Test Company', false, 'Company table does not exist', '');
    }
} catch (Exception $e) {
    display_test($test_number++, 'Get Test Company', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// TEST 9: Test cache_clear_company function (if it exists)
// ============================================================================
if ($test_company && function_exists('local_alx_report_api_cache_clear_company')) {
    try {
        $cleared = local_alx_report_api_cache_clear_company($test_company->id);
        $result = is_int($cleared) && $cleared >= 0;
        $message = $result ? "Function executed successfully. Cleared {$cleared} cache entries." : 'Function returned unexpected value.';
        $details = "Company ID: {$test_company->id}\n";
        $details .= "Entries cleared: {$cleared}\n";
        $details .= "Return type: " . gettype($cleared);
        display_test($test_number++, 'Execute Cache Clear Function', $result, $message, $details);
    } catch (Exception $e) {
        display_test($test_number++, 'Execute Cache Clear Function', false, 'Exception occurred', $e->getMessage());
    }
} else {
    $reason = !$test_company ? 'No test company available' : 'Function does not exist';
    display_test($test_number++, 'Execute Cache Clear Function', false, 'Skipped: ' . $reason, '');
}

// ============================================================================
// TEST 10: Test get_cache_stats function (if it exists)
// ============================================================================
if ($test_company && function_exists('local_alx_report_api_get_cache_stats')) {
    try {
        $stats = local_alx_report_api_get_cache_stats($test_company->id);
        $result = is_object($stats) && isset($stats->total_entries);
        $message = $result ? 'Function executed successfully and returned stats object.' : 'Function returned unexpected value.';
        
        $details = "Company ID: {$test_company->id}\n";
        if ($result) {
            $details .= "Total Entries: {$stats->total_entries}\n";
            $details .= "Last Update: " . ($stats->last_update ? date('Y-m-d H:i:s', $stats->last_update) : 'null') . "\n";
            $details .= "Expires At: " . ($stats->expires_at ? date('Y-m-d H:i:s', $stats->expires_at) : 'null') . "\n";
            $details .= "Is Expired: " . ($stats->is_expired ? 'true' : 'false') . "\n";
            $details .= "Cache Enabled: {$stats->cache_enabled}";
        } else {
            $details .= "Return type: " . gettype($stats);
        }
        
        display_test($test_number++, 'Execute Get Cache Stats Function', $result, $message, $details);
    } catch (Exception $e) {
        display_test($test_number++, 'Execute Get Cache Stats Function', false, 'Exception occurred', $e->getMessage());
    }
} else {
    $reason = !$test_company ? 'No test company available' : 'Function does not exist';
    display_test($test_number++, 'Execute Get Cache Stats Function', false, 'Skipped: ' . $reason, '');
}

// ============================================================================
// TEST 11: Check cache data in database
// ============================================================================
if ($test_company) {
    try {
        $cache_count = $DB->count_records(constants::TABLE_CACHE, ['companyid' => $test_company->id]);
        $result = true; // Always pass, just informational
        $message = "Found {$cache_count} cache entries for company: {$test_company->name}";
        
        $details = "Company ID: {$test_company->id}\n";
        $details .= "Cache entries: {$cache_count}\n\n";
        
        if ($cache_count > 0) {
            $sample = $DB->get_records(constants::TABLE_CACHE, ['companyid' => $test_company->id], 'timecreated DESC', '*', 0, 3);
            $details .= "Sample cache entries (latest 3):\n";
            foreach ($sample as $entry) {
                $details .= "- ID: {$entry->id}, Key: {$entry->cache_key}, Created: " . date('Y-m-d H:i:s', $entry->timecreated) . "\n";
            }
        } else {
            $details .= "No cache entries found. This is normal if cache hasn't been created yet.";
        }
        
        display_test($test_number++, 'Check Cache Data', $result, $message, $details);
    } catch (Exception $e) {
        display_test($test_number++, 'Check Cache Data', false, 'Exception occurred', $e->getMessage());
    }
} else {
    display_test($test_number++, 'Check Cache Data', false, 'Skipped: No test company available', '');
}

// ============================================================================
// TEST 12: Check PHP error log location
// ============================================================================
try {
    $error_log = ini_get('error_log');
    $result = !empty($error_log);
    $message = $result ? 'PHP error log location found.' : 'PHP error log location not configured.';
    $details = "Error log: " . ($error_log ? $error_log : 'Not set') . "\n";
    $details .= "Display errors: " . ini_get('display_errors') . "\n";
    $details .= "Log errors: " . ini_get('log_errors');
    display_test($test_number++, 'PHP Error Log Location', $result, $message, $details);
} catch (Exception $e) {
    display_test($test_number++, 'PHP Error Log Location', false, 'Exception occurred', $e->getMessage());
}

// ============================================================================
// Summary
// ============================================================================
$total = $passed + $failed;
$pass_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
$summary_color = $failed == 0 ? '#10b981' : ($passed > $failed ? '#f59e0b' : '#ef4444');

echo '<div style="background: linear-gradient(135deg, ' . $summary_color . ' 0%, ' . $summary_color . 'dd 100%); color: white; padding: 30px; border-radius: 12px; margin-top: 30px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">';
echo '<h2 style="margin: 0 0 20px 0; font-size: 28px;">📊 Test Summary</h2>';
echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">';

echo '<div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">';
echo '<div style="font-size: 48px; font-weight: 700; margin-bottom: 5px;">' . $total . '</div>';
echo '<div style="font-size: 14px; opacity: 0.9;">Total Tests</div>';
echo '</div>';

echo '<div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">';
echo '<div style="font-size: 48px; font-weight: 700; margin-bottom: 5px;">' . $passed . '</div>';
echo '<div style="font-size: 14px; opacity: 0.9;">Passed</div>';
echo '</div>';

echo '<div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">';
echo '<div style="font-size: 48px; font-weight: 700; margin-bottom: 5px;">' . $failed . '</div>';
echo '<div style="font-size: 14px; opacity: 0.9;">Failed</div>';
echo '</div>';

echo '</div>';

echo '<div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.2); border-radius: 8px;">';
echo '<strong>Pass Rate:</strong> ' . $pass_rate . '%';
echo '</div>';

if ($failed > 0) {
    echo '<div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.3); border-radius: 8px;">';
    echo '<strong>⚠️ Action Required:</strong> Some tests failed. Please review the failed tests above and fix the issues.';
    echo '</div>';
} else {
    echo '<div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.3); border-radius: 8px;">';
    echo '<strong>✅ All tests passed!</strong> The cache management feature should be working correctly.';
    echo '</div>';
}

echo '</div>';

// ============================================================================
// Troubleshooting Guide
// ============================================================================
echo '<div style="background: #f8f9fa; border: 2px solid #e2e8f0; padding: 30px; border-radius: 12px; margin-top: 30px;">';
echo '<h2 style="color: #2d3748; margin: 0 0 20px 0;">🔧 Troubleshooting Guide</h2>';

echo '<h3 style="color: #4a5568; margin: 20px 0 10px 0;">If you see 500 errors:</h3>';
echo '<ol style="color: #64748b; line-height: 1.8;">';
echo '<li>Check PHP error log: <code>' . ($error_log ? $error_log : '/var/log/php-fpm.log') . '</code></li>';
echo '<li>Check Apache error log: <code>/var/log/apache2/error.log</code></li>';
echo '<li>Check Moodle error log: <code>/var/www/moodledata/error_log</code></li>';
echo '<li>Enable debugging in Moodle: Site Administration → Development → Debugging</li>';
echo '</ol>';

echo '<h3 style="color: #4a5568; margin: 20px 0 10px 0;">Common Issues:</h3>';
echo '<ul style="color: #64748b; line-height: 1.8;">';
echo '<li><strong>Function not found:</strong> Make sure lib.php has been saved and Moodle cache is cleared</li>';
echo '<li><strong>Table not found:</strong> Run database upgrade: Site Administration → Notifications</li>';
echo '<li><strong>Wrong field names:</strong> Check that code uses <code>timecreated</code> not <code>created_at</code></li>';
echo '<li><strong>Syntax errors:</strong> Check lib.php for missing semicolons, brackets, or quotes</li>';
echo '</ul>';

echo '<h3 style="color: #4a5568; margin: 20px 0 10px 0;">How to clear Moodle cache:</h3>';
echo '<ol style="color: #64748b; line-height: 1.8;">';
echo '<li>Go to: Site Administration → Development → Purge all caches</li>';
echo '<li>Or run: <code>php admin/cli/purge_caches.php</code></li>';
echo '</ol>';

echo '</div>';

echo '</div>'; // Close main container

echo $OUTPUT->footer();
