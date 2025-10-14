# Email Alert System - Current Status & Analysis

**Date:** 2025-10-14  
**Issue:** #9 from PROJECT_ANALYSIS_AND_BUGS.md  
**Status:** ⚠️ INCOMPLETE - Needs Implementation

---

## 🐛 THE PROBLEM

### **What's Missing:**

The alert system has the **infrastructure** but is **NOT sending emails**:

1. ✅ **Alerts Table** - Exists and stores alerts
2. ✅ **Scheduled Task** - Runs every 15 minutes
3. ✅ **Task File** - `check_alerts_task.php` exists
4. ❌ **Alert Function** - `local_alx_report_api_check_and_alert()` DOES NOT EXIST
5. ❌ **Email Sending** - No email functionality implemented
6. ❌ **Settings UI** - No configuration for alert recipients
7. ❌ **Enable/Disable** - No setting to enable alerting

---

## 📊 CURRENT STATE

### **What Works:**

```
Alerts Table (local_alx_api_alerts)
├── ✅ Stores alerts (rate limit, auth failures, etc.)
├── ✅ Has severity levels (low, medium, high, critical)
├── ✅ Has resolved flag
└── ✅ Displays in Control Center Security tab
```

### **What's Broken:**

```
Scheduled Task (check_alerts_task)
├── ✅ Runs every 15 minutes
├── ✅ Checks if alerting is enabled
├── ❌ Calls non-existent function: local_alx_report_api_check_and_alert()
└── ❌ No email sending happens
```

---

## 🔍 DETAILED ANALYSIS

### **File: `check_alerts_task.php`**

**Current Code:**
```php
public function execute() {
    mtrace('ALX Report API: Starting alert check...');
    
    // Check if alerting is enabled
    $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
    if (!$alerting_enabled) {
        mtrace('ALX Report API: Alerting is disabled. Skipping alert check.');
        return;
    }
    
    // Call the alert checking function
    local_alx_report_api_check_and_alert();  // ← THIS FUNCTION DOESN'T EXIST!
    
    mtrace('ALX Report API: Alert check completed.');
}
```

**Problem:** The function `local_alx_report_api_check_and_alert()` is called but never defined anywhere.

---

### **Missing Function in `lib.php`**

**What Should Exist:**
```php
function local_alx_report_api_check_and_alert() {
    // 1. Query unresolved alerts
    // 2. Group by severity
    // 3. Get email recipients
    // 4. Send email notifications
    // 5. Mark alerts as notified
}
```

**Current Status:** Function does not exist in lib.php

---

### **Missing Settings**

**What Should Exist in `settings.php`:**
1. Enable/Disable alerting checkbox
2. Email recipients (comma-separated)
3. Alert threshold (minimum severity to send)
4. Email frequency (immediate, hourly, daily digest)
5. Test email button

**Current Status:** No alert settings in admin UI

---

## 🎯 WHAT NEEDS TO BE IMPLEMENTED

### **1. Alert Checking Function** (lib.php)

**Purpose:** Query alerts and prepare for sending

**Functionality:**
- Query unresolved alerts from database
- Filter by severity (only send high/critical)
- Group alerts by type
- Check if already notified recently (prevent spam)
- Return alerts that need notification

---

### **2. Email Sending Function** (lib.php)

**Purpose:** Send email notifications

**Functionality:**
- Use Moodle's `email_to_user()` function
- Format email with alert details
- Include:
  - Alert type and severity
  - Timestamp
  - Affected company/user
  - Link to Control Center
  - Action recommendations
- Support HTML email format

---

### **3. Settings Configuration** (settings.php)

**Purpose:** Admin configuration for alerts

**Settings Needed:**
```php
// Enable/Disable
$settings->add(new admin_setting_configcheckbox(
    'local_alx_report_api/enable_alerting',
    'Enable Email Alerts',
    'Send email notifications for security alerts',
    0
));

// Email Recipients
$settings->add(new admin_setting_configtextarea(
    'local_alx_report_api/alert_recipients',
    'Alert Recipients',
    'Email addresses (one per line)',
    ''
));

// Minimum Severity
$settings->add(new admin_setting_configselect(
    'local_alx_report_api/alert_min_severity',
    'Minimum Severity',
    'Only send alerts at or above this level',
    'high',
    ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']
));

// Email Frequency
$settings->add(new admin_setting_configselect(
    'local_alx_report_api/alert_frequency',
    'Email Frequency',
    'How often to send alert emails',
    'immediate',
    ['immediate' => 'Immediate', 'hourly' => 'Hourly Digest', 'daily' => 'Daily Digest']
));
```

---

### **4. Email Template** (lang/en/local_alx_report_api.php)

**Purpose:** Email content templates

**Strings Needed:**
```php
$string['alert_email_subject'] = 'ALX Report API Security Alert';
$string['alert_email_body'] = 'Security alerts detected in ALX Report API...';
$string['alert_email_footer'] = 'View details in Control Center: {$a}';
```

---

### **5. Alert Notification Tracking** (Database)

**Purpose:** Prevent duplicate emails

**Options:**
1. Add `notified` timestamp field to alerts table
2. Or track in separate table
3. Or use config setting for last notification time

---

## 📋 IMPLEMENTATION PLAN

### **Phase 1: Core Functionality (2-3 hours)**

1. **Create alert checking function** (30 min)
   - Query unresolved alerts
   - Filter by severity
   - Group by type

2. **Create email sending function** (1 hour)
   - Format email content
   - Use Moodle email API
   - Handle multiple recipients

3. **Add notification tracking** (30 min)
   - Add `notified` field to alerts table
   - Or use config setting

4. **Test email sending** (30 min)
   - Test with real alerts
   - Verify email delivery

### **Phase 2: Configuration UI (1-2 hours)**

5. **Add settings page** (1 hour)
   - Enable/disable checkbox
   - Email recipients field
   - Severity threshold
   - Frequency options

6. **Add test email button** (30 min)
   - Send test email to verify configuration
   - Show success/error message

### **Phase 3: Enhancement (Optional)**

7. **Email templates** (30 min)
   - HTML email format
   - Professional styling
   - Include alert details

8. **Digest mode** (1 hour)
   - Group multiple alerts
   - Send summary email
   - Reduce email spam

---

## 🎨 EMAIL FORMAT EXAMPLE

### **Subject:**
```
[ALX Report API] Security Alert: 3 High Priority Issues
```

### **Body:**
```
ALX Report API Security Alert
=============================

The following security issues require your attention:

🔴 CRITICAL (1 alert)
- Rate limit exceeded: User 'api_user' from Company 'Brillio' 
  exceeded limit (50/25 requests)
  Time: 2025-10-14 16:11:23
  
⚠️ HIGH (2 alerts)
- Token expiring soon: Token for 'Company A' expires in 5 days
  Time: 2025-10-14 14:30:00
  
- Failed authentication: 3 failed login attempts from IP 192.168.1.100
  Time: 2025-10-14 13:45:12

---
View full details and resolve alerts:
https://your-moodle-site/local/alx_report_api/control_center.php

This is an automated message from ALX Report API Plugin.
```

---

## ⚠️ IMPORTANT CONSIDERATIONS

### **1. Email Spam Prevention**

**Problem:** Too many emails can be annoying

**Solutions:**
- Only send high/critical alerts
- Implement digest mode (hourly/daily)
- Track last notification time
- Don't re-send for same alert

### **2. Email Delivery**

**Problem:** Moodle email might not be configured

**Solutions:**
- Check if Moodle email is working
- Provide test email button
- Show clear error messages
- Log email sending attempts

### **3. Alert Fatigue**

**Problem:** Too many alerts = ignored alerts

**Solutions:**
- Only alert on actionable issues
- Provide "resolve" functionality
- Auto-resolve old alerts
- Allow customizing thresholds

---

## 🧪 TESTING CHECKLIST

After implementation, test:

- [ ] Alert checking function runs without errors
- [ ] Email sends successfully
- [ ] Email contains correct alert details
- [ ] Multiple recipients receive email
- [ ] Severity filtering works
- [ ] Duplicate emails are prevented
- [ ] Settings save correctly
- [ ] Test email button works
- [ ] Scheduled task runs every 15 minutes
- [ ] Alerts are marked as notified

---

## 📊 IMPACT ASSESSMENT

### **Current Impact:**
- ❌ Admins don't know about security issues
- ❌ Rate limit violations go unnoticed
- ❌ Token expiry warnings not sent
- ❌ System issues not reported

### **After Implementation:**
- ✅ Proactive notification of issues
- ✅ Faster response to security events
- ✅ Reduced downtime
- ✅ Better system monitoring

---

## 🎯 RECOMMENDATION

**Priority:** HIGH (but not critical)

**Why Implement:**
- Improves security monitoring
- Proactive issue detection
- Professional system management
- Reduces manual monitoring

**Why It's Not Critical:**
- Alerts are visible in Control Center
- System works without emails
- Can be monitored manually
- No data loss if not implemented

**Estimated Time:** 3-5 hours total

**Complexity:** Medium (requires Moodle email API knowledge)

---

## 📝 SUMMARY

**What's Missing:**
1. Alert checking function
2. Email sending function
3. Settings configuration
4. Email templates
5. Notification tracking

**What Needs to be Done:**
1. Create `local_alx_report_api_check_and_alert()` function
2. Implement email sending with Moodle API
3. Add admin settings for configuration
4. Add notification tracking to prevent duplicates
5. Test email delivery

**Result:** Complete email alert system that notifies admins of security issues automatically.

---

**Status:** Ready for implementation when you decide to proceed!
