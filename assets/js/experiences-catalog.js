/**
 * WCEventsFP Experiences Catalog JavaScript
 * Interactive filtering, search, and AJAX functionality for experiences catalog
 *
 * @package WCEFP
 * @since 2.2.0
 */

(function($) {
    'use strict';

    /**
     * Experiences Catalog Class
     * Handles filtering, search, and AJAX functionality
     */
    class WCEFPExperiencesCatalog {
        
        constructor($container) {
            this.$container = $container;
            this.$grid = $container.find('.wcefp-experiences-grid');
            this.$filters = $container.find('.wcefp-experiences-filters');
            this.originalCards = [];
            this.filteredCards = [];
            this.currentFilters = {
                search: '',
                category: '',
                location: '',
                duration: '',
                price: '',
                rating: '',
                date: '',
                sortBy: 'date',
                sortDir: 'desc'
            };
            this.debounceTimer = null;
            this.currentPage = 1;
            this.isLoading = false;
            
            this.init();
        }

        init() {
            // Store original cards
            this.storeOriginalCards();
            
            // Bind events
            this.bindFilterEvents();
            this.bindCardEvents();
            
            // Initialize any existing filters
            this.applyInitialFilters();
            
            console.log('WCEventsFP Experiences Catalog initialized');
        }

        storeOriginalCards() {
            this.$grid.find('.wcefp-experience-card').each((index, card) => {
                const $card = $(card);
                const cardData = {
                    element: $card.clone(true),
                    title: $card.find('.wcefp-card-title').text().toLowerCase(),
                    category: $card.data('category') || '',
                    price: parseFloat($card.find('.wcefp-card-price').text().replace(/[^\d.]/g, '')) || 0,
                    experienceId: $card.data('experience-id')
                };
                this.originalCards.push(cardData);
            });
            
            this.filteredCards = [...this.originalCards];
        }

        bindFilterEvents() {
            const $searchInput = this.$filters.find('.wcefp-search-input');
            const $categorySelect = this.$filters.find('.wcefp-filter-category');
            const $locationSelect = this.$filters.find('.wcefp-filter-location');
            const $durationSelect = this.$filters.find('.wcefp-filter-duration');
            const $priceSelect = this.$filters.find('.wcefp-filter-price');
            const $ratingSelect = this.$filters.find('.wcefp-filter-rating');
            const $dateInput = this.$filters.find('.wcefp-filter-date');
            const $sortSelect = this.$filters.find('.wcefp-sort-select');
            const $clearButton = this.$filters.find('.wcefp-clear-filters');

            // Search input with debouncing
            $searchInput.on('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.currentFilters.search = $(e.target).val().toLowerCase();
                    this.applyFilters();
                    this.trackEvent('experience_search', { search_term: this.currentFilters.search });
                }, 300);
            });

            // Category filter
            $categorySelect.on('change', (e) => {
                this.currentFilters.category = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_category', { category: this.currentFilters.category });
            });

            // Location filter
            $locationSelect.on('change', (e) => {
                this.currentFilters.location = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_location', { location: this.currentFilters.location });
            });

            // Duration filter
            $durationSelect.on('change', (e) => {
                this.currentFilters.duration = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_duration', { duration: this.currentFilters.duration });
            });

            // Price filter
            $priceSelect.on('change', (e) => {
                this.currentFilters.price = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_price', { price_range: this.currentFilters.price });
            });

            // Rating filter
            $ratingSelect.on('change', (e) => {
                this.currentFilters.rating = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_rating', { rating: this.currentFilters.rating });
            });

            // Date filter
            $dateInput.on('change', (e) => {
                this.currentFilters.date = $(e.target).val();
                this.applyFilters();
                this.trackEvent('experience_filter_date', { date: this.currentFilters.date });
            });

            // Sort control
            $sortSelect.on('change', (e) => {
                const sortValue = $(e.target).val();
                const [sortBy, sortDir] = sortValue.split('-');
                this.currentFilters.sortBy = sortBy;
                this.currentFilters.sortDir = sortDir;
                this.applySorting();
                this.trackEvent('experience_sort', { sort_by: sortBy, sort_direction: sortDir });
            });

            // Clear filters
            $clearButton.on('click', (e) => {
                e.preventDefault();
                this.clearAllFilters();
                this.trackEvent('experience_filters_cleared');
            });

            // AJAX Load More
            const $loadMore = this.$container.find('.wcefp-load-more');
            $loadMore.on('click', (e) => {
                e.preventDefault();
                this.loadMoreExperiences();
            });
        }

        bindCardEvents() {
            // Add hover effects and analytics tracking
            this.$container.on('mouseenter', '.wcefp-experience-card', (e) => {
                const $card = $(e.currentTarget);
                const experienceId = $card.data('experience-id');
                
                // Track hover event for analytics
                this.trackEvent('experience_card_hover', {
                    experience_id: experienceId,
                    position: $card.index()
                });
            });

            // Track clicks to experience details
            this.$container.on('click', '.wcefp-card-title a, .wcefp-btn-primary', (e) => {
                const $card = $(e.target).closest('.wcefp-experience-card');
                const experienceId = $card.data('experience-id');
                const title = $card.find('.wcefp-card-title').text();
                
                // Track click event
                this.trackEvent('experience_card_click', {
                    experience_id: experienceId,
                    experience_title: title,
                    position: $card.index()
                });
            });
        }

        applyInitialFilters() {
            // Check for URL parameters to set initial filters
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('category')) {
                this.currentFilters.category = urlParams.get('category');
                this.$filters.find('.wcefp-filter-category').val(this.currentFilters.category);
            }
            
            if (urlParams.has('search')) {
                this.currentFilters.search = urlParams.get('search').toLowerCase();
                this.$filters.find('.wcefp-search-input').val(this.currentFilters.search);
            }
            
            if (urlParams.has('price')) {
                this.currentFilters.price = urlParams.get('price');
                this.$filters.find('.wcefp-filter-price').val(this.currentFilters.price);
            }

            // Apply initial filters if any are set
            if (this.currentFilters.search || this.currentFilters.category || this.currentFilters.price) {
                this.applyFilters();
            }
        }

        applyFilters() {
            // Show loading state
            this.$container.addClass('wcefp-filtering');
            
            // Filter cards based on current criteria
            this.filteredCards = this.originalCards.filter(cardData => {
                return this.matchesSearchFilter(cardData) &&
                       this.matchesCategoryFilter(cardData) &&
                       this.matchesPriceFilter(cardData);
            });

            // Update the display
            this.renderFilteredCards();
            
            // Update URL without page reload
            this.updateUrlParams();
            
            // Track filter usage
            this.trackFilterUsage();
            
            // Remove loading state
            setTimeout(() => {
                this.$container.removeClass('wcefp-filtering');
            }, 200);
        }

        matchesSearchFilter(cardData) {
            if (!this.currentFilters.search) return true;
            
            const searchTerm = this.currentFilters.search;
            return cardData.title.includes(searchTerm);
        }

        matchesCategoryFilter(cardData) {
            if (!this.currentFilters.category) return true;
            
            return cardData.category === this.currentFilters.category;
        }

        matchesPriceFilter(cardData) {
            if (!this.currentFilters.price) return true;
            
            const priceRange = this.currentFilters.price;
            const price = cardData.price;
            
            switch(priceRange) {
                case '0-50':
                    return price >= 0 && price <= 50;
                case '50-100':
                    return price > 50 && price <= 100;
                case '100-200':
                    return price > 100 && price <= 200;
                case '200+':
                    return price > 200;
                default:
                    return true;
            }
        }

        renderFilteredCards() {
            // Clear current grid
            this.$grid.empty();
            
            if (this.filteredCards.length === 0) {
                this.showEmptyState();
                return;
            }

            // Add filtered cards with animation
            this.filteredCards.forEach((cardData, index) => {
                const $card = cardData.element;
                $card.css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                });
                
                this.$grid.append($card);
                
                // Animate in with delay
                setTimeout(() => {
                    $card.css({
                        opacity: 1,
                        transform: 'translateY(0)',
                        transition: 'all 0.3s ease-out'
                    });
                }, index * 50);
            });

            // Update result count
            this.updateResultCount();
        }

        showEmptyState() {
            const emptyHtml = `
                <div class="wcefp-experiences-empty-filtered">
                    <div class="wcefp-empty-icon">🔍</div>
                    <h3>Nessuna esperienza trovata</h3>
                    <p>Prova a modificare i filtri di ricerca per trovare più risultati.</p>
                    <button class="wcefp-btn wcefp-btn-primary wcefp-clear-filters-inline">
                        Cancella tutti i filtri
                    </button>
                </div>
            `;
            
            this.$grid.html(emptyHtml);
            
            // Bind clear filters button
            this.$grid.find('.wcefp-clear-filters-inline').on('click', () => {
                this.clearAllFilters();
            });
        }

        updateResultCount() {
            let $resultCount = this.$container.find('.wcefp-result-count');
            
            if ($resultCount.length === 0) {
                $resultCount = $('<div class="wcefp-result-count"></div>');
                this.$filters.append($resultCount);
            }
            
            const count = this.filteredCards.length;
            const text = count === 1 ? 
                `${count} esperienza trovata` : 
                `${count} esperienze trovate`;
                
            $resultCount.text(text);
        }

        clearAllFilters() {
            // Reset filter values
            this.currentFilters = {
                search: '',
                category: '',
                location: '',
                duration: '',
                price: '',
                rating: '',
                date: '',
                sortBy: 'date',
                sortDir: 'desc'
            };
            
            // Reset form inputs
            this.$filters.find('.wcefp-search-input').val('');
            this.$filters.find('.wcefp-filter-category').val('');
            this.$filters.find('.wcefp-filter-location').val('');
            this.$filters.find('.wcefp-filter-duration').val('');
            this.$filters.find('.wcefp-filter-price').val('');
            this.$filters.find('.wcefp-filter-rating').val('');
            this.$filters.find('.wcefp-filter-date').val('');
            this.$filters.find('.wcefp-sort-select').val('date-desc');
            
            // Show all cards
            this.filteredCards = [...this.originalCards];
            this.renderFilteredCards();
            
            // Clear URL params
            this.updateUrlParams();
            
            // Track filter clear
            this.trackEvent('filters_cleared', {});
        }

        updateUrlParams() {
            if (!window.history || !window.history.pushState) return;
            
            const url = new URL(window.location);
            
            // Clear existing filter params
            url.searchParams.delete('search');
            url.searchParams.delete('category');
            url.searchParams.delete('price');
            
            // Add current filters
            if (this.currentFilters.search) {
                url.searchParams.set('search', this.currentFilters.search);
            }
            if (this.currentFilters.category) {
                url.searchParams.set('category', this.currentFilters.category);
            }
            if (this.currentFilters.price) {
                url.searchParams.set('price', this.currentFilters.price);
            }
            
            // Update URL without reload
            window.history.pushState({}, '', url);
        }

        trackFilterUsage() {
            const activeFilters = [];
            if (this.currentFilters.search) activeFilters.push('search');
            if (this.currentFilters.category) activeFilters.push('category');
            if (this.currentFilters.price) activeFilters.push('price');
            
            this.trackEvent('catalog_filtered', {
                active_filters: activeFilters,
                result_count: this.filteredCards.length,
                search_term: this.currentFilters.search,
                selected_category: this.currentFilters.category,
                selected_price_range: this.currentFilters.price
            });
        }

        trackEvent(eventName, data = {}) {
            // Check if tracking is disabled via admin settings
            if (typeof WCEFPData !== 'undefined' && WCEFPData.disable_analytics) {
                return;
            }
            
            // Google Analytics 4 enhanced ecommerce events
            if (typeof gtag !== 'undefined') {
                // Map custom events to GA4 standard events where possible
                let ga4EventName = eventName;
                let ga4Parameters = { ...data };
                
                switch (eventName) {
                    case 'experience_card_click':
                        ga4EventName = 'select_item';
                        ga4Parameters = {
                            item_list_name: 'experiences_catalog',
                            items: [{
                                item_id: data.experience_id,
                                item_name: data.experience_title,
                                item_category: 'experience',
                                index: data.position
                            }]
                        };
                        break;
                        
                    case 'experience_load_more':
                        ga4EventName = 'view_item_list';
                        ga4Parameters = {
                            item_list_name: 'experiences_catalog',
                            page: data.page
                        };
                        break;
                        
                    default:
                        ga4Parameters.event_category = 'wcefp_experiences';
                        ga4Parameters.custom_parameters = data;
                        break;
                }
                
                // Send to GA4
                gtag('event', ga4EventName, ga4Parameters);
            }
            
            // DataLayer for GTM (enhanced with consent mode support)
            if (window.dataLayer) {
                window.dataLayer.push({
                    event: eventName,
                    event_category: 'wcefp_experiences',
                    wcefp_action: eventName,
                    wcefp_data: data,
                    // Add consent mode flags if available
                    ...(window.gtag_consent_state && { consent_state: window.gtag_consent_state })
                });
            }
            
            // Facebook Pixel integration (if available and consent given)
            if (typeof fbq !== 'undefined' && eventName === 'experience_card_click') {
                fbq('trackCustom', 'ViewExperience', {
                    experience_id: data.experience_id,
                    experience_title: data.experience_title
                });
            }
            
            // Console log for debugging (only in non-production)
            if (window.console && typeof console.log === 'function' && 
                (typeof WCEFPData === 'undefined' || WCEFPData.debug_mode)) {
                console.log('WCEventsFP Analytics:', eventName, data);
            }
        }

        applySorting() {
            const { sortBy, sortDir } = this.currentFilters;
            
            this.filteredCards.sort((a, b) => {
                let aValue, bValue;
                
                switch (sortBy) {
                    case 'price':
                        aValue = a.price || 0;
                        bValue = b.price || 0;
                        break;
                    case 'rating':
                        aValue = a.rating || 0;
                        bValue = b.rating || 0;
                        break;
                    case 'popularity':
                        aValue = a.popularity || 0;
                        bValue = b.popularity || 0;
                        break;
                    case 'title':
                        aValue = a.title || '';
                        bValue = b.title || '';
                        break;
                    case 'date':
                    default:
                        aValue = a.date || 0;
                        bValue = b.date || 0;
                        break;
                }
                
                if (typeof aValue === 'string') {
                    return sortDir === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                } else {
                    return sortDir === 'asc' ? aValue - bValue : bValue - aValue;
                }
            });
            
            this.renderFilteredCards();
        }

        loadMoreExperiences() {
            if (this.isLoading) return;
            
            const $loadMore = this.$container.find('.wcefp-load-more');
            const $spinner = $loadMore.find('.wcefp-load-spinner');
            const $text = $loadMore.find('.wcefp-load-text');
            
            this.isLoading = true;
            $loadMore.prop('disabled', true);
            $spinner.show();
            $text.text('Caricamento...');
            
            this.currentPage++;
            
            // AJAX request to load more experiences
            if (typeof wcefp_ajax !== 'undefined') {
                $.ajax({
                    url: wcefp_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'wcefp_load_more_experiences',
                        nonce: wcefp_ajax.nonce,
                        page: this.currentPage,
                        filters: this.currentFilters,
                        per_page: this.$container.data('per-page') || 12
                    },
                    success: (response) => {
                        if (response.success && response.data.experiences) {
                            const $grid = this.$container.find('.wcefp-experiences-items');
                            $grid.append(response.data.experiences);
                            
                            // Update pagination state
                            if (!response.data.has_more) {
                                $loadMore.hide();
                            }
                            
                            this.trackEvent('experience_load_more', {
                                page: this.currentPage,
                                results_count: response.data.count
                            });
                        } else {
                            console.error('Failed to load more experiences:', response);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX error loading experiences:', error);
                        this.currentPage--; // Revert page increment on error
                    },
                    complete: () => {
                        this.isLoading = false;
                        $loadMore.prop('disabled', false);
                        $spinner.hide();
                        $text.text('Carica altre esperienze');
                    }
                });
            }
        }

        showSkeletonLoader() {
            const $skeleton = this.$container.find('.wcefp-skeleton-loader');
            const $items = this.$container.find('.wcefp-experiences-items');
            
            $items.hide();
            $skeleton.show();
        }

        hideSkeletonLoader() {
            const $skeleton = this.$container.find('.wcefp-skeleton-loader');
            const $items = this.$container.find('.wcefp-experiences-items');
            
            $skeleton.hide();
            $items.show();
        }
    }

    /**
     * Map Integration (placeholder for Google Maps integration)
     */
    class WCEFPMapIntegration {
        
        constructor($mapContainer) {
            this.$mapContainer = $mapContainer;
            this.map = null;
            this.markers = [];
            
            this.init();
        }

        init() {
            // Check if Google Maps is loaded
            if (typeof google !== 'undefined' && google.maps) {
                this.initGoogleMap();
            } else {
                this.showMapPlaceholder();
            }
        }

        initGoogleMap() {
            const mapOptions = {
                zoom: 12,
                center: { lat: 41.9028, lng: 12.4964 }, // Default to Rome
                styles: this.getMapStyles()
            };
            
            this.map = new google.maps.Map(this.$mapContainer[0], mapOptions);
            
            // Add markers for experiences
            this.addExperienceMarkers();
        }

        addExperienceMarkers() {
            // This would be populated with real experience locations
            const experiences = this.getExperienceLocations();
            
            experiences.forEach(experience => {
                const marker = new google.maps.Marker({
                    position: { lat: experience.lat, lng: experience.lng },
                    map: this.map,
                    title: experience.title,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="16" fill="#667eea"/>
                                <path d="M16 8L18.5 13H24L19.5 16.5L21.5 22L16 18.5L10.5 22L12.5 16.5L8 13H13.5L16 8Z" fill="white"/>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });
                
                // Add info window
                const infoWindow = new google.maps.InfoWindow({
                    content: this.createInfoWindowContent(experience)
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(this.map, marker);
                });
                
                this.markers.push(marker);
            });
        }

        createInfoWindowContent(experience) {
            return `
                <div class="wcefp-map-info-window">
                    <h4>${experience.title}</h4>
                    <p>${experience.excerpt}</p>
                    <div class="wcefp-map-info-price">${experience.price}</div>
                    <a href="${experience.permalink}" class="wcefp-btn wcefp-btn-primary wcefp-btn-sm">
                        Scopri di più
                    </a>
                </div>
            `;
        }

        getMapStyles() {
            return [
                {
                    "featureType": "all",
                    "elementType": "geometry.fill",
                    "stylers": [{"weight": "2.00"}]
                },
                {
                    "featureType": "all",
                    "elementType": "geometry.stroke",
                    "stylers": [{"color": "#9c9c9c"}]
                },
                {
                    "featureType": "all",
                    "elementType": "labels.text",
                    "stylers": [{"visibility": "on"}]
                },
                {
                    "featureType": "landscape",
                    "elementType": "all",
                    "stylers": [{"color": "#f2f2f2"}]
                },
                {
                    "featureType": "water",
                    "elementType": "all",
                    "stylers": [{"color": "#667eea"}]
                }
            ];
        }

        getExperienceLocations() {
            // This would be populated from WordPress data
            return [];
        }

        showMapPlaceholder() {
            this.$mapContainer.html(`
                <div class="wcefp-map-placeholder">
                    <div class="wcefp-map-placeholder-content">
                        <div class="wcefp-map-placeholder-icon">🗺️</div>
                        <h4>Mappa non disponibile</h4>
                        <p>Per visualizzare le esperienze sulla mappa, configura Google Maps API nelle impostazioni del plugin.</p>
                    </div>
                </div>
            `);
        }
    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        // Initialize experiences catalogs
        $('.wcefp-experiences-catalog').each(function() {
            new WCEFPExperiencesCatalog($(this));
        });

        // Initialize maps
        $('.wcefp-experiences-map').each(function() {
            new WCEFPMapIntegration($(this));
        });
    });

    // Export classes for external use
    window.WCEFPExperiencesCatalog = WCEFPExperiencesCatalog;
    window.WCEFPMapIntegration = WCEFPMapIntegration;

})(jQuery);