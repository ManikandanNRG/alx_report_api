# ALX Report API Plugin - Design Document

## Overview

The ALX Report API Plugin is architected as a high-performance, multi-tenant API system for Moodle that provides secure access to course progress data. The design emphasizes performance optimization through intelligent caching and sync mechanisms, comprehensive security through token-based authentication and rate limiting, and operational excellence through detailed monitoring and alerting.

## Architecture

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    External Clients                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │   Power BI  │  │  Tableau    │  │  Custom Dashboards      │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└─────────────────────┬───────────────────────────────────────────┘
                      │ HTTPS/REST API
┌─────────────────────▼───────────────────────────────────────────┐
│                 ALX Report API Plugin                           │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              Security Layer                                 │ │
│  │  • Token Authentication  • Rate Limiting  • Audit Logging  │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              API Controller Layer                           │ │
│  │  • Request Validation   • Company Resolution               │ │
│  │  • Sync Mode Detection  • Response Formatting              │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              Business Logic Layer                           │ │
│  │  • Intelligent Sync Engine  • Cache Management             │ │
│  │  • Field Filtering          • Error Handling               │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              Data Access Layer                              │ │
│  │  • Reporting Table Queries  • Live Data Fallback           │ │
│  │  • Batch Processing         • Transaction Management       │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────┬───────────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────────┐
│                 Database Layer                                  │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────────┐ │
│  │ Reporting Table │ │  Cache Table    │ │   Settings Table    │ │
│  │ (Optimized)     │ │  (Performance)  │ │   (Configuration)   │ │
│  └─────────────────┘ └─────────────────┘ └─────────────────────┘ │
│  ┌─────────────────┐ ┌─────────────────┐                       │ │
│  │   Logs Table    │ │ Sync Status     │                       │ │
│  │  (Monitoring)   │ │   Table         │                       │ │
│  └─────────────────┘ └─────────────────┘                       │ │
└─────────────────────────────────────────────────────────────────┘
```

### Core Components

#### 1. Security Layer
- **Token Authentication**: Validates API tokens against Moodle's external_tokens table
- **Rate Limiting**: Configurable daily request limits per user
- **Audit Logging**: Comprehensive request/response logging with performance metrics
- **Security Headers**: Proper HTTP security headers and CORS configuration

#### 2. API Controller Layer
- **Request Validation**: Parameter validation and sanitization
- **Company Resolution**: Determines user's company association for multi-tenant isolation
- **Sync Mode Detection**: Intelligent decision making for full vs incremental sync
- **Response Formatting**: Consistent JSON response structure with error handling

#### 3. Business Logic Layer
- **Intelligent Sync Engine**: Automated sync mode selection based on conditions
- **Cache Management**: High-performance caching with TTL and LRU eviction
- **Field Filtering**: Company-specific field visibility controls
- **Error Handling**: Comprehensive error recovery and fallback mechanisms

#### 4. Data Access Layer
- **Reporting Table Queries**: Optimized queries against pre-built reporting table
- **Live Data Fallback**: Complex queries against live Moodle tables when needed
- **Batch Processing**: Efficient handling of large datasets
- **Transaction Management**: ACID compliance for data consistency

## Components and Interfaces

### External API Interface

```php
/**
 * Primary API endpoint for course progress data
 * 
 * @param int $limit Maximum records to return (default: 100, max: 1000)
 * @param int $offset Pagination offset (default: 0)
 * @return array Course progress data with metadata
 */
public function get_course_progress($limit = 100, $offset = 0);
```

**Request Format:**
```http
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=your_api_token_here
wsfunction=local_alx_report_api_get_course_progress
moodlewsrestformat=json
limit=100
offset=0
```

**Response Format:**
```json
[
  {
    "userid": 123,
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@company.com",
    "courseid": 456,
    "coursename": "Safety Training",
    "timecompleted": "2024-01-15 14:30:00",
    "timecompleted_unix": 1705329000,
    "timestarted": "2024-01-15 09:00:00",
    "timestarted_unix": 1705309200,
    "percentage": 100.0,
    "status": "completed"
  }
]
```

### Internal Component Interfaces

#### Sync Engine Interface
```php
interface SyncEngineInterface {
    public function determineSyncMode(int $companyId, string $token): string;
    public function updateSyncStatus(int $companyId, string $token, int $recordCount, string $status): bool;
    public function getSyncStatus(int $companyId, string $token): ?object;
}
```

#### Cache Manager Interface
```php
interface CacheManagerInterface {
    public function get(string $key, int $companyId): mixed;
    public function set(string $key, int $companyId, mixed $data, int $ttl): bool;
    public function delete(string $key, int $companyId): bool;
    public function cleanup(): int;
}
```

#### Data Repository Interface
```php
interface DataRepositoryInterface {
    public function getCourseProgress(int $companyId, int $limit, int $offset, array $filters): array;
    public function getIncrementalChanges(int $companyId, int $since, int $limit, int $offset): array;
    public function populateReportingTable(int $companyId, int $batchSize): array;
}
```

## Data Models

### Database Schema

#### local_alx_api_logs
```sql
CREATE TABLE local_alx_api_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    userid BIGINT NOT NULL,
    company_shortname VARCHAR(100),
    endpoint VARCHAR(255) NOT NULL,
    record_count BIGINT DEFAULT 0,
    error_message TEXT,
    response_time_ms DECIMAL(10,2),
    timeaccessed BIGINT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    additional_data TEXT,
    INDEX idx_userid (userid),
    INDEX idx_company (company_shortname),
    INDEX idx_endpoint (endpoint),
    INDEX idx_time (timeaccessed),
    INDEX idx_response_time (response_time_ms)
);
```

#### local_alx_api_settings
```sql
CREATE TABLE local_alx_api_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    companyid BIGINT NOT NULL,
    setting_name VARCHAR(100) NOT NULL,
    setting_value TINYINT DEFAULT 0,
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL,
    UNIQUE KEY unique_company_setting (companyid, setting_name),
    INDEX idx_company (companyid),
    INDEX idx_setting (setting_name)
);
```

#### local_alx_api_reporting
```sql
CREATE TABLE local_alx_api_reporting (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    userid BIGINT NOT NULL,
    companyid BIGINT NOT NULL,
    courseid BIGINT NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    coursename VARCHAR(255) NOT NULL,
    timecompleted BIGINT DEFAULT 0,
    timestarted BIGINT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'not_started',
    last_updated BIGINT NOT NULL,
    is_deleted TINYINT DEFAULT 0,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    UNIQUE KEY unique_user_course (userid, courseid, companyid),
    INDEX idx_company (companyid),
    INDEX idx_last_updated (last_updated),
    INDEX idx_user_course (userid, courseid),
    INDEX idx_completion (timecompleted),
    INDEX idx_status (status),
    INDEX idx_deleted (is_deleted)
);
```

#### local_alx_api_sync_status
```sql
CREATE TABLE local_alx_api_sync_status (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    companyid BIGINT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    last_sync_timestamp BIGINT DEFAULT 0,
    sync_mode VARCHAR(20) DEFAULT 'auto',
    sync_window_hours INT DEFAULT 24,
    last_sync_records BIGINT DEFAULT 0,
    last_sync_status VARCHAR(20) DEFAULT 'success',
    last_sync_error TEXT,
    total_syncs BIGINT DEFAULT 0,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    UNIQUE KEY unique_company_token (companyid, token_hash),
    INDEX idx_company (companyid),
    INDEX idx_token (token_hash),
    INDEX idx_last_sync (last_sync_timestamp),
    INDEX idx_sync_mode (sync_mode)
);
```

#### local_alx_api_cache
```sql
CREATE TABLE local_alx_api_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) NOT NULL,
    companyid BIGINT NOT NULL,
    cache_data TEXT NOT NULL,
    cache_timestamp BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    hit_count BIGINT DEFAULT 0,
    last_accessed BIGINT NOT NULL,
    UNIQUE KEY unique_cache_key (cache_key, companyid),
    INDEX idx_cache_key (cache_key),
    INDEX idx_company (companyid),
    INDEX idx_expires (expires_at),
    INDEX idx_timestamp (cache_timestamp)
);
```

### Data Flow Models

#### Sync Decision Flow
```
API Request → Token Validation → Company Resolution → Sync Mode Decision
                                                           ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    Sync Mode Decision Logic                         │
├─────────────────────────────────────────────────────────────────────┤
│ IF first_sync OR last_sync_failed OR time_gap > window:            │
│     → FULL SYNC (return all data)                                  │
│ ELSE:                                                               │
│     → INCREMENTAL SYNC (return only changes)                       │
└─────────────────────────────────────────────────────────────────────┘
                                ↓
Cache Check → Database Query → Response Formatting → Logging
```

#### Data Processing Pipeline
```
Moodle Core Data → Background Sync Task → Reporting Table → API Response
                                              ↓
                                        Cache Layer
                                              ↓
                                        Performance Optimization
```

## Error Handling

### Error Classification

#### 1. Authentication Errors (4xx)
- **401 Unauthorized**: Invalid or missing token
- **403 Forbidden**: Valid token but insufficient permissions
- **429 Too Many Requests**: Rate limit exceeded

#### 2. Validation Errors (4xx)
- **400 Bad Request**: Invalid parameters
- **413 Payload Too Large**: Limit parameter exceeds maximum

#### 3. System Errors (5xx)
- **500 Internal Server Error**: Database connection issues
- **503 Service Unavailable**: System maintenance mode
- **504 Gateway Timeout**: Query timeout

### Error Response Format
```json
{
  "data": [],
  "status": "error",
  "message": "Human-readable error description",
  "error_code": "SYSTEM_ERROR_CODE",
  "debug_info": {
    "sync_mode": "incremental",
    "last_sync": "2024-01-15 14:30:00",
    "company_id": 1
  },
  "timestamp": "2024-01-15 16:45:23"
}
```

### Recovery Mechanisms

#### 1. Automatic Fallback
- **Cache Miss**: Fall back to database query
- **Reporting Table Empty**: Fall back to complex live queries
- **Sync Failure**: Automatically retry with full sync

#### 2. Self-Healing
- **Corrupted Cache**: Automatic cache invalidation and rebuild
- **Failed Sync Status**: Reset to full sync on next request
- **Database Timeout**: Retry with optimized query

## Testing Strategy

### Unit Testing
- **Authentication Logic**: Token validation and company resolution
- **Sync Engine**: Decision logic for sync modes
- **Cache Manager**: Cache operations and TTL handling
- **Data Repository**: Query optimization and result formatting

### Integration Testing
- **API Endpoints**: Full request/response cycle testing
- **Database Operations**: Transaction handling and data consistency
- **External Services**: Web service integration and token management
- **Background Tasks**: Scheduled sync operations

### Performance Testing
- **Load Testing**: High-volume API requests
- **Stress Testing**: Database performance under load
- **Cache Performance**: Hit rates and response times
- **Memory Usage**: Large dataset processing

### Security Testing
- **Authentication**: Token validation and expiration
- **Authorization**: Company data isolation
- **Rate Limiting**: Request throttling effectiveness
- **Input Validation**: SQL injection and XSS prevention

## Monitoring and Observability

### Key Performance Indicators (KPIs)
- **API Response Time**: Average and 95th percentile
- **Cache Hit Rate**: Percentage of requests served from cache
- **Sync Success Rate**: Percentage of successful sync operations
- **Error Rate**: Percentage of failed API requests
- **Database Query Performance**: Query execution times

### Alerting Thresholds
- **High Response Time**: > 2 seconds average
- **Low Cache Hit Rate**: < 70%
- **High Error Rate**: > 5%
- **Database Performance**: > 200ms average query time
- **Rate Limit Violations**: > 10 per hour per user

### Monitoring Dashboards
1. **System Overview**: Health status, key metrics, recent alerts
2. **Performance Metrics**: Response times, throughput, cache performance
3. **Error Analysis**: Error rates, failure patterns, resolution tracking
4. **Usage Analytics**: API usage patterns, company activity, trend analysis

## Deployment and Configuration

### Installation Requirements
- **Moodle Version**: 4.2+ (requires external web services)
- **PHP Version**: 7.4+ (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**: JSON, cURL, OpenSSL

### Configuration Parameters
```php
// Global Settings
$CFG->alx_api_max_records = 1000;           // Maximum records per request
$CFG->alx_api_rate_limit = 100;             // Daily request limit per user
$CFG->alx_api_cache_ttl = 3600;             // Cache TTL in seconds
$CFG->alx_api_sync_window = 24;             // Sync window in hours
$CFG->alx_api_log_retention = 90;           // Log retention in days

// Company-Specific Settings (stored in database)
// field_* settings control field visibility
// course_* settings control course access
// sync_mode controls sync behavior per company
```

### Deployment Checklist
1. **Pre-Installation**
   - [ ] Verify Moodle version compatibility
   - [ ] Check database permissions
   - [ ] Backup existing data

2. **Installation**
   - [ ] Upload plugin files
   - [ ] Run Moodle upgrade process
   - [ ] Verify all 5 tables created
   - [ ] Configure web services

3. **Post-Installation**
   - [ ] Create API tokens
   - [ ] Configure company settings
   - [ ] Test API endpoints
   - [ ] Set up monitoring

4. **Production Readiness**
   - [ ] Configure rate limiting
   - [ ] Set up alerting
   - [ ] Document API usage
   - [ ] Train administrators

This design provides a robust, scalable, and maintainable foundation for the ALX Report API Plugin, ensuring high performance, security, and operational excellence in production environments.