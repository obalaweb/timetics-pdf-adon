<?php
/**
 * Structured Email Parser for Timetics PDF Addon
 * 
 * This class provides intelligent email parsing with proper structure preservation
 * and context-aware field extraction.
 * 
 * @package Timetics_Pdf_Addon
 * @since 2.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

class StructuredEmailParser {
    
    /**
     * Service type mapping database
     */
    private $service_mapping = [
        'Echocardiogram scan' => [
            'code' => '0190',
            'icd_code' => 'ICD10 R93.1', // Abnormal findings on diagnostic imaging of heart
            'description' => 'Echocardiogram scan',
            'base_price' => 2500.00,
            'category' => 'diagnostic'
        ],
        'Prescription' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Prescription',
            'base_price' => 420.00,
            'category' => 'prescription'
        ],
        'Red Light Therapy' => [
            'code' => '0190',
            'icd_code' => 'ICD10 88045', // Red Light Therapy
            'description' => 'Red Light Therapy Treatment',
            'base_price' => 700.00,
            'category' => 'therapy'
        ],
        'InBody Analysis (Scan only)' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'InBody Analysis (Scan only)',
            'base_price' => 450.00,
            'category' => 'analysis'
        ],
        'InBody Analysis (Full)' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'InBody Analysis (Full)',
            'base_price' => 1250.00,
            'category' => 'analysis'
        ],
        'Blood Results' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'General Consultation (Blood Results)',
            'base_price' => 960.00,
            'category' => 'consultation'
        ],
        'IV Drip' => [
            'code' => '0190',
            'icd_code' => 'ICD10 Z76.89', // Intravenous drip
            'description' => 'IV Drip Treatment',
            'base_price' => 1750.00,
            'category' => 'treatment'
        ],
        'Sports Injury' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation (same as other bookings)
            'description' => 'Sports Injury Consultation',
            'base_price' => 1250.00,
            'category' => 'consultation'
        ],
        'Longevity/Bio-Optimisation' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Longevity/Bio-Optimisation Consultation',
            'base_price' => 1250.00,
            'category' => 'consultation'
        ],
        'Weight Loss' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Weight Loss Consultation',
            'base_price' => 1250.00,
            'category' => 'consultation'
        ],
        'Testosterone Optimization/Replacement Therapy' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Testosterone Optimization/Replacement Therapy',
            'base_price' => 1250.00,
            'category' => 'therapy'
        ],
        'Sleep Issues' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Sleep Issues Consultation',
            'base_price' => 1250.00,
            'category' => 'consultation'
        ],
        'Botulinum Toxin (Botox) Therapy' => [
            'code' => '0190',
            'icd_code' => 'ICD10 0190 Z00.0', // General Consultation
            'description' => 'Botulinum Toxin (Botox) Therapy',
            'base_price' => 1250.00,
            'category' => 'therapy'
        ],
        'General Consultation' => [
            'code' => '0190',
            'icd_code' => 'Z00.0', // General medical examination
            'description' => 'General Consultation',
            'base_price' => 960.00,
            'category' => 'consultation'
        ],
        'Follow-up Consultation' => [
            'code' => '0190',
            'icd_code' => 'Z00.0', // General medical examination
            'description' => 'Follow-up Consultation',
            'base_price' => 960.00,
            'category' => 'consultation'
        ]
    ];
    
    /**
     * Date format patterns for parsing
     */
    private $date_patterns = [
        '/Date:\s*(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
        '/Date:\s*(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4})/i',
        '/Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})/i',
        '/Date:\s*(\d{4}-\d{2}-\d{2})/i'
    ];
    
    /**
     * Time format patterns
     */
    private $time_patterns = [
        '/Time:\s*(\d{1,2}:\d{2}\s*(?:am|pm|AM|PM))/i',
        '/Time:\s*(\d{1,2}:\d{2})/i'
    ];
    
    /**
     * Parse email content focusing only on essential dynamic fields
     */
    public function parseEmail($subject, $message) {
        // Preserve structure while cleaning HTML
        $structured_content = $this->preserveStructure($message);
        
        // Extract only the essential dynamic fields
        $extracted_data = $this->extractEssentialFields($structured_content);
        
        // Apply business logic and set static data
        $processed_data = $this->processForInvoice($extracted_data);
        
        return $processed_data;
    }
    
    /**
     * Preserve email structure while cleaning HTML completely for PDF
     */
    private function preserveStructure($message) {
        // Convert HTML entities first
        $clean = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Replace HTML line breaks and paragraphs with newlines
        $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);
        $clean = preg_replace('/<\/p>/i', "\n\n", $clean);
        $clean = preg_replace('/<p[^>]*>/i', '', $clean);
        
        // Remove all HTML tags completely - no tags should remain for PDF
        $clean = strip_tags($clean);
        
        // Clean up whitespace but preserve line structure
        $lines = explode("\n", $clean);
        $structured_lines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $structured_lines[] = $line;
            }
        }
        
        return $structured_lines;
    }
    
    /**
     * Extract only essential dynamic fields for invoice
     */
    private function extractEssentialFields($lines) {
        $data = [
            'customer_name' => null,
            'service_name' => null,
            'service_code' => null,
            'icd_code' => null,
            'service_description' => null,
            'unit_price' => null,
            'appointment_date' => null,
            'appointment_time' => null,
            'duration' => null,
            'location' => null,
            'customer_email' => null,
            'medical_aid_scheme' => null,
            'medical_aid_number' => null,
            'id_number' => null,
            'practitioner_name' => null,
            'company_registration' => null,
            'practitioner_number' => null,
            'practice_number' => null
        ];
        
        $content = implode("\n", $lines);
        
        // Extract customer name (for Bill To section)
        if (preg_match('/Your name:\s*(.+?)(?:\s+Your email|\s+Date|\s+Time|\s+Parking|\s+Practitioner|$)/i', $content, $matches)) {
            $data['customer_name'] = trim($matches[1]);
        }
        
        // Extract customer email
        if (preg_match('/Your email:\s*([^\s<>]+@[^\s<>]+)/i', $content, $matches)) {
            $data['customer_email'] = trim($matches[1]);
        }
        
        // Extract service type (handle both formats: "Type: Service" and "- Type: Service")
        if (preg_match('/Type:\s*(.+)/i', $content, $matches)) {
            $data['service_name'] = trim($matches[1]);
        }
        
        // Extract appointment date
        if (preg_match('/Date:\s*(.+)/i', $content, $matches)) {
            $data['appointment_date'] = trim($matches[1]);
        }
        
        // Extract appointment time
        if (preg_match('/Time:\s*(.+)/i', $content, $matches)) {
            $data['appointment_time'] = trim($matches[1]);
        }
        
        // Extract duration
        if (preg_match('/Duration:\s*(.+)/i', $content, $matches)) {
            $data['duration'] = trim($matches[1]);
        }
        
        // Extract location (handle both formats)
        if (preg_match('/Location:\s*(.+)/i', $content, $matches)) {
            $data['location'] = trim($matches[1]);
        }
        
        // Extract practitioner name
        if (preg_match('/Practitioner:\s*(.+)/i', $content, $matches)) {
            $data['practitioner_name'] = trim($matches[1]);
        }
        
        // Extract company registration
        if (preg_match('/Company Registration No:\s*(.+)/i', $content, $matches)) {
            $data['company_registration'] = trim($matches[1]);
        }
        
        // Extract practitioner number
        if (preg_match('/Practitioner Number:\s*(.+)/i', $content, $matches)) {
            $data['practitioner_number'] = trim($matches[1]);
        }
        
        // Extract practice number
        if (preg_match('/Practice Number:\s*(.+)/i', $content, $matches)) {
            $data['practice_number'] = trim($matches[1]);
        }
        
        // Extract ICD-10 code and service code
        // Handle both old format: "ICD-10: (Code 0190) Z00.0" and new format: "ICD10 0190 Z00.0" or "ICD10 88045"
        if (preg_match('/ICD-10:\s*\(Code\s*(\d+)\)\s*([A-Z]\d{2}\.\d)/i', $content, $matches)) {
            // Old format
            $data['service_code'] = 'Code ' . $matches[1];
            $data['icd_code'] = $matches[2];
        } elseif (preg_match('/ICD10\s+(\d+)\s+([A-Z]\d{2}\.\d)/i', $content, $matches)) {
            // New format with both service code and ICD code
            $data['service_code'] = 'Code ' . $matches[1];
            $data['icd_code'] = $matches[2];
        } elseif (preg_match('/ICD10\s+([A-Z]\d{2}\.\d)/i', $content, $matches)) {
            // New format with only ICD code
            $data['icd_code'] = $matches[1];
        } elseif (preg_match('/ICD10\s+(\d+)/i', $content, $matches)) {
            // New format with numeric code only
            $data['icd_code'] = 'ICD10 ' . $matches[1];
        }
        
        // Extract medical aid scheme
        if (preg_match('/Medical Aid Scheme:\s*(.+?)(?:\n|Medical Aid Number:|ID Number:|$)/i', $content, $matches)) {
            $data['medical_aid_scheme'] = trim($matches[1]);
        } elseif (preg_match('/Medical Scheme:\s*(.+?)(?:\n|Medical Number:|ID Number:|$)/i', $content, $matches)) {
            $data['medical_aid_scheme'] = trim($matches[1]);
        } elseif (preg_match('/Scheme:\s*(.+?)(?:\n|Number:|ID:|$)/i', $content, $matches)) {
            $data['medical_aid_scheme'] = trim($matches[1]);
        }
        
        // Extract medical aid number
        if (preg_match('/Medical Aid Number:\s*(.+?)(?:\n|ID Number:|$)/i', $content, $matches)) {
            $data['medical_aid_number'] = trim($matches[1]);
        } elseif (preg_match('/Medical Number:\s*(.+?)(?:\n|ID Number:|$)/i', $content, $matches)) {
            $data['medical_aid_number'] = trim($matches[1]);
        } elseif (preg_match('/Aid Number:\s*(.+?)(?:\n|ID:|$)/i', $content, $matches)) {
            $data['medical_aid_number'] = trim($matches[1]);
        }
        
        // Extract ID number
        if (preg_match('/ID Number:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $data['id_number'] = trim($matches[1]);
        } elseif (preg_match('/Identity Number:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $data['id_number'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    /**
     * Extract value with context awareness to prevent greedy matching
     */
    private function extractValueWithContext($value, $next_line) {
        $value = trim($value);
        
        // Stop at common field boundaries
        $stop_patterns = [
            '/\s+Your email:/i',
            '/\s+Date:/i',
            '/\s+Time:/i',
            '/\s+Duration:/i',
            '/\s+Location:/i',
            '/\s+Practitioner:/i',
            '/\s+Company Registration/i',
            '/\s+Parking/i',
            '/\s+Google Maps/i',
            '/\s+Cancellation Policy/i'
        ];
        
        foreach ($stop_patterns as $pattern) {
            if (preg_match($pattern, $value, $matches)) {
                $value = substr($value, 0, strpos($value, $matches[0]));
                break;
            }
        }
        
        return trim($value);
    }
    
    /**
     * Process extracted data for invoice with static data
     */
    private function processForInvoice($extracted_data) {
        // Apply service mapping to get proper codes and pricing
        if ($extracted_data['service_name']) {
            $service_info = $this->mapServiceType($extracted_data['service_name']);
            if ($service_info) {
                $extracted_data['service_code'] = $service_info['code'];
                $extracted_data['icd_code'] = $service_info['icd_code'];
                $extracted_data['service_description'] = $service_info['description'];
                $extracted_data['unit_price'] = $service_info['base_price'];
            }
        }
        
        // Set defaults if not extracted
        $extracted_data['service_code'] = $extracted_data['service_code'] ?? '0190';
        $extracted_data['icd_code'] = $extracted_data['icd_code'] ?? 'Z00.0';
        $extracted_data['service_description'] = $extracted_data['service_description'] ?? 'General Consultation (Blood Results)';
        $extracted_data['unit_price'] = $extracted_data['unit_price'] ?? 960.00;
        $extracted_data['customer_name'] = $extracted_data['customer_name'] ?? 'Valued Patient';
        
        // Set static invoice data
        $data = [
            // Dynamic fields (extracted from email)
            'customer_name' => $extracted_data['customer_name'],
            'service_code' => 'Code ' . $extracted_data['service_code'],
            'icd_code' => $extracted_data['icd_code'],
            'service_description' => $extracted_data['service_description'],
            'quantity' => 1.00, // Always 1
            'unit_price' => $extracted_data['unit_price'],
            'subtotal' => $extracted_data['unit_price'],
            
            // Additional extracted fields for reference
            'service_name' => $extracted_data['service_name'],
            'appointment_date' => $extracted_data['appointment_date'],
            'appointment_time' => $extracted_data['appointment_time'],
            'duration' => $extracted_data['duration'],
            'location' => $extracted_data['location'],
            'customer_email' => $extracted_data['customer_email'],
            'medical_aid_scheme' => $extracted_data['medical_aid_scheme'],
            'medical_aid_number' => $extracted_data['medical_aid_number'],
            'id_number' => $extracted_data['id_number'],
            'practitioner_name' => $extracted_data['practitioner_name'],
            
            // Static fields (always the same)
            'invoice_number' => 'INV-' . date('md') . rand(100, 999),
            'invoice_date' => date('j M Y'), // Today's date
            'due_date' => date('j M Y'), // Same as invoice date (today)
            'vat_amount' => 0.00,
            'total_amount' => $extracted_data['unit_price'],
            'amount_paid' => $extracted_data['unit_price'],
            'amount_due' => 0.00,
            
            // Use extracted company information or defaults
            'company_name' => 'Performance MD Inc',
            'company_registration' => $extracted_data['company_registration'] ?? '2024/748523/21',
            'practitioner_number' => $extracted_data['practitioner_number'] ?? 'MP0953814',
            'practice_number' => $extracted_data['practice_number'] ?? 'PR1153307',
            
            // Create items array for PDF generation
            'items' => [[
                'service_code' => 'Code ' . $extracted_data['service_code'],
                'icd_code' => $extracted_data['icd_code'],
                'description' => $extracted_data['service_description'],
                'quantity' => 1.00,
                'unit_price' => $extracted_data['unit_price'],
                'subtotal' => $extracted_data['unit_price']
            ]]
        ];
        
        return $data;
    }
    
    /**
     * Map service type to standardized information
     */
    private function mapServiceType($service_name) {
        $service_name = trim($service_name);
        
        // Direct match
        if (isset($this->service_mapping[$service_name])) {
            return $this->service_mapping[$service_name];
        }
        
        // Create service name variations for better matching
        $service_variations = [
            'echocardiogram' => 'Echocardiogram scan',
            'echo' => 'Echocardiogram scan',
            'heart scan' => 'Echocardiogram scan',
            'prescription' => 'Prescription',
            'script' => 'Prescription',
            'red light' => 'Red Light Therapy',
            'red light therapy' => 'Red Light Therapy',
            'light therapy' => 'Red Light Therapy',
            'inbody scan' => 'InBody Analysis (Scan only)',
            'inbody analysis scan' => 'InBody Analysis (Scan only)',
            'inbody full' => 'InBody Analysis (Full)',
            'inbody analysis full' => 'InBody Analysis (Full)',
            'inbody analysis' => 'InBody Analysis (Full)',
            'blood results' => 'Blood Results',
            'blood test' => 'Blood Results',
            'blood work' => 'Blood Results',
            'iv drip' => 'IV Drip',
            'intravenous' => 'IV Drip',
            'sports injury' => 'Sports Injury',
            'injury' => 'Sports Injury',
            'longevity' => 'Longevity/Bio-Optimisation',
            'bio-optimisation' => 'Longevity/Bio-Optimisation',
            'bio optimisation' => 'Longevity/Bio-Optimisation',
            'biooptimisation' => 'Longevity/Bio-Optimisation',
            'weight loss' => 'Weight Loss',
            'weight management' => 'Weight Loss',
            'testosterone optimization' => 'Testosterone Optimization/Replacement Therapy',
            'testosterone replacement' => 'Testosterone Optimization/Replacement Therapy',
            'testosterone therapy' => 'Testosterone Optimization/Replacement Therapy',
            'trt' => 'Testosterone Optimization/Replacement Therapy',
            'sleep issues' => 'Sleep Issues',
            'sleep problems' => 'Sleep Issues',
            'insomnia' => 'Sleep Issues',
            'sleep consultation' => 'Sleep Issues',
            'botulinum toxin' => 'Botulinum Toxin (Botox) Therapy',
            'botox therapy' => 'Botulinum Toxin (Botox) Therapy',
            'botox' => 'Botulinum Toxin (Botox) Therapy',
            'botulinum' => 'Botulinum Toxin (Botox) Therapy',
            'general consultation' => 'General Consultation',
            'consultation' => 'General Consultation',
            'follow-up' => 'Follow-up Consultation',
            'follow up' => 'Follow-up Consultation'
        ];
        
        // Check variations first
        $normalized_name = strtolower($service_name);
        foreach ($service_variations as $variation => $mapped_service) {
            if (stripos($normalized_name, $variation) !== false) {
                return $this->service_mapping[$mapped_service];
            }
        }
        
        // Fuzzy matching with original service names
        foreach ($this->service_mapping as $key => $info) {
            if (stripos($service_name, $key) !== false || stripos($key, $service_name) !== false) {
                return $info;
            }
        }
        
        // Default service - return default mapping if General Consultation not found
        return $this->service_mapping['General Consultation'] ?? [
            'code' => '0190',
            'icd_code' => 'Z00.0',
            'description' => 'General Consultation',
            'base_price' => 960.00,
            'category' => 'consultation'
        ];
    }
    
    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber() {
        return 'INV-' . date('md') . rand(100, 999);
    }
    
    /**
     * Format invoice date
     */
    private function formatInvoiceDate($appointment_date) {
        if (!$appointment_date) {
            return date('j M Y');
        }
        
        // Try to parse the appointment date
        $formats = [
            'd F Y',      // 06 September 2025
            'd M Y',      // 06 Sep 2025
            'd/m/Y',      // 06/09/2025
            'Y-m-d'       // 2025-09-06
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $appointment_date);
            if ($date) {
                return $date->format('j M Y');
            }
        }
        
        return date('j M Y');
    }
    
    /**
     * Validate and enrich extracted data
     */
    private function validateAndEnrich($data) {
        // Validate email
        if ($data['customer_email'] && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $data['customer_email'] = null;
        }
        
        // Validate and format customer name
        if ($data['customer_name']) {
            $data['customer_name'] = $this->formatCustomerName($data['customer_name']);
        } else {
            $data['customer_name'] = 'Valued Patient';
        }
        
        // Ensure required fields have defaults
        $data['service_code'] = $data['service_code'] ?? 'Code 0190';
        $data['icd_code'] = $data['icd_code'] ?? 'Z00.0';
        $data['service_description'] = $data['service_description'] ?? 'General Consultation';
        $data['unit_price'] = $data['unit_price'] ?? 960.00;
        $data['company_registration'] = $data['company_registration'] ?? '2024/748523/21';
        $data['practitioner_number'] = $data['practitioner_number'] ?? 'MP0953814';
        $data['practice_number'] = $data['practice_number'] ?? 'PR1153307';
        
        // Calculate totals
        $data['subtotal'] = $data['unit_price'] * $data['quantity'];
        $data['total_amount'] = $data['subtotal'] + $data['vat_amount'];
        $data['amount_due'] = $data['total_amount'] - $data['amount_paid'];
        
        // Create items array for PDF generation
        $data['items'] = [[
            'service_code' => $data['service_code'],
            'icd_code' => $data['icd_code'],
            'description' => $data['service_description'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'subtotal' => $data['subtotal']
        ]];
        
        return $data;
    }
    
    /**
     * Format customer name properly
     */
    private function formatCustomerName($name) {
        // Remove extra content and clean up
        $name = trim($name);
        $name = preg_replace('/\s+Your email.*$/i', '', $name);
        $name = preg_replace('/\s+Parking.*$/i', '', $name);
        
        // Capitalize properly
        return ucwords(strtolower($name));
    }
    
    /**
     * Get service mapping for external use
     */
    public function getServiceMapping() {
        return $this->service_mapping;
    }
    
    /**
     * Add new service mapping
     */
    public function addServiceMapping($service_name, $mapping) {
        $this->service_mapping[$service_name] = $mapping;
    }
}
