/**
 * Enhanced admin JavaScript for WCEventsFP
 * Provides better user feedback and error handling
 * 
 * @since 1.7.2
 */
(function($) {
    'use strict';
    
    // Toast notification system
    const WCEFPToast = {
        show: function(message, type = 'info', duration = 5000) {
            const toast = $(`
                <div class="wcefp-toast wcefp-toast-${type}">
                    <div class="wcefp-toast-content">
                        <span class="wcefp-toast-message">${message}</span>
                        <button class="wcefp-toast-close" type="button">&times;</button>
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            
            // Auto-remove after duration
            setTimeout(() => {
                toast.fadeOut(() => toast.remove());
            }, duration);
            
            // Manual close
            toast.find('.wcefp-toast-close').on('click', function() {
                toast.fadeOut(() => toast.remove());
            });
        },
        
        success: function(message, duration) {
            this.show(message, 'success', duration);
        },
        
        error: function(message, duration) {
            this.show(message, 'error', duration || 8000);
        },
        
        warning: function(message, duration) {
            this.show(message, 'warning', duration);
        },
        
        info: function(message, duration) {
            this.show(message, 'info', duration);
        }
    };
    
    // Loading indicator system
    const WCEFPLoading = {
        show: function(target = null) {
            if (target) {
                $(target).addClass('wcefp-loading');
            } else {
                $('body').addClass('wcefp-loading');
            }
        },
        
        hide: function(target = null) {
            if (target) {
                $(target).removeClass('wcefp-loading');
            } else {
                $('body').removeClass('wcefp-loading');
            }
        }
    };
    
    // Enhanced AJAX wrapper
    const WCEFPAjax = {
        request: function(action, data = {}, options = {}) {
            const defaults = {
                showLoading: true,
                showToast: true,
                loadingTarget: null
            };
            
            options = $.extend(defaults, options);
            
            if (options.showLoading) {
                WCEFPLoading.show(options.loadingTarget);
            }
            
            const ajaxData = $.extend({
                action: action,
                nonce: window.WCEFPAdmin?.nonce || window.WCEFPClose?.nonce
            }, data);
            
            return $.post(
                window.WCEFPAdmin?.ajaxUrl || window.WCEFPClose?.ajaxUrl || ajaxurl,
                ajaxData
            ).done(function(response) {
                if (options.showToast) {
                    if (response.success) {
                        WCEFPToast.success(response.data?.message || 'Operazione completata con successo');
                    } else {
                        WCEFPToast.error(response.data?.msg || response.data?.message || 'Si è verificato un errore');
                    }
                }
            }).fail(function(xhr, status, error) {
                if (options.showToast) {
                    WCEFPToast.error(`Errore di comunicazione: ${error}`);
                }
                console.error('WCEFP Ajax Error:', {xhr, status, error});
            }).always(function() {
                if (options.showLoading) {
                    WCEFPLoading.hide(options.loadingTarget);
                }
            });
        }
    };
    
    // Form validation helpers
    const WCEFPValidation = {
        validateDate: function(dateString) {
            const date = new Date(dateString);
            return date instanceof Date && !isNaN(date);
        },
        
        validateEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        validateRequired: function(value) {
            return value !== null && value !== undefined && value.toString().trim() !== '';
        },
        
        highlightField: function($field, isValid = true) {
            $field.toggleClass('wcefp-field-error', !isValid)
                  .toggleClass('wcefp-field-valid', isValid);
        },
        
        validateForm: function($form, rules) {
            let isValid = true;
            
            $.each(rules, function(fieldName, rule) {
                const $field = $form.find(`[name="${fieldName}"]`);
                const value = $field.val();
                let fieldValid = true;
                
                if (rule.required && !this.validateRequired(value)) {
                    fieldValid = false;
                }
                
                if (fieldValid && rule.type) {
                    switch(rule.type) {
                        case 'date':
                            fieldValid = this.validateDate(value);
                            break;
                        case 'email':
                            fieldValid = this.validateEmail(value);
                            break;
                    }
                }
                
                this.highlightField($field, fieldValid);
                
                if (!fieldValid) {
                    isValid = false;
                    if (rule.message) {
                        WCEFPToast.error(rule.message);
                    }
                }
            }.bind(this));
            
            return isValid;
        }
    };
    
    // Enhanced Calendar functionality
    if (typeof window.WCEFPAdmin !== 'undefined') {
        const originalLoadCalendar = window.loadCalendar;
        
        window.loadCalendar = function() {
            WCEFPLoading.show('#wcefp-view');
            
            if (originalLoadCalendar) {
                originalLoadCalendar.call(this);
            }
            
            // Override the original AJAX call to use enhanced error handling
            const $view = $('#wcefp-view');
            const cal = $('<div id="wcefp-calendar"></div>').appendTo($view.empty());
            const now = new Date();
            const from = new Date(now.getFullYear(), now.getMonth()-1, 1).toISOString().slice(0,10);
            const to = new Date(now.getFullYear(), now.getMonth()+2, 0).toISOString().slice(0,10);
            const product_id = parseInt($('#wcefp-filter-product').val() || '0', 10);

            WCEFPAjax.request('wcefp_get_calendar', {
                from: from,
                to: to,
                product_id: product_id
            }, {
                showLoading: false,
                showToast: false,
                loadingTarget: '#wcefp-view'
            }).done(function(response) {
                const events = (response && response.success) ? response.data.events : [];
                
                if (typeof FullCalendar === 'undefined' || !FullCalendar.Calendar) {
                    cal.html('<p>Calendario non disponibile su questa pagina.</p>');
                    return;
                }
                
                const calendar = new FullCalendar.Calendar(cal[0], {
                    initialView: 'dayGridMonth',
                    height: 650,
                    events: events,
                    headerToolbar: { 
                        left:'prev,next today', 
                        center:'title', 
                        right:'dayGridMonth,timeGridWeek,listWeek' 
                    },
                    eventClick: function(info) {
                        handleEventClick(info);
                    }
                });
                
                calendar.render();
            }).always(function() {
                WCEFPLoading.hide('#wcefp-view');
            });
        };
        
        function handleEventClick(info) {
            const e = info.event;
            const occ = e.id;
            const ep = e.extendedProps || {};
            const currentCap = parseInt(ep.capacity || 0, 10);
            const currentStatus = ep.status || 'active';

            const newCapStr = prompt('Nuova capienza per questo slot:', currentCap);
            if (newCapStr === null) return;
            
            const newCap = parseInt(newCapStr, 10);
            if (Number.isNaN(newCap) || newCap < 0) { 
                WCEFPToast.error('Valore di capienza non valido'); 
                return; 
            }

            const toggle = confirm('Vuoi alternare lo stato (attivo/disattivato)?\nOK = alterna, Annulla = lascia invariato');
            const nextStatus = toggle ? (currentStatus === 'active' ? 'cancelled' : 'active') : currentStatus;

            WCEFPAjax.request('wcefp_update_occurrence', {
                occ: occ,
                capacity: newCap,
                status: nextStatus
            }).done(function(response) {
                if (response && response.success) {
                    // Reload calendar to reflect changes
                    window.loadCalendar();
                }
            });
        }
    }
    
    // Export WCEFPAdmin enhancements globally
    window.WCEFPAdmin = window.WCEFPAdmin || {};
    $.extend(window.WCEFPAdmin, {
        Toast: WCEFPToast,
        Loading: WCEFPLoading,
        Ajax: WCEFPAjax,
        Validation: WCEFPValidation
    });
    
    $(document).ready(function() {
        // Initialize enhanced UX features
        
        // Enhanced form submissions
        $(document).on('submit', '.wcefp-form', function(e) {
            const $form = $(this);
            const rules = $form.data('validation-rules');
            
            if (rules && !WCEFPValidation.validateForm($form, rules)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-clear field validation on input
        $(document).on('input change', '.wcefp-field-error, .wcefp-field-valid', function() {
            $(this).removeClass('wcefp-field-error wcefp-field-valid');
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save (prevent browser save dialog)
            if ((e.ctrlKey || e.metaKey) && e.which === 83) {
                e.preventDefault();
                const $saveBtn = $('.wcefp-save-button:visible').first();
                if ($saveBtn.length) {
                    $saveBtn.click();
                    WCEFPToast.info('Tentativo di salvataggio...');
                }
            }
        });
    });
    
})(jQuery);