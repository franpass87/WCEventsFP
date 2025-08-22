/**
 * WCEventsFP Accessibility JavaScript
 * Provides accessibility enhancements and WCAG 2.1 AA compliance features
 */

(function($) {
    'use strict';

    class WCEFPAccessibilityManager {
        constructor() {
            this.init();
            this.bindEvents();
            this.setupKeyboardNavigation();
            this.enhanceFormAccessibility();
            this.setupAriaLiveRegions();
        }

        init() {
            // Initialize accessibility preferences from localStorage
            this.preferences = {
                highContrast: localStorage.getItem('wcefp_high_contrast') === 'true',
                textSize: parseInt(localStorage.getItem('wcefp_text_size')) || 100,
                focusMode: localStorage.getItem('wcefp_focus_mode') === 'true'
            };

            // Apply saved preferences
            this.applyPreferences();

            // Setup skip links
            this.setupSkipLinks();
        }

        bindEvents() {
            // High contrast toggle
            $(document).on('click', '#wcefp-high-contrast-toggle', (e) => {
                this.toggleHighContrast();
            });

            // Text size controls
            $(document).on('click', '#wcefp-text-size-increase', (e) => {
                this.adjustTextSize(10);
            });

            $(document).on('click', '#wcefp-text-size-decrease', (e) => {
                this.adjustTextSize(-10);
            });

            // Focus mode toggle
            $(document).on('click', '#wcefp-focus-indicators-toggle', (e) => {
                this.toggleFocusMode();
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                this.handleKeyboardShortcuts(e);
            });

            // Form submission accessibility
            $(document).on('submit', '.wcefp-booking-form', (e) => {
                this.announceFormSubmission();
            });

            // AJAX completion announcements
            $(document).ajaxComplete((event, xhr, settings) => {
                if (settings.url.includes('wcefp')) {
                    this.announceAjaxCompletion(xhr);
                }
            });
        }

        setupKeyboardNavigation() {
            // Enhanced keyboard navigation for calendar
            $('.wcefp-calendar').on('keydown', (e) => {
                this.handleCalendarNavigation(e);
            });

            // Booking form navigation
            $('.wcefp-booking-form').on('keydown', (e) => {
                this.handleFormNavigation(e);
            });

            // Modal navigation
            $('.wcefp-modal').on('keydown', (e) => {
                this.handleModalNavigation(e);
            });

            // Ensure all interactive elements are focusable
            this.ensureFocusability();
        }

        setupSkipLinks() {
            if (!$('.wcefp-skip-links').length) {
                const skipLinks = $(`
                    <div class="wcefp-skip-links" role="navigation" aria-label="${WCEFPAccessibility.strings.skipToNavigation}">
                        <a href="#wcefp-main-content" class="wcefp-skip-link">${WCEFPAccessibility.strings.skipToContent}</a>
                        <a href="#wcefp-booking-form" class="wcefp-skip-link">${WCEFPAccessibility.strings.bookingForm}</a>
                        <a href="#wcefp-calendar" class="wcefp-skip-link">${WCEFPAccessibility.strings.calendar}</a>
                    </div>
                `);
                
                $('body').prepend(skipLinks);
            }
        }

        enhanceFormAccessibility() {
            // Add required field indicators
            $('.wcefp-booking-form input[required], .wcefp-booking-form select[required]').each(function() {
                const $field = $(this);
                const $label = $('label[for="' + $field.attr('id') + '"]');
                
                if ($label.length && !$label.find('.required-indicator').length) {
                    $label.append(' <span class="required-indicator" aria-label="required">*</span>');
                }

                // Add aria-invalid for validation
                $field.on('invalid', function() {
                    $(this).attr('aria-invalid', 'true');
                }).on('input change', function() {
                    if (this.validity.valid) {
                        $(this).removeAttr('aria-invalid');
                    }
                });
            });

            // Enhanced error handling
            $('.wcefp-booking-form').on('submit', (e) => {
                const $form = $(e.target);
                const $invalidFields = $form.find(':invalid');

                if ($invalidFields.length) {
                    e.preventDefault();
                    this.announceFormErrors($invalidFields);
                    $invalidFields.first().focus();
                }
            });

            // Add help text associations
            $('.wcefp-field-help').each(function() {
                const $help = $(this);
                const fieldId = $help.data('for');
                const helpId = fieldId + '-help';
                
                $help.attr('id', helpId);
                $('#' + fieldId).attr('aria-describedby', helpId);
            });
        }

        setupAriaLiveRegions() {
            // Create live regions if they don't exist
            if (!$('#wcefp-announcements').length) {
                $('body').append(`
                    <div id="wcefp-announcements" aria-live="polite" aria-atomic="true" class="wcefp-sr-only"></div>
                    <div id="wcefp-alerts" aria-live="assertive" aria-atomic="true" class="wcefp-sr-only"></div>
                `);
            }
        }

        toggleHighContrast() {
            this.preferences.highContrast = !this.preferences.highContrast;
            this.applyHighContrast();
            this.savePreferences();
            
            const message = this.preferences.highContrast 
                ? 'High contrast mode enabled'
                : 'High contrast mode disabled';
            
            this.announce(message);
            
            // Update button state
            $('#wcefp-high-contrast-toggle').attr('aria-pressed', this.preferences.highContrast);
        }

        applyHighContrast() {
            $('body').toggleClass('wcefp-high-contrast', this.preferences.highContrast);
        }

        adjustTextSize(change) {
            this.preferences.textSize = Math.max(80, Math.min(150, this.preferences.textSize + change));
            this.applyTextSize();
            this.savePreferences();
            
            this.announce(`Text size set to ${this.preferences.textSize}%`);
        }

        applyTextSize() {
            $('body').css('font-size', this.preferences.textSize + '%');
        }

        toggleFocusMode() {
            this.preferences.focusMode = !this.preferences.focusMode;
            this.applyFocusMode();
            this.savePreferences();
            
            const message = this.preferences.focusMode 
                ? 'Enhanced focus indicators enabled'
                : 'Enhanced focus indicators disabled';
            
            this.announce(message);
            
            // Update button state
            $('#wcefp-focus-indicators-toggle').attr('aria-pressed', this.preferences.focusMode);
        }

        applyFocusMode() {
            $('body').toggleClass('wcefp-enhanced-focus', this.preferences.focusMode);
        }

        applyPreferences() {
            this.applyHighContrast();
            this.applyTextSize();
            this.applyFocusMode();
        }

        savePreferences() {
            localStorage.setItem('wcefp_high_contrast', this.preferences.highContrast);
            localStorage.setItem('wcefp_text_size', this.preferences.textSize);
            localStorage.setItem('wcefp_focus_mode', this.preferences.focusMode);
        }

        handleKeyboardShortcuts(e) {
            // Alt + C: Toggle high contrast
            if (e.altKey && e.key === 'c') {
                e.preventDefault();
                this.toggleHighContrast();
            }
            
            // Alt + +: Increase text size
            if (e.altKey && e.key === '=') {
                e.preventDefault();
                this.adjustTextSize(10);
            }
            
            // Alt + -: Decrease text size
            if (e.altKey && e.key === '-') {
                e.preventDefault();
                this.adjustTextSize(-10);
            }

            // Alt + F: Toggle focus mode
            if (e.altKey && e.key === 'f') {
                e.preventDefault();
                this.toggleFocusMode();
            }
        }

        handleCalendarNavigation(e) {
            const $calendar = $(e.target).closest('.wcefp-calendar');
            const $focusedDate = $calendar.find('.fc-day.fc-day-today, .fc-day:focus').first();

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.navigateCalendar($focusedDate, -1, 'day');
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.navigateCalendar($focusedDate, 1, 'day');
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.navigateCalendar($focusedDate, -7, 'day');
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.navigateCalendar($focusedDate, 7, 'day');
                    break;
                case 'Home':
                    e.preventDefault();
                    this.navigateToCalendarStart($calendar);
                    break;
                case 'End':
                    e.preventDefault();
                    this.navigateToCalendarEnd($calendar);
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    this.selectCalendarDate($focusedDate);
                    break;
            }
        }

        navigateCalendar($current, offset, unit) {
            // This would need integration with the specific calendar implementation
            // For now, we'll focus on the next/previous focusable element
            const $days = $('.fc-day');
            const currentIndex = $days.index($current);
            const newIndex = Math.max(0, Math.min($days.length - 1, currentIndex + offset));
            
            $days.eq(newIndex).focus();
            this.announce(`${$days.eq(newIndex).attr('data-date')} selected`);
        }

        handleFormNavigation(e) {
            const $form = $(e.target).closest('.wcefp-booking-form');
            const $focusables = $form.find('input, select, button, textarea').filter(':visible');

            if (e.key === 'Tab') {
                // Let default tab behavior work, but announce field context
                setTimeout(() => {
                    const $focused = $(':focus');
                    if ($focused.is('input[required], select[required]')) {
                        // Don't announce every time, just on first focus
                        if (!$focused.data('announced')) {
                            this.announce('Required field');
                            $focused.data('announced', true);
                        }
                    }
                }, 10);
            }
        }

        handleModalNavigation(e) {
            if (e.key === 'Escape') {
                const $modal = $(e.target).closest('.wcefp-modal');
                const $closeBtn = $modal.find('.wcefp-modal-close');
                if ($closeBtn.length) {
                    $closeBtn.click();
                }
            }
        }

        ensureFocusability() {
            // Ensure all interactive elements are keyboard accessible
            $('.wcefp-clickable').each(function() {
                const $el = $(this);
                if (!$el.is('button, a, input, select, textarea') && !$el.attr('tabindex')) {
                    $el.attr('tabindex', '0').attr('role', 'button');
                }
            });

            // Add keyboard event handlers for custom interactive elements
            $('.wcefp-clickable[role="button"]').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        }

        announceFormSubmission() {
            this.announce(WCEFPAccessibility.strings.loading);
        }

        announceAjaxCompletion(xhr) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    this.announce(WCEFPAccessibility.strings.bookingSuccess);
                } else {
                    this.alert(WCEFPAccessibility.strings.bookingError);
                }
            } catch (e) {
                // Ignore parsing errors
            }
        }

        announceFormErrors($invalidFields) {
            const errorCount = $invalidFields.length;
            const message = `Form has ${errorCount} error${errorCount > 1 ? 's' : ''}. Please correct and try again.`;
            this.alert(message);

            // Add individual field error announcements
            $invalidFields.each(function() {
                const $field = $(this);
                const label = $('label[for="' + $field.attr('id') + '"]').text() || $field.attr('name');
                const errorMessage = this.validationMessage || 'Invalid input';
                
                setTimeout(() => {
                    this.alert(`${label}: ${errorMessage}`);
                }.bind(this), 500);
            });
        }

        announce(message, priority = 'polite') {
            const $region = priority === 'assertive' ? $('#wcefp-alerts') : $('#wcefp-announcements');
            $region.text(message);
            
            // Clear after announcement to allow repeat announcements
            setTimeout(() => $region.empty(), 1000);
        }

        alert(message) {
            this.announce(message, 'assertive');
        }

        // Public API methods
        getAccessibilityStatus() {
            return {
                preferences: this.preferences,
                features: {
                    highContrast: true,
                    textSize: true,
                    focusMode: true,
                    keyboardNavigation: true,
                    screenReader: true
                }
            };
        }

        resetToDefaults() {
            this.preferences = {
                highContrast: false,
                textSize: 100,
                focusMode: false
            };
            
            this.applyPreferences();
            this.savePreferences();
            this.announce('Accessibility settings reset to defaults');
        }
    }

    // Initialize accessibility manager when document is ready
    $(document).ready(function() {
        if (typeof WCEFPAccessibility !== 'undefined') {
            window.WCEFPAccessibilityManager = new WCEFPAccessibilityManager();
            
            // Expose for debugging and external access
            window.WCEFPAccessibility = window.WCEFPAccessibility || {};
            window.WCEFPAccessibility.manager = window.WCEFPAccessibilityManager;
            
            console.log('WCEventsFP: Accessibility enhancements loaded');
        }
    });

})(jQuery);