/**
 * WCEventsFP Feature Manager JavaScript
 */
(function($) {
    'use strict';

    // Feature Manager Object
    const WCEFPFeatureManager = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('change', '.wcefp-feature-toggle input', this.toggleFeature);
            $(document).on('click', '.wcefp-reset-installation', this.resetInstallation);
        },
        
        toggleFeature: function(e) {
            const $checkbox = $(this);
            const feature = $checkbox.data('feature');
            const enabled = $checkbox.is(':checked');
            const $card = $checkbox.closest('.wcefp-feature-card');
            
            // Show loading
            $card.addClass('wcefp-loading-state');
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcefp_toggle_feature',
                    feature: feature,
                    enabled: enabled ? 1 : 0,
                    nonce: wcefp_feature_manager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.toggleClass('enabled', enabled);
                        $card.toggleClass('disabled', !enabled);
                    } else {
                        // Revert checkbox
                        $checkbox.prop('checked', !enabled);
                        alert(response.data || wcefp_feature_manager.strings.error);
                    }
                },
                error: function() {
                    // Revert checkbox
                    $checkbox.prop('checked', !enabled);
                    alert(wcefp_feature_manager.strings.error);
                },
                complete: function() {
                    $card.removeClass('wcefp-loading-state');
                }
            });
        },
        
        resetInstallation: function(e) {
            e.preventDefault();
            
            if (!confirm(wcefp_feature_manager.strings.confirm_reset)) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcefp_reset_installation',
                    nonce: wcefp_feature_manager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || wcefp_feature_manager.strings.error);
                    }
                },
                error: function() {
                    alert(wcefp_feature_manager.strings.error);
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WCEFPFeatureManager.init();
    });
    
})(jQuery);