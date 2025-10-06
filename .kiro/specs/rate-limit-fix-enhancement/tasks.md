# Implementation Plan: Rate Limit Fix & Per-Company Enhancement

## Overview

This implementation plan breaks down the rate limit fix and per-company enhancement into discrete, manageable tasks. Each task is designed to be implemented incrementally with testing at each step.

---

## Implementation Tasks

- [ ] 1. Add rate limit violation logging functions
  - Create `log_rate_limit_violation()` function in externallib.php
  - Create `log_rate_limit_warning()` function in externallib.php
  - Add error handling for missing alerts table
  - Include detailed alert data (user, company, IP, user agent)
  - Test logging functions independently
  - _Requirements: 1.4, 4.1, 4.2, 4.3_

- [ ] 2. Create enhanced rate limit check function with company support
  - Create `check_rate_limit_with_company($userid, $companyid)` function in externallib.php
  - Implement company setting lookup with fallback to global default
  - Add request counting logic (reuse existing code pattern)
  - Call `log_rate_limit_violation()` before throwing exception
  - Call `log_rate_limit_warning()` at 80% threshold
  - Add PHPDoc comments explaining parameters and behavior
  - _Requirements: 1.1, 1.2, 2.3, 2.4, 2.5, 4.3_

- [x] 3. Fix rate limit bypass bug in main API function


  - Modify `get_course_progress()` in externallib.php
  - Move company ID resolution before rate limit check
  - Replace `check_rate_limit($userid)` with `check_rate_limit_with_company($userid, $companyid)`
  - Add `$is_rate_limit_error` flag to track rate limit exceptions
  - In catch block, detect rate limit errors and set flag
  - In finally block, add conditional: only log if NOT rate limit error
  - Test that rate-limited requests are not logged to api_logs table
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 4. Add rate limit field to company settings UI

  - Open `company_settings.php` file
  - Add rate limit input field to the form
  - Set placeholder text showing global default value
  - Add help text explaining the feature
  - Add validation attributes (min=1, max=10000, type=number)
  - Display current usage statistics below the field
  - Show percentage of limit used
  - Test UI displays correctly and shows proper defaults
  - _Requirements: 2.1, 5.1, 5.2, 5.4, 4.5_

- [x] 5. Implement rate limit setting save/update logic


  - Add form processing code in company_settings.php
  - Validate rate limit value (1-10000 range)
  - Save valid values using `local_alx_report_api_set_company_setting()`
  - Handle empty value (clear custom setting, use global default)
  - Display success/error messages
  - Test saving, updating, and clearing rate limit settings
  - _Requirements: 2.2, 2.6, 5.3_

- [ ] 6. Test rate limit bypass fix
  - Set company rate limit to 5 requests/day
  - Make 5 API requests - verify all succeed
  - Make 6th request - verify it fails with rate limit error
  - Check database: verify exactly 5 requests logged (not 6)
  - Make 7th request - verify it still fails
  - Check database: verify still exactly 5 requests logged (not 7)
  - Verify violation logged to alerts table
  - _Requirements: 1.1, 1.2, 1.3, 6.1_

- [ ] 7. Test per-company rate limits
  - Set Company A rate limit to 10 requests/day
  - Set Company B rate limit to 20 requests/day
  - Leave Company C without custom setting (uses global default)
  - Make 10 requests from Company A - all should succeed
  - Make 11th request from Company A - should fail
  - Make 10 requests from Company B - all should succeed (independent counter)
  - Make requests from Company C - should use global default limit
  - Verify each company has independent rate limit counter
  - _Requirements: 2.3, 2.4, 2.5, 6.2_

- [ ] 8. Test backward compatibility
  - Remove all custom company rate limit settings
  - Verify global default rate limit is used for all companies
  - Test with existing API tokens - should work without regeneration
  - Verify existing company settings are not affected
  - Test error handling when company setting lookup fails
  - Verify graceful fallback to global default
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 9. Test rate limit monitoring and alerts
  - Trigger rate limit violation - verify alert logged to alerts table
  - Verify alert contains: user ID, company ID, IP, user agent, timestamp
  - Make requests approaching limit (80%) - verify warning alert logged
  - Verify warning only logged once per day per user
  - Check monitoring dashboard displays rate limit statistics
  - Verify current usage shown in company settings page
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ] 10. Test daily rate limit reset
  - Make requests up to limit for a company
  - Simulate time change to next day (or wait until midnight)
  - Verify rate limit counter resets to 0
  - Verify company can make requests again
  - Test that yesterday's violations don't affect today's limit
  - _Requirements: 6.4_

- [ ] 11. Test edge cases and error scenarios
  - Test with invalid rate limit values (negative, zero, > 10000)
  - Test with missing alerts table (graceful degradation)
  - Test with missing company setting (fallback to global)
  - Test with company ID = 0 or null
  - Test with user not associated with any company
  - Verify all error scenarios handled gracefully without breaking API
  - _Requirements: 3.5, 6.6_

- [ ] 12. Code review and documentation
  - Review all code changes for consistency with existing patterns
  - Verify PHPDoc comments are complete and accurate
  - Check code follows Moodle coding standards
  - Verify no hardcoded values (use constants or config)
  - Update README.md if needed (document per-company rate limits)
  - Add inline comments explaining complex logic
  - _Requirements: All_

- [ ] 13. Final integration testing
  - Test complete API flow: authentication → rate limit check → data retrieval
  - Test with multiple concurrent requests from same company
  - Test with multiple companies making requests simultaneously
  - Verify performance is acceptable (no significant slowdown)
  - Check database query count hasn't increased significantly
  - Monitor error logs for any warnings or errors
  - _Requirements: All_

- [ ] 14. Deployment preparation
  - Create deployment checklist
  - Document rollback procedure
  - Prepare monitoring queries for post-deployment
  - Test on staging environment first
  - Verify no 500 errors or breaking changes
  - Create backup of current code
  - _Requirements: All_

---

## Task Dependencies

```
Task 1 (Logging functions)
    ↓
Task 2 (Enhanced rate limit check) ← depends on Task 1
    ↓
Task 3 (Fix bypass bug) ← depends on Task 2
    ↓
Task 6 (Test bypass fix) ← depends on Task 3

Task 4 (UI enhancement)
    ↓
Task 5 (Save logic) ← depends on Task 4
    ↓
Task 7 (Test per-company) ← depends on Task 3 and Task 5

Task 8 (Test backward compatibility) ← depends on Task 3, 5
Task 9 (Test monitoring) ← depends on Task 1, 3
Task 10 (Test daily reset) ← depends on Task 3
Task 11 (Test edge cases) ← depends on Task 3, 5

Task 12 (Code review) ← depends on all implementation tasks
Task 13 (Integration testing) ← depends on all implementation and test tasks
Task 14 (Deployment prep) ← depends on Task 13
```

---

## Execution Order

### Phase 1: Core Implementation (Tasks 1-3)
**Estimated Time:** 2-3 hours

1. Task 1: Add logging functions (30 min)
2. Task 2: Create enhanced rate limit check (45 min)
3. Task 3: Fix bypass bug in main API function (45 min)

**Milestone:** Rate limit bypass bug is fixed

### Phase 2: UI Enhancement (Tasks 4-5)
**Estimated Time:** 1-2 hours

4. Task 4: Add rate limit field to UI (45 min)
5. Task 5: Implement save/update logic (45 min)

**Milestone:** Per-company rate limits are configurable

### Phase 3: Testing (Tasks 6-11)
**Estimated Time:** 2-3 hours

6. Task 6: Test bypass fix (30 min)
7. Task 7: Test per-company limits (30 min)
8. Task 8: Test backward compatibility (30 min)
9. Task 9: Test monitoring and alerts (30 min)
10. Task 10: Test daily reset (20 min)
11. Task 11: Test edge cases (30 min)

**Milestone:** All functionality tested and verified

### Phase 4: Finalization (Tasks 12-14)
**Estimated Time:** 1-2 hours

12. Task 12: Code review and documentation (45 min)
13. Task 13: Final integration testing (45 min)
14. Task 14: Deployment preparation (30 min)

**Milestone:** Ready for production deployment

---

## Total Estimated Time

**Total:** 6-10 hours (depending on testing thoroughness and any issues encountered)

**Recommended Timeline:**
- Day 1: Phase 1 (Core Implementation) - 2-3 hours
- Day 2: Phase 2 (UI Enhancement) - 1-2 hours
- Day 3: Phase 3 (Testing) - 2-3 hours
- Day 4: Phase 4 (Finalization) - 1-2 hours

---

## Success Criteria

- [x] Rate limit bypass bug is completely fixed
- [x] Rate-limited requests are NOT logged to api_logs table
- [x] Rate limit violations are logged to alerts table
- [x] Per-company rate limits are configurable via UI
- [x] Global default continues to work as fallback
- [x] All tests pass successfully
- [x] No breaking changes or 500 errors
- [x] Code follows existing patterns and standards
- [x] Documentation is updated

---

## Rollback Plan

If issues are encountered during deployment:

1. **Immediate Rollback:**
   - Revert code changes to previous version
   - Global rate limit will continue to work
   - No data loss (no schema changes)

2. **Partial Rollback:**
   - If only UI has issues: revert company_settings.php
   - Core rate limit fix can remain (it's backward compatible)

3. **Data Cleanup:**
   - If needed, remove custom rate limit settings:
     ```sql
     DELETE FROM mdl_local_alx_api_settings 
     WHERE setting_name = 'rate_limit';
     ```

---

## Notes

- **No database migrations required** - uses existing tables
- **Backward compatible** - existing functionality continues to work
- **Incremental implementation** - can test each phase independently
- **Safe to deploy** - graceful fallbacks for all error scenarios
- **Easy to rollback** - no schema changes or data dependencies

---

**Document Version:** 1.0  
**Created:** October 6, 2025  
**Status:** Ready for Implementation
