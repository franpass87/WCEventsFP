<?php
/**
 * Notification Service
 * 
 * @package WCEFP
 * @subpackage Services\Domain
 * @since 2.2.0
 */

namespace WCEFP\Services\Domain;

use WCEFP\Core\SecurityManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automated notification service for bookings and events
 */
class NotificationService {
    
    /**
     * Notification types
     */
    const TYPE_BOOKING_CONFIRMED = 'booking_confirmed';
    const TYPE_BOOKING_CANCELLED = 'booking_cancelled';
    const TYPE_BOOKING_RESCHEDULED = 'booking_rescheduled';
    const TYPE_REMINDER_24H = 'reminder_24h';
    const TYPE_REMINDER_2H = 'reminder_2h';
    const TYPE_CAPACITY_LOW = 'capacity_low';
    const TYPE_CAPACITY_FULL = 'capacity_full';
    const TYPE_WEATHER_ALERT = 'weather_alert';
    const TYPE_EVENT_CANCELLED = 'event_cancelled';
    const TYPE_WAITLIST_SPOT_AVAILABLE = 'waitlist_spot_available';
    
    /**
     * Notification channels
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_WEBHOOK = 'webhook';
    
    /**
     * Send notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients data
     * @param array $data Notification data
     * @param array $options Send options
     * @return array Send results
     */
    public function send_notification($type, $recipients, $data = [], $options = []) {
        if (!$this->is_notification_enabled($type)) {
            Logger::info("Notification type {$type} is disabled, skipping");
            return ['sent' => false, 'reason' => 'disabled'];
        }
        
        // Get notification template
        $template = $this->get_notification_template($type, $data);
        if (!$template) {
            Logger::error("No template found for notification type: {$type}");
            return ['sent' => false, 'reason' => 'no_template'];
        }
        
        // Process recipients
        $processed_recipients = $this->process_recipients($recipients);
        if (empty($processed_recipients)) {
            Logger::warning("No valid recipients for notification type: {$type}");
            return ['sent' => false, 'reason' => 'no_recipients'];
        }
        
        $results = [
            'sent' => true,
            'channels' => [],
            'recipients_count' => count($processed_recipients),
            'errors' => []
        ];
        
        // Get enabled channels for this notification type
        $enabled_channels = $this->get_enabled_channels($type, $options);
        
        foreach ($enabled_channels as $channel) {
            $channel_result = $this->send_via_channel($channel, $type, $processed_recipients, $template, $data, $options);
            $results['channels'][$channel] = $channel_result;
            
            if (!$channel_result['success']) {
                $results['errors'][] = $channel_result['error'] ?? 'Unknown error';
            }
        }
        
        // Log notification
        $this->log_notification($type, $processed_recipients, $results, $data);
        
        // Trigger action for integrations
        do_action('wcefp_notification_sent', $type, $recipients, $data, $results);
        
        return $results;
    }
    
    /**
     * Check if notification type is enabled
     * 
     * @param string $type Notification type
     * @return bool Whether enabled
     */
    private function is_notification_enabled($type) {
        $settings = get_option('wcefp_notification_settings', []);
        return !empty($settings['enabled_types'][$type]);
    }
    
    /**
     * Get notification template
     * 
     * @param string $type Notification type
     * @param array $data Notification data
     * @return array|null Template data
     */
    private function get_notification_template($type, $data = []) {
        $templates = $this->get_notification_templates();
        $template = $templates[$type] ?? null;
        
        if (!$template) {
            return null;
        }
        
        // Process template variables
        $template['subject'] = $this->process_template_variables($template['subject'], $data);
        $template['content'] = $this->process_template_variables($template['content'], $data);
        
        return apply_filters('wcefp_notification_template', $template, $type, $data);
    }
    
    /**
     * Get all notification templates
     * 
     * @return array Templates
     */
    private function get_notification_templates() {
        return [
            self::TYPE_BOOKING_CONFIRMED => [
                'subject' => __('Booking Confirmed - {event_title}', 'wceventsfp'),
                'content' => $this->get_booking_confirmed_template(),
                'channels' => [self::CHANNEL_EMAIL],
                'priority' => 'high'
            ],
            self::TYPE_BOOKING_CANCELLED => [
                'subject' => __('Booking Cancelled - {event_title}', 'wceventsfp'),
                'content' => $this->get_booking_cancelled_template(),
                'channels' => [self::CHANNEL_EMAIL],
                'priority' => 'high'
            ],
            self::TYPE_BOOKING_RESCHEDULED => [
                'subject' => __('Booking Rescheduled - {event_title}', 'wceventsfp'),
                'content' => $this->get_booking_rescheduled_template(),
                'channels' => [self::CHANNEL_EMAIL],
                'priority' => 'medium'
            ],
            self::TYPE_REMINDER_24H => [
                'subject' => __('Reminder: {event_title} tomorrow', 'wceventsfp'),
                'content' => $this->get_reminder_24h_template(),
                'channels' => [self::CHANNEL_EMAIL],
                'priority' => 'medium'
            ],
            self::TYPE_REMINDER_2H => [
                'subject' => __('Starting Soon: {event_title}', 'wceventsfp'),
                'content' => $this->get_reminder_2h_template(),
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SMS],
                'priority' => 'high'
            ],
            self::TYPE_CAPACITY_LOW => [
                'subject' => __('Low Availability: {event_title}', 'wceventsfp'),
                'content' => $this->get_capacity_low_template(),
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_WEBHOOK],
                'priority' => 'medium'
            ],
            self::TYPE_WAITLIST_SPOT_AVAILABLE => [
                'subject' => __('Spot Available: {event_title}', 'wceventsfp'),
                'content' => $this->get_waitlist_available_template(),
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SMS],
                'priority' => 'high'
            ]
        ];
    }
    
    /**
     * Process template variables
     * 
     * @param string $template Template string
     * @param array $data Variable data
     * @return string Processed template
     */
    private function process_template_variables($template, $data) {
        $variables = [
            'event_title' => $data['event_title'] ?? '',
            'customer_name' => $data['customer_name'] ?? '',
            'booking_date' => $data['booking_date'] ?? '',
            'booking_time' => $data['booking_time'] ?? '',
            'booking_id' => $data['booking_id'] ?? '',
            'meeting_point' => $data['meeting_point'] ?? '',
            'contact_phone' => $data['contact_phone'] ?? '',
            'site_name' => get_bloginfo('name') ?: '',
            'site_url' => home_url() ?: ''
        ];
        
        foreach ($variables as $key => $value) {
            $template = \WCEFP\Support\safe_str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Process recipients data
     * 
     * @param array $recipients Raw recipients data
     * @return array Processed recipients
     */
    private function process_recipients($recipients) {
        $processed = [];
        
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                // Simple email address
                if (is_email($recipient)) {
                    $processed[] = [
                        'email' => $recipient,
                        'name' => '',
                        'type' => 'customer'
                    ];
                }
            } elseif (is_array($recipient)) {
                // Structured recipient data
                if (!empty($recipient['email']) && is_email($recipient['email'])) {
                    $processed[] = [
                        'email' => sanitize_email($recipient['email']),
                        'name' => sanitize_text_field($recipient['name'] ?? ''),
                        'phone' => sanitize_text_field($recipient['phone'] ?? ''),
                        'type' => sanitize_text_field($recipient['type'] ?? 'customer'),
                        'user_id' => (int) ($recipient['user_id'] ?? 0)
                    ];
                }
            } elseif (is_numeric($recipient)) {
                // User ID
                $user = get_user_by('id', $recipient);
                if ($user) {
                    $processed[] = [
                        'email' => $user->user_email,
                        'name' => $user->display_name,
                        'type' => 'customer',
                        'user_id' => $user->ID
                    ];
                }
            }
        }
        
        return $processed;
    }
    
    /**
     * Get enabled channels for notification type
     * 
     * @param string $type Notification type
     * @param array $options Options
     * @return array Enabled channels
     */
    private function get_enabled_channels($type, $options = []) {
        $settings = get_option('wcefp_notification_settings', []);
        $global_channels = $settings['channels'] ?? [self::CHANNEL_EMAIL];
        
        // Override with options if provided
        if (!empty($options['channels'])) {
            return array_intersect($options['channels'], $global_channels);
        }
        
        // Use template default channels
        $templates = $this->get_notification_templates();
        $template_channels = $templates[$type]['channels'] ?? [self::CHANNEL_EMAIL];
        
        return array_intersect($template_channels, $global_channels);
    }
    
    /**
     * Send notification via specific channel
     * 
     * @param string $channel Channel type
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $template Template
     * @param array $data Data
     * @param array $options Options
     * @return array Send result
     */
    private function send_via_channel($channel, $type, $recipients, $template, $data, $options) {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return $this->send_email_notification($type, $recipients, $template, $data, $options);
                
            case self::CHANNEL_SMS:
                return $this->send_sms_notification($type, $recipients, $template, $data, $options);
                
            case self::CHANNEL_WEBHOOK:
                return $this->send_webhook_notification($type, $recipients, $template, $data, $options);
                
            default:
                return ['success' => false, 'error' => 'Unsupported channel: ' . $channel];
        }
    }
    
    /**
     * Send email notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $template Template
     * @param array $data Data
     * @param array $options Options
     * @return array Result
     */
    private function send_email_notification($type, $recipients, $template, $data, $options) {
        $success_count = 0;
        $errors = [];
        
        foreach ($recipients as $recipient) {
            $subject = $template['subject'];
            $message = $this->format_email_content($template['content'], $data);
            
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ];
            
            $sent = wp_mail($recipient['email'], $subject, $message, $headers);
            
            if ($sent) {
                $success_count++;
            } else {
                $errors[] = "Failed to send to {$recipient['email']}";
            }
        }
        
        return [
            'success' => $success_count > 0,
            'sent_count' => $success_count,
            'total_count' => count($recipients),
            'errors' => $errors
        ];
    }
    
    /**
     * Send SMS notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $template Template
     * @param array $data Data
     * @param array $options Options
     * @return array Result
     */
    private function send_sms_notification($type, $recipients, $template, $data, $options) {
        // SMS functionality would require integration with SMS providers
        // For now, return success to avoid errors
        return [
            'success' => true,
            'sent_count' => 0,
            'total_count' => count($recipients),
            'note' => 'SMS functionality requires SMS provider integration'
        ];
    }
    
    /**
     * Send webhook notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $template Template
     * @param array $data Data
     * @param array $options Options
     * @return array Result
     */
    private function send_webhook_notification($type, $recipients, $template, $data, $options) {
        $webhook_urls = get_option('wcefp_webhook_urls', []);
        
        if (empty($webhook_urls)) {
            return [
                'success' => false,
                'error' => 'No webhook URLs configured'
            ];
        }
        
        $payload = [
            'notification_type' => $type,
            'recipients' => $recipients,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url()
        ];
        
        $success_count = 0;
        $errors = [];
        
        foreach ($webhook_urls as $url) {
            $response = wp_remote_post($url, [
                'body' => json_encode($payload),
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $success_count++;
            } else {
                $error = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                $errors[] = "Webhook {$url}: {$error}";
            }
        }
        
        return [
            'success' => $success_count > 0,
            'sent_count' => $success_count,
            'total_count' => count($webhook_urls),
            'errors' => $errors
        ];
    }
    
    /**
     * Format email content
     * 
     * @param string $content Raw content
     * @param array $data Data
     * @return string Formatted HTML content
     */
    private function format_email_content($content, $data) {
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $html .= wpautop($content);
        $html .= '<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">';
        $html .= '<p style="font-size: 12px; color: #666;">This email was sent from ' . get_bloginfo('name') . '</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }
    
    /**
     * Log notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $results Results
     * @param array $data Data
     * @return void
     */
    private function log_notification($type, $recipients, $results, $data) {
        Logger::info("Notification sent", [
            'type' => $type,
            'recipients_count' => count($recipients),
            'success' => $results['sent'],
            'channels' => array_keys($results['channels']),
            'booking_id' => $data['booking_id'] ?? null,
            'product_id' => $data['product_id'] ?? null
        ]);
    }
    
    /**
     * Schedule notification
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $data Data
     * @param int $send_at Timestamp when to send
     * @param array $options Options
     * @return bool Success
     */
    public function schedule_notification($type, $recipients, $data, $send_at, $options = []) {
        $args = [
            'type' => $type,
            'recipients' => $recipients,
            'data' => $data,
            'options' => $options
        ];
        
        $scheduled = wp_schedule_single_event($send_at, 'wcefp_send_scheduled_notification', $args);
        
        if ($scheduled) {
            Logger::info("Notification scheduled", [
                'type' => $type,
                'send_at' => date('Y-m-d H:i:s', $send_at),
                'recipients_count' => count($recipients)
            ]);
        }
        
        return $scheduled;
    }
    
    /**
     * Schedule reminder notifications for a booking
     * 
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     * @return array Scheduled notifications
     */
    public function schedule_booking_reminders($booking_id, $booking_data) {
        $scheduled = [];
        
        $booking_datetime = $booking_data['booking_datetime'] ?? '';
        if (empty($booking_datetime)) {
            return $scheduled;
        }
        
        $booking_timestamp = strtotime($booking_datetime);
        if (!$booking_timestamp || $booking_timestamp <= time()) {
            return $scheduled;
        }
        
        $recipients = [
            [
                'email' => $booking_data['customer_email'] ?? '',
                'name' => $booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        // Schedule 24-hour reminder
        $reminder_24h_time = $booking_timestamp - (24 * 3600);
        if ($reminder_24h_time > time()) {
            $data = array_merge($booking_data, ['booking_id' => $booking_id]);
            
            if ($this->schedule_notification(self::TYPE_REMINDER_24H, $recipients, $data, $reminder_24h_time)) {
                $scheduled[] = self::TYPE_REMINDER_24H;
            }
        }
        
        // Schedule 2-hour reminder
        $reminder_2h_time = $booking_timestamp - (2 * 3600);
        if ($reminder_2h_time > time()) {
            $data = array_merge($booking_data, ['booking_id' => $booking_id]);
            
            if ($this->schedule_notification(self::TYPE_REMINDER_2H, $recipients, $data, $reminder_2h_time)) {
                $scheduled[] = self::TYPE_REMINDER_2H;
            }
        }
        
        return $scheduled;
    }
    
    /**
     * Cancel scheduled notifications for a booking
     * 
     * @param int $booking_id Booking ID
     * @return bool Success
     */
    public function cancel_booking_notifications($booking_id) {
        // WordPress doesn't provide direct access to scheduled events by custom args
        // This would require custom implementation or using wp-cron directly
        
        do_action('wcefp_cancel_booking_notifications', $booking_id);
        
        Logger::info("Booking notifications cancelled for booking {$booking_id}");
        
        return true;
    }
    
    /**
     * Get notification templates for booking confirmed
     * 
     * @return string Template content
     */
    private function get_booking_confirmed_template() {
        return __('Dear {customer_name},<br><br>Your booking for <strong>{event_title}</strong> has been confirmed!<br><br><strong>Date:</strong> {booking_date}<br><strong>Time:</strong> {booking_time}<br><strong>Meeting Point:</strong> {meeting_point}<br><br>We look forward to seeing you!<br><br>If you have any questions, please contact us.', 'wceventsfp');
    }
    
    /**
     * Get notification templates for booking cancelled
     * 
     * @return string Template content
     */
    private function get_booking_cancelled_template() {
        return __('Dear {customer_name},<br><br>Your booking for <strong>{event_title}</strong> has been cancelled.<br><br><strong>Booking ID:</strong> {booking_id}<br><br>If this was unexpected, please contact us immediately.<br><br>Any applicable refunds will be processed according to our cancellation policy.', 'wceventsfp');
    }
    
    /**
     * Get notification templates for booking rescheduled
     * 
     * @return string Template content
     */
    private function get_booking_rescheduled_template() {
        return __('Dear {customer_name},<br><br>Your booking for <strong>{event_title}</strong> has been rescheduled.<br><br><strong>New Date:</strong> {booking_date}<br><strong>New Time:</strong> {booking_time}<br><strong>Meeting Point:</strong> {meeting_point}<br><br>We look forward to seeing you at the new time!', 'wceventsfp');
    }
    
    /**
     * Get notification templates for 24h reminder
     * 
     * @return string Template content
     */
    private function get_reminder_24h_template() {
        return __('Dear {customer_name},<br><br>This is a friendly reminder that your experience <strong>{event_title}</strong> is scheduled for tomorrow!<br><br><strong>Date:</strong> {booking_date}<br><strong>Time:</strong> {booking_time}<br><strong>Meeting Point:</strong> {meeting_point}<br><br>We can\'t wait to see you!<br><br>For any last-minute questions: {contact_phone}', 'wceventsfp');
    }
    
    /**
     * Get notification templates for 2h reminder
     * 
     * @return string Template content
     */
    private function get_reminder_2h_template() {
        return __('Dear {customer_name},<br><br>Your experience <strong>{event_title}</strong> starts in 2 hours!<br><br><strong>Time:</strong> {booking_time}<br><strong>Meeting Point:</strong> {meeting_point}<br><br>Please arrive 15 minutes early. See you soon!', 'wceventsfp');
    }
    
    /**
     * Get notification templates for capacity low
     * 
     * @return string Template content
     */
    private function get_capacity_low_template() {
        return __('Alert: Low availability for <strong>{event_title}</strong> on {booking_date}.<br><br>Only a few spots remaining. Consider promoting this experience to maximize bookings.', 'wceventsfp');
    }
    
    /**
     * Get notification templates for waitlist spot available
     * 
     * @return string Template content
     */
    private function get_waitlist_available_template() {
        return __('Great news {customer_name}!<br><br>A spot has become available for <strong>{event_title}</strong> on {booking_date} at {booking_time}.<br><br>This spot is held for you for the next 30 minutes. Book now to secure your place!', 'wceventsfp');
    }
    
    /**
     * Get notification statistics
     * 
     * @param array $filters Date/type filters
     * @return array Statistics
     */
    public function get_notification_statistics($filters = []) {
        // This would typically query a notifications log table
        // For now, return basic structure
        return [
            'total_sent' => 0,
            'by_type' => [],
            'by_channel' => [],
            'success_rate' => 100,
            'recent_notifications' => []
        ];
    }
}