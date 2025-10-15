# Cache Setting Testing Guide

**Quick guide to test the cache enable/disable fix**

---

## 🧪 TEST 1: Cache DISABLED (Company 42)

### Setup:
1. Go to Company Management → Company 42
2. **Uncheck** "Enable Response Caching"
3. Save settings

### Test Steps:

**Step 1: First API Call**
```
Expected:
- Debug log: "Cache enabled for company 42: NO"
- Debug log: "Cache disabled - skipping cache check"
- Debug log: "Cache disabled - skipping cache storage"
- Result: Returns data from database
```

**Step 2: Second API Call (Immediate)**
```
Expected:
- Debug log: "Cache enabled for company 42: NO"
- Debug log: "Cache disabled - skipping cache check"
- Result: Queries database again (no cache used)
- Performance: Slightly slower (no cache benefit)
```

**Step 3: Manual Sync**
```
Expected:
- Sync result shows: "cache_cleared: 0"
- Sync result shows: "cache_status: disabled"
- Debug log: Cache clear skipped
```

**Step 4: API Call After Sync**
```
Expected:
- Still queries database directly
- No cache involved
```

### ✅ Success Criteria:
- [ ] API never uses cache
- [ ] API never saves to cache
- [ ] Manual sync doesn't clear cache
- [ ] Always gets real-time data

---

## 🧪 TEST 2: Cache ENABLED (Default)

### Setup:
1. Go to Company Management → Any company
2. **Check** "Enable Response Caching"
3. Set Cache TTL to 60 minutes
4. Save settings

### Test Steps:

**Step 1: First API Call**
```
Expected:
- Debug log: "Cache enabled for company X: YES"
- Debug log: "Cache miss - will fetch fresh data"
- Debug log: "Cached result for sync mode: full, TTL: 3600 seconds"
- Result: Returns data from database and caches it
```

**Step 2: Second API Call (Immediate)**
```
Expected:
- Debug log: "Cache enabled for company X: YES"
- Debug log: "Cache hit - returning cached data"
- Result: Returns cached data (fast!)
- Performance: Much faster (cache benefit)
```

**Step 3: Manual Sync**
```
Expected:
- Sync result shows: "cache_cleared: 5" (or similar number)
- Debug log: Cache cleared successfully
```

**Step 4: API Call After Sync**
```
Expected:
- Debug log: "Cache miss - will fetch fresh data"
- Queries database for fresh data
- Caches new result
```

### ✅ Success Criteria:
- [ ] First call caches data
- [ ] Second call uses cache (fast)
- [ ] Manual sync clears cache
- [ ] Next call gets fresh data

---

## 🧪 TEST 3: Switch Cache Setting

### Test Steps:

**Step 1: Start with Cache ENABLED**
```
1. Enable cache
2. Call API → Caches result
3. Call API again → Returns cached data ✅
```

**Step 2: Disable Cache**
```
1. Disable cache in settings
2. Call API immediately
Expected:
- Ignores old cache
- Queries database directly ✅
```

**Step 3: Enable Cache Again**
```
1. Enable cache in settings
2. Call API
Expected:
- Queries database
- Caches new result ✅
```

### ✅ Success Criteria:
- [ ] Setting change takes effect immediately
- [ ] No need to clear cache manually
- [ ] Behavior switches correctly

---

## 🧪 TEST 4: Manual Sync Statistics

### Test with Cache ENABLED:
```
Run manual sync
Check result:
{
  "total_processed": 2,
  "records_updated": 2,
  "cache_cleared": 5,        ← Should show number
  "duration_seconds": 1
}
```

### Test with Cache DISABLED:
```
Run manual sync
Check result:
{
  "total_processed": 2,
  "records_updated": 2,
  "cache_cleared": 0,        ← Should be 0
  "cache_status": "disabled", ← Should show disabled
  "duration_seconds": 1
}
```

### ✅ Success Criteria:
- [ ] Statistics reflect cache status
- [ ] Easy to see if cache was cleared
- [ ] Clear indication when disabled

---

## 📊 PERFORMANCE COMPARISON

### Cache ENABLED:
```
First API call:  ~500ms (queries DB + caches)
Second API call: ~50ms  (returns cache) ← 10x faster!
Third API call:  ~50ms  (returns cache)
```

### Cache DISABLED:
```
First API call:  ~500ms (queries DB)
Second API call: ~500ms (queries DB) ← Same speed
Third API call:  ~500ms (queries DB)
```

**Trade-off:**
- Cache ENABLED: Faster, but may show slightly stale data
- Cache DISABLED: Slower, but always real-time data

---

## 🔍 DEBUG LOG LOCATIONS

### Where to Check Logs:
1. **Moodle Debug Log:** `moodledata/alx_report_api_debug.log`
2. **PHP Error Log:** Check your server's PHP error log
3. **Browser Console:** Check for any JavaScript errors

### What to Look For:

**Cache ENABLED:**
```
Cache enabled for company 42: YES
Cache miss - will fetch fresh data
Cached result for sync mode: full, TTL: 3600 seconds
```

**Cache DISABLED:**
```
Cache enabled for company 42: NO
Cache disabled - skipping cache check, will query database directly
Cache disabled - skipping cache storage
```

---

## ❌ TROUBLESHOOTING

### Issue: Cache still being used when disabled

**Check:**
1. Did you save the company settings?
2. Did you refresh the API call (not browser cache)?
3. Check debug log - does it say "Cache enabled: NO"?

**Solution:**
- Clear browser cache
- Hard refresh (Ctrl+F5)
- Check company settings were saved

---

### Issue: Cache not working when enabled

**Check:**
1. Is "Enable Response Caching" checked?
2. Is Cache TTL set to a positive number?
3. Check debug log - does it say "Cache enabled: YES"?

**Solution:**
- Verify checkbox is checked
- Set Cache TTL to 60 minutes
- Save settings

---

### Issue: Manual sync not clearing cache

**Check:**
1. Is cache enabled for the company?
2. Were any records actually processed?
3. Check sync statistics

**Solution:**
- Cache only clears if enabled AND records processed
- Check "cache_cleared" in sync result
- If "cache_status: disabled", cache is off (correct!)

---

## ✅ FINAL VERIFICATION

### All Tests Pass If:
- [ ] Cache ENABLED → Uses cache (fast responses)
- [ ] Cache DISABLED → Queries DB directly (real-time data)
- [ ] Setting change takes effect immediately
- [ ] Manual sync respects cache setting
- [ ] Debug logs show correct cache status
- [ ] Statistics reflect cache activity

**If all checked:** Fix is working correctly! ✅

---

## 📝 NOTES

### Default Behavior:
- New companies: Cache ENABLED by default
- Existing companies: Cache ENABLED (backward compatible)
- Company 42: Cache DISABLED (as configured)

### When to Use Cache DISABLED:
- Need real-time data
- Frequent data changes
- Testing/debugging
- Small company (performance not critical)

### When to Use Cache ENABLED:
- Large company (many users)
- Performance is critical
- Data doesn't change frequently
- Production environment

---

**Ready to test!** 🚀
