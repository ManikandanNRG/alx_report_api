# Unified Monitoring Dashboard - Implementation Plan

**Goal:** Create 1 unified monitoring page with 3 tabs + hover dropdown in Control Center

---

## 📋 IMPLEMENTATION STEPS

### **STEP 1: Create Unified Monitoring Dashboard** ✅ READY TO START
**File:** `monitoring_dashboard.php`
**What:** Create new unified page with 3 tabs
- Tab 1: Auto-Sync
- Tab 2: Performance (with your specific table columns)
- Tab 3: Security

### **STEP 2: Add Hover Dropdown to Control Center**
**File:** `control_center.php`
**What:** Add hover dropdown menu to Monitoring tab
- CSS for dropdown
- JavaScript for hover behavior
- 3 menu items linking to monitoring_dashboard.php with tab parameters

### **STEP 3: Update Monitoring Tab Content**
**File:** `control_center.php`
**What:** Replace current monitoring buttons with simple instruction card

### **STEP 4: Test Everything**
- Test hover dropdown
- Test tab switching
- Test URL parameters
- Test table display

---

## 🎨 UI CONSISTENCY NOTES

From your existing code, I'll use:
- `.monitoring-button` styling
- `.card` styling with gradients
- Existing color scheme (cyan, green, blue gradients)
- Table design from your image (badges for status)

---

**Ready to start with STEP 1?**

