# Sync Intelligence Testing Guide

## 🌐 Browser Testing

### Step 1: Clear Sync History (Optional)
If you want to test from a fresh state, you can reset the sync status by accessing the monitoring dashboard and clearing the sync history for your company.

### Step 2: First Call - Full Sync Test
**URL:**
```
https://target.betterworklearning.com/webservice/rest/server.php?wstoken=2801e2d525ae404083d139035705441e&wsfunction=local_alx_report_api_get_course_progress&moodlewsrestformat=json&limit=100
```

**Expected Results:**
- ✅ Returns 12+ records (your current data)
- ✅ Response time: 1-3 seconds
- ✅ Creates sync status entry in database
- ✅ Sync mode: "full"

**What to Look For:**
```json
[
  {
    "userid": 123,
    "firstname": "John",
    "lastname": "Doe",
    "courseid": 456,
    "coursename": "Course Name",
    "status": "completed",
    "timecompleted": "2024-01-15 14:30:00"
  }
  // ... more records
]
```

### Step 3: Second Call - Incremental Sync Test
**Wait 30 seconds**, then call the same URL again.

**Expected Results:**
- ✅ Returns 0-5 records (only changes since first call)
- ✅ Response time: 0.1-0.5 seconds (much faster)
- ✅ Sync mode: "incremental"

**Possible Outcomes:**
1. **Empty Array `[]`**: No changes since last sync ✅ **PERFECT**
2. **Few Records**: Only new/changed data ✅ **PERFECT**
3. **Same Full Dataset**: Something may be wrong ❌

### Step 4: Third Call - Cache Test
**Immediately** call the same URL again (within 1 minute).

**Expected Results:**
- ✅ Very fast response (< 200ms)
- ✅ Same data as second call
- ✅ Cache hit indicator in logs

### Step 5: Force Full Sync Test
**Wait 25+ hours** OR **manually mark last sync as failed** in database, then call API.

**Expected Results:**
- ✅ Returns full dataset again
- ✅ Sync mode: "full"
- ✅ Response time back to 1-3 seconds

---

## 🔧 Postman Testing

### Setup Instructions

1. **Import Collection**
   - Open Postman
   - Click "Import" → "File" → Select `ALX_Report_API_Postman_Collection.json`
   - Collection will be imported with all test scenarios

2. **Verify Variables**
   - Collection variables are pre-configured:
     - `base_url`: https://target.betterworklearning.com
     - `token`: 2801e2d525ae404083d139035705441e
     - `function_name`: local_alx_report_api_get_course_progress

### Test Scenarios

#### 🎯 Scenario 1: Sync Intelligence Flow
**Run these in sequence:**

1. **"1. First Call - Full Sync Test"**
   - Automatically stores response time and record count
   - Check Console for: `Record count: X`

2. **"2. Second Call - Incremental Sync Test"**  
   - Compares with first call
   - Check Console for: `✅ Sync Intelligence Working: Incremental sync returned fewer records`

3. **"3. Third Call - Cache Test"**
   - Tests cache performance
   - Check Console for: `✅ Likely cache hit - very fast response`

#### 🚀 Scenario 2: Performance Testing
**Run these to test different loads:**

1. **"Small Batch Test (limit=10)"** - Quick response test
2. **"Large Batch Test (limit=500)"** - Load testing
3. **"Pagination Test (offset=100)"** - Pagination functionality

#### 📡 Scenario 3: POST Method Testing
**Test POST requests:**

1. **"POST - Full Sync Test"** - First POST call
2. **"POST - Incremental Test"** - Follow-up POST call

#### ⚠️ Scenario 4: Error Handling
**Test error conditions:**

1. **"Invalid Token Test"** - Should return authentication error
2. **"Invalid Function Test"** - Should return function not found error
3. **"Oversized Limit Test"** - Should handle limit validation

---

## 📊 How to Interpret Results

### ✅ Sync Intelligence Working Correctly

#### First Call Response:
```json
[
  {"userid": 1, "courseid": 101, "status": "completed"},
  {"userid": 2, "courseid": 102, "status": "in_progress"},
  // ... 10 more records
]
```
- **Record Count**: 12 records
- **Response Time**: 1.2 seconds
- **Sync Mode**: Full

#### Second Call Response:
```json
[]
```
- **Record Count**: 0 records (no changes)
- **Response Time**: 0.15 seconds  
- **Sync Mode**: Incremental
- **Intelligence**: ✅ **Working perfectly!**

### 🔍 Alternative Success Patterns

#### Pattern 1: Some Changes Detected
```json
[
  {"userid": 13, "courseid": 103, "status": "completed"}
]
```
- **Record Count**: 1 record (new completion)
- **Intelligence**: ✅ **Working - only returns changes**

#### Pattern 2: Cache Hit
```json
[]
```
- **Record Count**: 0 records
- **Response Time**: 45ms (very fast)
- **Cache**: ✅ **Cache hit - optimal performance**

### ❌ Potential Issues

#### Issue 1: Always Full Sync
If every call returns the full 12 records:
- **Cause**: Sync mode might be set to "Always Full Sync"
- **Check**: Company settings → Sync Mode should be "Auto"

#### Issue 2: Always Empty
If second call always returns `[]` but you expect changes:
- **Cause**: No actual data changes occurred (normal)
- **Test**: Manually complete a course, then call API

#### Issue 3: Slow Responses
If all calls take 1+ seconds:
- **Cause**: Cache not working or database performance
- **Check**: Cache hit rates in monitoring dashboard

---

## 🔍 Monitoring & Debugging

### Check Sync Status
**URL:** `https://target.betterworklearning.com/local/alx_report_api/auto_sync_status.php`

**Look For:**
- Total API calls count increasing
- Last sync timestamp updating
- Success rate (should be 99%+)
- Cache hit rate (should be 70%+)

### Database Queries (if you have DB access)
```sql
-- Check sync status
SELECT * FROM mdl_local_alx_api_sync_status WHERE companyid = 1;

-- Check cache entries  
SELECT cache_key, hit_count, expires_at FROM mdl_local_alx_api_cache WHERE companyid = 1;

-- Check recent API logs
SELECT * FROM mdl_local_alx_api_logs WHERE companyid = 1 ORDER BY timestamp DESC LIMIT 10;
```

### Debug Logs
If debug logging is enabled, check Moodle logs for entries like:
```
=== API Request Start (Combined Approach) ===
Company ID: 1, Limit: 100, Offset: 0
Sync mode determined: incremental
Cache hit - returning cached data
```

---

## 🎯 Success Criteria Checklist

### ✅ Basic Functionality
- [ ] First call returns data (full sync)
- [ ] Second call returns fewer/no records (incremental)
- [ ] Third call is faster (cache hit)
- [ ] No error responses

### ✅ Performance Indicators
- [ ] First call: 1-3 seconds response time
- [ ] Incremental calls: <0.5 seconds
- [ ] Cache hits: <200ms
- [ ] 85%+ reduction in data transfer

### ✅ Intelligence Verification
- [ ] Sync mode switches automatically
- [ ] Empty responses when no changes
- [ ] Full sync after time window
- [ ] Error recovery working

### ✅ Monitoring
- [ ] Sync status dashboard accessible
- [ ] Metrics updating correctly
- [ ] Cache statistics available
- [ ] Error logging functional

---

## 🚨 Troubleshooting

### Problem: "Empty [] response always"
**Solution:** This is likely correct! If no data changes between calls, incremental sync should return empty array.

### Problem: "Same full data every time"
**Check:** 
1. Company sync mode setting (should be "Auto")
2. Sync status table for errors
3. Time window settings

### Problem: "Very slow responses"
**Check:**
1. Database performance
2. Cache system functionality
3. Network connectivity
4. Server resources

### Problem: "Authentication errors"
**Check:**
1. Token validity
2. Web service configuration
3. User permissions
4. Function assignment to service

---

## 📞 Need Help?

If you encounter issues:

1. **Check Console Logs** (Browser F12 → Console)
2. **Review Postman Test Results** (Console tab in Postman)
3. **Access Monitoring Dashboard** (Auto Sync Status page)
4. **Check Database Tables** (sync_status, cache, logs)

The Sync Intelligence System is designed to be self-diagnosing and self-healing, so most issues resolve automatically on subsequent calls. 