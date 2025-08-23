<?php
/**
 * Export Data Admin Page
 * 
 * WordPress-native interface for exporting bookings and calendar data.
 * 
 * @package WCEFP
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Export Data', 'wceventsfp'); ?></h1>
    
    <div class="wcefp-export-container">
        <!-- Bookings Export Section -->
        <div class="wcefp-export-section">
            <h2><?php esc_html_e('Export Bookings', 'wceventsfp'); ?></h2>
            <p class="description">
                <?php esc_html_e('Export booking data as CSV file with advanced filtering options.', 'wceventsfp'); ?>
            </p>
            
            <form id="wcefp-export-bookings-form" class="wcefp-export-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bookings_date_from"><?php esc_html_e('Date From', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="bookings_date_from" name="date_from" class="regular-text" />
                            <p class="description"><?php esc_html_e('Leave empty to include all dates', 'wceventsfp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bookings_date_to"><?php esc_html_e('Date To', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="bookings_date_to" name="date_to" class="regular-text" />
                            <p class="description"><?php esc_html_e('Leave empty to include all future dates', 'wceventsfp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bookings_status"><?php esc_html_e('Booking Status', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <select id="bookings_status" name="status" class="regular-text">
                                <option value="all"><?php esc_html_e('All Statuses', 'wceventsfp'); ?></option>
                                <option value="pending"><?php esc_html_e('Pending', 'wceventsfp'); ?></option>
                                <option value="confirmed"><?php esc_html_e('Confirmed', 'wceventsfp'); ?></option>
                                <option value="cancelled"><?php esc_html_e('Cancelled', 'wceventsfp'); ?></option>
                                <option value="completed"><?php esc_html_e('Completed', 'wceventsfp'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bookings_event"><?php esc_html_e('Specific Event', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <select id="bookings_event" name="event_id" class="regular-text">
                                <option value="0"><?php esc_html_e('All Events', 'wceventsfp'); ?></option>
                                <?php
                                $events = get_posts([
                                    'post_type' => 'product',
                                    'posts_per_page' => -1,
                                    'meta_query' => [
                                        [
                                            'key' => '_wcefp_is_event',
                                            'value' => 'yes',
                                            'compare' => '='
                                        ]
                                    ]
                                ]);
                                foreach ($events as $event) {
                                    printf(
                                        '<option value="%d">%s</option>',
                                        esc_attr($event->ID),
                                        esc_html($event->post_title)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Bookings CSV', 'wceventsfp'); ?>
                    </button>
                </p>
                
                <?php wp_nonce_field('wcefp_export', 'wcefp_export_nonce'); ?>
            </form>
        </div>
        
        <!-- Calendar Export Section -->
        <div class="wcefp-export-section">
            <h2><?php esc_html_e('Export Calendar', 'wceventsfp'); ?></h2>
            <p class="description">
                <?php esc_html_e('Export events as ICS calendar file compatible with Google Calendar, Outlook, and other calendar applications.', 'wceventsfp'); ?>
            </p>
            
            <form id="wcefp-export-calendar-form" class="wcefp-export-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="calendar_event"><?php esc_html_e('Event Selection', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <select id="calendar_event" name="event_id" class="regular-text">
                                <option value="0"><?php esc_html_e('All Events', 'wceventsfp'); ?></option>
                                <?php
                                foreach ($events as $event) {
                                    printf(
                                        '<option value="%d">%s</option>',
                                        esc_attr($event->ID),
                                        esc_html($event->post_title)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="calendar_range"><?php esc_html_e('Date Range', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <select id="calendar_range" name="date_range" class="regular-text">
                                <option value="30"><?php esc_html_e('Next 30 days', 'wceventsfp'); ?></option>
                                <option value="60"><?php esc_html_e('Next 60 days', 'wceventsfp'); ?></option>
                                <option value="90"><?php esc_html_e('Next 90 days', 'wceventsfp'); ?></option>
                                <option value="180"><?php esc_html_e('Next 6 months', 'wceventsfp'); ?></option>
                                <option value="365"><?php esc_html_e('Next year', 'wceventsfp'); ?></option>
                                <option value="all"><?php esc_html_e('All future events', 'wceventsfp'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Export Calendar ICS', 'wceventsfp'); ?>
                    </button>
                </p>
                
                <?php wp_nonce_field('wcefp_export', 'wcefp_calendar_nonce'); ?>
            </form>
        </div>
        
        <!-- Public Calendar Feeds Section -->
        <div class="wcefp-export-section">
            <h2><?php esc_html_e('Public Calendar Feeds', 'wceventsfp'); ?></h2>
            <p class="description">
                <?php esc_html_e('Generate public ICS feeds that can be subscribed to from external calendar applications.', 'wceventsfp'); ?>
            </p>
            
            <div class="wcefp-calendar-feeds">
                <div class="wcefp-feed-item">
                    <h4><?php esc_html_e('Public Events Feed', 'wceventsfp'); ?></h4>
                    <p class="description">
                        <?php esc_html_e('Subscribe to this feed to get automatic updates of published events.', 'wceventsfp'); ?>
                    </p>
                    <?php
                    $public_feed_url = home_url('wcefp-calendar/public');
                    ?>
                    <div class="wcefp-feed-url">
                        <input type="text" value="<?php echo esc_url($public_feed_url); ?>" readonly class="large-text code" />
                        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($public_feed_url); ?>')">
                            <?php esc_html_e('Copy URL', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="wcefp-feed-instructions">
                    <h4><?php esc_html_e('How to Subscribe', 'wceventsfp'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Google Calendar:', 'wceventsfp'); ?></strong> <?php esc_html_e('Go to Settings → Add calendar → From URL', 'wceventsfp'); ?></li>
                        <li><strong><?php esc_html_e('Outlook:', 'wceventsfp'); ?></strong> <?php esc_html_e('Go to Calendar → Add calendar → Subscribe from web', 'wceventsfp'); ?></li>
                        <li><strong><?php esc_html_e('Apple Calendar:', 'wceventsfp'); ?></strong> <?php esc_html_e('File → New Calendar Subscription', 'wceventsfp'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Export Statistics -->
        <div class="wcefp-export-section">
            <h2><?php esc_html_e('Export Statistics', 'wceventsfp'); ?></h2>
            
            <div class="wcefp-export-stats">
                <?php
                global $wpdb;
                
                // Get booking statistics
                $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_occorrenze");
                $confirmed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_occorrenze WHERE stato = 'confirmed'");
                $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_occorrenze WHERE stato = 'pending'");
                
                // Get event statistics
                $total_events = $wpdb->get_var("
                    SELECT COUNT(*) FROM {$wpdb->posts} p 
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                    WHERE p.post_type = 'product' 
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_wcefp_is_event' 
                    AND pm.meta_value = 'yes'
                ");
                ?>
                
                <div class="wcefp-stats-grid">
                    <div class="wcefp-stat-item">
                        <div class="wcefp-stat-number"><?php echo esc_html(number_format_i18n($total_bookings)); ?></div>
                        <div class="wcefp-stat-label"><?php esc_html_e('Total Bookings', 'wceventsfp'); ?></div>
                    </div>
                    <div class="wcefp-stat-item">
                        <div class="wcefp-stat-number"><?php echo esc_html(number_format_i18n($confirmed_bookings)); ?></div>
                        <div class="wcefp-stat-label"><?php esc_html_e('Confirmed Bookings', 'wceventsfp'); ?></div>
                    </div>
                    <div class="wcefp-stat-item">
                        <div class="wcefp-stat-number"><?php echo esc_html(number_format_i18n($pending_bookings)); ?></div>
                        <div class="wcefp-stat-label"><?php esc_html_e('Pending Bookings', 'wceventsfp'); ?></div>
                    </div>
                    <div class="wcefp-stat-item">
                        <div class="wcefp-stat-number"><?php echo esc_html(number_format_i18n($total_events)); ?></div>
                        <div class="wcefp-stat-label"><?php esc_html_e('Published Events', 'wceventsfp'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>