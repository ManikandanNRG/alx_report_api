<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$companyid = 42;

echo "<h2>Cache Clear Debug Test</h2>";

// Check if table exists
$table_exists = $DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_CACHE);
echo "<p>Table exists: " . ($table_exists ? 'YES' : 'NO') . "</p>";

// Count before
$count_before = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p>Cache entries BEFORE clear: <strong>$count_before</strong></p>";

// Show the records
$records = $DB->get_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p>Records found:</p><pre>";
print_r($records);
echo "</pre>";

// Try to delete
echo "<h3>Attempting to delete...</h3>";
try {
    $deleted = $DB->delete_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
    echo "<p>Delete result: <strong>$deleted</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}

// Count after
$count_after = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p>Cache entries AFTER clear: <strong>$count_after</strong></p>";

// Test the function
echo "<h3>Testing local_alx_report_api_cache_clear_company() function</h3>";
$cleared = local_alx_report_api_cache_clear_company($companyid);
echo "<p>Function returned: <strong>$cleared</strong></p>";

// Final count
$count_final = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p>Cache entries FINAL: <strong>$count_final</strong></p>";

echo "<hr>";
echo "<p><a href='populate_reporting_table.php'>Back to Populate Reporting Table</a></p>";
