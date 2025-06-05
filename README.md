# Universal Systeme.io Sync Plugin

Universal WordPress plugin to sync customer data from various booking plugins to Systeme.io using hooks and API.

## File Structure

```
universal-systeme-sync/
├── universal-systeme-sync.php      # Main plugin file
├── includes/
│   ├── class-core.php             # Core plugin class
│   ├── class-api.php              # Systeme.io API handler
│   ├── class-admin.php            # Admin interface
│   ├── class-integrations.php    # Plugin integrations
│   ├── class-ajax.php             # AJAX handler
│   └── class-logger.php           # Logging system
├── templates/
│   └── admin-page.php             # Admin page template
├── assets/
│   └── admin.js                   # Admin JavaScript
└── README.md                      # This file
```

## Main Features

1. **Modular Structure**: Plugin split into multiple classes for easy maintenance
2. **Automatic Plugin Detection**: Accurately detects installed plugins
3. **Systeme.io Field Mapping**: Automatic mapping to correct Systeme.io field structure
4. **Custom Field Support**: Track products/events (perfect for free plans)
5. **Debug Mode**: Debug mode for troubleshooting
6. **Logging System**: Structured logging system

## New Feature: Custom Field Support

### Why Custom Fields?

Free Systeme.io plans only have 1 tag, making it difficult to differentiate customers by product/event. With custom fields, you can:

- Track which product/event customers purchased
- Create segmentation based on custom fields
- No limit on the number of values that can be used

### How to Use Custom Fields

1. Enable "Use Custom Field" in settings
2. Set custom field name (default: "products")
3. Map each integration to desired custom field value
4. Plugin will automatically create custom field if it doesn't exist

## Improvements from Previous Version

### 1. Better Plugin Detection

Plugin now uses multiple checks to detect plugins:

- Class existence check
- Function existence check
- Constant check

### 2. Correct Systeme.io Field Mapping

Plugin now uses the correct field structure for Systeme.io API:

```php
$contact_data = array(
    'email' => $email,
    'fields' => array(
        array('slug' => 'first_name', 'value' => $firstName),
        array('slug' => 'surname', 'value' => $lastName),
        array('slug' => 'country', 'value' => 'ID'),
        array('slug' => 'phone_number', 'value' => $phone),
        array('slug' => 'products', 'value' => 'Workshop 2024') // Custom field
    )
);
```

### 3. Product/Event Tracking

Plugin can now retrieve product/event information from:

- WooCommerce: Product names from orders
- WooCommerce Bookings: Booking product name
- Amelia: Service or event name
- And more

## Usage Guide

### 1. Installation

1. Upload `universal-systeme-sync` folder to `/wp-content/plugins/` directory
2. Activate plugin through 'Plugins' menu in WordPress
3. Configure at Settings → Systeme.io Sync

### 2. Configuration

1. **API Settings**:

   - Enter API Key from Systeme.io
   - Enable synchronization
   - (Optional) Add default tags
   - Enable debug mode for troubleshooting

2. **Custom Field Settings**:

   - Enable "Use Custom Field" for product/event tracking
   - Set custom field name (default: "products")
   - Configure mapping for each integration
   - Choose whether to use tags and custom fields together

3. **Plugin Integrations**:
   - Select plugins to integrate
   - Plugin will automatically detect installed plugins

### 3. Custom Field Mapping

You can set custom field values for each source:

```
Amelia Appointments → "Consultation"
Amelia Events → "Workshop"
WooCommerce Orders → "Product"
Contact Form 7 → "Lead"
```

Or leave empty to use default values.

### 4. Developer Usage

Other plugins can use these hooks:

```php
// Method 1: Using action hook
do_action('systeme_sync_customer', array(
    'email' => 'customer@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '+1234567890',
    'country' => 'US',
    'product' => 'Premium Package', // For custom field
    'event' => 'Workshop 2024'      // Alternative for custom field
), 'my_plugin', array('tag1', 'tag2'));

// Method 2: Using static method
UniversalSystemeSync\Core::sync_customer_data($customer_data, $source, $tags);
```

## Supported Integrations

- **Amelia Booking**: Appointments and Events (with service/event name)
- **WooCommerce**: Orders (with product names)
- **Contact Form 7**: Form submissions
- **Gravity Forms**: Form submissions
- **Bookly**: Appointments
- **WooCommerce Bookings**: Bookings (with booking product name)
- **Easy Appointments**: Appointments
- **WordPress User Registration**: New users

## API Endpoints Used

This plugin uses the following Systeme.io API endpoints:

- `POST /api/contacts` - Create/update contact
- `GET /api/contacts` - Get contact
- `POST /api/tags` - Create tag
- `POST /api/contact_fields` - Create custom field
- `GET /api/contact_fields` - Get custom fields list

## Troubleshooting

1. **Plugin not detected**:

   - Ensure plugin is active
   - Refresh admin page
   - Plugin uses multiple detection methods

2. **API connection failed**:

   - Check API key
   - Ensure API key has sufficient permissions
   - Check logs for error details

3. **Custom field not created**:

   - Enable debug mode to see API response
   - Ensure API key has permission to create custom fields
   - Check if field already exists with same name

4. **Data not syncing**:

   - Enable debug mode
   - Check logs for error details
   - Ensure customer email is valid

5. **Contact Form 7 not syncing**:
   - Check field names in your form
   - Common field names: your-email, your-name, phone
   - Enable debug mode to see posted data

## Hooks and Filters

### Actions

- `systeme_sync_customer` - Main sync hook
- `systeme_sync_contact` - Alternative sync hook
- `universal_systeme_sync` - Alternative sync hook
- `systeme_sync_completed` - Fired after successful sync

### Filters

- `systeme_sync_customer_data` - Filter customer data before sync
- `systeme_sync_additional_tags` - Filter tags before sync

## Usage Tips

1. **For Free Systeme.io Plans**:

   - Enable custom field to replace tags functionality
   - Set clear mapping for each product/event
   - Use this field for automation in Systeme.io

2. **For Paid Plans**:

   - Can use both tags and custom fields together
   - Tags for general categories, custom fields for specific details

3. **Custom Field Behavior**:

   - New contacts: Custom field will be created with the mapped value
   - Existing contacts: New values will be appended to existing custom field values
   - Duplicate prevention: Same values won't be added twice
   - Values are separated by commas for easy parsing

4. **Best Practices**:
   - Use descriptive custom field names
   - Be consistent in mapping names
   - Regularly check logs for monitoring
   - Consider the comma-separated format when setting up automations

## Changelog

### Version 2.3.0

- Added background processing option for faster form submissions
- Implemented dynamic placeholders for custom field values
- Added support for form-specific values (service names, event names, product names, form titles)
- Optimized API calls by preloading tags
- Improved performance for Contact Form 7 and Amelia integrations
- Added placeholder system for custom field mappings
- Available placeholders: {service_name}, {event_name}, {product_name}, {form_title}, {source}, {date}, {datetime}, {customer_name}, {email}

### Version 2.2.4

- Fixed tag assignment error (405 Method Not Allowed)
- Implemented proper tag management with existence checking
- Added tag caching to improve performance
- Prevent duplicate tag assignments to contacts
- Improved tag creation and assignment workflow
- Added support for retrieving contact's existing tags

### Version 2.2.3

- Fixed contact update method to use PATCH with correct content type
- Improved content-type handling for different API methods
- Added support for application/merge-patch+json for PATCH requests
- Better handling of contact lookup response formats
- Enhanced API debugging with content-type logging

### Version 2.2.2

- Fixed "email already exists" error by implementing proper contact update logic
- Plugin now checks if contact exists before creating/updating
- Improved tag assignment for both new and existing contacts
- Enhanced error handling for contact updates
- Better support for using both tags and custom fields together

### Version 2.2.1

- Added automatic appending of custom field values for existing contacts
- Implemented duplicate value prevention in custom fields
- Improved contact lookup before updating
- Enhanced debug logging for contact updates

### Version 2.2.0

- Added custom field support for product/event tracking
- Improved admin UI with custom field settings
- Added ability to map each integration to custom values
- Enhanced product/event detection from various plugins
- Added option to use both tags and custom fields
- Fixed Amelia phone number sync issue
- Fixed Contact Form 7 sync with expanded field detection
- Improved custom field creation with better error handling

### Version 2.1.0

- Restructured plugin into modular classes
- Improved plugin detection
- Fixed Systeme.io field mapping
- Added better error handling
- Improved admin interface

### Version 2.0.0

- Initial release

## Support

If you experience issues or have questions:
1. Enable debug mode and check logs
2. Verify all settings are correct
3. Review the Systeme.io API documentation for limitations

## License

GPL v2 or later
