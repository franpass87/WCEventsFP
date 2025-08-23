/**
 * Analytics Dashboard JavaScript
 *
 * Handles Chart.js visualizations and dashboard interactions
 * Part of Phase 6: Analytics & Automation
 *
 * @package
 * @subpackage Assets
 * @since 2.2.0
 */

( function ( $ ) {
	'use strict';

	class WCEFPAnalyticsDashboard {
		constructor() {
			this.charts = {};
			this.currentDateRange = '30days';
			this.init();
		}

		init() {
			this.bindEvents();
			this.initDateRangeFilter();
			this.loadDashboardData();
		}

		bindEvents() {
			// Date range filter
			$( document ).on(
				'change',
				'#wcefp-date-range-filter',
				this.handleDateRangeChange.bind( this )
			);

			// Refresh button
			$( document ).on(
				'click',
				'#wcefp-refresh-dashboard',
				this.refreshDashboard.bind( this )
			);

			// Export buttons
			$( document ).on(
				'click',
				'.wcefp-export-btn',
				this.handleExport.bind( this )
			);

			// Chart interactions
			$( document ).on(
				'click',
				'.wcefp-chart-filter',
				this.handleChartFilter.bind( this )
			);
		}

		initDateRangeFilter() {
			// Initialize date picker if custom range is selected
			$( '#wcefp-custom-date-start, #wcefp-custom-date-end' ).datepicker(
				{
					dateFormat: 'yy-mm-dd',
					onSelect: this.handleCustomDateChange.bind( this ),
				}
			);
		}

		handleDateRangeChange( e ) {
			this.currentDateRange = $( e.target ).val();

			if ( this.currentDateRange === 'custom' ) {
				$( '.wcefp-custom-date-range' ).show();
			} else {
				$( '.wcefp-custom-date-range' ).hide();
				this.loadDashboardData();
			}
		}

		handleCustomDateChange() {
			const startDate = $( '#wcefp-custom-date-start' ).val();
			const endDate = $( '#wcefp-custom-date-end' ).val();

			if ( startDate && endDate ) {
				this.loadDashboardData( startDate, endDate );
			}
		}

		loadDashboardData( startDate = null, endDate = null ) {
			this.showLoading( true );

			const data = {
				action: 'wcefp_get_dashboard_data',
				nonce: wcefp_analytics.nonce,
				date_range: this.currentDateRange,
				start_date: startDate,
				end_date: endDate,
			};

			$.post( wcefp_analytics.ajax_url, data )
				.done( this.handleDashboardDataSuccess.bind( this ) )
				.fail( this.handleDashboardDataError.bind( this ) )
				.always( () => this.showLoading( false ) );
		}

		handleDashboardDataSuccess( response ) {
			if ( response.success ) {
				this.updateMetricCards( response.data );
				this.loadBookingTrends();
				this.loadRevenueAnalytics();
				this.loadEventPerformance();
				this.updateRecentActivities( response.data.recent_activities );
			} else {
				this.showError( response.data.message );
			}
		}

		handleDashboardDataError( xhr, status, error ) {
			this.showError( wcefp_analytics.strings.error + ': ' + error );
		}

		updateMetricCards( data ) {
			// Update overview metric cards
			$(
				'.wcefp-metric[data-metric="bookings"] .wcefp-metric-value'
			).text( this.formatNumber( data.total_bookings ) );

			$(
				'.wcefp-metric[data-metric="revenue"] .wcefp-metric-value'
			).text( this.formatCurrency( data.total_revenue ) );

			$( '.wcefp-metric[data-metric="events"] .wcefp-metric-value' ).text(
				this.formatNumber( data.total_events )
			);

			$(
				'.wcefp-metric[data-metric="customers"] .wcefp-metric-value'
			).text( this.formatNumber( data.total_customers ) );

			// Update growth indicators
			this.updateGrowthIndicator(
				'bookings',
				data.growth_rates.bookings
			);
			this.updateGrowthIndicator( 'revenue', data.growth_rates.revenue );
		}

		updateGrowthIndicator( metric, growthRate ) {
			const indicator = $(
				`.wcefp-metric[data-metric="${ metric }"] .wcefp-growth-indicator`
			);
			const isPositive = growthRate >= 0;

			indicator
				.removeClass( 'positive negative' )
				.addClass( isPositive ? 'positive' : 'negative' )
				.find( '.wcefp-growth-value' )
				.text( Math.abs( growthRate ).toFixed( 1 ) + '%' );

			indicator
				.find( '.wcefp-growth-arrow' )
				.removeClass( 'up down' )
				.addClass( isPositive ? 'up' : 'down' );
		}

		loadBookingTrends() {
			const data = {
				action: 'wcefp_get_booking_trends',
				nonce: wcefp_analytics.nonce,
				date_range: this.currentDateRange,
				period: 'daily',
			};

			$.post( wcefp_analytics.ajax_url, data )
				.done( this.handleBookingTrendsSuccess.bind( this ) )
				.fail( () => this.showChartError( 'booking-trends' ) );
		}

		handleBookingTrendsSuccess( response ) {
			if ( response.success && response.data ) {
				this.renderBookingTrendsChart( response.data );
			}
		}

		renderBookingTrendsChart( data ) {
			const ctx = document.getElementById( 'wcefp-booking-trends-chart' );
			if ( ! ctx ) return;

			// Destroy existing chart if it exists
			if ( this.charts.bookingTrends ) {
				this.charts.bookingTrends.destroy();
			}

			this.charts.bookingTrends = new Chart( ctx, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [
						{
							label: wcefp_analytics.strings.bookings,
							data: data.bookings,
							borderColor: wcefp_analytics.chart_colors.primary,
							backgroundColor:
								wcefp_analytics.chart_colors.primary + '20',
							tension: 0.4,
							fill: true,
						},
						{
							label: wcefp_analytics.strings.revenue,
							data: data.revenue,
							borderColor: wcefp_analytics.chart_colors.secondary,
							backgroundColor:
								wcefp_analytics.chart_colors.secondary + '20',
							tension: 0.4,
							yAxisID: 'y1',
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						intersect: false,
					},
					scales: {
						x: {
							display: true,
							title: {
								display: true,
								text: 'Date',
							},
						},
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: wcefp_analytics.strings.bookings,
							},
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							title: {
								display: true,
								text: wcefp_analytics.strings.revenue,
							},
							grid: {
								drawOnChartArea: false,
							},
						},
					},
					plugins: {
						legend: {
							position: 'top',
						},
						tooltip: {
							callbacks: {
								afterLabel: ( context ) => {
									if ( context.datasetIndex === 1 ) {
										return this.formatCurrency(
											context.raw
										);
									}
									return '';
								},
							},
						},
					},
				},
			} );
		}

		loadRevenueAnalytics() {
			const data = {
				action: 'wcefp_get_revenue_analytics',
				nonce: wcefp_analytics.nonce,
				date_range: this.currentDateRange,
			};

			$.post( wcefp_analytics.ajax_url, data )
				.done( this.handleRevenueAnalyticsSuccess.bind( this ) )
				.fail( () => this.showChartError( 'revenue-analytics' ) );
		}

		handleRevenueAnalyticsSuccess( response ) {
			if ( response.success && response.data ) {
				this.renderRevenueByEventChart( response.data.by_event );
			}
		}

		renderRevenueByEventChart( data ) {
			const ctx = document.getElementById(
				'wcefp-revenue-by-event-chart'
			);
			if ( ! ctx || ! data.length ) return;

			if ( this.charts.revenueByEvent ) {
				this.charts.revenueByEvent.destroy();
			}

			this.charts.revenueByEvent = new Chart( ctx, {
				type: 'doughnut',
				data: {
					labels: data.map( ( item ) => item.event_title ),
					datasets: [
						{
							data: data.map( ( item ) =>
								parseFloat( item.revenue )
							),
							backgroundColor: [
								wcefp_analytics.chart_colors.primary,
								wcefp_analytics.chart_colors.secondary,
								wcefp_analytics.chart_colors.warning,
								wcefp_analytics.chart_colors.danger,
								wcefp_analytics.chart_colors.info,
							],
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'right',
						},
						tooltip: {
							callbacks: {
								label: ( context ) => {
									const label = context.label || '';
									const value = this.formatCurrency(
										context.raw
									);
									return `${ label }: ${ value }`;
								},
							},
						},
					},
				},
			} );
		}

		loadEventPerformance() {
			const data = {
				action: 'wcefp_get_event_performance',
				nonce: wcefp_analytics.nonce,
				date_range: this.currentDateRange,
				limit: 10,
			};

			$.post( wcefp_analytics.ajax_url, data )
				.done( this.handleEventPerformanceSuccess.bind( this ) )
				.fail( () =>
					this.showError( 'Failed to load event performance data' )
				);
		}

		handleEventPerformanceSuccess( response ) {
			if ( response.success && response.data ) {
				this.updateEventPerformanceTable( response.data );
			}
		}

		updateEventPerformanceTable( data ) {
			const tbody = $( '#wcefp-event-performance-table tbody' );
			tbody.empty();

			data.forEach( ( event ) => {
				const row = `
                    <tr>
                        <td>${ this.escapeHtml( event.event_title ) }</td>
                        <td>${ this.formatNumber( event.booking_count ) }</td>
                        <td>${ this.formatCurrency( event.revenue ) }</td>
                        <td>${ this.formatCurrency(
							event.avg_booking_value
						) }</td>
                        <td>
                            <div class="wcefp-occupancy-bar">
                                <div class="wcefp-occupancy-fill" style="width: ${
									event.occupancy_rate
								}%"></div>
                                <span class="wcefp-occupancy-text">${ event.occupancy_rate.toFixed(
									1
								) }%</span>
                            </div>
                        </td>
                    </tr>
                `;
				tbody.append( row );
			} );
		}

		updateRecentActivities( activities ) {
			const container = $( '#wcefp-recent-activities' );
			container.empty();

			if ( ! activities || ! activities.length ) {
				container.append(
					'<p>' + wcefp_analytics.strings.no_data + '</p>'
				);
				return;
			}

			const list = $( '<ul class="wcefp-activity-list"></ul>' );

			activities.forEach( ( activity ) => {
				const listItem = `
                    <li class="wcefp-activity-item">
                        <div class="wcefp-activity-content">
                            <strong>${ this.escapeHtml(
								activity.booking_title
							) }</strong>
                            <span class="wcefp-activity-email">${ this.escapeHtml(
								activity.customer_email
							) }</span>
                        </div>
                        <div class="wcefp-activity-meta">
                            <span class="wcefp-activity-date">${ this.formatDate(
								activity.created_date
							) }</span>
                            <span class="wcefp-activity-status status-${
								activity.status
							}">${ activity.status }</span>
                        </div>
                    </li>
                `;
				list.append( listItem );
			} );

			container.append( list );
		}

		refreshDashboard() {
			this.loadDashboardData();
		}

		handleExport( e ) {
			const exportType = $( e.target ).data( 'export-type' );
			const exportData = {
				action: 'wcefp_export_analytics_data',
				nonce: wcefp_analytics.nonce,
				export_type: exportType,
				date_range: this.currentDateRange,
			};

			// Create download link
			const params = new URLSearchParams( exportData );
			window.open(
				`${ wcefp_analytics.ajax_url }?${ params.toString() }`,
				'_blank'
			);
		}

		showLoading( show ) {
			const loader = $( '.wcefp-dashboard-loader' );
			if ( show ) {
				loader.show();
				$( '.wcefp-dashboard-content' ).css( 'opacity', '0.6' );
			} else {
				loader.hide();
				$( '.wcefp-dashboard-content' ).css( 'opacity', '1' );
			}
		}

		showError( message ) {
			const notice = $( `
                <div class="notice notice-error is-dismissible">
                    <p>${ this.escapeHtml( message ) }</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            ` );

			$( '.wcefp-dashboard-notices' ).append( notice );
		}

		showChartError( chartId ) {
			const chartContainer = $( `#${ chartId }-chart` ).parent();
			chartContainer.html( `
                <div class="wcefp-chart-error">
                    <p>${ wcefp_analytics.strings.error }</p>
                </div>
            ` );
		}

		// Utility methods
		formatNumber( number ) {
			return new Intl.NumberFormat().format( number );
		}

		formatCurrency( amount ) {
			return (
				wcefp_analytics.currency_symbol +
				this.formatNumber( parseFloat( amount ).toFixed( 2 ) )
			);
		}

		formatDate( dateString ) {
			const date = new Date( dateString );
			return date.toLocaleDateString();
		}

		escapeHtml( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}
	}

	// Initialize dashboard when document is ready
	$( document ).ready( function () {
		if ( $( '.wcefp-analytics-dashboard' ).length ) {
			new WCEFPAnalyticsDashboard();
		}
	} );

	// Export for global access
	window.WCEFPAnalyticsDashboard = WCEFPAnalyticsDashboard;
} )( jQuery );
