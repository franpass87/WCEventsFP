/**
 * WCEFP Modal System - WordPress-native modals to replace browser alerts
 * 
 * @package WCEFP
 * @since 2.2.0
 */

(function($) {
    'use strict';

    // Global modal system object
    window.WCEFPModals = {
        
        /**
         * Show success notice
         * @param {string} message - Success message
         * @param {boolean} dismissible - Whether notice can be dismissed
         */
        showSuccess: function(message, dismissible = true) {
            this.showNotice(message, 'success', dismissible);
        },

        /**
         * Show error notice
         * @param {string} message - Error message
         * @param {boolean} dismissible - Whether notice can be dismissed
         */
        showError: function(message, dismissible = true) {
            this.showNotice(message, 'error', dismissible);
        },

        /**
         * Show warning notice
         * @param {string} message - Warning message
         * @param {boolean} dismissible - Whether notice can be dismissed
         */
        showWarning: function(message, dismissible = true) {
            this.showNotice(message, 'warning', dismissible);
        },

        /**
         * Show info notice
         * @param {string} message - Info message
         * @param {boolean} dismissible - Whether notice can be dismissed
         */
        showInfo: function(message, dismissible = true) {
            this.showNotice(message, 'info', dismissible);
        },

        /**
         * Show WordPress-style admin notice
         * @param {string} message - Notice message
         * @param {string} type - Notice type (success, error, warning, info)
         * @param {boolean} dismissible - Whether notice can be dismissed
         */
        showNotice: function(message, type = 'info', dismissible = true) {
            const noticeClass = `notice notice-${type}` + (dismissible ? ' is-dismissible' : '');
            const notice = $(`
                <div class="${noticeClass}">
                    <p>${message}</p>
                    ${dismissible ? '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' : ''}
                </div>
            `);

            // Add notice to admin notices area or create one if not exists
            let noticesContainer = $('.wrap .notice').first().parent();
            if (noticesContainer.length === 0) {
                noticesContainer = $('.wrap');
            }
            
            notice.prependTo(noticesContainer);

            // Handle dismiss functionality
            if (dismissible) {
                notice.find('.notice-dismiss').on('click', function() {
                    notice.fadeOut(300, function() {
                        notice.remove();
                    });
                });
            }

            // Auto-dismiss success notices after 5 seconds
            if (type === 'success' && dismissible) {
                setTimeout(() => {
                    if (notice.length) {
                        notice.fadeOut(300, function() {
                            notice.remove();
                        });
                    }
                }, 5000);
            }
        },

        /**
         * Show confirmation dialog (replacement for confirm())
         * @param {string} message - Confirmation message
         * @param {function} onConfirm - Callback for confirmation
         * @param {function} onCancel - Callback for cancellation
         * @param {object} options - Additional options
         */
        showConfirm: function(message, onConfirm, onCancel = null, options = {}) {
            const defaults = {
                title: 'Conferma',
                confirmText: 'OK',
                cancelText: 'Annulla',
                type: 'warning'
            };
            const opts = $.extend(defaults, options);

            const modalId = 'wcefp-confirm-modal-' + Date.now();
            const modal = $(`
                <div id="${modalId}" class="wcefp-modal-overlay" role="dialog" aria-labelledby="${modalId}-title" aria-describedby="${modalId}-desc">
                    <div class="wcefp-modal-dialog" role="document">
                        <div class="wcefp-modal-content">
                            <div class="wcefp-modal-header">
                                <h2 id="${modalId}-title" class="wcefp-modal-title">
                                    <span class="dashicons dashicons-${this.getIconByType(opts.type)}"></span>
                                    ${opts.title}
                                </h2>
                                <button type="button" class="wcefp-modal-close" aria-label="Chiudi">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="wcefp-modal-body">
                                <p id="${modalId}-desc">${message}</p>
                            </div>
                            <div class="wcefp-modal-footer">
                                <button type="button" class="button button-primary wcefp-modal-confirm">${opts.confirmText}</button>
                                <button type="button" class="button wcefp-modal-cancel">${opts.cancelText}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Handle confirm
            modal.find('.wcefp-modal-confirm').on('click', function() {
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
                modal.remove();
            });

            // Handle cancel/close
            modal.find('.wcefp-modal-cancel, .wcefp-modal-close').on('click', function() {
                if (typeof onCancel === 'function') {
                    onCancel();
                }
                modal.remove();
            });

            // Handle overlay click
            modal.on('click', function(e) {
                if (e.target === modal[0]) {
                    if (typeof onCancel === 'function') {
                        onCancel();
                    }
                    modal.remove();
                }
            });

            // Handle escape key
            $(document).on('keydown.wcefp-modal', function(e) {
                if (e.keyCode === 27) { // ESC key
                    if (typeof onCancel === 'function') {
                        onCancel();
                    }
                    modal.remove();
                    $(document).off('keydown.wcefp-modal');
                }
            });

            // Focus management for accessibility
            modal.find('.wcefp-modal-confirm').focus();
        },

        /**
         * Show input prompt dialog (replacement for prompt())
         * @param {string} message - Prompt message
         * @param {string} defaultValue - Default input value
         * @param {function} onConfirm - Callback for confirmation with input value
         * @param {function} onCancel - Callback for cancellation
         * @param {object} options - Additional options
         */
        showPrompt: function(message, defaultValue = '', onConfirm, onCancel = null, options = {}) {
            const defaults = {
                title: 'Inserisci valore',
                confirmText: 'OK',
                cancelText: 'Annulla',
                inputType: 'text',
                placeholder: ''
            };
            const opts = $.extend(defaults, options);

            const modalId = 'wcefp-prompt-modal-' + Date.now();
            const modal = $(`
                <div id="${modalId}" class="wcefp-modal-overlay" role="dialog" aria-labelledby="${modalId}-title" aria-describedby="${modalId}-desc">
                    <div class="wcefp-modal-dialog" role="document">
                        <div class="wcefp-modal-content">
                            <div class="wcefp-modal-header">
                                <h2 id="${modalId}-title" class="wcefp-modal-title">
                                    <span class="dashicons dashicons-edit"></span>
                                    ${opts.title}
                                </h2>
                                <button type="button" class="wcefp-modal-close" aria-label="Chiudi">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="wcefp-modal-body">
                                <p id="${modalId}-desc">${message}</p>
                                <input type="${opts.inputType}" id="${modalId}-input" class="wcefp-modal-input regular-text" 
                                       value="${defaultValue}" placeholder="${opts.placeholder}" />
                            </div>
                            <div class="wcefp-modal-footer">
                                <button type="button" class="button button-primary wcefp-modal-confirm">${opts.confirmText}</button>
                                <button type="button" class="button wcefp-modal-cancel">${opts.cancelText}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            const input = modal.find('.wcefp-modal-input');

            // Handle confirm
            modal.find('.wcefp-modal-confirm').on('click', function() {
                if (typeof onConfirm === 'function') {
                    onConfirm(input.val());
                }
                modal.remove();
            });

            // Handle enter key in input
            input.on('keydown', function(e) {
                if (e.keyCode === 13) { // Enter key
                    if (typeof onConfirm === 'function') {
                        onConfirm(input.val());
                    }
                    modal.remove();
                }
            });

            // Handle cancel/close
            modal.find('.wcefp-modal-cancel, .wcefp-modal-close').on('click', function() {
                if (typeof onCancel === 'function') {
                    onCancel();
                }
                modal.remove();
            });

            // Handle overlay click
            modal.on('click', function(e) {
                if (e.target === modal[0]) {
                    if (typeof onCancel === 'function') {
                        onCancel();
                    }
                    modal.remove();
                }
            });

            // Handle escape key
            $(document).on('keydown.wcefp-modal', function(e) {
                if (e.keyCode === 27) { // ESC key
                    if (typeof onCancel === 'function') {
                        onCancel();
                    }
                    modal.remove();
                    $(document).off('keydown.wcefp-modal');
                }
            });

            // Focus on input for better UX
            input.focus().select();
        },

        /**
         * Get appropriate dashicon by type
         * @param {string} type - Notice/modal type
         * @returns {string} - Dashicon class name
         */
        getIconByType: function(type) {
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info-outline'
            };
            return icons[type] || 'info-outline';
        }
    };

    // Legacy compatibility - replace global functions
    window.wcefpAlert = function(message) {
        WCEFPModals.showInfo(message);
    };

    window.wcefpConfirm = function(message, callback) {
        WCEFPModals.showConfirm(message, callback);
    };

    window.wcefpPrompt = function(message, defaultValue, callback) {
        WCEFPModals.showPrompt(message, defaultValue, callback);
    };

})(jQuery);