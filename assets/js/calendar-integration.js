/**
 * Calendar Integration Frontend Script
 * 
 * Handles Google Calendar integration, Outlook calendar buttons, and ICS downloads
 * 
 * @package WCEventsFP
 * @since 2.2.0
 */

(function($) {
    'use strict';
    
    const CalendarIntegration = {
        
        /**
         * Initialize calendar integration
         */
        init: function() {
            this.bindEvents();
            this.enhanceCalendarButtons();
        },
        
        /**
         * Bind calendar events
         */
        bindEvents: function() {
            // Calendar button clicks with analytics
            $(document).on('click', '.wcefp-calendar-btn', this.handleCalendarClick);
            
            // Keyboard navigation for accessibility
            $(document).on('keydown', '.wcefp-calendar-btn', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            // Dynamic calendar button generation for AJAX-loaded content
            $(document).on('wcefp:booking_confirmed', this.addCalendarButtons);
            $(document).on('wcefp:content_updated', this.enhanceCalendarButtons);
        },
        
        /**
         * Handle calendar button clicks
         */
        handleCalendarClick: function(e) {
            const $button = $(this);
            const calendarType = CalendarIntegration.getCalendarType($button);
            
            // Track calendar integration usage
            CalendarIntegration.trackCalendarUsage(calendarType);
            
            // Add visual feedback
            CalendarIntegration.addClickFeedback($button);
            
            // Handle ICS download specially
            if ($button.hasClass('wcefp-ics-download')) {
                CalendarIntegration.handleICSDownload(e, $button);
            }
        },
        
        /**
         * Handle ICS download with fallback
         */
        handleICSDownload: function(e, $button) {
            const downloadUrl = $button.attr('href');
            
            // Try modern download approach
            if (navigator.msSaveBlob || window.downloadFile) {
                e.preventDefault();
                
                $.ajax({
                    url: downloadUrl,
                    method: 'GET',
                    dataType: 'text',
                    success: function(data) {
                        CalendarIntegration.downloadICSFile(data, 'event.ics');
                    },
                    error: function() {
                        // Fallback to direct link
                        window.open(downloadUrl, '_blank');
                    }
                });
            }
            // Otherwise, let the browser handle the download normally
        },
        
        /**
         * Download ICS file programmatically
         */
        downloadICSFile: function(content, filename) {
            const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' });
            
            // Modern browsers
            if (window.navigator.msSaveBlob) {
                window.navigator.msSaveBlob(blob, filename);
                return;
            }
            
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        },
        
        /**
         * Get calendar type from button
         */
        getCalendarType: function($button) {
            if ($button.hasClass('wcefp-google-calendar')) return 'google';
            if ($button.hasClass('wcefp-outlook-calendar')) return 'outlook';
            if ($button.hasClass('wcefp-ics-download')) return 'ics';
            return 'unknown';
        },
        
        /**
         * Track calendar usage for analytics
         */
        trackCalendarUsage: function(calendarType) {
            // Send usage analytics
            if (typeof wcefpAnalytics !== 'undefined' && wcefpAnalytics.enabled) {
                $.ajax({
                    url: wcefpCalendar.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_track_calendar_usage',
                        calendar_type: calendarType,
                        nonce: wcefpCalendar.nonce
                    }
                });
            }
            
            // Google Analytics integration
            if (typeof gtag === 'function') {
                gtag('event', 'calendar_integration', {
                    event_category: 'booking',
                    event_label: calendarType,
                    value: 1
                });
            }
            
            // Facebook Pixel integration
            if (typeof fbq === 'function') {
                fbq('track', 'AddToWishlist', {
                    content_category: 'calendar_integration',
                    content_name: calendarType
                });
            }
        },
        
        /**
         * Add visual click feedback
         */
        addClickFeedback: function($button) {
            $button.addClass('wcefp-btn-clicked');
            
            setTimeout(function() {
                $button.removeClass('wcefp-btn-clicked');
            }, 200);
        },
        
        /**
         * Enhance existing calendar buttons
         */
        enhanceCalendarButtons: function() {
            $('.wcefp-calendar-buttons').each(function() {
                const $container = $(this);
                
                // Add accessibility attributes
                $container.find('.wcefp-calendar-btn').each(function() {
                    const $btn = $(this);
                    
                    if (!$btn.attr('role')) {
                        $btn.attr('role', 'button');
                    }
                    
                    if (!$btn.attr('tabindex')) {
                        $btn.attr('tabindex', '0');
                    }
                    
                    // Add ARIA labels
                    if (!$btn.attr('aria-label')) {
                        const text = $btn.text().trim();
                        $btn.attr('aria-label', text);
                    }
                });
                
                // Add responsive behavior
                CalendarIntegration.makeResponsive($container);
            });
        },
        
        /**
         * Make calendar buttons responsive
         */
        makeResponsive: function($container) {
            const $buttons = $container.find('.wcefp-calendar-button-group');
            
            function checkWidth() {
                const containerWidth = $container.width();
                
                if (containerWidth < 400) {
                    $buttons.addClass('wcefp-stack-vertical');
                } else {
                    $buttons.removeClass('wcefp-stack-vertical');
                }
            }
            
            // Initial check
            checkWidth();
            
            // Check on window resize
            $(window).on('resize.wcefp-calendar', checkWidth);
        },
        
        /**
         * Add calendar buttons to booking confirmation
         */
        addCalendarButtons: function(e, bookingData) {
            if (!bookingData || !bookingData.booking_id) {
                return;
            }
            
            // Generate calendar buttons via AJAX
            $.ajax({
                url: wcefpCalendar.ajaxurl,
                method: 'POST',
                data: {
                    action: 'wcefp_get_calendar_buttons',
                    booking_id: bookingData.booking_id,
                    nonce: wcefpCalendar.nonce
                },
                success: function(response) {
                    if (response.success && response.data.buttons) {
                        // Find confirmation container and add buttons
                        const $confirmation = $('.wcefp-booking-confirmation, .wcefp-booking-success');
                        if ($confirmation.length) {
                            $confirmation.append(response.data.buttons);
                            CalendarIntegration.enhanceCalendarButtons();
                        }
                    }
                }
            });
        },
        
        /**
         * Generate calendar URLs dynamically
         */
        generateCalendarUrls: function(eventData) {
            const startDate = this.formatCalendarDate(eventData.date, eventData.time);
            const endDate = this.formatCalendarDate(eventData.date, eventData.time, 2); // +2 hours
            
            return {
                google: this.buildGoogleCalendarUrl({
                    title: eventData.title,
                    start: startDate,
                    end: endDate,
                    location: eventData.location,
                    description: eventData.description
                }),
                outlook: this.buildOutlookCalendarUrl({
                    title: eventData.title,
                    start: startDate,
                    end: endDate,
                    location: eventData.location,
                    description: eventData.description
                })
            };
        },
        
        /**
         * Build Google Calendar URL
         */
        buildGoogleCalendarUrl: function(event) {
            const params = new URLSearchParams({
                action: 'TEMPLATE',
                text: event.title,
                dates: event.start + '/' + event.end,
                details: event.description || '',
                location: event.location || '',
                trp: 'false'
            });
            
            return 'https://calendar.google.com/calendar/render?' + params.toString();
        },
        
        /**
         * Build Outlook Calendar URL
         */
        buildOutlookCalendarUrl: function(event) {
            const params = new URLSearchParams({
                subject: event.title,
                startdt: event.start,
                enddt: event.end,
                body: event.description || '',
                location: event.location || ''
            });
            
            return 'https://outlook.live.com/calendar/0/deeplink/compose?' + params.toString();
        },
        
        /**
         * Format date for calendar URLs
         */
        formatCalendarDate: function(date, time, hoursToAdd) {
            const datetime = new Date(date + 'T' + time);
            
            if (hoursToAdd) {
                datetime.setHours(datetime.getHours() + hoursToAdd);
            }
            
            return datetime.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        },
        
        /**
         * Show calendar integration modal
         */
        showCalendarModal: function(eventData) {
            const urls = this.generateCalendarUrls(eventData);
            
            const modalContent = `
                <div class="wcefp-calendar-modal">
                    <h3>${wcefpCalendar.strings.addToCalendar}</h3>
                    <div class="wcefp-calendar-options">
                        <a href="${urls.google}" class="wcefp-calendar-option wcefp-google" target="_blank">
                            <span class="wcefp-calendar-icon wcefp-google-icon"></span>
                            <span class="wcefp-calendar-text">
                                <strong>Google Calendar</strong>
                                <small>${wcefpCalendar.strings.opensInNewTab}</small>
                            </span>
                        </a>
                        <a href="${urls.outlook}" class="wcefp-calendar-option wcefp-outlook" target="_blank">
                            <span class="wcefp-calendar-icon wcefp-outlook-icon"></span>
                            <span class="wcefp-calendar-text">
                                <strong>Outlook</strong>
                                <small>${wcefpCalendar.strings.opensInNewTab}</small>
                            </span>
                        </a>
                    </div>
                </div>
            `;
            
            // Use WCEFP modal system if available
            if (typeof wcefpModals !== 'undefined') {
                wcefpModals.show({
                    title: wcefpCalendar.strings.addToCalendar,
                    content: modalContent,
                    size: 'medium'
                });
            } else {
                // Fallback to simple modal
                this.showSimpleModal(modalContent);
            }
        },
        
        /**
         * Simple modal fallback
         */
        showSimpleModal: function(content) {
            const $modal = $(`
                <div class="wcefp-simple-modal">
                    <div class="wcefp-modal-overlay"></div>
                    <div class="wcefp-modal-content">
                        <button class="wcefp-modal-close">&times;</button>
                        ${content}
                    </div>
                </div>
            `);
            
            $('body').append($modal);
            $modal.fadeIn(200);
            
            // Close modal events
            $modal.find('.wcefp-modal-close, .wcefp-modal-overlay').on('click', function() {
                $modal.fadeOut(200, function() {
                    $modal.remove();
                });
            });
            
            // Escape key to close
            $(document).on('keydown.wcefp-modal', function(e) {
                if (e.key === 'Escape') {
                    $modal.find('.wcefp-modal-close').click();
                    $(document).off('keydown.wcefp-modal');
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        CalendarIntegration.init();
    });
    
    // Expose to global scope for external integration
    window.wcefpCalendarIntegration = CalendarIntegration;
    
})(jQuery);