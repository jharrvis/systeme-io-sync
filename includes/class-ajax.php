<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Ajax {
    
    private $core;
    
    public function __construct($core) {
        $this->core = $core;
        
        // Add AJAX handlers
        add_action('wp_ajax_clear_sync_logs', array($this, 'clear_sync_logs'));
        add_action('wp_ajax_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_test_manual_sync', array($this, 'test_manual_sync'));
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $api_key = $this->core->get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is not set'));
        }
        
        // Test by trying to get contacts list
        $api = $this->core->get_api();
        $response = $api->call_systeme_api_method('contacts?limit=1', null, 'GET');
        
        if ($response['success']) {
            wp_send_json_success(array('message' => 'Connection successful! API key is valid.'));
        } else {
            wp_send_json_error(array('message' => "Connection failed: {$response['message']}"));
        }
    }
    
    /**
     * Test manual sync
     */
    public function test_manual_sync() {
        check_ajax_referer('test_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!$this->core->is_sync_enabled()) {
            wp_send_json_error(array('message' => 'Synchronization is not enabled. Please enable it in settings first.'));
        }
        
        $api_key = $this->core->get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is not set'));
        }
        
        // Create test customer data
        $test_customer = array(
            'email' => 'test@example.com',
            'firstName' => 'Test',
            'lastName' => 'Customer',
            'phone' => '+1234567890',
            'country' => 'US'
        );
        
        $this->core->get_logger()->log('Manual Test: Starting manual sync test with sample data');
        
        $result = $this->core->sync_customer_universal($test_customer, 'manual_test', array('Test Tag'));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Manual sync test completed successfully! Check logs for details.'));
        } else {
            wp_send_json_error(array('message' => 'Manual sync test failed. Check logs for error details.'));
        }
    }
    
    /**
     * Clear sync logs
     */
    public function clear_sync_logs() {
        check_ajax_referer('clear_logs_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        delete_option(Core::LOG_OPTION);
        wp_send_json_success(array('message' => 'Logs cleared successfully'));
    }
}