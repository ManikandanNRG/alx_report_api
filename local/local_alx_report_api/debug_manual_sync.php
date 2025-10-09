<?php
/**
 * Debug script to test manual sync and see detailed output
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

require_login();

if (!is_siteadmin()) {
    die('Access denied. Only site administrators can access this script.');
}

// Get company ID from URL parameter
$companyid = optional_param('companyid', 301, PARAM_INT);

echo "<h1>Manual Sync Debug - Company ID: {$companyid}</h1>";
echo "<p><a href='?companyid={$companyid}'>Refresh</a> | <a href='sync_reporting_data.php'>Back to Manual Sync</a></p>";

// Get company info
$company = $DB->get_record('company', ['id' => $companyid]);
if (!$company) {
    die("<p style='color:red;'>Company {$companyid} not found!</p>");
}

echo "<h2>Company Information</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><td>{$company->id}</td></tr>";
echo "<tr><th>Name</th><td>{$company->name}</td></tr>";
echo "<tr><th>Shortname</th><td>{$company->shortname}</td></tr>";
echo "</table>";

// Get enabled courses
echo "<h2>Step 1: Get Enabled Courses</h2>";
$enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
echo "<p>Enabled courses from settings: " . count($enabled_courses) . "</p>";

if (empty($enabled_courses)) {
    echo "<p style='color:orange;'>No courses enabled in settings, getting all company courses...</p>";
    $company_courses = local_alx_report_api_get_company_courses($companyid);
    $enabled_courses = array_column($company_courses, 'id');
    echo "<p>Company courses found: " . count($enabled_courses) . "</p>";
}

if (empty($enabled_courses)) {
    die("<p style='color:red;'>No courses found for this company!</p>");
}

echo "<p><strong>Enabled course IDs:</strong> " . implode(', ', $enabled_courses) . "</p>";

// Build SQL query
echo "<h2>Step 2: Build SQL Query</h2>";
list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');

$sql = "
    SELECT DISTINCT
        u.id as userid,
        u.firstname,
        u.lastname,
        u.email,
        c.id as courseid,
        c.fullname as coursename,
        COALESCE(cc.timecompleted, 0) as timecompleted,
        COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
        COALESCE(
            CASE 
                WHEN cc.timecompleted > 0 THEN 100.0
                ELSE 0.0
            END, 0.0) as percentage,
        CASE 
            WHEN cc.timecompleted > 0 THEN 'completed'
            WHEN ue.id IS NOT NULL THEN 'not_started'
            ELSE 'not_enrolled'
        END as status
    FROM {user} u
    JOIN {company_users} cu ON cu.userid = u.id
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid
    JOIN {course} c ON c.id = e.courseid
    LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
    WHERE cu.companyid = :companyid
        AND u.deleted = 0
        AND u.suspended = 0
        AND c.visible = 1
        AND c.id $course_sql
        AND ue.status = 0
    ORDER BY u.id, c.id";

$params = array_merge(['companyid' => $companyid], $course_params);

echo "<p><strong>SQL Parameters:</strong></p>";
echo "<pre>" . print_r($params, true) . "</pre>";

// Execute query
echo "<h2>Step 3: Execute Query</h2>";
$start_query = microtime(true);
$records = $DB->get_records_sql($sql, $params);
$query_time = microtime(true) - $start_query;

echo "<p><strong>Query executed in:</strong> " . round($query_time, 3) . " seconds</p>";
echo "<p><strong>Records found:</strong> " . count($records) . "</p>";

if (empty($records)) {
    die("<p style='color:red;'>No records found! The SQL query returned 0 results.</p>");
}

// Show first 10 records
echo "<h3>First 10 Records from Query:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Course ID</th><th>Course Name</th><th>Status</th><th>Percentage</th></tr>";
$count = 0;
foreach ($records as $record) {
    if ($count >= 10) break;
    echo "<tr>";
    echo "<td>{$record->userid}</td>";
    echo "<td>{$record->firstname} {$record->lastname}</td>";
    echo "<td>{$record->email}</td>";
    echo "<td>{$record->courseid}</td>";
    echo "<td>{$record->coursename}</td>";
    echo "<td>{$record->status}</td>";
    echo "<td>" . round($record->percentage, 1) . "%</td>";
    echo "</tr>";
    $count++;
}
echo "</table>";

// Check existing records in reporting table
echo "<h2>Step 4: Check Existing Records in Reporting Table</h2>";
$existing_count = $DB->count_records('local_alx_api_reporting', ['companyid' => $companyid]);
echo "<p><strong>Existing records in reporting table for this company:</strong> {$existing_count}</p>";

// Check which records need insert vs update
echo "<h3>Insert vs Update Analysis:</h3>";
$need_insert = 0;
$need_update = 0;
$sample_inserts = [];
$sample_updates = [];

foreach ($records as $record) {
    $existing = $DB->get_record('local_alx_api_reporting', [
        'userid' => $record->userid,
        'courseid' => $record->courseid,
        'companyid' => $companyid
    ]);
    
    if (!$existing) {
        $need_insert++;
        if (count($sample_inserts) < 5) {
            $sample_inserts[] = "User {$record->userid} ({$record->firstname} {$record->lastname}) - Course {$record->courseid}";
        }
    } else {
        $need_update++;
        if (count($sample_updates) < 5) {
            $sample_updates[] = "User {$record->userid} ({$record->firstname} {$record->lastname}) - Course {$record->courseid}";
        }
    }
}

echo "<p><strong>Records needing INSERT:</strong> {$need_insert}</p>";
if (!empty($sample_inserts)) {
    echo "<ul>";
    foreach ($sample_inserts as $sample) {
        echo "<li>{$sample}</li>";
    }
    echo "</ul>";
}

echo "<p><strong>Records needing UPDATE:</strong> {$need_update}</p>";
if (!empty($sample_updates)) {
    echo "<ul>";
    foreach ($sample_updates as $sample) {
        echo "<li>{$sample}</li>";
    }
    echo "</ul>";
}

// Now call the actual function
echo "<h2>Step 5: Call populate_reporting_table() Function</h2>";
echo "<p>Calling function with companyid={$companyid}, batch_size=1000, output_progress=false...</p>";

$before_count = $DB->count_records('local_alx_api_reporting', ['companyid' => $companyid]);
echo "<p><strong>Before count:</strong> {$before_count}</p>";

$start_time = microtime(true);
$result = local_alx_report_api_populate_reporting_table($companyid, 1000, false);
$execution_time = microtime(true) - $start_time;

$after_count = $DB->count_records('local_alx_api_reporting', ['companyid' => $companyid]);
echo "<p><strong>After count:</strong> {$after_count}</p>";

echo "<h3>Function Result:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Key</th><th>Value</th></tr>";
foreach ($result as $key => $value) {
    if ($key === 'errors' && is_array($value)) {
        $value = empty($value) ? 'None' : implode('<br>', $value);
    }
    echo "<tr><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
}
echo "<tr><td><strong>actual_execution_time</strong></td><td>" . round($execution_time, 3) . " seconds</td></tr>";
echo "<tr><td><strong>records_created</strong></td><td>" . ($after_count - $before_count) . "</td></tr>";
echo "</table>";

// Final summary
echo "<h2>Summary</h2>";
echo "<table border='1' cellpadding='5' style='background-color: #f0f0f0;'>";
echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>";
echo "<tr><td>SQL Query Found</td><td>" . count($records) . " records</td><td style='color:green;'>✓</td></tr>";
echo "<tr><td>Function Returned total_processed</td><td>{$result['total_processed']}</td><td style='color:" . ($result['total_processed'] > 0 ? 'green' : 'red') . ";'>" . ($result['total_processed'] > 0 ? '✓' : '✗') . "</td></tr>";
echo "<tr><td>Function Returned total_inserted</td><td>{$result['total_inserted']}</td><td style='color:" . ($result['total_inserted'] > 0 ? 'green' : 'orange') . ";'>" . ($result['total_inserted'] > 0 ? '✓' : '⚠') . "</td></tr>";
echo "<tr><td>Actual Records Created</td><td>" . ($after_count - $before_count) . "</td><td style='color:" . (($after_count - $before_count) > 0 ? 'green' : 'orange') . ";'>" . (($after_count - $before_count) > 0 ? '✓' : '⚠') . "</td></tr>";
echo "<tr><td>Errors</td><td>" . count($result['errors']) . "</td><td style='color:" . (count($result['errors']) == 0 ? 'green' : 'red') . ";'>" . (count($result['errors']) == 0 ? '✓' : '✗') . "</td></tr>";
echo "</table>";

if ($result['total_processed'] == 0 && count($records) > 0) {
    echo "<h3 style='color:red;'>⚠️ PROBLEM IDENTIFIED!</h3>";
    echo "<p>The SQL query found " . count($records) . " records, but the function returned total_processed = 0.</p>";
    echo "<p>This means the function is NOT processing the records it fetches.</p>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>";
    echo "<li>The while loop is breaking immediately</li>";
    echo "<li>An exception is being caught silently</li>";
    echo "<li>The batch processing logic has a bug</li>";
    echo "</ul>";
} else if ($result['total_processed'] > 0) {
    echo "<h3 style='color:green;'>✓ Function is working correctly!</h3>";
    echo "<p>The function processed {$result['total_processed']} records as expected.</p>";
}
