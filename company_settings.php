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
    $field_settings = [
        'field_userid', 'field_firstname', 'field_lastname', 'field_email',
        'field_courseid', 'field_coursename', 'field_timecompleted', 
        'field_timecompleted_unix', 'field_timestarted', 'field_timestarted_unix',
        'field_percentage', 'field_status'
    ];
    
    // Save field settings
    foreach ($field_settings as $setting) {
        $value = optional_param($setting, 0, PARAM_INT);
        local_alx_report_api_set_company_setting($companyid, $setting, $value);
    }
    
    // Save course settings
    $company_courses = local_alx_report_api_get_company_courses($companyid);
    foreach ($company_courses as $course) {
        $course_setting = 'course_' . $course->id;
        $value = optional_param($course_setting, 0, PARAM_INT);
        local_alx_report_api_set_company_setting($companyid, $course_setting, $value);
    }
    
    redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
             get_string('settings_saved', 'local_alx_report_api'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle copy from template
if ($action === 'copy_template' && $companyid && confirm_sesskey()) {
    local_alx_report_api_copy_company_settings(0, $companyid);
    redirect($PAGE->url->out(false, ['companyid' => $companyid]), 
             get_string('template_copied', 'local_alx_report_api'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Start output
echo $OUTPUT->header();

// Modern CSS styling for better UI
echo '<style>
.alx_report_api-container {
    max-width: 1200px;
    margin: 20px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.alx_report_api-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 30px;
    text-align: center;
}

.alx_report_api-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
}

.company-selector {
    background: #f8f9fa;
    padding: 25px 30px;
    border-bottom: 1px solid #e9ecef;
}

.company-selector h3 {
    color: #495057;
    margin-bottom: 20px;
    font-size: 20px;
    font-weight: 600;
}

.form-inline {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.form-inline label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0;
    font-size: 16px;
}

.form-inline select {
    min-width: 250px;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
    background: white;
    transition: all 0.3s;
}

.form-inline select:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    outline: none;
}

.btn-primary {
    background: #28a745;
    border: 2px solid #28a745;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    cursor: pointer;
}

.btn-primary:hover {
    background: #218838;
    border-color: #218838;
    transform: translateY(-1px);
}

.company-settings {
    padding: 30px;
}

.company-settings h3 {
    color: #2c3e50;
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 3px solid #28a745;
}

.section-title {
    color: #495057;
    font-size: 18px;
    font-weight: 600;
    margin: 30px 0 20px 0;
    padding: 10px 0;
    border-bottom: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.checkbox-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 12px;
}

.checkbox-item:hover {
    border-color: #28a745;
    background: #f1f9f1;
    transform: translateY(-1px);
}

.checkbox-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #28a745;
}

.checkbox-item label {
    font-weight: 500;
    color: #495057;
    cursor: pointer;
    margin: 0;
    flex: 1;
}

.control-buttons {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.btn-outline {
    background: white;
    color: #6c757d;
    border: 2px solid #6c757d;
    padding: 8px 16px;
    border-radius: 6px;
    margin: 0 5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-outline:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-1px);
}

.form-actions {
    background: #f8f9fa;
    padding: 25px;
    margin-top: 30px;
    border-radius: 8px;
    text-align: center;
}

.btn-success {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    margin: 0 10px;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    margin: 0 10px;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    font-weight: 500;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.text-muted {
    color: #6c757d;
    font-style: italic;
    margin-bottom: 15px;
}

.quick-actions {
    background: #e9ecef;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
    text-align: center;
}

.quick-actions small {
    display: block;
    color: #6c757d;
    margin-bottom: 10px;
    font-weight: 500;
}
</style>';

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
