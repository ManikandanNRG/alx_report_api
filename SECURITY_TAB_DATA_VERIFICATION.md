# Security Tab Data Verification Report

## Summary
Verified whether the Security & Alerts tab is using live data or placeholder values.

---

## 🔍 Data Source Analysis

### Metric Cards

#### 1. Active Tokens
```php
$active_tokens = $DB->count_records('external_tokens');
```
**Status**: ✅ **LIVE DATA**
- Source: `external_tokens` table
- Query: Counts all records in external_tokens table
- **Issue**: ⚠️ Should filter by active tokens only (validuntil > time())

**Recommended Fix**:
```php
$active_tokens = $DB->count_records_select('external_tokens', 
    'validuntil IS NULL OR validuntil > ?', [time()]);
```

#### 2. Rate Limit Violations
```php
$rate_limit_violations = $DB->count_records_select('local_alx_api_alerts', 
    "alert_type = 'rate_limit_exceeded' AND timecreated >= ?", [$today_start]);
```
**Status**: ✅ **LIVE DATA**
- Source: `local_alx_api_alerts` table
- Query: Counts alerts with type 'rate_limit_exceeded' from today
- **Issue**: ⚠️ Only counts if alerts are being logged to database

**Potential Problem**: If you see a rate limit violation in the API Monitor tab but not here, it means:
1. The violation is not being logged to `local_alx_api_alerts` table
2. The alert_type might be different (e.g., 'rate_limit', 'rate_exceeded')
3. The alert logging function is not being called

#### 3. Failed Auth Attempts
```php
$failed_auth = 0;
```
**Status**: ❌ **PLACEHOLDER VALUE**
- Hardcoded to 0
- Not reading from any database table
- **Issue**: 🔴 This is NOT live data!

**Recommended Fix**:
```php
$failed_auth = 0;
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    if (isset($table_info['status'])) {
        $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
        $failed_auth = $DB->count_records_select('local_alx_api_logs',
            "{$time_field} >= ? AND (status = ? OR status = ?)",
            [$today_start, 'auth_failed', 'unauthorized']
        );
    }
}
```

#### 4. Security Status
```php
$rate_limit_violations + $failed_auth === 0 ? 'Secure' : 'Alert'
```
**Status**: ⚠️ **PARTIALLY LIVE**
- Depends on rate_limit_violations (live) and failed_auth (placeholder)
- Logic is correct, but data source is incomplete

---

## 📊 Tables Data

### Recent Security Events Table
```php
$alerts = $DB->get_records('local_alx_api_alerts', null, 'timecreated DESC', '*', 0, 10);
```
**Status**: ✅ **LIVE DATA**
- Source: `local_alx_api_alerts` table
- Query: Gets last 10 alerts ordered by time
- Displays: Time, Event Type, User/IP, Details, Status

### Active System Alerts Table
```php
$active_alerts = $DB->get_records('local_alx_api_alerts', ['resolved' => 0], 'timecreated DESC', '*', 0, 10);
```
**Status**: ✅ **LIVE DATA**
- Source: `local_alx_api_alerts` table
- Query: Gets unresolved alerts
- Displays: Alert Type, Severity, Message, Time, Status

---

## 🔴 Critical Issues Found

### Issue 1: Failed Auth Attempts is Hardcoded to 0
**Problem**: The `$failed_auth` variable is set to 0 and never updated with real data.

**Impact**: 
- Users cannot see actual failed authentication attempts
- Security status may show "Secure" even when there are auth failures
- Misleading security information

**Solution**: Query the `local_alx_api_logs` table for failed auth attempts

### Issue 2: Active Tokens Count Includes Expired Tokens
**Problem**: Counts all tokens, including expired ones.

**Impact**:
- Inflated token count
- Doesn't reflect actual active tokens

**Solution**: Filter by `validuntil IS NULL OR validuntil > time()`

### Issue 3: Rate Limit Violations Depends on Alert Logging
**Problem**: Only shows violations if they're logged to `local_alx_api_alerts` table.

**Impact**:
- If alert logging is disabled or not working, violations won't show
- You mentioned seeing a violation in API Monitor but not in Security tab

**Solution**: Query `local_alx_api_logs` table directly for rate limit violations

---

## 🔧 Recommended Fixes

### Fix 1: Update Security Data Calculation

Replace the current security data section (lines 95-102) with:

```php
// Get security data - LIVE DATA
$active_tokens = 0;
$rate_limit_violations = 0;
$failed_auth = 0;
$today_start = mktime(0, 0, 0);

// Count ACTIVE tokens only (not expired)
$active_tokens = $DB->count_records_select('external_tokens', 
    'validuntil IS NULL OR validuntil > ?', [time()]);

// Count rate limit violations from API logs (more reliable)
if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
    $table_info = $DB->get_columns('local_alx_api_logs');
    $time_field = isset($table_info['timeaccessed']) ? 'timeaccessed' : 'timecreated';
    
    // Count rate limit violations
    if (isset($table_info['status'])) {
        $rate_limit_violations = $DB->count_records_select('local_alx_api_logs',
            "{$time_field} >= ? AND (status = ? OR status = ? OR status = ?)",
            [$today_start, 'rate_limited', 'rate_limit_exceeded', 'too_many_requests']
        );
    }
    
    // Count failed authentication attempts
    if (isset($table_info['status'])) {
        $failed_auth = $DB->count_records_select('local_alx_api_logs',
            "{$time_field} >= ? AND (status = ? OR status = ? OR status = ?)",
            [$today_start, 'auth_failed', 'unauthorized', 'forbidden']
        );
    }
}

// Also check alerts table as backup
if ($DB->get_manager()->table_exists('local_alx_api_alerts')) {
    // Add alerts that might not be in logs
    $alert_violations = $DB->count_records_select('local_alx_api_alerts', 
        "alert_type = 'rate_limit_exceeded' AND timecreated >= ?", [$today_start]);
    $rate_limit_violations = max($rate_limit_violations, $alert_violations);
    
    $alert_auth_failures = $DB->count_records_select('local_alx_api_alerts', 
        "alert_type = 'auth_failed' AND timecreated >= ?", [$today_start]);
    $failed_auth = max($failed_auth, $alert_auth_failures);
}
```

### Fix 2: Add Error Handling

```php
try {
    // Security data calculation here
} catch (Exception $e) {
    error_log('Security data calculation error: ' . $e->getMessage());
    // Set safe defaults
    $active_tokens = 0;
    $rate_limit_violations = 0;
    $failed_auth = 0;
}
```

---

## 📋 Verification Checklist

### Current Status:
- ✅ Active Tokens: Live data (but includes expired tokens)
- ✅ Rate Limit Violations: Live data (but only from alerts table)
- ❌ Failed Auth Attempts: Placeholder (hardcoded to 0)
- ⚠️ Security Status: Partially live (depends on above)
- ✅ Recent Security Events: Live data
- ✅ Active System Alerts: Live data

### After Fixes:
- ✅ Active Tokens: Live data (only active tokens)
- ✅ Rate Limit Violations: Live data (from logs + alerts)
- ✅ Failed Auth Attempts: Live data (from logs + alerts)
- ✅ Security Status: Fully live
- ✅ Recent Security Events: Live data
- ✅ Active System Alerts: Live data

---

## 🎯 Why You're Seeing Violations in API Monitor but Not Security Tab

**Reason**: The API Monitor tab likely shows violations based on:
1. Actual API call counts exceeding limits
2. Real-time calculation from `local_alx_api_logs` table
3. Company-specific rate limit checks

**Security Tab Issue**: Only shows violations if they're logged to `local_alx_api_alerts` table, which might not be happening.

**Solution**: Update Security tab to query `local_alx_api_logs` directly (like API Monitor does) instead of relying on `local_alx_api_alerts`.

---

## 🚀 Next Steps

1. **Implement Fix 1**: Update security data calculation to use live data
2. **Test**: Verify that violations show up correctly
3. **Add Fix 2**: Add error handling
4. **Verify**: Check that all metrics show real data
5. **Document**: Update any documentation about security monitoring

---

**Recommendation**: Implement the fixes immediately to ensure Security tab shows accurate, live data.

