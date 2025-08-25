/**
 * WCEventsFP Single Experience Page JavaScript
 * Interactive functionality for experience detail pages
 * 
 * @package WCEFP
 * @since 2.2.0
 */

(function($) {
    'use strict';

    /**
     * Single Experience Page Class
     */
    var WCEFPSingle = {
        
        // Configuration
        config: {
            animationSpeed: 300,
            galleryOptions: {
                autoplay: false,
                loop: true,
                keyboard: true,
                arrows: true,
                dots: true
            }
        },
        
        // State
        state: {
            currentImageIndex: 0,
            isGalleryOpen: false,
            selectedDate: null,
            selectedTime: null,
            participants: 2
        },
        
        /**
         * Initialize single experience page functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeBookingForm();
            this.initializeGallery();
            this.initializeReviews();
            console.log('WCEventsFP Single Experience initialized');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Gallery interactions
            $(document).on('click', '.wcefp-gallery-trigger', function(e) {
                e.preventDefault();
                var productId = $(this).data('product-id');
                self.openGallery(productId);
            });
            
            $(document).on('click', '.wcefp-thumb', function() {
                var index = $(this).index();
                self.changeGalleryImage(index + 1); // +1 because main image is index 0
            });
            
            // Booking form interactions
            $(document).on('click', '.wcefp-qty-minus', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                self.decreaseQuantity(target);
            });
            
            $(document).on('click', '.wcefp-qty-plus', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                self.increaseQuantity(target);
            });
            
            $(document).on('change', '#wcefp-booking-date', function() {
                var date = $(this).val();
                self.updateAvailableSlots(date);
            });
            
            $(document).on('change', '#wcefp-booking-time', function() {
                var time = $(this).val();
                self.updatePricing();
            });
            
            $(document).on('submit', '.wcefp-booking-form', function(e) {
                if (!self.validateBookingForm()) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Review interactions
            $(document).on('click', '.wcefp-show-all-reviews', function(e) {
                e.preventDefault();
                self.loadAllReviews();
            });
            
            // Collapsible sections
            $(document).on('click', '.wcefp-section-toggle', function() {
                var $section = $(this).closest('.wcefp-section');
                var $content = $section.find('.wcefp-collapsible-content');
                
                $content.slideToggle(self.config.animationSpeed);
                $(this).toggleClass('expanded');
            });
            
            // Smooth scrolling for internal links
            $(document).on('click', 'a[href^="#wcefp-"]', function(e) {
                e.preventDefault();
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 500);
                }
            });
        },
        
        /**
         * Initialize booking form functionality
         */
        initializeBookingForm: function() {
            // Set minimum date to today
            var today = new Date().toISOString().split('T')[0];
            $('#wcefp-booking-date').attr('min', today);
            
            // Initialize form validation
            this.setupFormValidation();
            
            // Load initial pricing
            this.updatePricing();
        },
        
        /**
         * Setup form validation
         */
        setupFormValidation: function() {
            var $form = $('.wcefp-booking-form');
            var $dateField = $('#wcefp-booking-date');
            var $timeField = $('#wcefp-booking-time');
            var $submitBtn = $form.find('button[type="submit"]');
            
            function checkFormValidity() {
                var isValid = $dateField.val() && $timeField.val();
                $submitBtn.prop('disabled', !isValid);
                
                if (isValid) {
                    $submitBtn.removeClass('disabled').text(wcefp_single.strings.book_now);
                } else {
                    $submitBtn.addClass('disabled').text(wcefp_single.strings.select_date);
                }
            }
            
            $dateField.on('change', checkFormValidity);
            $timeField.on('change', checkFormValidity);
            
            // Initial check
            checkFormValidity();
        },
        
        /**
         * Validate booking form before submission
         */
        validateBookingForm: function() {
            var $form = $('.wcefp-booking-form');
            var isValid = true;
            var errorMessage = '';
            
            // Check required fields
            var date = $('#wcefp-booking-date').val();
            var time = $('#wcefp-booking-time').val();
            var participants = parseInt($('#wcefp-participants').val());
            
            if (!date) {
                isValid = false;
                errorMessage = wcefp_single.strings.select_date;
            } else if (!time) {
                isValid = false;
                errorMessage = wcefp_single.strings.select_time;
            } else if (!participants || participants < 1) {
                isValid = false;
                errorMessage = 'Please select number of participants';
            }
            
            if (!isValid) {
                this.showNotification(errorMessage, 'error');
                return false;
            }
            
            return true;
        },
        
        /**
         * Initialize gallery functionality
         */
        initializeGallery: function() {
            // Prepare gallery modal if it doesn't exist
            if (!$('#wcefp-gallery-modal').length) {
                this.createGalleryModal();
            }
            
            // Keyboard navigation
            $(document).on('keydown', this.handleGalleryKeyboard.bind(this));
        },
        
        /**
         * Create gallery modal
         */
        createGalleryModal: function() {
            var modalHtml = `
                <div id="wcefp-gallery-modal" class="wcefp-modal" style="display: none;">
                    <div class="wcefp-modal-overlay"></div>
                    <div class="wcefp-modal-content">
                        <button type="button" class="wcefp-modal-close">&times;</button>
                        <div class="wcefp-gallery-container">
                            <button type="button" class="wcefp-gallery-nav wcefp-gallery-prev">&larr;</button>
                            <div class="wcefp-gallery-image-container">
                                <img src="" alt="" class="wcefp-gallery-image">
                            </div>
                            <button type="button" class="wcefp-gallery-nav wcefp-gallery-next">&rarr;</button>
                        </div>
                        <div class="wcefp-gallery-thumbs-container"></div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Bind modal events
            $(document).on('click', '#wcefp-gallery-modal .wcefp-modal-close, #wcefp-gallery-modal .wcefp-modal-overlay', function() {
                WCEFPSingle.closeGallery();
            });
            
            $(document).on('click', '.wcefp-gallery-prev', function() {
                WCEFPSingle.previousGalleryImage();
            });
            
            $(document).on('click', '.wcefp-gallery-next', function() {
                WCEFPSingle.nextGalleryImage();
            });
        },
        
        /**
         * Open gallery modal
         */
        openGallery: function(productId) {
            var self = this;
            var $modal = $('#wcefp-gallery-modal');
            var $heroImage = $('.wcefp-hero-image');
            var $thumbs = $('.wcefp-thumb img');
            
            // Collect all images
            var images = [];
            
            // Add main image
            if ($heroImage.length) {
                images.push({
                    src: $heroImage.attr('src'),
                    alt: $heroImage.attr('alt')
                });
            }
            
            // Add thumbnail images
            $thumbs.each(function() {
                images.push({
                    src: $(this).attr('src'),
                    alt: $(this).attr('alt')
                });
            });
            
            if (images.length === 0) return;
            
            // Store images and show modal
            self.state.galleryImages = images;
            self.state.currentImageIndex = 0;
            self.state.isGalleryOpen = true;
            
            self.updateGalleryImage();
            self.createGalleryThumbs();
            
            $modal.fadeIn(this.config.animationSpeed);
            $('body').addClass('wcefp-modal-open');
        },
        
        /**
         * Close gallery modal
         */
        closeGallery: function() {
            var $modal = $('#wcefp-gallery-modal');
            
            this.state.isGalleryOpen = false;
            $modal.fadeOut(this.config.animationSpeed);
            $('body').removeClass('wcefp-modal-open');
        },
        
        /**
         * Update gallery image
         */
        updateGalleryImage: function() {
            var images = this.state.galleryImages;
            var index = this.state.currentImageIndex;
            
            if (!images || !images[index]) return;
            
            var $image = $('#wcefp-gallery-modal .wcefp-gallery-image');
            $image.attr('src', images[index].src);
            $image.attr('alt', images[index].alt);
            
            // Update thumb selection
            $('#wcefp-gallery-modal .wcefp-gallery-thumb').removeClass('active');
            $('#wcefp-gallery-modal .wcefp-gallery-thumb').eq(index).addClass('active');
        },
        
        /**
         * Create gallery thumbnails
         */
        createGalleryThumbs: function() {
            var images = this.state.galleryImages;
            var $container = $('#wcefp-gallery-modal .wcefp-gallery-thumbs-container');
            
            $container.empty();
            
            if (images.length <= 1) return;
            
            images.forEach(function(image, index) {
                var $thumb = $('<div class="wcefp-gallery-thumb"><img src="' + image.src + '" alt="' + image.alt + '"></div>');
                if (index === 0) $thumb.addClass('active');
                
                $thumb.on('click', function() {
                    WCEFPSingle.changeGalleryImage(index);
                });
                
                $container.append($thumb);
            });
        },
        
        /**
         * Change gallery image
         */
        changeGalleryImage: function(index) {
            var images = this.state.galleryImages;
            
            if (!images || index < 0 || index >= images.length) return;
            
            this.state.currentImageIndex = index;
            this.updateGalleryImage();
        },
        
        /**
         * Previous gallery image
         */
        previousGalleryImage: function() {
            var newIndex = this.state.currentImageIndex - 1;
            if (newIndex < 0) {
                newIndex = this.state.galleryImages.length - 1;
            }
            this.changeGalleryImage(newIndex);
        },
        
        /**
         * Next gallery image
         */
        nextGalleryImage: function() {
            var newIndex = this.state.currentImageIndex + 1;
            if (newIndex >= this.state.galleryImages.length) {
                newIndex = 0;
            }
            this.changeGalleryImage(newIndex);
        },
        
        /**
         * Handle gallery keyboard navigation
         */
        handleGalleryKeyboard: function(e) {
            if (!this.state.isGalleryOpen) return;
            
            switch (e.key) {
                case 'Escape':
                    this.closeGallery();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.previousGalleryImage();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.nextGalleryImage();
                    break;
            }
        },
        
        /**
         * Initialize reviews functionality
         */
        initializeReviews: function() {
            // Implement review loading and filtering if needed
        },
        
        /**
         * Load all reviews
         */
        loadAllReviews: function() {
            var $button = $('.wcefp-show-all-reviews');
            var $reviewsList = $('.wcefp-reviews-list');
            
            $button.prop('disabled', true).text(wcefp_single.strings.loading);
            
            // AJAX call to load all reviews
            $.ajax({
                url: wcefp_single.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcefp_load_all_reviews',
                    product_id: wcefp_single.product_id,
                    nonce: wcefp_single.nonce
                }
            })
            .done(function(response) {
                if (response.success && response.data.html) {
                    $reviewsList.html(response.data.html);
                    $button.hide();
                } else {
                    WCEFPSingle.showNotification('Error loading reviews', 'error');
                }
            })
            .fail(function() {
                WCEFPSingle.showNotification('Error loading reviews', 'error');
            })
            .always(function() {
                $button.prop('disabled', false).text('Show all reviews');
            });
        },
        
        /**
         * Update available time slots based on selected date
         */
        updateAvailableSlots: function(date) {
            if (!date) return;
            
            var $timeSelect = $('#wcefp-booking-time');
            var originalValue = $timeSelect.val();
            
            $timeSelect.prop('disabled', true);
            
            // AJAX call to get available slots
            $.ajax({
                url: wcefp_single.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcefp_get_available_slots',
                    product_id: wcefp_single.product_id,
                    date: date,
                    nonce: wcefp_single.nonce
                }
            })
            .done(function(response) {
                if (response.success && response.data.slots) {
                    var options = '<option value="">' + wcefp_single.strings.select_time + '</option>';
                    
                    response.data.slots.forEach(function(slot) {
                        var selected = originalValue === slot.value ? ' selected' : '';
                        var disabled = !slot.available ? ' disabled' : '';
                        options += '<option value="' + slot.value + '"' + selected + disabled + '>' + 
                                  slot.label + (slot.available ? '' : ' (Sold Out)') + '</option>';
                    });
                    
                    $timeSelect.html(options);
                } else {
                    $timeSelect.html('<option value="">' + wcefp_single.strings.no_slots + '</option>');
                }
            })
            .fail(function() {
                WCEFPSingle.showNotification('Error loading available slots', 'error');
            })
            .always(function() {
                $timeSelect.prop('disabled', false);
            });
        },
        
        /**
         * Update pricing based on selections
         */
        updatePricing: function() {
            var participants = parseInt($('#wcefp-participants').val()) || 2;
            var date = $('#wcefp-booking-date').val();
            var time = $('#wcefp-booking-time').val();
            
            if (!date || !time) return;
            
            // AJAX call to calculate pricing
            $.ajax({
                url: wcefp_single.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcefp_calculate_booking_price',
                    product_id: wcefp_single.product_id,
                    participants: participants,
                    date: date,
                    time: time,
                    nonce: wcefp_single.nonce
                }
            })
            .done(function(response) {
                if (response.success && response.data.pricing) {
                    $('.wcefp-price-display').html(response.data.pricing.formatted);
                    $('.wcefp-booking-form button[type="submit"]').prop('disabled', false);
                }
            })
            .fail(function() {
                console.warn('Error calculating price');
            });
        },
        
        /**
         * Increase quantity
         */
        increaseQuantity: function(targetId) {
            var $input = $('#' + targetId);
            var currentValue = parseInt($input.val()) || 0;
            var maxValue = parseInt($input.attr('max')) || 99;
            
            if (currentValue < maxValue) {
                $input.val(currentValue + 1).trigger('change');
                this.updatePricing();
            }
        },
        
        /**
         * Decrease quantity
         */
        decreaseQuantity: function(targetId) {
            var $input = $('#' + targetId);
            var currentValue = parseInt($input.val()) || 0;
            var minValue = parseInt($input.attr('min')) || 1;
            
            if (currentValue > minValue) {
                $input.val(currentValue - 1).trigger('change');
                this.updatePricing();
            }
        },
        
        /**
         * Show notification message
         */
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="wcefp-notification wcefp-notification-' + type + '">' + message + '</div>');
            
            // Remove existing notifications
            $('.wcefp-notification').remove();
            
            // Add to page
            $('body').append($notification);
            
            // Show notification
            $notification.slideDown(200);
            
            // Auto hide after 5 seconds
            setTimeout(function() {
                $notification.slideUp(200, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if single localization is available
        if (typeof wcefp_single === 'undefined') {
            console.warn('WCEFP Single: Missing localization data');
            return;
        }
        
        // Initialize single page functionality
        WCEFPSingle.init();
    });
    
    /**
     * Expose WCEFPSingle globally for external access
     */
    window.WCEFPSingle = WCEFPSingle;

})(jQuery);

/**
 * Additional CSS for gallery modal and notifications
 */
$(document).ready(function() {
    if (!$('#wcefp-single-inline-styles').length) {
        $('<style id="wcefp-single-inline-styles">').text(`
            .wcefp-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
            }
            
            .wcefp-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
            }
            
            .wcefp-modal-content {
                position: relative;
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .wcefp-modal-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
                background: none;
                border: none;
                color: white;
                font-size: 2rem;
                cursor: pointer;
                z-index: 10001;
            }
            
            .wcefp-gallery-container {
                position: relative;
                max-width: 90vw;
                max-height: 80vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .wcefp-gallery-image {
                max-width: 100%;
                max-height: 80vh;
                object-fit: contain;
            }
            
            .wcefp-gallery-nav {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                font-size: 2rem;
                padding: 1rem;
                cursor: pointer;
                border-radius: 50%;
                transition: background-color 0.2s ease;
            }
            
            .wcefp-gallery-nav:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            
            .wcefp-gallery-prev {
                left: -3rem;
            }
            
            .wcefp-gallery-next {
                right: -3rem;
            }
            
            .wcefp-gallery-thumbs-container {
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
                max-width: 100%;
                overflow-x: auto;
            }
            
            .wcefp-gallery-thumb {
                flex-shrink: 0;
                width: 60px;
                height: 60px;
                border-radius: 8px;
                overflow: hidden;
                cursor: pointer;
                opacity: 0.6;
                transition: opacity 0.2s ease;
            }
            
            .wcefp-gallery-thumb.active {
                opacity: 1;
                border: 2px solid white;
            }
            
            .wcefp-gallery-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .wcefp-notification {
                position: fixed;
                top: 2rem;
                right: 2rem;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                display: none;
                max-width: 300px;
            }
            
            .wcefp-notification-info {
                background: #007cba;
            }
            
            .wcefp-notification-error {
                background: #dc3545;
            }
            
            .wcefp-notification-success {
                background: #28a745;
            }
            
            body.wcefp-modal-open {
                overflow: hidden;
            }
            
            @media (max-width: 768px) {
                .wcefp-gallery-nav {
                    font-size: 1.5rem;
                    padding: 0.75rem;
                }
                
                .wcefp-gallery-prev {
                    left: -2rem;
                }
                
                .wcefp-gallery-next {
                    right: -2rem;
                }
                
                .wcefp-notification {
                    right: 1rem;
                    left: 1rem;
                    max-width: none;
                }
            }
        `).appendTo('head');
    }
});