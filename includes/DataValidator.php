<?php
/**
 * Data Validator for Timetics PDF Addon
 * 
 * This class provides comprehensive data validation and enrichment
 * for extracted email data.
 * 
 * @package Timetics_Pdf_Addon
 * @since 2.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

class DataValidator {
    
    /**
     * Validation results
     */
    private $validation_results = [];
    
    /**
     * Validate extracted email data
     */
    public function validateData($data) {
        $this->validation_results = [
            'customer_name' => $this->validateCustomerName($data['customer_name'] ?? null),
            'customer_email' => $this->validateEmail($data['customer_email'] ?? null),
            'appointment_date' => $this->validateDate($data['appointment_date'] ?? null),
            'appointment_time' => $this->validateTime($data['appointment_time'] ?? null),
            'service_name' => $this->validateServiceName($data['service_name'] ?? null),
            'practitioner_info' => $this->validatePractitionerInfo($data),
            'pricing' => $this->validatePricing($data),
            'required_fields' => $this->validateRequiredFields($data)
        ];
        
        return $this->validation_results;
    }
    
    /**
     * Validate customer name
     */
    private function validateCustomerName($name) {
        if (empty($name)) {
            return [
                'valid' => false,
                'error' => 'Customer name is required',
                'suggestion' => 'Use "Valued Patient" as default'
            ];
        }
        
        // Check for reasonable length
        if (strlen($name) < 2) {
            return [
                'valid' => false,
                'error' => 'Customer name too short',
                'suggestion' => 'Use "Valued Patient" as default'
            ];
        }
        
        if (strlen($name) > 100) {
            return [
                'valid' => false,
                'error' => 'Customer name too long',
                'suggestion' => 'Truncate to first 100 characters'
            ];
        }
        
        // Check for invalid characters
        if (preg_match('/[<>"\']/', $name)) {
            return [
                'valid' => false,
                'error' => 'Customer name contains invalid characters',
                'suggestion' => 'Remove HTML/quote characters'
            ];
        }
        
        return [
            'valid' => true,
            'value' => $name
        ];
    }
    
    /**
     * Validate email address
     */
    private function validateEmail($email) {
        if (empty($email)) {
            return [
                'valid' => true, // Email is optional
                'value' => null
            ];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Invalid email format',
                'suggestion' => 'Remove invalid email or use null'
            ];
        }
        
        return [
            'valid' => true,
            'value' => $email
        ];
    }
    
    /**
     * Validate appointment date
     */
    private function validateDate($date) {
        if (empty($date)) {
            return [
                'valid' => false,
                'error' => 'Appointment date is required',
                'suggestion' => 'Use current date as default'
            ];
        }
        
        // Try to parse the date
        $formats = [
            'd F Y',      // 06 September 2025
            'd M Y',      // 06 Sep 2025
            'd/m/Y',      // 06/09/2025
            'Y-m-d'       // 2025-09-06
        ];
        
        $parsed_date = null;
        foreach ($formats as $format) {
            $parsed = DateTime::createFromFormat($format, $date);
            if ($parsed) {
                $parsed_date = $parsed;
                break;
            }
        }
        
        if (!$parsed_date) {
            return [
                'valid' => false,
                'error' => 'Invalid date format',
                'suggestion' => 'Use current date as default'
            ];
        }
        
        // Check if date is in the past (more than 1 day ago)
        $now = new DateTime();
        $one_day_ago = clone $now;
        $one_day_ago->modify('-1 day');
        
        if ($parsed_date < $one_day_ago) {
            return [
                'valid' => false,
                'error' => 'Date is in the past',
                'suggestion' => 'Verify date or use current date'
            ];
        }
        
        return [
            'valid' => true,
            'value' => $date,
            'parsed' => $parsed_date
        ];
    }
    
    /**
     * Validate appointment time
     */
    private function validateTime($time) {
        if (empty($time)) {
            return [
                'valid' => true, // Time is optional
                'value' => null
            ];
        }
        
        // Check for valid time format
        if (!preg_match('/^\d{1,2}:\d{2}(\s*(am|pm|AM|PM))?$/', $time)) {
            return [
                'valid' => false,
                'error' => 'Invalid time format',
                'suggestion' => 'Use format like "14:30" or "2:30 PM"'
            ];
        }
        
        return [
            'valid' => true,
            'value' => $time
        ];
    }
    
    /**
     * Validate service name
     */
    private function validateServiceName($service_name) {
        if (empty($service_name)) {
            return [
                'valid' => false,
                'error' => 'Service name is required',
                'suggestion' => 'Use "General Consultation" as default'
            ];
        }
        
        // Check for reasonable length
        if (strlen($service_name) > 200) {
            return [
                'valid' => false,
                'error' => 'Service name too long',
                'suggestion' => 'Truncate to first 200 characters'
            ];
        }
        
        return [
            'valid' => true,
            'value' => $service_name
        ];
    }
    
    /**
     * Validate practitioner information
     */
    private function validatePractitionerInfo($data) {
        $issues = [];
        
        // Check practitioner number format
        if (!empty($data['practitioner_number'])) {
            if (!preg_match('/^MP\d{7}$/', $data['practitioner_number'])) {
                $issues[] = 'Invalid practitioner number format (should be MP followed by 7 digits)';
            }
        }
        
        // Check practice number format
        if (!empty($data['practice_number'])) {
            if (!preg_match('/^PR\d{7}$/', $data['practice_number'])) {
                $issues[] = 'Invalid practice number format (should be PR followed by 7 digits)';
            }
        }
        
        // Check company registration format
        if (!empty($data['company_registration'])) {
            if (!preg_match('/^\d{4}\/\d{6}\/\d{1,2}$/', $data['company_registration'])) {
                $issues[] = 'Invalid company registration format (should be YYYY/NNNNNN/N)';
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'suggestion' => empty($issues) ? 'All practitioner info is valid' : 'Fix format issues'
        ];
    }
    
    /**
     * Validate pricing information
     */
    private function validatePricing($data) {
        $issues = [];
        
        // Check unit price
        if (!isset($data['unit_price']) || !is_numeric($data['unit_price'])) {
            $issues[] = 'Unit price must be a valid number';
        } elseif ($data['unit_price'] < 0) {
            $issues[] = 'Unit price cannot be negative';
        } elseif ($data['unit_price'] > 10000) {
            $issues[] = 'Unit price seems unusually high';
        }
        
        // Check quantity
        if (!isset($data['quantity']) || !is_numeric($data['quantity'])) {
            $issues[] = 'Quantity must be a valid number';
        } elseif ($data['quantity'] <= 0) {
            $issues[] = 'Quantity must be greater than 0';
        } elseif ($data['quantity'] > 100) {
            $issues[] = 'Quantity seems unusually high';
        }
        
        // Check VAT amount
        if (isset($data['vat_amount']) && $data['vat_amount'] < 0) {
            $issues[] = 'VAT amount cannot be negative';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'suggestion' => empty($issues) ? 'Pricing is valid' : 'Fix pricing issues'
        ];
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields($data) {
        $required_fields = [
            'customer_name' => 'Customer name',
            'service_name' => 'Service name',
            'unit_price' => 'Unit price',
            'invoice_number' => 'Invoice number',
            'invoice_date' => 'Invoice date'
        ];
        
        $missing_fields = [];
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $missing_fields[] = $label;
            }
        }
        
        return [
            'valid' => empty($missing_fields),
            'missing' => $missing_fields,
            'suggestion' => empty($missing_fields) ? 'All required fields present' : 'Add missing required fields'
        ];
    }
    
    /**
     * Get validation summary
     */
    public function getValidationSummary() {
        $total_validations = count($this->validation_results);
        $valid_count = 0;
        $issues = [];
        
        foreach ($this->validation_results as $field => $result) {
            if ($result['valid']) {
                $valid_count++;
            } else {
                $issues[$field] = $result;
            }
        }
        
        return [
            'total_fields' => $total_validations,
            'valid_fields' => $valid_count,
            'invalid_fields' => $total_validations - $valid_count,
            'success_rate' => $total_validations > 0 ? ($valid_count / $total_validations) * 100 : 0,
            'issues' => $issues
        ];
    }
    
    /**
     * Get validation report for logging
     */
    public function getValidationReport() {
        $summary = $this->getValidationSummary();
        
        $report = "Data Validation Report:\n";
        $report .= "Success Rate: " . round($summary['success_rate'], 2) . "%\n";
        $report .= "Valid Fields: {$summary['valid_fields']}/{$summary['total_fields']}\n";
        
        if (!empty($summary['issues'])) {
            $report .= "\nIssues Found:\n";
            foreach ($summary['issues'] as $field => $issue) {
                $report .= "- {$field}: {$issue['error']}\n";
                if (isset($issue['suggestion'])) {
                    $report .= "  Suggestion: {$issue['suggestion']}\n";
                }
            }
        }
        
        return $report;
    }
    
    /**
     * Apply validation fixes to data
     */
    public function applyFixes($data) {
        $fixed_data = $data;
        
        // Fix customer name
        if (!$this->validation_results['customer_name']['valid']) {
            $fixed_data['customer_name'] = 'Valued Patient';
        }
        
        // Fix email
        if (!$this->validation_results['customer_email']['valid']) {
            $fixed_data['customer_email'] = null;
        }
        
        // Fix date
        if (!$this->validation_results['appointment_date']['valid']) {
            $fixed_data['appointment_date'] = date('d F Y');
            $fixed_data['invoice_date'] = date('j M Y');
        }
        
        // Fix service name
        if (!$this->validation_results['service_name']['valid']) {
            $fixed_data['service_name'] = 'General Consultation';
            $fixed_data['service_description'] = 'General Consultation';
        }
        
        // Fix pricing
        if (!$this->validation_results['pricing']['valid']) {
            $fixed_data['unit_price'] = 960.00;
            $fixed_data['quantity'] = 1.00;
            $fixed_data['subtotal'] = 960.00;
            $fixed_data['total_amount'] = 960.00;
        }
        
        // Ensure required fields
        if (!$this->validation_results['required_fields']['valid']) {
            $fixed_data['invoice_number'] = $fixed_data['invoice_number'] ?? 'INV-' . date('md') . rand(100, 999);
            $fixed_data['invoice_date'] = $fixed_data['invoice_date'] ?? date('j M Y');
        }
        
        return $fixed_data;
    }
}
