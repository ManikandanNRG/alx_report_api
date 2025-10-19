# ALX Report API - Tenant Reporting System

A comprehensive Moodle plugin for multi-tenant course progress reporting and Power BI integration.

## Overview

This repository contains the **ALX Report API** plugin for IOMAD Moodle, providing secure REST API access to course completion and progress data across multiple tenants/companies. The plugin is designed for enterprise-level reporting with Power BI integration.

## Key Features

- **Multi-tenant Security**: Company-isolated data access with token-based authentication
- **REST API**: Secure endpoints for course progress and completion data
- **Power BI Integration**: Optimized for Power BI data refresh and reporting
- **Control Center Dashboard**: Unified interface for all plugin operations
- **Real-time Monitoring**: System health, sync status, and performance metrics
- **Data Synchronization**: Automated and manual sync capabilities
- **Audit Logging**: Complete API access tracking
- **Performance Optimized**: Separate reporting tables for fast queries

## Project Structure

```
tenantReport_NEW/
└── local/
    └── local_alx_report_api/     # Main plugin directory
        ├── classes/              # Core classes and API logic
        ├── db/                   # Database schema and upgrade scripts
        ├── lang/                 # Language strings
        ├── styles/               # CSS stylesheets
        ├── archive/              # Archived files
        ├── backup/               # Backup files
        ├── version.php           # Plugin version information
        ├── settings.php          # Plugin settings
        ├── lib.php               # Core library functions
        ├── externallib.php       # External API functions
        └── README.md             # Plugin documentation
```

## Installation

1. Clone this repository to your Moodle installation
2. Place the plugin in `[moodle_root]/local/alx_report_api/`
3. Log in as admin and navigate to Site Administration → Notifications
4. Follow the installation prompts
5. Enable web services and REST protocol in Moodle

For detailed installation and configuration instructions, see the [plugin README](local/local_alx_report_api/README.md).

## Requirements

- Moodle 4.2.6 or higher
- IOMAD (for multi-tenancy support)
- PHP 7.4 or higher
- MySQL/MariaDB database

## Version

Current version: **1.9.0**

## Documentation

- [Plugin README](local/local_alx_report_api/README.md) - Detailed plugin documentation
- [Manual Cleanup Guide](local/local_alx_report_api/MANUAL_CLEANUP.md) - Data cleanup procedures
- [Uninstall Procedure](local/local_alx_report_api/PROPER_UNINSTALL_PROCEDURE.md) - Safe uninstallation steps

## License

GNU GPL v3 or later

## Copyright

© 2024 ALX Report API Plugin
