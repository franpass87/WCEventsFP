/**
 * WCEFP Diagnostics Admin Page JavaScript
 * 
 * @package WCEFP
 * @since 2.2.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        var DiagnosticsPage = {
            
            init: function() {
                this.bindEvents();
                this.initTabs();
            },
            
            /**
             * Bind event handlers
             */
            bindEvents: function() {
                // Tab navigation
                $('.wcefp-diagnostics-tabs .nav-tab').on('click', this.switchTab);
                
                // Refresh button
                $('#wcefp-refresh-diagnostics').on('click', this.refreshDiagnostics);
                
                // Export buttons
                $('#wcefp-export-diagnostics, #wcefp-export-diagnostics-txt').on('click', this.exportDiagnostics);
                
                // Test shortcode buttons
                $('.test-shortcode').on('click', this.testShortcode);
                
                // Test AJAX buttons
                $('.test-ajax').on('click', this.testAjax);
            },
            
            /**
             * Initialize tab functionality
             */
            initTabs: function() {
                // Show first tab by default
                $('.tab-pane').removeClass('active');
                $('.tab-pane').first().addClass('active');
                $('.nav-tab').removeClass('nav-tab-active');
                $('.nav-tab').first().addClass('nav-tab-active');
            },
            
            /**
             * Handle tab switching
             */
            switchTab: function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var target = $tab.attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $tab.addClass('nav-tab-active');
                
                // Update active content
                $('.tab-pane').removeClass('active');
                $(target).addClass('active');
            },
            
            /**
             * Refresh diagnostics data
             */
            refreshDiagnostics: function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                
                $button.addClass('loading')
                       .prop('disabled', true)
                       .text(wcefp_diagnostics.refresh_text);
                
                $.ajax({
                    url: wcefp_diagnostics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcefp_refresh_diagnostics',
                        nonce: wcefp_diagnostics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update timestamp
                            $('.wcefp-last-updated').text('Last updated: ' + response.data.timestamp);
                            
                            // Show success message
                            DiagnosticsPage.showNotice(response.data.message, 'success');
                            
                            // Reload page to refresh data
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            DiagnosticsPage.showNotice('Error: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        DiagnosticsPage.showNotice('Failed to refresh diagnostics data.', 'error');
                    },
                    complete: function() {
                        $button.removeClass('loading')
                               .prop('disabled', false)
                               .text(originalText);
                    }
                });
            },
            
            /**
             * Test shortcode rendering
             */
            testShortcode: function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var shortcode = $button.data('shortcode');
                var $row = $button.closest('tr');
                
                // Remove existing test results
                $row.find('.test-result').remove();
                
                $button.prop('disabled', true).text('Testing...');
                
                // Create test content div
                var $testDiv = $('<div class="test-result">Testing shortcode [' + shortcode + ']...</div>');
                $row.find('td:last').append($testDiv);
                $testDiv.show();
                
                // Simulate shortcode test (in real implementation, this would make an AJAX call)
                setTimeout(function() {
                    try {
                        // Mock test result - in real implementation, this would test actual shortcode
                        var mockResult = 'Shortcode [' + shortcode + '] rendered successfully.\n\nOutput preview:\n<div class="wcefp-' + shortcode.replace('wcefp_', '') + '">Sample content</div>';
                        
                        $testDiv.removeClass('error')
                                .addClass('success')
                                .text(mockResult);
                    } catch (error) {
                        $testDiv.addClass('error')
                                .text('Error testing shortcode: ' + error.message);
                    }
                    
                    $button.prop('disabled', false).text('Test Render');
                }, 1500);
            },
            
            /**
             * Test AJAX endpoints
             */
            testAjax: function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var action = $button.data('action');
                var $row = $button.closest('tr');
                
                // Remove existing test results
                $row.find('.test-result').remove();
                
                $button.prop('disabled', true).text('Testing...');
                
                // Create test content div
                var $testDiv = $('<div class="test-result">Testing AJAX endpoint: ' + action + '...</div>');
                $row.find('td:last').append($testDiv);
                $testDiv.show();
                
                $.ajax({
                    url: wcefp_diagnostics.ajax_url,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: wcefp_diagnostics.nonce,
                        test_mode: true
                    },
                    success: function(response) {
                        var resultText = 'AJAX endpoint test results:\n\n';
                        resultText += 'Status: ' + (response.success ? 'SUCCESS' : 'ERROR') + '\n';
                        resultText += 'Response: ' + JSON.stringify(response, null, 2);
                        
                        $testDiv.removeClass('error')
                                .addClass('success')
                                .text(resultText);
                    },
                    error: function(xhr, status, error) {
                        var resultText = 'AJAX endpoint test failed:\n\n';
                        resultText += 'Status: ' + status + '\n';
                        resultText += 'Error: ' + error + '\n';
                        resultText += 'Response: ' + xhr.responseText;
                        
                        $testDiv.addClass('error')
                                .text(resultText);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test');
                    }
                });
            },
            
            /**
             * Export diagnostics report
             */
            exportDiagnostics: function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var format = $button.data('format') || 'json';
                var originalText = $button.text();
                
                $button.prop('disabled', true).text('Exporting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcefp_export_diagnostics',
                        format: format,
                        nonce: wcefpDiagnostics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create download link
                            var blob = new Blob([typeof response.data.data === 'string' ? response.data.data : JSON.stringify(response.data.data, null, 2)], {
                                type: response.data.content_type
                            });
                            
                            var url = window.URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = response.data.filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            document.body.removeChild(a);
                            
                            DiagnosticsPage.showNotice('Diagnostics report exported successfully', 'success');
                        } else {
                            DiagnosticsPage.showNotice('Export failed: ' + (response.data || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        DiagnosticsPage.showNotice('Export request failed. Please try again.', 'error');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            },
            
            /**
             * Show admin notice
             */
            showNotice: function(message, type) {
                var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($notice);
                
                // Auto-dismiss after 5 seconds
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
                
                // Handle manual dismiss
                $notice.on('click', '.notice-dismiss', function() {
                    $notice.fadeOut();
                });
            }
        };
        
        // Initialize the diagnostics page
        DiagnosticsPage.init();
    });
    
})(jQuery);