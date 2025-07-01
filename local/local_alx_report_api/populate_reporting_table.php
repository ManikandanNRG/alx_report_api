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
            $deleted_count = $DB->count_records('local_alx_api_reporting', ['companyid' => $cleanup_companyid]);
            $DB->delete_records('local_alx_api_reporting', ['companyid' => $cleanup_companyid]);
            
            // Also clear related sync status and cache
            $DB->delete_records('local_alx_api_sync_status', ['companyid' => $cleanup_companyid]);
            $DB->delete_records('local_alx_api_cache', ['companyid' => $cleanup_companyid]);
            
            $company_name = $DB->get_field('company', 'name', ['id' => $cleanup_companyid]);
            echo "Cleared $deleted_count records for company: $company_name\n";
        } else {
            // Clear all data
            $deleted_count = $DB->count_records('local_alx_api_reporting');
            $DB->delete_records('local_alx_api_reporting');
            $DB->delete_records('local_alx_api_sync_status');
            $DB->delete_records('local_alx_api_cache');
            
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
        echo "  --batch-size=N    Number of records to process per batch (default: 1000)\n";
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

// Check if reporting table exists
if (!$DB->get_manager()->table_exists('local_alx_api_reporting')) {
    echo $OUTPUT->notification('Reporting table does not exist. Please upgrade the plugin first.', 'error');
    echo $OUTPUT->footer();
    exit;
}

// Get current statistics
$total_reporting_records = $DB->count_records('local_alx_api_reporting');
$companies = local_alx_report_api_get_companies();

echo $OUTPUT->heading('ALX Report API - Populate Reporting Table');

echo '<div class="alert alert-info">';
echo '<h4>About This Tool</h4>';
echo '<p>This tool populates the reporting table with existing data from your main database. ';
echo 'This is required for the combined approach (separate reporting table + incremental sync) to work properly.</p>';
echo '<p><strong>Important:</strong> This process may take several minutes depending on your data size. ';
echo 'It is recommended to run this during off-peak hours.</p>';
echo '</div>';

// Show current status
echo '<div class="card mb-4">';
echo '<div class="card-header"><h5>Current Status</h5></div>';
echo '<div class="card-body">';
echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<p><strong>Companies Available:</strong> ' . count($companies) . '</p>';
echo '<p><strong>Reporting Records:</strong> ' . number_format($total_reporting_records) . '</p>';
echo '</div>';
echo '<div class="col-md-6">';
if ($total_reporting_records > 0) {
    $last_update = $DB->get_field_select('local_alx_api_reporting', 'MAX(last_updated)', '1=1');
    echo '<p><strong>Last Update:</strong> ' . ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</p>';
    echo '<p><strong>Status:</strong> <span class="badge badge-success">Data Available</span></p>';
} else {
    echo '<p><strong>Last Update:</strong> Never</p>';
    echo '<p><strong>Status:</strong> <span class="badge badge-warning">No Data</span></p>';
}
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Population form
echo '<div class="card">';
echo '<div class="card-header"><h5>Populate Reporting Table</h5></div>';
echo '<div class="card-body">';

echo '<form method="post" id="populate-form">';
echo '<input type="hidden" name="action" value="populate">';

echo '<div class="form-group">';
echo '<label for="company-dropdown">Companies to Populate:</label>';
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
    $existing_records = $DB->count_records('local_alx_api_reporting', ['companyid' => $company->id]);
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
echo '<small class="form-text text-muted">Click to select one or more companies. You can select multiple companies at once.</small>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="batch_size">Batch Size:</label>';
echo '<input type="number" name="batch_size" id="batch_size" class="form-control" value="1000" min="100" max="5000">';
echo '<small class="form-text text-muted">Number of records to process per batch. Larger batches are faster but use more memory.</small>';
echo '</div>';

echo '<div class="form-check mb-3">';
echo '<input type="checkbox" name="confirm" value="1" id="confirm" class="form-check-input" required>';
echo '<label for="confirm" class="form-check-label">';
echo 'I understand this process may take several minutes and should be run during off-peak hours.';
echo '</label>';
echo '</div>';

if ($total_reporting_records > 0) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Warning:</strong> You already have ' . number_format($total_reporting_records) . ' records in the reporting table. ';
    echo 'This process will add new records but will not update existing ones. ';
    echo 'If you want to refresh existing data, you may need to clear the reporting table first.';
    echo '</div>';
}

echo '<button type="submit" class="btn btn-primary btn-lg" id="populate-btn">';
echo '<i class="fa fa-database"></i> Start Population Process';
echo '</button>';

echo '</form>';
echo '</div>';
echo '</div>';

// Add cleanup section
if ($total_reporting_records > 0) {
    echo '<div class="card mt-4" style="border-left: 4px solid #dc3545;">';
    echo '<div class="card-header bg-danger"><h5 class="text-white mb-0">🗑️ Clear Reporting Table Data</h5></div>';
    echo '<div class="card-body">';
    
    echo '<div class="alert alert-warning">';
    echo '<strong>⚠️ Warning:</strong> This action will permanently delete data from the reporting table. ';
    echo 'This cannot be undone. Use with caution!';
    echo '</div>';
    
    echo '<form method="post" id="cleanup-form">';
    echo '<input type="hidden" name="cleanup_action" value="clear">';
    
    echo '<div class="form-group">';
    echo '<label for="cleanup_companyid">Company to Clear:</label>';
    echo '<select name="cleanup_companyid" id="cleanup_companyid" class="form-control">';
    echo '<option value="0">⚠️ All Companies (Clear Everything)</option>';
    foreach ($companies as $company) {
        $company_records = $DB->count_records('local_alx_api_reporting', ['companyid' => $company->id]);
        echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . ' (' . number_format($company_records) . ' records)</option>';
    }
    echo '</select>';
    echo '<small class="form-text text-muted">Select which company data to remove, or clear all data.</small>';
    echo '</div>';
    
    echo '<div class="form-check mb-3">';
    echo '<input type="checkbox" name="cleanup_confirm" value="1" id="cleanup_confirm" class="form-check-input" required>';
    echo '<label for="cleanup_confirm" class="form-check-label text-danger">';
    echo '<strong>I understand this will permanently delete the selected data and cannot be undone.</strong>';
    echo '</label>';
    echo '</div>';
    
    echo '<button type="submit" class="btn btn-danger" id="cleanup-btn">';
    echo '<i class="fa fa-trash"></i> Clear Selected Data';
    echo '</button>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

// Statistics by company
if (!empty($companies) && $total_reporting_records > 0) {
    echo '<div class="card mt-4">';
    echo '<div class="card-header"><h5>Records by Company</h5></div>';
    echo '<div class="card-body">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Company</th><th>Total Records</th><th>Active Records</th><th>Last Updated</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($companies as $company) {
        $stats = local_alx_report_api_get_reporting_stats($company->id);
        $last_update = $stats['last_update'] ? date('Y-m-d H:i:s', $stats['last_update']) : 'Never';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($company->name) . '</td>';
        echo '<td>' . number_format($stats['total_records']) . '</td>';
        echo '<td>' . number_format($stats['active_records']) . '</td>';
        echo '<td>' . $last_update . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// JavaScript for form handling
echo '<style>
/* Custom dropdown styling */
.company-dropdown-container {
    position: relative;
}

.company-dropdown-toggle {
    width: 100%;
    min-height: 50px;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    background-color: #ffffff;
    color: #495057;
    font-size: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.company-dropdown-toggle:hover {
    border-color: #007bff;
    box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.15);
}

.company-dropdown-toggle:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    outline: none;
}

.company-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    min-width: 100%;
    max-height: 350px;
    overflow-y: auto;
    padding: 16px;
    margin-top: 4px;
    background-color: #ffffff;
    border: 2px solid #007bff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: none;
}

.company-dropdown-menu.show {
    display: block;
}

/* Check All styling */
.check-all-item {
    padding: 12px 16px;
    margin: -8px -8px 8px -8px;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-radius: 8px;
    margin-bottom: 16px;
}

.check-all-item .form-check-label {
    color: #ffffff;
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 0;
    cursor: pointer;
}

.check-all-item .form-check-input {
    width: 18px;
    height: 18px;
    margin-right: 12px;
}

/* Individual company items */
.company-item {
    padding: 10px 12px;
    margin: 4px 0;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.company-item:hover {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
    padding-left: 16px;
    transform: translateX(4px);
}

.company-item.selected {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding-left: 16px;
}

.company-item .form-check-label {
    width: 100%;
    margin-bottom: 0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #495057;
    font-size: 14px;
    font-weight: 500;
}

.company-item:hover .form-check-label {
    color: #007bff;
}

.company-item .form-check-input {
    width: 16px;
    height: 16px;
    margin-right: 12px;
    margin-top: 0;
}

/* Badge styling */
.company-badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-has-data {
    background-color: #28a745;
    color: white;
}

.badge-no-data {
    background-color: #6c757d;
    color: white;
}

/* Dropdown arrow */
.dropdown-arrow {
    transition: transform 0.3s ease;
    color: #6c757d;
    font-size: 14px;
}

.dropdown-arrow.rotated {
    transform: rotate(180deg);
}

/* Selection text styling */
.selection-text {
    color: #495057;
    font-weight: 500;
}

.selection-text.has-selection {
    color: #007bff;
    font-weight: 600;
}

.selection-text.all-selected {
    color: #28a745;
    font-weight: 700;
}

/* Scrollbar styling */
.company-dropdown-menu::-webkit-scrollbar {
    width: 8px;
}

.company-dropdown-menu::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.company-dropdown-menu::-webkit-scrollbar-thumb {
    background: #007bff;
    border-radius: 4px;
}

.company-dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: #0056b3;
}

/* Divider */
.custom-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #dee2e6, transparent);
    margin: 12px 0;
}
</style>';

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

echo $OUTPUT->footer(); 