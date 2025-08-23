( function ( $ ) {
	$( function () {
		const $giftToggle = $( '#wcefp-gift-toggle' );
		const $giftFields = $( '#wcefp-gift-fields' );
		$giftToggle.on( 'change', function () {
			$giftFields.toggle( this.checked );
		} );

		const priceFormatter = new Intl.NumberFormat( WCEFPData.locale, {
			style: 'currency',
			currency: WCEFPData.currency,
		} );
		function formatCurrency( x ) {
			return priceFormatter.format( Number( x ) );
		}

		$( '.wcefp-widget' ).each( function () {
			const $w = $( this );
			const pid = $w.data( 'product' );
			const priceAdult = parseFloat( $w.data( 'price-adult' ) || '0' );
			const priceChild = parseFloat( $w.data( 'price-child' ) || '0' );
			const hasVoucher = String( $w.data( 'voucher' ) || '0' ) === '1';

			const $date = $w.find( '.wcefp-date' );
			const $slot = $w.find( '.wcefp-slot' );
			const $ad = $w.find( '.wcefp-adults' );
			const $ch = $w.find( '.wcefp-children' );
			const $btn = $w.find( '.wcefp-add' );
			const $fb = $w.find( '.wcefp-feedback' );
			const $tot = $w.find( '.wcefp-total' );

			function getExtras() {
				const extras = [];
				$w.find( '.wcefp-extra-row' ).each( function () {
					const id = $( this ).data( 'id' );
					const name = $( this ).data( 'name' );
					const price = parseFloat(
						$( this ).data( 'price' ) || '0'
					);
					const pricing = $( this ).data( 'pricing' );
					const $qty = $( this ).find( '.wcefp-extra-qty' );
					const $tg = $( this ).find( '.wcefp-extra-toggle' );
					let qty = 0;
					if ( $qty.length ) qty = parseInt( $qty.val() || '0', 10 );
					if ( $tg.length ) qty = $tg.is( ':checked' ) ? 1 : 0;
					if ( qty > 0 ) {
						extras.push( {
							id,
							name,
							price,
							qty,
							pricing,
						} );
					}
				} );
				return extras;
			}

			function updateTotal() {
				const ad = parseInt( $ad.val() || '0', 10 );
				const ch = parseInt( $ch.val() || '0', 10 );
				const extras = getExtras();
				let extrasCost = 0;
				extras.forEach( ( ex ) => {
					let mult = ex.qty;
					if ( ex.pricing === 'per_person' ) mult *= ad + ch;
					else if ( ex.pricing === 'per_child' ) mult *= ch;
					else if ( ex.pricing === 'per_adult' ) mult *= ad;
					extrasCost += ex.price * mult;
				} );
				const total = hasVoucher
					? 0
					: ad * priceAdult + ch * priceChild + extrasCost;
				$tot.text( formatCurrency( total ) );
			}

			function loadSlots() {
				const d = $date.val();
				$slot.html( '<option value="">Seleziona orario</option>' );
				if ( ! d ) return;
				$.post(
					WCEFPData.ajaxUrl,
					{
						action: 'wcefp_public_occurrences',
						nonce: WCEFPData.nonce,
						product_id: pid,
						date: d,
					},
					function ( r ) {
						if ( r && r.success ) {
							const slots = r.data.slots || [];
							if ( slots.length === 0 ) {
								$slot.append(
									'<option value="" disabled>Nessuno slot disponibile</option>'
								);
							}
							slots.forEach( ( s ) => {
								const disabled = s.soldout ? 'disabled' : '';
								const label = s.soldout
									? `${ s.time } (sold-out)`
									: `${ s.time } (${ s.available } posti)`;
								$slot.append(
									`<option value="${ s.id }" ${ disabled }>${ label }</option>`
								);
							} );
						}
					}
				);
			}

			$date.on( 'change', loadSlots );
			$ad.on( 'input', updateTotal );
			$ch.on( 'input', updateTotal );
			function handleExtrasChange() {
				updateTotal();
				if ( WCEFPData.ga4_enabled ) {
					const extras = getExtras();
					window.dataLayer = window.dataLayer || [];
					window.dataLayer.push( {
						event: 'select_extras',
						item_id: pid,
						extras,
					} );
				}
			}
			$w.on( 'input', '.wcefp-extra-qty', handleExtrasChange );
			$w.on( 'change', '.wcefp-extra-toggle', handleExtrasChange );
			updateTotal();

			$btn.on( 'click', function ( e ) {
				e.preventDefault();
				$fb.text( '' ).removeClass( 'wcefp-error wcefp-success' );
				const occ = $slot.val();
				const ad = parseInt( $ad.val() || '0', 10 );
				const ch = parseInt( $ch.val() || '0', 10 );
				const extras = getExtras();

				if ( ! occ ) {
					$fb.text( 'Seleziona uno slot.' )
						.addClass( 'wcefp-error' )
						.hide()
						.slideDown( 300 );
					return;
				}
				if ( ad + ch <= 0 ) {
					$fb.text( 'Indica almeno 1 partecipante.' )
						.addClass( 'wcefp-error' )
						.hide()
						.slideDown( 300 );
					return;
				}

				const giftEnabled = $giftToggle.is( ':checked' );
				let giftName = '',
					giftEmail = '',
					giftMsg = '';
				if ( giftEnabled ) {
					giftName = $.trim(
						$( 'input[name="gift_recipient_name"]' ).val()
					);
					giftEmail = $.trim(
						$( 'input[name="gift_recipient_email"]' ).val()
					);
					giftMsg = $.trim(
						$( 'textarea[name="gift_message"]' ).val()
					);
					if ( ! giftName ) {
						$fb.text( 'Inserisci il nome del destinatario.' )
							.addClass( 'wcefp-error' )
							.hide()
							.slideDown( 300 );
						return;
					}
				}

				// Add loading state
				$btn.prop( 'disabled', true ).text(
					'Aggiungendo al carrello...'
				);

				// Add subtle loading animation
				$w.addClass( 'wcefp-loading' );

				$.post(
					WCEFPData.ajaxUrl,
					{
						action: 'wcefp_add_to_cart',
						nonce: WCEFPData.nonce,
						product_id: pid,
						occurrence_id: occ,
						adults: ad,
						children: ch,
						extras,
						wcefp_gift_toggle: giftEnabled ? 1 : 0,
						gift_recipient_name: giftName,
						gift_recipient_email: giftEmail,
						gift_message: giftMsg,
					},
					function ( r ) {
						$w.removeClass( 'wcefp-loading' );
						$btn.prop( 'disabled', false ).text(
							'Aggiungi al carrello'
						);

						if ( r && r.success ) {
							$fb.text( 'Aggiunto al carrello con successo!' )
								.addClass( 'wcefp-success' )
								.hide()
								.slideDown( 300 );

							// Enhanced GA4/GTM tracking
							if ( WCEFPData.ga4_enabled ) {
								window.dataLayer = window.dataLayer || [];

								// Get product data for enhanced tracking
								const productData = {
									item_id: pid.toString(),
									item_name:
										$w
											.find( '.wcefp-product-title' )
											.text() || 'Unknown Product',
									item_category: 'Experience',
									item_category2: 'Booking',
									quantity: ad + ch,
									price:
										parseFloat(
											$w
												.find( '.wcefp-total-price' )
												.text()
												.replace( /[^\d.,]/g, '' )
												.replace( ',', '.' )
										) || 0,
								};

								// Enhanced begin_checkout event with ecommerce data
								window.dataLayer.push( {
									event: 'begin_checkout',
									ecommerce: {
										currency: WCEFPData.currency || 'EUR',
										value: productData.price,
										items: [ productData ],
									},
									// Additional custom parameters
									booking_type: 'experience',
									adults: ad,
									children: ch,
									selected_date: $w
										.find( '.wcefp-date' )
										.val(),
									selected_slot: $w
										.find( '.wcefp-slot' )
										.val(),
								} );

								// Enhanced add_to_cart event
								window.dataLayer.push( {
									event: 'add_to_cart',
									ecommerce: {
										currency: WCEFPData.currency || 'EUR',
										value: productData.price,
										items: [ productData ],
									},
								} );
							}

							// Enhanced Meta Pixel tracking
							if (
								WCEFPData.meta_pixel_id &&
								typeof fbq !== 'undefined'
							) {
								const pixelData = {
									content_ids: [ pid.toString() ],
									content_type: 'product',
									value:
										parseFloat(
											$w
												.find( '.wcefp-total-price' )
												.text()
												.replace( /[^\d.,]/g, '' )
												.replace( ',', '.' )
										) || 0,
									currency: WCEFPData.currency || 'EUR',
									content_name:
										$w
											.find( '.wcefp-product-title' )
											.text() || 'Experience',
									content_category: 'Experience',
									num_items: ad + ch,
								};

								fbq( 'track', 'AddToCart', pixelData );
								fbq( 'track', 'InitiateCheckout', pixelData );
							}

							// Celebrate with animation
							$w.addClass( 'wcefp-success-pulse' );
							setTimeout( () => {
								$w.removeClass( 'wcefp-success-pulse' );
								window.location.href = r.data.cart_url;
							}, 1500 );
						} else {
							$fb.text(
								r && r.data && r.data.msg
									? r.data.msg
									: "Errore durante l'aggiunta al carrello."
							)
								.addClass( 'wcefp-error' )
								.hide()
								.slideDown( 300 );
						}
					}
				).fail( function () {
					$w.removeClass( 'wcefp-loading' );
					$btn.prop( 'disabled', false ).text(
						'Aggiungi al carrello'
					);
					$fb.text( 'Errore di connessione. Riprova.' )
						.addClass( 'wcefp-error' )
						.hide()
						.slideDown( 300 );
				} );
			} );
		} );

		// Enhanced animations and interactions
		$( '.wcefp-widget' ).each( function () {
			const $widget = $( this );

			// Add entrance animation
			$widget.css( 'opacity', '0' ).animate( { opacity: 1 }, 600 );

			// Enhanced input focus effects
			$widget
				.find( 'input, select' )
				.on( 'focus', function () {
					$( this )
						.closest( '.wcefp-row' )
						.addClass( 'wcefp-row-focused' );
				} )
				.on( 'blur', function () {
					$( this )
						.closest( '.wcefp-row' )
						.removeClass( 'wcefp-row-focused' );
				} );

			// Add visual feedback on value changes
			$widget.find( 'input[type="number"]' ).on( 'change', function () {
				$( this ).addClass( 'wcefp-value-changed' );
				setTimeout( () => {
					$( this ).removeClass( 'wcefp-value-changed' );
				}, 300 );
			} );

			// Enhanced date picker interaction
			$widget.find( '.wcefp-date' ).on( 'change', function () {
				const $slotRow = $widget
					.find( '.wcefp-slot' )
					.closest( '.wcefp-row' );
				$slotRow.addClass( 'wcefp-loading-inline' );
				setTimeout( () => {
					$slotRow.removeClass( 'wcefp-loading-inline' );
				}, 500 );
			} );
		} );

		// Add countdown timer for popular events
		$( '.wcefp-card' ).each( function () {
			const $card = $( this );
			const availability = $card.find( '.wcefp-badge.avail' );
			if ( availability.length && Math.random() > 0.7 ) {
				// Add urgency indicator for some events
				const urgencyHTML = `
          <div class="wcefp-urgency" style="
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            margin-top: 8px;
            border-radius: 8px;
          ">
            🔥 Solo ${ Math.floor( Math.random() * 5 ) + 2 } posti rimasti!
          </div>
        `;
				$card.find( '.wcefp-card-body' ).append( urgencyHTML );
			}
		} );
	} );
} )( jQuery );

// Enhanced CSS animations
$( document ).ready( function () {
	if ( ! $( '#wcefp-enhanced-styles' ).length ) {
		$( '<style id="wcefp-enhanced-styles">' ).appendTo( 'head' ).text( `
      .wcefp-loading {
        position: relative;
        pointer-events: none;
      }
      
      .wcefp-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        border-radius: inherit;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      
      .wcefp-loading::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 24px;
        height: 24px;
        margin: -12px 0 0 -12px;
        border: 2px solid #e5e7eb;
        border-top: 2px solid #4f46e5;
        border-radius: 50%;
        animation: wcefpRotate 1s linear infinite;
        z-index: 11;
      }
      
      @keyframes wcefpRotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      
      .wcefp-row-focused {
        background: rgba(79, 70, 229, 0.05);
        border-radius: 8px;
        transition: background 0.3s ease;
      }
      
      .wcefp-value-changed {
        animation: wcefpPulse 0.3s ease-out;
      }
      
      @keyframes wcefpPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
      }
      
      .wcefp-success-pulse {
        animation: wcefpSuccessPulse 1.5s ease-out;
      }
      
      @keyframes wcefpSuccessPulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        25% { transform: scale(1.02); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0.4); }
        50% { transform: scale(1.01); box-shadow: 0 0 0 15px rgba(16, 185, 129, 0.2); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
      }
      
      .wcefp-loading-inline {
        opacity: 0.7;
        position: relative;
      }
      
      .wcefp-loading-inline::after {
        content: '⏳';
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        animation: wcefpBounce 1s infinite;
      }
      
      @keyframes wcefpBounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(-50%); }
        40% { transform: translateY(-60%); }
        60% { transform: translateY(-55%); }
      }
      
      .wcefp-feedback.wcefp-error {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border: 1px solid #f87171;
      }
      
      .wcefp-feedback.wcefp-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        border: 1px solid #34d399;
      }
    ` );
	}
} );

// Multi-Step Booking Widget Enhancement
( function ( $ ) {
	class WCEFPMultiStepWidget {
		constructor( $widget ) {
			this.$widget = $widget;
			this.currentStep = 1;
			this.totalSteps = 3;
			this.data = {};

			if ( this.$widget.hasClass( 'wcefp-multistep' ) ) {
				this.init();
			}
		}

		init() {
			this.createProgressIndicator();
			this.organizeSteps();
			this.bindNavigation();
			this.showStep( 1 );
		}

		createProgressIndicator() {
			const progressHTML = `
        <div class="wcefp-progress-indicator">
          <div class="wcefp-step-indicator ${
				this.currentStep >= 1 ? 'active' : ''
			}">
            <div class="wcefp-step-number">1</div>
            <div class="wcefp-step-label">Data & Orario</div>
          </div>
          <div class="wcefp-step-connector ${
				this.currentStep > 1 ? 'completed' : ''
			}"></div>
          <div class="wcefp-step-indicator ${
				this.currentStep >= 2 ? 'active' : ''
			}">
            <div class="wcefp-step-number">2</div>
            <div class="wcefp-step-label">Partecipanti & Extra</div>
          </div>
          <div class="wcefp-step-connector ${
				this.currentStep > 2 ? 'completed' : ''
			}"></div>
          <div class="wcefp-step-indicator ${
				this.currentStep >= 3 ? 'active' : ''
			}">
            <div class="wcefp-step-number">3</div>
            <div class="wcefp-step-label">Conferma</div>
          </div>
        </div>
      `;

			this.$widget.prepend( progressHTML );
		}

		organizeSteps() {
			// Group existing form elements into steps
			const $dateSlot = this.$widget
				.find( '.wcefp-date, .wcefp-slot' )
				.closest( '.wcefp-row' );
			const $participants = this.$widget
				.find( '.wcefp-adults, .wcefp-children' )
				.closest( '.wcefp-row' );
			const $extras = this.$widget.find( '.wcefp-extra-row' );
			const $total = this.$widget.find( '.wcefp-total-row' );
			const $button = this.$widget.find( '.wcefp-add' );

			// Create step containers
			const $step1 = $( '<div class="wcefp-step" data-step="1"></div>' );
			const $step2 = $( '<div class="wcefp-step" data-step="2"></div>' );
			const $step3 = $( '<div class="wcefp-step" data-step="3"></div>' );

			// Move elements to appropriate steps
			$step1.append( $dateSlot );
			$step2.append( $participants ).append( $extras );
			$step3.append( $total ).append( $button );

			// Add navigation buttons
			$step1.append(
				'<button type="button" class="wcefp-next-step">Avanti →</button>'
			);
			$step2.append(
				'<button type="button" class="wcefp-prev-step">← Indietro</button><button type="button" class="wcefp-next-step">Avanti →</button>'
			);
			$step3.append(
				'<button type="button" class="wcefp-prev-step">← Indietro</button>'
			);

			// Add steps to widget
			this.$widget.append( $step1 ).append( $step2 ).append( $step3 );
		}

		bindNavigation() {
			this.$widget.on( 'click', '.wcefp-next-step', ( e ) => {
				e.preventDefault();
				if ( this.validateCurrentStep() ) {
					this.nextStep();
				}
			} );

			this.$widget.on( 'click', '.wcefp-prev-step', ( e ) => {
				e.preventDefault();
				this.prevStep();
			} );
		}

		showStep( step ) {
			this.currentStep = step;

			// Hide all steps
			this.$widget.find( '.wcefp-step' ).removeClass( 'active' );

			// Show current step
			this.$widget
				.find( `.wcefp-step[data-step="${ step }"]` )
				.addClass( 'active' );

			// Update progress indicator
			this.updateProgressIndicator();

			// Add entrance animation
			this.$widget
				.find( '.wcefp-step.active' )
				.addClass( 'wcefp-step-enter' );
			setTimeout( () => {
				this.$widget
					.find( '.wcefp-step.active' )
					.removeClass( 'wcefp-step-enter' );
			}, 300 );
		}

		updateProgressIndicator() {
			const $indicators = this.$widget.find( '.wcefp-step-indicator' );
			const $connectors = this.$widget.find( '.wcefp-step-connector' );

			$indicators.each( ( index, indicator ) => {
				const $indicator = $( indicator );
				const stepNumber = index + 1;

				$indicator.removeClass( 'active completed' );

				if ( stepNumber < this.currentStep ) {
					$indicator.addClass( 'completed' );
				} else if ( stepNumber === this.currentStep ) {
					$indicator.addClass( 'active' );
				}
			} );

			$connectors.each( ( index, connector ) => {
				const $connector = $( connector );
				const stepNumber = index + 1;

				$connector.removeClass( 'completed' );

				if ( stepNumber < this.currentStep ) {
					$connector.addClass( 'completed' );
				}
			} );
		}

		validateCurrentStep() {
			const $feedback = this.$widget.find( '.wcefp-feedback' );
			$feedback.removeClass( 'wcefp-error' ).hide();

			if ( this.currentStep === 1 ) {
				const $date = this.$widget.find( '.wcefp-date' );
				const $slot = this.$widget.find( '.wcefp-slot' );

				if ( ! $date.val() ) {
					$feedback
						.text( 'Seleziona una data.' )
						.addClass( 'wcefp-error' )
						.slideDown();
					return false;
				}

				if ( ! $slot.val() ) {
					$feedback
						.text( 'Seleziona un orario.' )
						.addClass( 'wcefp-error' )
						.slideDown();
					return false;
				}
			}

			if ( this.currentStep === 2 ) {
				const adults = parseInt(
					this.$widget.find( '.wcefp-adults' ).val() || '0'
				);
				const children = parseInt(
					this.$widget.find( '.wcefp-children' ).val() || '0'
				);

				if ( adults + children <= 0 ) {
					$feedback
						.text( 'Indica almeno 1 partecipante.' )
						.addClass( 'wcefp-error' )
						.slideDown();
					return false;
				}
			}

			return true;
		}

		nextStep() {
			if ( this.currentStep < this.totalSteps ) {
				this.showStep( this.currentStep + 1 );
			}
		}

		prevStep() {
			if ( this.currentStep > 1 ) {
				this.showStep( this.currentStep - 1 );
			}
		}
	}

	// Initialize multi-step widgets
	$( document ).ready( function () {
		$( '.wcefp-widget' ).each( function () {
			new WCEFPMultiStepWidget( $( this ) );
		} );

		// Add multi-step styles
		if ( ! $( '#wcefp-multistep-styles' ).length ) {
			$( '<style id="wcefp-multistep-styles">' ).appendTo( 'head' )
				.text( `
        .wcefp-multistep .wcefp-progress-indicator {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 32px;
          padding: 20px;
          background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
          border-radius: 16px;
        }
        
        .wcefp-step-indicator {
          display: flex;
          flex-direction: column;
          align-items: center;
          position: relative;
        }
        
        .wcefp-step-number {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: #e5e7eb;
          color: #6b7280;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 700;
          font-size: 16px;
          margin-bottom: 8px;
          transition: all 0.3s ease;
        }
        
        .wcefp-step-indicator.active .wcefp-step-number {
          background: linear-gradient(135deg, #667eea, #764ba2);
          color: white;
          transform: scale(1.1);
        }
        
        .wcefp-step-indicator.completed .wcefp-step-number {
          background: linear-gradient(135deg, #10b981, #059669);
          color: white;
        }
        
        .wcefp-step-indicator.completed .wcefp-step-number::after {
          content: '✓';
          position: absolute;
        }
        
        .wcefp-step-label {
          font-size: 12px;
          font-weight: 600;
          color: #6b7280;
          text-align: center;
        }
        
        .wcefp-step-indicator.active .wcefp-step-label {
          color: #4f46e5;
        }
        
        .wcefp-step-connector {
          flex: 1;
          height: 2px;
          background: #e5e7eb;
          margin: 0 12px;
          position: relative;
          top: -20px;
          transition: background 0.3s ease;
        }
        
        .wcefp-step-connector.completed {
          background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .wcefp-step {
          display: none;
        }
        
        .wcefp-step.active {
          display: block;
          animation: wcefpStepSlideIn 0.3s ease-out;
        }
        
        @keyframes wcefpStepSlideIn {
          from {
            opacity: 0;
            transform: translateX(30px);
          }
          to {
            opacity: 1;
            transform: translateX(0);
          }
        }
        
        .wcefp-step-enter {
          animation: wcefpStepSlideIn 0.3s ease-out;
        }
        
        .wcefp-next-step, .wcefp-prev-step {
          margin: 16px 8px 0;
          padding: 12px 24px;
          border: none;
          border-radius: 12px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
        }
        
        .wcefp-next-step {
          background: linear-gradient(135deg, #667eea, #764ba2);
          color: white;
        }
        
        .wcefp-next-step:hover {
          background: linear-gradient(135deg, #5a6fd8, #6b46a8);
          transform: translateY(-1px);
        }
        
        .wcefp-prev-step {
          background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
          color: #374151;
        }
        
        .wcefp-prev-step:hover {
          background: linear-gradient(135deg, #e5e7eb, #d1d5db);
          transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
          .wcefp-progress-indicator {
            padding: 16px 12px;
          }
          
          .wcefp-step-label {
            font-size: 11px;
            max-width: 80px;
          }
          
          .wcefp-step-number {
            width: 32px;
            height: 32px;
            font-size: 14px;
          }
          
          .wcefp-step-connector {
            margin: 0 8px;
            top: -16px;
          }
        }
      ` );
		}
	} );
} )( jQuery );
