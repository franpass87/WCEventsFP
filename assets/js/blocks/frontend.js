/**
 * Gutenberg Blocks Frontend
 * 
 * Frontend JavaScript for WCEventsFP Gutenberg blocks.
 * Part of Phase 3: Data & Integration
 */
(function($) {
    'use strict';
    
    const WCEFPBlocks = {
        
        init() {
            this.initBookingForms();
            this.initEventLists();
        },
        
        initBookingForms() {
            $('.wcefp-booking-form-block').each(function() {
                const $block = $(this);
                WCEFPBlocks.enhanceBookingForm($block);
            });
        },
        
        initEventLists() {
            $('.wcefp-event-list-block').each(function() {
                const $block = $(this);
                WCEFPBlocks.enhanceEventList($block);
            });
        },
        
        enhanceBookingForm($block) {
            // Add smooth animations and enhanced interactions
            $block.find('.wcefp-booking-form').addClass('wcefp-enhanced-form');
            
            // Handle form submissions with modals
            $block.find('form').on('submit', function(e) {
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
                
                // Show loading state
                $submitBtn.prop('disabled', true).addClass('wcefp-loading');
                
                // Add loading spinner if not already present
                if (!$submitBtn.find('.wcefp-spinner').length) {
                    $submitBtn.append('<span class="wcefp-spinner"></span>');
                }
            });
            
            // Add focus management for accessibility
            $block.find('input, select, textarea').on('focus', function() {
                $(this).closest('.wcefp-form-field').addClass('wcefp-field-focused');
            }).on('blur', function() {
                $(this).closest('.wcefp-form-field').removeClass('wcefp-field-focused');
            });
        },
        
        enhanceEventList($block) {
            // Add smooth hover effects
            $block.find('.wcefp-event-item').each(function() {
                const $item = $(this);
                
                $item.on('mouseenter', function() {
                    $(this).addClass('wcefp-item-hover');
                }).on('mouseleave', function() {
                    $(this).removeClass('wcefp-item-hover');
                });
            });
            
            // Handle booking button clicks
            $block.find('.wcefp-btn').on('click', function(e) {
                const $btn = $(this);
                
                // Add loading state if it's an AJAX action
                if ($btn.data('ajax-action')) {
                    e.preventDefault();
                    $btn.addClass('wcefp-loading').prop('disabled', true);
                    
                    // Perform AJAX action here if needed
                    setTimeout(() => {
                        $btn.removeClass('wcefp-loading').prop('disabled', false);
                    }, 1000);
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WCEFPBlocks.init();
    });
    
    // Reinitialize when new content is loaded (for dynamic content)
    $(document).on('wcefp:content-updated', function() {
        WCEFPBlocks.init();
    });
    
})(jQuery);