/**
 * WCEventsFP Advanced Features v1.8.1
 * Additional enhancements for competitive booking experience
 */

(function($) {
    'use strict';

    // Advanced notification system
    class WCEFPNotifications {
        constructor() {
            this.container = null;
            this.init();
        }

        init() {
            // Create notification container
            if (!$('#wcefp-notifications').length) {
                $('body').append('<div id="wcefp-notifications" class="wcefp-notifications-container"></div>');
                this.container = $('#wcefp-notifications');
            }
        }

        show(message, type = 'info', duration = 5000) {
            const id = 'wcefp-notification-' + Date.now();
            const icons = {
                'success': '✅',
                'error': '❌', 
                'warning': '⚠️',
                'info': 'ℹ️'
            };

            const notification = $(`
                <div class="wcefp-notification wcefp-notification-${type}" id="${id}">
                    <div class="wcefp-notification-icon">${icons[type] || icons.info}</div>
                    <div class="wcefp-notification-content">${message}</div>
                    <button class="wcefp-notification-close" aria-label="Chiudi notifica">&times;</button>
                </div>
            `);

            this.container.append(notification);
            
            // Animate in
            setTimeout(() => notification.addClass('wcefp-show'), 100);

            // Auto dismiss
            setTimeout(() => this.dismiss(id), duration);

            // Click to dismiss
            notification.find('.wcefp-notification-close').on('click', () => this.dismiss(id));

            return id;
        }

        dismiss(id) {
            const notification = $(`#${id}`);
            notification.removeClass('wcefp-show');
            setTimeout(() => notification.remove(), 300);
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
            this.checkInterval = setInterval(() => {
                this.checkAvailability();
            }, 120000);
        }

        bindVisibilityChange() {
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    // Page became visible, check immediately
                    this.checkAvailability();
                }
            });
        }

        checkAvailability() {
            $('.wcefp-card').each(function() {
                const $card = $(this);
                const productId = $card.data('product-id');
                
                if (!productId) return;

                // Simulate availability check (in real implementation, this would be an AJAX call)
                const availability = Math.floor(Math.random() * 20) + 1;
                const $indicator = $card.find('.wcefp-availability');
                
                if ($indicator.length) {
                    let className = '';
                    let text = '';
                    
                    if (availability <= 3) {
                        className = 'critical';
                        text = `⚠️ Solo ${availability} posti rimasti`;
                    } else if (availability <= 8) {
                        className = 'limited';
                        text = `🔥 ${availability} posti disponibili`;
                    } else {
                        className = 'available';
                        text = `✅ ${availability}+ posti disponibili`;
                    }
                    
                    $indicator.removeClass('wcefp-availability-critical wcefp-availability-limited wcefp-availability-available')
                              .addClass(`wcefp-availability-${className}`)
                              .html(text);
                }
            });
        }

        stop() {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
            }
        }
    }

    // Enhanced booking analytics
    class WCEFPAnalytics {
        constructor() {
            this.events = [];
            this.init();
        }

        init() {
            this.bindEvents();
            this.startSession();
        }

        startSession() {
            this.track('session_start', {
                timestamp: new Date().toISOString(),
                page_url: window.location.href,
                user_agent: navigator.userAgent,
                screen_resolution: `${screen.width}x${screen.height}`,
                viewport_size: `${window.innerWidth}x${window.innerHeight}`
            });
        }

        bindEvents() {
            // Track widget interactions
            $(document).on('click', '.wcefp-widget .wcefp-add', (e) => {
                this.track('booking_attempt', {
                    product_id: $(e.target).closest('.wcefp-widget').data('product'),
                    step: 'add_to_cart'
                });
            });

            // Track filter usage
            $(document).on('input change', '.wcefp-search-input, .wcefp-filter-select', (e) => {
                this.track('filter_used', {
                    filter_type: e.target.className,
                    filter_value: e.target.value
                });
            });

            // Track card interactions
            $(document).on('click', '.wcefp-card', (e) => {
                this.track('card_clicked', {
                    product_id: $(e.currentTarget).data('product-id'),
                    card_position: $(e.currentTarget).index()
                });
            });

            // Track social sharing
            $(document).on('click', '.wcefp-share-btn', (e) => {
                this.track('social_share', {
                    platform: e.target.className.split(' ').find(cls => cls.includes('share-')),
                    product_id: $(e.target).closest('.wcefp-card').data('product-id')
                });
            });
        }

        track(event_name, data = {}) {
            const event = {
                event: event_name,
                timestamp: new Date().toISOString(),
                ...data
            };
            
            this.events.push(event);

            // Send to Google Analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', event_name, data);
            }

            // Send to dataLayer if available
            if (typeof dataLayer !== 'undefined') {
                dataLayer.push(event);
            }

            // Store in localStorage for debugging
            if (window.localStorage) {
                const stored = JSON.parse(localStorage.getItem('wcefp_analytics') || '[]');
                stored.push(event);
                
                // Keep only last 100 events
                if (stored.length > 100) {
                    stored.splice(0, stored.length - 100);
                }
                
                localStorage.setItem('wcefp_analytics', JSON.stringify(stored));
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

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Show custom install prompt
                this.showInstallPrompt(deferredPrompt);
            });
        }

        showInstallPrompt(deferredPrompt) {
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
            
            $('body').append(promptHTML);
            
            $('.wcefp-install-btn').on('click', () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        deferredPrompt = null;
                        $('.wcefp-install-prompt').remove();
                    });
                }
            });
            
            $('.wcefp-install-dismiss').on('click', () => {
                $('.wcefp-install-prompt').remove();
            });
        }

        handleOfflineState() {
            window.addEventListener('online', () => {
                const notifications = new WCEFPNotifications();
                notifications.show('✅ Connessione ristabilita!', 'success');
            });

            window.addEventListener('offline', () => {
                const notifications = new WCEFPNotifications();
                notifications.show('⚠️ Connessione persa. Alcune funzioni potrebbero non funzionare.', 'warning');
            });
        }

        optimizePerformance() {
            // Lazy load images when they come into viewport
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                img.classList.add('wcefp-loaded');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });

                $('.wcefp-card img[data-src]').each(function() {
                    imageObserver.observe(this);
                });
            }

            // Prefetch important resources
            this.prefetchResources();
        }

        prefetchResources() {
            // Prefetch commonly used resources
            const resources = [
                '/wp-content/plugins/wceventsfp/assets/css/templates.css',
                '/wp-content/plugins/wceventsfp/assets/js/templates.js'
            ];

            resources.forEach(resource => {
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = resource;
                document.head.appendChild(link);
            });
        }
    }

    // Initialize all advanced features when document is ready
    $(document).ready(function() {
        // Initialize notification system
        window.WCEFPNotifications = new WCEFPNotifications();
        
        // Initialize availability checker
        window.WCEFPAvailabilityChecker = new WCEFPAvailabilityChecker();
        
        // Initialize analytics
        window.WCEFPAnalytics = new WCEFPAnalytics();
        
        // Initialize PWA features
        window.WCEFPPWAFeatures = new WCEFPPWAFeatures();
        
        // Add advanced styles
        if (!$('#wcefp-advanced-styles').length) {
            $('<style id="wcefp-advanced-styles">').appendTo('head').text(`
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
            `);
        }
    });

})(jQuery);