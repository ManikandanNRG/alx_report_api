<?php
/**
 * Simple Cache Test - Debugging Version
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../config.php');

// Check if user is logged in
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/alx_report_api/cache_test_simple.php');
$PAGE->set_context($context);
$PAGE->set_title('Simple Cache Test');
$PAGE->set_heading('Simple Cache Test');

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';

echo '<h2>ALX Report API - Simple Cache Test</h2>';

try {
    // Test 1: Check if cache table exists
    echo '<h3>Test 1: Cache Table Check</h3>';
    if ($DB->get_manager()->table_exists('local_alx_api_cache')) {
        echo '<p style="color: green;">✅ Cache table exists</p>';
        
        // Get basic stats
        $total_entries = $DB->count_records('local_alx_api_cache');
        echo "<p>📊 Total cache entries: {$total_entries}</p>";
        
        if ($total_entries > 0) {
            // Show sample entries
            $sample_entries = $DB->get_records('local_alx_api_cache', null, 'id DESC', '*', 0, 5);
            echo '<h4>Sample Cache Entries:</h4>';
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
            echo '<tr><th>ID</th><th>Cache Key</th><th>Company ID</th><th>Created</th><th>Expires</th><th>Hit Count</th></tr>';
            foreach ($sample_entries as $entry) {
                echo '<tr>';
                echo '<td>' . $entry->id . '</td>';
                echo '<td>' . htmlspecialchars(substr($entry->cache_key, 0, 30)) . '...</td>';
                echo '<td>' . $entry->companyid . '</td>';
                echo '<td>' . date('Y-m-d H:i:s', $entry->cache_timestamp) . '</td>';
                echo '<td>' . date('Y-m-d H:i:s', $entry->expires_at) . '</td>';
                echo '<td>' . $entry->hit_count . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
    } else {
        echo '<p style="color: red;">❌ Cache table does not exist</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error checking cache table: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

try {
    // Test 2: Check if lib.php functions are available
    echo '<h3>Test 2: Function Availability Check</h3>';
    
    require_once($CFG->dirroot . '/local/alx_report_api/lib.php');
    
    if (function_exists('local_alx_report_api_cache_get')) {
        echo '<p style="color: green;">✅ local_alx_report_api_cache_get function exists</p>';
    } else {
        echo '<p style="color: red;">❌ local_alx_report_api_cache_get function missing</p>';
    }
    
    if (function_exists('local_alx_report_api_cache_set')) {
        echo '<p style="color: green;">✅ local_alx_report_api_cache_set function exists</p>';
    } else {
        echo '<p style="color: red;">❌ local_alx_report_api_cache_set function missing</p>';
    }
    
    if (function_exists('local_alx_report_api_cache_cleanup')) {
        echo '<p style="color: green;">✅ local_alx_report_api_cache_cleanup function exists</p>';
    } else {
        echo '<p style="color: red;">❌ local_alx_report_api_cache_cleanup function missing</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error loading lib.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

try {
    // Test 3: Simple cache operations test
    echo '<h3>Test 3: Simple Cache Operations</h3>';
    
    if (function_exists('local_alx_report_api_cache_set') && function_exists('local_alx_report_api_cache_get')) {
        $test_key = 'simple_test_' . time();
        $test_data = ['message' => 'Hello Cache!', 'timestamp' => time()];
        $company_id = 1;
        
        // Test cache set
        $set_result = local_alx_report_api_cache_set($test_key, $company_id, $test_data, 3600);
        if ($set_result) {
            echo '<p style="color: green;">✅ Cache set successful</p>';
            
            // Test cache get
            $get_result = local_alx_report_api_cache_get($test_key, $company_id);
            if ($get_result && $get_result['message'] === 'Hello Cache!') {
                echo '<p style="color: green;">✅ Cache get successful - data matches</p>';
            } else {
                echo '<p style="color: orange;">⚠️ Cache get failed or data mismatch</p>';
            }
            
            // Cleanup
            $DB->delete_records('local_alx_api_cache', ['cache_key' => $test_key]);
            echo '<p style="color: blue;">🧹 Test data cleaned up</p>';
            
        } else {
            echo '<p style="color: red;">❌ Cache set failed</p>';
        }
    } else {
        echo '<p style="color: red;">❌ Cache functions not available</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error in cache operations: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

try {
    // Test 4: Companies check
    echo '<h3>Test 4: Companies Check</h3>';
    
    if (function_exists('local_alx_report_api_get_companies')) {
        $companies = local_alx_report_api_get_companies();
        echo '<p style="color: green;">✅ Found ' . count($companies) . ' companies</p>';
        
        if (count($companies) > 0) {
            echo '<p>First few companies:</p>';
            echo '<ul>';
            $count = 0;
            foreach ($companies as $company) {
                if ($count >= 3) break;
                echo '<li>ID: ' . $company->id . ' - ' . htmlspecialchars($company->name) . '</li>';
                $count++;
            }
            echo '</ul>';
        }
    } else {
        echo '<p style="color: red;">❌ local_alx_report_api_get_companies function not found</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error getting companies: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h3>Conclusion</h3>';
echo '<p>If all tests above show green checkmarks, the cache system is working correctly.</p>';
echo '<p><strong>Next Step:</strong> If this simple test works, we can identify what\'s causing the issue in the full verification tool.</p>';

echo '</div>';

echo $OUTPUT->footer();
?> 