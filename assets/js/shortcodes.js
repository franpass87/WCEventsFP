/**
 * WCEventsFP Shortcodes JavaScript
 * 
 * @package WCEFP
 * @since 2.1.4
 */

(function($) {
    'use strict';
    
    // Shortcodes manager object
    const WCEFPShortcodes = {
        
        init() {
            this.initBookingForms();
            this.initSearchForms();
            this.initEventGalleries();
            this.initPriceCalculators();
            this.initViewToggle();
            this.initFilterToggles();
            this.initLoadMore();
            this.bindGlobalEvents();
        },
        
        // Initialize booking forms
        initBookingForms() {
            $('.wcefp-booking-form').each(function() {
                const $form = $(this);
                const $dateField = $form.find('#wcefp_booking_date');
                const $timeField = $form.find('#wcefp_booking_time');
                const $participantsField = $form.find('#wcefp_participants');
                const $customerInfo = $form.find('.wcefp-customer-info');
                const $totalPrice = $form.find('.wcefp-total-price');
                const eventId = $form.data('event-id');
                
                // Show customer info when date and participants are selected
                function checkFormCompletion() {
                    if ($dateField.val() && $participantsField.val()) {
                        $customerInfo.slideDown();
                        updateTotalPrice();
                    } else {
                        $customerInfo.slideUp();
                    }
                }
                
                // Update total price calculation
                function updateTotalPrice() {
                    const participants = parseInt($participantsField.val()) || 1;
                    const basePrice = parseFloat($form.find('[data-base-price]').data('base-price')) || 0;
                    const total = basePrice * participants;
                    
                    if ($totalPrice.length) {
                        $totalPrice.html(WCEFPShortcodes.formatPrice(total));
                    }
                }
                
                // Bind events
                $dateField.on('change', function() {
                    WCEFPShortcodes.loadAvailableTimeSlots($(this).val(), eventId, $timeField);
                    checkFormCompletion();
                });
                
                $participantsField.on('change', function() {
                    checkFormCompletion();
                    updateTotalPrice();
                });
                
                // Form submission
                $form.on('submit', function(e) {
                    e.preventDefault();
                    WCEFPShortcodes.handleBookingSubmission($form);
                });
            });
        },
        
        // Initialize search forms
        initSearchForms() {
            $('.wcefp-search-form-inner').on('submit', function(e) {
                e.preventDefault();
                WCEFPShortcodes.performSearch($(this));
            });
            
            // Real-time search (debounced)
            $('.wcefp-search-input').on('input', WCEFPShortcodes.debounce(function() {
                const $form = $(this).closest('.wcefp-search-form-inner');
                WCEFPShortcodes.performSearch($form, true);
            }, 500));
            
            // Category and filter changes
            $('.wcefp-category-select, .wcefp-sort-select').on('change', function() {
                const $form = $(this).closest('.wcefp-search-form-inner');
                WCEFPShortcodes.performSearch($form);
            });
        },
        
        // Initialize event galleries
        initEventGalleries() {
            $('.wcefp-event-gallery-slider').each(function() {
                const $gallery = $(this);
                const $images = $gallery.find('img');
                
                if ($images.length > 1) {
                    // Simple gallery navigation
                    let currentIndex = 0;
                    
                    $images.hide().first().show();
                    
                    // Add navigation buttons
                    const $nav = $('<div class="wcefp-gallery-nav">' +
                        '<button class="wcefp-gallery-prev">‹</button>' +
                        '<span class="wcefp-gallery-counter">1 / ' + $images.length + '</span>' +
                        '<button class="wcefp-gallery-next">›</button>' +
                        '</div>');
                    
                    $gallery.append($nav);
                    
                    // Navigation functionality
                    $nav.on('click', '.wcefp-gallery-prev', function() {
                        currentIndex = currentIndex > 0 ? currentIndex - 1 : $images.length - 1;
                        WCEFPShortcodes.showGalleryImage($images, currentIndex, $nav);
                    });
                    
                    $nav.on('click', '.wcefp-gallery-next', function() {
                        currentIndex = currentIndex < $images.length - 1 ? currentIndex + 1 : 0;
                        WCEFPShortcodes.showGalleryImage($images, currentIndex, $nav);
                    });
                    
                    // Touch/swipe support
                    let startX = 0;
                    $gallery.on('touchstart', function(e) {
                        startX = e.originalEvent.touches[0].clientX;
                    });
                    
                    $gallery.on('touchend', function(e) {
                        const endX = e.originalEvent.changedTouches[0].clientX;
                        const diff = startX - endX;
                        
                        if (Math.abs(diff) > 50) { // Minimum swipe distance
                            if (diff > 0) {
                                $nav.find('.wcefp-gallery-next').click();
                            } else {
                                $nav.find('.wcefp-gallery-prev').click();
                            }
                        }
                    });
                }
            });
        },
        
        // Initialize view toggle
        initViewToggle() {
            $('.wcefp-view-btn').on('click', function() {
                const $btn = $(this);
                const view = $btn.data('view');
                const $container = $btn.closest('.wcefp-search-events').find('.wcefp-results-container');
                
                // Update button states
                $btn.addClass('active').siblings().removeClass('active');
                
                // Update container class
                $container.removeClass('wcefp-view-grid wcefp-view-list').addClass('wcefp-view-' + view);
                
                // Re-render results with new view
                WCEFPShortcodes.performSearch($container.closest('.wcefp-search-events').find('.wcefp-search-form-inner'));
            });
        },
        
        // Initialize filter toggles
        initFilterToggles() {
            $('.wcefp-toggle-filters').on('click', function() {
                const $btn = $(this);
                const $filters = $btn.prev('.wcefp-search-filters');
                
                $filters.slideToggle();
                
                $btn.text(function(i, text) {
                    return text === wcefp_shortcodes.strings.more_filters ? 
                           wcefp_shortcodes.strings.less_filters : 
                           wcefp_shortcodes.strings.more_filters;
                });
            });
        },
        
        // Initialize load more functionality
        initLoadMore() {
            $(document).on('click', '.wcefp-load-more', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const page = parseInt($btn.data('page')) + 1;
                const $container = $btn.closest('.wcefp-search-results').find('.wcefp-results-container');
                
                $btn.prop('disabled', true).text(wcefp_shortcodes.strings.loading);
                
                $.ajax({
                    url: wcefp_shortcodes.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcefp_load_more_events',
                        page: page,
                        nonce: wcefp_shortcodes.nonce,
                        ...WCEFPShortcodes.getSearchParams($btn.closest('.wcefp-search-events'))
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            $container.append(response.data.html);
                            
                            if (response.data.has_more) {
                                $btn.data('page', page).prop('disabled', false).text(wcefp_shortcodes.strings.load_more);
                            } else {
                                $btn.remove();
                            }
                        } else {
                            WCEFPShortcodes.showError($container, wcefp_shortcodes.strings.error);
                        }
                    },
                    error: function() {
                        WCEFPShortcodes.showError($container, wcefp_shortcodes.strings.error);
                        $btn.prop('disabled', false).text(wcefp_shortcodes.strings.load_more);
                    }
                });
            });
        },
        
        // Bind global events
        bindGlobalEvents() {
            // Handle dynamic content loading
            $(document).ajaxComplete(function() {
                WCEFPShortcodes.initBookingForms();
                WCEFPShortcodes.initEventGalleries();
            });
            
            // Handle responsive view changes
            $(window).on('resize', WCEFPShortcodes.debounce(function() {
                WCEFPShortcodes.handleResponsiveChanges();
            }, 250));
        },
        
        // Load available time slots for date
        loadAvailableTimeSlots(date, eventId, $timeField) {
            if (!date || !eventId) return;
            
            $timeField.prop('disabled', true).html('<option>' + wcefp_shortcodes.strings.loading + '</option>');
            
            $.ajax({
                url: wcefp_shortcodes.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcefp_get_time_slots',
                    date: date,
                    event_id: eventId,
                    nonce: wcefp_shortcodes.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $timeField.html('<option value="">' + wcefp_shortcodes.strings.select_time + '</option>' + response.data.options);
                        
                        // Show availability info
                        const $availabilityInfo = $timeField.closest('.wcefp-form-field').find('.wcefp-availability-info');
                        if (response.data.availability_info) {
                            $availabilityInfo.html(response.data.availability_info).show();
                        }
                    } else {
                        $timeField.html('<option>' + wcefp_shortcodes.strings.no_slots + '</option>');
                    }
                    $timeField.prop('disabled', false);
                },
                error: function() {
                    $timeField.html('<option>' + wcefp_shortcodes.strings.error + '</option>').prop('disabled', false);
                }
            });
        },
        
        // Handle booking form submission
        handleBookingSubmission($form) {
            const $submitBtn = $form.find('.wcefp-book-now-btn');
            const $btnText = $submitBtn.find('.wcefp-btn-text');
            const $btnLoader = $submitBtn.find('.wcefp-btn-loader');
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();
            
            const formData = new FormData($form[0]);
            formData.append('action', 'wcefp_process_booking');
            formData.append('nonce', wcefp_shortcodes.nonce);
            
            $.ajax({
                url: wcefp_shortcodes.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Redirect to checkout or show success message
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            WCEFPShortcodes.showSuccess($form, response.data.message || wcefp_shortcodes.strings.booking_success);
                        }
                    } else {
                        WCEFPShortcodes.showError($form, response.data.message || wcefp_shortcodes.strings.booking_error);
                    }
                },
                error: function() {
                    WCEFPShortcodes.showError($form, wcefp_shortcodes.strings.error);
                },
                complete: function() {
                    // Reset loading state
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnLoader.hide();
                }
            });
        },
        
        // Perform event search
        performSearch($form, isRealtime = false) {
            const $container = $form.closest('.wcefp-search-events').find('.wcefp-results-container');
            const searchParams = WCEFPShortcodes.getSearchParams($form.closest('.wcefp-search-events'));
            
            if (!isRealtime) {
                $container.html('<div class="wcefp-loading">' + wcefp_shortcodes.strings.loading + '</div>');
            }
            
            $.ajax({
                url: wcefp_shortcodes.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcefp_search_events',
                    nonce: wcefp_shortcodes.nonce,
                    ...searchParams
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                        
                        // Update results count
                        const $resultsCount = $form.closest('.wcefp-search-events').find('.wcefp-results-count');
                        if (response.data.count !== undefined) {
                            $resultsCount.text(response.data.count + ' ' + wcefp_shortcodes.strings.events_found);
                        }
                        
                        // Add load more button if needed
                        if (response.data.has_more) {
                            $container.append('<div class="wcefp-load-more-container">' +
                                '<button class="wcefp-btn wcefp-load-more" data-page="1">' +
                                wcefp_shortcodes.strings.load_more +
                                '</button></div>');
                        }
                    } else {
                        WCEFPShortcodes.showError($container, response.data.message || wcefp_shortcodes.strings.no_events);
                    }
                },
                error: function() {
                    WCEFPShortcodes.showError($container, wcefp_shortcodes.strings.error);
                }
            });
        },
        
        // Get search parameters from form
        getSearchParams($searchContainer) {
            const $form = $searchContainer.find('.wcefp-search-form-inner');
            const params = {};
            
            $form.find('input, select').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const value = $field.val();
                
                if (name && value) {
                    params[name] = value;
                }
            });
            
            // Get view type
            const view = $searchContainer.find('.wcefp-view-btn.active').data('view') || 'grid';
            params.view = view;
            
            return params;
        },
        
        // Show gallery image
        showGalleryImage($images, index, $nav) {
            $images.hide().eq(index).fadeIn(300);
            $nav.find('.wcefp-gallery-counter').text((index + 1) + ' / ' + $images.length);
        },
        
        // Handle responsive changes
        handleResponsiveChanges() {
            // Adjust grid layouts if needed
            $('.wcefp-events-grid').each(function() {
                const $grid = $(this);
                // Could implement adaptive column count based on container width
            });
        },
        
        // Utility functions
        formatPrice(amount) {
            return new Intl.NumberFormat(document.documentElement.lang || 'en', {
                style: 'currency',
                currency: wcefp_shortcodes.currency || 'USD'
            }).format(amount);
        },
        
        showError($container, message) {
            $container.html('<div class="wcefp-error">' + message + '</div>');
        },
        
        showSuccess($container, message) {
            $container.html('<div class="wcefp-success" style="background:#d4edda;color:#155724;padding:15px;border-radius:4px;margin:10px 0;">' + message + '</div>');
        },
        
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WCEFPShortcodes.init();
    });
    
    // Expose globally for extensions
    window.WCEFPShortcodes = WCEFPShortcodes;
    
})(jQuery);