/**
 * WCEventsFP Admin Product Editor JavaScript
 * 
 * Handles dynamic UI interactions for the enhanced product editor
 */
(function($) {
    'use strict';
    
    let wcefpEditor = {
        
        init: function() {
            this.initTabSwitching();
            this.initDynamicLists();
            this.initValidation();
            this.initMeetingPointSelector();
            this.initExtrasSelector();
            this.initFormEnhancements();
            this.bindEvents();
        },
        
        initTabSwitching: function() {
            // Show/hide fields based on product type
            $('body').on('woocommerce-product-type-change', this.handleProductTypeChange);
            
            // Initialize on page load
            $(document).ready(function() {
                wcefpEditor.handleProductTypeChange();
            });
        },
        
        handleProductTypeChange: function() {
            const productType = $('#product-type').val();
            const wcefpTabs = $('.wcefp_tab');
            
            if (productType === 'evento' || productType === 'esperienza') {
                wcefpTabs.show();
            } else {
                wcefpTabs.hide();
            }
        },
        
        initDynamicLists: function() {
            // Ticket Types
            this.initTicketTypes();
            
            // Product Extras
            this.initProductExtras();
            
            // Alternative Meeting Points
            this.initAlternativeMeetingPoints();
            
            // Cancellation Rules
            this.initCancellationRules();
        },
        
        initTicketTypes: function() {
            let ticketIndex = $('.wcefp-ticket-type-row').length;
            
            // Add ticket type
            $(document).on('click', '.wcefp-add-ticket-type', function(e) {
                e.preventDefault();
                
                const newRow = `
                    <div class="wcefp-ticket-type-row" data-index="${ticketIndex}">
                        <div class="wcefp-ticket-fields">
                            <div class="wcefp-field-group">
                                <label>Tipo:</label>
                                <input type="text" name="_wcefp_ticket_types[${ticketIndex}][type]" value="" class="regular-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Etichetta:</label>
                                <input type="text" name="_wcefp_ticket_types[${ticketIndex}][label]" value="" class="regular-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Prezzo (€):</label>
                                <input type="number" step="0.01" name="_wcefp_ticket_types[${ticketIndex}][price]" value="" class="small-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Min:</label>
                                <input type="number" name="_wcefp_ticket_types[${ticketIndex}][min_quantity]" value="0" class="small-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Max:</label>
                                <input type="number" name="_wcefp_ticket_types[${ticketIndex}][max_quantity]" value="10" class="small-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label><input type="checkbox" name="_wcefp_ticket_types[${ticketIndex}][enabled]" value="1" checked /> Attivo</label>
                            </div>
                            <div class="wcefp-field-actions">
                                <button type="button" class="button wcefp-remove-ticket-type" title="Rimuovi">✕</button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('.wcefp-ticket-types-list').append(newRow);
                ticketIndex++;
                
                wcefpEditor.updateRowIndices('.wcefp-ticket-type-row', '_wcefp_ticket_types');
            });
            
            // Remove ticket type
            $(document).on('click', '.wcefp-remove-ticket-type', function(e) {
                e.preventDefault();
                $(this).closest('.wcefp-ticket-type-row').fadeOut(300, function() {
                    $(this).remove();
                    wcefpEditor.updateRowIndices('.wcefp-ticket-type-row', '_wcefp_ticket_types');
                });
            });
        },
        
        initProductExtras: function() {
            let extraIndex = $('.wcefp-product-extra-row').length;
            
            // Add product extra
            $(document).on('click', '.wcefp-add-product-extra', function(e) {
                e.preventDefault();
                
                const newRow = `
                    <div class="wcefp-product-extra-row" data-index="${extraIndex}">
                        <div class="wcefp-extra-fields">
                            <div class="wcefp-field-group">
                                <label>Nome:</label>
                                <input type="text" name="_wcefp_product_extras[${extraIndex}][name]" value="" class="regular-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Prezzo (€):</label>
                                <input type="number" step="0.01" name="_wcefp_product_extras[${extraIndex}][price]" value="" class="small-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Tipo prezzo:</label>
                                <select name="_wcefp_product_extras[${extraIndex}][pricing_type]">
                                    <option value="fixed">Fisso</option>
                                    <option value="per_person">Per persona</option>
                                    <option value="per_adult">Per adulto</option>
                                    <option value="per_child">Per bambino</option>
                                </select>
                            </div>
                            <div class="wcefp-field-actions">
                                <button type="button" class="button wcefp-remove-product-extra" title="Rimuovi">✕</button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('.wcefp-product-extras-list').append(newRow);
                extraIndex++;
                
                wcefpEditor.updateRowIndices('.wcefp-product-extra-row', '_wcefp_product_extras');
            });
            
            // Remove product extra
            $(document).on('click', '.wcefp-remove-product-extra', function(e) {
                e.preventDefault();
                $(this).closest('.wcefp-product-extra-row').fadeOut(300, function() {
                    $(this).remove();
                    wcefpEditor.updateRowIndices('.wcefp-product-extra-row', '_wcefp_product_extras');
                });
            });
        },
        
        initAlternativeMeetingPoints: function() {
            let altMpIndex = $('.wcefp-alternative-mp-row').length;
            
            // Add alternative meeting point
            $(document).on('click', '.wcefp-add-alternative-mp', function(e) {
                e.preventDefault();
                
                const meetingPointOptions = $('#_wcefp_meeting_point_id').html();
                
                const newRow = `
                    <div class="wcefp-alternative-mp-row" data-index="${altMpIndex}">
                        <div class="wcefp-alt-mp-fields">
                            <div class="wcefp-field-group">
                                <label>Condizione:</label>
                                <input type="text" name="_wcefp_alternative_meeting_points[${altMpIndex}][condition]" value="" placeholder="Es. Maltempo" class="regular-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Meeting Point:</label>
                                <select name="_wcefp_alternative_meeting_points[${altMpIndex}][meeting_point_id]">
                                    ${meetingPointOptions}
                                </select>
                            </div>
                            <div class="wcefp-field-actions">
                                <button type="button" class="button wcefp-remove-alternative-mp" title="Rimuovi">✕</button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#wcefp-alternative-meeting-points .wcefp-alternative-actions').before(newRow);
                altMpIndex++;
                
                wcefpEditor.updateRowIndices('.wcefp-alternative-mp-row', '_wcefp_alternative_meeting_points');
            });
            
            // Remove alternative meeting point
            $(document).on('click', '.wcefp-remove-alternative-mp', function(e) {
                e.preventDefault();
                $(this).closest('.wcefp-alternative-mp-row').fadeOut(300, function() {
                    $(this).remove();
                    wcefpEditor.updateRowIndices('.wcefp-alternative-mp-row', '_wcefp_alternative_meeting_points');
                });
            });
        },
        
        initCancellationRules: function() {
            let ruleIndex = $('.wcefp-cancellation-rule-row').length;
            
            // Add cancellation rule
            $(document).on('click', '.wcefp-add-cancellation-rule', function(e) {
                e.preventDefault();
                
                const newRow = `
                    <div class="wcefp-cancellation-rule-row" data-index="${ruleIndex}">
                        <div class="wcefp-rule-fields">
                            <div class="wcefp-field-group">
                                <label>Tempo limite:</label>
                                <input type="text" name="_wcefp_cancellation_rules[${ruleIndex}][timeframe]" value="" placeholder="24h" class="small-text" />
                                <span class="description">(es: 24h, 2d, 1w)</span>
                            </div>
                            <div class="wcefp-field-group">
                                <label>Rimborso (%):</label>
                                <input type="number" min="0" max="100" name="_wcefp_cancellation_rules[${ruleIndex}][refund_percentage]" value="" class="small-text" />
                            </div>
                            <div class="wcefp-field-group">
                                <label>Descrizione:</label>
                                <input type="text" name="_wcefp_cancellation_rules[${ruleIndex}][description]" value="" class="regular-text" />
                            </div>
                            <div class="wcefp-field-actions">
                                <button type="button" class="button wcefp-remove-cancellation-rule" title="Rimuovi">✕</button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('.wcefp-rules-container').append(newRow);
                ruleIndex++;
                
                wcefpEditor.updateRowIndices('.wcefp-cancellation-rule-row', '_wcefp_cancellation_rules');
            });
            
            // Remove cancellation rule
            $(document).on('click', '.wcefp-remove-cancellation-rule', function(e) {
                e.preventDefault();
                if ($('.wcefp-cancellation-rule-row').length <= 1) {
                    alert('Deve rimanere almeno una regola di cancellazione.');
                    return;
                }
                
                $(this).closest('.wcefp-cancellation-rule-row').fadeOut(300, function() {
                    $(this).remove();
                    wcefpEditor.updateRowIndices('.wcefp-cancellation-rule-row', '_wcefp_cancellation_rules');
                });
            });
        },
        
        updateRowIndices: function(rowSelector, fieldName) {
            $(rowSelector).each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input, select').each(function() {
                    const name = $(this).attr('name');
                    if (name && name.includes(fieldName)) {
                        const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', newName);
                    }
                });
            });
        },
        
        initMeetingPointSelector: function() {
            // Show/hide custom override field based on meeting point selection
            $(document).on('change', '#_wcefp_meeting_point_id', function() {
                const selectedValue = $(this).val();
                const customField = $('#_wcefp_meeting_point_custom').closest('.form-field');
                
                if (selectedValue && selectedValue !== '') {
                    customField.fadeIn();
                } else {
                    customField.fadeOut();
                }
            });
            
            // Initialize on page load
            $('#_wcefp_meeting_point_id').trigger('change');
        },
        
        initExtrasSelector: function() {
            // Initialize Select2 for multi-select extras
            if ($.fn.select2) {
                $('#wcefp_available_extras').select2({
                    placeholder: 'Seleziona extra da collegare...',
                    allowClear: true
                });
                
                // Update hidden input when selection changes
                $('#wcefp_available_extras').on('change', function() {
                    const selectedValues = $(this).val() || [];
                    $('#wcefp_linked_extras_input').val(selectedValues.join(','));
                });
            }
        },
        
        initValidation: function() {
            // Real-time validation for numeric fields
            $(document).on('input', 'input[type="number"]', function() {
                const input = $(this);
                const min = parseFloat(input.attr('min'));
                const max = parseFloat(input.attr('max'));
                const value = parseFloat(input.val());
                
                input.removeClass('wcefp-field-error wcefp-field-success');
                
                if (input.val() !== '' && !isNaN(value)) {
                    if ((!isNaN(min) && value < min) || (!isNaN(max) && value > max)) {
                        input.addClass('wcefp-field-error');
                    } else {
                        input.addClass('wcefp-field-success');
                    }
                }
            });
            
            // Validate timeframe format
            $(document).on('input', 'input[name*="timeframe"]', function() {
                const input = $(this);
                const value = input.val();
                const timeframeRegex = /^\d+[hdw]$/i;
                
                input.removeClass('wcefp-field-error wcefp-field-success');
                
                if (value !== '') {
                    if (timeframeRegex.test(value)) {
                        input.addClass('wcefp-field-success');
                    } else {
                        input.addClass('wcefp-field-error');
                    }
                }
            });
            
            // Validate time slots format
            $(document).on('input', '#_wcefp_time_slots', function() {
                const input = $(this);
                const value = input.val();
                const timeSlots = value.split(',');
                const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
                
                input.removeClass('wcefp-field-error wcefp-field-success');
                
                if (value !== '') {
                    let allValid = true;
                    for (let slot of timeSlots) {
                        if (!timeRegex.test(slot.trim())) {
                            allValid = false;
                            break;
                        }
                    }
                    
                    if (allValid) {
                        input.addClass('wcefp-field-success');
                    } else {
                        input.addClass('wcefp-field-error');
                    }
                }
            });
        },
        
        initFormEnhancements: function() {
            // Add loading states for form submission
            $('form#post').on('submit', function() {
                $('.wcefp_tab').addClass('wcefp-loading');
            });
            
            // Auto-calculate child price based on adult price
            $(document).on('input', '#_wcefp_price_adult', function() {
                const adultPrice = parseFloat($(this).val()) || 0;
                const childPrice = adultPrice * 0.7; // 30% discount for children
                
                if ($('#_wcefp_price_child').val() === '') {
                    $('#_wcefp_price_child').val(childPrice.toFixed(2));
                }
            });
            
            // Show/hide conditional fields
            this.initConditionalFields();
        },
        
        initConditionalFields: function() {
            // Overbooking settings
            $(document).on('change', '#_wcefp_overbooking_enabled', function() {
                const overbookingFields = $('#_wcefp_overbooking_percentage').closest('.form-field');
                if ($(this).is(':checked')) {
                    overbookingFields.fadeIn();
                } else {
                    overbookingFields.fadeOut();
                }
            }).trigger('change');
            
            // Waitlist settings
            $(document).on('change', '#_wcefp_waitlist_enabled', function() {
                const waitlistFields = $('#_wcefp_waitlist_threshold').closest('.form-field');
                if ($(this).is(':checked')) {
                    waitlistFields.fadeIn();
                } else {
                    waitlistFields.fadeOut();
                }
            }).trigger('change');
            
            // Custom cancellation policy
            $(document).on('change', '#_wcefp_custom_cancellation_enabled', function() {
                const cancellationFields = $('#wcefp-cancellation-rules');
                if ($(this).is(':checked')) {
                    cancellationFields.fadeIn();
                } else {
                    cancellationFields.fadeOut();
                }
            }).trigger('change');
            
            // Rescheduling settings
            $(document).on('change', '#_wcefp_rescheduling_enabled', function() {
                const reschedulingFields = $('#_wcefp_rescheduling_deadline, #_wcefp_rescheduling_fee_percentage, #_wcefp_rescheduling_max_times').closest('.form-field');
                if ($(this).is(':checked')) {
                    reschedulingFields.fadeIn();
                } else {
                    reschedulingFields.fadeOut();
                }
            }).trigger('change');
            
            // Weather policy settings
            $(document).on('change', '#_wcefp_weather_cancellation_enabled', function() {
                const weatherFields = $('#_wcefp_weather_policy_type, #_wcefp_weather_policy_text').closest('.form-field');
                if ($(this).is(':checked')) {
                    weatherFields.fadeIn();
                } else {
                    weatherFields.fadeOut();
                }
            }).trigger('change');
        },
        
        bindEvents: function() {
            // Tab navigation enhancements
            $('.woocommerce_options_panel').on('show', function() {
                if ($(this).hasClass('wcefp_tab')) {
                    $(this).find('input:first').focus();
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + S to save
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                    e.preventDefault();
                    $('form#post').submit();
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        wcefpEditor.init();
    });
    
    // Expose for external use
    window.WCEFPEditor = wcefpEditor;
    
})(jQuery);