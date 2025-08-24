/**
 * Extra Services Frontend JavaScript
 * 
 * @package WCEventsFP
 * @since 2.1.4
 */

(function($) {
    'use strict';

    /**
     * Extra Services Manager
     */
    const WCEFPExtraServices = {
        
        /**
         * Initialize extra services functionality
         */
        init: function() {
            if (!$('.wcefp-extra-services').length) return;
            
            this.bindEvents();
            this.calculateTotal();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $(document).on('change', '.wcefp-extra-checkbox, .wcefp-extra-select', this.onExtraChange.bind(this));
            $(document).on('click', '.single_add_to_cart_button', this.onAddToCart.bind(this));
        },

        /**
         * Handle extra service selection change
         */
        onExtraChange: function() {
            this.calculateTotal();
            this.updateProductPrice();
        },

        /**
         * Calculate total extra services cost
         */
        calculateTotal: function() {
            let total = 0;
            
            $('.wcefp-extra-service').each(function() {
                const $service = $(this);
                const price = parseFloat($service.data('price')) || 0;
                let quantity = 0;
                
                const $checkbox = $service.find('.wcefp-extra-checkbox');
                const $select = $service.find('.wcefp-extra-select');
                
                if ($checkbox.length && $checkbox.is(':checked')) {
                    quantity = 1;
                } else if ($select.length) {
                    quantity = parseInt($select.val()) || 0;
                }
                
                total += price * quantity;
            });
            
            // Update total display
            const $totalDiv = $('.wcefp-extra-total');
            if (total > 0) {
                $totalDiv.find('.wcefp-extra-total-amount').text(this.formatPrice(total));
                $totalDiv.show();
            } else {
                $totalDiv.hide();
            }
            
            return total;
        },

        /**
         * Update product price display (if available)
         */
        updateProductPrice: function() {
            if (!window.WCEFPExtras) return;
            
            const extraTotal = this.calculateTotal();
            const $priceElement = $('.woocommerce-Price-amount, .price .amount').first();
            
            if ($priceElement.length && extraTotal > 0) {
                // Store original price if not already stored
                if (!$priceElement.data('original-price')) {
                    $priceElement.data('original-price', $priceElement.text());
                }
                
                // Get base price from data attribute or parse from text
                const originalText = $priceElement.data('original-price');
                const originalPrice = this.extractPrice(originalText);
                const newTotal = originalPrice + extraTotal;
                const newPriceText = this.formatPrice(newTotal);
                
                $priceElement.html(newPriceText);
            }
        },

        /**
         * Handle add to cart with extra services validation
         */
        onAddToCart: function(e) {
            // Check if required extras are selected
            let allRequiredSelected = true;
            
            $('.wcefp-extra-service').each(function() {
                const $service = $(this);
                const $required = $service.find('input[type="hidden"]');
                
                if ($required.length) {
                    // This is a required service, always included
                    return;
                }
                
                // Check other required services (this logic can be extended)
                const $checkbox = $service.find('.wcefp-extra-checkbox');
                const $select = $service.find('.wcefp-extra-select');
                
                // Add custom required logic here if needed
            });
            
            if (!allRequiredSelected) {
                e.preventDefault();
                alert('Please select all required extra services.');
                return false;
            }
        },

        /**
         * Extract numeric price from formatted text
         */
        extractPrice: function(priceText) {
            if (!priceText) return 0;
            
            // Remove currency symbols and thousands separators, keep decimal separator
            const cleaned = priceText.replace(/[^0-9,.-]/g, '');
            const normalized = cleaned.replace(',', '.');
            return parseFloat(normalized) || 0;
        },

        /**
         * Format price using WooCommerce currency settings
         */
        formatPrice: function(price) {
            if (!window.WCEFPExtras || !window.WCEFPExtras.currency_symbol) {
                return '€' + price.toFixed(2).replace('.', ',');
            }
            
            // Simple formatting - in production you might want more sophisticated formatting
            return window.WCEFPExtras.currency_symbol + price.toFixed(2).replace('.', ',');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCEFPExtraServices.init();
    });

    // Re-initialize on AJAX product updates (for variations, etc.)
    $(document).on('found_variation', function() {
        WCEFPExtraServices.calculateTotal();
    });

})(jQuery);