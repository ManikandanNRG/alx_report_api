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
 * Auto-sync Intelligence Dashboard for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/alx_report_api/lib.php');

// Check permissions.
admin_externalpage_setup('local_alx_report_api_auto_sync_status');
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/auto_sync_status.php');
$PAGE->set_title('Auto-Sync Intelligence - ALX Report API');
$PAGE->set_heading('Auto-Sync Intelligence Dashboard');

// Add dedicated CSS file for auto-sync monitoring
$PAGE->requires->css('/local/alx_report_api/auto_sync_monitoring.css');

// Include modern font and icons
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

// Include Chart.js for interactive charts
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

echo $OUTPUT->header();

// Get LIVE DATA from database - NO PLACEHOLDERS
global $DB;

// Get current configuration
$sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
$max_sync_time = get_config('local_alx_report_api', 'max_sync_time') ?: 300;
$last_sync = get_config('local_alx_report_api', 'last_auto_sync');
$last_stats = get_config('local_alx_report_api', 'last_sync_stats');

// Parse last sync statistics - LIVE DATA
$sync_statistics = [];
if ($last_stats) {
    $sync_statistics = json_decode($last_stats, true) ?: [];
}

// Get scheduled task info - LIVE DATA
$task_record = $DB->get_record('task_scheduled', ['classname' => '\local_alx_report_api\task\sync_reporting_data_task']);

// Get system health data - LIVE DATA
$companies = local_alx_report_api_get_companies();
$total_companies = count($companies);
$companies_with_api = 0;
foreach ($companies as $company) {
    if ($DB->record_exists('local_alx_api_settings', ['companyid' => $company->id])) {
        $companies_with_api++;
    }
}

// Get historical sync data (last 7 days) - LIVE DATA
$historical_data = [];
if ($DB->get_manager()->table_exists('local_alx_api_sync_status')) {
    try {
        // Check what columns exist in the table
        $table_info = $DB->get_columns('local_alx_api_sync_status');
        $has_last_sync_status = isset($table_info['last_sync_status']);
        $has_last_sync_timestamp = isset($table_info['last_sync_timestamp']);
        
        if ($has_last_sync_timestamp) {
            // Get daily sync counts for the last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $day_start = time() - ($i * 24 * 3600);
                $day_end = $day_start + (24 * 3600);
                
                $daily_syncs = $DB->count_records_select('local_alx_api_sync_status', 
                    'last_sync_timestamp >= ? AND last_sync_timestamp < ?', 
                    [$day_start, $day_end]);
                
                $successful_syncs = 0;
                if ($has_last_sync_status) {
                    $successful_syncs = $DB->count_records_select('local_alx_api_sync_status', 
                        'last_sync_timestamp >= ? AND last_sync_timestamp < ? AND last_sync_status = ?', 
                        [$day_start, $day_end, 'success']);
                } else {
                    // If no status column, assume all syncs are successful
                    $successful_syncs = $daily_syncs;
                }
                
                $historical_data[] = [
                    'date' => date('M j', $day_start),
                    'total_syncs' => $daily_syncs,
                    'successful_syncs' => $successful_syncs,
                    'success_rate' => $daily_syncs > 0 ? round(($successful_syncs / $daily_syncs) * 100) : 0
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Auto-sync status query error: ' . $e->getMessage());
        // Initialize empty historical data on error
        for ($i = 6; $i >= 0; $i--) {
            $day_start = time() - ($i * 24 * 3600);
            $historical_data[] = [
                'date' => date('M j', $day_start),
                'total_syncs' => 0,
                'successful_syncs' => 0,
                'success_rate' => 0
            ];
        }
    }
}

// Check cron health - LIVE DATA
$last_cron = get_config('tool_task', 'lastcronstart');
$cron_healthy = false;
if ($last_cron) {
    $time_since_cron = time() - $last_cron;
    $cron_healthy = $time_since_cron < 3600;
}

// Calculate next sync time - LIVE DATA
$next_sync_time = null;
if ($task_record && $last_sync) {
    $next_sync_time = $last_sync + ($sync_hours * 3600);
}

?>

<div class="auto-sync-container">
    
    <!-- ROW 1: HEADER SECTION -->
    <div class="header-section">
        <div class="header-content">
            <div>
                <h1 class="header-title">🔄 Auto-Sync Intelligence</h1>
                <p class="header-subtitle">Monitor automated data synchronization across all company tenants</p>
            </div>
            <a href="control_center.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Control Center
            </a>
        </div>
    </div>

    <!-- ROW 2: SYNC STATISTICS CARDS (8 cards - 4x2 grid) -->
    <div class="stats-grid">
        <!-- First Row -->
        <div class="stat-card success">
            <div class="stat-value"><?php echo $sync_statistics['companies_processed'] ?? 0; ?></div>
            <div class="stat-label">Companies Processed</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $sync_statistics['total_users_updated'] ?? 0; ?></div>
            <div class="stat-label">Users Updated</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $sync_statistics['total_records_updated'] ?? 0; ?></div>
            <div class="stat-label">Records Updated</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $sync_statistics['total_records_created'] ?? 0; ?></div>
            <div class="stat-label">Records Created</div>
        </div>
        
        <!-- Second Row -->
        <div class="stat-card <?php echo ($sync_statistics['total_errors'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
            <div class="stat-value"><?php echo $sync_statistics['total_errors'] ?? 0; ?></div>
            <div class="stat-label">Errors</div>
        </div>
        <div class="stat-card <?php echo $cron_healthy ? 'success' : 'warning'; ?>">
            <div class="stat-value"><?php echo $last_sync ? date('H:i', $last_sync) : 'Never'; ?></div>
            <div class="stat-label">Last Sync</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php 
                if ($next_sync_time) {
                    echo date('H:i', $next_sync_time);
                } else {
                    echo 'Pending';
                }
            ?></div>
            <div class="stat-label">Next Sync</div>
        </div>
        <div class="stat-card <?php echo $task_record && $task_record->disabled == 0 ? 'success' : 'danger'; ?>">
            <div class="stat-value"><?php echo $task_record && $task_record->disabled == 0 ? 'Active' : 'Disabled'; ?></div>
            <div class="stat-label">Task Status</div>
        </div>
    </div>

    <!-- ROW 3: SYNC TRENDS CHART (50% + 50%) -->
    <div class="chart-section">
        <div class="chart-header">
            <h3 class="chart-title">
                <i class="fas fa-chart-area"></i>
                📊 Weekly Sync Trends & Last Sync Statistics
            </h3>
            <p class="chart-subtitle">Interactive chart showing sync performance over the last 7 days with detailed statistics</p>
        </div>
        <div class="chart-body">
            <div class="chart-grid">
                <!-- Left Side: Chart (50%) -->
                <div class="chart-container">
                    <canvas id="syncTrendsChart"></canvas>
                </div>
                
                <!-- Right Side: Statistics (50%) -->
                <div class="chart-stats">
                    <h4>📈 Last Sync Statistics</h4>
                    <div class="chart-stat-item">
                        <span class="chart-stat-label">Total Syncs (7 days)</span>
                        <span class="chart-stat-value"><?php echo array_sum(array_column($historical_data, 'total_syncs')); ?></span>
                    </div>
                    <div class="chart-stat-item">
                        <span class="chart-stat-label">Successful Syncs</span>
                        <span class="chart-stat-value"><?php echo array_sum(array_column($historical_data, 'successful_syncs')); ?></span>
                    </div>
                    <div class="chart-stat-item">
                        <span class="chart-stat-label">Overall Success Rate</span>
                        <span class="chart-stat-value"><?php 
                            $total_syncs = array_sum(array_column($historical_data, 'total_syncs'));
                            $successful_syncs = array_sum(array_column($historical_data, 'successful_syncs'));
                            echo $total_syncs > 0 ? round(($successful_syncs / $total_syncs) * 100) . '%' : '0%';
                        ?></span>
                    </div>
                    <div class="chart-stat-item">
                        <span class="chart-stat-label">Avg Syncs/Day</span>
                        <span class="chart-stat-value"><?php echo round(array_sum(array_column($historical_data, 'total_syncs')) / 7, 1); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: SYSTEM STATUS BANNER -->
    <div class="status-banner <?php echo $cron_healthy ? '' : 'warning'; ?>">
        <div class="status-content">
            <div class="status-icon">
                <?php echo $cron_healthy ? '✅' : '⚠️'; ?>
            </div>
            <div class="status-details">
                <h3>Auto-Sync System Status: <?php echo $cron_healthy ? 'HEALTHY' : 'WARNING'; ?></h3>
                <div class="status-meta">
                    <?php if ($cron_healthy): ?>
                        System is running normally and ready for automatic synchronization
                    <?php else: ?>
                        Warning: Cron system may need attention for optimal sync performance
                    <?php endif; ?>
                </div>
                <div class="status-grid">
                    <div class="status-item">
                        <span class="status-label">Total Companies</span>
                        <span class="status-value"><?php echo $total_companies; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">API Configured</span>
                        <span class="status-value"><?php echo $companies_with_api; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Sync Interval</span>
                        <span class="status-value"><?php echo $sync_hours; ?>h</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Max Sync Time</span>
                        <span class="status-value"><?php echo $max_sync_time; ?>s</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: TIMELINE PROCESS FLOW -->
    <div class="timeline-section">
        <div class="timeline-header">
            <h3 class="timeline-title">🔄 Auto-Sync Process Flow</h3>
        </div>
        <div class="timeline-flow">
            <div class="timeline-step">
                <div class="timeline-icon">⏰</div>
                <div class="timeline-label">
                    <strong>Scheduled Execution</strong>
                    <span>Cron-based trigger</span>
                </div>
            </div>
            <div class="timeline-step">
                <div class="timeline-icon">🏢</div>
                <div class="timeline-label">
                    <strong>Smart Company Detection</strong>
                    <span>Identify active companies</span>
                </div>
            </div>
            <div class="timeline-step">
                <div class="timeline-icon">🔍</div>
                <div class="timeline-label">
                    <strong>Change Detection</strong>
                    <span>Incremental updates</span>
                </div>
            </div>
            <div class="timeline-step">
                <div class="timeline-icon">🔄</div>
                <div class="timeline-label">
                    <strong>Data Update</strong>
                    <span>Intelligent sync</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 6: QUICK ACTIONS (4 buttons - 25% each) -->
    <div class="actions-section">
        <div class="actions-header">
            <h3 class="actions-title">🚀 Quick Actions</h3>
        </div>
        <div class="actions-grid">
            <a href="sync_reporting_data.php" class="action-button">
                <div class="action-icon">🔄</div>
                <div class="action-label">Manual Sync</div>
            </a>
            <a href="company_settings.php" class="action-button">
                <div class="action-icon">⚙️</div>
                <div class="action-label">Company Settings</div>
            </a>
            <a href="monitoring_dashboard.php" class="action-button">
                <div class="action-icon">💚</div>
                <div class="action-label">System Health & Alerts</div>
            </a>
            <a href="advanced_monitoring.php" class="action-button">
                <div class="action-icon">🔒</div>
                <div class="action-label">API Performance & Security</div>
            </a>
        </div>
    </div>

</div>

<script>
// Initialize Chart.js with LIVE DATA
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('syncTrendsChart').getContext('2d');
    
    // LIVE DATA from PHP - NO PLACEHOLDERS
    const chartData = {
        labels: <?php echo json_encode(array_column($historical_data, 'date')); ?>,
        datasets: [{
            label: 'Total Syncs',
            data: <?php echo json_encode(array_column($historical_data, 'total_syncs')); ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }, {
            label: 'Successful Syncs',
            data: <?php echo json_encode(array_column($historical_data, 'successful_syncs')); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    };
    
    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weekly Sync Trends (Last 7 Days)',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });
});
</script>

<?php
echo $OUTPUT->footer();
?>