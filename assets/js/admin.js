( function ( $ ) {
	$( function () {
		// --------- Product Admin Form Validation & Enhancements ---------
		if ( $( '#wcefp_product_data' ).length ) {
			initProductAdminEnhancements();
		}

		// --------- Toolbar Calendario/Lista ----------
		const $filter = $( '#wcefp-filter-product' );
		if ( window.WCEFPAdmin && Array.isArray( WCEFPAdmin.products ) ) {
			WCEFPAdmin.products.forEach( ( p ) => {
				$filter.append(
					`<option value="${ p.id }">${ p.title }</option>`
				);
			} );
		}

		const $view = $( '#wcefp-view' );

		function loadCalendar() {
			$view.empty();
			const cal = $( '<div id="wcefp-calendar"></div>' ).appendTo(
				$view
			);
			const now = new Date();
			const from = new Date( now.getFullYear(), now.getMonth() - 1, 1 )
				.toISOString()
				.slice( 0, 10 );
			const to = new Date( now.getFullYear(), now.getMonth() + 2, 0 )
				.toISOString()
				.slice( 0, 10 );
			const product_id = parseInt(
				$( '#wcefp-filter-product' ).val() || '0',
				10
			);

			$.post(
				WCEFPAdmin.ajaxUrl,
				{
					action: 'wcefp_get_calendar',
					nonce: WCEFPAdmin.nonce,
					from,
					to,
					product_id,
				},
				function ( r ) {
					const events = r && r.success ? r.data.events : [];
					// FullCalendar può non essere caricato sulle altre pagine del menu
					if (
						typeof FullCalendar === 'undefined' ||
						! FullCalendar.Calendar
					) {
						cal.html(
							'<p>Calendario non disponibile su questa pagina.</p>'
						);
						return;
					}
					const calendar = new FullCalendar.Calendar( cal[ 0 ], {
						initialView: 'dayGridMonth',
						height: 650,
						events,
						headerToolbar: {
							left: 'prev,next today',
							center: 'title',
							right: 'dayGridMonth,timeGridWeek,listWeek',
						},
						eventClick( info ) {
							const e = info.event;
							const occ = e.id;
							const ep = e.extendedProps || {};
							const currentCap = parseInt( ep.capacity || 0, 10 );
							const currentStatus = ep.status || 'active';

							const newCapStr = WCEFPModals.showPrompt(
								'Nuova capienza per questo slot:',
								currentCap,
								function ( newCapStr ) {
									if (
										newCapStr === null ||
										newCapStr === ''
									)
										return;
									const newCap = parseInt( newCapStr, 10 );
									if (
										Number.isNaN( newCap ) ||
										newCap < 0
									) {
										WCEFPModals.showError(
											'Valore non valido'
										);
										return;
									}

									WCEFPModals.showConfirm(
										'Vuoi alternare lo stato (attivo/disattivato)?',
										function () {
											// OK = alterna
											const nextStatus =
												currentStatus === 'active'
													? 'cancelled'
													: 'active';
											updateOccurrence(
												occ,
												newCap,
												nextStatus
											);
										},
										function () {
											// Annulla = lascia invariato
											updateOccurrence(
												occ,
												newCap,
												currentStatus
											);
										}
									);
								}
							);
						},
						eventDidMount( arg ) {
							// Tooltip semplice con capienza
							const ep = arg.event.extendedProps || {};
							const tip = `${ ep.booked || 0 }/${
								ep.capacity || 0
							}`;
							arg.el.setAttribute( 'title', tip );
						},
					} );
					calendar.render();
				}
			);
		}

		// Helper function to update occurrence
		function updateOccurrence( occ, capacity, status ) {
			$.post(
				WCEFPAdmin.ajaxUrl,
				{
					action: 'wcefp_update_occurrence',
					nonce: WCEFPAdmin.nonce,
					occ,
					capacity,
					status,
				},
				function ( res ) {
					if ( res && res.success ) {
						WCEFPModals.showSuccess( 'Aggiornato.' );
						loadCalendar();
					} else {
						WCEFPModals.showError( 'Errore aggiornamento.' );
					}
				}
			);
		}

		function loadList() {
			$view.html( '<p>Carico lista…</p>' );
			$.post(
				WCEFPAdmin.ajaxUrl,
				{ action: 'wcefp_get_bookings', nonce: WCEFPAdmin.nonce },
				function ( r ) {
					if ( r.success ) {
						const rows = r.data.rows || [];
						if ( ! rows.length ) {
							$view.html( '<p>Nessuna prenotazione.</p>' );
							return;
						}
						let html =
							'<div class="wcefp-list-wrap"><input type="search" id="wcefp-list-search" placeholder="Cerca…" style="margin-bottom:8px;max-width:260px" /><table class="widefat striped wcefp-list-table"><thead><tr><th>Ordine</th><th>Status</th><th>Data</th><th>Prodotto</th><th>Q.tà</th><th>Totale</th></tr></thead><tbody>';
						rows.forEach( ( x ) => {
							html += `<tr>
              <td>${ x.order }</td>
              <td>${ x.status }</td>
              <td>${ x.date }</td>
              <td>${ x.product }</td>
              <td>${ x.qty }</td>
              <td>€ ${ Number( x.total ).toFixed( 2 ) }</td>
            </tr>`;
						} );
						html += '</tbody></table></div>';
						$view.html( html );

						// Ricerca live
						$( '#wcefp-list-search' ).on( 'input', function () {
							const q = $( this ).val().toLowerCase();
							$view.find( 'tbody tr' ).each( function () {
								const txt = $( this ).text().toLowerCase();
								$( this ).toggle( txt.indexOf( q ) !== -1 );
							} );
						} );
					} else {
						$view.html( '<p>Errore nel caricamento.</p>' );
					}
				}
			);
		}

		$( '#wcefp-switch-calendar' ).on( 'click', loadCalendar );
		$( '#wcefp-switch-list' ).on( 'click', loadList );
		$filter.on( 'change', loadCalendar );

		// Avvio: calendario
		if ( $view.length ) $( '#wcefp-switch-calendar' ).trigger( 'click' );

		// --------- Tab prodotto: Genera occorrenze ----------
		$( '#wcefp-generate' ).on( 'click', function () {
			const pid = $( this ).data( 'product' );
			const from = $( '#wcefp_generate_from' ).val();
			const to = $( '#wcefp_generate_to' ).val();
			const $out = $( '#wcefp-generate-result' ).html(
				'<em>Generazione in corso…</em>'
			);
			$.post(
				ajaxurl,
				{
					action: 'wcefp_generate_occurrences',
					nonce: WCEFPAdmin.nonce,
					product_id: pid,
					from,
					to,
				},
				function ( r ) {
					if ( r && r.success ) {
						$out.html(
							'<span>Occorrenze create: <strong>' +
								r.data.created +
								'</strong></span>'
						);
					} else {
						// Escape the error message to prevent XSS
						const errorMsg =
							r && r.data && r.data.msg
								? $( '<div>' ).text( r.data.msg ).html()
								: 'unknown';
						$out.html(
							'<span style="color:#b32d2e">Errore: ' +
								errorMsg +
								'</span>'
						);
					}
				}
			);
		} );

		// --------- Extra opzionali (tab prodotto) ----------
		const $extraRows = $( '#wcefp-extra-rows' );
		if ( $extraRows.length ) {
			$( '.wcefp-add-extra' ).on( 'click', function () {
				const idx = $extraRows.find( 'tr' ).length;
				const tpl = $( '#wcefp-extra-row-template' )
					.html()
					.replace( /{{INDEX}}/g, idx );
				$extraRows.append( tpl );
			} );
			$extraRows.on( 'click', '.wcefp-remove-extra', function () {
				$( this ).closest( 'tr' ).remove();
				$extraRows.find( 'tr' ).each( function ( i ) {
					$( this )
						.find( 'input' )
						.each( function () {
							const name = $( this )
								.attr( 'name' )
								.replace( /\[\d+\]/, '[' + i + ']' );
							$( this ).attr( 'name', name );
						} );
				} );
			} );
		}

		// --------- Product Admin Form Enhancements (moved from inline) ---------
		function initProductAdminEnhancements() {
			// Add enhanced validation and visual feedback
			$( '#wcefp_product_data input, #wcefp_product_data textarea' ).on(
				'blur',
				function () {
					const $field = $( this ).closest( '.form-field' );
					const value = $( this ).val();

					// Remove existing validation classes
					$field.removeClass( 'has-error has-success' );

					// Add validation feedback based on field requirements
					if (
						$( this ).attr( 'required' ) ||
						$( this ).closest( '[data-required]' ).length
					) {
						if ( value.trim() === '' ) {
							$field.addClass( 'has-error' );
						} else {
							$field.addClass( 'has-success' );
						}
					}

					// Specific validations
					if ( $( this ).attr( 'data-type' ) === 'price' && value ) {
						if (
							isNaN( parseFloat( value ) ) ||
							parseFloat( value ) <= 0
						) {
							$field.addClass( 'has-error' );
						} else {
							$field.addClass( 'has-success' );
						}
					}

					if ( $( this ).attr( 'type' ) === 'number' && value ) {
						const min = parseInt( $( this ).attr( 'min' ) );
						const max = parseInt( $( this ).attr( 'max' ) );
						const val = parseInt( value );

						if (
							isNaN( val ) ||
							( min && val < min ) ||
							( max && val > max )
						) {
							$field.addClass( 'has-error' );
						} else {
							$field.addClass( 'has-success' );
						}
					}
				}
			);

			// Enhance language input with tags-like behavior
			$( '#_wcefp_languages' ).on( 'keyup', function () {
				const value = $( this ).val();
				if ( value ) {
					// Auto-uppercase and format
					const formatted = value
						.toUpperCase()
						.replace( /\s*,\s*/g, ', ' );
					if ( formatted !== value ) {
						$( this ).val( formatted );
					}
				}
			} );

			// Real-time price formatting
			$( 'input[data-type="price"]' ).on( 'keyup', function () {
				const value = $( this ).val();
				if ( value && ! isNaN( parseFloat( value ) ) ) {
					$( this )
						.closest( '.form-field' )
						.addClass( 'has-success' )
						.removeClass( 'has-error' );
				}
			} );

			// Time slots validation and formatting
			$( '#_wcefp_time_slots' ).on( 'blur', function () {
				const value = $( this ).val().trim();
				const $field = $( this ).closest( '.wcefp-time-slots-section' );

				if ( value ) {
					// Validate time format (HH:MM)
					const timePattern = /^(\d{1,2}:\d{2})(\s*,\s*\d{1,2}:\d{2})*$/;
					if ( timePattern.test( value ) ) {
						// Additional validation: check each time is valid
						const times = value.split( ',' ).map( function ( t ) {
							return t.trim();
						} );
						const allValid = times.every( function ( time ) {
							const parts = time.split( ':' );
							const hours = parseInt( parts[ 0 ] );
							const minutes = parseInt( parts[ 1 ] );
							return (
								hours >= 0 &&
								hours <= 23 &&
								minutes >= 0 &&
								minutes <= 59
							);
						} );

						if ( allValid ) {
							$field
								.removeClass( 'has-error' )
								.addClass( 'has-success' );
							// Format nicely
							$( this ).val( times.join( ', ' ) );
						} else {
							$field
								.removeClass( 'has-success' )
								.addClass( 'has-error' );
						}
					} else {
						$field
							.removeClass( 'has-success' )
							.addClass( 'has-error' );
					}
				} else {
					$field.removeClass( 'has-error has-success' );
				}
			} );
		}
	} );
} )( jQuery );
