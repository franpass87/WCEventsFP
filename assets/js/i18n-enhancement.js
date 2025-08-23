/**
 * WCEventsFP Enhanced Internationalization
 * Client-side i18n management and dynamic translations
 */

( function ( $ ) {
	'use strict';

	// Enhanced i18n manager
	class WCEFPInternationalization {
		constructor() {
			this.currentLocale = wcefp_i18n.current_locale || 'en_US';
			this.supportedLocales = wcefp_i18n.supported_locales || {};
			this.translations = wcefp_i18n.frontend_strings || {};
			this.rtlLocales = [ 'ar', 'he_IL', 'fa_IR' ];

			this.init();
		}

		init() {
			this.bindEvents();
			this.initLanguageSwitcher();
			this.applyLocaleFormatting();
		}

		/**
		 * Bind i18n related events
		 */
		bindEvents() {
			// Handle language switching
			$( document ).on(
				'click',
				'.wcefp-language-switcher .wcefp-locale-option',
				( e ) => {
					e.preventDefault();
					const locale = $( e.target ).data( 'locale' );
					this.switchLocale( locale );
				}
			);

			// Auto-translate dynamic content
			$( document ).on( 'wcefp_content_loaded', () => {
				this.translateDynamicContent();
			} );

			// Handle booking form localization
			$( document ).on( 'wcefp_booking_form_init', () => {
				this.localizeBookingForm();
			} );
		}

		/**
		 * Initialize language switcher widget
		 */
		initLanguageSwitcher() {
			if ( $( '.wcefp-language-switcher' ).length ) {
				return; // Already initialized
			}

			const currentLocaleInfo = this.supportedLocales[
				this.currentLocale
			];
			if ( ! currentLocaleInfo ) return;

			const switcher = $( `
                <div class="wcefp-language-switcher">
                    <button class="wcefp-current-language" aria-label="Change Language">
                        <span class="wcefp-flag">${
							currentLocaleInfo.flag
						}</span>
                        <span class="wcefp-language-name">${
							currentLocaleInfo.name
						}</span>
                        <span class="wcefp-dropdown-arrow">▼</span>
                    </button>
                    <ul class="wcefp-language-dropdown" style="display: none;">
                        ${ this.generateLanguageOptions() }
                    </ul>
                </div>
            ` );

			// Add to page (you can customize the selector)
			if ( $( '.wcefp-booking-widget' ).length ) {
				$( '.wcefp-booking-widget' ).prepend( switcher );
			}

			// Toggle dropdown
			switcher.find( '.wcefp-current-language' ).on( 'click', () => {
				switcher.find( '.wcefp-language-dropdown' ).toggle();
			} );

			// Close dropdown on outside click
			$( document ).on( 'click', ( e ) => {
				if (
					! $( e.target ).closest( '.wcefp-language-switcher' ).length
				) {
					$( '.wcefp-language-dropdown' ).hide();
				}
			} );
		}

		/**
		 * Generate language options HTML
		 */
		generateLanguageOptions() {
			let options = '';

			// Sort locales by priority
			const sortedLocales = Object.entries( this.supportedLocales ).sort(
				( [ , a ], [ , b ] ) => a.priority - b.priority
			);

			sortedLocales.forEach( ( [ locale, info ] ) => {
				if ( locale !== this.currentLocale ) {
					options += `
                        <li>
                            <a href="#" class="wcefp-locale-option" data-locale="${ locale }">
                                <span class="wcefp-flag">${ info.flag }</span>
                                <span class="wcefp-language-name">${ info.name }</span>
                            </a>
                        </li>
                    `;
				}
			} );

			return options;
		}

		/**
		 * Switch to a different locale
		 *
		 * @param  locale
		 */
		async switchLocale( locale ) {
			if ( ! this.supportedLocales[ locale ] ) {
				console.error( 'Unsupported locale:', locale );
				return;
			}

			try {
				this.showLoadingOverlay();

				// Fetch translations for new locale
				const response = await this.fetchTranslations( locale );

				if ( response.success ) {
					this.currentLocale = locale;
					this.translations = response.data.translations;

					// Update UI
					this.updateLanguageSwitcher( locale );
					this.translateDynamicContent();
					this.applyLocaleFormatting();
					this.updateBookingFormLocalization();

					// Trigger event for other components
					$( document ).trigger( 'wcefp_locale_changed', [
						locale,
						this.supportedLocales[ locale ],
					] );

					// Store preference
					localStorage.setItem( 'wcefp_preferred_locale', locale );

					this.showNotification(
						'Language changed successfully',
						'success'
					);
				} else {
					throw new Error(
						response.data.message || 'Failed to fetch translations'
					);
				}
			} catch ( error ) {
				console.error( 'Error switching locale:', error );
				this.showNotification( 'Failed to change language', 'error' );
			} finally {
				this.hideLoadingOverlay();
			}
		}

		/**
		 * Fetch translations via AJAX
		 *
		 * @param  locale
		 */
		fetchTranslations( locale ) {
			const stringsToTranslate = this.extractStringsFromPage();

			return $.post( wcefp_i18n.ajax_url, {
				action: 'wcefp_get_translations',
				locale,
				strings: stringsToTranslate,
				nonce: wcefp_i18n.nonce,
			} );
		}

		/**
		 * Extract translatable strings from current page
		 */
		extractStringsFromPage() {
			const strings = new Set();

			// Common booking strings
			const commonStrings = [
				'Book Now',
				'Check Availability',
				'Select Date',
				'Participants',
				'Total Price',
				'Confirm Booking',
				'Duration',
				'Meeting Point',
				"What's Included",
				'Reviews',
				'Loading...',
				'Cancel Booking',
			];

			commonStrings.forEach( ( str ) => strings.add( str ) );

			// Extract from data attributes
			$( '[data-translate]' ).each( function () {
				strings.add( $( this ).data( 'translate' ) );
			} );

			return Array.from( strings );
		}

		/**
		 * Translate dynamic content on the page
		 */
		translateDynamicContent() {
			// Translate elements with data-translate attribute
			$( '[data-translate]' ).each( ( index, element ) => {
				const $element = $( element );
				const key = $element.data( 'translate' );
				const translation = this.getTranslation( key );

				if ( translation !== key ) {
					$element.text( translation );
				}
			} );

			// Translate common booking elements
			this.translateBookingElements();
		}

		/**
		 * Translate common booking elements
		 */
		translateBookingElements() {
			const selectors = {
				'.wcefp-book-now': 'book_now',
				'.wcefp-check-availability': 'check_availability',
				'.wcefp-select-date': 'select_date',
				'.wcefp-participants-label': 'participants',
				'.wcefp-total-price-label': 'total_price',
				'.wcefp-confirm-booking': 'confirm_booking',
				'.wcefp-duration-label': 'duration',
				'.wcefp-meeting-point-label': 'meeting_point',
				'.wcefp-whats-included-label': 'whats_included',
				'.wcefp-reviews-label': 'reviews',
				'.wcefp-loading': 'loading',
			};

			Object.entries( selectors ).forEach(
				( [ selector, translationKey ] ) => {
					$( selector ).each( ( index, element ) => {
						const $element = $( element );
						const translation = this.getTranslation(
							translationKey,
							'booking'
						);

						if (
							translation &&
							! $element.data( 'original-text' )
						) {
							$element.data( 'original-text', $element.text() );
							$element.text( translation );
						}
					} );
				}
			);
		}

		/**
		 * Get translation from cached data
		 *
		 * @param  key
		 * @param  category
		 */
		getTranslation( key, category = null ) {
			if (
				category &&
				this.translations[ category ] &&
				this.translations[ category ][ key ]
			) {
				return this.translations[ category ][ key ];
			}

			if ( this.translations[ key ] ) {
				return this.translations[ key ];
			}

			return key; // Fallback to key
		}

		/**
		 * Apply locale-specific formatting
		 */
		applyLocaleFormatting() {
			const localeInfo = this.supportedLocales[ this.currentLocale ];
			if ( ! localeInfo ) return;

			// Apply RTL direction if needed
			if ( this.isRTL() ) {
				$( 'body' ).addClass( 'wcefp-rtl' ).removeClass( 'wcefp-ltr' );
				$( 'html' ).attr( 'dir', 'rtl' );
			} else {
				$( 'body' ).addClass( 'wcefp-ltr' ).removeClass( 'wcefp-rtl' );
				$( 'html' ).attr( 'dir', 'ltr' );
			}

			// Format prices
			$( '.wcefp-price' ).each( ( index, element ) => {
				const $element = $( element );
				const amount = parseFloat( $element.data( 'amount' ) );

				if ( ! isNaN( amount ) ) {
					$element.text( this.formatPrice( amount ) );
				}
			} );

			// Format dates
			$( '.wcefp-date' ).each( ( index, element ) => {
				const $element = $( element );
				const dateString = $element.data( 'date' );

				if ( dateString ) {
					$element.text( this.formatDate( dateString ) );
				}
			} );
		}

		/**
		 * Format price according to current locale
		 *
		 * @param  amount
		 */
		formatPrice( amount ) {
			const localeInfo = this.supportedLocales[ this.currentLocale ];
			if ( ! localeInfo ) return amount;

			const formatted = amount.toLocaleString( this.currentLocale, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			} );

			return `${ localeInfo.currency } ${ formatted }`;
		}

		/**
		 * Format date according to current locale
		 *
		 * @param  dateString
		 */
		formatDate( dateString ) {
			const date = new Date( dateString );
			const localeInfo = this.supportedLocales[ this.currentLocale ];

			if ( ! localeInfo ) return dateString;

			// Use Intl.DateTimeFormat for better locale support
			try {
				return new Intl.DateTimeFormat( this.currentLocale, {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
				} ).format( date );
			} catch ( error ) {
				return dateString; // Fallback
			}
		}

		/**
		 * Check if current locale is RTL
		 */
		isRTL() {
			return this.rtlLocales.includes( this.currentLocale );
		}

		/**
		 * Update language switcher after locale change
		 *
		 * @param  newLocale
		 */
		updateLanguageSwitcher( newLocale ) {
			const newLocaleInfo = this.supportedLocales[ newLocale ];
			if ( ! newLocaleInfo ) return;

			const $switcher = $( '.wcefp-language-switcher' );
			if ( ! $switcher.length ) return;

			// Update current language display
			$switcher.find( '.wcefp-flag' ).text( newLocaleInfo.flag );
			$switcher.find( '.wcefp-language-name' ).text( newLocaleInfo.name );

			// Regenerate dropdown options
			$switcher
				.find( '.wcefp-language-dropdown' )
				.html( this.generateLanguageOptions() );

			// Hide dropdown
			$switcher.find( '.wcefp-language-dropdown' ).hide();
		}

		/**
		 * Localize booking form
		 */
		localizeBookingForm() {
			const $bookingForm = $( '.wcefp-booking-form' );
			if ( ! $bookingForm.length ) return;

			// Update form labels and placeholders
			$bookingForm
				.find( 'label[for="wcefp-date"]' )
				.text( this.getTranslation( 'select_date', 'booking' ) );
			$bookingForm
				.find( 'label[for="wcefp-participants"]' )
				.text( this.getTranslation( 'participants', 'booking' ) );
			$bookingForm
				.find( '.wcefp-total-price' )
				.text( this.getTranslation( 'total_price', 'booking' ) );

			// Update button texts
			$bookingForm
				.find( '.wcefp-book-button' )
				.text( this.getTranslation( 'book_now', 'booking' ) );
			$bookingForm
				.find( '.wcefp-availability-button' )
				.text( this.getTranslation( 'check_availability', 'booking' ) );
		}

		/**
		 * Update booking form localization after locale change
		 */
		updateBookingFormLocalization() {
			this.localizeBookingForm();

			// Trigger form update event
			$( document ).trigger( 'wcefp_booking_form_localized', [
				this.currentLocale,
			] );
		}

		/**
		 * Show loading overlay during locale switch
		 */
		showLoadingOverlay() {
			if ( $( '.wcefp-loading-overlay' ).length ) return;

			$( 'body' ).append( `
                <div class="wcefp-loading-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                ">
                    <div style="
                        background: white;
                        padding: 20px;
                        border-radius: 8px;
                        text-align: center;
                    ">
                        <div style="
                            width: 40px;
                            height: 40px;
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #3498db;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 10px;
                        "></div>
                        <div>${ this.getTranslation(
							'loading',
							'booking'
						) }</div>
                    </div>
                </div>
            ` );

			// Add CSS animation for spinner
			if ( ! $( '#wcefp-spinner-css' ).length ) {
				$( 'head' ).append( `
                    <style id="wcefp-spinner-css">
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                ` );
			}
		}

		/**
		 * Hide loading overlay
		 */
		hideLoadingOverlay() {
			$( '.wcefp-loading-overlay' ).remove();
		}

		/**
		 * Show notification message
		 *
		 * @param  message
		 * @param  type
		 */
		showNotification( message, type = 'info' ) {
			// Use existing notification system if available
			if ( window.wcefpNotifications ) {
				window.wcefpNotifications.show( message, type );
			} else {
				// Simple fallback notification
				console.log( `[${ type.toUpperCase() }] ${ message }` );
			}
		}

		/**
		 * Load saved locale preference
		 */
		loadSavedLocale() {
			const savedLocale = localStorage.getItem(
				'wcefp_preferred_locale'
			);
			if (
				savedLocale &&
				this.supportedLocales[ savedLocale ] &&
				savedLocale !== this.currentLocale
			) {
				this.switchLocale( savedLocale );
			}
		}

		/**
		 * Get current locale
		 */
		getCurrentLocale() {
			return this.currentLocale;
		}

		/**
		 * Get locale information
		 *
		 * @param  locale
		 */
		getLocaleInfo( locale = null ) {
			if ( ! locale ) {
				locale = this.currentLocale;
			}
			return this.supportedLocales[ locale ];
		}
	}

	// Initialize i18n when DOM is ready
	$( document ).ready( function () {
		// Check if we have i18n data
		if ( typeof wcefp_i18n !== 'undefined' ) {
			window.wcefpI18n = new WCEFPInternationalization();

			// Load saved locale preference after initialization
			setTimeout( () => {
				window.wcefpI18n.loadSavedLocale();
			}, 100 );

			// Track initialization
			if (
				typeof WCEFPAnalytics !== 'undefined' &&
				window.wcefpAnalytics
			) {
				window.wcefpAnalytics.track( 'i18n_initialized', {
					current_locale: window.wcefpI18n.getCurrentLocale(),
					supported_locales: Object.keys(
						window.wcefpI18n.supportedLocales
					),
				} );
			}
		}
	} );
} )( jQuery );
