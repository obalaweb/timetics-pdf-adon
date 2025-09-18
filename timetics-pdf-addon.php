<?php

/**
 * Plugin Name: Timetics PDF Addon
 * Plugin URI: https://arraytics.com/timetics/
 * Description: Automatically convert Timetics booking emails to PDF and attach them to the same email.
 * Version: 2.7.3
 * 
 * Changelog:
 * v2.7.3 - Minor formatting improvements: Removed CSS classes from PDF table cells for better PDF rendering
 * v2.7.2 - UI improvements: Added service name display in PDF items and removed company registration from address
 * v2.7.1 - Bug fixes: Fixed Staff::get_name() fatal error and updated branding to Dr Ben
 * v2.7.0 - MAJOR RELEASE: Comprehensive critical fixes and automated testing suite
 *          - CRITICAL FIX: Fixed fatal error - get_total_price() method doesn't exist, changed to get_total() method
 *          - CRITICAL FIX: Fixed substr() TypeError - array passed instead of string, added type checking
 *          - NEW: Complete automated test suite with 17 comprehensive tests
 *          - NEW: WordPress native test runner for easy validation
 *          - IMPROVED: Error handling and edge case management
 * v2.6.9 - CRITICAL FIX: Fixed fatal error - get_total_price() method doesn't exist, changed to get_total() method
 * v2.6.8 - CRITICAL FIX: Fixed Appointment and Booking method calls - get_title() -> get_name(), use Booking for dates/location
 * v2.6.7 - CRITICAL FIX: Fixed Customer::get_name() method call - added method_exists checks for different customer name methods
 * v2.6.6 - CRITICAL FIX: Fixed post_status from 'publish' to 'completed' - bookings have status 'completed' not 'publish'
 * v2.6.5 - CRITICAL DEBUG: Added detailed database query debugging to identify why booking lookup returns NULL
 * v2.6.4 - CRITICAL FIX: Changed from email content extraction to database lookup for booking_id - email content doesn't contain booking ID
 * v2.6.3 - CRITICAL DEBUG: Added debug logging to extract_booking_id_from_email to identify why booking_id is NULL
 * v2.6.2 - CRITICAL DEBUG: Added error_log to create_invoice_pdf_html to confirm function is being called and identify why debug logs aren't appearing
 * v2.6.1 - CRITICAL FIX: Fixed missing booking_id parameter in create_invoice_pdf_html call - this was preventing medical info extraction
 * v2.6.0 - CRITICAL DEBUG: Added direct debugging to create_invoice_pdf_html to identify medical info extraction issue
 * v2.5.9 - DEBUG: Added debug logging to GitHub updater to troubleshoot update detection
 * v2.5.8 - DEBUG: Added more obvious debug logs to confirm function calls are working
 * v2.5.7 - DEBUG: Added error logging to get_email_signature to identify why medical info extraction isn't working
 * v2.5.6 - FEATURE: Added GitHub updater functionality for automatic plugin updates from WordPress admin
 * v2.5.5 - CRITICAL FIX: Removed call to non-existent init_github_updater() method that was causing fatal error
 * v2.5.4 - COMPREHENSIVE DEBUG: Added detailed logging throughout entire medical info extraction pipeline to identify root cause
 * v2.5.3 - DEBUG: Added comprehensive debugging for medical info extraction to identify why medical data is not being populated
 * v2.5.2 - CRITICAL FIX: Removed non-existent ContextAwareExtractor::enhance() method call
 * v2.5.1 - CRITICAL FIX: Fixed StructuredEmailParser method call from parse() to parseEmail()
 * v2.5.0 - Clean working version based on v2.4.4; guaranteed email sending with medical info extraction
 * v2.4.11 - Simplified approach based on working v2.4.4; removed complex enforcement; kept essential medical lookup
 * v2.4.10 - Added error handling to prevent email blocking; wrapped enforcement logic in try-catch
 * v2.4.9 - Direct DB-first medical info retrieval; REST endpoint for admin access; email-scoped
 *          recent booking lookup; persist last-known medical per email
 * v2.4.8 - Enforce medical info resolution before PDF; add last-known per-email cache; scope
 *          recent booking fallback by email; integrate context-based booking resolver in parse
 * v2.4.7 - Email-only fallback to enrich medical info when booking ID missing
 * v2.4.6 - Added booking ID resolver from email context and wired admin debug button
 * v2.4.5 - Added comprehensive production debugging system to capture all email processing data
 * v2.4.4 - Fixed customer medical information extraction, added fallback mechanism for recent bookings
 * v2.4.3 - Fixed TCPDF autoload issue by including PDF library directly in plugin
 * v2.4.2 - Added PDF viewer to admin debug interface, enhanced troubleshooting capabilities
 * v2.4.1 - Fixed PDF attachment issue, resolved double email sending, unified email flow
 * v2.4.0 - Race condition fixes for concurrent bookings, improved booking ID retrieval, 
 *           enhanced customer medical information extraction, WordPress transient storage
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Obala Joseph Ivan
 * Author URI: https://codprez.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: timetics-pdf-addon
 * Domain Path: /languages
 *
 * @package Timetics_Pdf_Addon
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Simple Timetics PDF Addon
 * Converts Timetics emails to PDF and attaches them
 */
class Timetics_Pdf_Addon
{

    /**
     * Plugin version.
     */
    const VERSION = '2.7.3';

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_github_updater();
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies()
    {
        // Load new parsing classes
        require_once __DIR__ . '/includes/StructuredEmailParser.php';
        require_once __DIR__ . '/includes/DataValidator.php';
        require_once __DIR__ . '/includes/ContextAwareExtractor.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Hook into WordPress email system
        add_filter('wp_mail', [$this, 'maybe_attach_pdf'], 10, 1);

        // Hook into Timetics specific email hooks if they exist
        add_action('timetics_booking_email_sent', [$this, 'handle_timetics_email'], 10, 2);
        add_action('timetics_confirmation_email_sent', [$this, 'handle_timetics_email'], 10, 2);
        
        // Clean up expired transients periodically
        add_action('wp_loaded', [$this, 'maybe_cleanup_transients']);

        // Hook to modify email subjects from "meeting" to "appointment"
        add_filter('wp_mail', [$this, 'modify_email_subjects'], 5, 1);

        // Short-circuit email sending for excluded services (e.g., Echocardiogram)
        add_filter('pre_wp_mail', [$this, 'suppress_excluded_service_email'], 5, 2);

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

		// REST API routes
		add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Add AJAX handler for viewing Timetics services
        add_action('wp_ajax_timetics_pdf_view_services', [$this, 'ajax_view_services']);
        
        // Add AJAX handler for PDF debugging
        add_action('wp_ajax_timetics_pdf_debug', [$this, 'ajax_pdf_debug']);

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Scheduled cleanup hook
        add_action('timetics_pdf_cleanup', [$this, 'cleanup_old_pdfs']);
    }

    /**
     * Initialize GitHub updater for automatic plugin updates.
     */
    private function init_github_updater()
    {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }

        // Add update checker
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_plugin_update']);
        add_filter('plugins_api', [$this, 'plugin_api_call'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }

    /**
     * Check for plugin updates from GitHub.
     */
    public function check_for_plugin_update($transient)
    {
        error_log("TIMETICS_DEBUG: check_for_plugin_update called");
        
        if (empty($transient->checked)) {
            error_log("TIMETICS_DEBUG: No checked plugins, returning transient");
            return $transient;
        }

        // Get latest release from GitHub
        $latest_release = $this->get_latest_github_release();
        
        if (!$latest_release) {
            return $transient;
        }

        $plugin_file = plugin_basename(__FILE__);
        $current_version = self::VERSION;
        $latest_version = $latest_release['tag_name'];

        // Remove 'v' prefix if present
        $latest_version = ltrim($latest_version, 'v');

        // Debug logging for version comparison
        error_log("TIMETICS_DEBUG: Current version: $current_version, Latest version: $latest_version");
        
        // Check if update is available
        if (version_compare($current_version, $latest_version, '<')) {
            error_log("TIMETICS_DEBUG: Update available! Adding to response.");
            $transient->response[$plugin_file] = (object) [
                'slug' => 'timetics-pdf-addon',
                'plugin' => $plugin_file,
                'new_version' => $latest_version,
                'url' => 'https://github.com/obalaweb/timetics-pdf-adon',
                'package' => $latest_release['zipball_url'],
                'icons' => [],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => get_bloginfo('version'),
                'requires_php' => '7.4',
                'compatibility' => new stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Get latest release from GitHub API.
     */
    private function get_latest_github_release()
    {
        $cache_key = 'timetics_pdf_latest_release';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get('https://api.github.com/repos/obalaweb/timetics-pdf-adon/releases/latest', [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress Plugin Updater'
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['tag_name'])) {
            return false;
        }

        // Cache for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        
        return $data;
    }

    /**
     * Handle plugin API calls for update information.
     */
    public function plugin_api_call($result, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== 'timetics-pdf-addon') {
            return $result;
        }

        $latest_release = $this->get_latest_github_release();
        
        if (!$latest_release) {
            return $result;
        }

        return (object) [
            'name' => 'Timetics PDF Addon',
            'slug' => 'timetics-pdf-addon',
            'version' => ltrim($latest_release['tag_name'], 'v'),
            'author' => 'Obala Joseph Ivan',
            'author_profile' => 'https://codprez.com/',
            'requires' => '5.2',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'last_updated' => $latest_release['published_at'],
            'homepage' => 'https://github.com/obalaweb/timetics-pdf-adon',
            'sections' => [
                'description' => 'Automatically convert Timetics booking emails to PDF and attach them to the same email.',
                'changelog' => $latest_release['body'] ?? 'No changelog available.',
            ],
            'download_link' => $latest_release['zipball_url'],
        ];
    }

    /**
     * Handle post-install actions.
     */
    public function post_install($true, $hook_extra, $result)
    {
        global $wp_filesystem;

        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'timetics-pdf-addon';
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;

        if (is_plugin_active('timetics-pdf-addon/timetics-pdf-addon.php')) {
            activate_plugin('timetics-pdf-addon/timetics-pdf-addon.php');
        }

        return $result;
    }

    /**
     * Modify email subjects from "meeting" to "appointment" terminology.
     */
    public function modify_email_subjects($args)
    {
        // Check if email terminology modification is enabled
        if (!get_option('timetics_pdf_modify_email_terminology', true)) {
            return $args;
        }

        // Check if this is a Timetics email by looking for specific patterns
        if (!$this->is_timetics_email($args)) {
            return $args;
        }

        $subject = $args['subject'] ?? '';
        
        // Replace "meeting" with "appointment" in email subjects
        $subject_replacements = [
            'New meeting scheduled!' => 'New appointment scheduled!',
            'Meeting cancelled' => 'Appointment cancelled',
            'Meeting rescheduled!' => 'Appointment rescheduled!',
            'meeting' => 'appointment',
            'Meeting' => 'Appointment'
        ];

        foreach ($subject_replacements as $search => $replace) {
            if (strpos($subject, $search) !== false) {
                $args['subject'] = str_replace($search, $replace, $subject);
                $this->log_info('Email subject modified: "' . $search . '" → "' . $replace . '"');
                break; // Only apply the first match to avoid multiple replacements
            }
        }

        // Also modify email body content
        $message = $args['message'] ?? '';
        if (!empty($message)) {
            $message_replacements = [
                'A new meeting has been scheduled' => 'A new appointment has been scheduled',
                'meeting has been scheduled' => 'appointment has been scheduled',
                'meeting has been cancelled' => 'appointment has been cancelled',
                'meeting has been rescheduled' => 'appointment has been rescheduled',
                '{%meeting_title%}' => '{%appointment_title%}',
                'meeting' => 'appointment',
                'Meeting' => 'Appointment'
            ];

            foreach ($message_replacements as $search => $replace) {
                if (strpos($message, $search) !== false) {
                    $args['message'] = str_replace($search, $replace, $message);
                    $this->log_info('Email body modified: "' . $search . '" → "' . $replace . '"');
                    break; // Only apply the first match to avoid multiple replacements
                }
            }
        }

        return $args;
    }

    /**
     * Check if this is a Timetics email and attach PDF.
     */
    public function maybe_attach_pdf($args)
    {
        $this->debug_log('maybe_attach_pdf called', ['subject' => $args['subject'] ?? 'N/A']);

        // Validate input arguments
        if (!$this->validate_email_args($args)) {
            $this->log_error('Invalid email arguments provided to maybe_attach_pdf');
            return $args;
        }

        // Check if this looks like a Timetics booking email
        if (!$this->is_timetics_email($args)) {
            $this->debug_log('Email not identified as Timetics email', ['subject' => $args['subject'] ?? 'N/A']);
            return $args;
        }

        $this->debug_log('Email identified as Timetics email - proceeding with PDF attachment', ['subject' => $args['subject'] ?? 'N/A']);

        // Check if PDF generation is enabled
        if (!get_option('timetics_pdf_enabled', true)) {
            $this->log_info('PDF generation is disabled, skipping PDF attachment');
            return $args;
        }

        // Check for excluded services (e.g., Echocardiogram) AFTER confirming it's a Timetics email
        if ($this->is_excluded_service($args['subject'] ?? '', $args['message'] ?? '')) {
            $this->log_info('PDF generation skipped for excluded service');
            return $args; // Return without PDF attachment
        }

        try {
            $this->debug_log('Starting PDF generation');
            
            // Generate or reuse PDF from email content using content signature
            $pdf_path = $this->get_or_generate_pdf($args);

            $this->debug_log('PDF generation completed', ['pdf_path' => $pdf_path, 'file_exists' => $pdf_path ? file_exists($pdf_path) : false]);

            if ($pdf_path && file_exists($pdf_path)) {
                $this->debug_log('PDF file exists, validating', ['file_size' => filesize($pdf_path)]);
                
                // Validate PDF file
                if ($this->validate_pdf_file($pdf_path)) {
                    $this->debug_log('PDF file is valid, attaching to email', ['attachments_count' => count($args['attachments'] ?? [])]);
                    
                    // Add PDF as attachment
                    if (!isset($args['attachments'])) {
                        $args['attachments'] = [];
                    }
                    $args['attachments'][] = $pdf_path;

                    // Add note to email body
                    $args['message'] .= "\n\n---\nA PDF confirmation has been attached to this email.";

                    $this->debug_log('PDF successfully attached to email: ' . basename($pdf_path));
                } else {
                    $this->log_error('Generated PDF file is invalid: ' . $pdf_path);
                    // Clean up invalid file
                    if (file_exists($pdf_path)) {
                        unlink($pdf_path);
                    }
                }
            } else {
                $this->log_error('Failed to generate PDF file - file does not exist', ['pdf_path' => $pdf_path]);
            }
        } catch (Exception $e) {
            $this->log_error('PDF generation failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $args;
    }

    /**
     * Handle Timetics specific email hooks.
     */
    public function handle_timetics_email($booking_id, $email_data)
    {
        try {
            $this->log_info('Timetics hook triggered', ['booking_id' => $booking_id, 'subject' => $email_data['subject'] ?? 'N/A']);
            
            // CACHE TIMETICS HOOK DATA
            $this->cache_production_debug_data('TIMETICS_HOOK_TRIGGERED', [
                'booking_id' => $booking_id,
                'email_data' => $email_data,
                'hook_source' => 'timetics_booking_email_sent'
            ]);
            
            // Store booking ID for potential use by wp_mail filter
            $this->store_booking_id($booking_id, $email_data);
            
            // Skip entirely if excluded service is detected
            if ($this->is_excluded_service($email_data['subject'] ?? '', $email_data['message'] ?? '')) {
                $this->log_info('Timetics hook: skipped PDF for excluded service');
                return;
            }

            // Generate PDF from booking data and store it for wp_mail filter to use
            $this->log_info('Timetics hook: generating PDF from booking data', ['booking_id' => $booking_id]);
            $pdf_path = $this->generate_pdf_from_booking($booking_id, $email_data);

            if ($pdf_path && file_exists($pdf_path)) {
                // Store the PDF path for the wp_mail filter to attach
                $this->store_generated_pdf($email_data, $pdf_path);
                $this->log_info('Timetics hook: PDF generated and stored for wp_mail filter', ['pdf_path' => $pdf_path, 'file_size' => filesize($pdf_path)]);
            } else {
                $this->log_error('Timetics hook: PDF generation failed', ['booking_id' => $booking_id, 'pdf_path' => $pdf_path]);
            }
        } catch (Exception $e) {
            $this->log_error('Timetics hook error: ' . $e->getMessage(), ['booking_id' => $booking_id]);
            error_log('Timetics PDF Addon Error: ' . $e->getMessage());
        }
    }

    /**
     * Maybe cleanup expired transients (called on wp_loaded).
     */
    public function maybe_cleanup_transients()
    {
        // Only cleanup occasionally (1% chance) to avoid performance impact
        if (rand(1, 100) === 1) {
            $this->cleanup_expired_booking_transients();
        }
    }

    /**
     * Validate email arguments.
     */
    private function validate_email_args($args)
    {
        if (!is_array($args)) {
            return false;
        }

        // Check required fields
        if (!isset($args['subject']) || !isset($args['message'])) {
            return false;
        }

        // Validate subject and message are strings
        if (!is_string($args['subject']) || !is_string($args['message'])) {
            return false;
        }

        // Check for reasonable length limits
        if (strlen($args['subject']) > 500 || strlen($args['message']) > 50000) {
            return false;
        }

        return true;
    }

    /**
     * Validate PDF file.
     */
    private function validate_pdf_file($file_path)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        // Check file size (max 10MB)
        if (filesize($file_path) > 10 * 1024 * 1024) {
            return false;
        }

        // Check if file is readable
        if (!is_readable($file_path)) {
            return false;
        }

        // Basic PDF header check
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === '%PDF';
    }

    /**
     * Check if the email content refers to an excluded service (e.g., Echocardiogram).
     */
    private function is_excluded_service($subject, $message)
    {
        $content = strtolower(($subject ?? '') . ' ' . ($message ?? ''));
        $excluded_services = [
            'echocardiogram',
            'echocardiogram scan',
            'echocardiography',
            'echo scan',
            'cardiac echo',
            'ecd',
            'ecd scan'
        ];

        foreach ($excluded_services as $excluded_service) {
            if (strpos($content, $excluded_service) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a stable signature for an email's content to reuse the same PDF across recipients.
     */
    private function get_email_signature($args)
    {
        // Prefer a signature based on parsed, structured data to remain stable across recipients
        $subject = $args['subject'] ?? '';
        $message = $args['message'] ?? '';

        $this->log_info('=== GET_EMAIL_SIGNATURE CALLED ===');
        $this->log_info('DEBUG: get_email_signature called with subject: ' . substr($subject, 0, 50) . '...');

        try {
            $data = $this->parse_email_data($subject, $message);
            // Build a compact canonical string from key invoice fields
            $parts = [
                'invoice_number' => $data['invoice_number'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'appointment_date' => $data['appointment_date'] ?? '',
                'appointment_time' => $data['appointment_time'] ?? '',
                'service_code' => $data['service_code'] ?? '',
                'icd_code' => $data['icd_code'] ?? '',
                'service_description' => $data['service_description'] ?? '',
                'unit_price' => isset($data['unit_price']) ? number_format((float)$data['unit_price'], 2, '.', '') : '',
                'subtotal' => isset($data['subtotal']) ? number_format((float)$data['subtotal'], 2, '.', '') : '',
                'total_amount' => isset($data['total_amount']) ? number_format((float)$data['total_amount'], 2, '.', '') : '',
                'amount_paid' => isset($data['amount_paid']) ? number_format((float)$data['amount_paid'], 2, '.', '') : '',
                'amount_due' => isset($data['amount_due']) ? number_format((float)$data['amount_due'], 2, '.', '') : ''
            ];

            // Include first item if present
            if (!empty($data['items']) && is_array($data['items'])) {
                $firstItem = $data['items'][0];
                $parts['item_service_code'] = $firstItem['service_code'] ?? '';
                $parts['item_icd_code'] = $firstItem['icd_code'] ?? '';
                $parts['item_description'] = $firstItem['description'] ?? '';
                $parts['item_quantity'] = isset($firstItem['quantity']) ? number_format((float)$firstItem['quantity'], 2, '.', '') : '';
                $parts['item_unit_price'] = isset($firstItem['unit_price']) ? number_format((float)$firstItem['unit_price'], 2, '.', '') : '';
                $parts['item_subtotal'] = isset($firstItem['subtotal']) ? number_format((float)$firstItem['subtotal'], 2, '.', '') : '';
            }

            $canonical = strtolower(implode('|', array_values($parts)));
            return sha1($canonical);
        } catch (Exception $e) {
            // Log the error so we can see what's happening
            $this->log_error('ERROR in get_email_signature: ' . $e->getMessage());
            $this->log_error('ERROR trace: ' . $e->getTraceAsString());
            
            // Fallback to normalized subject+message
            $subjectSafe = $this->sanitize_text($subject);
            $messageSafe = $this->sanitize_html($message);
            $normalized = strtolower(trim($subjectSafe . "\n" . $messageSafe));
            return sha1($normalized);
        }
    }

    /**
     * Get existing PDF for this email signature or generate a new one and cache the path.
     */
    private function get_or_generate_pdf($args)
    {
        $this->log_info('=== GET_OR_GENERATE_PDF CALLED ===');
        $signature = $this->get_email_signature($args);
        $transient_key = 'timetics_pdf_' . $signature;

        // First, try to get pre-generated PDF from Timetics hooks
        $cached_path = get_transient($transient_key);
        if (!empty($cached_path) && is_string($cached_path) && file_exists($cached_path) && $this->validate_pdf_file($cached_path)) {
            $this->log_info('Using pre-generated PDF from Timetics hook for signature: ' . $signature);
            return $cached_path;
        }

        // Generate a new PDF
        $pdf_path = $this->generate_pdf_from_email($args);
        if ($pdf_path && file_exists($pdf_path) && $this->validate_pdf_file($pdf_path)) {
            // Cache for 20 minutes to cover both customer and team emails
            set_transient($transient_key, $pdf_path, 20 * MINUTE_IN_SECONDS);
            return $pdf_path;
        }

        return null;
    }

    /**
     * Log error message.
     */
    private function log_error($message, $context = [])
    {
        $log_message = '[Timetics PDF Addon ERROR] ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }

        error_log($log_message);

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_message);
        }
    }

    /**
     * Force log a debug message (always logs regardless of debug setting)
     */
    private function debug_log($message, $context = [])
    {
        $log_message = '[Timetics PDF Addon DEBUG] ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }

        error_log($log_message);
    }

    /**
     * Log info message.
     */
    private function log_info($message, $context = [])
    {
        if (!get_option('timetics_pdf_debug_logging', false)) {
            return;
        }

        $log_message = '[Timetics PDF Addon INFO] ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }

        error_log($log_message);
    }

    /**
     * Suppress sending for excluded services by short-circuiting wp_mail.
     *
     * @param bool|null $return Short-circuit return value. Default null to continue.
     * @param array     $atts   Array of the email arguments passed to wp_mail().
     * @return bool|null
     */
    public function suppress_excluded_service_email($return, $atts)
    {
        // Only act if this is a Timetics email
        if (!$this->is_timetics_email($atts)) {
            return $return;
        }

        // Block if excluded service detected
        if ($this->is_excluded_service($atts['subject'] ?? '', $atts['message'] ?? '')) {
            $this->log_info('Email suppressed for excluded service');
            return true; // truthy short-circuit: wp_mail returns true without sending
        }

        return $return;
    }

    /**
     * Check if this is a Timetics email.
     */
    private function is_timetics_email($args)
    {
        $subject = $args['subject'] ?? '';
        $message = $args['message'] ?? '';
        $headers = $args['headers'] ?? '';

        $content = strtolower($subject . ' ' . $message . ' ' . $headers);

        // First, check for WooCommerce order confirmation emails and exclude them
        $woocommerce_patterns = [
            'order confirmation',
            'order #',
            'woocommerce',
            'your order',
            'order details',
            'billing address',
            'shipping address',
            'payment method',
            'order total',
            'thank you for your order',
            'order received',
            'order status',
            'track your order',
            'woocommerce order',
            'order summary',
            'order invoice',
            'payment confirmation',
            'order completed',
            'order shipped',
            'order delivered',
            // Custom WooCommerce subject patterns
            'your longevity clinic appointment has been booked',
            'longevity clinic appointment has been booked',
            'appointment has been booked'
        ];

        foreach ($woocommerce_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $this->log_info('Email excluded as WooCommerce order confirmation: ' . $pattern);
                return false;
            }
        }

        // Check for other common e-commerce patterns that should be excluded
        $ecommerce_patterns = [
            'invoice #',
            'receipt #',
            'transaction #',
            'payment receipt',
            'billing receipt',
            'purchase confirmation',
            'shipping confirmation',
            'delivery confirmation',
            'subscription',
            'membership',
            'renewal',
            'payment failed',
            'payment pending',
            // WooCommerce specific patterns
            'woocommerce',
            'order id:',
            'order key:',
            'billing address:',
            'shipping address:',
            'payment method:',
            'order total:',
            'order notes:',
            'view order:',
            'order details:',
            'item',
            'quantity',
            'price',
            'subtotal:',
            'tax:',
            'shipping:',
            'total:'
        ];

        foreach ($ecommerce_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $this->log_info('Email excluded as e-commerce email: ' . $pattern);
                return false;
            }
        }

        // Check custom exclusion patterns
        $custom_exclusions = get_option('timetics_pdf_custom_exclusions', '');
        if (!empty($custom_exclusions)) {
            $custom_patterns = array_filter(array_map('trim', explode("\n", $custom_exclusions)));
            foreach ($custom_patterns as $pattern) {
                if (!empty($pattern) && strpos($content, strtolower($pattern)) !== false) {
                    $this->log_info('Email excluded by custom pattern: ' . $pattern);
                    return false;
                }
            }
        }

        // Note: Echocardiogram exclusion is handled in maybe_attach_pdf() after Timetics email detection

        // Check for specific Timetics email patterns
        $timetics_patterns = [
            // Must contain specific Timetics structure
            'your appointment has been successfully scheduled',
            'consultation details:',
            'practitioner information:',
            'dr ben coetsee',
            'dr ben',
            'practitioner number:',
            'practice number:',
            'icd-10:',
            'icd10',
            'val de vie',
            'polo village offices',
            'cancellation policy:',
            'parking & directions:',
            'warm regards,',
            'dr ben coetsee & team',
            // More specific patterns to distinguish from WooCommerce
            'type: iv drip',
            'type: prescription',
            'type: red light therapy',
            'type: inbody analysis',
            'type: blood results',
            'type: sports injury',
            'type: longevity',
            'type: weight loss',
            'duration:',
            'location: val de vie',
            'practitioner: dr ben coetsee',
            'practitioner number: mp0953814',
            'practice number: pr1153307'
        ];

        $timetics_score = 0;
        foreach ($timetics_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $timetics_score++;
            }
        }

        // Get the minimum score required from settings (default: 3)
        $min_score = get_option('timetics_pdf_min_score', 3);
        
        // Require at least the configured minimum Timetics-specific patterns to be considered a Timetics email
        if ($timetics_score >= $min_score) {
            $this->log_info('Email identified as Timetics email with score: ' . $timetics_score . ' (min required: ' . $min_score . ')');
            return true;
        }

        // Additional check: Look for the specific email structure that Timetics uses
        // Must have the complete Timetics structure, not just partial matches
        $structure_checks = [
            'type:\s*.+' => 'Service type field',
            'date:\s*.+' => 'Date field', 
            'time:\s*.+' => 'Time field',
            'practitioner:\s*.+' => 'Practitioner field',
            'your name:\s*.+' => 'Customer name field',
            'your email:\s*.+' => 'Customer email field'
        ];
        
        $structure_score = 0;
        foreach ($structure_checks as $pattern => $description) {
            if (preg_match('/' . $pattern . '/i', $content)) {
                $structure_score++;
            }
        }
        
        // Require at least 4 out of 6 structure fields to be present
        if ($structure_score >= 4) {
            $this->log_info('Email identified as Timetics email based on structure patterns (score: ' . $structure_score . '/6)');
            return true;
        }

        // Check if this is from a Timetics-specific sender or domain
        if (isset($args['headers']) && is_array($args['headers'])) {
            foreach ($args['headers'] as $header) {
                if (stripos($header, 'from:') === 0) {
                    $from_header = strtolower($header);
                    if (strpos($from_header, 'timetics') !== false || 
                        strpos($from_header, 'dr ben') !== false ||
                        strpos($from_header, 'performance md') !== false) {
                        $this->log_info('Email identified as Timetics email based on sender: ' . $header);
                        return true;
                    }
                }
            }
        }

        $this->log_info('Email not identified as Timetics email (score: ' . $timetics_score . ')');
        return false;
    }

    /**
     * Generate PDF from email content.
     */
    private function generate_pdf_from_email($email_args)
    {
        $this->debug_log('generate_pdf_from_email started');

        // Validate input
        if (!$this->validate_email_args($email_args)) {
            throw new Exception('Invalid email arguments provided');
        }

        $this->debug_log('Email arguments validated');

        // Load TCPDF
        if (!class_exists('TCPDF')) {
            $tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
            $this->debug_log('Checking TCPDF path', ['path' => $tcpdf_path]);
            
            if (!file_exists($tcpdf_path)) {
                throw new Exception('TCPDF file not found: ' . $tcpdf_path);
            }
            require_once($tcpdf_path);
            $this->debug_log('TCPDF file loaded');
        }

        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF class not available after autoload');
        }

        $this->debug_log('TCPDF class is available');

        // Check memory and time limits
        $this->check_system_limits();

        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Dr Ben Coetsee');
        $pdf->SetAuthor('Dr Ben Coetsee');
        $pdf->SetTitle('Consultation Confirmation');
        $pdf->SetSubject('Booking Confirmation');

        // Disable default header and footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(30);

        // Add a page
        $pdf->AddPage();

        // Get email content and sanitize
        $subject = $this->sanitize_text($email_args['subject'] ?? 'Consultation Confirmation');
        $message = $this->sanitize_html($email_args['message'] ?? '');

        // Create HTML content for PDF using the index.html template structure
        // Get booking ID from database using customer email for medical info extraction
        $customer_email = $this->extract_customer_email_from_args($email_args);
        $booking_id = $this->get_latest_booking_id_by_email($customer_email);
        error_log('TIMETICS_DEBUG: Found booking ID for PDF generation: ' . ($booking_id ? $booking_id : 'NULL') . ' for email: ' . ($customer_email ? $customer_email : 'NULL'));
        $html = $this->create_invoice_pdf_html($subject, $message, $booking_id);

        // Validate HTML content
        if (empty($html) || strlen($html) > 100000) {
            throw new Exception('Invalid or oversized HTML content generated');
        }

        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Add custom footer text at the bottom of the same page
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 3, 'Cancellation Policy: Kindly provide at least 24 hours\' notice. Cancellations made less than 24 hours before the appointment will be charged at the full consultation fee. We appreciate your understanding and cooperation.', 0, 'C', 0, 1, '', '', true, 0, false, true, 0, 'T', false);

        // Generate secure filename
        $filename = $this->generate_secure_filename();
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';

        // Create directory if it doesn't exist
        if (!file_exists($pdf_dir)) {
            if (!wp_mkdir_p($pdf_dir)) {
                throw new Exception('Failed to create PDF directory');
            }
        }

        // Ensure directory is writable
        if (!is_writable($pdf_dir)) {
            throw new Exception('PDF directory is not writable');
        }

        $pdf_path = $pdf_dir . $filename;

        $this->debug_log('Outputting PDF to file', ['path' => $pdf_path]);

        // Output PDF to file
        $pdf->Output($pdf_path, 'F');

        $this->debug_log('PDF output completed, validating file');

        // Validate the generated PDF
        if (!$this->validate_pdf_file($pdf_path)) {
            throw new Exception('Generated PDF file is invalid');
        }

        $this->debug_log('PDF validation passed');
        $this->log_info('PDF generated successfully: ' . basename($pdf_path));

        return $pdf_path;
    }

    /**
     * Generate PDF from booking data.
     */
    private function generate_pdf_from_booking($booking_id, $email_data)
    {
        // Load TCPDF
        if (!class_exists('TCPDF')) {
            require_once(__DIR__ . '/tcpdf/tcpdf.php');
        }

        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF not available');
        }

        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Dr Ben Coetsee');
        $pdf->SetAuthor('Dr Ben Coetsee');
        $pdf->SetTitle('Confirmation');
        $pdf->SetSubject('Confirmation');

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Add a page
        $pdf->AddPage();

        // Create HTML content for PDF using invoice template
        $subject = $email_data['subject'] ?? 'Consultation Confirmation';
        $message = $email_data['message'] ?? '';
        $html = $this->create_invoice_pdf_html($subject, $message, $booking_id);

        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Generate unique filename
        $filename = 'confirmation-' . $booking_id . '-' . date('Y-m-d-H-i-s') . '.pdf';
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';

        // Create directory if it doesn't exist
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $pdf_path = $pdf_dir . $filename;

        // Output PDF to file
        $pdf->Output($pdf_path, 'F');

        return $pdf_path;
    }

    /**
     * Parse email content to extract structured data for PDF using improved parser.
     */
    private function parse_email_data($subject, $message, $booking_id = null)
    {
        $this->log_info('=== MEDICAL INFO EXTRACTION DEBUG START ===');
        $this->log_info('Input - Subject: ' . substr($subject, 0, 100) . '...');
        $this->log_info('Input - Message length: ' . strlen($message) . ' characters');
        $this->log_info('Input - Booking ID: ' . ($booking_id ? $booking_id : 'NULL'));
        
        try {
            
            // PRIMARY APPROACH: Try to get booking ID from multiple sources
            if (!$booking_id) {
                $this->log_info('DEBUG: No booking ID provided, trying to extract from email...');
                
                // First, try to get from stored cache (from Timetics hooks)
                $email_data = ['subject' => $subject, 'message' => $message];
                $booking_id = $this->get_stored_booking_id($email_data);
                $this->log_info('DEBUG: Stored booking ID result: ' . ($booking_id ? $booking_id : 'NOT FOUND'));
                
                
                // If not found in cache, try to extract from email content
                if (!$booking_id) {
            $booking_id = $this->extract_booking_id_from_email($subject, $message);
                    $this->log_info('DEBUG: Extracted booking ID from email: ' . ($booking_id ? $booking_id : 'NOT FOUND'));
                    
                }

            } else {
                $this->log_info('DEBUG: Booking ID provided directly: ' . $booking_id);
                
            }
            
            if ($booking_id && is_numeric($booking_id)) {
                $this->log_info('MEDICAL_DEBUG: Using booking ID for Timetics data extraction: ' . $booking_id);
                $timetics_data = $this->get_data_from_timetics_objects($booking_id);
                
                
                if ($timetics_data) {
                    $this->log_info('MEDICAL_DEBUG: Successfully extracted data from Timetics objects for booking: ' . $booking_id);
                    $this->log_info('MEDICAL_DEBUG: Timetics data medical info: ' . json_encode([
                        'medical_aid_scheme' => $timetics_data['medical_aid_scheme'] ?? 'NOT SET',
                        'medical_aid_number' => $timetics_data['medical_aid_number'] ?? 'NOT SET',
                        'id_number' => $timetics_data['id_number'] ?? 'NOT SET'
                    ]));
                    return $timetics_data;
                } else {
                    $this->log_info('MEDICAL_DEBUG: Failed to extract data from Timetics objects for booking: ' . $booking_id);
                }
            } else {
                $this->log_info('MEDICAL_DEBUG: No valid booking ID available for Timetics data extraction');
            }

            // FALLBACK APPROACH: Use email parsing if Timetics objects fail
            $this->log_info('MEDICAL_DEBUG: Falling back to email parsing method');
            
            // Use the new structured email parser
            $parser = new StructuredEmailParser();
            $data = $parser->parseEmail($subject, $message);
            
            $this->log_info('MEDICAL_DEBUG: Email parser extracted data: ' . json_encode([
                'customer_email' => $data['customer_email'] ?? 'NOT SET',
                'medical_aid_scheme' => $data['medical_aid_scheme'] ?? 'NOT SET',
                'medical_aid_number' => $data['medical_aid_number'] ?? 'NOT SET',
                'id_number' => $data['id_number'] ?? 'NOT SET'
            ]));

            // Data is already parsed by StructuredEmailParser

            // Calculate unit price and totals if not present
            if (!isset($data['unit_price'])) {
                $service_info = $this->get_service_info_by_name($data['service_name'] ?? 'General Consultation', $booking_id);
                $data['unit_price'] = $service_info['base_price'] ?? 960.00;
                $data['total_amount'] = $data['unit_price'];
            }

            // Service codes and customer medical info
            $service_info = $this->get_service_info_by_name($data['service_name'], $booking_id);
            $data['service_code'] = $service_info['code'] ?? 'Code 0190';
            $data['icd_code'] = $service_info['icd_code'] ?? 'Z00.0';
            
            $this->log_info('MEDICAL_DEBUG: Service info medical data: ' . json_encode([
                'medical_aid_scheme' => $service_info['medical_aid_scheme'] ?? 'NOT SET',
                'medical_aid_number' => $service_info['medical_aid_number'] ?? 'NOT SET',
                'id_number' => $service_info['id_number'] ?? 'NOT SET'
            ]));
            
            // Add customer medical information if available
            if (isset($service_info['medical_aid_scheme'])) {
                $data['medical_aid_scheme'] = $service_info['medical_aid_scheme'];
                $this->log_info('MEDICAL_DEBUG: Added medical_aid_scheme from service info: ' . $service_info['medical_aid_scheme']);
            }
            if (isset($service_info['medical_aid_number'])) {
                $data['medical_aid_number'] = $service_info['medical_aid_number'];
                $this->log_info('MEDICAL_DEBUG: Added medical_aid_number from service info: ' . $service_info['medical_aid_number']);
            }
            if (isset($service_info['id_number'])) {
                $data['id_number'] = $service_info['id_number'];
                $this->log_info('MEDICAL_DEBUG: Added id_number from service info: ' . $service_info['id_number']);
            }
            if (isset($service_info['residential_address'])) {
                $data['residential_address'] = $service_info['residential_address'];
            }

            // SIMPLE medical info enhancement - like v2.4.4 but improved
            $this->log_info('MEDICAL_DEBUG: Before enhancement - medical data: ' . json_encode([
                'medical_aid_scheme' => $data['medical_aid_scheme'] ?? 'NOT SET',
                'medical_aid_number' => $data['medical_aid_number'] ?? 'NOT SET',
                'id_number' => $data['id_number'] ?? 'NOT SET'
            ]));
            
            $data = $this->enhance_with_booking_data($data, $message);
            
            $this->log_info('MEDICAL_DEBUG: After enhancement - medical data: ' . json_encode([
                'medical_aid_scheme' => $data['medical_aid_scheme'] ?? 'NOT SET',
                'medical_aid_number' => $data['medical_aid_number'] ?? 'NOT SET',
                'id_number' => $data['id_number'] ?? 'NOT SET'
            ]));

            // CACHE FINAL PARSED DATA
            $this->cache_production_debug_data('FINAL_PARSED_DATA', [
                'data' => $data,
                'booking_id_used' => $booking_id,
                'medical_info_status' => [
                    'medical_aid_scheme' => !empty($data['medical_aid_scheme']),
                    'medical_aid_number' => !empty($data['medical_aid_number']),
                    'id_number' => !empty($data['id_number']),
                    'residential_address' => !empty($data['residential_address'])
                ]
            ]);

            $this->log_info('=== MEDICAL INFO EXTRACTION DEBUG END ===');
            $this->log_info('FINAL RESULT - Medical data status: ' . json_encode([
                'medical_aid_scheme' => !empty($data['medical_aid_scheme']) ? 'FOUND: ' . $data['medical_aid_scheme'] : 'NOT PROVIDED',
                'medical_aid_number' => !empty($data['medical_aid_number']) ? 'FOUND: ' . $data['medical_aid_number'] : 'NOT PROVIDED',
                'id_number' => !empty($data['id_number']) ? 'FOUND: ' . $data['id_number'] : 'NOT PROVIDED'
            ]));
            
            return $data;
        } catch (Exception $e) {
            $this->log_error('MEDICAL_DEBUG: Error parsing email data: ' . $e->getMessage());
            $this->log_error('MEDICAL_DEBUG: Exception trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Fallback: enrich medical info from most recent booking by matching customer email only.
     */
    private function enrich_medical_info_from_email_only($subject, $message, array &$data)
    {
        try {
            $content = $subject . ' ' . $message;
            $customer_email = null;
            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $content, $em)) {
                $customer_email = strtolower($em[0]);
            }


            if (empty($customer_email)) {
                return;
            }

            $q = new \WP_Query([
                'post_type' => 'timetics-booking',
                'post_status' => ['publish', 'pending', 'draft'],
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => [[
                    'key' => '_tt_booking_customer_email',
                    'value' => $customer_email,
                    'compare' => '=',
                ]],
                'fields' => 'ids',
            ]);

            if ($q && !empty($q->posts)) {
                $booking_id = intval($q->posts[0]);

                $custom_form_data = get_post_meta($booking_id, '_tt_booking_custom_form_data', true);
                $unserialized_data = maybe_unserialize($custom_form_data);
                if (is_array($unserialized_data)) {
                    $email_medical = [
                        'medical_aid_scheme' => $unserialized_data['name_of_medical_aid_scheme'] ?? '',
                        'medical_aid_number' => $unserialized_data['medical_aid_number'] ?? '',
                        'id_number' => $unserialized_data['enter_your_id_number'] ?? '',
                        'residential_address' => $unserialized_data['enter_your_residential_address'] ?? '',
                    ];
                    foreach ($email_medical as $k => $v) {
                        if (!empty($v)) {
                            $data[$k] = $v;
                        }
                    }
                    // Persist as last-known for this email
                    $this->update_last_known_medical_info_by_email($customer_email, $email_medical);
                }
            } else {
            }
        } catch (\Exception $e) {
            $this->log_error('Email-only medical enrichment failed: ' . $e->getMessage());
        }
    }

    /**
     * Get default data structure when parsing fails.
     */
    private function get_default_data()
    {
        return [
            'customer_name' => 'Valued Patient',
            'medical_aid_scheme' => '',
            'medical_aid_number' => '',
            'id_number' => '',
            'appointment_date' => date('d F Y'),
            'appointment_time' => null,
            'service_name' => 'General Consultation',
            'service_code' => 'Code 0190',
            'icd_code' => 'Z00.0',
            'service_description' => 'General Consultation (Blood Results)',
            'unit_price' => 960.00,
            'quantity' => 1.00,
            'subtotal' => 960.00,
            'vat_amount' => 0.00,
            'total_amount' => 960.00,
            'amount_paid' => 960.00,
            'amount_due' => 0.00,
            'invoice_number' => 'INV-' . date('md') . rand(100, 999),
            'invoice_date' => date('j M Y'),
            'due_date' => date('j M Y'),
            'company_name' => 'Dr Ben',
            'practitioner_number' => 'MP0953814',
            'practice_number' => 'PR1153307',
            'company_registration' => '2024/748523/21',
            'items' => [[
                'service_code' => 'Code 0190',
                'icd_code' => 'Z00.0',
                'description' => 'General Consultation (Blood Results)',
                'quantity' => 1.00,
                'unit_price' => 960.00,
                'subtotal' => 960.00
            ]],
            'customer_email' => '',
        ];
    }

    /**
     * Enhance parsed data with custom fields from recent booking database records.
     * Simplified version based on v2.4.4 that was working.
     */
    private function enhance_with_booking_data($data, $message)
    {
        try {
            $customer_email = $data['customer_email'] ?? '';
            $this->log_info('ENHANCE_DEBUG: Starting enhancement for email: ' . $customer_email);
            
            if (empty($customer_email) || $customer_email === 'customer@example.com') {
                $this->log_info('ENHANCE_DEBUG: Skipping enhancement - invalid email');
                return $data;
            }
            
            // Look for recent booking with this email using correct meta key
            $recent_bookings = get_posts([
                'post_type' => 'timetics-booking',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_tt_booking_customer_email',
                        'value' => $customer_email,
                        'compare' => '='
                    ]
                ],
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            
            $this->log_info('ENHANCE_DEBUG: Found ' . count($recent_bookings) . ' recent bookings for email: ' . $customer_email);
            
            if (!empty($recent_bookings)) {
                $booking_id = $recent_bookings[0]->ID;
                $booking_meta = get_post_meta($booking_id);
                
                $this->log_info('ENHANCE_DEBUG: Using booking ID: ' . $booking_id);
                $this->log_info('ENHANCE_DEBUG: Booking meta keys: ' . implode(', ', array_keys($booking_meta)));
                
                // Try to get custom fields from the recent booking
                $custom_fields = $this->extract_custom_fields_from_meta($booking_meta);
                
                $this->log_info('ENHANCE_DEBUG: Extracted custom fields: ' . json_encode($custom_fields));
                
                // Enhance data with custom fields if they're missing
                if (empty($data['medical_aid_scheme']) && !empty($custom_fields['medical_aid_scheme'])) {
                    $data['medical_aid_scheme'] = $custom_fields['medical_aid_scheme'];
                    $this->log_info('Enhanced medical aid scheme from recent booking: ' . $custom_fields['medical_aid_scheme']);
                }
                
                if (empty($data['medical_aid_number']) && !empty($custom_fields['medical_aid_number'])) {
                    $data['medical_aid_number'] = $custom_fields['medical_aid_number'];
                    $this->log_info('Enhanced medical aid number from recent booking: ' . $custom_fields['medical_aid_number']);
                }
                
                if (empty($data['id_number']) && !empty($custom_fields['id_number'])) {
                    $data['id_number'] = $custom_fields['id_number'];
                    $this->log_info('Enhanced ID number from recent booking: ' . $custom_fields['id_number']);
                }
            }
            
        } catch (Exception $e) {
            $this->log_error('Error enhancing data with recent booking: ' . $e->getMessage());
        }
        
        return $data;
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes()
    {
        register_rest_route('timetics-pdf/v1', '/medical', [
            'methods' => 'GET',
            'callback' => function ($request) {
                $booking_id = intval($request->get_param('booking_id'));
                $email = sanitize_email($request->get_param('email'));

                if ($booking_id > 0) {
                    $info = $this->get_customer_medical_info_from_booking($booking_id);
                    return rest_ensure_response([
                        'source' => 'booking',
                        'booking_id' => $booking_id,
                        'medical' => $info ?: [],
                    ]);
                }

                if (!empty($email)) {
                    $info = $this->get_medical_info_from_recent_bookings($email);
                    return rest_ensure_response([
                        'source' => 'email_recent',
                        'email' => $email,
                        'medical' => $info ?: [],
                    ]);
                }

                return new \WP_Error('bad_request', 'Provide booking_id or email', ['status' => 400]);
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Check system limits for PDF generation.
     */
    private function check_system_limits()
    {
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);

        if ($memory_limit_bytes < 128 * 1024 * 1024) { // 128MB
            $this->log_error('Low memory limit detected: ' . $memory_limit);
        }

        // Check execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 60) {
            $this->log_error('Low execution time limit: ' . $max_execution_time . ' seconds');
        }
    }

    /**
     * Convert memory limit string to bytes.
     */
    private function convert_to_bytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Generate secure filename for PDF.
     */
    private function generate_secure_filename()
    {
        $timestamp = date('Y-m-d-H-i-s');
        $random = wp_generate_password(12, false);
        return 'confirmation-' . $timestamp . '-' . $random . '.pdf';
    }

    /**
     * Sanitize text input.
     */
    private function sanitize_text($text)
    {
        if (!is_string($text)) {
            return '';
        }

        // Remove null bytes and control characters
        $text = str_replace(["\0", "\x00"], '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Limit length
        $text = substr($text, 0, 1000);

        return sanitize_text_field($text);
    }

    /**
     * Sanitize HTML input and convert to clean text for PDF.
     */
    private function sanitize_html($html)
    {
        if (!is_string($html)) {
            return '';
        }

        // Remove null bytes and control characters
        $html = str_replace(["\0", "\x00"], '', $html);
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);

        // Limit length
        $html = substr($html, 0, 50000);

        // Convert HTML to clean text while preserving structure
        $html = $this->clean_html_for_pdf($html);

        return $html;
    }

    /**
     * Clean HTML specifically for PDF generation - no raw HTML tags should remain.
     */
    private function clean_html_for_pdf($html)
    {
        // Convert common HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Replace HTML line breaks with actual line breaks
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<p[^>]*>/i', '', $html);
        
        // Remove all HTML tags while preserving content
        $html = strip_tags($html);
        
        // Clean up multiple whitespace and newlines
        $html = preg_replace('/\n\s*\n\s*\n/', "\n\n", $html);
        $html = preg_replace('/[ \t]+/', ' ', $html);
        
        // Trim and return
        return trim($html);
    }

    /**
     * Clean HTML content to extract plain text.
     */
    private function clean_html_content($message)
    {
        // Remove HTML tags but preserve line breaks
        $clean = strip_tags($message, '<br><p>');

        // Convert HTML entities
        $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');

        // Replace HTML line breaks with newlines
        $clean = str_replace(['<br>', '<br/>', '<br />', '</p>', '<p>'], "\n", $clean);

        // Clean up multiple whitespace
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Remove extra newlines
        $clean = preg_replace('/\n\s*\n/', "\n", $clean);

        return trim($clean);
    }

    /**
     * Extract multiple items from email content.
     */
    private function extract_multiple_items($message, &$data)
    {
        // Look for item patterns in the email
        $lines = explode("\n", $message);
        $current_item = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Look for service codes
            if (preg_match('/code[:\s]+(\d+)/i', $line, $matches)) {
                if ($current_item) {
                    $data['items'][] = $current_item;
                }
                $current_item = [
                    'service_code' => 'Code ' . $matches[1],
                    'icd_code' => 'Z00.0',
                    'description' => 'General Consultation',
                    'quantity' => 1.00,
                    'unit_price' => 960.00
                ];
            }

            // Look for ICD codes
            if ($current_item && preg_match('/([A-Z]\d{2}\.\d)/', $line, $matches)) {
                $current_item['icd_code'] = $matches[1];
            }

            // Look for descriptions
            if ($current_item && preg_match('/consultation[:\s]+(.+)/i', $line, $matches)) {
                $current_item['description'] = trim($matches[1]);
            }

            // Look for prices
            if ($current_item && preg_match('/R(\d+(?:\.\d{2})?)/', $line, $matches)) {
                $current_item['unit_price'] = floatval($matches[1]);
            }
        }

        // Add the last item if exists
        if ($current_item) {
            $data['items'][] = $current_item;
        }

        // If no items found, create default item
        if (empty($data['items'])) {
            $data['items'][] = [
                'service_code' => $data['service_code'],
                'icd_code' => $data['icd_code'],
                'description' => $data['service_description'],
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price']
            ];
        }
    }

    /**
     * Calculate totals for all items.
     */
    private function calculate_totals(&$data)
    {
        $total_subtotal = 0;

        foreach ($data['items'] as &$item) {
            $item['subtotal'] = $item['quantity'] * $item['unit_price'];
            $total_subtotal += $item['subtotal'];
        }

        $data['subtotal'] = $total_subtotal;
        $data['total_amount'] = $data['subtotal'] + $data['vat_amount'];
        $data['amount_due'] = $data['total_amount'] - $data['amount_paid'];
    }

    /**
     * Format email content for PDF display.
     */
    private function format_email_content($message)
    {
        // If the message contains HTML, clean it up for PDF display
        if (strip_tags($message) !== $message) {
            // It contains HTML, so let's clean it up
            $message = wp_kses($message, [
                'p' => [],
                'br' => [],
                'strong' => [],
                'b' => [],
                'em' => [],
                'i' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'h1' => [],
                'h2' => [],
                'h3' => [],
                'h4' => [],
                'h5' => [],
                'h6' => [],
                'div' => ['class' => []],
                'span' => ['class' => []],
                'a' => ['href' => []]
            ]);
        } else {
            // It's plain text, convert line breaks to HTML
            $message = nl2br(esc_html($message));
        }

        return $message;
    }

    /**
     * Create invoice HTML content using the index.html template structure
     */
    private function create_invoice_pdf_html($subject, $message, $booking_id = null)
    {
        error_log('TIMETICS_CRITICAL: create_invoice_pdf_html called with booking_id: ' . ($booking_id ? $booking_id : 'NULL'));
        $this->log_info('=== CREATE_INVOICE_PDF_HTML CALLED ===');
        $this->log_info('DEBUG: create_invoice_pdf_html called with subject: ' . substr($subject, 0, 50) . '...');
        
        // Parse email data
        try {
            $data = $this->parse_email_data($subject, $message, $booking_id);
            $this->log_info('DEBUG: parse_email_data completed successfully');
        } catch (Exception $e) {
            $this->log_error('ERROR in create_invoice_pdf_html parse_email_data: ' . $e->getMessage());
            $this->log_error('ERROR trace: ' . $e->getTraceAsString());
            // Fallback to basic data
            $data = [
                'customer_name' => 'Customer',
                'customer_email' => 'customer@example.com',
                'service_name' => 'General Consultation',
                'unit_price' => 960.00,
                'total_amount' => 960.00,
                'medical_aid_scheme' => '[Not provided]',
                'medical_aid_number' => '[Not provided]',
                'id_number' => '[Not provided]'
            ];
        }

        $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice ' . esc_html($data['invoice_number']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            color: #333;
            line-height: 1.3;
            background-color: white;
            font-size: 11px;
        }
        
        /* Remove global selector - scope to specific elements instead */
        body, p, div, span, td, th, h1, h2, h3, h4, h5, h6 {
            text-indent: 0;
        }
        
        .header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            min-height: 60px;
        }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: none;
        }
        
        .header-table td {
            vertical-align: top;
            padding: 5px;
            width: 33.33%;
            border: none;
        }
        
        .header-left {
            text-align: left;
        }
        
        .header-center {
            text-align: left;
        }
        
        .header-right {
            text-align: left;
        }
        
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 8px 0;
            color: #333;
        }
        
        .customer-name {
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        
        .medical-info {
            font-size: 10px;
            margin-top: 3px;
            color: #555;
            text-indent: 0;
            text-align: left;
        }
        
        .invoice-details {
            font-size: 11px;
            text-indent: 0;
            text-align: left;
        }
        
        .invoice-details p {
            margin: 5px 0;
            line-height: 1.2;
            text-indent: 0;
            text-align: left;
        }
        
        .company-name {
            font-weight: bold;
            font-size: 12px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 9px;
            color: #666;
            line-height: 1.3;
            text-indent: 0;
            text-align: left;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            table-layout: fixed;
        }
        
        .invoice-table th {
            background-color: #f5f5f5;
            padding: 12px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            vertical-align: top;
        }
        
        .invoice-table td {
            padding: 12px 8px;
            font-size: 10px;
            vertical-align: top;
        }
        
        .invoice-table th:first-child,
        .invoice-table td:first-child {
            width: 40%;
        }
        
        .invoice-table th:nth-child(2),
        .invoice-table td:nth-child(2) {
            width: 25%;
        }
        
        .invoice-table th:nth-child(3),
        .invoice-table td:nth-child(3) {
            width: 12%;
            text-align: center;
        }
        
        .invoice-table th:nth-child(4),
        .invoice-table td:nth-child(4) {
            width: 12%;
            text-align: right;
        }
        
        .invoice-table th:nth-child(5),
        .invoice-table td:nth-child(5) {
            width: 15%;
            text-align: right;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-row td {
            font-weight: normal;
        }
        
        .totals-row td:nth-child(4) {
            font-weight: bold;
            text-align: right;
        }
        
        .totals-row td:nth-child(5) {
            font-weight: bold;
            text-align: right;
        }
        
        .amount-due-row td {
            font-weight: bold;
            background-color: #f0f8ff;
        }
        
        .amount-due-row td:nth-child(4),
        .amount-due-row td:nth-child(5) {
            font-size: 11px;
        }
        
        .due-date-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .due-date-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 15px;
            text-align: left;
            color: #333;
        }
        
        .due-date-section p {
            margin: 10px 0;
            font-size: 8px;
            line-height: 1.5;
            color: #555;
        }
        
        
        /* Ensure single page layout */
        @page {
            size: A4;
            margin: 15mm;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 10px;
                font-size: 10px;
            }
            
            .header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .invoice-table {
                margin: 20px 0;
            }
            
            .due-date-section {
                margin-top: 20px;
                padding: 15px;
            }
            
            /* Prevent page breaks in critical sections */
            .header,
            .totals-row,
            .amount-due-row {
                page-break-inside: avoid;
            }
            
            .invoice-table {
                page-break-inside: auto;
            }
            
            .invoice-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="invoice-title">TAX INVOICE</div>
                    <div class="customer-section">
                        <div class="customer-name">' . esc_html($data['customer_name']) . '</div>
                        <div class="medical-info"><strong>Medical Aid Scheme:</strong> ' . (!empty($data['medical_aid_scheme']) ? esc_html($data['medical_aid_scheme']) : '[Not provided]') . '</div>
                        <div class="medical-info"><strong>Medical Aid Number:</strong> ' . (!empty($data['medical_aid_number']) ? esc_html($data['medical_aid_number']) : '[Not provided]') . '</div>
                        <div class="medical-info"><strong>ID Number:</strong> ' . (!empty($data['id_number']) ? esc_html($data['id_number']) : '[Not provided]') . '</div>
                    </div>
                </td>
                
                <td class="header-center">
                    <div class="invoice-details">
                        <p><strong>Invoice Date:</strong><br>' . esc_html($data['invoice_date']) . '</p>
                        <p><strong>Invoice Number:</strong><br>' . esc_html($data['invoice_number']) . '</p>
                    </div>
                </td>
                
                <td class="header-right">
                    <div class="company-name">Dr Ben</div>
                    <div class="company-info">
                        MP0953814 – PR1153307<br>
                        Office A2, 1st floor Polo Village Offices<br>
                        Val de Vie, Paarl, Western Cape<br>
                        7636, South Africa
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Item Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Amount ZAR</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    ' . esc_html($data['service_code']) . '<br>
                    ' . esc_html($data['icd_code']) . '<br>
                    <strong>' . esc_html($data['service_name']) . '</strong>
                </td>
                <td>' . esc_html($data['service_description']) . '</td>
                <td>1.00</td>
                <td>' . number_format($data['unit_price'], 2) . '</td>
                <td>' . number_format($data['unit_price'], 2) . '</td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td style="border-bottom: 1px solid #333;">Subtotal</td>
                <td style="border-bottom: 1px solid #333;">' . number_format($data['unit_price'], 2) . '</td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td>TOTAL VAT</td>
                <td>0.00</td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td>TOTAL ZAR</td>
                <td>' . number_format($data['unit_price'], 2) . '</td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td>Less Amount Paid</td>
                <td>' . number_format($data['unit_price'], 2) . '</td>
            </tr>
            <tr class="amount-due-row">
                <td></td>
                <td></td>
                <td></td>
                <td>AMOUNT DUE ZAR</td>
                <td>0.00</td>
            </tr>
        </tbody>
    </table>
    
    <div class="due-date-section">
        <p class="due-date-title">Due Date: ' . esc_html($data['due_date']) . '</p>
        <p>This is a cash practice. The patient agrees to submit any medical aid claims independently.</p>
        <p>The patient indemnifies and hold harmless Dr Ben Coetsee and his staff from any claims, liability, or damages arising from this consultation or treatment.</p>
        <p>The patient confirms to have read, understood, and agree to the above terms.</p>
    </div>
    
</body>
</html>';

        return $html;
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            'Timetics PDF Settings',
            'Timetics PDF',
            'manage_options',
            'timetics-pdf-settings',
            [$this, 'admin_page']
        );
        
        add_submenu_page(
            'options-general.php',
            'Timetics PDF Debug',
            'Timetics PDF Debug',
            'manage_options',
            'timetics-pdf-debug',
            [$this, 'debug_page']
        );
    }

    /**
     * Admin page.
     */
    public function admin_page()
    {
        if (isset($_POST['submit'])) {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['timetics_pdf_nonce'], 'timetics_pdf_settings')) {
                wp_die('Security check failed');
            }

            // Update settings
            update_option('timetics_pdf_enabled', isset($_POST['timetics_pdf_enabled']));
            update_option('timetics_pdf_primary_color', sanitize_hex_color($_POST['timetics_pdf_primary_color']));
            update_option('timetics_pdf_secondary_color', sanitize_hex_color($_POST['timetics_pdf_secondary_color']));
            update_option('timetics_pdf_debug_logging', isset($_POST['timetics_pdf_debug_logging']));
            update_option('timetics_pdf_auto_cleanup', isset($_POST['timetics_pdf_auto_cleanup']));
            update_option('timetics_pdf_cleanup_days', intval($_POST['timetics_pdf_cleanup_days']));
            update_option('timetics_pdf_min_score', intval($_POST['timetics_pdf_min_score']));
            update_option('timetics_pdf_custom_exclusions', sanitize_textarea_field($_POST['timetics_pdf_custom_exclusions']));
            update_option('timetics_pdf_modify_email_terminology', isset($_POST['timetics_pdf_modify_email_terminology']));

            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        if (isset($_POST['test_parsing'])) {
            if (!wp_verify_nonce($_POST['timetics_pdf_nonce'], 'timetics_pdf_settings')) {
                wp_die('Security check failed');
            }
            $test_data = $this->test_email_parsing();
            echo '<div class="notice notice-info"><p><strong>Test Results:</strong></p><pre>' . print_r($test_data, true) . '</pre></div>';
        }

        if (isset($_POST['test_detection'])) {
            if (!wp_verify_nonce($_POST['timetics_pdf_nonce'], 'timetics_pdf_settings')) {
                wp_die('Security check failed');
            }
            $test_results = $this->test_email_detection();
            echo '<div class="notice notice-info"><p><strong>Email Detection Test Results:</strong></p><pre>' . print_r($test_results, true) . '</pre></div>';
        }

        if (isset($_POST['cleanup_files'])) {
            if (!wp_verify_nonce($_POST['timetics_pdf_nonce'], 'timetics_pdf_settings')) {
                wp_die('Security check failed');
            }
            $cleaned = $this->cleanup_old_pdfs();
            echo '<div class="notice notice-success"><p>Cleaned up ' . $cleaned . ' old PDF files.</p></div>';
        }

        $primary_color = get_option('timetics_pdf_primary_color', '#6870bb');
        $secondary_color = get_option('timetics_pdf_secondary_color', '#badd4f');
        $enabled = get_option('timetics_pdf_enabled', true);
        $debug_logging = get_option('timetics_pdf_debug_logging', false);
        $auto_cleanup = get_option('timetics_pdf_auto_cleanup', true);
        $cleanup_days = get_option('timetics_pdf_cleanup_days', 30);
        $min_score = get_option('timetics_pdf_min_score', 3);
        $custom_exclusions = get_option('timetics_pdf_custom_exclusions', '');
        $modify_email_terminology = get_option('timetics_pdf_modify_email_terminology', true);
?>
        <div class="wrap">
            <h1>Timetics PDF Settings</h1>
            <form method="post">
                <?php wp_nonce_field('timetics_pdf_settings', 'timetics_pdf_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable PDF Generation</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_pdf_enabled" value="1" <?php checked($enabled); ?> />
                                Enable automatic PDF generation for Timetics emails
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Modify Email Terminology</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_pdf_modify_email_terminology" value="1" <?php checked($modify_email_terminology); ?> />
                                Change "meeting" to "appointment" in email subjects and content
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Primary Color</th>
                        <td>
                            <input type="text" name="timetics_pdf_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Secondary Color</th>
                        <td>
                            <input type="text" name="timetics_pdf_secondary_color" value="<?php echo esc_attr($secondary_color); ?>" class="color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Debug Logging</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_pdf_debug_logging" value="1" <?php checked($debug_logging); ?> />
                                Enable debug logging (check error logs for detailed information)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto Cleanup</th>
                        <td>
                            <label>
                                <input type="checkbox" name="timetics_pdf_auto_cleanup" value="1" <?php checked($auto_cleanup); ?> />
                                Automatically clean up old PDF files
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cleanup Days</th>
                        <td>
                            <input type="number" name="timetics_pdf_cleanup_days" value="<?php echo esc_attr($cleanup_days); ?>" min="1" max="365" />
                            <p class="description">Number of days after which to delete old PDF files</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Detection Sensitivity</th>
                        <td>
                            <input type="number" name="timetics_pdf_min_score" value="<?php echo esc_attr($min_score); ?>" min="1" max="10" />
                            <p class="description">Minimum number of Timetics patterns required to identify an email (higher = more strict, lower = more permissive)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Custom Exclusion Patterns</th>
                        <td>
                            <textarea name="timetics_pdf_custom_exclusions" rows="4" cols="50" class="large-text"><?php echo esc_textarea($custom_exclusions); ?></textarea>
                            <p class="description">Enter custom patterns (one per line) to exclude from PDF generation. These will be checked in addition to the built-in WooCommerce exclusions. Example: "your longevity clinic appointment has been booked"</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Service Integration</th>
                        <td>
                            <button type="button" id="view-timetics-services" class="button button-secondary">View Timetics Services</button>
                            <p class="description">View all services loaded from the Timetics database (for debugging)</p>
                            <div id="timetics-services-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h3>Test & Maintenance</h3>
            <form method="post" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field('timetics_pdf_settings', 'timetics_pdf_nonce'); ?>
                <?php submit_button('Test Email Parsing', 'secondary', 'test_parsing'); ?>
            </form>

            <form method="post" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field('timetics_pdf_settings', 'timetics_pdf_nonce'); ?>
                <?php submit_button('Test Email Detection', 'secondary', 'test_detection'); ?>
            </form>

            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('timetics_pdf_settings', 'timetics_pdf_nonce'); ?>
                <?php submit_button('Cleanup Old PDFs', 'secondary', 'cleanup_files'); ?>
            </form>

            <hr>

            <h3>System Information</h3>
            <table class="widefat">
                <tr>
                    <td><strong>PDF Directory:</strong></td>
                    <td><?php echo esc_html(wp_upload_dir()['basedir'] . '/timetics-pdf/'); ?></td>
                </tr>
                <tr>
                    <td><strong>Directory Writable:</strong></td>
                    <td><?php echo is_writable(wp_upload_dir()['basedir'] . '/timetics-pdf/') ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td><strong>TCPDF Available:</strong></td>
                    <td><?php echo class_exists('TCPDF') ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td><strong>Memory Limit:</strong></td>
                    <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                </tr>
                <tr>
                    <td><strong>Max Execution Time:</strong></td>
                    <td><?php echo esc_html(ini_get('max_execution_time')); ?> seconds</td>
                </tr>
            </table>
        </div>

            <script>
                jQuery(document).ready(function($) {
                    $('.color-picker').wpColorPicker();
                    
                    // Handle View Timetics Services button
                    $('#view-timetics-services').on('click', function() {
                        var button = $(this);
                        var resultDiv = $('#timetics-services-result');
                        
                        button.prop('disabled', true).text('Loading...');
                        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px;"></div>');
                        
                        $.post(ajaxurl, {
                            action: 'timetics_pdf_view_services',
                            nonce: '<?php echo wp_create_nonce('timetics_pdf_view_services'); ?>'
                        }, function(response) {
                            resultDiv.html(response);
                        }).fail(function() {
                            resultDiv.html('<div class="notice notice-error"><p>Failed to load services. Please check the error logs.</p></div>');
                        }).always(function() {
                            button.prop('disabled', false).text('View Timetics Services');
                        });
                    });
                });
            </script>
<?php
    }

    /**
     * Test email detection logic with various email types.
     */
    public function test_email_detection()
    {
        $test_emails = [
            'timetics_appointment' => [
                'subject' => 'Appointment Confirmation',
                'message' => 'Your appointment has been successfully scheduled.

Consultation Details:
- Type: IV Drip
- Date: 06 September 2025
- Time: 01:00 pm
- Duration: 40 min
- Location: Val De Vie Estate, Paarl

Your information as provided:
Your name: Owen
Your email: jivanobala@gmail.com

Practitioner Information:
- Practitioner: Dr Ben Coetsee
- Practitioner Number: MP0953814
- Practice Number: PR1153307
- ICD10 0190 Z00.0

Warm regards,
Dr Ben Coetsee & Team'
            ],
            'woocommerce_modified' => [
                'subject' => 'Your Longevity Clinic appointment has been booked!',
                'message' => 'Thank you for your order. We have received your order and will process it shortly.

Order Details:
Order ID: #12345
Order Date: 2025-01-15
Payment Method: Credit Card
Billing Address: 123 Main St, City, State
Order Total: $150.00

Items:
- Longevity Consultation x1 - $150.00

Thank you for your business!'
            ],
            'woocommerce_standard' => [
                'subject' => 'Order Confirmation',
                'message' => 'Thank you for your order. We have received your order and will process it shortly.

Order Details:
Order ID: #12345
Order Date: 2025-01-15
Payment Method: Credit Card
Billing Address: 123 Main St, City, State
Order Total: $150.00

Items:
- Product Name x1 - $150.00

Thank you for your business!'
            ]
        ];

        $results = [];
        foreach ($test_emails as $email_type => $email_data) {
            $is_timetics = $this->is_timetics_email($email_data);
            $results[$email_type] = [
                'subject' => $email_data['subject'],
                'is_timetics' => $is_timetics,
                'expected' => $email_type === 'timetics_appointment' ? true : false,
                'correct' => ($email_type === 'timetics_appointment' && $is_timetics) || 
                           ($email_type !== 'timetics_appointment' && !$is_timetics)
            ];
        }

        return $results;
    }

    /**
     * Test function to demonstrate improved email data extraction.
     */
    public function test_email_parsing()
    {
        $test_email = "
        <p>Your appointment has been successfully scheduled.</p><p><br></p>
        <p><strong>Consultation Details:</strong></p>
        <p>- Type: IV Drip</p>
        <p>- Date: 06 September 2025</p>
        <p>- Time: 01:00 pm</p>
        <p>- Duration: 40 min</p>
        <p>- Location: Val De Vie Estate, Paarl</p>
        <p><br></p>
        <p>Please arrive 10 minutes before your scheduled consultation.</p>
        <p><br></p>
        <p><strong>Your information as provided:</strong></p>
        <p>Your name: Owen</p>
        <p>Your email: jivanobala@gmail.com</p>
        <p><br></p>
        <p><strong>Parking & Directions:</strong></p>
        <p>Parking is available at Office A2,1st floor Polo Village Offices, Val de Vie, Paarl, Western Cape, 7636, South Africa.</p>
        <p>Google Maps pin: <a href=\"https://maps.app.goo.gl/UsDdxSec7yf884j58\" rel=\"noopener noreferrer\" target=\"_blank\" style=\"color: inherit\">https://maps.app.goo.gl/UsDdxSec7yf884j58</a> </p>
        <p><br></p>
        <p><strong>Practitioner Information:</strong></p>
        <p>- Practitioner: Dr Ben Coetsee</p>
        <p>- Company Registration No: 2024/748523/2</p>
        <p>- Practitioner Number: MP0953814</p>
        <p>- Practice Number: PR1153307</p>
        <p>- ICD10 0190 Z00.0</p>
        <p><br></p>
        <p><strong>Cancellation Policy:</strong></p>
        <p>Kindly provide at least 24 hours' notice for cancellations. Cancellations made less than 24 hours before appointment will be charged at the full consultation fee. We appreciate your understanding and cooperation.</p>
        <p><br></p>
        <p>If you have any questions, you can reach us via <a href=\"https://api.whatsapp.com/send/?phone=27787377686&text=Hey!&type=phone_number&app_absent=0\" rel=\"noopener noreferrer\" target=\"_blank\">WhatsApp</a> or call at +27 78 737 7686.</p>
        <p>We look forward to seeing you! </p>
        <p><br></p>
        <p>Warm regards, </p>
        <p>Dr Ben Coetsee & Team</p>
        ";

        try {
            // Test the new parsing system
            $parser = new StructuredEmailParser();
            $extracted_data = $parser->parseEmail('Appointment Confirmation', $test_email);

            // Test validation
            $validator = new DataValidator();
            $validation_results = $validator->validateData($extracted_data);

            // Test context-aware extraction
            $extractor = new ContextAwareExtractor();
            $extraction_stats = $extractor->getExtractionStats($test_email);

            $test_results = [
                'extracted_data' => $extracted_data,
                'validation_results' => $validation_results,
                'validation_summary' => $validator->getValidationSummary(),
                'extraction_stats' => $extraction_stats,
                'validation_report' => $validator->getValidationReport()
            ];

            // Log the test results for debugging
            error_log('Improved Email Parsing Test Results: ' . print_r($test_results, true));

            return $test_results;
        } catch (Exception $e) {
            error_log('Email parsing test failed: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'fallback_data' => $this->get_default_data()
            ];
        }
    }

    /**
     * Cleanup old PDF files.
     */
    public function cleanup_old_pdfs()
    {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';

        if (!file_exists($pdf_dir)) {
            return 0;
        }

        $cleanup_days = get_option('timetics_pdf_cleanup_days', 30);
        $cutoff_time = time() - ($cleanup_days * 24 * 60 * 60);
        $cleaned_count = 0;

        $files = glob($pdf_dir . '*.pdf');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $cleaned_count++;
                    $this->log_info('Cleaned up old PDF file: ' . basename($file));
                } else {
                    $this->log_error('Failed to delete old PDF file: ' . $file);
                }
            }
        }

        return $cleaned_count;
    }

    /**
     * Schedule automatic cleanup.
     */
    public function schedule_cleanup()
    {
        if (!wp_next_scheduled('timetics_pdf_cleanup')) {
            wp_schedule_event(time(), 'daily', 'timetics_pdf_cleanup');
        }
    }

    /**
     * Unschedule automatic cleanup.
     */
    public function unschedule_cleanup()
    {
        $timestamp = wp_next_scheduled('timetics_pdf_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'timetics_pdf_cleanup');
        }
    }

    /**
     * Plugin activation.
     */
    public function activate()
    {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);

            // Create .htaccess to protect PDF files
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($pdf_dir . '.htaccess', $htaccess_content);
        }

        // Set default options
        add_option('timetics_pdf_primary_color', '#6870bb');
        add_option('timetics_pdf_secondary_color', '#badd4f');
        add_option('timetics_pdf_enabled', true);
        add_option('timetics_pdf_debug_logging', false);
        add_option('timetics_pdf_auto_cleanup', true);
        add_option('timetics_pdf_cleanup_days', 30);
        add_option('timetics_pdf_min_score', 3);
        add_option('timetics_pdf_custom_exclusions', '');
        add_option('timetics_pdf_modify_email_terminology', true);

        // Schedule cleanup if auto cleanup is enabled
        if (get_option('timetics_pdf_auto_cleanup', true)) {
            $this->schedule_cleanup();
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate()
    {
        // Unschedule cleanup
        $this->unschedule_cleanup();

        // Clean up old PDFs on deactivation
        $this->cleanup_old_pdfs();
    }


    /**
     * Extract custom fields from booking meta data.
     */
    private function extract_custom_fields_from_meta($booking_meta)
    {
        $custom_fields = [
            'medical_aid_scheme' => '',
            'medical_aid_number' => '',
            'id_number' => '',
        ];

        // First, try to get data from the new Timetics booking format (_tt_booking_custom_form_data)
        if (isset($booking_meta['_tt_booking_custom_form_data']) && !empty($booking_meta['_tt_booking_custom_form_data'])) {
            $custom_form_data = $booking_meta['_tt_booking_custom_form_data'][0];
            $debug_data = is_string($custom_form_data) ? substr($custom_form_data, 0, 200) : json_encode($custom_form_data);
            $this->log_info('EXTRACT_DEBUG: Found _tt_booking_custom_form_data: ' . $debug_data . '...');
            
            $unserialized_data = maybe_unserialize($custom_form_data);
            
            if (is_array($unserialized_data)) {
                $this->log_info('EXTRACT_DEBUG: Unserialized data keys: ' . implode(', ', array_keys($unserialized_data)));
                
                $custom_fields['medical_aid_scheme'] = $unserialized_data['name_of_medical_aid_scheme'] ?? '';
                $custom_fields['medical_aid_number'] = $unserialized_data['medical_aid_number'] ?? '';
                $custom_fields['id_number'] = $unserialized_data['enter_your_id_number'] ?? '';
                
                $this->log_info('Extracted custom fields from _tt_booking_custom_form_data: ' . json_encode($custom_fields));
                return $custom_fields;
            } else {
                $this->log_info('EXTRACT_DEBUG: Failed to unserialize _tt_booking_custom_form_data');
            }
        } else {
            $this->log_info('EXTRACT_DEBUG: No _tt_booking_custom_form_data found in booking meta');
        }

        // Fallback to old meta key patterns (for backward compatibility)
        $possible_keys = [
            'medical_aid_scheme' => [
                '_timetics_booking_medical_aid_scheme',
                '_timetics_booking_medical_aid',
                '_timetics_booking_medical_scheme',
                'medical_aid_scheme',
                'medical_aid',
            ],
            'medical_aid_number' => [
                '_timetics_booking_medical_aid_number',
                '_timetics_booking_medical_number',
                '_timetics_booking_aid_number',
                'medical_aid_number',
                'medical_number',
            ],
            'id_number' => [
                '_timetics_booking_id_number',
                '_timetics_booking_identity_number',
                '_timetics_booking_id',
                'id_number',
                'identity_number',
            ],
        ];

        foreach ($custom_fields as $field => $default_value) {
            $custom_fields[$field] = $this->find_meta_value($booking_meta, $possible_keys[$field], $default_value);
        }

        return $custom_fields;
    }

    /**
     * Get customer medical information from a specific Timetics booking.
     * This method reads from the actual booking records with customer data.
     */
    private function get_customer_medical_info_from_booking($booking_id)
    {
        try {
            $this->log_info('DEBUG: Starting customer medical info extraction for booking ID: ' . $booking_id);
            
            if (empty($booking_id)) {
                $this->log_info('No booking ID provided for customer medical info extraction');
                return null;
            }

            // Get the booking post to verify it exists and is a timetics-booking
            $booking_post = get_post($booking_id);
            if (!$booking_post) {
                $this->log_info('DEBUG: Booking post not found for ID: ' . $booking_id);
                return null;
            }
            
            if ($booking_post->post_type !== 'timetics-booking') {
                $this->log_info('DEBUG: Post type is not timetics-booking: ' . $booking_post->post_type . ' for ID: ' . $booking_id);
                return null;
            }

            $this->log_info('DEBUG: Booking post found, checking for custom form data...');

            // Get the custom form data from the booking
            $custom_form_data = get_post_meta($booking_id, '_tt_booking_custom_form_data', true);
            $debug_data = empty($custom_form_data) ? 'EMPTY' : 'FOUND - ' . (is_array($custom_form_data) ? json_encode($custom_form_data) : substr($custom_form_data, 0, 200)) . '...';
            $this->log_info('DEBUG: Custom form data raw: ' . $debug_data);
            
            if (empty($custom_form_data)) {
                $this->log_info('No custom form data found for booking: ' . $booking_id);
                return null;
            }

            // Unserialize the data if it's serialized
            $unserialized_data = maybe_unserialize($custom_form_data);
            $this->log_info('DEBUG: Unserialized data: ' . (is_array($unserialized_data) ? 'ARRAY with ' . count($unserialized_data) . ' keys' : 'NOT ARRAY - ' . gettype($unserialized_data)));
            
            if (!is_array($unserialized_data)) {
                $this->log_info('Custom form data is not in expected array format for booking: ' . $booking_id);
                return null;
            }

            // Log all available keys
            $this->log_info('DEBUG: Available keys in custom form data: ' . implode(', ', array_keys($unserialized_data)));

            // Extract the customer medical information using the correct array keys
            $customer_medical_info = [
                'medical_aid_scheme' => $unserialized_data['name_of_medical_aid_scheme'] ?? '',
                'medical_aid_number' => $unserialized_data['medical_aid_number'] ?? '',
                'id_number' => $unserialized_data['enter_your_id_number'] ?? '',
                'residential_address' => $unserialized_data['enter_your_residential_address'] ?? '',
            ];

            // Log what we found
            $this->log_info('Extracted customer medical info from booking ' . $booking_id . ': ' . json_encode($customer_medical_info));

            return $customer_medical_info;

        } catch (Exception $e) {
            $this->log_error('Error extracting customer medical info from booking ' . $booking_id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache comprehensive debug data for production analysis.
     */
    private function cache_production_debug_data($context, $data)
    {
        try {
            $debug_data = [
                'timestamp' => current_time('mysql'),
                'context' => $context,
                'data' => $data,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            
            // Store in transient with unique key
            $cache_key = 'timetics_debug_' . md5($context . time() . wp_rand());
            set_transient($cache_key, $debug_data, 3600); // 1 hour
            
            // Also store in options for persistent access
            $existing_debug = get_option('timetics_production_debug', []);
            $existing_debug[] = $debug_data;
            
            // Keep only last 50 debug entries
            if (count($existing_debug) > 50) {
                $existing_debug = array_slice($existing_debug, -50);
            }
            
            update_option('timetics_production_debug', $existing_debug);
            
            $this->log_info('PRODUCTION DEBUG CACHED: ' . $context . ' - Key: ' . $cache_key);
            
        } catch (Exception $e) {
            $this->log_error('Error caching production debug data: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve last-known medical info by customer email from options cache.
     */
    private function get_last_known_medical_info_by_email($email)
    {
        if (empty($email)) {
            return null;
        }
        $all = get_option('timetics_last_known_medical_by_email', []);
        if (!is_array($all)) {
            $all = [];
        }
        $key = strtolower(trim($email));
        return isset($all[$key]) && is_array($all[$key]) ? $all[$key] : null;
    }

    /**
     * Update last-known medical info for a customer email. Stores only non-empty values.
     */
    private function update_last_known_medical_info_by_email($email, array $info)
    {
        if (empty($email)) {
            return;
        }
        $key = strtolower(trim($email));
        $existing = $this->get_last_known_medical_info_by_email($key) ?: [];
        $merged = $existing;
        foreach (['medical_aid_scheme','medical_aid_number','id_number','residential_address'] as $field) {
            if (!empty($info[$field])) {
                $merged[$field] = $info[$field];
            }
        }
        $all = get_option('timetics_last_known_medical_by_email', []);
        if (!is_array($all)) {
            $all = [];
        }
        $all[$key] = $merged;
        update_option('timetics_last_known_medical_by_email', $all);
        $this->cache_production_debug_data('LAST_KNOWN_MEDICAL_UPDATED', [
            'email' => $key,
            'stored' => $merged,
        ]);
    }

    /**
     * Get medical information from recent bookings as fallback.
     * Optionally scope by customer email to improve accuracy.
     */
    private function get_medical_info_from_recent_bookings($customer_email = null)
    {
        try {
            $this->log_info('DEBUG: Searching for recent bookings with medical information...');
            
            // If we have a customer email, target those bookings first
            if (!empty($customer_email)) {
                $this->log_info('DEBUG: Scoping recent bookings search by email: ' . $customer_email);
                $q = new \WP_Query([
                    'post_type' => 'timetics-booking',
                    'post_status' => ['publish', 'pending', 'draft'],
                    'posts_per_page' => 10,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids',
                    'meta_query' => [[
                        'key' => '_tt_booking_customer_email',
                        'value' => strtolower($customer_email),
                        'compare' => '=',
                    ]],
                ]);
                $recent_bookings = [];
                if ($q && !empty($q->posts)) {
                    foreach ($q->posts as $post_id) {
                        $recent_bookings[] = (object)['ID' => intval($post_id)];
                    }
                }
            } else {
                // Get recent timetics-booking posts (unscoped)
                $recent_bookings = get_posts([
                    'post_type' => 'timetics-booking',
                    'post_status' => 'publish',
                    'numberposts' => 10,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ]);
            }
            
            if (empty($recent_bookings)) {
                $this->log_info('DEBUG: No recent bookings found');
                return null;
            }
            
            $this->log_info('DEBUG: Found ' . count($recent_bookings) . ' recent bookings');
            
            // Check each booking for medical information
            foreach ($recent_bookings as $booking) {
                $booking_id = $booking->ID;
                $this->log_info('DEBUG: Checking booking ID: ' . $booking_id);
                
                $custom_form_data = get_post_meta($booking_id, '_tt_booking_custom_form_data', true);
                
                if (empty($custom_form_data)) {
                    continue;
                }
                
                $unserialized_data = maybe_unserialize($custom_form_data);
                
                if (!is_array($unserialized_data)) {
                    continue;
                }
                
                // Check if this booking has medical information
                $medical_aid_scheme = $unserialized_data['name_of_medical_aid_scheme'] ?? '';
                $medical_aid_number = $unserialized_data['medical_aid_number'] ?? '';
                $id_number = $unserialized_data['enter_your_id_number'] ?? '';
                $residential_address = $unserialized_data['enter_your_residential_address'] ?? '';
                
                if (!empty($medical_aid_scheme) || !empty($medical_aid_number) || !empty($id_number)) {
                    $medical_info = [
                        'medical_aid_scheme' => $medical_aid_scheme,
                        'medical_aid_number' => $medical_aid_number,
                        'id_number' => $id_number,
                        'residential_address' => $residential_address,
                    ];
                    
                    $this->log_info('DEBUG: Found medical info in recent booking ' . $booking_id . ': ' . json_encode($medical_info));
                    return $medical_info;
                }
            }
            
            $this->log_info('DEBUG: No medical information found in recent bookings');
            return null;
            
        } catch (Exception $e) {
            $this->log_error('Error getting medical info from recent bookings: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find meta value from multiple possible keys.
     */
    private function find_meta_value($booking_meta, $possible_keys, $default_value = '')
    {
        foreach ($possible_keys as $key) {
            if (isset($booking_meta[$key]) && is_array($booking_meta[$key]) && !empty($booking_meta[$key])) {
                $value = $booking_meta[$key][0];
                if (!empty($value) && $value !== 'N/A' && $value !== 'None') {
                    return $value;
                }
            }
        }
        return $default_value;
    }

    /**
     * Extract customer email from email arguments.
     */
    private function extract_customer_email_from_args($email_args)
    {
        // Try to get email from various sources in the email arguments
        $email = null;
        
        // Check if email is directly in the arguments
        if (!empty($email_args['to'])) {
            if (is_array($email_args['to'])) {
                $email = $email_args['to'][0] ?? null;
            } else {
                $email = $email_args['to'];
            }
        }
        
        // Check if email is in headers
        if (!$email && !empty($email_args['headers'])) {
            if (is_array($email_args['headers'])) {
                foreach ($email_args['headers'] as $header) {
                    if (strpos($header, 'To:') === 0) {
                        $email = trim(substr($header, 3));
                        break;
                    }
                }
            } else {
                if (strpos($email_args['headers'], 'To:') === 0) {
                    $email = trim(substr($email_args['headers'], 3));
                }
            }
        }
        
        // Clean up email (remove any extra formatting)
        if ($email) {
            $email = trim($email);
            // Remove angle brackets if present
            $email = trim($email, '<>');
        }
        
        error_log('TIMETICS_DEBUG: Extracted customer email: ' . ($email ? $email : 'NULL'));
        return $email;
    }

    /**
     * Get the latest booking ID for a customer email.
     */
    private function get_latest_booking_id_by_email($customer_email)
    {
        if (empty($customer_email)) {
            error_log('TIMETICS_DEBUG: No customer email provided for booking lookup');
            return null;
        }
        
        global $wpdb;
        
        // Query for the most recent booking for this email
        $query = $wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'timetics-booking'
            AND p.post_status = 'completed'
            AND pm.meta_key = '_tt_booking_customer_email'
            AND pm.meta_value = %s
            ORDER BY p.post_date DESC
            LIMIT 1
        ", $customer_email);
        
        error_log('TIMETICS_DEBUG: Executing query: ' . $query);
        
        $booking_id = $wpdb->get_var($query);
        
        error_log('TIMETICS_DEBUG: Database query result for email ' . $customer_email . ': ' . ($booking_id ? $booking_id : 'NULL'));
        
        // If no result, let's check what bookings exist for this email with different meta keys
        if (!$booking_id) {
            $debug_query = $wpdb->prepare("
                SELECT p.ID, pm.meta_key, pm.meta_value
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'timetics-booking'
                AND p.post_status = 'completed'
                AND (pm.meta_key LIKE '%email%' OR pm.meta_key LIKE '%customer%')
                AND pm.meta_value LIKE %s
                ORDER BY p.post_date DESC
                LIMIT 5
            ", '%' . $customer_email . '%');
            
            $debug_results = $wpdb->get_results($debug_query);
            error_log('TIMETICS_DEBUG: Debug query results: ' . json_encode($debug_results));
        }
        
        return $booking_id ? intval($booking_id) : null;
    }

    /**
     * Extract booking ID from email content using multiple patterns.
     */
    private function extract_booking_id_from_email($subject, $message)
    {
        $content = $subject . ' ' . $message;
        error_log('TIMETICS_DEBUG: extract_booking_id_from_email called with subject: ' . substr($subject, 0, 50) . '...');
        error_log('TIMETICS_DEBUG: extract_booking_id_from_email content length: ' . strlen($content));
        
        // Try multiple patterns to find booking ID (enhanced patterns)
        $patterns = [
            // Timetics specific patterns
            '/booking[^0-9]*([0-9]+)/i',
            '/appointment[^0-9]*([0-9]+)/i',
            '/confirmation[^0-9]*([0-9]+)/i',
            '/invoice[^0-9]*([0-9]+)/i',
            '/#([0-9]+)/i',
            '/id[^0-9]*([0-9]+)/i',
            '/INV-([0-9]+)/i',
            
            // Enhanced patterns for better detection
            '/booking\s*id[^0-9]*([0-9]+)/i',
            '/appointment\s*id[^0-9]*([0-9]+)/i',
            '/reference[^0-9]*([0-9]+)/i',
            '/ref[^0-9]*([0-9]+)/i',
            '/booking\s*#([0-9]+)/i',
            '/appointment\s*#([0-9]+)/i',
            '/confirmation\s*#([0-9]+)/i',
            
            // Look for any 4-6 digit numbers that could be booking IDs
            '/\b([0-9]{4,6})\b/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $booking_id = intval($matches[1]);
                if ($booking_id > 0 && $this->is_valid_booking_id($booking_id)) {
                    error_log('TIMETICS_DEBUG: Extracted valid booking ID from email: ' . $booking_id);
                    $this->log_info('Extracted valid booking ID from email: ' . $booking_id);
                    return $booking_id;
                }
            }
        }
        
        error_log('TIMETICS_DEBUG: No booking ID found in email content');
        $this->log_info('No booking ID found in email content');
        return null;
    }

    /**
     * Validate if a booking ID exists in the database.
     */
    private function is_valid_booking_id($booking_id)
    {
        if (empty($booking_id) || !is_numeric($booking_id)) {
            return false;
        }

        // Check if the booking post exists and is a timetics-booking
        $booking_post = get_post($booking_id);
        if (!$booking_post || $booking_post->post_type !== 'timetics-booking') {
            return false;
        }

        // Additional check: verify it has Timetics booking meta data
        $custom_form_data = get_post_meta($booking_id, '_tt_booking_custom_form_data', true);
        $appointment_id = get_post_meta($booking_id, '_tt_booking_appointment', true);
        
        // If it has either custom form data or appointment ID, it's likely a valid booking
        return !empty($custom_form_data) || !empty($appointment_id);
    }

    /**
     * Store booking ID for cross-hook communication with race condition protection.
     */
    private function store_booking_id($booking_id, $email_data)
    {
        if (empty($booking_id) || !is_numeric($booking_id)) {
            return;
        }

        // Create a unique signature that includes booking-specific data
        $signature = $this->get_unique_booking_signature($booking_id, $email_data);
        
        // Use WordPress transients for persistence across requests
        $cache_data = [
            'booking_id' => intval($booking_id),
            'timestamp' => time(),
            'email_subject' => $email_data['subject'] ?? '',
            'email_message_hash' => md5($email_data['message'] ?? ''),
        ];
        
        // Store with shorter expiration (2 minutes) and unique key
        $transient_key = 'timetics_booking_' . $signature;
        set_transient($transient_key, $cache_data, 120); // 2 minutes
        
        $this->log_info('Stored booking ID ' . $booking_id . ' with unique signature: ' . $signature);
    }

    /**
     * Retrieve stored booking ID with race condition protection and validation.
     */
    private function get_stored_booking_id($email_data)
    {
        // Try multiple signature approaches to find the booking
        $signatures_to_try = $this->generate_signature_candidates($email_data);
        
        foreach ($signatures_to_try as $signature) {
            $transient_key = 'timetics_booking_' . $signature;
            $cached_data = get_transient($transient_key);
            
            if ($cached_data && is_array($cached_data)) {
                // Validate the cached data matches current email
                if ($this->validate_cached_booking_data($cached_data, $email_data)) {
                    $this->log_info('Retrieved validated booking ID ' . $cached_data['booking_id'] . ' for signature: ' . $signature);
                    return $cached_data['booking_id'];
                }
            }
        }
        
        return null;
    }

    /**
     * Generate unique booking signature that includes booking-specific data.
     */
    private function get_unique_booking_signature($booking_id, $email_data)
    {
        // Include booking ID in signature to make it unique
        $booking_specific_data = [
            'booking_id' => $booking_id,
            'subject' => $email_data['subject'] ?? '',
            'message_hash' => md5($email_data['message'] ?? ''),
            'timestamp' => time(),
        ];
        
        return md5(serialize($booking_specific_data));
    }

    /**
     * Generate multiple signature candidates for retrieval.
     */
    private function generate_signature_candidates($email_data)
    {
        $candidates = [];
        
        // Try to extract booking ID from email content first
        $extracted_booking_id = $this->extract_booking_id_from_email(
            $email_data['subject'] ?? '', 
            $email_data['message'] ?? ''
        );
        
        if ($extracted_booking_id) {
            // Create signature with extracted booking ID
            $candidates[] = $this->get_unique_booking_signature($extracted_booking_id, $email_data);
        }
        
        // Also try with basic email signature (fallback)
        $candidates[] = md5($email_data['subject'] ?? '' . $email_data['message'] ?? '');
        
        // Try with just subject hash
        $candidates[] = md5($email_data['subject'] ?? '');
        
        return array_unique($candidates);
    }

    /**
     * Validate cached booking data matches current email.
     */
    private function validate_cached_booking_data($cached_data, $email_data)
    {
        // Check if cached data is recent (within 2 minutes)
        if (time() - $cached_data['timestamp'] > 120) {
            return false;
        }
        
        // Validate email subject matches
        if (($cached_data['email_subject'] ?? '') !== ($email_data['subject'] ?? '')) {
            return false;
        }
        
        // Validate email message hash matches
        $current_message_hash = md5($email_data['message'] ?? '');
        if (($cached_data['email_message_hash'] ?? '') !== $current_message_hash) {
            return false;
        }
        
        // Additional validation: check if booking ID is still valid
        if (!$this->is_valid_booking_id($cached_data['booking_id'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Store generated PDF for wp_mail filter to use.
     */
    private function store_generated_pdf($email_data, $pdf_path)
    {
        $signature = $this->get_email_signature($email_data);
        $transient_key = 'timetics_pdf_' . $signature;
        
        // Store the PDF path with a longer expiration (10 minutes)
        set_transient($transient_key, $pdf_path, 600);
        
        $this->log_info('Stored generated PDF for signature: ' . $signature);
    }

    /**
     * Clean up expired booking and PDF transients (called periodically).
     */
    private function cleanup_expired_booking_transients()
    {
        global $wpdb;
        
        // Clean up booking transients
        $expired_booking_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timetics_booking_%' 
             AND option_value < " . (time() - 300) // 5 minutes ago
        );
        
        foreach ($expired_booking_transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            delete_transient($transient_name);
        }
        
        // Clean up PDF transients
        $expired_pdf_transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timetics_pdf_%' 
             AND option_value < " . (time() - 600) // 10 minutes ago
        );
        
        foreach ($expired_pdf_transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            delete_transient($transient_name);
        }
        
        $total_cleaned = count($expired_booking_transients) + count($expired_pdf_transients);
        if ($total_cleaned > 0) {
            $this->log_info('Cleaned up ' . $total_cleaned . ' expired transients (' . 
                          count($expired_booking_transients) . ' booking, ' . 
                          count($expired_pdf_transients) . ' PDF)');
        }
    }

    /**
     * Get booking data using Timetics native objects (PRIMARY APPROACH).
     */
    private function get_data_from_timetics_objects($booking_id)
    {
        $this->log_info('MEDICAL_DEBUG: get_data_from_timetics_objects called with booking_id: ' . $booking_id);
        
        try {
            // Check if Timetics classes are available
            if (!class_exists('Timetics\Core\Bookings\Booking')) {
                $this->log_info('MEDICAL_DEBUG: Timetics\Core\Bookings\Booking class not available');
                return null;
            }

            // Try to get booking object
            $booking = new \Timetics\Core\Bookings\Booking($booking_id);
            if (!$booking || !$booking->get_id()) {
                $this->log_info('MEDICAL_DEBUG: Booking object not found for ID: ' . $booking_id);
                return null;
            }
            
            $this->log_info('MEDICAL_DEBUG: Booking object found, ID: ' . $booking->get_id());

            // Get related objects
            $appointment = null;
            $customer = null;
            $staff = null;

            if (class_exists('Timetics\Core\Appointments\Appointment') && $booking->get_appointment()) {
                $appointment = new \Timetics\Core\Appointments\Appointment($booking->get_appointment());
            }

            if (class_exists('Timetics\Core\Customers\Customer') && $booking->get_customer_id()) {
                $customer = new \Timetics\Core\Customers\Customer($booking->get_customer_id());
            }

            if (class_exists('Timetics\Core\Staffs\Staff') && $booking->get_staff_id()) {
                $staff = new \Timetics\Core\Staffs\Staff($booking->get_staff_id());
            }

            // Extract data from objects
            $data = [
                'booking_id' => $booking->get_id(),
                'invoice_number' => 'INV-' . str_pad($booking->get_id(), 6, '0', STR_PAD_LEFT),
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d'),
            ];

            // Customer data
            if ($customer) {
                $this->log_info('MEDICAL_DEBUG: Customer object found, ID: ' . $customer->get_id());
                
                // Use the correct Timetics Customer methods
                $data['customer_name'] = $customer->get_display_name() ?: 'Customer';
                $data['customer_email'] = $customer->get_email() ?: 'customer@example.com';
                $data['customer_phone'] = $customer->get_phone() ?: '';
                
                $this->log_info('MEDICAL_DEBUG: Customer data: ' . json_encode([
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone']
                ]));
                
                // Try to get custom fields from customer meta
                $customer_meta = get_user_meta($customer->get_id());
                if ($customer_meta) {
                    $this->log_info('MEDICAL_DEBUG: Customer meta found, keys: ' . implode(', ', array_keys($customer_meta)));
                    $custom_fields = $this->extract_custom_fields_from_meta($customer_meta);
                    $data['medical_aid_scheme'] = $custom_fields['medical_aid_scheme'];
                    $data['medical_aid_number'] = $custom_fields['medical_aid_number'];
                    $data['id_number'] = $custom_fields['id_number'];
                    
                    $this->log_info('MEDICAL_DEBUG: Customer custom fields extracted: ' . json_encode($custom_fields));
                } else {
                    $this->log_info('MEDICAL_DEBUG: No customer meta found for customer ID: ' . $customer->get_id());
                }
            } else {
                $this->log_info('MEDICAL_DEBUG: No customer object found');
                $data['customer_name'] = 'Customer';
                $data['customer_email'] = 'customer@example.com';
                $data['customer_phone'] = '';
                $data['medical_aid_scheme'] = '';
                $data['medical_aid_number'] = '';
                $data['id_number'] = '';
            }

            // Appointment/Service data
            if ($appointment) {
                $data['service_name'] = $appointment->get_name() ?: 'General Consultation';
                $data['service_description'] = $appointment->get_description() ?: $data['service_name'];
                $data['duration'] = $appointment->get_duration() ? $appointment->get_duration() . ' min' : '30 min';
            } else {
                $data['service_name'] = 'General Consultation';
                $data['service_description'] = 'General Consultation';
                $data['duration'] = '30 min';
            }
            
            // Booking data (dates, times, location)
            if ($booking) {
                $data['appointment_date'] = $booking->get_start_date() ? date('Y-m-d', strtotime($booking->get_start_date())) : date('Y-m-d');
                $data['appointment_time'] = $booking->get_start_date() ? date('H:i', strtotime($booking->get_start_date())) : '10:00';
                $data['location'] = $booking->get_location() ?: 'Val De Vie Estate, Paarl';
            } else {
                $data['appointment_date'] = date('Y-m-d');
                $data['appointment_time'] = '10:00';
                $data['location'] = 'Val De Vie Estate, Paarl';
            }

            // Pricing data - try to get from booking or use defaults
            $booking_price = $booking->get_total();
            if ($booking_price && $booking_price > 0) {
                $data['unit_price'] = floatval($booking_price);
                $data['total_amount'] = floatval($booking_price);
            } else {
                // Use service mapping to get price
                $service_info = $this->get_service_info_by_name($data['service_name'], $booking_id);
                $data['unit_price'] = $service_info['base_price'] ?? 960.00;
                $data['total_amount'] = $data['unit_price'];
            }

            // Service codes and customer medical info
            $service_info = $this->get_service_info_by_name($data['service_name'], $booking_id);
            $data['service_code'] = $service_info['code'] ?? 'Code 0190';
            $data['icd_code'] = $service_info['icd_code'] ?? 'Z00.0';
            
            // Add customer medical information if available
            if (isset($service_info['medical_aid_scheme'])) {
                $data['medical_aid_scheme'] = $service_info['medical_aid_scheme'];
            }
            if (isset($service_info['medical_aid_number'])) {
                $data['medical_aid_number'] = $service_info['medical_aid_number'];
            }
            if (isset($service_info['id_number'])) {
                $data['id_number'] = $service_info['id_number'];
            }
            if (isset($service_info['residential_address'])) {
                $data['residential_address'] = $service_info['residential_address'];
            }
            
            // ALWAYS try to get medical info from recent bookings as fallback (scoped by email)
            if (empty($data['medical_aid_scheme'])) {
                $this->log_info('DEBUG: Medical aid scheme is empty, trying to get from recent bookings...');
                $recent_medical_info = $this->get_medical_info_from_recent_bookings($data['customer_email'] ?? null);
                
                // CACHE RECENT BOOKINGS MEDICAL INFO RESULT
                $this->cache_production_debug_data('RECENT_BOOKINGS_MEDICAL_INFO', [
                    'recent_medical_info' => $recent_medical_info,
                    'success' => !empty($recent_medical_info),
                    'current_data' => [
                        'medical_aid_scheme' => $data['medical_aid_scheme'] ?? '',
                        'medical_aid_number' => $data['medical_aid_number'] ?? '',
                        'id_number' => $data['id_number'] ?? ''
                    ]
                ]);
                
                if ($recent_medical_info) {
                    $data['medical_aid_scheme'] = $recent_medical_info['medical_aid_scheme'];
                    $data['medical_aid_number'] = $recent_medical_info['medical_aid_number'];
                    $data['id_number'] = $recent_medical_info['id_number'];
                    $data['residential_address'] = $recent_medical_info['residential_address'];
                    $this->log_info('DEBUG: Used medical info from recent booking as fallback: ' . json_encode($recent_medical_info));
                } else {
                    $this->log_info('DEBUG: No medical info found in recent bookings either');
                }
            }
            
            // CACHE FINAL PARSED DATA
            $this->cache_production_debug_data('FINAL_PARSED_DATA', [
                'data' => $data,
                'booking_id_used' => $booking_id,
                'medical_info_status' => [
                    'medical_aid_scheme' => !empty($data['medical_aid_scheme']),
                    'medical_aid_number' => !empty($data['medical_aid_number']),
                    'id_number' => !empty($data['id_number']),
                    'residential_address' => !empty($data['residential_address'])
                ]
            ]);

            // Staff data
            if ($staff) {
                $data['practitioner_name'] = $staff->get_full_name() ?: 'Dr Ben Coetsee';
                $data['practitioner_email'] = $staff->get_email() ?: 'drben@capecodes.com';
                $data['practitioner_phone'] = $staff->get_phone() ?: '+27 78 737 7686';
            } else {
                $data['practitioner_name'] = 'Dr Ben Coetsee';
                $data['practitioner_email'] = 'drben@capecodes.com';
                $data['practitioner_phone'] = '+27 78 737 7686';
            }

            // Static practice information
            $data['practitioner_number'] = 'MP0953814';
            $data['practice_number'] = 'PR1153307';
            $data['company_registration'] = '2024/748523/21';

            $this->log_info('MEDICAL_DEBUG: Successfully extracted data from Timetics objects');
            $this->log_info('MEDICAL_DEBUG: Final medical data from Timetics objects: ' . json_encode([
                'medical_aid_scheme' => $data['medical_aid_scheme'] ?? 'NOT SET',
                'medical_aid_number' => $data['medical_aid_number'] ?? 'NOT SET',
                'id_number' => $data['id_number'] ?? 'NOT SET'
            ]));
            return $data;

        } catch (Exception $e) {
            $this->log_error('Failed to extract data from Timetics objects: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get service information by name using dynamic Timetics services (PRIMARY) + hardcoded mapping (FALLBACK).
     */
    private function get_service_info_by_name($service_name, $booking_id = null)
    {
        try {
            // PRIMARY APPROACH: Try to get service info from Timetics database
            $timetics_service_info = $this->get_service_from_timetics_database($service_name);
            if ($timetics_service_info) {
                $this->log_info('Found service info from Timetics database: ' . $service_name);
                
                // If we have a booking ID, try to get customer medical info from the booking
                if ($booking_id) {
                    $customer_medical_info = $this->get_customer_medical_info_from_booking($booking_id);
                    if ($customer_medical_info) {
                        $timetics_service_info = array_merge($timetics_service_info, $customer_medical_info);
                        $this->log_info('Enhanced service info with customer medical data from booking: ' . $booking_id);
                    }
                }
                
                return $timetics_service_info;
            }

            // FALLBACK APPROACH: Use hardcoded StructuredEmailParser mapping
            $this->log_info('Falling back to hardcoded service mapping for: ' . $service_name);
            
            $parser = new StructuredEmailParser();
            // Access the service mapping through reflection since it's private
            $reflection = new ReflectionClass($parser);
            $property = $reflection->getProperty('service_mapping');
            $property->setAccessible(true);
            $service_mapping = $property->getValue($parser);

            // Direct match first
            if (isset($service_mapping[$service_name])) {
                $service_info = $service_mapping[$service_name];
                
                // If we have a booking ID, try to get customer medical info from the booking
                if ($booking_id) {
                    $customer_medical_info = $this->get_customer_medical_info_from_booking($booking_id);
                    if ($customer_medical_info) {
                        $service_info = array_merge($service_info, $customer_medical_info);
                        $this->log_info('Enhanced hardcoded service info with customer medical data from booking: ' . $booking_id);
                    }
                }
                
                return $service_info;
            }

            // Fuzzy matching
            $service_name_lower = strtolower($service_name);
            foreach ($service_mapping as $key => $info) {
                if (stripos($service_name_lower, strtolower($key)) !== false || 
                    stripos(strtolower($key), $service_name_lower) !== false) {
                    
                    // If we have a booking ID, try to get customer medical info from the booking
                    if ($booking_id) {
                        $customer_medical_info = $this->get_customer_medical_info_from_booking($booking_id);
                        if ($customer_medical_info) {
                            $info = array_merge($info, $customer_medical_info);
                            $this->log_info('Enhanced fuzzy-matched service info with customer medical data from booking: ' . $booking_id);
                        }
                    }
                    
                    return $info;
                }
            }

            // Default service info
            $default_info = [
                'code' => '0190',
                'icd_code' => 'Z00.0',
                'description' => 'General Consultation',
                'base_price' => 960.00,
                'category' => 'consultation'
            ];
            
            // If we have a booking ID, try to get customer medical info from the booking
            if ($booking_id) {
                $customer_medical_info = $this->get_customer_medical_info_from_booking($booking_id);
                if ($customer_medical_info) {
                    $default_info = array_merge($default_info, $customer_medical_info);
                    $this->log_info('Enhanced default service info with customer medical data from booking: ' . $booking_id);
                }
            }
            
            return $default_info;

        } catch (Exception $e) {
            $this->log_error('Error getting service info: ' . $e->getMessage());
            return [
                'code' => '0190',
                'icd_code' => 'Z00.0',
                'description' => 'General Consultation',
                'base_price' => 960.00,
                'category' => 'consultation'
            ];
        }
    }

    /**
     * Get service information from Timetics database (PRIMARY APPROACH).
     */
    private function get_service_from_timetics_database($service_name)
    {
        try {
            // Get all Timetics appointments/services
            $services = get_posts([
                'post_type' => 'timetics-appointment',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ]);

            if (empty($services)) {
                $this->log_info('No Timetics appointments found in database');
                return null;
            }

            $this->log_info('Found ' . count($services) . ' Timetics services in database');

            // Try direct match first
            foreach ($services as $service) {
                if (strcasecmp($service->post_title, $service_name) === 0) {
                    return $this->extract_service_info_from_post($service);
                }
            }

            // Try fuzzy matching
            $service_name_lower = strtolower($service_name);
            foreach ($services as $service) {
                $service_title_lower = strtolower($service->post_title);
                
                // Check if service name contains the appointment title or vice versa
                if (stripos($service_name_lower, $service_title_lower) !== false || 
                    stripos($service_title_lower, $service_name_lower) !== false) {
                    return $this->extract_service_info_from_post($service);
                }
            }

            // Try matching against service content/description
            foreach ($services as $service) {
                $content_lower = strtolower($service->post_content);
                if (stripos($content_lower, $service_name_lower) !== false) {
                    return $this->extract_service_info_from_post($service);
                }
            }

            $this->log_info('No matching service found in Timetics database for: ' . $service_name);
            return null;

        } catch (Exception $e) {
            $this->log_error('Error getting service from Timetics database: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract service information from Timetics appointment post.
     */
    private function extract_service_info_from_post($service_post)
    {
        try {
            $service_id = $service_post->ID;
            $service_meta = get_post_meta($service_id);

            // Extract basic info
            $service_info = [
                'code' => '0190', // Default code
                'icd_code' => 'Z00.0', // Default ICD code
                'description' => $service_post->post_title,
                'base_price' => 960.00, // Default price
                'category' => 'consultation'
            ];

            // Try to get price from various meta fields
            $price_fields = [
                '_timetics_appointment_price',
                '_timetics_service_price',
                'price',
                'cost',
                'amount',
                'fee'
            ];

            foreach ($price_fields as $field) {
                if (isset($service_meta[$field]) && !empty($service_meta[$field][0])) {
                    $price = floatval($service_meta[$field][0]);
                    if ($price > 0) {
                        $service_info['base_price'] = $price;
                        break;
                    }
                }
            }

            // Try to get service code from meta
            $code_fields = [
                '_timetics_service_code',
                '_timetics_appointment_code',
                'service_code',
                'code'
            ];

            foreach ($code_fields as $field) {
                if (isset($service_meta[$field]) && !empty($service_meta[$field][0])) {
                    $service_info['code'] = 'Code ' . $service_meta[$field][0];
                    break;
                }
            }

            // Try to get ICD code from meta
            $icd_fields = [
                '_timetics_icd_code',
                '_timetics_icd10_code',
                'icd_code',
                'icd10_code',
                'diagnosis_code'
            ];

            foreach ($icd_fields as $field) {
                if (isset($service_meta[$field]) && !empty($service_meta[$field][0])) {
                    $icd_value = $service_meta[$field][0];
                    // Ensure it starts with ICD10 if it doesn't already
                    if (!stripos($icd_value, 'icd') !== false) {
                        $service_info['icd_code'] = 'ICD10 ' . $icd_value;
                    } else {
                        $service_info['icd_code'] = $icd_value;
                    }
                    break;
                }
            }

            // Try to determine category from service name
            $service_name_lower = strtolower($service_post->post_title);
            if (stripos($service_name_lower, 'therapy') !== false || 
                stripos($service_name_lower, 'treatment') !== false ||
                stripos($service_name_lower, 'botox') !== false ||
                stripos($service_name_lower, 'testosterone') !== false) {
                $service_info['category'] = 'therapy';
            } elseif (stripos($service_name_lower, 'scan') !== false || 
                      stripos($service_name_lower, 'analysis') !== false ||
                      stripos($service_name_lower, 'test') !== false) {
                $service_info['category'] = 'diagnostic';
            }

            // Use post content as description if it's more detailed than title
            if (!empty($service_post->post_content) && strlen($service_post->post_content) > strlen($service_post->post_title)) {
                $service_info['description'] = wp_strip_all_tags($service_post->post_content);
                // Limit description length
                if (strlen($service_info['description']) > 200) {
                    $service_info['description'] = substr($service_info['description'], 0, 200) . '...';
                }
            }

            $this->log_info('Extracted service info from Timetics database', [
                'service_id' => $service_id,
                'title' => $service_post->post_title,
                'price' => $service_info['base_price'],
                'code' => $service_info['code'],
                'icd_code' => $service_info['icd_code']
            ]);

            return $service_info;

        } catch (Exception $e) {
            $this->log_error('Error extracting service info from post: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all available services from Timetics database for admin/debugging.
     */
    public function get_all_timetics_services()
    {
        try {
            $services = get_posts([
                'post_type' => 'timetics-appointment',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ]);

            $service_list = [];
            foreach ($services as $service) {
                $service_info = $this->extract_service_info_from_post($service);
                $service_list[$service->post_title] = $service_info;
            }

            return $service_list;

        } catch (Exception $e) {
            $this->log_error('Error getting all Timetics services: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * AJAX handler to view Timetics services for debugging.
     */
    public function ajax_view_services()
    {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'timetics_pdf_view_services')) {
            wp_die('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $services = $this->get_all_timetics_services();
        
        if (empty($services)) {
            echo '<div class="notice notice-warning"><p>No Timetics services found in the database.</p></div>';
            wp_die();
        }

        echo '<div class="wrap">';
        echo '<h3>Timetics Services Found (' . count($services) . ')</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Service Name</th>';
        echo '<th>Price</th>';
        echo '<th>Code</th>';
        echo '<th>ICD Code</th>';
        echo '<th>Category</th>';
        echo '<th>Description</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($services as $name => $info) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td>R ' . number_format($info['base_price'], 2) . '</td>';
            echo '<td>' . esc_html($info['code']) . '</td>';
            echo '<td>' . esc_html($info['icd_code']) . '</td>';
            echo '<td>' . esc_html($info['category']) . '</td>';
            echo '<td>' . esc_html($info['description']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        wp_die();
    }

    /**
     * Debug page for PDF generation monitoring.
     */
    public function debug_page()
    {
        ?>
        <div class="wrap">
            <h1>Timetics PDF Debug</h1>
            
            <div class="card">
                <h2>PDF Generation Status</h2>
                <p>Use this tool to check if PDFs are being generated and troubleshoot attachment issues.</p>
                
                <button type="button" class="button button-primary" id="check-pdf-status">
                    Check PDF Generation Status
                </button>
                
                <button type="button" class="button" id="test-pdf-generation">
                    Test PDF Generation
                </button>
                
                <button type="button" class="button" id="clear-pdf-cache">
                    Clear PDF Cache
                </button>
                
                <button type="button" class="button" id="view-existing-pdfs">
                    View Existing PDFs
                </button>
                
                <button type="button" class="button" id="view-production-debug">
                    View Production Debug Data
                </button>
                
                <div id="debug-results" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card">
                <h2>Recent PDF Activity</h2>
                <div id="recent-activity">
                    <p>Click "Check PDF Generation Status" to see recent activity.</p>
                </div>
            </div>
            
            <div class="card">
                <h2>System Information</h2>
                <div id="system-info">
                    <p><strong>Plugin Version:</strong> <?php echo esc_html(self::VERSION); ?></p>
                    <p><strong>WordPress Version:</strong> <?php echo esc_html(get_bloginfo('version')); ?></p>
                    <p><strong>PHP Version:</strong> <?php echo esc_html(PHP_VERSION); ?></p>
                    <p><strong>Upload Directory:</strong> <?php echo esc_html(wp_upload_dir()['basedir']); ?></p>
                    <p><strong>Upload Directory Writable:</strong> <?php echo is_writable(wp_upload_dir()['basedir']) ? 'Yes' : 'No'; ?></p>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#check-pdf-status').click(function() {
                $('#debug-results').html('<p>Checking PDF generation status...</p>');
                
                $.post(ajaxurl, {
                    action: 'timetics_pdf_debug',
                    debug_action: 'check_status',
                    nonce: '<?php echo wp_create_nonce('timetics_pdf_debug'); ?>'
                }, function(response) {
                    $('#debug-results').html(response);
                });
            });
            
            $('#test-pdf-generation').click(function() {
                $('#debug-results').html('<p>Testing PDF generation...</p>');
                
                $.post(ajaxurl, {
                    action: 'timetics_pdf_debug',
                    debug_action: 'test_generation',
                    nonce: '<?php echo wp_create_nonce('timetics_pdf_debug'); ?>'
                }, function(response) {
                    $('#debug-results').html(response);
                });
            });
            
            $('#clear-pdf-cache').click(function() {
                $('#debug-results').html('<p>Clearing PDF cache...</p>');
                
                $.post(ajaxurl, {
                    action: 'timetics_pdf_debug',
                    debug_action: 'clear_cache',
                    nonce: '<?php echo wp_create_nonce('timetics_pdf_debug'); ?>'
                }, function(response) {
                    $('#debug-results').html(response);
                });
            });
            
            $('#view-existing-pdfs').click(function() {
                $('#debug-results').html('<p>Loading existing PDFs...</p>');
                
                $.post(ajaxurl, {
                    action: 'timetics_pdf_debug',
                    debug_action: 'view_pdfs',
                    nonce: '<?php echo wp_create_nonce('timetics_pdf_debug'); ?>'
                }, function(response) {
                    $('#debug-results').html(response);
                });
            });

            $('#view-production-debug').click(function() {
                $('#debug-results').html('<p>Loading production debug data...</p>');
               $.post(ajaxurl, {
                    action: 'timetics_pdf_debug',
                    debug_action: 'view_production_debug',
                    nonce: '<?php echo wp_create_nonce('timetics_pdf_debug'); ?>'
                }, function(response) {
                    $('#debug-results').html(response);
                });
            });
         });
         </script>
         <?php
    }

    /**
     * AJAX handler for PDF debugging.
     */
    public function ajax_pdf_debug()
    {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'timetics_pdf_debug')) {
            wp_die('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $action = $_POST['debug_action'] ?? '';
        
        switch ($action) {
            case 'check_status':
                $this->debug_check_status();
                break;
            case 'test_generation':
                $this->debug_test_generation();
                break;
            case 'clear_cache':
                $this->debug_clear_cache();
                break;
            case 'view_pdfs':
                $this->debug_view_pdfs();
                break;
            case 'view_production_debug':
                $this->debug_view_production_debug();
                break;
            default:
                echo '<div class="notice notice-error"><p>Invalid debug action.</p></div>';
        }
        
        wp_die();
    }

    /**
     * Check PDF generation status.
     */
    private function debug_check_status()
    {
        echo '<h3>PDF Generation Status Check</h3>';
        
        // Check recent transients
        global $wpdb;
        $recent_transients = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timetics_%' 
             ORDER BY option_id DESC LIMIT 10"
        );
        
        if (empty($recent_transients)) {
            echo '<div class="notice notice-warning"><p><strong>No recent PDF activity found.</strong> This suggests PDFs are not being generated.</p></div>';
        } else {
            echo '<div class="notice notice-success"><p><strong>Found ' . count($recent_transients) . ' recent PDF transients.</strong></p></div>';
            echo '<table class="widefat">';
            echo '<thead><tr><th>Transient Key</th><th>Value</th><th>Type</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($recent_transients as $transient) {
                $key = str_replace('_transient_', '', $transient->option_name);
                $value = maybe_unserialize($transient->option_value);
                $type = 'Unknown';
                
                if (strpos($key, 'timetics_booking_') === 0) {
                    $type = 'Booking ID Cache';
                } elseif (strpos($key, 'timetics_pdf_') === 0) {
                    $type = 'PDF Cache';
                }
                
                echo '<tr>';
                echo '<td>' . esc_html($key) . '</td>';
                echo '<td>' . esc_html(is_string($value) ? $value : json_encode($value)) . '</td>';
                echo '<td>' . esc_html($type) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Check upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdfs';
        
        if (!is_dir($pdf_dir)) {
            echo '<div class="notice notice-warning"><p><strong>PDF directory does not exist:</strong> ' . esc_html($pdf_dir) . '</p></div>';
        } else {
            $pdf_files = glob($pdf_dir . '/*.pdf');
            echo '<div class="notice notice-info"><p><strong>PDF directory exists with ' . count($pdf_files) . ' PDF files.</strong></p></div>';
            
            if (!empty($pdf_files)) {
                echo '<h4>Recent PDF Files:</h4>';
                echo '<ul>';
                foreach (array_slice($pdf_files, -5) as $file) {
                    $file_time = filemtime($file);
                    echo '<li>' . esc_html(basename($file)) . ' (created: ' . date('Y-m-d H:i:s', $file_time) . ')</li>';
                }
                echo '</ul>';
            }
        }
        
        // Check if hooks are working
        echo '<h4>Hook Status:</h4>';
        echo '<ul>';
        echo '<li>wp_mail filter: ' . (has_filter('wp_mail', [$this, 'maybe_attach_pdf']) ? 'Active' : 'Not Active') . '</li>';
        echo '<li>Timetics hooks: ' . (has_action('timetics_booking_email_sent', [$this, 'handle_timetics_email']) ? 'Active' : 'Not Active') . '</li>';
        echo '</ul>';
    }

    /**
     * Test PDF generation.
     */
    private function debug_test_generation()
    {
        echo '<h3>PDF Generation Test</h3>';
        
        // Create test email data
        $test_email = [
            'subject' => 'Test Appointment Confirmation - Debug Test',
            'message' => 'This is a test email for PDF generation debugging. Booking #9999',
            'to' => 'test@example.com'
        ];
        
        try {
            // Test PDF generation
            $pdf_path = $this->generate_pdf_from_email($test_email);
            
            if ($pdf_path && file_exists($pdf_path)) {
                echo '<div class="notice notice-success"><p><strong>PDF generation test successful!</strong></p></div>';
                echo '<p>Generated PDF: ' . esc_html($pdf_path) . '</p>';
                echo '<p>File size: ' . size_format(filesize($pdf_path)) . '</p>';
                echo '<p>File exists: ' . (file_exists($pdf_path) ? 'Yes' : 'No') . '</p>';
            } else {
                echo '<div class="notice notice-error"><p><strong>PDF generation test failed!</strong></p></div>';
                echo '<p>No PDF was generated or file does not exist.</p>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p><strong>PDF generation test failed with error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Clear PDF cache.
     */
    private function debug_clear_cache()
    {
        echo '<h3>PDF Cache Clear</h3>';
        
        global $wpdb;
        
        // Clear all Timetics transients
        $deleted_transients = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timetics_%'"
        );
        
        // Clear old PDF files
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdfs';
        $deleted_files = 0;
        
        if (is_dir($pdf_dir)) {
            $pdf_files = glob($pdf_dir . '/*.pdf');
            foreach ($pdf_files as $file) {
                if (filemtime($file) < time() - 3600) { // Older than 1 hour
                    unlink($file);
                    $deleted_files++;
                }
            }
        }
        
        echo '<div class="notice notice-success"><p><strong>Cache cleared successfully!</strong></p></div>';
        echo '<p>Deleted ' . $deleted_transients . ' transients and ' . $deleted_files . ' old PDF files.</p>';
    }

    /**
     * View existing PDFs with download/view options.
     */
    private function debug_view_pdfs()
    {
        echo '<h3>Existing PDF Files</h3>';
        
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdfs';
        
        if (!is_dir($pdf_dir)) {
            echo '<div class="notice notice-warning"><p><strong>PDF directory does not exist:</strong> ' . esc_html($pdf_dir) . '</p></div>';
            return;
        }
        
        $pdf_files = glob($pdf_dir . '/*.pdf');
        
        if (empty($pdf_files)) {
            echo '<div class="notice notice-info"><p><strong>No PDF files found in the directory.</strong></p></div>';
            return;
        }
        
        // Sort by modification time (newest first)
        usort($pdf_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        echo '<div class="notice notice-success"><p><strong>Found ' . count($pdf_files) . ' PDF files.</strong></p></div>';
        
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Filename</th>';
        echo '<th>Size</th>';
        echo '<th>Created</th>';
        echo '<th>Modified</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($pdf_files as $file) {
            $filename = basename($file);
            $file_size = filesize($file);
            $file_created = filectime($file);
            $file_modified = filemtime($file);
            
            // Create download URL
            $upload_url = wp_upload_dir()['baseurl'];
            $pdf_url = $upload_url . '/timetics-pdfs/' . $filename;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($filename) . '</strong></td>';
            echo '<td>' . size_format($file_size) . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', $file_created) . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', $file_modified) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">View PDF</a> ';
            echo '<a href="' . esc_url($pdf_url) . '" download class="button button-small">Download</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Add some additional information
        echo '<h4>PDF Directory Information:</h4>';
        echo '<ul>';
        echo '<li><strong>Directory:</strong> ' . esc_html($pdf_dir) . '</li>';
        echo '<li><strong>Total Files:</strong> ' . count($pdf_files) . '</li>';
        echo '<li><strong>Total Size:</strong> ' . size_format(array_sum(array_map('filesize', $pdf_files))) . '</li>';
        echo '<li><strong>Directory Writable:</strong> ' . (is_writable($pdf_dir) ? 'Yes' : 'No') . '</li>';
        echo '</ul>';
        
        // Show recent activity summary
        $recent_files = array_slice($pdf_files, 0, 5);
        if (!empty($recent_files)) {
            echo '<h4>Most Recent PDFs:</h4>';
            echo '<ul>';
            foreach ($recent_files as $file) {
                $filename = basename($file);
                $file_modified = filemtime($file);
                $time_ago = human_time_diff($file_modified, current_time('timestamp'));
                echo '<li><strong>' . esc_html($filename) . '</strong> - ' . $time_ago . ' ago</li>';
            }
            echo '</ul>';
        }
    }

    /**
     * Debug method to view production debug data.
     */
    private function debug_view_production_debug()
    {
        echo '<h3>Production Debug Data</h3>';
        
        $debug_data = get_option('timetics_production_debug', []);
        
        if (empty($debug_data)) {
            echo '<p>No production debug data found. This data is captured when emails are processed.</p>';
            echo '<p>To capture debug data:</p>';
            echo '<ol>';
            echo '<li>Trigger a booking email (make a test booking)</li>';
            echo '<li>Check this page again</li>';
            echo '<li>Copy and paste the debug data here for analysis</li>';
            echo '</ol>';
            return;
        }
        
        echo '<p><strong>Found ' . count($debug_data) . ' debug entries:</strong></p>';
        
        // Group by context
        $grouped_data = [];
        foreach ($debug_data as $entry) {
            $context = $entry['context'] ?? 'unknown';
            if (!isset($grouped_data[$context])) {
                $grouped_data[$context] = [];
            }
            $grouped_data[$context][] = $entry;
        }
        
        foreach ($grouped_data as $context => $entries) {
            echo '<h4>' . esc_html($context) . ' (' . count($entries) . ' entries)</h4>';
            
            foreach ($entries as $i => $entry) {
                echo '<div style="border: 1px solid #ddd; margin: 10px 0; padding: 10px; background: #f9f9f9;">';
                echo '<strong>Entry ' . ($i + 1) . ' - ' . esc_html($entry['timestamp']) . '</strong><br>';
                echo '<strong>Memory:</strong> ' . size_format($entry['memory_usage']) . ' (Peak: ' . size_format($entry['peak_memory']) . ')<br>';
                echo '<strong>Data:</strong><br>';
                echo '<pre style="background: white; padding: 10px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto;">';
                echo esc_html(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                echo '</pre>';
                echo '</div>';
            }
        }
        
        echo '<div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff;">';
        echo '<h4>📋 Instructions for Analysis</h4>';
        echo '<p>Copy the debug data above and paste it in your message. Look for:</p>';
        echo '<ul>';
        echo '<li><strong>EMAIL_INPUT:</strong> The original email subject and message</li>';
        echo '<li><strong>STORED_BOOKING_ID:</strong> If booking ID was found in cache</li>';
        echo '<li><strong>EXTRACTED_BOOKING_ID:</strong> If booking ID was extracted from email</li>';
        echo '<li><strong>TIMETICS_DATA_EXTRACTION:</strong> What data was retrieved from database</li>';
        echo '<li><strong>RECENT_BOOKINGS_MEDICAL_INFO:</strong> Fallback medical info attempt</li>';
        echo '<li><strong>FINAL_PARSED_DATA:</strong> The final data used for PDF generation</li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Attempt to resolve booking ID from email links and customer email when no explicit booking number is present.
     */
    private function resolve_booking_id_from_email_context($subject, $message)
    {
        try {
            $content = $subject . ' ' . $message;

            // Extract customer email from message
            $customer_email = null;
            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $content, $em)) {
                $customer_email = strtolower($em[0]);
            }

            // Extract appointment_id and id from any URLs in the email
            $appointment_id = null;
            if (preg_match('/[?&]appointment_id=([0-9]+)/i', $content, $am)) {
                $appointment_id = intval($am[1]);
            }
            $appointment_post_id = null;
            if (preg_match('/[?&]id=([0-9]+)/i', $content, $im)) {
                $appointment_post_id = intval($im[1]);
            }

            $this->cache_production_debug_data('EMAIL_CONTEXT_TOKENS', [
                'customer_email' => $customer_email,
                'appointment_id' => $appointment_id,
                'appointment_post_id' => $appointment_post_id,
            ]);

            // If we have neither email nor appointment id, we cannot resolve
            if (empty($customer_email) && empty($appointment_id) && empty($appointment_post_id)) {
                return null;
            }

            // Build meta query
            $meta_query = ['relation' => 'AND'];
            if (!empty($customer_email)) {
                $meta_query[] = [
                    'key' => '_tt_booking_customer_email',
                    'value' => $customer_email,
                    'compare' => '=',
                ];
            }
            if (!empty($appointment_id) || !empty($appointment_post_id)) {
                $or = ['relation' => 'OR'];
                if (!empty($appointment_id)) {
                    $or[] = ['key' => '_tt_booking_appointment', 'value' => $appointment_id, 'compare' => '='];
                    $or[] = ['key' => '_tt_booking_appointment_id', 'value' => $appointment_id, 'compare' => '='];
                }
                if (!empty($appointment_post_id)) {
                    $or[] = ['key' => '_tt_booking_appointment', 'value' => $appointment_post_id, 'compare' => '='];
                    $or[] = ['key' => '_tt_booking_appointment_id', 'value' => $appointment_post_id, 'compare' => '='];
                }
                $meta_query[] = $or;
            }

            $args = [
                'post_type' => 'timetics-booking',
                'post_status' => ['publish', 'pending', 'draft'],
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => $meta_query,
                'fields' => 'ids',
            ];

            $q = new \WP_Query($args);
            if ($q && !empty($q->posts)) {
                $booking_id = intval($q->posts[0]);
                if ($this->is_valid_booking_id($booking_id)) {
                    $this->cache_production_debug_data('EMAIL_CONTEXT_RESOLVER_MATCH', [
                        'matched_booking_id' => $booking_id,
                        'candidate_ids' => $q->posts,
                        'meta_query' => $meta_query,
                    ]);
                    return $booking_id;
                }
            }

            $this->cache_production_debug_data('EMAIL_CONTEXT_RESOLVER_NO_MATCH', [
                'meta_query' => $meta_query,
                'reason' => 'No posts matched or invalid booking id',
            ]);
            return null;
        } catch (\Exception $e) {
            $this->log_error('Error resolving booking ID from email context: ' . $e->getMessage());
            return null;
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function () {
    Timetics_Pdf_Addon::get_instance();
});

// Enqueue admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ('settings_page_timetics-pdf-settings' === $hook) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
});
