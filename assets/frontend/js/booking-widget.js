/**
 * WCEventsFP Frontend Booking Widget JavaScript
 * 
 * Handles customer-facing booking interactions
 */
(function($) {
    'use strict';
    
    let wcefpBooking = {
        
        widgets: {},
        
        init: function() {
            this.bindGlobalEvents();
            this.initializeWidgets();
        },
        
        bindGlobalEvents: function() {
            // Initialize widgets when they appear (for dynamic content)
            $(document).on('wcefp:init-widget', '.wcefp-booking-widget', function() {
                wcefpBooking.initWidget($(this));
            });
            
            // Re-initialize on AJAX complete (for cart updates)
            $(document).ajaxComplete(function() {
                wcefpBooking.initializeWidgets();
            });
        },
        
        initializeWidgets: function() {
            $('.wcefp-booking-widget').each(function() {
                wcefpBooking.initWidget($(this));
            });
        },
        
        initWidget: function($widget) {
            const productId = $widget.data('product-id');
            
            if (!productId || this.widgets[productId]) {
                return; // Already initialized or invalid
            }
            
            this.widgets[productId] = new BookingWidgetInstance($widget, productId);
        }
    };
    
    /**
     * Individual booking widget instance
     */
    function BookingWidgetInstance($widget, productId) {
        this.$widget = $widget;
        this.productId = productId;
        this.selectedDate = null;
        this.selectedSlot = null;
        this.currentPrice = 0;
        
        this.init();
    }
    
    BookingWidgetInstance.prototype = {
        
        init: function() {
            this.bindEvents();
            this.initDatePicker();
            this.updateSummary();
        },
        
        bindEvents: function() {
            const self = this;
            
            // Date selection
            this.$widget.find('.wcefp-date-input').on('change', function() {
                self.onDateChange($(this).val());
            });
            
            // Time slot selection
            this.$widget.on('click', '.wcefp-time-slot:not(.full)', function() {
                self.onSlotSelect($(this));
            });
            
            // Ticket quantity changes
            this.$widget.on('change', '.wcefp-ticket-qty', function() {
                self.onQuantityChange();
            });
            
            this.$widget.on('click', '.wcefp-qty-minus', function() {
                self.adjustQuantity($(this), -1);
            });
            
            this.$widget.on('click', '.wcefp-qty-plus', function() {
                self.adjustQuantity($(this), 1);
            });
            
            // Extra quantity changes
            this.$widget.on('change', '.wcefp-extra-qty', function() {
                self.onQuantityChange();
            });
            
            // Add to cart
            this.$widget.find('.wcefp-add-to-cart-btn').on('click', function() {
                self.addToCart();
            });
        },
        
        initDatePicker: function() {
            const $dateInput = this.$widget.find('.wcefp-date-input');
            const today = new Date().toISOString().split('T')[0];
            const maxDate = new Date();
            maxDate.setFullYear(maxDate.getFullYear() + 1);
            
            $dateInput.attr('min', today);
            $dateInput.attr('max', maxDate.toISOString().split('T')[0]);
        },
        
        onDateChange: function(date) {
            if (!date) return;
            
            this.selectedDate = date;
            this.selectedSlot = null;
            this.loadTimeSlots(date);
        },
        
        loadTimeSlots: function(date) {
            const self = this;
            const $slotsContainer = this.$widget.find('.wcefp-time-slots');
            
            // Show loading state
            $slotsContainer.html('<div class="wcefp-loading-slots"><div class="wcefp-spinner"></div><p>' + wcefp_booking.i18n.loading + '</p></div>');
            
            $.ajax({
                url: wcefp_booking.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_get_occurrences',
                    nonce: wcefp_booking.nonce,
                    product_id: this.productId,
                    date: date
                },
                success: function(response) {
                    if (response.success && response.data.occurrences) {
                        self.renderTimeSlots(response.data.occurrences);
                    } else {
                        $slotsContainer.html('<p class="wcefp-no-slots">' + wcefp_booking.i18n.no_availability + '</p>');
                    }
                },
                error: function() {
                    $slotsContainer.html('<p class="wcefp-error">' + wcefp_booking.i18n.booking_error + '</p>');
                }
            });
        },
        
        renderTimeSlots: function(occurrences) {
            const $container = this.$widget.find('.wcefp-time-slots');
            let html = '';
            
            if (occurrences.length === 0) {
                html = '<p class="wcefp-no-slots">' + wcefp_booking.i18n.no_availability + '</p>';
            } else {
                occurrences.forEach(function(occurrence) {
                    const available = occurrence.capacity - occurrence.booked;
                    const isFull = available <= 0;
                    const slotClass = isFull ? 'wcefp-time-slot full' : 'wcefp-time-slot';
                    
                    const startTime = new Date(occurrence.start_local).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    html += `
                        <button type="button" 
                                class="${slotClass}" 
                                data-occurrence-id="${occurrence.id}"
                                data-start-time="${occurrence.start_local}"
                                data-available="${available}"
                                ${isFull ? 'disabled' : ''}>
                            <span class="wcefp-slot-info">
                                <strong>${startTime}</strong>
                                <span class="wcefp-capacity">${available} ${wcefp_booking.i18n.available || 'disponibili'}</span>
                            </span>
                        </button>
                    `;
                });
            }
            
            $container.html(html);
        },
        
        onSlotSelect: function($slot) {
            // Remove previous selection
            this.$widget.find('.wcefp-time-slot').removeClass('selected');
            
            // Select new slot
            $slot.addClass('selected');
            
            this.selectedSlot = {
                id: $slot.data('occurrence-id'),
                startTime: $slot.data('start-time'),
                available: $slot.data('available')
            };
            
            this.validateSelection();
            this.updateSummary();
        },
        
        adjustQuantity: function($button, delta) {
            const $input = $button.siblings('input[type="number"]');
            const current = parseInt($input.val()) || 0;
            const min = parseInt($input.attr('min')) || 0;
            const max = parseInt($input.attr('max')) || 10;
            
            let newValue = current + delta;
            newValue = Math.max(min, Math.min(max, newValue));
            
            $input.val(newValue).trigger('change');
        },
        
        onQuantityChange: function() {
            this.validateSelection();
            this.calculatePrice();
        },
        
        validateSelection: function() {
            const self = this;
            const $addButton = this.$widget.find('.wcefp-add-to-cart-btn');
            let isValid = true;
            let errors = [];
            
            // Check if date and slot are selected
            if (!this.selectedDate || !this.selectedSlot) {
                isValid = false;
                errors.push(wcefp_booking.i18n.select_date);
            }
            
            // Check if at least one ticket is selected
            const totalTickets = this.getTotalTickets();
            if (totalTickets === 0) {
                isValid = false;
                errors.push(wcefp_booking.i18n.select_tickets);
            }
            
            // Check capacity
            if (this.selectedSlot && totalTickets > this.selectedSlot.available) {
                isValid = false;
                errors.push(wcefp_booking.i18n.max_capacity);
            }
            
            // Check minimum requirements
            const minParticipants = this.getMinimumParticipants();
            if (minParticipants && totalTickets < minParticipants) {
                isValid = false;
                errors.push(wcefp_booking.i18n.min_participants + ': ' + minParticipants);
            }
            
            // Update button state
            $addButton.prop('disabled', !isValid);
            
            // Show/hide errors
            this.showMessages(errors, 'error');
            
            return isValid;
        },
        
        getTotalTickets: function() {
            let total = 0;
            this.$widget.find('.wcefp-ticket-qty').each(function() {
                total += parseInt($(this).val()) || 0;
            });
            return total;
        },
        
        getMinimumParticipants: function() {
            // This could be enhanced to read from product meta
            return 1; // Default minimum
        },
        
        calculatePrice: function() {
            const self = this;
            
            if (!this.selectedDate) {
                this.updatePriceDisplay(0);
                return;
            }
            
            // Collect ticket data
            const tickets = {};
            this.$widget.find('.wcefp-ticket-qty').each(function() {
                const $input = $(this);
                const ticketKey = $input.closest('.wcefp-ticket-row').data('ticket-key');
                const quantity = parseInt($input.val()) || 0;
                if (quantity > 0) {
                    tickets[ticketKey] = quantity;
                }
            });
            
            // Collect extras data
            const extras = {};
            this.$widget.find('.wcefp-extra-qty').each(function() {
                const $input = $(this);
                const extraKey = $input.closest('.wcefp-extra-row').data('extra-key');
                const quantity = parseInt($input.val()) || 0;
                if (quantity > 0) {
                    extras[extraKey] = quantity;
                }
            });
            
            // Calculate via AJAX
            $.ajax({
                url: wcefp_booking.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_calculate_booking_price',
                    nonce: wcefp_booking.nonce,
                    product_id: this.productId,
                    tickets: tickets,
                    extras: extras,
                    date: this.selectedDate
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updatePriceDisplay(response.data);
                    }
                },
                error: function() {
                    console.error('Price calculation failed');
                }
            });
        },
        
        updatePriceDisplay: function(calculation) {
            const $totalElement = this.$widget.find('.wcefp-total-value');
            
            if (typeof calculation === 'object' && calculation.total !== undefined) {
                this.currentPrice = calculation.total;
                $totalElement.html(this.formatPrice(calculation.total));
                this.updateDetailedSummary(calculation);
            } else {
                const price = parseFloat(calculation) || 0;
                this.currentPrice = price;
                $totalElement.html(this.formatPrice(price));
            }
        },
        
        updateDetailedSummary: function(calculation) {
            const $summary = this.$widget.find('.wcefp-summary');
            let html = '';
            
            if (calculation.tickets && calculation.tickets.length > 0) {
                calculation.tickets.forEach(function(ticket) {
                    if (ticket.quantity > 0) {
                        html += `
                            <div class="wcefp-summary-item">
                                <div class="wcefp-summary-label">
                                    ${ticket.type}
                                    <span class="wcefp-summary-quantity">(${ticket.quantity}x)</span>
                                </div>
                                <div class="wcefp-summary-price">${wcefpBooking.formatPrice(ticket.total)}</div>
                            </div>
                        `;
                    }
                });
            }
            
            if (calculation.extras && calculation.extras.length > 0) {
                calculation.extras.forEach(function(extra) {
                    if (extra.quantity > 0) {
                        html += `
                            <div class="wcefp-summary-item">
                                <div class="wcefp-summary-label">
                                    ${extra.type}
                                    <span class="wcefp-summary-quantity">(${extra.quantity}x)</span>
                                </div>
                                <div class="wcefp-summary-price">${wcefpBooking.formatPrice(extra.total)}</div>
                            </div>
                        `;
                    }
                });
            }
            
            if (html) {
                $summary.html(html);
            } else {
                $summary.html('<div class="wcefp-summary-empty"><p>' + wcefp_booking.i18n.select_tickets + '</p></div>');
            }
        },
        
        updateSummary: function() {
            this.calculatePrice();
        },
        
        addToCart: function() {
            const self = this;
            const $button = this.$widget.find('.wcefp-add-to-cart-btn');
            
            if (!this.validateSelection()) {
                return;
            }
            
            // Collect form data
            const tickets = {};
            this.$widget.find('.wcefp-ticket-qty').each(function() {
                const $input = $(this);
                const ticketKey = $input.closest('.wcefp-ticket-row').data('ticket-key');
                const quantity = parseInt($input.val()) || 0;
                if (quantity > 0) {
                    tickets[ticketKey] = quantity;
                }
            });
            
            const extras = {};
            this.$widget.find('.wcefp-extra-qty').each(function() {
                const $input = $(this);
                const extraKey = $input.closest('.wcefp-extra-row').data('extra-key');
                const quantity = parseInt($input.val()) || 0;
                if (quantity > 0) {
                    extras[extraKey] = quantity;
                }
            });
            
            // Show loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Add to cart via AJAX
            $.ajax({
                url: wcefp_booking.ajax_url,
                method: 'POST',
                data: {
                    action: 'wcefp_add_booking_to_cart',
                    nonce: wcefp_booking.nonce,
                    product_id: this.productId,
                    occurrence_id: this.selectedSlot.id,
                    tickets: tickets,
                    extras: extras
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessages([wcefp_booking.i18n.booking_success], 'success');
                        
                        // Trigger WooCommerce cart update
                        $(document.body).trigger('wc_fragment_refresh');
                        
                        // Reset form
                        self.resetForm();
                    } else {
                        const errorMsg = response.data || wcefp_booking.i18n.booking_error;
                        self.showMessages([errorMsg], 'error');
                    }
                },
                error: function() {
                    self.showMessages([wcefp_booking.i18n.booking_error], 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },
        
        resetForm: function() {
            // Reset selections
            this.selectedDate = null;
            this.selectedSlot = null;
            
            // Reset form fields
            this.$widget.find('.wcefp-date-input').val('');
            this.$widget.find('.wcefp-time-slots').html('<p class="wcefp-placeholder">' + wcefp_booking.i18n.select_date + '</p>');
            this.$widget.find('.wcefp-ticket-qty, .wcefp-extra-qty').each(function() {
                const $input = $(this);
                const isRequired = $input.prop('required');
                $input.val(isRequired ? 1 : 0);
            });
            this.$widget.find('.wcefp-time-slot').removeClass('selected');
            
            // Reset summary
            this.updateSummary();
        },
        
        showMessages: function(messages, type) {
            const $messagesContainer = this.$widget.find('.wcefp-messages');
            $messagesContainer.empty();
            
            messages.forEach(function(message) {
                const $message = $(`<div class="wcefp-message ${type}">${message}</div>`);
                $messagesContainer.append($message);
            });
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $messagesContainer.empty();
                }, 5000);
            }
        },
        
        formatPrice: function(price) {
            return wcefp_booking.currency_symbol + parseFloat(price).toFixed(2);
        }
    };
    
    // Static utility method
    wcefpBooking.formatPrice = function(price) {
        return wcefp_booking.currency_symbol + parseFloat(price).toFixed(2);
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        wcefpBooking.init();
    });
    
    // Expose to global scope
    window.wcefpBooking = wcefpBooking;
    
})(jQuery);