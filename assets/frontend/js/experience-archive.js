/**
 * WCEventsFP Experience Archive JavaScript
 * 
 * Handles filtering, search, and dynamic functionality for the experiences archive
 */
(function($) {
    'use strict';
    
    let wcefpExperienceArchive = {
        
        // Current filters state
        filters: {
            search: '',
            duration: '',
            price: '',
            sort: 'menu_order'
        },
        
        // Debounced search timer
        searchTimer: null,
        
        // Cache DOM elements
        $container: null,
        $grid: null,
        $filters: null,
        $searchInput: null,
        $loading: null,
        
        /**
         * Initialize the archive functionality
         */
        init: function() {
            this.bindElements();
            this.bindEvents();
            this.initializeFilters();
            
            // Initialize masonry if layout is masonry
            if (this.$grid.hasClass('wcefp-layout-masonry')) {
                this.initMasonry();
            }
        },
        
        /**
         * Bind DOM elements
         */
        bindElements: function() {
            this.$container = $('.wcefp-experiences-archive-shortcode, .wcefp-experiences-archive');
            this.$grid = this.$container.find('.wcefp-experiences-grid');
            this.$filters = this.$container.find('.wcefp-archive-filters');
            this.$searchInput = this.$container.find('.wcefp-search-input');
            
            // Create loading indicator
            this.$loading = $('<div class="wcefp-loading">Caricamento...</div>').hide();
            this.$container.prepend(this.$loading);
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;
            
            // Search input
            this.$searchInput.on('input', function() {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(function() {
                    self.filters.search = self.$searchInput.val();
                    self.performSearch();
                }, 500); // 500ms debounce
            });
            
            // Filter selects
            this.$container.on('change', '.wcefp-filter-select', function() {
                const filterId = $(this).attr('id');
                const value = $(this).val();
                
                switch(filterId) {
                    case 'wcefp-duration-filter':
                        self.filters.duration = value;
                        break;
                    case 'wcefp-price-filter':
                        self.filters.price = value;
                        break;
                    case 'wcefp-sort-filter':
                        self.filters.sort = value;
                        break;
                }
                
                self.performSearch();
            });
            
            // Handle pagination clicks
            this.$container.on('click', '.wcefp-pagination a', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                self.loadPage(url);
            });
            
            // Handle card hover effects
            this.$container.on('mouseenter', '.wcefp-experience-card', function() {
                $(this).addClass('wcefp-card-hover');
            }).on('mouseleave', '.wcefp-experience-card', function() {
                $(this).removeClass('wcefp-card-hover');
            });
            
            // Keyboard navigation for cards
            this.$container.on('keydown', '.wcefp-experience-card', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const link = $(this).find('.wcefp-card-title a, .wcefp-view-experience-btn').first();
                    if (link.length) {
                        window.location.href = link.attr('href');
                    }
                }
            });
        },
        
        /**
         * Initialize filters from URL parameters
         */
        initializeFilters: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Set initial filter values from URL
            const search = urlParams.get('search');
            if (search) {
                this.filters.search = search;
                this.$searchInput.val(search);
            }
            
            const duration = urlParams.get('duration');
            if (duration) {
                this.filters.duration = duration;
                $('#wcefp-duration-filter').val(duration);
            }
            
            const price = urlParams.get('price');
            if (price) {
                this.filters.price = price;
                $('#wcefp-price-filter').val(price);
            }
            
            const sort = urlParams.get('sort');
            if (sort) {
                this.filters.sort = sort;
                $('#wcefp-sort-filter').val(sort);
            }
        },
        
        /**
         * Perform search/filtering
         */
        performSearch: function() {
            this.showLoading();
            
            // Build query parameters
            const params = {
                action: 'wcefp_filter_experiences',
                nonce: wcefp_archive.nonce,
                ...this.filters
            };
            
            // Update URL without reload
            this.updateUrl(params);
            
            // Perform AJAX request
            $.ajax({
                url: wcefp_archive.ajax_url,
                method: 'POST',
                data: params,
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.updateGrid(response.data.html);
                        this.updatePagination(response.data.pagination);
                        this.announceResults(response.data.count);
                    } else {
                        this.showError(response.data?.message || wcefp_archive.i18n.error);
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showError(wcefp_archive.i18n.error);
                }
            });
        },
        
        /**
         * Load specific page
         */
        loadPage: function(url) {
            this.showLoading();
            
            $.ajax({
                url: url,
                method: 'GET',
                success: (response) => {
                    this.hideLoading();
                    
                    // Extract the grid content from response
                    const $response = $(response);
                    const $newGrid = $response.find('.wcefp-experiences-grid');
                    const $newPagination = $response.find('.wcefp-archive-pagination');
                    
                    if ($newGrid.length) {
                        this.updateGrid($newGrid.html());
                    }
                    
                    if ($newPagination.length) {
                        this.updatePagination($newPagination.html());
                    }
                    
                    // Scroll to top of archive
                    this.$container[0].scrollIntoView({ behavior: 'smooth' });
                },
                error: () => {
                    this.hideLoading();
                    this.showError(wcefp_archive.i18n.error);
                }
            });
        },
        
        /**
         * Update grid content
         */
        updateGrid: function(html) {
            this.$grid.fadeOut(200, () => {
                this.$grid.html(html);
                this.$grid.fadeIn(200);
                
                // Reinitialize masonry if needed
                if (this.$grid.hasClass('wcefp-layout-masonry')) {
                    this.initMasonry();
                }
            });
        },
        
        /**
         * Update pagination
         */
        updatePagination: function(html) {
            const $pagination = this.$container.find('.wcefp-archive-pagination');
            if ($pagination.length) {
                $pagination.html(html);
            }
        },
        
        /**
         * Show loading state
         */
        showLoading: function() {
            this.$loading.show();
            this.$grid.addClass('wcefp-filtering');
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            this.$loading.hide();
            this.$grid.removeClass('wcefp-filtering');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            const $error = $('<div class="wcefp-error-message">' + message + '</div>');
            this.$container.prepend($error);
            
            // Remove error after 5 seconds
            setTimeout(() => {
                $error.fadeOut(() => $error.remove());
            }, 5000);
        },
        
        /**
         * Update URL with current filters
         */
        updateUrl: function(params) {
            const url = new URL(window.location);
            
            // Clear existing parameters
            ['search', 'duration', 'price', 'sort'].forEach(key => {
                url.searchParams.delete(key);
            });
            
            // Add new parameters
            Object.keys(params).forEach(key => {
                if (params[key] && key !== 'action' && key !== 'nonce') {
                    url.searchParams.set(key, params[key]);
                }
            });
            
            // Update browser history without reload
            window.history.replaceState(null, '', url.toString());
        },
        
        /**
         * Announce results to screen readers
         */
        announceResults: function(count) {
            const message = count > 0 
                ? count + ' esperienze trovate'
                : wcefp_archive.i18n.no_results;
                
            // Create or update aria-live region
            let $announcement = $('#wcefp-search-announcement');
            if (!$announcement.length) {
                $announcement = $('<div id="wcefp-search-announcement" aria-live="polite" class="sr-only"></div>');
                this.$container.prepend($announcement);
            }
            
            $announcement.text(message);
        },
        
        /**
         * Initialize masonry layout
         */
        initMasonry: function() {
            if (typeof Masonry !== 'undefined') {
                new Masonry(this.$grid[0], {
                    itemSelector: '.wcefp-experience-item',
                    columnWidth: '.wcefp-experience-item',
                    percentPosition: true
                });
            } else {
                // Fallback CSS-only masonry
                this.$grid.addClass('wcefp-css-masonry');
            }
        },
        
        /**
         * Handle responsive layout changes
         */
        handleResize: function() {
            // Debounced resize handler
            let resizeTimer;
            $(window).on('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (this.$grid.hasClass('wcefp-layout-masonry')) {
                        this.initMasonry();
                    }
                }, 250);
            });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if we're on an experience archive page
        if ($('.wcefp-experiences-archive, .wcefp-experiences-archive-shortcode').length > 0) {
            wcefpExperienceArchive.init();
            wcefpExperienceArchive.handleResize();
        }
    });
    
    /**
     * AJAX handler for experience filtering
     */
    $(document).on('ready', function() {
        // Register AJAX handler for filtering (if not already registered)
        if (typeof wcefp_archive !== 'undefined') {
            // This would be handled on the server side
            // The AJAX endpoint 'wcefp_filter_experiences' should be registered in PHP
        }
    });
    
    /**
     * Utility functions
     */
    window.wcefpExperienceArchive = {
        /**
         * Refresh the archive (public method)
         */
        refresh: function() {
            wcefpExperienceArchive.performSearch();
        },
        
        /**
         * Set filter values programmatically
         */
        setFilters: function(filters) {
            Object.assign(wcefpExperienceArchive.filters, filters);
            wcefpExperienceArchive.performSearch();
        },
        
        /**
         * Get current filter values
         */
        getFilters: function() {
            return {...wcefpExperienceArchive.filters};
        }
    };
    
})(jQuery);

/**
 * Vanilla JavaScript fallback for non-jQuery environments
 */
if (typeof jQuery === 'undefined') {
    console.warn('WCEventsFP Experience Archive: jQuery not found, some features may not work');
    
    // Basic fallback functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('.wcefp-search-input');
        const filterSelects = document.querySelectorAll('.wcefp-filter-select');
        
        // Basic search functionality
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const searchTerm = this.value.toLowerCase();
                    const cards = document.querySelectorAll('.wcefp-experience-card');
                    
                    cards.forEach(card => {
                        const title = card.querySelector('.wcefp-card-title');
                        const excerpt = card.querySelector('.wcefp-card-excerpt');
                        
                        if (title && excerpt) {
                            const titleText = title.textContent.toLowerCase();
                            const excerptText = excerpt.textContent.toLowerCase();
                            
                            if (titleText.includes(searchTerm) || excerptText.includes(searchTerm)) {
                                card.style.display = 'block';
                            } else {
                                card.style.display = 'none';
                            }
                        }
                    });
                }, 500);
            });
        }
        
        // Basic accessibility enhancements
        const cards = document.querySelectorAll('.wcefp-experience-card');
        cards.forEach(card => {
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'article');
            
            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const link = this.querySelector('.wcefp-card-title a, .wcefp-view-experience-btn');
                    if (link) {
                        window.location.href = link.href;
                    }
                }
            });
        });
    });
}