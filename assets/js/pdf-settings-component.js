/**
 * PDF Settings Component for Timetics
 * 
 * This component integrates with the Timetics settings page to provide PDF configuration.
 */

(function() {
    'use strict';

    // Wait for Timetics to be ready
    function waitForTimetics() {
        if (typeof window.timetics !== 'undefined' && window.timetics.settings) {
            initializePdfSettings();
        } else {
            setTimeout(waitForTimetics, 100);
        }
    }

    function initializePdfSettings() {
        // Add PDF settings tab to Timetics settings
        if (window.timetics.settings && window.timetics.settings.addTab) {
            window.timetics.settings.addTab('pdf-settings', {
                title: 'PDF Settings',
                component: PdfSettingsComponent,
                icon: 'pdf'
            });
        }
    }

    // PDF Settings React Component
    const PdfSettingsComponent = React.memo(function PdfSettingsComponent() {
        const [settings, setSettings] = React.useState({
            enabled: 'yes',
            storage: 'temporary',
            logo: '',
            header_text: 'Booking Confirmation',
            footer_text: 'Thank you for your booking!',
            primary_color: '#3161F1',
            secondary_color: '#0C274A',
            template: 'default'
        });
        const [loading, setLoading] = React.useState(true);
        const [saving, setSaving] = React.useState(false);
        const [message, setMessage] = React.useState('');

        // Load settings on component mount
        React.useEffect(() => {
            loadSettings();
        }, []);

        const loadSettings = async () => {
            try {
                const response = await fetch('/wp-json/timetics/v1/settings/pdf', {
                    headers: {
                        'X-WP-Nonce': window.timeticsPro?.nonce || wpApiSettings?.nonce || ''
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    setSettings(data);
                }
            } catch (error) {
                console.error('Error loading PDF settings:', error);
            } finally {
                setLoading(false);
            }
        };

        const saveSettings = async () => {
            setSaving(true);
            setMessage('');

            try {
                const response = await fetch('/wp-json/timetics/v1/settings/pdf', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.timeticsPro?.nonce || wpApiSettings?.nonce || ''
                    },
                    body: JSON.stringify(settings)
                });

                const data = await response.json();
                
                if (response.ok) {
                    setMessage('Settings saved successfully!');
                    setTimeout(() => setMessage(''), 3000);
                } else {
                    setMessage('Error saving settings: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                setMessage('Error saving settings: ' + error.message);
            } finally {
                setSaving(false);
            }
        };

        const handleInputChange = (field, value) => {
            setSettings(prev => ({
                ...prev,
                [field]: value
            }));
        };

        const openMediaLibrary = () => {
            if (window.wp && window.wp.media) {
                const frame = window.wp.media({
                    title: 'Select Logo',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });

                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    handleInputChange('logo', attachment.url);
                });

                frame.open();
            }
        };

        if (loading) {
            return React.createElement('div', { className: 'pdf-settings-loading' },
                React.createElement('p', null, 'Loading PDF settings...')
            );
        }

        return React.createElement('div', { className: 'pdf-settings-container' },
            // Header
            React.createElement('div', { className: 'pdf-settings-header' },
                React.createElement('h2', null, 'PDF Settings'),
                React.createElement('p', { className: 'description' }, 
                    'Configure how PDF confirmations are generated and sent to customers.'
                )
            ),

            // Message
            message && React.createElement('div', { 
                className: `notice notice-${message.includes('Error') ? 'error' : 'success'}` 
            }, message),

            // General Settings Section
            React.createElement('div', { className: 'settings-section' },
                React.createElement('h3', null, 'General Settings'),
                
                // Enable PDF Generation
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', { className: 'checkbox-label' },
                        React.createElement('input', {
                            type: 'checkbox',
                            checked: settings.enabled === 'yes',
                            onChange: (e) => handleInputChange('enabled', e.target.checked ? 'yes' : 'no')
                        }),
                        ' Enable PDF Generation'
                    ),
                    React.createElement('p', { className: 'description' }, 
                        'Enable automatic PDF generation for booking confirmations.'
                    )
                ),

                // PDF Storage
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'PDF Storage'),
                    React.createElement('select', {
                        value: settings.storage,
                        onChange: (e) => handleInputChange('storage', e.target.value)
                    },
                        React.createElement('option', { value: 'temporary' }, 'Temporary (delete after email)'),
                        React.createElement('option', { value: 'permanent' }, 'Permanent (keep files)')
                    ),
                    React.createElement('p', { className: 'description' }, 
                        'Choose how to store generated PDF files.'
                    )
                )
            ),

            // PDF Customization Section
            React.createElement('div', { className: 'settings-section' },
                React.createElement('h3', null, 'PDF Customization'),
                
                // Company Logo
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'Company Logo'),
                    React.createElement('div', { className: 'logo-input-group' },
                        React.createElement('input', {
                            type: 'url',
                            value: settings.logo,
                            onChange: (e) => handleInputChange('logo', e.target.value),
                            placeholder: 'https://example.com/logo.png'
                        }),
                        React.createElement('button', {
                            type: 'button',
                            className: 'button',
                            onClick: openMediaLibrary
                        }, 'Upload Logo')
                    ),
                    settings.logo && React.createElement('div', { className: 'logo-preview' },
                        React.createElement('img', { 
                            src: settings.logo, 
                            alt: 'Logo Preview',
                            style: { maxWidth: '200px', maxHeight: '100px' }
                        })
                    ),
                    React.createElement('p', { className: 'description' }, 
                        'Enter the URL of your company logo to include in PDF confirmations.'
                    )
                ),

                // Header Text
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'Header Text'),
                    React.createElement('input', {
                        type: 'text',
                        value: settings.header_text,
                        onChange: (e) => handleInputChange('header_text', e.target.value),
                        placeholder: 'Booking Confirmation'
                    }),
                    React.createElement('p', { className: 'description' }, 
                        'Main header text for the PDF confirmation.'
                    )
                ),

                // Footer Text
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'Footer Text'),
                    React.createElement('textarea', {
                        value: settings.footer_text,
                        onChange: (e) => handleInputChange('footer_text', e.target.value),
                        placeholder: 'Thank you for your booking!',
                        rows: 3
                    }),
                    React.createElement('p', { className: 'description' }, 
                        'Footer text for the PDF confirmation.'
                    )
                ),

                // Primary Color
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'Primary Color'),
                    React.createElement('input', {
                        type: 'color',
                        value: settings.primary_color,
                        onChange: (e) => handleInputChange('primary_color', e.target.value)
                    }),
                    React.createElement('p', { className: 'description' }, 
                        'Primary color for PDF styling (headers, titles).'
                    )
                ),

                // Secondary Color
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'Secondary Color'),
                    React.createElement('input', {
                        type: 'color',
                        value: settings.secondary_color,
                        onChange: (e) => handleInputChange('secondary_color', e.target.value)
                    }),
                    React.createElement('p', { className: 'description' }, 
                        'Secondary color for PDF styling (section headers).'
                    )
                ),

                // PDF Template
                React.createElement('div', { className: 'form-field' },
                    React.createElement('label', null, 'PDF Template'),
                    React.createElement('select', {
                        value: settings.template,
                        onChange: (e) => handleInputChange('template', e.target.value)
                    },
                        React.createElement('option', { value: 'default' }, 'Default Template'),
                        React.createElement('option', { value: 'minimal' }, 'Minimal Template'),
                        React.createElement('option', { value: 'professional' }, 'Professional Template')
                    ),
                    React.createElement('p', { className: 'description' }, 
                        'Choose the PDF template style.'
                    )
                )
            ),

            // Save Button
            React.createElement('div', { className: 'form-actions' },
                React.createElement('button', {
                    type: 'button',
                    className: 'button button-primary',
                    onClick: saveSettings,
                    disabled: saving
                }, saving ? 'Saving...' : 'Save Settings')
            )
        );
    });

    // Add styles
    const styles = `
        .pdf-settings-container {
            max-width: 800px;
            padding: 20px;
        }
        .pdf-settings-header {
            margin-bottom: 30px;
        }
        .pdf-settings-header h2 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        .settings-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            margin: 20px 0;
            padding: 20px;
            border-radius: 4px;
        }
        .settings-section h3 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-field {
            margin-bottom: 20px;
        }
        .form-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #23282d;
        }
        .form-field input[type="text"],
        .form-field input[type="url"],
        .form-field textarea,
        .form-field select {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-field input[type="color"] {
            width: 60px;
            height: 40px;
            padding: 2px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-field textarea {
            min-height: 80px;
            resize: vertical;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: normal;
        }
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        .description {
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        .logo-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .logo-input-group input {
            flex: 1;
        }
        .logo-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            text-align: center;
        }
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .notice {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .notice-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .notice-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    `;

    // Inject styles
    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForTimetics);
    } else {
        waitForTimetics();
    }

})();
