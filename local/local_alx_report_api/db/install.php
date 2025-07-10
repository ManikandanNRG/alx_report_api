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
 * Post installation and migration code for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Enhanced function to run after the plugin has been installed.
 * Includes robust error handling and verification to ensure function mapping always works.
 */
function xmldb_local_alx_report_api_install() {
    global $DB, $CFG;

    try {
        // Enable web services if not already enabled.
        if (!get_config('moodle', 'enablewebservices')) {
            set_config('enablewebservices', 1);
        }

        // Enable REST protocol if not already enabled.
        $enabledprotocols = get_config('moodle', 'webserviceprotocols');
        if (strpos($enabledprotocols, 'rest') === false) {
            if (empty($enabledprotocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $enabledprotocols . ',rest');
            }
        }

        // Create custom service (since we removed it from services.php)
        $existing_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        
        $serviceid = null;
        if (!$existing_service) {
            // Create new service
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
        } else {
            $serviceid = $existing_service->id;
        }

        // Enhanced function mapping with verification and retry logic
        if ($serviceid) {
            // Clear any existing functions for this service to avoid duplicates
            $DB->delete_records('external_services_functions', [
                'externalserviceid' => $serviceid,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            // Add function to the custom service with retry logic
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            
            $function_inserted = $DB->insert_record('external_services_functions', $function);
            
            // Verify the function was mapped correctly
            if ($function_inserted) {
                $verify_mapping = $DB->record_exists('external_services_functions', [
                    'externalserviceid' => $serviceid,
                    'functionname' => 'local_alx_report_api_get_course_progress'
                ]);
                
                if (!$verify_mapping) {
                    // Retry once more if verification failed
                    $DB->insert_record('external_services_functions', $function);
                }
            }
        }

        // Clear relevant caches to ensure changes take effect
        if (function_exists('cache_helper')) {
            cache_helper::purge_by_definition('core', 'external_services');
            cache_helper::purge_by_definition('core', 'external_functions');
        }

        // Set installation flag to track successful setup
        set_config('installation_completed', time(), 'local_alx_report_api');
        
        return true;
        
    } catch (Exception $e) {
        // Log error for debugging but don't fail installation
        error_log('ALX Report API installation error: ' . $e->getMessage());
        
        // Set flag to indicate manual setup needed
        set_config('manual_setup_required', 1, 'local_alx_report_api');
        
        return true; // Still return true to not fail entire installation
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
