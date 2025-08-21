/**
 * WCEventsFP AI-Powered Recommendations System
 * Provides intelligent suggestions based on user behavior and preferences
 */

class WCEFPRecommendations {
    constructor() {
        this.apiEndpoint = window.wcefp_ajax?.url || '/wp-admin/admin-ajax.php';
        this.nonce = window.wcefp_ajax?.nonce || '';
        this.userPreferences = this.loadUserPreferences();
        this.sessionData = this.initSessionData();
        this.init();
    }

    init() {
        this.trackUserBehavior();
        this.loadRecommendations();
        this.bindEvents();
        this.setupRealTimeUpdates();
    }

    // Track user interactions and preferences
    trackUserBehavior() {
        // Track page views
        this.trackEvent('page_view', {
            page: window.location.pathname,
            timestamp: Date.now()
        });

        // Track scroll depth
        this.trackScrollDepth();

        // Track clicks on events/experiences
        document.addEventListener('click', (e) => {
            if (e.target.closest('.wcefp-event-card')) {
                const card = e.target.closest('.wcefp-event-card');
                const eventId = card.dataset.eventId || card.dataset.productId;
                const eventType = card.dataset.eventType || 'event';
                const price = card.dataset.price || null;
                
                this.trackEvent('event_interest', {
                    event_id: eventId,
                    event_type: eventType,
                    price: price,
                    interaction_type: 'click',
                    timestamp: Date.now()
                });
            }

            // Track filter usage
            if (e.target.closest('.wcefp-filters')) {
                const filterType = e.target.name || e.target.className;
                const filterValue = e.target.value || e.target.textContent;
                
                this.trackEvent('filter_usage', {
                    filter_type: filterType,
                    filter_value: filterValue,
                    timestamp: Date.now()
                });
            }

            // Track booking attempts
            if (e.target.closest('.wcefp-booking-widget')) {
                const productId = e.target.closest('.wcefp-booking-widget').dataset.productId;
                this.trackEvent('booking_interest', {
                    product_id: productId,
                    step: 'initiated',
                    timestamp: Date.now()
                });
            }
        });

        // Track time spent on page
        this.trackTimeOnPage();
    }

    trackScrollDepth() {
        let maxScroll = 0;
        const trackScroll = () => {
            const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
                if (scrollPercent >= 25 || scrollPercent >= 50 || scrollPercent >= 75 || scrollPercent >= 90) {
                    this.trackEvent('scroll_depth', {
                        depth: scrollPercent,
                        timestamp: Date.now()
                    });
                }
            }
        };

        window.addEventListener('scroll', this.debounce(trackScroll, 100));
        window.addEventListener('beforeunload', () => {
            this.trackEvent('final_scroll_depth', {
                depth: maxScroll,
                timestamp: Date.now()
            });
        });
    }

    trackTimeOnPage() {
        const startTime = Date.now();
        window.addEventListener('beforeunload', () => {
            const timeSpent = Date.now() - startTime;
            this.trackEvent('time_on_page', {
                duration: timeSpent,
                page: window.location.pathname,
                timestamp: Date.now()
            });
        });
    }

    trackEvent(eventType, data) {
        // Store in session for immediate use
        if (!this.sessionData.events) this.sessionData.events = [];
        this.sessionData.events.push({
            type: eventType,
            data: data,
            timestamp: Date.now()
        });

        // Send to server for long-term storage and analysis
        this.sendToServer('track_behavior', {
            event_type: eventType,
            event_data: JSON.stringify(data),
            session_id: this.sessionData.sessionId
        });

        // Update recommendations if significant event
        if (['event_interest', 'booking_interest', 'filter_usage'].includes(eventType)) {
            this.debounceUpdateRecommendations();
        }
    }

    // Load and display recommendations
    async loadRecommendations() {
        try {
            const recommendations = await this.fetchRecommendations();
            if (recommendations && recommendations.length > 0) {
                this.displayRecommendations(recommendations);
            }
        } catch (error) {
            console.error('Failed to load recommendations:', error);
        }
    }

    async fetchRecommendations() {
        const response = await fetch(this.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'wcefp_get_recommendations',
                nonce: this.nonce,
                user_data: JSON.stringify(this.userPreferences),
                session_data: JSON.stringify(this.sessionData),
                current_page: window.location.pathname
            })
        });

        const data = await response.json();
        return data.success ? data.data : [];
    }

    displayRecommendations(recommendations) {
        const containers = document.querySelectorAll('.wcefp-recommendations-container');
        
        if (containers.length === 0) {
            // Create recommendation container if it doesn't exist
            this.createRecommendationContainer(recommendations);
        } else {
            // Update existing containers
            containers.forEach(container => {
                this.renderRecommendations(container, recommendations);
            });
        }
    }

    createRecommendationContainer(recommendations) {
        const container = document.createElement('div');
        container.className = 'wcefp-recommendations-container';
        container.innerHTML = `
            <div class="wcefp-recommendations-header">
                <h3>
                    <span class="wcefp-recommendations-icon">🎯</span>
                    Consigliato per te
                </h3>
                <p class="wcefp-recommendations-subtitle">
                    Basato sui tuoi interessi e preferenze
                </p>
            </div>
            <div class="wcefp-recommendations-content"></div>
        `;

        // Insert after main content or at the end of body
        const mainContent = document.querySelector('.wcefp-event-grid, .wcefp-main-content, main');
        if (mainContent) {
            mainContent.parentNode.insertBefore(container, mainContent.nextSibling);
        } else {
            document.body.appendChild(container);
        }

        this.renderRecommendations(container.querySelector('.wcefp-recommendations-content'), recommendations);
    }

    renderRecommendations(container, recommendations) {
        const html = recommendations.map(rec => this.createRecommendationCard(rec)).join('');
        container.innerHTML = `
            <div class="wcefp-recommendations-grid">
                ${html}
            </div>
        `;

        // Add interaction tracking to recommendation cards
        container.querySelectorAll('.wcefp-recommendation-card').forEach(card => {
            card.addEventListener('click', () => {
                const recId = card.dataset.recommendationId;
                const recType = card.dataset.recommendationType;
                this.trackEvent('recommendation_click', {
                    recommendation_id: recId,
                    recommendation_type: recType,
                    position: Array.from(container.children).indexOf(card),
                    timestamp: Date.now()
                });
            });
        });
    }

    createRecommendationCard(recommendation) {
        const {
            id,
            title,
            description,
            price,
            image,
            rating,
            type,
            confidence,
            reason
        } = recommendation;

        return `
            <div class="wcefp-recommendation-card" 
                 data-recommendation-id="${id}"
                 data-recommendation-type="${type}"
                 data-confidence="${confidence}">
                <div class="wcefp-recommendation-image">
                    ${image ? `<img src="${image}" alt="${title}" loading="lazy">` : ''}
                    <div class="wcefp-recommendation-badge">${this.getRecommendationBadge(type, confidence)}</div>
                </div>
                <div class="wcefp-recommendation-content">
                    <h4 class="wcefp-recommendation-title">${title}</h4>
                    <p class="wcefp-recommendation-description">${description}</p>
                    ${rating ? `
                        <div class="wcefp-recommendation-rating">
                            ${'⭐'.repeat(Math.floor(rating))} ${rating}/5
                        </div>
                    ` : ''}
                    <div class="wcefp-recommendation-footer">
                        ${price ? `<span class="wcefp-recommendation-price">€${price}</span>` : ''}
                        <span class="wcefp-recommendation-reason">${reason}</span>
                    </div>
                    <button class="wcefp-recommendation-cta" onclick="window.location.href='/product/${id}'">
                        Scopri di più
                    </button>
                </div>
            </div>
        `;
    }

    getRecommendationBadge(type, confidence) {
        const badges = {
            'similar': '🔄 Simile',
            'popular': '🔥 Popolare',
            'trending': '📈 Trend',
            'personalized': '🎯 Per te',
            'location': '📍 Zona',
            'price': '💰 Prezzo',
            'seasonal': '🗓️ Stagionale'
        };

        return badges[type] || '💡 Suggerito';
    }

    // Advanced recommendation algorithms
    calculateSimilarityScore(item1, item2) {
        let score = 0;
        
        // Category similarity
        if (item1.category === item2.category) score += 0.3;
        
        // Price similarity (within 30%)
        if (Math.abs(item1.price - item2.price) / item1.price < 0.3) score += 0.2;
        
        // Location similarity
        if (item1.location === item2.location) score += 0.2;
        
        // Duration similarity
        if (Math.abs(item1.duration - item2.duration) < 60) score += 0.1;
        
        // Rating similarity
        if (Math.abs(item1.rating - item2.rating) < 0.5) score += 0.1;
        
        // Tag overlap
        const commonTags = item1.tags?.filter(tag => item2.tags?.includes(tag)) || [];
        score += (commonTags.length / Math.max(item1.tags?.length || 1, item2.tags?.length || 1)) * 0.1;
        
        return Math.min(score, 1);
    }

    generateCollaborativeRecommendations(userBehavior, allUsers) {
        // Find users with similar behavior patterns
        const similarUsers = allUsers
            .map(user => ({
                user,
                similarity: this.calculateUserSimilarity(userBehavior, user.behavior)
            }))
            .filter(item => item.similarity > 0.5)
            .sort((a, b) => b.similarity - a.similarity)
            .slice(0, 10);

        // Get recommendations from similar users
        const recommendations = [];
        similarUsers.forEach(({user, similarity}) => {
            user.purchases?.forEach(purchase => {
                if (!userBehavior.viewed?.includes(purchase.id)) {
                    recommendations.push({
                        ...purchase,
                        confidence: similarity,
                        reason: 'Utenti simili hanno scelto questo',
                        type: 'collaborative'
                    });
                }
            });
        });

        return recommendations
            .sort((a, b) => b.confidence - a.confidence)
            .slice(0, 5);
    }

    calculateUserSimilarity(user1Behavior, user2Behavior) {
        // Jaccard similarity for viewed items
        const viewed1 = new Set(user1Behavior.viewed || []);
        const viewed2 = new Set(user2Behavior.viewed || []);
        const intersection = new Set([...viewed1].filter(x => viewed2.has(x)));
        const union = new Set([...viewed1, ...viewed2]);
        
        return intersection.size / union.size;
    }

    // Real-time updates
    setupRealTimeUpdates() {
        // Check for new recommendations every 5 minutes
        setInterval(() => {
            this.loadRecommendations();
        }, 300000);

        // Update recommendations when user preferences change
        window.addEventListener('wcefp-preferences-changed', () => {
            this.userPreferences = this.loadUserPreferences();
            this.loadRecommendations();
        });
    }

    // Utility methods
    loadUserPreferences() {
        const stored = localStorage.getItem('wcefp-user-preferences');
        const defaults = {
            categories: [],
            priceRange: { min: 0, max: 1000 },
            locations: [],
            languages: ['it'],
            duration: { min: 0, max: 480 },
            groupSize: { min: 1, max: 10 },
            accessibility: false,
            petFriendly: false
        };
        
        return stored ? { ...defaults, ...JSON.parse(stored) } : defaults;
    }

    initSessionData() {
        return {
            sessionId: this.generateSessionId(),
            startTime: Date.now(),
            events: [],
            currentPage: window.location.pathname
        };
    }

    generateSessionId() {
        return 'wcefp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    debounceUpdateRecommendations = this.debounce(() => {
        this.loadRecommendations();
    }, 2000);

    async sendToServer(action, data) {
        try {
            await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: `wcefp_${action}`,
                    nonce: this.nonce,
                    ...data
                })
            });
        } catch (error) {
            console.error('Failed to send data to server:', error);
        }
    }

    // Public API
    updatePreferences(preferences) {
        this.userPreferences = { ...this.userPreferences, ...preferences };
        localStorage.setItem('wcefp-user-preferences', JSON.stringify(this.userPreferences));
        
        window.dispatchEvent(new CustomEvent('wcefp-preferences-changed', {
            detail: { preferences: this.userPreferences }
        }));
    }

    forceRefresh() {
        this.loadRecommendations();
    }
}

// CSS for recommendations
const recommendationStyles = `
.wcefp-recommendations-container {
    margin: 40px 0;
    background: var(--wcefp-bg-secondary, #f8fafc);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--wcefp-border-light, #e2e8f0);
}

.wcefp-recommendations-header h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px 0;
    color: var(--wcefp-text-primary, #0f172a);
    font-size: 1.5rem;
    font-weight: 600;
}

.wcefp-recommendations-subtitle {
    color: var(--wcefp-text-tertiary, #64748b);
    margin: 0 0 20px 0;
    font-size: 0.875rem;
}

.wcefp-recommendations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.wcefp-recommendation-card {
    background: var(--wcefp-bg-primary, white);
    border-radius: 12px;
    padding: 16px;
    border: 1px solid var(--wcefp-border-light, #e2e8f0);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.wcefp-recommendation-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px var(--wcefp-shadow-medium, rgba(0,0,0,0.1));
    border-color: var(--wcefp-primary, #4f46e5);
}

.wcefp-recommendation-image {
    position: relative;
    margin-bottom: 12px;
}

.wcefp-recommendation-image img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
}

.wcefp-recommendation-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--wcefp-primary, #4f46e5);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.wcefp-recommendation-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 8px 0;
    color: var(--wcefp-text-primary, #0f172a);
}

.wcefp-recommendation-description {
    color: var(--wcefp-text-secondary, #334155);
    margin: 0 0 12px 0;
    font-size: 0.875rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.wcefp-recommendation-rating {
    margin-bottom: 12px;
    font-size: 0.875rem;
}

.wcefp-recommendation-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.wcefp-recommendation-price {
    font-weight: 600;
    color: var(--wcefp-primary, #4f46e5);
}

.wcefp-recommendation-reason {
    font-size: 0.75rem;
    color: var(--wcefp-text-tertiary, #64748b);
}

.wcefp-recommendation-cta {
    width: 100%;
    background: var(--wcefp-primary, #4f46e5);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.wcefp-recommendation-cta:hover {
    background: var(--wcefp-primary-dark, #3730a3);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .wcefp-recommendations-grid {
        grid-template-columns: 1fr;
    }
    
    .wcefp-recommendations-container {
        margin: 20px 0;
        padding: 16px;
    }
}
`;

// Inject styles
const styleEl = document.createElement('style');
styleEl.textContent = recommendationStyles;
document.head.appendChild(styleEl);

// Initialize when ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.wcefpRecommendations = new WCEFPRecommendations();
    });
} else {
    window.wcefpRecommendations = new WCEFPRecommendations();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WCEFPRecommendations;
}