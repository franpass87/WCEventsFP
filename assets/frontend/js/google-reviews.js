/**
 * WCEventsFP Google Reviews JavaScript
 * 
 * Handles dynamic loading and interactions for Google Reviews display
 */
(function($) {
    'use strict';
    
    let wcefpGoogleReviews = {
        
        init: function() {
            this.bindEvents();
            this.initLazyLoading();
        },
        
        bindEvents: function() {
            // Handle review image loading errors
            $(document).on('error', '.wcefp-review-avatar img', function() {
                $(this).closest('.wcefp-review-avatar').hide();
            });
            
            // Handle gallery interactions in hero sections
            $(document).on('click', '.wcefp-gallery-thumb', function(e) {
                e.preventDefault();
                const fullImageUrl = $(this).find('img').data('full');
                const $mainImage = $(this).closest('.wcefp-hero-section').find('.wcefp-hero-main-image img');
                
                if (fullImageUrl && $mainImage.length) {
                    $mainImage.attr('src', fullImageUrl);
                    $(this).siblings().removeClass('active');
                    $(this).addClass('active');
                }
            });
            
            // Handle dynamic review loading
            $(document).on('click', '[data-load-reviews]', function(e) {
                e.preventDefault();
                wcefpGoogleReviews.loadReviewsAjax($(this));
            });
        },
        
        initLazyLoading: function() {
            // Lazy load reviews that are not immediately visible
            $('.wcefp-google-reviews-v2[data-lazy="true"]').each(function() {
                const $container = $(this);
                
                // Use Intersection Observer if available
                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(function(entries) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                wcefpGoogleReviews.loadVisibleReviews($container);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, {
                        rootMargin: '100px 0px'
                    });
                    
                    observer.observe($container[0]);
                } else {
                    // Fallback for older browsers
                    $(window).on('scroll', wcefpGoogleReviews.throttle(function() {
                        if (wcefpGoogleReviews.isInViewport($container)) {
                            wcefpGoogleReviews.loadVisibleReviews($container);
                        }
                    }, 250));
                }
            });
        },
        
        loadVisibleReviews: function($container) {
            const placeId = $container.data('place-id');
            const attributes = $container.data('attributes') || {};
            
            if (!placeId) {
                return;
            }
            
            $.ajax({
                url: wcefp_reviews.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_load_reviews',
                    place_id: placeId,
                    atts: attributes,
                    nonce: wcefp_reviews.nonce
                },
                beforeSend: function() {
                    $container.addClass('wcefp-loading');
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $container.html(response.data.html);
                    }
                },
                error: function() {
                    $container.addClass('wcefp-error');
                },
                complete: function() {
                    $container.removeClass('wcefp-loading');
                }
            });
        },
        
        loadReviewsAjax: function($trigger) {
            const placeId = $trigger.data('place-id');
            const targetSelector = $trigger.data('target');
            const $target = $(targetSelector);
            
            if (!placeId || !$target.length) {
                return;
            }
            
            $.ajax({
                url: wcefp_reviews.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_load_reviews',
                    place_id: placeId,
                    atts: $trigger.data('attributes') || {},
                    nonce: wcefp_reviews.nonce
                },
                beforeSend: function() {
                    $trigger.prop('disabled', true);
                    $target.addClass('wcefp-loading');
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $target.html(response.data.html);
                        $trigger.hide(); // Hide load button after successful load
                    }
                },
                error: function() {
                    $target.html('<div class="wcefp-reviews-error">' + 
                               'Unable to load reviews at this time.' + 
                               '</div>');
                },
                complete: function() {
                    $trigger.prop('disabled', false);
                    $target.removeClass('wcefp-loading');
                }
            });
        },
        
        isInViewport: function($element) {
            const elementTop = $element.offset().top;
            const elementBottom = elementTop + $element.outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            
            return elementBottom > viewportTop && elementTop < viewportBottom;
        },
        
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        // Accessibility helpers
        announceToScreenReader: function(message) {
            if (!$('#wcefp-reviews-live-region').length) {
                $('body').append('<div id="wcefp-reviews-live-region" aria-live="polite" aria-atomic="true" class="wcefp-sr-only"></div>');
            }
            $('#wcefp-reviews-live-region').text(message);
        },
        
        enhanceAccessibility: function() {
            // Add ARIA labels to star ratings
            $('.wcefp-stars').each(function() {
                const $stars = $(this);
                const rating = $stars.closest('[data-rating]').data('rating') || 
                             $stars.siblings('[data-rating]').data('rating');
                
                if (rating) {
                    $stars.attr('aria-label', rating + ' out of 5 stars');
                }
            });
            
            // Improve keyboard navigation for review items
            $('.wcefp-review-item').each(function() {
                const $item = $(this);
                if (!$item.attr('tabindex')) {
                    $item.attr('tabindex', '0');
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        wcefpGoogleReviews.init();
        wcefpGoogleReviews.enhanceAccessibility();
    });
    
    // Reinitialize when new content is loaded
    $(document).on('wcefp:content-updated', function() {
        wcefpGoogleReviews.enhanceAccessibility();
    });
    
    // Expose to global scope for external access
    window.wcefpGoogleReviews = wcefpGoogleReviews;
    
})(jQuery);

// Add loading styles dynamically
if (!$('#wcefp-reviews-dynamic-styles').length) {
    $('head').append(`
        <style id="wcefp-reviews-dynamic-styles">
            .wcefp-google-reviews-v2.wcefp-loading {
                position: relative;
                opacity: 0.7;
                pointer-events: none;
            }
            
            .wcefp-google-reviews-v2.wcefp-loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 32px;
                height: 32px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #007cba;
                border-radius: 50%;
                animation: wcefp-reviews-spin 1s linear infinite;
            }
            
            @keyframes wcefp-reviews-spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
            
            .wcefp-reviews-error {
                text-align: center;
                color: #721c24;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 1rem;
                margin: 1rem 0;
            }
            
            .wcefp-gallery-thumb.active {
                border: 2px solid #007cba;
                border-radius: 4px;
            }
            
            .wcefp-gallery-thumb {
                transition: all 0.2s ease;
            }
            
            .wcefp-gallery-thumb:hover {
                transform: translateY(-2px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }
        </style>
    `);
}