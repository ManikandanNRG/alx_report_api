# Final Archive List - After Review

## ✅ CONFIRMED AFTER USER REVIEW

You correctly identified that `ajax_stats.php` and `auto_sync_status.php` should be kept!

---

## 📋 FINAL LIST: 31 Files to Archive

### 🐛 Debug Files (3)
1. `debug_access_control.php`
2. `debug_manual_sync.php`
3. `debug_rate_limit.php`

### 🧪 Test Files (4)
4. `test_alerts.php`
5. `test_email_alert.php`
6. `test_unified_dashboard.php`
7. `cache_test_simple.php`

### 🔧 Fix Files (5)
8. `fix_alert_config.php`
9. `fix_missing_tables.php`
10. `fix_service.php`
11. `fix_test_issues.php`
12. `fix_webservice_access.php`

### 💾 Backup Files (5)
13. `control_center_backup_before_fix.php`
14. `control_center_backup.php`
15. `monitoring_dashboard_backup.php`
16. `settings_backup.php`
17. `sync_reporting_data_BACKUP.php`

### ✅ Verification Files (4)
18. `cache_verification.php`
19. `service_verification.php`
20. `verify_reporting_data.php`
21. `check_rate_limit.php`

### 📊 Unused Dashboard Files (3)
22. `advanced_monitoring.php`
23. `monitoring_dashboard_new.php`
24. `unified_monitoring_dashboard.php`

### 🎨 Unused CSS Files (3)
25. `advanced_monitoring.css`
26. `auto_sync_monitoring.css`
27. `control_center_fix.css`

### 🗄️ SQL Files (1)
28. `create_missing_tables.sql`

### 🗑️ Unused Utility Files (3)
29. `export_data.php`
30. `post_install_setup.php`
31. `trends_data.php`

---

## ✅ FILES TO KEEP (22 Production Files)

### Core Plugin Files (5)
- ✅ `version.php`
- ✅ `lib.php`
- ✅ `externallib.php`
- ✅ `settings.php`
- ✅ `README.md`

### Active Dashboards & Tools (6)
- ✅ `control_center.php` - Main dashboard
- ✅ `monitoring_dashboard.php` - System monitoring
- ✅ `company_settings.php` - Company configuration
- ✅ `system_health_monitoring.css` - Active CSS
- ✅ `ajax_stats.php` - **KEEP** - AJAX endpoint for real-time stats
- ✅ `auto_sync_status.php` - **KEEP** - Auto-sync monitoring page

### Data Management (2)
- ✅ `sync_reporting_data.php` - Manual sync tool
- ✅ `populate_reporting_table.php` - Initial population

### Database Files (6)
- ✅ `db/install.xml`
- ✅ `db/install.php`
- ✅ `db/upgrade.php`
- ✅ `db/uninstall.php`
- ✅ `db/services.php`
- ✅ `db/tasks.php`

### Task Classes (2)
- ✅ `classes/task/sync_reporting_data_task.php`
- ✅ `classes/task/check_alerts_task.php`

### Language Files (1)
- ✅ `lang/en/local_alx_report_api.php`

---

## 📄 Documentation Files to Move (3)

Move these from plugin root to `docs/` folder:
1. `CACHE_WORKFLOW_ANALYSIS.md`
2. `CONTROL_CENTER_GUIDE.md`
3. `GRADIENT_COLORS_GUIDE.md`

---

## 📊 SUMMARY

| Action | Count |
|--------|-------|
| Files to Archive | 31 |
| Files to Keep | 22 |
| Docs to Move | 3 |
| **Total Cleanup** | **34 files moved** |

---

## 🎯 WHY KEEP ajax_stats.php and auto_sync_status.php?

### ajax_stats.php
- **Purpose:** AJAX endpoint for real-time statistics
- **Used by:** Control Center for quick data refresh
- **Returns:** JSON data with current stats
- **Status:** Active utility file

### auto_sync_status.php
- **Purpose:** Standalone auto-sync intelligence monitoring page
- **Used by:** Direct access for detailed sync monitoring
- **Features:** Real-time sync status, charts, analytics
- **Status:** Active monitoring tool

---

## ✅ READY TO PROCEED

**Updated Archive Plan:**
- Archive: 31 files (down from 33)
- Keep: 22 files (up from 20)
- Move to docs: 3 files

**Confirmed by user:** ajax_stats.php and auto_sync_status.php are needed for Control Center functionality.

---

## 🚀 NEXT STEPS

1. **Confirm this final list** ✅
2. **Create archive folder structure**
3. **Move 31 files to archive**
4. **Move 3 docs to docs/ folder**
5. **Test Control Center and monitoring**
6. **Verify AJAX refresh works**

---

**Ready to execute cleanup? Just confirm and I'll proceed!** 🎯
