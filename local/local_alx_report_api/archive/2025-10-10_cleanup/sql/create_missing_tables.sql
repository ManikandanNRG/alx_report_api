-- ALX Report API - Create Missing Tables SQL Script
-- Run this SQL script directly in your database if the PHP upgrade doesn't work
-- Replace 'mdl_' with your actual Moodle table prefix if different

-- Create local_alx_api_logs table
CREATE TABLE IF NOT EXISTS `mdl_local_alx_api_logs` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `userid` bigint(10) NOT NULL,
  `company_shortname` varchar(100) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `record_count` bigint(10) NOT NULL DEFAULT '0',
  `error_message` longtext,
  `response_time_ms` decimal(10,2) DEFAULT NULL,
  `timeaccessed` bigint(10) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` longtext,
  `additional_data` longtext,
  PRIMARY KEY (`id`),
  KEY `mdl_localapilogs_use_ix` (`userid`),
  KEY `mdl_localapilogs_com_ix` (`company_shortname`),
  KEY `mdl_localapilogs_end_ix` (`endpoint`),
  KEY `mdl_localapilogs_tim_ix` (`timeaccessed`),
  KEY `mdl_localapilogs_res_ix` (`response_time_ms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API access logs for the ALX Report API plugin';

-- Create local_alx_api_cache table
CREATE TABLE IF NOT EXISTS `mdl_local_alx_api_cache` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `companyid` bigint(10) NOT NULL,
  `cache_data` longtext NOT NULL,
  `cache_timestamp` bigint(10) NOT NULL,
  `expires_at` bigint(10) NOT NULL,
  `hit_count` bigint(10) NOT NULL DEFAULT '0',
  `last_accessed` bigint(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdl_localapicach_caccom_uix` (`cache_key`,`companyid`),
  KEY `mdl_localapicach_cac_ix` (`cache_key`),
  KEY `mdl_localapicach_com_ix` (`companyid`),
  KEY `mdl_localapicach_exp_ix` (`expires_at`),
  KEY `mdl_localapicach_cac2_ix` (`cache_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Incremental data caching for performance optimization';

-- Create local_alx_api_reporting table
CREATE TABLE IF NOT EXISTS `mdl_local_alx_api_reporting` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `userid` bigint(10) NOT NULL,
  `companyid` bigint(10) NOT NULL,
  `courseid` bigint(10) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `coursename` varchar(255) NOT NULL,
  `timecompleted` bigint(10) NOT NULL DEFAULT '0',
  `timestarted` bigint(10) NOT NULL DEFAULT '0',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'not_started',
  `last_updated` bigint(10) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` bigint(10) NOT NULL,
  `updated_at` bigint(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdl_localapirep_usecouco_uix` (`userid`,`courseid`,`companyid`),
  KEY `mdl_localapirep_com_ix` (`companyid`),
  KEY `mdl_localapirep_las_ix` (`last_updated`),
  KEY `mdl_localapirep_usecou_ix` (`userid`,`courseid`),
  KEY `mdl_localapirep_tim_ix` (`timecompleted`),
  KEY `mdl_localapirep_sta_ix` (`status`),
  KEY `mdl_localapirep_isd_ix` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pre-built reporting table for fast API responses';

-- Verify tables were created
SELECT 
    'local_alx_api_logs' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'mdl_local_alx_api_logs'
UNION ALL
SELECT 
    'local_alx_api_cache' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'mdl_local_alx_api_cache'
UNION ALL
SELECT 
    'local_alx_api_reporting' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'mdl_local_alx_api_reporting'
UNION ALL
SELECT 
    'local_alx_api_settings' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'mdl_local_alx_api_settings'
UNION ALL
SELECT 
    'local_alx_api_sync_status' as table_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'mdl_local_alx_api_sync_status';