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
 * Upgrade code for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2023 ALX Report API
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade local_alx_report_api.
 * Fixed version that creates tables matching install.xml exactly
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_alx_report_api_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    try {
        // Log upgrade attempt
        error_log("ALX Report API Upgrade: Starting upgrade from version {$oldversion}");
        
        // Create local_alx_api_logs table if it doesn't exist (matches install.xml exactly)
        if (!$dbman->table_exists('local_alx_api_logs')) {
            error_log("ALX Report API Upgrade: Creating local_alx_api_logs table");
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
            error_log("ALX Report API Upgrade: Created local_alx_api_logs table successfully");
        } else {
            error_log("ALX Report API Upgrade: local_alx_api_logs table already exists, skipping");
        }

        // Create local_alx_api_cache table if it doesn't exist (matches install.xml exactly)
        if (!$dbman->table_exists('local_alx_api_cache')) {
            error_log("ALX Report API Upgrade: Creating local_alx_api_cache table");
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
            error_log("ALX Report API Upgrade: Created local_alx_api_cache table successfully");
        } else {
            error_log("ALX Report API Upgrade: local_alx_api_cache table already exists, skipping");
        }

        // Create local_alx_api_reporting table if it doesn't exist (matches install.xml exactly)
        if (!$dbman->table_exists('local_alx_api_reporting')) {
            error_log("ALX Report API Upgrade: Creating local_alx_api_reporting table");
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
            error_log("ALX Report API Upgrade: Created local_alx_api_reporting table successfully");
        } else {
            error_log("ALX Report API Upgrade: local_alx_api_reporting table already exists, skipping");
        }

        // Create external service if it doesn't exist
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        if (!$service) {
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
            if (!$service) {
                error_log("ALX Report API Upgrade: Creating external service");
                $servicedata = new stdClass();
                $servicedata->name = 'ALX Report API Custom Service';
                $servicedata->shortname = 'alx_report_api_custom';
                $servicedata->component = 'local_alx_report_api';
                $servicedata->timecreated = time();
                $servicedata->timemodified = time();
                $servicedata->enabled = 1;
                $servicedata->restrictedusers = 0;
                $servicedata->downloadfiles = 0;
                $servicedata->uploadfiles = 0;
                
                $serviceid = $DB->insert_record('external_services', $servicedata);
                
                // Add functions to the service
                $functions = [
                    'local_alx_report_api_get_course_progress',
                    'local_alx_report_api_get_user_courses',
                    'local_alx_report_api_get_company_progress',
                    'local_alx_report_api_get_course_completions'
                ];
                
                foreach ($functions as $functionname) {
                    if ($DB->record_exists('external_functions', ['name' => $functionname])) {
                        $servicefunction = new stdClass();
                        $servicefunction->externalserviceid = $serviceid;
                        $servicefunction->functionname = $functionname;
                        $DB->insert_record('external_services_functions', $servicefunction);
                    }
                }
                error_log("ALX Report API Upgrade: External service created successfully");
            } else {
                error_log("ALX Report API Upgrade: External service already exists, skipping");
            }
        } else {
            error_log("ALX Report API Upgrade: External service already exists, skipping");
        }

        // Create initial admin token if none exists
        if ($dbman->table_exists('local_alx_api_tokens') && !$DB->record_exists('local_alx_api_tokens', [])) {
            error_log("ALX Report API Upgrade: Creating initial admin token");
            $token = new stdClass();
            $token->token = bin2hex(random_bytes(32));
            $token->companyid = 0;
            $token->company_shortname = 'system';
            $token->created = time();
            $token->expires = 0;
            $token->is_active = 1;
            
            $DB->insert_record('local_alx_api_tokens', $token);
            error_log("ALX Report API Upgrade: Initial admin token created successfully");
        } else {
            error_log("ALX Report API Upgrade: Admin token already exists or tokens table not available, skipping");
        }

        error_log("ALX Report API Upgrade: Upgrade completed successfully");
        return true;

    } catch (Exception $e) {
        error_log('ALX Report API Upgrade Error: ' . $e->getMessage());
        error_log('ALX Report API Upgrade Error Stack: ' . $e->getTraceAsString());
        // Return false to indicate failure, but don't throw the exception to prevent infinite loading
        return false;
    }
} 
