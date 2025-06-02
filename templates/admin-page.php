<?php
// Admin page template
if (!defined('ABSPATH')) {
    exit;
}

$settings = $this->core->get_settings();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Settings saved successfully!</strong></p>
        </div>
    <?php endif; ?>
    
    <!-- Free Plan Notice -->
    <div class="notice notice-info">
        <h3>📋 Important Information for Free Plan Users</h3>
        <p><strong>If you're using Systeme.io Free Plan:</strong></p>
        <ul>
            <li>✅ <strong>Enable "Use Custom Field"</strong> option below instead of using tags</li>
            <li>✅ Free plans have limited tags, but unlimited custom fields</li>
            <li>✅ Custom fields work just as well for tracking customers and purchases</li>
            <li>❌ Avoid using tags if you're on the free plan to prevent sync errors</li>
        </ul>
        <p><em>This plugin will automatically handle plan limitations and continue syncing successfully.</em></p>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields(\UniversalSystemeSync\Core::OPTION_NAME);
        do_settings_sections(\UniversalSystemeSync\Core::OPTION_NAME);
        ?>
        
        <table class="form-table">
            <!-- API Settings Section will be rendered here by WordPress -->
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <!-- Test Section -->
    <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
        <h2>🧪 Test Connection & Sync</h2>
        <p>Use these buttons to test your configuration:</p>
        
        <p>
            <button type="button" id="test-api-connection" class="button button-secondary">
                Test API Connection
            </button>
            <span class="description">Check if your API key is valid and working</span>
        </p>
        
        <p>
            <button type="button" id="test-manual-sync" class="button button-secondary">
                Test Manual Sync
            </button>
            <span class="description">Send a test customer to Systeme.io</span>
        </p>
        
        <div id="test-results" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Sync Logs Section -->
    <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
        <h2>📊 Synchronization Logs</h2>
        <p>Recent sync activities and debug information:</p>
        
        <p>
            <button type="button" id="clear-logs" class="button button-secondary">
                Clear Logs
            </button>
            <span class="description">Remove all log entries</span>
        </p>
        
        <div style="margin-top: 15px;">
            <?php
            $logger = $this->core->get_logger();
            $logger->display_logs();
            ?>
        </div>
    </div>
    
    <!-- Integration Guide -->
    <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7;">
        <h2>🔗 Integration Guide</h2>
        <p><strong>How to use this plugin with other plugins:</strong></p>
        
        <h3>Option 1: Automatic Integration</h3>
        <p>Enable the plugins you want to sync in the "Plugin Integrations" section above. The plugin will automatically sync customers when:</p>
        <ul>
            <li>🛒 WooCommerce orders are placed</li>
            <li>📝 Contact Form 7 forms are submitted</li>
            <li>📋 Gravity Forms are submitted</li>
            <li>📅 Amelia bookings are made</li>
            <li>📅 Bookly appointments are created</li>
            <li>👤 New users register</li>
        </ul>
        
        <h3>Option 2: Manual Integration (For Developers)</h3>
        <p>Other plugins can integrate using this simple code:</p>
        <pre style="background: #f1f1f1; padding: 10px; overflow-x: auto;"><code><?php echo esc_html('
// Example: Sync a customer manually
$customer_data = array(
    \'email\' => \'customer@example.com\',
    \'firstName\' => \'John\',
    \'lastName\' => \'Doe\',
    \'phone\' => \'+1234567890\',
    \'country\' => \'US\'
);

$additional_tags = array(\'Custom Tag\', \'Another Tag\');

// Trigger the sync
do_action(\'systeme_sync_customer\', $customer_data, \'my_plugin\', $additional_tags);
'); ?></code></pre>
        
        <h3>Custom Field Placeholders</h3>
        <p>When using custom field mappings, you can use these placeholders:</p>
        <ul>
            <li><code>{service_name}</code> - Service name from Amelia/Bookly</li>
            <li><code>{event_name}</code> - Event name from Amelia</li>
            <li><code>{product_name}</code> - Product name from WooCommerce</li>
            <li><code>{form_title}</code> - Form title from Contact Form 7/Gravity Forms</li>
            <li><code>{customer_name}</code> - Full customer name</li>
            <li><code>{email}</code> - Customer email</li>
            <li><code>{date}</code> - Current date (YYYY-MM-DD)</li>
            <li><code>{datetime}</code> - Current date and time</li>
        </ul>
    </div>
    
    <!-- Troubleshooting -->
    <div style="margin-top: 40px; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb;">
        <h2>🔧 Troubleshooting</h2>
        
        <h3>Common Issues & Solutions:</h3>
        
        <h4>❌ "Tag limit reached" errors:</h4>
        <ul>
            <li>✅ Enable "Use Custom Field" option instead of tags</li>
            <li>✅ Free plans are limited to ~10 tags, but have unlimited custom fields</li>
            <li>✅ The plugin will automatically handle this and continue syncing</li>
        </ul>
        
        <h4>❌ Service/Event names not syncing from Amelia:</h4>
        <ul>
            <li>✅ The plugin now automatically fetches service/event names from the database</li>
            <li>✅ Enable debug mode to see what data is being captured</li>
            <li>✅ Use custom field mappings with placeholders like <code>{service_name}</code></li>
        </ul>
        
        <h4>❌ API connection fails:</h4>
        <ul>
            <li>✅ Check that your API key is correct (copy from Systeme.io dashboard)</li>
            <li>✅ Ensure your website can make outbound HTTPS requests</li>
            <li>✅ Try the "Test API Connection" button above</li>
        </ul>
        
        <h4>❌ Forms not syncing:</h4>
        <ul>
            <li>✅ Make sure the plugin integration is enabled for that form plugin</li>
            <li>✅ Check that form fields use standard names (email, name, phone)</li>
            <li>✅ Enable debug mode to see what data is being captured</li>
        </ul>
        
        <p><strong>Still having issues?</strong> Enable "Debug Mode" above and check the logs for detailed error information.</p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test API Connection
    $('#test-api-connection').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing API connection...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_api_connection',
                nonce: universal_systeme_sync.nonces.test_api
            },
            success: function(response) {
                if (response.success) {
                    $('#test-results').html('<div class="notice notice-success inline"><p><strong>✅ ' + response.data.message + '</strong></p></div>');
                } else {
                    $('#test-results').html('<div class="notice notice-error inline"><p><strong>❌ ' + response.data.message + '</strong></p></div>');
                }
            },
            error: function() {
                $('#test-results').html('<div class="notice notice-error inline"><p><strong>❌ Connection test failed</strong></p></div>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Test Manual Sync
    $('#test-manual-sync').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.text('Testing...').prop('disabled', true);
        $('#test-results').html('<p>Testing manual sync...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_manual_sync',
                nonce: universal_systeme_sync.nonces.test_sync
            },
            success: function(response) {
                if (response.success) {
                    $('#test-results').html('<div class="notice notice-success inline"><p><strong>✅ ' + response.data.message + '</strong></p></div>');
                } else {
                    $('#test-results').html('<div class="notice notice-error inline"><p><strong>❌ ' + response.data.message + '</strong></p></div>');
                }
            },
            error: function() {
                $('#test-results').html('<div class="notice notice-error inline"><p><strong>❌ Manual sync test failed</strong></p></div>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
                // Reload the page after 2 seconds to show updated logs
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        });
    });
    
    // Clear Logs
    $('#clear-logs').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        if (!confirm('Are you sure you want to clear all logs?')) {
            return;
        }
        
        button.text('Clearing...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_sync_logs',
                nonce: universal_systeme_sync.nonces.clear_logs
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to clear logs');
                }
            },
            error: function() {
                alert('Failed to clear logs');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>