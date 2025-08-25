/**
 * WCEventsFP Advanced Analytics Dashboard
 * Enhanced KPI visualizations with predictive analytics and real-time monitoring
 */

class WCEFPAdvancedAnalytics {
	constructor() {
		this.apiEndpoint = window.wcefp_ajax?.url || '/wp-admin/admin-ajax.php';
		this.nonce = window.wcefp_ajax?.nonce || '';
		this.charts = {};
		this.realTimeInterval = null;
		this.predictiveModels = {};
		this.init();
	}

	init() {
		this.createDashboard();
		this.loadAnalyticsData();
		this.setupRealTimeUpdates();
		this.initializeCharts();
		this.bindEvents();
	}

	createDashboard() {
		const existingDashboard = document.querySelector(
			'.wcefp-advanced-analytics'
		);
		if ( existingDashboard ) return;

		const dashboard = document.createElement( 'div' );
		dashboard.className = 'wcefp-advanced-analytics';
		dashboard.innerHTML = this.getDashboardHTML();

		// Insert after existing KPI dashboard or at the beginning of content
		const kpiDashboard = document.querySelector( '.wcefp-kpi-dashboard' );
		const insertTarget = kpiDashboard || document.querySelector( '.wrap' );

		if ( insertTarget ) {
			insertTarget.parentNode.insertBefore(
				dashboard,
				kpiDashboard
					? kpiDashboard.nextSibling
					: insertTarget.firstChild
			);
		}
	}

	getDashboardHTML() {
		return `
            <div class="wcefp-analytics-header">
                <h2>📊 Analytics Avanzate</h2>
                <div class="wcefp-analytics-controls">
                    <select class="wcefp-time-range" id="wcefp-time-range">
                        <option value="7">Ultimi 7 giorni</option>
                        <option value="30" selected>Ultimi 30 giorni</option>
                        <option value="90">Ultimi 3 mesi</option>
                        <option value="365">Ultimo anno</option>
                    </select>
                    <button class="wcefp-refresh-btn" id="wcefp-refresh-analytics">
                        🔄 Aggiorna
                    </button>
                    <button class="wcefp-export-btn" id="wcefp-export-analytics">
                        📊 Esporta
                    </button>
                </div>
            </div>

            <!-- Real-time metrics -->
            <div class="wcefp-realtime-metrics">
                <h3>📈 Metriche in Tempo Reale</h3>
                <div class="wcefp-metrics-grid">
                    <div class="wcefp-metric-card wcefp-realtime-visitors">
                        <div class="wcefp-metric-icon">👥</div>
                        <div class="wcefp-metric-content">
                            <div class="wcefp-metric-value" id="realtime-visitors">--</div>
                            <div class="wcefp-metric-label">Visitatori Attivi</div>
                            <div class="wcefp-metric-trend" id="visitors-trend"></div>
                        </div>
                    </div>
                    <div class="wcefp-metric-card wcefp-realtime-bookings">
                        <div class="wcefp-metric-icon">🎯</div>
                        <div class="wcefp-metric-content">
                            <div class="wcefp-metric-value" id="realtime-bookings">--</div>
                            <div class="wcefp-metric-label">Prenotazioni Oggi</div>
                            <div class="wcefp-metric-trend" id="bookings-trend"></div>
                        </div>
                    </div>
                    <div class="wcefp-metric-card wcefp-realtime-revenue">
                        <div class="wcefp-metric-icon">💰</div>
                        <div class="wcefp-metric-content">
                            <div class="wcefp-metric-value" id="realtime-revenue">--</div>
                            <div class="wcefp-metric-label">Ricavi Oggi</div>
                            <div class="wcefp-metric-trend" id="revenue-trend"></div>
                        </div>
                    </div>
                    <div class="wcefp-metric-card wcefp-realtime-conversion">
                        <div class="wcefp-metric-icon">🎯</div>
                        <div class="wcefp-metric-content">
                            <div class="wcefp-metric-value" id="realtime-conversion">--</div>
                            <div class="wcefp-metric-label">Tasso Conversione</div>
                            <div class="wcefp-metric-trend" id="conversion-trend"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced charts section -->
            <div class="wcefp-charts-section">
                <div class="wcefp-charts-grid">
                    <!-- Revenue prediction chart -->
                    <div class="wcefp-chart-container">
                        <div class="wcefp-chart-header">
                            <h3>📈 Previsione Ricavi</h3>
                            <div class="wcefp-chart-controls">
                                <select id="prediction-model">
                                    <option value="linear">Lineare</option>
                                    <option value="seasonal">Stagionale</option>
                                    <option value="trend">Trend</option>
                                </select>
                            </div>
                        </div>
                        <div class="wcefp-chart-content">
                            <canvas id="revenue-prediction-chart"></canvas>
                        </div>
                    </div>

                    <!-- Customer journey funnel -->
                    <div class="wcefp-chart-container">
                        <div class="wcefp-chart-header">
                            <h3>🎯 Customer Journey</h3>
                        </div>
                        <div class="wcefp-chart-content">
                            <canvas id="customer-journey-chart"></canvas>
                        </div>
                    </div>

                    <!-- Booking patterns heatmap -->
                    <div class="wcefp-chart-container wcefp-heatmap-container">
                        <div class="wcefp-chart-header">
                            <h3>🗓️ Pattern di Prenotazione</h3>
                        </div>
                        <div class="wcefp-chart-content">
                            <div id="booking-heatmap"></div>
                        </div>
                    </div>

                    <!-- Customer segmentation -->
                    <div class="wcefp-chart-container">
                        <div class="wcefp-chart-header">
                            <h3>👥 Segmentazione Clienti</h3>
                        </div>
                        <div class="wcefp-chart-content">
                            <canvas id="customer-segments-chart"></canvas>
                        </div>
                    </div>

                    <!-- Performance insights -->
                    <div class="wcefp-chart-container wcefp-insights-container">
                        <div class="wcefp-chart-header">
                            <h3>🔍 Insights & Raccomandazioni</h3>
                        </div>
                        <div class="wcefp-chart-content">
                            <div id="performance-insights"></div>
                        </div>
                    </div>

                    <!-- A/B Testing results -->
                    <div class="wcefp-chart-container">
                        <div class="wcefp-chart-header">
                            <h3>🧪 A/B Testing</h3>
                            <button class="wcefp-ab-test-btn" id="create-ab-test">+ Nuovo Test</button>
                        </div>
                        <div class="wcefp-chart-content">
                            <div id="ab-testing-results"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts and notifications -->
            <div class="wcefp-alerts-section" id="wcefp-analytics-alerts"></div>
        `;
	}

	async loadAnalyticsData( timeRange = 30 ) {
		try {
			const response = await fetch( this.apiEndpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'wcefp_get_advanced_analytics',
					nonce: this.nonce,
					time_range: timeRange,
					include_predictions: true,
					include_segments: true,
				} ),
			} );

			const data = await response.json();
			if ( data.success ) {
				this.updateDashboard( data.data );
				this.generatePredictions( data.data );
				this.analyzePerformance( data.data );
			}
		} catch ( error ) {
			console.error( 'Failed to load analytics data:', error );
			this.showError( 'Impossibile caricare i dati di analytics' );
		}
	}

	updateDashboard( data ) {
		this.updateRealTimeMetrics( data.realtime || {} );
		this.updateCharts( data );
		this.updateInsights( data.insights || [] );
		this.updateAlerts( data.alerts || [] );
	}

	updateRealTimeMetrics( realtime ) {
		const updates = {
			'realtime-visitors': realtime.active_visitors || 0,
			'realtime-bookings': realtime.bookings_today || 0,
			'realtime-revenue': `€${ this.formatNumber(
				realtime.revenue_today || 0
			) }`,
			'realtime-conversion': `${ (
				realtime.conversion_rate || 0
			).toFixed( 1 ) }%`,
		};

		Object.entries( updates ).forEach( ( [ id, value ] ) => {
			const element = document.getElementById( id );
			if ( element ) {
				this.animateCounter( element, value );
			}
		} );

		// Update trend indicators
		this.updateTrendIndicators( realtime.trends || {} );
	}

	animateCounter( element, newValue ) {
		const currentValue = element.textContent;
		const isNumeric = ! isNaN( parseFloat( currentValue ) );

		if ( isNumeric && ! isNaN( parseFloat( newValue ) ) ) {
			const start = parseFloat( currentValue ) || 0;
			const end = parseFloat( newValue );
			const duration = 1000;
			const startTime = performance.now();

			const animate = ( currentTime ) => {
				const elapsed = currentTime - startTime;
				const progress = Math.min( elapsed / duration, 1 );

				const current =
					start + ( end - start ) * this.easeOutCubic( progress );
				element.textContent = Math.round( current );

				if ( progress < 1 ) {
					requestAnimationFrame( animate );
				}
			};

			requestAnimationFrame( animate );
		} else {
			element.textContent = newValue;
		}
	}

	easeOutCubic( x ) {
		return 1 - Math.pow( 1 - x, 3 );
	}

	updateTrendIndicators( trends ) {
		const trendElements = {
			'visitors-trend': trends.visitors || 0,
			'bookings-trend': trends.bookings || 0,
			'revenue-trend': trends.revenue || 0,
			'conversion-trend': trends.conversion || 0,
		};

		Object.entries( trendElements ).forEach( ( [ id, trend ] ) => {
			const element = document.getElementById( id );
			if ( element ) {
				const isPositive = trend > 0;
				const arrow = isPositive ? '↗️' : trend < 0 ? '↘️' : '➡️';
				const color = isPositive
					? '#10b981'
					: trend < 0
					? '#ef4444'
					: '#64748b';

				element.innerHTML = `
                    <span style="color: ${ color }">
                        ${ arrow } ${ Math.abs( trend ).toFixed( 1 ) }%
                    </span>
                `;
			}
		} );
	}

	initializeCharts() {
		// Revenue prediction chart
		const revenueCtx = document.getElementById(
			'revenue-prediction-chart'
		);
		if ( revenueCtx ) {
			this.charts.revenuePrediction = new Chart( revenueCtx, {
				type: 'line',
				data: {
					labels: [],
					datasets: [
						{
							label: 'Ricavi Storici',
							data: [],
							borderColor: 'rgb(79, 70, 229)',
							backgroundColor: 'rgba(79, 70, 229, 0.1)',
							tension: 0.4,
						},
						{
							label: 'Previsione',
							data: [],
							borderColor: 'rgb(245, 158, 11)',
							backgroundColor: 'rgba(245, 158, 11, 0.1)',
							borderDash: [ 5, 5 ],
							tension: 0.4,
						},
					],
				},
				options: this.getChartOptions( 'Revenue (€)' ),
			} );
		}

		// Customer journey funnel
		const journeyCtx = document.getElementById( 'customer-journey-chart' );
		if ( journeyCtx ) {
			this.charts.customerJourney = new Chart( journeyCtx, {
				type: 'bar',
				data: {
					labels: [
						'Visitatori',
						'Interessati',
						'Carrello',
						'Checkout',
						'Acquisto',
					],
					datasets: [
						{
							label: 'Utenti',
							data: [],
							backgroundColor: [
								'rgba(79, 70, 229, 0.8)',
								'rgba(99, 102, 241, 0.8)',
								'rgba(245, 158, 11, 0.8)',
								'rgba(239, 68, 68, 0.8)',
								'rgba(16, 185, 129, 0.8)',
							],
						},
					],
				},
				options: this.getChartOptions( 'Utenti' ),
			} );
		}

		// Customer segments
		const segmentsCtx = document.getElementById(
			'customer-segments-chart'
		);
		if ( segmentsCtx ) {
			this.charts.customerSegments = new Chart( segmentsCtx, {
				type: 'doughnut',
				data: {
					labels: [],
					datasets: [
						{
							data: [],
							backgroundColor: [
								'#4f46e5',
								'#06b6d4',
								'#10b981',
								'#f59e0b',
								'#ef4444',
							],
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
						},
					},
				},
			} );
		}
	}

	getChartOptions( yAxisLabel ) {
		return {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: true,
				},
			},
			scales: {
				y: {
					beginAtZero: true,
					title: {
						display: true,
						text: yAxisLabel,
					},
				},
			},
			interaction: {
				intersect: false,
				mode: 'index',
			},
		};
	}

	updateCharts( data ) {
		// Update revenue prediction chart
		if ( this.charts.revenuePrediction && data.revenue_data ) {
			this.charts.revenuePrediction.data.labels =
				data.revenue_data.labels;
			this.charts.revenuePrediction.data.datasets[ 0 ].data =
				data.revenue_data.historical;
			this.charts.revenuePrediction.data.datasets[ 1 ].data =
				data.revenue_data.prediction;
			this.charts.revenuePrediction.update();
		}

		// Update customer journey
		if ( this.charts.customerJourney && data.journey_data ) {
			this.charts.customerJourney.data.datasets[ 0 ].data =
				data.journey_data.values;
			this.charts.customerJourney.update();
		}

		// Update customer segments
		if ( this.charts.customerSegments && data.segments_data ) {
			this.charts.customerSegments.data.labels =
				data.segments_data.labels;
			this.charts.customerSegments.data.datasets[ 0 ].data =
				data.segments_data.values;
			this.charts.customerSegments.update();
		}

		// Update booking heatmap
		this.updateBookingHeatmap( data.booking_patterns || {} );
	}

	updateBookingHeatmap( patterns ) {
		const heatmapContainer = document.getElementById( 'booking-heatmap' );
		if ( ! heatmapContainer || ! patterns.data ) return;

		const days = [ 'Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab' ];
		const hours = Array.from( { length: 24 }, ( _, i ) => i );

		let html = '<div class="wcefp-heatmap-grid">';

		// Header row
		html += '<div class="wcefp-heatmap-row">';
		html += '<div class="wcefp-heatmap-cell wcefp-heatmap-header"></div>';
		hours.forEach( ( hour ) => {
			html += `<div class="wcefp-heatmap-cell wcefp-heatmap-header">${ hour }</div>`;
		} );
		html += '</div>';

		// Data rows
		days.forEach( ( day, dayIndex ) => {
			html += '<div class="wcefp-heatmap-row">';
			html += `<div class="wcefp-heatmap-cell wcefp-heatmap-header">${ day }</div>`;

			hours.forEach( ( hour ) => {
				const value = patterns.data[ dayIndex ]?.[ hour ] || 0;
				const intensity = Math.min(
					( value / patterns.max ) * 100,
					100
				);
				const color = this.getHeatmapColor( intensity );

				html += `
                    <div class="wcefp-heatmap-cell wcefp-heatmap-data" 
                         style="background-color: ${ color }"
                         title="${ day } ${ hour }:00 - ${ value } prenotazioni">
                        ${ value > 0 ? value : '' }
                    </div>
                `;
			} );
			html += '</div>';
		} );

		html += '</div>';
		heatmapContainer.innerHTML = html;
	}

	getHeatmapColor( intensity ) {
		if ( intensity === 0 ) return 'transparent';
		const alpha = Math.max( intensity / 100, 0.1 );
		return `rgba(79, 70, 229, ${ alpha })`;
	}

	generatePredictions( data ) {
		const model =
			document.getElementById( 'prediction-model' )?.value || 'linear';

		// Simple linear regression for demo
		if ( data.revenue_data?.historical ) {
			const historical = data.revenue_data.historical;
			const predictions = this.predictWithLinearRegression(
				historical,
				7
			);

			// Update prediction data in chart
			if ( this.charts.revenuePrediction ) {
				const currentLabels = [
					...this.charts.revenuePrediction.data.labels,
				];
				const predictionLabels = this.generateFutureDates(
					currentLabels.length,
					7
				);

				this.charts.revenuePrediction.data.labels = [
					...currentLabels,
					...predictionLabels,
				];
				this.charts.revenuePrediction.data.datasets[ 1 ].data = [
					...Array( currentLabels.length ).fill( null ),
					...predictions,
				];
				this.charts.revenuePrediction.update();
			}
		}
	}

	predictWithLinearRegression( data, periods ) {
		const n = data.length;
		const x = data.map( ( _, i ) => i );
		const y = data;

		const sumX = x.reduce( ( a, b ) => a + b, 0 );
		const sumY = y.reduce( ( a, b ) => a + b, 0 );
		const sumXY = x.reduce( ( sum, xi, i ) => sum + xi * y[ i ], 0 );
		const sumXX = x.reduce( ( sum, xi ) => sum + xi * xi, 0 );

		const slope = ( n * sumXY - sumX * sumY ) / ( n * sumXX - sumX * sumX );
		const intercept = ( sumY - slope * sumX ) / n;

		const predictions = [];
		for ( let i = 0; i < periods; i++ ) {
			const x_pred = n + i;
			predictions.push( Math.max( 0, slope * x_pred + intercept ) );
		}

		return predictions;
	}

	generateFutureDates( currentLength, periods ) {
		const dates = [];
		const today = new Date();

		for ( let i = 1; i <= periods; i++ ) {
			const futureDate = new Date( today );
			futureDate.setDate( today.getDate() + i );
			dates.push( futureDate.toLocaleDateString() );
		}

		return dates;
	}

	updateInsights( insights ) {
		const container = document.getElementById( 'performance-insights' );
		if ( ! container ) return;

		const html = insights
			.map(
				( insight ) => `
            <div class="wcefp-insight-card ${ insight.type }">
                <div class="wcefp-insight-icon">${ this.getInsightIcon(
					insight.type
				) }</div>
                <div class="wcefp-insight-content">
                    <h4>${ insight.title }</h4>
                    <p>${ insight.description }</p>
                    ${
						insight.action
							? `<button class="wcefp-insight-action" data-action="${ insight.action }">${ insight.actionText }</button>`
							: ''
					}
                </div>
                <div class="wcefp-insight-impact">${ insight.impact }</div>
            </div>
        `
			)
			.join( '' );

		container.innerHTML =
			html || '<p>Nessun insight disponibile al momento</p>';

		// Bind insight action buttons
		container
			.querySelectorAll( '.wcefp-insight-action' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					const action = e.target.dataset.action;
					this.executeInsightAction( action );
				} );
			} );
	}

	getInsightIcon( type ) {
		const icons = {
			opportunity: '🎯',
			warning: '⚠️',
			success: '✅',
			recommendation: '💡',
			trend: '📈',
		};
		return icons[ type ] || '📊';
	}

	executeInsightAction( action ) {
		// Handle different insight actions
		switch ( action ) {
			case 'optimize_pricing':
				this.showPricingOptimizationModal();
				break;
			case 'improve_conversion':
				this.showConversionOptimizationModal();
				break;
			case 'schedule_promotion':
				this.showPromotionScheduler();
				break;
			default:
				console.log( 'Unknown insight action:', action );
		}
	}

	updateAlerts( alerts ) {
		const container = document.getElementById( 'wcefp-analytics-alerts' );
		if ( ! container ) return;

		if ( alerts.length === 0 ) {
			container.innerHTML = '';
			return;
		}

		const html = `
            <h3>🚨 Alerts</h3>
            <div class="wcefp-alerts-list">
                ${ alerts
					.map(
						( alert ) => `
                    <div class="wcefp-alert ${ alert.severity }">
                        <div class="wcefp-alert-icon">${ this.getAlertIcon(
							alert.severity
						) }</div>
                        <div class="wcefp-alert-content">
                            <strong>${ alert.title }</strong>
                            <p>${ alert.message }</p>
                            <small>${ this.formatDate(
								alert.timestamp
							) }</small>
                        </div>
                        <button class="wcefp-alert-dismiss" data-alert-id="${
							alert.id
						}">✕</button>
                    </div>
                `
					)
					.join( '' ) }
            </div>
        `;

		container.innerHTML = html;

		// Bind dismiss buttons
		container
			.querySelectorAll( '.wcefp-alert-dismiss' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					const alertId = e.target.dataset.alertId;
					this.dismissAlert( alertId );
				} );
			} );
	}

	getAlertIcon( severity ) {
		const icons = {
			critical: '🔴',
			warning: '🟡',
			info: '🔵',
		};
		return icons[ severity ] || '🔵';
	}

	setupRealTimeUpdates() {
		// Update real-time metrics every 30 seconds
		this.realTimeInterval = setInterval( () => {
			this.fetchRealTimeMetrics();
		}, 30000 );

		// Update full dashboard every 5 minutes
		setInterval( () => {
			const timeRange =
				document.getElementById( 'wcefp-time-range' )?.value || 30;
			this.loadAnalyticsData( parseInt( timeRange ) );
		}, 300000 );
	}

	async fetchRealTimeMetrics() {
		try {
			const response = await fetch( this.apiEndpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'wcefp_get_realtime_metrics',
					nonce: this.nonce,
				} ),
			} );

			const data = await response.json();
			if ( data.success ) {
				this.updateRealTimeMetrics( data.data );
			}
		} catch ( error ) {
			console.error( 'Failed to update real-time metrics:', error );
		}
	}

	bindEvents() {
		// Time range selector
		const timeRangeSelect = document.getElementById( 'wcefp-time-range' );
		if ( timeRangeSelect ) {
			timeRangeSelect.addEventListener( 'change', ( e ) => {
				const timeRange = parseInt( e.target.value );
				this.loadAnalyticsData( timeRange );
			} );
		}

		// Refresh button
		const refreshBtn = document.getElementById( 'wcefp-refresh-analytics' );
		if ( refreshBtn ) {
			refreshBtn.addEventListener( 'click', () => {
				const timeRange =
					document.getElementById( 'wcefp-time-range' )?.value || 30;
				this.loadAnalyticsData( parseInt( timeRange ) );
			} );
		}

		// Export button
		const exportBtn = document.getElementById( 'wcefp-export-analytics' );
		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', () => {
				this.exportAnalytics();
			} );
		}
	}

	formatNumber( num ) {
		return new Intl.NumberFormat( 'it-IT', {
			minimumFractionDigits: 0,
			maximumFractionDigits: 2,
		} ).format( num );
	}

	formatDate( timestamp ) {
		return new Date( timestamp ).toLocaleString( 'it-IT' );
	}

	showError( message ) {
		const notification = document.createElement( 'div' );
		notification.className = 'wcefp-notification wcefp-notification-error';
		notification.textContent = message;
		document.body.appendChild( notification );

		setTimeout( () => {
			notification.remove();
		}, 5000 );
	}

	async exportAnalytics() {
		try {
			const timeRange =
				document.getElementById( 'wcefp-time-range' )?.value || 30;

			const response = await fetch( this.apiEndpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'wcefp_export_analytics',
					nonce: this.nonce,
					time_range: timeRange,
					format: 'csv',
				} ),
			} );

			const blob = await response.blob();
			const url = window.URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.style.display = 'none';
			a.href = url;
			a.download = `wcefp-analytics-${
				new Date().toISOString().split( 'T' )[ 0 ]
			}.csv`;
			document.body.appendChild( a );
			a.click();
			window.URL.revokeObjectURL( url );
		} catch ( error ) {
			console.error( 'Failed to export analytics:', error );
			this.showError( "Errore durante l'esportazione dei dati" );
		}
	}

	destroy() {
		if ( this.realTimeInterval ) {
			clearInterval( this.realTimeInterval );
		}

		Object.values( this.charts ).forEach( ( chart ) => {
			if ( chart && chart.destroy ) {
				chart.destroy();
			}
		} );
	}
}

// Initialize analytics dashboard
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		if ( typeof Chart !== 'undefined' ) {
			window.wcefpAdvancedAnalytics = new WCEFPAdvancedAnalytics();
		}
	} );
} else if ( typeof Chart !== 'undefined' ) {
	window.wcefpAdvancedAnalytics = new WCEFPAdvancedAnalytics();
}

// Export for module systems
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = WCEFPAdvancedAnalytics;
}
