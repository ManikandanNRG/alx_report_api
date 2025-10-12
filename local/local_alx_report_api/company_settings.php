<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Company-specific settings page for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_company_settings');

$companyid = optional_param('companyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Page setup
$PAGE->set_url('/local/alx_report_api/company_settings.php');
$PAGE->set_title(get_string('company_settings_title', 'local_alx_report_api'));
$PAGE->set_heading(get_string('company_settings_title', 'local_alx_report_api'));

// Get all companies
$companies = local_alx_report_api_get_companies();

// Handle form submission
if ($action === 'save' && $companyid && confirm_sesskey()) {
    $errors = [];
    $success_count = 0;
    
    try {
    $field_settings = [
        'field_userid', 'field_firstname', 'field_lastname', 'field_email',
        'field_courseid', 'field_coursename', 'field_timecompleted', 
        'field_timecompleted_unix', 'field_timestarted', 'field_timestarted_unix',
        'field_percentage', 'field_status'
    ];
    
    // Save field settings
    foreach ($field_settings as $setting) {
        $value = optional_param($setting, 0, PARAM_INT);
            $result = local_alx_report_api_set_company_setting($companyid, $setting, $value);
            if ($result === false) {
                $errors[] = "Failed to save field setting: $setting";
            } else {
                $success_count++;
            }
    }
    
    // Save course settings
    $company_courses = local_alx_report_api_get_company_courses($companyid);
    foreach ($company_courses as $course) {
        $course_setting = 'course_' . $course->id;
        $value = optional_param($course_setting, 0, PARAM_INT);
            $result = local_alx_report_api_set_company_setting($companyid, $course_setting, $value);
            if ($result === false) {
                $errors[] = "Failed to save course setting for: " . $course->fullname;
            } else {
                $success_count++;
            }
    }
    
    // Save incremental sync settings
    $sync_settings = [
        'sync_mode', 'sync_window_hours', 'first_sync_hours', 'cache_enabled', 'cache_ttl_minutes'
    ];
    
    foreach ($sync_settings as $setting) {
        if ($setting === 'sync_mode') {
                $value = optional_param($setting, 0, PARAM_INT);
                // Validate sync_mode values: 0=Auto, 1=Always Incremental, 2=Always Full, 3=Disabled
                if (!in_array($value, [0, 1, 2, 3])) {
                    $value = 0; // Default to Auto if invalid value
                }
        } else {
            $value = optional_param($setting, 0, PARAM_INT);
                // Ensure positive values for numeric settings
                if ($value < 0) {
                    $value = 0;
                }
            }
            
            $result = local_alx_report_api_set_company_setting($companyid, $setting, $value);
            if ($result === false) {
                $errors[] = "Failed to save sync setting: $setting";
            } else {
                $success_count++;
            }
        }
        
        // Display results
        if (!empty($errors)) {
            $error_message = "Some settings could not be saved:\n" . implode("\n", $errors);
            $error_message .= "\n\nSuccessfully saved: $success_count settings";
            redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
                     $error_message, null, \core\output\notification::NOTIFY_ERROR);
        } else {
            redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
                     "All settings saved successfully! ($success_count settings)", null, \core\output\notification::NOTIFY_SUCCESS);
        }
        
    } catch (Exception $e) {
        // Catch any unexpected errors
        $error_message = "Error saving settings: " . $e->getMessage();
        if ($success_count > 0) {
            $error_message .= "\n\nSuccessfully saved: $success_count settings before error occurred";
        }
    redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
                 $error_message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Handle copy from template
if ($action === 'copy_template' && $companyid && confirm_sesskey()) {
    local_alx_report_api_copy_company_settings(0, $companyid);
    redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
             get_string('template_copied', 'local_alx_report_api'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Start output
echo '<link rel="stylesheet" href="' . new moodle_url('/local/alx_report_api/styles/company-settings.css') . '">';
echo $OUTPUT->header();

echo '<div class="alx_report_api-container">';
echo '<div class="alx_report_api-header">';
echo '<h2>🏢 ' . get_string('company_settings_title', 'local_alx_report_api') . '</h2>';
echo '</div>';

// Company selector
echo '<div class="company-selector">';
echo '<h3>📋 ' . get_string('select_company', 'local_alx_report_api') . '</h3>';

if (empty($companies)) {
    echo '<div class="alert alert-warning">⚠️ ' . get_string('no_companies', 'local_alx_report_api') . '</div>';
} else {
    echo '<form method="get" class="form-inline">';
    echo '<label for="companyid">' . get_string('company', 'local_alx_report_api') . ':</label>';
    
    $options = [0 => get_string('choose_company', 'local_alx_report_api')];
    foreach ($companies as $company) {
        $options[$company->id] = $company->name;
    }
    
    echo html_writer::select($options, 'companyid', $companyid, false, [
        'id' => 'companyid',
        'onchange' => 'this.form.submit();',
        'class' => 'form-control'
    ]);
    echo '<input type="submit" value="' . get_string('go') . '" class="btn btn-primary">';
    echo '</form>';
}
echo '</div>';

// Show company settings if selected
if ($companyid && isset($companies[$companyid])) {
    $company = $companies[$companyid];
    $current_settings = local_alx_report_api_get_company_settings($companyid);
    
    echo '<div class="company-settings">';
    echo '<h3>⚙️ ' . get_string('settings_for_company', 'local_alx_report_api', $company->name) . '</h3>';
    
    // Critical Warning about Historical Data Population
    global $DB;
    $reporting_records = $DB->count_records('local_alx_api_reporting', ['companyid' => $companyid, 'is_deleted' => 0]);
    if ($reporting_records === 0) {
        echo '<div class="alert" style="background: #f8d7da; color: #721c24; border: 2px solid #f5c6cb; padding: 20px; margin: 20px 0; border-radius: 8px;">';
        echo '<h4 style="margin: 0 0 15px 0; font-weight: bold;">🚨 CRITICAL: Historical Data Population Required</h4>';
        echo '<p style="margin: 0 0 10px 0;"><strong>Your reporting table is currently EMPTY!</strong></p>';
        echo '<p style="margin: 0 0 10px 0;">Without populating historical data first, the API will only return users with recent activity (last 24 hours), causing <strong>PERMANENT DATA LOSS</strong> for historical course completions.</p>';
        echo '<p style="margin: 0 0 15px 0;"><strong>Required Action:</strong> Go to <a href="populate_reporting_table.php" style="color: #721c24; font-weight: bold; text-decoration: underline;">Populate Historical Data</a> page and run the population process BEFORE using this API.</p>';
        echo '<p style="margin: 0; font-size: 14px; font-style: italic;">This warning will disappear once historical data is populated (' . number_format($reporting_records) . ' records currently).</p>';
        echo '</div>';
    } else {
        echo '<div class="alert" style="background: #d4edda; color: #155724; border: 2px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 8px;">';
        echo '<h4 style="margin: 0 0 10px 0; font-weight: bold;">✅ Historical Data Status: Ready</h4>';
        echo '<p style="margin: 0;">Reporting table contains <strong>' . number_format($reporting_records) . '</strong> records for this company. API will return complete historical data.</p>';
        echo '</div>';
    }
    
    // Settings form
    echo '<form method="post" class="form">';
    echo '<input type="hidden" name="companyid" value="' . $companyid . '">';
    echo '<input type="hidden" name="action" value="save">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    
    // Field controls
    echo '<h4 class="section-title">📊 ' . get_string('field_controls', 'local_alx_report_api') . '</h4>';
    echo '<div class="checkbox-grid">';
    
    $field_definitions = [
        'field_userid' => get_string('field_userid', 'local_alx_report_api'),
        'field_firstname' => get_string('field_firstname', 'local_alx_report_api'),
        'field_lastname' => get_string('field_lastname', 'local_alx_report_api'),
        'field_email' => get_string('field_email', 'local_alx_report_api'),
        'field_courseid' => get_string('field_courseid', 'local_alx_report_api'),
        'field_coursename' => get_string('field_coursename', 'local_alx_report_api'),
        'field_timecompleted' => get_string('field_timecompleted', 'local_alx_report_api'),
        'field_timecompleted_unix' => get_string('field_timecompleted_unix', 'local_alx_report_api'),
        'field_timestarted' => get_string('field_timestarted', 'local_alx_report_api'),
        'field_timestarted_unix' => get_string('field_timestarted_unix', 'local_alx_report_api'),
        'field_percentage' => get_string('field_percentage', 'local_alx_report_api'),
        'field_status' => get_string('field_status', 'local_alx_report_api'),
    ];
    
    foreach ($field_definitions as $field => $label) {
        $checked = isset($current_settings[$field]) ? $current_settings[$field] : 1;
        echo '<div class="checkbox-item">';
        echo '<input type="checkbox" name="' . $field . '" value="1" id="' . $field . '" ' . ($checked ? 'checked' : '') . '>';
        echo '<label for="' . $field . '">' . $label . '</label>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Course controls
    $company_courses = local_alx_report_api_get_company_courses($companyid);
    if (!empty($company_courses)) {
        echo '<h4 class="section-title">📚 ' . get_string('course_controls', 'local_alx_report_api') . '</h4>';
        echo '<p class="text-muted">' . get_string('course_controls_desc', 'local_alx_report_api') . '</p>';
        
        // Quick course selection buttons
        echo '<div class="control-buttons">';
        echo '<button type="button" class="btn-outline" onclick="toggleAllCourses(true)">✅ ' . get_string('select_all_courses', 'local_alx_report_api') . '</button>';
        echo '<button type="button" class="btn-outline" onclick="toggleAllCourses(false)">❌ ' . get_string('deselect_all_courses', 'local_alx_report_api') . '</button>';
        echo '</div>';
        
        echo '<div class="checkbox-grid">';
        
        foreach ($company_courses as $course) {
            $course_setting = 'course_' . $course->id;
            $checked = isset($current_settings[$course_setting]) ? $current_settings[$course_setting] : 1;
            $label = $course->fullname . ' (ID: ' . $course->id . ')';
            
            echo '<div class="checkbox-item">';
            echo '<input type="checkbox" class="course-checkbox" name="' . $course_setting . '" value="1" id="' . $course_setting . '" ' . ($checked ? 'checked' : '') . '>';
            echo '<label for="' . $course_setting . '">' . $label . '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<h4 class="section-title">📚 ' . get_string('course_controls', 'local_alx_report_api') . '</h4>';
        echo '<div class="alert alert-info">ℹ️ ' . get_string('no_courses_for_company', 'local_alx_report_api') . '</div>';
    }
    
    // Incremental Sync Settings
    echo '<h4 class="section-title">🔄 Incremental Sync Settings</h4>';
    echo '<p class="text-muted">Configure how the API handles incremental updates for this company.</p>';
    
    echo '<div class="sync-settings-grid">';
    
    // Sync Mode
    $sync_mode = isset($current_settings['sync_mode']) ? $current_settings['sync_mode'] : 0;
    echo '<div class="setting-item">';
    echo '<label for="sync_mode"><strong>Sync Mode:</strong></label>';
    echo '<select name="sync_mode" id="sync_mode" class="form-control">';
    echo '<option value="0"' . ($sync_mode === 0 ? ' selected' : '') . '>Auto (Intelligent Switching)</option>';
    echo '<option value="1"' . ($sync_mode === 1 ? ' selected' : '') . '>Always Incremental</option>';
    echo '<option value="2"' . ($sync_mode === 2 ? ' selected' : '') . '>Always Full Sync</option>';
    echo '<option value="3"' . ($sync_mode === 3 ? ' selected' : '') . '>Disabled</option>';
    echo '</select>';
    echo '<small class="form-text text-muted">Choose sync strategy: Auto (smart switching), Always Incremental (performance), Always Full (consistency), or Disabled.</small>';
    echo '</div>';
    
    // Sync Window Hours
    $sync_window_hours = isset($current_settings['sync_window_hours']) ? $current_settings['sync_window_hours'] : 24;
    echo '<div class="setting-item">';
    echo '<label for="sync_window_hours"><strong>Sync Window (Hours):</strong></label>';
    echo '<input type="number" name="sync_window_hours" id="sync_window_hours" class="form-control" value="' . $sync_window_hours . '" min="1" max="168">';
    echo '<small class="form-text text-muted">Time window for incremental sync. If last sync was longer ago, full sync will be performed.</small>';
    echo '</div>';
    
    // First Time Data Window (for empty reporting table scenarios)
    $first_sync_hours = isset($current_settings['first_sync_hours']) ? $current_settings['first_sync_hours'] : 0;
    echo '<div class="setting-item">';
    echo '<label for="first_sync_hours"><strong>First Time Data Window (Hours):</strong></label>';
    echo '<select name="first_sync_hours" id="first_sync_hours" class="form-control">';
    echo '<option value="0"' . ($first_sync_hours == 0 ? ' selected' : '') . '>All Historical Data (Complete)</option>';
    echo '<option value="24"' . ($first_sync_hours == 24 ? ' selected' : '') . '>Last 24 Hours (Fast)</option>';
    echo '<option value="168"' . ($first_sync_hours == 168 ? ' selected' : '') . '>Last 7 Days (Balanced)</option>';
    echo '<option value="720"' . ($first_sync_hours == 720 ? ' selected' : '') . '>Last 30 Days (Recent)</option>';
    echo '<option value="2160"' . ($first_sync_hours == 2160 ? ' selected' : '') . '>Last 90 Days (Extended)</option>';
    echo '</select>';
    echo '<small class="form-text text-muted">⚠️ If reporting table is empty, this controls how much historical data to return on first API call. Use "All Historical Data" to prevent data loss.</small>';
    echo '</div>';
    
    // Cache Enabled
    $cache_enabled = isset($current_settings['cache_enabled']) ? $current_settings['cache_enabled'] : 1;
    echo '<div class="setting-item">';
    echo '<input type="checkbox" name="cache_enabled" value="1" id="cache_enabled" ' . ($cache_enabled ? 'checked' : '') . '>';
    echo '<label for="cache_enabled"><strong>Enable Caching</strong></label>';
    echo '<small class="form-text text-muted">Cache API responses to improve performance.</small>';
    echo '</div>';
    
    // Cache TTL Minutes
    $cache_ttl_minutes = isset($current_settings['cache_ttl_minutes']) ? $current_settings['cache_ttl_minutes'] : 30;
    echo '<div class="setting-item">';
    echo '<label for="cache_ttl_minutes"><strong>Cache Duration (Minutes):</strong></label>';
    echo '<input type="number" name="cache_ttl_minutes" id="cache_ttl_minutes" class="form-control" value="' . $cache_ttl_minutes . '" min="5" max="1440">';
    echo '<small class="form-text text-muted">How long to cache API responses (5-1440 minutes).</small>';
    echo '</div>';
    
    echo '</div>';
    

    
    // Action buttons
    echo '<div class="form-actions">';
    echo '<input type="submit" value="💾 ' . get_string('save_settings', 'local_alx_report_api') . '" class="btn btn-success">';
    echo '<a href="' . $PAGE->url->out(false, ['companyid' => $companyid, 'action' => 'copy_template', 'sesskey' => sesskey()]) . '" class="btn btn-secondary">📋 ' . get_string('copy_from_template', 'local_alx_report_api') . '</a>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    
    // JavaScript for course selection
    if (!empty($company_courses)) {
        echo '<script>
        function toggleAllCourses(selectAll) {
            var checkboxes = document.querySelectorAll(\'.course-checkbox\');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll;
            });
        }
        </script>';
    }
}

echo '</div>';

echo $OUTPUT->footer(); 
