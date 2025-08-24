/**
 * Frontend Extras Management
 * 
 * @package WCEFP
 * @since 2.1.4
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Calculate and update extras total
    function updateExtrasTotal() {
        let total = 0;
        
        $('.wcefp-extra-select').each(function() {
            const quantity = parseInt($(this).val()) || 0;
            const price = parseFloat($(this).closest('.wcefp-extra-item').data('price')) || 0;
            total += quantity * price;
        });
        
        $('.wcefp-extras-total-amount').text(formatPrice(total));
        
        // Update product price if WooCommerce variations script is available
        if (typeof wc_single_product_params !== 'undefined') {
            updateProductPrice(total);
        }
    }
    
    // Format price using WooCommerce format
    function formatPrice(amount) {
        if (typeof accounting !== 'undefined') {
            return accounting.formatMoney(amount, {
                symbol: wcefp_extras.currency_symbol,
                format: '%s%v'
            });
        }
        return wcefp_extras.currency_symbol + amount.toFixed(2);
    }
    
    // Update product total price
    function updateProductPrice(extrasTotal) {
        const $priceDisplay = $('.price .woocommerce-Price-amount');
        if ($priceDisplay.length && typeof window.wcefp_base_price !== 'undefined') {
            const basePrice = window.wcefp_base_price;
            const totalPrice = basePrice + extrasTotal;
            
            $priceDisplay.html(
                '<bdi>' + formatPrice(totalPrice) + '</bdi>'
            );
        }
    }
    
    // Initialize extras functionality
    function initExtras() {
        // Store base product price
        const $basePrice = $('.price .woocommerce-Price-amount bdi');
        if ($basePrice.length) {
            const priceText = $basePrice.text().replace(/[^\d.,]/g, '');
            window.wcefp_base_price = parseFloat(priceText.replace(',', '.')) || 0;
        }
        
        // Bind change event to extras selects
        $('.wcefp-extra-select').on('change', function() {
            updateExtrasTotal();
            
            // Validate required extras
            validateRequiredExtras();
        });
        
        // Initial calculation
        updateExtrasTotal();
        
        // Validate on form submit
        $('form.cart').on('submit', function(e) {
            if (!validateRequiredExtras()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Validate required extras
    function validateRequiredExtras() {
        let valid = true;
        
        $('.wcefp-extra-select[required]').each(function() {
            const $select = $(this);
            const value = parseInt($select.val()) || 0;
            
            if (value === 0) {
                $select.addClass('error');
                valid = false;
            } else {
                $select.removeClass('error');
            }
        });
        
        if (!valid) {
            alert('Please select required additional services.');
        }
        
        return valid;
    }
    
    // Add error styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .wcefp-extra-select.error {
                border-color: #dc3232 !important;
                box-shadow: 0 0 2px rgba(220, 50, 50, 0.5) !important;
            }
            .wcefp-extras-total-amount {
                font-weight: bold;
                color: #0073aa;
            }
            .wcefp-extra-item:hover {
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
        `)
        .appendTo('head');
    
    // Initialize when page loads
    initExtras();
    
    // Re-initialize if variations change (WooCommerce compatibility)
    $('body').on('woocommerce_variation_has_changed', function() {
        setTimeout(initExtras, 100);
    });
});