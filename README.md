# ALX Report API Plugin - Development Repository

This is the development repository for the ALX Report API Moodle plugin.

## Repository Structure

```
alx_report_api_development/
├── local_alx_report_api/          # Plugin source code
├── releases/                      # Clean ZIP files for Moodle installation
├── scripts/                       # Development helper scripts
├── README.md                      # This file
└── .gitignore                     # Git ignore rules
```

## Development Workflow

### 1. Make Changes
Edit files in the `local_alx_report_api/` directory.

### 2. Create Release
Run the release script to create clean ZIP files:
```bash
./scripts/create_release.bat
```

### 3. Install in Moodle
Use the ZIP file from `releases/` directory to install in Moodle.

## Important Notes

- **Never** install directly from this Git repository
- **Always** use the clean ZIP files from `releases/` directory
- The `releases/` directory contains ZIP files without .git folders
- This ensures Moodle installation works correctly

## Git Commands

```bash
# Commit changes
git add .
git commit -m "Your commit message"

# Push to remote
git push origin main

# Create release
./scripts/create_release.bat
```

## Plugin Information

- **Plugin Name**: ALX Report API
- **Plugin Type**: local
- **Moodle Compatibility**: 4.2+
- **Features**: Multi-tenant API, Incremental Sync, Performance Optimization 