# Manual Database Cleanup Guide

If you need to manually clean up the database tables before reinstalling the plugin, follow these steps.

## Option 1: Using Moodle Admin Interface

1. Go to: **Site administration → Plugins → Plugins overview**
2. Find "ALX Report API" plugin
3. Click **Uninstall**
4. Confirm uninstallation
5. Tables should be automatically removed

## Option 2: Manual SQL Cleanup (If uninstall fails)

**⚠️ WARNING: Only use this if normal uninstall doesn't work!**

### Step 1: Backup your database first!

```bash
mysqldump -u [username] -p [database_name] > backup_before_cleanup.sql
```

### Step 2: Run these SQL commands

Connect to your Moodle database and run:

```sql
-- Drop all ALX Report API tables
DROP TABLE IF EXISTS mdl_local_alx_api_alerts;
DROP TABLE IF EXISTS mdl_local_alx_api_cache;
DROP TABLE IF EXISTS mdl_local_alx_api_sync_status;
DROP TABLE IF EXISTS mdl_local_alx_api_reporting;
DROP TABLE IF EXISTS mdl_local_alx_api_settings;
DROP TABLE IF EXISTS mdl_local_alx_api_logs;

-- Clean up config settings
DELETE FROM mdl_config_plugins WHERE plugin = 'local_alx_report_api';

-- Clean up capabilities
DELETE FROM mdl_capabilities WHERE component = 'local_alx_report_api';

-- Clean up events
DELETE FROM mdl_events_queue_handlers WHERE component = 'local_alx_report_api';
```

### Step 3: Clear Moodle caches

```bash
# Via command line
php admin/cli/purge_caches.php

# Or via web interface
# Go to: Site administration → Development → Purge all caches
```

### Step 4: Remove plugin files

```bash
# SSH into your server
cd /path/to/moodle/local/
rm -rf alx_report_api
```

### Step 5: Reinstall

Now you can install the plugin fresh via ZIP upload.

## Option 3: Quick Cleanup Script (PostgreSQL)

If using PostgreSQL:

```sql
-- Drop all ALX Report API tables
DROP TABLE IF EXISTS mdl_local_alx_api_alerts CASCADE;
DROP TABLE IF EXISTS mdl_local_alx_api_cache CASCADE;
DROP TABLE IF EXISTS mdl_local_alx_api_sync_status CASCADE;
DROP TABLE IF EXISTS mdl_local_alx_api_reporting CASCADE;
DROP TABLE IF EXISTS mdl_local_alx_api_settings CASCADE;
DROP TABLE IF EXISTS mdl_local_alx_api_logs CASCADE;

-- Clean up config
DELETE FROM mdl_config_plugins WHERE plugin = 'local_alx_report_api';
```

## Verification

After cleanup, verify tables are gone:

```sql
-- MySQL
SHOW TABLES LIKE 'mdl_local_alx_api%';

-- PostgreSQL
SELECT tablename FROM pg_tables WHERE tablename LIKE 'mdl_local_alx_api%';
```

Should return **0 rows**.

## Common Issues

### Issue: "Table already exists" error during install

**Cause:** Previous installation wasn't fully removed

**Solution:** Use Option 2 (Manual SQL Cleanup) above

### Issue: Uninstall button doesn't work

**Cause:** Plugin files were deleted before uninstalling

**Solution:**
1. Restore plugin files from backup or re-upload
2. Uninstall properly via Moodle interface
3. Then delete files

### Issue: Permission denied when deleting files

**Cause:** Web server owns the files

**Solution:**
```bash
# Change ownership first
sudo chown -R your_user:your_group /path/to/moodle/local/alx_report_api
# Then delete
rm -rf /path/to/moodle/local/alx_report_api
```

## Prevention

To avoid these issues in the future:

1. ✅ **Always uninstall via Moodle interface first**
2. ✅ **Never delete plugin files manually before uninstalling**
3. ✅ **Backup database before major changes**
4. ✅ **Test on development server first**

## Support

If you still have issues after following this guide, check:
- Moodle error logs: `/path/to/moodle/moodledata/`
- PHP error logs: Usually `/var/log/apache2/error.log` or `/var/log/php-fpm/error.log`
- Database error logs

