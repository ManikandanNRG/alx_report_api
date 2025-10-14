# Batch Size Bug Analysis

**Date**: 2025-10-14  
**Status**: 🔴 CRITICAL BUG CONFIRMED  
**Impact**: Batch size parameter is ignored during data population

---

## 🐛 Bug Report

**User Report:**
- Selected: 1 company
- Batch Size: 1000 records
- Expected: Maximum 1000 records inserted
- Actual: 3,313 records inserted

**Conclusion:** The batch_size parameter is being ignored!

---

## 🔍 Investigation Results

### 1. Code Flow Analysis

**populate_reporting_table.php (Line 420):**
```php
$result = local_alx_report_api_populate_reporting_table($company_id, $batch_size, true);
```

The function is called with `$batch_size` parameter, but:

❌ **PROBLEM**: The function `local_alx_report_api_populate_reporting_table()` does NOT exist in the codebase!

### 2. Search Results

Searched in:
- ✅ `lib.php` - Function NOT found
- ✅ `populate_reporting_table.php` - Function NOT defined
- ✅ `externallib.php` - Function NOT found
- ✅ `sync_reporting_data.php` - No batch logic found
- ✅ All PHP files - Function does NOT exist

### 3. Root Cause

**The function `local_alx_report_api_populate_reporting_table()` is missing or undefined!**

This means:
1. Either the function was never implemented
2. Or it was removed/renamed
3. Or there's a fatal PHP error being silently caught

---

## 📊 Expected vs Actual Behavior

### Expected Behavior:

```php
function local_alx_report_api_populate_reporting_table($companyid, $batch_size, $verbose = false) {
    global $DB;
    
    // Get users for the company
    $sql = "SELECT ... FROM ... WHERE companyid = ? LIMIT ?";
    $users = $DB->get_records_sql($sql, [$companyid, $batch_size]);  // ✅ LIMIT applied
    
    // Process only $batch_size records
    foreach ($users as $user) {
        // Insert into reporting table
    }
    
    return [
        'success' => true,
        'total_processed' => count($users),  // Should be <= $batch_size
        'total_inserted' => $inserted_count,
        ...
    ];
}
```

### Actual Behavior:

```php
// Function doesn't exist!
// OR
// Function exists but ignores $batch_size parameter
// OR
// Function uses wrong SQL without LIMIT clause
```

---

## 🎯 The Bug

**Scenario 1: Function Missing**
- The function is called but doesn't exist
- PHP should throw a fatal error
- But the error might be caught/suppressed
- All records are processed anyway (3,313 instead of 1,000)

**Scenario 2: Function Ignores Parameter**
- The function exists but doesn't use `$batch_size`
- SQL query doesn't have `LIMIT` clause
- All records for the company are processed

**Scenario 3: Wrong Implementation**
- The function uses `$batch_size` for something else
- But not for limiting the number of records

---

## 🔧 Required Fix

### Step 1: Find or Create the Function

The function should be in `lib.php`:

```php
/**
 * Populate reporting table with data from a specific company.
 *
 * @param int $companyid Company ID (0 for all companies)
 * @param int $batch_size Maximum number of records to process
 * @param bool $verbose Whether to output progress messages
 * @return array Result array with success status and statistics
 */
function local_alx_report_api_populate_reporting_table($companyid = 0, $batch_size = 1000, $verbose = false) {
    global $DB;
    
    $result = [
        'success' => true,
        'total_processed' => 0,
        'total_inserted' => 0,
        'companies_processed' => 0,
        'errors' => []
    ];
    
    try {
        if ($companyid == 0) {
            // Process all companies
            $companies = $DB->get_records('company', null, '', 'id, name, shortname');
            
            foreach ($companies as $company) {
                // Process each company with batch_size limit
                $company_result = process_company_data($company->id, $batch_size);
                $result['total_processed'] += $company_result['processed'];
                $result['total_inserted'] += $company_result['inserted'];
                $result['companies_processed']++;
            }
        } else {
            // Process single company with batch_size limit
            $company_result = process_company_data($companyid, $batch_size);
            $result['total_processed'] = $company_result['processed'];
            $result['total_inserted'] = $company_result['inserted'];
            $result['companies_processed'] = 1;
        }
    } catch (Exception $e) {
        $result['success'] = false;
        $result['errors'][] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Process data for a single company with batch size limit.
 *
 * @param int $companyid Company ID
 * @param int $batch_size Maximum number of records to process
 * @return array Array with 'processed' and 'inserted' counts
 */
function process_company_data($companyid, $batch_size) {
    global $DB;
    
    $processed = 0;
    $inserted = 0;
    
    // Get users for this company with LIMIT
    $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email,
                   cu.companyid
            FROM {user} u
            JOIN {company_users} cu ON cu.userid = u.id
            WHERE cu.companyid = ?
            AND u.deleted = 0
            LIMIT ?";  // ✅ CRITICAL: Apply batch_size limit here!
    
    $users = $DB->get_records_sql($sql, [$companyid, $batch_size]);
    
    foreach ($users as $user) {
        $processed++;
        
        // Get user's course enrollments
        $enrollments = get_user_enrollments($user->id, $companyid);
        
        foreach ($enrollments as $enrollment) {
            // Insert into reporting table
            $record = new stdClass();
            $record->companyid = $companyid;
            $record->userid = $user->id;
            $record->courseid = $enrollment->courseid;
            $record->username = $user->username;
            // ... other fields ...
            $record->timecreated = time();
            $record->timemodified = time();
            
            try {
                $DB->insert_record(constants::TABLE_REPORTING, $record);
                $inserted++;
            } catch (Exception $e) {
                // Log error but continue
                error_log("Failed to insert record: " . $e->getMessage());
            }
        }
    }
    
    return [
        'processed' => $processed,
        'inserted' => $inserted
    ];
}
```

### Step 2: Key Points

1. **LIMIT Clause**: Must use `LIMIT ?` in SQL with `$batch_size` parameter
2. **Parameter Binding**: Pass `$batch_size` to `get_records_sql()`
3. **Respect Limit**: Don't process more than `$batch_size` records
4. **Return Accurate Counts**: Return actual processed/inserted counts

---

## 🧪 Testing

### Test Case 1: Single Company, Batch Size 1000
- Select: 1 company
- Batch Size: 1000
- Expected: Maximum 1000 records processed
- Verify: Check `total_processed` <= 1000

### Test Case 2: Single Company, Batch Size 100
- Select: 1 company  
- Batch Size: 100
- Expected: Maximum 100 records processed
- Verify: Check `total_processed` <= 100

### Test Case 3: Multiple Companies, Batch Size 500
- Select: 3 companies
- Batch Size: 500
- Expected: Maximum 500 records per company (1500 total)
- Verify: Each company processes <= 500 records

---

## 📝 Summary

**Bug**: Batch size parameter is completely ignored

**Root Cause**: Function `local_alx_report_api_populate_reporting_table()` either:
- Doesn't exist
- Doesn't use the `$batch_size` parameter
- Doesn't apply `LIMIT` clause in SQL

**Impact**: 
- Users can't control how many records to process
- Risk of timeout on large datasets
- Unexpected behavior (3,313 records instead of 1,000)

**Fix Required**: 
- Implement or fix the function in `lib.php`
- Add `LIMIT ?` clause to SQL query
- Pass `$batch_size` parameter to database query
- Return accurate counts

**Priority**: 🔴 HIGH - This breaks a core feature

---

**Next Steps**: 
1. Locate or create the missing function
2. Implement proper batch_size limiting
3. Test with different batch sizes
4. Verify counts match expectations
