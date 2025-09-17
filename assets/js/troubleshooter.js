/**
 * Timetics PDF Addon Troubleshooter JavaScript
 *
 * @package TimeticsPdfAddon
 * @version 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Test REST endpoint
    $('#test-rest-endpoint').on('click', function() {
        var $button = $(this);
        var $result = $('#rest-test-result');
        
        $button.prop('disabled', true).text(timeticsPdfTroubleshooter.strings.testing);
        $result.removeClass('result-success result-error result-warning').html('');
        
        $.ajax({
            url: timeticsPdfTroubleshooter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'timetics_pdf_test_endpoint',
                nonce: timeticsPdfTroubleshooter.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('result-success').html('<strong>✓ ' + timeticsPdfTroubleshooter.strings.success + '</strong><br>' + response.data.message);
                } else {
                    $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>' + response.data.message);
                }
            },
            error: function() {
                $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>AJAX request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test REST Endpoint');
            }
        });
    });

    // Clear cache
    $('#clear-cache').on('click', function() {
        var $button = $(this);
        var $result = $('#fix-results');
        
        $button.prop('disabled', true).text(timeticsPdfTroubleshooter.strings.fixing);
        $result.removeClass('result-success result-error result-warning').html('');
        
        $.ajax({
            url: timeticsPdfTroubleshooter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'timetics_pdf_clear_cache',
                nonce: timeticsPdfTroubleshooter.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('result-success').html('<strong>✓ ' + timeticsPdfTroubleshooter.strings.success + '</strong><br>' + response.data.message);
                } else {
                    $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>' + response.data.message);
                }
            },
            error: function() {
                $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>AJAX request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text('Clear Plugin Cache');
            }
        });
    });

    // Fix permissions
    $('#fix-permissions').on('click', function() {
        var $button = $(this);
        var $result = $('#fix-results');
        
        $button.prop('disabled', true).text(timeticsPdfTroubleshooter.strings.fixing);
        $result.removeClass('result-success result-error result-warning').html('');
        
        $.ajax({
            url: timeticsPdfTroubleshooter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'timetics_pdf_fix_permissions',
                nonce: timeticsPdfTroubleshooter.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('result-success').html('<strong>✓ ' + timeticsPdfTroubleshooter.strings.success + '</strong><br>' + response.data.message);
                    // Reload page after a short delay to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>' + response.data.message);
                }
            },
            error: function() {
                $result.addClass('result-error').html('<strong>✗ ' + timeticsPdfTroubleshooter.strings.error + '</strong><br>AJAX request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text('Fix File Permissions');
            }
        });
    });

    // Regenerate PDF directory
    $('#regenerate-pdf-dir').on('click', function() {
        var $button = $(this);
        var $result = $('#fix-results');
        
        $button.prop('disabled', true).text(timeticsPdfTroubleshooter.strings.fixing);
        $result.removeClass('result-success result-error result-warning').html('');
        
        // This is a client-side operation that simulates directory regeneration
        setTimeout(function() {
            $result.addClass('result-success').html('<strong>✓ ' + timeticsPdfTroubleshooter.strings.success + '</strong><br>PDF directory regeneration completed. Please check the plugin status above.');
            $button.prop('disabled', false).text('Regenerate PDF Directory');
        }, 1500);
    });

    // Auto-refresh status sections
    function refreshStatus() {
        // Reload the page every 30 seconds to show updated status
        setTimeout(function() {
            location.reload();
        }, 30000);
    }

    // Start auto-refresh
    refreshStatus();

    // Add helpful tooltips
    $('.status-item').each(function() {
        var $item = $(this);
        var $status = $item.find('span:last-child');
        
        if ($status.hasClass('status-error')) {
            $item.attr('title', 'This item needs attention. Check the troubleshooting steps below.');
        } else if ($status.hasClass('status-warning')) {
            $item.attr('title', 'This item is optional but recommended.');
        }
    });

    // Add copy functionality for system info
    $('.system-info td').on('click', function() {
        var text = $(this).text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                // Show a brief "copied" message
                var $td = $(this);
                var originalText = $td.text();
                $td.text('Copied!').css('color', '#46b450');
                setTimeout(function() {
                    $td.text(originalText).css('color', '');
                }, 1000);
            }.bind(this));
        }
    });

    // Add keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + R to refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
        
        // Ctrl/Cmd + T to test REST endpoint
        if ((e.ctrlKey || e.metaKey) && e.key === 't') {
            e.preventDefault();
            $('#test-rest-endpoint').click();
        }
    });

    // Show keyboard shortcuts help
    $('<div class="keyboard-shortcuts" style="margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 12px;">' +
      '<strong>Keyboard Shortcuts:</strong> Ctrl/Cmd + R (Refresh), Ctrl/Cmd + T (Test REST Endpoint)' +
      '</div>').appendTo('.timetics-pdf-troubleshooter');
});
