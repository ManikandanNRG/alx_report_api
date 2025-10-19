<?php
/**
 * Quick diagnostic script to test if plugin is working
 * Access via: http://your-moodle/local/alx_report_api/test_connection.php
 */

require_once(__DIR__ . '/../../config.php');

echo "<!DOCTYPE html><html><head><title>ALX API Test</title></head><body>";
echo "<h1>ALX Report API - Diagnostic Test</h1>";

try {
    echo "<h2>✅ Step 1: Moodle Config Loaded</h2>";
    
    echo "<h2>Testing Database Connection...</h2>";
    global $DB;
    
    // Test basic DB connection
    $version = $DB->get_field('config', 'value', ['name' => 'version']);
    echo "<p>✅ Database connected. Moodle version: " . $version . "</p>";
    
    // Check if our tables exist
    echo "<h2>Checking Plugin Tables...</h2>";
    $tables = [
        \local_alx_report_api\constants::TABLE_LOGS,
        \local_alx_report_api\constants::TABLE_SETTINGS,
        \local_alx_report_api\constants::TABLE_REPORTING,
        \local_alx_report_api\constants::TABLE_SYNC_STATUS,
        \local_alx_report_api\constants::TABLE_CACHE,
        \local_alx_report_api\constants::TABLE_ALERTS
    ];
    
    foreach ($tables as $table) {
        if ($DB->get_manager()->table_exists($table)) {
            $count = $DB->count_records($table);
            echo "<p>✅ Table '{$table}' exists ({$count} records)</p>";
        } else {
            echo "<p>❌ Table '{$table}' MISSING</p>";
        }
    }
    
    // Check field names in logs table
    echo "<h2>Checking Field Names in logs table...</h2>";
    if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
        $columns = $DB->get_columns(\local_alx_report_api\constants::TABLE_LOGS);
        echo "<p>Fields in local_alx_api_logs:</p><ul>";
        foreach ($columns as $column) {
            echo "<li>" . $column->name . " (" . $column->type . ")</li>";
        }
        echo "</ul>";
    }
    
    // Test lib.php functions
    echo "<h2>Testing lib.php Functions...</h2>";
    require_once(__DIR__ . '/lib.php');
    
    // Test get_companies
    $companies = local_alx_report_api_get_companies();
    echo "<p>✅ get_companies() works. Found " . count($companies) . " companies</p>";
    
    echo "<h2>✅ ALL TESTS PASSED!</h2>";
    echo "<p><a href='control_center.php'>Try Control Center</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERROR FOUND!</h2>";
    echo "<pre style='background: #fee; padding: 20px; border: 2px solid red;'>";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}

echo "</body></html>";
