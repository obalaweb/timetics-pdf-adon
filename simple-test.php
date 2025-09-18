<?php
/**
 * Simple HTML Parsing Test
 */

// Bootstrap WordPress so ABSPATH is defined
require_once('../../../wp-load.php');

// Load the StructuredEmailParser directly
require_once('includes/StructuredEmailParser.php');

echo "=== Simple HTML Parsing Test ===\n\n";

// Test HTML content
$html_message = '<p>Your appointment has been successfully scheduled.</p>
<p><strong>Consultation Details:</strong></p>
<p>- Type: IV Drip</p>
<p>- Date: 06 September 2025</p>
<p>- Time: 01:00 pm</p>
<p>- Duration: 40 min</p>
<p>- Location: Val De Vie Estate, Paarl</p>
<p><strong>Your information as provided:</strong></p>
<p>Your name: John Doe</p>
<p>Your email: john.doe@example.com</p>
<p>Medical Aid Scheme: Discovery Health</p>
<p>Medical Aid Number: 123456789</p>
<p>ID Number: 8001010001087</p>
<p><strong>Practitioner Information:</strong></p>
<p>- Practitioner: Dr Ben Coetsee</p>
<p>- Company Registration No: 2024/748523/21</p>
<p>- Practitioner Number: MP0953814</p>
<p>- Practice Number: PR1153307</p>
<p>- ICD10 0190 Z00.0</p>';

echo "Original HTML (first 200 chars):\n";
echo substr($html_message, 0, 200) . "...\n\n";

try {
    echo "1. Testing StructuredEmailParser...\n";
    $parser = new StructuredEmailParser();
    $parsed_data = $parser->parseEmail('Test Subject', $html_message);
    
    echo "✅ Parsing successful!\n\n";
    echo "Extracted fields:\n";
    
    $important_fields = [
        'customer_name',
        'service_name', 
        'medical_aid_scheme',
        'medical_aid_number',
        'id_number',
        'service_code',
        'icd_code',
        'unit_price',
        'service_description'
    ];
    
    foreach ($important_fields as $field) {
        $value = isset($parsed_data[$field]) ? $parsed_data[$field] : 'NOT FOUND';
        echo "  {$field}: {$value}\n";
    }
    
    echo "\n2. Testing HTML structure preservation...\n";
    
    // Test the preserveStructure method
    $reflection = new ReflectionClass($parser);
    $method = $reflection->getMethod('preserveStructure');
    $method->setAccessible(true);
    
    $structured_lines = $method->invoke($parser, $html_message);
    
    echo "✅ Structure preservation successful!\n";
    echo "Structured lines (" . count($structured_lines) . " lines):\n";
    
    foreach (array_slice($structured_lines, 0, 10) as $i => $line) {
        echo "  " . ($i + 1) . ". {$line}\n";
    }
    
    if (count($structured_lines) > 10) {
        echo "  ... and " . (count($structured_lines) - 10) . " more lines\n";
    }
    
    echo "\n3. Testing field extraction patterns...\n";
    
    $content = implode("\n", $structured_lines);
    
    // Test specific regex patterns
    $patterns = [
        'customer_name' => '/Your name:\s*(.+)/i',
        'service_type' => '/Type:\s*(.+)/i',
        'medical_aid_scheme' => '/Medical Aid Scheme:\s*(.+)/i',
        'medical_aid_number' => '/Medical Aid Number:\s*(.+)/i',
        'id_number' => '/ID Number:\s*(.+)/i',
        'icd_code' => '/ICD10\s+(\d+)\s+([A-Z]\d{2}\.\d)/i'
    ];
    
    foreach ($patterns as $field => $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            echo "  ✅ {$field}: Found '{$matches[1]}'\n";
        } else {
            echo "  ❌ {$field}: Not found with pattern {$pattern}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
