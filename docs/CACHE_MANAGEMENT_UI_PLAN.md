# Cache Management UI - Development Plan

## 🎯 Goal
Add a "Cache Management" section to `populate_reporting_table.php` that shows cache information and allows manual cache clearing.

---

## 📍 Location
**File:** `local/local_alx_report_api/populate_reporting_table.php`  
**Position:** Next to "Clear Reporting Table Data" section

---

## 🎨 UI Design (ASCII Diagram)

```
┌─────────────────────────────────────────────────────────────────┐
│  ALX Report API - Populate Reporting Table                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📊 Populate Reporting Table                                    │
│  ─────────────────────────────────────────────────────────────  │
│  Company: [Select Company ▼]                                    │
│  Batch Size: [100]                                              │
│  [Start Population]                                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🗑️ Clear Reporting Table Data                                  │
│  ─────────────────────────────────────────────────────────────  │
│  Company: [Select Company ▼]                                    │
│  [Clear Table Data]                                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐  ← NEW SECTION
│  💾 Cache Management                                            │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  Company: [Select Company ▼]                                    │
│                                                                  │
│  📈 Cache Statistics:                                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Total Cache Entries: 1,234                              │  │
│  │  Last Cache Update: 2025-10-16 14:30:25                  │  │
│  │  Cache Expires At: 2025-10-16 15:30:25 (in 45 minutes)  │  │
│  │  Cache Status: ✅ Active                                  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  [🗑️ Clear Cache Now]                                           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Data Flow Diagram

```
User Action Flow:
─────────────────

1. Page Load
   │
   ├─→ Get selected company
   │
   ├─→ Query cache table for company
   │   SELECT * FROM {local_alx_api_cache}
   │   WHERE companyid = ?
   │
   ├─→ Calculate statistics:
   │   • Total entries
   │   • Last update time (MAX(created_at))
   │   • Expiry time (created_at + TTL)
   │   • Cache status (active/expired)
   │
   └─→ Display cache info


2. User Clicks "Clear Cache Now"
   │
   ├─→ Show confirmation dialog
   │   "Are you sure you want to clear cache for [Company Name]?"
   │
   ├─→ User confirms
   │
   ├─→ Call: local_alx_report_api_cache_clear_company($companyid)
   │   DELETE FROM {local_alx_api_cache}
   │   WHERE companyid = ?
   │
   ├─→ Show success message
   │   "✅ Cache cleared successfully! X entries removed."
   │
   └─→ Refresh page to show updated stats
```

---

## 🗄️ Database Queries

### Query 1: Get Cache Statistics
```sql
SELECT 
    COUNT(*) as total_entries,
    MAX(created_at) as last_update,
    MIN(created_at) as oldest_entry
FROM {local_alx_api_cache}
WHERE companyid = :companyid
```

### Query 2: Clear Cache
```sql
DELETE FROM {local_alx_api_cache}
WHERE companyid = :companyid
```

### Query 3: Check Cache Status (from settings)
```sql
SELECT value 
FROM {local_alx_api_settings}
WHERE companyid = :companyid 
AND setting_name = 'enable_cache'
```

---

## 🔧 Implementation Steps

### Step 1: Add Cache Statistics Function (lib.php)
```php
/**
 * Get cache statistics for a company.
 *
 * @param int $companyid Company ID
 * @return array Cache statistics
 */
function local_alx_report_api_get_cache_stats($companyid) {
    global $DB;
    
    $sql = "
        SELECT 
            COUNT(*) as total_entries,
            MAX(created_at) as last_update,
            MIN(created_at) as oldest_entry
        FROM {local_alx_api_cache}
        WHERE companyid = :companyid";
    
    $stats = $DB->get_record_sql($sql, ['companyid' => $companyid]);
    
    // Get cache TTL (default 1 hour = 3600 seconds)
    $cache_ttl = 3600;
    
    // Calculate expiry time
    if ($stats && $stats->last_update) {
        $stats->expires_at = $stats->last_update + $cache_ttl;
        $stats->is_expired = (time() > $stats->expires_at);
        $stats->time_until_expiry = $stats->expires_at - time();
    } else {
        $stats->expires_at = null;
        $stats->is_expired = true;
        $stats->time_until_expiry = 0;
    }
    
    // Check if cache is enabled
    $stats->cache_enabled = local_alx_report_api_get_company_setting(
        $companyid, 
        'enable_cache', 
        1
    );
    
    return $stats;
}
```

### Step 2: Add Form Handler (populate_reporting_table.php)
```php
// Handle cache clear action
if ($action === 'clear_cache' && $confirm) {
    $companyid = required_param('companyid', PARAM_INT);
    
    // Get company name for message
    $company = $DB->get_record('company', ['id' => $companyid], 'name');
    
    // Clear cache
    $cleared = local_alx_report_api_cache_clear_company($companyid);
    
    // Show success message
    echo $OUTPUT->notification(
        "Cache cleared successfully for {$company->name}! {$cleared} entries removed.",
        'success'
    );
}
```

### Step 3: Add UI Section (populate_reporting_table.php)
```php
// Cache Management Section
echo '<div class="cache-management-section">';
echo '<h3><i class="fas fa-database"></i> Cache Management</h3>';

// Company selector
echo '<div class="form-group">';
echo '<label>Company:</label>';
echo '<select id="cache-company-select" class="form-control">';
echo '<option value="0">Select a company...</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . $company->name . '</option>';
}
echo '</select>';
echo '</div>';

// Cache statistics (loaded via JavaScript)
echo '<div id="cache-stats-container" style="display:none;">';
echo '<div class="cache-stats-box">';
echo '<h4>📈 Cache Statistics</h4>';
echo '<div id="cache-stats-content"></div>';
echo '</div>';

// Clear cache button
echo '<form method="post" id="clear-cache-form">';
echo '<input type="hidden" name="action" value="clear_cache">';
echo '<input type="hidden" name="companyid" id="cache-companyid">';
echo '<input type="hidden" name="confirm" value="1">';
echo '<button type="submit" class="btn btn-danger">';
echo '<i class="fas fa-trash"></i> Clear Cache Now';
echo '</button>';
echo '</form>';

echo '</div>'; // cache-stats-container
echo '</div>'; // cache-management-section
```

### Step 4: Add JavaScript (populate_reporting_table.php)
```javascript
// Load cache stats when company selected
document.getElementById('cache-company-select').addEventListener('change', function() {
    const companyid = this.value;
    
    if (companyid > 0) {
        // Show loading
        document.getElementById('cache-stats-container').style.display = 'block';
        document.getElementById('cache-stats-content').innerHTML = 'Loading...';
        
        // Fetch cache stats via AJAX
        fetch('ajax_get_cache_stats.php?companyid=' + companyid)
            .then(response => response.json())
            .then(data => {
                displayCacheStats(data);
            });
        
        // Set company ID for form
        document.getElementById('cache-companyid').value = companyid;
    } else {
        document.getElementById('cache-stats-container').style.display = 'none';
    }
});

// Display cache statistics
function displayCacheStats(stats) {
    let html = '<table class="cache-stats-table">';
    html += '<tr><td>Total Cache Entries:</td><td><strong>' + stats.total_entries + '</strong></td></tr>';
    
    if (stats.last_update) {
        html += '<tr><td>Last Cache Update:</td><td>' + formatDate(stats.last_update) + '</td></tr>';
        html += '<tr><td>Cache Expires At:</td><td>' + formatDate(stats.expires_at);
        
        if (stats.is_expired) {
            html += ' <span class="badge badge-danger">Expired</span>';
        } else {
            html += ' <span class="badge badge-success">Active (in ' + formatDuration(stats.time_until_expiry) + ')</span>';
        }
        html += '</td></tr>';
    } else {
        html += '<tr><td colspan="2"><em>No cache entries found</em></td></tr>';
    }
    
    html += '<tr><td>Cache Status:</td><td>';
    if (stats.cache_enabled) {
        html += '<span class="badge badge-success">✅ Enabled</span>';
    } else {
        html += '<span class="badge badge-warning">⚠️ Disabled</span>';
    }
    html += '</td></tr>';
    html += '</table>';
    
    document.getElementById('cache-stats-content').innerHTML = html;
}

// Confirm before clearing cache
document.getElementById('clear-cache-form').addEventListener('submit', function(e) {
    const companySelect = document.getElementById('cache-company-select');
    const companyName = companySelect.options[companySelect.selectedIndex].text;
    
    if (!confirm('Are you sure you want to clear cache for ' + companyName + '?')) {
        e.preventDefault();
    }
});
```

### Step 5: Add CSS Styling (populate-reporting-table.css)
```css
.cache-management-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cache-stats-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.cache-stats-table {
    width: 100%;
    border-collapse: collapse;
}

.cache-stats-table td {
    padding: 8px;
    border-bottom: 1px solid #dee2e6;
}

.cache-stats-table td:first-child {
    font-weight: 500;
    width: 200px;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-danger {
    background: #dc3545;
    color: white;
}

.badge-warning {
    background: #ffc107;
    color: #212529;
}
```

---

## 📁 Files to Modify/Create

### Files to Modify:
1. **populate_reporting_table.php** - Add cache management section
2. **lib.php** - Add `local_alx_report_api_get_cache_stats()` function
3. **populate-reporting-table.css** - Add cache section styling

### Files to Create:
4. **ajax_get_cache_stats.php** - AJAX endpoint for fetching cache stats

---

## 🔄 Alternative: No AJAX (Simpler)

If you prefer no AJAX, we can:
1. Load cache stats on page load for selected company
2. Use form submission to change company
3. Refresh page after clearing cache

**Simpler Flow:**
```
Page Load → Show company dropdown
User selects company → Form submits → Page reloads with cache stats
User clicks "Clear Cache" → Confirmation → Clear → Page reloads
```

---

## 🧪 Testing Checklist

- [ ] Select company → Cache stats display correctly
- [ ] No cache entries → Shows "No cache entries found"
- [ ] Cache enabled → Shows "✅ Enabled"
- [ ] Cache disabled → Shows "⚠️ Disabled"
- [ ] Cache expired → Shows "Expired" badge
- [ ] Cache active → Shows time until expiry
- [ ] Click "Clear Cache" → Confirmation dialog appears
- [ ] Confirm clear → Cache cleared successfully
- [ ] After clear → Stats update to show 0 entries
- [ ] Multiple companies → Each shows correct stats

---

## 📊 Expected Output Examples

### Example 1: Active Cache
```
Total Cache Entries: 1,234
Last Cache Update: 2025-10-16 14:30:25
Cache Expires At: 2025-10-16 15:30:25 ✅ Active (in 45 minutes)
Cache Status: ✅ Enabled
```

### Example 2: Expired Cache
```
Total Cache Entries: 856
Last Cache Update: 2025-10-16 12:00:00
Cache Expires At: 2025-10-16 13:00:00 ⚠️ Expired
Cache Status: ✅ Enabled
```

### Example 3: No Cache
```
No cache entries found
Cache Status: ✅ Enabled
```

### Example 4: Cache Disabled
```
Total Cache Entries: 0
Cache Status: ⚠️ Disabled
```

---

## 🎯 Summary

**What we're building:**
- New "Cache Management" section in populate_reporting_table.php
- Shows cache statistics per company
- Manual "Clear Cache Now" button
- Real-time cache status display

**Benefits:**
- Admins can see cache status at a glance
- Manual cache clearing without code
- Better cache visibility and control
- Matches existing UI style

**Complexity:** Medium
**Estimated Time:** 1-2 hours
**Files Modified:** 3-4 files
**New Features:** Cache stats display + manual clear button
