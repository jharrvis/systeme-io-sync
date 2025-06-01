<?php
namespace UniversalSystemeSync;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class API {
    
    private $settings;
    private $logger;
    private $custom_fields_cache = null;
    private $tags_cache = null;
    
    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }
    
    /**
     * Send customer data to Systeme.io using the correct field structure
     */
    public function send_customer_to_systeme($customer, $source = '', $additional_tags = array()) {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $this->logger->log('Error: API key not found');
            return false;
        }
        
        // Debug: Log raw customer data
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Raw customer data from {$source}: " . json_encode($customer));
        }
        
        // Extract customer data with multiple possible field name formats
        $email = $this->extract_customer_field($customer, ['email']);
        $firstName = $this->extract_customer_field($customer, ['firstName', 'first_name', 'name']);
        $lastName = $this->extract_customer_field($customer, ['lastName', 'last_name', 'surname']);
        $phone = $this->extract_customer_field($customer, ['phone', 'phoneNumber', 'phone_number']);
        $country = $this->extract_customer_field($customer, ['country', 'country_code']);
        
        // Split name if firstName contains full name
        if (!empty($firstName) && empty($lastName) && strpos($firstName, ' ') !== false) {
            $nameParts = explode(' ', $firstName, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        }
        
        // Validate minimum data
        if (empty($email)) {
            $this->logger->log('Error: Email is required but not found in customer data');
            return false;
        }
        
        // Preload tags if using tags (do this before checking contact)
        if ($this->should_use_tags() && !empty($additional_tags)) {
            $this->load_tags();
        }
        
        // Check if contact already exists
        $existing_contact = $this->get_contact_by_email($email);
        $contact_exists = false;
        $contact_id = null;
        $existing_custom_field_value = '';
        
        if ($existing_contact && isset($existing_contact['data'])) {
            $contact_exists = true;
            if (isset($existing_contact['data']['id'])) {
                $contact_id = $existing_contact['data']['id'];
            }
            
            // Get existing custom field value if using custom fields
            if ($this->should_use_custom_field()) {
                $custom_field_slug = $this->get_custom_field_slug();
                $existing_custom_field_value = $this->get_contact_custom_field_value($existing_contact['data'], $custom_field_slug);
                
                if ($this->is_debug_mode()) {
                    $this->logger->log("Debug: Existing contact found with ID: {$contact_id} and custom field value: " . $existing_custom_field_value);
                }
            }
        }
        
        // Prepare contact data with Systeme.io field structure
        $contact_data = array(
            'email' => $email,
            'fields' => array()
        );
        
        // Add first name field
        if (!empty($firstName)) {
            $contact_data['fields'][] = array(
                'slug' => 'first_name',
                'value' => $firstName
            );
        }
        
        // Add surname field
        if (!empty($lastName)) {
            $contact_data['fields'][] = array(
                'slug' => 'surname',
                'value' => $lastName
            );
        }
        
        // Add country field (default to ID if not provided)
        if (!empty($country)) {
            $contact_data['fields'][] = array(
                'slug' => 'country',
                'value' => strtoupper(substr($country, 0, 2)) // Ensure 2-letter country code
            );
        } else {
            $contact_data['fields'][] = array(
                'slug' => 'country',
                'value' => 'ID' // Default to Indonesia
            );
        }
        
        // Add phone field
        if (!empty($phone)) {
            // Clean phone number - remove non-numeric except + at start
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            $contact_data['fields'][] = array(
                'slug' => 'phone_number',
                'value' => $phone
            );
        }
        
        // Handle custom field for product/event tracking
        if ($this->should_use_custom_field()) {
            $custom_field_slug = $this->get_custom_field_slug();
            $custom_field_value = $this->get_custom_field_value($source, $customer, $additional_tags);
            
            if (!empty($custom_field_slug) && !empty($custom_field_value)) {
                // Ensure custom field exists BEFORE creating/updating contact
                $field_created = $this->ensure_custom_field_exists($custom_field_slug);
                
                if ($field_created) {
                    // Append to existing value if contact exists and has custom field value
                    if (!empty($existing_custom_field_value)) {
                        // Check if the new value already exists in the list
                        $existing_values = array_map('trim', explode(',', $existing_custom_field_value));
                        if (!in_array($custom_field_value, $existing_values)) {
                            $custom_field_value = $existing_custom_field_value . ', ' . $custom_field_value;
                            $this->logger->log("Info: Appending '{$custom_field_value}' to existing custom field value");
                        } else {
                            // Value already exists, use the existing value
                            $custom_field_value = $existing_custom_field_value;
                            $this->logger->log("Info: Custom field value '{$custom_field_value}' already exists, not duplicating");
                        }
                    }
                    
                    // Add custom field to contact data
                    $contact_data['fields'][] = array(
                        'slug' => $custom_field_slug,
                        'value' => $custom_field_value
                    );
                } else {
                    $this->logger->log("Warning: Could not create custom field '{$custom_field_slug}', proceeding without it");
                }
            }
        }
        
        // Debug: Log prepared data
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Prepared contact data: " . json_encode($contact_data));
        }
        
        // Create or update contact
        $response = null;
        if ($contact_exists && $contact_id) {
            // Update existing contact
            $this->logger->log("Info: Updating existing contact {$email} (ID: {$contact_id})");
            $response = $this->update_contact($contact_id, $contact_data);
        } else {
            // Create new contact
            $this->logger->log("Info: Creating new contact {$email}");
            $response = $this->call_systeme_api('contacts', $contact_data);
        }
        
        if ($response['success']) {
            $this->logger->log("Success: Customer {$email} ({$firstName} {$lastName}) successfully synced from {$source}");
            
            // Handle tags based on settings
            if ($this->should_use_tags()) {
                // Prepare all tags
                $all_tags = array();
                if (!empty($this->get_default_tags())) {
                    $default_tags = array_map('trim', explode(',', $this->get_default_tags()));
                    $all_tags = array_merge($all_tags, $default_tags);
                }
                if (!empty($additional_tags)) {
                    $all_tags = array_merge($all_tags, $additional_tags);
                }
                
                // Add tags if any
                if (!empty($all_tags)) {
                    // Use contact ID if available, otherwise use email
                    $contact_identifier = $contact_id ?: $email;
                    $this->add_tags_to_contact($contact_identifier, $all_tags, $contact_id !== null);
                }
            }
            
            // Fire action for other plugins to hook into
            do_action('systeme_sync_completed', $contact_data, $source, $response);
            
            return true;
        } else {
            $this->logger->log("Error: Failed to sync customer {$email} to Systeme.io - {$response['message']}");
            return false;
        }
    }
    
    /**
     * Extract field value from customer data
     */
    private function extract_customer_field($customer, $possible_keys) {
        foreach ($possible_keys as $key) {
            if (isset($customer[$key]) && !empty($customer[$key])) {
                return trim($customer[$key]);
            }
        }
        return '';
    }
    
    /**
     * Update existing contact
     */
    private function update_contact($contact_id, $contact_data) {
        // Use PATCH method to update contact with merge-patch content type
        return $this->call_systeme_api_method("contacts/{$contact_id}", $contact_data, 'PATCH');
    }
    
    /**
     * Add tags to contact
     */
    private function add_tags_to_contact($contact_identifier, $tags, $is_id = false) {
        // First, ensure all tags exist
        $this->ensure_tags_exist($tags);
        
        // Get contact ID if we have email
        $contact_id = $contact_identifier;
        if (!$is_id) {
            $contact = $this->get_contact_by_email($contact_identifier);
            if (!$contact || !isset($contact['data']['id'])) {
                $this->logger->log("Error: Could not find contact to assign tags");
                return;
            }
            $contact_id = $contact['data']['id'];
        }
        
        // Get existing tags for the contact to avoid duplicates
        $existing_tags = $this->get_contact_tags($contact_id);
        
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tag = trim($tag);
                
                // Check if tag is already assigned
                if (in_array($tag, $existing_tags)) {
                    if ($this->is_debug_mode()) {
                        $this->logger->log("Debug: Tag '{$tag}' already assigned to contact, skipping");
                    }
                    continue;
                }
                
                // Get tag ID from cache
                $tag_id = $this->get_tag_id_by_name($tag);
                if (!$tag_id) {
                    $this->logger->log("Warning: Could not find tag ID for '{$tag}'");
                    continue;
                }
                
                if ($this->is_debug_mode()) {
                    $this->logger->log("Debug: Assigning tag '{$tag}' (ID: {$tag_id}) to contact ID: {$contact_id}");
                }
                
                // Assign tag to contact
                $tag_data = array(
                    'tagId' => $tag_id
                );
                
                $response = $this->call_systeme_api("contacts/{$contact_id}/tags", $tag_data);
                
                if ($response['success']) {
                    $this->logger->log("Success: Tag '{$tag}' assigned to contact");
                } else {
                    $this->logger->log("Warning: Failed to assign tag '{$tag}' to contact - {$response['message']}");
                }
            }
        }
    }
    
    /**
     * Ensure all tags exist
     */
    private function ensure_tags_exist($tags) {
        // Load existing tags
        $this->load_tags();
        
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $tag = trim($tag);
                
                // Check if tag already exists in cache
                if (!$this->tag_exists_in_cache($tag)) {
                    // Create the tag
                    $this->create_tag($tag);
                }
            }
        }
    }
    
    /**
     * Load tags from Systeme.io
     */
    private function load_tags() {
        $this->tags_cache = array();
        
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Loading tags from Systeme.io");
        }
        
        $response = $this->call_systeme_api_method('tags', null, 'GET');
        
        if ($response['success']) {
            if (isset($response['data']['items'])) {
                foreach ($response['data']['items'] as $tag) {
                    if (isset($tag['name']) && isset($tag['id'])) {
                        $this->tags_cache[$tag['name']] = $tag['id'];
                    }
                }
            } elseif (isset($response['data']) && is_array($response['data'])) {
                // Handle different response format
                foreach ($response['data'] as $tag) {
                    if (isset($tag['name']) && isset($tag['id'])) {
                        $this->tags_cache[$tag['name']] = $tag['id'];
                    }
                }
            }
            
            if ($this->is_debug_mode()) {
                $this->logger->log("Debug: Loaded " . count($this->tags_cache) . " tags");
            }
        } else {
            $this->logger->log("Warning: Failed to load tags - {$response['message']}");
        }
    }
    
    /**
     * Check if tag exists in cache
     */
    private function tag_exists_in_cache($tag_name) {
        return isset($this->tags_cache[$tag_name]);
    }
    
    /**
     * Get tag ID by name
     */
    private function get_tag_id_by_name($tag_name) {
        return isset($this->tags_cache[$tag_name]) ? $this->tags_cache[$tag_name] : null;
    }
    
    /**
     * Create a tag
     */
    private function create_tag($tag_name) {
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Creating tag '{$tag_name}'");
        }
        
        $tag_data = array(
            'name' => $tag_name
        );
        
        $response = $this->call_systeme_api('tags', $tag_data);
        
        if ($response['success']) {
            if (isset($response['data']['id'])) {
                $this->tags_cache[$tag_name] = $response['data']['id'];
                $this->logger->log("Success: Tag '{$tag_name}' created with ID: " . $response['data']['id']);
            }
        } else {
            // Check if tag already exists
            if (strpos($response['message'], 'already') !== false || 
                strpos($response['message'], 'duplicate') !== false ||
                strpos($response['message'], 'taken') !== false) {
                // Tag exists, try to get its ID
                $this->load_tags();
                if ($this->is_debug_mode()) {
                    $this->logger->log("Debug: Tag '{$tag_name}' already exists, reloaded tags");
                }
            } else {
                $this->logger->log("Error: Failed to create tag '{$tag_name}' - {$response['message']}");
            }
        }
    }
    
    /**
     * Get contact tags
     */
    private function get_contact_tags($contact_id) {
        $tags = array();
        
        // Get contact details to see current tags
        $response = $this->call_systeme_api_method("contacts/{$contact_id}", null, 'GET');
        
        if ($response['success'] && isset($response['data']['tags'])) {
            foreach ($response['data']['tags'] as $tag) {
                if (isset($tag['name'])) {
                    $tags[] = $tag['name'];
                }
            }
        }
        
        return $tags;
    }
    
    /**
     * Assign tag to contact using various methods
     */
    private function assign_tag_to_contact($contact_identifier, $tag_name, $tag_id = null, $is_id = false) {
        // This method is now deprecated, use add_tags_to_contact instead
        $this->add_tags_to_contact($contact_identifier, array($tag_name), $is_id);
    }
    
    /**
     * Get contact by email
     */
    private function get_contact_by_email($email) {
        $encoded_email = urlencode($email);
        $response = $this->call_systeme_api_method("contacts?email={$encoded_email}", null, 'GET');
        
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Get contact by email response: " . json_encode($response));
        }
        
        // Check if we got a valid contact
        if ($response['success'] && isset($response['data'])) {
            // Handle different response formats
            if (isset($response['data']['items']) && !empty($response['data']['items'])) {
                // List format - get first item
                return array('success' => true, 'data' => $response['data']['items'][0]);
            } elseif (isset($response['data']['email'])) {
                // Direct contact format
                return $response;
            } elseif (isset($response['data'][0]) && isset($response['data'][0]['email'])) {
                // Array format
                return array('success' => true, 'data' => $response['data'][0]);
            }
        }
        
        return array('success' => false, 'data' => null);
    }
    
    /**
     * Get custom field value from contact data
     */
    private function get_contact_custom_field_value($contact_data, $field_slug) {
        if (!isset($contact_data['fields']) || !is_array($contact_data['fields'])) {
            return '';
        }
        
        foreach ($contact_data['fields'] as $field) {
            if (isset($field['slug']) && $field['slug'] === $field_slug && isset($field['value'])) {
                return $field['value'];
            }
        }
        
        return '';
    }
    
    /**
     * Check if should use custom field instead of tags
     */
    private function should_use_custom_field() {
        return isset($this->settings['use_custom_field']) && $this->settings['use_custom_field'];
    }
    
    /**
     * Check if should use tags
     */
    private function should_use_tags() {
        return !$this->should_use_custom_field() || 
               (isset($this->settings['use_both_tags_and_fields']) && $this->settings['use_both_tags_and_fields']);
    }
    
    /**
     * Get custom field slug
     */
    private function get_custom_field_slug() {
        return isset($this->settings['custom_field_slug']) ? $this->settings['custom_field_slug'] : 'products';
    }
    
    /**
     * Get custom field value based on source and settings
     */
    private function get_custom_field_value($source, $customer_data, $additional_tags) {
        // Check if there's a custom mapping for this source
        $custom_mappings = $this->get_custom_field_mappings();
        
        if (isset($custom_mappings[$source]) && !empty($custom_mappings[$source])) {
            // Process placeholders in the mapping
            $value = $custom_mappings[$source];
            
            // Replace placeholders with actual values
            $value = $this->replace_placeholders($value, $customer_data, $source);
            
            return $value;
        }
        
        // Check for product/event/service information in customer data
        if (isset($customer_data['product']) && !empty($customer_data['product'])) {
            return $customer_data['product'];
        }
        if (isset($customer_data['event']) && !empty($customer_data['event'])) {
            return $customer_data['event'];
        }
        if (isset($customer_data['service']) && !empty($customer_data['service'])) {
            return $customer_data['service'];
        }
        if (isset($customer_data['form_title']) && !empty($customer_data['form_title'])) {
            return $customer_data['form_title'];
        }
        
        // Use first additional tag if available
        if (!empty($additional_tags)) {
            return $additional_tags[0];
        }
        
        // Default to source name
        return ucfirst(str_replace('_', ' ', $source));
    }
    
    /**
     * Replace placeholders in custom field mapping
     */
    private function replace_placeholders($value, $customer_data, $source) {
        // Available placeholders
        $placeholders = array(
            '{service_name}' => isset($customer_data['service']) ? $customer_data['service'] : '',
            '{event_name}' => isset($customer_data['event']) ? $customer_data['event'] : '',
            '{product_name}' => isset($customer_data['product']) ? $customer_data['product'] : '',
            '{form_title}' => isset($customer_data['form_title']) ? $customer_data['form_title'] : '',
            '{form_name}' => isset($customer_data['form_name']) ? $customer_data['form_name'] : '',
            '{source}' => ucfirst(str_replace('_', ' ', $source)),
            '{date}' => date('Y-m-d'),
            '{datetime}' => date('Y-m-d H:i'),
            '{customer_name}' => trim((isset($customer_data['firstName']) ? $customer_data['firstName'] : '') . ' ' . (isset($customer_data['lastName']) ? $customer_data['lastName'] : '')),
            '{email}' => isset($customer_data['email']) ? $customer_data['email'] : ''
        );
        
        // Replace all placeholders
        foreach ($placeholders as $placeholder => $replacement) {
            $value = str_replace($placeholder, $replacement, $value);
        }
        
        // Remove any unreplaced placeholders
        $value = preg_replace('/\{[^}]+\}/', '', $value);
        
        return trim($value);
    }
    
    /**
     * Get custom field mappings from settings
     */
    private function get_custom_field_mappings() {
        if (isset($this->settings['custom_field_mappings'])) {
            return $this->settings['custom_field_mappings'];
        }
        return array();
    }
    
    /**
     * Ensure custom field exists in Systeme.io
     */
    private function ensure_custom_field_exists($field_slug) {
        // First, load all existing fields to check
        $this->load_custom_fields();
        
        // Check if field already exists
        if (isset($this->custom_fields_cache[$field_slug])) {
            if ($this->is_debug_mode()) {
                $this->logger->log("Debug: Custom field '{$field_slug}' already exists");
            }
            return true;
        }
        
        // Field doesn't exist, create it
        $field_data = array(
            'name' => ucfirst(str_replace('_', ' ', $field_slug)),
            'slug' => $field_slug,
            'type' => 'text'
        );
        
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Creating custom field: " . json_encode($field_data));
        }
        
        $response = $this->call_systeme_api('contact_fields', $field_data);
        
        if ($response['success']) {
            $this->logger->log("Success: Custom field '{$field_slug}' created");
            $this->custom_fields_cache[$field_slug] = true;
            return true;
        } else {
            // Check for various error messages
            $error_message = strtolower($response['message']);
            if (strpos($error_message, 'already exists') !== false || 
                strpos($error_message, 'duplicate') !== false ||
                strpos($error_message, 'already been taken') !== false) {
                $this->custom_fields_cache[$field_slug] = true;
                if ($this->is_debug_mode()) {
                    $this->logger->log("Debug: Custom field '{$field_slug}' already exists (from error message)");
                }
                return true;
            }
            
            $this->logger->log("Error: Failed to create custom field '{$field_slug}' - {$response['message']}");
            return false;
        }
    }
    
    /**
     * Load custom fields from Systeme.io
     */
    private function load_custom_fields() {
        $this->custom_fields_cache = array();
        
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: Loading existing custom fields from Systeme.io");
        }
        
        $response = $this->call_systeme_api_method('contact_fields', null, 'GET');
        
        if ($response['success']) {
            if (isset($response['data']['items'])) {
                foreach ($response['data']['items'] as $field) {
                    if (isset($field['slug'])) {
                        $this->custom_fields_cache[$field['slug']] = true;
                        if ($this->is_debug_mode()) {
                            $this->logger->log("Debug: Found existing field: " . $field['slug']);
                        }
                    }
                }
            } elseif (isset($response['data']) && is_array($response['data'])) {
                // Handle different response format
                foreach ($response['data'] as $field) {
                    if (isset($field['slug'])) {
                        $this->custom_fields_cache[$field['slug']] = true;
                        if ($this->is_debug_mode()) {
                            $this->logger->log("Debug: Found existing field: " . $field['slug']);
                        }
                    }
                }
            }
        } else {
            $this->logger->log("Warning: Failed to load custom fields - {$response['message']}");
        }
    }
    
    /**
     * Call Systeme.io API
     */
    public function call_systeme_api($endpoint, $data) {
        return $this->call_systeme_api_method($endpoint, $data, 'POST');
    }
    
    /**
     * Call Systeme.io API with specific method
     */
    public function call_systeme_api_method($endpoint, $data, $method = 'POST') {
        $api_key = $this->get_api_key();
        $url = 'https://api.systeme.io/api/' . $endpoint;
        
        // Determine content type based on method
        $content_type = 'application/json';
        if ($method === 'PATCH') {
            $content_type = 'application/merge-patch+json';
        }
        
        $args = array(
            'headers' => array(
                'Content-Type' => $content_type,
                'Accept' => 'application/json',
                'X-API-Key' => $api_key
            ),
            'method' => $method,
            'timeout' => 30
        );
        
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Debug logging
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: API Call - Method: {$method}, URL: {$url}, Content-Type: {$content_type}, Data: " . json_encode($data));
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if ($this->is_debug_mode()) {
                $this->logger->log("Debug: WP Error in API call: {$error_message}");
            }
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Debug logging
        if ($this->is_debug_mode()) {
            $this->logger->log("Debug: API Response - Status: {$status_code}, Body: {$body}");
        }
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => json_decode($body, true),
                'message' => 'Success',
                'status_code' => $status_code
            );
        } else {
            return array(
                'success' => false,
                'message' => "HTTP {$status_code}: {$body}",
                'status_code' => $status_code
            );
        }
    }
    
    // Getters
    private function get_api_key() {
        return isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
    }
    
    private function get_default_tags() {
        return isset($this->settings['default_tags']) ? $this->settings['default_tags'] : '';
    }
    
    private function is_debug_mode() {
        return isset($this->settings['debug_mode']) && $this->settings['debug_mode'];
    }
}