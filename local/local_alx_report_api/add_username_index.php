<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manual script to add username index to local_alx_api_reporting table.
 * Run this if the upgrade didn't create the index automatically.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up page
$PAGE->set_url('/local/alx_report_api/add_username_index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Add Username Index');
$PAGE->set_heading('Add Username Index to Reporting Table');

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
echo '<h2>Add Username Index to Reporting Table</h2>';

global $DB;
$dbman = $DB->get_manager();

try {
    // Check if table exists
    $table = new xmldb_table('local_alx_api_reporting');
    
    if (!$dbman->table_exists($table)) {
        echo '<div class="alert alert-danger">❌ Error: Table local_alx_api_reporting does not exist!</div>';
        echo $OUTPUT->footer();
        exit;
    }
    
    echo '<div class="alert alert-info">✅ Table local_alx_api_reporting exists</div>';
    
    // Check if username field exists
    $field = new xmldb_field('username');
    if (!$dbman->field_exists($table, $field)) {
        echo '<div class="alert alert-danger">❌ Error: Username field does not exist in the table!</div>';
        echo $OUTPUT->footer();
        exit;
    }
    
    echo '<div class="alert alert-info">✅ Username field exists</div>';
    
    // Check if index already exists
    $index = new xmldb_index('username', XMLDB_INDEX_NOTUNIQUE, array('username'));
    
    if ($dbman->index_exists($table, $index)) {
        echo '<div class="alert alert-warning">⚠️ Index already exists! No action needed.</div>';
    } else {
        echo '<div class="alert alert-info">📝 Index does not exist. Creating now...</div>';
        
        // Add the index
        $dbman->add_index($table, $index);
        
        echo '<div class="alert alert-success">✅ SUCCESS! Username index has been created successfully!</div>';
        
        // Verify it was created
        if ($dbman->index_exists($table, $index)) {
            echo '<div class="alert alert-success">✅ VERIFIED: Index exists in database</div>';
        } else {
            echo '<div class="alert alert-danger">❌ WARNING: Index creation reported success but verification failed</div>';
        }
    }
    
    // Show current indexes
    echo '<h3>Current Indexes on local_alx_api_reporting:</h3>';
    echo '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace;">';
    
    // Get indexes using raw SQL
    $sql = "SHOW INDEX FROM {local_alx_api_reporting}";
    $indexes = $DB->get_records_sql($sql);
    
    if ($indexes) {
        echo '<table class="table table-bordered" style="background: white;">';
        echo '<thead><tr><th>Key Name</th><th>Column</th><th>Non Unique</th><th>Seq in Index</th></tr></thead>';
        echo '<tbody>';
        foreach ($indexes as $idx) {
            $highlight = ($idx->key_name === 'username') ? 'style="background: #d4edda; font-weight: bold;"' : '';
            echo "<tr $highlight>";
            echo "<td>{$idx->key_name}</td>";
            echo "<td>{$idx->column_name}</td>";
            echo "<td>{$idx->non_unique}</td>";
            echo "<td>{$idx->seq_in_index}</td>";
            echo "</tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No indexes found or unable to retrieve index information.</p>';
    }
    
    echo '</div>';
    
    echo '<h3>Verification SQL:</h3>';
    echo '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace;">';
    echo '<p>Run this in your MySQL client to verify:</p>';
    echo '<code>SHOW INDEX FROM mdl_local_alx_api_reporting WHERE Key_name = \'username\';</code>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">❌ ERROR: ' . $e->getMessage() . '</div>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

echo '<div style="margin-top: 20px;">';
echo '<a href="' . $CFG->wwwroot . '/admin/index.php" class="btn btn-primary">← Back to Site Administration</a>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
