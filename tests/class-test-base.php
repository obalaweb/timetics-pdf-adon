<?php
/**
 * Test Base Class
 * 
 * Base class for all Timetics PDF Addon tests
 */

abstract class Test_Base extends WP_UnitTestCase {
    
    protected $pdf_addon;
    protected $test_booking_id;
    protected $test_appointment_id;
    protected $test_customer_id;
    protected $test_staff_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Initialize the PDF addon
        $this->pdf_addon = new Timetics_Pdf_Addon();
        
        // Create test data
        $this->create_test_data();
    }
    
    public function tearDown(): void {
        // Clean up test data
        $this->cleanup_test_data();
        
        parent::tearDown();
    }
    
    /**
     * Create test data for testing
     */
    protected function create_test_data() {
        // Create test customer
        $this->test_customer_id = wp_insert_user([
            'user_login' => 'test_customer_' . uniqid(),
            'user_email' => 'test@example.com',
            'user_pass' => 'password',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'role' => 'customer'
        ]);
        
        // Create test staff
        $this->test_staff_id = wp_insert_user([
            'user_login' => 'test_staff_' . uniqid(),
            'user_email' => 'staff@example.com',
            'user_pass' => 'password',
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'role' => 'administrator'
        ]);
        
        // Create test appointment
        $this->test_appointment_id = wp_insert_post([
            'post_title' => 'Test Appointment',
            'post_type' => 'timetics-appointment',
            'post_status' => 'publish',
            'post_author' => $this->test_staff_id
        ]);
        
        // Add appointment meta
        update_post_meta($this->test_appointment_id, '_tt_appointment_name', 'Test Appointment');
        update_post_meta($this->test_appointment_id, '_tt_appointment_duration', 60);
        update_post_meta($this->test_appointment_id, '_tt_appointment_price', 100.00);
        
        // Create test booking
        $this->test_booking_id = wp_insert_post([
            'post_title' => 'Test Booking',
            'post_type' => 'timetics-booking',
            'post_status' => 'completed',
            'post_author' => $this->test_customer_id
        ]);
        
        // Add booking meta
        update_post_meta($this->test_booking_id, '_tt_booking_customer', $this->test_customer_id);
        update_post_meta($this->test_booking_id, '_tt_booking_appointment', $this->test_appointment_id);
        update_post_meta($this->test_booking_id, '_tt_booking_staff', $this->test_staff_id);
        update_post_meta($this->test_booking_id, '_tt_booking_order_total', 100.00);
        update_post_meta($this->test_booking_id, '_tt_booking_start_date', '2025-01-20');
        update_post_meta($this->test_booking_id, '_tt_booking_start_time', '10:00');
        update_post_meta($this->test_booking_id, '_tt_booking_end_date', '2025-01-20');
        update_post_meta($this->test_booking_id, '_tt_booking_end_time', '11:00');
        update_post_meta($this->test_booking_id, '_tt_booking_location', 'Test Location');
        update_post_meta($this->test_booking_id, '_tt_booking_payment_status', 'paid');
        
        // Add custom form data for medical info
        $custom_form_data = [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'medical_aid_number' => 'MED123456',
            'id_number' => '1234567890123',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'postal_code' => '12345',
            'emergency_contact' => 'Emergency Person',
            'emergency_phone' => '0987654321',
            'medical_conditions' => 'None',
            'medications' => 'None',
            'allergies' => 'None'
        ];
        
        update_post_meta($this->test_booking_id, '_tt_booking_custom_form_data', maybe_serialize($custom_form_data));
    }
    
    /**
     * Clean up test data
     */
    protected function cleanup_test_data() {
        if ($this->test_booking_id) {
            wp_delete_post($this->test_booking_id, true);
        }
        
        if ($this->test_appointment_id) {
            wp_delete_post($this->test_appointment_id, true);
        }
        
        if ($this->test_customer_id) {
            wp_delete_user($this->test_customer_id);
        }
        
        if ($this->test_staff_id) {
            wp_delete_user($this->test_staff_id);
        }
    }
    
    /**
     * Assert that a booking object has the expected methods
     */
    protected function assertBookingObjectHasRequiredMethods($booking) {
        $this->assertTrue(method_exists($booking, 'get_id'), 'Booking should have get_id method');
        $this->assertTrue(method_exists($booking, 'get_total'), 'Booking should have get_total method');
        $this->assertTrue(method_exists($booking, 'get_customer_id'), 'Booking should have get_customer_id method');
        $this->assertTrue(method_exists($booking, 'get_appointment'), 'Booking should have get_appointment method');
        $this->assertTrue(method_exists($booking, 'get_staff_id'), 'Booking should have get_staff_id method');
        $this->assertTrue(method_exists($booking, 'get_start_date'), 'Booking should have get_start_date method');
        $this->assertTrue(method_exists($booking, 'get_start_time'), 'Booking should have get_start_time method');
        $this->assertTrue(method_exists($booking, 'get_location'), 'Booking should have get_location method');
    }
    
    /**
     * Assert that an appointment object has the expected methods
     */
    protected function assertAppointmentObjectHasRequiredMethods($appointment) {
        $this->assertTrue(method_exists($appointment, 'get_id'), 'Appointment should have get_id method');
        $this->assertTrue(method_exists($appointment, 'get_name'), 'Appointment should have get_name method');
        $this->assertTrue(method_exists($appointment, 'get_duration'), 'Appointment should have get_duration method');
        $this->assertTrue(method_exists($appointment, 'get_price'), 'Appointment should have get_price method');
    }
    
    /**
     * Assert that a customer object has the expected methods
     */
    protected function assertCustomerObjectHasRequiredMethods($customer) {
        $this->assertTrue(method_exists($customer, 'get_id'), 'Customer should have get_id method');
        $this->assertTrue(method_exists($customer, 'get_display_name'), 'Customer should have get_display_name method');
        $this->assertTrue(method_exists($customer, 'get_email'), 'Customer should have get_email method');
    }
}
