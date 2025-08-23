/**
 * WCEventsFP Advanced Features v1.8.1
 * Additional enhancements for competitive booking experience
 */

( function ( $ ) {
	'use strict';

	// Advanced notification system
	class WCEFPNotifications {
		constructor() {
			this.container = null;
			this.init();
		}

		init() {
			// Create notification container
			if ( ! $( '#wcefp-notifications' ).length ) {
				$( 'body' ).append(
					'<div id="wcefp-notifications" class="wcefp-notifications-container"></div>'
				);
				this.container = $( '#wcefp-notifications' );
			}
		}

		show( message, type = 'info', duration = 5000 ) {
			const id = 'wcefp-notification-' + Date.now();
			const icons = {
				success: '✅',
				error: '❌',
				warning: '⚠️',
				info: 'ℹ️',
			};

			const notification = $( `
                <div class="wcefp-notification wcefp-notification-${ type }" id="${ id }">
                    <div class="wcefp-notification-icon">${
						icons[ type ] || icons.info
					}</div>
                    <div class="wcefp-notification-content">${ message }</div>
                    <button class="wcefp-notification-close" aria-label="Chiudi notifica">&times;</button>
                </div>
            ` );

			this.container.append( notification );

			// Animate in
			setTimeout( () => notification.addClass( 'wcefp-show' ), 100 );

			// Auto dismiss
			setTimeout( () => this.dismiss( id ), duration );

			// Click to dismiss
			notification
				.find( '.wcefp-notification-close' )
				.on( 'click', () => this.dismiss( id ) );

			return id;
		}

		dismiss( id ) {
			const notification = $( `#${ id }` );
			notification.removeClass( 'wcefp-show' );
			setTimeout( () => notification.remove(), 300 );
		}
	}

	// Real-time availability checker
	class WCEFPAvailabilityChecker {
		constructor() {
			this.checkInterval = null;
			this.init();
		}

		init() {
			this.startPeriodicCheck();
			this.bindVisibilityChange();
		}

		startPeriodicCheck() {
			// Check every 2 minutes
			this.checkInterval = setInterval( () => {
				this.checkAvailability();
			}, 120000 );
		}

		bindVisibilityChange() {
			document.addEventListener( 'visibilitychange', () => {
				if ( ! document.hidden ) {
					// Page became visible, check immediately
					this.checkAvailability();
				}
			} );
		}

		checkAvailability() {
			$( '.wcefp-card' ).each( function () {
				const $card = $( this );
				const productId = $card.data( 'product-id' );

				if ( ! productId ) return;

				// Simulate availability check (in real implementation, this would be an AJAX call)
				const availability = Math.floor( Math.random() * 20 ) + 1;
				const $indicator = $card.find( '.wcefp-availability' );

				if ( $indicator.length ) {
					let className = '';
					let text = '';

					if ( availability <= 3 ) {
						className = 'critical';
						text = `⚠️ Solo ${ availability } posti rimasti`;
					} else if ( availability <= 8 ) {
						className = 'limited';
						text = `🔥 ${ availability } posti disponibili`;
					} else {
						className = 'available';
						text = `✅ ${ availability }+ posti disponibili`;
					}

					$indicator
						.removeClass(
							'wcefp-availability-critical wcefp-availability-limited wcefp-availability-available'
						)
						.addClass( `wcefp-availability-${ className }` )
						.html( text );
				}
			} );
		}

		stop() {
			if ( this.checkInterval ) {
				clearInterval( this.checkInterval );
			}
		}
	}

	// Enhanced booking analytics with competitive tracking
	class WCEFPAnalytics {
		constructor() {
			this.events = [];
			this.sessionData = {};
			this.conversionFunnel = {};
			this.performanceMetrics = {};
			this.init();
		}

		init() {
			this.bindEvents();
			this.startSession();
			this.initPerformanceTracking();
			this.initConversionFunnel();
			this.initCrossDeviceTracking();
		}

		startSession() {
			this.sessionData = {
				session_id: this.generateSessionId(),
				start_time: Date.now(),
				page_url: window.location.href,
				user_agent: navigator.userAgent,
				screen_resolution: `${ screen.width }x${ screen.height }`,
				viewport_size: `${ window.innerWidth }x${ window.innerHeight }`,
				referrer: document.referrer,
				utm_source: this.getUrlParameter( 'utm_source' ),
				utm_medium: this.getUrlParameter( 'utm_medium' ),
				utm_campaign: this.getUrlParameter( 'utm_campaign' ),
				device_type: this.getDeviceType(),
				connection_type: this.getConnectionType(),
			};

			this.track( 'session_start', this.sessionData );
		}

		initPerformanceTracking() {
			// Core Web Vitals tracking
			if ( 'PerformanceObserver' in window ) {
				// Largest Contentful Paint (LCP)
				new PerformanceObserver( ( entryList ) => {
					const entries = entryList.getEntries();
					const lastEntry = entries[ entries.length - 1 ];
					this.track( 'core_web_vital', {
						metric: 'LCP',
						value: lastEntry.startTime,
						rating:
							lastEntry.startTime < 2500
								? 'good'
								: lastEntry.startTime < 4000
								? 'needs_improvement'
								: 'poor',
					} );
				} ).observe( { entryTypes: [ 'largest-contentful-paint' ] } );

				// First Input Delay (FID)
				new PerformanceObserver( ( entryList ) => {
					const entries = entryList.getEntries();
					entries.forEach( ( entry ) => {
						this.track( 'core_web_vital', {
							metric: 'FID',
							value: entry.processingStart - entry.startTime,
							rating:
								entry.processingStart - entry.startTime < 100
									? 'good'
									: entry.processingStart - entry.startTime <
									  300
									? 'needs_improvement'
									: 'poor',
						} );
					} );
				} ).observe( { entryTypes: [ 'first-input' ] } );

				// Cumulative Layout Shift (CLS)
				let clsValue = 0;
				new PerformanceObserver( ( entryList ) => {
					const entries = entryList.getEntries();
					entries.forEach( ( entry ) => {
						if ( ! entry.hadRecentInput ) {
							clsValue += entry.value;
						}
					} );
					this.track( 'core_web_vital', {
						metric: 'CLS',
						value: clsValue,
						rating:
							clsValue < 0.1
								? 'good'
								: clsValue < 0.25
								? 'needs_improvement'
								: 'poor',
					} );
				} ).observe( { entryTypes: [ 'layout-shift' ] } );
			}

			// Page load performance
			window.addEventListener( 'load', () => {
				setTimeout( () => {
					const navigation = performance.getEntriesByType(
						'navigation'
					)[ 0 ];
					if ( navigation ) {
						this.track( 'page_performance', {
							dom_content_loaded:
								navigation.domContentLoadedEventEnd -
								navigation.domContentLoadedEventStart,
							load_complete:
								navigation.loadEventEnd -
								navigation.loadEventStart,
							total_load_time:
								navigation.loadEventEnd - navigation.fetchStart,
							dns_lookup:
								navigation.domainLookupEnd -
								navigation.domainLookupStart,
							tcp_connection:
								navigation.connectEnd - navigation.connectStart,
							server_response:
								navigation.responseStart -
								navigation.requestStart,
							dom_processing:
								navigation.domComplete - navigation.domLoading,
						} );
					}
				}, 1000 );
			} );
		}

		initConversionFunnel() {
			this.conversionFunnel = {
				page_view: false,
				product_view: false,
				date_selected: false,
				participants_selected: false,
				extras_viewed: false,
				add_to_cart_attempted: false,
				add_to_cart_completed: false,
				checkout_initiated: false,
				purchase_completed: false,
			};

			// Track funnel progression
			this.updateFunnelStep( 'page_view' );
		}

		initCrossDeviceTracking() {
			// Generate or retrieve user fingerprint
			const fingerprint = this.generateUserFingerprint();
			this.sessionData.user_fingerprint = fingerprint;

			// Store cross-session data
			const userId =
				localStorage.getItem( 'wcefp_user_id' ) ||
				this.generateUserId();
			localStorage.setItem( 'wcefp_user_id', userId );
			this.sessionData.user_id = userId;
		}

		bindEvents() {
			// Enhanced widget interaction tracking
			$( document ).on( 'click', '.wcefp-widget .wcefp-add', ( e ) => {
				this.updateFunnelStep( 'add_to_cart_attempted' );
				this.track( 'booking_attempt', {
					product_id: $( e.target )
						.closest( '.wcefp-widget' )
						.data( 'product' ),
					step: 'add_to_cart',
					participants_total: this.getParticipantsCount(
						$( e.target ).closest( '.wcefp-widget' )
					),
					selected_date: $( e.target )
						.closest( '.wcefp-widget' )
						.find( '.wcefp-date' )
						.val(),
					selected_time: $( e.target )
						.closest( '.wcefp-widget' )
						.find( '.wcefp-slot' )
						.val(),
					funnel_completion: this.calculateFunnelCompletion(),
				} );
			} );

			// Date selection tracking
			$( document ).on( 'change', '.wcefp-date', ( e ) => {
				this.updateFunnelStep( 'date_selected' );
				this.track( 'date_selected', {
					product_id: $( e.target )
						.closest( '.wcefp-widget' )
						.data( 'product' ),
					selected_date: e.target.value,
					days_from_now: this.getDaysFromNow( e.target.value ),
				} );
			} );

			// Participants selection tracking
			$( document ).on(
				'change',
				'.wcefp-adults, .wcefp-children',
				( e ) => {
					this.updateFunnelStep( 'participants_selected' );
					const $widget = $( e.target ).closest( '.wcefp-widget' );
					this.track( 'participants_changed', {
						product_id: $widget.data( 'product' ),
						adults: parseInt(
							$widget.find( '.wcefp-adults' ).val() || 0
						),
						children: parseInt(
							$widget.find( '.wcefp-children' ).val() || 0
						),
						total: this.getParticipantsCount( $widget ),
					} );
				}
			);

			// Time slot selection tracking
			$( document ).on( 'change', '.wcefp-slot', ( e ) => {
				this.track( 'time_slot_selected', {
					product_id: $( e.target )
						.closest( '.wcefp-widget' )
						.data( 'product' ),
					selected_slot: e.target.value,
					slot_text: $( e.target ).find( 'option:selected' ).text(),
				} );
			} );

			// Extras interaction tracking
			$( document ).on( 'change', '.wcefp-extra-checkbox', ( e ) => {
				this.updateFunnelStep( 'extras_viewed' );
				const $checkbox = $( e.target );
				const extraData = {
					product_id: $checkbox
						.closest( '.wcefp-widget' )
						.data( 'product' ),
					extra_id: $checkbox.data( 'extra-id' ),
					extra_name: $checkbox.data( 'extra-name' ),
					extra_price: parseFloat(
						$checkbox.data( 'extra-price' ) || 0
					),
					action: $checkbox.is( ':checked' ) ? 'added' : 'removed',
				};

				this.track( 'extra_interaction', extraData );

				// Send to dataLayer for GA4/GTM
				if ( typeof dataLayer !== 'undefined' ) {
					dataLayer.push( {
						event: 'extra_selected',
						ecommerce: {
							currency: WCEFPData.currency || 'EUR',
							value: extraData.extra_price,
							items: [
								{
									item_id: extraData.extra_id.toString(),
									item_name: extraData.extra_name,
									item_category: 'Extra',
									quantity:
										extraData.action === 'added' ? 1 : -1,
									price: extraData.extra_price,
								},
							],
						},
					} );
				}
			} );

			// Enhanced filter usage tracking
			$( document ).on(
				'input change',
				'.wcefp-search-input, .wcefp-filter-select',
				( e ) => {
					this.track( 'filter_used', {
						filter_type:
							e.target.className
								.split( ' ' )
								.find( ( cls ) => cls.includes( 'filter' ) ) ||
							'search',
						filter_value: e.target.value,
						results_count: $( '.wcefp-card:visible' ).length,
						total_items: $( '.wcefp-card' ).length,
					} );
				}
			);

			// Enhanced card interaction tracking
			$( document ).on( 'click', '.wcefp-card', ( e ) => {
				this.updateFunnelStep( 'product_view' );
				const $card = $( e.currentTarget );
				this.track( 'card_clicked', {
					product_id: $card.data( 'product-id' ),
					product_name: $card.find( '.wcefp-card-title' ).text(),
					card_position: $card.index(),
					price: this.extractPriceFromCard( $card ),
					rating: this.extractRatingFromCard( $card ),
					availability: $card.find( '.wcefp-availability' ).text(),
				} );
			} );

			// Social sharing tracking
			$( document ).on( 'click', '.wcefp-share-btn', ( e ) => {
				const platform =
					e.target.className
						.split( ' ' )
						.find( ( cls ) => cls.includes( 'share-' ) ) ||
					'unknown';
				this.track( 'social_share', {
					platform: platform.replace( 'share-', '' ),
					product_id: $( e.target )
						.closest( '.wcefp-card' )
						.data( 'product-id' ),
					share_url: window.location.href,
				} );
			} );

			// Scroll depth tracking
			let maxScrollDepth = 0;
			$( window ).on(
				'scroll',
				this.throttle( () => {
					const scrollDepth = Math.round(
						( $( window ).scrollTop() /
							( $( document ).height() -
								$( window ).height() ) ) *
							100
					);
					if (
						scrollDepth > maxScrollDepth &&
						scrollDepth % 25 === 0
					) {
						maxScrollDepth = scrollDepth;
						this.track( 'scroll_depth', {
							depth_percentage: scrollDepth,
							page_url: window.location.href,
						} );
					}
				}, 1000 )
			);

			// Engagement time tracking
			let engagementStart = Date.now();
			let isActive = true;

			// Track when user becomes inactive
			$( document ).on( 'visibilitychange', () => {
				if ( document.hidden ) {
					if ( isActive ) {
						this.track( 'engagement_time', {
							duration: Date.now() - engagementStart,
							type: 'visible',
						} );
						isActive = false;
					}
				} else {
					engagementStart = Date.now();
					isActive = true;
				}
			} );

			// Track page exit
			$( window ).on( 'beforeunload', () => {
				if ( isActive ) {
					this.track( 'engagement_time', {
						duration: Date.now() - engagementStart,
						type: 'total',
					} );
				}

				// Send final funnel status
				this.track( 'session_end', {
					funnel_completion: this.calculateFunnelCompletion(),
					final_step: this.getFinalFunnelStep(),
					session_duration: Date.now() - this.sessionData.start_time,
				} );
			} );
		}

		track( event_name, data = {} ) {
			const event = {
				event: event_name,
				timestamp: new Date().toISOString(),
				session_id: this.sessionData.session_id,
				user_id: this.sessionData.user_id,
				page_url: window.location.href,
				...data,
			};

			this.events.push( event );

			// Enhanced Google Analytics tracking
			if ( typeof gtag !== 'undefined' ) {
				// Send to Google Analytics with enhanced data
				gtag( 'event', event_name, {
					custom_parameter_1: data.product_id || '',
					custom_parameter_2: data.step || data.type || '',
					custom_parameter_3: data.value || '',
					session_id: this.sessionData.session_id,
					user_id: this.sessionData.user_id,
					...data,
				} );
			}

			// Enhanced dataLayer push for GTM
			if ( typeof dataLayer !== 'undefined' ) {
				dataLayer.push( {
					event: `wcefp_${ event_name }`,
					wcefp_data: {
						event_category: 'WCEFP',
						event_action: event_name,
						event_label: data.product_id || data.type || '',
						session_id: this.sessionData.session_id,
						user_id: this.sessionData.user_id,
						funnel_step: this.getFinalFunnelStep(),
						funnel_completion: this.calculateFunnelCompletion(),
					},
					...event,
				} );
			}

			// Enhanced Meta Pixel tracking
			if ( typeof fbq !== 'undefined' && WCEFPData.meta_pixel_id ) {
				const customData = {
					session_id: this.sessionData.session_id,
					user_id: this.sessionData.user_id,
					event_category: 'WCEFP',
					...data,
				};

				// Map specific events to Meta Pixel standard events
				const metaEventMap = {
					product_view: 'ViewContent',
					date_selected: 'Search',
					add_to_cart_attempted: 'AddToCart',
					booking_attempt: 'InitiateCheckout',
					social_share: 'Share',
				};

				if ( metaEventMap[ event_name ] ) {
					fbq( 'track', metaEventMap[ event_name ], customData );
				} else {
					fbq( 'trackCustom', `WCEFP_${ event_name }`, customData );
				}
			}

			// Google Ads conversion tracking
			if ( typeof gtag !== 'undefined' && WCEFPData.google_ads_id ) {
				// Track key conversion events for Google Ads
				const conversionEvents = {
					add_to_cart_completed: 'add_to_cart',
					booking_attempt: 'begin_checkout',
					purchase_completed: 'purchase',
				};

				if ( conversionEvents[ event_name ] ) {
					gtag( 'event', 'conversion', {
						send_to: WCEFPData.google_ads_id,
						value: data.value || data.price || 0,
						currency: WCEFPData.currency || 'EUR',
						transaction_id:
							data.transaction_id || this.sessionData.session_id,
					} );
				}
			}

			// Store in localStorage for offline analysis
			if ( window.localStorage ) {
				const stored = JSON.parse(
					localStorage.getItem( 'wcefp_analytics' ) || '[]'
				);
				stored.push( event );

				// Keep only last 500 events for better performance
				if ( stored.length > 500 ) {
					stored.splice( 0, stored.length - 500 );
				}

				localStorage.setItem(
					'wcefp_analytics',
					JSON.stringify( stored )
				);
			}

			// Send to server for advanced analytics (optional)
			if (
				WCEFPData.enable_server_analytics &&
				this.shouldSendToServer( event_name )
			) {
				this.sendToServer( event );
			}
		}

		// Utility methods for enhanced tracking
		updateFunnelStep( step ) {
			if ( this.conversionFunnel.hasOwnProperty( step ) ) {
				this.conversionFunnel[ step ] = true;
			}
		}

		calculateFunnelCompletion() {
			const steps = Object.keys( this.conversionFunnel );
			const completedSteps = steps.filter(
				( step ) => this.conversionFunnel[ step ]
			);
			return Math.round( ( completedSteps.length / steps.length ) * 100 );
		}

		getFinalFunnelStep() {
			const steps = Object.keys( this.conversionFunnel );
			for ( let i = steps.length - 1; i >= 0; i-- ) {
				if ( this.conversionFunnel[ steps[ i ] ] ) {
					return steps[ i ];
				}
			}
			return 'page_view';
		}

		getParticipantsCount( $widget ) {
			const adults = parseInt(
				$widget.find( '.wcefp-adults' ).val() || 0
			);
			const children = parseInt(
				$widget.find( '.wcefp-children' ).val() || 0
			);
			return adults + children;
		}

		getDaysFromNow( dateString ) {
			const selectedDate = new Date( dateString );
			const today = new Date();
			return Math.ceil(
				( selectedDate - today ) / ( 1000 * 60 * 60 * 24 )
			);
		}

		extractPriceFromCard( $card ) {
			const priceText = $card.find( '.wcefp-price' ).text();
			return (
				parseFloat(
					priceText.replace( /[^\d.,]/g, '' ).replace( ',', '.' )
				) || 0
			);
		}

		extractRatingFromCard( $card ) {
			const ratingElement = $card.find( '.wcefp-rating' );
			if ( ratingElement.length ) {
				return parseFloat( ratingElement.data( 'rating' ) ) || 0;
			}
			return null;
		}

		generateSessionId() {
			return (
				'wcefp_' +
				Date.now() +
				'_' +
				Math.random().toString( 36 ).substr( 2, 9 )
			);
		}

		generateUserId() {
			return (
				'user_' +
				Date.now() +
				'_' +
				Math.random().toString( 36 ).substr( 2, 9 )
			);
		}

		generateUserFingerprint() {
			const canvas = document.createElement( 'canvas' );
			const ctx = canvas.getContext( '2d' );
			ctx.textBaseline = 'top';
			ctx.font = '14px Arial';
			ctx.fillText( 'WCEventsFP fingerprint', 2, 2 );

			const fingerprint = [
				navigator.userAgent,
				navigator.language,
				screen.width + 'x' + screen.height,
				new Date().getTimezoneOffset(),
				canvas.toDataURL(),
			].join( '|' );

			return this.simpleHash( fingerprint );
		}

		simpleHash( str ) {
			let hash = 0;
			if ( str.length === 0 ) return hash;
			for ( let i = 0; i < str.length; i++ ) {
				const char = str.charCodeAt( i );
				hash = ( hash << 5 ) - hash + char;
				hash = hash & hash; // Convert to 32bit integer
			}
			return Math.abs( hash ).toString( 36 );
		}

		getUrlParameter( name ) {
			const urlParams = new URLSearchParams( window.location.search );
			return urlParams.get( name );
		}

		getDeviceType() {
			const width = window.innerWidth;
			if ( width <= 768 ) return 'mobile';
			if ( width <= 1024 ) return 'tablet';
			return 'desktop';
		}

		getConnectionType() {
			if ( 'connection' in navigator ) {
				return navigator.connection.effectiveType || 'unknown';
			}
			return 'unknown';
		}

		throttle( func, wait ) {
			let timeout;
			return function executedFunction( ...args ) {
				const later = () => {
					clearTimeout( timeout );
					func( ...args );
				};
				clearTimeout( timeout );
				timeout = setTimeout( later, wait );
			};
		}

		shouldSendToServer( event_name ) {
			// Only send critical events to reduce server load
			const criticalEvents = [
				'booking_attempt',
				'add_to_cart_completed',
				'purchase_completed',
				'session_start',
				'session_end',
				'core_web_vital',
			];
			return criticalEvents.includes( event_name );
		}

		sendToServer( event ) {
			// Send event to server for advanced analytics
			if ( typeof fetch !== 'undefined' ) {
				fetch( WCEFPData.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'wcefp_track_analytics',
						nonce: WCEFPData.nonce,
						event_data: JSON.stringify( event ),
					} ),
				} ).catch( ( error ) => {
					console.log( 'Analytics tracking error:', error );
				} );
			}
		}

		getEvents() {
			return this.events;
		}
	}

	// Progressive Web App features
	class WCEFPPWAFeatures {
		constructor() {
			this.init();
		}

		init() {
			this.addToHomeScreenPrompt();
			this.handleOfflineState();
			this.optimizePerformance();
		}

		addToHomeScreenPrompt() {
			let deferredPrompt;

			window.addEventListener( 'beforeinstallprompt', ( e ) => {
				e.preventDefault();
				deferredPrompt = e;

				// Show custom install prompt
				this.showInstallPrompt( deferredPrompt );
			} );
		}

		showInstallPrompt( deferredPrompt ) {
			const promptHTML = `
                <div class="wcefp-install-prompt">
                    <div class="wcefp-install-content">
                        <h4>📱 Installa WCEventsFP</h4>
                        <p>Aggiungi l'app alla tua schermata principale per un accesso rapido!</p>
                        <div class="wcefp-install-actions">
                            <button class="wcefp-install-btn">Installa</button>
                            <button class="wcefp-install-dismiss">Non ora</button>
                        </div>
                    </div>
                </div>
            `;

			$( 'body' ).append( promptHTML );

			$( '.wcefp-install-btn' ).on( 'click', () => {
				if ( deferredPrompt ) {
					deferredPrompt.prompt();
					deferredPrompt.userChoice.then( ( choiceResult ) => {
						deferredPrompt = null;
						$( '.wcefp-install-prompt' ).remove();
					} );
				}
			} );

			$( '.wcefp-install-dismiss' ).on( 'click', () => {
				$( '.wcefp-install-prompt' ).remove();
			} );
		}

		handleOfflineState() {
			window.addEventListener( 'online', () => {
				const notifications = new WCEFPNotifications();
				notifications.show( '✅ Connessione ristabilita!', 'success' );
			} );

			window.addEventListener( 'offline', () => {
				const notifications = new WCEFPNotifications();
				notifications.show(
					'⚠️ Connessione persa. Alcune funzioni potrebbero non funzionare.',
					'warning'
				);
			} );
		}

		optimizePerformance() {
			// Lazy load images when they come into viewport
			if ( 'IntersectionObserver' in window ) {
				const imageObserver = new IntersectionObserver( ( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting ) {
							const img = entry.target;
							if ( img.dataset.src ) {
								img.src = img.dataset.src;
								img.removeAttribute( 'data-src' );
								img.classList.add( 'wcefp-loaded' );
								imageObserver.unobserve( img );
							}
						}
					} );
				} );

				$( '.wcefp-card img[data-src]' ).each( function () {
					imageObserver.observe( this );
				} );
			}

			// Prefetch important resources
			this.prefetchResources();
		}

		prefetchResources() {
			// Prefetch commonly used resources
			const resources = [
				'/wp-content/plugins/wceventsfp/assets/css/templates.css',
				'/wp-content/plugins/wceventsfp/assets/js/templates.js',
			];

			resources.forEach( ( resource ) => {
				const link = document.createElement( 'link' );
				link.rel = 'prefetch';
				link.href = resource;
				document.head.appendChild( link );
			} );
		}
	}

	// Initialize all advanced features when document is ready
	$( document ).ready( function () {
		// Initialize notification system
		window.WCEFPNotifications = new WCEFPNotifications();

		// Initialize availability checker
		window.WCEFPAvailabilityChecker = new WCEFPAvailabilityChecker();

		// Initialize analytics
		window.WCEFPAnalytics = new WCEFPAnalytics();

		// Initialize PWA features
		window.WCEFPPWAFeatures = new WCEFPPWAFeatures();

		// Add advanced styles
		if ( ! $( '#wcefp-advanced-styles' ).length ) {
			$( '<style id="wcefp-advanced-styles">' ).appendTo( 'head' ).text( `
                /* Notification System */
                .wcefp-notifications-container {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    pointer-events: none;
                }
                
                .wcefp-notification {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    margin-bottom: 12px;
                    padding: 16px;
                    min-width: 300px;
                    max-width: 400px;
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    pointer-events: auto;
                    transform: translateX(100%);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .wcefp-notification.wcefp-show {
                    transform: translateX(0);
                    opacity: 1;
                }
                
                .wcefp-notification-success {
                    border-left: 4px solid #10b981;
                }
                
                .wcefp-notification-error {
                    border-left: 4px solid #ef4444;
                }
                
                .wcefp-notification-warning {
                    border-left: 4px solid #f59e0b;
                }
                
                .wcefp-notification-info {
                    border-left: 4px solid #3b82f6;
                }
                
                .wcefp-notification-icon {
                    font-size: 18px;
                    flex-shrink: 0;
                }
                
                .wcefp-notification-content {
                    flex: 1;
                    font-size: 14px;
                    line-height: 1.4;
                }
                
                .wcefp-notification-close {
                    background: none;
                    border: none;
                    font-size: 18px;
                    cursor: pointer;
                    color: #6b7280;
                    flex-shrink: 0;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: background-color 0.2s;
                }
                
                .wcefp-notification-close:hover {
                    background-color: #f3f4f6;
                }
                
                /* Install Prompt */
                .wcefp-install-prompt {
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 400px;
                    margin: 0 auto;
                    animation: wcefpSlideUp 0.3s ease-out;
                }
                
                .wcefp-install-content {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    border-radius: 16px;
                    padding: 20px;
                    text-align: center;
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
                }
                
                .wcefp-install-content h4 {
                    margin: 0 0 8px;
                    font-size: 1.2rem;
                }
                
                .wcefp-install-content p {
                    margin: 0 0 16px;
                    opacity: 0.9;
                    font-size: 0.9rem;
                }
                
                .wcefp-install-actions {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                }
                
                .wcefp-install-btn,
                .wcefp-install-dismiss {
                    padding: 8px 16px;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    font-size: 0.9rem;
                }
                
                .wcefp-install-btn {
                    background: white;
                    color: #667eea;
                }
                
                .wcefp-install-dismiss {
                    background: rgba(255, 255, 255, 0.2);
                    color: white;
                }
                
                .wcefp-install-btn:hover {
                    transform: scale(1.05);
                }
                
                .wcefp-install-dismiss:hover {
                    background: rgba(255, 255, 255, 0.3);
                }
                
                @keyframes wcefpSlideUp {
                    from {
                        transform: translateY(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                
                /* Loaded image state */
                .wcefp-card img.wcefp-loaded {
                    animation: wcefpFadeIn 0.3s ease-out;
                }
                
                @media (max-width: 768px) {
                    .wcefp-notifications-container {
                        top: 10px;
                        right: 10px;
                        left: 10px;
                    }
                    
                    .wcefp-notification {
                        min-width: auto;
                        max-width: 100%;
                    }
                    
                    .wcefp-install-prompt {
                        bottom: 10px;
                        left: 10px;
                        right: 10px;
                    }
                }
            ` );
		}
	} );
} )( jQuery );
