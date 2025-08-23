/**
 * WCEventsFP Enhanced Admin JavaScript
 * Provides interactive enhancements for the modern admin interface
 *
 * @since 2.1.3
 * @author WCEventsFP Team
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Enhanced KPI Cards Animation
		animateKpiCards();

		// Enhanced Button Interactions
		enhanceButtons();

		// Toast Notifications System
		initToastSystem();

		// Enhanced Product Tab Experience
		enhanceProductTabs();

		// Enhanced Form Interactions
		enhanceFormFields();

		// Admin Menu Enhancements
		enhanceAdminMenu();
	} );

	/**
	 * Animate KPI cards with staggered entrance
	 */
	function animateKpiCards() {
		$( '.wcefp-kpi-card' ).each( function ( index ) {
			const $card = $( this );

			// Add entrance animation with delay
			setTimeout( () => {
				$card.addClass( 'animate-in' );
			}, index * 150 );

			// Add hover effect enhancements
			$card.hover(
				function () {
					$( this ).find( '.wcefp-kpi-icon' ).addClass( 'bounce' );
				},
				function () {
					$( this ).find( '.wcefp-kpi-icon' ).removeClass( 'bounce' );
				}
			);
		} );

		// Add CSS for animations
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            .wcefp-kpi-card {
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.6s cubic-bezier(0.4, 0.0, 0.2, 1);
            }
            .wcefp-kpi-card.animate-in {
                opacity: 1;
                transform: translateY(0);
            }
            .wcefp-kpi-icon.bounce {
                animation: wcefp-bounce 0.6s ease-in-out;
            }
            @keyframes wcefp-bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
        `
			)
			.appendTo( 'head' );
	}

	/**
	 * Enhance button interactions
	 */
	function enhanceButtons() {
		// Add ripple effect to WCEFP buttons
		$( '.wcefp-btn' ).on( 'click', function ( e ) {
			const $button = $( this );
			const offset = $button.offset();
			const x = e.pageX - offset.left;
			const y = e.pageY - offset.top;

			const $ripple = $( '<span class="wcefp-ripple"></span>' );
			$ripple.css( {
				left: x + 'px',
				top: y + 'px',
			} );

			$button.append( $ripple );

			setTimeout( () => {
				$ripple.remove();
			}, 600 );
		} );

		// Add ripple CSS
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            .wcefp-btn {
                position: relative;
                overflow: hidden;
            }
            .wcefp-ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: wcefp-ripple-effect 0.6s linear;
                pointer-events: none;
            }
            @keyframes wcefp-ripple-effect {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `
			)
			.appendTo( 'head' );
	}

	/**
	 * Initialize toast notification system
	 */
	function initToastSystem() {
		window.wcefpShowToast = function (
			message,
			type = 'info',
			duration = 5000
		) {
			const toastId = 'wcefp-toast-' + Date.now();
			const $toast = $( `
                <div id="${ toastId }" class="wcefp-toast wcefp-toast-${ type }">
                    <div class="wcefp-toast-content">
                        <span class="wcefp-toast-message">${ message }</span>
                        <button class="wcefp-toast-close" aria-label="Chiudi">×</button>
                    </div>
                </div>
            ` );

			$( 'body' ).append( $toast );

			// Show toast
			setTimeout( () => {
				$toast.addClass( 'show' );
			}, 100 );

			// Auto-hide toast
			const hideTimer = setTimeout( () => {
				hideToast( toastId );
			}, duration );

			// Manual close
			$toast.find( '.wcefp-toast-close' ).on( 'click', () => {
				clearTimeout( hideTimer );
				hideToast( toastId );
			} );
		};

		function hideToast( toastId ) {
			const $toast = $( '#' + toastId );
			$toast.removeClass( 'show' );
			setTimeout( () => {
				$toast.remove();
			}, 300 );
		}

		// Add toast CSS
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            .wcefp-toast {
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 999999;
                min-width: 300px;
                max-width: 500px;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease-out;
            }
            .wcefp-toast.show {
                opacity: 1;
                transform: translateX(0);
            }
        `
			)
			.appendTo( 'head' );
	}

	/**
	 * Enhance product tabs experience
	 */
	function enhanceProductTabs() {
		// Add visual feedback when switching to WCEFP tab
		$( '#woocommerce-product-data' ).on(
			'click',
			'.wcefp_tab a',
			function () {
				setTimeout( () => {
					$( '#wcefp_product_data' ).addClass( 'tab-active' );
					animateFormFields();
				}, 100 );
			}
		);

		// Remove active class when switching away
		$( '#woocommerce-product-data' ).on(
			'click',
			'.wc-tab:not(.wcefp_tab) a',
			function () {
				$( '#wcefp_product_data' ).removeClass( 'tab-active' );
			}
		);

		function animateFormFields() {
			$( '#wcefp_product_data .form-field' ).each( function ( index ) {
				const $field = $( this );
				setTimeout( () => {
					$field.addClass( 'animate-in' );
				}, index * 50 );
			} );
		}

		// Add CSS for tab animations
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            #wcefp_product_data .form-field {
                opacity: 0;
                transform: translateY(10px);
                transition: all 0.4s ease-out;
            }
            #wcefp_product_data.tab-active .form-field.animate-in {
                opacity: 1;
                transform: translateY(0);
            }
            #wcefp_product_data h4 {
                animation: wcefp-pulse 2s infinite;
            }
            @keyframes wcefp-pulse {
                0%, 100% { box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1); }
                50% { box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3); }
            }
        `
			)
			.appendTo( 'head' );
	}

	/**
	 * Enhance form field interactions
	 */
	function enhanceFormFields() {
		// Enhanced focus effects for form fields
		$( document ).on(
			'focus',
			'#wcefp_product_data input, #wcefp_product_data textarea',
			function () {
				$( this ).closest( '.form-field' ).addClass( 'field-focused' );
			}
		);

		$( document ).on(
			'blur',
			'#wcefp_product_data input, #wcefp_product_data textarea',
			function () {
				$( this )
					.closest( '.form-field' )
					.removeClass( 'field-focused' );
			}
		);

		// Character counter for textarea fields
		$( '#wcefp_product_data textarea' ).each( function () {
			const $textarea = $( this );
			const maxLength = 500; // Default max length

			const $counter = $(
				'<div class="wcefp-char-counter"><span class="current">0</span>/<span class="max">' +
					maxLength +
					'</span></div>'
			);
			$textarea.after( $counter );

			$textarea.on( 'input', function () {
				const currentLength = $( this ).val().length;
				$counter.find( '.current' ).text( currentLength );

				if ( currentLength > maxLength * 0.9 ) {
					$counter.addClass( 'warning' );
				} else {
					$counter.removeClass( 'warning' );
				}
			} );
		} );

		// Add CSS for enhanced form interactions
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            .form-field.field-focused {
                transform: scale(1.01);
                z-index: 10;
                position: relative;
            }
            .wcefp-char-counter {
                font-size: 0.75rem;
                color: var(--wcefp-gray-500, #6b7280);
                text-align: right;
                margin-top: 4px;
            }
            .wcefp-char-counter.warning {
                color: var(--wcefp-warning, #f59e0b);
                font-weight: 600;
            }
        `
			)
			.appendTo( 'head' );
	}

	/**
	 * Enhance admin menu interactions
	 */
	function enhanceAdminMenu() {
		// Add visual feedback for menu items
		$( '#adminmenu .toplevel_page_wcefp' )
			.on( 'mouseenter', function () {
				$( this ).addClass( 'wcefp-menu-hover' );
			} )
			.on( 'mouseleave', function () {
				$( this ).removeClass( 'wcefp-menu-hover' );
			} );

		// Add notification badges (example)
		const $menuItem = $( '#adminmenu .toplevel_page_wcefp .wp-menu-name' );
		if ( $menuItem.length ) {
			// Example: Add notification count
			// $menuItem.append('<span class="wcefp-menu-badge">3</span>');
		}

		// Add CSS for menu enhancements
		$( '<style>' )
			.prop( 'type', 'text/css' )
			.html(
				`
            .wcefp-menu-hover .wp-menu-image {
                transform: scale(1.1);
                transition: transform 0.2s ease;
            }
            .wcefp-menu-badge {
                display: inline-block;
                background: var(--wcefp-error, #ef4444);
                color: white;
                border-radius: 50%;
                width: 18px;
                height: 18px;
                font-size: 10px;
                line-height: 18px;
                text-align: center;
                margin-left: 8px;
                font-weight: bold;
                animation: wcefp-badge-pulse 2s infinite;
            }
            @keyframes wcefp-badge-pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
        `
			)
			.appendTo( 'head' );
	}
} )( jQuery );
