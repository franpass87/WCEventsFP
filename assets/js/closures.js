/**
 * Closures Management JavaScript
 * 
 * @package WCEventsFP
 * @since 2.1.4
 */

(function($) {
    'use strict';

    /**
     * Closures manager object
     */
    const WCEFPClosures = {
        
        /**
         * Initialize closures functionality
         */
        init: function() {
            this.populateProductSelect();
            this.loadClosuresList();
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#wcefp-add-closure').on('click', this.addClosure.bind(this));
            $(document).on('click', '.wcefp-delete-closure', this.deleteClosure.bind(this));
        },

        /**
         * Populate the product select dropdown
         */
        populateProductSelect: function() {
            if (typeof WCEFPClose !== 'undefined' && WCEFPClose.products) {
                const $select = $('#wcefp-close-product');
                $.each(WCEFPClose.products, function(index, product) {
                    $select.append(`<option value="${product.id}">${product.title}</option>`);
                });
            }
        },

        /**
         * Add a new closure
         */
        addClosure: function() {
            const data = {
                action: 'wcefp_add_closure',
                nonce: WCEFPClose.nonce,
                product_id: $('#wcefp-close-product').val(),
                from: $('#wcefp-close-from').val(),
                to: $('#wcefp-close-to').val(),
                note: $('#wcefp-close-note').val()
            };

            // Basic validation
            if (!data.from || !data.to) {
                alert('Please select both start and end dates.');
                return;
            }

            if (data.from > data.to) {
                alert('Start date must be before or equal to end date.');
                return;
            }

            $.post(WCEFPClose.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.clearForm();
                        this.loadClosuresList();
                        this.showMessage('Closure added successfully.', 'success');
                    } else {
                        this.showMessage(response.data.msg || 'Error adding closure.', 'error');
                    }
                })
                .fail(() => {
                    this.showMessage('Network error. Please try again.', 'error');
                });
        },

        /**
         * Delete a closure
         */
        deleteClosure: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this closure?')) {
                return;
            }

            const closureId = $(e.target).data('id');
            const data = {
                action: 'wcefp_delete_closure',
                nonce: WCEFPClose.nonce,
                id: closureId
            };

            $.post(WCEFPClose.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.loadClosuresList();
                        this.showMessage('Closure deleted successfully.', 'success');
                    } else {
                        this.showMessage(response.data.msg || 'Error deleting closure.', 'error');
                    }
                })
                .fail(() => {
                    this.showMessage('Network error. Please try again.', 'error');
                });
        },

        /**
         * Load closures list
         */
        loadClosuresList: function() {
            const data = {
                action: 'wcefp_list_closures',
                nonce: WCEFPClose.nonce
            };

            $('#wcefp-closures-list').html('<p>Loading...</p>');

            $.post(WCEFPClose.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.renderClosuresList(response.data.rows);
                    } else {
                        $('#wcefp-closures-list').html('<p>Error loading closures list.</p>');
                    }
                })
                .fail(() => {
                    $('#wcefp-closures-list').html('<p>Network error loading closures.</p>');
                });
        },

        /**
         * Render closures list HTML
         */
        renderClosuresList: function(closures) {
            if (!closures || closures.length === 0) {
                $('#wcefp-closures-list').html('<p>No closures found.</p>');
                return;
            }

            let html = '<div class="wcefp-closures-table">';
            html += '<table class="widefat striped">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Product</th>';
            html += '<th>From</th>';
            html += '<th>To</th>';
            html += '<th>Note</th>';
            html += '<th>Created</th>';
            html += '<th>Actions</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            $.each(closures, function(index, closure) {
                html += '<tr>';
                html += `<td><strong>${closure.product}</strong></td>`;
                html += `<td>${closure.from}</td>`;
                html += `<td>${closure.to}</td>`;
                html += `<td>${closure.note || '—'}</td>`;
                html += `<td>${closure.created_at}</td>`;
                html += `<td><button type="button" class="button wcefp-delete-closure" data-id="${closure.id}">Delete</button></td>`;
                html += '</tr>';
            });

            html += '</tbody>';
            html += '</table>';
            html += '</div>';

            $('#wcefp-closures-list').html(html);
        },

        /**
         * Clear the form after successful submission
         */
        clearForm: function() {
            $('#wcefp-close-product').val('0');
            $('#wcefp-close-from').val('');
            $('#wcefp-close-to').val('');
            $('#wcefp-close-note').val('');
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            const className = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $(`<div class="notice ${className} is-dismissible"><p>${message}</p></div>`);
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                $notice.fadeOut();
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on closures page
        if ($('#wcefp-closures-list').length) {
            WCEFPClosures.init();
        }
    });

})(jQuery);