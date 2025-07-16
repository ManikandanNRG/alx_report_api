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
 * Post installation and migration code for ALX Report API plugin.
 * Fixed version that allows proper table creation from install.xml
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to do any post-installation cleanup or setup.
 * This function runs AFTER install.xml creates the tables.
 *
 * @return bool
 */
function xmldb_local_alx_report_api_install() {
    global $DB, $CFG;

    try {
        error_log("ALX Report API Install: Starting post-installation setup");

        // Verify all required tables were created by install.xml
        $required_tables = [
            'local_alx_api_logs',
            'local_alx_api_settings', 
            'local_alx_api_reporting',
            'local_alx_api_sync_status',
            'local_alx_api_cache'
        ];

        $dbman = $DB->get_manager();
        $missing_tables = [];
        
        foreach ($required_tables as $table_name) {
            if (!$dbman->table_exists($table_name)) {
                $missing_tables[] = $table_name;
                error_log("ALX Report API Install: Missing table: {$table_name}");
            } else {
                error_log("ALX Report API Install: Table exists: {$table_name}");
            }
        }

        if (!empty($missing_tables)) {
            error_log("ALX Report API Install: ERROR - Missing tables: " . implode(', ', $missing_tables));
            error_log("ALX Report API Install: This indicates an issue with install.xml processing");
            // Don't return false - let the installation continue and we'll handle missing tables in upgrade
        } else {
            error_log("ALX Report API Install: All required tables created successfully");
        }

        // Create the external service if it doesn't exist
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        if (!$service) {
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
            if (!$service) {
                error_log("ALX Report API Install: Creating external service");
                // Create the service
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
                error_log("ALX Report API Install: External service created successfully");
            }
        }

        // Enable web services if not already enabled
        if (!$CFG->enablewebservices) {
            set_config('enablewebservices', 1);
            error_log("ALX Report API Install: Enabled web services");
        }

        // Enable REST protocol if not already enabled
        $protocols = explode(',', $CFG->webserviceprotocols ?? '');
        if (!in_array('rest', $protocols)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', array_filter($protocols)));
            error_log("ALX Report API Install: Enabled REST protocol");
        }

        error_log("ALX Report API Install: Fresh installation completed successfully");
        return true;

    } catch (Exception $e) {
        error_log('ALX Report API Install Error: ' . $e->getMessage());
        error_log('ALX Report API Install Error Stack: ' . $e->getTraceAsString());
        return false;
    }
}

/**
 * Post-installation token creation function
 * This function is called after tables are created to set up initial data
 */
function local_alx_report_api_post_install_setup() {
    global $DB;
    
    try {
        $dbman = $DB->get_manager();
        
        // Create initial admin token if tokens table exists and no tokens exist
        if ($dbman->table_exists('local_alx_api_tokens') && !$DB->record_exists('local_alx_api_tokens', [])) {
            error_log("ALX Report API Post-Install: Creating initial admin token");
            $token = new stdClass();
            $token->token = bin2hex(random_bytes(32));
            $token->companyid = 0; // System token
            $token->company_shortname = 'system';
            $token->created = time();
            $token->expires = 0; // Never expires
            $token->is_active = 1;
            
            $DB->insert_record('local_alx_api_tokens', $token);
            error_log("ALX Report API Post-Install: Initial admin token created: " . $token->token);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('ALX Report API Post-Install Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced post-installation verification function
 * This can be called from admin interfaces to verify and fix setup
 */
function local_alx_report_api_verify_installation() {
    global $DB;
    
    $issues = [];
    $fixes_applied = [];
    
    // Check if web services are enabled
    if (!get_config('moodle', 'enablewebservices')) {
        $issues[] = 'Web services not enabled';
        set_config('enablewebservices', 1);
        $fixes_applied[] = 'Enabled web services';
    }
    
    // Check if REST protocol is enabled
    $enabledprotocols = get_config('moodle', 'webserviceprotocols');
    if (strpos($enabledprotocols, 'rest') === false) {
        $issues[] = 'REST protocol not enabled';
        if (empty($enabledprotocols)) {
            set_config('webserviceprotocols', 'rest');
        } else {
            set_config('webserviceprotocols', $enabledprotocols . ',rest');
        }
        $fixes_applied[] = 'Enabled REST protocol';
    }
    
    // Check service exists
    $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$service) {
        $issues[] = 'ALX Report API service not found';
        
        // Create service
        $service_obj = new stdClass();
        $service_obj->name = 'ALX Report API Service';
        $service_obj->shortname = 'alx_report_api_custom';
        $service_obj->enabled = 1;
        $service_obj->restrictedusers = 1;
        $service_obj->downloadfiles = 0;
        $service_obj->uploadfiles = 0;
        $service_obj->timecreated = time();
        $service_obj->timemodified = time();
        
        $serviceid = $DB->insert_record('external_services', $service_obj);
        $service = $DB->get_record('external_services', ['id' => $serviceid]);
        $fixes_applied[] = 'Created ALX Report API service';
    }
    
    // Check function mapping
    if ($service) {
        $function_mapped = $DB->record_exists('external_services_functions', [
            'externalserviceid' => $service->id,
            'functionname' => 'local_alx_report_api_get_course_progress'
        ]);
        
        if (!$function_mapped) {
            $issues[] = 'Function not mapped to service';
            
            // Add function mapping
            $function = new stdClass();
            $function->externalserviceid = $service->id;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $function);
            $fixes_applied[] = 'Mapped function to service';
        }
    }
    
    return [
        'issues_found' => $issues,
        'fixes_applied' => $fixes_applied,
        'service_ready' => empty($issues) || !empty($fixes_applied)
    ];
} 
