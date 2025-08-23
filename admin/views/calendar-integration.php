<?php
/**
 * Calendar Integration Admin View
 * 
 * Administrative interface for managing Google Calendar integration and authenticated feeds
 * 
 * @package WCEFP
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current admin calendar token info
$admin_token = get_option('wcefp_admin_calendar_token');
$token_expiry = get_option('wcefp_admin_calendar_token_expiry');
$has_active_token = !empty($admin_token) && time() < $token_expiry;
$feed_url = $has_active_token ? home_url("/wcefp-admin-calendar/{$admin_token}/") : '';

// Get calendar usage statistics
$usage_data = get_option('wcefp_calendar_usage', []);
$total_usage = array_sum(array_map('array_sum', $usage_data));
$recent_usage = array_slice($usage_data, -7, 7, true); // Last 7 days
?>

<div class="wrap wcefp-calendar-integration">
    <h1><?php esc_html_e('Calendar Integration', 'wceventsfp'); ?></h1>
    
    <div class="wcefp-admin-grid">
        
        <!-- Admin Calendar Feed Section -->
        <div class="wcefp-admin-card">
            <h2><?php esc_html_e('Admin Calendar Feed', 'wceventsfp'); ?></h2>
            <p><?php esc_html_e('Generate a private calendar feed for admins to subscribe to all confirmed bookings.', 'wceventsfp'); ?></p>
            
            <?php if ($has_active_token): ?>
                <div class="wcefp-feed-active">
                    <div class="wcefp-status-indicator wcefp-status-active">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Active Calendar Feed', 'wceventsfp'); ?>
                    </div>
                    
                    <div class="wcefp-feed-details">
                        <label for="wcefp-feed-url"><?php esc_html_e('Calendar Feed URL:', 'wceventsfp'); ?></label>
                        <div class="wcefp-feed-url-container">
                            <input type="text" id="wcefp-feed-url" value="<?php echo esc_url($feed_url); ?>" readonly class="wcefp-feed-url">
                            <button type="button" class="button wcefp-copy-url" data-clipboard-target="#wcefp-feed-url">
                                <span class="dashicons dashicons-clipboard"></span>
                                <?php esc_html_e('Copy', 'wceventsfp'); ?>
                            </button>
                        </div>
                        
                        <p class="wcefp-expiry-info">
                            <span class="dashicons dashicons-clock"></span>
                            <?php printf(
                                esc_html__('Expires: %s', 'wceventsfp'),
                                '<strong>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $token_expiry) . '</strong>'
                            ); ?>
                        </p>
                    </div>
                    
                    <div class="wcefp-feed-actions">
                        <button type="button" class="button button-secondary wcefp-refresh-token">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh Token', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button button-link-delete wcefp-revoke-token">
                            <?php esc_html_e('Revoke Access', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="wcefp-feed-inactive">
                    <div class="wcefp-status-indicator wcefp-status-inactive">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('No Active Feed', 'wceventsfp'); ?>
                    </div>
                    
                    <p><?php esc_html_e('Generate a secure calendar feed that you can subscribe to in Google Calendar, Outlook, or any calendar application.', 'wceventsfp'); ?></p>
                    
                    <button type="button" class="button button-primary wcefp-generate-feed">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Generate Calendar Feed', 'wceventsfp'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-feed-instructions">
                <h3><?php esc_html_e('How to Subscribe:', 'wceventsfp'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Copy the calendar feed URL above', 'wceventsfp'); ?></li>
                    <li><?php esc_html_e('In Google Calendar: Add calendar → From URL → Paste URL', 'wceventsfp'); ?></li>
                    <li><?php esc_html_e('In Outlook: Add calendar → From internet → Paste URL', 'wceventsfp'); ?></li>
                    <li><?php esc_html_e('The calendar will automatically update with new bookings', 'wceventsfp'); ?></li>
                </ol>
            </div>
        </div>
        
        <!-- Calendar Usage Statistics -->
        <div class="wcefp-admin-card">
            <h2><?php esc_html_e('Calendar Integration Usage', 'wceventsfp'); ?></h2>
            
            <div class="wcefp-usage-stats">
                <div class="wcefp-stat-box">
                    <span class="wcefp-stat-number"><?php echo esc_html($total_usage); ?></span>
                    <span class="wcefp-stat-label"><?php esc_html_e('Total Uses', 'wceventsfp'); ?></span>
                </div>
                
                <?php if (!empty($recent_usage)): ?>
                    <div class="wcefp-recent-usage">
                        <h3><?php esc_html_e('Last 7 Days', 'wceventsfp'); ?></h3>
                        <table class="wcefp-usage-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date', 'wceventsfp'); ?></th>
                                    <th><?php esc_html_e('Google', 'wceventsfp'); ?></th>
                                    <th><?php esc_html_e('Outlook', 'wceventsfp'); ?></th>
                                    <th><?php esc_html_e('ICS', 'wceventsfp'); ?></th>
                                    <th><?php esc_html_e('Total', 'wceventsfp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_usage as $date => $stats): ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?></td>
                                        <td><?php echo esc_html($stats['google'] ?? 0); ?></td>
                                        <td><?php echo esc_html($stats['outlook'] ?? 0); ?></td>
                                        <td><?php echo esc_html($stats['ics'] ?? 0); ?></td>
                                        <td><strong><?php echo esc_html(array_sum($stats)); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Integration Instructions -->
        <div class="wcefp-admin-card">
            <h2><?php esc_html_e('Frontend Integration', 'wceventsfp'); ?></h2>
            <p><?php esc_html_e('Calendar integration buttons are automatically added to booking confirmations. You can also use the shortcode or add buttons manually.', 'wceventsfp'); ?></p>
            
            <div class="wcefp-integration-examples">
                <h3><?php esc_html_e('Shortcode Usage:', 'wceventsfp'); ?></h3>
                <code>[wcefp_add_to_calendar booking_id="123"]</code>
                <p class="description"><?php esc_html_e('Replace 123 with the actual booking ID', 'wceventsfp'); ?></p>
                
                <h3><?php esc_html_e('PHP Function:', 'wceventsfp'); ?></h3>
                <code>do_action('wcefp:add_calendar_buttons', $booking_data);</code>
                <p class="description"><?php esc_html_e('Use in themes or custom plugins', 'wceventsfp'); ?></p>
            </div>
        </div>
        
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Generate calendar feed
    $('.wcefp-generate-feed').on('click', function() {
        const $button = $(this);
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php esc_js_e('Generating...', 'wceventsfp'); ?>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wcefp_generate_admin_calendar_feed',
                nonce: '<?php echo wp_create_nonce('wcefp_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_js_e('Error generating calendar feed', 'wceventsfp'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_js_e('Error generating calendar feed', 'wceventsfp'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Refresh token
    $('.wcefp-refresh-token').on('click', function() {
        const $button = $(this);
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php esc_js_e('Refreshing...', 'wceventsfp'); ?>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wcefp_generate_admin_calendar_feed',
                nonce: '<?php echo wp_create_nonce('wcefp_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_js_e('Error refreshing token', 'wceventsfp'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_js_e('Error refreshing token', 'wceventsfp'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Revoke token
    $('.wcefp-revoke-token').on('click', function() {
        if (!confirm('<?php esc_js_e('Are you sure you want to revoke the calendar feed? The current URL will stop working.', 'wceventsfp'); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wcefp_revoke_admin_calendar_feed',
                nonce: '<?php echo wp_create_nonce('wcefp_admin_nonce'); ?>'
            },
            success: function(response) {
                location.reload();
            },
            error: function() {
                alert('<?php esc_js_e('Error revoking token', 'wceventsfp'); ?>');
            }
        });
    });
    
    // Copy URL to clipboard
    $('.wcefp-copy-url').on('click', function() {
        const $button = $(this);
        const $input = $($(this).data('clipboard-target'));
        const originalText = $button.html();
        
        $input.select();
        document.execCommand('copy');
        
        $button.html('<span class="dashicons dashicons-yes"></span> <?php esc_js_e('Copied!', 'wceventsfp'); ?>');
        
        setTimeout(function() {
            $button.html(originalText);
        }, 2000);
    });
    
});
</script>

<style>
.wcefp-calendar-integration {
    max-width: 1200px;
}

.wcefp-admin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .wcefp-admin-grid {
        grid-template-columns: 1fr;
    }
}

.wcefp-admin-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.wcefp-admin-card h2 {
    margin-top: 0;
    color: #23282d;
    font-size: 16px;
    font-weight: 600;
}

.wcefp-status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 4px;
    font-weight: 500;
}

.wcefp-status-active {
    background: #d1f2d1;
    color: #155724;
    border: 1px solid #c3e6c3;
}

.wcefp-status-inactive {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.wcefp-feed-url-container {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}

.wcefp-feed-url {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}

.wcefp-expiry-info {
    color: #666;
    font-size: 13px;
    margin: 10px 0;
}

.wcefp-feed-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.wcefp-feed-instructions ol {
    margin-left: 20px;
}

.wcefp-feed-instructions li {
    margin-bottom: 8px;
}

.wcefp-usage-stats {
    margin-top: 15px;
}

.wcefp-stat-box {
    text-align: center;
    padding: 20px;
    background: #f7f7f7;
    border-radius: 4px;
    margin-bottom: 20px;
}

.wcefp-stat-number {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.wcefp-stat-label {
    display: block;
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.wcefp-usage-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.wcefp-usage-table th,
.wcefp-usage-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.wcefp-usage-table th {
    background: #f7f7f7;
    font-weight: 600;
}

.wcefp-integration-examples code {
    display: block;
    background: #f4f4f4;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: monospace;
    margin: 8px 0;
}

.dashicons.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>