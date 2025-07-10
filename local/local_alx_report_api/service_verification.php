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
 * ALX Report API Service Verification & Auto-Fix Tool
 * 
 * This page provides a simple interface to check and automatically fix
 * the function mapping issue that requires manual intervention after installation.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Page setup
$PAGE->set_url('/local/alx_report_api/service_verification.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Service Verification');
$PAGE->set_heading('ALX Report API Service Verification');

// Handle verification/fix request
$action = optional_param('action', '', PARAM_ALPHA);
$verification_result = null;

if ($action === 'verify_and_fix') {
    $verification_result = local_alx_report_api_verify_service_installation();
}

echo $OUTPUT->header();

// Include modern CSS
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';

?>

<style>
:root {
    --primary-color: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
}

* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.verification-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 24px;
    background: var(--light-bg);
    min-height: 100vh;
}

.verification-header {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: white;
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    text-align: center;
}

.verification-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.verification-header p {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-item {
    background: white;
    padding: 20px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    text-align: center;
    position: relative;
}

.status-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.status-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.status-value {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.status-ok { color: var(--success-color); }
.status-error { color: var(--danger-color); }
.status-warning { color: var(--warning-color); }

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

.alert {
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
    border: 1px solid;
}

.alert-success {
    background: #d1fae5;
    border-color: #10b981;
    color: #065f46;
}

.alert-warning {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
}

.alert-danger {
    background: #fee2e2;
    border-color: #ef4444;
    color: #991b1b;
}

.alert-info {
    background: #e0f2fe;
    border-color: #06b6d4;
    color: #155e75;
}

.issues-list, .fixes-list {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}

.issues-list li, .fixes-list li {
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.issues-list li:before {
    content: "❌ ";
    margin-right: 8px;
}

.fixes-list li:before {
    content: "✅ ";
    margin-right: 8px;
}

.back-link {
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 20px;
    display: inline-block;
}

.back-link:hover {
    color: var(--primary-color);
    text-decoration: none;
}

.verification-actions {
    text-align: center;
    margin: 30px 0;
}
</style>

<div class="verification-container">
    <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Control Center
    </a>

    <div class="verification-header">
        <h1><i class="fas fa-tools"></i> Service Verification & Auto-Fix</h1>
        <p>Automatically diagnose and fix ALX Report API service configuration issues</p>
    </div>

    <?php if ($verification_result): ?>
        <?php if ($verification_result['success']): ?>
            <div class="alert alert-<?php echo $verification_result['service_ready'] ? 'success' : 'warning'; ?>">
                <h4><i class="fas fa-<?php echo $verification_result['service_ready'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                    <?php echo htmlspecialchars($verification_result['message']); ?></h4>
                
                <?php if (!empty($verification_result['issues_found'])): ?>
                    <h5>Issues Found:</h5>
                    <ul class="issues-list">
                        <?php foreach ($verification_result['issues_found'] as $issue): ?>
                            <li><?php echo htmlspecialchars($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($verification_result['fixes_applied'])): ?>
                    <h5>Fixes Applied:</h5>
                    <ul class="fixes-list">
                        <?php foreach ($verification_result['fixes_applied'] as $fix): ?>
                            <li><?php echo htmlspecialchars($fix); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($verification_result['warnings'])): ?>
                    <h5>Warnings:</h5>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($verification_result['warnings'] as $warning): ?>
                            <li style="color: #92400e; padding: 4px 0;">⚠️ <?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Service Status Details</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_status']['webservices_enabled'] ? 'status-ok' : 'status-error'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_status']['webservices_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="status-label">Web Services</div>
                        <div class="status-value"><?php echo $verification_result['service_status']['webservices_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
                    </div>

                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_status']['rest_enabled'] ? 'status-ok' : 'status-error'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_status']['rest_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="status-label">REST Protocol</div>
                        <div class="status-value"><?php echo $verification_result['service_status']['rest_enabled'] ? 'Enabled' : 'Disabled'; ?></div>
                    </div>

                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_status']['service_exists'] ? 'status-ok' : 'status-error'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_status']['service_exists'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="status-label">API Service</div>
                        <div class="status-value"><?php echo $verification_result['service_status']['service_exists'] ? 'Created' : 'Missing'; ?></div>
                    </div>

                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_status']['function_mapped'] ? 'status-ok' : 'status-error'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_status']['function_mapped'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        </div>
                        <div class="status-label">Function Mapping</div>
                        <div class="status-value"><?php echo $verification_result['service_status']['function_mapped'] ? 'Mapped' : 'Not Mapped'; ?></div>
                    </div>

                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_status']['active_tokens'] > 0 ? 'status-ok' : 'status-warning'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_status']['active_tokens'] > 0 ? 'key' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="status-label">Active Tokens</div>
                        <div class="status-value"><?php echo $verification_result['service_status']['active_tokens']; ?> token(s)</div>
                    </div>

                    <div class="status-item">
                        <div class="status-icon <?php echo $verification_result['service_ready'] ? 'status-ok' : 'status-error'; ?>">
                            <i class="fas fa-<?php echo $verification_result['service_ready'] ? 'thumbs-up' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="status-label">Overall Status</div>
                        <div class="status-value"><?php echo $verification_result['service_ready'] ? 'Ready' : 'Needs Attention'; ?></div>
                    </div>
                </div>

                <?php if ($verification_result['service_status']['service_id']): ?>
                    <p><strong>Service ID:</strong> <?php echo $verification_result['service_status']['service_id']; ?> 
                       (<?php echo htmlspecialchars($verification_result['service_status']['service_name']); ?>)</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle"></i> Verification Failed</h4>
                <p><?php echo htmlspecialchars($verification_result['message']); ?></p>
                <?php if (isset($verification_result['error'])): ?>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($verification_result['error']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="verification-actions">
            <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/service_verification.php?action=verify_and_fix" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Run Verification Again
            </a>
            
            <?php if ($verification_result['service_ready']): ?>
                <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=webservicetokens" class="btn btn-success">
                    <i class="fas fa-key"></i> Manage API Tokens
                </a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="card">
            <h3><i class="fas fa-info-circle"></i> About This Tool</h3>
            <p>This tool automatically checks and fixes the common issue where the ALX Report API service is created during installation but the function mapping fails, requiring manual intervention.</p>
            
            <h4>What This Tool Checks:</h4>
            <ul>
                <li><strong>Web Services Configuration:</strong> Ensures web services and REST protocol are enabled</li>
                <li><strong>Service Creation:</strong> Verifies the ALX Report API service exists</li>
                <li><strong>Function Mapping:</strong> Checks if the API function is properly mapped to the service</li>
                <li><strong>Service Status:</strong> Ensures the service is enabled and configured correctly</li>
                <li><strong>Token Availability:</strong> Shows how many API tokens are currently active</li>
            </ul>

            <h4>What This Tool Fixes:</h4>
            <ul>
                <li>Automatically enables web services if disabled</li>
                <li>Enables REST protocol if not configured</li>
                <li>Creates the API service if missing</li>
                <li><strong>Maps the function to the service</strong> (fixes the main issue you reported)</li>
                <li>Enables the service if disabled</li>
                <li>Clears relevant caches to ensure changes take effect</li>
            </ul>
        </div>

        <div class="verification-actions">
            <a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/service_verification.php?action=verify_and_fix" class="btn btn-primary">
                <i class="fas fa-tools"></i> Run Service Verification & Auto-Fix
            </a>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fas fa-question-circle"></i> Need Help?</h3>
        <p>If the automatic fix doesn't resolve your issues, you can:</p>
        <ul>
            <li><a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=externalservices">Manually manage external services</a></li>
            <li><a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=webservicetokens">Create and manage API tokens</a></li>
            <li><a href="<?php echo $CFG->wwwroot; ?>/local/alx_report_api/control_center.php">Return to the Control Center</a></li>
        </ul>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?> 