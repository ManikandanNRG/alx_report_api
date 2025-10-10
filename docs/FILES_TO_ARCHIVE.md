# Files to Archive - Cleanup List

## 📋 SUMMARY
**Total Files to Archive:** 29 files  
**Categories:** Debug (3), Test (4), Fix (5), Backup (3), Verification (4), Unused Dashboards (3), Unused CSS (3), Documentation (2), SQL (1), Other (1)

---

## 🗂️ FILES TO MOVE TO ARCHIVE FOLDER

### 🐛 DEBUG FILES (3 files)
These were used for debugging during development:

1. `debug_access_control.php` - Debug access control issues
2. `debug_manual_sync.php` - Debug manual sync functionality
3. `debug_rate_limit.php` - Debug rate limiting

**Reason:** Only needed during development, not for production

---

### 🧪 TEST FILES (4 files)
These were used for testing features:

4. `test_alerts.php` - Test alert system
5. `test_email_alert.php` - Test email alert functionality
6. `test_unified_dashboard.php` - Test unified dashboard
7. `cache_test_simple.php` - Simple cache testing

**Reason:** Testing files should not be in production code

---

### 🔧 FIX FILES (5 files)
These were one-time fix scripts:

8. `fix_alert_config.php` - Fix alert configuration
9. `fix_missing_tables.php` - Fix missing database tables
10. `fix_service.php` - Fix web service configuration
11. `fix_test_issues.php` - Fix test-related issues
12. `fix_webservice_access.php` - Fix webservice access issues

**Reason:** One-time fixes, not needed after initial setup

---

### 💾 BACKUP FILES (3 files)
Old backup versions of files:

13. `control_center_backup_before_fix.php` - Backup before fixes
14. `control_center_backup.php` - Another backup
15. `monitoring_dashboard_backup.php` - Dashboard backup
16. `settings_backup.php` - Settings backup
17. `sync_reporting_data_BACKUP.php` - Sync script backup

**Reason:** Should use version control (git) instead of backup files

---

### ✅ VERIFICATION FILES (4 files)
Scripts used to verify functionality:

18. `cache_verification.php` - Verify cache is working
19. `service_verification.php` - Verify web service setup
20. `verify_reporting_data.php` - Verify reporting table data
21. `check_rate_limit.php` - Check rate limit status

**Reason:** Verification scripts for development/testing only

---

### 📊 UNUSED/DUPLICATE DASHBOARD FILES (3 files)
Alternative dashboard versions not being used:

22. `advanced_monitoring.php` - Alternative monitoring dashboard
23. `monitoring_dashboard_new.php` - New version (if not using)
24. `unified_monitoring_dashboard.php` - Unified dashboard attempt

**Decision Needed:** Keep only ONE monitoring dashboard
- If using `monitoring_dashboard.php` → archive the others
- If using `unified_monitoring_dashboard.php` → archive the others

---

### 🎨 UNUSED CSS FILES (3 files)
CSS files that may not be in use:

25. `advanced_monitoring.css` - For advanced_monitoring.php
26. `auto_sync_monitoring.css` - For auto_sync_status.php (if not used)
27. `control_center_fix.css` - Temporary fix CSS
28. `system_health_monitoring.css` - For monitoring dashboard

**Decision Needed:** Check which CSS files are actually loaded
- Keep only CSS files that are actively used
- Archive unused ones

---

### 📄 DOCUMENTATION FILES (2 files)
Documentation that could be moved to docs folder:

29. `CACHE_WORKFLOW_ANALYSIS.md` - Cache workflow documentation
30. `CONTROL_CENTER_GUIDE.md` - Control center guide
31. `GRADIENT_COLORS_GUIDE.md` - Gradient colors guide

**Recommendation:** Move to `docs/` folder instead of archive

---

### 🗄️ SQL FILES (1 file)
SQL scripts for manual database fixes:

32. `create_missing_tables.sql` - SQL to create missing tables

**Reason:** Database schema should be in install.xml only

---

### 🤔 QUESTIONABLE FILES (Need Review)

33. `ajax_stats.php` - AJAX endpoint for statistics
   - **Keep if:** Used by dashboards for real-time data
   - **Archive if:** Not referenced anywhere

34. `auto_sync_status.php` - Auto sync status page
   - **Keep if:** Actively used for monitoring
   - **Archive if:** Functionality merged into control center

35. `export_data.php` - Data export functionality
   - **Keep if:** Users need to export data
   - **Archive if:** Not implemented/used

36. `post_install_setup.php` - Post-installation setup
   - **Keep if:** Runs after plugin installation
   - **Archive if:** Functionality moved to install.php

37. `trends_data.php` - Trends data endpoint
   - **Keep if:** Used by monitoring dashboard
   - **Archive if:** Not referenced anywhere

---

## ✅ CORE FILES TO KEEP (Production Files)

### Essential Plugin Files
- ✅ `version.php` - Plugin version info
- ✅ `lib.php` - Core library functions
- ✅ `externallib.php` - API endpoint
- ✅ `settings.php` - Admin settings
- ✅ `README.md` - Plugin documentation

### Main Functionality
- ✅ `control_center.php` - Main dashboard
- ✅ `monitoring_dashboard.php` - System monitoring (choose one)
- ✅ `company_settings.php` - Company configuration
- ✅ `sync_reporting_data.php` - Manual sync tool
- ✅ `populate_reporting_table.php` - Initial data population

### Database Files (db/ folder)
- ✅ `db/install.xml` - Database schema
- ✅ `db/install.php` - Post-install hooks
- ✅ `db/upgrade.php` - Database upgrades
- ✅ `db/uninstall.php` - Cleanup on uninstall
- ✅ `db/services.php` - Web service definitions
- ✅ `db/tasks.php` - Scheduled tasks

### Classes (classes/ folder)
- ✅ `classes/task/sync_reporting_data_task.php` - Sync cron job
- ✅ `classes/task/check_alerts_task.php` - Alert checking

### Language Files (lang/en/ folder)
- ✅ `lang/en/local_alx_report_api.php` - English strings

---

## 📦 RECOMMENDED ARCHIVE STRUCTURE

```
local/local_alx_report_api/
├── archive/
│   ├── debug/
│   │   ├── debug_access_control.php
│   │   ├── debug_manual_sync.php
│   │   └── debug_rate_limit.php
│   ├── test/
│   │   ├── test_alerts.php
│   │   ├── test_email_alert.php
│   │   ├── test_unified_dashboard.php
│   │   └── cache_test_simple.php
│   ├── fix/
│   │   ├── fix_alert_config.php
│   │   ├── fix_missing_tables.php
│   │   ├── fix_service.php
│   │   ├── fix_test_issues.php
│   │   └── fix_webservice_access.php
│   ├── backup/
│   │   ├── control_center_backup_before_fix.php
│   │   ├── control_center_backup.php
│   │   ├── monitoring_dashboard_backup.php
│   │   ├── settings_backup.php
│   │   └── sync_reporting_data_BACKUP.php
│   ├── verification/
│   │   ├── cache_verification.php
│   │   ├── service_verification.php
│   │   ├── verify_reporting_data.php
│   │   └── check_rate_limit.php
│   ├── unused_dashboards/
│   │   ├── advanced_monitoring.php
│   │   ├── advanced_monitoring.css
│   │   ├── monitoring_dashboard_new.php (if not using)
│   │   └── unified_monitoring_dashboard.php (if not using)
│   ├── unused_css/
│   │   ├── auto_sync_monitoring.css (if not using)
│   │   ├── control_center_fix.css
│   │   └── system_health_monitoring.css (if not using)
│   └── sql/
│       └── create_missing_tables.sql
└── docs/
    ├── CACHE_WORKFLOW_ANALYSIS.md
    ├── CONTROL_CENTER_GUIDE.md
    └── GRADIENT_COLORS_GUIDE.md
```

---

## ⚠️ BEFORE ARCHIVING - CHECKLIST

### 1. Check File References
- [ ] Search codebase for references to each file
- [ ] Ensure no active code includes/requires these files
- [ ] Check if any dashboards link to these files

### 2. Test After Moving
- [ ] Test Control Center loads
- [ ] Test Monitoring Dashboard loads
- [ ] Test API endpoint works
- [ ] Test manual sync works
- [ ] Check for any broken links

### 3. Backup Before Moving
- [ ] Create full backup of plugin folder
- [ ] Note current state in git (if using)
- [ ] Document what was moved and when

---

## 🎯 RECOMMENDED ACTION PLAN

### Phase 1: Safe to Archive (Definitely Move)
**Files:** debug_*, test_*, fix_*, *_backup*, *_BACKUP*  
**Count:** 15 files  
**Risk:** Very Low

### Phase 2: Probably Archive (Likely Not Used)
**Files:** verification files, unused dashboards, create_missing_tables.sql  
**Count:** 8 files  
**Risk:** Low (but verify first)

### Phase 3: Move to Docs (Not Archive)
**Files:** *.md documentation files  
**Count:** 3 files  
**Risk:** None

### Phase 4: Review Carefully (Need Decision)
**Files:** ajax_stats.php, auto_sync_status.php, export_data.php, post_install_setup.php, trends_data.php  
**Count:** 5 files  
**Risk:** Medium (might be in use)

---

## 🔍 HOW TO CHECK IF FILE IS USED

### Method 1: Search in Code
```bash
# Search for file references
grep -r "debug_access_control" local/local_alx_report_api/
grep -r "test_alerts" local/local_alx_report_api/
```

### Method 2: Check Web Server Logs
- Look for recent access to these files
- If not accessed in 30+ days, likely safe to archive

### Method 3: Check Database
- Some files might be referenced in database settings
- Check mdl_config_plugins table

---

## ✅ FINAL RECOMMENDATION

### Definitely Archive (29 files)
All debug, test, fix, backup, and verification files

### Move to Docs (3 files)
All .md documentation files

### Review Before Decision (5 files)
ajax_stats.php, auto_sync_status.php, export_data.php, post_install_setup.php, trends_data.php

### Total Cleanup
- **Remove from production:** 29 files
- **Move to docs:** 3 files
- **Review:** 5 files
- **Result:** Cleaner, more maintainable codebase

---

**Ready to proceed? Confirm which files to archive and I'll help you move them!**
