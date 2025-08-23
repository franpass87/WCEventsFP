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