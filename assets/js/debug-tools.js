/**
 * WCEventsFP Debug Tools - Client Side
 * Advanced debugging and development utilities for frontend
 */

(function($) {
    'use strict';

    // Debug tools manager
    class WCEFPDebugTools {
        constructor() {
            this.isVisible = false;
            this.currentTab = 'log';
            this.refreshInterval = null;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initConsoleInterceptor();
            this.initPerformanceObserver();
        }

        /**
         * Bind events
         */
        bindEvents() {
            // Tab switching
            $(document).on('click', '.wcefp-debug-tab', (e) => {
                const tab = $(e.target).data('tab');
                this.switchTab(tab);
            });

            // Panel interactions
            $(document).on('keydown', (e) => {
                // Alt + D to toggle panel
                if (e.altKey && e.key === 'd') {
                    e.preventDefault();
                    this.togglePanel();
                }
                
                // ESC to close panel
                if (e.key === 'Escape' && this.isVisible) {
                    this.closePanel();
                }
            });

            // Auto-refresh in performance tab
            $(document).on('wcefp_debug_tab_changed', (e, tab) => {
                if (tab === 'performance') {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }

        /**
         * Toggle debug panel
         */
        togglePanel() {
            if (this.isVisible) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        }

        /**
         * Open debug panel
         */
        openPanel() {
            $('#wcefp-debug-panel').fadeIn(300);
            this.isVisible = true;
            
            // Load initial data for current tab
            this.refreshTabContent(this.currentTab);
            
            // Track panel open
            this.trackEvent('debug_panel_opened');
        }

        /**
         * Close debug panel
         */
        closePanel() {
            $('#wcefp-debug-panel').fadeOut(300);
            this.isVisible = false;
            this.stopAutoRefresh();
            
            // Track panel close
            this.trackEvent('debug_panel_closed');
        }

        /**
         * Switch between tabs
         */
        switchTab(tab) {
            if (tab === this.currentTab) return;
            
            // Update tab buttons
            $('.wcefp-debug-tab').removeClass('active');
            $(`.wcefp-debug-tab[data-tab="${tab}"]`).addClass('active');
            
            // Update tab content
            $('.wcefp-debug-tab-content').removeClass('active');
            $(`#wcefp-debug-${tab}`).addClass('active');
            
            this.currentTab = tab;
            
            // Load content for new tab
            this.refreshTabContent(tab);
            
            // Trigger event
            $(document).trigger('wcefp_debug_tab_changed', [tab]);
            
            // Track tab switch
            this.trackEvent('debug_tab_switched', { tab: tab });
        }

        /**
         * Refresh content for specific tab
         */
        refreshTabContent(tab) {
            switch (tab) {
                case 'performance':
                    this.loadPerformanceData();
                    break;
                case 'system':
                    this.loadSystemInfo();
                    break;
                case 'queries':
                    this.loadQueryInfo();
                    break;
                case 'log':
                    // Log is already loaded in PHP, but we can refresh it
                    this.refreshLogData();
                    break;
            }
        }

        /**
         * Load performance data
         */
        loadPerformanceData() {
            const $container = $('.wcefp-performance-metrics');
            $container.html('<div class="wcefp-loading">Loading performance data...</div>');

            $.post(wcefp_debug.ajax_url, {
                action: 'wcefp_debug_performance',
                nonce: wcefp_debug.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.renderPerformanceData(response.data);
                } else {
                    $container.html('<div class="wcefp-error">Failed to load performance data</div>');
                }
            })
            .fail(() => {
                $container.html('<div class="wcefp-error">Network error loading performance data</div>');
            });
        }

        /**
         * Render performance data
         */
        renderPerformanceData(data) {
            let html = '<div class="wcefp-performance-summary">';
            
            // Current metrics
            html += '<h4>Current Performance</h4>';
            html += `<div class="wcefp-metric">Memory Usage: ${this.formatBytes(data.memory_usage)}</div>`;
            html += `<div class="wcefp-metric">Peak Memory: ${this.formatBytes(data.memory_peak)}</div>`;
            html += `<div class="wcefp-metric">Database Queries: ${data.query_count}</div>`;
            
            // Performance markers
            if (data.performance_markers && Object.keys(data.performance_markers).length > 0) {
                html += '<h4>Performance Markers</h4>';
                html += '<div class="wcefp-markers">';
                
                Object.entries(data.performance_markers).forEach(([name, marker]) => {
                    if (name === 'calculated_metrics') {
                        html += '<h5>Calculated Metrics</h5>';
                        html += `<div class="wcefp-metric">Total Time: ${marker.total_time?.toFixed(2)}ms</div>`;
                        html += `<div class="wcefp-metric">Memory Used: ${this.formatBytes(marker.memory_used)}</div>`;
                        html += `<div class="wcefp-metric">Queries: ${marker.queries_executed}</div>`;
                    } else {
                        const time = new Date(marker.timestamp * 1000).toLocaleTimeString();
                        html += `<div class="wcefp-marker">`;
                        html += `<strong>${name}</strong> - ${time}`;
                        html += `<br><small>Memory: ${this.formatBytes(marker.memory)}</small>`;
                        html += `</div>`;
                    }
                });
                
                html += '</div>';
            }
            
            // Browser performance (if available)
            if (window.performance && window.performance.timing) {
                html += this.renderBrowserPerformance();
            }
            
            html += '</div>';
            
            $('.wcefp-performance-metrics').html(html);
        }

        /**
         * Render browser performance metrics
         */
        renderBrowserPerformance() {
            const timing = window.performance.timing;
            const navigation = timing.navigationStart;
            
            const metrics = {
                'DNS Lookup': timing.domainLookupEnd - timing.domainLookupStart,
                'TCP Connection': timing.connectEnd - timing.connectStart,
                'Server Response': timing.responseStart - timing.requestStart,
                'DOM Processing': timing.domComplete - timing.domLoading,
                'Page Load': timing.loadEventEnd - navigation
            };
            
            let html = '<h4>Browser Performance</h4>';
            Object.entries(metrics).forEach(([name, value]) => {
                html += `<div class="wcefp-metric">${name}: ${value}ms</div>`;
            });
            
            return html;
        }

        /**
         * Load system information
         */
        loadSystemInfo() {
            const $container = $('.wcefp-system-info');
            $container.html('<div class="wcefp-loading">Loading system information...</div>');

            $.post(wcefp_debug.ajax_url, {
                action: 'wcefp_debug_info',
                nonce: wcefp_debug.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.renderSystemInfo(response.data.system_info);
                } else {
                    $container.html('<div class="wcefp-error">Failed to load system information</div>');
                }
            })
            .fail(() => {
                $container.html('<div class="wcefp-error">Network error loading system information</div>');
            });
        }

        /**
         * Render system information
         */
        renderSystemInfo(data) {
            let html = '<div class="wcefp-system-sections">';
            
            Object.entries(data).forEach(([section, info]) => {
                html += `<div class="wcefp-system-section">`;
                html += `<h4>${this.capitalizeFirst(section)}</h4>`;
                
                Object.entries(info).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        html += `<div class="wcefp-system-item">`;
                        html += `<strong>${this.formatKey(key)}:</strong>`;
                        html += `<ul>`;
                        value.forEach(item => {
                            if (typeof item === 'object') {
                                html += `<li>${JSON.stringify(item)}</li>`;
                            } else {
                                html += `<li>${item}</li>`;
                            }
                        });
                        html += `</ul></div>`;
                    } else if (typeof value === 'object') {
                        html += `<div class="wcefp-system-item">`;
                        html += `<strong>${this.formatKey(key)}:</strong>`;
                        html += `<pre>${JSON.stringify(value, null, 2)}</pre>`;
                        html += `</div>`;
                    } else {
                        html += `<div class="wcefp-system-item">`;
                        html += `<strong>${this.formatKey(key)}:</strong> ${value}`;
                        html += `</div>`;
                    }
                });
                
                html += `</div>`;
            });
            
            html += '</div>';
            
            $('.wcefp-system-info').html(html);
        }

        /**
         * Load query information
         */
        loadQueryInfo() {
            const $container = $('.wcefp-query-log');
            $container.html('<div class="wcefp-loading">Loading query information...</div>');

            $.post(wcefp_debug.ajax_url, {
                action: 'wcefp_debug_queries',
                nonce: wcefp_debug.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.renderQueryInfo(response.data);
                } else {
                    $container.html('<div class="wcefp-error">Failed to load query information</div>');
                }
            })
            .fail(() => {
                $container.html('<div class="wcefp-error">Network error loading query information</div>');
            });
        }

        /**
         * Render query information
         */
        renderQueryInfo(data) {
            let html = `<div class="wcefp-query-summary">Total Queries: ${data.total_queries}</div>`;
            
            if (data.query_log && data.query_log.length > 0) {
                html += '<div class="wcefp-query-entries">';
                
                data.query_log.forEach((entry, index) => {
                    const time = new Date(entry.timestamp * 1000).toLocaleTimeString();
                    html += `<div class="wcefp-query-entry">`;
                    html += `<div class="wcefp-query-header">`;
                    html += `<span class="wcefp-query-number">#${index + 1}</span>`;
                    html += `<span class="wcefp-query-time">${time}</span>`;
                    html += `</div>`;
                    html += `<div class="wcefp-query-sql"><code>${this.escapeHtml(entry.query)}</code></div>`;
                    html += `</div>`;
                });
                
                html += '</div>';
            } else {
                html += '<div class="wcefp-no-queries">No queries logged yet.</div>';
            }
            
            $('.wcefp-query-log').html(html);
        }

        /**
         * Refresh log data
         */
        refreshLogData() {
            // The log is already rendered in PHP, but we can add client-side filtering
            this.initLogFiltering();
        }

        /**
         * Initialize log filtering
         */
        initLogFiltering() {
            if ($('.wcefp-log-filter').length) return;
            
            const filterHtml = `
                <div class="wcefp-log-filter">
                    <select id="wcefp-log-level-filter">
                        <option value="">All Levels</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="performance">Performance</option>
                    </select>
                    <input type="text" id="wcefp-log-search" placeholder="Search messages...">
                </div>
            `;
            
            $('#wcefp-debug-log').prepend(filterHtml);
            
            // Bind filter events
            $('#wcefp-log-level-filter, #wcefp-log-search').on('input', () => {
                this.filterLogEntries();
            });
        }

        /**
         * Filter log entries
         */
        filterLogEntries() {
            const levelFilter = $('#wcefp-log-level-filter').val();
            const searchTerm = $('#wcefp-log-search').val().toLowerCase();
            
            $('.wcefp-debug-entry').each(function() {
                const $entry = $(this);
                const level = $entry.find('.wcefp-debug-level').text().replace(/[\[\]]/g, '').toLowerCase();
                const message = $entry.find('.wcefp-debug-message').text().toLowerCase();
                
                let visible = true;
                
                // Level filter
                if (levelFilter && level !== levelFilter) {
                    visible = false;
                }
                
                // Search filter
                if (searchTerm && !message.includes(searchTerm)) {
                    visible = false;
                }
                
                $entry.toggle(visible);
            });
        }

        /**
         * Clear debug log
         */
        clearLog() {
            if (!confirm('Are you sure you want to clear the debug log?')) {
                return;
            }
            
            $.post(wcefp_debug.ajax_url, {
                action: 'wcefp_debug_clear',
                nonce: wcefp_debug.nonce
            })
            .done((response) => {
                if (response.success) {
                    $('.wcefp-debug-log-entries').html('<div class="wcefp-no-entries">Debug log cleared</div>');
                    this.showNotification('Debug log cleared successfully', 'success');
                } else {
                    this.showNotification('Failed to clear debug log', 'error');
                }
            })
            .fail(() => {
                this.showNotification('Network error clearing debug log', 'error');
            });
        }

        /**
         * Show system info in modal
         */
        showSystemInfo() {
            this.openPanel();
            this.switchTab('system');
        }

        /**
         * Show performance in modal
         */
        showPerformance() {
            this.openPanel();
            this.switchTab('performance');
        }

        /**
         * Show queries in modal
         */
        showQueries() {
            this.openPanel();
            this.switchTab('queries');
        }

        /**
         * Start auto-refresh
         */
        startAutoRefresh() {
            if (this.refreshInterval) return;
            
            this.refreshInterval = setInterval(() => {
                if (this.isVisible && this.currentTab === 'performance') {
                    this.loadPerformanceData();
                }
            }, 5000); // Refresh every 5 seconds
        }

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        }

        /**
         * Initialize console interceptor
         */
        initConsoleInterceptor() {
            if (!wcefp_debug.is_debug_enabled) return;
            
            const originalConsole = {
                log: console.log,
                warn: console.warn,
                error: console.error
            };
            
            // Intercept console messages and add to debug log
            ['log', 'warn', 'error'].forEach(method => {
                console[method] = (...args) => {
                    originalConsole[method].apply(console, args);
                    
                    // Add to our debug system
                    this.addClientLog(args.join(' '), method === 'log' ? 'info' : method);
                };
            });
        }

        /**
         * Initialize performance observer
         */
        initPerformanceObserver() {
            if (!window.PerformanceObserver) return;
            
            try {
                // Observe resource loading
                const resourceObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        if (entry.duration > 1000) { // Log slow resources
                            this.addClientLog(
                                `Slow resource: ${entry.name} (${entry.duration.toFixed(2)}ms)`,
                                'performance'
                            );
                        }
                    });
                });
                
                resourceObserver.observe({ entryTypes: ['resource'] });
                
                // Observe navigation timing
                const navigationObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        this.addClientLog(
                            `Page navigation completed in ${entry.duration.toFixed(2)}ms`,
                            'performance'
                        );
                    });
                });
                
                navigationObserver.observe({ entryTypes: ['navigation'] });
                
            } catch (error) {
                console.warn('Performance observer not fully supported', error);
            }
        }

        /**
         * Add client-side log entry
         */
        addClientLog(message, level = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logHtml = `
                <div class="wcefp-debug-entry wcefp-debug-${level} wcefp-client-log">
                    <span class="wcefp-debug-time">${timestamp}</span>
                    <span class="wcefp-debug-level">[${level}]</span>
                    <span class="wcefp-debug-message">${this.escapeHtml(message)}</span>
                    <span class="wcefp-debug-location">client-side</span>
                </div>
            `;
            
            $('.wcefp-debug-log-entries').prepend(logHtml);
            
            // Limit entries to prevent memory issues
            const entries = $('.wcefp-debug-entry');
            if (entries.length > 100) {
                entries.slice(100).remove();
            }
        }

        /**
         * Utility functions
         */
        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        formatKey(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            if (window.wcefpNotifications) {
                window.wcefpNotifications.show(message, type);
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }

        /**
         * Track debug events
         */
        trackEvent(eventName, data = {}) {
            if (window.wcefpAnalytics) {
                window.wcefpAnalytics.track(`debug_${eventName}`, data);
            }
        }
    }

    // Initialize debug tools when DOM is ready
    $(document).ready(function() {
        if (typeof wcefp_debug !== 'undefined') {
            window.wcefpDebugTools = new WCEFPDebugTools();
            
            // Add global keyboard shortcut hint
            if (wcefp_debug.is_debug_enabled) {
                console.log('%cWCEFP Debug Tools: Press Alt+D to toggle debug panel', 
                          'background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px;');
            }
        }
    });

})(jQuery);