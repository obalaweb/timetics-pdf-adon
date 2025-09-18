/**
 * Admin JavaScript for Timetics PDF Addon
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize color pickers
    if (typeof $.fn.wpColorPicker !== 'undefined') {
        $('#pdf_primary_color, #pdf_secondary_color').wpColorPicker();
    }

    // Handle logo upload
    $('#upload_logo_button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var logoInput = $('#pdf_logo');
        var logoPreview = $('#logo_preview');
        
        // Create media frame
        var frame = wp.media({
            title: 'Select Logo',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        // When image selected
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            
            // Update input field
            logoInput.val(attachment.url);
            
            // Update preview
            logoPreview.html('<img src="' + attachment.url + '" alt="Logo" style="max-width: 200px; max-height: 100px;">');
            logoPreview.show();
        });

        // Open media frame
        frame.open();
    });

    // Handle logo URL input change
    $('#pdf_logo').on('input', function() {
        var logoUrl = $(this).val();
        var logoPreview = $('#logo_preview');
        
        if (logoUrl) {
            logoPreview.html('<img src="' + logoUrl + '" alt="Logo" style="max-width: 200px; max-height: 100px;">');
            logoPreview.show();
        } else {
            logoPreview.hide();
        }
    });

    // Form validation
    $('form').on('submit', function(e) {
        var enabled = $('#pdf_enabled').is(':checked');
        
        if (!enabled) {
            // If PDF generation is disabled, allow form submission
            return true;
        }
        
        // Basic validation for enabled PDF generation
        var headerText = $('#pdf_header_text').val().trim();
        var footerText = $('#pdf_footer_text').val().trim();
        
        if (!headerText) {
            alert('Please enter a header text for the PDF.');
            $('#pdf_header_text').focus();
            e.preventDefault();
            return false;
        }
        
        if (!footerText) {
            alert('Please enter a footer text for the PDF.');
            $('#pdf_footer_text').focus();
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // Show/hide settings based on enable checkbox
    $('#pdf_enabled').on('change', function() {
        var enabled = $(this).is(':checked');
        var settingsRows = $('tr:not(:first-child)');
        
        if (enabled) {
            settingsRows.show();
        } else {
            settingsRows.hide();
            $('tr:first-child').show(); // Keep the enable checkbox visible
        }
    });

    // Initialize visibility on page load
    $('#pdf_enabled').trigger('change');

    // Add some helpful tooltips
    $('.form-table th').each(function() {
        var label = $(this).text();
        var helpText = '';
        
        switch (label.trim()) {
            case 'Enable PDF Generation':
                helpText = 'When enabled, PDF confirmations will be automatically generated and attached to booking emails.';
                break;
            case 'PDF Storage':
                helpText = 'Choose whether to keep PDF files permanently or delete them after sending emails.';
                break;
            case 'Company Logo':
                helpText = 'Upload your company logo to include in PDF confirmations. Recommended size: 200x100 pixels.';
                break;
            case 'Header Text':
                helpText = 'The main title that appears at the top of the PDF confirmation.';
                break;
            case 'Footer Text':
                helpText = 'A thank you message or additional information that appears at the bottom of the PDF.';
                break;
            case 'Primary Color':
                helpText = 'The main color used for headers and important elements in the PDF.';
                break;
            case 'Secondary Color':
                helpText = 'The secondary color used for sub-headers and accent elements in the PDF.';
                break;
            case 'PDF Template':
                helpText = 'Choose the visual style and layout for your PDF confirmations.';
                break;
        }
        
        if (helpText) {
            $(this).append('<span class="dashicons dashicons-editor-help" style="margin-left: 5px; color: #666; cursor: help;" title="' + helpText + '"></span>');
        }
    });

    // Add success message handling
    if (window.location.search.includes('settings-updated=true')) {
        $('<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>')
            .insertAfter('h1')
            .delay(3000)
            .fadeOut();
    }
});
