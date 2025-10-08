<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task to check system conditions and send alerts.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_alx_report_api\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

/**
 * Scheduled task to check system conditions and send alerts.
 */
class check_alerts_task extends \core\task\scheduled_task {
    
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('check_alerts_task', 'local_alx_report_api');
    }
    
    /**
     * Execute the task.
     */
    public function execute() {
        mtrace('ALX Report API: Starting alert check...');
        
        // Check if alerting is enabled
        $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
        if (!$alerting_enabled) {
            mtrace('ALX Report API: Alerting is disabled. Skipping alert check.');
            return;
        }
        
        // Call the alert checking function
        local_alx_report_api_check_and_alert();
        
        mtrace('ALX Report API: Alert check completed.');
    }
}
