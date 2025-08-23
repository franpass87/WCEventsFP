/**
 * WCEventsFP Admin Settings JavaScript
 * Handles tab navigation and enhanced settings functionality
 */

( function ( $ ) {
	'use strict';

	// Settings object
	const WCEFPSettings = {
		/**
		 * Initialize settings page functionality
		 */
		init() {
			this.bindEvents();
			this.initTabs();
			this.initHelpTips();
			this.initFormValidation();
			this.initRateLimiterControls();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Tab navigation
			$( document ).on(
				'click',
				'.wcefp-nav-tab-wrapper .nav-tab',
				this.handleTabClick.bind( this )
			);

			// Form submission
			$( document ).on(
				'submit',
				'.wcefp-settings-form',
				this.handleFormSubmit.bind( this )
			);

			// Keyboard navigation for tabs
			$( document ).on(
				'keydown',
				'.wcefp-nav-tab-wrapper .nav-tab',
				this.handleTabKeydown.bind( this )
			);

			// Help tips
			$( document ).on(
				'mouseenter',
				'.wcefp-help-tip',
				this.showHelpTip.bind( this )
			);
			$( document ).on(
				'mouseleave',
				'.wcefp-help-tip',
				this.hideHelpTip.bind( this )
			);
		},

		/**
		 * Initialize tab functionality
		 */
		initTabs() {
			// Get current tab from URL or set default
			const urlParams = new URLSearchParams( window.location.search );
			const currentTab = urlParams.get( 'tab' ) || 'general';

			// Update active tab
			this.switchTab( currentTab, false );
		},

		/**
		 * Handle tab click events
		 *
		 * @param  e
		 */
		handleTabClick( e ) {
			e.preventDefault();

			const $tab = $( e.currentTarget );
			const targetTab = $tab.data( 'tab' );

			// Only switch if not already active
			if ( ! $tab.hasClass( 'nav-tab-active' ) ) {
				this.switchTab( targetTab, true );
			}
		},

		/**
		 * Handle keyboard navigation for tabs
		 *
		 * @param  e
		 */
		handleTabKeydown( e ) {
			const $currentTab = $( e.currentTarget );
			const $tabs = $( '.wcefp-nav-tab-wrapper .nav-tab' );
			const currentIndex = $tabs.index( $currentTab );
			let targetIndex = currentIndex;

			switch ( e.which ) {
				case 37: // Left arrow
					targetIndex =
						currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
					e.preventDefault();
					break;
				case 39: // Right arrow
					targetIndex =
						currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
					e.preventDefault();
					break;
				case 13: // Enter
				case 32: // Space
					$currentTab.trigger( 'click' );
					e.preventDefault();
					break;
				default:
					return;
			}

			if ( targetIndex !== currentIndex ) {
				$tabs.eq( targetIndex ).focus();
			}
		},

		/**
		 * Switch to a specific tab
		 *
		 * @param  tabKey
		 * @param  updateUrl
		 */
		switchTab( tabKey, updateUrl ) {
			const $tabs = $( '.wcefp-nav-tab-wrapper .nav-tab' );
			const $targetTab = $tabs.filter( '[data-tab="' + tabKey + '"]' );

			if ( $targetTab.length === 0 ) {
				return;
			}

			// Update active tab
			$tabs.removeClass( 'nav-tab-active' );
			$targetTab.addClass( 'nav-tab-active' );

			// Update URL if requested and browser supports it
			if ( updateUrl && history.pushState ) {
				const url = new URL( window.location );
				url.searchParams.set( 'tab', tabKey );
				history.pushState( {}, '', url );
			}

			// Show loading state briefly for better UX
			this.showLoadingState();

			// Redirect to new tab with server-side fallback after brief delay
			setTimeout( () => {
				if ( updateUrl ) {
					window.location.href = $targetTab.attr( 'href' );
				} else {
					this.hideLoadingState();
				}
			}, 100 );
		},

		/**
		 * Initialize help tips
		 */
		initHelpTips() {
			// Add help icons where needed
			$( '.wcefp-settings-form .description' ).each( function () {
				const $description = $( this );
				const $field = $description.prev( 'input, textarea, select' );

				if ( $field.length > 0 ) {
					$field.attr(
						'aria-describedby',
						$description.attr( 'id' )
					);
				}
			} );
		},

		/**
		 * Show help tip
		 *
		 * @param  e
		 */
		showHelpTip( e ) {
			const $tip = $( e.currentTarget );
			$tip.addClass( 'wcefp-help-tip-visible' );
		},

		/**
		 * Hide help tip
		 *
		 * @param  e
		 */
		hideHelpTip( e ) {
			const $tip = $( e.currentTarget );
			$tip.removeClass( 'wcefp-help-tip-visible' );
		},

		/**
		 * Initialize form validation
		 */
		initFormValidation() {
			// Real-time JSON validation for price rules
			$( document ).on(
				'input',
				'#wcefp_price_rules',
				this.validatePriceRules.bind( this )
			);

			// Email validation
			$( document ).on(
				'blur',
				'input[type="email"]',
				this.validateEmail.bind( this )
			);

			// Number validation
			$( document ).on(
				'input',
				'input[type="number"]',
				this.validateNumber.bind( this )
			);
		},

		/**
		 * Validate price rules JSON
		 *
		 * @param  e
		 */
		validatePriceRules( e ) {
			const $field = $( e.currentTarget );
			const value = $field.val().trim();

			if ( value === '' ) {
				this.clearFieldError( $field );
				return;
			}

			try {
				JSON.parse( value );
				this.clearFieldError( $field );
				this.showFieldSuccess( $field, 'JSON valido' );
			} catch ( error ) {
				this.showFieldError( $field, 'Formato JSON non valido' );
			}
		},

		/**
		 * Validate email fields
		 *
		 * @param  e
		 */
		validateEmail( e ) {
			const $field = $( e.currentTarget );
			const email = $field.val().trim();

			if ( email === '' ) {
				this.clearFieldError( $field );
				return;
			}

			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if ( emailRegex.test( email ) ) {
				this.clearFieldError( $field );
			} else {
				this.showFieldError( $field, 'Formato email non valido' );
			}
		},

		/**
		 * Validate number fields
		 *
		 * @param  e
		 */
		validateNumber( e ) {
			const $field = $( e.currentTarget );
			const value = $field.val();
			const min = parseFloat( $field.attr( 'min' ) ) || 0;
			const max = parseFloat( $field.attr( 'max' ) ) || Infinity;

			if ( value === '' ) {
				this.clearFieldError( $field );
				return;
			}

			const numValue = parseFloat( value );
			if ( isNaN( numValue ) || numValue < min || numValue > max ) {
				let message = 'Valore non valido';
				if ( min > 0 && max < Infinity ) {
					message = `Valore deve essere tra ${ min } e ${ max }`;
				} else if ( min > 0 ) {
					message = `Valore deve essere almeno ${ min }`;
				} else if ( max < Infinity ) {
					message = `Valore deve essere al massimo ${ max }`;
				}
				this.showFieldError( $field, message );
			} else {
				this.clearFieldError( $field );
			}
		},

		/**
		 * Show field error
		 *
		 * @param  $field
		 * @param  message
		 */
		showFieldError( $field, message ) {
			this.clearFieldMessages( $field );

			const $error = $(
				'<div class="wcefp-field-error" style="color: #d63638; font-size: 12px; margin-top: 5px;">'
			).text( message );

			$field
				.addClass( 'wcefp-field-invalid' )
				.css( 'border-color', '#d63638' );
			$field.after( $error );
		},

		/**
		 * Show field success
		 *
		 * @param  $field
		 * @param  message
		 */
		showFieldSuccess( $field, message ) {
			this.clearFieldMessages( $field );

			const $success = $(
				'<div class="wcefp-field-success" style="color: #00a32a; font-size: 12px; margin-top: 5px;">'
			).text( message );

			$field
				.removeClass( 'wcefp-field-invalid' )
				.css( 'border-color', '' );
			$field.after( $success );

			// Remove success message after 3 seconds
			setTimeout( () => {
				$success.fadeOut( () => $success.remove() );
			}, 3000 );
		},

		/**
		 * Clear field error
		 *
		 * @param  $field
		 */
		clearFieldError( $field ) {
			$field
				.removeClass( 'wcefp-field-invalid' )
				.css( 'border-color', '' );
			this.clearFieldMessages( $field );
		},

		/**
		 * Clear all field messages
		 *
		 * @param  $field
		 */
		clearFieldMessages( $field ) {
			$field
				.nextAll( '.wcefp-field-error, .wcefp-field-success' )
				.remove();
		},

		/**
		 * Handle form submission
		 *
		 * @param  e
		 */
		handleFormSubmit( e ) {
			const $form = $( e.currentTarget );

			// Check for validation errors
			if ( $form.find( '.wcefp-field-invalid' ).length > 0 ) {
				e.preventDefault();
				this.showNotice(
					'Correggi gli errori nel modulo prima di salvare.',
					'error'
				);
				return false;
			}

			// Show loading state
			this.showLoadingState();

			// Show saving message
			this.showNotice( 'Salvataggio in corso...', 'info' );
		},

		/**
		 * Show loading state
		 */
		showLoadingState() {
			$( '.wcefp-settings-form' ).addClass( 'loading' );
			$( 'input[type="submit"]' ).prop( 'disabled', true );
		},

		/**
		 * Hide loading state
		 */
		hideLoadingState() {
			$( '.wcefp-settings-form' ).removeClass( 'loading' );
			$( 'input[type="submit"]' ).prop( 'disabled', false );
		},

		/**
		 * Show notice message
		 *
		 * @param  message
		 * @param  type
		 */
		showNotice( message, type ) {
			type = type || 'info';

			// Remove existing notices
			$( '.wcefp-settings-wrap .notice.wcefp-notice' ).remove();

			const $notice = $(
				'<div class="notice wcefp-notice notice-' +
					type +
					' is-dismissible">'
			)
				.append( '<p>' + message + '</p>' )
				.prependTo( '.wcefp-settings-wrap' );

			// Auto-remove info notices after 5 seconds
			if ( type === 'info' ) {
				setTimeout( () => {
					$notice.fadeOut( () => $notice.remove() );
				}, 5000 );
			}

			// Add dismiss functionality
			$notice.on( 'click', '.notice-dismiss', function () {
				$notice.fadeOut( () => $notice.remove() );
			} );
		},

		/**
		 * Utility function to get URL parameter
		 *
		 * @param  name
		 */
		getUrlParameter( name ) {
			const urlParams = new URLSearchParams( window.location.search );
			return urlParams.get( name );
		},

		/**
		 * Initialize tooltips for help icons
		 */
		initTooltips() {
			// Create tooltip element
			if ( $( '#wcefp-tooltip' ).length === 0 ) {
				$( 'body' ).append(
					'<div id="wcefp-tooltip" class="wcefp-tooltip"></div>'
				);
			}

			const $tooltip = $( '#wcefp-tooltip' );

			$( document ).on(
				'mouseenter',
				'[data-wcefp-tooltip]',
				function ( e ) {
					const message = $( this ).data( 'wcefp-tooltip' );
					$tooltip.text( message ).show();

					// Position tooltip
					const rect = e.target.getBoundingClientRect();
					$tooltip.css( {
						top: rect.bottom + window.scrollY + 5,
						left: rect.left + window.scrollX,
					} );
				}
			);

			$( document ).on(
				'mouseleave',
				'[data-wcefp-tooltip]',
				function () {
					$tooltip.hide();
				}
			);
		},

		/**
		 * Initialize rate limiter controls
		 */
		initRateLimiterControls() {
			const saveButton = document.getElementById('save-rate-limits');
			const resetButton = document.getElementById('reset-rate-limits');
			
			if (saveButton) {
				saveButton.addEventListener('click', (e) => {
					e.preventDefault();
					
					const form = document.getElementById('wcefp-rate-limits-form');
					if (!form) return;
					
					const formData = new FormData(form);
					formData.append('action', 'wcefp_update_rate_limits');
					
					fetch(ajaxurl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							this.showNotice('Rate limits updated successfully', 'success');
							setTimeout(() => location.reload(), 1500);
						} else {
							this.showNotice('Failed to update rate limits', 'error');
						}
					})
					.catch(error => {
						console.error('Rate limiter error:', error);
						this.showNotice('Network error occurred', 'error');
					});
				});
			}
			
			if (resetButton) {
				resetButton.addEventListener('click', () => {
					if (!confirm(wcefpSettings?.strings?.resetRateLimitsConfirm || 'Reset all rate limits to default values?')) {
						return;
					}
					
					const formData = new FormData();
					formData.append('action', 'wcefp_update_rate_limits');
					formData.append('reset', '1');
					formData.append('_wpnonce', wcefpSettings?.nonce || '');
					
					fetch(ajaxurl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							location.reload();
						}
					})
					.catch(error => {
						console.error('Rate limiter reset error:', error);
					});
				});
			}
		},
	};

	// Initialize when DOM is ready
	$( document ).ready( function () {
		WCEFPSettings.init();
	} );

	// Expose globally for potential extensions
	window.WCEFPSettings = WCEFPSettings;
} )( jQuery );
