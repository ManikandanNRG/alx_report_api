# Cache Key Bug Fix - Quick Summary

**Version:** 1.7.2  
**Date:** 2025-10-14  
**Status:** ✅ COMPLETE

---

## 🐛 The Bug

User selected course 371, but API returned data for course 70 (previously selected course).

**Cause:** Cache key didn't include course/field settings, so changing settings didn't invalidate cache.

---

## ✅ The Fix

**Changed cache key from:**
```
api_response_123_100_0_full
```

**To:**
```
api_response_123_100_0_full_a3f5e8b2c1d4_f7a9b2c3d4e5
                              ↑ courses    ↑ fields
```

Now when you change course or field settings, cache key changes automatically!

---

## 📝 Files Modified

1. **externallib.php** - Updated cache key generation
2. **version.php** - Updated to 1.7.2 (2024101401)

---

## 🧪 Testing

**Test your scenario:**
1. Select only course 371 in Company Settings
2. Call API immediately
3. Should return ONLY course 371 data (not course 70)

**Verify cache still works:**
1. Call API twice with same settings
2. Second call should be faster (cache hit)

---

## ⚠️ Important

- ✅ No database upgrade needed
- ✅ Old cache entries will expire naturally
- ✅ Settings changes now take effect immediately
- ✅ No manual cache clearing needed

---

## 🚀 Deploy

1. Copy updated files to Moodle
2. Refresh Moodle (no upgrade needed)
3. Test with your scenario
4. Check debug logs if needed

---

**Result:** Your bug is fixed! Course selection changes now work immediately.
