<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Commission Management System
 * Advanced commission and reseller management similar to what Bokun and Regiondo offer
 * Handles affiliate commissions, reseller tiers, and revenue sharing
 */
class WCEFP_Commission_Management {

    public static function init() {
        // Database tables
        add_action('init', [__CLASS__, 'create_commission_tables']);
        
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        
        // User roles and capabilities
        add_action('init', [__CLASS__, 'add_user_roles']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_create_reseller', [__CLASS__, 'ajax_create_reseller']);
        add_action('wp_ajax_wcefp_update_commission_rates', [__CLASS__, 'ajax_update_commission_rates']);
        add_action('wp_ajax_wcefp_generate_payout_report', [__CLASS__, 'ajax_generate_payout_report']);
        add_action('wp_ajax_wcefp_process_commission_payment', [__CLASS__, 'ajax_process_commission_payment']);
        
        // Hooks into booking process
        add_action('woocommerce_order_status_completed', [__CLASS__, 'calculate_commissions'], 10, 1);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'reverse_commissions'], 10, 1);
        
        // Shortcodes for resellers
        add_shortcode('wcefp_reseller_dashboard', [__CLASS__, 'reseller_dashboard_shortcode']);
        add_shortcode('wcefp_affiliate_link', [__CLASS__, 'affiliate_link_shortcode']);
        
        // Track referrals
        add_action('init', [__CLASS__, 'track_referrals']);
        
        // Meta boxes
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_commission_meta']);
    }

    public static function create_commission_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Reseller/Affiliate accounts
        $table_resellers = $wpdb->prefix . 'wcefp_resellers';
        $sql_resellers = "CREATE TABLE IF NOT EXISTS $table_resellers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            reseller_code varchar(50) NOT NULL,
            business_name varchar(255),
            tier varchar(50) DEFAULT 'bronze',
            status varchar(20) DEFAULT 'active',
            commission_rate decimal(5,2) DEFAULT 10.00,
            payment_method varchar(50) DEFAULT 'bank_transfer',
            payment_details longtext,
            minimum_payout decimal(10,2) DEFAULT 50.00,
            total_sales decimal(12,2) DEFAULT 0.00,
            total_commissions decimal(12,2) DEFAULT 0.00,
            paid_commissions decimal(12,2) DEFAULT 0.00,
            pending_commissions decimal(12,2) DEFAULT 0.00,
            signup_bonus decimal(10,2) DEFAULT 0.00,
            referral_count int DEFAULT 0,
            last_activity datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY reseller_code (reseller_code),
            KEY tier (tier),
            KEY status (status)
        ) $charset_collate;";

        // Commission transactions
        $table_commissions = $wpdb->prefix . 'wcefp_commissions';
        $sql_commissions = "CREATE TABLE IF NOT EXISTS $table_commissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            reseller_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            product_id bigint(20),
            commission_type varchar(50) NOT NULL,
            base_amount decimal(12,2) NOT NULL,
            commission_rate decimal(5,2) NOT NULL,
            commission_amount decimal(12,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            tier_bonus decimal(5,2) DEFAULT 0.00,
            performance_bonus decimal(10,2) DEFAULT 0.00,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime NULL,
            paid_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reseller_order (reseller_id, order_id),
            KEY reseller_id (reseller_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY commission_type (commission_type),
            KEY status (status),
            KEY created_at (created_at),
            FOREIGN KEY (reseller_id) REFERENCES {$wpdb->prefix}wcefp_resellers(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Payout history
        $table_payouts = $wpdb->prefix . 'wcefp_commission_payouts';
        $sql_payouts = "CREATE TABLE IF NOT EXISTS $table_payouts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            reseller_id bigint(20) NOT NULL,
            payout_amount decimal(12,2) NOT NULL,
            payout_method varchar(50) NOT NULL,
            payout_reference varchar(255),
            commission_ids text,
            status varchar(20) DEFAULT 'pending',
            processed_by bigint(20),
            processed_at datetime NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reseller_id (reseller_id),
            KEY status (status),
            KEY processed_at (processed_at)
        ) $charset_collate;";

        // Commission tiers configuration
        $table_tiers = $wpdb->prefix . 'wcefp_commission_tiers';
        $sql_tiers = "CREATE TABLE IF NOT EXISTS $table_tiers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tier_name varchar(50) NOT NULL,
            min_sales decimal(12,2) DEFAULT 0.00,
            base_commission_rate decimal(5,2) NOT NULL,
            tier_bonus decimal(5,2) DEFAULT 0.00,
            minimum_payout decimal(10,2) DEFAULT 50.00,
            payout_frequency varchar(20) DEFAULT 'monthly',
            benefits longtext,
            requirements longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tier_name (tier_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_resellers);
        dbDelta($sql_commissions);
        dbDelta($sql_payouts);
        dbDelta($sql_tiers);
        
        // Insert default tiers
        self::insert_default_tiers();
    }

    private static function insert_default_tiers() {
        global $wpdb;
        
        $default_tiers = [
            [
                'tier_name' => 'bronze',
                'min_sales' => 0,
                'base_commission_rate' => 8.00,
                'tier_bonus' => 0.00,
                'minimum_payout' => 50.00,
                'benefits' => json_encode(['Commissioni di base', 'Dashboard reseller']),
                'requirements' => json_encode(['Registrazione completata'])
            ],
            [
                'tier_name' => 'silver',
                'min_sales' => 2000,
                'base_commission_rate' => 12.00,
                'tier_bonus' => 2.00,
                'minimum_payout' => 25.00,
                'benefits' => json_encode(['Commissioni più alte', 'Bonus prestazioni', 'Supporto prioritario']),
                'requirements' => json_encode(['€2,000 di vendite in 3 mesi'])
            ],
            [
                'tier_name' => 'gold',
                'min_sales' => 10000,
                'base_commission_rate' => 15.00,
                'tier_bonus' => 3.00,
                'minimum_payout' => 10.00,
                'benefits' => json_encode(['Commissioni premium', 'Bonus extra', 'Account manager dedicato']),
                'requirements' => json_encode(['€10,000 di vendite in 6 mesi'])
            ],
            [
                'tier_name' => 'platinum',
                'min_sales' => 50000,
                'base_commission_rate' => 20.00,
                'tier_bonus' => 5.00,
                'minimum_payout' => 0.00,
                'benefits' => json_encode(['Commissioni massime', 'Bonus VIP', 'White label opzioni']),
                'requirements' => json_encode(['€50,000 di vendite annuali'])
            ]
        ];
        
        foreach ($default_tiers as $tier) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wcefp_commission_tiers WHERE tier_name = %s", $tier['tier_name']));
            if (!$exists) {
                $wpdb->insert($wpdb->prefix . 'wcefp_commission_tiers', $tier);
            }
        }
    }

    public static function add_user_roles() {
        // Add reseller role
        add_role('wcefp_reseller', 'Reseller WCEventsFP', [
            'read' => true,
            'wcefp_view_dashboard' => true,
            'wcefp_manage_bookings' => true
        ]);
        
        // Add capabilities to existing roles
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('wcefp_manage_commissions');
            $admin->add_cap('wcefp_process_payouts');
        }
        
        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            $shop_manager->add_cap('wcefp_manage_commissions');
            $shop_manager->add_cap('wcefp_process_payouts');
        }
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=wcefp_event',
            'Gestione Commissioni',
            'Commissioni',
            'wcefp_manage_commissions',
            'wcefp-commissions',
            [__CLASS__, 'commissions_page']
        );
    }

    public static function commissions_page() {
        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap wcefp-commissions-page">
            <h1>Gestione Commissioni e Resellers</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=overview" 
                   class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                   Dashboard
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=resellers" 
                   class="nav-tab <?php echo $active_tab === 'resellers' ? 'nav-tab-active' : ''; ?>">
                   Resellers
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=commissions" 
                   class="nav-tab <?php echo $active_tab === 'commissions' ? 'nav-tab-active' : ''; ?>">
                   Commissioni
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=payouts" 
                   class="nav-tab <?php echo $active_tab === 'payouts' ? 'nav-tab-active' : ''; ?>">
                   Pagamenti
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=tiers" 
                   class="nav-tab <?php echo $active_tab === 'tiers' ? 'nav-tab-active' : ''; ?>">
                   Livelli
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=settings" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                   Impostazioni
                </a>
            </nav>

            <div class="wcefp-tab-content">
                <?php
                switch ($active_tab) {
                    case 'overview':
                        self::render_overview_tab();
                        break;
                    case 'resellers':
                        self::render_resellers_tab();
                        break;
                    case 'commissions':
                        self::render_commissions_tab();
                        break;
                    case 'payouts':
                        self::render_payouts_tab();
                        break;
                    case 'tiers':
                        self::render_tiers_tab();
                        break;
                    case 'settings':
                        self::render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .wcefp-commissions-page .nav-tab-wrapper { margin-bottom: 20px; }
        .wcefp-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .wcefp-stat-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s;
        }
        .wcefp-stat-card:hover { transform: translateY(-5px); }
        .wcefp-stat-number { font-size: 2.5em; font-weight: bold; display: block; margin-bottom: 8px; }
        .wcefp-stat-label { font-size: 0.9em; opacity: 0.9; }
        .wcefp-reseller-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .wcefp-tier-badge { 
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;
        }
        .wcefp-tier-bronze { background: #cd7f32; color: white; }
        .wcefp-tier-silver { background: #c0c0c0; color: #333; }
        .wcefp-tier-gold { background: #ffd700; color: #333; }
        .wcefp-tier-platinum { background: #e5e4e2; color: #333; }
        </style>
        <?php
    }

    private static function render_overview_tab() {
        global $wpdb;
        
        // Get summary statistics
        $total_resellers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_resellers WHERE status = 'active'");
        $total_commissions = $wpdb->get_var("SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}wcefp_commissions WHERE status IN ('pending', 'approved')") ?: 0;
        $pending_payouts = $wpdb->get_var("SELECT COALESCE(SUM(commission_amount), 0) FROM {$wpdb->prefix}wcefp_commissions WHERE status = 'approved'") ?: 0;
        $total_sales = $wpdb->get_var("SELECT COALESCE(SUM(total_sales), 0) FROM {$wpdb->prefix}wcefp_resellers") ?: 0;
        
        // Get monthly performance
        $monthly_stats = $wpdb->get_results("
            SELECT 
                MONTH(created_at) as month,
                YEAR(created_at) as year,
                COUNT(*) as commission_count,
                SUM(commission_amount) as total_commissions
            FROM {$wpdb->prefix}wcefp_commissions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY year DESC, month DESC
        ");
        
        ?>
        <div class="wcefp-overview-content">
            <div class="wcefp-stats-grid">
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo number_format($total_resellers); ?></span>
                    <span class="wcefp-stat-label">Resellers Attivi</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($total_sales, 0); ?></span>
                    <span class="wcefp-stat-label">Vendite Totali</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($total_commissions, 0); ?></span>
                    <span class="wcefp-stat-label">Commissioni Totali</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($pending_payouts, 0); ?></span>
                    <span class="wcefp-stat-label">Da Pagare</span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
                <div class="wcefp-chart-section">
                    <h2>Performance Ultimi 6 Mesi</h2>
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <?php if (empty($monthly_stats)): ?>
                            <p style="text-align: center; color: #666; padding: 40px;">Nessun dato disponibile</p>
                        <?php else: ?>
                            <canvas id="commissionsChart" width="400" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wcefp-quick-actions">
                    <h2>Azioni Rapide</h2>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <a href="user-new.php?role=wcefp_reseller" class="button button-primary">
                            Aggiungi Reseller
                        </a>
                        <button onclick="generatePayoutReport()" class="button">
                            Genera Report Pagamenti
                        </button>
                        <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=payouts&action=bulk_payout" class="button">
                            Pagamento Batch
                        </a>
                        <a href="?post_type=wcefp_event&page=wcefp-commissions&tab=tiers" class="button">
                            Configura Livelli
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function generatePayoutReport() {
            if (confirm('Generare il report dei pagamenti per il mese corrente?')) {
                window.open('?post_type=wcefp_event&page=wcefp-commissions&action=export_payouts&format=csv', '_blank');
            }
        }
        </script>
        <?php
    }

    private static function render_resellers_tab() {
        global $wpdb;
        
        $resellers = $wpdb->get_results("
            SELECT r.*, u.display_name, u.user_email,
                   COUNT(c.id) as commission_count,
                   COALESCE(SUM(c.commission_amount), 0) as total_earned
            FROM {$wpdb->prefix}wcefp_resellers r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}wcefp_commissions c ON r.id = c.reseller_id
            GROUP BY r.id
            ORDER BY r.created_at DESC
        ");
        
        ?>
        <div class="wcefp-resellers-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2>Gestione Resellers</h2>
                <a href="user-new.php?role=wcefp_reseller" class="button button-primary">
                    Aggiungi Nuovo Reseller
                </a>
            </div>
            
            <?php if (empty($resellers)): ?>
                <div class="wcefp-empty-state" style="text-align: center; padding: 50px; color: #666;">
                    <h3>Nessun reseller registrato</h3>
                    <p>Inizia creando il primo account reseller per la tua rete di affiliati.</p>
                    <a href="user-new.php?role=wcefp_reseller" class="button button-primary">
                        Crea Primo Reseller
                    </a>
                </div>
            <?php else: ?>
                <div class="wcefp-reseller-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Reseller</th>
                                <th>Codice</th>
                                <th>Livello</th>
                                <th>Commissioni</th>
                                <th>Vendite Totali</th>
                                <th>Da Pagare</th>
                                <th>Ultimo Accesso</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resellers as $reseller): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($reseller->display_name ?: $reseller->business_name); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($reseller->user_email); ?></small>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">
                                            <?php echo esc_html($reseller->reseller_code); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="wcefp-tier-badge wcefp-tier-<?php echo $reseller->tier; ?>">
                                            <?php echo ucfirst($reseller->tier); ?>
                                        </span>
                                        <br><small><?php echo number_format($reseller->commission_rate, 1); ?>%</small>
                                    </td>
                                    <td>
                                        <?php echo intval($reseller->commission_count); ?> transazioni<br>
                                        <small>€<?php echo number_format($reseller->total_earned, 2); ?> totale</small>
                                    </td>
                                    <td>€<?php echo number_format($reseller->total_sales, 0); ?></td>
                                    <td>
                                        <strong style="color: #d63384;">€<?php echo number_format($reseller->pending_commissions, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($reseller->last_activity): ?>
                                            <?php echo human_time_diff(strtotime($reseller->last_activity), time()) . ' fa'; ?>
                                        <?php else: ?>
                                            <span style="color: #856404;">Mai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="button button-small" onclick="editReseller(<?php echo $reseller->id; ?>)">
                                                Modifica
                                            </button>
                                            <button class="button button-small" onclick="viewPerformance(<?php echo $reseller->id; ?>)">
                                                Performance
                                            </button>
                                            <?php if ($reseller->pending_commissions > 0): ?>
                                                <button class="button button-small button-primary" onclick="processPayment(<?php echo $reseller->id; ?>)">
                                                    Paga
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function editReseller(resellerId) {
            window.location.href = 'user-edit.php?user_id=' + resellerId;
        }
        
        function viewPerformance(resellerId) {
            window.open('?post_type=wcefp_event&page=wcefp-commissions&tab=commissions&reseller_id=' + resellerId, '_blank');
        }
        
        function processPayment(resellerId) {
            if (confirm('Procedere con il pagamento delle commissioni per questo reseller?')) {
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_process_commission_payment',
                        reseller_id: resellerId,
                        nonce: '<?php echo wp_create_nonce('wcefp_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Pagamento processato con successo!');
                            location.reload();
                        } else {
                            alert('Errore: ' + (response.data.msg || 'Errore sconosciuto'));
                        }
                    }
                });
            }
        }
        </script>
        <?php
    }

    private static function render_commissions_tab() {
        echo '<div class="wcefp-commissions-content"><h2>Cronologia Commissioni</h2><p>Lista dettagliata delle commissioni - Da implementare</p></div>';
    }

    private static function render_payouts_tab() {
        echo '<div class="wcefp-payouts-content"><h2>Gestione Pagamenti</h2><p>Cronologia e gestione dei pagamenti commissioni - Da implementare</p></div>';
    }

    private static function render_tiers_tab() {
        echo '<div class="wcefp-tiers-content"><h2>Configurazione Livelli</h2><p>Gestione dei livelli e commissioni - Da implementare</p></div>';
    }

    private static function render_settings_tab() {
        echo '<div class="wcefp-settings-content"><h2>Impostazioni Commissioni</h2><p>Configurazioni globali del sistema commissioni - Da implementare</p></div>';
    }

    // Commission calculation
    public static function calculate_commissions($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Check if order has events/experiences
        $has_events = false;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product_types = wp_get_object_terms($product_id, 'product_type', ['fields' => 'slugs']);
            if (array_intersect($product_types, ['wcefp_event', 'wcefp_experience'])) {
                $has_events = true;
                break;
            }
        }
        
        if (!$has_events) return;
        
        // Get referral information
        $referral_code = get_post_meta($order_id, '_wcefp_referral_code', true);
        if (empty($referral_code)) return;
        
        $reseller = self::get_reseller_by_code($referral_code);
        if (!$reseller) return;
        
        // Calculate commission
        $order_total = $order->get_total();
        $commission_rate = $reseller->commission_rate;
        $commission_amount = ($order_total * $commission_rate) / 100;
        
        // Save commission
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'wcefp_commissions', [
            'reseller_id' => $reseller->id,
            'order_id' => $order_id,
            'commission_type' => 'sale',
            'base_amount' => $order_total,
            'commission_rate' => $commission_rate,
            'commission_amount' => $commission_amount,
            'status' => 'approved'
        ]);
        
        // Update reseller totals
        $wpdb->update($wpdb->prefix . 'wcefp_resellers', [
            'total_sales' => $reseller->total_sales + $order_total,
            'pending_commissions' => $reseller->pending_commissions + $commission_amount
        ], ['id' => $reseller->id]);
    }

    public static function reverse_commissions($order_id) {
        global $wpdb;
        
        // Mark commissions as reversed
        $wpdb->update($wpdb->prefix . 'wcefp_commissions', [
            'status' => 'reversed'
        ], ['order_id' => $order_id]);
    }

    // Referral tracking
    public static function track_referrals() {
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $ref_code = sanitize_text_field($_GET['ref']);
            
            // Store in session
            WC()->session->set('wcefp_referral', $ref_code);
            
            // Store in cookie for longer tracking
            setcookie('wcefp_referral', $ref_code, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Add referral to order during checkout
        add_action('woocommerce_checkout_order_processed', function($order_id) {
            $referral = WC()->session->get('wcefp_referral') ?: ($_COOKIE['wcefp_referral'] ?? '');
            
            if (!empty($referral)) {
                update_post_meta($order_id, '_wcefp_referral_code', $referral);
            }
        });
    }

    // Shortcodes
    public static function reseller_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Devi essere loggato per vedere questa pagina.</p>';
        }
        
        $user_id = get_current_user_id();
        $reseller = self::get_reseller_by_user_id($user_id);
        
        if (!$reseller) {
            return '<p>Non sei registrato come reseller.</p>';
        }
        
        ob_start();
        ?>
        <div class="wcefp-reseller-dashboard">
            <h2>Dashboard Reseller</h2>
            
            <div class="wcefp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($reseller->total_sales, 0); ?></span>
                    <span class="wcefp-stat-label">Vendite Totali</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($reseller->pending_commissions, 2); ?></span>
                    <span class="wcefp-stat-label">Da Ricevere</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number">€<?php echo number_format($reseller->paid_commissions, 2); ?></span>
                    <span class="wcefp-stat-label">Già Pagato</span>
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Il Tuo Link Referral</h3>
                <p>Usa questo link per promuovere i nostri eventi e guadagnare commissioni:</p>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="text" value="<?php echo home_url('?ref=' . $reseller->reseller_code); ?>" 
                           readonly style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                           id="referral-link">
                    <button onclick="copyReferralLink()" class="button">Copia</button>
                </div>
            </div>
        </div>
        
        <script>
        function copyReferralLink() {
            document.getElementById('referral-link').select();
            document.execCommand('copy');
            alert('Link copiato!');
        }
        </script>
        <?php
        
        return ob_get_clean();
    }

    public static function affiliate_link_shortcode($atts) {
        $a = shortcode_atts(['product_id' => 0, 'text' => 'Promuovi questo evento'], $atts);
        
        if (!is_user_logged_in()) return '';
        
        $user_id = get_current_user_id();
        $reseller = self::get_reseller_by_user_id($user_id);
        
        if (!$reseller) return '';
        
        $product_url = get_permalink($a['product_id']);
        $affiliate_url = add_query_arg('ref', $reseller->reseller_code, $product_url);
        
        return sprintf('<a href="%s" class="wcefp-affiliate-link">%s</a>', 
            esc_url($affiliate_url), 
            esc_html($a['text'])
        );
    }

    // Helper methods
    private static function get_reseller_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcefp_resellers WHERE reseller_code = %s", $code));
    }

    private static function get_reseller_by_user_id($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcefp_resellers WHERE user_id = %d", $user_id));
    }

    // Meta boxes and admin functionality (simplified for space)
    public static function add_meta_boxes() {
        // Add meta box to orders showing commission info
        add_meta_box(
            'wcefp_commission_info',
            'Informazioni Commissioni',
            [__CLASS__, 'commission_info_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    public static function commission_info_meta_box($post) {
        $order_id = $post->ID;
        $referral_code = get_post_meta($order_id, '_wcefp_referral_code', true);
        
        if (empty($referral_code)) {
            echo '<p>Nessun referral per questo ordine.</p>';
            return;
        }
        
        $reseller = self::get_reseller_by_code($referral_code);
        echo '<p><strong>Referral:</strong> ' . esc_html($referral_code) . '</p>';
        
        if ($reseller) {
            echo '<p><strong>Reseller:</strong> ' . esc_html($reseller->business_name) . '</p>';
            echo '<p><strong>Commissione:</strong> ' . number_format($reseller->commission_rate, 1) . '%</p>';
        }
    }

    public static function save_commission_meta($post_id) {
        // Handle commission meta save if needed
    }

    // AJAX handlers (simplified)
    public static function ajax_create_reseller() {
        check_ajax_referer('wcefp_admin', 'nonce');
        wp_send_json_success(['msg' => 'Reseller creation - to implement']);
    }

    public static function ajax_update_commission_rates() {
        check_ajax_referer('wcefp_admin', 'nonce');
        wp_send_json_success(['msg' => 'Commission rates update - to implement']);
    }

    public static function ajax_generate_payout_report() {
        check_ajax_referer('wcefp_admin', 'nonce');
        wp_send_json_success(['msg' => 'Payout report generation - to implement']);
    }

    public static function ajax_process_commission_payment() {
        check_ajax_referer('wcefp_admin', 'nonce');
        wp_send_json_success(['msg' => 'Commission payment processing - to implement']);
    }
}

// Initialize the commission management system
WCEFP_Commission_Management::init();