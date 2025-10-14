# Cache Key Fix - Verification Checklist

**Version:** 1.7.2  
**Date:** 2025-10-14

---

## ✅ Pre-Deployment Checklist

- [x] Code changes made to externallib.php
- [x] Version updated to 1.7.2 (2024101401)
- [x] No syntax errors (verified with getDiagnostics)
- [x] Documentation created
- [x] Changes are minimal and focused

---

## 🧪 Testing Checklist

### **Test 1: Your Original Bug**
- [ ] Go to Company Settings
- [ ] Select ONLY "Refresher Brillio 2024" (Course 371)
- [ ] Save settings
- [ ] Call API immediately
- [ ] **Expected:** Returns ONLY course 371 data
- [ ] **Expected:** Does NOT return course 70 data

### **Test 2: Cache Still Works**
- [ ] Call API with same settings
- [ ] Call API again (same settings)
- [ ] **Expected:** Second call is faster (cache hit)
- [ ] Check debug logs for "Cache hit" message

### **Test 3: Multiple Course Changes**
- [ ] Enable courses [70, 371]
- [ ] Call API → Should return both courses
- [ ] Change to only [371]
- [ ] Call API → Should return only 371
- [ ] Change to only [70]
- [ ] Call API → Should return only 70
- [ ] **Expected:** Each change returns correct data immediately

### **Test 4: Field Changes**
- [ ] Enable all fields including "email"
- [ ] Call API → Response should include email
- [ ] Disable "email" field
- [ ] Call API → Response should NOT include email
- [ ] **Expected:** Field changes take effect immediately

### **Test 5: Debug Logs**
- [ ] Check `moodledata/alx_report_api_debug.log`
- [ ] Look for cache key with hashes
- [ ] **Expected:** `Cache key: api_response_123_100_0_full_abc123_def456`
- [ ] **Expected:** `Cache key components - Courses: [371], Fields: [...]`

---

## 🔍 What to Look For

### **Success Indicators:**
✅ API returns correct course data immediately after settings change  
✅ Cache key in logs includes two hash values  
✅ Debug logs show "Cache miss" after settings change  
✅ Debug logs show "Cache hit" on repeated calls with same settings  
✅ No 500 errors  
✅ No PHP errors in Moodle logs  

### **Failure Indicators:**
❌ Still getting wrong course data  
❌ Cache key doesn't include hashes  
❌ 500 Internal Server Error  
❌ PHP syntax errors  
❌ Cache not working at all  

---

## 🐛 If Something Goes Wrong

### **Rollback Steps:**
```bash
# Restore backup
cp local/local_alx_report_api/externallib.php.backup local/local_alx_report_api/externallib.php

# Or restore from git
git checkout local/local_alx_report_api/externallib.php
```

### **Debug Steps:**
1. Check Moodle error logs
2. Check debug logs: `tail -f moodledata/alx_report_api_debug.log`
3. Test with cache disabled (set TTL to 0)
4. Verify version: Check Site Administration → Notifications

---

## 📊 Expected Results

### **Before Fix:**
```
User selects course 371
API returns: [course 70 data, course 371 data] ❌
Reason: Old cached data
```

### **After Fix:**
```
User selects course 371
API returns: [course 371 data only] ✅
Reason: New cache key, fresh data
```

---

## 🎯 Success Criteria

The fix is successful if:
1. ✅ Changing course selection returns correct data immediately
2. ✅ Changing field selection returns correct fields immediately
3. ✅ Cache still works (repeated calls are faster)
4. ✅ No errors in logs
5. ✅ Debug logs show proper cache keys with hashes

---

## 📝 Notes

- Old cache entries will remain until they expire (60 minutes default)
- This is OK - they won't be used because cache keys are different
- Cache cleanup task will remove old entries
- No manual cache clearing needed

---

**Ready to test!** Follow the checklist above to verify the fix works correctly.
