<?php
/**
 * Enhanced Voucher Management System
 * 
 * Phase 2: Communication & Automation - Modern voucher management with
 * WordPress-native modals, analytics, and enhanced user experience
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
 * Modern voucher management system
 * Enhances existing WCEFP_Gift functionality with modern interfaces
 */
class VoucherManager {
    
    /**
     * Voucher status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_REDEEMED = 'redeemed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Email manager instance
     *
     * @var EmailManager
     */
    private EmailManager $email_manager;
    
    /**
     * Initialize voucher management system
     * 
     * @param EmailManager|null $email_manager Optional email manager instance
     */
    public function __construct(?EmailManager $email_manager = null) {
        $this->email_manager = $email_manager ?: new EmailManager();
        
        $this->init_hooks();
        $this->init_admin_features();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Enhanced voucher creation with email integration
        add_action('wcefp_voucher_generated', [$this, 'handle_voucher_created'], 10, 2);
        
        // Admin enhancements
        add_action('wp_ajax_wcefp_voucher_action', [$this, 'handle_ajax_voucher_action']);
        add_action('wp_ajax_wcefp_get_voucher_analytics', [$this, 'handle_ajax_get_analytics']);
        
        // Frontend enhancements
        add_shortcode('wcefp_voucher_status', [$this, 'voucher_status_shortcode']);
        add_shortcode('wcefp_voucher_redeem', [$this, 'enhanced_redeem_shortcode']);
        
        // Integration with existing system
        add_filter('wcefp_voucher_display_data', [$this, 'enhance_voucher_display_data'], 10, 2);
    }
    
    /**
     * Initialize admin-specific features
     */
    private function init_admin_features(): void {
        if (!is_admin()) {
            return;
        }
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_footer', [$this, 'render_voucher_modals']);
        
        // Add bulk actions to voucher admin table
        add_filter('bulk_actions-vouchers', [$this, 'add_voucher_bulk_actions']);
        add_filter('handle_bulk_actions-vouchers', [$this, 'handle_voucher_bulk_actions'], 10, 3);
    }
    
    /**
     * Handle voucher creation with enhanced communication
     * 
     * @param string $voucher_code Generated voucher code
     * @param array $voucher_data Voucher details
     */
    public function handle_voucher_created($voucher_code, $voucher_data) {
        if (empty($voucher_data['recipient_email']) || !is_email($voucher_data['recipient_email'])) {
            $this->log_error('Invalid recipient email for voucher', [
                'voucher_code' => $voucher_code,
                'voucher_data' => $voucher_data
            ]);
            return;
        }
        
        // Prepare enhanced voucher data for email
        $enhanced_data = [
            'code' => $voucher_code,
            'amount' => $voucher_data['amount'] ?? '',
            'message' => $voucher_data['message'] ?? '',
            'sender_name' => $voucher_data['sender_name'] ?? '',
            'expiry_date' => $voucher_data['expiry_date'] ?? '',
            'url' => $this->get_voucher_url($voucher_code)
        ];
        
        $recipient = [
            'email' => $voucher_data['recipient_email'],
            'name' => $voucher_data['recipient_name'] ?? ''
        ];
        
        // Send enhanced voucher notification
        $email_sent = $this->email_manager->send_voucher_notification($enhanced_data, $recipient);
        
        if ($email_sent) {
            $this->log_success('Enhanced voucher notification sent', [
                'voucher_code' => $voucher_code,
                'recipient' => $recipient['email']
            ]);
            
            // Update voucher status to indicate email was sent
            $this->update_voucher_status($voucher_code, [
                'email_sent' => true,
                'email_sent_date' => current_time('mysql')
            ]);
        } else {
            $this->log_error('Failed to send enhanced voucher notification', [
                'voucher_code' => $voucher_code,
                'recipient' => $recipient['email']
            ]);
        }
        
        // Schedule reminder email if voucher hasn't been used in 7 days
        $this->email_manager->schedule_email('voucher_reminder', [
            'voucher_code' => $voucher_code,
            'recipient' => $recipient,
            'voucher_data' => $enhanced_data
        ], 7 * DAY_IN_SECONDS);
    }
    
    /**
     * Enqueue admin assets for enhanced voucher management
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on voucher-related admin pages
        if (strpos($hook, 'voucher') === false && strpos($hook, 'wcefp') === false) {
            return;
        }
        
        // Ensure modal system is loaded
        wp_enqueue_script('wcefp-modals');
        wp_enqueue_style('wcefp-modals');
        
        // Enhanced voucher management script
        wp_enqueue_script(
            'wcefp-voucher-manager',
            WCEFP_PLUGIN_URL . 'assets/js/voucher-manager.js',
            ['jquery', 'wcefp-modals'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-voucher-manager', 'wcefpVoucherManager', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_voucher_action'),
            'strings' => [
                'confirm_cancel' => __('Sei sicuro di voler annullare questo voucher?', 'wceventsfp'),
                'confirm_resend' => __('Vuoi inviare nuovamente l\'email del voucher?', 'wceventsfp'),
                'voucher_cancelled' => __('Voucher annullato con successo.', 'wceventsfp'),
                'email_resent' => __('Email del voucher inviata nuovamente.', 'wceventsfp'),
                'error_occurred' => __('Si è verificato un errore. Riprova.', 'wceventsfp'),
                'loading' => __('Caricamento...', 'wceventsfp')
            ]
        ]);
        
        // Enhanced voucher styles
        wp_enqueue_style(
            'wcefp-voucher-manager',
            WCEFP_PLUGIN_URL . 'assets/css/voucher-manager.css',
            ['wcefp-modals'],
            WCEFP_VERSION
        );
    }
    
    /**
     * Handle AJAX voucher actions
     */
    public function handle_ajax_voucher_action() {
        // Security check
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wcefp_voucher_action')) {
            wp_die(__('Sicurezza non valida.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permessi insufficienti.', 'wceventsfp'));
        }
        
        $action = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';
        $voucher_code = isset($_POST['voucher_code']) ? sanitize_text_field(wp_unslash($_POST['voucher_code'])) : '';
        
        if (empty($voucher_code)) {
            wp_send_json_error(['message' => __('Codice voucher richiesto.', 'wceventsfp')]);
            return;
        }
        
        switch ($action) {
            case 'cancel_voucher':
                $result = $this->cancel_voucher($voucher_code);
                break;
                
            case 'resend_email':
                $result = $this->resend_voucher_email($voucher_code);
                break;
                
            case 'get_voucher_details':
                $result = $this->get_voucher_details($voucher_code);
                break;
                
            default:
                wp_send_json_error(['message' => __('Azione non riconosciuta.', 'wceventsfp')]);
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * Handle AJAX request for voucher analytics
     */
    public function handle_ajax_get_analytics(): void {
        // Security check  
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wcefp_voucher_action')) {
            wp_die(__('Sicurezza non valida.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permessi insufficienti.', 'wceventsfp'));
        }
        
        $analytics = $this->get_voucher_analytics();
        wp_send_json_success($analytics);
    }
    
    /**
     * Cancel a voucher
     * 
     * @param string $voucher_code Voucher code
     * @return array Result array with success status and data/message
     */
    private function cancel_voucher($voucher_code) {
        global $wpdb;
        
        // Check if voucher exists and is cancellable
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_vouchers WHERE code = %s",
            $voucher_code
        ));
        
        if (!$voucher) {
            return [
                'success' => false,
                'message' => __('Voucher non trovato.', 'wceventsfp')
            ];
        }
        
        if ($voucher->status === self::STATUS_REDEEMED) {
            return [
                'success' => false,
                'message' => __('Non è possibile annullare un voucher già utilizzato.', 'wceventsfp')
            ];
        }
        
        if ($voucher->status === self::STATUS_CANCELLED) {
            return [
                'success' => false,
                'message' => __('Il voucher è già stato annullato.', 'wceventsfp')
            ];
        }
        
        // Update voucher status
        $updated = $wpdb->update(
            $wpdb->prefix . 'wcefp_vouchers',
            [
                'status' => self::STATUS_CANCELLED,
                'cancelled_date' => current_time('mysql'),
                'cancelled_by' => get_current_user_id()
            ],
            ['code' => $voucher_code],
            ['%s', '%s', '%d'],
            ['%s']
        );
        
        if ($updated === false) {
            return [
                'success' => false,
                'message' => __('Errore durante l\'aggiornamento del database.', 'wceventsfp')
            ];
        }
        
        $this->log_success('Voucher cancelled', [
            'voucher_code' => $voucher_code,
            'cancelled_by' => get_current_user_id()
        ]);
        
        return [
            'success' => true,
            'data' => [
                'message' => __('Voucher annullato con successo.', 'wceventsfp'),
                'new_status' => self::STATUS_CANCELLED
            ]
        ];
    }
    
    /**
     * Resend voucher email
     * 
     * @param string $voucher_code Voucher code
     * @return array Result array with success status and data/message
     */
    private function resend_voucher_email($voucher_code) {
        global $wpdb;
        
        // Get voucher details
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_vouchers WHERE code = %s",
            $voucher_code
        ));
        
        if (!$voucher) {
            return [
                'success' => false,
                'message' => __('Voucher non trovato.', 'wceventsfp')
            ];
        }
        
        if (empty($voucher->recipient_email) || !is_email($voucher->recipient_email)) {
            return [
                'success' => false,
                'message' => __('Email del destinatario non valida.', 'wceventsfp')
            ];
        }
        
        // Prepare voucher data for email
        $voucher_data = [
            'code' => $voucher->code,
            'amount' => $voucher->amount,
            'message' => $voucher->message ?? '',
            'sender_name' => $voucher->sender_name ?? '',
            'expiry_date' => $voucher->expiry_date ?? '',
            'url' => $this->get_voucher_url($voucher->code)
        ];
        
        $recipient = [
            'email' => $voucher->recipient_email,
            'name' => $voucher->recipient_name ?? ''
        ];
        
        // Send email
        $email_sent = $this->email_manager->send_voucher_notification($voucher_data, $recipient);
        
        if ($email_sent) {
            // Update voucher with new email sent date
            $wpdb->update(
                $wpdb->prefix . 'wcefp_vouchers',
                [
                    'email_resent_date' => current_time('mysql'),
                    'email_resent_count' => $voucher->email_resent_count + 1
                ],
                ['code' => $voucher_code],
                ['%s', '%d'],
                ['%s']
            );
            
            $this->log_success('Voucher email resent', [
                'voucher_code' => $voucher_code,
                'recipient' => $recipient['email']
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'message' => __('Email del voucher inviata nuovamente.', 'wceventsfp')
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => __('Errore durante l\'invio dell\'email.', 'wceventsfp')
            ];
        }
    }
    
    /**
     * Get detailed voucher information
     * 
     * @param string $voucher_code Voucher code
     * @return array Result array with success status and voucher data
     */
    private function get_voucher_details($voucher_code) {
        global $wpdb;
        
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_vouchers WHERE code = %s",
            $voucher_code
        ));
        
        if (!$voucher) {
            return [
                'success' => false,
                'message' => __('Voucher non trovato.', 'wceventsfp')
            ];
        }
        
        // Get usage history if redeemed
        $usage_history = [];
        if ($voucher->status === self::STATUS_REDEEMED) {
            $usage_history = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcefp_voucher_usage WHERE voucher_code = %s ORDER BY used_date DESC",
                $voucher_code
            ));
        }
        
        return [
            'success' => true,
            'data' => [
                'voucher' => $voucher,
                'usage_history' => $usage_history,
                'formatted_amount' => wc_price($voucher->amount),
                'status_label' => $this->get_status_label($voucher->status),
                'can_cancel' => in_array($voucher->status, [self::STATUS_ACTIVE]),
                'can_resend' => !empty($voucher->recipient_email)
            ]
        ];
    }
    
    /**
     * Get voucher analytics
     * 
     * @return array Analytics data
     */
    private function get_voucher_analytics() {
        global $wpdb;
        
        // Get basic stats
        $stats = $wpdb->get_results(
            "SELECT 
                status,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total_value
             FROM {$wpdb->prefix}wcefp_vouchers 
             GROUP BY status"
        );
        
        // Get monthly creation stats (last 12 months)
        $monthly_stats = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(created_date, '%Y-%m') as month,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total_value
             FROM {$wpdb->prefix}wcefp_vouchers 
             WHERE created_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(created_date, '%Y-%m')
             ORDER BY month DESC"
        );
        
        // Get redemption rate
        $total_vouchers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers"
        );
        $redeemed_vouchers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE status = 'redeemed'"
        );
        
        $redemption_rate = $total_vouchers > 0 ? ($redeemed_vouchers / $total_vouchers) * 100 : 0;
        
        return [
            'status_breakdown' => $stats,
            'monthly_stats' => $monthly_stats,
            'redemption_rate' => round($redemption_rate, 2),
            'total_vouchers' => (int) $total_vouchers,
            'active_vouchers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE status = 'active'"),
            'expired_vouchers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE status = 'expired'")
        ];
    }
    
    /**
     * Render comprehensive voucher admin page
     * 
     * @return void
     */
    public function render_admin_page(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        // Handle voucher actions first
        $this->handle_voucher_actions();
        
        // Check if vouchers feature is enabled and has data
        $vouchers_enabled = $this->is_vouchers_feature_enabled();
        $has_vouchers = $this->has_voucher_data();
        
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Vouchers Management', 'wceventsfp') . '</h1>';
        
        // Show onboarding if feature is off or no data
        if (!$vouchers_enabled || !$has_vouchers) {
            $this->render_voucher_onboarding($vouchers_enabled, $has_vouchers);
        } else {
            // Render full voucher management interface
            $this->render_voucher_management();
        }
        
        echo '</div>';
    }
    
    /**
     * Check if vouchers feature is enabled
     * 
     * @return bool
     */
    private function is_vouchers_feature_enabled(): bool {
        $settings = get_option('wcefp_features_settings', []);
        return !empty($settings['enable_vouchers']);
    }
    
    /**
     * Check if voucher data exists in database
     * 
     * @return bool
     */
    private function has_voucher_data(): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        return (int) $count > 0;
    }
    
    /**
     * Render onboarding interface
     * 
     * @param bool $vouchers_enabled Whether vouchers are enabled
     * @param bool $has_vouchers Whether voucher data exists
     * @return void
     */
    private function render_voucher_onboarding(bool $vouchers_enabled, bool $has_vouchers): void {
        ?>
        <div class="wcefp-onboarding">
            <div class="notice notice-info inline" style="padding: 20px; margin: 20px 0;">
                <h2><?php esc_html_e('Voucher Gift System Setup', 'wceventsfp'); ?></h2>
                
                <?php if (!$vouchers_enabled): ?>
                    <p><?php esc_html_e('The voucher gift system is currently disabled. Enable it in the plugin settings to start managing gift vouchers for your events.', 'wceventsfp'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-settings&tab=features')); ?>" class="button button-primary">
                            <?php esc_html_e('Enable Vouchers in Settings', 'wceventsfp'); ?>
                        </a>
                    </p>
                
                <?php elseif (!$has_vouchers): ?>
                    <p><?php esc_html_e('No gift vouchers have been created yet. Vouchers are automatically generated when customers purchase gift vouchers from your event products.', 'wceventsfp'); ?></p>
                    
                    <h3><?php esc_html_e('Getting Started', 'wceventsfp'); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php esc_html_e('Configure your event products to allow gift voucher purchases', 'wceventsfp'); ?></li>
                        <li><?php esc_html_e('Customize voucher email templates in Communications settings', 'wceventsfp'); ?></li>
                        <li><?php esc_html_e('Set voucher validity periods and redemption rules', 'wceventsfp'); ?></li>
                    </ul>
                    
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-settings&tab=vouchers')); ?>" class="button button-primary">
                            <?php esc_html_e('Configure Voucher Settings', 'wceventsfp'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button">
                            <?php esc_html_e('Manage Event Products', 'wceventsfp'); ?>
                        </a>
                    </p>
                <?php endif; ?>
                
                <h3><?php esc_html_e('Voucher Features', 'wceventsfp'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 10px;">
                    <div class="feature-box" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h4><?php esc_html_e('Automatic Generation', 'wceventsfp'); ?></h4>
                        <p><?php esc_html_e('Vouchers are created automatically when gift purchases are completed', 'wceventsfp'); ?></p>
                    </div>
                    <div class="feature-box" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h4><?php esc_html_e('Email Delivery', 'wceventsfp'); ?></h4>
                        <p><?php esc_html_e('Send vouchers directly to recipients with customizable email templates', 'wceventsfp'); ?></p>
                    </div>
                    <div class="feature-box" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h4><?php esc_html_e('Redemption Tracking', 'wceventsfp'); ?></h4>
                        <p><?php esc_html_e('Track voucher usage, redemption dates, and remaining values', 'wceventsfp'); ?></p>
                    </div>
                    <div class="feature-box" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h4><?php esc_html_e('Bulk Management', 'wceventsfp'); ?></h4>
                        <p><?php esc_html_e('Regenerate codes, resend emails, and manage multiple vouchers at once', 'wceventsfp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render full voucher management interface
     * 
     * @return void
     */
    private function render_voucher_management(): void {
        // Include the existing voucher table if available
        if (!class_exists('WCEFP_Vouchers_Table')) {
            require_once WCEFP_PLUGIN_DIR . 'admin/class-wcefp-vouchers-table.php';
        }
        
        // Create enhanced voucher table instance
        $voucher_table = new WCEFP_Enhanced_Vouchers_Table();
        $voucher_table->prepare_items();
        
        // Render voucher statistics
        $this->render_voucher_statistics();
        
        // Render the list table
        ?>
        <form method="get" class="voucher-filters" style="margin: 20px 0;">
            <input type="hidden" name="page" value="wcefp-vouchers" />
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <?php
                    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
                    ?>
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'wceventsfp'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'wceventsfp'); ?></option>
                        <option value="redeemed" <?php selected($status_filter, 'redeemed'); ?>><?php esc_html_e('Redeemed', 'wceventsfp'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'wceventsfp'); ?></option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'wceventsfp'); ?></option>
                    </select>
                    
                    <input type="text" name="search" placeholder="<?php esc_attr_e('Search code or email...', 'wceventsfp'); ?>" 
                           value="<?php echo esc_attr(isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''); ?>" />
                    
                    <?php submit_button(__('Filter', 'wceventsfp'), 'action', 'filter', false); ?>
                    
                    <?php if (isset($_GET['search']) || isset($_GET['status'])): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-vouchers')); ?>" class="button">
                            <?php esc_html_e('Clear Filters', 'wceventsfp'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="alignright actions">
                    <a href="<?php echo esc_url(add_query_arg(['action' => 'export_csv'], admin_url('admin.php?page=wcefp-vouchers'))); ?>" class="button">
                        <?php esc_html_e('Export CSV', 'wceventsfp'); ?>
                    </a>
                </div>
            </div>
        </form>
        
        <form method="post" class="voucher-bulk-actions">
            <?php wp_nonce_field('wcefp_voucher_bulk_actions', 'wcefp_voucher_nonce'); ?>
            <input type="hidden" name="page" value="wcefp-vouchers" />
            <?php $voucher_table->display(); ?>
        </form>
        <?php
    }
    
    /**
     * Render voucher statistics overview
     * 
     * @return void
     */
    private function render_voucher_statistics(): void {
        $analytics = $this->get_voucher_analytics();
        
        if (empty($analytics['status_breakdown'])) {
            return;
        }
        
        // Calculate totals
        $total_value = 0;
        $status_counts = [];
        
        foreach ($analytics['status_breakdown'] as $stat) {
            $status_counts[$stat->status] = $stat->count;
            $total_value += $stat->total_value;
        }
        
        ?>
        <div class="wcefp-voucher-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php echo esc_html($analytics['total_vouchers']); ?></h3>
                <p style="margin: 0; font-weight: 500;"><?php esc_html_e('Total Vouchers', 'wceventsfp'); ?></p>
            </div>
            
            <div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #00a32a;"><?php echo esc_html($analytics['active_vouchers']); ?></h3>
                <p style="margin: 0; font-weight: 500;"><?php esc_html_e('Active Vouchers', 'wceventsfp'); ?></p>
            </div>
            
            <div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #d63638;"><?php echo esc_html($analytics['redemption_rate']); ?>%</h3>
                <p style="margin: 0; font-weight: 500;"><?php esc_html_e('Redemption Rate', 'wceventsfp'); ?></p>
            </div>
            
            <div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #784f7b;"><?php echo wc_price($total_value); ?></h3>
                <p style="margin: 0; font-weight: 500;"><?php esc_html_e('Total Value', 'wceventsfp'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle voucher page actions
     * 
     * @return void
     */
    private function handle_voucher_actions(): void {
        // Handle single voucher actions
        if (isset($_GET['action']) && isset($_GET['voucher_id']) && isset($_GET['_wpnonce'])) {
            $action = sanitize_text_field($_GET['action']);
            $voucher_id = absint($_GET['voucher_id']);
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'wcefp_voucher_action_' . $voucher_id)) {
                wp_die(__('Security check failed.', 'wceventsfp'));
            }
            
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('You do not have sufficient permissions.', 'wceventsfp'));
            }
            
            $result = $this->execute_voucher_action($action, $voucher_id);
            
            // Redirect with notice
            $redirect_url = remove_query_arg(['action', 'voucher_id', '_wpnonce']);
            $redirect_url = add_query_arg(['message' => $result['success'] ? 'action_success' : 'action_error'], $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['voucher_ids']) && is_array($_POST['voucher_ids'])) {
            if (!wp_verify_nonce($_POST['wcefp_voucher_nonce'], 'wcefp_voucher_bulk_actions')) {
                wp_die(__('Security check failed.', 'wceventsfp'));
            }
            
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('You do not have sufficient permissions.', 'wceventsfp'));
            }
            
            $action = sanitize_text_field($_POST['action']);
            $voucher_ids = array_map('absint', $_POST['voucher_ids']);
            
            $results = $this->execute_bulk_voucher_action($action, $voucher_ids);
            
            // Redirect with notice
            $redirect_url = remove_query_arg(['action', 'voucher_ids']);
            $message = $results['success_count'] > 0 ? 'bulk_success' : 'bulk_error';
            $redirect_url = add_query_arg(['message' => $message, 'count' => $results['success_count']], $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }
        
        // Display admin notices
        $this->display_voucher_notices();
    }
    
    /**
     * Execute single voucher action
     * 
     * @param string $action Action to execute
     * @param int $voucher_id Voucher ID
     * @return array Result array
     */
    private function execute_voucher_action(string $action, int $voucher_id): array {
        switch ($action) {
            case 'regenerate':
                return $this->regenerate_voucher_code_by_id($voucher_id);
            
            case 'resend':
                return $this->resend_voucher_email_by_id($voucher_id);
            
            case 'cancel':
                return $this->cancel_voucher_by_id($voucher_id);
            
            default:
                return ['success' => false, 'message' => __('Invalid action.', 'wceventsfp')];
        }
    }
    
    /**
     * Execute bulk voucher action
     * 
     * @param string $action Action to execute
     * @param array $voucher_ids Array of voucher IDs
     * @return array Result array with success count
     */
    private function execute_bulk_voucher_action(string $action, array $voucher_ids): array {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($voucher_ids as $voucher_id) {
            $result = $this->execute_voucher_action($action, $voucher_id);
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        return [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total_count' => count($voucher_ids)
        ];
    }
    
    /**
     * Display admin notices for voucher actions
     * 
     * @return void
     */
    private function display_voucher_notices(): void {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message_type = sanitize_text_field($_GET['message']);
        $count = isset($_GET['count']) ? absint($_GET['count']) : 0;
        
        switch ($message_type) {
            case 'action_success':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Voucher action completed successfully.', 'wceventsfp') . 
                     '</p></div>';
                break;
                
            case 'action_error':
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Voucher action failed. Please try again.', 'wceventsfp') . 
                     '</p></div>';
                break;
                
            case 'bulk_success':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(esc_html__('%d voucher(s) processed successfully.', 'wceventsfp'), $count) . 
                     '</p></div>';
                break;
                
            case 'bulk_error':
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Bulk action failed. Please try again.', 'wceventsfp') . 
                     '</p></div>';
                break;
        }
    }

    /**
     * Regenerate voucher code by ID
     * 
     * @param int $voucher_id Voucher ID
     * @return array Result array
     */
    private function regenerate_voucher_code_by_id(int $voucher_id): array {
        global $wpdb;
        
        // Generate new unique code
        $new_code = $this->generate_unique_voucher_code();
        
        $result = $wpdb->update(
            $wpdb->prefix . 'wcefp_vouchers',
            ['code' => $new_code, 'updated_at' => current_time('mysql')],
            ['id' => $voucher_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            do_action('wcefp_voucher_code_regenerated', $voucher_id, $new_code);
            return ['success' => true, 'message' => __('Voucher code regenerated successfully.', 'wceventsfp')];
        }
        
        return ['success' => false, 'message' => __('Failed to regenerate voucher code.', 'wceventsfp')];
    }
    
    /**
     * Resend voucher email by ID
     * 
     * @param int $voucher_id Voucher ID
     * @return array Result array
     */
    private function resend_voucher_email_by_id(int $voucher_id): array {
        global $wpdb;
        
        // Get voucher details
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_vouchers WHERE id = %d",
            $voucher_id
        ));
        
        if (!$voucher || empty($voucher->recipient_email)) {
            return ['success' => false, 'message' => __('Invalid voucher or missing email.', 'wceventsfp')];
        }
        
        // Send email using email manager
        $email_sent = $this->email_manager->send_voucher_email($voucher);
        
        if ($email_sent) {
            // Update last sent date
            $wpdb->update(
                $wpdb->prefix . 'wcefp_vouchers',
                ['last_sent_at' => current_time('mysql')],
                ['id' => $voucher_id],
                ['%s'],
                ['%d']
            );
            
            do_action('wcefp_voucher_email_resent', $voucher_id, $voucher->recipient_email);
            return ['success' => true, 'message' => __('Voucher email resent successfully.', 'wceventsfp')];
        }
        
        return ['success' => false, 'message' => __('Failed to send voucher email.', 'wceventsfp')];
    }
    
    /**
     * Cancel voucher by ID
     * 
     * @param int $voucher_id Voucher ID
     * @return array Result array
     */
    private function cancel_voucher_by_id(int $voucher_id): array {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'wcefp_vouchers',
            ['status' => self::STATUS_CANCELLED, 'cancelled_at' => current_time('mysql')],
            ['id' => $voucher_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            do_action('wcefp_voucher_cancelled', $voucher_id);
            return ['success' => true, 'message' => __('Voucher cancelled successfully.', 'wceventsfp')];
        }
        
        return ['success' => false, 'message' => __('Failed to cancel voucher.', 'wceventsfp')];
    }
    
    /**
     * Generate unique voucher code
     * 
     * @return string Unique voucher code
     */
    private function generate_unique_voucher_code(): string {
        global $wpdb;
        
        $attempts = 0;
        $max_attempts = 50;
        
        do {
            $code = 'WCEFP-' . strtoupper(wp_generate_password(8, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE code = %s",
                $code
            ));
            $attempts++;
        } while ($exists > 0 && $attempts < $max_attempts);
        
        return $code;
    }

    /**
     * Enhanced redeem shortcode with modern UI
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function enhanced_redeem_shortcode($atts) {
        $atts = shortcode_atts([
            'style' => 'modern',
            'show_status' => 'true',
            'success_redirect' => ''
        ], $atts);
        
        // Enqueue frontend assets
        wp_enqueue_script('wcefp-widgets');
        wp_enqueue_style('wcefp-widgets');
        
        // Also enqueue voucher manager for redeem functionality
        $this->enqueue_admin_scripts();
        
        // Update localization to include redeem-specific data
        wp_localize_script('wcefp-voucher-manager', 'wcefpVoucherManager', array_merge(
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_voucher_action'),
                'strings' => [
                    'confirm_cancel' => __('Sei sicuro di voler annullare questo voucher?', 'wceventsfp'),
                    'confirm_resend' => __('Vuoi inviare nuovamente l\'email del voucher?', 'wceventsfp'),
                    'voucher_cancelled' => __('Voucher annullato con successo.', 'wceventsfp'),
                    'email_resent' => __('Email del voucher inviata nuovamente.', 'wceventsfp'),
                    'error_occurred' => __('Si è verificato un errore. Riprova.', 'wceventsfp'),
                    'loading' => __('Caricamento...', 'wceventsfp'),
                    // Redeem-specific strings
                    'enter_voucher_code' => __('Inserisci un codice voucher.', 'wceventsfp'),
                    'verifying' => __('Verifica in corso...', 'wceventsfp'),
                    'verification_failed' => __('Errore durante la verifica del voucher.', 'wceventsfp'),
                    'network_error' => __('Errore di rete. Riprova.', 'wceventsfp'),
                ]
            ],
            [
                'successRedirect' => !empty($atts['success_redirect']) ? $atts['success_redirect'] : false
            ]
        ));
        
        ob_start();
        ?>
        <div class="wcefp-widget wcefp-voucher-redeem-widget" data-="<?php echo esc_attr(esc_attr($atts['style'])); ?>">
            <div class="wcefp-widget-header">
                <h3><?php _e('Riscatta il tuo Voucher', 'wceventsfp'); ?></h3>
                <p><?php _e('Inserisci il codice del tuo voucher per utilizzarlo.', 'wceventsfp'); ?></p>
            </div>
            
            <form class="wcefp-voucher-redeem-form" method="post">
                <?php wp_nonce_field('wcefp_voucher_redeem', 'wcefp_voucher_nonce'); ?>
                
                <div class="wcefp-form-group">
                    <label for="voucher_code"><?php _e('Codice Voucher', 'wceventsfp'); ?></label>
                    <input type="text" 
                           id="voucher_code" 
                           name="voucher_code" 
                           class="wcefp-input" 
                           placeholder="<?php esc_attr_e('Inserisci il codice...', 'wceventsfp'); ?>"
                           required>
                </div>
                
                <div class="wcefp-form-actions">
                    <button type="submit" class="wcefp-button wcefp-button-primary">
                        <?php _e('Verifica Voucher', 'wceventsfp'); ?>
                    </button>
                </div>
            </form>
            
            <?php if ($atts['show_status'] === 'true'): ?>
            <div class="wcefp-voucher-status" id="wcefp-voucher-status" style="display: none;">
                <!-- Status will be populated by JavaScript -->
            </div>
            <?php endif; ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Voucher status shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function voucher_status_shortcode($atts) {
        $atts = shortcode_atts([
            'code' => '',
            'style' => 'compact'
        ], $atts);
        
        if (empty($atts['code'])) {
            return '<p class="wcefp-error">' . __('Codice voucher richiesto.', 'wceventsfp') . '</p>';
        }
        
        $voucher_details = $this->get_voucher_details($atts['code']);
        
        if (!$voucher_details['success']) {
            return '<p class="wcefp-error">' . esc_html($voucher_details['message']) . '</p>';
        }
        
        $voucher = $voucher_details['data']['voucher'];
        
        ob_start();
        ?>
        <div class="wcefp-widget wcefp-voucher-status-widget" data-="<?php echo esc_attr(esc_attr($atts['style'])); ?>">
            <div class="wcefp-voucher-status-header">
                <h4><?php _e('Stato Voucher', 'wceventsfp'); ?></h4>
                <span class="wcefp-voucher-code"><?php echo esc_html($voucher->code); ?></span>
            </div>
            
            <div class="wcefp-voucher-details">
                <div class="wcefp-voucher-amount">
                    <strong><?php echo wc_price($voucher->amount); ?></strong>
                </div>
                
                <div class="wcefp-voucher-status wcefp-status-<?php echo esc_attr(esc_attr($voucher->status)); ?>">
                    <?php echo esc_html($this->get_status_label($voucher->status)); ?>
                </div>
                
                <?php if (!empty($voucher->expiry_date)): ?>
                <div class="wcefp-voucher-expiry">
                    <small>
                        <?php _e('Scade il:', 'wceventsfp'); ?>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($voucher->expiry_date))); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($voucher->message)): ?>
                <div class="wcefp-voucher-message">
                    <em><?php echo esc_html($voucher->message); ?></em>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add bulk actions to voucher admin table
     * 
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function add_voucher_bulk_actions($actions) {
        $actions['cancel_selected'] = __('Annulla selezionati', 'wceventsfp');
        $actions['resend_emails'] = __('Reinvia email selezionate', 'wceventsfp');
        return $actions;
    }
    
    /**
     * Handle bulk actions on voucher admin table
     * 
     * @param string $redirect_url Redirect URL
     * @param string $action_name Action name
     * @param array $voucher_ids Selected voucher IDs
     * @return string Modified redirect URL
     */
    public function handle_voucher_bulk_actions($redirect_url, $action_name, $voucher_ids) {
        if (empty($voucher_ids)) {
            return $redirect_url;
        }
        
        $processed = 0;
        $errors = 0;
        
        switch ($action_name) {
            case 'cancel_selected':
                foreach ($voucher_ids as $voucher_id) {
                    $voucher_code = $this->get_voucher_code_by_id($voucher_id);
                    if ($voucher_code) {
                        $result = $this->cancel_voucher($voucher_code);
                        if ($result['success']) {
                            $processed++;
                        } else {
                            $errors++;
                        }
                    }
                }
                
                $redirect_url = add_query_arg([
                    'cancelled' => $processed,
                    'errors' => $errors
                ], $redirect_url);
                break;
                
            case 'resend_emails':
                foreach ($voucher_ids as $voucher_id) {
                    $voucher_code = $this->get_voucher_code_by_id($voucher_id);
                    if ($voucher_code) {
                        $result = $this->resend_voucher_email($voucher_code);
                        if ($result['success']) {
                            $processed++;
                        } else {
                            $errors++;
                        }
                    }
                }
                
                $redirect_url = add_query_arg([
                    'emails_sent' => $processed,
                    'errors' => $errors
                ], $redirect_url);
                break;
        }
        
        return $redirect_url;
    }
    
    /**
     * Render voucher management modals
     */
    public function render_voucher_modals() {
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, 'voucher') === false) {
            return;
        }
        ?>
        
        <!-- Voucher Details Modal -->
        <div id="wcefp-voucher-details-modal" class="wcefp-modal" style="display: none;">
            <div class="wcefp-modal-content">
                <div class="wcefp-modal-header">
                    <h2><?php _e('Dettagli Voucher', 'wceventsfp'); ?></h2>
                    <button class="wcefp-modal-close">&times;</button>
                </div>
                <div class="wcefp-modal-body">
                    <div id="wcefp-voucher-details-content">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                </div>
                <div class="wcefp-modal-footer">
                    <button class="wcefp-button wcefp-button-secondary wcefp-modal-close">
                        <?php _e('Chiudi', 'wceventsfp'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Voucher Analytics Modal -->
        <div id="wcefp-voucher-analytics-modal" class="wcefp-modal" style="display: none;">
            <div class="wcefp-modal-content wcefp-modal-large">
                <div class="wcefp-modal-header">
                    <h2><?php _e('Statistiche Voucher', 'wceventsfp'); ?></h2>
                    <button class="wcefp-modal-close">&times;</button>
                </div>
                <div class="wcefp-modal-body">
                    <div id="wcefp-voucher-analytics-content">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                </div>
                <div class="wcefp-modal-footer">
                    <button class="wcefp-button wcefp-button-secondary wcefp-modal-close">
                        <?php _e('Chiudi', 'wceventsfp'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get voucher URL for display/printing
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
     * Update voucher status
     * 
     * @param string $voucher_code Voucher code
     * @param array $data Data to update
     * @return bool Success status
     */
    private function update_voucher_status($voucher_code, $data) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'wcefp_vouchers',
            $data,
            ['code' => $voucher_code],
            null,
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get voucher code by ID
     * 
     * @param int $voucher_id Voucher ID
     * @return string|false Voucher code or false if not found
     */
    private function get_voucher_code_by_id($voucher_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT code FROM {$wpdb->prefix}wcefp_vouchers WHERE id = %d",
            $voucher_id
        ));
    }
    
    /**
     * Get human-readable status label
     * 
     * @param string $status Status code
     * @return string Status label
     */
    private function get_status_label($status) {
        $labels = [
            self::STATUS_ACTIVE => __('Attivo', 'wceventsfp'),
            self::STATUS_REDEEMED => __('Utilizzato', 'wceventsfp'),
            self::STATUS_EXPIRED => __('Scaduto', 'wceventsfp'),
            self::STATUS_CANCELLED => __('Annullato', 'wceventsfp')
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Log success message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_success($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info($message, array_merge(['component' => 'VoucherManager'], $context));
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
            \WCEFP\Utils\Logger::error($message, array_merge(['component' => 'VoucherManager'], $context));
        }
    }
    
    /**
     * Check if voucher functionality is available
     * 
     * @return bool True if voucher system is available
     */
    public function is_available() {
        global $wpdb;
        
        // Check if voucher table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wcefp_vouchers'");
        
        return !empty($table_exists);
    }
}

/**
 * Enhanced Vouchers List Table
 * Extends WordPress WP_List_Table with requested columns and actions
 */
class WCEFP_Enhanced_Vouchers_Table extends \WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'voucher',
            'plural'   => 'vouchers',
            'ajax'     => false,
        ]);
    }
    
    /**
     * Get vouchers data with filters
     * 
     * @param int $per_page Items per page
     * @param int $page_number Current page number
     * @return array Vouchers data
     */
    public static function get_vouchers($per_page = 20, $page_number = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_vouchers';
        
        // Base query
        $where = '1=1';
        $params = [];
        
        // Status filter
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($_GET['status']);
        }
        
        // Search filter
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = sanitize_text_field($_GET['search']);
            $where .= ' AND (code LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s)';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Ordering
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_date';
        $order = (isset($_REQUEST['order']) && strtolower(sanitize_text_field($_REQUEST['order'])) === 'asc') ? 'ASC' : 'DESC';
        
        // Build final query
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page_number - 1) * $per_page;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Get total count of vouchers with filters
     * 
     * @return int Total count
     */
    public static function record_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_vouchers';
        
        $where = '1=1';
        $params = [];
        
        // Apply same filters as get_vouchers
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($_GET['status']);
        }
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = sanitize_text_field($_GET['search']);
            $where .= ' AND (code LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s)';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        
        if (!empty($params)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * No items message
     */
    public function no_items() {
        esc_html_e('No vouchers found.', 'wceventsfp');
    }
    
    /**
     * Get table columns - as requested: Codice, Ordine, Destinatario, Stato, Data invio, Azioni
     * 
     * @return array Column definitions
     */
    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'code'          => __('Codice', 'wceventsfp'), // Code
            'order'         => __('Ordine', 'wceventsfp'), // Order
            'recipient'     => __('Destinatario', 'wceventsfp'), // Recipient  
            'status'        => __('Stato', 'wceventsfp'), // Status
            'sent_date'     => __('Data invio', 'wceventsfp'), // Send Date
            'value'         => __('Valore', 'wceventsfp'), // Value
            'actions'       => __('Azioni', 'wceventsfp'), // Actions: Regenerate/Resend
        ];
    }
    
    /**
     * Get sortable columns
     * 
     * @return array Sortable column definitions
     */
    protected function get_sortable_columns() {
        return [
            'code'       => ['code', false],
            'order'      => ['order_id', false],
            'recipient'  => ['recipient_email', false],
            'status'     => ['status', false],
            'sent_date'  => ['last_sent_at', true],
            'value'      => ['amount', false],
        ];
    }
    
    /**
     * Column checkbox for bulk actions
     * 
     * @param array $item Row data
     * @return string Checkbox HTML
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="voucher_ids[]" value="%d" />', $item['id']);
    }
    
    /**
     * Default column display
     * 
     * @param array $item Row data
     * @param string $column_name Column name
     * @return string Column content
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'code':
                // Link to voucher detail view
                $actions = [];
                if ($item['status'] === 'active') {
                    $actions['edit'] = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(add_query_arg([
                            'page' => 'wcefp-vouchers',
                            'action' => 'view',
                            'voucher_id' => $item['id']
                        ], admin_url('admin.php'))),
                        esc_html__('View Details', 'wceventsfp')
                    );
                }
                
                $code_display = '<strong>' . esc_html($item['code']) . '</strong>';
                return $code_display . $this->row_actions($actions);
            
            case 'order':
                if (!empty($item['order_id'])) {
                    $order_url = admin_url('post.php?post=' . $item['order_id'] . '&action=edit');
                    return sprintf('<a href="%s">#%s</a>', esc_url($order_url), esc_html($item['order_id']));
                }
                return '—';
            
            case 'recipient':
                $recipient = '';
                if (!empty($item['recipient_name'])) {
                    $recipient .= esc_html($item['recipient_name']) . '<br>';
                }
                if (!empty($item['recipient_email'])) {
                    $recipient .= '<small>' . esc_html($item['recipient_email']) . '</small>';
                }
                return $recipient ?: '—';
            
            case 'status':
                return $this->get_status_display($item['status']);
            
            case 'sent_date':
                if (!empty($item['last_sent_at']) && $item['last_sent_at'] !== '0000-00-00 00:00:00') {
                    return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['last_sent_at']);
                } elseif (!empty($item['created_date'])) {
                    return mysql2date(get_option('date_format'), $item['created_date']) . '<br><small>' . esc_html__('(Created)', 'wceventsfp') . '</small>';
                }
                return '—';
            
            case 'value':
                return wc_price($item['amount'] ?? 0);
            
            case 'actions':
                return $this->get_action_buttons($item);
            
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }
    
    /**
     * Get status display with color coding
     * 
     * @param string $status Voucher status
     * @return string Formatted status HTML
     */
    private function get_status_display($status) {
        $status_labels = [
            'active'    => __('Active', 'wceventsfp'),
            'redeemed'  => __('Redeemed', 'wceventsfp'),
            'expired'   => __('Expired', 'wceventsfp'),
            'cancelled' => __('Cancelled', 'wceventsfp'),
            'used'      => __('Used', 'wceventsfp'), // Legacy status
        ];
        
        $status_colors = [
            'active'    => '#00a32a',
            'redeemed'  => '#0073aa',
            'expired'   => '#d63638',
            'cancelled' => '#646970',
            'used'      => '#0073aa',
        ];
        
        $label = $status_labels[$status] ?? ucfirst($status);
        $color = $status_colors[$status] ?? '#646970';
        
        return sprintf(
            '<span style="color: %s; font-weight: 500;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
    
    /**
     * Get action buttons for voucher - Rigenera/Reinvia as requested
     * 
     * @param array $item Voucher data
     * @return string Action buttons HTML
     */
    private function get_action_buttons($item) {
        $actions = [];
        
        // Regenerate action (Rigenera) - for active vouchers
        if (in_array($item['status'], ['active', 'expired'])) {
            $regenerate_url = wp_nonce_url(
                add_query_arg([
                    'page' => 'wcefp-vouchers',
                    'action' => 'regenerate',
                    'voucher_id' => $item['id']
                ], admin_url('admin.php')),
                'wcefp_voucher_action_' . $item['id']
            );
            
            $actions[] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                esc_url($regenerate_url),
                esc_attr__('Generate new voucher code', 'wceventsfp'),
                esc_html__('Rigenera', 'wceventsfp')
            );
        }
        
        // Resend action (Reinvia) - if email is available and voucher is not cancelled
        if (!empty($item['recipient_email']) && $item['status'] !== 'cancelled') {
            $resend_url = wp_nonce_url(
                add_query_arg([
                    'page' => 'wcefp-vouchers',
                    'action' => 'resend',
                    'voucher_id' => $item['id']
                ], admin_url('admin.php')),
                'wcefp_voucher_action_' . $item['id']
            );
            
            $actions[] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                esc_url($resend_url),
                esc_attr__('Resend voucher email to recipient', 'wceventsfp'),
                esc_html__('Reinvia', 'wceventsfp')
            );
        }
        
        // Cancel action for active vouchers
        if ($item['status'] === 'active') {
            $cancel_url = wp_nonce_url(
                add_query_arg([
                    'page' => 'wcefp-vouchers',
                    'action' => 'cancel',
                    'voucher_id' => $item['id']
                ], admin_url('admin.php')),
                'wcefp_voucher_action_' . $item['id']
            );
            
            $actions[] = sprintf(
                '<a href="%s" class="button button-small button-link-delete" title="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url($cancel_url),
                esc_attr__('Cancel this voucher', 'wceventsfp'),
                esc_attr__('Are you sure you want to cancel this voucher?', 'wceventsfp'),
                esc_html__('Cancella', 'wceventsfp')
            );
        }
        
        return implode(' ', $actions);
    }
    
    /**
     * Get bulk actions - as requested
     * 
     * @return array Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'regenerate' => __('Rigenera codice', 'wceventsfp'), // Regenerate code
            'resend'     => __('Reinvia email', 'wceventsfp'),   // Resend email
            'cancel'     => __('Cancella voucher', 'wceventsfp'), // Cancel vouchers
        ];
    }
    
    /**
     * Prepare table items
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Get columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Get data
        $data = self::get_vouchers($per_page, $current_page);
        $total_items = self::record_count();
        
        $this->items = $data;
        
        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}