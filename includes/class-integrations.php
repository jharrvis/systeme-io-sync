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
        
        // User registration hooks - use lower priority to run after booking processes
        if ($this->core->is_plugin_sync_enabled('user_registration')) {
            add_action('user_register', array($this, 'sync_new_user'), 999, 1); // Very low priority
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
            
            // Additional Amelia hooks for better event detection
            add_action('amelia_booking_confirmed', array($this, 'sync_amelia_booking_confirmed'), 10, 1);
            add_action('amelia_event_booking_added', array($this, 'sync_amelia_event_booking_added'), 10, 1);
            add_action('ameliabooking_event_booking_saved', array($this, 'sync_amelia_event_customer'), 10, 2);
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
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Amelia appointment booking data: " . json_encode($booking));
            $this->core->get_logger()->log("Debug: Amelia appointment reservation data: " . json_encode($reservation));
        }
        
        if (isset($booking['customer'])) {
            // Try to get service info from reservation or booking
            $service_data = $this->get_amelia_service_from_reservation($reservation, $booking);
            
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Extracted service data: " . json_encode($service_data));
            }
            
            // Pass booking data which may contain service info
            $customer_data = $this->prepare_amelia_customer_data($booking['customer'], $service_data);
            $this->core->sync_customer_universal($customer_data, 'amelia_appointment', array('Amelia Customer'));
        }
    }
    
    /**
     * Sync Amelia event customer
     */
    public function sync_amelia_event_customer($booking, $reservation) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Amelia event booking data: " . json_encode($booking));
            $this->core->get_logger()->log("Debug: Amelia event reservation data: " . json_encode($reservation));
        }
        
        if (isset($booking['customer'])) {
            // Try to get event info from reservation or booking
            $event_data = $this->get_amelia_event_from_reservation($reservation, $booking);
            
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Extracted event data: " . json_encode($event_data));
            }
            
            // Pass booking data which may contain event info
            $customer_data = $this->prepare_amelia_customer_data($booking['customer'], $event_data);
            $this->core->sync_customer_universal($customer_data, 'amelia_event', array('Amelia Event'));
        }
    }
    
    /**
     * Sync Amelia booking confirmed
     */
    public function sync_amelia_booking_confirmed($booking_data) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Amelia booking confirmed: " . json_encode($booking_data));
        }
        
        // Determine if it's an event or appointment
        if (isset($booking_data['eventId']) || isset($booking_data['event_id']) || isset($booking_data['event'])) {
            // It's an event booking
            if (isset($booking_data['customer'])) {
                $event_data = $this->extract_amelia_event_data($booking_data);
                $customer_data = $this->prepare_amelia_customer_data($booking_data['customer'], $event_data);
                $this->core->sync_customer_universal($customer_data, 'amelia_event', array('Amelia Event'));
            }
        } else {
            // It's an appointment booking
            if (isset($booking_data['customer'])) {
                $service_data = $this->extract_amelia_service_data($booking_data);
                $customer_data = $this->prepare_amelia_customer_data($booking_data['customer'], $service_data);
                $this->core->sync_customer_universal($customer_data, 'amelia_appointment', array('Amelia Customer'));
            }
        }
    }
    
    /**
     * Sync Amelia event booking added
     */
    public function sync_amelia_event_booking_added($booking_data) {
        if (!$this->core->is_plugin_sync_enabled('amelia')) return;
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Amelia event booking added: " . json_encode($booking_data));
        }
        
        if (isset($booking_data['customer'])) {
            $event_data = $this->extract_amelia_event_data($booking_data);
            $customer_data = $this->prepare_amelia_customer_data($booking_data['customer'], $event_data);
            $this->core->sync_customer_universal($customer_data, 'amelia_event', array('Amelia Event'));
        }
    }
    
    /**
     * Extract Amelia event data from booking
     */
    private function extract_amelia_event_data($booking_data) {
        $event_data = array();
        
        // Try to get event from direct data
        if (isset($booking_data['event']) && isset($booking_data['event']['name'])) {
            $event_data = array('event' => $booking_data['event']);
        } elseif (isset($booking_data['eventName'])) {
            $event_data = array('event' => array('name' => $booking_data['eventName']));
        } elseif (isset($booking_data['event_name'])) {
            $event_data = array('event' => array('name' => $booking_data['event_name']));
        }
        
        // If not found, try to get by ID
        if (empty($event_data)) {
            $event_id = null;
            if (isset($booking_data['eventId'])) {
                $event_id = $booking_data['eventId'];
            } elseif (isset($booking_data['event_id'])) {
                $event_id = $booking_data['event_id'];
            }
            
            if ($event_id) {
                $event_data = $this->get_amelia_event_by_id($event_id);
            }
        }
        
        return $event_data;
    }
    
    /**
     * Extract Amelia service data from booking
     */
    private function extract_amelia_service_data($booking_data) {
        $service_data = array();
        
        // Try to get service from direct data
        if (isset($booking_data['service']) && isset($booking_data['service']['name'])) {
            $service_data = array('service' => $booking_data['service']);
        } elseif (isset($booking_data['serviceName'])) {
            $service_data = array('service' => array('name' => $booking_data['serviceName']));
        } elseif (isset($booking_data['service_name'])) {
            $service_data = array('service' => array('name' => $booking_data['service_name']));
        }
        
        // If not found, try to get by ID
        if (empty($service_data)) {
            $service_id = null;
            if (isset($booking_data['serviceId'])) {
                $service_id = $booking_data['serviceId'];
            } elseif (isset($booking_data['service_id'])) {
                $service_id = $booking_data['service_id'];
            }
            
            if ($service_id) {
                $service_data = $this->get_amelia_service_by_id($service_id);
            }
        }
        
        return $service_data;
    }
    
    /**
     * Get Amelia service from reservation data
     */
    private function get_amelia_service_from_reservation($reservation, $booking) {
        $service_data = array();
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Looking for service in reservation: " . json_encode($reservation));
            $this->core->get_logger()->log("Debug: Looking for service in booking: " . json_encode($booking));
        }
        
        // First try to get service from direct data in reservation or booking
        if (isset($reservation['service']) && isset($reservation['service']['name'])) {
            $service_data = array('service' => $reservation['service']);
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Found service in reservation direct: " . $reservation['service']['name']);
            }
        } elseif (isset($booking['service']) && isset($booking['service']['name'])) {
            $service_data = array('service' => $booking['service']);
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Found service in booking direct: " . $booking['service']['name']);
            }
        }
        
        // If not found, try to get service ID and lookup from database
        if (empty($service_data)) {
            $service_id = null;
            
            // Try to get service ID from various places
            if (isset($reservation['serviceId'])) {
                $service_id = $reservation['serviceId'];
            } elseif (isset($booking['serviceId'])) {
                $service_id = $booking['serviceId'];
            } elseif (isset($reservation['service_id'])) {
                $service_id = $reservation['service_id'];
            } elseif (isset($booking['service_id'])) {
                $service_id = $booking['service_id'];
            }
            
            if ($service_id) {
                if ($this->core->is_debug_mode()) {
                    $this->core->get_logger()->log("Debug: Found service ID: {$service_id}, looking up in database");
                }
                $service_data = $this->get_amelia_service_by_id($service_id);
            }
        }
        
        // If still empty, try to find service name in any text fields
        if (empty($service_data)) {
            // Look for service name in various possible fields
            $possible_service_fields = array('serviceName', 'service_name', 'name');
            
            foreach (array($reservation, $booking) as $data_source) {
                if (is_array($data_source)) {
                    foreach ($possible_service_fields as $field) {
                        if (isset($data_source[$field]) && !empty($data_source[$field])) {
                            $service_data = array('service' => array('name' => $data_source[$field]));
                            if ($this->core->is_debug_mode()) {
                                $this->core->get_logger()->log("Debug: Found service name in {$field}: " . $data_source[$field]);
                            }
                            break 2;
                        }
                    }
                }
            }
        }
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Final service data: " . json_encode($service_data));
        }
        
        return $service_data;
    }
    
    /**
     * Get Amelia event from reservation data
     */
    private function get_amelia_event_from_reservation($reservation, $booking) {
        $event_data = array();
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Looking for event in reservation: " . json_encode($reservation));
            $this->core->get_logger()->log("Debug: Looking for event in booking: " . json_encode($booking));
        }
        
        // First try to get event from direct data in reservation or booking
        if (isset($reservation['event']) && isset($reservation['event']['name'])) {
            $event_data = array('event' => $reservation['event']);
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Found event in reservation direct: " . $reservation['event']['name']);
            }
        } elseif (isset($booking['event']) && isset($booking['event']['name'])) {
            $event_data = array('event' => $booking['event']);
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Found event in booking direct: " . $booking['event']['name']);
            }
        }
        
        // If not found, try to get event ID and lookup from database
        if (empty($event_data)) {
            $event_id = null;
            
            // Try to get event ID from various places
            if (isset($reservation['eventId'])) {
                $event_id = $reservation['eventId'];
            } elseif (isset($booking['eventId'])) {
                $event_id = $booking['eventId'];
            } elseif (isset($reservation['event_id'])) {
                $event_id = $reservation['event_id'];
            } elseif (isset($booking['event_id'])) {
                $event_id = $booking['event_id'];
            }
            
            if ($event_id) {
                if ($this->core->is_debug_mode()) {
                    $this->core->get_logger()->log("Debug: Found event ID: {$event_id}, looking up in database");
                }
                $event_data = $this->get_amelia_event_by_id($event_id);
            }
        }
        
        // If still empty, try to find event name in any text fields
        if (empty($event_data)) {
            // Look for event name in various possible fields
            $possible_event_fields = array('eventName', 'event_name', 'name');
            
            foreach (array($reservation, $booking) as $data_source) {
                if (is_array($data_source)) {
                    foreach ($possible_event_fields as $field) {
                        if (isset($data_source[$field]) && !empty($data_source[$field])) {
                            $event_data = array('event' => array('name' => $data_source[$field]));
                            if ($this->core->is_debug_mode()) {
                                $this->core->get_logger()->log("Debug: Found event name in {$field}: " . $data_source[$field]);
                            }
                            break 2;
                        }
                    }
                }
            }
        }
        
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Final event data: " . json_encode($event_data));
        }
        
        return $event_data;
    }
    
    /**
     * Get Amelia service by ID from database
     */
    private function get_amelia_service_by_id($service_id) {
        global $wpdb;
        
        try {
            // Try multiple possible table names for Amelia services
            $possible_tables = array(
                $wpdb->prefix . 'amelia_services',
                $wpdb->prefix . 'amelia_service',
                $wpdb->prefix . 'ameliabooking_services',
                $wpdb->prefix . 'ameliabooking_service'
            );
            
            foreach ($possible_tables as $table_name) {
                // Check if table exists
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    if ($this->core->is_debug_mode()) {
                        $this->core->get_logger()->log("Debug: Found Amelia services table: {$table_name}");
                    }
                    
                    $service = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE id = %d",
                        $service_id
                    ), ARRAY_A);
                    
                    if ($service) {
                        if ($this->core->is_debug_mode()) {
                            $this->core->get_logger()->log("Debug: Found service in database: " . json_encode($service));
                        }
                        
                        // Try different possible name columns
                        $service_name = null;
                        if (isset($service['name'])) {
                            $service_name = $service['name'];
                        } elseif (isset($service['title'])) {
                            $service_name = $service['title'];
                        } elseif (isset($service['service_name'])) {
                            $service_name = $service['service_name'];
                        }
                        
                        if ($service_name) {
                            return array('service' => array('name' => $service_name));
                        }
                    }
                }
            }
            
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: No Amelia services table found or service not found with ID: {$service_id}");
            }
            
        } catch (Exception $e) {
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Error getting Amelia service: " . $e->getMessage());
            }
        }
        
        return array();
    }
    
    /**
     * Get Amelia event by ID from database
     */
    private function get_amelia_event_by_id($event_id) {
        global $wpdb;
        
        try {
            // Try multiple possible table names for Amelia events
            $possible_tables = array(
                $wpdb->prefix . 'amelia_events',
                $wpdb->prefix . 'amelia_event',
                $wpdb->prefix . 'ameliabooking_events',
                $wpdb->prefix . 'ameliabooking_event'
            );
            
            foreach ($possible_tables as $table_name) {
                // Check if table exists
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    if ($this->core->is_debug_mode()) {
                        $this->core->get_logger()->log("Debug: Found Amelia events table: {$table_name}");
                    }
                    
                    // Try different possible column names
                    $event = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE id = %d",
                        $event_id
                    ), ARRAY_A);
                    
                    if ($event) {
                        if ($this->core->is_debug_mode()) {
                            $this->core->get_logger()->log("Debug: Found event in database: " . json_encode($event));
                        }
                        
                        // Try different possible name columns
                        $event_name = null;
                        if (isset($event['name'])) {
                            $event_name = $event['name'];
                        } elseif (isset($event['title'])) {
                            $event_name = $event['title'];
                        } elseif (isset($event['event_name'])) {
                            $event_name = $event['event_name'];
                        }
                        
                        if ($event_name) {
                            return array('event' => array('name' => $event_name));
                        }
                    }
                }
            }
            
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: No Amelia events table found or event not found with ID: {$event_id}");
            }
            
        } catch (Exception $e) {
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Error getting Amelia event: " . $e->getMessage());
            }
        }
        
        return array();
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
        
        // Debug log the prepared data
        if ($this->core->is_debug_mode()) {
            $this->core->get_logger()->log("Debug: Prepared Amelia customer data: " . json_encode($data));
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
        
        // Check if this user registration is triggered by other plugins (Amelia, etc.)
        // Skip if it's part of another booking process
        if ($this->is_user_registration_from_booking($user_id)) {
            if ($this->core->is_debug_mode()) {
                $this->core->get_logger()->log("Debug: Skipping user registration sync for user {$user_id} as it's part of booking process");
            }
            return;
        }
        
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
     * Check if user registration is from booking process
     */
    private function is_user_registration_from_booking($user_id) {
        // Check if we're in the middle of Amelia booking process
        if (did_action('amelia_after_booking_added') || 
            did_action('amelia_after_appointment_booking_saved') || 
            did_action('amelia_after_event_booking_saved')) {
            return true;
        }
        
        // Check if we're in WooCommerce checkout process
        if (did_action('woocommerce_checkout_order_processed') || 
            did_action('woocommerce_new_order')) {
            return true;
        }
        
        // Check recent user meta to see if this is part of booking
        $user_registered_time = get_userdata($user_id)->user_registered;
        $current_time = current_time('mysql');
        $time_diff = strtotime($current_time) - strtotime($user_registered_time);
        
        // If user was registered within last 30 seconds, it might be part of booking
        if ($time_diff < 30) {
            // Check if there are any booking-related actions in progress
            global $wp_filter;
            $booking_actions = array('amelia_', 'bookly_', 'woocommerce_');
            
            foreach ($booking_actions as $action_prefix) {
                foreach ($wp_filter as $action_name => $callbacks) {
                    if (strpos($action_name, $action_prefix) === 0) {
                        return true;
                    }
                }
            }
        }
        
        return false;
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