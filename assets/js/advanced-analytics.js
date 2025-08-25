/**
 * WCEFP Advanced Analytics Dashboard JavaScript
 * Handles interactive charts, real-time data updates, and optimization tools
 * @since 2.2.2
 */

(function($) {
    'use strict';

    // Global variables
    let chartInstances = {};
    let refreshInterval = null;
    let currentTimeframe = '7d';

    /**
     * Initialize Advanced Analytics Dashboard
     */
    function initAdvancedAnalytics() {
        // Initialize tabs
        initTabs();
        
        // Initialize controls
        initControls();
        
        // Load initial data
        loadAnalyticsData();
        
        // Initialize charts
        initCharts();
        
        // Setup auto-refresh
        setupAutoRefresh();
        
        // Initialize optimization tools
        initOptimizationTools();
        
        console.log('WCEFP Advanced Analytics Dashboard initialized');
    }

    /**
     * Initialize tab navigation
     */
    function initTabs() {
        $('.wcefp-analytics-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).attr('href').substring(1);
            
            // Update active states
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.wcefp-tab-content').removeClass('tab-active');
            $('#' + targetTab).addClass('tab-active');
            
            // Load tab-specific data
            loadTabData(targetTab);
        });
    }

    /**
     * Initialize dashboard controls
     */
    function initControls() {
        // Refresh data button
        $('#wcefp-refresh-analytics').on('click', function() {
            $(this).find('.dashicons').addClass('spin');
            loadAnalyticsData().always(() => {
                $(this).find('.dashicons').removeClass('spin');
            });
        });

        // Performance optimization button
        $('#wcefp-optimize-performance').on('click', function() {
            optimizePerformance();
        });

        // Timeframe selector
        $('#wcefp-analytics-timeframe').on('change', function() {
            currentTimeframe = $(this).val();
            loadAnalyticsData();
            updateAllCharts();
        });

        // Export functionality
        $(document).on('click', '.export-btn', function() {
            const format = $(this).data('format');
            exportAnalyticsData(format);
        });
    }

    /**
     * Load analytics data from server
     */
    function loadAnalyticsData() {
        return $.ajax({
            url: wcefpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wcefp_get_analytics_data',
                nonce: wcefpAnalytics.nonce,
                timeframe: currentTimeframe
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateOverviewCards(response.data.overview);
                    updatePerformanceTrends(response.data.trends);
                    updateShortcodeAnalytics(response.data.shortcode_analytics);
                    updateApiMonitoring(response.data.api_monitoring);
                    updateHealthAlerts(response.data.health_alerts);
                    updatePredictiveInsights(response.data.predictive_insights);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load analytics data:', error);
                showNotification('Error loading analytics data', 'error');
            }
        });
    }

    /**
     * Update overview metric cards
     */
    function updateOverviewCards(overviewData) {
        if (!overviewData) return;

        // Update metric values
        $('[data-metric="performance_score"]').text(overviewData.performance_score || '--');
        $('[data-metric="avg_response_time"]').text((overviewData.avg_response_time || '--') + 'ms');
        $('[data-metric="memory_efficiency"]').text((overviewData.memory_efficiency || '--') + '%');
        $('[data-metric="error_rate"]').text((overviewData.error_rate || '--') + '%');

        // Update change indicators
        if (overviewData.changes) {
            Object.keys(overviewData.changes).forEach(metric => {
                const change = overviewData.changes[metric];
                const changeEl = $('[data-change="' + metric + '"]');
                
                if (change > 0) {
                    changeEl.text('+' + change + '%').removeClass('negative neutral').addClass('positive');
                } else if (change < 0) {
                    changeEl.text(change + '%').removeClass('positive neutral').addClass('negative');
                } else {
                    changeEl.text('No change').removeClass('positive negative').addClass('neutral');
                }
            });
        }
    }

    /**
     * Initialize Chart.js charts
     */
    function initCharts() {
        // Response Time Chart
        const responseTimeCtx = document.getElementById('response-time-chart');
        if (responseTimeCtx) {
            chartInstances.responseTime = new Chart(responseTimeCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: getChartOptions('Response Time (ms)')
            });
        }

        // Memory Usage Chart
        const memoryUsageCtx = document.getElementById('memory-usage-chart');
        if (memoryUsageCtx) {
            chartInstances.memoryUsage = new Chart(memoryUsageCtx, {
                type: 'area',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Memory Usage (%)',
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: getChartOptions('Memory Usage (%)', 0, 100)
            });
        }

        // Database Performance Chart
        const databaseCtx = document.getElementById('database-performance-chart');
        if (databaseCtx) {
            chartInstances.databasePerformance = new Chart(databaseCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Query Time (ms)',
                        data: [],
                        backgroundColor: '#f59e0b',
                        borderColor: '#d97706',
                        borderWidth: 1
                    }]
                },
                options: getChartOptions('Query Time (ms)')
            });
        }

        // Error Rate Chart
        const errorRateCtx = document.getElementById('error-rate-chart');
        if (errorRateCtx) {
            chartInstances.errorRate = new Chart(errorRateCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Error Rate (%)',
                        data: [],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: getChartOptions('Error Rate (%)', 0, 20)
            });
        }

        // Shortcode Usage Chart
        const shortcodeUsageCtx = document.getElementById('shortcode-usage-chart');
        if (shortcodeUsageCtx) {
            chartInstances.shortcodeUsage = new Chart(shortcodeUsageCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // API Response Chart
        const apiResponseCtx = document.getElementById('api-response-chart');
        if (apiResponseCtx) {
            chartInstances.apiResponse = new Chart(apiResponseCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Google Places API',
                            data: [],
                            borderColor: '#4285f4',
                            backgroundColor: 'rgba(66, 133, 244, 0.1)',
                            borderWidth: 2,
                            tension: 0.4
                        },
                        {
                            label: 'Google Reviews API',
                            data: [],
                            borderColor: '#ea4335',
                            backgroundColor: 'rgba(234, 67, 53, 0.1)',
                            borderWidth: 2,
                            tension: 0.4
                        }
                    ]
                },
                options: getChartOptions('Response Time (ms)')
            });
        }
    }

    /**
     * Get default chart options
     */
    function getChartOptions(yAxisLabel, yMin = null, yMax = null) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#4f46e5',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    min: yMin,
                    max: yMax,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + (yAxisLabel.includes('%') ? '%' : '');
                        }
                    },
                    title: {
                        display: true,
                        text: yAxisLabel
                    }
                }
            },
            elements: {
                point: {
                    radius: 3,
                    hoverRadius: 6
                }
            }
        };
    }

    /**
     * Update performance trends charts
     */
    function updatePerformanceTrends(trendsData) {
        if (!trendsData) return;

        // Update Response Time Chart
        if (chartInstances.responseTime && trendsData.response_time) {
            chartInstances.responseTime.data.labels = trendsData.timestamps || [];
            chartInstances.responseTime.data.datasets[0].data = trendsData.response_time;
            chartInstances.responseTime.update('none');
        }

        // Update Memory Usage Chart
        if (chartInstances.memoryUsage && trendsData.memory_usage) {
            chartInstances.memoryUsage.data.labels = trendsData.timestamps || [];
            chartInstances.memoryUsage.data.datasets[0].data = trendsData.memory_usage;
            chartInstances.memoryUsage.update('none');
        }

        // Update Error Rate Chart
        if (chartInstances.errorRate && trendsData.error_rate) {
            chartInstances.errorRate.data.labels = trendsData.timestamps || [];
            chartInstances.errorRate.data.datasets[0].data = trendsData.error_rate;
            chartInstances.errorRate.update('none');
        }
    }

    /**
     * Update shortcode analytics data
     */
    function updateShortcodeAnalytics(shortcodeData) {
        if (!shortcodeData) return;

        // Update popular shortcodes list
        if (shortcodeData.popular_shortcodes) {
            const popularList = $('#popular-shortcodes');
            popularList.empty();
            
            shortcodeData.popular_shortcodes.forEach(shortcode => {
                popularList.append(`
                    <div class="shortcode-item">
                        <span class="shortcode-name">[${shortcode.name}]</span>
                        <span class="shortcode-metric">${shortcode.usage_count} uses</span>
                    </div>
                `);
            });
        }

        // Update slow shortcodes list
        if (shortcodeData.slow_shortcodes) {
            const slowList = $('#slow-shortcodes');
            slowList.empty();
            
            shortcodeData.slow_shortcodes.forEach(shortcode => {
                slowList.append(`
                    <div class="shortcode-item">
                        <span class="shortcode-name">[${shortcode.name}]</span>
                        <span class="shortcode-metric slow">${shortcode.avg_render_time}ms</span>
                    </div>
                `);
            });
        }

        // Update error shortcodes list
        if (shortcodeData.error_shortcodes) {
            const errorList = $('#error-shortcodes');
            errorList.empty();
            
            shortcodeData.error_shortcodes.forEach(shortcode => {
                errorList.append(`
                    <div class="shortcode-item">
                        <span class="shortcode-name">[${shortcode.name}]</span>
                        <span class="shortcode-metric error">${shortcode.error_rate}% errors</span>
                    </div>
                `);
            });
        }

        // Update shortcode usage chart
        if (chartInstances.shortcodeUsage && shortcodeData.usage_trends) {
            chartInstances.shortcodeUsage.data.labels = shortcodeData.usage_trends.labels || [];
            chartInstances.shortcodeUsage.data.datasets[0].data = shortcodeData.usage_trends.data || [];
            chartInstances.shortcodeUsage.update('none');
        }
    }

    /**
     * Update API monitoring data
     */
    function updateApiMonitoring(apiData) {
        if (!apiData) return;

        // Update Google Places API status
        if (apiData.google_places) {
            const placesCard = $('[data-api="google-places"]');
            const placesStatus = apiData.google_places.status;
            
            placesCard.find('[data-status]').attr('data-status', placesStatus);
            placesCard.find('[data-metric="response_time"]').text(apiData.google_places.response_time + 'ms');
            placesCard.find('[data-metric="success_rate"]').text(apiData.google_places.success_rate + '%');
            placesCard.find('[data-metric="daily_requests"]').text(apiData.google_places.daily_requests.toLocaleString());
        }

        // Update Google Reviews API status
        if (apiData.google_reviews) {
            const reviewsCard = $('[data-api="google-reviews"]');
            const reviewsStatus = apiData.google_reviews.status;
            
            reviewsCard.find('[data-status]').attr('data-status', reviewsStatus);
            reviewsCard.find('[data-metric="response_time"]').text(apiData.google_reviews.response_time + 'ms');
            reviewsCard.find('[data-metric="success_rate"]').text(apiData.google_reviews.success_rate + '%');
            reviewsCard.find('[data-metric="cache_hit_rate"]').text(apiData.google_reviews.cache_hit_rate + '%');
        }

        // Update API response chart
        if (chartInstances.apiResponse && apiData.response_trends) {
            chartInstances.apiResponse.data.labels = apiData.response_trends.timestamps || [];
            chartInstances.apiResponse.data.datasets[0].data = apiData.response_trends.google_places || [];
            chartInstances.apiResponse.data.datasets[1].data = apiData.response_trends.google_reviews || [];
            chartInstances.apiResponse.update('none');
        }
    }

    /**
     * Update health alerts
     */
    function updateHealthAlerts(alertsData) {
        if (!alertsData || !Array.isArray(alertsData)) return;

        const alertsList = $('#recent-alerts-list');
        alertsList.empty();

        if (alertsData.length === 0) {
            alertsList.append('<p>No recent alerts</p>');
            return;
        }

        alertsData.forEach(alert => {
            const alertClass = `alert-${alert.type}`;
            const alertIcon = getAlertIcon(alert.type);
            
            alertsList.append(`
                <div class="alert-item ${alertClass}">
                    <div class="alert-icon">${alertIcon}</div>
                    <div class="alert-content">
                        <p class="alert-message">${alert.message}</p>
                        <p class="alert-timestamp">${alert.timestamp}</p>
                    </div>
                </div>
            `);
        });

        // Update health summary
        const summary = calculateHealthSummary(alertsData);
        updateHealthSummary(summary);
    }

    /**
     * Get alert icon based on type
     */
    function getAlertIcon(type) {
        const icons = {
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            memory: '💾',
            performance: '🚀'
        };
        return icons[type] || 'ℹ️';
    }

    /**
     * Calculate health summary from alerts
     */
    function calculateHealthSummary(alerts) {
        const summary = {
            good: 0,
            warning: 0,
            critical: 0
        };

        alerts.forEach(alert => {
            if (alert.type === 'error') {
                summary.critical++;
            } else if (alert.type === 'warning' || alert.type === 'memory' || alert.type === 'performance') {
                summary.warning++;
            } else {
                summary.good++;
            }
        });

        return summary;
    }

    /**
     * Update health summary display
     */
    function updateHealthSummary(summary) {
        const summaryGrid = $('#health-summary');
        summaryGrid.empty();

        summaryGrid.append(`
            <div class="health-summary-item status-good">
                <div class="summary-value">${summary.good}</div>
                <div class="summary-label">Good</div>
            </div>
            <div class="health-summary-item status-warning">
                <div class="summary-value">${summary.warning}</div>
                <div class="summary-label">Warning</div>
            </div>
            <div class="health-summary-item status-critical">
                <div class="summary-value">${summary.critical}</div>
                <div class="summary-label">Critical</div>
            </div>
        `);
    }

    /**
     * Update predictive insights
     */
    function updatePredictiveInsights(insightsData) {
        if (!insightsData) return;

        // Update performance prediction
        if (insightsData.performance_prediction) {
            $('#performance-prediction').text(insightsData.performance_prediction);
        }

        // Update capacity analysis
        if (insightsData.capacity_analysis) {
            $('#capacity-analysis').text(insightsData.capacity_analysis);
        }

        // Update recommendations
        if (insightsData.recommendations && Array.isArray(insightsData.recommendations)) {
            const recommendationsList = $('#optimization-recommendations');
            recommendationsList.empty();
            
            insightsData.recommendations.forEach(recommendation => {
                recommendationsList.append(`
                    <div class="recommendation-item">${recommendation}</div>
                `);
            });
        }
    }

    /**
     * Initialize optimization tools
     */
    function initOptimizationTools() {
        $(document).on('click', '[data-action]', function() {
            const action = $(this).data('action');
            const button = $(this);
            
            button.prop('disabled', true).text('Processing...');
            
            runOptimizationAction(action).always(() => {
                button.prop('disabled', false);
                // Reset button text based on action
                const buttonTexts = {
                    'clear-cache': 'Clear All Cache',
                    'optimize-cache': 'Optimize Cache',
                    'preload-cache': 'Preload Cache',
                    'optimize-tables': 'Optimize Tables',
                    'clean-expired': 'Clean Expired Data',
                    'rebuild-indexes': 'Rebuild Indexes',
                    'minify-assets': 'Minify Assets',
                    'combine-css': 'Combine CSS',
                    'optimize-images': 'Optimize Images'
                };
                button.text(buttonTexts[action] || 'Process');
            });
        });
    }

    /**
     * Run optimization action
     */
    function runOptimizationAction(action) {
        return $.ajax({
            url: wcefpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wcefp_optimize_performance',
                nonce: wcefpAnalytics.nonce,
                optimization_type: action
            },
            success: function(response) {
                if (response.success && response.data) {
                    showNotification(`${action} completed successfully! ${response.data.performance_gain} improvement`, 'success');
                    loadAnalyticsData(); // Refresh data
                } else {
                    showNotification('Optimization failed', 'error');
                }
            },
            error: function() {
                showNotification('Optimization failed', 'error');
            }
        });
    }

    /**
     * Optimize performance (general)
     */
    function optimizePerformance() {
        const button = $('#wcefp-optimize-performance');
        button.prop('disabled', true).text('Optimizing...');
        
        $.ajax({
            url: wcefpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wcefp_optimize_performance',
                nonce: wcefpAnalytics.nonce,
                optimization_type: 'general'
            },
            success: function(response) {
                if (response.success && response.data) {
                    showNotification(wcefpAnalytics.strings.optimized + ' ' + response.data.performance_gain + ' improvement', 'success');
                    loadAnalyticsData();
                } else {
                    showNotification('Optimization failed', 'error');
                }
            },
            error: function() {
                showNotification('Optimization failed', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Optimize Performance');
            }
        });
    }

    /**
     * Load tab-specific data
     */
    function loadTabData(tabId) {
        switch(tabId) {
            case 'performance-trends':
                updateAllCharts();
                break;
            case 'shortcode-analytics':
                // Already loaded with main data
                break;
            case 'api-monitoring':
                // Already loaded with main data
                break;
            case 'health-alerts':
                // Already loaded with main data
                break;
            case 'optimization-tools':
                updateToolStatuses();
                break;
            case 'predictive-insights':
                // Already loaded with main data
                break;
        }
    }

    /**
     * Update all charts
     */
    function updateAllCharts() {
        $.ajax({
            url: wcefpAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wcefp_get_performance_trends',
                nonce: wcefpAnalytics.nonce,
                timeframe: currentTimeframe
            },
            success: function(response) {
                if (response.success && response.data) {
                    updatePerformanceTrends(response.data);
                }
            }
        });
    }

    /**
     * Update optimization tool statuses
     */
    function updateToolStatuses() {
        $('[data-tool="cache"]').text('Active');
        $('[data-tool="database"]').text('Healthy');
        $('[data-tool="assets"]').text('Optimized');
    }

    /**
     * Setup auto-refresh functionality
     */
    function setupAutoRefresh() {
        // Auto-refresh every 5 minutes
        refreshInterval = setInterval(() => {
            loadAnalyticsData();
        }, 5 * 60 * 1000);
    }

    /**
     * Export analytics data
     */
    function exportAnalyticsData(format) {
        const exportData = {
            action: 'wcefp_export_diagnostics',
            nonce: wcefpAnalytics.nonce,
            format: format,
            source: 'analytics'
        };

        // Create temporary form for file download
        const form = $('<form>', {
            method: 'POST',
            action: wcefpAnalytics.ajax_url,
            style: 'display: none;'
        });

        Object.keys(exportData).forEach(key => {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: exportData[key]
            }));
        });

        $('body').append(form);
        form.submit();
        form.remove();

        showNotification(`Analytics data exported as ${format.toUpperCase()}`, 'success');
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notificationClass = type === 'error' ? 'notice-error' : 
                                 type === 'success' ? 'notice-success' : 'notice-info';
        
        const notification = $(`
            <div class="notice ${notificationClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wcefp-analytics-wrap').prepend(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(() => notification.remove());
        });
    }

    /**
     * Add CSS animations
     */
    function addAnimationStyles() {
        $('<style>').text(`
            .spin {
                animation: wcefp-spin 1s linear infinite !important;
            }
            @keyframes wcefp-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `).appendTo('head');
    }

    /**
     * Cleanup function
     */
    function cleanup() {
        // Clear refresh interval
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        
        // Destroy chart instances
        Object.values(chartInstances).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        chartInstances = {};
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.wcefp-analytics-wrap').length) {
            addAnimationStyles();
            initAdvancedAnalytics();
        }
    });

    // Cleanup when page is unloaded
    $(window).on('beforeunload', cleanup);

})(jQuery);