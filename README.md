# Timetics PDF Addon

A WordPress plugin that automatically converts Timetics booking emails to PDF invoices and attaches them to the same email.

## Features

- **Automatic PDF Generation**: Converts booking confirmation emails to professional PDF invoices
- **Medical Information Integration**: Extracts and includes customer medical aid details
- **Email Attachment**: Seamlessly attaches PDF to the original email
- **Service Mapping**: Maps services to appropriate medical codes and pricing
- **Admin Interface**: Debug tools and settings for troubleshooting
- **REST API**: Admin endpoints for medical data retrieval

## Installation

1. Upload the plugin files to `/wp-content/plugins/timetics-pdf-addon/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure settings in the admin panel if needed

## Requirements

- WordPress 5.2 or higher
- PHP 7.4 or higher
- Timetics plugin (for booking data)

## Changelog

### v2.6.1 - CRITICAL FIX: Fixed Missing Booking ID Parameter
- **CRITICAL ISSUE IDENTIFIED**: The create_invoice_pdf_html function was being called WITHOUT the booking_id parameter
- **ROOT CAUSE**: Line 984 was calling create_invoice_pdf_html($subject, $message) instead of create_invoice_pdf_html($subject, $message, $booking_id)
- **IMPACT**: This prevented medical info extraction from working because the function couldn't access booking data
- **FIXED**: Added booking ID extraction and now passes booking_id parameter to enable medical info extraction

### v2.6.0 - CRITICAL DEBUG: Direct Debugging for Medical Info Extraction
- **Added debug logs to create_invoice_pdf_html function**
- **Added error handling around parse_email_data call**
- **Added fallback data with medical info placeholders**
- **This will definitively show if the function is being called and if there are errors**

### v2.5.2
- **CRITICAL FIX**: Removed non-existent ContextAwareExtractor::enhance() method call
- Fixed fatal error: Call to undefined method ContextAwareExtractor::enhance()
- Removed the problematic method call entirely
- Data is already parsed by StructuredEmailParser, no enhancement needed
- Thoroughly checked all method calls to ensure no other similar issues

### v2.5.1
- **CRITICAL FIX**: Fixed StructuredEmailParser method call from parse() to parseEmail()
- Fixed fatal error: Call to undefined method StructuredEmailParser::parse()
- Method name corrected to match actual class implementation

### v2.5.0
- Clean working version based on v2.4.4
- Guaranteed email sending with medical info extraction
- Simplified approach to prevent email blocking

### v2.4.11
- Simplified approach based on working v2.4.4
- Removed complex enforcement logic that was blocking emails
- Kept essential medical lookup functionality
- Fixed meta key usage for customer email lookup

### v2.4.10
- Added error handling to prevent email blocking
- Wrapped enforcement logic in try-catch blocks

### v2.4.9
- Direct DB-first medical info retrieval
- REST endpoint for admin access
- Email-scoped recent booking lookup
- Persist last-known medical per email

### v2.4.8
- Enforce medical info resolution before PDF generation
- Add last-known per-email cache
- Scope recent booking fallback by email
- Integrate context-based booking resolver

### v2.4.7
- Email-only fallback to enrich medical info when booking ID missing

### v2.4.6
- Added booking ID resolver from email context
- Wired admin debug button

### v2.4.5
- Added comprehensive production debugging system
- Capture all email processing data

### v2.4.4
- Fixed customer medical information extraction
- Added fallback mechanism for recent bookings

### v2.4.3
- Fixed TCPDF autoload issue by including PDF library directly

### v2.4.2
- Added PDF viewer to admin debug interface
- Enhanced troubleshooting capabilities

### v2.4.1
- Fixed PDF attachment issue
- Resolved double email sending
- Unified email flow

### v2.4.0
- Race condition fixes for concurrent bookings
- Improved booking ID retrieval
- Enhanced customer medical information extraction
- WordPress transient storage

## API Endpoints

### GET /wp-json/timetics-pdf/v1/medical

Retrieve medical information for a booking or email.

**Parameters:**
- `booking_id` (optional): Booking ID to lookup
- `email` (optional): Customer email to lookup recent bookings

**Example:**
```
GET /wp-json/timetics-pdf/v1/medical?booking_id=123
GET /wp-json/timetics-pdf/v1/medical?email=customer@example.com
```

## Support

For support and feature requests, please contact the plugin author.

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

**Obala Joseph Ivan**  
Website: https://codprez.com/  
Plugin URI: https://arraytics.com/timetics/