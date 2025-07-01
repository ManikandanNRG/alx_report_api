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
 * ALX Report API Trends Data Provider
 * 
 * AJAX endpoint for historical trends chart data.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

try {
    // Get parameters
    $type = optional_param('type', 'usage', PARAM_ALPHA);
    $days = optional_param('days', 7, PARAM_INT);
    $company = optional_param('company', '', PARAM_TEXT);
    
    // Validate parameters
    $days = max(1, min(365, $days));
    $allowed_types = ['usage', 'performance', 'errors', 'users', 'companies'];
    
    if (!in_array($type, $allowed_types)) {
        throw new Exception('Invalid chart type');
    }
    
    // Generate data based on type
    switch ($type) {
        case 'usage':
            $data = local_alx_report_api_get_usage_trends($days, $company);
            break;
        case 'performance':
            $data = local_alx_report_api_get_performance_trends($days, $company);
            break;
        case 'errors':
            $data = local_alx_report_api_get_error_trends($days, $company);
            break;
        case 'users':
            $data = local_alx_report_api_get_user_trends($days, $company);
            break;
        case 'companies':
            $data = local_alx_report_api_get_company_trends($days);
            break;
        default:
            throw new Exception('Unknown chart type');
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get API usage trends over time.
 */
function local_alx_report_api_get_usage_trends($days, $company_filter = '') {
    global $DB;
    
    $end_time = time();
    $start_time = $end_time - ($days * 24 * 3600);
    
    // Determine time grouping based on period
    if ($days == 1) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600; // 1 hour
    } elseif ($days <= 7) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600 * 4; // 4 hours
    } elseif ($days <= 30) {
        $time_format = '%Y-%m-%d';
        $interval_seconds = 86400; // 1 day
    } else {
        $time_format = '%Y-%U'; // Year-Week
        $interval_seconds = 86400 * 7; // 1 week
    }
    
    $where_conditions = ['timeaccessed >= ? AND timeaccessed <= ?'];
    $params = [$start_time, $end_time];
    
    if ($company_filter) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $company_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '{$time_format}') as time_bucket,
            COUNT(*) as total_calls,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT company_shortname) as unique_companies,
            SUM(record_count) as total_records,
            AVG(response_time_ms) as avg_response_time
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY FROM_UNIXTIME(timeaccessed, '{$time_format}')
        ORDER BY time_bucket ASC
    ";
    
    $results = $DB->get_records_sql($sql, $params);
    
    // Fill gaps in data
    $data = local_alx_report_api_fill_time_gaps($results, $start_time, $end_time, $interval_seconds, $time_format);
    
    $labels = [];
    $values = [];
    $unique_users = [];
    $response_times = [];
    
    foreach ($data as $point) {
        $labels[] = local_alx_report_api_format_chart_label($point->time_bucket, $days);
        $values[] = (int)$point->total_calls;
        $unique_users[] = (int)$point->unique_users;
        $response_times[] = round($point->avg_response_time ?: 0, 2);
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'unique_users' => $unique_users,
        'response_times' => $response_times,
        'stats' => [
            'total_calls' => array_sum($values),
            'avg_calls_per_period' => count($values) > 0 ? round(array_sum($values) / count($values), 1) : 0,
            'peak_calls' => max($values),
            'unique_users_total' => max($unique_users),
            'avg_response_time' => count($response_times) > 0 ? round(array_sum($response_times) / count($response_times), 2) : 0
        ]
    ];
}

/**
 * Get performance trends over time.
 */
function local_alx_report_api_get_performance_trends($days, $company_filter = '') {
    global $DB;
    
    $end_time = time();
    $start_time = $end_time - ($days * 24 * 3600);
    
    // Time grouping
    if ($days == 1) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600;
    } elseif ($days <= 7) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600 * 4;
    } elseif ($days <= 30) {
        $time_format = '%Y-%m-%d';
        $interval_seconds = 86400;
    } else {
        $time_format = '%Y-%U';
        $interval_seconds = 86400 * 7;
    }
    
    $where_conditions = ['timeaccessed >= ? AND timeaccessed <= ?'];
    $params = [$start_time, $end_time];
    
    if ($company_filter) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $company_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '{$time_format}') as time_bucket,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as successful_requests,
            AVG(response_time_ms) as avg_response_time,
            MAX(response_time_ms) as max_response_time,
            MIN(response_time_ms) as min_response_time,
            STDDEV(response_time_ms) as response_time_stddev
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY FROM_UNIXTIME(timeaccessed, '{$time_format}')
        ORDER BY time_bucket ASC
    ";
    
    $results = $DB->get_records_sql($sql, $params);
    
    $data = local_alx_report_api_fill_time_gaps($results, $start_time, $end_time, $interval_seconds, $time_format);
    
    $labels = [];
    $response_times = [];
    $success_rates = [];
    $max_response_times = [];
    
    foreach ($data as $point) {
        $labels[] = local_alx_report_api_format_chart_label($point->time_bucket, $days);
        $response_times[] = round($point->avg_response_time ?: 0, 2);
        
        $success_rate = $point->total_requests > 0 
            ? round(($point->successful_requests / $point->total_requests) * 100, 2) 
            : 100;
        $success_rates[] = $success_rate;
        
        $max_response_times[] = round($point->max_response_time ?: 0, 2);
    }
    
    return [
        'labels' => $labels,
        'response_times' => $response_times,
        'success_rates' => $success_rates,
        'max_response_times' => $max_response_times,
        'stats' => [
            'avg_response_time' => count($response_times) > 0 ? round(array_sum($response_times) / count($response_times), 2) : 0,
            'peak_response_time' => max($max_response_times),
            'avg_success_rate' => count($success_rates) > 0 ? round(array_sum($success_rates) / count($success_rates), 2) : 0,
            'best_success_rate' => max($success_rates),
            'worst_success_rate' => min($success_rates)
        ]
    ];
}

/**
 * Get error trends over time.
 */
function local_alx_report_api_get_error_trends($days, $company_filter = '') {
    global $DB;
    
    $end_time = time();
    $start_time = $end_time - ($days * 24 * 3600);
    
    // Time grouping
    if ($days == 1) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600;
    } elseif ($days <= 7) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600 * 4;
    } elseif ($days <= 30) {
        $time_format = '%Y-%m-%d';
        $interval_seconds = 86400;
    } else {
        $time_format = '%Y-%U';
        $interval_seconds = 86400 * 7;
    }
    
    $where_conditions = ['timeaccessed >= ? AND timeaccessed <= ?'];
    $params = [$start_time, $end_time];
    
    if ($company_filter) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $company_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '{$time_format}') as time_bucket,
            COUNT(*) as total_requests,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as error_count,
            COUNT(DISTINCT CASE WHEN error_message IS NOT NULL AND error_message != '' THEN userid END) as users_with_errors,
            COUNT(DISTINCT CASE WHEN error_message IS NOT NULL AND error_message != '' THEN company_shortname END) as companies_with_errors
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY FROM_UNIXTIME(timeaccessed, '{$time_format}')
        ORDER BY time_bucket ASC
    ";
    
    $results = $DB->get_records_sql($sql, $params);
    
    $data = local_alx_report_api_fill_time_gaps($results, $start_time, $end_time, $interval_seconds, $time_format);
    
    $labels = [];
    $error_rates = [];
    $error_counts = [];
    $affected_users = [];
    
    foreach ($data as $point) {
        $labels[] = local_alx_report_api_format_chart_label($point->time_bucket, $days);
        
        $error_rate = $point->total_requests > 0 
            ? round(($point->error_count / $point->total_requests) * 100, 2) 
            : 0;
        $error_rates[] = $error_rate;
        $error_counts[] = (int)$point->error_count;
        $affected_users[] = (int)$point->users_with_errors;
    }
    
    return [
        'labels' => $labels,
        'error_rates' => $error_rates,
        'error_counts' => $error_counts,
        'affected_users' => $affected_users,
        'stats' => [
            'avg_error_rate' => count($error_rates) > 0 ? round(array_sum($error_rates) / count($error_rates), 2) : 0,
            'peak_error_rate' => max($error_rates),
            'total_errors' => array_sum($error_counts),
            'max_affected_users' => max($affected_users),
            'error_free_periods' => count(array_filter($error_rates, function($rate) { return $rate == 0; }))
        ]
    ];
}

/**
 * Get user activity trends over time.
 */
function local_alx_report_api_get_user_trends($days, $company_filter = '') {
    global $DB;
    
    $end_time = time();
    $start_time = $end_time - ($days * 24 * 3600);
    
    // Time grouping
    if ($days == 1) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600;
    } elseif ($days <= 7) {
        $time_format = '%Y-%m-%d %H:00:00';
        $interval_seconds = 3600 * 4;
    } elseif ($days <= 30) {
        $time_format = '%Y-%m-%d';
        $interval_seconds = 86400;
    } else {
        $time_format = '%Y-%U';
        $interval_seconds = 86400 * 7;
    }
    
    $where_conditions = ['timeaccessed >= ? AND timeaccessed <= ?'];
    $params = [$start_time, $end_time];
    
    if ($company_filter) {
        $where_conditions[] = 'company_shortname = ?';
        $params[] = $company_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Active users (users who made API calls)
    $sql = "
        SELECT 
            FROM_UNIXTIME(timeaccessed, '{$time_format}') as time_bucket,
            COUNT(DISTINCT userid) as active_users,
            COUNT(*) as total_requests,
            AVG(record_count) as avg_records_per_request
        FROM {local_alx_api_logs} 
        WHERE {$where_clause}
        GROUP BY FROM_UNIXTIME(timeaccessed, '{$time_format}')
        ORDER BY time_bucket ASC
    ";
    
    $results = $DB->get_records_sql($sql, $params);
    
    // Get new users (first-time API users in each period)
    $new_users_sql = "
        SELECT 
            FROM_UNIXTIME(first_access, '{$time_format}') as time_bucket,
            COUNT(*) as new_users
        FROM (
            SELECT userid, MIN(timeaccessed) as first_access
            FROM {local_alx_api_logs}
            WHERE {$where_clause}
            GROUP BY userid
        ) first_accesses
        GROUP BY FROM_UNIXTIME(first_access, '{$time_format}')
        ORDER BY time_bucket ASC
    ";
    
    $new_users_results = $DB->get_records_sql($new_users_sql, $params);
    
    $data = local_alx_report_api_fill_time_gaps($results, $start_time, $end_time, $interval_seconds, $time_format);
    $new_users_data = local_alx_report_api_fill_time_gaps($new_users_results, $start_time, $end_time, $interval_seconds, $time_format);
    
    $labels = [];
    $active_users = [];
    $new_users = [];
    $requests_per_user = [];
    
    foreach ($data as $i => $point) {
        $labels[] = local_alx_report_api_format_chart_label($point->time_bucket, $days);
        $active_users[] = (int)$point->active_users;
        
        $new_user_count = isset($new_users_data[$i]) ? (int)$new_users_data[$i]->new_users : 0;
        $new_users[] = $new_user_count;
        
        $requests_per_user[] = $point->active_users > 0 
            ? round($point->total_requests / $point->active_users, 1) 
            : 0;
    }
    
    return [
        'labels' => $labels,
        'active_users' => $active_users,
        'new_users' => $new_users,
        'requests_per_user' => $requests_per_user,
        'stats' => [
            'peak_active_users' => max($active_users),
            'avg_active_users' => count($active_users) > 0 ? round(array_sum($active_users) / count($active_users), 1) : 0,
            'total_new_users' => array_sum($new_users),
            'avg_requests_per_user' => count($requests_per_user) > 0 ? round(array_sum($requests_per_user) / count($requests_per_user), 1) : 0,
            'peak_requests_per_user' => max($requests_per_user)
        ]
    ];
}

/**
 * Get company usage trends.
 */
function local_alx_report_api_get_company_trends($days) {
    global $DB;
    
    $end_time = time();
    $start_time = $end_time - ($days * 24 * 3600);
    
    $sql = "
        SELECT 
            company_shortname,
            COUNT(*) as total_requests,
            COUNT(DISTINCT userid) as unique_users,
            AVG(response_time_ms) as avg_response_time,
            COUNT(CASE WHEN error_message IS NOT NULL AND error_message != '' THEN 1 END) as error_count
        FROM {local_alx_api_logs} 
        WHERE timeaccessed >= ? AND timeaccessed <= ?
        GROUP BY company_shortname
        ORDER BY total_requests DESC
        LIMIT 20
    ";
    
    $results = $DB->get_records_sql($sql, [$start_time, $end_time]);
    
    $labels = [];
    $values = [];
    $users = [];
    $error_rates = [];
    
    foreach ($results as $company) {
        $labels[] = $company->company_shortname ?: 'Unknown';
        $values[] = (int)$company->total_requests;
        $users[] = (int)$company->unique_users;
        
        $error_rate = $company->total_requests > 0 
            ? round(($company->error_count / $company->total_requests) * 100, 2) 
            : 0;
        $error_rates[] = $error_rate;
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'users' => $users,
        'error_rates' => $error_rates,
        'stats' => [
            'total_companies' => count($labels),
            'total_requests' => array_sum($values),
            'avg_requests_per_company' => count($values) > 0 ? round(array_sum($values) / count($values), 1) : 0,
            'top_company_requests' => max($values),
            'avg_error_rate' => count($error_rates) > 0 ? round(array_sum($error_rates) / count($error_rates), 2) : 0
        ]
    ];
}

/**
 * Fill gaps in time series data.
 */
function local_alx_report_api_fill_time_gaps($results, $start_time, $end_time, $interval_seconds, $time_format) {
    $filled_data = [];
    $results_array = array_values($results);
    $current_time = $start_time;
    
    while ($current_time <= $end_time) {
        $time_bucket = date($time_format === '%Y-%U' ? 'Y-W' : ($time_format === '%Y-%m-%d' ? 'Y-m-d' : 'Y-m-d H:i:s'), $current_time);
        if ($time_format === '%Y-%m-%d %H:00:00') {
            $time_bucket = date('Y-m-d H:00:00', $current_time);
        }
        
        // Find matching result
        $found = false;
        foreach ($results_array as $result) {
            if ($result->time_bucket === $time_bucket) {
                $filled_data[] = $result;
                $found = true;
                break;
            }
        }
        
        if (!found) {
            // Create empty data point
            $empty_point = new stdClass();
            $empty_point->time_bucket = $time_bucket;
            $empty_point->total_calls = 0;
            $empty_point->total_requests = 0;
            $empty_point->unique_users = 0;
            $empty_point->unique_companies = 0;
            $empty_point->total_records = 0;
            $empty_point->avg_response_time = 0;
            $empty_point->successful_requests = 0;
            $empty_point->error_count = 0;
            $empty_point->max_response_time = 0;
            $empty_point->min_response_time = 0;
            $empty_point->users_with_errors = 0;
            $empty_point->companies_with_errors = 0;
            $empty_point->new_users = 0;
            
            $filled_data[] = $empty_point;
        }
        
        $current_time += $interval_seconds;
    }
    
    return $filled_data;
}

/**
 * Format chart labels based on time period.
 */
function local_alx_report_api_format_chart_label($time_bucket, $days) {
    if ($days == 1) {
        // For 24 hours, show hour format
        return date('H:i', strtotime($time_bucket));
    } elseif ($days <= 7) {
        // For week, show day and hour
        return date('M j H:i', strtotime($time_bucket));
    } elseif ($days <= 30) {
        // For month, show day format
        return date('M j', strtotime($time_bucket));
    } else {
        // For longer periods, show week format
        if (strpos($time_bucket, '-W') !== false) {
            list($year, $week) = explode('-W', $time_bucket);
            return "Week $week";
        } else {
            return date('M j', strtotime($time_bucket));
        }
    }
}
?> 