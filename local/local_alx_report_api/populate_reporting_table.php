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
 * Initial data population script for ALX Report API reporting table.
 * 
 * This script populates the reporting table with existing data from the main database.
 * Run this once after installing the combined approach database schema.
 *
 * Usage:
 * - Via web browser: /local/alx_report_api/populate_reporting_table.php
 * - Via CLI: php populate_reporting_table.php
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include Moodle config
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Security check
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up page
$PAGE->set_url('/local/alx_report_api/populate_reporting_table.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Populate Reporting Table');
$PAGE->set_heading('ALX Report API - Initial Data Population');

// Check if this is a CLI request
$is_cli = (php_sapi_name() === 'cli');

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$company_ids = optional_param_array('company_ids', [], PARAM_INT);
$company_all = optional_param('company_all', 0, PARAM_INT);
$batch_size = optional_param('batch_size', 1000, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$cleanup_action = optional_param('cleanup_action', '', PARAM_ALPHA);
$cleanup_companyid = optional_param('cleanup_companyid', 0, PARAM_INT);
$cleanup_confirm = optional_param('cleanup_confirm', 0, PARAM_INT);

// Pagination parameters for results display
$results_page = optional_param('results_page', 1, PARAM_INT);
$results_perpage = 50; // Show 50 companies per page

// Process company selection
if ($action === 'populate' && $confirm) {
    // Determine which companies to process
    $companies_to_process = [];
    if ($company_all || empty($company_ids)) {
        // Process all companies
        $companies_to_process = [0]; // 0 means all companies
    } else {
        // Process selected companies
        $companies_to_process = $company_ids;
    }
}

// Handle cleanup action
if ($cleanup_action === 'clear' && $cleanup_confirm) {
    if (!$is_cli) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Clearing Reporting Table Data...');
        echo '<div class="alert alert-info">Removing data from reporting table...</div>';
        echo '<pre id="progress-log">';
        flush();
    }
    
    $start_time = time();
    echo "Starting data cleanup at " . date('Y-m-d H:i:s') . "\n";
    echo "Company ID: " . ($cleanup_companyid > 0 ? $cleanup_companyid : 'All companies') . "\n";
    echo str_repeat('-', 50) . "\n";
    flush();
    
    try {
        if ($cleanup_companyid > 0) {
            // Clear specific company
            $deleted_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $cleanup_companyid]);
            $DB->delete_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $cleanup_companyid]);
            
            // Also clear related sync status and cache
            $DB->delete_records(\local_alx_report_api\constants::TABLE_SYNC_STATUS, ['companyid' => $cleanup_companyid]);
            $DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $cleanup_companyid]);
            
            $company_name = $DB->get_field('company', 'name', ['id' => $cleanup_companyid]);
            echo "Cleared $deleted_count records for company: $company_name\n";
        } else {
            // Clear all data
            $deleted_count = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
            $DB->delete_records(\local_alx_report_api\constants::TABLE_REPORTING);
            $DB->delete_records(\local_alx_report_api\constants::TABLE_SYNC_STATUS);
            $DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE);
            
            echo "Cleared $deleted_count records for all companies\n";
        }
        
        $duration = time() - $start_time;
    echo "\n" . str_repeat('-', 50) . "\n";
        echo "Cleanup completed!\n";
        echo "Records deleted: $deleted_count\n";
        echo "Duration: $duration seconds\n";
        echo "Success: YES\n";
        
        if (!$is_cli) {
            echo '</pre>';
            echo '<div class="alert alert-success mt-3">';
            echo '<h4>Cleanup Complete!</h4>';
            echo '<p><strong>Records Deleted:</strong> ' . $deleted_count . '</p>';
            echo '<p><strong>Duration:</strong> ' . $duration . ' seconds</p>';
            echo '</div>';
            echo '<p><a href="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" class="btn btn-primary">Back to Population Tool</a></p>';
            echo $OUTPUT->footer();
        }
        
    } catch (Exception $e) {
        echo "Error during cleanup: " . $e->getMessage() . "\n";
        if (!$is_cli) {
            echo '</pre>';
            echo '<div class="alert alert-danger mt-3">';
            echo '<h4>Cleanup Failed!</h4>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
            echo '<p><a href="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" class="btn btn-primary">Back to Population Tool</a></p>';
            echo $OUTPUT->footer();
        }
    }
    
    exit;
}

// Handle cache clear action (using existing function)
if ($action === 'clear_cache' && $confirm) {
    require_sesskey();
    $cache_companyid = required_param('companyid', PARAM_INT);
    
    if ($cache_companyid > 0) {
        $company = $DB->get_record('company', ['id' => $cache_companyid], 'name');
        // Use existing function - it already works!
        $cleared = local_alx_report_api_cache_clear_company($cache_companyid);
        
        redirect(
            new moodle_url('/local/alx_report_api/populate_reporting_table.php'),
            "Cache cleared successfully for {$company->name}! {$cleared} entries removed.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

if ($action === 'populate' && $confirm) {
    if (!$is_cli) {
        echo $OUTPUT->header();
        
        // Modern population interface with real-time updates
        echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
        echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 8px 32px rgba(0,0,0,0.1);">';
        echo '<h1 style="margin: 0; font-size: 2rem; font-weight: 700;"><i class="fas fa-database"></i> Data Population in Progress</h1>';
        echo '<p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Processing your data - please wait while we populate the reporting table...</p>';
        echo '</div>';
        
        // Progress container
        echo '<div class="progress-container" style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">';
        
        // Progress header
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">';
        echo '<h3 style="margin: 0; color: #2d3748;"><i class="fas fa-chart-line"></i> Population Progress</h3>';
        echo '<div id="status-badge" style="background: #3182ce; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 14px;">🔄 Processing...</div>';
        echo '</div>';
        
        // Progress bars
        echo '<div style="margin-bottom: 30px;">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
        echo '<span style="font-weight: 600; color: #4a5568;">Overall Progress</span>';
        echo '<span id="progress-percentage" style="font-weight: 700; color: #3182ce;">0%</span>';
        echo '</div>';
        echo '<div style="background: #e2e8f0; border-radius: 10px; height: 12px; overflow: hidden; margin-bottom: 20px;">';
        echo '<div id="progress-bar" style="background: linear-gradient(90deg, #3182ce 0%, #63b3ed 100%); width: 0%; height: 100%; transition: width 0.3s ease; border-radius: 10px;"></div>';
        echo '</div>';
        echo '</div>';
        
        // Stats grid
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">';
        
        // Processed records
        echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">';
        echo '<div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;" id="processed-count">0</div>';
        echo '<div style="opacity: 0.9; font-size: 14px;">Records Processed</div>';
        echo '</div>';
        
        // Inserted records
        echo '<div style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">';
        echo '<div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;" id="inserted-count">0</div>';
        echo '<div style="opacity: 0.9; font-size: 14px;">Records Inserted</div>';
        echo '</div>';
        
        // Companies processed
        echo '<div style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">';
        echo '<div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;" id="companies-count">0</div>';
        echo '<div style="opacity: 0.9; font-size: 14px;">Companies Processed</div>';
        echo '</div>';
        
        // Duration
        echo '<div style="background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">';
        echo '<div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;" id="duration-count">0s</div>';
        echo '<div style="opacity: 0.9; font-size: 14px;">Duration</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Live log container
        echo '<div style="background: #1a202c; color: #e2e8f0; border-radius: 10px; padding: 20px; font-family: \'Courier New\', monospace; font-size: 14px; line-height: 1.6; max-height: 400px; overflow-y: auto;" id="live-log">';
        echo '<div style="color: #68d391; font-weight: 600;">📊 Population Log - ' . date('Y-m-d H:i:s') . '</div>';
        echo '<div style="color: #90cdf4;">═══════════════════════════════════════════════════════════════</div>';
        echo '</div>';
        
        echo '</div>'; // Close progress container
        
        // Completion container (hidden initially)
        echo '<div id="completion-container" style="display: none; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center;">';
        echo '<div style="font-size: 4rem; color: #48bb78; margin-bottom: 20px;">✅</div>';
        echo '<h2 style="color: #2d3748; margin-bottom: 15px;">Population Complete!</h2>';
        echo '<div id="final-summary" style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;"></div>';
        echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/control_center.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; margin-right: 10px;">📊 View Control Center</a>';
        echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" style="background: #e2e8f0; color: #4a5568; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">🔄 Run Again</a>';
        echo '</div>';
        
        echo '</div>'; // Close main container
        
        // JavaScript for real-time updates
        echo '<script>
        let startTime = Date.now();
        let updateInterval;
        
        function updateDuration() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById("duration-count").textContent = elapsed + "s";
        }
        
        function addLogEntry(message, type = "info") {
            const log = document.getElementById("live-log");
            const colors = {
                info: "#e2e8f0",
                success: "#68d391", 
                warning: "#fbb649",
                error: "#f56565",
                company: "#90cdf4"
            };
            
            const timestamp = new Date().toLocaleTimeString();
            const entry = `<div style="color: ${colors[type]}; margin: 2px 0; padding: 2px 0; border-left: 3px solid ${colors[type]}; padding-left: 8px;">[${timestamp}] ${message}</div>`;
            log.innerHTML += entry;
            log.scrollTop = log.scrollHeight;
        }
        
        function updateProgress(processed, inserted, companies, percentage) {
            document.getElementById("processed-count").textContent = processed.toLocaleString();
            document.getElementById("inserted-count").textContent = inserted.toLocaleString();
            document.getElementById("companies-count").textContent = companies;
            document.getElementById("progress-percentage").textContent = percentage + "%";
            document.getElementById("progress-bar").style.width = percentage + "%";
            
            // Add visual feedback for progress
            if (percentage >= 100) {
                document.getElementById("progress-bar").style.background = "linear-gradient(90deg, #48bb78 0%, #68d391 100%)";
                document.getElementById("status-badge").innerHTML = "🎉 Processing Complete";
                document.getElementById("status-badge").style.background = "#48bb78";
            } else if (percentage >= 75) {
                document.getElementById("progress-bar").style.background = "linear-gradient(90deg, #ed8936 0%, #fbb649 100%)";
            } else if (percentage >= 50) {
                document.getElementById("progress-bar").style.background = "linear-gradient(90deg, #667eea 0%, #764ba2 100%)";
            }
        }
        
        function showCompletion(summary) {
            document.getElementById("status-badge").innerHTML = "✅ Complete";
            document.getElementById("status-badge").style.background = "#48bb78";
            document.getElementById("final-summary").innerHTML = summary;
            document.getElementById("completion-container").style.display = "block";
            
            // Add celebration animation
            document.getElementById("completion-container").style.animation = "fadeInUp 0.5s ease-out";
            
            // Hide progress container
            document.querySelector(".progress-container").style.display = "none";
            
            clearInterval(updateInterval);
            
            // Add confetti effect (simple)
            setTimeout(() => {
                addLogEntry("🎊 Population process completed successfully! 🎊", "success");
            }, 500);
        }
        
        // Start duration timer
        updateInterval = setInterval(updateDuration, 1000);
        
        // Add CSS animations
        const style = document.createElement("style");
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .progress-container {
                animation: fadeInUp 0.3s ease-out;
            }
            
            #status-badge {
                animation: pulse 2s infinite;
            }
            
            #live-log {
                font-family: "Consolas", "Monaco", "Courier New", monospace !important;
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%) !important;
                border: 1px solid #4a5568;
            }
            
            #live-log::-webkit-scrollbar {
                width: 8px;
            }
            
            #live-log::-webkit-scrollbar-track {
                background: #2d3748;
                border-radius: 4px;
            }
            
            #live-log::-webkit-scrollbar-thumb {
                background: #4a5568;
                border-radius: 4px;
            }
            
            #live-log::-webkit-scrollbar-thumb:hover {
                background: #718096;
            }
        `;
        document.head.appendChild(style);
        </script>';
        
        echo '<div style="display: none;" id="progress-data">';
        flush();
    }
    
    $start_time = time();
    
    if (!$is_cli) {
        echo '<script>addLogEntry("🚀 Starting data population process...", "success");</script>';
        flush();
    } else {
        echo "Starting data population at " . date('Y-m-d H:i:s') . "\n";
    }
    
    // Display selected companies
    if ($company_all || empty($company_ids)) {
        if (!$is_cli) {
            echo '<script>addLogEntry("📋 Target: All companies", "info");</script>';
        } else {
            echo "Companies: All companies\n";
        }
        $companies_to_process = [0]; // 0 means all companies
    } else {
        $company_names = [];
        foreach ($company_ids as $cid) {
            $company_name = $DB->get_field('company', 'name', ['id' => $cid]);
            if ($company_name) {
                $company_names[] = $company_name;
            }
        }
        if (!$is_cli) {
            echo '<script>addLogEntry("📋 Target: ' . implode(', ', $company_names) . '", "info");</script>';
        } else {
            echo "Companies: " . implode(', ', $company_names) . "\n";
        }
        $companies_to_process = $company_ids;
    }
    
    if (!$is_cli) {
        echo '<script>addLogEntry("⚙️ Batch size: ' . $batch_size . ' records", "info");</script>';
        echo '<script>addLogEntry("═══════════════════════════════════════════════════════════════", "info");</script>';
        flush();
    } else {
        echo "Batch size: $batch_size\n";
        echo str_repeat('-', 50) . "\n";
    }
    flush();
    
    // Process each company
    $total_results = [
        'success' => true,
        'total_processed' => 0,
        'total_inserted' => 0,
        'companies_processed' => 0,
        'duration_seconds' => 0,
        'errors' => []
    ];
    
    if (in_array(0, $companies_to_process)) {
        // Process all companies
        if (!$is_cli) {
            echo '<script>addLogEntry("🏢 Processing all companies...", "info");</script>';
            flush();
        } else {
            echo "Processing all companies...\n";
            flush();
        }
        
        $result = local_alx_report_api_populate_reporting_table(0, $batch_size, true);
        $total_results['total_processed'] += $result['total_processed'];
        $total_results['total_inserted'] += $result['total_inserted'];
        $total_results['companies_processed'] += $result['companies_processed'];
        $total_results['errors'] = array_merge($total_results['errors'], $result['errors']);
        if (!$result['success']) {
            $total_results['success'] = false;
        }
        
        if (!$is_cli) {
            $percentage = 100; // All companies processed
            echo '<script>updateProgress(' . $total_results['total_processed'] . ', ' . $total_results['total_inserted'] . ', ' . $total_results['companies_processed'] . ', ' . $percentage . ');</script>';
            echo '<script>addLogEntry("✅ All companies processed - ' . number_format($total_results['total_processed']) . ' records processed, ' . number_format($total_results['total_inserted']) . ' inserted", "success");</script>';
            flush();
        }
    } else {
        // Process selected companies individually
        $total_companies = count($companies_to_process);
        $processed_companies = 0;
        
        foreach ($companies_to_process as $company_id) {
            $company_name = $DB->get_field('company', 'name', ['id' => $company_id]);
            
            if (!$is_cli) {
                echo '<script>addLogEntry("🏢 Processing company: ' . htmlspecialchars($company_name) . ' (ID: ' . $company_id . ')...", "company");</script>';
                flush();
            } else {
                echo "Processing company: $company_name (ID: $company_id)...\n";
                flush();
            }
            
            $result = local_alx_report_api_populate_reporting_table($company_id, $batch_size, true);
            $total_results['total_processed'] += $result['total_processed'];
            $total_results['total_inserted'] += $result['total_inserted'];
            $total_results['companies_processed'] += $result['companies_processed'];
            $total_results['errors'] = array_merge($total_results['errors'], $result['errors']);
            if (!$result['success']) {
                $total_results['success'] = false;
            }
            
            $processed_companies++;
            $percentage = round(($processed_companies / $total_companies) * 100);
            
            if (!$is_cli) {
                echo '<script>updateProgress(' . $total_results['total_processed'] . ', ' . $total_results['total_inserted'] . ', ' . $total_results['companies_processed'] . ', ' . $percentage . ');</script>';
                echo '<script>addLogEntry("  ✅ ' . htmlspecialchars($company_name) . ' - Processed: ' . number_format($result['total_processed']) . ', Inserted: ' . number_format($result['total_inserted']) . '", "success");</script>';
                flush();
            } else {
                echo "  - Processed: {$result['total_processed']}, Inserted: {$result['total_inserted']}\n";
                flush();
            }
        }
    }
    
    $total_results['duration_seconds'] = time() - $start_time;
    
    if (!$is_cli) {
        // Show completion with modern interface
        echo '<script>addLogEntry("═══════════════════════════════════════════════════════════════", "info");</script>';
        echo '<script>addLogEntry("🎉 Population completed successfully!", "success");</script>';
        echo '<script>addLogEntry("📊 Total processed: ' . number_format($total_results['total_processed']) . ' records", "info");</script>';
        echo '<script>addLogEntry("✅ Total inserted: ' . number_format($total_results['total_inserted']) . ' records", "success");</script>';
        echo '<script>addLogEntry("🏢 Companies processed: ' . $total_results['companies_processed'] . '", "info");</script>';
        echo '<script>addLogEntry("⏱️ Duration: ' . $total_results['duration_seconds'] . ' seconds", "info");</script>';
        
        if (!empty($total_results['errors'])) {
            echo '<script>addLogEntry("⚠️ Errors encountered:", "warning");</script>';
            foreach ($total_results['errors'] as $error) {
                echo '<script>addLogEntry("  - ' . htmlspecialchars($error) . '", "error");</script>';
            }
        }
        
        // Build completion summary
        $summary = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; text-align: center;">';
        $summary .= '<div><div style="font-size: 2rem; font-weight: 700; color: #667eea; margin-bottom: 5px;">' . number_format($total_results['total_processed']) . '</div><div style="color: #4a5568;">Records Processed</div></div>';
        $summary .= '<div><div style="font-size: 2rem; font-weight: 700; color: #48bb78; margin-bottom: 5px;">' . number_format($total_results['total_inserted']) . '</div><div style="color: #4a5568;">Records Inserted</div></div>';
        $summary .= '<div><div style="font-size: 2rem; font-weight: 700; color: #ed8936; margin-bottom: 5px;">' . $total_results['companies_processed'] . '</div><div style="color: #4a5568;">Companies</div></div>';
        $summary .= '<div><div style="font-size: 2rem; font-weight: 700; color: #9f7aea; margin-bottom: 5px;">' . $total_results['duration_seconds'] . 's</div><div style="color: #4a5568;">Duration</div></div>';
        $summary .= '</div>';
        
        if (!empty($total_results['errors'])) {
            $summary .= '<div style="background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px; margin-top: 15px;">';
            $summary .= '<h4 style="margin: 0 0 10px 0; color: #c53030;">⚠️ Errors Encountered:</h4>';
            $summary .= '<ul style="margin: 0; padding-left: 20px;">';
            foreach ($total_results['errors'] as $error) {
                $summary .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $summary .= '</ul></div>';
        }
        
        echo '<script>showCompletion(`' . $summary . '`);</script>';
        echo '</div>'; // Close progress-data div
        
        // Add comprehensive detailed results section
        echo '<div style="max-width: 1400px; margin: 30px auto; padding: 0 20px;">';
        
        // Section Header
        echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 30px; border-radius: 12px 12px 0 0; margin-top: 30px;">';
        echo '<h2 style="margin: 0; font-size: 24px; font-weight: 700;"><i class="fas fa-chart-bar"></i> Detailed Population Results</h2>';
        echo '<p style="margin: 8px 0 0 0; opacity: 0.9;">Comprehensive breakdown of populated data</p>';
        echo '</div>';
        
        echo '<div style="background: white; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        
        // Get detailed company information
        if (!empty($companies_to_process) && !in_array(0, $companies_to_process)) {
            list($company_sql, $company_params) = $DB->get_in_or_equal($companies_to_process, SQL_PARAMS_NAMED);
            $where_clause = "WHERE c.id $company_sql";
        } else {
            $company_sql = "";
            $company_params = [];
            $where_clause = "";
        }
        
        // 1. COMPANY INFORMATION CARDS
        echo '<h3 style="color: #2d3748; font-size: 20px; font-weight: 600; margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;"><i class="fas fa-building"></i> Company Information</h3>';
        
        // Get total count first for pagination
        $count_sql = "SELECT COUNT(DISTINCT c.id) as total
                      FROM {company} c
                      LEFT JOIN {local_alx_api_reporting} r ON r.companyid = c.id
                      " . $where_clause . "
                      HAVING COUNT(r.id) > 0";
        
        $count_params = array_merge($company_params);
        $total_companies_result = $DB->get_record_sql($count_sql, $count_params);
        $total_companies = $total_companies_result ? $total_companies_result->total : 0;
        $total_pages = ceil($total_companies / $results_perpage);
        $offset = ($results_page - 1) * $results_perpage;
        
        // Display pagination controls if needed
        if ($total_companies > $results_perpage) {
            echo '<div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
            echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">';
            
            // Pagination info
            $showing_from = (($results_page - 1) * $results_perpage) + 1;
            $showing_to = min($results_page * $results_perpage, $total_companies);
            echo '<div>';
            echo '<strong style="color: #2d3748;">Showing companies ' . $showing_from . '-' . $showing_to . ' of ' . $total_companies . '</strong>';
            echo '<span style="color: #64748b; margin-left: 10px;">(Page ' . $results_page . ' of ' . $total_pages . ')</span>';
            echo '</div>';
            
            // Pagination buttons
            echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            
            // First page
            if ($results_page > 1) {
                echo '<a href="?results_page=1" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">« First</a>';
            }
            
            // Previous page
            if ($results_page > 1) {
                echo '<a href="?results_page=' . ($results_page - 1) . '" style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; text-decoration: none; font-weight: 500;">‹ Previous</a>';
            }
            
            // Page numbers (show 5 pages around current)
            $start_page = max(1, $results_page - 2);
            $end_page = min($total_pages, $results_page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $results_page) {
                    echo '<span style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; font-weight: 600;">' . $i . '</span>';
                } else {
                    echo '<a href="?results_page=' . $i . '" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">' . $i . '</a>';
                }
            }
            
            // Next page
            if ($results_page < $total_pages) {
                echo '<a href="?results_page=' . ($results_page + 1) . '" style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">Next ›</a>';
            }
            
            // Last page
            if ($results_page < $total_pages) {
                echo '<a href="?results_page=' . $total_pages . '" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">Last »</a>';
            }
            
            echo '</div>'; // Close buttons
            echo '</div>'; // Close flex container
            echo '</div>'; // Close pagination box
        }
        
        // Get detailed company stats with LIMIT and OFFSET
        $company_stats_sql = "SELECT 
                                c.id,
                                c.name,
                                c.shortname,
                                COUNT(DISTINCT r.userid) as total_users,
                                COUNT(DISTINCT r.courseid) as active_courses,
                                COUNT(r.id) as total_records,
                                SUM(CASE WHEN r.timecreated >= :starttime1 THEN 1 ELSE 0 END) as records_created,
                                SUM(CASE WHEN r.timemodified >= :starttime2 AND r.timecreated < :starttime3 THEN 1 ELSE 0 END) as records_updated
                              FROM {company} c
                              LEFT JOIN {local_alx_api_reporting} r ON r.companyid = c.id
                              " . $where_clause . "
                              GROUP BY c.id, c.name, c.shortname
                              HAVING COUNT(r.id) > 0
                              ORDER BY total_records DESC
                              LIMIT $results_perpage OFFSET $offset";
        
        $params = array_merge(['starttime1' => $start_time, 'starttime2' => $start_time, 'starttime3' => $start_time], $company_params);
        $company_stats = $DB->get_records_sql($company_stats_sql, $params);
        
        if (!empty($company_stats)) {
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">';
            
            foreach ($company_stats as $company) {
                echo '<div style="background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 10px; padding: 20px; border-left: 4px solid #667eea;">';
                echo '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">';
                echo '<div>';
                echo '<h4 style="margin: 0 0 5px 0; color: #2d3748; font-size: 18px; font-weight: 600;">' . htmlspecialchars($company->name) . '</h4>';
                echo '<p style="margin: 0; color: #718096; font-size: 13px;">ID: ' . $company->id . ' | ' . htmlspecialchars($company->shortname) . '</p>';
                echo '</div>';
                echo '<span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Company</span>';
                echo '</div>';
                
                echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">';
                echo '<div style="background: white; padding: 12px; border-radius: 8px; text-align: center;">';
                echo '<div style="font-size: 24px; font-weight: 700; color: #667eea;">' . $company->total_users . '</div>';
                echo '<div style="font-size: 12px; color: #718096; margin-top: 4px;">Total Users</div>';
                echo '</div>';
                echo '<div style="background: white; padding: 12px; border-radius: 8px; text-align: center;">';
                echo '<div style="font-size: 24px; font-weight: 700; color: #ed8936;">' . $company->active_courses . '</div>';
                echo '<div style="font-size: 12px; color: #718096; margin-top: 4px;">Active Courses</div>';
                echo '</div>';
                echo '</div>';
                
                echo '<div style="background: white; padding: 15px; border-radius: 8px;">';
                echo '<div style="font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 10px;">Population Statistics</div>';
                echo '<div style="display: flex; justify-content: space-between; margin-bottom: 6px;">';
                echo '<span style="color: #718096; font-size: 13px;">Records Created:</span>';
                echo '<span style="color: #48bb78; font-weight: 600; font-size: 13px;">' . $company->records_created . '</span>';
                echo '</div>';
                echo '<div style="display: flex; justify-content: space-between; margin-bottom: 6px;">';
                echo '<span style="color: #718096; font-size: 13px;">Records Updated:</span>';
                echo '<span style="color: #3182ce; font-weight: 600; font-size: 13px;">' . $company->records_updated . '</span>';
                echo '</div>';
                echo '<div style="display: flex; justify-content: space-between; padding-top: 6px; border-top: 1px solid #e2e8f0;">';
                echo '<span style="color: #2d3748; font-weight: 600; font-size: 13px;">Total Records:</span>';
                echo '<span style="color: #2d3748; font-weight: 700; font-size: 13px;">' . $company->total_records . '</span>';
                echo '</div>';
                echo '</div>';
                
                echo '</div>';
            }
            
            echo '</div>';
            
            // Bottom pagination controls
            if ($total_companies > $results_perpage) {
                echo '<div style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
                echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">';
                
                // Pagination info
                $showing_from = (($results_page - 1) * $results_perpage) + 1;
                $showing_to = min($results_page * $results_perpage, $total_companies);
                echo '<div>';
                echo '<strong style="color: #2d3748;">Showing companies ' . $showing_from . '-' . $showing_to . ' of ' . $total_companies . '</strong>';
                echo '<span style="color: #64748b; margin-left: 10px;">(Page ' . $results_page . ' of ' . $total_pages . ')</span>';
                echo '</div>';
                
                // Pagination buttons
                echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                
                // First page
                if ($results_page > 1) {
                    echo '<a href="?results_page=1" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">« First</a>';
                }
                
                // Previous page
                if ($results_page > 1) {
                    echo '<a href="?results_page=' . ($results_page - 1) . '" style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; text-decoration: none; font-weight: 500;">‹ Previous</a>';
                }
                
                // Page numbers (show 5 pages around current)
                $start_page = max(1, $results_page - 2);
                $end_page = min($total_pages, $results_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $results_page) {
                        echo '<span style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; font-weight: 600;">' . $i . '</span>';
                    } else {
                        echo '<a href="?results_page=' . $i . '" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">' . $i . '</a>';
                    }
                }
                
                // Next page
                if ($results_page < $total_pages) {
                    echo '<a href="?results_page=' . ($results_page + 1) . '" style="padding: 8px 16px; background: #667eea; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">Next ›</a>';
                }
                
                // Last page
                if ($results_page < $total_pages) {
                    echo '<a href="?results_page=' . $total_pages . '" style="padding: 8px 16px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #2d3748; font-weight: 500;">Last »</a>';
                }
                
                echo '</div>'; // Close buttons
                echo '</div>'; // Close flex container
                echo '</div>'; // Close pagination box
            }
        }
        
        if (!empty($affected_companies)) {
            echo '<h2 style="margin: 30px 0 20px 0; color: #2d3748; font-size: 24px; font-weight: 600;"><i class="fas fa-building"></i> Affected Companies</h2>';
            echo '<div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px;">';
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
            echo '<tr>';
            echo '<th style="padding: 16px; text-align: left; font-weight: 600;">Company Name</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600;">Records Populated</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600;">Status</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($affected_companies as $company) {
                echo '<tr style="border-bottom: 1px solid #e2e8f0;">';
                echo '<td style="padding: 14px 16px; color: #2d3748;">' . htmlspecialchars($company->name) . '</td>';
                echo '<td style="padding: 14px 16px; text-align: center; font-weight: 600; color: #2d3748;">' . $company->record_count . '</td>';
                echo '<td style="padding: 14px 16px; text-align: center;"><span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ Populated</span></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        // 2. AFFECTED COURSES TABLE
        echo '<h3 style="color: #2d3748; font-size: 20px; font-weight: 600; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;"><i class="fas fa-book"></i> Affected Courses</h3>';
        
        // Get detailed course stats
        if (!empty($companies_to_process) && !in_array(0, $companies_to_process)) {
            list($company_sql, $company_params) = $DB->get_in_or_equal($companies_to_process, SQL_PARAMS_NAMED);
            $course_where = "WHERE r.companyid $company_sql";
            $course_params = array_merge(['coursetime1' => $start_time, 'coursetime2' => $start_time, 'coursetime3' => $start_time], $company_params);
        } else {
            $course_where = "";
            $course_params = ['coursetime1' => $start_time, 'coursetime2' => $start_time, 'coursetime3' => $start_time];
        }
        
        $affected_courses_sql = "SELECT 
                                    c.id,
                                    c.fullname,
                                    COUNT(r.id) as total_changes,
                                    SUM(CASE WHEN r.timecreated >= :coursetime1 THEN 1 ELSE 0 END) as records_created,
                                    SUM(CASE WHEN r.timemodified >= :coursetime2 AND r.timecreated < :coursetime3 THEN 1 ELSE 0 END) as records_updated
                                FROM {local_alx_api_reporting} r
                                JOIN {course} c ON c.id = r.courseid
                                $course_where
                                GROUP BY c.id, c.fullname
                                HAVING COUNT(r.id) > 0
                                ORDER BY total_changes DESC
                                LIMIT 20";
        $affected_courses = $DB->get_records_sql($affected_courses_sql, $course_params);
        
        if (!empty($affected_courses)) {
            echo '<div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px;">';
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
            echo '<tr>';
            echo '<th style="padding: 16px; text-align: left; font-weight: 600; width: 50%;">Course Name</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 15%;">Created</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 15%;">Updated</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 20%;">Total Changes</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($affected_courses as $course) {
                echo '<tr style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;" onmouseover="this.style.background=\'#f7fafc\'" onmouseout="this.style.background=\'white\'">';
                echo '<td style="padding: 14px 16px; color: #2d3748;">' . htmlspecialchars($course->fullname) . '</td>';
                echo '<td style="padding: 14px 16px; text-align: center;">';
                if ($course->records_created > 0) {
                    echo '<span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 13px; font-weight: 600;">' . $course->records_created . '</span>';
                } else {
                    echo '<span style="color: #a0aec0;">0</span>';
                }
                echo '</td>';
                echo '<td style="padding: 14px 16px; text-align: center;">';
                if ($course->records_updated > 0) {
                    echo '<span style="display: inline-block; padding: 4px 12px; background: #bee3f8; color: #2c5282; border-radius: 12px; font-size: 13px; font-weight: 600;">' . $course->records_updated . '</span>';
                } else {
                    echo '<span style="color: #a0aec0;">0</span>';
                }
                echo '</td>';
                echo '<td style="padding: 14px 16px; text-align: center; font-weight: 700; color: #2d3748; font-size: 15px;">' . $course->total_changes . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div style="background: #f7fafc; border: 2px dashed #cbd5e0; border-radius: 8px; padding: 30px; text-align: center; color: #718096; margin-bottom: 30px;">';
            echo '<i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px;"></i>';
            echo '<p style="margin: 0; font-size: 16px;">No course data available for the selected companies.</p>';
            echo '</div>';
        }
        
        // 3. AFFECTED USERS TABLE
        echo '<h3 style="color: #2d3748; font-size: 20px; font-weight: 600; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;"><i class="fas fa-users"></i> Affected Users (Top 50)</h3>';
        
        // Get detailed user stats
        if (!empty($companies_to_process) && !in_array(0, $companies_to_process)) {
            list($company_sql, $company_params) = $DB->get_in_or_equal($companies_to_process, SQL_PARAMS_NAMED);
            $user_where = "WHERE r.companyid $company_sql";
            $user_params = array_merge(['usertime1' => $start_time, 'usertime2' => $start_time, 'usertime3' => $start_time, 'usertime4' => $start_time], $company_params);
        } else {
            $user_where = "";
            $user_params = ['usertime1' => $start_time, 'usertime2' => $start_time, 'usertime3' => $start_time, 'usertime4' => $start_time];
        }
        
        $affected_users_sql = "SELECT 
                                u.id,
                                u.firstname,
                                u.lastname,
                                u.email,
                                COUNT(DISTINCT r.courseid) as courses_synced,
                                SUM(CASE WHEN r.timecreated >= :usertime1 THEN 1 ELSE 0 END) as records_created,
                                SUM(CASE WHEN r.timemodified >= :usertime2 AND r.timecreated < :usertime3 THEN 1 ELSE 0 END) as records_updated,
                                CASE 
                                    WHEN SUM(CASE WHEN r.timecreated >= :usertime4 THEN 1 ELSE 0 END) > 0 THEN 'Created'
                                    ELSE 'Updated'
                                END as status
                            FROM {user} u
                            JOIN {local_alx_api_reporting} r ON r.userid = u.id
                            $user_where
                            GROUP BY u.id, u.firstname, u.lastname, u.email
                            ORDER BY courses_synced DESC, records_created DESC
                            LIMIT 50";
        $affected_users = $DB->get_records_sql($affected_users_sql, $user_params);
        
        if (!empty($affected_users)) {
            echo '<div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px;">';
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
            echo '<tr>';
            echo '<th style="padding: 16px; text-align: left; font-weight: 600; width: 25%;">User Name</th>';
            echo '<th style="padding: 16px; text-align: left; font-weight: 600; width: 30%;">Email</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 15%;">Courses Synced</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 15%;">Records</th>';
            echo '<th style="padding: 16px; text-align: center; font-weight: 600; width: 15%;">Status</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($affected_users as $user) {
                $total_records = $user->records_created + $user->records_updated;
                echo '<tr style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;" onmouseover="this.style.background=\'#f7fafc\'" onmouseout="this.style.background=\'white\'">';
                echo '<td style="padding: 14px 16px; color: #2d3748; font-weight: 500;">' . htmlspecialchars($user->firstname . ' ' . $user->lastname) . '</td>';
                echo '<td style="padding: 14px 16px; color: #718096; font-size: 13px;">' . htmlspecialchars($user->email) . '</td>';
                echo '<td style="padding: 14px 16px; text-align: center; font-weight: 600; color: #667eea; font-size: 15px;">' . $user->courses_synced . '</td>';
                echo '<td style="padding: 14px 16px; text-align: center;">';
                echo '<div style="font-size: 13px; color: #4a5568;">';
                if ($user->records_created > 0) {
                    echo '<span style="color: #48bb78; font-weight: 600;">+' . $user->records_created . '</span>';
                }
                if ($user->records_updated > 0) {
                    if ($user->records_created > 0) echo ' / ';
                    echo '<span style="color: #3182ce; font-weight: 600;">~' . $user->records_updated . '</span>';
                }
                echo '</div>';
                echo '</td>';
                echo '<td style="padding: 14px 16px; text-align: center;">';
                if ($user->status === 'Created') {
                    echo '<span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ Created</span>';
                } else {
                    echo '<span style="display: inline-block; padding: 4px 12px; background: #bee3f8; color: #2c5282; border-radius: 12px; font-size: 12px; font-weight: 600;">↻ Updated</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div style="background: #f7fafc; border: 2px dashed #cbd5e0; border-radius: 8px; padding: 30px; text-align: center; color: #718096; margin-bottom: 30px;">';
            echo '<i class="fas fa-user-slash" style="font-size: 48px; opacity: 0.3; margin-bottom: 10px;"></i>';
            echo '<p style="margin: 0; font-size: 16px;">No user data available for the selected companies.</p>';
            echo '</div>';
        }
        
        echo '</div>'; // Close detailed results white container
        
        echo '</div>'; // Close max-width container
        
        echo $OUTPUT->footer();
    } else {
        echo "\n" . str_repeat('-', 50) . "\n";
        echo "Population completed!\n";
        echo "Total processed: " . $total_results['total_processed'] . "\n";
        echo "Total inserted: " . $total_results['total_inserted'] . "\n";
        echo "Companies processed: " . $total_results['companies_processed'] . "\n";
        echo "Duration: " . $total_results['duration_seconds'] . " seconds\n";
        echo "Success: " . ($total_results['success'] ? 'YES' : 'NO') . "\n";
        
        if (!empty($total_results['errors'])) {
            echo "\nErrors encountered:\n";
            foreach ($total_results['errors'] as $error) {
                echo "- $error\n";
            }
        }
    }
    
    exit;
}

if ($is_cli) {
    echo "ALX Report API - Reporting Table Population Tool\n";
    echo "============================================\n\n";
    
    // Get CLI parameters
    $options = getopt('', ['companyid:', 'batch-size:', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php populate_reporting_table.php [options]\n\n";
        echo "Options:\n";
        echo "  --companyid=ID    Populate data for specific company ID (default: all companies)\n";
        echo "  --batch-size=N    Number of records to process per batch. Processes ALL records in batches (default: 1000)\n";
        echo "  --help           Show this help message\n\n";
        echo "Examples:\n";
        echo "  php populate_reporting_table.php\n";
        echo "  php populate_reporting_table.php --companyid=5 --batch-size=500\n\n";
        exit;
    }
    
    $cli_companyid = isset($options['companyid']) ? (int)$options['companyid'] : 0;
    $cli_batch_size = isset($options['batch-size']) ? (int)$options['batch-size'] : 1000;
    
    echo "Starting population with:\n";
    echo "Company ID: " . ($cli_companyid > 0 ? $cli_companyid : 'All companies') . "\n";
    echo "Batch size: $cli_batch_size\n\n";
    echo "Press Enter to continue or Ctrl+C to cancel...";
    fgets(STDIN);
    
    // Run population
    $result = local_alx_report_api_populate_reporting_table($cli_companyid, $cli_batch_size, true);
    
    echo "\nPopulation Results:\n";
    echo "==================\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "Total processed: " . $result['total_processed'] . "\n";
    echo "Total inserted: " . $result['total_inserted'] . "\n";
    echo "Companies processed: " . $result['companies_processed'] . "\n";
    echo "Duration: " . $result['duration_seconds'] . " seconds\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    exit;
}

// Web interface
echo $OUTPUT->header();

// Modern UI styling
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
echo '<link rel="stylesheet" href="' . new moodle_url('/local/alx_report_api/styles/populate-reporting-table.css') . '">';

// Check if reporting table exists
if (!$DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_REPORTING)) {
    echo '<div class="populate-container">';
    echo '<div class="alert alert-danger">Reporting table does not exist. Please upgrade the plugin first.</div>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Get current statistics
$total_reporting_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING);
$companies = local_alx_report_api_get_companies();

echo '<div class="populate-container">';

echo '<div class="page-header">';
echo '<div>';
echo '<h1 style="margin: 0 0 10px 0; font-size: 32px;"><i class="fas fa-database"></i> Populate Reporting Table</h1>';
echo '<p style="margin: 0; opacity: 0.9; font-size: 16px;">Initial data population for the reporting table</p>';
echo '</div>';
echo '<a href="control_center.php" style="background: rgba(255,255,255,0.2); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;" onmouseover="this.style.background=\'rgba(255,255,255,0.3)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.2)\'">';
echo '<i class="fas fa-arrow-left"></i> Back to Control Center';
echo '</a>';
echo '</div>';

echo '<div class="dashboard-card">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-info-circle"></i> About This Tool</h3>';
echo '<p class="card-subtitle">Important information about the population process</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<p style="margin: 0 0 10px 0;">This tool populates the reporting table with existing data from your main database. ';
echo 'This is required for the combined approach (separate reporting table + incremental sync) to work properly.</p>';
echo '<p style="margin: 0; color: #f59e0b; font-weight: 500;"><i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> This process may take several minutes depending on your data size. ';
echo 'It is recommended to run this during off-peak hours.</p>';
echo '</div>';
echo '</div>';

// Show current status
echo '<div class="dashboard-card">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-chart-bar"></i> Current Status</h3>';
echo '<p class="card-subtitle">Overview of your reporting table data</p>';
echo '</div>';
echo '<div class="card-body">';
echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">';
echo '<div><strong style="color: #64748b;">Companies Available:</strong><br><span style="font-size: 24px; color: #2d3748; font-weight: 600;">' . count($companies) . '</span></div>';
echo '<div><strong style="color: #64748b;">Reporting Records:</strong><br><span style="font-size: 24px; color: #2d3748; font-weight: 600;">' . number_format($total_reporting_records) . '</span></div>';
if ($total_reporting_records > 0) {
    $last_update = $DB->get_field_select(\local_alx_report_api\constants::TABLE_REPORTING, 'MAX(last_updated)', '1=1');
    echo '<div><strong style="color: #64748b;">Last Update:</strong><br><span style="font-size: 18px; color: #2d3748;">' . ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</span></div>';
    echo '<div><strong style="color: #64748b;">Status:</strong><br><span style="display: inline-block; padding: 6px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 14px; font-weight: 600; margin-top: 8px;">✓ Data Available</span></div>';
} else {
    echo '<div><strong style="color: #64748b;">Last Update:</strong><br><span style="font-size: 18px; color: #2d3748;">Never</span></div>';
    echo '<div><strong style="color: #64748b;">Status:</strong><br><span style="display: inline-block; padding: 6px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 14px; font-weight: 600; margin-top: 8px;">⚠ No Data</span></div>';
}
echo '</div>';
echo '</div>';
echo '</div>';

// Population form
echo '<div class="dashboard-card">';
echo '<div class="card-header">';
echo '<h3 class="card-title"><i class="fas fa-play-circle"></i> Populate Reporting Table</h3>';
echo '<p class="card-subtitle">Select companies and configure population settings</p>';
echo '</div>';
echo '<div class="card-body">';

echo '<form method="post" id="populate-form">';
echo '<input type="hidden" name="action" value="populate">';

echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<label for="company-dropdown" style="display: block; margin-bottom: 12px; font-weight: 600; color: #495057;">Companies to Populate:</label>';
echo '<div class="company-dropdown-container">';
echo '<div class="company-dropdown-toggle" id="company-dropdown" onclick="toggleDropdown()">';
echo '<span class="selection-text" id="dropdown-text">Select Companies</span>';
echo '<span class="dropdown-arrow" id="dropdown-arrow">▼</span>';
echo '</div>';
echo '<div class="company-dropdown-menu" id="dropdown-menu">';

// All Companies checkbox
echo '<div class="check-all-item">';
echo '<div class="form-check">';
echo '<input type="checkbox" name="company_all" id="company_all" class="form-check-input" onchange="toggleAllCompanies()">';
echo '<label for="company_all" class="form-check-label">';
echo '✅ Check All Companies';
echo '</label>';
echo '</div>';
echo '</div>';

echo '<div class="custom-divider"></div>';

// Individual company checkboxes
foreach ($companies as $company) {
    $existing_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $company->id]);
    echo '<div class="company-item" id="item-' . $company->id . '">';
    echo '<div class="form-check">';
    echo '<input type="checkbox" name="company_ids[]" value="' . $company->id . '" id="company_' . $company->id . '" class="form-check-input company-checkbox" onchange="updateDropdownText(); updateItemStyle(' . $company->id . ')">';
    echo '<label for="company_' . $company->id . '" class="form-check-label">';
    echo '<span>' . htmlspecialchars($company->name) . '</span>';
    if ($existing_records > 0) {
        echo '<span class="company-badge badge-has-data">' . number_format($existing_records) . ' records</span>';
    } else {
        echo '<span class="company-badge badge-no-data">No data</span>';
    }
    echo '</label>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '<small style="color: #64748b; font-size: 13px;">Click to select one or more companies. You can select multiple companies at once.</small>';
echo '</div>';

echo '<div style="margin-top: 20px;">';
echo '<label for="batch_size" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Batch Size:</label>';
echo '<input type="number" name="batch_size" id="batch_size" value="1000" min="100" max="5000" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px;">';
echo '<small style="color: #64748b; font-size: 13px;">Number of records to process per batch. The system will process <strong>ALL records</strong> in batches of this size to avoid memory issues. Larger batches are faster but use more memory. <em>Example: 3,313 records with batch size 1000 = 4 batches (1000+1000+1000+313)</em></small>';
echo '</div>';

echo '<div style="display: flex; align-items: center; gap: 8px; margin-top: 20px;">';
echo '<input type="checkbox" name="confirm" value="1" id="confirm" required style="width: 18px; height: 18px;">';
echo '<label for="confirm" style="margin: 0; color: #495057; font-weight: 500;">';
echo 'I understand this process may take several minutes and should be run during off-peak hours.';
echo '</label>';
echo '</div>';
echo '</div>';

if ($total_reporting_records > 0) {
    echo '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin-top: 20px;">';
    echo '<p style="margin: 0; color: #92400e;"><strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong> You already have ' . number_format($total_reporting_records) . ' records in the reporting table. ';
    echo 'This process will add new records but will not update existing ones. ';
    echo 'If you want to refresh existing data, you may need to clear the reporting table first.</p>';
    echo '</div>';
}

echo '<button type="submit" class="btn-populate" id="populate-btn" style="margin-top: 20px;">';
echo '<i class="fas fa-database"></i> Start Population Process';
echo '</button>';

echo '</form>';
echo '</div>';
echo '</div>';

// Add cleanup section
if ($total_reporting_records > 0) {
    echo '<div class="dashboard-card" style="border-left: 4px solid #ef4444;">';
    echo '<div class="card-header" style="background: linear-gradient(to right, #fee2e2, #ffffff);">';
    echo '<h3 class="card-title" style="color: #dc2626;"><i class="fas fa-trash-alt"></i> Clear Reporting Table Data</h3>';
    echo '<p class="card-subtitle" style="color: #991b1b;">Permanently delete data from the reporting table</p>';
    echo '</div>';
    echo '<div class="card-body">';
    
    echo '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<p style="margin: 0; color: #92400e;"><strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong> This action will permanently delete data from the reporting table. ';
    echo 'This cannot be undone. Use with caution!</p>';
    echo '</div>';
    
    echo '<form method="post" id="cleanup-form">';
    echo '<input type="hidden" name="cleanup_action" value="clear">';
    
    echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<label for="cleanup_companyid" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Company to Clear:</label>';
    echo '<select name="cleanup_companyid" id="cleanup_companyid" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">';
    echo '<option value="0">⚠️ All Companies (Clear Everything)</option>';
    foreach ($companies as $company) {
        $company_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, ['companyid' => $company->id]);
        echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . ' (' . number_format($company_records) . ' records)</option>';
    }
    echo '</select>';
    echo '<small style="color: #64748b; font-size: 13px; display: block; margin-top: 8px;">Select which company data to remove, or clear all data.</small>';
    
    echo '<div style="display: flex; align-items: center; gap: 8px; margin-top: 20px;">';
    echo '<input type="checkbox" name="cleanup_confirm" value="1" id="cleanup_confirm" required style="width: 18px; height: 18px;">';
    echo '<label for="cleanup_confirm" style="margin: 0; color: #dc2626; font-weight: 600;">';
    echo 'I understand this will permanently delete the selected data and cannot be undone.';
    echo '</label>';
    echo '</div>';
    echo '</div>';
    
    echo '<button type="submit" class="btn-populate btn-danger" id="cleanup-btn">';
    echo '<i class="fas fa-trash"></i> Clear Selected Data';
    echo '</button>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

// Cache Management Section
if (!empty($companies)) {
    $selected_cache_company = optional_param('cache_company', 0, PARAM_INT);
    
    echo '<div class="dashboard-card" style="margin-top: 30px;">';
    echo '<div class="card-header" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">';
    echo '<h3 class="card-title"><i class="fas fa-database"></i> 💾 Cache Management</h3>';
    echo '<p class="card-subtitle">View cache statistics and manually clear cache for a company</p>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Company selector form
    echo '<form method="get" action="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" style="margin-bottom: 20px;">';
    echo '<div style="margin-bottom: 15px;">';
    echo '<label style="display: block; font-weight: 600; color: #2d3748; margin-bottom: 8px;">Select Company:</label>';
    echo '<select name="cache_company" id="cache_company" onchange="this.form.submit()" style="width: 100%; padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; background: white;">';
    echo '<option value="0">Select a company...</option>';
    foreach ($companies as $company) {
        $selected = ($company->id == $selected_cache_company) ? 'selected' : '';
        echo '<option value="' . $company->id . '" ' . $selected . '>' . htmlspecialchars($company->name) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</form>';
    
    // Show cache stats if company selected (using simple direct queries)
    if ($selected_cache_company > 0) {
        $company = $DB->get_record('company', ['id' => $selected_cache_company], 'name');
        
        // Simple direct queries (no complex functions)
        $cache_count = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $selected_cache_company]);
        $cache_enabled = local_alx_report_api_get_company_setting($selected_cache_company, 'enable_cache', 1);
        
        $last_update = null;
        $expires_at = null;
        $is_expired = true;
        
        if ($cache_count > 0) {
            $sql = "SELECT MAX(timecreated) as last_update FROM {" . \local_alx_report_api\constants::TABLE_CACHE . "} WHERE companyid = ?";
            $last_update = $DB->get_field_sql($sql, [$selected_cache_company]);
            if ($last_update) {
                $expires_at = $last_update + 3600; // Default 1 hour TTL
                $is_expired = (time() > $expires_at);
            }
        }
        
        echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';
        echo '<h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">📈 Cache Statistics for ' . htmlspecialchars($company->name) . '</h4>';
        
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr style="border-bottom: 1px solid #e2e8f0;">';
        echo '<td style="padding: 10px 0; font-weight: 600; color: #4a5568;">Total Cache Entries:</td>';
        echo '<td style="padding: 10px 0; text-align: right; font-weight: 700; color: #2d3748;">' . number_format($cache_count) . '</td>';
        echo '</tr>';
        
        if ($last_update) {
            echo '<tr style="border-bottom: 1px solid #e2e8f0;">';
            echo '<td style="padding: 10px 0; font-weight: 600; color: #4a5568;">Last Cache Update:</td>';
            echo '<td style="padding: 10px 0; text-align: right; color: #2d3748;">' . date('Y-m-d H:i:s', $last_update) . '</td>';
            echo '</tr>';
            
            echo '<tr style="border-bottom: 1px solid #e2e8f0;">';
            echo '<td style="padding: 10px 0; font-weight: 600; color: #4a5568;">Cache Expires At:</td>';
            echo '<td style="padding: 10px 0; text-align: right; color: #2d3748;">';
            echo date('Y-m-d H:i:s', $expires_at);
            if ($is_expired) {
                echo ' <span style="display: inline-block; padding: 2px 8px; background: #fee2e2; color: #dc2626; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 8px;">Expired</span>';
            } else {
                $minutes_left = round(($expires_at - time()) / 60);
                echo ' <span style="display: inline-block; padding: 2px 8px; background: #d1fae5; color: #065f46; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 8px;">Active (in ' . $minutes_left . ' min)</span>';
            }
            echo '</td>';
            echo '</tr>';
        } else {
            echo '<tr style="border-bottom: 1px solid #e2e8f0;">';
            echo '<td colspan="2" style="padding: 10px 0; color: #64748b; font-style: italic;">No cache entries found</td>';
            echo '</tr>';
        }
        
        echo '<tr>';
        echo '<td style="padding: 10px 0; font-weight: 600; color: #4a5568;">Cache Status:</td>';
        echo '<td style="padding: 10px 0; text-align: right;">';
        if ($cache_enabled) {
            echo '<span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 13px; font-weight: 600;">✅ Enabled</span>';
        } else {
            echo '<span style="display: inline-block; padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 13px; font-weight: 600;">⚠️ Disabled</span>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '</div>';
        
        // Clear cache form (only if entries exist)
        if ($cache_count > 0) {
            echo '<form method="post" action="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" onsubmit="return confirm(\'Are you sure you want to clear cache for ' . htmlspecialchars($company->name) . '? This will force fresh data to be loaded on the next API call.\');">';
            echo '<input type="hidden" name="action" value="clear_cache">';
            echo '<input type="hidden" name="companyid" value="' . $selected_cache_company . '">';
            echo '<input type="hidden" name="confirm" value="1">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<button type="submit" class="btn-populate btn-danger">';
            echo '<i class="fas fa-trash"></i> Clear Cache Now';
            echo '</button>';
            echo '</form>';
        } else {
            echo '<p style="color: #64748b; font-style: italic; margin: 0;">No cache entries to clear.</p>';
        }
    } else {
        echo '<p style="color: #64748b; font-style: italic; margin: 0;">Select a company to view cache statistics.</p>';
    }
    
    echo '</div>';
    echo '</div>';
}

// Statistics by company - Intelligence Dashboard Style
if (!empty($companies) && $total_reporting_records > 0) {
    echo '<div class="dashboard-card">';
    echo '<div class="card-header">';
    echo '<h3 class="card-title"><i class="fas fa-building"></i> 📊 Complete Company Reporting Intelligence Dashboard</h3>';
    echo '<p class="card-subtitle">Comprehensive overview of reporting records across all companies</p>';
    echo '</div>';
    echo '<div class="card-body" style="padding: 0; overflow-x: auto;">';
    echo '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
    echo '<thead>';
    echo '<tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
    echo '<th style="padding: 16px; text-align: left; font-weight: 600; white-space: nowrap;">Company Name</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Total Records</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Active Records</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Deleted Records</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Active %</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Last Updated</th>';
    echo '<th style="padding: 16px; text-align: center; font-weight: 600; white-space: nowrap;">Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    if (empty($companies)) {
        echo '<tr>';
        echo '<td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">No companies found.</td>';
        echo '</tr>';
    } else {
        foreach ($companies as $company) {
            $stats = local_alx_report_api_get_reporting_stats($company->id);
            $last_update = $stats['last_update'] ? date('Y-m-d H:i:s', $stats['last_update']) : 'Never';
            $active_percentage = $stats['total_records'] > 0 ? round(($stats['active_records'] / $stats['total_records']) * 100, 1) : 0;
            
            // Determine status color
            $status_color = '#10b981'; // green
            $status_text = 'Healthy';
            if ($stats['total_records'] == 0) {
                $status_color = '#f59e0b'; // orange
                $status_text = 'No Data';
            } elseif ($active_percentage < 80) {
                $status_color = '#ef4444'; // red
                $status_text = 'Needs Cleanup';
            }
            
            echo '<tr style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;">';
            echo '<td style="padding: 16px; font-weight: 600; color: #2d3748;">' . htmlspecialchars($company->name) . '</td>';
            echo '<td style="padding: 16px; text-align: center; color: #2d3748; font-weight: 600;">' . number_format($stats['total_records']) . '</td>';
            echo '<td style="padding: 16px; text-align: center;">';
            echo '<span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 13px; font-weight: 600;">';
            echo number_format($stats['active_records']);
            echo '</span>';
            echo '</td>';
            echo '<td style="padding: 16px; text-align: center;">';
            echo '<span style="display: inline-block; padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 13px; font-weight: 600;">';
            echo number_format($stats['total_records'] - $stats['active_records']);
            echo '</span>';
            echo '</td>';
            echo '<td style="padding: 16px; text-align: center; font-weight: 600; color: ' . ($active_percentage >= 90 ? '#10b981' : ($active_percentage >= 80 ? '#f59e0b' : '#ef4444')) . ';">';
            echo $active_percentage . '%';
            echo '</td>';
            echo '<td style="padding: 16px; text-align: center; color: #64748b; font-size: 13px;">' . $last_update . '</td>';
            echo '<td style="padding: 16px; text-align: center;">';
            echo '<span style="display: inline-block; padding: 6px 12px; background: ' . $status_color . '20; color: ' . $status_color . '; border-radius: 12px; font-size: 13px; font-weight: 600;">';
            echo $status_text;
            echo '</span>';
            echo '</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
}

// JavaScript for form handling

echo '<script>
// Toggle dropdown visibility
function toggleDropdown() {
    var dropdown = document.getElementById("dropdown-menu");
    var arrow = document.getElementById("dropdown-arrow");
    
    if (dropdown.classList.contains("show")) {
        dropdown.classList.remove("show");
        arrow.classList.remove("rotated");
    } else {
        dropdown.classList.add("show");
        arrow.classList.add("rotated");
    }
}

// Update item styling based on selection
function updateItemStyle(companyId) {
    var checkbox = document.getElementById("company_" + companyId);
    var item = document.getElementById("item-" + companyId);
    
    if (checkbox.checked) {
        item.classList.add("selected");
    } else {
        item.classList.remove("selected");
    }
}

// Close dropdown when clicking outside
document.addEventListener("click", function(event) {
    var container = document.querySelector(".company-dropdown-container");
    var dropdown = document.getElementById("dropdown-menu");
    
    if (!container.contains(event.target)) {
        dropdown.classList.remove("show");
        document.getElementById("dropdown-arrow").classList.remove("rotated");
    }
});

// Update dropdown text based on selections
function updateDropdownText() {
    var companyCheckboxes = document.querySelectorAll(".company-checkbox");
    var checkedCount = 0;
    var checkedNames = [];
    
    for (var i = 0; i < companyCheckboxes.length; i++) {
        if (companyCheckboxes[i].checked) {
            checkedCount++;
            var label = document.querySelector("label[for=\"" + companyCheckboxes[i].id + "\"] span");
            if (label && checkedNames.length < 2) {
                checkedNames.push(label.textContent);
            }
        }
    }
    
    var dropdownText = document.getElementById("dropdown-text");
    if (checkedCount === 0) {
        dropdownText.textContent = "Select Companies";
        dropdownText.className = "selection-text";
    } else if (checkedCount === companyCheckboxes.length) {
        dropdownText.textContent = "All Companies Selected (" + checkedCount + ")";
        dropdownText.className = "selection-text all-selected";
    } else if (checkedCount === 1) {
        dropdownText.textContent = checkedNames[0];
        dropdownText.className = "selection-text has-selection";
    } else if (checkedCount === 2) {
        dropdownText.textContent = checkedNames[0] + ", " + checkedNames[1];
        dropdownText.className = "selection-text has-selection";
    } else {
        dropdownText.textContent = checkedNames[0] + ", " + checkedNames[1] + " (+" + (checkedCount - 2) + " more)";
        dropdownText.className = "selection-text has-selection";
    }
    
    updateAllCheckboxState();
}

// Toggle all companies function
function toggleAllCompanies() {
    var allCheckbox = document.getElementById("company_all");
    var companyCheckboxes = document.querySelectorAll(".company-checkbox");
    
    for (var i = 0; i < companyCheckboxes.length; i++) {
        companyCheckboxes[i].checked = allCheckbox.checked;
        // Update individual item styling
        var companyId = companyCheckboxes[i].value;
        updateItemStyle(companyId);
    }
    
    updateDropdownText();
}

// Update "All Companies" checkbox state based on individual selections
function updateAllCheckboxState() {
    var allCheckbox = document.getElementById("company_all");
    var companyCheckboxes = document.querySelectorAll(".company-checkbox");
    var checkedCount = 0;
    
    for (var i = 0; i < companyCheckboxes.length; i++) {
        if (companyCheckboxes[i].checked) {
            checkedCount++;
        }
    }
    
    // Update "All" checkbox state
    if (checkedCount === companyCheckboxes.length) {
        allCheckbox.checked = true;
        allCheckbox.indeterminate = false;
    } else if (checkedCount > 0) {
        allCheckbox.checked = false;
        allCheckbox.indeterminate = true;
    } else {
        allCheckbox.checked = false;
        allCheckbox.indeterminate = false;
    }
}

// Initialize dropdown functionality
document.addEventListener("DOMContentLoaded", function() {
    // Initial state update
    updateDropdownText();
    
    // Set up individual item styling
    var companyCheckboxes = document.querySelectorAll(".company-checkbox");
    for (var i = 0; i < companyCheckboxes.length; i++) {
        var companyId = companyCheckboxes[i].value;
        updateItemStyle(companyId);
    }
});

document.getElementById("populate-form").addEventListener("submit", function(e) {
    // Check if at least one company is selected
    var allCheckbox = document.getElementById("company_all");
    var companyCheckboxes = document.querySelectorAll(".company-checkbox");
    var hasSelection = allCheckbox.checked;
    
    if (!hasSelection) {
        for (var i = 0; i < companyCheckboxes.length; i++) {
            if (companyCheckboxes[i].checked) {
                hasSelection = true;
                break;
            }
        }
    }
    
    if (!hasSelection) {
        alert("Please select at least one company to populate.");
        e.preventDefault();
        return false;
    }
    
    var btn = document.getElementById("populate-btn");
    btn.innerHTML = "<i class=\\"fa fa-spinner fa-spin\\"></i> Processing...";
    btn.disabled = true;
    
    // Show processing message
    var alert = document.createElement("div");
    alert.className = "alert alert-info mt-3";
    alert.innerHTML = "<strong>Processing...</strong> Please wait while the data is being populated. This page will refresh when complete.";
    document.getElementById("populate-form").appendChild(alert);
});

// Cleanup form handling
var cleanupForm = document.getElementById("cleanup-form");
if (cleanupForm) {
    cleanupForm.addEventListener("submit", function(e) {
        var companySelect = document.getElementById("cleanup_companyid");
        var selectedText = companySelect.options[companySelect.selectedIndex].text;
        
        var confirmMessage = "Are you sure you want to permanently delete data for: " + selectedText + "?\\\\n\\\\n" +
                           "This action cannot be undone and will remove all reporting table data for the selected company/companies.";
        
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
        
        var btn = document.getElementById("cleanup-btn");
        btn.innerHTML = "<i class=\\"fa fa-spinner fa-spin\\"></i> Deleting...";
        btn.disabled = true;
        
        // Show processing message
        var alert = document.createElement("div");
        alert.className = "alert alert-warning mt-3";
        alert.innerHTML = "<strong>Deleting data...</strong> Please wait while the selected data is being removed.";
        cleanupForm.appendChild(alert);
    });
}
</script>';

echo '</div>'; // Close populate-container
echo $OUTPUT->footer(); 