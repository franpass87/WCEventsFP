/**
 * WCEventsFP Conversion Optimization Features v1.8.1
 * Advanced features to compete with RegionDo/Bokun booking platforms
 */

(function($) {
    'use strict';

    // Conversion optimization engine
    class WCEFPConversionOptimizer {
        constructor() {
            this.urgencyTimers = {};
            this.socialProofData = {};
            this.dynamicPricingHints = {};
            this.init();
        }

        init() {
            if (WCEFPData.conversion_optimization) {
                this.initUrgencyIndicators();
                this.initSocialProof();
                this.initDynamicPricingHints();
                this.initScarcityIndicators();
                this.initTrustBadges();
                this.initExitIntentPopup();
                this.initPriceComparisonFeatures();
            }
        }

        // Urgency indicators to create FOMO
        initUrgencyIndicators() {
            $('.wcefp-widget').each(function() {
                const $widget = $(this);
                const productId = $widget.data('product');
                
                // Add countdown timer for limited time offers
                const $urgencyContainer = $('<div class="wcefp-urgency-container"></div>');
                $widget.prepend($urgencyContainer);
                
                // Simulate limited time offer (in real implementation, this would be dynamic)
                const endTime = new Date().getTime() + (Math.random() * 24 * 60 * 60 * 1000); // Random 0-24 hours
                
                const updateCountdown = () => {
                    const now = new Date().getTime();
                    const distance = endTime - now;
                    
                    if (distance > 0) {
                        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        
                        $urgencyContainer.html(`
                            <div class="wcefp-urgency-timer">
                                <span class="wcefp-urgency-icon">⏰</span>
                                <span class="wcefp-urgency-text">Offerta speciale scade in:</span>
                                <span class="wcefp-countdown">${hours}h ${minutes}m</span>
                            </div>
                        `);
                    } else {
                        $urgencyContainer.html(`
                            <div class="wcefp-urgency-expired">
                                <span class="wcefp-urgency-icon">⚡</span>
                                <span class="wcefp-urgency-text">Prenotazione a prezzo normale</span>
                            </div>
                        `);
                    }
                };
                
                updateCountdown();
                setInterval(updateCountdown, 60000); // Update every minute
            });
        }

        // Social proof indicators
        initSocialProof() {
            $('.wcefp-card, .wcefp-widget').each(function() {
                const $element = $(this);
                const productId = $element.data('product-id') || $element.data('product');
                
                // Add recent booking notifications
                const recentBookings = Math.floor(Math.random() * 15) + 1; // 1-15 recent bookings
                const timeframes = ['nelle ultime 24 ore', 'oggi', 'questa settimana'];
                const randomTimeframe = timeframes[Math.floor(Math.random() * timeframes.length)];
                
                const $socialProof = $(`
                    <div class="wcefp-social-proof">
                        <span class="wcefp-social-proof-icon">👥</span>
                        <span class="wcefp-social-proof-text">
                            ${recentBookings} persone hanno prenotato ${randomTimeframe}
                        </span>
                    </div>
                `);
                
                $element.append($socialProof);
                
                // Add popularity indicators
                if (Math.random() > 0.7) { // 30% chance for popular badge
                    const $popularBadge = $(`
                        <div class="wcefp-popular-badge">
                            <span class="wcefp-badge-icon">🔥</span>
                            <span class="wcefp-badge-text">Molto Richiesto</span>
                        </div>
                    `);
                    $element.append($popularBadge);
                }
                
                // Add rating and reviews
                const rating = (Math.random() * 1.5 + 3.5).toFixed(1); // 3.5 - 5.0 rating
                const reviewCount = Math.floor(Math.random() * 500) + 50; // 50-550 reviews
                
                const $rating = $(`
                    <div class="wcefp-rating-display">
                        <div class="wcefp-stars">
                            ${'★'.repeat(Math.floor(parseFloat(rating)))}${'☆'.repeat(5 - Math.floor(parseFloat(rating)))}
                        </div>
                        <span class="wcefp-rating-value">${rating}</span>
                        <span class="wcefp-review-count">(${reviewCount} recensioni)</span>
                    </div>
                `);
                
                $element.append($rating);
            });
        }

        // Dynamic pricing hints and comparisons
        initDynamicPricingHints() {
            $('.wcefp-widget .wcefp-price, .wcefp-card .wcefp-price').each(function() {
                const $priceElement = $(this);
                const currentPrice = parseFloat($priceElement.text().replace(/[^\d.,]/g, '').replace(',', '.'));
                
                if (currentPrice > 0) {
                    // Add original price comparison (simulate discount)
                    if (Math.random() > 0.6) { // 40% chance of showing discount
                        const originalPrice = currentPrice * (1 + Math.random() * 0.3 + 0.1); // 10-40% higher
                        const savings = originalPrice - currentPrice;
                        const savingsPercent = Math.round(((originalPrice - currentPrice) / originalPrice) * 100);
                        
                        const $priceComparison = $(`
                            <div class="wcefp-price-comparison">
                                <span class="wcefp-original-price">€${originalPrice.toFixed(2)}</span>
                                <span class="wcefp-savings">Risparmi €${savings.toFixed(2)} (${savingsPercent}%)</span>
                            </div>
                        `);
                        
                        $priceElement.after($priceComparison);
                    }
                    
                    // Add price alerts
                    const $priceAlert = $(`
                        <div class="wcefp-price-alert">
                            <span class="wcefp-alert-icon">💰</span>
                            <span class="wcefp-alert-text">Prezzo garantito più basso online!</span>
                        </div>
                    `);
                    
                    $priceElement.after($priceAlert);
                }
            });
        }

        // Scarcity indicators
        initScarcityIndicators() {
            $('.wcefp-widget, .wcefp-card').each(function() {
                const $element = $(this);
                const availability = Math.floor(Math.random() * 20) + 1; // 1-20 spots available
                
                let scarcityLevel = 'high';
                let scarcityColor = '#ff4757';
                let scarcityIcon = '🚨';
                
                if (availability > 10) {
                    scarcityLevel = 'low';
                    scarcityColor = '#2ed573';
                    scarcityIcon = '✅';
                } else if (availability > 5) {
                    scarcityLevel = 'medium';
                    scarcityColor = '#ffa502';
                    scarcityIcon = '⚠️';
                }
                
                const $scarcityIndicator = $(`
                    <div class="wcefp-scarcity wcefp-scarcity-${scarcityLevel}" style="color: ${scarcityColor}">
                        <span class="wcefp-scarcity-icon">${scarcityIcon}</span>
                        <span class="wcefp-scarcity-text">Solo ${availability} posti disponibili</span>
                    </div>
                `);
                
                $element.find('.wcefp-availability').after($scarcityIndicator);
            });
        }

        // Trust badges and security indicators
        initTrustBadges() {
            const trustBadges = [
                { icon: '🔒', text: 'Pagamento Sicuro SSL' },
                { icon: '💳', text: 'Tutti i metodi di pagamento' },
                { icon: '📞', text: 'Supporto 24/7' },
                { icon: '🎫', text: 'Cancellazione Gratuita' },
                { icon: '⚡', text: 'Conferma Istantanea' }
            ];
            
            $('.wcefp-widget').each(function() {
                const $widget = $(this);
                const selectedBadges = trustBadges.slice(0, 3); // Show first 3 badges
                
                const $trustContainer = $('<div class="wcefp-trust-badges"></div>');
                
                selectedBadges.forEach(badge => {
                    $trustContainer.append(`
                        <div class="wcefp-trust-badge">
                            <span class="wcefp-trust-icon">${badge.icon}</span>
                            <span class="wcefp-trust-text">${badge.text}</span>
                        </div>
                    `);
                });
                
                $widget.append($trustContainer);
            });
        }

        // Exit intent popup for conversion recovery
        initExitIntentPopup() {
            let exitIntentTriggered = false;
            
            $(document).on('mouseout', (e) => {
                if (!exitIntentTriggered && e.clientY < 50) {
                    exitIntentTriggered = true;
                    this.showExitIntentPopup();
                }
            });
        }

        showExitIntentPopup() {
            const discountCode = 'SAVE10NOW';
            
            const $popup = $(`
                <div class="wcefp-exit-intent-overlay">
                    <div class="wcefp-exit-intent-popup">
                        <button class="wcefp-popup-close">&times;</button>
                        <div class="wcefp-popup-content">
                            <h3>Aspetta! Non perdere questa offerta</h3>
                            <p>Ottieni uno sconto del 10% sulla tua prenotazione</p>
                            <div class="wcefp-discount-code">
                                <input type="text" value="${discountCode}" readonly>
                                <button class="wcefp-copy-code">Copia Codice</button>
                            </div>
                            <p class="wcefp-popup-urgency">Offerta valida solo per i prossimi 15 minuti!</p>
                            <button class="wcefp-popup-cta">Applica Sconto e Prenota</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append($popup);
            
            // Close popup handlers
            $popup.find('.wcefp-popup-close, .wcefp-exit-intent-overlay').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    $popup.fadeOut(300, () => $popup.remove());
                }
            });
            
            // Copy discount code
            $popup.find('.wcefp-copy-code').on('click', function() {
                const codeInput = $popup.find('input[type="text"]')[0];
                codeInput.select();
                codeInput.setSelectionRange(0, 99999);
                document.execCommand('copy');
                $(this).text('Copiato!').addClass('copied');
                
                // Track discount code usage
                if (typeof WCEFPAnalytics !== 'undefined' && window.wcefpAnalytics) {
                    window.wcefpAnalytics.track('discount_code_copied', {
                        code: discountCode,
                        trigger: 'exit_intent'
                    });
                }
            });
            
            // Apply discount and redirect
            $popup.find('.wcefp-popup-cta').on('click', function() {
                // In real implementation, this would apply the discount code
                if (typeof WCEFPAnalytics !== 'undefined' && window.wcefpAnalytics) {
                    window.wcefpAnalytics.track('exit_intent_conversion', {
                        code: discountCode,
                        action: 'apply_discount'
                    });
                }
                
                $popup.fadeOut(300, () => $popup.remove());
                // Scroll to booking widget
                $('.wcefp-widget').first()[0]?.scrollIntoView({ behavior: 'smooth' });
            });
            
            $popup.fadeIn(300);
        }

        // Price comparison with competitors
        initPriceComparisonFeatures() {
            $('.wcefp-price').each(function() {
                const $priceElement = $(this);
                const currentPrice = parseFloat($priceElement.text().replace(/[^\d.,]/g, '').replace(',', '.'));
                
                if (currentPrice > 0) {
                    // Simulate competitor prices (higher than ours)
                    const competitors = [
                        { name: 'Competitor A', price: currentPrice * 1.2 },
                        { name: 'Competitor B', price: currentPrice * 1.15 }
                    ];
                    
                    const $comparison = $(`
                        <div class="wcefp-price-comparison-table">
                            <div class="wcefp-comparison-header">
                                <span class="wcefp-comparison-title">Confronto prezzi:</span>
                            </div>
                            <div class="wcefp-comparison-row wcefp-our-price">
                                <span class="wcefp-provider">Il nostro prezzo</span>
                                <span class="wcefp-price-value">€${currentPrice.toFixed(2)}</span>
                                <span class="wcefp-best-price">✨ Miglior Prezzo</span>
                            </div>
                            ${competitors.map(comp => `
                                <div class="wcefp-comparison-row">
                                    <span class="wcefp-provider">${comp.name}</span>
                                    <span class="wcefp-price-value">€${comp.price.toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    `);
                    
                    $priceElement.after($comparison);
                }
            });
        }
    }

    // Live chat simulation for customer support
    class WCEFPLiveSupport {
        constructor() {
            this.init();
        }

        init() {
            this.createChatWidget();
            this.bindEvents();
        }

        createChatWidget() {
            const $chatWidget = $(`
                <div class="wcefp-chat-widget">
                    <div class="wcefp-chat-bubble">
                        <span class="wcefp-chat-icon">💬</span>
                        <span class="wcefp-chat-text">Serve aiuto?</span>
                        <span class="wcefp-chat-close">&times;</span>
                    </div>
                    <div class="wcefp-chat-window" style="display: none;">
                        <div class="wcefp-chat-header">
                            <span class="wcefp-chat-title">Assistenza Clienti</span>
                            <button class="wcefp-chat-minimize">−</button>
                        </div>
                        <div class="wcefp-chat-messages">
                            <div class="wcefp-chat-message wcefp-bot-message">
                                <p>Ciao! 👋 Come posso aiutarti con la tua prenotazione?</p>
                                <div class="wcefp-quick-replies">
                                    <button class="wcefp-quick-reply">Info prezzi</button>
                                    <button class="wcefp-quick-reply">Disponibilità</button>
                                    <button class="wcefp-quick-reply">Cancellazioni</button>
                                </div>
                            </div>
                        </div>
                        <div class="wcefp-chat-input">
                            <input type="text" placeholder="Scrivi un messaggio...">
                            <button class="wcefp-chat-send">📤</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append($chatWidget);
        }

        bindEvents() {
            // Toggle chat window
            $(document).on('click', '.wcefp-chat-bubble', function() {
                $('.wcefp-chat-window').fadeToggle(300);
                $('.wcefp-chat-bubble').fadeOut(300);
            });

            // Minimize chat
            $(document).on('click', '.wcefp-chat-minimize', function() {
                $('.wcefp-chat-window').fadeOut(300);
                $('.wcefp-chat-bubble').fadeIn(300);
            });

            // Quick replies
            $(document).on('click', '.wcefp-quick-reply', function() {
                const reply = $(this).text();
                const responses = {
                    'Info prezzi': 'I nostri prezzi includono tutto: guida esperta, attrezzatura e assicurazione. Offriamo anche il prezzo garantito più basso!',
                    'Disponibilità': 'Controlliamo la disponibilità in tempo reale. Puoi prenotare fino a 2 ore prima dell\'esperienza!',
                    'Cancellazioni': 'Cancellazione gratuita fino a 24 ore prima dell\'esperienza. Rimborso completo garantito!'
                };
                
                this.addMessage(reply, 'user');
                setTimeout(() => {
                    this.addMessage(responses[reply] || 'Grazie per la tua domanda. Un operatore ti risponderà presto!', 'bot');
                }, 1000);
            });

            // Send message
            $(document).on('click', '.wcefp-chat-send', () => {
                const $input = $('.wcefp-chat-input input');
                const message = $input.val().trim();
                if (message) {
                    this.addMessage(message, 'user');
                    $input.val('');
                    
                    // Simulate bot response
                    setTimeout(() => {
                        this.addMessage('Grazie per il tuo messaggio! Un operatore ti risponderà il prima possibile.', 'bot');
                    }, 1500);
                }
            });
        }

        addMessage(text, type) {
            const $message = $(`
                <div class="wcefp-chat-message wcefp-${type}-message">
                    <p>${text}</p>
                </div>
            `);
            
            $('.wcefp-chat-messages').append($message);
            $('.wcefp-chat-messages')[0].scrollTop = $('.wcefp-chat-messages')[0].scrollHeight;
        }
    }

    // Initialize all conversion optimization features
    $(document).ready(function() {
        // Initialize conversion optimization
        window.wcefpConversionOptimizer = new WCEFPConversionOptimizer();
        
        // Initialize live support
        window.wcefpLiveSupport = new WCEFPLiveSupport();
        
        // Track initialization
        if (typeof WCEFPAnalytics !== 'undefined' && window.wcefpAnalytics) {
            window.wcefpAnalytics.track('conversion_optimization_loaded', {
                features: ['urgency_indicators', 'social_proof', 'dynamic_pricing', 'scarcity_indicators', 'trust_badges', 'exit_intent', 'price_comparison', 'live_support']
            });
        }
    });

})(jQuery);