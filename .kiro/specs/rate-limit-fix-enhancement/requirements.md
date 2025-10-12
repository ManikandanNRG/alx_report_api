# Requirements Document: Rate Limit Fix & Per-Company Enhancement

## Introduction

This feature enhances the ALX Report API plugin's rate limiting system with per-company rate limit configuration. 

**CLARIFICATION:** The current rate limiting system is **working as designed**. When a rate limit is exceeded:
- ✅ The request IS blocked (no data is returned to the client)
- ✅ The violation IS logged for monitoring and security purposes
- ✅ This is industry standard practice (GitHub, AWS, Stripe all do this)
- ✅ Enables security monitoring, business intelligence, and debugging

The current implementation uses a global rate limit for all companies. This enhancement will allow administrators to set different rate limits for different companies, supporting tiered service levels and better resource allocation.

**Enhancement Goal:** Add per-company rate limit configuration while maintaining backward compatibility with the global default.

---

## Requirements

### Requirement 1: Per-Company Rate Limit Configuration

**User Story:** As a system administrator, I want to set different rate limits for different companies, so that I can offer tiered service levels and allocate resources appropriately based on company size or subscription tier.

#### Acceptance Criteria

1. WHEN an administrator views a company's settings page THEN the system SHALL display a "Rate Limit (requests/day)" field with the current value
2. WHEN an administrator sets a company-specific rate limit THEN the system SHALL store it in the `local_alx_api_settings` table with setting name `rate_limit`
3. WHEN a company has a custom rate limit set THEN the system SHALL use that value instead of the global default
4. WHEN a company does not have a custom rate limit set THEN the system SHALL use the global default rate limit from plugin settings
5. WHEN the rate limit check is performed THEN the system SHALL first check for company-specific setting, then fall back to global default
6. WHEN an administrator clears a company's custom rate limit THEN the system SHALL revert to using the global default for that company

---

### Requirement 2: Backward Compatibility

**User Story:** As a system administrator, I want the rate limit fix to work with existing configurations, so that I don't need to reconfigure all companies after the update.

#### Acceptance Criteria

1. WHEN the plugin is updated THEN existing companies without custom rate limits SHALL continue using the global default
2. WHEN the global rate limit setting exists THEN it SHALL continue to function as the default for all companies
3. WHEN existing API tokens are used THEN they SHALL work with the new rate limit system without requiring regeneration
4. WHEN existing company settings are present THEN they SHALL not be affected by the rate limit enhancement
5. WHEN the rate limit check fails to find a company setting THEN it SHALL gracefully fall back to the global default without errors

---

### Requirement 3: Rate Limit Monitoring & Visibility

**User Story:** As a system administrator, I want to see rate limit usage and violations, so that I can monitor API usage patterns and adjust limits as needed.

#### Acceptance Criteria

1. WHEN a rate limit violation occurs THEN the system SHALL log it to the `local_alx_api_alerts` table with alert_type='rate_limit_exceeded'
2. WHEN viewing the monitoring dashboard THEN administrators SHALL see rate limit statistics including violations per company
3. WHEN a company approaches their rate limit (80% threshold) THEN the system SHALL log a warning alert
4. WHEN viewing company settings THEN administrators SHALL see current usage count for today alongside the rate limit setting
5. WHEN rate limit data is displayed THEN it SHALL show: current count, limit, percentage used, and time until reset (midnight)

---

### Requirement 4: Admin UI Enhancement

**User Story:** As a system administrator, I want an intuitive interface to manage per-company rate limits, so that I can easily configure and monitor rate limiting across all companies.

#### Acceptance Criteria

1. WHEN viewing the company settings page THEN the rate limit field SHALL be clearly labeled and include help text explaining the feature
2. WHEN entering a rate limit value THEN the system SHALL validate it is a positive integer between 1 and 10000
3. WHEN saving an invalid rate limit value THEN the system SHALL display a clear error message and not save the value
4. WHEN the rate limit field is empty THEN the system SHALL display "(using global default: X)" as placeholder text
5. WHEN viewing the company list THEN administrators SHALL optionally see a column showing each company's rate limit (custom or default)

---

### Requirement 5: Testing & Validation

**User Story:** As a developer, I want comprehensive testing of the rate limit system, so that I can ensure it works correctly and doesn't break existing functionality.

#### Acceptance Criteria

1. WHEN testing rate limit enforcement THEN requests beyond the limit SHALL be blocked and not logged
2. WHEN testing per-company limits THEN different companies SHALL have independent rate limit counters
3. WHEN testing the daily reset THEN rate limit counters SHALL reset at midnight
4. WHEN testing with no company setting THEN the global default SHALL be used correctly
5. WHEN testing with a company setting THEN the custom limit SHALL override the global default
6. WHEN testing error scenarios THEN the system SHALL handle missing settings, invalid values, and database errors gracefully

---

## Non-Functional Requirements

### Performance
- Rate limit check SHALL complete in less than 50ms
- Per-company setting lookup SHALL use existing caching mechanisms
- No additional database queries SHALL be added to the main API request flow beyond the rate limit check

### Security
- Rate limit bypass vulnerability SHALL be completely eliminated
- Rate limit settings SHALL only be modifiable by administrators with `moodle/site:config` capability
- Rate limit violations SHALL be logged for security monitoring

### Maintainability
- Code changes SHALL follow existing plugin coding standards
- New functions SHALL include PHPDoc comments
- Changes SHALL not modify existing database schema (use existing `local_alx_api_settings` table)

### Compatibility
- Changes SHALL be compatible with Moodle 4.2.6+
- Changes SHALL not break existing API clients
- Changes SHALL not require API token regeneration

---

## Success Criteria

1. ✅ Per-company rate limits are configurable via company settings page
2. ✅ Global default rate limit continues to work for companies without custom settings
3. ✅ Rate limit violations continue to be logged for monitoring (current behavior maintained)
4. ✅ Admin UI provides clear interface for managing rate limits
5. ✅ All existing functionality continues to work without modification
6. ✅ No 500 errors or breaking changes introduced
7. ✅ Rate limiting continues to block requests while logging violations for security monitoring

---

## Out of Scope

- Changing the rate limit time window (remains daily, resets at midnight)
- Implementing per-user rate limits (remains per-company)
- Adding rate limit tiers or multiple limit types
- Implementing rate limit APIs for external management
- Changing the existing database schema or adding new tables

---

## Assumptions

- The `local_alx_api_settings` table exists and is functioning correctly
- The `local_alx_api_alerts` table exists for logging violations
- The company settings page (`company_settings.php`) exists and is accessible
- The global rate limit setting (`rate_limit`) exists in plugin configuration
- The `check_rate_limit()` function in `externallib.php` is the only rate limit enforcement point

---

## Dependencies

- Existing `local_alx_api_settings` table structure
- Existing `local_alx_api_alerts` table structure
- Existing company settings management functions in `lib.php`
- Existing rate limit configuration in plugin settings

---

## Risks & Mitigation

### Risk 1: Breaking Existing Rate Limit Functionality
**Mitigation:** Maintain backward compatibility by keeping global default as fallback

### Risk 2: Performance Impact from Additional Setting Lookup
**Mitigation:** Use existing company settings caching mechanism

### Risk 3: Incorrect Rate Limit Enforcement
**Mitigation:** Comprehensive testing with multiple scenarios before deployment

### Risk 4: UI Changes Causing Confusion
**Mitigation:** Clear help text and placeholder values showing defaults

---

## Acceptance Testing Scenarios

### Scenario 1: Rate Limit Enforcement (Current Behavior - Should Remain)
1. Set global rate limit to 5 requests/day
2. Make 5 successful API requests - all succeed and are logged
3. Make 6th request - should fail with rate limit error BUT be logged as violation
4. Check database - should show 6 logged requests (5 successful + 1 violation)
5. Make 7th request - should still fail and be logged as violation
6. Check database - should show 7 logged requests (5 successful + 2 violations)
7. Monitoring dashboard should show: 5 successful calls, 2 violations

### Scenario 2: Per-Company Rate Limits
1. Company A: Set custom rate limit to 10 requests/day
2. Company B: Leave at global default (100 requests/day)
3. Company A makes 10 requests - all succeed
4. Company A makes 11th request - fails with rate limit error
5. Company B makes 10 requests - all succeed (not affected by Company A)
6. Company B can continue making requests up to 100

### Scenario 3: Global Default Fallback
1. Company C: No custom rate limit set
2. Global default: 100 requests/day
3. Company C makes requests - should use 100 request limit
4. Set Company C custom limit to 50
5. Company C should now use 50 request limit
6. Remove Company C custom limit
7. Company C should revert to 100 request limit

### Scenario 4: Daily Reset
1. Company makes requests up to limit
2. Wait until after midnight (or simulate time change)
3. Rate limit counter should reset to 0
4. Company should be able to make requests again

---

## Definition of Done

- [ ] Per-company rate limit setting is stored in `local_alx_api_settings` table
- [ ] Company settings page UI includes rate limit field with validation
- [ ] Rate limit check uses company setting with fallback to global default
- [ ] Rate limit violations continue to be logged for monitoring (existing behavior maintained)
- [ ] All acceptance criteria are met and tested
- [ ] No existing functionality is broken
- [ ] Code is reviewed and follows plugin standards
- [ ] Documentation is updated to clarify rate limit logging is intentional
- [ ] Changes are deployed without errors

---

**Document Version:** 1.0  
**Created:** October 6, 2025  
**Status:** Ready for Review
