<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Core {
    
    const OPTION_NAME = 'universal_systeme_settings';
    const LOG_OPTION = 'universal_systeme_logs';
    
    private $settings;
    private $api;
    private $admin;
    private $integrations;
    private $ajax;
    private $logger;
    
    public function __construct() {
        $this->settings = get_option(self::OPTION_NAME, array());
        
        // Initialize components
        $this->logger = new Logger();
        $this->api = new API($this->settings, $this->logger);
        $this->integrations = new Integrations($this);
        
        // Initialize admin only if in admin area
        if (is_admin()) {
            $this->admin = new Admin($this);
            $this->ajax = new Ajax($this);
        }
        
        // Register universal hooks
        $this->register_universal_hooks();
    }
    
    /**
     * Register universal hooks that any plugin can use
     */
    private function register_universal_hooks() {
        // Main sync hook - any plugin can trigger this
        add_action('systeme_sync_customer', array($this, 'sync_customer_universal'), 10, 3);
        
        // Alternative hook names for flexibility
        add_action('systeme_sync_contact', array($this, 'sync_customer_universal'), 10, 3);
        add_action('universal_systeme_sync', array($this, 'sync_customer_universal'), 10, 3);
        
        // Background processing hook
        add_action('systeme_sync_process_background', array($this, 'process_background_sync'), 10, 1);
    }
    
    /**
     * Universal customer sync function - can be called by any plugin
     */
    public function sync_customer_universal($customer_data, $source = 'unknown', $additional_tags = array()) {
        if (!$this->is_sync_enabled()) {
            return false;
        }
        
        // Apply filters to allow customization
        $customer_data = apply_filters('systeme_sync_customer_data', $customer_data, $source);
        $additional_tags = apply_filters('systeme_sync_additional_tags', $additional_tags, $source, $customer_data);
        
        // Check if background processing is enabled
        if ($this->is_background_processing_enabled()) {
            // Schedule background processing
            $this->schedule_background_sync($customer_data, $source, $additional_tags);
            return true;
        } else {
            // Process immediately
            return $this->api->send_customer_to_systeme($customer_data, $source, $additional_tags);
        }
    }
    
    /**
     * Schedule background sync
     */
    private function schedule_background_sync($customer_data, $source, $additional_tags) {
        $sync_data = array(
            'customer_data' => $customer_data,
            'source' => $source,
            'additional_tags' => $additional_tags
        );
        
        // Use WP Cron to process in background
        wp_schedule_single_event(time(), 'systeme_sync_process_background', array($sync_data));
        
        $this->logger->log("Info: Scheduled background sync for " . $customer_data['email']);
    }
    
    /**
     * Process background sync
     */
    public function process_background_sync($sync_data) {
        $this->api->send_customer_to_systeme(
            $sync_data['customer_data'],
            $sync_data['source'],
            $sync_data['additional_tags']
        );
    }
    
    /**
     * Check if background processing is enabled
     */
    public function is_background_processing_enabled() {
        return isset($this->settings['background_processing']) && $this->settings['background_processing'];
    }
    
    /**
     * Static helper function for other plugins to use
     */
    public static function sync_customer_data($customer_data, $source = 'external', $additional_tags = array()) {
        $instance = universal_systeme_sync();
        if ($instance) {
            return $instance->sync_customer_universal($customer_data, $source, $additional_tags);
        }
        return false;
    }
    
    // Getters
    public function get_settings() {
        return $this->settings;
    }
    
    public function get_api() {
        return $this->api;
    }
    
    public function get_logger() {
        return $this->logger;
    }
    
    public function is_sync_enabled() {
        return isset($this->settings['enable_sync']) && $this->settings['enable_sync'];
    }
    
    public function is_plugin_sync_enabled($plugin) {
        $key = 'sync_' . $plugin;
        return isset($this->settings[$key]) && $this->settings[$key];
    }
    
    public function get_api_key() {
        return isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
    }
    
    public function get_default_tags() {
        return isset($this->settings['default_tags']) ? $this->settings['default_tags'] : '';
    }
    
    public function is_debug_mode() {
        return isset($this->settings['debug_mode']) && $this->settings['debug_mode'];
    }
}