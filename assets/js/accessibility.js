/**
 * WCEventsFP Accessibility Enhancement
 * 
 * Provides JavaScript-based accessibility improvements for WCAG 2.1 AA compliance.
 * Handles keyboard navigation, screen reader announcements, and dynamic content accessibility.
 * 
 * @package WCEventsFP
 * @since 2.1.4
 */

(function($) {
    'use strict';
    
    // Global accessibility object
    window.WCEFPAccessibility = {
        settings: {},
        announcer: null,
        assertiveAnnouncer: null,
        preferences: {},
        
        init: function() {
            this.settings = wcefpA11y?.settings || {};
            this.loadPreferences();
            this.setupAnnouncers();
            this.setupKeyboardNavigation();
            this.setupFocusManagement();
            this.setupFormEnhancements();
            this.setupDynamicContent();
            this.setupHighContrast();
            this.bindEvents();
            this.applyPreferences();
            
            console.log('WCEventsFP Accessibility initialized');
        },
        
        /**
         * Load accessibility preferences from localStorage
         */
        loadPreferences: function() {
            this.preferences = {
                highContrast: localStorage.getItem('wcefp_high_contrast') === 'true',
                textSize: parseInt(localStorage.getItem('wcefp_text_size')) || 100,
                focusMode: localStorage.getItem('wcefp_focus_mode') === 'true',
                reducedMotion: localStorage.getItem('wcefp_reduced_motion') === 'true'
            };
        },
        
        /**
         * Apply accessibility preferences
         */
        applyPreferences: function() {
            if (this.preferences.highContrast) {
                $('body').addClass('wcefp-high-contrast');
            }
            
            if (this.preferences.focusMode) {
                $('body').addClass('wcefp-focus-mode');
            }
            
            if (this.preferences.reducedMotion) {
                $('body').addClass('wcefp-reduced-motion');
            }
            
            if (this.preferences.textSize !== 100) {
                $('body').css('font-size', this.preferences.textSize + '%');
            }
        },
        
        /**
         * Setup screen reader announcers
         */
        setupAnnouncers: function() {
            this.announcer = $('#wcefp-a11y-announcer');
            this.assertiveAnnouncer = $('#wcefp-a11y-announcer-assertive');
            
            if (!this.announcer.length) {
                this.announcer = $('<div id="wcefp-a11y-announcer" aria-live="polite" aria-atomic="true" class="screen-reader-text"></div>')
                    .appendTo('body');
            }
            
            if (!this.assertiveAnnouncer.length) {
                this.assertiveAnnouncer = $('<div id="wcefp-a11y-announcer-assertive" aria-live="assertive" aria-atomic="true" class="screen-reader-text"></div>')
                    .appendTo('body');
            }
        },
        
        /**
         * Announce message to screen readers
         */
        announce: function(message, priority = 'polite') {
            if (!this.settings.announceChanges) return;
            
            const announcer = priority === 'assertive' ? this.assertiveAnnouncer : this.announcer;
            
            // Clear and announce
            announcer.text('');
            setTimeout(() => {
                announcer.text(message);
            }, 100);
            
            // Clear after announcement
            setTimeout(() => {
                announcer.text('');
            }, 1000);
        },
        
        /**
         * Setup keyboard navigation
         */
        setupKeyboardNavigation: function() {
            if (!this.settings.keyboardNavigation) return;
            
            // Skip links
            this.addSkipLinks();
            
            // Arrow key navigation for lists and grids
            $('.wcefp-events-grid, .wcefp-bookings-list').on('keydown', this.handleArrowNavigation);
            
            // Tab trap for modals
            $(document).on('keydown', '.wcefp-modal', this.trapTabInModal);
            
            // Escape key handling
            $(document).on('keydown', this.handleEscapeKey);
        },
        
        /**
         * Add skip links
         */
        addSkipLinks: function() {
            if (!$('.wcefp-skip-link').length && $('.wcefp-container').length) {
                const skipLink = $('<a href="#wcefp-main-content" class="wcefp-skip-link">')
                    .text(wcefpA11y.strings.skipToContent)
                    .on('focus', function() {
                        $(this).css({
                            'position': 'static',
                            'width': 'auto',
                            'height': 'auto',
                            'padding': '8px 16px',
                            'background': '#000',
                            'color': '#fff',
                            'text-decoration': 'none',
                            'z-index': '999999'
                        });
                    })
                    .on('blur', function() {
                        $(this).css({
                            'position': 'absolute',
                            'left': '-9999px',
                            'top': 'auto',
                            'width': '1px',
                            'height': '1px',
                            'overflow': 'hidden'
                        });
                    });
                    
                $('.wcefp-container').first().before(skipLink);
            }
        },
        
        /**
         * Handle arrow key navigation
         */
        handleArrowNavigation: function(e) {
            const $items = $(this).find('[tabindex], a, button, input, select, textarea').filter(':visible');
            const $current = $items.filter(':focus');
            
            if (!$current.length) return;
            
            const currentIndex = $items.index($current);
            let nextIndex;
            
            switch(e.which) {
                case 37: // Left arrow
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : $items.length - 1;
                    break;
                case 39: // Right arrow
                    nextIndex = currentIndex < $items.length - 1 ? currentIndex + 1 : 0;
                    break;
                case 38: // Up arrow
                    e.preventDefault();
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : $items.length - 1;
                    break;
                case 40: // Down arrow
                    e.preventDefault();
                    nextIndex = currentIndex < $items.length - 1 ? currentIndex + 1 : 0;
                    break;
                default:
                    return;
            }
            
            $items.eq(nextIndex).focus();
        },
        
        /**
         * Trap tab navigation within modals
         */
        trapTabInModal: function(e) {
            if (e.which !== 9) return; // Not tab key
            
            const $modal = $(this);
            const $focusableElements = $modal.find('a, button, input, select, textarea, [tabindex]').filter(':visible');
            const $firstElement = $focusableElements.first();
            const $lastElement = $focusableElements.last();
            
            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === $firstElement[0]) {
                    e.preventDefault();
                    $lastElement.focus();
                }
            } else {
                // Tab
                if (document.activeElement === $lastElement[0]) {
                    e.preventDefault();
                    $firstElement.focus();
                }
            }
        },
        
        /**
         * Handle escape key
         */
        handleEscapeKey: function(e) {
            if (e.which !== 27) return; // Not escape key
            
            // Close modals
            if ($('.wcefp-modal:visible').length) {
                WCEFPAccessibility.closeModal();
                return;
            }
            
            // Clear search
            if ($('.wcefp-search input:focus').length) {
                $('.wcefp-search input').val('').trigger('input');
                return;
            }
            
            // Close dropdowns
            $('.wcefp-dropdown.open').removeClass('open');
        },
        
        /**
         * Setup focus management
         */
        setupFocusManagement: function() {
            // Enhance focus indicators
            $('a, button, input, select, textarea, [tabindex]').on('focus', function() {
                $(this).addClass('wcefp-focused');
            }).on('blur', function() {
                $(this).removeClass('wcefp-focused');
            });
            
            // Manage focus for dynamic content
            $(document).on('wcefp:content-loaded', function(e, $container) {
                WCEFPAccessibility.manageFocus($container);
            });
        },
        
        /**
         * Setup form enhancements
         */
        setupFormEnhancements: function() {
            // Add ARIA attributes to form fields
            $('.wcefp-form').each(function() {
                WCEFPAccessibility.enhanceForm($(this));
            });
            
            // Live validation feedback
            $('.wcefp-form input, .wcefp-form select, .wcefp-form textarea').on('blur', function() {
                WCEFPAccessibility.validateField($(this));
            });
        },
        
        /**
         * Enhance form accessibility
         */
        enhanceForm: function($form) {
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const $label = $form.find('label[for="' + $field.attr('id') + '"]');
                
                // Ensure field has an ID
                if (!$field.attr('id')) {
                    const id = 'wcefp-field-' + Math.random().toString(36).substr(2, 9);
                    $field.attr('id', id);
                    $label.attr('for', id);
                }
                
                // Add aria-describedby for help text
                const $helpText = $field.next('.wcefp-field-description');
                if ($helpText.length) {
                    const descId = $field.attr('id') + '-description';
                    $helpText.attr('id', descId);
                    $field.attr('aria-describedby', descId);
                }
                
                // Add aria-required for required fields
                if ($field.prop('required') || $field.hasClass('required')) {
                    $field.attr('aria-required', 'true');
                }
            });
        },
        
        /**
         * Validate form field
         */
        validateField: function($field) {
            let isValid = true;
            let errorMessage = '';
            
            // Required field check
            if ($field.attr('aria-required') === 'true' && !$field.val()) {
                isValid = false;
                errorMessage = wcefpA11y.strings.required;
            }
            
            // Email validation
            if ($field.attr('type') === 'email' && $field.val()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test($field.val())) {
                    isValid = false;
                    errorMessage = wcefpA11y.strings.invalid;
                }
            }
            
            // Update ARIA attributes
            $field.attr('aria-invalid', isValid ? 'false' : 'true');
            
            return isValid;
        },
        
        /**
         * Setup dynamic content accessibility
         */
        setupDynamicContent: function() {
            // Monitor for AJAX loading states
            $(document).on('wcefp:ajax-start', function() {
                WCEFPAccessibility.announce(wcefpA11y.strings.loading);
            });
            
            $(document).on('wcefp:ajax-success', function() {
                WCEFPAccessibility.announce(wcefpA11y.strings.success);
            });
            
            $(document).on('wcefp:ajax-error', function() {
                WCEFPAccessibility.announce(wcefpA11y.strings.error, 'assertive');
            });
        },
        
        /**
         * Setup high contrast mode
         */
        setupHighContrast: function() {
            // Add accessibility control panel
            this.addAccessibilityControls();
        },
        
        /**
         * Add accessibility control panel
         */
        addAccessibilityControls: function() {
            if ($('.wcefp-accessibility-controls').length) return;
            
            const $controls = $(`
                <div class="wcefp-accessibility-controls" role="toolbar" aria-label="Accessibility Controls">
                    <button class="wcefp-a11y-toggle-contrast" data-setting="highContrast">
                        <span class="screen-reader-text">Toggle High Contrast</span>
                        <span aria-hidden="true">🎨</span>
                    </button>
                    <button class="wcefp-a11y-toggle-focus" data-setting="focusMode">
                        <span class="screen-reader-text">Toggle Focus Mode</span>
                        <span aria-hidden="true">🎯</span>
                    </button>
                    <button class="wcefp-a11y-text-size" data-action="increase">
                        <span class="screen-reader-text">Increase Text Size</span>
                        <span aria-hidden="true">🔍+</span>
                    </button>
                    <button class="wcefp-a11y-text-size" data-action="decrease">
                        <span class="screen-reader-text">Decrease Text Size</span>
                        <span aria-hidden="true">🔍-</span>
                    </button>
                </div>
            `);
            
            $('body').append($controls);
        },
        
        /**
         * Toggle accessibility setting
         */
        toggleSetting: function(setting) {
            this.preferences[setting] = !this.preferences[setting];
            localStorage.setItem('wcefp_' + setting.replace(/([A-Z])/g, '_$1').toLowerCase(), 
                                this.preferences[setting].toString());
            
            this.applyPreferences();
            
            // Announce change
            const message = setting + ' ' + (this.preferences[setting] ? 'enabled' : 'disabled');
            this.announce(message);
        },
        
        /**
         * Change text size
         */
        changeTextSize: function(action) {
            let newSize = this.preferences.textSize;
            
            if (action === 'increase' && newSize < 150) {
                newSize += 10;
            } else if (action === 'decrease' && newSize > 80) {
                newSize -= 10;
            }
            
            if (newSize !== this.preferences.textSize) {
                this.preferences.textSize = newSize;
                localStorage.setItem('wcefp_text_size', newSize.toString());
                $('body').css('font-size', newSize + '%');
                
                this.announce('Text size changed to ' + newSize + '%');
            }
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            const $modal = $('.wcefp-modal:visible');
            if ($modal.length) {
                $modal.hide().attr('aria-hidden', 'true');
                
                // Return focus to trigger element
                const triggerId = $modal.data('trigger');
                if (triggerId) {
                    $('#' + triggerId).focus();
                }
                
                this.announce(wcefpA11y.strings.closeDialog);
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;
            
            // Accessibility controls
            $(document).on('click', '.wcefp-a11y-toggle-contrast, .wcefp-a11y-toggle-focus', function() {
                const setting = $(this).data('setting');
                self.toggleSetting(setting);
            });
            
            $(document).on('click', '.wcefp-a11y-text-size', function() {
                const action = $(this).data('action');
                self.changeTextSize(action);
            });
            
            // Modal triggers
            $(document).on('click', '[data-wcefp-modal]', function(e) {
                e.preventDefault();
                const modalId = $(this).data('wcefp-modal');
                const $modal = $('#' + modalId);
                
                if ($modal.length) {
                    $modal.show().attr('aria-hidden', 'false').data('trigger', $(this).attr('id'));
                    $modal.find('a, button, input, select, textarea').first().focus();
                }
            });
            
            // Modal close
            $(document).on('click', '.wcefp-modal-close', function() {
                self.closeModal();
            });
        }
    };
    
    // Backward compatibility wrapper
    class WCEFPAccessibilityManager {
        constructor() {
            WCEFPAccessibility.init();
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof wcefpA11y !== 'undefined') {
            window.wcefpAccessibilityManager = new WCEFPAccessibilityManager();
        }
    });
    
})(jQuery);
			this.applyPreferences();

			// Setup skip links
			this.setupSkipLinks();
		}

		bindEvents() {
			// High contrast toggle
			$( document ).on( 'click', '#wcefp-high-contrast-toggle', ( e ) => {
				this.toggleHighContrast();
			} );

			// Text size controls
			$( document ).on( 'click', '#wcefp-text-size-increase', ( e ) => {
				this.adjustTextSize( 10 );
			} );

			$( document ).on( 'click', '#wcefp-text-size-decrease', ( e ) => {
				this.adjustTextSize( -10 );
			} );

			// Focus mode toggle
			$( document ).on(
				'click',
				'#wcefp-focus-indicators-toggle',
				( e ) => {
					this.toggleFocusMode();
				}
			);

			// Keyboard shortcuts
			$( document ).on( 'keydown', ( e ) => {
				this.handleKeyboardShortcuts( e );
			} );

			// Form submission accessibility
			$( document ).on( 'submit', '.wcefp-booking-form', ( e ) => {
				this.announceFormSubmission();
			} );

			// AJAX completion announcements
			$( document ).ajaxComplete( ( event, xhr, settings ) => {
				if ( settings.url.includes( 'wcefp' ) ) {
					this.announceAjaxCompletion( xhr );
				}
			} );
		}

		setupKeyboardNavigation() {
			// Enhanced keyboard navigation for calendar
			$( '.wcefp-calendar' ).on( 'keydown', ( e ) => {
				this.handleCalendarNavigation( e );
			} );

			// Booking form navigation
			$( '.wcefp-booking-form' ).on( 'keydown', ( e ) => {
				this.handleFormNavigation( e );
			} );

			// Modal navigation
			$( '.wcefp-modal' ).on( 'keydown', ( e ) => {
				this.handleModalNavigation( e );
			} );

			// Ensure all interactive elements are focusable
			this.ensureFocusability();
		}

		setupSkipLinks() {
			if ( ! $( '.wcefp-skip-links' ).length ) {
				const skipLinks = $( `
                    <div class="wcefp-skip-links" role="navigation" aria-label="${ WCEFPAccessibility.strings.skipToNavigation }">
                        <a href="#wcefp-main-content" class="wcefp-skip-link">${ WCEFPAccessibility.strings.skipToContent }</a>
                        <a href="#wcefp-booking-form" class="wcefp-skip-link">${ WCEFPAccessibility.strings.bookingForm }</a>
                        <a href="#wcefp-calendar" class="wcefp-skip-link">${ WCEFPAccessibility.strings.calendar }</a>
                    </div>
                ` );

				$( 'body' ).prepend( skipLinks );
			}
		}

		enhanceFormAccessibility() {
			// Add required field indicators
			$(
				'.wcefp-booking-form input[required], .wcefp-booking-form select[required]'
			).each( function () {
				const $field = $( this );
				const $label = $( 'label[for="' + $field.attr( 'id' ) + '"]' );

				if (
					$label.length &&
					! $label.find( '.required-indicator' ).length
				) {
					$label.append(
						' <span class="required-indicator" aria-label="required">*</span>'
					);
				}

				// Add aria-invalid for validation
				$field
					.on( 'invalid', function () {
						$( this ).attr( 'aria-invalid', 'true' );
					} )
					.on( 'input change', function () {
						if ( this.validity.valid ) {
							$( this ).removeAttr( 'aria-invalid' );
						}
					} );
			} );

			// Enhanced error handling
			$( '.wcefp-booking-form' ).on( 'submit', ( e ) => {
				const $form = $( e.target );
				const $invalidFields = $form.find( ':invalid' );

				if ( $invalidFields.length ) {
					e.preventDefault();
					this.announceFormErrors( $invalidFields );
					$invalidFields.first().focus();
				}
			} );

			// Add help text associations
			$( '.wcefp-field-help' ).each( function () {
				const $help = $( this );
				const fieldId = $help.data( 'for' );
				const helpId = fieldId + '-help';

				$help.attr( 'id', helpId );
				$( '#' + fieldId ).attr( 'aria-describedby', helpId );
			} );
		}

		setupAriaLiveRegions() {
			// Create live regions if they don't exist
			if ( ! $( '#wcefp-announcements' ).length ) {
				$( 'body' ).append( `
                    <div id="wcefp-announcements" aria-live="polite" aria-atomic="true" class="wcefp-sr-only"></div>
                    <div id="wcefp-alerts" aria-live="assertive" aria-atomic="true" class="wcefp-sr-only"></div>
                ` );
			}
		}

		toggleHighContrast() {
			this.preferences.highContrast = ! this.preferences.highContrast;
			this.applyHighContrast();
			this.savePreferences();

			const message = this.preferences.highContrast
				? 'High contrast mode enabled'
				: 'High contrast mode disabled';

			this.announce( message );

			// Update button state
			$( '#wcefp-high-contrast-toggle' ).attr(
				'aria-pressed',
				this.preferences.highContrast
			);
		}

		applyHighContrast() {
			$( 'body' ).toggleClass(
				'wcefp-high-contrast',
				this.preferences.highContrast
			);
		}

		adjustTextSize( change ) {
			this.preferences.textSize = Math.max(
				80,
				Math.min( 150, this.preferences.textSize + change )
			);
			this.applyTextSize();
			this.savePreferences();

			this.announce( `Text size set to ${ this.preferences.textSize }%` );
		}

		applyTextSize() {
			$( 'body' ).css( 'font-size', this.preferences.textSize + '%' );
		}

		toggleFocusMode() {
			this.preferences.focusMode = ! this.preferences.focusMode;
			this.applyFocusMode();
			this.savePreferences();

			const message = this.preferences.focusMode
				? 'Enhanced focus indicators enabled'
				: 'Enhanced focus indicators disabled';

			this.announce( message );

			// Update button state
			$( '#wcefp-focus-indicators-toggle' ).attr(
				'aria-pressed',
				this.preferences.focusMode
			);
		}

		applyFocusMode() {
			$( 'body' ).toggleClass(
				'wcefp-enhanced-focus',
				this.preferences.focusMode
			);
		}

		applyPreferences() {
			this.applyHighContrast();
			this.applyTextSize();
			this.applyFocusMode();
		}

		savePreferences() {
			localStorage.setItem(
				'wcefp_high_contrast',
				this.preferences.highContrast
			);
			localStorage.setItem(
				'wcefp_text_size',
				this.preferences.textSize
			);
			localStorage.setItem(
				'wcefp_focus_mode',
				this.preferences.focusMode
			);
		}

		handleKeyboardShortcuts( e ) {
			// Alt + C: Toggle high contrast
			if ( e.altKey && e.key === 'c' ) {
				e.preventDefault();
				this.toggleHighContrast();
			}

			// Alt + +: Increase text size
			if ( e.altKey && e.key === '=' ) {
				e.preventDefault();
				this.adjustTextSize( 10 );
			}

			// Alt + -: Decrease text size
			if ( e.altKey && e.key === '-' ) {
				e.preventDefault();
				this.adjustTextSize( -10 );
			}

			// Alt + F: Toggle focus mode
			if ( e.altKey && e.key === 'f' ) {
				e.preventDefault();
				this.toggleFocusMode();
			}
		}

		handleCalendarNavigation( e ) {
			const $calendar = $( e.target ).closest( '.wcefp-calendar' );
			const $focusedDate = $calendar
				.find( '.fc-day.fc-day-today, .fc-day:focus' )
				.first();

			switch ( e.key ) {
				case 'ArrowLeft':
					e.preventDefault();
					this.navigateCalendar( $focusedDate, -1, 'day' );
					break;
				case 'ArrowRight':
					e.preventDefault();
					this.navigateCalendar( $focusedDate, 1, 'day' );
					break;
				case 'ArrowUp':
					e.preventDefault();
					this.navigateCalendar( $focusedDate, -7, 'day' );
					break;
				case 'ArrowDown':
					e.preventDefault();
					this.navigateCalendar( $focusedDate, 7, 'day' );
					break;
				case 'Home':
					e.preventDefault();
					this.navigateToCalendarStart( $calendar );
					break;
				case 'End':
					e.preventDefault();
					this.navigateToCalendarEnd( $calendar );
					break;
				case 'Enter':
				case ' ':
					e.preventDefault();
					this.selectCalendarDate( $focusedDate );
					break;
			}
		}

		navigateCalendar( $current, offset, unit ) {
			// This would need integration with the specific calendar implementation
			// For now, we'll focus on the next/previous focusable element
			const $days = $( '.fc-day' );
			const currentIndex = $days.index( $current );
			const newIndex = Math.max(
				0,
				Math.min( $days.length - 1, currentIndex + offset )
			);

			$days.eq( newIndex ).focus();
			this.announce(
				`${ $days.eq( newIndex ).attr( 'data-date' ) } selected`
			);
		}

		handleFormNavigation( e ) {
			const $form = $( e.target ).closest( '.wcefp-booking-form' );
			const $focusables = $form
				.find( 'input, select, button, textarea' )
				.filter( ':visible' );

			if ( e.key === 'Tab' ) {
				// Let default tab behavior work, but announce field context
				setTimeout( () => {
					const $focused = $( ':focus' );
					if ( $focused.is( 'input[required], select[required]' ) ) {
						// Don't announce every time, just on first focus
						if ( ! $focused.data( 'announced' ) ) {
							this.announce( 'Required field' );
							$focused.data( 'announced', true );
						}
					}
				}, 10 );
			}
		}

		handleModalNavigation( e ) {
			if ( e.key === 'Escape' ) {
				const $modal = $( e.target ).closest( '.wcefp-modal' );
				const $closeBtn = $modal.find( '.wcefp-modal-close' );
				if ( $closeBtn.length ) {
					$closeBtn.click();
				}
			}
		}

		ensureFocusability() {
			// Ensure all interactive elements are keyboard accessible
			$( '.wcefp-clickable' ).each( function () {
				const $el = $( this );
				if (
					! $el.is( 'button, a, input, select, textarea' ) &&
					! $el.attr( 'tabindex' )
				) {
					$el.attr( 'tabindex', '0' ).attr( 'role', 'button' );
				}
			} );

			// Add keyboard event handlers for custom interactive elements
			$( '.wcefp-clickable[role="button"]' ).on(
				'keydown',
				function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						$( this ).click();
					}
				}
			);
		}

		announceFormSubmission() {
			this.announce( WCEFPAccessibility.strings.loading );
		}

		announceAjaxCompletion( xhr ) {
			try {
				const response = JSON.parse( xhr.responseText );
				if ( response.success ) {
					this.announce( WCEFPAccessibility.strings.bookingSuccess );
				} else {
					this.alert( WCEFPAccessibility.strings.bookingError );
				}
			} catch ( e ) {
				// Ignore parsing errors
			}
		}

		announceFormErrors( $invalidFields ) {
			const errorCount = $invalidFields.length;
			const message = `Form has ${ errorCount } error${
				errorCount > 1 ? 's' : ''
			}. Please correct and try again.`;
			this.alert( message );

			// Add individual field error announcements
			$invalidFields.each( function () {
				const $field = $( this );
				const label =
					$( 'label[for="' + $field.attr( 'id' ) + '"]' ).text() ||
					$field.attr( 'name' );
				const errorMessage = this.validationMessage || 'Invalid input';

				setTimeout( () => {
					alert( `${ label }: ${ errorMessage }` );
				}, 500 );
			} );
		}

		announce( message, priority = 'polite' ) {
			const $region =
				priority === 'assertive'
					? $( '#wcefp-alerts' )
					: $( '#wcefp-announcements' );
			$region.text( message );

			// Clear after announcement to allow repeat announcements
			setTimeout( () => $region.empty(), 1000 );
		}

		alert( message ) {
			this.announce( message, 'assertive' );
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
					screenReader: true,
				},
			};
		}

		resetToDefaults() {
			this.preferences = {
				highContrast: false,
				textSize: 100,
				focusMode: false,
			};

			this.applyPreferences();
			this.savePreferences();
			this.announce( 'Accessibility settings reset to defaults' );
		}
	}

	// Initialize accessibility manager when document is ready
	$( document ).ready( function () {
		if ( typeof WCEFPAccessibility !== 'undefined' ) {
			window.WCEFPAccessibilityManager = new WCEFPAccessibilityManager();

			// Expose for debugging and external access
			window.WCEFPAccessibility = window.WCEFPAccessibility || {};
			window.WCEFPAccessibility.manager =
				window.WCEFPAccessibilityManager;

			console.log( 'WCEventsFP: Accessibility enhancements loaded' );
		}
	} );
} )( jQuery );
