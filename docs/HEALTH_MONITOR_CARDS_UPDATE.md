# Health Monitor Cards Update

**Date:** 2025-10-14  
**Status:** ✅ IMPLEMENTED

---

## 🎯 What Changed

### **Old Cards:**
```
🔑 Active Tokens    ⚠️ Violations    🚫 Failed Auth    🛡️ Security Status
     5                   2                 0                Alert
```

### **New Cards:**
```
🔑 Active Tokens    ⚠️ Violations    💚 Token Health    🚨 Total Alerts
     5                   2                80%                 5
```

---

## 📊 New Metrics Explained

### **Card 3: Token Health Score** 💚

**What it shows:** Percentage of healthy tokens (not expired, not expiring in next 7 days)

**Calculation:**
```php
$healthy_tokens = tokens that are:
  - NOT expired (validuntil > now)
  - NOT expiring soon (validuntil > now + 7 days)

$token_health_score = ($healthy_tokens / $active_tokens) * 100
```

**Color Coding:**
- 🟢 Green (80-100%): Healthy
- 🟡 Yellow (50-79%): Warning - some tokens expiring soon
- 🔴 Red (0-49%): Critical - many tokens expired/expiring

**Example:**
- 5 total tokens
- 4 healthy (not expiring in 7 days)
- 1 expiring in 3 days
- Score: 80% (4/5)

**Actionable:** When score drops below 80%, renew tokens that are expiring soon.

---

### **Card 4: Total Alerts** 🚨

**What it shows:** Number of unresolved security alerts

**Calculation:**
```php
$total_alerts = COUNT(*) FROM local_alx_api_alerts WHERE resolved = 0
```

**Includes:**
- Rate limit violations
- Failed authentication attempts
- Suspicious activity
- Token expiration warnings
- Any other security alerts

**Color Coding:**
- 🟢 Green (0): No alerts
- 🟡 Yellow (1-5): Few alerts
- 🔴 Red (6+): Many alerts

**Actionable:** Click "Health Monitor" button to view and resolve alerts.

---

## 🔧 Technical Implementation

### **File Modified:** `monitoring_dashboard_new.php`

### **Changes Made:**

#### **1. Added Token Health Score Calculation (Line ~190-220)**
```php
// Calculate Token Health Score
$token_health_score = 0;
$token_health_text = 'N/A';
if ($active_tokens > 0) {
    $expiry_threshold = time() + (7 * 24 * 3600); // 7 days from now
    
    // Count healthy tokens (not expiring in next 7 days)
    $healthy_tokens = 0;
    foreach ($tokens as $token) {
        if (!$token->validuntil || $token->validuntil > $expiry_threshold) {
            $healthy_tokens++;
        }
    }
    
    $token_health_score = round(($healthy_tokens / $active_tokens) * 100);
    $token_health_text = $token_health_score . '%';
}
```

#### **2. Added Total Alerts Calculation (Line ~225-230)**
```php
// Calculate Total Alerts
$total_alerts = 0;
if ($DB->get_manager()->table_exists(TABLE_ALERTS)) {
    $total_alerts = $DB->count_records(TABLE_ALERTS, ['resolved' => 0]);
}
```

#### **3. Updated Card Display (Line ~650-670)**
```php
<!-- Card 3: Token Health Score -->
<div class="metric-card">
    <div class="metric-icon">💚</div>
    <div class="metric-value" style="color: [dynamic]"><?php echo $token_health_text; ?></div>
    <div class="metric-label">Token Health Score</div>
</div>

<!-- Card 4: Total Alerts -->
<div class="metric-card">
    <div class="metric-icon">🚨</div>
    <div class="metric-value" style="color: [dynamic]"><?php echo $total_alerts; ?></div>
    <div class="metric-label">Total Alerts</div>
</div>
```

---

## ✅ Benefits

### **Token Health Score:**
- ✅ Proactive monitoring (warns before tokens expire)
- ✅ Visual health indicator (color-coded)
- ✅ Prevents service interruptions
- ✅ Easy to understand (percentage)

### **Total Alerts:**
- ✅ Comprehensive security overview
- ✅ Single number for all alerts
- ✅ Shows unresolved issues
- ✅ Actionable (click to view details)

---

## 🧪 Testing

### **Test 1: Token Health Score**

**Scenario 1: All tokens healthy**
- 5 tokens, all valid for 30+ days
- Expected: 100% (green)

**Scenario 2: One token expiring soon**
- 5 tokens, 1 expires in 3 days
- Expected: 80% (green/yellow)

**Scenario 3: Multiple tokens expiring**
- 5 tokens, 3 expire in 5 days
- Expected: 40% (red)

### **Test 2: Total Alerts**

**Scenario 1: No alerts**
- No unresolved alerts
- Expected: 0 (green)

**Scenario 2: Few alerts**
- 2 rate limit violations (unresolved)
- Expected: 2 (yellow)

**Scenario 3: Many alerts**
- 10 unresolved alerts
- Expected: 10 (red)

---

## 📝 What Stayed the Same

- ✅ Card 1: Active Tokens (unchanged)
- ✅ Card 2: Rate Limit Violations (unchanged)
- ✅ All other functionality preserved
- ✅ No database changes needed

---

## 🎨 Visual Changes

### **Color Coding:**

**Token Health Score:**
- 80-100%: Green (#10b981)
- 50-79%: Yellow (#f59e0b)
- 0-49%: Red (#ef4444)

**Total Alerts:**
- 0: Green (#10b981)
- 1-5: Yellow (#f59e0b)
- 6+: Red (#ef4444)

---

## ⚠️ Important Notes

### **Token Health Score:**
- Shows "N/A" if no active tokens
- Checks 7-day expiry window (configurable)
- Only counts ALX Report API service tokens
- Updates in real-time

### **Total Alerts:**
- Counts only unresolved alerts
- Includes all alert types
- Click "Health Monitor" to see details
- Resolve alerts to reduce count

---

## 🔧 Troubleshooting

### **Issue: Token Health shows N/A**
**Check:**
1. Verify active tokens exist
2. Check external_tokens table
3. Verify service ID is correct

### **Issue: Total Alerts shows 0 but there are violations**
**Check:**
1. Verify alerts table exists
2. Check if alerts are marked as resolved
3. Verify alert_type values

### **Issue: Colors not showing**
**Check:**
1. Clear browser cache
2. Verify CSS is loaded
3. Check inline styles in HTML

---

## 📚 Related Documentation

- Security monitoring best practices
- Token management guide
- Alert resolution workflow

---

**Implementation Date:** 2025-10-14  
**Status:** ✅ COMPLETE - New cards provide better security insights

**Result:** Health Monitor now shows proactive metrics (Token Health) and comprehensive overview (Total Alerts)!
