<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Integrations {
    
    private $core;
    
    public function __construct($core) {
        $this->core = $core;
        
        // Initialize hooks after all plugins are loaded
        add_action('init', array($this, 'init_integrations'), 20);
    }
    
    /**
     * Initialize all integrations
     */
    public function init_integrations() {
        $this->init_universal_hooks();
        $this->init_plugin_specific_hooks();
    }
    
    /**
     * Initialize universal WordPress hooks
     */
    private function init_universal_hooks() {
        // WooCommerce hooks
        if ($this->core->is_plugin_sync_enabled('woocommerce') && class_exists('WooCommerce')) {
            add_action('woocommerce_new_order', array($this, 'sync_woocommerce_customer'), 10, 1);
            add_action('woocommerce_checkout_order_processed', array($this, 'sync_woocommerce_customer'), 10, 1);
        }
        
        // Contact Form 7 hooks
        if ($this->core->is_plugin_sync_enabled('cf7') && class_exists('WPCF7')) {
            add_action('wpcf7_mail_sent', array($this, 'sync_cf7_customer'), 10, 1);
        }
        
        // Gravity Forms hooks
        if ($this->core->is_plugin_sync_enabled('gravity_forms') && class_exists('GFForms')) {
            add_action('gform_after_submission', array($this, 'sync_gravity_forms_customer'), 10, 2);
        }
        
        // User registration hooks
        if ($this->core->is_plugin_sync_enabled('user_registration')) {
            add_action('user_register', array($this, 'sync_new_user'), 10, 1);
        }
    }
    
    /**
     * Initialize plugin-specific hooks (Amelia, Bookly, etc.)
     */
    private function init_plugin_specific_hooks() {
        // Amelia Booking hooks
        if ($this->core->is_plugin_sync_enabled('amelia') && $this->is_amelia_active()) {
            add_action('amelia_after_booking_added', array($this, 'sync_amelia_customer'), 10, 1);
            add_action('amelia_after_appointment_booking_saved', array($this, 'sync_amelia_customer_saved'), 10, 2);
            add_action('amelia_after_event_booking_saved', array($this, 'sync_amelia_event_customer'), 10, 2);
        }
        
        // Bookly hooks
        if ($this->core->is_plugin_sync_enabled('bookly') && $this->is_bookly_active()) {
            add_action('bookly_appointment_created', array($this, 'sync_bookly_customer'), 10, 1);
            add_action('bookly_customer_created', array($this, 'sync_bookly_customer_created'), 10, 1);
        }
        
        // Easy Appointments hooks
        if ($this->core->is_plugin_sync_enabled('easy_appointments') && $this->is_easy_appointments_active()) {
            add_action('ea_appointment_created', array($this, 'sync_easy_appointments_customer'), 10, 1);
            add_action('easyapp_after_appointment_save', array($this, 'sync_easy_appointments_customer'), 10, 1);
        }
        
        // WooCommerce Bookings hooks
        if ($this->core->is_plugin_sync_enabled('wc_bookings') && class_exists('WC_Bookings')) {
            add_action('woocommerce_booking_confirmed', array($this, 'sync_wc_booking_customer'), 10, 1);
            add_action('woocommerce_new_booking', array($this, 'sync_wc_booking_customer'), 10, 1);
        }
    }
    
    /**
     * Check if Amelia is active
     */
    public function is_amelia_active() {
        return class_exists('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationHook') ||
               class_exists('AmeliaBooking\Plugin') ||
               defined('AMELIA_VERSION');
    }
    
    /**
     * Check if Bookly is active
     */
    public function is_bookly_active() {
        return class_exists('Bookly\Lib\Plugin') ||
               class_exists('BooklyPlugin') ||
               defined('BOOKLY_VERSION');
    }
    
    /**
     * Check if Easy Appointments is active
     */
    public function is_easy_appointments_active() {
        return class_exists('Easy_Appointments') ||
               function_exists('ea_bootstrap') ||
               defined('EA_VERSION');
    }
    
    // Plugin-specific sync methods
    
    /**
     * Sync Amelia customer
     */
    public function sync_amelia_customer($appointment) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if (isset($appointment['bookings']) && is_array($appointment['bookings'])) {
            foreach ($appointment['bookings'] as $booking) {
                if (isset($booking['customer'])) {
                    // Pass the entire appointment data for service/event info
                    $customer_data = $this->prepare_amelia_customer_data($booking['customer'], $appointment);
                    $this->core->sync_customer_universal($customer_data, 'amelia_appointment', array('Amelia Customer'));
                }
            }
        }
    }
    
    /**
     * Sync Amelia customer (saved hook)
     */
    public function sync_amelia_customer_saved($booking, $reservation) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if (isset($booking['customer'])) {
            // Pass booking data which may contain service info
            $customer_data = $this->prepare_amelia_customer_data($booking['customer'], $booking);
            $this->core->sync_customer_universal($customer_data, 'amelia_appointment', array('Amelia Customer'));
        }
    }
    
    /**
     * Sync Amelia event customer
     */
    public function sync_amelia_event_customer($booking, $reservation) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if (isset($booking['customer'])) {
            // Pass booking data which may contain event info
            $customer_data = $this->prepare_amelia_customer_data($booking['customer'], $booking);
            $this->core->sync_customer_universal($customer_data, 'amelia_event', array('Amelia Event'));
        }
    }
    
    /**
     * Prepare Amelia customer data
     */
    private function prepare_amelia_customer_data($customer, $booking_data = array()) {
        $data = array();
        
        // Map Amelia fields to our standard fields
        if (isset($customer['email'])) $data['email'] = $customer['email'];
        if (isset($customer['firstName'])) $data['firstName'] = $customer['firstName'];
        if (isset($customer['lastName'])) $data['lastName'] = $customer['lastName'];
        
        // Handle phone number - Amelia stores it in different fields
        if (isset($customer['phone'])) {
            $data['phone'] = $customer['phone'];
        } elseif (isset($customer['phoneNumber'])) {
            $data['phone'] = $customer['phoneNumber'];
        }
        
        // Handle country code
        if (isset($customer['countryPhoneIso'])) {
            $data['country'] = $customer['countryPhoneIso'];
        }
        
        // Add product/service information if available
        if (isset($booking_data['service']) && isset($booking_data['service']['name'])) {
            $data['service'] = $booking_data['service']['name'];
        }
        if (isset($booking_data['event']) && isset($booking_data['event']['name'])) {
            $data['event'] = $booking_data['event']['name'];  
        }
        
        return $data;
    }
    
    /**
     * Sync WooCommerce customer
     */
    public function sync_woocommerce_customer($order_id) {
        if (!$this->core->is_plugin_sync_enabled('woocommerce')) return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $customer_data = array(
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'country' => $order->get_billing_country()
        );
        
        // Add product information
        $products = array();
        foreach ($order->get_items() as $item) {
            $products[] = $item->get_name();
        }
        if (!empty($products)) {
            $customer_data['product'] = implode(', ', $products);
        }
        
        $this->core->sync_customer_universal($customer_data, 'woocommerce', array('WooCommerce Customer'));
    }
    
    /**
     * Sync Contact Form 7 customer
     */
    public function sync_cf7_customer($contact_form) {
        if (!$this->core->is_plugin_sync_enabled('cf7')) return;
        
        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) return;
        
        $posted_data = $submission->get_posted_data();
        
        // Get form title
        $form_title = $contact_form->title();
        
        // Debug log
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: CF7 posted data: " . json_encode($posted_data));
            $this->core->get_logger()->log("Debug: CF7 form title: " . $form_title);
        }
        
        // Try to extract email and name from common field names
        $customer_data = array();
        
        // Add form title to customer data
        $customer_data['form_title'] = $form_title;
        $customer_data['form_name'] = $form_title;
        
        // Common email field names - expanded list
        $email_fields = array('your-email', 'email', 'email-address', 'user_email', 'customer_email', 'e-mail', 'mail');
        foreach ($email_fields as $field) {
            if (isset($posted_data[$field]) && is_email($posted_data[$field])) {
                $customer_data['email'] = sanitize_email($posted_data[$field]);
                break;
            }
        }
        
        // Common name field names - expanded list
        $name_fields = array('your-name', 'name', 'full-name', 'fullname', 'user_name', 'customer_name', 'nama');
        foreach ($name_fields as $field) {
            if (isset($posted_data[$field]) && !empty($posted_data[$field])) {
                $name_parts = explode(' ', trim($posted_data[$field]), 2);
                $customer_data['firstName'] = $name_parts[0];
                $customer_data['lastName'] = isset($name_parts[1]) ? $name_parts[1] : '';
                break;
            }
        }
        
        // Try separate first/last name fields
        $first_name_fields = array('first-name', 'firstname', 'first_name', 'fname');
        foreach ($first_name_fields as $field) {
            if (isset($posted_data[$field]) && !empty($posted_data[$field])) {
                $customer_data['firstName'] = trim($posted_data[$field]);
                break;
            }
        }
        
        $last_name_fields = array('last-name', 'lastname', 'last_name', 'surname', 'lname');
        foreach ($last_name_fields as $field) {
            if (isset($posted_data[$field]) && !empty($posted_data[$field])) {
                $customer_data['lastName'] = trim($posted_data[$field]);
                break;
            }
        }
        
        // Phone field - expanded list
        $phone_fields = array('phone', 'your-phone', 'phone-number', 'phone_number', 'tel', 'telephone', 'mobile', 'hp', 'telepon');
        foreach ($phone_fields as $field) {
            if (isset($posted_data[$field]) && !empty($posted_data[$field])) {
                $customer_data['phone'] = trim($posted_data[$field]);
                break;
            }
        }
        
        // If no name found, use email prefix as firstName
        if (empty($customer_data['firstName']) && !empty($customer_data['email'])) {
            $email_parts = explode('@', $customer_data['email']);
            $customer_data['firstName'] = $email_parts[0];
        }
        
        if (!empty($customer_data['email'])) {
            $this->core->get_logger()->log("Info: Processing CF7 submission from " . $customer_data['email'] . " (Form: " . $form_title . ")");
            $this->core->sync_customer_universal($customer_data, 'contact_form_7', array('CF7 Lead'));
        } else {
            $this->core->get_logger()->log("Warning: No email found in CF7 submission");
        }
    }
    
    /**
     * Sync Gravity Forms customer
     */
    public function sync_gravity_forms_customer($entry, $form) {
        if (!$this->core->is_plugin_sync_enabled('gravity_forms')) return;
        
        $customer_data = array();
        
        // Add form title
        $customer_data['form_title'] = $form['title'];
        $customer_data['form_name'] = $form['title'];
        
        // Find email field
        foreach ($form['fields'] as $field) {
            if ($field->type == 'email') {
                $customer_data['email'] = rgar($entry, $field->id);
                break;
            }
        }
        
        // Find name fields
        foreach ($form['fields'] as $field) {
            if ($field->type == 'name') {
                $customer_data['firstName'] = rgar($entry, $field->id . '.3');
                $customer_data['lastName'] = rgar($entry, $field->id . '.6');
                break;
            }
        }
        
        // Find phone field
        foreach ($form['fields'] as $field) {
            if ($field->type == 'phone') {
                $customer_data['phone'] = rgar($entry, $field->id);
                break;
            }
        }
        
        if (!empty($customer_data['email'])) {
            $this->core->sync_customer_universal($customer_data, 'gravity_forms', array('GF Lead'));
        }
    }
    
    /**
     * Sync new user registration
     */
    public function sync_new_user($user_id) {
        if (!$this->core->is_plugin_sync_enabled('user_registration')) return;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $customer_data = array(
            'email' => $user->user_email,
            'firstName' => $user->first_name ?: $user->display_name,
            'lastName' => $user->last_name
        );
        
        // Get user meta for phone if exists
        $phone = get_user_meta($user_id, 'phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
        }
        if (!empty($phone)) {
            $customer_data['phone'] = $phone;
        }
        
        // Get country from user meta
        $country = get_user_meta($user_id, 'billing_country', true);
        if (!empty($country)) {
            $customer_data['country'] = $country;
        }
        
        $this->core->sync_customer_universal($customer_data, 'user_registration', array('WordPress User'));
    }
    
    /**
     * Sync Bookly customer
     */
    public function sync_bookly_customer($appointment_data) {
        if (!$this->core->is_plugin_sync_enabled('bookly')) return;
        
        // Try to extract customer data from Bookly appointment
        if (is_array($appointment_data)) {
            $customer_data = array();
            
            // Map common Bookly fields
            if (isset($appointment_data['customer_email'])) {
                $customer_data['email'] = $appointment_data['customer_email'];
            }
            if (isset($appointment_data['customer_name'])) {
                $name_parts = explode(' ', $appointment_data['customer_name'], 2);
                $customer_data['firstName'] = $name_parts[0];
                $customer_data['lastName'] = isset($name_parts[1]) ? $name_parts[1] : '';
            }
            if (isset($appointment_data['customer_phone'])) {
                $customer_data['phone'] = $appointment_data['customer_phone'];
            }
            
            // Add service name if available
            if (isset($appointment_data['service_name'])) {
                $customer_data['service'] = $appointment_data['service_name'];
            }
            
            if (!empty($customer_data['email'])) {
                $this->core->sync_customer_universal($customer_data, 'bookly', array('Bookly Customer'));
            }
        } elseif (is_object($appointment_data)) {
            // Handle Bookly object format
            $this->sync_bookly_customer_object($appointment_data);
        }
    }
    
    /**
     * Sync Bookly customer (created hook)
     */
    public function sync_bookly_customer_created($customer) {
        if (!$this->core->is_plugin_sync_enabled('bookly')) return;
        
        $customer_data = array();
        
        // Handle Bookly customer object
        if (method_exists($customer, 'getEmail')) {
            $customer_data['email'] = $customer->getEmail();
        }
        if (method_exists($customer, 'getFirstName')) {
            $customer_data['firstName'] = $customer->getFirstName();
        }
        if (method_exists($customer, 'getLastName')) {
            $customer_data['lastName'] = $customer->getLastName();
        }
        if (method_exists($customer, 'getPhone')) {
            $customer_data['phone'] = $customer->getPhone();
        }
        
        if (!empty($customer_data['email'])) {
            $this->core->sync_customer_universal($customer_data, 'bookly', array('Bookly Customer'));
        }
    }
    
    /**
     * Sync Bookly customer object
     */
    private function sync_bookly_customer_object($appointment) {
        // Get customer ID from appointment
        $customer_id = null;
        if (method_exists($appointment, 'getCustomerId')) {
            $customer_id = $appointment->getCustomerId();
        }
        
        if ($customer_id) {
            // Load customer data
            global $wpdb;
            $table_name = $wpdb->prefix . 'bookly_customers';
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                $customer_data = array(
                    'email' => $customer['email'],
                    'firstName' => $customer['first_name'],
                    'lastName' => $customer['last_name'],
                    'phone' => $customer['phone']
                );
                
                // Get service name if possible
                if (method_exists($appointment, 'getServiceId')) {
                    $service_id = $appointment->getServiceId();
                    $service_table = $wpdb->prefix . 'bookly_services';
                    $service = $wpdb->get_row($wpdb->prepare(
                        "SELECT title FROM $service_table WHERE id = %d",
                        $service_id
                    ));
                    if ($service && isset($service->title)) {
                        $customer_data['service'] = $service->title;
                    }
                }
                
                $this->core->sync_customer_universal($customer_data, 'bookly', array('Bookly Customer'));
            }
        }
    }
    
    /**
     * Sync Easy Appointments customer
     */
    public function sync_easy_appointments_customer($appointment_data) {
        if (!$this->core->is_plugin_sync_enabled('easy_appointments')) return;
        
        $customer_data = array();
        
        // Handle different Easy Appointments data structures
        if (is_array($appointment_data)) {
            // Check for customer data in appointment
            if (isset($appointment_data['customer'])) {
                $customer = $appointment_data['customer'];
                if (isset($customer['email'])) $customer_data['email'] = $customer['email'];
                if (isset($customer['first_name'])) $customer_data['firstName'] = $customer['first_name'];
                if (isset($customer['last_name'])) $customer_data['lastName'] = $customer['last_name'];
                if (isset($customer['phone_number'])) $customer_data['phone'] = $customer['phone_number'];
            } else {
                // Direct fields
                if (isset($appointment_data['email'])) $customer_data['email'] = $appointment_data['email'];
                if (isset($appointment_data['name'])) {
                    $name_parts = explode(' ', $appointment_data['name'], 2);
                    $customer_data['firstName'] = $name_parts[0];
                    $customer_data['lastName'] = isset($name_parts[1]) ? $name_parts[1] : '';
                }
                if (isset($appointment_data['phone'])) $customer_data['phone'] = $appointment_data['phone'];
            }
            
            // Add service name if available
            if (isset($appointment_data['service']) && isset($appointment_data['service']['name'])) {
                $customer_data['service'] = $appointment_data['service']['name'];
            } elseif (isset($appointment_data['service_name'])) {
                $customer_data['service'] = $appointment_data['service_name'];
            }
        } elseif (is_numeric($appointment_data)) {
            // If we got appointment ID, try to load data
            $this->sync_easy_appointments_by_id($appointment_data);
            return;
        }
        
        if (!empty($customer_data['email'])) {
            $this->core->sync_customer_universal($customer_data, 'easy_appointments', array('Easy Appointments Customer'));
        }
    }
    
    /**
     * Sync Easy Appointments by ID
     */
    private function sync_easy_appointments_by_id($appointment_id) {
        global $wpdb;
        
        // Get appointment data
        $table_name = $wpdb->prefix . 'ea_appointments';
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $appointment_id
        ), ARRAY_A);
        
        if ($appointment) {
            // Get customer data
            $customer_table = $wpdb->prefix . 'ea_customers';
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $customer_table WHERE id = %d",
                $appointment['customer_id']
            ), ARRAY_A);
            
            if ($customer) {
                $customer_data = array(
                    'email' => $customer['email'],
                    'firstName' => $customer['first_name'],
                    'lastName' => $customer['last_name'],
                    'phone' => $customer['phone']
                );
                
                // Get service name
                $service_table = $wpdb->prefix . 'ea_services';
                $service = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM $service_table WHERE id = %d",
                    $appointment['service_id']
                ));
                if ($service && isset($service->name)) {
                    $customer_data['service'] = $service->name;
                }
                
                $this->core->sync_customer_universal($customer_data, 'easy_appointments', array('Easy Appointments Customer'));
            }
        }
    }
    
    /**
     * Sync WooCommerce Booking customer
     */
    public function sync_wc_booking_customer($booking_id) {
        if (!$this->core->is_plugin_sync_enabled('wc_bookings')) return;
        
        // Get booking object
        $booking = get_wc_booking($booking_id);
        if (!$booking) return;
        
        // Get customer data
        $customer_id = $booking->get_customer_id();
        $order_id = $booking->get_order_id();
        
        $customer_data = array();
        
        // Try to get data from order first
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $customer_data = array(
                    'email' => $order->get_billing_email(),
                    'firstName' => $order->get_billing_first_name(),
                    'lastName' => $order->get_billing_last_name(),
                    'phone' => $order->get_billing_phone(),
                    'country' => $order->get_billing_country()
                );
            }
        }
        
        // If no order data, try user data
        if (empty($customer_data['email']) && $customer_id) {
            $user = get_userdata($customer_id);
            if ($user) {
                $customer_data = array(
                    'email' => $user->user_email,
                    'firstName' => get_user_meta($customer_id, 'first_name', true) ?: $user->display_name,
                    'lastName' => get_user_meta($customer_id, 'last_name', true),
                    'phone' => get_user_meta($customer_id, 'billing_phone', true),
                    'country' => get_user_meta($customer_id, 'billing_country', true)
                );
            }
        }
        
        // Add booking product information
        $product = $booking->get_product();
        if ($product) {
            $customer_data['product'] = $product->get_name();
        }
        
        if (!empty($customer_data['email'])) {
            $this->core->sync_customer_universal($customer_data, 'wc_bookings', array('WooCommerce Booking'));
        }
    }
}