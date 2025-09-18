# Timetics PDF Addon - Developer Documentation

## Overview
The Timetics PDF Addon is a WordPress plugin that automatically generates and attaches PDF invoices to booking confirmation emails. It integrates with the Timetics booking system and WooCommerce to create professional tax invoices for medical appointments.

## Architecture

### Main Components

1. **Main Plugin File**: `timetics-pdf-addon.php`
   - Contains the core `Timetics_Pdf_Addon` class
   - Handles WordPress hooks and plugin initialization
   - Manages PDF generation and email attachment

2. **Email Parser**: `includes/StructuredEmailParser.php`
   - Extracts structured data from email content
   - Handles medical information parsing
   - Provides fallback data extraction methods

3. **PDF Generator**: Uses DomPDF library
   - Converts HTML templates to PDF
   - Handles styling and formatting
   - Manages file storage and cleanup

## Key Workflow

### 1. Email Hook Integration
```php
// Hook into WordPress email system
add_action('wp_mail', [$this, 'maybe_attach_pdf'], 10, 1);
```

The plugin intercepts all outgoing emails and checks if they should have a PDF attached based on:
- Email subject patterns
- Booking confirmation emails
- WooCommerce order completion emails

### 2. PDF Generation Process

#### Step 1: Data Extraction
The system tries multiple methods to extract booking data:

1. **Primary Method**: Extract from Timetics objects using booking ID
   ```php
   $timetics_data = $this->get_data_from_timetics_objects($booking_id);
   ```

2. **Fallback Method**: Parse email content for structured data
   ```php
   $parsed_data = $this->parse_email_data($email_data);
   ```

3. **Final Fallback**: Use recent booking data for the same customer
   ```php
   $recent_medical_info = $this->get_medical_info_from_recent_bookings($customer_email);
   ```

#### Step 2: Data Processing
The extracted data is processed and validated:
- Customer information (name, email, phone)
- Medical information (Medical Aid Scheme, Medical Aid Number, ID Number)
- Booking details (date, time, service, practitioner)
- Pricing information (amount, tax, total)

#### Step 3: HTML Template Generation
The system generates an HTML invoice template with:
- Professional styling
- Company branding
- Customer details
- Service breakdown
- Tax calculations
- Medical information display

#### Step 4: PDF Conversion
The HTML is converted to PDF using DomPDF:
```php
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->render();
$pdf_content = $dompdf->output();
```

### 3. Email Attachment
The generated PDF is attached to the original email:
```php
$attachments[] = $pdf_file_path;
wp_mail($to, $subject, $message, $headers, $attachments);
```

## Data Sources and Fallbacks

### Medical Information Extraction
The system handles medical information through multiple fallback layers:

1. **Service Information**: From the booking's service metadata
2. **Recent Bookings**: From previous bookings by the same customer
3. **Email Parsing**: From structured email content
4. **Default Values**: Shows "[Not provided]" when no data is available

### Staff Information
Staff data is extracted from the Timetics Staff class:
```php
// Fixed method call (was causing fatal error)
$data['practitioner_name'] = $staff->get_full_name() ?: 'Dr Ben Coetsee';
$data['practitioner_email'] = $staff->get_email() ?: 'drben@capecodes.com';
$data['practitioner_phone'] = $staff->get_phone() ?: '+27 78 737 7686';
```

## Key Methods

### `get_data_from_timetics_objects($booking_id)`
- Primary data extraction method
- Gets booking, customer, and service information
- Handles medical information from multiple sources
- Returns structured data array

### `parse_email_data($email_data)`
- Fallback data extraction from email content
- Uses regex patterns to extract structured information
- Handles various email formats and layouts

### `create_invoice_pdf_html($data)`
- Generates HTML template for PDF
- Includes all customer and booking information
- Applies professional styling
- Handles medical information display

### `get_medical_info_from_recent_bookings($customer_email)`
- Searches recent bookings for medical information
- Provides fallback when current booking lacks data
- Scoped by customer email for privacy

## Error Handling and Logging

### Debug Logging
The system includes comprehensive logging:
```php
$this->log_info('DEBUG: Message here');
$this->log_error('ERROR: Error message here');
```

### Production Debug Data
Debug information is cached for troubleshooting:
```php
$this->cache_production_debug_data('DEBUG_KEY', $debug_data);
```

### Error Recovery
- Graceful fallbacks when data extraction fails
- Default values for missing information
- Continues PDF generation even with incomplete data

## Configuration

### Static Practice Information
```php
$data['practitioner_number'] = 'MP0953814';
$data['practice_name'] = 'BC Longevity Clinic';
$data['practice_address'] = '...';
```

### Email Patterns
The system identifies relevant emails using subject patterns:
- Booking confirmation emails
- WooCommerce order completion emails
- Custom patterns for specific use cases

## File Management

### PDF Storage
- PDFs are stored in WordPress uploads directory
- Temporary files are cleaned up after email sending
- File naming includes booking ID for tracking

### Cache Management
- Debug data is cached for troubleshooting
- Cache keys include timestamps and booking IDs
- Automatic cleanup of old cache entries

## Common Issues and Solutions

### 1. Fatal Error: Call to undefined method
**Issue**: `Call to undefined method Staff::get_name()`
**Solution**: Use `get_full_name()` instead of `get_name()`

### 2. Missing Medical Information
**Issue**: Medical Aid Scheme/Number shows "[Not provided]"
**Solution**: Check if data exists in:
- Service metadata
- Recent bookings
- Email content parsing

### 3. PDF Generation Fails
**Issue**: PDF not being generated or attached
**Solution**: Check:
- DomPDF library availability
- File permissions
- HTML template validity
- Data extraction success

## Maintenance Guidelines

### Adding New Data Fields
1. Update data extraction methods
2. Add to HTML template
3. Include in fallback mechanisms
4. Update logging for debugging

### Modifying PDF Template
1. Edit HTML generation in `create_invoice_pdf_html()`
2. Test with various data scenarios
3. Ensure responsive design for PDF
4. Update CSS for proper PDF rendering

### Debugging Issues
1. Check debug logs for data extraction
2. Verify email patterns match
3. Test with sample data
4. Use production debug cache for analysis

## Dependencies

- **WordPress**: Core WordPress functionality
- **Timetics Plugin**: Booking system integration
- **WooCommerce**: E-commerce integration
- **DomPDF**: PDF generation library
- **PHP**: Version 7.4 or higher

## Security Considerations

- All user input is sanitized using `esc_html()`
- File paths are validated before operations
- Temporary files are properly cleaned up
- No sensitive data is logged in production

## Performance Considerations

- PDF generation is cached when possible
- File cleanup runs asynchronously
- Debug logging can be disabled in production
- Database queries are optimized with proper indexing

This documentation should help any developer understand and maintain the Timetics PDF Addon system effectively.
