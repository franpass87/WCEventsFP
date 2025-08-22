/**
 * WCEventsFP Real-time Features JavaScript
 * Handles real-time updates, notifications, and live data synchronization
 */

(function($) {
    'use strict';

    class WCEFPRealtimeClient {
        constructor() {
            this.sessionId = null;
            this.pollInterval = null;
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = WCEFPRealtime.maxReconnectAttempts || 5;
            this.reconnectDelay = WCEFPRealtime.reconnectDelay || 1000;
            this.pollIntervalMs = WCEFPRealtime.pollInterval || 5000;
            this.isConnecting = false;
            this.isConnected = false;
            
            // Event handlers
            this.eventHandlers = {};
            
            // Initialize connection
            this.connect();
            
            // Handle page visibility changes
            this.handleVisibilityChange();
            
            // Handle before unload
            $(window).on('beforeunload', () => this.disconnect());
        }

        /**
         * Establish real-time connection
         */
        async connect() {
            if (this.isConnecting || this.isConnected) {
                return;
            }

            this.isConnecting = true;
            
            try {
                const response = await $.post(WCEFPRealtime.ajaxUrl, {
                    action: 'wcefp_realtime_connect',
                    nonce: WCEFPRealtime.nonce
                });

                if (response.success) {
                    this.sessionId = response.data.session_id;
                    this.isConnected = true;
                    this.isConnecting = false;
                    this.reconnectAttempts = 0;
                    
                    this.emit('connected', { sessionId: this.sessionId });
                    this.startPolling();
                    
                    console.log('WCEventsFP: Real-time connection established');
                } else {
                    throw new Error(response.data?.msg || 'Connection failed');
                }
                
            } catch (error) {
                this.isConnecting = false;
                this.handleConnectionError(error);
            }
        }

        /**
         * Start polling for updates
         */
        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }

            this.pollInterval = setInterval(() => {
                this.pollUpdates();
            }, this.pollIntervalMs);
        }

        /**
         * Poll for real-time updates
         */
        async pollUpdates() {
            if (!this.isConnected || !this.sessionId) {
                return;
            }

            try {
                const response = await $.post(WCEFPRealtime.ajaxUrl, {
                    action: 'wcefp_get_realtime_updates',
                    nonce: WCEFPRealtime.nonce,
                    session_id: this.sessionId
                });

                if (response.success) {
                    const updates = response.data.updates || [];
                    this.processUpdates(updates);
                } else {
                    if (response.data?.msg?.includes('Session expired')) {
                        this.handleSessionExpired();
                    }
                }
                
            } catch (error) {
                console.warn('WCEventsFP: Error polling updates:', error);
                this.handleConnectionError(error);
            }
        }

        /**
         * Process received updates
         */
        processUpdates(updates) {
            updates.forEach(update => {
                switch (update.type) {
                    case 'booking_update':
                        this.handleBookingUpdate(update);
                        break;
                    case 'availability_update':
                        this.handleAvailabilityUpdate(update);
                        break;
                    case 'notification':
                        this.handleNotification(update);
                        break;
                    default:
                        this.emit('update', update);
                }
            });
        }

        /**
         * Handle booking updates
         */
        handleBookingUpdate(update) {
            this.emit('booking_update', update);
            
            // Show notification if notifications are enabled
            if (window.WCEFPNotifications) {
                window.WCEFPNotifications.show(
                    update.message,
                    'info',
                    5000
                );
            }
            
            // Update any booking counters on page
            this.updateBookingCounters(update);
        }

        /**
         * Handle availability updates
         */
        handleAvailabilityUpdate(update) {
            this.emit('availability_update', update);
            
            // Update availability indicators
            this.updateAvailabilityIndicators(update);
            
            // Update booking buttons if needed
            this.updateBookingButtons(update);
        }

        /**
         * Handle real-time notifications
         */
        handleNotification(notification) {
            this.emit('notification', notification);
            
            if (window.WCEFPNotifications) {
                window.WCEFPNotifications.show(
                    notification.message,
                    notification.notification_type || 'info',
                    8000
                );
            }
        }

        /**
         * Update availability indicators on the page
         */
        updateAvailabilityIndicators(update) {
            const selectors = [
                `[data-occurrence-id="${update.occurrence_id}"]`,
                `[data-product-id="${update.product_id}"]`
            ];

            selectors.forEach(selector => {
                const elements = $(selector);
                elements.each((index, element) => {
                    const $el = $(element);
                    
                    // Update availability text
                    const availabilityEl = $el.find('.wcefp-availability, .availability');
                    if (availabilityEl.length) {
                        let className = 'available';
                        let text = `✅ ${update.available}+ posti disponibili`;
                        
                        if (update.available <= 0) {
                            className = 'sold-out';
                            text = '❌ Sold Out';
                        } else if (update.available <= 3) {
                            className = 'critical';
                            text = `⚠️ Solo ${update.available} posti rimasti!`;
                        } else if (update.available <= 10) {
                            className = 'limited';
                            text = `⚠️ ${update.available} posti disponibili`;
                        }
                        
                        availabilityEl
                            .removeClass('available critical limited sold-out')
                            .addClass(className)
                            .html(text);
                    }
                    
                    // Update progress bars
                    const progressEl = $el.find('.wcefp-capacity-progress');
                    if (progressEl.length && update.capacity > 0) {
                        const percentage = (update.booked / update.capacity) * 100;
                        progressEl.find('.progress-fill').css('width', percentage + '%');
                    }
                });
            });
        }

        /**
         * Update booking buttons based on availability
         */
        updateBookingButtons(update) {
            const buttons = $(`[data-occurrence-id="${update.occurrence_id}"] .wcefp-book-btn, [data-product-id="${update.product_id}"] .wcefp-book-btn`);
            
            buttons.each((index, button) => {
                const $btn = $(button);
                
                if (update.available <= 0 || update.status !== 'active') {
                    $btn.addClass('disabled').prop('disabled', true).text('Non Disponibile');
                } else {
                    $btn.removeClass('disabled').prop('disabled', false).text('Prenota Ora');
                }
            });
        }

        /**
         * Update booking counters
         */
        updateBookingCounters(update) {
            // Update any global booking counters
            $('.wcefp-total-bookings').each((index, element) => {
                const $counter = $(element);
                const current = parseInt($counter.text()) || 0;
                $counter.text(current + 1);
            });
        }

        /**
         * Handle connection errors
         */
        handleConnectionError(error) {
            this.isConnected = false;
            this.emit('connection_error', error);
            
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }

            // Try to reconnect with exponential backoff
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts);
                this.reconnectAttempts++;
                
                setTimeout(() => {
                    console.log(`WCEventsFP: Reconnecting... (attempt ${this.reconnectAttempts})`);
                    this.connect();
                }, delay);
            } else {
                console.error('WCEventsFP: Max reconnection attempts reached');
                this.emit('max_reconnects_reached');
            }
        }

        /**
         * Handle session expiry
         */
        handleSessionExpired() {
            this.disconnect();
            this.connect(); // Establish new session
        }

        /**
         * Handle page visibility changes (pause when hidden)
         */
        handleVisibilityChange() {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    // Page is hidden, reduce polling or pause
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                    }
                } else {
                    // Page is visible, resume polling
                    if (this.isConnected) {
                        this.startPolling();
                    }
                }
            });
        }

        /**
         * Disconnect from real-time service
         */
        disconnect() {
            this.isConnected = false;
            
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            
            this.emit('disconnected');
        }

        /**
         * Event system
         */
        on(event, handler) {
            if (!this.eventHandlers[event]) {
                this.eventHandlers[event] = [];
            }
            this.eventHandlers[event].push(handler);
        }

        off(event, handler) {
            if (this.eventHandlers[event]) {
                const index = this.eventHandlers[event].indexOf(handler);
                if (index > -1) {
                    this.eventHandlers[event].splice(index, 1);
                }
            }
        }

        emit(event, data) {
            if (this.eventHandlers[event]) {
                this.eventHandlers[event].forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        console.error(`Error in event handler for ${event}:`, error);
                    }
                });
            }
        }

        /**
         * Get connection status
         */
        isRealtimeConnected() {
            return this.isConnected;
        }

        /**
         * Get session ID
         */
        getSessionId() {
            return this.sessionId;
        }
    }

    // Initialize real-time client when document is ready
    $(document).ready(function() {
        // Only initialize if WCEFPRealtime is available
        if (typeof WCEFPRealtime !== 'undefined') {
            window.WCEFPRealtimeClient = new WCEFPRealtimeClient();
            
            // Expose some methods globally for debugging
            window.WCEFPRealtime = window.WCEFPRealtime || {};
            window.WCEFPRealtime.client = window.WCEFPRealtimeClient;
            
            // Add status indicator to admin bar (if present)
            if ($('#wpadminbar').length) {
                const statusIndicator = $('<div id="wcefp-realtime-status" style="position: fixed; top: 32px; right: 20px; z-index: 99999; padding: 5px 10px; background: #f0f0f0; border-radius: 3px; font-size: 11px; display: none;">Real-time: <span class="status">Connecting...</span></div>');
                $('body').append(statusIndicator);
                
                window.WCEFPRealtimeClient.on('connected', () => {
                    statusIndicator.show().find('.status').text('Connected').css('color', 'green');
                });
                
                window.WCEFPRealtimeClient.on('disconnected', () => {
                    statusIndicator.find('.status').text('Disconnected').css('color', 'red');
                });
                
                window.WCEFPRealtimeClient.on('connection_error', () => {
                    statusIndicator.find('.status').text('Error').css('color', 'red');
                });
            }
        }
    });

})(jQuery);