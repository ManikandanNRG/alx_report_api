<?php
/**
 * Debug script for cache management functions
 * 
 * This script tests the cache management functions and provides detailed error information.
 * 
 * Usage: https://your-site.com/local/alx_report_api/test_cache_management.php?companyid=301
 * 
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

// Require login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get company ID from URL
$companyid = optional_param('companyid', 0, PARAM_INT);

// Set page context
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/alx_report_api/test_cache_management.php');
$PAGE->set_title('Cache Management Debug');
$PAGE->set_heading('Cache Management Debug');

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
echo '<h2>🔍 Cache Management Debug Script</h2>';
echo '<p>This script tests the cache management functions and provides detailed error information.</p>';

// Test 1: Check if constants class exists
echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>Test 1: Check Constants Class</h3>';
try {
    if (class_exists('local_alx_report_api\constants')) {
        echo '<p style="color: green;">✅ Constants class exists</p>';
        echo '<p>TABLE_CACHE = ' . \local_alx_report_api\constants::TABLE_CACHE . '</p>';
    } else {
        echo '<p style="color: red;">❌ Constants class NOT found</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Test 2: Check if functions exist
echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>Test 2: Check Functions Exist</h3>';

$functions = [
    'local_alx_report_api_cache_clear_company',
    'local_alx_report_api_get_cache_stats',
    'local_alx_report_api_get_company_setting'
];

foreach ($functions as $function) {
    if (function_exists($function)) {
        echo '<p style="color: green;">✅ ' . $function . '() exists</p>';
    } else {
        echo '<p style="color: red;">❌ ' . $function . '() NOT found</p>';
    }
}
echo '</div>';

// Test 3: Check database tables
echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>Test 3: Check Database Tables</h3>';
try {
    $tables = [
        'local_alx_api_cache',
        'local_alx_api_settings',
        'company'
    ];
    
    foreach ($tables as $table) {
        if ($DB->get_manager()->table_exists($table)) {
            $count = $DB->count_records($table);
            echo '<p style="color: green;">✅ Table ' . $table . ' exists (' . $count . ' records)</p>';
        } else {
            echo '<p style="color: red;">❌ Table ' . $table . ' NOT found</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Test 4: Check cache table structure
echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>Test 4: Check Cache Table Structure</h3>';
try {
    $sql = "SHOW COLUMNS FROM {local_alx_api_cache}";
    $columns = $DB->get_records_sql($sql);
    
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th style="padding: 8px; background: #e9ecef;">Field</th><th style="padding: 8px; background: #e9ecef;">Type</th></tr>';
    foreach ($columns as $column) {
        echo '<tr>';
        echo '<td style="padding: 8px;">' . $column->field . '</td>';
        echo '<td style="padding: 8px;">' . $column->type . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Check for correct field names
    $required_fields = ['id', 'companyid', 'cache_key', 'cache_data', 'timecreated'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        $found = false;
        foreach ($columns as $column) {
            if ($column->field === $field) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing_fields[] = $field;
        }
    }
    
    if (empty($missing_fields)) {
        echo '<p style="color: green;">✅ All required fields exist</p>';
    } else {
        echo '<p style="color: red;">❌ Missing fields: ' . implode(', ', $missing_fields) . '</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Test 5: Get companies list
echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>Test 5: Get Companies</h3>';
try {
    $companies = $DB->get_records('company', null, 'name', 'id, name');
    
    if (!empty($companies)) {
        echo '<p style="color: green;">✅ Found ' . count($companies) . ' companies</p>';
        echo '<form method="get" action="">';
        echo '<select name="companyid" onchange="this.form.submit()" style="padding: 8px; width: 300px;">';
        echo '<option value="0">Select a company...</option>';
        foreach ($companies as $company) {
            $selected = ($company->id == $companyid) ? 'selected' : '';
            echo '<option value="' . $company->id . '" ' . $selected . '>' . htmlspecialchars($company->name) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    } else {
        echo '<p style="color: orange;">⚠️ No companies found</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Test 6: Test cache stats function (if company selected)
if ($companyid > 0) {
    echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Test 6: Test local_alx_report_api_get_cache_stats()</h3>';
    
    try {
        $company = $DB->get_record('company', ['id' => $companyid], 'name');
        echo '<p><strong>Testing for company:</strong> ' . htmlspecialchars($company->name) . ' (ID: ' . $companyid . ')</p>';
        
        // Test the function
        $stats = local_alx_report_api_get_cache_stats($companyid);
        
        echo '<p style="color: green;">✅ Function executed successfully!</p>';
        echo '<h4>Results:</h4>';
        echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th style="padding: 8px; background: #e9ecef;">Property</th><th style="padding: 8px; background: #e9ecef;">Value</th></tr>';
        echo '<tr><td style="padding: 8px;">total_entries</td><td style="padding: 8px;">' . $stats->total_entries . '</td></tr>';
        echo '<tr><td style="padding: 8px;">last_update</td><td style="padding: 8px;">' . ($stats->last_update ? date('Y-m-d H:i:s', $stats->last_update) : 'NULL') . '</td></tr>';
        echo '<tr><td style="padding: 8px;">expires_at</td><td style="padding: 8px;">' . ($stats->expires_at ? date('Y-m-d H:i:s', $stats->expires_at) : 'NULL') . '</td></tr>';
        echo '<tr><td style="padding: 8px;">is_expired</td><td style="padding: 8px;">' . ($stats->is_expired ? 'true' : 'false') . '</td></tr>';
        echo '<tr><td style="padding: 8px;">cache_enabled</td><td style="padding: 8px;">' . $stats->cache_enabled . '</td></tr>';
        echo '</table>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
        echo '<pre style="background: #fff3cd; padding: 10px; border-radius: 4px;">';
        echo 'Error Type: ' . get_class($e) . "\n";
        echo 'Error Message: ' . $e->getMessage() . "\n";
        echo 'Error File: ' . $e->getFile() . "\n";
        echo 'Error Line: ' . $e->getLine() . "\n";
        echo "\nStack Trace:\n" . $e->getTraceAsString();
        echo '</pre>';
    }
    echo '</div>';
    
    // Test 7: Test cache clear function
    echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Test 7: Test local_alx_report_api_cache_clear_company()</h3>';
    
    $test_clear = optional_param('test_clear', 0, PARAM_INT);
    
    if ($test_clear) {
        try {
            $cleared = local_alx_report_api_cache_clear_company($companyid);
            echo '<p style="color: green;">✅ Function executed successfully!</p>';
            echo '<p><strong>Cache entries cleared:</strong> ' . $cleared . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
            echo '<pre style="background: #fff3cd; padding: 10px; border-radius: 4px;">';
            echo 'Error Type: ' . get_class($e) . "\n";
            echo 'Error Message: ' . $e->getMessage() . "\n";
            echo 'Error File: ' . $e->getFile() . "\n";
            echo 'Error Line: ' . $e->getLine() . "\n";
            echo "\nStack Trace:\n" . $e->getTraceAsString();
            echo '</pre>';
        }
    } else {
        echo '<p>Click the button below to test the cache clear function:</p>';
        echo '<form method="get" action="" onsubmit="return confirm(\'Are you sure you want to clear cache for this company?\');">';
        echo '<input type="hidden" name="companyid" value="' . $companyid . '">';
        echo '<input type="hidden" name="test_clear" value="1">';
        echo '<button type="submit" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Test Cache Clear</button>';
        echo '</form>';
    }
    echo '</div>';
    
    // Test 8: Show actual cache records
    echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Test 8: Show Cache Records</h3>';
    try {
        $cache_records = $DB->get_records('local_alx_api_cache', ['companyid' => $companyid], 'timecreated DESC', '*', 0, 10);
        
        if (!empty($cache_records)) {
            echo '<p style="color: green;">✅ Found ' . count($cache_records) . ' cache records (showing first 10)</p>';
            echo '<table border="1" style="border-collapse: collapse; width: 100%; font-size: 12px;">';
            echo '<tr>';
            echo '<th style="padding: 8px; background: #e9ecef;">ID</th>';
            echo '<th style="padding: 8px; background: #e9ecef;">Cache Key</th>';
            echo '<th style="padding: 8px; background: #e9ecef;">Created</th>';
            echo '<th style="padding: 8px; background: #e9ecef;">Age</th>';
            echo '</tr>';
            foreach ($cache_records as $record) {
                $age = time() - $record->timecreated;
                $age_minutes = round($age / 60);
                echo '<tr>';
                echo '<td style="padding: 8px;">' . $record->id . '</td>';
                echo '<td style="padding: 8px; font-size: 10px;">' . substr($record->cache_key, 0, 50) . '...</td>';
                echo '<td style="padding: 8px;">' . date('Y-m-d H:i:s', $record->timecreated) . '</td>';
                echo '<td style="padding: 8px;">' . $age_minutes . ' min</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color: orange;">⚠️ No cache records found for this company</p>';
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';
}

// Summary
echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">';
echo '<h3>📝 Summary</h3>';
echo '<p>If all tests pass with ✅, the cache management functions should work correctly.</p>';
echo '<p>If you see any ❌ errors, please share the error details for debugging.</p>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
