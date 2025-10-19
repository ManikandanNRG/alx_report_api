<?php
/**
 * Simple test to verify cache clear function works
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$companyid = optional_param('companyid', 1, PARAM_INT);

echo "<h2>Cache Clear Test</h2>";

// Show cache before clearing
$cache_before = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p><strong>Cache entries BEFORE clearing:</strong> $cache_before</p>";

if ($cache_before > 0) {
    // Show some cache records
    $records = $DB->get_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid], 'timecreated DESC', '*', 0, 3);
    echo "<p><strong>Sample cache records:</strong></p>";
    echo "<ul>";
    foreach ($records as $record) {
        echo "<li>ID: {$record->id}, Key: " . substr($record->cache_key, 0, 50) . "..., Created: " . date('Y-m-d H:i:s', $record->timecreated) . "</li>";
    }
    echo "</ul>";
}

// Test the clear function
echo "<p><strong>Testing local_alx_report_api_cache_clear_company($companyid)...</strong></p>";

try {
    $cleared = local_alx_report_api_cache_clear_company($companyid);
    echo "<p style='color: green;'>✅ Function executed successfully!</p>";
    echo "<p><strong>Entries cleared:</strong> $cleared</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show cache after clearing
$cache_after = $DB->count_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid]);
echo "<p><strong>Cache entries AFTER clearing:</strong> $cache_after</p>";

if ($cache_after > 0) {
    echo "<p style='color: orange;'>⚠️ Warning: Cache entries still exist after clearing!</p>";
    $remaining = $DB->get_records(\local_alx_report_api\constants::TABLE_CACHE, ['companyid' => $companyid], 'timecreated DESC', '*', 0, 5);
    echo "<p><strong>Remaining cache records:</strong></p>";
    echo "<ul>";
    foreach ($remaining as $record) {
        echo "<li>ID: {$record->id}, Key: " . substr($record->cache_key, 0, 50) . "..., Created: " . date('Y-m-d H:i:s', $record->timecreated) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ All cache entries cleared successfully!</p>";
}

echo "<p><a href='populate_reporting_table.php?cache_company=$companyid#cache-management'>Back to Cache Management</a></p>";