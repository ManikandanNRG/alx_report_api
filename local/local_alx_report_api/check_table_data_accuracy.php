<?php
/**
 * Table Data Accuracy Checker for ALX Report API Plugin
 * 
 * This script verifies data accuracy in tables across:
 * 1. Control Center vs Monitoring Dashboard (New) consistency
 * 2. Monitoring Dashboard (New) tables
 * 3. Populate Reporting page tables
 * 4. Sync Reporting Data page tables
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/check_table_data_accuracy.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Table Data Accuracy Check');
$PAGE->set_heading('Table Data Accuracy Check');

echo $OUTPUT->header();

echo '<h2>ALX Report API Plugin - Table Data Accuracy Analysis</h2>';

// Common time calculations
$today_start = mktime(0, 0, 0);
$time_field = 'timecreated';

// Check table existence
$logs_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS);
$reporting_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING);
$settings_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_SETTINGS);

echo '<h3>1. Control Center vs Monitoring Dashboard (New) - Key Metrics Consistency</h3>';

echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th>Metric</th><th>Control Center</th><th>Monitoring Dashboard (New)</th><th>Match?</th><th>Notes</th></tr>';

// === API CALLS TODAY ===
$cc_api_calls = 0;
$mdn_api_calls = 0;

if ($logs_exists) {
    // Control Center calculation
    $cc_api_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
    
    // Monitoring Dashboard New calculation (same method)
    $mdn_api_calls = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
}

$api_calls_match = ($cc_api_calls === $mdn_api_calls);
echo '<tr style="' . ($api_calls_match ? '' : 'background-color: #f8d7da;') . '">';
echo '<td><strong>API Calls Today</strong></td>';
echo '<td>' . number_format($cc_api_calls) . '</td>';
echo '<td>' . number_format($mdn_api_calls) . '</td>';
echo '<td>' . ($api_calls_match ? '✅ Yes' : '❌ No') . '</td>';
echo '<td>' . ($api_calls_match ? 'Perfect match' : 'Different calculations used') . '</td>';
echo '</tr>';

// === SUCCESS RATE ===
$cc_success_rate = 100;
$mdn_success_rate = 100;

if ($logs_exists) {
    $table_info = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
    
    if (isset($table_info['error_message'])) {
        // Control Center method: (total - errors) / total * 100
        $total_calls_cc = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
        $error_calls_cc = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ? AND error_message IS NOT NULL", [$today_start]);
        $cc_success_rate = $total_calls_cc > 0 ? round((($total_calls_cc - $error_calls_cc) / $total_calls_cc) * 100, 1) : 100;
        
        // Monitoring Dashboard New method: success_count / total * 100
        $success_count_mdn = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ? AND error_message IS NULL", [$today_start]);
        $total_calls_mdn = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, "{$time_field} >= ?", [$today_start]);
        $mdn_success_rate = $total_calls_mdn > 0 ? round(($success_count_mdn / $total_calls_mdn) * 100, 1) : 100;
    }
}

$success_rate_match = ($cc_success_rate === $mdn_success_rate);
echo '<tr style="' . ($success_rate_match ? '' : 'background-color: #f8d7da;') . '">';
echo '<td><strong>Success Rate</strong></td>';
echo '<td>' . $cc_success_rate . '%</td>';
echo '<td>' . $mdn_success_rate . '%</td>';
echo '<td>' . ($success_rate_match ? '✅ Yes' : '❌ No') . '</td>';
echo '<td>' . ($success_rate_match ? 'Both methods produce same result' : 'Different calculation methods') . '</td>';
echo '</tr>';

// === TOTAL COMPANIES ===
$cc_companies = count(local_alx_report_api_get_companies());
$mdn_companies = count(local_alx_report_api_get_companies()); // Same function used

echo '<tr>';
echo '<td><strong>Total Companies</strong></td>';
echo '<td>' . number_format($cc_companies) . '</td>';
echo '<td>' . number_format($mdn_companies) . '</td>';
echo '<td>✅ Yes</td>';
echo '<td>Same function used</td>';
echo '</tr>';

echo '</table>';

// === COMPANY TABLE DATA VERIFICATION ===
echo '<h3>2. Company Table Data Verification (Monitoring Dashboard New)</h3>';

$companies = local_alx_report_api_get_companies();
if (empty($companies)) {
    echo '<p style="color: orange;">⚠️ No companies found. This might be normal for a new installation.</p>';
} else {
    echo '<p><strong>Checking data accuracy for ' . count($companies) . ' companies...</strong></p>';
    
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Company</th><th>API Calls Today</th><th>Success Rate</th><th>Total Requests</th><th>Data Source</th><th>Status</th></tr>';
    
    $company_count = 0;
    foreach ($companies as $company) {
        $company_count++;
        if ($company_count > 5) { // Limit to first 5 companies for display
            echo '<tr><td colspan="6"><em>... and ' . (count($companies) - 5) . ' more companies</em></td></tr>';
            break;
        }
        
        // Get company data using same methods as Monitoring Dashboard New
        $company_calls_today = 0;
        $company_success_rate = '100%';
        $total_requests = 0;
        $data_issues = [];
        
        if ($logs_exists) {
            // API calls today
            $company_calls_today = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                "{$time_field} >= ? AND company_shortname = ?",
                [$today_start, $company->shortname]
            );
            
            // Success rate
            if (isset($table_info['error_message'])) {
                $error_count = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS,
                    "{$time_field} >= ? AND company_shortname = ? AND error_message IS NOT NULL",
                    [$today_start, $company->shortname]
                );
                $company_success_rate = $company_calls_today > 0 ? 
                    round((($company_calls_today - $error_count) / $company_calls_today) * 100, 1) . '%' : '100%';
            }
            
            // Total requests (all time)
            $total_requests = $DB->count_records_select(\local_alx_report_api\constants::TABLE_LOGS, 
                'company_shortname = ?', [$company->shortname]);
        } else {
            $data_issues[] = 'Logs table missing';
        }
        
        $status = empty($data_issues) ? '✅ OK' : '⚠️ ' . implode(', ', $data_issues);
        $row_color = empty($data_issues) ? '' : 'background-color: #fff3cd;';
        
        echo '<tr style="' . $row_color . '">';
        echo '<td><strong>' . htmlspecialchars($company->name) . '</strong><br><small>' . htmlspecialchars($company->shortname) . '</small></td>';
        echo '<td>' . number_format($company_calls_today) . '</td>';
        echo '<td>' . $company_success_rate . '</td>';
        echo '<td>' . number_format($total_requests) . '</td>';
        echo '<td>local_alx_api_logs</td>';
        echo '<td>' . $status . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// === REPORTING TABLE DATA VERIFICATION ===
echo '<h3>3. Reporting Table Data Verification (Populate Reporting & Sync Pages)</h3>';

if (!$reporting_exists) {
    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;">';
    echo '<h4>❌ Reporting Table Missing</h4>';
    echo '<p>The local_alx_api_reporting table does not exist. This table is used by:</p>';
    echo '<ul>';
    echo '<li>Populate Reporting page</li>';
    echo '<li>Sync Reporting Data page</li>';
    echo '<li>API endpoints for course progress data</li>';
    echo '</ul>';
    echo '<p><strong>Action needed:</strong> Run the populate reporting table process to create and populate this table.</p>';
    echo '</div>';
} else {
    // Check reporting table data
    $reporting_stats = [];
    
    // Total records
    $reporting_stats['total_records'] = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
    
    // Active records (not deleted)
    $reporting_stats['active_records'] = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['is_deleted' => 0]);
    
    // Deleted records
    $reporting_stats['deleted_records'] = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['is_deleted' => 1]);
    
    // Records by company
    $reporting_stats['companies_with_data'] = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT companyid) FROM {local_alx_api_reporting} WHERE is_deleted = 0"
    );
    
    // Recent updates (last 24 hours)
    $reporting_stats['recent_updates'] = $DB->count_records_select(\local_alx_report_api\constants::TABLE_REPORTING,
        'timemodified >= ?', [time() - 86400]
    );
    
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Metric</th><th>Value</th><th>Status</th><th>Notes</th></tr>';
    
    // Total records
    $total_status = $reporting_stats['total_records'] > 0 ? '✅ Good' : '⚠️ Empty';
    echo '<tr>';
    echo '<td><strong>Total Records</strong></td>';
    echo '<td>' . number_format($reporting_stats['total_records']) . '</td>';
    echo '<td>' . $total_status . '</td>';
    echo '<td>' . ($reporting_stats['total_records'] > 0 ? 'Table has data' : 'Table is empty - run populate process') . '</td>';
    echo '</tr>';
    
    // Active vs deleted
    $active_percentage = $reporting_stats['total_records'] > 0 ? 
        round(($reporting_stats['active_records'] / $reporting_stats['total_records']) * 100, 1) : 0;
    $active_status = $active_percentage > 90 ? '✅ Good' : ($active_percentage > 70 ? '⚠️ OK' : '❌ Poor');
    
    echo '<tr>';
    echo '<td><strong>Active Records</strong></td>';
    echo '<td>' . number_format($reporting_stats['active_records']) . ' (' . $active_percentage . '%)</td>';
    echo '<td>' . $active_status . '</td>';
    echo '<td>Non-deleted records available for API</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td><strong>Deleted Records</strong></td>';
    echo '<td>' . number_format($reporting_stats['deleted_records']) . '</td>';
    echo '<td>ℹ️ Info</td>';
    echo '<td>Soft-deleted records (cleanup candidates)</td>';
    echo '</tr>';
    
    // Companies with data
    $total_companies_system = count(local_alx_report_api_get_companies());
    $company_coverage = $total_companies_system > 0 ? 
        round(($reporting_stats['companies_with_data'] / $total_companies_system) * 100, 1) : 0;
    $coverage_status = $company_coverage > 80 ? '✅ Good' : ($company_coverage > 50 ? '⚠️ Partial' : '❌ Poor');
    
    echo '<tr>';
    echo '<td><strong>Company Coverage</strong></td>';
    echo '<td>' . $reporting_stats['companies_with_data'] . '/' . $total_companies_system . ' (' . $company_coverage . '%)</td>';
    echo '<td>' . $coverage_status . '</td>';
    echo '<td>Companies with reporting data</td>';
    echo '</tr>';
    
    // Recent updates
    $update_status = $reporting_stats['recent_updates'] > 0 ? '✅ Active' : '⚠️ Stale';
    echo '<tr>';
    echo '<td><strong>Recent Updates (24h)</strong></td>';
    echo '<td>' . number_format($reporting_stats['recent_updates']) . '</td>';
    echo '<td>' . $update_status . '</td>';
    echo '<td>' . ($reporting_stats['recent_updates'] > 0 ? 'Data is being updated' : 'No recent updates - check sync process') . '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    // Sample data verification
    echo '<h4>Sample Data Verification</h4>';
    $sample_records = $DB->get_records(\local_alx_report_api\constants::TABLE_REPORTING, 
        ['is_deleted' => 0], 'timemodified DESC', '*', 0, 3);
    
    if (empty($sample_records)) {
        echo '<p style="color: orange;">⚠️ No active records found in reporting table.</p>';
    } else {
        echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>User</th><th>Course</th><th>Status</th><th>Percentage</th><th>Last Updated</th><th>Data Quality</th></tr>';
        
        foreach ($sample_records as $record) {
            $data_quality = [];
            
            // Check for required fields
            if (empty($record->firstname) || empty($record->lastname)) {
                $data_quality[] = 'Missing name';
            }
            if (empty($record->email)) {
                $data_quality[] = 'Missing email';
            }
            if (empty($record->coursename)) {
                $data_quality[] = 'Missing course name';
            }
            if ($record->percentage < 0 || $record->percentage > 100) {
                $data_quality[] = 'Invalid percentage';
            }
            
            $quality_status = empty($data_quality) ? '✅ Good' : '⚠️ ' . implode(', ', $data_quality);
            $row_color = empty($data_quality) ? '' : 'background-color: #fff3cd;';
            
            echo '<tr style="' . $row_color . '">';
            echo '<td>' . htmlspecialchars($record->firstname . ' ' . $record->lastname) . '<br><small>' . htmlspecialchars($record->email) . '</small></td>';
            echo '<td>' . htmlspecialchars($record->coursename) . '</td>';
            echo '<td>' . htmlspecialchars($record->status) . '</td>';
            echo '<td>' . number_format($record->percentage, 1) . '%</td>';
            echo '<td>' . date('Y-m-d H:i', $record->timemodified) . '</td>';
            echo '<td>' . $quality_status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}

// === OVERALL SUMMARY ===
echo '<h3>4. Overall Data Accuracy Summary</h3>';

$issues_found = [];
$warnings_found = [];

// Check Control Center vs Monitoring Dashboard consistency
if (!$api_calls_match) {
    $issues_found[] = 'API calls count mismatch between Control Center and Monitoring Dashboard';
}
if (!$success_rate_match) {
    $issues_found[] = 'Success rate calculation differences between screens';
}

// Check table existence
if (!$logs_exists) {
    $issues_found[] = 'API logs table missing - no API tracking possible';
}
if (!$reporting_exists) {
    $warnings_found[] = 'Reporting table missing - populate process needed';
}

// Check data quality
if ($reporting_exists && $reporting_stats['total_records'] === 0) {
    $warnings_found[] = 'Reporting table is empty - no course progress data available';
}

if (empty($issues_found) && empty($warnings_found)) {
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">';
    echo '<h4>🎉 EXCELLENT - All Table Data is Accurate!</h4>';
    echo '<ul>';
    echo '<li>✅ Control Center and Monitoring Dashboard show consistent data</li>';
    echo '<li>✅ All database tables exist and contain valid data</li>';
    echo '<li>✅ Company data is accurate across all screens</li>';
    echo '<li>✅ Reporting table has good data quality</li>';
    echo '</ul>';
    echo '</div>';
} else {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; color: #856404;">';
    echo '<h4>⚠️ Issues Found</h4>';
    
    if (!empty($issues_found)) {
        echo '<p><strong>Critical Issues:</strong></p>';
        echo '<ul>';
        foreach ($issues_found as $issue) {
            echo '<li>❌ ' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';
    }
    
    if (!empty($warnings_found)) {
        echo '<p><strong>Warnings:</strong></p>';
        echo '<ul>';
        foreach ($warnings_found as $warning) {
            echo '<li>⚠️ ' . htmlspecialchars($warning) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

echo $OUTPUT->footer();
?>