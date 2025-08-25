/**
 * WCEventsFP Experiences Catalog JavaScript
 * Interactive filtering, search, and layout switching
 * 
 * @package WCEFP
 * @since 2.2.0
 */

(function($) {
    'use strict';

    /**
     * Main Catalog Class
     */
    var WCEFPCatalog = {
        
        // Configuration
        config: {
            searchDelay: 500,
            animationSpeed: 300,
            loadingTimeout: 10000
        },
        
        // State
        state: {
            isLoading: false,
            currentRequest: null,
            searchTimeout: null
        },
        
        /**
         * Initialize the catalog functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeExistingCatalogs();
            console.log('WCEventsFP Catalog initialized');
        },
        
        /**
         * Initialize any existing catalog instances on page load
         */
        initializeExistingCatalogs: function() {
            $('.wcefp-experiences-catalog').each(function() {
                var $catalog = $(this);
                WCEFPCatalog.setupCatalog($catalog);
            });
        },
        
        /**
         * Setup individual catalog instance
         */
        setupCatalog: function($catalog) {
            var catalogId = $catalog.attr('id');
            console.log('Setting up catalog:', catalogId);
            
            // Initialize layout state
            var $layoutBtns = $catalog.find('.wcefp-layout-btn');
            var activeLayout = $layoutBtns.filter('.active').data('layout') || 'grid';
            this.updateLayout($catalog, activeLayout);
            
            // Initialize filter state
            this.updateResultsCount($catalog);
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Search functionality with debouncing
            $(document).on('input', '.wcefp-experience-search', function() {
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                var searchTerm = $(this).val();
                
                // Clear existing timeout
                if (self.state.searchTimeout) {
                    clearTimeout(self.state.searchTimeout);
                }
                
                // Set new timeout
                self.state.searchTimeout = setTimeout(function() {
                    self.filterExperiences($catalog);
                }, self.config.searchDelay);
            });
            
            // Filter dropdowns
            $(document).on('change', '.wcefp-filter-select', function() {
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                self.filterExperiences($catalog);
            });
            
            // Sort dropdown
            $(document).on('change', '.wcefp-sort-select', function() {
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                self.filterExperiences($catalog);
            });
            
            // Layout toggle buttons
            $(document).on('click', '.wcefp-layout-btn', function(e) {
                e.preventDefault();
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                var layout = $(this).data('layout');
                
                // Update button states
                $(this).addClass('active').siblings('.wcefp-layout-btn').removeClass('active');
                
                // Update layout
                self.updateLayout($catalog, layout);
            });
            
            // Clear filters button
            $(document).on('click', '.wcefp-clear-filters', function(e) {
                e.preventDefault();
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                self.clearAllFilters($catalog);
            });
            
            // Pagination (when implemented)
            $(document).on('click', '.wcefp-pagination-btn', function(e) {
                e.preventDefault();
                var $catalog = $(this).closest('.wcefp-experiences-catalog');
                var page = $(this).data('page');
                if (page && !$(this).hasClass('active') && !$(this).is(':disabled')) {
                    self.loadPage($catalog, page);
                }
            });
            
            // Card hover effects
            $(document).on('mouseenter', '.wcefp-experience-card', function() {
                $(this).addClass('hovered');
            }).on('mouseleave', '.wcefp-experience-card', function() {
                $(this).removeClass('hovered');
            });
            
            // Accessibility: keyboard navigation for layout buttons
            $(document).on('keydown', '.wcefp-layout-btn', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },
        
        /**
         * Filter experiences via AJAX
         */
        filterExperiences: function($catalog, page) {
            var self = this;
            page = page || 1;
            
            // Prevent multiple simultaneous requests
            if (self.state.isLoading) {
                if (self.state.currentRequest) {
                    self.state.currentRequest.abort();
                }
            }
            
            var $results = $catalog.find('.wcefp-catalog-results');
            var $grid = $catalog.find('.wcefp-experiences-grid');
            var $loading = $catalog.find('.wcefp-loading');
            var $noResults = $catalog.find('.wcefp-no-results');
            
            // Collect all filter data
            var filters = {};
            $catalog.find('.wcefp-filter-select').each(function() {
                var filter = $(this).data('filter');
                var value = $(this).val();
                if (value) {
                    filters[filter] = value;
                }
            });
            
            // Get catalog attributes
            var attributes = {};
            try {
                attributes = JSON.parse($catalog.data('attributes') || '{}');
            } catch (e) {
                console.warn('Failed to parse catalog attributes:', e);
            }
            
            var data = {
                action: 'wcefp_filter_experiences',
                nonce: wcefp_catalog.nonce,
                search: $catalog.find('.wcefp-experience-search').val(),
                sort: $catalog.find('.wcefp-sort-select').val() || 'popularity',
                layout: $catalog.find('.wcefp-layout-btn.active').data('layout') || 'grid',
                filters: filters,
                page: page,
                limit: parseInt(attributes.limit) || 12
            };
            
            console.log('Filtering experiences with data:', data);
            
            // Show loading state
            self.showLoadingState($catalog, true);
            self.state.isLoading = true;
            
            // Make AJAX request
            self.state.currentRequest = $.ajax({
                url: wcefp_catalog.ajaxurl,
                type: 'POST',
                data: data,
                timeout: self.config.loadingTimeout
            })
            .done(function(response) {
                console.log('Filter response:', response);
                
                if (response.success && response.data) {
                    self.updateCatalogContent($catalog, response.data);
                    self.updateResultsCount($catalog, response.data.found_posts);
                    
                    // Scroll to results if not on first page
                    if (page > 1) {
                        self.scrollToResults($catalog);
                    }
                    
                    // Trigger custom event
                    $catalog.trigger('wcefp:filtered', [response.data]);
                    
                } else {
                    self.showError($catalog, response.data?.message || wcefp_catalog.strings.error);
                }
            })
            .fail(function(xhr, textStatus, errorThrown) {
                if (textStatus !== 'abort') {
                    console.error('Filter request failed:', textStatus, errorThrown);
                    self.showError($catalog, wcefp_catalog.strings.error);
                }
            })
            .always(function() {
                self.showLoadingState($catalog, false);
                self.state.isLoading = false;
                self.state.currentRequest = null;
            });
        },
        
        /**
         * Update catalog content after successful filter
         */
        updateCatalogContent: function($catalog, data) {
            var $grid = $catalog.find('.wcefp-experiences-grid');
            var $noResults = $catalog.find('.wcefp-no-results');
            
            if (data.html && data.found_posts > 0) {
                $grid.fadeOut(200, function() {
                    $grid.html(data.html).fadeIn(300);
                    $noResults.hide();
                    
                    // Ensure layout classes are correct
                    var layout = $catalog.find('.wcefp-layout-btn.active').data('layout') || 'grid';
                    $grid.find('.wcefp-experience-card').removeClass('layout-grid layout-list').addClass('layout-' + layout);
                });
            } else {
                $grid.fadeOut(200);
                $noResults.fadeIn(300);
            }
        },
        
        /**
         * Update layout of experience cards
         */
        updateLayout: function($catalog, layout) {
            var $grid = $catalog.find('.wcefp-experiences-grid');
            var $cards = $catalog.find('.wcefp-experience-card');
            
            // Update grid classes
            $grid.removeClass('layout-grid layout-list').addClass('layout-' + layout);
            
            // Update card classes
            $cards.removeClass('layout-grid layout-list').addClass('layout-' + layout);
            
            console.log('Layout updated to:', layout);
        },
        
        /**
         * Clear all filters and reset catalog
         */
        clearAllFilters: function($catalog) {
            // Clear search input
            $catalog.find('.wcefp-experience-search').val('');
            
            // Reset filter selects
            $catalog.find('.wcefp-filter-select').val('');
            
            // Reset sort to default
            $catalog.find('.wcefp-sort-select').val('popularity');
            
            // Trigger filter update
            this.filterExperiences($catalog);
            
            console.log('Filters cleared');
        },
        
        /**
         * Load specific page
         */
        loadPage: function($catalog, page) {
            this.filterExperiences($catalog, page);
        },
        
        /**
         * Show/hide loading state
         */
        showLoadingState: function($catalog, show) {
            var $loading = $catalog.find('.wcefp-loading');
            var $grid = $catalog.find('.wcefp-experiences-grid');
            var $noResults = $catalog.find('.wcefp-no-results');
            
            if (show) {
                $grid.fadeOut(150);
                $noResults.hide();
                $loading.fadeIn(200);
            } else {
                $loading.fadeOut(150);
            }
        },
        
        /**
         * Show error message
         */
        showError: function($catalog, message) {
            var $grid = $catalog.find('.wcefp-experiences-grid');
            var errorHtml = '<div class="wcefp-error">' + 
                           '<p>' + (message || wcefp_catalog.strings.error) + '</p>' +
                           '</div>';
            
            $grid.html(errorHtml).show();
            console.error('Catalog error:', message);
        },
        
        /**
         * Update results count display
         */
        updateResultsCount: function($catalog, count) {
            var $resultsCount = $catalog.find('.wcefp-results-count');
            if ($resultsCount.length && typeof count !== 'undefined') {
                var text = count === 1 ? 
                    wcefp_catalog.strings.one_result : 
                    wcefp_catalog.strings.multiple_results.replace('%d', count);
                $resultsCount.text(text);
            }
        },
        
        /**
         * Scroll to results area
         */
        scrollToResults: function($catalog) {
            var $results = $catalog.find('.wcefp-catalog-results');
            if ($results.length) {
                $('html, body').animate({
                    scrollTop: $results.offset().top - 100
                }, 500);
            }
        },
        
        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        /**
         * Get catalog instance data
         */
        getCatalogData: function($catalog) {
            var data = {
                id: $catalog.attr('id'),
                attributes: {},
                filters: {},
                search: $catalog.find('.wcefp-experience-search').val(),
                sort: $catalog.find('.wcefp-sort-select').val(),
                layout: $catalog.find('.wcefp-layout-btn.active').data('layout')
            };
            
            // Parse attributes
            try {
                data.attributes = JSON.parse($catalog.data('attributes') || '{}');
            } catch (e) {
                console.warn('Failed to parse catalog attributes');
            }
            
            // Collect filters
            $catalog.find('.wcefp-filter-select').each(function() {
                var filter = $(this).data('filter');
                var value = $(this).val();
                if (value) {
                    data.filters[filter] = value;
                }
            });
            
            return data;
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if catalog localization is available
        if (typeof wcefp_catalog === 'undefined') {
            console.warn('WCEFP Catalog: Missing localization data');
            // Provide fallback
            window.wcefp_catalog = {
                ajaxurl: '/wp-admin/admin-ajax.php',
                nonce: '',
                strings: {
                    loading: 'Loading...',
                    no_results: 'No experiences found.',
                    error: 'An error occurred while loading experiences.',
                    prev_page: 'Previous',
                    next_page: 'Next',
                    one_result: '1 experience found',
                    multiple_results: '%d experiences found'
                }
            };
        }
        
        // Initialize catalog
        WCEFPCatalog.init();
    });
    
    /**
     * Expose WCEFPCatalog globally for external access
     */
    window.WCEFPCatalog = WCEFPCatalog;

})(jQuery);

/**
 * Vanilla JavaScript fallback for non-jQuery environments
 */
if (typeof jQuery === 'undefined') {
    console.warn('WCEFP Catalog: jQuery not found, some functionality may be limited');
    
    // Basic functionality without jQuery
    document.addEventListener('DOMContentLoaded', function() {
        // Basic search functionality
        var searchInputs = document.querySelectorAll('.wcefp-experience-search');
        searchInputs.forEach(function(input) {
            var timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    console.log('Search term:', input.value);
                    // Implement basic filtering here if needed
                }, 500);
            });
        });
        
        // Basic layout toggle
        var layoutBtns = document.querySelectorAll('.wcefp-layout-btn');
        layoutBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var layout = this.dataset.layout;
                var catalog = this.closest('.wcefp-experiences-catalog');
                
                // Update button states
                layoutBtns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                
                // Update layout
                var grid = catalog.querySelector('.wcefp-experiences-grid');
                var cards = catalog.querySelectorAll('.wcefp-experience-card');
                
                grid.className = grid.className.replace(/layout-\w+/g, '') + ' layout-' + layout;
                cards.forEach(function(card) {
                    card.className = card.className.replace(/layout-\w+/g, '') + ' layout-' + layout;
                });
            });
        });
    });
}