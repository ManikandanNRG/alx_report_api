# Email Alert System - Complete Guide

**Plugin:** ALX Report API  
**Version:** 1.7.2+  
**Date:** 2025-10-14

---

## 📧 OVERVIEW

The Email Alert System automatically monitors your API for security issues, performance problems, and rate limit violations, then sends email notifications to administrators.

---

## 🎯 KEY FEATURES

### 1. **Automatic Alert Creation**
Alerts are automatically created when:
- Rate limits are exceeded
- Security issues detected
- Performance problems occur
- System health degrades

### 2. **Email Notifications**
- Sends professional HTML emails
- Configurable recipients (multiple emails supported)
- Severity-based filtering
- Cooldown period to prevent spam

### 3. **Scheduled Processing**
- Runs every 15 minutes automatically
- Processes unresolved alerts
- Marks alerts as resolved after sending

### 4. **Configurable Settings**
- Enable/disable alerting
- Set severity threshold
- Configure recipients
- Adjust cooldown period

---

## 🔄 HOW IT WORKS

### **Complete Flow:**

```
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: Alert Creation (Real-time)                         │
└─────────────────────────────────────────────────────────────┘
                            ↓
    When event occurs (e.g., rate limit exceeded):
    
    1. externallib.php detects violation
    2. Creates record in alerts table:
       - alert_type: 'rate_limit_exceeded'
       - severity: 'high'
       - message: Details about violation
       - hostname: IP address
       - resolved: 0 (unresolved)
       - timecreated: timestamp

┌─────────────────────────────────────────────────────────────┐
│ STEP 2: Scheduled Task (Every 15 minutes)                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
    Task: check_alerts_task runs automatically
    
    1. Checks if alerting is enabled
    2. Calls: local_alx_report_api_check_and_alert()

┌─────────────────────────────────────────────────────────────┐
│ STEP 3: Alert Processing (lib.php)                         │
└─────────────────────────────────────────────────────────────┘
                            ↓
    Function: local_alx_report_api_check_and_alert()
    
    1. Query alerts table for unresolved alerts (resolved = 0)
    2. Filter by severity threshold (from settings)
    3. Check cooldown period (prevent spam)
    4. Get recipients from settings
    5. Send email to each recipient
    6. Mark alert as resolved (resolved = 1)

┌─────────────────────────────────────────────────────────────┐
│ STEP 4: Email Delivery                                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
    Uses Moodle's email_to_user() function
    
    1. Creates recipient user object
    2. Formats email with alert details
    3. Sends via Moodle's email system
    4. Returns success/failure status
```

---

## ⚙️ CONFIGURATION SETTINGS

### **Location:** Control Center → Settings Tab

| Setting | Default | Description |
|---------|---------|-------------|
| **Enable Alert System** | Yes | Master switch for entire alert system |
| **Enable Email Alerts** | Yes | Enable/disable email sending |
| **Alert Severity Threshold** | Medium | Minimum severity to send emails |
| **Alert Recipients** | (empty) | Comma-separated email addresses |
| **Alert Cooldown Period** | 60 minutes | Time between same alert type |

### **Severity Levels:**

```
┌──────────────────────────────────────────────────────────┐
│ Threshold: LOW                                           │
│ Sends: Low, Medium, High, Critical                       │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ Threshold: MEDIUM (Recommended)                          │
│ Sends: Medium, High, Critical                            │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ Threshold: HIGH                                          │
│ Sends: High, Critical                                    │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ Threshold: CRITICAL                                      │
│ Sends: Critical only                                     │
└──────────────────────────────────────────────────────────┘
```

---

## 📊 DATABASE STRUCTURE

### **Table: mdl_local_alx_report_api_alerts**

```sql
CREATE TABLE mdl_local_alx_report_api_alerts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    alert_type VARCHAR(50),      -- Type of alert
    severity VARCHAR(20),         -- low, medium, high, critical
    message TEXT,                 -- Alert message
    hostname VARCHAR(255),        -- IP address or hostname
    resolved TINYINT(1),          -- 0 = unresolved, 1 = resolved
    timecreated BIGINT,          -- When alert was created
    timeresolved BIGINT          -- When alert was resolved (NULL if unresolved)
);
```

### **Alert Types:**

| Type | Description | Typical Severity |
|------|-------------|------------------|
| `rate_limit_exceeded` | User exceeded daily API call limit | High |
| `health` | System health score below threshold | Critical/Medium |
| `performance` | High API usage or slow response | Medium |
| `security` | Security-related issues | High |

---

## 📧 EMAIL TEMPLATE

### **Email Format:**

```
From: noreply@yourdomain.com
To: admin@example.com, manager@example.com
Subject: [ALX Report API] high Alert: rate_limit_exceeded

Alert Details:

Type: rate_limit_exceeded
Severity: HIGH
Message: User John Doe from Company ABC exceeded rate limit (151/150 requests)
Hostname: 192.168.1.100
Time: 2025-10-14 19:32:33

View details in Control Center:
https://dev.aktrea.net/local/alx_report_api/control_center.php?tab=security
```

### **Email Customization:**

The email template is hardcoded in `lib.php` function `local_alx_report_api_check_and_alert()` around line 2650:

```php
$subject = "[ALX Report API] {$alert->severity} Alert: {$alert->alert_type}";

$message = "Alert Details:\n\n";
$message .= "Type: {$alert->alert_type}\n";
$message .= "Severity: " . strtoupper($alert->severity) . "\n";
$message .= "Message: {$alert->message}\n";
$message .= "Hostname: {$alert->hostname}\n";
$message .= "Time: " . date('Y-m-d H:i:s', $alert->timecreated) . "\n\n";
$message .= "View details in Control Center:\n";
$message .= $CFG->wwwroot . "/local/alx_report_api/control_center.php?tab=security\n";
```

**To customize the template:**
1. Edit `local/alx_report_api/lib.php`
2. Find the `local_alx_report_api_check_and_alert()` function
3. Modify the `$subject` and `$message` variables
4. Save and test

---

## 🔧 KEY FUNCTIONS

### **1. Alert Creation**

**File:** `externallib.php` (line 252-267)

```php
// Create alert when rate limit exceeded
$alert = new stdClass();
$alert->alert_type = 'rate_limit_exceeded';
$alert->severity = 'high';
$alert->message = "User {$username} from {$company_name} exceeded rate limit";
$alert->hostname = $_SERVER['REMOTE_ADDR'] ?? '';
$alert->resolved = 0;
$alert->timecreated = time();

$DB->insert_record(\local_alx_report_api\constants::TABLE_ALERTS, $alert);
```

### **2. Alert Processing**

**File:** `lib.php` (line 2571+)

```php
function local_alx_report_api_check_and_alert() {
    global $DB, $CFG;
    
    // Get unresolved alerts
    $unresolved_alerts = $DB->get_records_sql(
        "SELECT * FROM {alerts} 
         WHERE resolved = 0 
         AND severity IN (allowed_severities)"
    );
    
    // Process each alert
    foreach ($unresolved_alerts as $alert) {
        // Check cooldown
        // Get recipients
        // Send email
        // Mark as resolved
    }
}
```

### **3. Scheduled Task**

**File:** `classes/task/check_alerts_task.php`

```php
public function execute() {
    mtrace('ALX Report API: Starting alert check...');
    
    $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
    if (!$alerting_enabled) {
        mtrace('Alerting is disabled. Skipping.');
        return;
    }
    
    local_alx_report_api_check_and_alert();
    
    mtrace('ALX Report API: Alert check completed.');
}
```

---

## 🧪 TESTING

### **Method 1: Test Page (Recommended)**

1. Visit: `/local/alx_report_api/test_alert_processing.php`
2. View current settings and unresolved alerts
3. Click "Process Alerts & Send Emails" button
4. Check your email inbox

### **Method 2: Trigger Real Alert**

1. Make API calls exceeding the daily rate limit
2. Wait 15 minutes for scheduled task
3. Check email inbox

### **Method 3: Manual Task Execution**

```bash
php admin/cli/scheduled_task.php --execute='\local_alx_report_api\task\check_alerts_task'
```

### **Method 4: Test Email Function**

Visit: `/local/alx_report_api/test_email_alert.php`

---

## 🔍 TROUBLESHOOTING

### **No Emails Received?**

**Check 1: Alert System Enabled?**
```
Control Center → Settings → Enable Alert System = YES
Control Center → Settings → Enable Email Alerts = YES
```

**Check 2: Recipients Configured?**
```
Control Center → Settings → Alert Recipients = admin@example.com
```

**Check 3: Moodle Email Working?**
```
Site Administration → Server → Email → Test outgoing mail configuration
```

**Check 4: Alerts in Database?**
```sql
SELECT * FROM mdl_local_alx_report_api_alerts 
WHERE resolved = 0 
ORDER BY timecreated DESC;
```

**Check 5: Scheduled Task Running?**
```
Site Administration → Server → Scheduled tasks
Search for: "Check alerts and send notifications"
Status: Should show last run time
```

**Check 6: Cooldown Period?**
```
If same alert type was sent in last 60 minutes, it won't send again
Wait for cooldown period to expire
```

**Check 7: Severity Threshold?**
```
If threshold is HIGH, but alert is MEDIUM, it won't send
Lower the threshold or increase alert severity
```

### **Common Issues:**

| Issue | Cause | Solution |
|-------|-------|----------|
| 500 Error on test page | Missing global $CFG | Fixed in v1.7.2+ |
| No alerts created | Rate limit not exceeded | Trigger actual violation |
| Emails not sending | Moodle email not configured | Configure SMTP settings |
| Duplicate emails | Cooldown period too short | Increase cooldown period |
| Wrong recipients | Old email addresses | Update in settings |

---

## 📝 ADDING NEW ALERT TYPES

### **Step 1: Create Alert**

Add code where you want to trigger alert:

```php
global $DB;

$alert = new stdClass();
$alert->alert_type = 'custom_alert';  // Your alert type
$alert->severity = 'medium';          // low, medium, high, critical
$alert->message = 'Your custom message here';
$alert->hostname = $_SERVER['REMOTE_ADDR'] ?? '';
$alert->resolved = 0;
$alert->timecreated = time();

$DB->insert_record(\local_alx_report_api\constants::TABLE_ALERTS, $alert);
```

### **Step 2: Test**

1. Trigger the code that creates the alert
2. Check database: `SELECT * FROM mdl_local_alx_report_api_alerts`
3. Wait for scheduled task or run manually
4. Check email

### **Step 3: Customize Email (Optional)**

Edit `lib.php` function `local_alx_report_api_check_and_alert()` to add custom formatting for your alert type:

```php
// Add custom message for specific alert types
if ($alert->alert_type === 'custom_alert') {
    $message .= "\n\nCustom Instructions:\n";
    $message .= "1. Check the logs\n";
    $message .= "2. Contact support\n";
}
```

---

## 🎨 CUSTOMIZATION OPTIONS

### **1. Change Email Subject**

Edit line ~2649 in `lib.php`:

```php
// Current:
$subject = "[ALX Report API] {$alert->severity} Alert: {$alert->alert_type}";

// Custom:
$subject = "🚨 URGENT: {$alert->alert_type} - Action Required";
```

### **2. Add HTML Formatting**

Currently emails are plain text. To add HTML:

```php
// Create HTML message
$messagehtml = "<html><body>";
$messagehtml .= "<h2 style='color: red;'>Alert Details</h2>";
$messagehtml .= "<p><strong>Type:</strong> {$alert->alert_type}</p>";
$messagehtml .= "</body></html>";

// Send with HTML
$recipient->mailformat = 1; // 1 = HTML, 0 = plain text
email_to_user($recipient, $from, $subject, $message, $messagehtml);
```

### **3. Add Alert Recommendations**

```php
$recommendations = [
    'rate_limit_exceeded' => 'Consider increasing rate limit or blocking user',
    'performance' => 'Check server resources and optimize queries',
    'health' => 'Review system health checks immediately'
];

if (isset($recommendations[$alert->alert_type])) {
    $message .= "\n\nRecommended Action:\n";
    $message .= $recommendations[$alert->alert_type] . "\n";
}
```

### **4. Different Recipients per Alert Type**

```php
$recipients_by_type = [
    'rate_limit_exceeded' => ['security@example.com'],
    'performance' => ['devops@example.com'],
    'health' => ['admin@example.com', 'manager@example.com']
];

$recipients = $recipients_by_type[$alert->alert_type] ?? 
              explode(',', get_config('local_alx_report_api', 'alert_emails'));
```

---

## 📈 MONITORING & ANALYTICS

### **View Alert History**

```sql
-- All alerts
SELECT * FROM mdl_local_alx_report_api_alerts 
ORDER BY timecreated DESC;

-- Unresolved alerts
SELECT * FROM mdl_local_alx_report_api_alerts 
WHERE resolved = 0;

-- Alerts by type
SELECT alert_type, COUNT(*) as count 
FROM mdl_local_alx_report_api_alerts 
GROUP BY alert_type;

-- Alerts by severity
SELECT severity, COUNT(*) as count 
FROM mdl_local_alx_report_api_alerts 
GROUP BY severity;

-- Recent alerts (last 24 hours)
SELECT * FROM mdl_local_alx_report_api_alerts 
WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
ORDER BY timecreated DESC;
```

### **Alert Statistics**

View in Control Center → Security Tab:
- Total alerts
- Unresolved alerts
- Alerts by type
- Alerts by severity
- Recent alert timeline

---

## 🚀 BEST PRACTICES

1. **Configure Recipients Properly**
   - Use role-based emails (admin@, security@)
   - Add multiple recipients for redundancy
   - Test email delivery before going live

2. **Set Appropriate Threshold**
   - Start with MEDIUM (recommended)
   - Adjust based on alert volume
   - Don't set too low (spam) or too high (miss issues)

3. **Monitor Cooldown Period**
   - Default 60 minutes is good for most cases
   - Increase if getting too many emails
   - Decrease for critical systems

4. **Regular Testing**
   - Test monthly using test_alert_processing.php
   - Verify emails are being received
   - Check spam folders

5. **Review Alert History**
   - Check Control Center → Security tab weekly
   - Look for patterns in alerts
   - Adjust rate limits if needed

---

## 📚 FILES REFERENCE

| File | Purpose |
|------|---------|
| `externallib.php` | Creates alerts when events occur |
| `lib.php` | Contains alert processing functions |
| `classes/task/check_alerts_task.php` | Scheduled task that runs every 15 minutes |
| `test_alert_processing.php` | Test page for viewing/processing alerts |
| `test_email_alert.php` | Test page for sending test emails |
| `settings.php` | Alert configuration settings |
| `control_center.php` | View alert history (Security tab) |

---

## 🎯 SUMMARY

The Email Alert System is a **fully automated monitoring solution** that:

✅ Automatically detects issues (rate limits, performance, security)  
✅ Creates alerts in database  
✅ Sends email notifications every 15 minutes  
✅ Prevents spam with cooldown periods  
✅ Configurable severity thresholds  
✅ Multiple recipients supported  
✅ Professional email formatting  
✅ Easy to test and troubleshoot  

**Current Status:** ✅ FULLY IMPLEMENTED AND WORKING (v1.7.2+)

---

**Need Help?**
- Test Page: `/local/alx_report_api/test_alert_processing.php`
- Email Test: `/local/alx_report_api/test_email_alert.php`
- Settings: Control Center → Settings Tab
- History: Control Center → Security Tab
