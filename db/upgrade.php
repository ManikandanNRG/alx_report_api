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
 * Upgrade script for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool True on success
 */
function xmldb_local_alx_report_api_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024011502) {
        // Ensure web services are enabled.
        if (!get_config('moodle', 'enablewebservices')) {
            set_config('enablewebservices', 1);
        }

        // Ensure REST protocol is enabled.
        $enabledprotocols = get_config('moodle', 'webserviceprotocols');
        if (strpos($enabledprotocols, 'rest') === false) {
            if (empty($enabledprotocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $enabledprotocols . ',rest');
            }
        }

        // Ensure our service exists and is properly configured.
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if (!$service) {
            // Create the service if it doesn't exist.
            $service = new stdClass();
            $service->name = 'alx_report_api';
            $service->shortname = 'alx_report_api';
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timecreated = time();
            $service->timemodified = time();
            
            $serviceid = $DB->insert_record('external_services', $service);
            
            // Add function to service.
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $function);
        } else {
            // Update existing service to ensure it's properly configured.
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            
            // Ensure the function is added to the service.
            $function_exists = $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$function_exists) {
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->insert_record('external_services_functions', $function);
            }
        }

        // Ensure the log table exists.
        $table = new xmldb_table('local_alx_api_logs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('endpoint', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);
            $table->add_field('useragent', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011502, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011509) {
        // Create company settings table.
        $table = new xmldb_table('local_alx_api_settings');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting_name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting_value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_company_setting', XMLDB_KEY_UNIQUE, ['companyid', 'setting_name']);
            
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('setting_name', XMLDB_INDEX_NOTUNIQUE, ['setting_name']);

            $dbman->create_table($table);
        }

        // Migrate old service shortname from 'brilliapi' to 'alx_report_api' for existing installations
        $old_service = $DB->get_record('external_services', ['shortname' => 'brilliapi']);
        if ($old_service) {
            $old_service->shortname = 'alx_report_api';
            $old_service->name = 'ALX Report API Service';
            $old_service->restrictedusers = 1;
            $old_service->enabled = 0; // Start disabled for admin configuration
            $old_service->timemodified = time();
            $DB->update_record('external_services', $old_service);
            
            // Update function name in service functions
            $old_function = $DB->get_record('external_services_functions', [
                'externalserviceid' => $old_service->id,
                'functionname' => 'local_brilliapi_get_course_progress'
            ]);
            if ($old_function) {
                $old_function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->update_record('external_services_functions', $old_function);
            }
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011509, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011519) {
        // Clean up and ensure proper service configuration
        
        // Remove any duplicate services
        $services = $DB->get_records('external_services', ['shortname' => 'alx_report_api']);
        if (count($services) > 1) {
            // Keep the first one, remove duplicates
            $keep_service = reset($services);
            foreach ($services as $service) {
                if ($service->id != $keep_service->id) {
                    $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
                    $DB->delete_records('external_services_users', ['externalserviceid' => $service->id]);
                    $DB->delete_records('external_services', ['id' => $service->id]);
                }
            }
        }
        
        // Ensure our service is properly configured
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->name = 'ALX Report API Service';
            $service->restrictedusers = 1;
            $service->enabled = 0; // Start disabled for admin configuration
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            
            // Ensure correct function is associated
            $DB->delete_records('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_brilliapi_get_course_progress'
            ]);
            
            $correct_function = $DB->get_record('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$correct_function) {
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->insert_record('external_services_functions', $function);
            }
        }
        
        // Clean up old brilliapi service if it still exists
        $old_service = $DB->get_record('external_services', ['shortname' => 'brilliapi']);
        if ($old_service) {
            $DB->delete_records('external_services_functions', ['externalserviceid' => $old_service->id]);
            $DB->delete_records('external_services_users', ['externalserviceid' => $old_service->id]);
            $DB->delete_records('external_services', ['id' => $old_service->id]);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011519, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011520) {
        // Fix access control exception by updating service configuration
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->restrictedusers = 0; // Set to 0 since we handle user restriction manually
            $service->enabled = 1; // Enable the service
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011520, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011521) {
        // Fix service to be custom with proper authentication
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->restrictedusers = 1; // MUST be 1 for custom service with user restrictions
            $service->enabled = 1; // Enable the service
            $service->name = 'ALX Report API Service';
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011521, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011523) {
        // Remove any built-in services and create proper custom service
        
        // Remove old built-in service if it exists
        $old_builtin_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($old_builtin_service) {
            $DB->delete_records('external_services_functions', ['externalserviceid' => $old_builtin_service->id]);
            $DB->delete_records('external_services_users', ['externalserviceid' => $old_builtin_service->id]);
            $DB->delete_records('external_services', ['id' => $old_builtin_service->id]);
        }
        
        // Create custom service if it doesn't exist
        $custom_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        if (!$custom_service) {
            $service = new stdClass();
            $service->name = 'ALX Report API Service';
            $service->shortname = 'alx_report_api_custom';
            $service->enabled = 1;
            $service->restrictedusers = 1;  // This makes it a CUSTOM service
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timecreated = time();
            $service->timemodified = time();
            
            $serviceid = $DB->insert_record('external_services', $service);
            
            // Add function to the custom service
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $function);
        }
        
        // Clear all caches
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011523, 'local', 'alx_report_api');
    }

    return true;
} 
