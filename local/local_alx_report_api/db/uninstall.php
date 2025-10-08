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
 * Uninstall script for the ALX Report API plugin.
 * This file is automatically called when the plugin is uninstalled.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom uninstallation procedure.
 *
 * @return bool true on success
 */
function xmldb_local_alx_report_api_uninstall() {
    global $DB;

    // Get database manager
    $dbman = $DB->get_manager();

    // List of all plugin tables (in reverse order of dependencies)
    $tables = [
        'local_alx_api_alerts',
        'local_alx_api_cache',
        'local_alx_api_sync_status',
        'local_alx_api_reporting',
        'local_alx_api_settings',
        'local_alx_api_logs'
    ];

    // Drop each table if it exists
    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    // Clean up any config settings
    unset_config('maxrecords', 'local_alx_report_api');
    unset_config('logretention', 'local_alx_report_api');
    unset_config('ratelimit', 'local_alx_report_api');
    unset_config('allow_get_method', 'local_alx_report_api');
    unset_config('auto_sync_hours', 'local_alx_report_api');
    unset_config('max_sync_time', 'local_alx_report_api');
    
    // Clean up alert system settings
    unset_config('enable_alerting', 'local_alx_report_api');
    unset_config('alert_threshold', 'local_alx_report_api');
    unset_config('alert_emails', 'local_alx_report_api');
    unset_config('enable_email_alerts', 'local_alx_report_api');
    unset_config('alert_cooldown', 'local_alx_report_api');
    unset_config('high_api_usage_threshold', 'local_alx_report_api');
    unset_config('health_score_threshold', 'local_alx_report_api');
    unset_config('db_response_time_threshold', 'local_alx_report_api');

    return true;
}
