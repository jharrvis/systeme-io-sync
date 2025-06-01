<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private $core;
    
    public function __construct($core) {
        $this->core = $core;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Universal Systeme.io Sync',
            'Systeme.io Sync',
            'manage_options',
            'universal-systeme-sync',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_universal-systeme-sync' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'universal-systeme-sync-admin',
            UNIVERSAL_SYSTEME_SYNC_URL . 'assets/admin.js',
            array('jquery'),
            UNIVERSAL_SYSTEME_SYNC_VERSION,
            true
        );
        
        wp_localize_script('universal-systeme-sync-admin', 'universal_systeme_sync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'test_api' => wp_create_nonce('test_api_nonce'),
                'test_sync' => wp_create_nonce('test_sync_nonce'),
                'clear_logs' => wp_create_nonce('clear_logs_nonce')
            )
        ));
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting(Core::OPTION_NAME, Core::OPTION_NAME);
        
        add_settings_section(
            'systeme_api_section',
            __('Systeme.io API Settings', 'universal-systeme-sync'),
            array($this, 'api_settings_section_callback'),
            Core::OPTION_NAME
        );
        
        add_settings_section(
            'custom_field_section',
            __('Custom Field Settings', 'universal-systeme-sync'),
            array($this, 'custom_field_section_callback'),
            Core::OPTION_NAME
        );
        
        add_settings_section(
            'performance_section',
            __('Performance Settings', 'universal-systeme-sync'),
            array($this, 'performance_section_callback'),
            Core::OPTION_NAME
        );
        
        add_settings_section(
            'plugin_integration_section',
            __('Plugin Integrations', 'universal-systeme-sync'),
            array($this, 'integration_settings_section_callback'),
            Core::OPTION_NAME
        );
        
        // API Settings
        add_settings_field('api_key', __('Systeme.io API Key', 'universal-systeme-sync'), array($this, 'api_key_render'), Core::OPTION_NAME, 'systeme_api_section');
        add_settings_field('enable_sync', __('Enable Synchronization', 'universal-systeme-sync'), array($this, 'enable_sync_render'), Core::OPTION_NAME, 'systeme_api_section');
        add_settings_field('default_tags', __('Default Tags (optional)', 'universal-systeme-sync'), array($this, 'default_tags_render'), Core::OPTION_NAME, 'systeme_api_section');
        add_settings_field('debug_mode', __('Debug Mode', 'universal-systeme-sync'), array($this, 'debug_mode_render'), Core::OPTION_NAME, 'systeme_api_section');
        
        // Custom Field Settings
        add_settings_field('use_custom_field', __('Use Custom Field', 'universal-systeme-sync'), array($this, 'use_custom_field_render'), Core::OPTION_NAME, 'custom_field_section');
        add_settings_field('custom_field_slug', __('Custom Field Name', 'universal-systeme-sync'), array($this, 'custom_field_slug_render'), Core::OPTION_NAME, 'custom_field_section');
        add_settings_field('use_both_tags_and_fields', __('Use Both Tags and Fields', 'universal-systeme-sync'), array($this, 'use_both_tags_and_fields_render'), Core::OPTION_NAME, 'custom_field_section');
        add_settings_field('custom_field_mappings', __('Custom Field Mappings', 'universal-systeme-sync'), array($this, 'custom_field_mappings_render'), Core::OPTION_NAME, 'custom_field_section');
        
        // Performance Settings
        add_settings_field('background_processing', __('Background Processing', 'universal-systeme-sync'), array($this, 'background_processing_render'), Core::OPTION_NAME, 'performance_section');
        
        // Plugin Integration Settings
        $this->register_integration_fields();
    }
    
    /**
     * Register integration fields
     */
    private function register_integration_fields() {
        $integrations = $this->get_available_integrations();
        
        foreach ($integrations as $key => $integration) {
            add_settings_field(
                'sync_' . $key,
                $integration['name'],
                array($this, 'integration_field_render'),
                Core::OPTION_NAME,
                'plugin_integration_section',
                array('key' => $key, 'integration' => $integration)
            );
        }
    }
    
    /**
     * Get available integrations
     */
    private function get_available_integrations() {
        $integrations_instance = new Integrations($this->core);
        
        return array(
            'amelia' => array(
                'name' => __('Amelia Booking', 'universal-systeme-sync'),
                'description' => __('Sync Amelia Booking appointments and events', 'universal-systeme-sync'),
                'active' => $integrations_instance->is_amelia_active()
            ),
            'woocommerce' => array(
                'name' => __('WooCommerce Orders', 'universal-systeme-sync'),
                'description' => __('Sync WooCommerce customer orders', 'universal-systeme-sync'),
                'active' => class_exists('WooCommerce')
            ),
            'cf7' => array(
                'name' => __('Contact Form 7', 'universal-systeme-sync'),
                'description' => __('Sync Contact Form 7 submissions', 'universal-systeme-sync'),
                'active' => class_exists('WPCF7')
            ),
            'gravity_forms' => array(
                'name' => __('Gravity Forms', 'universal-systeme-sync'),
                'description' => __('Sync Gravity Forms submissions', 'universal-systeme-sync'),
                'active' => class_exists('GFForms')
            ),
            'bookly' => array(
                'name' => __('Bookly', 'universal-systeme-sync'),
                'description' => __('Sync Bookly appointments', 'universal-systeme-sync'),
                'active' => $integrations_instance->is_bookly_active()
            ),
            'wc_bookings' => array(
                'name' => __('WooCommerce Bookings', 'universal-systeme-sync'),
                'description' => __('Sync WooCommerce Bookings', 'universal-systeme-sync'),
                'active' => class_exists('WC_Bookings')
            ),
            'easy_appointments' => array(
                'name' => __('Easy Appointments', 'universal-systeme-sync'),
                'description' => __('Sync Easy Appointments bookings', 'universal-systeme-sync'),
                'active' => $integrations_instance->is_easy_appointments_active()
            ),
            'user_registration' => array(
                'name' => __('User Registration', 'universal-systeme-sync'),
                'description' => __('Sync new user registrations', 'universal-systeme-sync'),
                'active' => true // Always available
            )
        );
    }
    
    // Callback functions
    
    public function api_settings_section_callback() {
        echo __('Configure your Systeme.io API settings', 'universal-systeme-sync');
    }
    
    public function custom_field_section_callback() {
        echo __('Configure custom fields to track products/events/appointments. Useful for free Systeme.io plans with limited tags.', 'universal-systeme-sync');
    }
    
    public function performance_section_callback() {
        echo __('Optimize performance for faster form submissions', 'universal-systeme-sync');
    }
    
    public function integration_settings_section_callback() {
        echo __('Enable synchronization for specific plugins. Only enable the plugins you have installed and want to sync.', 'universal-systeme-sync');
    }
    
    public function api_key_render() {
        $settings = $this->core->get_settings();
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        ?>
        <input type="password" id="api_key" name="<?php echo Core::OPTION_NAME; ?>[api_key]" value="<?php echo esc_attr($api_key); ?>" size="50" />
        <p class="description">Get API key from Settings → Public API keys in your Systeme.io dashboard</p>
        <?php
    }
    
    public function enable_sync_render() {
        $settings = $this->core->get_settings();
        $enable_sync = isset($settings['enable_sync']) ? $settings['enable_sync'] : false;
        ?>
        <input type="checkbox" id="enable_sync" name="<?php echo Core::OPTION_NAME; ?>[enable_sync]" value="1" <?php checked(1, $enable_sync); ?> />
        <label for="enable_sync">Check to enable automatic synchronization</label>
        <?php
    }
    
    public function default_tags_render() {
        $settings = $this->core->get_settings();
        $default_tags = isset($settings['default_tags']) ? $settings['default_tags'] : '';
        ?>
        <input type="text" id="default_tags" name="<?php echo Core::OPTION_NAME; ?>[default_tags]" value="<?php echo esc_attr($default_tags); ?>" size="50" />
        <p class="description">Tags to be added to all contacts (separate with commas if multiple)</p>
        <?php
    }
    
    public function debug_mode_render() {
        $settings = $this->core->get_settings();
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        ?>
        <input type="checkbox" id="debug_mode" name="<?php echo Core::OPTION_NAME; ?>[debug_mode]" value="1" <?php checked(1, $debug_mode); ?> />
        <label for="debug_mode">Enable debug logging (useful for troubleshooting)</label>
        <?php
    }
    
    public function use_custom_field_render() {
        $settings = $this->core->get_settings();
        $use_custom_field = isset($settings['use_custom_field']) ? $settings['use_custom_field'] : false;
        ?>
        <input type="checkbox" id="use_custom_field" name="<?php echo Core::OPTION_NAME; ?>[use_custom_field]" value="1" <?php checked(1, $use_custom_field); ?> />
        <label for="use_custom_field">Use custom field instead of tags (recommended for free plans)</label>
        <p class="description">Enable this to track products/events in a custom field instead of using multiple tags</p>
        <?php
    }
    
    public function custom_field_slug_render() {
        $settings = $this->core->get_settings();
        $custom_field_slug = isset($settings['custom_field_slug']) ? $settings['custom_field_slug'] : 'products';
        ?>
        <input type="text" id="custom_field_slug" name="<?php echo Core::OPTION_NAME; ?>[custom_field_slug]" value="<?php echo esc_attr($custom_field_slug); ?>" />
        <p class="description">The slug (lowercase, no spaces) for the custom field. Default: "products"</p>
        <p class="description"><strong>Note:</strong> When a contact already exists, new values will be appended to the existing custom field value, separated by commas.</p>
        <?php
    }
    
    public function use_both_tags_and_fields_render() {
        $settings = $this->core->get_settings();
        $use_both = isset($settings['use_both_tags_and_fields']) ? $settings['use_both_tags_and_fields'] : false;
        ?>
        <input type="checkbox" id="use_both_tags_and_fields" name="<?php echo Core::OPTION_NAME; ?>[use_both_tags_and_fields]" value="1" <?php checked(1, $use_both); ?> />
        <label for="use_both_tags_and_fields">Also add tags (in addition to custom field)</label>
        <p class="description"><strong>Note:</strong> Free Systeme.io plans have limited tags. If you reach the limit, the plugin will continue without tags.</p>
        <?php
    }
    
    public function custom_field_mappings_render() {
        $settings = $this->core->get_settings();
        $mappings = isset($settings['custom_field_mappings']) ? $settings['custom_field_mappings'] : array();
        ?>
        <div id="custom-field-mappings">
            <p class="description">Map each integration source to a custom value. You can use placeholders:</p>
            <div style="background: #f9f9f9; padding: 10px; margin-bottom: 15px; border-left: 4px solid #0073aa;">
                <strong>Available placeholders:</strong><br>
                <code>{service_name}</code> - Service name (Amelia)<br>
                <code>{event_name}</code> - Event name (Amelia)<br>
                <code>{product_name}</code> - Product name (WooCommerce)<br>
                <code>{form_title}</code> - Form title (Contact Form 7)<br>
                <code>{source}</code> - Integration source<br>
                <code>{date}</code> - Current date<br>
                <code>{datetime}</code> - Current date and time<br>
                <code>{customer_name}</code> - Customer full name<br>
                <code>{email}</code> - Customer email
            </div>
            <?php
            $sources = array(
                'amelia_appointment' => 'Amelia Appointments',
                'amelia_event' => 'Amelia Events',
                'woocommerce' => 'WooCommerce Orders',
                'contact_form_7' => 'Contact Form 7',
                'gravity_forms' => 'Gravity Forms',
                'bookly' => 'Bookly',
                'wc_bookings' => 'WooCommerce Bookings',
                'easy_appointments' => 'Easy Appointments',
                'user_registration' => 'User Registration',
                'manual_test' => 'Manual Test'
            );
            
            foreach ($sources as $source => $label) {
                $value = isset($mappings[$source]) ? $mappings[$source] : '';
                $placeholder = $this->get_placeholder_suggestion($source);
                ?>
                <div style="margin-bottom: 10px;">
                    <label style="display: inline-block; width: 200px;"><?php echo esc_html($label); ?>:</label>
                    <input type="text" 
                           name="<?php echo Core::OPTION_NAME; ?>[custom_field_mappings][<?php echo $source; ?>]" 
                           value="<?php echo esc_attr($value); ?>" 
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           style="width: 300px;" />
                </div>
                <?php
            }
            ?>
            <p class="description" style="margin-top: 10px;">
                Leave empty to use default values. The placeholders will be replaced with actual values when syncing.
            </p>
        </div>
        <?php
    }
    
    /**
     * Get placeholder suggestion for source
     */
    private function get_placeholder_suggestion($source) {
        $suggestions = array(
            'amelia_appointment' => '{service_name}',
            'amelia_event' => '{event_name}',
            'woocommerce' => '{product_name}',
            'contact_form_7' => '{form_title}',
            'gravity_forms' => '{form_title}',
            'bookly' => '{service_name}',
            'wc_bookings' => '{product_name}',
            'easy_appointments' => '{service_name}',
            'user_registration' => 'New User - {date}',
            'manual_test' => 'Test - {datetime}'
        );
        
        return isset($suggestions[$source]) ? $suggestions[$source] : ucfirst(str_replace('_', ' ', $source));
    }
    
    public function background_processing_render() {
        $settings = $this->core->get_settings();
        $background_processing = isset($settings['background_processing']) ? $settings['background_processing'] : false;
        ?>
        <input type="checkbox" id="background_processing" name="<?php echo Core::OPTION_NAME; ?>[background_processing]" value="1" <?php checked(1, $background_processing); ?> />
        <label for="background_processing">Enable background processing for faster form submissions</label>
        <p class="description">When enabled, customer data will be synced to Systeme.io in the background, making form submissions faster.</p>
        <?php
    }
    
    public function integration_field_render($args) {
        $settings = $this->core->get_settings();
        $key = $args['key'];
        $integration = $args['integration'];
        $field_name = 'sync_' . $key;
        $is_enabled = isset($settings[$field_name]) ? $settings[$field_name] : false;
        $is_active = $integration['active'];
        ?>
        <input type="checkbox" id="<?php echo $field_name; ?>" name="<?php echo Core::OPTION_NAME; ?>[<?php echo $field_name; ?>]" value="1" <?php checked(1, $is_enabled); ?> <?php echo $is_active ? '' : 'disabled'; ?> />
        <label for="<?php echo $field_name; ?>"><?php echo esc_html($integration['description']); ?></label>
        <?php if (!$is_active): ?>
            <p class="description" style="color: #d63638;">Plugin not detected</p>
        <?php else: ?>
            <p class="description" style="color: #00a32a;">Plugin detected ✓</p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        include UNIVERSAL_SYSTEME_SYNC_PATH . 'templates/admin-page.php';
    }
}