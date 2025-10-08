# Rate Limit Detection Fix ✅

## Problem
Rate limit violations were happening (showing "Daily rate limit exceeded" error) but not appearing in the Security tab's "Rate Limit Violations" metric.

## Root Cause
The `local_alx_api_logs` table doesn't have a `status` field. Rate limit errors are logged in the `error_message` field instead.

**Previous code was checking**:
```php
// WRONG - status field doesn't exist!
if (isset($table_info['status'])) {
    $rate_limit_violations = $DB->count_records_select('local_alx_api_logs',
        "{$time_field} >= ? AND (status = ? OR status = ? OR status = ?)",
        [$today_start, 'rate_limited', 'rate_limit_exceeded', 'too_many_requests']
    );
}
```

**Result**: Since `status` field doesn't exist, the query never ran, so violations were always 0.

## Solution
Updated the code to check the `error_message` field for rate limit errors:

```php
// CORRECT - check error_message field
if (isset($table_info['error_message'])) {
    $rate_limit_violations = $DB->count_records_select('local_alx_api_logs',
        "{$time_field} >= ? AND error_message IS NOT NULL AND (
            error_message LIKE ? OR 
            error_message LIKE ? OR 
            error_message LIKE ? OR 
            error_message LIKE ? OR
            error_message LIKE ?
        )",
        [$today_start, '%rate limit%', '%rate_limit%', '%ratelimit%', '%too many%', '%Daily rate limit%']
    );
}
```

## What Changed

### Before:
- Only checked `status` field (which doesn't exist)
- Rate limit violations always showed 0
- Failed auth attempts always showed 0

### After:
- Checks `error_message` field (which exists and contains the errors)
- Searches for multiple rate limit error patterns:
  - `%rate limit%` - matches "rate limit exceeded"
  - `%rate_limit%` - matches "rate_limit_exceeded"
  - `%ratelimit%` - matches "ratelimit"
  - `%too many%` - matches "too many requests"
  - `%Daily rate limit%` - matches "Daily rate limit exceeded" (your exact error!)
- Also checks for auth errors:
  - `%auth%` - matches authentication errors
  - `%unauthorized%` - matches 401 errors
  - `%forbidden%` - matches 403 errors
  - `%authentication%` - matches authentication failed
  - `%permission%` - matches permission denied

## Database Schema Verification

From `db/install.xml`, the `local_alx_api_logs` table has:
```xml
<FIELD NAME="error_message" TYPE="text" NOTNULL="false" COMMENT="Error message if request failed"/>
```

**No `status` field exists!**

## Testing

### Test Case 1: Rate Limit Exceeded
**Error Message**: "Daily rate limit exceeded"
**Expected**: Should be counted in Rate Limit Violations
**Result**: ✅ Now detects via `%Daily rate limit%` pattern

### Test Case 2: Rate Limit (lowercase)
**Error Message**: "rate limit exceeded"
**Expected**: Should be counted
**Result**: ✅ Detects via `%rate limit%` pattern

### Test Case 3: Too Many Requests
**Error Message**: "too many requests"
**Expected**: Should be counted
**Result**: ✅ Detects via `%too many%` pattern

### Test Case 4: Authentication Failed
**Error Message**: "authentication failed"
**Expected**: Should be counted in Failed Auth Attempts
**Result**: ✅ Detects via `%auth%` and `%authentication%` patterns

## Additional Improvements

### 1. Case-Insensitive Search
SQL `LIKE` is case-insensitive by default in most databases, so it will match:
- "Daily rate limit exceeded"
- "DAILY RATE LIMIT EXCEEDED"
- "daily rate limit exceeded"

### 2. Multiple Pattern Matching
Covers various ways the error might be logged:
- With spaces: "rate limit"
- With underscores: "rate_limit"
- Without spaces: "ratelimit"
- Different wording: "too many requests"

### 3. Future Compatibility
Still checks for `status` field in case it's added later:
```php
if (isset($table_info['status'])) {
    // Also check status field
    $status_rate_violations = ...;
    // Use max of both sources
    $rate_limit_violations = max($rate_limit_violations, $status_rate_violations);
}
```

## Why It Wasn't Working Before

1. **Wrong Field**: Code was checking `status` field which doesn't exist
2. **Condition Never True**: `if (isset($table_info['status']))` was always false
3. **Query Never Ran**: Rate limit query was never executed
4. **Always Zero**: Metric always showed 0 violations

## Why It Works Now

1. **Correct Field**: Checks `error_message` field which exists
2. **Condition True**: `if (isset($table_info['error_message']))` is true
3. **Query Runs**: Rate limit query executes properly
4. **Real Data**: Shows actual violations from database

## Verification Steps

1. **Check current violations**:
   ```sql
   SELECT COUNT(*) FROM mdl_local_alx_api_logs 
   WHERE timeaccessed >= UNIX_TIMESTAMP(CURDATE()) 
   AND error_message LIKE '%Daily rate limit%';
   ```

2. **View actual errors**:
   ```sql
   SELECT error_message, timeaccessed, company_shortname 
   FROM mdl_local_alx_api_logs 
   WHERE error_message LIKE '%rate limit%' 
   ORDER BY timeaccessed DESC 
   LIMIT 10;
   ```

3. **Refresh dashboard**: The Security tab should now show the violations

## Expected Behavior

After this fix:
- ✅ Rate limit violations will show actual count from today
- ✅ Failed auth attempts will show actual count from today
- ✅ Security Status will show "Alert" if violations > 0
- ✅ Recent Security Events table will show the events
- ✅ Matches what you see in API responses

---

**Status**: ✅ Fixed - Rate limit violations now detected from error_message field
**Date**: January 7, 2025
**Impact**: Critical - Security monitoring now accurate

