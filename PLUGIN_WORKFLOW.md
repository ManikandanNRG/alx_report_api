# ALX Report API Plugin - Complete Workflow & Monitoring Needs

## 🔄 PLUGIN WORKFLOW - HOW IT WORKS

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 1: DATA COLLECTION (Moodle Core Tables)                                      │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  Moodle stores learning data in multiple tables:                                   │
│  • user (student information)                                                       │
│  • course (course details)                                                          │
│  • course_completions (completion records)                                          │
│  • course_modules_completion (activity completions)                                 │
│  • company (IOMAD companies)                                                        │
│  • company_users (company-user associations)                                        │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 2: AUTO-SYNC (Hourly Cron Job)                                               │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  Every hour, cron job runs:                                                        │
│  1. Check what changed in last hour                                                │
│  2. Query Moodle core tables for updates                                           │
│  3. Process data (calculate percentages, status)                                   │
│  4. Update local_alx_api_reporting table                                           │
│                                                                                     │
│  ⚠️ MONITOR: Sync success/failure, records processed, errors                       │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 3: REPORTING TABLE (Pre-built Data)                                          │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  local_alx_api_reporting contains:                                                 │
│  • userid, companyid, courseid                                                     │
│  • firstname, lastname, email                                                      │
│  • coursename, timecompleted, percentage, status                                   │
│  • last_updated (for incremental sync)                                             │
│                                                                                     │
│  ⚠️ MONITOR: Total records, active records, data quality                           │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 4: API REQUEST (Power BI / External System)                                  │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  External system calls API:                                                        │
│  POST /webservice/rest/server.php                                                  │
│  • wstoken: authentication token                                                   │
│  • wsfunction: local_alx_report_api_get_course_progress                            │
│  • limit, offset: pagination                                                       │
│                                                                                     │
│  ⚠️ MONITOR: API calls, response time, success rate                                │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 5: SECURITY CHECKS                                                            │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  1. Validate token                                                                  │
│  2. Check rate limit (100 requests/day default)                                    │
│  3. Verify company association                                                      │
│  4. Log request                                                                     │
│                                                                                     │
│  ⚠️ MONITOR: Rate limit violations, failed auth, suspicious activity               │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 6: CACHE CHECK                                                                │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  Check if data is cached:                                                          │
│  • Cache key: company_id + limit + offset + sync_mode                              │
│  • Cache TTL: 60 minutes                                                            │
│  • If cached: return immediately (fast!)                                            │
│  • If not: query database                                                           │
│                                                                                     │
│  ⚠️ MONITOR: Cache hit rate, cache entries                                         │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 7: QUERY REPORTING TABLE                                                      │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  Query local_alx_api_reporting:                                                    │
│  • Filter by company                                                                │
│  • Apply pagination (limit/offset)                                                  │
│  • Return JSON response                                                             │
│                                                                                     │
│  ⚠️ MONITOR: Query response time, records returned                                 │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────────────────┐
│  STEP 8: RETURN DATA TO POWER BI                                                    │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  JSON response with course progress data                                           │
│  Power BI creates dashboards and reports                                           │
└─────────────────────────────────────────────────────────────────────────────────────┘
```



---

## 🎯 WHAT NEEDS TO BE MONITORED

### **1. AUTO-SYNC MONITORING (Most Important)**
```
WHY: This is the heart of the plugin - keeps data fresh

METRICS TO MONITOR:
✓ Last sync time
✓ Next sync time  
✓ Companies processed
✓ Records synced (created + updated)
✓ Sync errors
✓ Sync success rate (7 days)
✓ Task status (active/disabled)

ALERTS NEEDED:
⚠️ Sync failed
⚠️ No sync in last 2 hours
⚠️ High error rate (>5%)
```

### **2. API PERFORMANCE MONITORING**
```
WHY: External systems depend on fast API responses

METRICS TO MONITOR:
✓ Total API calls (24h)
✓ Average response time
✓ Success rate
✓ Cache hit rate
✓ Top consumers (which companies call most)

ALERTS NEEDED:
⚠️ Response time > 5 seconds
⚠️ Success rate < 95%
⚠️ Cache hit rate < 70%
```

### **3. SECURITY MONITORING**
```
WHY: Protect against abuse and unauthorized access

METRICS TO MONITOR:
✓ Active tokens
✓ Rate limit violations
✓ Failed authentication attempts
✓ API errors by type

ALERTS NEEDED:
⚠️ Rate limit exceeded
⚠️ Multiple failed auth attempts
⚠️ Unusual API activity
```

---

## 📊 SIMPLIFIED MONITORING DASHBOARD - FINAL DESIGN

Based on the workflow, here's what each tab should show:

### **TAB 1: AUTO-SYNC (Default - Most Important)**
```
PURPOSE: Monitor the hourly sync that keeps data fresh

SHOW:
• Sync status cards (4): Companies, Last Sync, Next Sync, Status
• Weekly trend chart: Success rate over 7 days
• Company sync table: Which companies synced, how many records

WHY: If sync fails, data becomes stale and Power BI shows old data
```

### **TAB 2: PERFORMANCE**
```
PURPOSE: Monitor API speed and database health

SHOW:
• API metrics (5): Calls, Response Time, Success Rate, Cache Hit, Records
• Response time chart: 24-hour trend
• Top consumers table: Which companies use API most

WHY: Slow API = unhappy Power BI users
```

### **TAB 3: SECURITY**
```
PURPOSE: Monitor security threats and errors

SHOW:
• Security metrics (4): Tokens, Rate Limits, Failed Auth, Suspicious
• Rate limit violations table: Who exceeded limits
• Recent errors table: What's failing

WHY: Prevent abuse and catch authentication issues
```

---

## ✅ FINAL RECOMMENDATION

**Keep it simple with 3 tabs:**

1. **🔄 Auto-Sync** (Default) - The most critical monitoring
2. **⚡ Performance** - API speed and health
3. **🔒 Security** - Threats and errors

**Remove from current pages:**
- ❌ Duplicate charts showing same data
- ❌ "Overview" tab (Auto-Sync IS the overview)
- ❌ "Actions" tab (move actions to Control Center)
- ❌ Timeline process flow (nice but not essential)
- ❌ Endpoint testing (move to Actions in Control Center)

**Result:**
- Clean, focused monitoring
- Each tab has a clear purpose
- No duplicate information
- Fast to understand at a glance

