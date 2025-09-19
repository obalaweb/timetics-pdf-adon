<?php
/**
 * Context-Aware Field Extractor for Timetics PDF Addon
 * 
 * This class provides intelligent field extraction using context
 * and multiple extraction strategies.
 * 
 * @package Timetics_Pdf_Addon
 * @since 2.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

class ContextAwareExtractor {
    
    /**
     * Context patterns for different field types
     */
    private $context_patterns = [
        'customer_name' => [
            'primary' => '/Your name:\s*(.+?)(?:\s+Your email|\s+Date|\s+Time|\s+Parking|\s+Practitioner|$)/i',
            'fallback' => '/Bill To:\s*(.+?)(?:\s+Invoice|\s+Date|\s+Time|$)/i',
            'alternative' => '/Patient:\s*(.+?)(?:\s+Email|\s+Date|\s+Time|$)/i'
        ],
        'customer_email' => [
            'primary' => '/Your email:\s*([^\s<>]+@[^\s<>]+)/i',
            'fallback' => '/Email:\s*([^\s<>]+@[^\s<>]+)/i',
            'alternative' => '/Contact:\s*([^\s<>]+@[^\s<>]+)/i'
        ],
        'appointment_date' => [
            'primary' => '/Date:\s*(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
            'fallback' => '/Date:\s*(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4})/i',
            'alternative' => '/Appointment Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})/i'
        ],
        'appointment_time' => [
            'primary' => '/Time:\s*(\d{1,2}:\d{2}\s*(?:am|pm|AM|PM))/i',
            'fallback' => '/Time:\s*(\d{1,2}:\d{2})/i',
            'alternative' => '/Appointment Time:\s*(\d{1,2}:\d{2})/i'
        ],
        'service_name' => [
            'primary' => '/Type:\s*(.+?)(?:\s+- Date|\s+- Time|\s+- Duration|\s+- Location|$)/i',
            'fallback' => '/Service:\s*(.+?)(?:\s+- Date|\s+- Time|\s+- Duration|$)/i',
            'alternative' => '/Consultation Type:\s*(.+?)(?:\s+- Date|\s+- Time|$)/i'
        ],
        'duration' => [
            'primary' => '/Duration:\s*(\d+\s*min)/i',
            'fallback' => '/Length:\s*(\d+\s*min)/i',
            'alternative' => '/Time:\s*(\d+\s*min)/i'
        ],
        'location' => [
            'primary' => '/Location:\s*(.+?)(?:\s+Please arrive|\s+Parking|\s+Google Maps|\s+Practitioner|$)/i',
            'fallback' => '/Address:\s*(.+?)(?:\s+Please arrive|\s+Parking|$)/i',
            'alternative' => '/Venue:\s*(.+?)(?:\s+Please arrive|\s+Parking|$)/i'
        ],
        'practitioner_name' => [
            'primary' => '/Practitioner:\s*(.+?)(?:\s+- Company|\s+- Practitioner Number|$)/i',
            'fallback' => '/Doctor:\s*(.+?)(?:\s+- Company|\s+- Practitioner Number|$)/i',
            'alternative' => '/Provider:\s*(.+?)(?:\s+- Company|$)/i'
        ],
        'company_registration' => [
            'primary' => '/Company Registration No:\s*([^\s<>]+)/i',
            'fallback' => '/Registration No:\s*([^\s<>]+)/i',
            'alternative' => '/Company Reg:\s*([^\s<>]+)/i'
        ],
        'practitioner_number' => [
            'primary' => '/Practitioner Number:\s*([^\s<>]+)/i',
            'fallback' => '/MP Number:\s*([^\s<>]+)/i',
            'alternative' => '/Practitioner ID:\s*([^\s<>]+)/i'
        ],
        'practice_number' => [
            'primary' => '/Practice Number:\s*([^\s<>]+)/i',
            'fallback' => '/PR Number:\s*([^\s<>]+)/i',
            'alternative' => '/Practice ID:\s*([^\s<>]+)/i'
        ],
        'icd_code' => [
            'primary' => '/ICD-10:\s*\(Code\s*(\d+)\)\s*([A-Z]\d{2}\.\d)/i',
            'fallback' => '/ICD10\s+(\d+)\s+([A-Z]\d{2}\.\d)/i',
            'alternative' => '/ICD10\s+([A-Z]\d{2}\.\d)/i'
        ]
    ];
    
    /**
     * Extract field with context awareness
     */
    public function extractField($content, $field_name, $context = []) {
        if (!isset($this->context_patterns[$field_name])) {
            return null;
        }
        
        $patterns = $this->context_patterns[$field_name];
        
        // Try primary pattern first
        if (isset($patterns['primary'])) {
            $result = $this->tryPattern($patterns['primary'], $content, $context);
            if ($result) {
                return $result;
            }
        }
        
        // Try fallback pattern
        if (isset($patterns['fallback'])) {
            $result = $this->tryPattern($patterns['fallback'], $content, $context);
            if ($result) {
                return $result;
            }
        }
        
        // Try alternative pattern
        if (isset($patterns['alternative'])) {
            $result = $this->tryPattern($patterns['alternative'], $content, $context);
            if ($result) {
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * Try a specific pattern with context
     */
    private function tryPattern($pattern, $content, $context) {
        if (preg_match($pattern, $content, $matches)) {
            $value = trim($matches[1]);
            
            // Apply context-based cleaning
            $value = $this->applyContextCleaning($value, $context);
            
            // Validate the extracted value
            if ($this->validateExtractedValue($value, $context)) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Apply context-based cleaning to extracted value
     */
    private function applyContextCleaning($value, $context) {
        // Remove common trailing patterns
        $trailing_patterns = [
            '/\s+Your email.*$/i',
            '/\s+Date.*$/i',
            '/\s+Time.*$/i',
            '/\s+Duration.*$/i',
            '/\s+Location.*$/i',
            '/\s+Practitioner.*$/i',
            '/\s+Company Registration.*$/i',
            '/\s+Parking.*$/i',
            '/\s+Google Maps.*$/i',
            '/\s+Cancellation Policy.*$/i',
            '/\s+Please arrive.*$/i',
            '/\s+We look forward.*$/i',
            '/\s+Warm regards.*$/i'
        ];
        
        foreach ($trailing_patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        // Remove HTML tags if any
        $value = strip_tags($value);
        
        // Clean up whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);
        
        return $value;
    }
    
    /**
     * Validate extracted value based on context
     */
    private function validateExtractedValue($value, $context) {
        if (empty($value)) {
            return false;
        }
        
        // Check for reasonable length
        if (strlen($value) > 500) {
            return false; // Too long, likely captured too much
        }
        
        // Check for common extraction errors
        $error_patterns = [
            '/^[^a-zA-Z]*$/',  // No letters at all
            '/^\d+$/',         // Only numbers
            '/^[^\w\s]*$/',    // Only special characters
            '/^(the|and|or|but|in|on|at|to|for|of|with|by)\s*$/i'  // Only common words
        ];
        
        foreach ($error_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract multiple fields from content
     */
    public function extractMultipleFields($content, $fields = null) {
        if ($fields === null) {
            $fields = array_keys($this->context_patterns);
        }
        
        $results = [];
        
        foreach ($fields as $field) {
            $results[$field] = $this->extractField($content, $field);
        }
        
        return $results;
    }
    
    /**
     * Extract fields with confidence scoring
     */
    public function extractWithConfidence($content, $field_name, $context = []) {
        if (!isset($this->context_patterns[$field_name])) {
            return [
                'value' => null,
                'confidence' => 0,
                'method' => 'unknown'
            ];
        }
        
        $patterns = $this->context_patterns[$field_name];
        $confidence_scores = [
            'primary' => 0.9,
            'fallback' => 0.7,
            'alternative' => 0.5
        ];
        
        foreach (['primary', 'fallback', 'alternative'] as $method) {
            if (isset($patterns[$method])) {
                $result = $this->tryPattern($patterns[$method], $content, $context);
                if ($result) {
                    return [
                        'value' => $result,
                        'confidence' => $confidence_scores[$method],
                        'method' => $method
                    ];
                }
            }
        }
        
        return [
            'value' => null,
            'confidence' => 0,
            'method' => 'none'
        ];
    }
    
    /**
     * Get extraction statistics
     */
    public function getExtractionStats($content, $fields = null) {
        if ($fields === null) {
            $fields = array_keys($this->context_patterns);
        }
        
        $stats = [
            'total_fields' => count($fields),
            'extracted_fields' => 0,
            'confidence_scores' => [],
            'extraction_methods' => []
        ];
        
        foreach ($fields as $field) {
            $result = $this->extractWithConfidence($content, $field);
            
            if ($result['value'] !== null) {
                $stats['extracted_fields']++;
            }
            
            $stats['confidence_scores'][$field] = $result['confidence'];
            $stats['extraction_methods'][$field] = $result['method'];
        }
        
        $stats['success_rate'] = $stats['total_fields'] > 0 
            ? ($stats['extracted_fields'] / $stats['total_fields']) * 100 
            : 0;
        
        $stats['average_confidence'] = !empty($stats['confidence_scores']) 
            ? array_sum($stats['confidence_scores']) / count($stats['confidence_scores']) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Add custom pattern for field extraction
     */
    public function addCustomPattern($field_name, $pattern, $priority = 'alternative') {
        if (!isset($this->context_patterns[$field_name])) {
            $this->context_patterns[$field_name] = [];
        }
        
        $this->context_patterns[$field_name][$priority] = $pattern;
    }
    
    /**
     * Get all available patterns for a field
     */
    public function getFieldPatterns($field_name) {
        return $this->context_patterns[$field_name] ?? [];
    }
    
    /**
     * Test pattern against content
     */
    public function testPattern($pattern, $content) {
        $matches = [];
        $result = preg_match($pattern, $content, $matches);
        
        return [
            'matched' => $result === 1,
            'matches' => $matches,
            'full_match' => $matches[0] ?? null,
            'captured_groups' => array_slice($matches, 1)
        ];
    }
}
