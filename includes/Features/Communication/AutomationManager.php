<?php
/**
 * Automation Manager
 * 
 * Phase 2: Communication & Automation - Handles automated workflows,
 * scheduled emails, reminders and follow-ups
 *
 * @package WCEFP
 * @subpackage Features\Communication
 * @since 2.1.2
 */

namespace WCEFP\Features\Communication;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages automated communication workflows
 */
class AutomationManager {
    
    /**
     * Email manager instance
     *
     * @var EmailManager
     */
    private EmailManager $email_manager;
    
    /**
     * Voucher manager instance
     *
     * @var VoucherManager
     */
    private VoucherManager $voucher_manager;
    
    /**
     * Automation rules
     *
     * @var array<string, array<string, mixed>>
     */
    private array $automation_rules = [];
    
    /**
     * Constructor
     *
     * @param EmailManager $email_manager
     * @param VoucherManager $voucher_manager
     */
    public function __construct(EmailManager $email_manager, VoucherManager $voucher_manager) {
        $this->email_manager = $email_manager;
        $this->voucher_manager = $voucher_manager;
    }
    
    /**
     * Initialize automation system
     */
    public function init(): void {
        $this->setup_automation_rules();
        $this->init_hooks();
        $this->schedule_automated_tasks();
    }
    
    /**
     * Setup default automation rules
     */
    private function setup_automation_rules() {
        $this->automation_rules = [
            'booking_confirmation' => [
                'trigger' => 'wcefp_booking_confirmed',
                'delay' => 0,
                'email_type' => EmailManager::TYPE_BOOKING_CONFIRMATION,
                'enabled' => true
            ],
            'booking_reminder_7days' => [
                'trigger' => 'wcefp_booking_confirmed',
                'delay' => 7 * DAY_IN_SECONDS,
                'email_type' => EmailManager::TYPE_BOOKING_REMINDER,
                'enabled' => true,
                'condition' => 'booking_in_future'
            ],
            'booking_reminder_1day' => [
                'trigger' => 'wcefp_booking_confirmed',
                'delay' => 1 * DAY_IN_SECONDS,
                'email_type' => EmailManager::TYPE_BOOKING_REMINDER,
                'enabled' => true,
                'condition' => 'booking_in_future'
            ],
            'post_event_followup' => [
                'trigger' => 'wcefp_event_completed',
                'delay' => 2 * DAY_IN_SECONDS,
                'email_type' => EmailManager::TYPE_POST_EVENT_FOLLOWUP,
                'enabled' => true
            ],
            'voucher_reminder' => [
                'trigger' => 'wcefp_voucher_created',
                'delay' => 14 * DAY_IN_SECONDS,
                'email_type' => 'voucher_reminder',
                'enabled' => true,
                'condition' => 'voucher_unused'
            ],
            'voucher_expiry_warning' => [
                'trigger' => 'wcefp_voucher_expiring',
                'delay' => 7 * DAY_IN_SECONDS,
                'email_type' => 'voucher_expiry_warning',
                'enabled' => true
            ]
        ];
        
        // Allow filtering automation rules
        $this->automation_rules = apply_filters('wcefp_automation_rules', $this->automation_rules);
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Booking automation
        add_action('wcefp_booking_confirmed', [$this, 'trigger_booking_automation'], 10, 2);
        add_action('wcefp_event_completed', [$this, 'trigger_event_completion_automation'], 10, 2);
        
        // Voucher automation
        add_action('wcefp_voucher_created', [$this, 'trigger_voucher_automation'], 10, 2);
        
        // Process automated emails
        add_action('wcefp_process_automated_email', [$this, 'process_automated_email'], 10, 3);
        
        // Daily maintenance
        add_action('wcefp_daily_automation_maintenance', [$this, 'daily_maintenance']);
        
        // Admin interface for automation settings
        add_action('wp_ajax_wcefp_update_automation_settings', [$this, 'handle_automation_settings_update']);
        add_action('wp_ajax_wcefp_get_automation_stats', [$this, 'handle_get_automation_stats']);
        
        // Handle notice dismissal
        add_action('wp_ajax_wcefp_dismiss_phase2_notice', [$this, 'handle_dismiss_phase2_notice']);
    }
    
    /**
     * Schedule automated tasks
     */
    private function schedule_automated_tasks() {
        // Daily maintenance
        if (!wp_next_scheduled('wcefp_daily_automation_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wcefp_daily_automation_maintenance');
        }
        
        // Hourly voucher expiry check
        if (!wp_next_scheduled('wcefp_check_voucher_expiry')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_check_voucher_expiry');
        }
        add_action('wcefp_check_voucher_expiry', [$this, 'check_voucher_expiry']);
        
        // Weekly analytics summary
        if (!wp_next_scheduled('wcefp_weekly_analytics_summary')) {
            wp_schedule_event(time(), 'weekly', 'wcefp_weekly_analytics_summary');
        }
        add_action('wcefp_weekly_analytics_summary', [$this, 'send_weekly_analytics_summary']);
    }
    
    /**
     * Trigger booking automation workflows
     * 
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function trigger_booking_automation($booking_id, $booking_data) {
        foreach ($this->automation_rules as $rule_id => $rule) {
            if ($rule['trigger'] !== 'wcefp_booking_confirmed' || !$rule['enabled']) {
                continue;
            }
            
            // Check conditions if specified
            if (isset($rule['condition']) && !$this->check_condition($rule['condition'], $booking_data)) {
                continue;
            }
            
            $this->schedule_automated_email($rule_id, $rule, [
                'booking_id' => $booking_id,
                'customer_email' => $booking_data['customer_email'] ?? '',
                'customer_name' => $booking_data['customer_name'] ?? '',
                'event_title' => $booking_data['event_title'] ?? '',
                'event_date' => $booking_data['event_date'] ?? '',
                'event_time' => $booking_data['event_time'] ?? '',
                'event_location' => $booking_data['event_location'] ?? ''
            ], $rule['delay']);
        }
    }
    
    /**
     * Trigger voucher automation workflows
     * 
     * @param string $voucher_code Voucher code
     * @param array $voucher_data Voucher data
     */
    public function trigger_voucher_automation($voucher_code, $voucher_data) {
        foreach ($this->automation_rules as $rule_id => $rule) {
            if ($rule['trigger'] !== 'wcefp_voucher_created' || !$rule['enabled']) {
                continue;
            }
            
            $this->schedule_automated_email($rule_id, $rule, [
                'voucher_code' => $voucher_code,
                'recipient_email' => $voucher_data['recipient_email'] ?? '',
                'recipient_name' => $voucher_data['recipient_name'] ?? '',
                'voucher_amount' => $voucher_data['amount'] ?? '',
                'sender_name' => $voucher_data['sender_name'] ?? ''
            ], $rule['delay']);
        }
    }
    
    /**
     * Schedule an automated email
     * 
     * @param string $rule_id Automation rule ID
     * @param array $rule Rule configuration
     * @param array $data Email data
     * @param int $delay Delay in seconds
     */
    private function schedule_automated_email($rule_id, $rule, $data, $delay = 0) {
        $timestamp = time() + $delay;
        
        // Unique event key to prevent duplicates
        $event_key = md5($rule_id . serialize($data) . $timestamp);
        
        $scheduled = wp_schedule_single_event(
            $timestamp, 
            'wcefp_process_automated_email',
            [$rule_id, $rule, $data, $event_key]
        );
        
        if ($scheduled) {
            $this->log_success('Automated email scheduled', [
                'rule_id' => $rule_id,
                'timestamp' => $timestamp,
                'delay' => $delay,
                'event_key' => $event_key
            ]);
        } else {
            $this->log_error('Failed to schedule automated email', [
                'rule_id' => $rule_id,
                'data' => $data
            ]);
        }
    }
    
    /**
     * Process automated email (called by scheduled event)
     * 
     * @param string $rule_id Automation rule ID
     * @param array $rule Rule configuration
     * @param array $data Email data
     * @param string $event_key Unique event key
     */
    public function process_automated_email($rule_id, $rule, $data, $event_key) {
        // Prevent duplicate processing
        $processed_key = 'wcefp_processed_' . $event_key;
        if (get_transient($processed_key)) {
            return;
        }
        
        // Mark as processed (expires after 1 hour)
        set_transient($processed_key, true, HOUR_IN_SECONDS);
        
        $success = false;
        
        switch ($rule['email_type']) {
            case EmailManager::TYPE_BOOKING_CONFIRMATION:
                $success = $this->email_manager->send_booking_confirmation(
                    $data['booking_id'],
                    $data
                );
                break;
                
            case EmailManager::TYPE_BOOKING_REMINDER:
                $success = $this->email_manager->send_booking_reminder(
                    $data['booking_id'],
                    $data
                );
                break;
                
            case EmailManager::TYPE_POST_EVENT_FOLLOWUP:
                $success = $this->send_post_event_followup($data);
                break;
                
            case 'voucher_reminder':
                $success = $this->send_voucher_reminder($data);
                break;
                
            case 'voucher_expiry_warning':
                $success = $this->send_voucher_expiry_warning($data);
                break;
        }
        
        // Log result
        if ($success) {
            $this->log_success('Automated email processed successfully', [
                'rule_id' => $rule_id,
                'event_key' => $event_key,
                'email_type' => $rule['email_type']
            ]);
        } else {
            $this->log_error('Failed to process automated email', [
                'rule_id' => $rule_id,
                'event_key' => $event_key,
                'email_type' => $rule['email_type'],
                'data' => $data
            ]);
        }
        
        // Update automation statistics
        $this->update_automation_stats($rule_id, $success);
    }
    
    /**
     * Send post-event follow-up email
     * 
     * @param array $data Email data
     * @return bool Success status
     */
    private function send_post_event_followup($data) {
        if (empty($data['customer_email'])) {
            return false;
        }
        
        $subject = sprintf(
            __('Come è andata la tua esperienza: %s?', 'wceventsfp'),
            $data['event_title'] ?? 'Il tuo evento'
        );
        
        $template_data = array_merge($data, [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        ]);
        
        return $this->email_manager->send_templated_email(
            $data['customer_email'],
            $subject,
            EmailManager::TYPE_POST_EVENT_FOLLOWUP,
            $template_data
        );
    }
    
    /**
     * Send voucher reminder email
     * 
     * @param array $data Email data
     * @return bool Success status
     */
    private function send_voucher_reminder($data) {
        if (empty($data['recipient_email']) || empty($data['voucher_code'])) {
            return false;
        }
        
        // Check if voucher is still unused
        if (!$this->check_condition('voucher_unused', $data)) {
            return false; // Voucher was already used
        }
        
        $subject = sprintf(
            __('🎁 Ricorda: hai un voucher non utilizzato da %s!', 'wceventsfp'),
            get_bloginfo('name')
        );
        
        $template_data = array_merge($data, [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'voucher_url' => $this->get_voucher_url($data['voucher_code'])
        ]);
        
        return $this->email_manager->send_templated_email(
            $data['recipient_email'],
            $subject,
            'voucher_reminder',
            $template_data
        );
    }
    
    /**
     * Send voucher expiry warning email
     * 
     * @param array $data Email data
     * @return bool Success status
     */
    private function send_voucher_expiry_warning($data) {
        if (empty($data['recipient_email']) || empty($data['voucher_code'])) {
            return false;
        }
        
        $subject = sprintf(
            __('⚠️ Il tuo voucher scade presto - %s', 'wceventsfp'),
            get_bloginfo('name')
        );
        
        $template_data = array_merge($data, [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'voucher_url' => $this->get_voucher_url($data['voucher_code'])
        ]);
        
        return $this->email_manager->send_templated_email(
            $data['recipient_email'],
            $subject,
            'voucher_expiry_warning',
            $template_data
        );
    }
    
    /**
     * Check voucher expiry and send warnings
     */
    public function check_voucher_expiry() {
        global $wpdb;
        
        // Find vouchers expiring in 7 days that haven't been warned
        $expiring_vouchers = $wpdb->get_results($wpdb->prepare("
            SELECT code, recipient_email, recipient_name, amount, expiry_date 
            FROM {$wpdb->prefix}wcefp_vouchers 
            WHERE status = 'active' 
            AND expiry_date IS NOT NULL 
            AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND expiry_date > NOW()
            AND (expiry_warning_sent IS NULL OR expiry_warning_sent = 0)
        "));
        
        foreach ($expiring_vouchers as $voucher) {
            // Send expiry warning
            $this->trigger_automation('wcefp_voucher_expiring', [
                'voucher_code' => $voucher->code,
                'recipient_email' => $voucher->recipient_email,
                'recipient_name' => $voucher->recipient_name,
                'voucher_amount' => $voucher->amount,
                'expiry_date' => $voucher->expiry_date
            ]);
            
            // Mark warning as sent
            $wpdb->update(
                $wpdb->prefix . 'wcefp_vouchers',
                ['expiry_warning_sent' => 1],
                ['code' => $voucher->code],
                ['%d'],
                ['%s']
            );
        }
        
        // Update expired vouchers
        $expired_count = $wpdb->query("
            UPDATE {$wpdb->prefix}wcefp_vouchers 
            SET status = 'expired' 
            WHERE status = 'active' 
            AND expiry_date IS NOT NULL 
            AND expiry_date < NOW()
        ");
        
        if ($expired_count > 0) {
            $this->log_success('Updated expired vouchers', [
                'count' => $expired_count
            ]);
        }
    }
    
    /**
     * Send weekly analytics summary to administrators
     */
    public function send_weekly_analytics_summary() {
        // Get administrators
        $admins = get_users(['role' => 'administrator']);
        
        if (empty($admins)) {
            return;
        }
        
        // Collect weekly stats
        $analytics = $this->get_weekly_analytics();
        
        $subject = sprintf(
            __('📊 Riepilogo Settimanale WCEFP - %s', 'wceventsfp'),
            get_bloginfo('name')
        );
        
        $template_data = [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'week_start' => date('d/m/Y', strtotime('-7 days')),
            'week_end' => date('d/m/Y'),
            'analytics' => $analytics
        ];
        
        foreach ($admins as $admin) {
            $this->email_manager->send_templated_email(
                $admin->user_email,
                $subject,
                'weekly_analytics_summary',
                array_merge($template_data, [
                    'admin_name' => $admin->display_name
                ])
            );
        }
    }
    
    /**
     * Check automation condition
     * 
     * @param string $condition Condition to check
     * @param array $data Data for condition checking
     * @return bool Whether condition is met
     */
    private function check_condition($condition, $data) {
        switch ($condition) {
            case 'booking_in_future':
                if (empty($data['event_date'])) {
                    return false;
                }
                return strtotime($data['event_date']) > time();
                
            case 'voucher_unused':
                if (empty($data['voucher_code'])) {
                    return false;
                }
                global $wpdb;
                $status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}wcefp_vouchers WHERE code = %s",
                    $data['voucher_code']
                ));
                return $status === 'active';
                
            default:
                return true;
        }
    }
    
    /**
     * Trigger automation workflow manually
     * 
     * @param string $trigger Trigger name
     * @param array $data Trigger data
     */
    public function trigger_automation($trigger, $data) {
        do_action($trigger, $data);
    }
    
    /**
     * Get automation statistics
     * 
     * @return array Statistics
     */
    public function get_automation_stats() {
        $stats_key = 'wcefp_automation_stats';
        $stats = get_option($stats_key, []);
        
        // Calculate totals
        $total_sent = 0;
        $total_failed = 0;
        
        foreach ($stats as $rule_stats) {
            $total_sent += $rule_stats['sent'] ?? 0;
            $total_failed += $rule_stats['failed'] ?? 0;
        }
        
        return [
            'total_sent' => $total_sent,
            'total_failed' => $total_failed,
            'success_rate' => $total_sent + $total_failed > 0 ? 
                             round(($total_sent / ($total_sent + $total_failed)) * 100, 2) : 0,
            'by_rule' => $stats
        ];
    }
    
    /**
     * Get weekly analytics summary
     * 
     * @return array Weekly analytics
     */
    private function get_weekly_analytics() {
        global $wpdb;
        
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        // Booking statistics
        $bookings_this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_bookings WHERE created_date >= %s",
            $week_ago
        ));
        
        // Voucher statistics
        $vouchers_created = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE created_date >= %s",
            $week_ago
        ));
        
        $vouchers_redeemed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers 
             WHERE status = 'redeemed' AND redeemed_date >= %s",
            $week_ago
        ));
        
        // Email statistics
        $email_stats = $this->email_manager->get_email_stats();
        
        // Automation statistics
        $automation_stats = $this->get_automation_stats();
        
        return [
            'bookings' => [
                'created' => (int) $bookings_this_week
            ],
            'vouchers' => [
                'created' => (int) $vouchers_created,
                'redeemed' => (int) $vouchers_redeemed
            ],
            'emails' => $email_stats,
            'automation' => $automation_stats
        ];
    }
    
    /**
     * Update automation statistics
     * 
     * @param string $rule_id Rule ID
     * @param bool $success Whether the automation was successful
     */
    private function update_automation_stats($rule_id, $success) {
        $stats_key = 'wcefp_automation_stats';
        $stats = get_option($stats_key, []);
        
        if (!isset($stats[$rule_id])) {
            $stats[$rule_id] = ['sent' => 0, 'failed' => 0];
        }
        
        if ($success) {
            $stats[$rule_id]['sent']++;
        } else {
            $stats[$rule_id]['failed']++;
        }
        
        update_option($stats_key, $stats, false);
    }
    
    /**
     * Handle AJAX automation settings update
     */
    public function handle_automation_settings_update(): void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wcefp_automation_settings')) {
            wp_die(__('Sicurezza non valida.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permessi insufficienti.', 'wceventsfp'));
        }
        
        // Properly sanitize settings array
        $raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $settings = [];
        
        if (is_array($raw_settings)) {
            foreach ($raw_settings as $key => $value) {
                $sanitized_key = sanitize_key($key);
                if (is_array($value)) {
                    $settings[$sanitized_key] = array_map('sanitize_text_field', $value);
                } else {
                    $settings[$sanitized_key] = sanitize_text_field($value);
                }
            }
        }
        
        // Validate and save automation settings
        $valid_rules = array_keys($this->automation_rules);
        $saved_settings = [];
        
        foreach ($settings as $rule_id => $enabled) {
            if (in_array($rule_id, $valid_rules)) {
                $saved_settings[$rule_id] = (bool) $enabled;
            }
        }
        
        update_option('wcefp_automation_settings', $saved_settings, false);
        
        wp_send_json_success([
            'message' => __('Impostazioni automazione aggiornate.', 'wceventsfp')
        ]);
    }
    
    /**
     * Handle AJAX get automation stats
     */
    public function handle_get_automation_stats() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'wcefp_automation_stats')) {
            wp_die(__('Sicurezza non valida.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permessi insufficienti.', 'wceventsfp'));
        }
        
        $stats = $this->get_automation_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * Handle Phase 2 notice dismissal
     */
    public function handle_dismiss_phase2_notice() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_dismiss_notice')) {
            wp_die(__('Sicurezza non valida.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permessi insufficienti.', 'wceventsfp'));
        }
        
        update_option('wcefp_phase2_notice_dismissed', true, false);
        wp_send_json_success();
    }
    
    /**
     * Daily maintenance tasks
     */
    public function daily_maintenance() {
        // Clean up old automation events
        $this->cleanup_old_events();
        
        // Process any failed scheduled events
        $this->process_failed_events();
        
        $this->log_success('Daily automation maintenance completed');
    }
    
    /**
     * Clean up old automation events
     */
    private function cleanup_old_events() {
        global $wpdb;
        
        // Clean up processed event transients older than 24 hours
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcefp_processed_%' 
             AND option_name < %s",
            '_transient_wcefp_processed_' . (time() - DAY_IN_SECONDS)
        ));
    }
    
    /**
     * Process failed events (basic recovery mechanism)
     */
    private function process_failed_events() {
        // This is a placeholder for more advanced retry logic
        // Could be implemented to retry failed email sends, etc.
    }
    
    /**
     * Get voucher URL
     * 
     * @param string $voucher_code Voucher code
     * @return string Voucher URL
     */
    private function get_voucher_url($voucher_code) {
        return add_query_arg([
            'wcefp_voucher_view' => $voucher_code,
            'nonce' => wp_create_nonce('wcefp_voucher_view_' . $voucher_code)
        ], home_url());
    }
    
    /**
     * Log success message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_success($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info($message, array_merge(['component' => 'AutomationManager'], $context));
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
            \WCEFP\Utils\Logger::error($message, array_merge(['component' => 'AutomationManager'], $context));
        }
    }
}