<?php
/**
 * Mobile Check-in Interface Template
 * 
 * Template for the mobile check-in shortcode interface
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage Templates
 * @since 2.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$theme_class = 'wcefp-checkin-theme-' . esc_attr($atts['theme']);
$show_location = $atts['show_location'] === 'yes';
$show_notes = $atts['show_notes'] === 'yes';
?>

<div id="wcefp-mobile-checkin" class="wcefp-mobile-checkin <?php echo $theme_class; ?>">
    
    <!-- Header -->
    <div class="wcefp-checkin-header">
        <h2><?php _e('Event Check-in', 'wceventsfp'); ?></h2>
        <p class="wcefp-checkin-subtitle">
            <?php _e('Scan QR code or enter booking details to check in', 'wceventsfp'); ?>
        </p>
    </div>

    <!-- Check-in Methods Tabs -->
    <div class="wcefp-checkin-tabs">
        <button class="wcefp-tab-button active" data-tab="qr-scan">
            <span class="dashicons dashicons-camera"></span>
            <?php _e('Scan QR Code', 'wceventsfp'); ?>
        </button>
        <button class="wcefp-tab-button" data-tab="manual-entry">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('Manual Entry', 'wceventsfp'); ?>
        </button>
    </div>

    <!-- QR Code Scanner Tab -->
    <div class="wcefp-tab-content active" id="qr-scan-tab">
        <div class="wcefp-qr-scanner-container">
            <div id="wcefp-qr-scanner">
                <div class="wcefp-scanner-viewfinder">
                    <div class="wcefp-scanner-overlay">
                        <div class="wcefp-scanner-frame">
                            <div class="wcefp-scanner-corner top-left"></div>
                            <div class="wcefp-scanner-corner top-right"></div>
                            <div class="wcefp-scanner-corner bottom-left"></div>
                            <div class="wcefp-scanner-corner bottom-right"></div>
                        </div>
                        <p class="wcefp-scanner-instruction">
                            <?php _e('Position QR code within the frame', 'wceventsfp'); ?>
                        </p>
                    </div>
                    <video id="wcefp-scanner-video" autoplay playsinline></video>
                    <canvas id="wcefp-scanner-canvas" style="display: none;"></canvas>
                </div>
                
                <div class="wcefp-scanner-controls">
                    <button type="button" id="wcefp-start-scanner" class="wcefp-btn wcefp-btn-primary">
                        <span class="dashicons dashicons-camera"></span>
                        <?php _e('Start Scanner', 'wceventsfp'); ?>
                    </button>
                    <button type="button" id="wcefp-stop-scanner" class="wcefp-btn wcefp-btn-secondary" style="display: none;">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Stop Scanner', 'wceventsfp'); ?>
                    </button>
                </div>
                
                <div class="wcefp-scanner-status">
                    <p id="wcefp-scanner-message" class="wcefp-scanner-message"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Entry Tab -->
    <div class="wcefp-tab-content" id="manual-entry-tab">
        <form id="wcefp-manual-checkin-form" class="wcefp-checkin-form">
            <div class="wcefp-form-group">
                <label for="wcefp-token">
                    <?php _e('Check-in Token', 'wceventsfp'); ?> <span class="required">*</span>
                </label>
                <input type="text" id="wcefp-token" name="token" class="wcefp-form-control" 
                       placeholder="<?php esc_attr_e('Enter check-in token', 'wceventsfp'); ?>" required>
                <small class="wcefp-form-help">
                    <?php _e('Token can be found in your booking confirmation email', 'wceventsfp'); ?>
                </small>
            </div>
            
            <div class="wcefp-form-group">
                <label for="wcefp-booking-id">
                    <?php _e('Booking ID', 'wceventsfp'); ?> <span class="required">*</span>
                </label>
                <input type="number" id="wcefp-booking-id" name="booking_id" class="wcefp-form-control" 
                       placeholder="<?php esc_attr_e('Enter booking ID', 'wceventsfp'); ?>" required>
            </div>
            
            <?php if ($show_location): ?>
            <div class="wcefp-form-group">
                <label for="wcefp-location">
                    <?php _e('Location', 'wceventsfp'); ?>
                </label>
                <input type="text" id="wcefp-location" name="location" class="wcefp-form-control" 
                       placeholder="<?php esc_attr_e('Current location (optional)', 'wceventsfp'); ?>">
            </div>
            <?php endif; ?>
            
            <?php if ($show_notes): ?>
            <div class="wcefp-form-group">
                <label for="wcefp-notes">
                    <?php _e('Notes', 'wceventsfp'); ?>
                </label>
                <textarea id="wcefp-notes" name="notes" class="wcefp-form-control" rows="3" 
                          placeholder="<?php esc_attr_e('Additional notes (optional)', 'wceventsfp'); ?>"></textarea>
            </div>
            <?php endif; ?>
            
            <div class="wcefp-form-actions">
                <button type="submit" class="wcefp-btn wcefp-btn-primary wcefp-btn-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Check In', 'wceventsfp'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Check-in Status Display -->
    <div id="wcefp-checkin-status" class="wcefp-checkin-status" style="display: none;">
        <div class="wcefp-status-content">
            <div class="wcefp-status-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="wcefp-status-message">
                <h3><?php _e('Check-in Successful!', 'wceventsfp'); ?></h3>
                <p id="wcefp-status-details"></p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="wcefp-checkin-loading" class="wcefp-loading-overlay" style="display: none;">
        <div class="wcefp-loading-spinner">
            <div class="wcefp-spinner"></div>
            <p><?php _e('Processing check-in...', 'wceventsfp'); ?></p>
        </div>
    </div>

    <!-- Error Display -->
    <div id="wcefp-checkin-error" class="wcefp-error-message" style="display: none;">
        <div class="wcefp-error-content">
            <span class="dashicons dashicons-warning"></span>
            <div class="wcefp-error-text">
                <h4><?php _e('Check-in Failed', 'wceventsfp'); ?></h4>
                <p id="wcefp-error-details"></p>
            </div>
            <button type="button" id="wcefp-retry-checkin" class="wcefp-btn wcefp-btn-secondary">
                <?php _e('Try Again', 'wceventsfp'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Quick Check-in Modal (for scanned QR codes) -->
<div id="wcefp-quick-checkin-modal" class="wcefp-modal" style="display: none;">
    <div class="wcefp-modal-overlay"></div>
    <div class="wcefp-modal-content">
        <div class="wcefp-modal-header">
            <h3><?php _e('Confirm Check-in', 'wceventsfp'); ?></h3>
            <button type="button" class="wcefp-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="wcefp-modal-body">
            <div class="wcefp-booking-details">
                <h4><?php _e('Booking Details', 'wceventsfp'); ?></h4>
                <div class="wcefp-detail-row">
                    <label><?php _e('Event:', 'wceventsfp'); ?></label>
                    <span id="wcefp-modal-event-title"></span>
                </div>
                <div class="wcefp-detail-row">
                    <label><?php _e('Customer:', 'wceventsfp'); ?></label>
                    <span id="wcefp-modal-customer-name"></span>
                </div>
                <div class="wcefp-detail-row">
                    <label><?php _e('Booking Date:', 'wceventsfp'); ?></label>
                    <span id="wcefp-modal-booking-date"></span>
                </div>
            </div>
            
            <?php if ($show_location): ?>
            <div class="wcefp-form-group">
                <label for="wcefp-modal-location"><?php _e('Location', 'wceventsfp'); ?></label>
                <input type="text" id="wcefp-modal-location" class="wcefp-form-control" 
                       placeholder="<?php esc_attr_e('Current location (optional)', 'wceventsfp'); ?>">
            </div>
            <?php endif; ?>
            
            <?php if ($show_notes): ?>
            <div class="wcefp-form-group">
                <label for="wcefp-modal-notes"><?php _e('Notes', 'wceventsfp'); ?></label>
                <textarea id="wcefp-modal-notes" class="wcefp-form-control" rows="2" 
                          placeholder="<?php esc_attr_e('Additional notes (optional)', 'wceventsfp'); ?>"></textarea>
            </div>
            <?php endif; ?>
        </div>
        <div class="wcefp-modal-footer">
            <button type="button" class="wcefp-btn wcefp-btn-secondary wcefp-modal-close">
                <?php _e('Cancel', 'wceventsfp'); ?>
            </button>
            <button type="button" id="wcefp-confirm-checkin" class="wcefp-btn wcefp-btn-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Confirm Check-in', 'wceventsfp'); ?>
            </button>
        </div>
    </div>
</div>