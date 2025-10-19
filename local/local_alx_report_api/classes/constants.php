<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Database table name constants for ALX Report API Plugin
 * 
 * Centralizes all table names to prevent typos and make refactoring easier.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_alx_report_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Database table name constants
 */
class constants {
    /** @var string Main reporting table with course progress data */
    const TABLE_REPORTING = 'local_alx_api_reporting';
    
    /** @var string API access logs table */
    const TABLE_LOGS = 'local_alx_api_logs';
    
    /** @var string Company-specific settings table */
    const TABLE_SETTINGS = 'local_alx_api_settings';
    
    /** @var string Sync intelligence tracking table */
    const TABLE_SYNC_STATUS = 'local_alx_api_sync_status';
    
    /** @var string Response caching table */
    const TABLE_CACHE = 'local_alx_api_cache';
    
    /** @var string Alert system table */
    const TABLE_ALERTS = 'local_alx_api_alerts';
}
