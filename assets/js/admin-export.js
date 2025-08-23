/**
 * Admin Export Functionality
 *
 * Handles CSV and ICS export functionality with WordPress-native modals.
 * Part of Phase 3: Data & Integration
 */
( function ( $ ) {
	'use strict';

	const WCEFPExport = {
		init() {
			this.bindEvents();
		},

		bindEvents() {
			$( '#wcefp-export-bookings-form' ).on(
				'submit',
				this.handleBookingsExport
			);
			$( '#wcefp-export-calendar-form' ).on(
				'submit',
				this.handleCalendarExport
			);
			$( '.wcefp-feed-url button' ).on( 'click', this.copyFeedUrl );
		},

		handleBookingsExport( e ) {
			e.preventDefault();

			const $form = $( this );
			const $button = $form.find( 'button[type="submit"]' );
			const originalText = $button.text();

			// Show loading state
			$button
				.prop( 'disabled', true )
				.html(
					'<span class="dashicons dashicons-update-alt spin"></span> ' +
						wcefpExport.strings.exporting
				);

			const formData = {
				action: 'wcefp_export_bookings',
				nonce: wcefpExport.nonce,
				date_from: $form.find( '[name="date_from"]' ).val(),
				date_to: $form.find( '[name="date_to"]' ).val(),
				status: $form.find( '[name="status"]' ).val(),
				event_id: $form.find( '[name="event_id"]' ).val(),
			};

			$.ajax( {
				url: wcefpExport.ajaxurl,
				type: 'POST',
				data: formData,
				success( response ) {
					if ( response.success ) {
						WCEFPExport.downloadFile(
							response.data.filename,
							response.data.content,
							'text/csv'
						);
						WCEFPModals.showSuccess(
							wcefpExport.strings.success +
								'\n' +
								sprintf(
									'Downloaded %d bookings.',
									response.data.count
								)
						);
					} else {
						WCEFPModals.showError(
							response.data.message || wcefpExport.strings.error
						);
					}
				},
				error() {
					WCEFPModals.showError( wcefpExport.strings.error );
				},
				complete() {
					$button.prop( 'disabled', false ).text( originalText );
				},
			} );
		},

		handleCalendarExport( e ) {
			e.preventDefault();

			const $form = $( this );
			const $button = $form.find( 'button[type="submit"]' );
			const originalText = $button.text();

			// Show loading state
			$button
				.prop( 'disabled', true )
				.html(
					'<span class="dashicons dashicons-update-alt spin"></span> ' +
						wcefpExport.strings.exporting
				);

			const formData = {
				action: 'wcefp_export_calendar',
				nonce: wcefpExport.nonce,
				event_id: $form.find( '[name="event_id"]' ).val(),
				date_range: $form.find( '[name="date_range"]' ).val(),
			};

			$.ajax( {
				url: wcefpExport.ajaxurl,
				type: 'POST',
				data: formData,
				success( response ) {
					if ( response.success ) {
						WCEFPExport.downloadFile(
							response.data.filename,
							response.data.content,
							'text/calendar'
						);
						WCEFPModals.showSuccess(
							wcefpExport.strings.success +
								'\n' +
								sprintf(
									'Downloaded calendar with %d events.',
									response.data.count
								)
						);
					} else {
						WCEFPModals.showError(
							response.data.message || wcefpExport.strings.error
						);
					}
				},
				error() {
					WCEFPModals.showError( wcefpExport.strings.error );
				},
				complete() {
					$button.prop( 'disabled', false ).text( originalText );
				},
			} );
		},

		downloadFile( filename, base64Content, mimeType ) {
			// Convert base64 to blob
			const byteCharacters = atob( base64Content );
			const byteNumbers = new Array( byteCharacters.length );

			for ( let i = 0; i < byteCharacters.length; i++ ) {
				byteNumbers[ i ] = byteCharacters.charCodeAt( i );
			}

			const byteArray = new Uint8Array( byteNumbers );
			const blob = new Blob( [ byteArray ], { type: mimeType } );

			// Create download link
			const url = window.URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = filename;

			// Trigger download
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );

			// Clean up
			window.URL.revokeObjectURL( url );
		},

		copyFeedUrl( e ) {
			const $button = $( this );
			const $input = $button.siblings( 'input' );
			const url = $input.val();

			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( url ).then( () => {
					const originalText = $button.text();
					$button.text( 'Copied!' );
					setTimeout( () => {
						$button.text( originalText );
					}, 2000 );
				} );
			} else {
				// Fallback for older browsers
				$input.select();
				document.execCommand( 'copy' );

				const originalText = $button.text();
				$button.text( 'Copied!' );
				setTimeout( () => {
					$button.text( originalText );
				}, 2000 );
			}
		},
	};

	// Simple sprintf implementation
	function sprintf( str, ...args ) {
		let i = 0;
		return str.replace( /%[sd]/g, () => args[ i++ ] );
	}

	// Initialize when document is ready
	$( document ).ready( function () {
		WCEFPExport.init();
	} );
} )( jQuery );
