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
 * External API for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

/**
 * External functions for the ALX Report API plugin.
 */
class local_alx_report_api_external extends external_api {

    /**
     * Validate HTTP method and security headers.
     *
     * @throws moodle_exception If validation fails
     */
    private static function validate_request_security() {
        // 1. Check if GET method is allowed (for development/testing)
        $allow_get_method = get_config('local_alx_report_api', 'allow_get_method');
        
        if (!$allow_get_method && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new moodle_exception('invalidrequestmethod', 'local_alx_report_api', '', null, 
                'Only POST method is allowed for security reasons');
        }

        // 2. Validate Content-Type for POST requests
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $allowed_types = [
            'application/x-www-form-urlencoded',
            'application/json',
            'multipart/form-data'
        ];
        
        $valid_content_type = false;
        foreach ($allowed_types as $type) {
            if (strpos($content_type, $type) === 0) {
                $valid_content_type = true;
                break;
            }
        }
        
        if (!$valid_content_type) {
            throw new moodle_exception('invalidcontenttype', 'local_alx_report_api', '', null, 
                'Invalid Content-Type header');
        }

        // 3. Add security headers to response
        self::add_security_headers();
    }

    /**
     * Extract and validate token from Authorization header.
     *
     * @return string|false Token if valid, false otherwise
     */
    private static function get_authorization_token() {
        // Check for Authorization header
        $headers = getallheaders();
        $auth_header = null;
        
        // Case-insensitive header search
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth_header = $value;
                break;
            }
        }
        
        // Fallback: Check $_SERVER for Authorization header
        if (!$auth_header) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Extract Bearer token
        if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }
        
        return false;
    }

    /**
     * Enhanced token validation with security checks.
     *
     * @param string $token API token
     * @return array Token validation result with user and company info
     * @throws moodle_exception If token is invalid
     */
    private static function validate_secure_token($token) {
        global $DB;
        
        if (empty($token)) {
            throw new moodle_exception('missingtoken', 'local_alx_report_api', '', null, 
                'Authorization token is required');
        }
        
        // 1. Basic token format validation (not empty, reasonable length)
        if (strlen($token) < 16) {
            throw new moodle_exception('invalidtokenformat', 'local_alx_report_api', '', null, 
                'Invalid token format');
        }
        
        // 2. Get token info from Moodle's external tokens table
        $tokenrecord = $DB->get_record('external_tokens', 
            ['token' => $token, 'externalserviceid' => self::get_service_id()]);
            
        if (!$tokenrecord) {
            throw new moodle_exception('invalidtoken', 'local_alx_report_api', '', null, 
                'Invalid or expired token');
        }
        
        // 3. Check if token is active
        if (!$tokenrecord->validuntil || $tokenrecord->validuntil < time()) {
            throw new moodle_exception('expiredtoken', 'local_alx_report_api', '', null, 
                'Token has expired');
        }
        
        // 4. Get user info
        $user = $DB->get_record('user', ['id' => $tokenrecord->userid]);
        if (!$user || $user->deleted || $user->suspended) {
            throw new moodle_exception('invaliduser', 'local_alx_report_api', '', null, 
                'User account is not active');
        }
        
        // 5. Get company association
        $companyid = self::get_user_company($user->id);
        if (!$companyid) {
            throw new moodle_exception('nocompanyassociation', 'local_alx_report_api', '', null, 
                'User is not associated with any company');
        }
        
        // 6. Log successful authentication
        self::log_security_event($user->id, $companyid, 'token_validated', 'success');
        
        return [
            'user' => $user,
            'companyid' => $companyid,
            'token' => $tokenrecord
        ];
    }

    /**
     * Check if the user has exceeded the daily rate limit.
     * Uses per-company rate limit if set, otherwise falls back to global default.
     *
     * @param int $userid User ID to check
     * @throws moodle_exception If rate limit is exceeded
     */
    private static function check_rate_limit($userid) {
        global $DB, $CFG;
        
        try {
            // Validate user ID
            if (empty($userid) || $userid <= 0) {
                error_log('ALX Report API: Invalid user ID provided to check_rate_limit');
                // Don't throw exception - just skip rate limiting for invalid users
                return;
            }
            
            // Get user's company ID
            $companyid = self::get_user_company($userid);
        
        // Get company-specific rate limit or global default
        $company_rate_limit = null;
        if ($companyid) {
            $company_rate_limit = local_alx_report_api_get_company_setting($companyid, 'rate_limit', null);
        }
        
        // Use company rate limit if set, otherwise use global default
        if ($company_rate_limit !== null && $company_rate_limit > 0) {
            $rate_limit = (int)$company_rate_limit;
        } else {
            $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
        }
        
        // Calculate start of today (midnight)
        $today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
        
        // Count requests from this user today
        $request_count = 0;
        if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
            // Use standard Moodle field name
            $time_field = 'timecreated';
            
            $request_count = $DB->count_records_select(
                \local_alx_report_api\constants::TABLE_LOGS, 
                "userid = ? AND {$time_field} >= ?", 
                [$userid, $today_start]
            );
        }
        
        // Check if limit exceeded
        if ($request_count >= $rate_limit) {
            // Log ONLY the first rejection (limit + 1) so we can detect violation
            // Don't log subsequent rejections to avoid inflating the count
            if ($request_count == $rate_limit) {
                // Get company shortname and name for logging
                $company_shortname = '';
                $company_name = '';
                if ($companyid) {
                    $company = $DB->get_record('company', ['id' => $companyid], 'shortname, name');
                    if ($company) {
                        $company_shortname = $company->shortname;
                        $company_name = $company->name;
                    }
                }
                
                // Log to API logs table
                if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
                    $log = new stdClass();
                    $log->userid = $userid;
                    $log->company_shortname = $company_shortname;
                    $log->endpoint = 'get_course_progress';
                    $log->record_count = 0;
                    $log->error_message = "Rate limit exceeded: {$request_count}/{$rate_limit} requests";
                    $log->response_time_ms = 0;
                    $log->timecreated = time();
                    $log->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    $DB->insert_record(\local_alx_report_api\constants::TABLE_LOGS, $log);
                }
                
                // Also create an alert for the Security tab
                if ($DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_ALERTS)) {
                    $user = $DB->get_record('user', ['id' => $userid], 'username, firstname, lastname');
                    $username = $user ? fullname($user) : 'Unknown';
                    
                    $alert = new stdClass();
                    $alert->alert_type = 'rate_limit_exceeded';
                    $alert->severity = 'high';
                    // Store plain text - will be formatted with colors in the UI
                    $alert->message = "User {$username} from {$company_name} exceeded rate limit ({$request_count}/{$rate_limit} requests)";
                    $alert->hostname = $_SERVER['REMOTE_ADDR'] ?? '';
                    $alert->resolved = 0;
                    $alert->timecreated = time();
                    
                    $DB->insert_record(\local_alx_report_api\constants::TABLE_ALERTS, $alert);
                }
            }
            
            throw new moodle_exception('ratelimitexceeded', 'local_alx_report_api', '', null, 
                "Daily rate limit exceeded. You have made {$request_count} requests today. Limit is {$rate_limit} requests per day. Try again tomorrow.");
        }
        
        } catch (moodle_exception $e) {
            // Re-throw rate limit exceptions (these are expected)
            throw $e;
            
        } catch (Exception $e) {
            // Log unexpected errors but don't block API access
            error_log('ALX Report API: Error checking rate limit - ' . $e->getMessage());
            // Don't throw - allow API call to proceed if rate limiting fails
        }
    }

    /**
     * Get the service ID for alx_report_api_custom or alx_report_api.
     *
     * @return int Service ID
     */
    private static function get_service_id() {
        global $DB;
        
        static $service_id = null;
        if ($service_id === null) {
            // Check for alx_report_api_custom first (primary service name)
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
            if (!$service) {
                // Fallback to alx_report_api for compatibility
                $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
            }
            $service_id = $service ? $service->id : 0;
        }
        
        return $service_id;
    }

    /**
     * Add security headers to the response.
     */
    private static function add_security_headers() {
        // Only add essential headers that won't break existing workflows
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS headers for cross-origin requests (like Power BI)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }

    /**
     * Log security events for monitoring.
     *
     * @param int $userid User ID
     * @param int $companyid Company ID
     * @param string $event_type Type of security event
     * @param string $status Status (success, failure, warning)
     * @param string $details Additional details
     */
    private static function log_security_event($userid, $companyid, $event_type, $status, $details = '') {
        global $DB;
        
        if (!$DB->get_manager()->table_exists(\local_alx_report_api\constants::TABLE_LOGS)) {
            return;
        }
        
        $log = new stdClass();
        $log->userid = $userid;
        $log->companyid = $companyid;
        $log->endpoint = 'security_' . $event_type;
        $log->request_data = json_encode([
            'event_type' => $event_type,
            'status' => $status,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        $log->response_data = '';
        $log->timecreated = time();
        
        $DB->insert_record(\local_alx_report_api\constants::TABLE_LOGS, $log);
    }

    /**
     * Write debug messages to a separate log file in moodledata.
     *
     * @param string $message Debug message to log
     */
    private static function debug_log($message) {
        global $CFG;
        
        $logfile = $CFG->dataroot . '/alx_report_api_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $logentry = "[$timestamp] $message\n";
        
        file_put_contents($logfile, $logentry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_course_progress_parameters() {
        return new external_function_parameters([
            'limit' => new external_value(PARAM_INT, 'Number of records to return (max 1000)', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get course progress data for the authenticated user's company.
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    public static function get_course_progress($limit = 100, $offset = 0) {
        global $DB, $USER;
        
        // Start response time measurement
        $start_time = microtime(true);
        $endpoint = 'get_course_progress';
        $error_message = null;
        $record_count = 0;
        $is_rate_limit_error = false;  // Track if error is rate limit

        try {
            // 1. Validate parameters
            $params = self::validate_parameters(self::get_course_progress_parameters(), [
                'limit' => $limit,
                'offset' => $offset
            ]);

            // 2. Validate limit is at least 1 (prevent zero or negative limits)
            if ($params['limit'] < 1) {
                throw new moodle_exception('invalidlimit', 'local_alx_report_api', '', null, 
                    "Limit must be at least 1. Received: {$params['limit']}");
            }

            // 3. Validate limit against configured maximum
            $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
            if ($params['limit'] > $max_records) {
                throw new moodle_exception('limittoolarge', 'local_alx_report_api', '', $max_records, 
                    "Requested limit ({$params['limit']}) exceeds maximum allowed ({$max_records}) records per request.");
            }

            // 4. Validate offset is non-negative (prevent negative offsets)
            if ($params['offset'] < 0) {
                throw new moodle_exception('invalidoffset', 'local_alx_report_api', '', null, 
                    "Offset must be non-negative. Received: {$params['offset']}");
            }

            // 5. Get current authenticated user
            if (!$USER || !$USER->id || $USER->id <= 0) {
                throw new moodle_exception('invaliduser', 'local_alx_report_api', '', null, 
                    'User must be authenticated to access this service');
            }

            // 6. Check rate limiting (global daily limit)
            self::check_rate_limit($USER->id);

            // 7. Check GET method restriction (if enabled in settings)
            $allow_get_method = get_config('local_alx_report_api', 'allow_get_method');
            if (!$allow_get_method && $_SERVER['REQUEST_METHOD'] === 'GET') {
                throw new moodle_exception('invalidrequestmethod', 'local_alx_report_api', '', null, 
                    'GET method is disabled. Only POST method is allowed for security reasons. Enable GET method in plugin settings for development/testing.');
            }

            // 8. Check rate limiting again (duplicate line removed in original, keeping consistent)
            self::check_rate_limit($USER->id);

            // 9. Get company association for the authenticated user
            $companyid = self::get_user_company($USER->id);
            if (!$companyid) {
                throw new moodle_exception('nocompanyassociation', 'local_alx_report_api', '', null, 
                    'User is not associated with any company');
            }

            // 10. Get company shortname for logging
            $company_shortname = 'unknown';
            if ($DB->get_manager()->table_exists('company')) {
                $company = $DB->get_record('company', ['id' => $companyid], 'shortname');
                if ($company) {
                    $company_shortname = $company->shortname;
                }
            }

            // 11. Get course progress data
            $progressdata = self::get_company_course_progress($companyid, $params['limit'], $params['offset']);
            
            // 12. Count returned records
            $record_count = count($progressdata);

            return $progressdata;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            // Check if this is a rate limit error
            if (strpos($error_message, 'rate limit') !== false || 
                (isset($e->errorcode) && $e->errorcode === 'ratelimitexceeded')) {
                $is_rate_limit_error = true;
            }
            
            throw $e;
        } finally {
            // Calculate response time in milliseconds
            $end_time = microtime(true);
            $response_time_ms = round(($end_time - $start_time) * 1000, 2);
            
            // Get company shortname if not set due to early error
            if (!isset($company_shortname)) {
                $company_shortname = 'unknown';
                if (isset($USER) && $USER->id > 0) {
                    $companyid = self::get_user_company($USER->id);
                    if ($companyid && $DB->get_manager()->table_exists('company')) {
                        $company = $DB->get_record('company', ['id' => $companyid], 'shortname');
                        if ($company) {
                            $company_shortname = $company->shortname;
                        }
                    }
                }
            }
            
            // Only log if NOT a rate limit error (FIX: Don't log rate-limited requests)
            if (!$is_rate_limit_error) {
                $userid = isset($USER) && $USER->id > 0 ? $USER->id : 0;
                local_alx_report_api_log_api_call(
                    $userid,
                    $company_shortname, 
                    $endpoint,
                    $record_count,
                    $error_message,
                    $response_time_ms,
                    [
                        'limit' => $limit,
                        'offset' => $offset,
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                    ]
                );
            }
        }
    }

    /**
     * Returns description of method return value.
     *
     * @return external_multiple_structure
     */
    public static function get_course_progress_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                'firstname' => new external_value(PARAM_TEXT, 'User first name', VALUE_OPTIONAL),
                'lastname' => new external_value(PARAM_TEXT, 'User last name', VALUE_OPTIONAL),
                'email' => new external_value(PARAM_EMAIL, 'User email', VALUE_OPTIONAL),
                'username' => new external_value(PARAM_TEXT, 'Username', VALUE_OPTIONAL),
                'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_OPTIONAL),
                'timecompleted' => new external_value(PARAM_TEXT, 'Completion date (Y-m-d H:i:s format)', VALUE_OPTIONAL),
                'timecompleted_unix' => new external_value(PARAM_INT, 'Completion timestamp (Unix format)', VALUE_OPTIONAL),
                'timestarted' => new external_value(PARAM_TEXT, 'Course start date (Y-m-d H:i:s format)', VALUE_OPTIONAL),
                'timestarted_unix' => new external_value(PARAM_INT, 'Course start timestamp (Unix format)', VALUE_OPTIONAL),
                'percentage' => new external_value(PARAM_FLOAT, 'Completion percentage', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_TEXT, 'Completion status (completed, in_progress, not_started)', VALUE_OPTIONAL)
            ])
        );
    }

    /**
     * Get the company ID for a given user.
     *
     * @param int $userid User ID
     * @return int|false Company ID or false if not found
     */
    private static function get_user_company($userid) {
        global $DB;

        try {
            // Validate user ID
            if (empty($userid) || $userid <= 0) {
                error_log('ALX Report API: Invalid user ID provided to get_user_company');
                return false;
            }

            // Check if IOMAD is installed and get company association
            if (!$DB->get_manager()->table_exists('company_users')) {
                error_log('ALX Report API: company_users table does not exist. IOMAD may not be installed.');
                return false;
            }
            
            $company = $DB->get_record('company_users', ['userid' => $userid], 'companyid');
            return $company ? $company->companyid : false;
            
        } catch (Exception $e) {
            error_log('ALX Report API: Error getting user company - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get course progress data for a company using reporting table and incremental sync.
     *
     * @param int $companyid Company ID
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    private static function get_company_course_progress($companyid, $limit, $offset) {
        global $DB;

        try {
            // Validate inputs
            if (empty($companyid) || $companyid <= 0) {
                self::debug_log("ERROR: Invalid company ID: $companyid");
                throw new moodle_exception('invalidcompanyid', 'local_alx_report_api', '', null, 
                    'Invalid company ID provided');
            }
            
            if ($limit < 0 || $offset < 0) {
                self::debug_log("ERROR: Invalid limit ($limit) or offset ($offset)");
                throw new moodle_exception('invalidparameters', 'local_alx_report_api', '', null, 
                    'Limit and offset must be non-negative');
            }

            // Debug logging
            self::debug_log("=== API Request Start (Combined Approach) ===");
            self::debug_log("Company ID: $companyid, Limit: $limit, Offset: $offset");

        // Get the API token for sync tracking
        $token = self::get_authorization_token();
        $token_hash = hash('sha256', $token);
        
        // Determine sync mode (full, incremental, or first)
        $sync_mode = local_alx_report_api_determine_sync_mode($companyid, $token);
        self::debug_log("Sync mode determined: $sync_mode");
        
        // Get enabled courses for this company (MOVED BEFORE CACHE CHECK)
        $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
        self::debug_log("Enabled courses for company $companyid: " . implode(',', $enabled_courses));
        
        // Get company field settings (MOVED BEFORE CACHE CHECK)
        $field_settings = [];
        $field_names = ['userid', 'firstname', 'lastname', 'email', 'username', 'coursename', 
                       'timecompleted', 'timecompleted_unix', 'timestarted', 'timestarted_unix', 
                       'percentage', 'status'];
        
        foreach ($field_names as $field) {
            $field_settings[$field] = local_alx_report_api_get_company_setting($companyid, 'field_' . $field, 1);
        }
        self::debug_log("Field settings for company $companyid: " . json_encode($field_settings));
        
        // Generate cache key that includes courses and fields (FIX FOR CACHE BUG)
        // Sort courses for consistent hash
        $courses_for_hash = $enabled_courses;
        sort($courses_for_hash);
        $courses_hash = empty($courses_for_hash) ? 'nocourses' : md5(implode(',', $courses_for_hash));
        
        // Generate fields hash (only include enabled fields)
        $enabled_fields = array_filter($field_settings, function($v) { return $v == 1; });
        ksort($enabled_fields);
        $fields_hash = md5(implode(',', array_keys($enabled_fields)));
        
        // Build complete cache key with all parameters that affect response
        $cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}_{$courses_hash}_{$fields_hash}";
        self::debug_log("Cache key: $cache_key");
        self::debug_log("Cache key components - Courses: [" . implode(',', $courses_for_hash) . "], Fields: [" . implode(',', array_keys($enabled_fields)) . "]");
        
        // Check if caching is enabled for this company (default: enabled for backward compatibility)
        $cache_enabled = local_alx_report_api_get_company_setting($companyid, 'enable_cache', 1);
        self::debug_log("Cache enabled for company {$companyid}: " . ($cache_enabled ? 'YES' : 'NO'));
        
        // Only check cache if caching is enabled for this company
        if ($cache_enabled) {
            $cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
            if ($cached_data !== false) {
                self::debug_log("Cache hit - returning cached data for sync mode: {$sync_mode}");
                return $cached_data;
            }
            self::debug_log("Cache miss - will fetch fresh data");
        } else {
            self::debug_log("Cache disabled - skipping cache check, will query database directly");
        }
        
        // Handle empty enabled courses (this logic now runs AFTER cache check)
        // Note: enabled_courses and field_settings are already loaded above for cache key
        if (empty($enabled_courses)) {
            $existing_settings = local_alx_report_api_get_company_settings($companyid);
            $has_course_settings = false;
            foreach ($existing_settings as $setting_name => $value) {
                if (strpos($setting_name, 'course_') === 0) {
                    $has_course_settings = true;
                    break;
                }
            }
            
            if ($has_course_settings) {
                self::debug_log("Course settings exist but no courses enabled - returning empty array");
                // Update sync status even for empty results
                local_alx_report_api_update_sync_status($companyid, $token, 0, 'success');
                return [];
            } else {
                // No course settings exist - auto-enable all company courses
                self::debug_log("No course settings found - auto-enabling all company courses");
                $company_courses = local_alx_report_api_get_company_courses($companyid);
                $enabled_courses = array_column($company_courses, 'id');
                
                // Save the auto-enabled course settings
                foreach ($company_courses as $course) {
                    $course_setting = 'course_' . $course->id;
                    local_alx_report_api_set_company_setting($companyid, $course_setting, 1);
                }
                
                self::debug_log("Auto-enabled courses: " . implode(',', $enabled_courses));
                
                // If still no courses available, return empty
                if (empty($enabled_courses)) {
                    self::debug_log("No courses available for company - returning empty array");
                    local_alx_report_api_update_sync_status($companyid, $token, 0, 'success');
                    return [];
                }
            }
        }

        // Build query based on sync mode
        $records = [];
        
        try {
            if ($sync_mode === 'incremental') {
                // Get sync status to determine last sync time
                $sync_status = local_alx_report_api_get_sync_status($companyid, $token);
                $last_sync_time = $sync_status ? $sync_status->last_sync_timestamp : 0;
                
                self::debug_log("Incremental sync - last sync time: " . date('Y-m-d H:i:s', $last_sync_time));
                
                // Query only changed records since last sync
                $sql = "SELECT *
                        FROM {local_alx_api_reporting}
                        WHERE companyid = :companyid
                            AND is_deleted = 0
                            AND last_updated > :last_sync_time";
                
                $params = [
                    'companyid' => $companyid,
                    'last_sync_time' => $last_sync_time
                ];
                
                // Add course filtering if enabled courses specified
                if (!empty($enabled_courses)) {
                    list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
                    $sql .= " AND courseid $course_sql";
                    $params = array_merge($params, $course_params);
                }
                
                $sql .= " ORDER BY last_updated DESC, userid, courseid";
                
                self::debug_log("Incremental SQL: " . $sql);
                $records = $DB->get_records_sql($sql, $params, $offset, $limit);
                
            } else {
                // Full sync or first sync - get all data from reporting table
                self::debug_log("Full/First sync - querying all data from reporting table");
                
                $sql = "SELECT *
                        FROM {local_alx_api_reporting}
                        WHERE companyid = :companyid
                            AND is_deleted = 0";
                
                $params = ['companyid' => $companyid];
                
                // Add course filtering if enabled courses specified
                if (!empty($enabled_courses)) {
                    list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
                    $sql .= " AND courseid $course_sql";
                    $params = array_merge($params, $course_params);
                }
                
                $sql .= " ORDER BY userid, courseid";
                
                self::debug_log("Full sync SQL: " . $sql);
                $records = $DB->get_records_sql($sql, $params, $offset, $limit);
            }
            
            self::debug_log("Found " . count($records) . " records from reporting table");
            
            // If no records found, check if reporting table is populated
            if (empty($records)) {
                self::debug_log("No records found - checking if reporting table is populated");
                
                $total_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, [
                    'companyid' => $companyid,
                    'is_deleted' => 0
                ]);
                
                if ($total_records === 0) {
                    self::debug_log("Reporting table is empty - falling back to complex query");
                    // Fall back to the original complex query if reporting table is empty
                    // This handles both incremental sync with empty table AND first sync with empty table
                    return self::get_company_course_progress_fallback($companyid, $limit, $offset);
                }
            }
            
        } catch (Exception $e) {
            self::debug_log("Error querying reporting table: " . $e->getMessage());
            // Fall back to original complex query on error
            local_alx_report_api_update_sync_status($companyid, $token, 0, 'failed', $e->getMessage());
            return self::get_company_course_progress_fallback($companyid, $limit, $offset);
        }

        // Process records and build response
        $result = [];
        
        foreach ($records as $record) {
            // Convert Unix timestamps to readable format
            $timecompleted = $record->timecompleted > 0 ? date('Y-m-d H:i:s', $record->timecompleted) : '';
            $timestarted = $record->timestarted > 0 ? date('Y-m-d H:i:s', $record->timestarted) : '';
            
            // Build response dynamically based on company-specific field settings
            $response_item = [];
            
            if ($field_settings['userid']) {
                $response_item['userid'] = (int)$record->userid;
            }
            if ($field_settings['firstname']) {
                $response_item['firstname'] = $record->firstname;
            }
            if ($field_settings['lastname']) {
                $response_item['lastname'] = $record->lastname;
            }
            if ($field_settings['email']) {
                $response_item['email'] = $record->email;
            }
            if ($field_settings['username']) {
                $response_item['username'] = $record->username;
            }
            if ($field_settings['coursename']) {
                $response_item['coursename'] = $record->coursename;
            }
            if ($field_settings['timecompleted']) {
                $response_item['timecompleted'] = $timecompleted;
            }
            if ($field_settings['timecompleted_unix']) {
                $response_item['timecompleted_unix'] = (int)$record->timecompleted;
            }
            if ($field_settings['timestarted']) {
                $response_item['timestarted'] = $timestarted;
            }
            if ($field_settings['timestarted_unix']) {
                $response_item['timestarted_unix'] = (int)$record->timestarted;
            }
            if ($field_settings['percentage']) {
                $response_item['percentage'] = (float)$record->percentage;
            }
            if ($field_settings['status']) {
                $response_item['status'] = $record->status;
            }
            
            $result[] = $response_item;
        }

        // Note: Sync status is now only updated by cron tasks, not API calls
        // This ensures control center shows actual sync times, not API access times
        
        // Only cache results if caching is enabled for this company
        // Note: $cache_enabled was already retrieved earlier in this function
        if ($cache_enabled) {
            // Get TTL from company settings or use default (60 minutes)
            $cache_ttl_minutes = local_alx_report_api_get_company_setting($companyid, 'cache_ttl_minutes', 60);
            $cache_ttl = $cache_ttl_minutes * 60; // Convert to seconds
            
            // Cache all results (including empty) for all sync modes
            local_alx_report_api_cache_set($cache_key, $companyid, $result, $cache_ttl);
            self::debug_log("Cached result for sync mode: {$sync_mode}, TTL: {$cache_ttl} seconds");
        } else {
            self::debug_log("Cache disabled - skipping cache storage");
        }
        
        // Handle empty results - must return empty array for Moodle external API compliance
        if (empty($result)) {
            self::debug_log("Returning empty array for no data. Sync mode: {$sync_mode}");
            // We must return an empty array to comply with the API contract.
            // The client (Power BI) will interpret this as "no changes" and not clear its data.
            // Detailed status is available in debug logs and the control center.
            local_alx_report_api_update_sync_status($companyid, $token, 0, 'success');
            return [];
        }

            self::debug_log("Final result count: " . count($result));
            self::debug_log("=== API Request End (Combined Approach) ===");

            return $result;
            
        } catch (moodle_exception $e) {
            // Re-throw Moodle exceptions (these are expected errors with user-friendly messages)
            self::debug_log("ERROR: Moodle exception - " . $e->getMessage());
            throw $e;
            
        } catch (dml_exception $e) {
            // Database errors
            self::debug_log("ERROR: Database exception - " . $e->getMessage());
            error_log('ALX Report API: Database error in get_company_course_progress - ' . $e->getMessage());
            throw new moodle_exception('databaseerror', 'local_alx_report_api', '', null, 
                'A database error occurred. Please contact your administrator.');
                
        } catch (Exception $e) {
            // Catch any other unexpected errors
            self::debug_log("ERROR: Unexpected exception - " . $e->getMessage());
            error_log('ALX Report API: Unexpected error in get_company_course_progress - ' . $e->getMessage());
            throw new moodle_exception('unexpectederror', 'local_alx_report_api', '', null, 
                'An unexpected error occurred. Please contact your administrator.');
        }
    }

    /**
     * Get course progress data for a company using the original complex query (fallback).
     *
     * @param int $companyid Company ID
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    private static function get_company_course_progress_fallback($companyid, $limit, $offset) {
        global $DB;

        self::debug_log("=== FALLBACK: Using original complex query ===");

        // Get enabled courses for this company
        $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
        
        // Get company field settings
        $field_settings = [];
        $field_names = ['userid', 'firstname', 'lastname', 'email', 'username', 'coursename', 
                       'timecompleted', 'timecompleted_unix', 'timestarted', 'timestarted_unix', 
                       'percentage', 'status'];
        
        foreach ($field_names as $field) {
            $field_settings[$field] = local_alx_report_api_get_company_setting($companyid, 'field_' . $field, 1);
        }

        // Check if this is a first-time sync with time window restriction
        $token = self::get_authorization_token();
        $sync_mode = local_alx_report_api_determine_sync_mode($companyid, $token);
        $first_sync_hours = local_alx_report_api_get_company_setting($companyid, 'first_sync_hours', 0);
        
        self::debug_log("Fallback sync mode: $sync_mode, first_sync_hours: $first_sync_hours");

        // Use the original complex query logic
        $sql = "
            SELECT DISTINCT
                u.id as userid,
                u.firstname,
                u.lastname,
                u.email,
                u.username,
                c.fullname as coursename,
                COALESCE(cc.timecompleted, 
                    (SELECT MAX(cmc.timemodified) 
                     FROM {course_modules_completion} cmc
                     JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1), 0) as timecompleted,
                COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
                COALESCE(
                    CASE 
                        WHEN cc.timecompleted > 0 THEN 100.0
                        ELSE COALESCE(
                            (SELECT AVG(CASE WHEN cmc.completionstate = 1 THEN 100.0 ELSE 0.0 END)
                             FROM {course_modules_completion} cmc
                             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                             WHERE cm.course = c.id AND cmc.userid = u.id), 0.0)
                    END, 0.0) as percentage,
                CASE 
                    WHEN cc.timecompleted > 0 THEN 'completed'
                    WHEN EXISTS(
                        SELECT 1 FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
                    ) THEN 'in_progress'
                    WHEN ue.id IS NOT NULL THEN 'not_started'
                    ELSE 'not_enrolled'
                END as status
            FROM {user} u
            JOIN {company_users} cu ON cu.userid = u.id
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
            WHERE cu.companyid = :companyid
                AND u.deleted = 0
                AND u.suspended = 0
                AND c.visible = 1
                AND ue.status = 0";

        $params = ['companyid' => $companyid];
        
        // Add time window filter for first sync if configured
        if ($sync_mode === 'first' && $first_sync_hours > 0) {
            $cutoff_time = time() - ($first_sync_hours * 3600);
            $sql .= " AND (cc.timecompleted > :cutoff_time OR cc.timecompleted = 0 OR cc.timecompleted IS NULL)";
            $params['cutoff_time'] = $cutoff_time;
            self::debug_log("Added time filter for first sync: cutoff_time = " . date('Y-m-d H:i:s', $cutoff_time));
        }
        
        // Add course filtering if enabled courses specified
        if (!empty($enabled_courses)) {
            list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
            $sql .= " AND c.id $course_sql";
            $params = array_merge($params, $course_params);
        }
        
        $sql .= " ORDER BY u.lastname, u.firstname, c.fullname";
        
        $records = $DB->get_records_sql($sql, $params, $offset, $limit);
        
        // Process records same as original logic
        $result = [];
        
        foreach ($records as $record) {
            $timecompleted = $record->timecompleted > 0 ? date('Y-m-d H:i:s', $record->timecompleted) : '';
            $timestarted = $record->timestarted > 0 ? date('Y-m-d H:i:s', $record->timestarted) : '';
            
            $response_item = [];
            
            if ($field_settings['userid']) {
                $response_item['userid'] = (int)$record->userid;
            }
            if ($field_settings['firstname']) {
                $response_item['firstname'] = $record->firstname;
            }
            if ($field_settings['lastname']) {
                $response_item['lastname'] = $record->lastname;
            }
            if ($field_settings['email']) {
                $response_item['email'] = $record->email;
            }
            if ($field_settings['username']) {
                $response_item['username'] = $record->username;
            }
            if ($field_settings['coursename']) {
                $response_item['coursename'] = $record->coursename;
            }
            if ($field_settings['timecompleted']) {
                $response_item['timecompleted'] = $timecompleted;
            }
            if ($field_settings['timecompleted_unix']) {
                $response_item['timecompleted_unix'] = (int)$record->timecompleted;
            }
            if ($field_settings['timestarted']) {
                $response_item['timestarted'] = $timestarted;
            }
            if ($field_settings['timestarted_unix']) {
                $response_item['timestarted_unix'] = (int)$record->timestarted;
            }
            if ($field_settings['percentage']) {
                $response_item['percentage'] = (float)$record->percentage;
            }
            if ($field_settings['status']) {
                $response_item['status'] = $record->status;
            }
            
            $result[] = $response_item;
        }

        // Handle empty results - must return empty array for Moodle external API compliance
        if (empty($result)) {
            self::debug_log("Fallback returning empty array for no data. Sync mode: {$sync_mode}");
            // Update sync status even for empty results
            local_alx_report_api_update_sync_status($companyid, $token, 0, 'success');
            return [];
        }

        self::debug_log("Fallback result count: " . count($result) . " (sync_mode: $sync_mode, time_filter: " . ($first_sync_hours > 0 && $sync_mode === 'first' ? 'YES' : 'NO') . ")");
        return $result;
    }

    /**
     * Generate detailed status message for empty API results.
     *
     * @param string $sync_mode Current sync mode
     * @param int $companyid Company ID
     * @param string $token API token
     * @param array $enabled_courses Enabled courses for company
     * @return array Status response with detailed message
     */
    private static function generate_empty_result_status($sync_mode, $companyid, $token, $enabled_courses) {
        global $DB;

        $status_response = [
            'data' => [],
            'status' => 'no_data',
            'sync_mode' => $sync_mode,
            'timestamp' => date('Y-m-d H:i:s'),
            'company_id' => $companyid
        ];

        // Check different scenarios for empty results
        if ($sync_mode === 'incremental') {
            $sync_status = local_alx_report_api_get_sync_status($companyid, $token);
            $last_sync_time = $sync_status ? date('Y-m-d H:i:s', $sync_status->last_sync_timestamp) : 'Never';
            
            $status_response['message'] = get_string('api_no_data_incremental', 'local_alx_report_api', 
                (object)['last_sync_time' => $last_sync_time]);
            $status_response['last_sync'] = $last_sync_time;
            $status_response['explanation'] = get_string('api_debug_no_changes', 'local_alx_report_api');
            
        } else if (empty($enabled_courses)) {
            $status_response['message'] = get_string('api_no_data_courses_filtered', 'local_alx_report_api');
            $status_response['action_required'] = 'Enable courses in Company Settings';
            
        } else {
            // Check if reporting table has any data for this company
            $total_records = $DB->count_records(\local_alx_report_api\constants::TABLE_REPORTING, [
                'companyid' => $companyid,
                'is_deleted' => 0
            ]);
            
            if ($total_records === 0) {
                $status_response['message'] = get_string('api_no_data_reporting_empty', 'local_alx_report_api');
                $status_response['action_required'] = 'Run historical data population';
                $status_response['help_url'] = '/local/alx_report_api/populate_reporting_table.php';
            } else {
                $status_response['message'] = get_string('api_no_data_full_sync', 'local_alx_report_api');
                $status_response['total_records_in_table'] = $total_records;
                $status_response['action_required'] = 'Check course assignments and user enrollments';
            }
        }

        // Add helpful debug information
        $status_response['debug_info'] = [
            'enabled_courses_count' => count($enabled_courses),
            'sync_mode' => $sync_mode,
            'company_id' => $companyid
        ];

        return $status_response;
    }
} 
 