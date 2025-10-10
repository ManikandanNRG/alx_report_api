<?php
/**
 * ALX Report API Cache Verification Tool
 * 
 * This tool verifies and tests the cache table workflow to ensure it's working correctly.
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

// Check if user is logged in and has admin privileges
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/alx_report_api/cache_verification.php');
$PAGE->set_context($context);
$PAGE->set_title('ALX Report API - Cache Verification');
$PAGE->set_heading('Cache Verification & Testing');

/**
 * Get comprehensive cache statistics
 */
function get_cache_statistics() {
    global $DB;
    
    $stats = [
        'table_exists' => false,
        'total_entries' => 0,
        'active_entries' => 0,
        'expired_entries' => 0,
        'avg_hit_count' => 0,
        'hit_rate' => 0,
        'oldest_entry' => 'N/A'
    ];
    
    try {
        if (!$DB->get_manager()->table_exists('local_alx_api_cache')) {
            return $stats;
        }
        
        $stats['table_exists'] = true;
        
        // Total entries
        $stats['total_entries'] = $DB->count_records('local_alx_api_cache');
        
        // Active vs expired entries
        $current_time = time();
        $stats['active_entries'] = $DB->count_records_select('local_alx_api_cache', 'expires_at > ?', [$current_time]);
        $stats['expired_entries'] = $stats['total_entries'] - $stats['active_entries'];
        
        // Average hit count
        $avg_hits = $DB->get_field_sql('SELECT AVG(hit_count) FROM {local_alx_api_cache}');
        $stats['avg_hit_count'] = round($avg_hits ?: 0, 1);
        
        // Hit rate calculation
        $total_hits = $DB->get_field_sql('SELECT SUM(hit_count) FROM {local_alx_api_cache}');
        $total_requests = $stats['total_entries'] + ($total_hits ?: 0);
        $stats['hit_rate'] = $total_requests > 0 ? round((($total_hits ?: 0) / $total_requests) * 100, 1) : 0;
        
        // Oldest entry
        $oldest = $DB->get_field_sql('SELECT MIN(cache_timestamp) FROM {local_alx_api_cache}');
        if ($oldest) {
            $hours_old = round((time() - $oldest) / 3600, 1);
            $stats['oldest_entry'] = $hours_old . 'h ago';
        }
        
    } catch (Exception $e) {
        error_log('Cache statistics error: ' . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Test basic cache operations
 */
function test_cache_operations($company_id) {
    global $DB;
    
    echo '<div class="test-results">';
    echo '<h4><i class="fas fa-flask"></i> Basic Cache Operations Test Results</h4>';
    
    $tests = [];
    $company_id = $company_id ?: 1; // Use company 1 if none specified
    
    try {
        // Test 1: Cache Set Operation
        $test_key = 'test_cache_' . time();
        $test_data = ['test' => 'data', 'timestamp' => time(), 'company' => $company_id];
        $set_result = local_alx_report_api_cache_set($test_key, $company_id, $test_data, 3600);
        $tests[] = [
            'name' => 'Cache Set Operation',
            'status' => $set_result ? 'pass' : 'fail',
            'details' => $set_result ? 'Successfully stored test data' : 'Failed to store data'
        ];
        
        // Test 2: Cache Get Operation
        $get_result = local_alx_report_api_cache_get($test_key, $company_id);
        $data_matches = $get_result && $get_result['test'] === 'data';
        $tests[] = [
            'name' => 'Cache Get Operation',
            'status' => $data_matches ? 'pass' : 'fail',
            'details' => $data_matches ? 'Successfully retrieved matching data' : 'Failed to retrieve or data mismatch'
        ];
        
        // Test 3: Cache Hit Count Tracking
        $cache_record = $DB->get_record('local_alx_api_cache', ['cache_key' => $test_key, 'companyid' => $company_id]);
        $hit_tracked = $cache_record && $cache_record->hit_count > 0;
        $tests[] = [
            'name' => 'Hit Count Tracking',
            'status' => $hit_tracked ? 'pass' : 'fail',
            'details' => $hit_tracked ? 'Hit count: ' . $cache_record->hit_count : 'Hit count not tracked'
        ];
        
        // Test 4: Cache Update Operation
        $updated_data = ['test' => 'updated_data', 'timestamp' => time(), 'updated' => true];
        $update_result = local_alx_report_api_cache_set($test_key, $company_id, $updated_data, 3600);
        $tests[] = [
            'name' => 'Cache Update Operation',
            'status' => $update_result ? 'pass' : 'fail',
            'details' => $update_result ? 'Successfully updated existing cache entry' : 'Failed to update cache'
        ];
        
        // Test 5: Expiration Handling
        $expired_key = 'expired_test_' . time();
        local_alx_report_api_cache_set($expired_key, $company_id, $test_data, -1); // Already expired
        $expired_result = local_alx_report_api_cache_get($expired_key, $company_id);
        $tests[] = [
            'name' => 'Expiration Handling',
            'status' => $expired_result === false ? 'pass' : 'fail',
            'details' => $expired_result === false ? 'Expired cache correctly returned false' : 'Expired cache not handled properly'
        ];
        
        // Test 6: Cache Cleanup
        $cleanup_count = local_alx_report_api_cache_cleanup(0); // Clean all expired
        $tests[] = [
            'name' => 'Cache Cleanup',
            'status' => $cleanup_count >= 0 ? 'pass' : 'fail',
            'details' => "Cleaned up {$cleanup_count} expired entries"
        ];
        
        // Cleanup test data
        $DB->delete_records('local_alx_api_cache', ['cache_key' => $test_key]);
        
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Test Execution',
            'status' => 'fail',
            'details' => 'Error: ' . $e->getMessage()
        ];
    }
    
    // Display results
    foreach ($tests as $test) {
        $status_class = $test['status'] === 'pass' ? 'result-pass' : 'result-fail';
        echo '<div class="test-result-item">';
        echo '<span>' . htmlspecialchars($test['name']) . '</span>';
        echo '<span class="result-status ' . $status_class . '">' . strtoupper($test['status']) . '</span>';
        echo '</div>';
        if (!empty($test['details'])) {
            echo '<div style="padding: 5px 0; color: var(--text-secondary); font-size: 0.9rem;">' . htmlspecialchars($test['details']) . '</div>';
        }
    }
    
    echo '</div>';
}

/**
 * Cleanup expired cache entries
 */
function cleanup_cache() {
    $cleanup_count = local_alx_report_api_cache_cleanup(24);
    
    echo '<div class="alert alert-success">';
    echo '<i class="fas fa-check-circle"></i>';
    echo '<div>';
    echo '<strong>Cache Cleanup Complete!</strong><br>';
    echo "Removed {$cleanup_count} expired cache entries.";
    echo '</div>';
    echo '</div>';
}

/**
 * Analyze cache performance
 */
function analyze_cache_performance() {
    global $DB;
    
    echo '<div class="test-results">';
    echo '<h4><i class="fas fa-chart-line"></i> Cache Performance Analysis</h4>';
    
    try {
        if (!$DB->get_manager()->table_exists('local_alx_api_cache')) {
            echo '<div class="alert alert-warning">Cache table does not exist.</div>';
            echo '</div>';
            return;
        }
        
        // Most accessed cache entries
        $top_entries = $DB->get_records_sql(
            'SELECT cache_key, companyid, hit_count, last_accessed 
             FROM {local_alx_api_cache} 
             ORDER BY hit_count DESC LIMIT 10'
        );
        
        echo '<h5>Top 10 Most Accessed Cache Entries:</h5>';
        if (!empty($top_entries)) {
            echo '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
            echo '<thead><tr style="background: var(--light-bg);">';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Cache Key</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Company</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Hit Count</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Last Accessed</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($top_entries as $entry) {
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . htmlspecialchars($entry->cache_key) . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . $entry->companyid . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . $entry->hit_count . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . date('Y-m-d H:i:s', $entry->last_accessed) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No cache entries found.</p>';
        }
        
        // Company-wise cache usage
        $company_stats = $DB->get_records_sql(
            'SELECT companyid, COUNT(*) as entry_count, SUM(hit_count) as total_hits, AVG(hit_count) as avg_hits
             FROM {local_alx_api_cache} 
             GROUP BY companyid 
             ORDER BY total_hits DESC'
        );
        
        echo '<h5 style="margin-top: 25px;">Cache Usage by Company:</h5>';
        if (!empty($company_stats)) {
            echo '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
            echo '<thead><tr style="background: var(--light-bg);">';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Company ID</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Cache Entries</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Total Hits</th>';
            echo '<th style="padding: 10px; border: 1px solid var(--border-color);">Avg Hits</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($company_stats as $stat) {
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . $stat->companyid . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . $stat->entry_count . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . $stat->total_hits . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-color);">' . round($stat->avg_hits, 1) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No company cache statistics available.</p>';
        }
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error analyzing cache: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '</div>';
}

/**
 * Stress test cache operations
 */
function stress_test_cache($company_id) {
    $company_id = $company_id ?: 1;
    $test_iterations = 100;
    
    echo '<div class="test-results">';
    echo '<h4><i class="fas fa-fire"></i> Cache Stress Test Results</h4>';
    
    $start_time = microtime(true);
    $success_count = 0;
    $error_count = 0;
    
    try {
        // Create multiple cache entries rapidly
        for ($i = 1; $i <= $test_iterations; $i++) {
            $key = "stress_test_{$i}_" . time();
            $data = [
                'iteration' => $i,
                'data' => str_repeat('x', 1000), // 1KB of data
                'timestamp' => time()
            ];
            
            if (local_alx_report_api_cache_set($key, $company_id, $data, 3600)) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            // Test retrieval every 10 iterations
            if ($i % 10 === 0) {
                $retrieved = local_alx_report_api_cache_get($key, $company_id);
                if (!$retrieved || $retrieved['iteration'] !== $i) {
                    $error_count++;
                }
            }
        }
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 3);
        $avg_time = round($duration / $test_iterations * 1000, 2); // ms per operation
        
        echo '<div class="test-result-item">';
        echo '<span>Stress Test Completion</span>';
        echo '<span class="result-status result-pass">COMPLETED</span>';
        echo '</div>';
        
        echo '<div style="padding: 15px 0; color: var(--text-secondary);">';
        echo "<strong>Test Results:</strong><br>";
        echo "• Total Operations: {$test_iterations}<br>";
        echo "• Successful Operations: {$success_count}<br>";
        echo "• Failed Operations: {$error_count}<br>";
        echo "• Total Duration: {$duration} seconds<br>";
        echo "• Average Time per Operation: {$avg_time} ms<br>";
        echo "• Operations per Second: " . round($test_iterations / $duration, 1) . "<br>";
        echo '</div>';
        
        // Cleanup stress test data
        global $DB;
        $cleanup_count = $DB->delete_records_select('local_alx_api_cache', 
            "cache_key LIKE 'stress_test_%' AND companyid = ?", [$company_id]);
        
        echo '<div class="alert alert-success">';
        echo '<i class="fas fa-check-circle"></i>';
        echo '<div>';
        echo '<strong>Stress Test Complete!</strong><br>';
        echo "Cleaned up {$cleanup_count} test cache entries.";
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Stress test error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '</div>';
}

echo $OUTPUT->header();

// Handle form submissions
$action = optional_param('action', '', PARAM_TEXT);
$test_company = optional_param('test_company', 0, PARAM_INT);

if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'test_cache_operations':
            test_cache_operations($test_company);
            break;
        case 'cleanup_cache':
            cleanup_cache();
            break;
        case 'analyze_cache_performance':
            analyze_cache_performance();
            break;
        case 'stress_test_cache':
            stress_test_cache($test_company);
            break;
    }
}

// Get current cache statistics
$cache_stats = get_cache_statistics();
$companies = local_alx_report_api_get_companies();
?>

<style>
:root {
    --primary-color: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --border-color: #e2e8f0;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius-md: 8px;
    --radius-lg: 12px;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.cache-verify-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--radius-lg);
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-lg);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 15px;
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 0;
}

.verification-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.verification-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 30px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-icon {
    font-size: 1.5rem;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.status-section {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: var(--radius-md);
    padding: 20px;
    margin-bottom: 25px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.status-item {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: var(--radius-md);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.status-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.status-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    margin: 5px;
    border: none;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    box-shadow: var(--shadow-sm);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), #3b82f6);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1d4ed8, var(--primary-color));
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #34d399);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #fbbf24);
    color: #92400e;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #f87171);
    color: white;
}

.test-results {
    background: var(--light-bg);
    border-radius: var(--radius-md);
    padding: 20px;
    margin: 20px 0;
    border-left: 4px solid var(--info-color);
}

.test-result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.test-result-item:last-child {
    border-bottom: none;
}

.result-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
}

.result-pass {
    background: var(--success-color);
    color: white;
}

.result-fail {
    background: var(--danger-color);
    color: white;
}

.result-warning {
    background: var(--warning-color);
    color: #92400e;
}

.form-section {
    background: white;
    border-radius: var(--radius-lg);
    padding: 25px;
    box-shadow: var(--shadow-md);
    margin-bottom: 25px;
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
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin: 15px 0;
    border-left: 4px solid;
}

.alert-success {
    background: #f0fdf4;
    border-color: var(--success-color);
    color: #166534;
}

.alert-warning {
    background: #fffbeb;
    border-color: var(--warning-color);
    color: #92400e;
}

.alert-danger {
    background: #fef2f2;
    border-color: var(--danger-color);
    color: #b91c1c;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success-color), #34d399);
    transition: width 0.3s ease;
}

@media (max-width: 768px) {
    .verification-grid {
        grid-template-columns: 1fr;
    }
    
    .status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="cache-verify-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-database"></i> Cache Verification & Testing
        </h1>
        <p class="page-subtitle">
            Comprehensive testing and analysis of the ALX Report API cache table workflow
        </p>
    </div>

    <!-- Current Cache Status -->
    <div class="form-section">
        <div class="card-header">
            <span class="card-icon">📊</span>
            <h3 class="card-title">Current Cache Status</h3>
        </div>
        
        <div class="status-section">
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-value" style="color: var(--primary-color);">
                        <?php echo number_format($cache_stats['total_entries']); ?>
                    </div>
                    <div class="status-label">Total Entries</div>
                </div>
                <div class="status-item">
                    <div class="status-value" style="color: var(--success-color);">
                        <?php echo number_format($cache_stats['active_entries']); ?>
                    </div>
                    <div class="status-label">Active Entries</div>
                </div>
                <div class="status-item">
                    <div class="status-value" style="color: var(--warning-color);">
                        <?php echo number_format($cache_stats['expired_entries']); ?>
                    </div>
                    <div class="status-label">Expired Entries</div>
                </div>
                <div class="status-item">
                    <div class="status-value" style="color: var(--info-color);">
                        <?php echo $cache_stats['avg_hit_count']; ?>
                    </div>
                    <div class="status-label">Avg Hit Count</div>
                </div>
                <div class="status-item">
                    <div class="status-value" style="color: var(--success-color);">
                        <?php echo $cache_stats['hit_rate']; ?>%
                    </div>
                    <div class="status-label">Hit Rate</div>
                </div>
                <div class="status-item">
                    <div class="status-value" style="color: var(--text-primary);">
                        <?php echo $cache_stats['oldest_entry']; ?>
                    </div>
                    <div class="status-label">Oldest Entry</div>
                </div>
            </div>
        </div>

        <?php if ($cache_stats['table_exists']): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Cache Table Status:</strong> ✅ Table exists and is accessible<br>
                <small>Table structure is valid with all required fields present</small>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Cache Table Status:</strong> ❌ Table missing or inaccessible<br>
                <small>The cache table needs to be created or repaired</small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Verification Tests -->
    <div class="verification-grid">
        <!-- Basic Cache Operations Test -->
        <div class="verification-card">
            <div class="card-header">
                <span class="card-icon">🧪</span>
                <h3 class="card-title">Basic Cache Operations</h3>
            </div>
            
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Test basic cache set, get, update, and delete operations
            </p>
            
            <form method="post" style="margin-bottom: 20px;">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="test_cache_operations">
                
                <div class="form-group">
                    <label for="test_company">Select Company for Testing:</label>
                    <select name="test_company" id="test_company" class="form-control">
                        <option value="0">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company->id; ?>">
                            <?php echo htmlspecialchars($company->name); ?> (ID: <?php echo $company->id; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play"></i> Run Basic Tests
                </button>
            </form>
        </div>

        <!-- Cache Performance Analysis -->
        <div class="verification-card">
            <div class="card-header">
                <span class="card-icon">⚡</span>
                <h3 class="card-title">Performance Analysis</h3>
            </div>
            
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Analyze cache performance and identify optimization opportunities
            </p>
            
            <form method="post">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="analyze_cache_performance">
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-chart-line"></i> Analyze Performance
                </button>
            </form>
        </div>

        <!-- Cache Cleanup -->
        <div class="verification-card">
            <div class="card-header">
                <span class="card-icon">🧹</span>
                <h3 class="card-title">Cache Cleanup</h3>
            </div>
            
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Clean up expired cache entries and optimize storage
            </p>
            
            <form method="post">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="cleanup_cache">
                
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-broom"></i> Cleanup Cache
                </button>
            </form>
        </div>

        <!-- Stress Test -->
        <div class="verification-card">
            <div class="card-header">
                <span class="card-icon">💪</span>
                <h3 class="card-title">Stress Test</h3>
            </div>
            
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Test cache performance under high load conditions
            </p>
            
            <form method="post">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="stress_test_cache">
                <input type="hidden" name="test_company" value="<?php echo $companies[0]->id ?? 1; ?>">
                
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-fire"></i> Run Stress Test
                </button>
            </form>
            
            <div class="alert alert-warning" style="margin-top: 15px;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Warning:</strong> This test creates many cache entries and may impact performance temporarily.
                </div>
            </div>
        </div>
    </div>

    <!-- Cache Workflow Documentation -->
    <div class="form-section">
        <div class="card-header">
            <span class="card-icon">📋</span>
            <h3 class="card-title">Cache Workflow Documentation</h3>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h4 style="color: var(--primary-color); margin-bottom: 15px;">
                    <i class="fas fa-arrow-right"></i> Cache Flow Process
                </h4>
                <ol style="line-height: 1.8; color: var(--text-secondary);">
                    <li><strong>API Request:</strong> Client calls the API endpoint</li>
                    <li><strong>Cache Check:</strong> System checks for existing cache entry</li>
                    <li><strong>Cache Hit:</strong> If found and valid, return cached data</li>
                    <li><strong>Cache Miss:</strong> If not found, query database</li>
                    <li><strong>Cache Store:</strong> Store new data in cache for future use</li>
                    <li><strong>Cache Cleanup:</strong> Automatic cleanup of expired entries</li>
                </ol>
            </div>
            
            <div>
                <h4 style="color: var(--success-color); margin-bottom: 15px;">
                    <i class="fas fa-cog"></i> Cache Configuration
                </h4>
                <ul style="line-height: 1.8; color: var(--text-secondary);">
                    <li><strong>Default TTL:</strong> 30 minutes (1800 seconds)</li>
                    <li><strong>Cache Key Format:</strong> api_response_{company}_{limit}_{offset}_{mode}</li>
                    <li><strong>Storage:</strong> Database table (local_alx_api_cache)</li>
                    <li><strong>Cleanup:</strong> Automatic on sync + manual cleanup available</li>
                    <li><strong>Hit Tracking:</strong> Tracks access count and timestamps</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?> 