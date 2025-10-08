# Security Tab Analysis - Features from test_alerts.php

## Summary
Analyzed `test_alerts.php` to identify useful security features that can be integrated into the Security & Alerts tab of the monitoring dashboard.

---

## 🔍 Features Found in test_alerts.php

### 1. **Alert System Configuration Status**
Shows real-time status of the alerting system:
- ✅ Alert System: Enabled/Disabled
- ✅ Email Alerts: Enabled/Disabled
- ✅ SMS Alerts: Enabled/Disabled
- ✅ Alert Threshold: Low/Medium/High/Critical
- ✅ Email Recipients: Count of configured emails
- ✅ SMS Service: Twilio/Nexmo/Disabled

**Usefulness**: ⭐⭐⭐⭐⭐ (Very Useful)
- Provides quick overview of security alert configuration
- Helps admins verify alert system is working
- Shows who will receive alerts

### 2. **Test Alert Functionality**
Allows sending test alerts with:
- Alert Type: Health, Performance, Security, Rate Limit
- Severity Level: Low, Medium, High, Critical
- Real-time feedback on success/failure

**Usefulness**: ⭐⭐⭐⭐ (Useful)
- Helps verify alert configuration is working
- Tests email delivery
- Validates alert recipients

### 3. **Alert Examples**
Shows sample alert formats:
- Critical Health Alert format
- High Security Alert format
- Medium Performance Alert format

**Usefulness**: ⭐⭐⭐ (Moderately Useful)
- Helps admins understand what alerts look like
- Educational for new users

### 4. **Recipients Information**
Displays:
- Configured email recipients (from settings)
- Site administrators (for critical alerts)
- Visual badges for each recipient

**Usefulness**: ⭐⭐⭐⭐ (Useful)
- Shows who will receive alerts
- Helps verify correct people are notified
- Displays both custom and admin recipients

---

## 📊 Recommended Features for Security Tab

### Priority 1: Must Have

#### 1. **Security Metrics Cards** (Already in current design)
- Active Tokens
- Rate Limit Violations
- Failed Auth Attempts
- Security Status

#### 2. **Alert System Status Panel** (NEW - from test_alerts.php)
```
┌─────────────────────────────────────┐
│ 🔔 Alert System Configuration      │
├─────────────────────────────────────┤
│ Alert System:     ✅ Enabled        │
│ Email Alerts:     ✅ Enabled        │
│ SMS Alerts:       ❌ Disabled       │
│ Alert Threshold:  Medium            │
│ Email Recipients: 3 configured      │
│ SMS Service:      Disabled          │
└─────────────────────────────────────┘
```

#### 3. **Recent Security Events Table** (Already in current design)
- Time, Event Type, User/IP, Details, Status

#### 4. **Active System Alerts Table** (Already in current design)
- Alert Type, Severity, Message, Time, Status

### Priority 2: Nice to Have

#### 5. **Quick Alert Test Button** (NEW - from test_alerts.php)
Add a button in the Security tab to send test alerts:
```html
<button onclick="sendTestAlert()">
    📧 Send Test Security Alert
</button>
```

#### 6. **Alert Recipients Display** (NEW - from test_alerts.php)
Show who will receive alerts:
```
📧 Alert Recipients:
- admin@example.com
- security@example.com
- John Doe (admin@site.com)
```

#### 7. **Alert History Chart** (NEW)
Show trend of security alerts over time:
- X-axis: Last 7 days
- Y-axis: Number of alerts
- Lines: Low, Medium, High, Critical

---

## 🎯 Recommended Implementation Plan

### Phase 1: Current Security Tab (Already Implemented)
✅ Security Metrics Cards
✅ Recent Security Events Table
✅ Active System Alerts Table

### Phase 2: Add Alert System Status (from test_alerts.php)
Add a new panel showing alert configuration status:
- Location: Between metrics cards and tables
- Data: Read from plugin settings
- Display: Status badges (Enabled/Disabled)

### Phase 3: Add Quick Actions (from test_alerts.php)
Add action buttons:
- "Send Test Alert" button
- "Configure Alerts" link to settings
- "View Alert History" link

### Phase 4: Add Recipients Display (from test_alerts.php)
Show configured alert recipients:
- Email recipients from settings
- Site administrators
- Visual badges for each

---

## 💡 Specific Features to Add

### Feature 1: Alert Configuration Status Panel

**Location**: After metric cards, before tables

**Code to Add**:
```php
<!-- Alert System Configuration -->
<div class="monitoring-table">
    <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">
        🔔 Alert System Configuration
    </h3>
    <table>
        <thead>
            <tr>
                <th>Setting</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $alerting_enabled = get_config('local_alx_report_api', 'enable_alerting');
            $email_enabled = get_config('local_alx_report_api', 'enable_email_alerts');
            $sms_enabled = get_config('local_alx_report_api', 'enable_sms_alerts');
            $alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
            $alert_emails = get_config('local_alx_report_api', 'alert_emails');
            ?>
            <tr>
                <td><strong>Alert System</strong></td>
                <td>
                    <span class="badge badge-<?php echo $alerting_enabled ? 'success' : 'danger'; ?>">
                        <?php echo $alerting_enabled ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </td>
                <td><?php echo $alerting_enabled ? 'System is monitoring and sending alerts' : 'Alerts are disabled'; ?></td>
            </tr>
            <tr>
                <td><strong>Email Alerts</strong></td>
                <td>
                    <span class="badge badge-<?php echo $email_enabled ? 'success' : 'warning'; ?>">
                        <?php echo $email_enabled ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </td>
                <td><?php echo $alert_emails ? count(explode(',', $alert_emails)) . ' recipients configured' : 'No recipients'; ?></td>
            </tr>
            <tr>
                <td><strong>Alert Threshold</strong></td>
                <td>
                    <span class="badge badge-info">
                        <?php echo ucfirst($alert_threshold); ?>
                    </span>
                </td>
                <td>Only alerts at <?php echo $alert_threshold; ?> level or higher will be sent</td>
            </tr>
        </tbody>
    </table>
</div>
```

### Feature 2: Quick Actions Panel

**Location**: After Alert Configuration panel

**Code to Add**:
```php
<!-- Quick Security Actions -->
<div class="monitoring-table">
    <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">
        ⚡ Quick Security Actions
    </h3>
    <div style="padding: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
        <button onclick="sendTestAlert()" class="btn-modern btn-primary">
            <i class="fas fa-envelope"></i> Send Test Alert
        </button>
        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api_settings" class="btn-modern btn-secondary">
            <i class="fas fa-cog"></i> Configure Alerts
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/test_alerts.php" class="btn-modern btn-info">
            <i class="fas fa-vial"></i> Advanced Alert Testing
        </a>
    </div>
</div>

<script>
function sendTestAlert() {
    if (confirm('Send a test security alert to all configured recipients?')) {
        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        
        // Make AJAX request to test_alerts.php
        fetch('<?php echo $CFG->wwwroot; ?>/local/alx_report_api/test_alerts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=send_test&alert_type=security&severity=medium&sesskey=' + M.cfg.sesskey
        })
        .then(response => response.text())
        .then(data => {
            alert('✅ Test alert sent! Check your email inbox.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        })
        .catch(error => {
            alert('❌ Error sending test alert: ' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}
</script>
```

### Feature 3: Alert Recipients Display

**Location**: After Quick Actions panel

**Code to Add**:
```php
<!-- Alert Recipients -->
<?php if ($alert_emails || $alerting_enabled): ?>
<div class="monitoring-table">
    <h3 style="padding: 20px 20px 0 20px; margin: 0; font-size: 18px; font-weight: 600;">
        📧 Alert Recipients
    </h3>
    <div style="padding: 20px;">
        <?php if ($alert_emails): ?>
        <div style="margin-bottom: 15px;">
            <strong>Configured Email Recipients:</strong><br>
            <?php 
            $emails = array_filter(array_map('trim', explode(',', $alert_emails)));
            foreach ($emails as $email) {
                echo "<span class='badge badge-info' style='margin: 2px;'>{$email}</span> ";
            }
            ?>
        </div>
        <?php endif; ?>
        
        <div>
            <strong>Site Administrators (for critical alerts):</strong><br>
            <?php 
            $admins = get_admins();
            foreach ($admins as $admin) {
                echo "<span class='badge badge-success' style='margin: 2px;'>" . fullname($admin) . " ({$admin->email})</span> ";
            }
            ?>
        </div>
    </div>
</div>
<?php endif; ?>
```

---

## 📋 Summary of Recommendations

### ✅ Features to Add from test_alerts.php:

1. **Alert Configuration Status Panel** - Shows if alerts are enabled/disabled
2. **Quick Actions Panel** - Send test alert, configure settings
3. **Alert Recipients Display** - Shows who receives alerts

### ❌ Features NOT to Add:

1. **Alert Examples** - Not needed in monitoring dashboard
2. **Detailed Test Form** - Keep in separate test_alerts.php page
3. **SMS Configuration** - Too detailed for dashboard

### 🎯 Final Security Tab Structure:

```
Security & Alerts Tab
├── Metric Cards (4 cards)
│   ├── Active Tokens
│   ├── Rate Limit Violations
│   ├── Failed Auth Attempts
│   └── Security Status
│
├── Alert Configuration Status (NEW)
│   └── Table showing alert system status
│
├── Quick Security Actions (NEW)
│   ├── Send Test Alert button
│   ├── Configure Alerts link
│   └── Advanced Testing link
│
├── Alert Recipients (NEW)
│   ├── Configured email recipients
│   └── Site administrators
│
├── Recent Security Events
│   └── Table of recent events
│
└── Active System Alerts
    └── Table of active alerts
```

---

## 🚀 Next Steps

1. Review this analysis
2. Decide which features to add
3. Implement selected features in Security tab
4. Test alert functionality
5. Update documentation

---

**Recommendation**: Add all 3 new features (Alert Configuration Status, Quick Actions, Alert Recipients) to make the Security tab more comprehensive and useful.

