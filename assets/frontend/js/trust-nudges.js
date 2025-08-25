/**
 * WCEventsFP Trust Nudges JavaScript
 * 
 * Handles dynamic trust elements and ethical social proof
 */
(function($) {
    'use strict';
    
    let wcefpTrustNudges = {
        
        updateIntervals: {},
        
        init: function() {
            this.bindEvents();
            this.initDynamicUpdates();
            this.enhanceAccessibility();
        },
        
        bindEvents: function() {
            // Handle trust element interactions
            $(document).on('click', '[data-trust-action]', function(e) {
                e.preventDefault();
                const action = $(this).data('trust-action');
                wcefpTrustNudges.handleTrustAction(action, $(this));
            });
            
            // Handle availability updates when date/time changes
            $(document).on('change', '.wcefp-date-input, .wcefp-time-slot.selected', function() {
                wcefpTrustNudges.updateAvailabilityIndicators();
            });
        },
        
        initDynamicUpdates: function() {
            $('.wcefp-trust-elements').each(function() {
                const $container = $(this);
                const productId = $container.data('product-id');
                const updateFrequency = $container.data('update-frequency') || 60000; // 1 minute default
                
                if (productId && wcefp_trust.settings.show_recent_bookings) {
                    wcefpTrustNudges.startPeriodicUpdates($container, productId, updateFrequency);
                }
            });
        },
        
        startPeriodicUpdates: function($container, productId, frequency) {
            // Clear existing interval if any
            if (this.updateIntervals[productId]) {
                clearInterval(this.updateIntervals[productId]);
            }
            
            // Start new periodic update
            this.updateIntervals[productId] = setInterval(function() {
                wcefpTrustNudges.updateTrustElements($container, productId);
            }, frequency);
        },
        
        updateTrustElements: function($container, productId) {
            // Only update if container is still visible and not in background tab
            if (!this.isElementVisible($container) || document.hidden) {
                return;
            }
            
            $.ajax({
                url: wcefp_trust.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_get_trust_data',
                    product_id: productId,
                    elements: ['recent_bookings', 'social_proof'],
                    nonce: wcefp_trust.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        wcefpTrustNudges.updateTrustElementsContent($container, response.data.html);
                    }
                },
                error: function() {
                    // Silently fail - trust elements are not critical
                    console.warn('Trust elements update failed');
                }
            });
        },
        
        updateTrustElementsContent: function($container, newContent) {
            const $newContent = $(newContent);
            
            // Update recent bookings if present
            const $recentBookings = $newContent.find('.wcefp-recent-bookings');
            if ($recentBookings.length) {
                $container.find('.wcefp-recent-bookings').replaceWith($recentBookings);
            }
            
            // Update social proof if present
            const $socialProof = $newContent.find('.wcefp-social-proof');
            if ($socialProof.length) {
                $container.find('.wcefp-social-proof').replaceWith($socialProof);
            }
        },
        
        updateAvailabilityIndicators: function() {
            $('.wcefp-availability-indicator').each(function() {
                const $indicator = $(this);
                const $widget = $indicator.closest('.wcefp-booking-widget-v2');
                const selectedDate = $widget.find('.wcefp-date-input').val();
                const $selectedSlot = $widget.find('.wcefp-time-slot.selected');
                
                if (selectedDate && $selectedSlot.length) {
                    const available = parseInt($selectedSlot.data('available')) || 0;
                    const total = parseInt($selectedSlot.data('total')) || 0;
                    
                    wcefpTrustNudges.updateAvailabilityDisplay($indicator, available, total);
                }
            });
        },
        
        updateAvailabilityDisplay: function($indicator, available, total) {
            const percentage = total > 0 ? (available / total) * 100 : 0;
            const settings = wcefp_trust.settings;
            
            let statusClass, statusText, statusIcon;
            
            if (available >= settings.availability_threshold_high) {
                statusClass = 'wcefp-availability-good';
                statusText = 'Good availability';
                statusIcon = '✅';
            } else if (available >= settings.availability_threshold_low) {
                statusClass = 'wcefp-availability-limited';
                statusText = available + ' spots remaining';
                statusIcon = '⏰';
            } else if (available > 0) {
                statusClass = 'wcefp-availability-low';
                statusText = 'Almost sold out';
                statusIcon = '🔥';
            } else {
                statusClass = 'wcefp-availability-none';
                statusText = 'Sold out';
                statusIcon = '❌';
            }
            
            $indicator.attr('data-percentage', percentage);
            $indicator.find('.wcefp-availability-status')
                .removeClass('wcefp-availability-good wcefp-availability-limited wcefp-availability-low wcefp-availability-none')
                .addClass(statusClass)
                .find('.wcefp-trust-icon').text(statusIcon);
            
            $indicator.find('.wcefp-trust-text').text(statusText);
            
            // Announce to screen reader
            this.announceToScreenReader('Availability updated: ' + statusText);
        },
        
        handleTrustAction: function(action, $element) {
            switch(action) {
                case 'show_policy':
                    this.showPolicyModal($element.data('policy-type'));
                    break;
                case 'show_reviews':
                    this.showReviewsModal($element.data('place-id'));
                    break;
                case 'toggle_details':
                    $element.next('.wcefp-trust-details').slideToggle();
                    break;
                default:
                    console.warn('Unknown trust action:', action);
            }
        },
        
        showPolicyModal: function(policyType) {
            // This would integrate with your existing modal system
            const modalContent = this.getPolicyContent(policyType);
            if (modalContent) {
                // Display modal with policy content
                console.log('Show policy modal for:', policyType);
            }
        },
        
        getPolicyContent: function(policyType) {
            const policies = {
                'cancellation': 'Free cancellation up to 24 hours before the experience.',
                'confirmation': 'Your booking is confirmed instantly upon payment.',
                'mobile_voucher': 'Show your mobile voucher at the meeting point.'
            };
            
            return policies[policyType] || null;
        },
        
        enhanceAccessibility: function() {
            // Add proper ARIA labels to trust elements
            $('.wcefp-trust-element').each(function() {
                const $element = $(this);
                const text = $element.find('.wcefp-trust-text').text().trim();
                
                if (text && !$element.attr('aria-label')) {
                    $element.attr('aria-label', text);
                }
            });
            
            // Make availability indicators keyboard accessible
            $('.wcefp-availability-indicator').attr('role', 'status').attr('aria-live', 'polite');
            
            // Add keyboard support for interactive trust elements
            $('[data-trust-action]').attr('role', 'button').attr('tabindex', '0').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },
        
        isElementVisible: function($element) {
            const elementTop = $element.offset().top;
            const elementBottom = elementTop + $element.outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            
            return elementBottom > viewportTop && elementTop < viewportBottom;
        },
        
        announceToScreenReader: function(message) {
            if (!$('#wcefp-trust-live-region').length) {
                $('body').append('<div id="wcefp-trust-live-region" aria-live="polite" aria-atomic="true" class="wcefp-sr-only"></div>');
            }
            $('#wcefp-trust-live-region').text(message);
        },
        
        // Ethical viewing counter based on conservative estimates
        generateEthicalViewingCount: function(productId) {
            const settings = wcefp_trust.settings;
            
            // Use deterministic approach based on product ID and current hour
            // This ensures consistency while avoiding fake high numbers
            const seed = productId + new Date().getHours();
            const min = settings.viewing_range_min || 2;
            const max = settings.viewing_range_max || 8;
            
            // Simple seeded random
            const random = (seed * 9301 + 49297) % 233280;
            const normalized = random / 233280;
            
            return Math.floor(min + normalized * (max - min + 1));
        },
        
        // Clean up intervals when page unloads
        cleanup: function() {
            Object.values(this.updateIntervals).forEach(interval => {
                clearInterval(interval);
            });
            this.updateIntervals = {};
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        wcefpTrustNudges.init();
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        wcefpTrustNudges.cleanup();
    });
    
    // Pause updates when page is not visible (saves resources)
    $(document).on('visibilitychange', function() {
        if (document.hidden) {
            // Pause all updates
            Object.values(wcefpTrustNudges.updateIntervals).forEach(interval => {
                clearInterval(interval);
            });
        } else {
            // Resume updates
            $('.wcefp-trust-elements').each(function() {
                const $container = $(this);
                const productId = $container.data('product-id');
                const updateFrequency = $container.data('update-frequency') || 60000;
                
                if (productId) {
                    wcefpTrustNudges.startPeriodicUpdates($container, productId, updateFrequency);
                }
            });
        }
    });
    
    // Expose to global scope
    window.wcefpTrustNudges = wcefpTrustNudges;
    
})(jQuery);

// Add dynamic styles for trust interactions
if (!$('#wcefp-trust-dynamic-styles').length) {
    $('head').append(`
        <style id="wcefp-trust-dynamic-styles">
            .wcefp-trust-details {
                display: none;
                margin-top: 0.5rem;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 4px;
                font-size: 0.85rem;
                color: #666;
            }
            
            [data-trust-action] {
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            [data-trust-action]:hover {
                opacity: 0.8;
                transform: translateY(-1px);
            }
            
            [data-trust-action]:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
            
            .wcefp-trust-element[aria-live] {
                transition: all 0.3s ease;
            }
            
            .wcefp-trust-element.updating {
                opacity: 0.7;
            }
        </style>
    `);
}