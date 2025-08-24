<?php
/**
 * Enhanced Email Communication Manager
 * 
 * Phase 2: Communication & Automation - Modern email system with templates,
 * automation workflows, and integration with existing gift/voucher functionality
 *
 * @package WCEFP
 * @subpackage Features\Communication
 * @since 2.1.2
 */

namespace WCEFP\Features\Communication;

use WCEFP\Utils\StringHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modern email communication system
 * Enhances existing email functionality with templates, automation, and analytics
 */
class EmailManager {
    
    /**
     * Email types for different communication scenarios
     */
    const TYPE_VOUCHER_CREATED = 'voucher_created';
    const TYPE_BOOKING_CONFIRMATION = 'booking_confirmation';
    const TYPE_BOOKING_REMINDER = 'booking_reminder';
    const TYPE_POST_EVENT_FOLLOWUP = 'post_event_followup';
    const TYPE_ADMIN_NOTIFICATION = 'admin_notification';
    
    /**
     * Default email templates directory
     * 
     * @var string
     */
    private string $templates_dir;
    
    /**
     * Email sending statistics
     * 
     * @var array<string, mixed>
     */
    private array $stats = [];
    
    public function __construct() {
        $this->templates_dir = WCEFP_PLUGIN_DIR . 'assets/email-templates/';
        
        // Ensure templates directory exists
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wcefp_send_automated_email', [$this, 'send_automated_email'], 10, 3);
        add_action('wcefp_voucher_created', [$this, 'send_voucher_notification'], 10, 2);
        add_action('wcefp_booking_confirmed', [$this, 'send_booking_confirmation'], 10, 2);
        
        // Daily cleanup and stats
        add_action('wcefp_daily_email_maintenance', [$this, 'daily_maintenance']);
        if (!wp_next_scheduled('wcefp_daily_email_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wcefp_daily_email_maintenance');
        }
    }
    
    /**
     * Send enhanced voucher notification email
     * Integrates with existing WCEFP_Gift functionality
     * 
     * @param array<string, mixed> $voucher_data Voucher information
     * @param array<string, mixed> $recipient Recipient details
     * @return bool Success status
     */
    public function send_voucher_notification(array $voucher_data, array $recipient): bool {
        if (empty($recipient['email']) || !is_email($recipient['email'])) {
            $this->log_error('Invalid recipient email for voucher notification', [
                'recipient' => $recipient,
                'voucher_code' => $voucher_data['code'] ?? 'unknown'
            ]);
            return false;
        }
        
        $template_data = [
            'recipient_name' => $recipient['name'] ?? __('Caro cliente', 'wceventsfp'),
            'voucher_code' => $voucher_data['code'],
            'voucher_amount' => $voucher_data['amount'],
            'voucher_url' => $voucher_data['url'] ?? '',
            'gift_message' => $voucher_data['message'] ?? '',
            'sender_name' => $voucher_data['sender_name'] ?? '',
            'expiry_date' => $voucher_data['expiry_date'] ?? '',
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        ];
        
        $subject = sprintf(
            __('🎁 Hai ricevuto un voucher regalo da %s!', 'wceventsfp'),
            $template_data['site_name']
        );
        
        return $this->send_templated_email(
            $recipient['email'],
            $subject,
            self::TYPE_VOUCHER_CREATED,
            $template_data
        );
    }
    
    /**
     * Send booking confirmation email
     * 
     * @param int $booking_id Booking ID
     * @param array<string, mixed> $booking_data Booking details
     * @return bool Success status
     */
    public function send_booking_confirmation(int $booking_id, array $booking_data): bool {
        if (empty($booking_data['customer_email']) || !is_email($booking_data['customer_email'])) {
            $this->log_error('Invalid customer email for booking confirmation', [
                'booking_id' => $booking_id,
                'booking_data' => $booking_data
            ]);
            return false;
        }
        
        $template_data = [
            'customer_name' => $booking_data['customer_name'] ?? __('Caro cliente', 'wceventsfp'),
            'booking_id' => $booking_id,
            'event_title' => $booking_data['event_title'] ?? '',
            'event_date' => $booking_data['event_date'] ?? '',
            'event_time' => $booking_data['event_time'] ?? '',
            'event_location' => $booking_data['event_location'] ?? '',
            'booking_details' => $booking_data['details'] ?? [],
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        ];
        
        $subject = sprintf(
            __('✅ Conferma prenotazione #%d - %s', 'wceventsfp'),
            $booking_id,
            $template_data['event_title']
        );
        
        return $this->send_templated_email(
            $booking_data['customer_email'],
            $subject,
            self::TYPE_BOOKING_CONFIRMATION,
            $template_data
        );
    }
    
    /**
     * Send templated email using modern WordPress practices
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $template_type Template identifier
     * @param array $template_data Data for template variables
     * @return bool Success status
     */
    public function send_templated_email($to, $subject, $template_type, $template_data = []) {
        // Get email template
        $template_content = $this->get_email_template($template_type, $template_data);
        if (!$template_content) {
            $this->log_error('Failed to load email template', [
                'template_type' => $template_type,
                'recipient' => $to
            ]);
            return false;
        }
        
        // Setup email headers for HTML content
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Allow filtering of email content
        $template_content = apply_filters('wcefp_email_content', $template_content, $template_type, $template_data);
        $subject = apply_filters('wcefp_email_subject', $subject, $template_type, $template_data);
        $headers = apply_filters('wcefp_email_headers', $headers, $template_type, $template_data);
        
        // Send email
        $sent = wp_mail($to, $subject, $template_content, $headers);
        
        // Log result
        if ($sent) {
            $this->log_success('Email sent successfully', [
                'recipient' => $to,
                'template_type' => $template_type,
                'subject' => $subject
            ]);
        } else {
            $this->log_error('Failed to send email', [
                'recipient' => $to,
                'template_type' => $template_type,
                'subject' => $subject
            ]);
        }
        
        // Update statistics
        $this->update_email_stats($template_type, $sent);
        
        return $sent;
    }
    
    /**
     * Get email template content
     * 
     * @param string $template_type Template identifier
     * @param array $template_data Data for template variables
     * @return string|false Template content or false on failure
     */
    private function get_email_template($template_type, $template_data = []) {
        // Look for custom template first
        $custom_template = $this->templates_dir . $template_type . '.html';
        
        if (file_exists($custom_template)) {
            $template_content = file_get_contents($custom_template);
        } else {
            // Use default template
            $template_content = $this->get_default_template($template_type);
        }
        
        if (!$template_content) {
            return false;
        }
        
        // Replace template variables
        foreach ($template_data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template_content = str_replace('{{' . $key . '}}', esc_html($value), $template_content);
            }
        }
        
        // Clean up any remaining template variables
        $template_content = StringHelper::safe_preg_replace('/\{\{[^}]+\}\}/', '', $template_content);
        
        return $template_content;
    }
    
    /**
     * Get default email template for a specific type
     * 
     * @param string $template_type Template identifier
     * @return string Default template content
     */
    private function get_default_template($template_type) {
        $base_template = $this->get_base_template();
        
        switch ($template_type) {
            case self::TYPE_VOUCHER_CREATED:
                $content = $this->get_voucher_template_content();
                break;
                
            case self::TYPE_BOOKING_CONFIRMATION:
                $content = $this->get_booking_confirmation_template_content();
                break;
                
            case self::TYPE_BOOKING_REMINDER:
                $content = $this->get_booking_reminder_template_content();
                break;
                
            default:
                $content = '<p>' . __('Grazie per aver utilizzato i nostri servizi.', 'wceventsfp') . '</p>';
                break;
        }
        
        return str_replace('{{EMAIL_CONTENT}}', $content, $base_template);
    }
    
    /**
     * Base HTML template for all emails
     * 
     * @return string Base HTML template
     */
    private function get_base_template() {
        return '
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{site_name}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
        .content { background: #ffffff; padding: 20px; border: 1px solid #e9ecef; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; font-size: 14px; color: #6c757d; }
        .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .voucher-code { font-size: 24px; font-weight: bold; background: #f8f9fa; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0; border: 2px dashed #007cba; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{site_name}}</h1>
    </div>
    <div class="content">
        {{EMAIL_CONTENT}}
    </div>
    <div class="footer">
        <p>{{site_name}} - <a href="{{site_url}}">{{site_url}}</a></p>
    </div>
</body>
</html>';
    }
    
    /**
     * Voucher email template content
     * 
     * @return string Template content
     */
    private function get_voucher_template_content() {
        return '
        <h2>🎁 Hai ricevuto un voucher regalo!</h2>
        <p>Ciao {{recipient_name}},</p>
        <p>Complimenti! Hai ricevuto un voucher regalo da {{sender_name}}.</p>
        
        ' . (strlen('{{gift_message}}') > 0 ? '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <strong>Messaggio per te:</strong><br>
            <em>"{{gift_message}}"</em>
        </div>' : '') . '
        
        <div class="voucher-code">
            CODICE: {{voucher_code}}
        </div>
        
        <p><strong>Valore del voucher:</strong> {{voucher_amount}}</p>
        
        ' . (strlen('{{expiry_date}}') > 0 ? '<p><strong>Scadenza:</strong> {{expiry_date}}</p>' : '') . '
        
        <p>Per utilizzare il tuo voucher:</p>
        <ol>
            <li>Visita il nostro sito web</li>
            <li>Scegli l\'esperienza che desideri prenotare</li>
            <li>Inserisci il codice voucher durante il checkout</li>
        </ol>
        
        ' . (strlen('{{voucher_url}}') > 0 ? '<p><a href="{{voucher_url}}" class="button">Visualizza Voucher</a></p>' : '') . '
        
        <p>Non vediamo l\'ora di offrirti un\'esperienza indimenticabile!</p>';
    }
    
    /**
     * Booking confirmation template content
     * 
     * @return string Template content
     */
    private function get_booking_confirmation_template_content() {
        return '
        <h2>✅ Prenotazione Confermata</h2>
        <p>Ciao {{customer_name}},</p>
        <p>La tua prenotazione è stata confermata con successo!</p>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h3>Dettagli della prenotazione:</h3>
            <p><strong>ID Prenotazione:</strong> #{{booking_id}}</p>
            <p><strong>Evento:</strong> {{event_title}}</p>
            <p><strong>Data:</strong> {{event_date}}</p>
            <p><strong>Orario:</strong> {{event_time}}</p>
            <p><strong>Luogo:</strong> {{event_location}}</p>
        </div>
        
        <p>Ti invieremo un promemoria qualche giorno prima dell\'evento.</p>
        <p>Se hai domande, non esitare a contattarci.</p>
        
        <p>Grazie per aver scelto i nostri servizi!</p>';
    }
    
    /**
     * Booking reminder template content
     * 
     * @return string Template content
     */
    private function get_booking_reminder_template_content() {
        return '
        <h2>⏰ Promemoria Prenotazione</h2>
        <p>Ciao {{customer_name}},</p>
        <p>Ti ricordiamo che hai una prenotazione in programma!</p>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
            <h3>La tua prenotazione:</h3>
            <p><strong>Evento:</strong> {{event_title}}</p>
            <p><strong>Data:</strong> {{event_date}}</p>
            <p><strong>Orario:</strong> {{event_time}}</p>
            <p><strong>Luogo:</strong> {{event_location}}</p>
        </div>
        
        <p>Non dimenticare di portare eventuali documenti richiesti.</p>
        <p>Non vediamo l\'ora di vederti!</p>';
    }
    
    /**
     * Schedule automated email
     * 
     * @param string $type Email type
     * @param array $data Email data
     * @param int $delay Delay in seconds
     * @return bool Success status
     */
    public function schedule_email($type, $data, $delay = 0) {
        $timestamp = time() + $delay;
        
        return wp_schedule_single_event($timestamp, 'wcefp_send_automated_email', [$type, $data, $timestamp]);
    }
    
    /**
     * Send automated email (called by scheduled event)
     * 
     * @param string $type Email type
     * @param array $data Email data
     * @param int $scheduled_time Original scheduled time
     */
    public function send_automated_email($type, $data, $scheduled_time) {
        switch ($type) {
            case self::TYPE_BOOKING_REMINDER:
                if (!empty($data['booking_id']) && !empty($data['customer_email'])) {
                    $this->send_booking_reminder($data['booking_id'], $data);
                }
                break;
                
            case self::TYPE_POST_EVENT_FOLLOWUP:
                if (!empty($data['booking_id']) && !empty($data['customer_email'])) {
                    $this->send_post_event_followup($data['booking_id'], $data);
                }
                break;
        }
    }
    
    /**
     * Send booking reminder
     * 
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking details
     * @return bool Success status
     */
    public function send_booking_reminder($booking_id, $booking_data) {
        $subject = sprintf(
            __('⏰ Promemoria: %s tra poco!', 'wceventsfp'),
            $booking_data['event_title'] ?? 'Il tuo evento'
        );
        
        return $this->send_templated_email(
            $booking_data['customer_email'],
            $subject,
            self::TYPE_BOOKING_REMINDER,
            array_merge($booking_data, ['booking_id' => $booking_id])
        );
    }
    
    /**
     * Update email statistics
     * 
     * @param string $template_type Template type
     * @param bool $sent Whether email was sent successfully
     */
    private function update_email_stats($template_type, $sent) {
        $today = date('Y-m-d');
        $stats_key = 'wcefp_email_stats_' . $today;
        $stats = get_option($stats_key, []);
        
        if (!isset($stats[$template_type])) {
            $stats[$template_type] = ['sent' => 0, 'failed' => 0];
        }
        
        if ($sent) {
            $stats[$template_type]['sent']++;
        } else {
            $stats[$template_type]['failed']++;
        }
        
        update_option($stats_key, $stats, false);
    }
    
    /**
     * Get email statistics for a specific date
     * 
     * @param string $date Date in Y-m-d format (default: today)
     * @return array Email statistics
     */
    public function get_email_stats($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $stats_key = 'wcefp_email_stats_' . $date;
        return get_option($stats_key, []);
    }
    
    /**
     * Daily maintenance - cleanup old stats, process queued emails
     */
    public function daily_maintenance() {
        // Clean up old email statistics (keep last 30 days)
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wcefp_email_stats_%' AND option_name < %s",
            'wcefp_email_stats_' . $cutoff_date
        ));
        
        $this->log_success('Daily email maintenance completed', [
            'cutoff_date' => $cutoff_date,
            'cleanup' => 'email_stats'
        ]);
    }
    
    /**
     * Log success message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_success($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info($message, array_merge(['component' => 'EmailManager'], $context));
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::error($message, array_merge(['component' => 'EmailManager'], $context));
        }
    }
    
    /**
     * Check if email functionality is available
     * 
     * @return bool True if email can be sent
     */
    public function is_available() {
        return function_exists('wp_mail');
    }
}