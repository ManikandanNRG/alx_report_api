-- ALX Report API - Rate Limit SQL Queries
-- Run these queries in your Moodle database to check rate limit status

-- 1. Check current rate limit settings
SELECT 
    'rate_limit' as setting_name,
    value as setting_value
FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' AND name = 'rate_limit'
UNION ALL
SELECT 
    'max_records' as setting_name,
    value as setting_value
FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' AND name = 'max_records'
UNION ALL
SELECT 
    'allow_get_method' as setting_name,
    value as setting_value
FROM mdl_config_plugins 
WHERE plugin = 'local_alx_report_api' AND name = 'allow_get_method';

-- 2. Calculate today's timestamp (adjust timezone as needed)
-- Today at 00:00:00 UTC - replace with your timezone calculation if needed
SELECT 
    UNIX_TIMESTAMP(CURDATE()) as today_start_timestamp,
    FROM_UNIXTIME(UNIX_TIMESTAMP(CURDATE())) as today_start_readable,
    UNIX_TIMESTAMP(NOW()) as current_timestamp,
    FROM_UNIXTIME(UNIX_TIMESTAMP(NOW())) as current_time_readable;

-- 3. Check today's API requests by user (replace UNIX_TIMESTAMP(CURDATE()) with actual timestamp if needed)
SELECT 
    l.userid,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) as fullname,
    COUNT(*) as requests_today,
    MIN(l.timecreated) as first_request_timestamp,
    FROM_UNIXTIME(MIN(l.timecreated)) as first_request_time,
    MAX(l.timecreated) as last_request_timestamp,
    FROM_UNIXTIME(MAX(l.timecreated)) as last_request_time
FROM mdl_local_alx_api_logs l
LEFT JOIN mdl_user u ON u.id = l.userid
WHERE l.timecreated >= UNIX_TIMESTAMP(CURDATE())
GROUP BY l.userid, u.username, u.firstname, u.lastname
ORDER BY requests_today DESC, last_request_timestamp DESC;

-- 4. Show recent API logs (last 20 requests)
SELECT 
    FROM_UNIXTIME(l.timecreated) as request_time,
    l.userid,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) as fullname,
    l.companyid,
    l.endpoint,
    l.ipaddress,
    LEFT(l.useragent, 50) as user_agent_short
FROM mdl_local_alx_api_logs l
LEFT JOIN mdl_user u ON u.id = l.userid
ORDER BY l.timecreated DESC
LIMIT 20;

-- 5. Check active tokens for ALX Report API service
SELECT 
    CONCAT(LEFT(t.token, 8), '...') as token_preview,
    t.userid,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) as fullname,
    FROM_UNIXTIME(t.timecreated) as token_created,
    CASE 
        WHEN t.validuntil IS NULL THEN 'Never expires'
        WHEN t.validuntil > UNIX_TIMESTAMP(NOW()) THEN FROM_UNIXTIME(t.validuntil)
        ELSE CONCAT(FROM_UNIXTIME(t.validuntil), ' (EXPIRED)')
    END as valid_until,
    CASE 
        WHEN t.validuntil IS NULL OR t.validuntil > UNIX_TIMESTAMP(NOW()) THEN 'Active'
        ELSE 'Expired'
    END as status
FROM mdl_external_tokens t
JOIN mdl_user u ON u.id = t.userid
JOIN mdl_external_services s ON s.id = t.externalserviceid
WHERE s.shortname = 'alx_report_api_custom'
ORDER BY t.timecreated DESC;

-- 6. Quick check: How many requests has a specific user made today?
-- Replace USER_ID with the actual user ID you want to check
-- SELECT 
--     COUNT(*) as requests_today,
--     (SELECT value FROM mdl_config_plugins WHERE plugin = 'local_alx_report_api' AND name = 'rate_limit') as daily_limit
-- FROM mdl_local_alx_api_logs 
-- WHERE userid = USER_ID 
--   AND timecreated >= UNIX_TIMESTAMP(CURDATE());

-- 7. Clear today's logs for testing (CAUTION: This will delete data!)
-- Uncomment and run only if you want to reset the rate limit for testing
-- DELETE FROM mdl_local_alx_api_logs WHERE timecreated >= UNIX_TIMESTAMP(CURDATE());

-- 8. Check if log table exists
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    UPDATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'mdl_local_alx_api_logs'; 