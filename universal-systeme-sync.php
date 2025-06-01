<?php
/**
 * Plugin Name: Universal Systeme.io Sync
 * Description: Universal plugin to sync customer data from any booking plugin to Systeme.io using hooks and API
 * Version: 2.3.0
 * Author: Julian H
 * Text Domain: universal-systeme-sync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UNIVERSAL_SYSTEME_SYNC_VERSION', '2.1.0');
define('UNIVERSAL_SYSTEME_SYNC_PATH', plugin_dir_path(__FILE__));
define('UNIVERSAL_SYSTEME_SYNC_URL', plugin_dir_url(__FILE__));
define('UNIVERSAL_SYSTEME_SYNC_BASENAME', plugin_basename(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'UniversalSystemeSync\\';
    $base_dir = UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load required files
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-core.php';
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-api.php';
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-admin.php';
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-integrations.php';
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-ajax.php';
require_once UNIVERSAL_SYSTEME_SYNC_PATH . 'includes/class-logger.php';

// Initialize the plugin
function universal_systeme_sync_init() {
    global $universal_systeme_sync;
    $universal_systeme_sync = new UniversalSystemeSync\Core();
}
add_action('plugins_loaded', 'universal_systeme_sync_init');

// Global function for easy access
if (!function_exists('universal_systeme_sync')) {
    function universal_systeme_sync() {
        global $universal_systeme_sync;
        return $universal_systeme_sync;
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    $default_settings = array(
        'enable_sync' => false,
        'api_key' => '',
        'default_tags' => '',
        'debug_mode' => false,
        'use_custom_field' => false,
        'custom_field_slug' => 'products',
        'use_both_tags_and_fields' => false,
        'custom_field_mappings' => array(),
        'background_processing' => false,
        'sync_amelia' => false,
        'sync_woocommerce' => false,
        'sync_cf7' => false,
        'sync_gravity_forms' => false,
        'sync_bookly' => false,
        'sync_wc_bookings' => false,
        'sync_easy_appointments' => false,
        'sync_user_registration' => false
    );
    
    if (!get_option('universal_systeme_settings')) {
        add_option('universal_systeme_settings', $default_settings);
    }
    
    // Schedule cron job for background processing
    if (!wp_next_scheduled('systeme_sync_process_queue')) {
        wp_schedule_event(time(), 'every_minute', 'systeme_sync_process_queue');
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled cron job
    wp_clear_scheduled_hook('systeme_sync_process_queue');
});