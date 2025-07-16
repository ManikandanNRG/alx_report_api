<?php
/**
 * Emergency table creation script for ALX Report API plugin
 * Run this script if tables are missing after installation
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

// Page setup
$PAGE->set_url('/local/alx_report_api/fix_missing_tables.php');
$PAGE->set_title('ALX Report API - Fix Missing Tables');
$PAGE->set_heading('Fix Missing Database Tables');
$PAGE->set_context(context_system::instance());

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
echo '<h2>🔧 ALX Report API - Emergency Table Creation</h2>';

// Check current table status
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>📊 Current Table Status</h3>';

$required_tables = [
    'local_alx_api_logs' => 'API access logging',
    'local_alx_api_settings' => 'Company-specific settings', 
    'local_alx_api_reporting' => 'Pre-built reporting data',
    'local_alx_api_sync_status' => 'Sync status tracking',
    'local_alx_api_cache' => 'Performance caching'
];

$dbman = $DB->get_manager();
$missing_tables = [];
$existing_tables = [];

foreach ($required_tables as $table_name => $description) {
    if ($dbman->table_exists($table_name)) {
        $existing_tables[] = $table_name;
        echo '<div style="color: green; margin: 5px 0;">✅ ' . $table_name . ' - ' . $description . '</div>';
    } else {
        $missing_tables[] = $table_name;
        echo '<div style="color: red; margin: 5px 0;">❌ ' . $table_name . ' - ' . $description . ' <strong>(MISSING)</strong></div>';
    }
}

echo '</div>';

// Handle form submission
if ($_POST && isset($_POST['create_tables'])) {
    echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    echo '<h3>🚀 Creating Missing Tables...</h3>';
    
    $success_count = 0;
    $error_count = 0;
    
    try {
        // Create local_alx_api_logs table
        if (!$dbman->table_exists('local_alx_api_logs')) {
            echo '<p>Creating local_alx_api_logs table...</p>';
            $table = new xmldb_table('local_alx_api_logs');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('company_shortname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('endpoint', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('record_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('response_time_ms', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
            $table->add_field('timeaccessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ip_address', XMLDB_TYPE_CHAR, '45', null, null, null, null);
            $table->add_field('user_agent', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('additional_data', XMLDB_TYPE_TEXT, null, null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $table->add_index('company_shortname', XMLDB_INDEX_NOTUNIQUE, array('company_shortname'));
            $table->add_index('endpoint', XMLDB_INDEX_NOTUNIQUE, array('endpoint'));
            $table->add_index('timeaccessed', XMLDB_INDEX_NOTUNIQUE, array('timeaccessed'));
            $table->add_index('response_time_ms', XMLDB_INDEX_NOTUNIQUE, array('response_time_ms'));

            $dbman->create_table($table);
            echo '<div style="color: green;">✅ local_alx_api_logs created successfully</div>';
            $success_count++;
        }

        // Create local_alx_api_cache table
        if (!$dbman->table_exists('local_alx_api_cache')) {
            echo '<p>Creating local_alx_api_cache table...</p>';
            $table = new xmldb_table('local_alx_api_cache');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cache_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cache_data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('cache_timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('hit_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('last_accessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('unique_cache_key', XMLDB_KEY_UNIQUE, array('cache_key', 'companyid'));
            $table->add_index('cache_key', XMLDB_INDEX_NOTUNIQUE, array('cache_key'));
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, array('companyid'));
            $table->add_index('expires_at', XMLDB_INDEX_NOTUNIQUE, array('expires_at'));
            $table->add_index('cache_timestamp', XMLDB_INDEX_NOTUNIQUE, array('cache_timestamp'));

            $dbman->create_table($table);
            echo '<div style="color: green;">✅ local_alx_api_cache created successfully</div>';
            $success_count++;
        }

        // Create local_alx_api_reporting table
        if (!$dbman->table_exists('local_alx_api_reporting')) {
            echo '<p>Creating local_alx_api_reporting table...</p>';
            $table = new xmldb_table('local_alx_api_reporting');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'not_started');
            $table->add_field('last_updated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('is_deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('unique_user_course', XMLDB_KEY_UNIQUE, array('userid', 'courseid', 'companyid'));
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, array('companyid'));
            $table->add_index('last_updated', XMLDB_INDEX_NOTUNIQUE, array('last_updated'));
            $table->add_index('userid_courseid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'courseid'));
            $table->add_index('timecompleted', XMLDB_INDEX_NOTUNIQUE, array('timecompleted'));
            $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));
            $table->add_index('is_deleted', XMLDB_INDEX_NOTUNIQUE, array('is_deleted'));

            $dbman->create_table($table);
            echo '<div style="color: green;">✅ local_alx_api_reporting created successfully</div>';
            $success_count++;
        }

        echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;">';
        echo '<h4 style="color: #155724; margin: 0;">🎉 Table Creation Complete!</h4>';
        echo '<p style="color: #155724; margin: 10px 0 0 0;">Successfully created ' . $success_count . ' missing tables.</p>';
        echo '</div>';

        // Refresh page to show updated status
        echo '<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>';

    } catch (Exception $e) {
        echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb;">';
        echo '<h4 style="color: #721c24; margin: 0;">❌ Error Creating Tables</h4>';
        echo '<p style="color: #721c24; margin: 10px 0 0 0;">Error: ' . $e->getMessage() . '</p>';
        echo '</div>';
        $error_count++;
    }
    
    echo '</div>';
}

// Show create button if there are missing tables
if (!empty($missing_tables)) {
    echo '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;">';
    echo '<h3 style="color: #856404;">⚠️ Action Required</h3>';
    echo '<p style="color: #856404;">You have ' . count($missing_tables) . ' missing tables that need to be created for the plugin to work properly.</p>';
    
    echo '<form method="post" style="margin: 20px 0;">';
    echo '<input type="hidden" name="create_tables" value="1">';
    echo '<button type="submit" style="background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">🔧 Create Missing Tables</button>';
    echo '</form>';
    echo '</div>';
} else {
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #c3e6cb;">';
    echo '<h3 style="color: #155724;">✅ All Tables Present</h3>';
    echo '<p style="color: #155724;">All required database tables are present and the plugin should work correctly.</p>';
    echo '</div>';
}

// Additional verification
echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>🔍 Additional System Checks</h3>';

// Check web services
$webservices_enabled = get_config('moodle', 'enablewebservices');
echo '<div style="margin: 10px 0;">';
echo $webservices_enabled ? 
    '<span style="color: green;">✅ Web services enabled</span>' : 
    '<span style="color: red;">❌ Web services disabled</span>';
echo '</div>';

// Check REST protocol
$protocols = get_config('moodle', 'webserviceprotocols');
$rest_enabled = strpos($protocols, 'rest') !== false;
echo '<div style="margin: 10px 0;">';
echo $rest_enabled ? 
    '<span style="color: green;">✅ REST protocol enabled</span>' : 
    '<span style="color: red;">❌ REST protocol disabled</span>';
echo '</div>';

// Check service
$service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
echo '<div style="margin: 10px 0;">';
echo $service ? 
    '<span style="color: green;">✅ ALX Report API service exists</span>' : 
    '<span style="color: red;">❌ ALX Report API service missing</span>';
echo '</div>';

echo '</div>';

// Quick links
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
echo '<h3>🔗 Quick Links</h3>';
echo '<ul>';
echo '<li><a href="' . $CFG->wwwroot . '/local/alx_report_api/control_center.php">Control Center</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/local/alx_report_api/company_settings.php">Company Settings</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/admin/webservice/tokens.php">Manage API Tokens</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/admin/settings.php?section=local_alx_report_api">Plugin Settings</a></li>';
echo '</ul>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
?>