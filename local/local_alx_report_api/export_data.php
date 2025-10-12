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
 * Export API Report Data for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Check permissions.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Get export parameters
$format = optional_param('format', 'csv', PARAM_TEXT);
$timerange = optional_param('timerange', '24h', PARAM_TEXT);
$companyid = optional_param('companyid', 0, PARAM_INT);
$page = optional_param('page', 1, PARAM_INT);
$perpage = 1000; // Records per page

global $DB;

// Calculate time range
switch ($timerange) {
    case '1h':
        $time_limit = time() - 3600;
        $time_label = 'Last Hour';
        break;
    case '24h':
        $time_limit = time() - 86400;
        $time_label = 'Last 24 Hours';
        break;
    case '7d':
        $time_limit = time() - 604800;
        $time_label = 'Last 7 Days';
        break;
    case '30d':
        $time_limit = time() - 2592000;
        $time_label = 'Last 30 Days';
        break;
    default:
        $time_limit = time() - 86400;
        $time_label = 'Last 24 Hours';
}

// If format is specified, generate the export file
if ($format === 'csv' || $format === 'json') {
    
    // Get API performance data
    $api_data = [];
    
    // Get API service information
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$service) {
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
    }
    
    // Get company data
    $companies = $DB->get_records('company');
    $company_data = [];
    foreach ($companies as $company) {
        $company_data[] = [
            'id' => $company->id,
            'name' => $company->name,
            'shortname' => $company->shortname,
            'timecreated' => date('Y-m-d H:i:s', $company->timecreated)
        ];
    }
    
    // Get API tokens
    $tokens = $DB->get_records('external_tokens');
    $token_data = [];
    foreach ($tokens as $token) {
        $token_data[] = [
            'id' => $token->id,
            'token' => substr($token->token, 0, 8) . '...',
            'userid' => $token->userid,
            'externalserviceid' => $token->externalserviceid,
            'timecreated' => date('Y-m-d H:i:s', $token->timecreated)
        ];
    }
    
    // Get reporting data if table exists
    $reporting_data = [];
    $total_records = 0;
    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
        try {
            // Build WHERE clause
            $where_conditions = ['timecreated > ?'];
            $params = [$time_limit];
            
            // Add company filter if specified
            if ($companyid > 0) {
                $where_conditions[] = 'companyid = ?';
                $params[] = $companyid;
            }
            
            $where_sql = implode(' AND ', $where_conditions);
            
            // Get total count for pagination
            $total_records = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {local_alx_api_reporting} WHERE {$where_sql}",
                $params
            );
            
            // Calculate offset for pagination
            $offset = ($page - 1) * $perpage;
            
            // Get paginated records
            $reports = $DB->get_records_sql("
                SELECT * FROM {local_alx_api_reporting} 
                WHERE {$where_sql}
                ORDER BY timecreated DESC 
                LIMIT {$perpage} OFFSET {$offset}
            ", $params);
            
            foreach ($reports as $report) {
                $reporting_data[] = [
                    'id' => $report->id,
                    'userid' => $report->userid,
                    'companyid' => $report->companyid,
                    'courseid' => $report->courseid,
                    'firstname' => $report->firstname ?? '',
                    'lastname' => $report->lastname ?? '',
                    'email' => $report->email ?? '',
                    'coursename' => $report->coursename ?? '',
                    'timecompleted' => $report->timecompleted ? date('Y-m-d H:i:s', $report->timecompleted) : 'Not completed',
                    'timestarted' => $report->timestarted ? date('Y-m-d H:i:s', $report->timestarted) : 'Not started',
                    'percentage' => $report->percentage ?? 0,
                    'status' => $report->status ?? 'unknown',
                    'timecreated' => date('Y-m-d H:i:s', $report->timecreated),
                    'timemodified' => date('Y-m-d H:i:s', $report->timemodified)
                ];
            }
        } catch (Exception $e) {
            error_log('ALX API Export Error: ' . $e->getMessage());
            // Continue with empty reporting data if there's an error
        }
    }
    
    // Generate summary statistics
    $total_pages = $total_records > 0 ? ceil($total_records / $perpage) : 1;
    $summary = [
        'export_time' => date('Y-m-d H:i:s'),
        'time_range' => $time_label,
        'company_filter' => $companyid > 0 ? "Company ID: {$companyid}" : 'All Companies',
        'page' => $page,
        'total_pages' => $total_pages,
        'records_per_page' => $perpage,
        'total_matching_records' => $total_records,
        'records_in_export' => count($reporting_data),
        'total_companies' => count($company_data),
        'total_tokens' => count($token_data),
        'api_service_status' => $service ? 'Active' : 'Inactive',
        'api_service_id' => $service ? $service->id : 'N/A'
    ];
    
    if ($format === 'csv') {
        // Generate CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="alx_api_report_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write summary section
        fputcsv($output, ['ALX API Report Summary']);
        fputcsv($output, ['Export Time', $summary['export_time']]);
        fputcsv($output, ['Time Range', $summary['time_range']]);
        fputcsv($output, ['Company Filter', $summary['company_filter']]);
        fputcsv($output, ['Page', $summary['page'] . ' of ' . $summary['total_pages']]);
        fputcsv($output, ['Records Per Page', $summary['records_per_page']]);
        fputcsv($output, ['Total Matching Records', $summary['total_matching_records']]);
        fputcsv($output, ['Records in This Export', $summary['records_in_export']]);
        fputcsv($output, ['Total Companies', $summary['total_companies']]);
        fputcsv($output, ['Total Tokens', $summary['total_tokens']]);
        fputcsv($output, ['API Service Status', $summary['api_service_status']]);
        fputcsv($output, ['API Service ID', $summary['api_service_id']]);
        fputcsv($output, []); // Empty row
        
        // Write companies section
        fputcsv($output, ['Companies']);
        fputcsv($output, ['ID', 'Name', 'Shortname', 'Created']);
        foreach ($company_data as $company) {
            fputcsv($output, [$company['id'], $company['name'], $company['shortname'], $company['timecreated']]);
        }
        fputcsv($output, []); // Empty row
        
        // Write tokens section
        fputcsv($output, ['API Tokens']);
        fputcsv($output, ['ID', 'Token (Partial)', 'User ID', 'Service ID', 'Created']);
        foreach ($token_data as $token) {
            fputcsv($output, [$token['id'], $token['token'], $token['userid'], $token['externalserviceid'], $token['timecreated']]);
        }
        fputcsv($output, []); // Empty row
        
        // Write reporting data section
        if (!empty($reporting_data)) {
            fputcsv($output, ['Reporting Data (' . $time_label . ')']);
            fputcsv($output, ['ID', 'User ID', 'Company ID', 'Course ID', 'First Name', 'Last Name', 'Email', 'Course Name', 'Time Completed', 'Time Started', 'Percentage', 'Status', 'Time Created', 'Time Modified']);
            foreach ($reporting_data as $report) {
                fputcsv($output, [
                    $report['id'], 
                    $report['userid'], 
                    $report['companyid'], 
                    $report['courseid'], 
                    $report['firstname'], 
                    $report['lastname'], 
                    $report['email'], 
                    $report['coursename'], 
                    $report['timecompleted'], 
                    $report['timestarted'], 
                    $report['percentage'], 
                    $report['status'], 
                    $report['timecreated'], 
                    $report['timemodified']
                ]);
            }
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'json') {
        // Generate JSON export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="alx_api_report_' . date('Y-m-d_H-i-s') . '.json"');
        
        $export_data = [
            'summary' => $summary,
            'companies' => $company_data,
            'tokens' => $token_data,
            'reporting_data' => $reporting_data
        ];
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
}

// If no format specified, show the export options page
$PAGE->set_url('/local/alx_report_api/export_data.php');
$PAGE->set_title('Export API Report - ALX Report API');
$PAGE->set_heading('Export API Report Data');

// Include modern styling
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
echo '<link rel="stylesheet" href="' . new moodle_url('/local/alx_report_api/styles/export-data.css') . '">';

echo $OUTPUT->header();
?>

<div class="export-container">
    <div class="export-header">
        <h1><i class="fas fa-download"></i> Export API Report</h1>
        <p>Generate comprehensive reports of your ALX API usage and performance</p>
    </div>

    <div class="export-options">
        <div class="export-section">
            <h3><i class="fas fa-clock"></i> Select Time Range</h3>
            <div class="time-range-options">
                <a href="?timerange=1h" class="time-range-btn <?php echo $timerange === '1h' ? 'active' : ''; ?>">Last Hour</a>
                <a href="?timerange=24h" class="time-range-btn <?php echo $timerange === '24h' ? 'active' : ''; ?>">Last 24 Hours</a>
                <a href="?timerange=7d" class="time-range-btn <?php echo $timerange === '7d' ? 'active' : ''; ?>">Last 7 Days</a>
                <a href="?timerange=30d" class="time-range-btn <?php echo $timerange === '30d' ? 'active' : ''; ?>">Last 30 Days</a>
            </div>
        </div>

        <div class="export-section">
            <h3><i class="fas fa-file-export"></i> Export Format</h3>
            <div class="export-buttons">
                <a href="?format=csv&timerange=<?php echo $timerange; ?>" class="export-btn">
                    <i class="fas fa-file-csv"></i> Download CSV
                </a>
                <a href="?format=json&timerange=<?php echo $timerange; ?>" class="export-btn">
                    <i class="fas fa-file-code"></i> Download JSON
                </a>
            </div>
        </div>

        <?php
        // Show preview statistics
        $preview_companies = $DB->count_records('company');
        $preview_tokens = $DB->count_records('external_tokens');
        $preview_reports = 0;
        $table_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING);
        $table_status = '';
        
        if ($table_exists) {
            try {
                $total_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
                $preview_reports = $DB->count_records_sql("
                    SELECT COUNT(*) FROM {local_alx_api_reporting} 
                    WHERE timecreated > ?
                ", [$time_limit]);
                
                if ($total_records == 0) {
                    $table_status = '<div class="alert alert-warning mt-3">
                        <h5>⚠️ Reporting Table is Empty</h5>
                        <p>The reporting table exists but contains no data. You need to populate it first:</p>
                        <ol>
                            <li>Go to <a href="populate_reporting_table.php" class="alert-link">Populate Reporting Table</a></li>
                            <li>Select your companies and click "Start Population"</li>
                            <li>Wait for the process to complete</li>
                            <li>Return here to export the data</li>
                        </ol>
                        <p><strong>Note:</strong> The reporting table is used for faster API responses and exports.</p>
                    </div>';
                } elseif ($preview_reports == 0) {
                    $table_status = '<div class="alert alert-info mt-3">
                        <h5>ℹ️ No Data in Selected Time Range</h5>
                        <p>The reporting table has <strong>' . $total_records . '</strong> total records, but none match your selected time range (' . $time_label . ').</p>
                        <p>Try selecting a longer time range like "Last 30 Days" to see more data.</p>
                    </div>';
                }
            } catch (Exception $e) {
                error_log('ALX API Export Preview Error: ' . $e->getMessage());
                $preview_reports = 0;
                $table_status = '<div class="alert alert-danger mt-3">
                    <h5>❌ Database Error</h5>
                    <p>Error querying reporting table: ' . htmlspecialchars($e->getMessage()) . '</p>
                    <p>Please check your database configuration or contact support.</p>
                </div>';
            }
        } else {
            $table_status = '<div class="alert alert-danger mt-3">
                <h5>❌ Reporting Table Missing</h5>
                <p>The reporting table does not exist. Please upgrade the plugin:</p>
                <ol>
                    <li>Go to Site Administration → Notifications</li>
                    <li>Complete any pending plugin upgrades</li>
                    <li>Return here to export data</li>
                </ol>
            </div>';
        }
        ?>

        <div class="stats-preview">
            <h3><i class="fas fa-chart-bar"></i> Data Preview (<?php echo $time_label; ?>)</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $preview_companies; ?></div>
                    <div class="stat-label">Companies</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $preview_tokens; ?></div>
                    <div class="stat-label">API Tokens</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $preview_reports; ?></div>
                    <div class="stat-label">Reports</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $service ? 'Active' : 'Inactive'; ?></div>
                    <div class="stat-label">API Service</div>
                </div>
            </div>
            <?php echo $table_status; ?>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="advanced_monitoring.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?>
