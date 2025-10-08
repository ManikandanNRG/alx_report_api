# System Configuration Tab - Editable Form Implementation Plan

**Date:** October 8, 2025  
**Request:** Make System Configuration tab editable (like Company Management tab)  
**Status:** ✅ **EASILY DOABLE** - Same pattern already exists!

---

## 🎯 **You're Right - It's Easy!**

After reviewing your existing code, I see you **already have the exact same pattern** in:
- ✅ **Company Management tab** - Full form with dropdowns, checkboxes, save button
- ✅ **Data Management tab** - Action buttons

So implementing **Option 1 (Inline Editable Form)** is **straightforward** - just follow the same pattern!

---

## 📋 **Implementation Plan**

### **Step 1: Add Form Processing at Top (PHP)**
**Location:** Before the settings-tab div  
**Pattern:** Same as Company Management tab (lines 1526-1610)

```php
<?php
// Handle system configuration form submission
$config_action = optional_param('config_action', '', PARAM_ALPHA);

if ($config_action === 'save_config' && confirm_sesskey()) {
    $success_count = 0;
    $errors = [];
    
    try {
        // Save rate limit
        $rate_limit = optional_param('rate_limit', 100, PARAM_INT);
        if ($rate_limit >= 1 && $rate_limit <= 10000) {
            set_config('rate_limit', $rate_limit, 'local_alx_report_api');
            $success_count++;
        } else {
            $errors[] = 'Rate limit must be between 1 and 10000';
        }
        
        // Save max records
        $max_records = optional_param('max_records', 1000, PARAM_INT);
        if ($max_records >= 100 && $max_records <= 10000) {
            set_config('max_records', $max_records, 'local_alx_report_api');
            $success_count++;
        } else {
            $errors[] = 'Max records must be between 100 and 10000';
        }
        
        // Save allow GET method
        $allow_get = optional_param('allow_get_method', 0, PARAM_INT);
        set_config('allow_get_method', $allow_get, 'local_alx_report_api');
        $success_count++;
        
        // Save alert settings
        $enable_alerting = optional_param('enable_alerting', 0, PARAM_INT);
        set_config('enable_alerting', $enable_alerting, 'local_alx_report_api');
        $success_count++;
        
        $enable_email_alerts = optional_param('enable_email_alerts', 0, PARAM_INT);
        set_config('enable_email_alerts', $enable_email_alerts, 'local_alx_report_api');
        $success_count++;
        
        $alert_threshold = optional_param('alert_threshold', 'medium', PARAM_ALPHA);
        set_config('alert_threshold', $alert_threshold, 'local_alx_report_api');
        $success_count++;
        
        $alert_emails = optional_param('alert_emails', '', PARAM_TEXT);
        set_config('alert_emails', $alert_emails, 'local_alx_report_api');
        $success_count++;
        
        // Save cache TTL
        $cache_ttl = optional_param('cache_ttl', 3600, PARAM_INT);
        if ($cache_ttl >= 300 && $cache_ttl <= 86400) {
            set_config('cache_ttl', $cache_ttl, 'local_alx_report_api');
            $success_count++;
        } else {
            $errors[] = 'Cache TTL must be between 300 and 86400 seconds';
        }
        
        if (empty($errors)) {
            echo '<div class="alert alert-success">✅ Configuration saved successfully! (' . $success_count . ' settings updated)</div>';
        } else {
            echo '<div class="alert alert-warning">⚠️ Some settings saved with warnings: ' . implode(', ', $errors) . '</div>';
        }
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">❌ Error saving configuration: ' . $e->getMessage() . '</div>';
    }
}

// Reload settings after save
$rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
$max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
$allow_get = get_config('local_alx_report_api', 'allow_get_method');
$enable_alerting = get_config('local_alx_report_api', 'enable_alerting');
$enable_email_alerts = get_config('local_alx_report_api', 'enable_email_alerts');
$alert_threshold = get_config('local_alx_report_api', 'alert_threshold') ?: 'medium';
$alert_emails = get_config('local_alx_report_api', 'alert_emails');
$cache_ttl = get_config('local_alx_report_api', 'cache_ttl') ?: 3600;
?>
```

---

### **Step 2: Create HTML Form (Same Pattern as Company Management)**

```html
<div id="settings-tab" class="tab-content">
    <div class="dashboard-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-cog"></i>
                System Configuration
            </h3>
            <p class="card-subtitle">Configure global plugin settings</p>
        </div>
        <div class="card-body">
            
            <!-- Configuration Form -->
            <form method="post" action="" style="max-width: 900px;">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="config_action" value="save_config">
                <input type="hidden" name="tab" value="settings">
                
                <!-- API Configuration Section -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 20px 0; color: white; font-size: 18px;">
                        <i class="fas fa-plug"></i> API Configuration
                    </h4>
                    
                    <!-- Rate Limit -->
                    <div style="margin-bottom: 20px;">
                        <label for="rate_limit" style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Global Rate Limit (requests/day per company)
                        </label>
                        <input type="number" 
                               id="rate_limit" 
                               name="rate_limit" 
                               value="<?php echo $rate_limit; ?>" 
                               min="1" 
                               max="10000"
                               style="width: 100%; padding: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background: rgba(255,255,255,0.9); color: #333;">
                        <small style="display: block; margin-top: 4px; opacity: 0.9; font-size: 13px;">
                            Recommended: 100-1000. Higher values allow more API calls per day.
                        </small>
                    </div>
                    
                    <!-- Max Records -->
                    <div style="margin-bottom: 20px;">
                        <label for="max_records" style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Max Records per Request
                        </label>
                        <input type="number" 
                               id="max_records" 
                               name="max_records" 
                               value="<?php echo $max_records; ?>" 
                               min="100" 
                               max="10000"
                               style="width: 100%; padding: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background: rgba(255,255,255,0.9); color: #333;">
                        <small style="display: block; margin-top: 4px; opacity: 0.9; font-size: 13px;">
                            Recommended: 1000. Lower values improve response time but require more API calls.
                        </small>
                    </div>
                    
                    <!-- Allow GET Method -->
                    <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="allow_get_method" 
                                   value="1" 
                                   <?php echo $allow_get ? 'checked' : ''; ?>
                                   style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer;">
                            <div>
                                <div style="font-weight: 600; font-size: 15px;">Allow GET Method</div>
                                <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                                    ⚠️ Development/Testing Only - Use POST in production for security
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Email Alerts Section -->
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 20px 0; color: white; font-size: 18px;">
                        <i class="fas fa-bell"></i> Email Alerts Configuration
                    </h4>
                    
                    <!-- Enable Alerting -->
                    <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="enable_alerting" 
                                   value="1" 
                                   <?php echo $enable_alerting ? 'checked' : ''; ?>
                                   style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer;">
                            <div>
                                <div style="font-weight: 600; font-size: 15px;">Enable Alert System</div>
                                <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                                    Master switch for all alerts (rate limits, security, performance)
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Enable Email Alerts -->
                    <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="enable_email_alerts" 
                                   value="1" 
                                   <?php echo $enable_email_alerts ? 'checked' : ''; ?>
                                   style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer;">
                            <div>
                                <div style="font-weight: 600; font-size: 15px;">Enable Email Alerts</div>
                                <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                                    Send alerts via email using Moodle's email system
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Alert Threshold -->
                    <div style="margin-bottom: 20px;">
                        <label for="alert_threshold" style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Alert Severity Threshold
                        </label>
                        <select id="alert_threshold" 
                                name="alert_threshold"
                                style="width: 100%; padding: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background: rgba(255,255,255,0.9); color: #333;">
                            <option value="low" <?php echo $alert_threshold === 'low' ? 'selected' : ''; ?>>Low - Send all alerts</option>
                            <option value="medium" <?php echo $alert_threshold === 'medium' ? 'selected' : ''; ?>>Medium - Send medium, high, and critical</option>
                            <option value="high" <?php echo $alert_threshold === 'high' ? 'selected' : ''; ?>>High - Send only high and critical</option>
                            <option value="critical" <?php echo $alert_threshold === 'critical' ? 'selected' : ''; ?>>Critical - Send only critical alerts</option>
                        </select>
                        <small style="display: block; margin-top: 4px; opacity: 0.9; font-size: 13px;">
                            Minimum severity level for sending alerts
                        </small>
                    </div>
                    
                    <!-- Alert Recipients -->
                    <div>
                        <label for="alert_emails" style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Alert Email Recipients
                        </label>
                        <textarea id="alert_emails" 
                                  name="alert_emails" 
                                  rows="3"
                                  placeholder="email1@example.com, email2@example.com"
                                  style="width: 100%; padding: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 14px; background: rgba(255,255,255,0.9); color: #333; font-family: monospace;"><?php echo htmlspecialchars($alert_emails); ?></textarea>
                        <small style="display: block; margin-top: 4px; opacity: 0.9; font-size: 13px;">
                            Comma-separated list of email addresses. Only these emails will receive alerts.
                        </small>
                    </div>
                </div>
                
                <!-- Cache Configuration Section -->
                <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <h4 style="margin: 0 0 20px 0; color: white; font-size: 18px;">
                        <i class="fas fa-bolt"></i> Cache Configuration
                    </h4>
                    
                    <!-- Cache TTL -->
                    <div>
                        <label for="cache_ttl" style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Cache Time-To-Live (seconds)
                        </label>
                        <input type="number" 
                               id="cache_ttl" 
                               name="cache_ttl" 
                               value="<?php echo $cache_ttl; ?>" 
                               min="300" 
                               max="86400"
                               style="width: 100%; padding: 12px; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background: rgba(255,255,255,0.9); color: #333;">
                        <small style="display: block; margin-top: 4px; opacity: 0.9; font-size: 13px;">
                            Recommended: 3600 (1 hour). Range: 300 (5 min) to 86400 (24 hours)
                        </small>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div style="text-align: center; padding: 20px 0;">
                    <button type="submit" 
                            class="btn-modern btn-primary" 
                            style="background: #2563eb; color: white; padding: 16px 48px; font-size: 18px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.3); transition: all 0.3s;">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                </div>
            </form>
            
        </div>
    </div>
</div>
```

---

## 🎨 **Visual Design**

### **Form Layout:**
```
┌─────────────────────────────────────────────────────┐
│  System Configuration                                │
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌────────────────────────────────────────────┐    │
│  │ 🔌 API Configuration (Purple Gradient)     │    │
│  │                                             │    │
│  │ Global Rate Limit:                          │    │
│  │ [100                    ] requests/day      │    │
│  │                                             │    │
│  │ Max Records per Request:                    │    │
│  │ [1000                   ] records           │    │
│  │                                             │    │
│  │ [✓] Allow GET Method (Dev Only)            │    │
│  └────────────────────────────────────────────┘    │
│                                                      │
│  ┌────────────────────────────────────────────┐    │
│  │ 🔔 Email Alerts (Pink Gradient)            │    │
│  │                                             │    │
│  │ [✓] Enable Alert System                    │    │
│  │ [✓] Enable Email Alerts                    │    │
│  │                                             │    │
│  │ Alert Threshold:                            │    │
│  │ [Medium ▼]                                  │    │
│  │                                             │    │
│  │ Recipients:                                 │    │
│  │ [email1@test.com, email2@test.com...]      │    │
│  └────────────────────────────────────────────┘    │
│                                                      │
│  ┌────────────────────────────────────────────┐    │
│  │ ⚡ Cache Configuration (Green Gradient)     │    │
│  │                                             │    │
│  │ Cache TTL (seconds):                        │    │
│  │ [3600                   ]                   │    │
│  └────────────────────────────────────────────┘    │
│                                                      │
│            [💾 Save Configuration]                  │
│                                                      │
└─────────────────────────────────────────────────────┘
```

---

## ✅ **Implementation Checklist**

### **Phase 1: Form Processing (PHP)**
- [ ] Add form handling code at top of settings-tab section
- [ ] Use `optional_param()` to get form values
- [ ] Validate inputs (ranges, formats)
- [ ] Use `set_config()` to save settings
- [ ] Show success/error messages
- [ ] Reload settings after save

### **Phase 2: HTML Form**
- [ ] Create form with sesskey and hidden fields
- [ ] Add API Configuration section (purple gradient)
  - [ ] Rate limit input (number, 1-10000)
  - [ ] Max records input (number, 100-10000)
  - [ ] Allow GET checkbox
- [ ] Add Email Alerts section (pink gradient)
  - [ ] Enable alerting checkbox
  - [ ] Enable email alerts checkbox
  - [ ] Alert threshold dropdown
  - [ ] Recipients textarea
- [ ] Add Cache Configuration section (green gradient)
  - [ ] Cache TTL input (number, 300-86400)
- [ ] Add Save button

### **Phase 3: Styling**
- [ ] Use gradient backgrounds (same as current display)
- [ ] Style inputs with proper padding, borders
- [ ] Add helpful descriptions under each field
- [ ] Make form responsive
- [ ] Add hover effects on save button

### **Phase 4: Testing**
- [ ] Test form submission
- [ ] Verify settings are saved correctly
- [ ] Test validation (min/max values)
- [ ] Test error handling
- [ ] Test success messages
- [ ] Verify settings persist after save

---

## 🔧 **Key Implementation Details**

### **1. Form Processing Pattern (Same as Company Management)**
```php
$config_action = optional_param('config_action', '', PARAM_ALPHA);
if ($config_action === 'save_config' && confirm_sesskey()) {
    // Process form
    // Save settings
    // Show messages
}
```

### **2. Input Validation**
```php
// Validate range
if ($rate_limit >= 1 && $rate_limit <= 10000) {
    set_config('rate_limit', $rate_limit, 'local_alx_report_api');
} else {
    $errors[] = 'Rate limit must be between 1 and 10000';
}
```

### **3. Checkbox Handling**
```php
// Checkbox returns 1 if checked, 0 if unchecked
$allow_get = optional_param('allow_get_method', 0, PARAM_INT);
set_config('allow_get_method', $allow_get, 'local_alx_report_api');
```

### **4. Textarea Handling**
```php
// Get textarea value
$alert_emails = optional_param('alert_emails', '', PARAM_TEXT);
set_config('alert_emails', $alert_emails, 'local_alx_report_api');
```

---

## 🎯 **Benefits of This Approach**

1. ✅ **Consistent with existing code** - Same pattern as Company Management
2. ✅ **Easy to implement** - Copy-paste-modify existing pattern
3. ✅ **User-friendly** - All settings in one place
4. ✅ **Secure** - Uses sesskey, validation, Moodle's set_config()
5. ✅ **Beautiful design** - Gradient cards, clear labels
6. ✅ **Helpful** - Descriptions and recommendations for each setting

---

## 📝 **Summary**

**You're absolutely right!** Since you already have the form pattern in Company Management tab, implementing an editable System Configuration form is **straightforward**:

1. Copy the form processing pattern from Company Management
2. Create HTML form with inputs for each setting
3. Use same styling (gradient cards)
4. Add save button
5. Done!

**Estimated Time:** 30-45 minutes  
**Complexity:** Low (pattern already exists)  
**Risk:** Low (same approach as existing working code)

---

**Ready to implement? Just say the word!** 🚀
