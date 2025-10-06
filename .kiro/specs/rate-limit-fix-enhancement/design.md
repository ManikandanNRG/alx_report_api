# Design Document: Rate Limit Fix & Per-Company Enhancement

## Overview

This design document outlines the technical approach for fixing the critical rate limit bypass vulnerability and implementing per-company rate limit configuration in the ALX Report API plugin. The solution maintains backward compatibility while adding flexible, company-specific rate limiting capabilities.

### Design Goals

1. **Fix Security Vulnerability:** Eliminate the rate limit bypass caused by logging in the `finally` block
2. **Add Flexibility:** Enable per-company rate limit configuration
3. **Maintain Compatibility:** Ensure no breaking changes to existing functionality
4. **Preserve Performance:** Minimize additional database queries and overhead
5. **Enhance Monitoring:** Provide visibility into rate limit usage and violations

---

## Architecture

### Current Architecture (Broken)

```
┌─────────────────────────────────────────────────────────────┐
│              Current Rate Limit Flow (BROKEN)               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  API Request → check_rate_limit()                           │
│         ↓                                                   │
│  Count requests from local_alx_api_logs                     │
│         ↓                                                   │
│  IF count >= limit → throw exception                        │
│         ↓                                                   │
│  catch block → re-throw exception                           │
│         ↓                                                   │
│  finally block → ALWAYS RUNS                                │
│         ↓                                                   │
│  local_alx_report_api_log_api_call() ← BUG: LOGS REQUEST!  │
│         ↓                                                   │
│  Request logged → count increments → bypass continues       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### New Architecture (Fixed)

```
┌─────────────────────────────────────────────────────────────┐
│              New Rate Limit Flow (FIXED)                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  API Request → get_course_progress()                        │
│         ↓                                                   │
│  Get company ID from authenticated user                     │
│         ↓                                                   │
│  check_rate_limit_with_company($userid, $companyid)         │
│         ↓                                                   │
│  Get company rate limit (custom or global default)          │
│         ↓                                                   │
│  Count requests from local_alx_api_logs                     │
│         ↓                                                   │
│  IF count >= limit:                                         │
│    ├─→ Log violation to local_alx_api_alerts               │
│    ├─→ throw exception                                      │
│    └─→ EXIT (finally block checks for rate limit error)    │
│         ↓                                                   │
│  Process request normally                                   │
│         ↓                                                   │
│  catch block → set $error_message                           │
│         ↓                                                   │
│  finally block:                                             │
│    ├─→ Check if error is rate limit violation              │
│    ├─→ IF rate limit: DON'T log to api_logs                │
│    └─→ IF other error or success: log normally             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Components and Interfaces

### Component 1: Enhanced Rate Limit Check Function

**Location:** `local/local_alx_report_api/externallib.php`

**New Function Signature:**
```php
/**
 * Check if the user has exceeded the daily rate limit for their company.
 *
 * @param int $userid User ID to check
 * @param int $companyid Company ID for per-company rate limiting
 * @throws moodle_exception If rate limit is exceeded
 */
private static function check_rate_limit_with_company($userid, $companyid)
```

**Function Logic:**
```php
private static function check_rate_limit_with_company($userid, $companyid) {
    global $DB;
    
    // 1. Get company-specific rate limit or global default
    $company_rate_limit = local_alx_report_api_get_company_setting(
        $companyid, 
        'rate_limit', 
        null  // null means not set
    );
    
    // 2. Fall back to global default if company setting not found
    if ($company_rate_limit === null || $company_rate_limit === 0) {
        $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
    } else {
        $rate_limit = (int)$company_rate_limit;
    }
    
    // 3. Calculate start of today (midnight)
    $today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
    
    // 4. Count requests from this user today
    $request_count = 0;
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        $table_info = $DB->get_columns('local_alx_api_logs');
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        
        $request_count = $DB->count_records_select(
            'local_alx_api_logs', 
            "userid = ? AND {$time_field} >= ?", 
            [$userid, $today_start]
        );
    }
    
    // 5. Check if limit exceeded
    if ($request_count >= $rate_limit) {
        // Log the violation to alerts table BEFORE throwing exception
        self::log_rate_limit_violation($userid, $companyid, $request_count, $rate_limit);
        
        // Throw exception with detailed message
        throw new moodle_exception(
            'ratelimitexceeded', 
            'local_alx_report_api', 
            '', 
            null, 
            "Daily rate limit exceeded. You have made {$request_count} requests today. " .
            "Your limit is {$rate_limit} requests per day. Try again tomorrow."
        );
    }
    
    // 6. Optional: Log warning if approaching limit (80% threshold)
    $warning_threshold = $rate_limit * 0.8;
    if ($request_count >= $warning_threshold && $request_count < $rate_limit) {
        self::log_rate_limit_warning($userid, $companyid, $request_count, $rate_limit);
    }
}
```

---

### Component 2: Rate Limit Violation Logging

**Location:** `local/local_alx_report_api/externallib.php`

**New Function:**
```php
/**
 * Log rate limit violation to alerts table.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $current_count Current request count
 * @param int $rate_limit Configured rate limit
 */
private static function log_rate_limit_violation($userid, $companyid, $current_count, $rate_limit) {
    global $DB;
    
    if (!$DB->get_manager()->table_exists('local_alx_api_alerts')) {
        return;  // Graceful degradation if alerts table doesn't exist
    }
    
    // Get company shortname for better logging
    $company_shortname = 'unknown';
    if ($DB->get_manager()->table_exists('company')) {
        $company = $DB->get_record('company', ['id' => $companyid], 'shortname');
        if ($company) {
            $company_shortname = $company->shortname;
        }
    }
    
    // Create alert record
    $alert = new stdClass();
    $alert->alert_type = 'rate_limit_exceeded';
    $alert->severity = 'high';
    $alert->message = "User {$userid} from company {$company_shortname} exceeded rate limit: " .
                     "{$current_count}/{$rate_limit} requests";
    $alert->alert_data = json_encode([
        'userid' => $userid,
        'companyid' => $companyid,
        'company_shortname' => $company_shortname,
        'current_count' => $current_count,
        'rate_limit' => $rate_limit,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    $alert->hostname = gethostname() ?: 'unknown';
    $alert->timecreated = time();
    $alert->resolved = 0;
    
    try {
        $DB->insert_record('local_alx_api_alerts', $alert);
    } catch (Exception $e) {
        // Graceful degradation - don't fail the rate limit check if alert logging fails
        error_log("Failed to log rate limit violation: " . $e->getMessage());
    }
}

/**
 * Log rate limit warning when approaching limit.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $current_count Current request count
 * @param int $rate_limit Configured rate limit
 */
private static function log_rate_limit_warning($userid, $companyid, $current_count, $rate_limit) {
    global $DB;
    
    if (!$DB->get_manager()->table_exists('local_alx_api_alerts')) {
        return;
    }
    
    // Only log warning once per day per user (check if warning already exists today)
    $today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
    $existing_warning = $DB->get_record_select(
        'local_alx_api_alerts',
        "alert_type = 'rate_limit_warning' AND alert_data LIKE ? AND timecreated >= ?",
        ['%"userid":' . $userid . '%', $today_start]
    );
    
    if ($existing_warning) {
        return;  // Warning already logged today
    }
    
    // Get company shortname
    $company_shortname = 'unknown';
    if ($DB->get_manager()->table_exists('company')) {
        $company = $DB->get_record('company', ['id' => $companyid], 'shortname');
        if ($company) {
            $company_shortname = $company->shortname;
        }
    }
    
    $percentage = round(($current_count / $rate_limit) * 100);
    
    $alert = new stdClass();
    $alert->alert_type = 'rate_limit_warning';
    $alert->severity = 'medium';
    $alert->message = "User {$userid} from company {$company_shortname} approaching rate limit: " .
                     "{$current_count}/{$rate_limit} requests ({$percentage}%)";
    $alert->alert_data = json_encode([
        'userid' => $userid,
        'companyid' => $companyid,
        'company_shortname' => $company_shortname,
        'current_count' => $current_count,
        'rate_limit' => $rate_limit,
        'percentage' => $percentage
    ]);
    $alert->hostname = gethostname() ?: 'unknown';
    $alert->timecreated = time();
    $alert->resolved = 0;
    
    try {
        $DB->insert_record('local_alx_api_alerts', $alert);
    } catch (Exception $e) {
        error_log("Failed to log rate limit warning: " . $e->getMessage());
    }
}
```

---

### Component 3: Modified API Request Handler

**Location:** `local/local_alx_report_api/externallib.php`

**Modified Function:**
```php
public static function get_course_progress($limit = 100, $offset = 0) {
    global $DB, $USER;
    
    $start_time = microtime(true);
    $endpoint = 'get_course_progress';
    $error_message = null;
    $record_count = 0;
    $is_rate_limit_error = false;  // NEW: Track if error is rate limit

    try {
        // 1. Validate parameters
        $params = self::validate_parameters(self::get_course_progress_parameters(), [
            'limit' => $limit,
            'offset' => $offset
        ]);

        // 2. Validate limit against configured maximum
        $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
        if ($params['limit'] > $max_records) {
            throw new moodle_exception('limittoolarge', 'local_alx_report_api', '', $max_records, 
                "Requested limit ({$params['limit']}) exceeds maximum allowed ({$max_records}) records per request.");
        }

        // 3. Get current authenticated user
        if (!$USER || !$USER->id || $USER->id <= 0) {
            throw new moodle_exception('invaliduser', 'local_alx_report_api', '', null, 
                'User must be authenticated to access this service');
        }

        // 4. Get company association BEFORE rate limit check
        $companyid = self::get_user_company($USER->id);
        if (!$companyid) {
            throw new moodle_exception('nocompanyassociation', 'local_alx_report_api', '', null, 
                'User is not associated with any company');
        }

        // 5. Check rate limiting with company-specific limit
        self::check_rate_limit_with_company($USER->id, $companyid);

        // 6. Check GET method restriction (if enabled in settings)
        $allow_get_method = get_config('local_alx_report_api', 'allow_get_method');
        if (!$allow_get_method && $_SERVER['REQUEST_METHOD'] === 'GET') {
            throw new moodle_exception('invalidrequestmethod', 'local_alx_report_api', '', null, 
                'GET method is disabled. Only POST method is allowed for security reasons.');
        }

        // 7. Get company shortname for logging
        $company_shortname = 'unknown';
        if ($DB->get_manager()->table_exists('company')) {
            $company = $DB->get_record('company', ['id' => $companyid], 'shortname');
            if ($company) {
                $company_shortname = $company->shortname;
            }
        }

        // 8. Get course progress data
        $progressdata = self::get_company_course_progress($companyid, $params['limit'], $params['offset']);
        
        // 9. Count returned records
        $record_count = count($progressdata);

        return $progressdata;

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        
        // NEW: Check if this is a rate limit error
        if (strpos($error_message, 'rate limit') !== false || 
            $e->errorcode === 'ratelimitexceeded') {
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
        
        // NEW: Only log if NOT a rate limit error
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
        // If it IS a rate limit error, it's already logged to alerts table
    }
}
```

---

### Component 4: Company Settings UI Enhancement

**Location:** `local/local_alx_report_api/company_settings.php`

**UI Addition:**
```php
// Add rate limit setting to company settings form

// Get current rate limit setting
$current_rate_limit = local_alx_report_api_get_company_setting($companyid, 'rate_limit', null);

// Get global default for display
$global_default = get_config('local_alx_report_api', 'rate_limit') ?: 100;

// Display rate limit field
echo '<div class="form-group">';
echo '<label for="rate_limit">Rate Limit (requests/day)</label>';
echo '<input type="number" 
             id="rate_limit" 
             name="rate_limit" 
             class="form-control" 
             value="' . ($current_rate_limit !== null ? $current_rate_limit : '') . '" 
             min="1" 
             max="10000" 
             placeholder="Using global default: ' . $global_default . '">';
echo '<small class="form-text text-muted">';
echo 'Set a custom daily rate limit for this company. Leave empty to use the global default (' . $global_default . ' requests/day). ';
echo 'This controls how many API requests users from this company can make per day.';
echo '</small>';

// Show current usage if available
$today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    // Get company users
    $company_users = $DB->get_records('company_users', ['companyid' => $companyid], '', 'userid');
    if (!empty($company_users)) {
        $userids = array_keys($company_users);
        list($user_sql, $user_params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        
        $sql = "SELECT COUNT(*) as request_count
                FROM {local_alx_api_logs}
                WHERE userid {$user_sql} AND {$time_field} >= :today_start";
        $params = array_merge($user_params, ['today_start' => $today_start]);
        
        $usage = $DB->get_record_sql($sql, $params);
        if ($usage) {
            $effective_limit = $current_rate_limit !== null ? $current_rate_limit : $global_default;
            $percentage = $effective_limit > 0 ? round(($usage->request_count / $effective_limit) * 100) : 0;
            
            echo '<div class="alert alert-info mt-2">';
            echo '<strong>Today\'s Usage:</strong> ' . $usage->request_count . ' / ' . $effective_limit . ' requests (' . $percentage . '%)';
            echo '</div>';
        }
    }
}

echo '</div>';
```

**Form Processing:**
```php
// Process rate limit setting when form is submitted
if ($data = data_submitted() && confirm_sesskey()) {
    $rate_limit_value = optional_param('rate_limit', null, PARAM_INT);
    
    if ($rate_limit_value !== null && $rate_limit_value !== '') {
        // Validate rate limit
        if ($rate_limit_value < 1 || $rate_limit_value > 10000) {
            echo $OUTPUT->notification('Rate limit must be between 1 and 10000', 'error');
        } else {
            // Save custom rate limit
            local_alx_report_api_set_company_setting($companyid, 'rate_limit', $rate_limit_value);
            echo $OUTPUT->notification('Rate limit updated successfully', 'success');
        }
    } else {
        // Empty value - remove custom setting to use global default
        $DB->delete_records('local_alx_api_settings', [
            'companyid' => $companyid,
            'setting_name' => 'rate_limit'
        ]);
        echo $OUTPUT->notification('Rate limit cleared - using global default', 'success');
    }
}
```

---

## Data Models

### Existing Tables (No Changes Required)

#### local_alx_api_settings
```sql
-- Used to store per-company rate limit
-- Setting name: 'rate_limit'
-- Setting value: integer (requests per day)

INSERT INTO mdl_local_alx_api_settings 
(companyid, setting_name, setting_value, timecreated, timemodified)
VALUES 
(1, 'rate_limit', 500, 1696550400, 1696550400);
```

#### local_alx_api_alerts
```sql
-- Used to log rate limit violations and warnings
-- Alert types: 'rate_limit_exceeded', 'rate_limit_warning'

INSERT INTO mdl_local_alx_api_alerts
(alert_type, severity, message, alert_data, hostname, timecreated, resolved)
VALUES
('rate_limit_exceeded', 'high', 'User 123 exceeded rate limit', '{"userid":123,...}', 'server1', 1696550400, 0);
```

#### local_alx_api_logs
```sql
-- Existing table - NO CHANGES
-- Rate-limited requests will NOT be logged here (that's the fix!)
```

---

## Error Handling

### Error Scenarios

#### Scenario 1: Rate Limit Exceeded
```
Request → check_rate_limit_with_company() → count >= limit
    ↓
Log to alerts table
    ↓
Throw moodle_exception('ratelimitexceeded')
    ↓
catch block sets $is_rate_limit_error = true
    ↓
finally block checks $is_rate_limit_error
    ↓
Skip logging to api_logs table
    ↓
Return error to client
```

#### Scenario 2: Missing Company Setting
```
Request → check_rate_limit_with_company()
    ↓
Get company setting → returns null
    ↓
Fall back to global default
    ↓
Continue with rate limit check using global default
```

#### Scenario 3: Alerts Table Missing
```
Rate limit exceeded → log_rate_limit_violation()
    ↓
Check if alerts table exists → false
    ↓
Gracefully return (don't fail rate limit check)
    ↓
Continue with exception throw
```

#### Scenario 4: Invalid Rate Limit Value
```
Admin sets rate limit to -5 or 99999
    ↓
Form validation catches invalid value
    ↓
Display error message
    ↓
Don't save invalid value
    ↓
Keep existing setting or global default
```

---

## Testing Strategy

### Unit Tests

#### Test 1: Rate Limit Check with Company Setting
```php
// Test that company-specific rate limit is used
$companyid = 1;
local_alx_report_api_set_company_setting($companyid, 'rate_limit', 50);

// Make 50 requests - should succeed
// Make 51st request - should fail
// Verify only 50 requests logged
```

#### Test 2: Rate Limit Check with Global Default
```php
// Test that global default is used when no company setting
$companyid = 2;
// Don't set company rate limit

// Make requests up to global default - should succeed
// Make request beyond global default - should fail
```

#### Test 3: Rate Limit Bypass Fix
```php
// Test that rate-limited requests are NOT logged
$companyid = 1;
local_alx_report_api_set_company_setting($companyid, 'rate_limit', 5);

// Make 5 requests - verify 5 logged
// Make 6th request - should fail
// Verify still only 5 logged (not 6)
// Make 7th request - should fail
// Verify still only 5 logged (not 7)
```

#### Test 4: Daily Reset
```php
// Test that rate limit resets at midnight
// Make requests up to limit
// Simulate time change to next day
// Verify can make requests again
```

### Integration Tests

#### Test 5: End-to-End API Request
```php
// Test complete API flow with rate limiting
// Authenticate user
// Make API request within limit - should succeed
// Make API request beyond limit - should fail with proper error
// Verify alerts table has violation logged
// Verify api_logs table doesn't have rate-limited request
```

#### Test 6: Company Settings UI
```php
// Test UI for setting rate limits
// Navigate to company settings page
// Set custom rate limit
// Verify setting is saved
// Clear rate limit
// Verify setting is removed and global default is used
```

---

## Performance Considerations

### Database Queries

**Before (per API request):**
1. Count requests from api_logs table (rate limit check)
2. Insert into api_logs table (logging)

**After (per API request):**
1. Get company setting from local_alx_api_settings (cached)
2. Count requests from api_logs table (rate limit check)
3. IF rate limit exceeded: Insert into alerts table (only on violation)
4. IF NOT rate limit exceeded: Insert into api_logs table (normal logging)

**Net Impact:** +1 query for company setting lookup (but cached), no additional queries for normal requests

### Caching Strategy

```php
// Company settings are already cached by existing functions
// local_alx_report_api_get_company_setting() uses Moodle's cache API
// No additional caching needed
```

---

## Security Considerations

### Access Control
- Rate limit settings only modifiable by users with `moodle/site:config` capability
- Company settings page already has proper capability checks
- No new security vulnerabilities introduced

### Data Validation
- Rate limit values validated: 1-10000 range
- Integer type enforcement
- SQL injection prevented by parameterized queries (existing pattern)

### Audit Trail
- Rate limit violations logged to alerts table
- Includes user ID, company ID, IP address, user agent
- Timestamp for forensic analysis

---

## Deployment Plan

### Phase 1: Code Changes
1. Add `check_rate_limit_with_company()` function to externallib.php
2. Add `log_rate_limit_violation()` and `log_rate_limit_warning()` functions
3. Modify `get_course_progress()` to use new rate limit check and conditional logging
4. Update company_settings.php to include rate limit field

### Phase 2: Testing
1. Run unit tests for rate limit logic
2. Test company settings UI
3. Test rate limit bypass fix
4. Test backward compatibility

### Phase 3: Deployment
1. Deploy code changes
2. No database migrations needed (using existing tables)
3. Monitor alerts table for rate limit violations
4. Verify no 500 errors or breaking changes

### Rollback Plan
If issues occur:
1. Revert code changes
2. Global rate limit will continue to work (backward compatible)
3. No data loss (no schema changes)

---

## Monitoring & Maintenance

### Metrics to Monitor
- Rate limit violations per company (from alerts table)
- Rate limit warnings (approaching limit)
- API request counts per company
- Error rates after deployment

### Maintenance Tasks
- Review rate limit violations weekly
- Adjust company rate limits based on usage patterns
- Clean up old alerts (resolved violations)
- Monitor performance impact

---

## Future Enhancements (Out of Scope)

- Per-user rate limits (in addition to per-company)
- Multiple rate limit tiers (hourly, daily, monthly)
- Rate limit APIs for external management
- Automatic rate limit adjustment based on usage patterns
- Rate limit notifications to company administrators

---

## Conclusion

This design provides a comprehensive solution to fix the critical rate limit bypass vulnerability while adding flexible per-company rate limiting. The approach maintains backward compatibility, minimizes performance impact, and provides enhanced monitoring capabilities.

**Key Benefits:**
- ✅ Fixes critical security vulnerability
- ✅ Adds per-company rate limit flexibility
- ✅ Maintains backward compatibility
- ✅ No database schema changes
- ✅ Minimal performance impact
- ✅ Enhanced monitoring and visibility

---

**Document Version:** 1.0  
**Created:** October 6, 2025  
**Status:** Ready for Review
