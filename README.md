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