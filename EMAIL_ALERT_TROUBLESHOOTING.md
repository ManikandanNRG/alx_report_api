# Email Alert System - Troubleshooting Guide

**Date:** October 8, 2025  
**Issues:** 1) Scheduled task not showing, 2) Test email not received

---

## 🔧 **SOLUTION STEPS:**

### **Step 1: Upgrade Plugin to Register New Task**

The scheduled task needs to be registered with Moodle.

**What I did:**
1. ✅ Bumped version: `2024071601` → `2024100801`
2. ✅ Updated release: `1.4.1` → `1.5.0`

**What you need to do:**

#### **Option A: Via Moodle UI (Recommended)**
1. Go to **Site Administration** → **Notifications**
2. You should see "Plugin to be upgraded: ALX Report API"
3. Click **"Upgrade Moodle database now"**
4. Wait for upgrade to complete
5. Go to **Site Administration** → **Server** → **Scheduled tasks**
6. Search for "ALX" or "alert"
7. You should now see: **"Check system conditions and send alerts"**

#### **Option B: Via CLI (Faster)**
```bash
php admin/cli/upgrade.php
```

---

### **Step 2: Test Email Functionality**

I created a dedicated test page for you!

**Access the test page:**
```
https://your-moodle-site.com/local/alx_report_api/test_email_alert.php
```

**What it does:**
- ✅ Checks if alert system is enabled
- ✅ Shows configured recipients
- ✅ Sends a real test email
- ✅ Shows detailed results
- ✅ Displays recent alerts
- ✅ Provides troubleshooting tips

---

## 📧 **Why Email Might Not Be Received:**

### **1. Alert System Not Enabled**
**Check:** Control Center → System Configuration → Enable Alert System toggle

**Fix:** Turn the toggle ON (should be purple)

---

### **2. No Recipients Configured**
**Check:** Control Center → System Configuration → Alert Email Recipients field

**Fix:** Add your email address (e.g., `admin@company.com`)

---

### **3. Alert in Cooldown Period**
**Issue:** Same alert type/severity sent within last 60 minutes

**Fix:** Wait 60 minutes OR temporarily disable cooldown:
- Edit `lib.php`
- Find `local_alx_report_api_is_alert_in_cooldown()`
- Temporarily return `false;` for testing

---

### **4. Moodle Email Not Configured**
**Check:** Site Administration → Server → Email → Outgoing mail configuration

**Common issues:**
- SMTP server not configured
- Wrong SMTP credentials
- Port blocked by firewall
- No-reply address not set

**Test Moodle email:**
1. Go to Site Administration → Server → Email → Test outgoing mail configuration
2. Enter your email
3. Click "Send test email"
4. If this fails, Moodle email is not working (not plugin issue)

---

### **5. Email Going to Spam**
**Check:** Your spam/junk folder

**Why:** Automated emails often flagged as spam

**Fix:** 
- Add sender to safe senders list
- Configure SPF/DKIM records for your domain

---

### **6. Threshold Too High**
**Issue:** Test alert is "medium" severity, but threshold set to "high" or "critical"

**Check:** Control Center → System Configuration → Alert Severity Threshold

**Fix:** Set to "Low" or "Medium" for testing

---

## 🧪 **Testing Checklist:**

### **Before Testing:**
- [ ] Plugin upgraded (version 1.5.0)
- [ ] Alert System toggle is ON (purple)
- [ ] Email recipients configured
- [ ] Threshold set to "Medium" or lower
- [ ] Moodle email working (test via Site Admin)

### **Run Test:**
- [ ] Visit `/local/alx_report_api/test_email_alert.php`
- [ ] Click "Send Test Email Alert"
- [ ] Check for success message
- [ ] Check email inbox (and spam folder)

### **If Email Not Received:**
- [ ] Check spam/junk folder
- [ ] Verify email address is correct
- [ ] Test Moodle email system
- [ ] Check Moodle error logs
- [ ] Wait 60 minutes (cooldown) and try again

---

## 🔍 **Debugging Steps:**

### **1. Check Moodle Logs**
```
Site Administration → Reports → Logs
Filter by: User = You, Activity = Email
```

Look for email sending errors

---

### **2. Check PHP Error Logs**
Look for errors related to:
- `email_to_user`
- `local_alx_report_api_send_email_alert`
- SMTP connection errors

---

### **3. Check Alert Logs**
Visit test page to see "Recent Alerts" table

**If alerts are logged but not sent:**
- Email system issue
- Cooldown preventing send
- Threshold filtering

**If alerts are NOT logged:**
- Alert system disabled
- Function not being called

---

### **4. Manual Function Test**
Create a simple test file:

```php
<?php
require_once('../../config.php');
require_once('lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

// Direct function call
$result = local_alx_report_api_send_alert(
    'performance',
    'critical', // Use critical to bypass threshold
    'Direct test alert',
    ['test' => 'manual']
);

echo $result ? 'Success!' : 'Failed!';
```

---

## 📊 **Expected Behavior:**

### **After Upgrade:**
**Scheduled Tasks Page:**
```
✅ Sync reporting data incrementally (every hour)
✅ Check system conditions and send alerts (every 15 minutes) ← NEW!
```

### **After Test Email:**
**Email Received:**
```
Subject: [ALX Report API] 🟡 Medium Alert: Performance

Body: HTML email with:
- Yellow header (medium severity)
- Test message
- Your details
- Recommendations
- Link to monitoring dashboard
```

---

## 🎯 **Quick Fixes:**

### **Task Not Showing:**
```bash
# Run upgrade
php admin/cli/upgrade.php

# Or via UI
Site Administration → Notifications → Upgrade
```

### **Email Not Sending:**
```
1. Check: Site Admin → Server → Email → Test outgoing mail
2. If Moodle test fails → Fix Moodle email first
3. If Moodle test works → Check plugin configuration
```

### **Bypass Cooldown for Testing:**
```php
// In lib.php, temporarily change:
function local_alx_report_api_is_alert_in_cooldown(...) {
    return false; // Disable cooldown for testing
}
```

---

## ✅ **Verification:**

### **Task Registered:**
```
Site Administration → Server → Scheduled tasks
Search: "alert" or "ALX"
Result: "Check system conditions and send alerts" appears
```

### **Email Working:**
```
1. Visit test_email_alert.php
2. Click "Send Test Email Alert"
3. See success message
4. Receive email within 1-2 minutes
```

---

## 📝 **Summary:**

**To fix task not showing:**
1. Upgrade plugin (Site Admin → Notifications)
2. Check Scheduled tasks page

**To fix email not received:**
1. Visit `/local/alx_report_api/test_email_alert.php`
2. Follow on-screen instructions
3. Check configuration status
4. Test Moodle email system
5. Check spam folder

**Files created:**
- ✅ `test_email_alert.php` - Comprehensive test page
- ✅ `version.php` - Bumped to 1.5.0

---

## 🚀 **Next Steps:**

1. **Upgrade plugin** - Site Admin → Notifications
2. **Visit test page** - `/local/alx_report_api/test_email_alert.php`
3. **Send test email** - Click the button
4. **Check results** - Follow troubleshooting tips on page

---

**The test page will guide you through everything!** 🎉
