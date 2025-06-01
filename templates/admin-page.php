<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Universal Systeme.io Sync</h1>
    
    <div class="notice notice-info">
        <p><strong>Information:</strong> This plugin provides universal synchronization to Systeme.io from multiple sources. Enable only the integrations you need.</p>
    </div>
    
    <form action="options.php" method="post">
        <?php
        settings_fields(\UniversalSystemeSync\Core::OPTION_NAME);
        do_settings_sections(\UniversalSystemeSync\Core::OPTION_NAME);
        submit_button('Save Settings');
        ?>
    </form>
    
    <hr>
    
    <h2>Developer Integration</h2>
    <p>Other plugins can use these hooks to sync customer data:</p>
    <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
        <h4>Primary Hook:</h4>
        <code>do_action('systeme_sync_customer', $customer_data, $source, $tags);</code>
        
        <h4>Alternative Hooks:</h4>
        <code>do_action('systeme_sync_contact', $customer_data, $source, $tags);</code><br>
        <code>do_action('universal_systeme_sync', $customer_data, $source, $tags);</code>
        
        <h4>Helper Function:</h4>
        <code>UniversalSystemeSync\Core::sync_customer_data($customer_data, $source, $tags);</code>
        
        <h4>Example Usage:</h4>
        <pre style="background: #fff; padding: 10px; margin-top: 10px;">
$customer = array(
    'email' => 'customer@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '+1234567890',
    'country' => 'US',
    'product' => 'Premium Package', // Optional: for custom field
    'event' => 'Workshop 2024'      // Optional: for custom field
);

do_action('systeme_sync_customer', $customer, 'my_plugin', array('tag1', 'tag2'));
        </pre>
        
        <h4>Systeme.io Field Mapping:</h4>
        <p>The plugin will automatically map your data to Systeme.io's field structure:</p>
        <ul>
            <li><code>firstName</code> → <code>first_name</code></li>
            <li><code>lastName</code> → <code>surname</code></li>
            <li><code>phone</code> → <code>phone_number</code></li>
            <li><code>country</code> → <code>country</code> (2-letter ISO code)</li>
            <li><code>product/event/service</code> → Custom field (if enabled)</li>
        </ul>
        
        <h4>Custom Field Support:</h4>
        <p>When custom field is enabled, the plugin will automatically create and populate a custom field in Systeme.io to track products, events, or services. This is especially useful for free Systeme.io plans with limited tags.</p>
    </div>
    
    <hr>
    
    <h2>Test API Connection</h2>
    <p>Click the button below to test connection to Systeme.io API:</p>
    <button type="button" class="button button-secondary" id="test-api-btn">Test API Connection</button>
    <div id="api-test-result"></div>
    
    <hr>
    
    <h2>Test Manual Sync</h2>
    <p>Test synchronization with sample customer data:</p>
    <button type="button" class="button button-secondary" id="test-sync-btn">Test Manual Sync</button>
    <div id="sync-test-result"></div>
    
    <hr>
    
    <h2>Synchronization Log</h2>
    <button type="button" class="button button-secondary" id="clear-logs-btn">Clear Logs</button>
    <div id="sync-logs" style="margin-top: 10px;">
        <?php 
        $logger = universal_systeme_sync()->get_logger();
        $logger->display_logs(); 
        ?>
    </div>
</div>