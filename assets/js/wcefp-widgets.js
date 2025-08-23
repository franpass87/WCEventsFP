/**
 * WCEFP Frontend Widget System - Dynamic behavior and state management
 *
 * @package
 * @since 2.2.0
 */

( function ( $ ) {
	'use strict';

	// Widget system namespace
	window.WCEFPWidgets = {
		/**
		 * Initialize all widgets on page load
		 */
		init() {
			this.initFormWidgets();
			this.initBookingWidgets();
			this.initValidation();
			this.initAccessibility();
			this.initResponsive();
		},

		/**
		 * Initialize form widgets with enhanced behavior
		 */
		initFormWidgets() {
			const self = this;

			// Auto-resize textareas
			$( '.wcefp-widget textarea' ).on( 'input', function () {
				this.style.height = 'auto';
				this.style.height = this.scrollHeight + 'px';
			} );

			// Form validation on blur
			$( '.wcefp-widget .wcefp-form-control' ).on( 'blur', function () {
				self.validateField( $( this ) );
			} );

			// Real-time form validation
			$( '.wcefp-widget .wcefp-form-control' ).on( 'input', function () {
				const $field = $( this );
				if (
					$field
						.closest( '.wcefp-form-group' )
						.hasClass( 'has-error' )
				) {
					self.validateField( $field );
				}
			} );

			// Enhanced select dropdowns
			$( '.wcefp-widget select.wcefp-form-select' ).each( function () {
				self.enhanceSelect( $( this ) );
			} );
		},

		/**
		 * Initialize booking-specific widgets
		 */
		initBookingWidgets() {
			// Date picker enhancement
			$( '.wcefp-widget input[type="date"]' ).each( function () {
				const $input = $( this );
				const today = new Date().toISOString().split( 'T' )[ 0 ];

				// Set minimum date to today if not already set
				if ( ! $input.attr( 'min' ) ) {
					$input.attr( 'min', today );
				}

				// Add date format help text if not present
				if ( ! $input.next( '.wcefp-form-help' ).length ) {
					$input.after(
						'<span class="wcefp-form-help">Format: DD/MM/YYYY</span>'
					);
				}
			} );

			// Quantity selector enhancement
			$( '.wcefp-quantity-selector' ).each( function () {
				const $container = $( this );
				const $input = $container.find( 'input[type="number"]' );
				const $decreaseBtn = $container.find( '.wcefp-qty-decrease' );
				const $increaseBtn = $container.find( '.wcefp-qty-increase' );

				// Quantity decrease
				$decreaseBtn.on( 'click', function ( e ) {
					e.preventDefault();
					const currentVal = parseInt( $input.val() ) || 0;
					const minVal = parseInt( $input.attr( 'min' ) ) || 0;
					if ( currentVal > minVal ) {
						$input.val( currentVal - 1 ).trigger( 'change' );
					}
				} );

				// Quantity increase
				$increaseBtn.on( 'click', function ( e ) {
					e.preventDefault();
					const currentVal = parseInt( $input.val() ) || 0;
					const maxVal = parseInt( $input.attr( 'max' ) ) || 999;
					if ( currentVal < maxVal ) {
						$input.val( currentVal + 1 ).trigger( 'change' );
					}
				} );
			} );
		},

		/**
		 * Initialize form validation system
		 */
		initValidation() {
			const self = this;

			// Form submission validation
			$( '.wcefp-widget form' ).on( 'submit', function ( e ) {
				const $form = $( this );
				let isValid = true;

				// Validate all required fields
				$form
					.find( '.wcefp-form-control[required]' )
					.each( function () {
						if ( ! self.validateField( $( this ) ) ) {
							isValid = false;
						}
					} );

				if ( ! isValid ) {
					e.preventDefault();
					self.showMessage(
						$form,
						'error',
						'Please correct the errors below.'
					);

					// Focus on first error field
					const $firstError = $form
						.find( '.has-error .wcefp-form-control' )
						.first();
					if ( $firstError.length ) {
						$firstError.focus();
					}
				}
			} );
		},

		/**
		 * Initialize accessibility features
		 */
		initAccessibility() {
			// Add ARIA attributes to form controls
			$( '.wcefp-widget .wcefp-form-control' ).each( function () {
				const $field = $( this );
				const $label = $field
					.closest( '.wcefp-form-group' )
					.find( '.wcefp-form-label' );
				const $help = $field.siblings( '.wcefp-form-help' );

				// Connect label to field
				if ( $label.length && ! $field.attr( 'aria-labelledby' ) ) {
					const labelId =
						'wcefp-label-' +
						Math.random().toString( 36 ).substr( 2, 9 );
					$label.attr( 'id', labelId );
					$field.attr( 'aria-labelledby', labelId );
				}

				// Connect help text to field
				if ( $help.length && ! $field.attr( 'aria-describedby' ) ) {
					const helpId =
						'wcefp-help-' +
						Math.random().toString( 36 ).substr( 2, 9 );
					$help.attr( 'id', helpId );
					$field.attr( 'aria-describedby', helpId );
				}

				// Add required indicator
				if (
					$field.prop( 'required' ) &&
					! $field.attr( 'aria-required' )
				) {
					$field.attr( 'aria-required', 'true' );
				}
			} );

			// Add role attributes to validation messages
			$( '.wcefp-message' ).attr( 'role', 'alert' );

			// Announce loading states
			$( '.wcefp-loading' ).attr( 'aria-live', 'polite' );
		},

		/**
		 * Initialize responsive behavior
		 */
		initResponsive() {
			// Handle responsive form layouts
			const handleResize = () => {
				$( '.wcefp-widget' ).each( function () {
					const $widget = $( this );
					const width = $widget.width();

					$widget.toggleClass( 'wcefp-mobile', width < 600 );
					$widget.toggleClass(
						'wcefp-tablet',
						width >= 600 && width < 900
					);
					$widget.toggleClass( 'wcefp-desktop', width >= 900 );
				} );
			};

			$( window ).on( 'resize', handleResize );
			handleResize(); // Initial call
		},

		/**
		 * Validate individual form field
		 *
		 * @param {jQuery} $field - Field to validate
		 * @return {boolean} - Validation result
		 */
		validateField( $field ) {
			const $formGroup = $field.closest( '.wcefp-form-group' );
			const value = $field.val();
			const fieldType = $field.attr( 'type' ) || 'text';
			const isRequired = $field.prop( 'required' );
			let isValid = true;
			let errorMessage = '';

			// Remove previous validation classes
			$formGroup.removeClass( 'has-error has-success' );
			$formGroup.find( '.wcefp-form-help.error' ).remove();

			// Required validation
			if ( isRequired && ! value.trim() ) {
				isValid = false;
				errorMessage = 'This field is required.';
			}
			// Email validation
			else if (
				fieldType === 'email' &&
				value &&
				! this.isValidEmail( value )
			) {
				isValid = false;
				errorMessage = 'Please enter a valid email address.';
			}
			// Number validation
			else if ( fieldType === 'number' && value ) {
				const numValue = parseFloat( value );
				const min = parseFloat( $field.attr( 'min' ) );
				const max = parseFloat( $field.attr( 'max' ) );

				if ( isNaN( numValue ) ) {
					isValid = false;
					errorMessage = 'Please enter a valid number.';
				} else if ( ! isNaN( min ) && numValue < min ) {
					isValid = false;
					errorMessage = `Value must be at least ${ min }.`;
				} else if ( ! isNaN( max ) && numValue > max ) {
					isValid = false;
					errorMessage = `Value must not exceed ${ max }.`;
				}
			}
			// Date validation
			else if ( fieldType === 'date' && value ) {
				const selectedDate = new Date( value );
				const minDate = new Date( $field.attr( 'min' ) );
				const maxDate = new Date( $field.attr( 'max' ) );

				if ( isNaN( selectedDate.getTime() ) ) {
					isValid = false;
					errorMessage = 'Please enter a valid date.';
				} else if ( minDate && selectedDate < minDate ) {
					isValid = false;
					errorMessage = 'Please select a future date.';
				} else if ( maxDate && selectedDate > maxDate ) {
					isValid = false;
					errorMessage = 'Please select an earlier date.';
				}
			}

			// Update field appearance
			if ( isValid ) {
				$formGroup.addClass( 'has-success' );
				$field.attr( 'aria-invalid', 'false' );
			} else {
				$formGroup.addClass( 'has-error' );
				$field.attr( 'aria-invalid', 'true' );
				$field.after(
					`<span class="wcefp-form-help error" role="alert">${ errorMessage }</span>`
				);
			}

			return isValid;
		},

		/**
		 * Enhance select dropdown with better UX
		 *
		 * @param {jQuery} $select - Select element to enhance
		 */
		enhanceSelect( $select ) {
			// Add loading state for dynamic selects
			if ( $select.data( 'dynamic' ) ) {
				this.setLoading( $select, true );
				// Simulate dynamic loading (replace with actual AJAX)
				setTimeout( () => {
					this.setLoading( $select, false );
				}, 1000 );
			}

			// Add change event for dependent selects
			$select.on( 'change', function () {
				const $dependentSelects = $(
					'.wcefp-widget select[data-depends="' +
						$select.attr( 'id' ) +
						'"]'
				);
				if ( $dependentSelects.length ) {
					$dependentSelects.trigger( 'wcefp:reload' );
				}
			} );
		},

		/**
		 * Show loading state on element
		 *
		 * @param {jQuery}  $element - Element to show loading on
		 * @param {boolean} show     - Whether to show or hide loading
		 */
		setLoading( $element, show ) {
			if ( show ) {
				$element.addClass( 'wcefp-loading' ).prop( 'disabled', true );
			} else {
				$element
					.removeClass( 'wcefp-loading' )
					.prop( 'disabled', false );
			}
		},

		/**
		 * Show message in widget
		 *
		 * @param {jQuery} $container - Container to show message in
		 * @param {string} type       - Message type (success, error, warning, info)
		 * @param {string} message    - Message text
		 */
		showMessage( $container, type, message ) {
			const $existingMessage = $container.find( '.wcefp-message' );
			const $newMessage = $( `
                <div class="wcefp-message wcefp-message-${ type }" role="alert">
                    ${ message }
                </div>
            ` );

			if ( $existingMessage.length ) {
				$existingMessage.replaceWith( $newMessage );
			} else {
				$container.prepend( $newMessage );
			}

			// Auto-dismiss success messages
			if ( type === 'success' ) {
				setTimeout( () => {
					$newMessage.fadeOut( () => $newMessage.remove() );
				}, 5000 );
			}

			// Scroll to message if not visible
			const messageTop = $newMessage.offset().top;
			const windowTop = $( window ).scrollTop();
			const windowHeight = $( window ).height();

			if (
				messageTop < windowTop ||
				messageTop > windowTop + windowHeight
			) {
				$( 'html, body' ).animate(
					{
						scrollTop: messageTop - 100,
					},
					300
				);
			}
		},

		/**
		 * Validate email address
		 *
		 * @param {string} email - Email to validate
		 * @return {boolean} - Validation result
		 */
		isValidEmail( email ) {
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return emailRegex.test( email );
		},

		/**
		 * Submit form via AJAX with loading state
		 *
		 * @param {jQuery}   $form    - Form to submit
		 * @param {string}   action   - WordPress AJAX action
		 * @param {Function} callback - Success callback
		 */
		submitForm( $form, action, callback ) {
			const self = this;
			const $submitBtn = $form.find(
				'button[type="submit"], input[type="submit"]'
			);
			const originalText = $submitBtn.text() || $submitBtn.val();

			// Set loading state
			this.setLoading( $form, true );
			$submitBtn.text( 'Processing...' ).prop( 'disabled', true );

			// Prepare form data
			const formData = new FormData( $form[ 0 ] );
			formData.append( 'action', action );
			formData.append( 'nonce', WCEFPData.nonce );

			// Submit via AJAX
			$.ajax( {
				url: WCEFPData.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success( response ) {
					if ( response.success ) {
						self.showMessage(
							$form,
							'success',
							response.data.message ||
								'Form submitted successfully!'
						);
						if ( typeof callback === 'function' ) {
							callback( response );
						}
						$form[ 0 ].reset(); // Reset form on success
					} else {
						self.showMessage(
							$form,
							'error',
							response.data.message ||
								'An error occurred. Please try again.'
						);
					}
				},
				error() {
					self.showMessage(
						$form,
						'error',
						'Connection error. Please check your internet connection and try again.'
					);
				},
				complete() {
					// Remove loading state
					self.setLoading( $form, false );
					$submitBtn.text( originalText ).prop( 'disabled', false );
				},
			} );
		},

		/**
		 * Conditional asset loading for performance
		 *
		 * @param {string}   assetType - 'css' or 'js'
		 * @param {string}   url       - Asset URL
		 * @param {Function} callback  - Load callback
		 */
		loadAsset( assetType, url, callback ) {
			if ( assetType === 'css' ) {
				const link = document.createElement( 'link' );
				link.rel = 'stylesheet';
				link.href = url;
				link.onload = callback;
				document.head.appendChild( link );
			} else if ( assetType === 'js' ) {
				const script = document.createElement( 'script' );
				script.src = url;
				script.onload = callback;
				document.head.appendChild( script );
			}
		},
	};

	// Initialize widgets when DOM is ready
	$( document ).ready( function () {
		// Check if we have WCEFP widgets on the page
		if ( $( '.wcefp-widget' ).length > 0 ) {
			WCEFPWidgets.init();
		}
	} );

	// Re-initialize widgets for dynamically added content
	$( document ).on( 'wcefp:widgets:refresh', function () {
		WCEFPWidgets.init();
	} );
} )( jQuery );
