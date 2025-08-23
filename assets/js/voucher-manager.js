/**
 * Enhanced Voucher Management JavaScript
 * 
 * Phase 2: Communication & Automation - Modern voucher management
 * with WordPress-native modals and enhanced user experience
 *
 * @package WCEFP
 * @since 2.1.2
 */

(function($) {
    'use strict';
    
    // Voucher Manager Object
    window.WCEFPVoucherManager = {
        
        /**
         * Initialize voucher management functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeTable();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Voucher action buttons
            $(document).on('click', '.wcefp-voucher-action', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const voucherCode = $(this).data('voucher-code');
                self.handleVoucherAction(action, voucherCode, $(this));
            });
            
            // Voucher details modal
            $(document).on('click', '.wcefp-voucher-details', function(e) {
                e.preventDefault();
                const voucherCode = $(this).data('voucher-code');
                self.showVoucherDetails(voucherCode);
            });
            
            // Analytics modal
            $(document).on('click', '.wcefp-show-analytics', function(e) {
                e.preventDefault();
                self.showVoucherAnalytics();
            });
            
            // Enhanced table search
            $(document).on('input', '#voucher-search', function() {
                self.filterTable($(this).val());
            });
            
            // Status filter
            $(document).on('change', '#voucher-status-filter', function() {
                self.filterTableByStatus($(this).val());
            });
        },
        
        /**
         * Initialize enhanced table functionality
         */
        initializeTable: function() {
            // Add search and filter controls if they don't exist
            if ($('.voucher-table-controls').length === 0) {
                const controls = this.createTableControls();
                $('.wp-list-table').before(controls);
            }
            
            // Enhance table rows with actions
            this.enhanceTableRows();
            
            // Add analytics button
            this.addAnalyticsButton();
        },
        
        /**
         * Create table control elements
         */
        createTableControls: function() {
            return $(`
                <div class="voucher-table-controls" style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div>
                            <label for="voucher-search" style="margin-right: 8px;">
                                ${wcefpVoucherManager.strings.search || 'Cerca'}:
                            </label>
                            <input type="text" 
                                   id="voucher-search" 
                                   placeholder="${wcefpVoucherManager.strings.search_placeholder || 'Codice, email, destinatario...'}"
                                   style="width: 200px;">
                        </div>
                        <div>
                            <label for="voucher-status-filter" style="margin-right: 8px;">
                                ${wcefpVoucherManager.strings.filter_by_status || 'Filtra per stato'}:
                            </label>
                            <select id="voucher-status-filter">
                                <option value="">${wcefpVoucherManager.strings.all_statuses || 'Tutti gli stati'}</option>
                                <option value="active">${wcefpVoucherManager.strings.status_active || 'Attivo'}</option>
                                <option value="redeemed">${wcefpVoucherManager.strings.status_redeemed || 'Utilizzato'}</option>
                                <option value="expired">${wcefpVoucherManager.strings.status_expired || 'Scaduto'}</option>
                                <option value="cancelled">${wcefpVoucherManager.strings.status_cancelled || 'Annullato'}</option>
                            </select>
                        </div>
                        <button class="button wcefp-show-analytics">
                            📊 ${wcefpVoucherManager.strings.show_analytics || 'Mostra Statistiche'}
                        </button>
                    </div>
                </div>
            `);
        },
        
        /**
         * Enhance table rows with quick action buttons
         */
        enhanceTableRows: function() {
            const self = this;
            
            $('.wp-list-table tbody tr').each(function() {
                const $row = $(this);
                const voucherCode = $row.find('.voucher-code').text().trim();
                const status = $row.find('.voucher-status').data('status') || 
                              $row.find('.voucher-status').text().toLowerCase().trim();
                
                if (!voucherCode) return;
                
                // Add quick actions if they don't exist
                let $actions = $row.find('.voucher-quick-actions');
                if ($actions.length === 0) {
                    $actions = $('<div class="voucher-quick-actions" style="margin-top: 5px;"></div>');
                    $row.find('td:first').append($actions);
                }
                
                // Clear existing actions
                $actions.empty();
                
                // Add action buttons based on status
                const buttons = [];
                
                // Details button (always available)
                buttons.push(`
                    <button class="button button-small wcefp-voucher-details" 
                            data-voucher-code="${voucherCode}"
                            title="${wcefpVoucherManager.strings.view_details || 'Visualizza dettagli'}">
                        👁️
                    </button>
                `);
                
                // Status-specific actions
                if (status === 'active') {
                    buttons.push(`
                        <button class="button button-small wcefp-voucher-action" 
                                data-action="resend_email" 
                                data-voucher-code="${voucherCode}"
                                title="${wcefpVoucherManager.strings.resend_email || 'Reinvia email'}">
                            📧
                        </button>
                    `);
                    
                    buttons.push(`
                        <button class="button button-small button-link-delete wcefp-voucher-action" 
                                data-action="cancel_voucher" 
                                data-voucher-code="${voucherCode}"
                                title="${wcefpVoucherManager.strings.cancel_voucher || 'Annulla voucher'}">
                            ❌
                        </button>
                    `);
                }
                
                if (status === 'redeemed' || status === 'cancelled' || status === 'expired') {
                    buttons.push(`
                        <button class="button button-small wcefp-voucher-action" 
                                data-action="resend_email" 
                                data-voucher-code="${voucherCode}"
                                title="${wcefpVoucherManager.strings.resend_email || 'Reinvia email'}">
                            📧
                        </button>
                    `);
                }
                
                $actions.html(buttons.join(' '));
            });
        },
        
        /**
         * Add analytics button to page header
         */
        addAnalyticsButton: function() {
            if ($('.wcefp-analytics-button').length > 0) return;
            
            const $analyticsBtn = $(`
                <button class="button button-secondary wcefp-show-analytics wcefp-analytics-button" 
                        style="margin-left: 10px;">
                    📊 ${wcefpVoucherManager.strings.show_analytics || 'Statistiche Voucher'}
                </button>
            `);
            
            $('.page-title-action').after($analyticsBtn);
        },
        
        /**
         * Handle voucher actions
         */
        handleVoucherAction: function(action, voucherCode, $button) {
            if (!action || !voucherCode) return;
            
            const self = this;
            let confirmMessage = '';
            
            // Get confirmation message based on action
            switch (action) {
                case 'cancel_voucher':
                    confirmMessage = wcefpVoucherManager.strings.confirm_cancel;
                    break;
                case 'resend_email':
                    confirmMessage = wcefpVoucherManager.strings.confirm_resend;
                    break;
                default:
                    confirmMessage = wcefpVoucherManager.strings.confirm_action || 'Confermi questa azione?';
            }
            
            // Show confirmation modal
            WCEFPModals.showConfirm(
                confirmMessage,
                function() {
                    // User confirmed, proceed with action
                    self.performVoucherAction(action, voucherCode, $button);
                }
            );
        },
        
        /**
         * Perform voucher action via AJAX
         */
        performVoucherAction: function(action, voucherCode, $button) {
            const self = this;
            
            // Show loading state
            const originalText = $button.html();
            $button.html('⏳').prop('disabled', true);
            
            $.ajax({
                url: wcefpVoucherManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcefp_voucher_action',
                    action_type: action,
                    voucher_code: voucherCode,
                    nonce: wcefpVoucherManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WCEFPModals.showSuccess(response.data.message);
                        
                        // Refresh table or update row
                        if (action === 'cancel_voucher') {
                            self.updateVoucherRowStatus(voucherCode, 'cancelled');
                        }
                        
                        // Re-enhance table rows after status change
                        setTimeout(function() {
                            self.enhanceTableRows();
                        }, 100);
                    } else {
                        WCEFPModals.showError(response.data.message || wcefpVoucherManager.strings.error_occurred);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Voucher action error:', error);
                    WCEFPModals.showError(wcefpVoucherManager.strings.error_occurred);
                },
                complete: function() {
                    // Restore button state
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Show voucher details modal
         */
        showVoucherDetails: function(voucherCode) {
            const $modal = $('#wcefp-voucher-details-modal');
            const $content = $('#wcefp-voucher-details-content');
            
            if ($modal.length === 0) {
                console.error('Voucher details modal not found');
                return;
            }
            
            // Show loading state
            $content.html(`
                <div class="wcefp-loading" style="text-align: center; padding: 40px;">
                    <div class="spinner is-active" style="float: none;"></div>
                    <p>${wcefpVoucherManager.strings.loading}</p>
                </div>
            `);
            
            // Show modal
            WCEFPModals.showModal($modal);
            
            // Load voucher details
            $.ajax({
                url: wcefpVoucherManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wcefp_voucher_action',
                    action_type: 'get_voucher_details',
                    voucher_code: voucherCode,
                    nonce: wcefpVoucherManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(self.formatVoucherDetails(response.data));
                    } else {
                        $content.html(`
                            <div class="error">
                                <p>${response.data.message || wcefpVoucherManager.strings.error_occurred}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load voucher details:', error);
                    $content.html(`
                        <div class="error">
                            <p>${wcefpVoucherManager.strings.error_occurred}</p>
                        </div>
                    `);
                }
            });
        },
        
        /**
         * Format voucher details for display
         */
        formatVoucherDetails: function(data) {
            const voucher = data.voucher;
            let html = `
                <div class="voucher-details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="voucher-basic-info">
                        <h3>${wcefpVoucherManager.strings.basic_info || 'Informazioni Base'}</h3>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.voucher_code || 'Codice'}:</strong></td>
                                    <td><code>${voucher.code}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.amount || 'Importo'}:</strong></td>
                                    <td>${data.formatted_amount}</td>
                                </tr>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.status || 'Stato'}:</strong></td>
                                    <td><span class="voucher-status status-${voucher.status}">${data.status_label}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.created || 'Creato'}:</strong></td>
                                    <td>${this.formatDate(voucher.created_date)}</td>
                                </tr>
            `;
            
            if (voucher.expiry_date) {
                html += `
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.expires || 'Scadenza'}:</strong></td>
                                    <td>${this.formatDate(voucher.expiry_date)}</td>
                                </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="voucher-recipient-info">
                        <h3>${wcefpVoucherManager.strings.recipient_info || 'Informazioni Destinatario'}</h3>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.recipient_name || 'Nome'}:</strong></td>
                                    <td>${voucher.recipient_name || '-'}</td>
                                </tr>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.recipient_email || 'Email'}:</strong></td>
                                    <td>${voucher.recipient_email || '-'}</td>
                                </tr>
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.sender_name || 'Mittente'}:</strong></td>
                                    <td>${voucher.sender_name || '-'}</td>
                                </tr>
            `;
            
            if (voucher.message) {
                html += `
                                <tr>
                                    <td><strong>${wcefpVoucherManager.strings.message || 'Messaggio'}:</strong></td>
                                    <td><em>"${voucher.message}"</em></td>
                                </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            // Add usage history if redeemed
            if (data.usage_history && data.usage_history.length > 0) {
                html += `
                    <div class="voucher-usage-history" style="margin-top: 20px;">
                        <h3>${wcefpVoucherManager.strings.usage_history || 'Cronologia Utilizzo'}</h3>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>${wcefpVoucherManager.strings.date || 'Data'}</th>
                                    <th>${wcefpVoucherManager.strings.order || 'Ordine'}</th>
                                    <th>${wcefpVoucherManager.strings.customer || 'Cliente'}</th>
                                    <th>${wcefpVoucherManager.strings.amount_used || 'Importo Utilizzato'}</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.usage_history.forEach(usage => {
                    html += `
                                <tr>
                                    <td>${this.formatDate(usage.used_date)}</td>
                                    <td>#${usage.order_id}</td>
                                    <td>${usage.customer_email}</td>
                                    <td>${usage.amount_used}</td>
                                </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // Add action buttons
            html += '<div class="voucher-details-actions" style="margin-top: 20px; text-align: right;">';
            
            if (data.can_resend) {
                html += `
                    <button class="button wcefp-voucher-action" 
                            data-action="resend_email" 
                            data-voucher-code="${voucher.code}">
                        📧 ${wcefpVoucherManager.strings.resend_email || 'Reinvia Email'}
                    </button>
                `;
            }
            
            if (data.can_cancel) {
                html += `
                    <button class="button button-link-delete wcefp-voucher-action" 
                            data-action="cancel_voucher" 
                            data-voucher-code="${voucher.code}"
                            style="margin-left: 10px;">
                        ❌ ${wcefpVoucherManager.strings.cancel_voucher || 'Annulla Voucher'}
                    </button>
                `;
            }
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Show voucher analytics modal
         */
        showVoucherAnalytics: function() {
            const $modal = $('#wcefp-voucher-analytics-modal');
            const $content = $('#wcefp-voucher-analytics-content');
            
            if ($modal.length === 0) {
                console.error('Voucher analytics modal not found');
                return;
            }
            
            // Show loading state
            $content.html(`
                <div class="wcefp-loading" style="text-align: center; padding: 40px;">
                    <div class="spinner is-active" style="float: none;"></div>
                    <p>${wcefpVoucherManager.strings.loading}</p>
                </div>
            `);
            
            // Show modal
            WCEFPModals.showModal($modal);
            
            // Load analytics data
            $.ajax({
                url: wcefpVoucherManager.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'wcefp_get_voucher_analytics',
                    nonce: wcefpVoucherManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(self.formatAnalytics(response.data));
                    } else {
                        $content.html(`
                            <div class="error">
                                <p>${wcefpVoucherManager.strings.error_occurred}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load analytics:', error);
                    $content.html(`
                        <div class="error">
                            <p>${wcefpVoucherManager.strings.error_occurred}</p>
                        </div>
                    `);
                }
            });
        },
        
        /**
         * Format analytics data for display
         */
        formatAnalytics: function(data) {
            let html = `
                <div class="analytics-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="analytics-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0 0 10px 0; color: #23282d;">${data.total_vouchers}</h3>
                        <p style="margin: 0; color: #666;">${wcefpVoucherManager.strings.total_vouchers || 'Totale Voucher'}</p>
                    </div>
                    <div class="analytics-card" style="background: #e8f5e8; padding: 20px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0 0 10px 0; color: #228b22;">${data.active_vouchers}</h3>
                        <p style="margin: 0; color: #666;">${wcefpVoucherManager.strings.active_vouchers || 'Voucher Attivi'}</p>
                    </div>
                    <div class="analytics-card" style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0 0 10px 0; color: #856404;">${data.expired_vouchers}</h3>
                        <p style="margin: 0; color: #666;">${wcefpVoucherManager.strings.expired_vouchers || 'Voucher Scaduti'}</p>
                    </div>
                    <div class="analytics-card" style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0 0 10px 0; color: #155724;">${data.redemption_rate}%</h3>
                        <p style="margin: 0; color: #666;">${wcefpVoucherManager.strings.redemption_rate || 'Tasso di Utilizzo'}</p>
                    </div>
                </div>
                
                <div class="analytics-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="status-breakdown">
                        <h3>${wcefpVoucherManager.strings.status_breakdown || 'Distribuzione per Stato'}</h3>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>${wcefpVoucherManager.strings.status || 'Stato'}</th>
                                    <th>${wcefpVoucherManager.strings.count || 'Numero'}</th>
                                    <th>${wcefpVoucherManager.strings.total_value || 'Valore Totale'}</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.status_breakdown && data.status_breakdown.length > 0) {
                data.status_breakdown.forEach(stat => {
                    html += `
                                <tr>
                                    <td><span class="voucher-status status-${stat.status}">${this.getStatusLabel(stat.status)}</span></td>
                                    <td>${stat.count}</td>
                                    <td>${this.formatCurrency(stat.total_value)}</td>
                                </tr>
                    `;
                });
            } else {
                html += `
                                <tr>
                                    <td colspan="3">${wcefpVoucherManager.strings.no_data || 'Nessun dato disponibile'}</td>
                                </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="monthly-stats">
                        <h3>${wcefpVoucherManager.strings.monthly_stats || 'Statistiche Mensili'}</h3>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>${wcefpVoucherManager.strings.month || 'Mese'}</th>
                                    <th>${wcefpVoucherManager.strings.created || 'Creati'}</th>
                                    <th>${wcefpVoucherManager.strings.value || 'Valore'}</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (data.monthly_stats && data.monthly_stats.length > 0) {
                data.monthly_stats.forEach(stat => {
                    html += `
                                <tr>
                                    <td>${this.formatMonth(stat.month)}</td>
                                    <td>${stat.count}</td>
                                    <td>${this.formatCurrency(stat.total_value)}</td>
                                </tr>
                    `;
                });
            } else {
                html += `
                                <tr>
                                    <td colspan="3">${wcefpVoucherManager.strings.no_data || 'Nessun dato disponibile'}</td>
                                </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            return html;
        },
        
        /**
         * Filter table by search term
         */
        filterTable: function(searchTerm) {
            const $rows = $('.wp-list-table tbody tr');
            
            if (!searchTerm) {
                $rows.show();
                return;
            }
            
            const term = searchTerm.toLowerCase();
            
            $rows.each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                
                if (text.includes(term)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },
        
        /**
         * Filter table by status
         */
        filterTableByStatus: function(status) {
            const $rows = $('.wp-list-table tbody tr');
            
            if (!status) {
                $rows.show();
                return;
            }
            
            $rows.each(function() {
                const $row = $(this);
                const rowStatus = $row.find('.voucher-status').data('status') || 
                                 $row.find('.voucher-status').text().toLowerCase().trim();
                
                if (rowStatus === status) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },
        
        /**
         * Update voucher row status
         */
        updateVoucherRowStatus: function(voucherCode, newStatus) {
            $('.wp-list-table tbody tr').each(function() {
                const $row = $(this);
                const rowVoucherCode = $row.find('.voucher-code').text().trim();
                
                if (rowVoucherCode === voucherCode) {
                    const $statusCell = $row.find('.voucher-status');
                    $statusCell
                        .removeClass('status-active status-redeemed status-expired status-cancelled')
                        .addClass('status-' + newStatus)
                        .text(this.getStatusLabel(newStatus))
                        .data('status', newStatus);
                    return false; // Break loop
                }
            });
        },
        
        /**
         * Utility functions
         */
        formatDate: function(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatMonth: function(monthString) {
            if (!monthString) return '-';
            const [year, month] = monthString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('it-IT', {
                year: 'numeric',
                month: 'long'
            });
        },
        
        formatCurrency: function(amount) {
            if (!amount) return '€0,00';
            return new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        },
        
        getStatusLabel: function(status) {
            const labels = {
                'active': wcefpVoucherManager.strings.status_active || 'Attivo',
                'redeemed': wcefpVoucherManager.strings.status_redeemed || 'Utilizzato',
                'expired': wcefpVoucherManager.strings.status_expired || 'Scaduto',
                'cancelled': wcefpVoucherManager.strings.status_cancelled || 'Annullato'
            };
            
            return labels[status] || status;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof wcefpVoucherManager !== 'undefined') {
            WCEFPVoucherManager.init();
        }
    });
    
})(jQuery);