<?php
/**
 * CLI Test for Timetics PDF Generation
 * 
 * Run this script from command line to test PDF generation
 * Usage: php cli-test.php
 */

// Set up WordPress environment
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Load the plugin classes
require_once('includes/StructuredEmailParser.php');

class CliPdfTest {
    
    public function __construct() {
        echo "=== Timetics PDF Generation CLI Test ===\n\n";
    }
    
    /**
     * Test HTML parsing with realistic content
     */
    public function testHtmlParsing() {
        echo "1. Testing HTML Parsing...\n";
        echo str_repeat("-", 50) . "\n";
        
        $html_message = '<p>Your appointment has been successfully scheduled.</p>
<p><br></p>
<p><strong>Consultation Details:</strong></p>
<p>- Type: IV Drip</p>
<p>- Date: 06 September 2025</p>
<p>- Time: 01:00 pm</p>
<p>- Duration: 40 min</p>
<p>- Location: Val De Vie Estate, Paarl</p>
<p><br></p>
<p><strong>Your information as provided:</strong></p>
<p>Your name: John Doe</p>
<p>Your email: john.doe@example.com</p>
<p>Medical Aid Scheme: Discovery Health</p>
<p>Medical Aid Number: 123456789</p>
<p>ID Number: 8001010001087</p>
<p><br></p>
<p><strong>Practitioner Information:</strong></p>
<p>- Practitioner: Dr Ben Coetsee</p>
<p>- Company Registration No: 2024/748523/21</p>
<p>- Practitioner Number: MP0953814</p>
<p>- Practice Number: PR1153307</p>
<p>- ICD10 0190 Z00.0</p>';

        try {
            $parser = new StructuredEmailParser();
            $parsed_data = $parser->parseEmail('Appointment Confirmation', $html_message);
            
            echo "✅ HTML parsing successful!\n\n";
            echo "Extracted Data:\n";
            echo "- Customer Name: " . ($parsed_data['customer_name'] ?? 'NOT FOUND') . "\n";
            echo "- Service Name: " . ($parsed_data['service_name'] ?? 'NOT FOUND') . "\n";
            echo "- Medical Aid Scheme: " . ($parsed_data['medical_aid_scheme'] ?? 'NOT FOUND') . "\n";
            echo "- Medical Aid Number: " . ($parsed_data['medical_aid_number'] ?? 'NOT FOUND') . "\n";
            echo "- ID Number: " . ($parsed_data['id_number'] ?? 'NOT FOUND') . "\n";
            echo "- Service Code: " . ($parsed_data['service_code'] ?? 'NOT FOUND') . "\n";
            echo "- ICD Code: " . ($parsed_data['icd_code'] ?? 'NOT FOUND') . "\n";
            echo "- Unit Price: " . ($parsed_data['unit_price'] ?? 'NOT FOUND') . "\n";
            
            return $parsed_data;
            
        } catch (Exception $e) {
            echo "❌ HTML parsing failed: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n\n";
            return null;
        }
    }
    
    /**
     * Test TCPDF availability
     */
    public function testTcpdfAvailability() {
        echo "\n2. Testing TCPDF Availability...\n";
        echo str_repeat("-", 50) . "\n";
        
        // Check if TCPDF is available
        if (class_exists('TCPDF')) {
            echo "✅ TCPDF class already loaded\n";
            return true;
        }
        
        // Try to load from vendor
        $autoload_path = __DIR__ . '/vendor/autoload.php';
        echo "Checking autoload path: {$autoload_path}\n";
        
        if (file_exists($autoload_path)) {
            echo "✅ Autoload file exists\n";
            require_once($autoload_path);
            
            if (class_exists('TCPDF')) {
                echo "✅ TCPDF loaded successfully\n";
                return true;
            } else {
                echo "❌ TCPDF not available after autoload\n";
                return false;
            }
        } else {
            echo "❌ Autoload file not found\n";
            return false;
        }
    }
    
    /**
     * Test PDF directory creation and permissions
     */
    public function testPdfDirectory() {
        echo "\n3. Testing PDF Directory...\n";
        echo str_repeat("-", 50) . "\n";
        
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';
        
        echo "PDF Directory: {$pdf_dir}\n";
        
        // Check if directory exists
        if (!file_exists($pdf_dir)) {
            echo "Directory doesn't exist, creating...\n";
            if (wp_mkdir_p($pdf_dir)) {
                echo "✅ Directory created successfully\n";
            } else {
                echo "❌ Failed to create directory\n";
                return false;
            }
        } else {
            echo "✅ Directory exists\n";
        }
        
        // Check if writable
        if (is_writable($pdf_dir)) {
            echo "✅ Directory is writable\n";
            return true;
        } else {
            echo "❌ Directory is not writable\n";
            return false;
        }
    }
    
    /**
     * Test actual PDF generation
     */
    public function testPdfGeneration($parsed_data) {
        echo "\n4. Testing PDF Generation...\n";
        echo str_repeat("-", 50) . "\n";
        
        if (!$parsed_data) {
            echo "❌ No parsed data available, skipping PDF test\n";
            return false;
        }
        
        try {
            // Create PDF using TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Dr Ben Coetsee');
            $pdf->SetAuthor('Dr Ben Coetsee');
            $pdf->SetTitle('Test Consultation Confirmation');
            $pdf->SetSubject('Test Booking Confirmation');
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Add a page
            $pdf->AddPage();
            
            // Create simple HTML content for testing
            $html = $this->createTestHtml($parsed_data);
            
            echo "✅ PDF object created\n";
            echo "✅ HTML content generated (" . strlen($html) . " chars)\n";
            
            // Write HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            echo "✅ HTML written to PDF\n";
            
            // Generate filename
            $filename = 'test-invoice-' . date('Y-m-d-H-i-s') . '.pdf';
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/timetics-pdf/';
            $pdf_path = $pdf_dir . $filename;
            
            // Output PDF to file
            $pdf->Output($pdf_path, 'F');
            
            echo "✅ PDF saved to: {$filename}\n";
            
            // Validate the PDF
            if (file_exists($pdf_path)) {
                $file_size = filesize($pdf_path);
                echo "✅ PDF file exists ({$file_size} bytes)\n";
                
                // Check PDF header
                $handle = fopen($pdf_path, 'rb');
                $header = fread($handle, 4);
                fclose($handle);
                
                if ($header === '%PDF') {
                    echo "✅ PDF header is valid\n";
                    
                    // Show download URL
                    $pdf_url = $upload_dir['baseurl'] . '/timetics-pdf/' . $filename;
                    echo "🔗 Download URL: {$pdf_url}\n";
                    
                    return $pdf_path;
                } else {
                    echo "❌ Invalid PDF header: " . bin2hex($header) . "\n";
                    return false;
                }
            } else {
                echo "❌ PDF file was not created\n";
                return false;
            }
            
        } catch (Exception $e) {
            echo "❌ PDF generation failed: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            return false;
        }
    }
    
    /**
     * Create test HTML content
     */
    private function createTestHtml($data) {
        $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Tax Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { margin-bottom: 30px; }
        .invoice-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .customer-name { font-size: 14px; font-weight: bold; margin-top: 10px; }
        .medical-info { font-size: 12px; margin-top: 5px; color: #555; }
        .company-info { font-size: 11px; color: #666; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-title">TEST TAX INVOICE</div>
        <div class="customer-name">' . esc_html($data['customer_name'] ?? 'Test Customer') . '</div>
        ' . (!empty($data['medical_aid_scheme']) ? '<div class="medical-info"><strong>Medical Aid Scheme:</strong> ' . esc_html($data['medical_aid_scheme']) . '</div>' : '') . '
        ' . (!empty($data['medical_aid_number']) ? '<div class="medical-info"><strong>Medical Aid Number:</strong> ' . esc_html($data['medical_aid_number']) . '</div>' : '') . '
        ' . (!empty($data['id_number']) ? '<div class="medical-info"><strong>ID Number:</strong> ' . esc_html($data['id_number']) . '</div>' : '') . '
        
        <div class="company-info">
            <strong>Dr Ben</strong><br>
            Company Registration No: 2024/748523/21<br>
            MP0953814 – PR1153307<br>
            Office A2, 1st floor Polo Village Offices<br>
            Val de Vie, Paarl, Western Cape, 7636, South Africa
        </div>
    </div>
    
    <table border="1">
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Amount ZAR</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . esc_html($data['service_code'] ?? 'Code 0190') . '<br>' . esc_html($data['icd_code'] ?? 'Z00.0') . '</td>
                <td>' . esc_html($data['service_description'] ?? 'Test Service') . '</td>
                <td class="text-center">1.00</td>
                <td class="text-right">' . number_format($data['unit_price'] ?? 960.00, 2) . '</td>
                <td class="text-right">' . number_format($data['unit_price'] ?? 960.00, 2) . '</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right"><strong>TOTAL ZAR</strong></td>
                <td class="text-right"><strong>' . number_format($data['unit_price'] ?? 960.00, 2) . '</strong></td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 30px; padding: 20px; background-color: #f9f9f9;">
        <p><strong>Due Date:</strong> ' . date('j M Y') . '</p>
        <p>This is a test invoice generated by the CLI test script.</p>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Test email detection logic
     */
    public function testEmailDetection() {
        echo "\n5. Testing Email Detection Logic...\n";
        echo str_repeat("-", 50) . "\n";
        
        $test_emails = [
            'timetics_valid' => [
                'subject' => 'Appointment Confirmation',
                'message' => 'Your appointment has been successfully scheduled. Consultation details: Type: IV Drip. Practitioner: Dr Ben Coetsee. Company Registration No: 2024/748523/21',
                'expected' => true
            ],
            'echocardiogram' => [
                'subject' => 'Appointment Confirmation', 
                'message' => 'Your appointment has been successfully scheduled. Type: Echocardiogram. Practitioner: Dr Ben Coetsee.',
                'expected' => true // Should detect as Timetics but skip PDF
            ],
            'woocommerce' => [
                'subject' => 'Order Confirmation',
                'message' => 'Thank you for your order. Order ID: #12345. Order total: $150.00',
                'expected' => false
            ]
        ];
        
        // We need to simulate the email detection logic here
        foreach ($test_emails as $type => $email) {
            $score = $this->calculateTimeticssScore($email);
            $is_detected = $score >= 3;
            
            echo "Email Type: {$type}\n";
            echo "  Score: {$score}\n";
            echo "  Detected: " . ($is_detected ? "✅ YES" : "❌ NO") . "\n";
            echo "  Expected: " . ($email['expected'] ? "YES" : "NO") . "\n";
            echo "  Result: " . (($is_detected === $email['expected']) ? "✅ CORRECT" : "❌ WRONG") . "\n\n";
        }
    }
    
    /**
     * Calculate Timetics score (simplified version)
     */
    private function calculateTimeticssScore($email) {
        $content = strtolower($email['subject'] . ' ' . $email['message']);
        
        $timetics_patterns = [
            'your appointment has been successfully scheduled',
            'consultation details:',
            'practitioner information:',
            'dr ben coetsee',
            'performance md inc',
            'company registration no:',
            'val de vie',
            'practitioner: dr ben coetsee',
            '2024/748523/21'
        ];
        
        $score = 0;
        foreach ($timetics_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $score++;
            }
        }
        
        return $score;
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Starting comprehensive CLI tests...\n\n";
        
        // Test 1: HTML Parsing
        $parsed_data = $this->testHtmlParsing();
        
        // Test 2: TCPDF Availability
        $tcpdf_available = $this->testTcpdfAvailability();
        
        // Test 3: PDF Directory
        $directory_ok = $this->testPdfDirectory();
        
        // Test 4: PDF Generation (only if previous tests passed)
        $pdf_generated = false;
        if ($tcpdf_available && $directory_ok && $parsed_data) {
            $pdf_generated = $this->testPdfGeneration($parsed_data);
        }
        
        // Test 5: Email Detection
        $this->testEmailDetection();
        
        // Summary
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        echo "HTML Parsing:     " . ($parsed_data ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "TCPDF Available:  " . ($tcpdf_available ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "PDF Directory:    " . ($directory_ok ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "PDF Generation:   " . ($pdf_generated ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "Overall Status:   " . (($parsed_data && $tcpdf_available && $directory_ok && $pdf_generated) ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// Run the tests
$tester = new CliPdfTest();
$tester->runAllTests();
