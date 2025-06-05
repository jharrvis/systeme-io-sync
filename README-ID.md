1# Universal Systeme.io Sync Plugin

Plugin WordPress universal untuk sinkronisasi data customer dari berbagai plugin booking ke Systeme.io menggunakan hooks dan API.

## Struktur File

```
universal-systeme-sync/
├── universal-systeme-sync.php      # File utama plugin
├── includes/
│   ├── class-core.php             # Kelas inti plugin
│   ├── class-api.php              # Handler API Systeme.io
│   ├── class-admin.php            # Interface admin
│   ├── class-integrations.php    # Integrasi dengan plugin lain
│   ├── class-ajax.php             # Handler AJAX
│   └── class-logger.php           # Sistem logging
├── templates/
│   └── admin-page.php             # Template halaman admin
├── assets/
│   └── admin.js                   # JavaScript untuk admin
└── README.md                      # File ini
```

## Fitur Utama

1. **Struktur Modular**: Plugin dipecah menjadi beberapa kelas untuk memudahkan maintenance
2. **Deteksi Plugin Otomatis**: Mendeteksi plugin yang terinstall dengan lebih akurat
3. **Field Mapping Systeme.io**: Mapping otomatis ke struktur field Systeme.io yang benar
4. **Custom Field Support**: Mendukung custom field untuk tracking produk/event (cocok untuk paket free)
5. **Debug Mode**: Mode debug untuk troubleshooting
6. **Logging System**: Sistem log yang terstruktur

## Fitur Baru: Custom Field Support

### Mengapa Custom Field?

Paket free Systeme.io hanya memiliki 1 tag, sehingga sulit untuk membedakan customer berdasarkan produk/event yang mereka beli. Dengan custom field, Anda bisa:

- Track produk/event yang dibeli customer
- Membuat segmentasi berdasarkan custom field
- Tidak terbatas jumlah nilai yang bisa digunakan

### Cara Menggunakan Custom Field

1. Enable "Use Custom Field" di settings
2. Set nama custom field (default: "products")
3. Mapping setiap integrasi ke nilai custom field yang diinginkan
4. Plugin akan otomatis membuat custom field jika belum ada

## Perbaikan dari Versi Sebelumnya

### 1. Deteksi Plugin yang Lebih Baik

Plugin sekarang menggunakan multiple check untuk mendeteksi plugin:

- Class existence check
- Function existence check
- Constant check

### 2. Field Mapping Systeme.io yang Benar

Plugin sekarang menggunakan struktur field yang benar untuk Systeme.io API:

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

### 3. Tracking Produk/Event

Plugin sekarang bisa mengambil informasi produk/event dari:

- WooCommerce: Nama produk dari order
- WooCommerce Bookings: Nama booking product
- Amelia: Nama service atau event
- Dan lainnya

## Cara Penggunaan

### 1. Instalasi

1. Upload folder `universal-systeme-sync` ke directory `/wp-content/plugins/`
2. Aktivasi plugin melalui menu 'Plugins' di WordPress
3. Konfigurasi di Settings → Systeme.io Sync

### 2. Konfigurasi

1. **API Settings**:

   - Masukkan API Key dari Systeme.io
   - Enable synchronization
   - (Optional) Tambahkan default tags
   - Enable debug mode untuk troubleshooting

2. **Custom Field Settings**:

   - Enable "Use Custom Field" untuk tracking produk/event
   - Set nama custom field (default: "products")
   - Atur mapping untuk setiap integrasi
   - Pilih apakah ingin menggunakan tags dan custom field bersamaan

3. **Plugin Integrations**:
   - Pilih plugin yang ingin diintegrasikan
   - Plugin akan otomatis mendeteksi plugin yang terinstall

### 3. Custom Field Mapping

Anda bisa mengatur nilai custom field untuk setiap sumber:

```
Amelia Appointments → "Konsultasi"
Amelia Events → "Workshop"
WooCommerce Orders → "Produk"
Contact Form 7 → "Lead"
```

Atau biarkan kosong untuk menggunakan nilai default.

### 4. Penggunaan untuk Developer

Plugin lain dapat menggunakan hook berikut:

```php
// Cara 1: Menggunakan action hook
do_action('systeme_sync_customer', array(
    'email' => 'customer@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '+1234567890',
    'country' => 'US',
    'product' => 'Premium Package', // Untuk custom field
    'event' => 'Workshop 2024'      // Alternatif untuk custom field
), 'my_plugin', array('tag1', 'tag2'));

// Cara 2: Menggunakan static method
UniversalSystemeSync\Core::sync_customer_data($customer_data, $source, $tags);
```

## Integrasi yang Didukung

- **Amelia Booking**: Appointments dan Events (dengan nama service/event)
- **WooCommerce**: Orders (dengan nama produk)
- **Contact Form 7**: Form submissions
- **Gravity Forms**: Form submissions
- **Bookly**: Appointments
- **WooCommerce Bookings**: Bookings (dengan nama booking product)
- **Easy Appointments**: Appointments
- **WordPress User Registration**: New users

## API Endpoints yang Digunakan

Plugin ini menggunakan Systeme.io API endpoints berikut:

- `POST /api/contacts` - Membuat/update contact
- `GET /api/contacts` - Mendapatkan contact
- `POST /api/tags` - Membuat tag
- `POST /api/contact_fields` - Membuat custom field
- `GET /api/contact_fields` - Mendapatkan daftar custom fields

## Troubleshooting

1. **Plugin tidak terdeteksi**:

   - Pastikan plugin sudah aktif
   - Refresh halaman admin
   - Plugin menggunakan multiple detection methods

2. **API connection failed**:

   - Periksa API key
   - Pastikan API key memiliki permission yang cukup
   - Cek log untuk detail error

3. **Custom field tidak dibuat**:

   - Enable debug mode untuk melihat response API
   - Pastikan API key memiliki permission untuk membuat custom field
   - Cek apakah field sudah ada dengan nama yang sama

4. **Data tidak tersinkron**:
   - Enable debug mode
   - Periksa log untuk error details
   - Pastikan email customer valid

## Hooks dan Filters

### Actions

- `systeme_sync_customer` - Main sync hook
- `systeme_sync_contact` - Alternative sync hook
- `universal_systeme_sync` - Alternative sync hook
- `systeme_sync_completed` - Fired after successful sync

### Filters

- `systeme_sync_customer_data` - Filter customer data before sync
- `systeme_sync_additional_tags` - Filter tags before sync

## Tips Penggunaan

1. **Untuk Paket Free Systeme.io**:

   - Enable custom field untuk menggantikan fungsi tags
   - Atur mapping yang jelas untuk setiap produk/event
   - Gunakan field ini untuk automation di Systeme.io

2. **Untuk Paket Berbayar**:

   - Bisa menggunakan tags dan custom field bersamaan
   - Tags untuk kategori umum, custom field untuk detail spesifik

3. **Best Practices**:
   - Gunakan nama custom field yang deskriptif
   - Konsisten dalam penamaan mapping
   - Regular check logs untuk monitoring

## Changelog

### Version 2.2.0

- Added custom field support for product/event tracking
- Improved admin UI with custom field settings
- Added ability to map each integration to custom values
- Enhanced product/event detection from various plugins
- Added option to use both tags and custom fields

### Version 2.1.0

- Restructured plugin into modular classes
- Improved plugin detection
- Fixed Systeme.io field mapping
- Added better error handling
- Improved admin interface

### Version 2.0.0

- Initial release

## Support

Jika Anda mengalami masalah atau memiliki pertanyaan:

1. Enable debug mode dan check logs
2. Pastikan semua settings sudah benar
3. Check dokumentasi Systeme.io API untuk limitations

## License

GPL v2 or later
