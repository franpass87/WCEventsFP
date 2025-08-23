/**
 * Mobile Check-in Interface JavaScript
 *
 * Handles QR code scanning, manual entry, and check-in processing
 * Part of Phase 5: Advanced Booking Features
 *
 * @package
 * @subpackage Assets
 * @since 2.2.0
 */

( function ( $ ) {
	'use strict';

	class WCEFPMobileCheckin {
		constructor() {
			this.scanner = null;
			this.video = null;
			this.canvas = null;
			this.scannerActive = false;
			this.currentBookingData = null;

			this.init();
		}

		init() {
			this.bindEvents();
			this.initScanner();
			this.checkCameraPermissions();
		}

		bindEvents() {
			// Tab switching
			$( '.wcefp-tab-button' ).on(
				'click',
				this.handleTabSwitch.bind( this )
			);

			// Scanner controls
			$( '#wcefp-start-scanner' ).on(
				'click',
				this.startScanner.bind( this )
			);
			$( '#wcefp-stop-scanner' ).on(
				'click',
				this.stopScanner.bind( this )
			);

			// Manual check-in form
			$( '#wcefp-manual-checkin-form' ).on(
				'submit',
				this.handleManualCheckin.bind( this )
			);

			// Modal controls
			$( '.wcefp-modal-close' ).on(
				'click',
				this.closeModal.bind( this )
			);
			$( '#wcefp-confirm-checkin' ).on(
				'click',
				this.confirmCheckin.bind( this )
			);

			// Retry button
			$( '#wcefp-retry-checkin' ).on(
				'click',
				this.resetInterface.bind( this )
			);

			// Auto-fill from URL parameters
			this.checkUrlParameters();
		}

		handleTabSwitch( e ) {
			e.preventDefault();

			const tabButton = $( e.currentTarget );
			const tabName = tabButton.data( 'tab' );

			// Update tab buttons
			$( '.wcefp-tab-button' ).removeClass( 'active' );
			tabButton.addClass( 'active' );

			// Update tab content
			$( '.wcefp-tab-content' ).removeClass( 'active' );
			$( `#${ tabName }-tab` ).addClass( 'active' );

			// Stop scanner when switching away from QR tab
			if ( tabName !== 'qr-scan' && this.scannerActive ) {
				this.stopScanner();
			}
		}

		async checkCameraPermissions() {
			try {
				const permissions = await navigator.permissions.query( {
					name: 'camera',
				} );

				if ( permissions.state === 'denied' ) {
					this.showScannerMessage(
						wcefp_checkin.strings.camera_denied ||
							'Camera access denied. Please use manual entry.',
						'error'
					);
					$( '.wcefp-tab-button[data-tab="manual-entry"]' ).click();
				}
			} catch ( error ) {
				// Permissions API not supported, continue normally
				console.log( 'Permissions API not supported' );
			}
		}

		initScanner() {
			this.video = document.getElementById( 'wcefp-scanner-video' );
			this.canvas = document.getElementById( 'wcefp-scanner-canvas' );

			if ( ! this.video || ! this.canvas ) {
				console.error( 'Scanner elements not found' );
				return;
			}

			// Check if getUserMedia is supported
			if (
				! navigator.mediaDevices ||
				! navigator.mediaDevices.getUserMedia
			) {
				this.showScannerMessage(
					wcefp_checkin.strings.camera_not_supported ||
						'Camera not supported on this device',
					'error'
				);
				$( '.wcefp-tab-button[data-tab="manual-entry"]' ).click();
			}
		}

		async startScanner() {
			try {
				this.showLoading( true );

				const stream = await navigator.mediaDevices.getUserMedia( {
					video: {
						facingMode: 'environment', // Prefer rear camera
						width: { ideal: 640 },
						height: { ideal: 480 },
					},
				} );

				this.video.srcObject = stream;
				this.scannerActive = true;

				$( '#wcefp-start-scanner' ).hide();
				$( '#wcefp-stop-scanner' ).show();

				this.showScannerMessage(
					wcefp_checkin.strings.scan_qr ||
						'Position QR code within the frame',
					'info'
				);

				// Start scanning loop
				this.video.addEventListener( 'loadedmetadata', () => {
					this.canvas.width = this.video.videoWidth;
					this.canvas.height = this.video.videoHeight;
					this.scanLoop();
				} );

				this.showLoading( false );
			} catch ( error ) {
				console.error( 'Error starting scanner:', error );
				this.showScannerMessage(
					wcefp_checkin.strings.camera_error ||
						'Failed to access camera',
					'error'
				);
				this.showLoading( false );
			}
		}

		stopScanner() {
			this.scannerActive = false;

			if ( this.video && this.video.srcObject ) {
				const tracks = this.video.srcObject.getTracks();
				tracks.forEach( ( track ) => track.stop() );
				this.video.srcObject = null;
			}

			$( '#wcefp-start-scanner' ).show();
			$( '#wcefp-stop-scanner' ).hide();

			this.showScannerMessage( '', '' );
		}

		scanLoop() {
			if ( ! this.scannerActive ) return;

			try {
				const context = this.canvas.getContext( '2d' );
				context.drawImage(
					this.video,
					0,
					0,
					this.canvas.width,
					this.canvas.height
				);

				const imageData = context.getImageData(
					0,
					0,
					this.canvas.width,
					this.canvas.height
				);
				const code = this.decodeQR( imageData );

				if ( code ) {
					this.handleQRCode( code );
					return;
				}
			} catch ( error ) {
				console.error( 'Error in scan loop:', error );
			}

			// Continue scanning
			requestAnimationFrame( this.scanLoop.bind( this ) );
		}

		// Simple QR code detection (basic implementation)
		// In production, you might want to use a library like jsQR
		decodeQR( imageData ) {
			// This is a placeholder - in a real implementation, you would use
			// a QR code detection library like jsQR or QuaggaJS

			// For now, we'll simulate detection by checking if certain patterns exist
			// You should replace this with actual QR code detection
			return null;
		}

		handleQRCode( qrData ) {
			this.stopScanner();

			try {
				// Parse QR code data (should be a URL with check-in parameters)
				const url = new URL( qrData );
				const params = new URLSearchParams( url.search );

				const token = params.get( 'token' );
				const bookingId = params.get( 'booking' );

				if ( ! token || ! bookingId ) {
					this.showError(
						wcefp_checkin.strings.invalid_qr || 'Invalid QR code'
					);
					return;
				}

				// Get booking details and show confirmation modal
				this.getBookingDetails( bookingId, token );
			} catch ( error ) {
				console.error( 'Error parsing QR code:', error );
				this.showError(
					wcefp_checkin.strings.invalid_qr || 'Invalid QR code format'
				);
			}
		}

		async getBookingDetails( bookingId, token ) {
			try {
				this.showLoading( true );

				const response = await $.ajax( {
					url: wcefp_checkin.ajax_url,
					method: 'POST',
					data: {
						action: 'wcefp_get_booking_details',
						booking_id: bookingId,
						token,
						nonce: wcefp_checkin.nonce,
					},
				} );

				if ( response.success ) {
					this.showBookingModal( response.data, token );
				} else {
					this.showError(
						response.data.message || wcefp_checkin.strings.error
					);
				}
			} catch ( error ) {
				console.error( 'Error getting booking details:', error );
				this.showError(
					wcefp_checkin.strings.error || 'An error occurred'
				);
			} finally {
				this.showLoading( false );
			}
		}

		showBookingModal( bookingData, token ) {
			this.currentBookingData = { ...bookingData, token };

			// Populate modal with booking details
			$( '#wcefp-modal-event-title' ).text(
				bookingData.event_title || ''
			);
			$( '#wcefp-modal-customer-name' ).text(
				bookingData.customer_name || ''
			);
			$( '#wcefp-modal-booking-date' ).text(
				bookingData.booking_date || ''
			);

			// Show modal
			$( '#wcefp-quick-checkin-modal' ).fadeIn( 300 );
		}

		closeModal() {
			$( '#wcefp-quick-checkin-modal' ).fadeOut( 300 );
			this.currentBookingData = null;
		}

		handleManualCheckin( e ) {
			e.preventDefault();

			const formData = {
				token: $( '#wcefp-token' ).val().trim(),
				booking_id: $( '#wcefp-booking-id' ).val().trim(),
				location: $( '#wcefp-location' ).val().trim(),
				notes: $( '#wcefp-notes' ).val().trim(),
			};

			this.processCheckin( formData );
		}

		confirmCheckin() {
			if ( ! this.currentBookingData ) return;

			const formData = {
				token: this.currentBookingData.token,
				booking_id: this.currentBookingData.booking_id,
				location: $( '#wcefp-modal-location' ).val().trim(),
				notes: $( '#wcefp-modal-notes' ).val().trim(),
			};

			this.closeModal();
			this.processCheckin( formData );
		}

		async processCheckin( formData ) {
			try {
				this.showLoading( true );
				this.hideError();

				const response = await $.ajax( {
					url: wcefp_checkin.ajax_url,
					method: 'POST',
					data: {
						action: 'wcefp_check_in_booking',
						...formData,
						nonce: wcefp_checkin.nonce,
					},
				} );

				if ( response.success ) {
					this.showSuccess( response.data );
				} else {
					this.showError(
						response.data.message || wcefp_checkin.strings.error
					);
				}
			} catch ( error ) {
				console.error( 'Error processing check-in:', error );
				this.showError(
					wcefp_checkin.strings.error ||
						'An error occurred during check-in'
				);
			} finally {
				this.showLoading( false );
			}
		}

		showSuccess( data ) {
			const statusText = `${
				wcefp_checkin.strings.success || 'Check-in successful!'
			} 
                              ${
									data.checkin_time
										? `at ${ data.checkin_time }`
										: ''
								}`;

			$( '#wcefp-status-details' ).text( statusText );
			$( '#wcefp-checkin-status' ).fadeIn( 300 );

			// Hide form after successful check-in
			$( '#wcefp-mobile-checkin .wcefp-tab-content' ).hide();
			$( '#wcefp-mobile-checkin .wcefp-checkin-tabs' ).hide();
		}

		showError( message ) {
			$( '#wcefp-error-details' ).text( message );
			$( '#wcefp-checkin-error' ).fadeIn( 300 );
		}

		hideError() {
			$( '#wcefp-checkin-error' ).fadeOut( 300 );
		}

		showLoading( show ) {
			if ( show ) {
				$( '#wcefp-checkin-loading' ).fadeIn( 300 );
			} else {
				$( '#wcefp-checkin-loading' ).fadeOut( 300 );
			}
		}

		showScannerMessage( message, type = '' ) {
			const messageElement = $( '#wcefp-scanner-message' );
			messageElement.text( message );
			messageElement.removeClass( 'info error warning' );

			if ( type ) {
				messageElement.addClass( type );
			}
		}

		resetInterface() {
			// Hide all status displays
			$( '#wcefp-checkin-status' ).hide();
			$( '#wcefp-checkin-error' ).hide();
			$( '#wcefp-checkin-loading' ).hide();

			// Show form elements
			$( '#wcefp-mobile-checkin .wcefp-tab-content' ).show();
			$( '#wcefp-mobile-checkin .wcefp-checkin-tabs' ).show();

			// Clear form
			$( '#wcefp-manual-checkin-form' )[ 0 ].reset();

			// Reset scanner
			if ( this.scannerActive ) {
				this.stopScanner();
			}

			// Reset current booking data
			this.currentBookingData = null;
		}

		checkUrlParameters() {
			const urlParams = new URLSearchParams( window.location.search );
			const token = urlParams.get( 'token' );
			const bookingId = urlParams.get( 'booking' );

			if ( token && bookingId ) {
				// Auto-fill manual entry form
				$( '#wcefp-token' ).val( token );
				$( '#wcefp-booking-id' ).val( bookingId );

				// Switch to manual entry tab
				$( '.wcefp-tab-button[data-tab="manual-entry"]' ).click();

				// Auto-submit if both values are present
				setTimeout( () => {
					this.processCheckin( { token, booking_id: bookingId } );
				}, 500 );
			}
		}
	}

	// Initialize when document is ready
	$( document ).ready( function () {
		if ( $( '#wcefp-mobile-checkin' ).length ) {
			new WCEFPMobileCheckin();
		}
	} );

	// Export for global access if needed
	window.WCEFPMobileCheckin = WCEFPMobileCheckin;
} )( jQuery );
