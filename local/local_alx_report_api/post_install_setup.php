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
 * Post-installation setup script for ALX Report API plugin.
 * This script creates the initial admin token after fresh installation.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/alx_report_api/post_install_setup.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Post Installation Setup');
$PAGE->set_heading('ALX Report API - Post Installation Setup');

echo $OUTPUT->header();

$success = false;
$message = '';
$token = '';

try {
    $dbman = $DB->get_manager();
    
    // Check if tables exist
    $tables_needed = [
        'local_alx_api_tokens',
        'local_alx_api_cache',
        'local_alx_api_logs', 
        'local_alx_reporting_data'
    ];
    
    $missing_tables = [];
    foreach ($tables_needed as $table_name) {
        if (!$dbman->table_exists($table_name)) {
            $missing_tables[] = $table_name;
        }
    }
    
    if (!empty($missing_tables)) {
        $message = 'ERROR: Missing database tables: ' . implode(', ', $missing_tables) . 
                  '. Please complete the plugin installation first.';
    } else {
        // Create initial admin token if none exists
        if (!$DB->record_exists('local_alx_api_tokens', [])) {
            $tokendata = new stdClass();
            $tokendata->token = bin2hex(random_bytes(32));
            $tokendata->companyid = 0; // System token
            $tokendata->company_shortname = 'system';
            $tokendata->created = time();
            $tokendata->expires = 0; // Never expires
            $tokendata->is_active = 1;
            
            $DB->insert_record('local_alx_api_tokens', $tokendata);
            
            $success = true;
            $token = $tokendata->token;
            $message = 'SUCCESS: Fresh installation completed! Initial admin token created.';
            
            // Log success
            error_log("ALX Report API Post-Install: Initial admin token created successfully");
            
        } else {
            // Get existing system token
            $existing_token = $DB->get_record('local_alx_api_tokens', ['companyid' => 0], '*', IGNORE_MULTIPLE);
            if ($existing_token) {
                $success = true;
                $token = $existing_token->token;
                $message = 'SUCCESS: Installation already completed. Using existing admin token.';
            } else {
                $message = 'ERROR: Tokens exist but no system token found. Please check the installation.';
            }
        }
    }
    
} catch (Exception $e) {
    $message = 'ERROR: ' . $e->getMessage();
    error_log('ALX Report API Post-Install Error: ' . $e->getMessage());
}

?>

<div class="card">
    <div class="card-body">
        <h3>ALX Report API - Fresh Installation Setup</h3>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <h4><i class="fa fa-check-circle"></i> Installation Successful!</h4>
                <p><?php echo $message; ?></p>
                
                <div class="mt-3 p-3 bg-light border rounded">
                    <h5>Your Admin API Token:</h5>
                    <code style="font-size: 14px; word-break: break-all;"><?php echo $token; ?></code>
                    <button class="btn btn-sm btn-secondary ml-2" onclick="copyToken()">Copy Token</button>
                </div>
                
                <div class="mt-3">
                    <h5>Next Steps:</h5>
                    <ol>
                        <li>Save the admin token above in a secure location</li>
                        <li>Visit the <a href="control_center.php">ALX Report API Control Center</a></li>
                        <li>Configure company settings and generate additional tokens as needed</li>
                        <li>Test the API endpoints using the provided token</li>
                    </ol>
                </div>
                
                <div class="mt-3">
                    <a href="control_center.php" class="btn btn-primary">
                        <i class="fa fa-dashboard"></i> Go to Control Center
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=local_alx_report_api" class="btn btn-secondary">
                        <i class="fa fa-cog"></i> Plugin Settings
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4><i class="fa fa-exclamation-triangle"></i> Installation Issue</h4>
                <p><?php echo $message; ?></p>
                
                <div class="mt-3">
                    <h5>Troubleshooting Steps:</h5>
                    <ol>
                        <li>Ensure the plugin installation completed successfully</li>
                        <li>Check Moodle error logs for detailed information</li>
                        <li>Verify database permissions</li>
                        <li>Try reinstalling the plugin if necessary</li>
                    </ol>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-warning" onclick="location.reload()">
                        <i class="fa fa-refresh"></i> Retry Setup
                    </button>
                    <a href="<?php echo $CFG->wwwroot; ?>/admin/plugins.php" class="btn btn-secondary">
                        <i class="fa fa-list"></i> Manage Plugins
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <h5>System Information:</h5>
            <ul>
                <li><strong>Plugin Version:</strong> 1.4.0</li>
                <li><strong>Installation Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                <li><strong>Moodle Version:</strong> <?php echo $CFG->version; ?></li>
                <li><strong>Web Services:</strong> <?php echo $CFG->enablewebservices ? 'Enabled' : 'Disabled'; ?></li>
                <li><strong>REST Protocol:</strong> <?php echo (strpos($CFG->webserviceprotocols ?? '', 'rest') !== false) ? 'Enabled' : 'Disabled'; ?></li>
            </ul>
        </div>
    </div>
</div>

<script>
function copyToken() {
    const tokenText = '<?php echo $token; ?>';
    navigator.clipboard.writeText(tokenText).then(function() {
        alert('Token copied to clipboard!');
    }, function(err) {
        console.error('Could not copy token: ', err);
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = tokenText;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Token copied to clipboard!');
    });
}
</script>

<?php
echo $OUTPUT->footer();
?> 