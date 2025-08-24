<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Admin {

    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
        
        // Initialize new settings class
        if (class_exists('WCEFP_Admin_Settings')) {
            WCEFP_Admin_Settings::get_instance();
        }
    }

    /**
     * Register admin menu pages
     */
    public static function admin_menu() {
        $cap = 'manage_woocommerce';

        add_menu_page(
            __('Eventi & Degustazioni','wceventsfp'),
            __('Eventi & Degustazioni','wceventsfp'),
            $cap,
            'wcefp',
            [__CLASS__,'render_kpi_page'],
            'dashicons-calendar-alt',
            56
        );

        add_submenu_page('wcefp', __('Analisi KPI','wceventsfp'), __('Analisi KPI','wceventsfp'), $cap,'wcefp',[__CLASS__,'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista','wceventsfp'), __('Calendario & Lista','wceventsfp'), $cap,'wcefp-calendar',[__CLASS__,'render_calendar_page']);
        add_submenu_page('wcefp', __('Chiusure straordinarie','wceventsfp'), __('Chiusure straordinarie','wceventsfp'), $cap,'wcefp-closures',['WCEFP_Closures','render_admin_page']);
        add_submenu_page('wcefp', __('Performance','wceventsfp'), __('Performance','wceventsfp'), $cap,'wcefp-performance',[__CLASS__,'render_performance_page']);
        add_submenu_page('wcefp', __('Esporta','wceventsfp'), __('Esporta','wceventsfp'), $cap,'wcefp-export',[__CLASS__,'render_export_page']);
        add_submenu_page('wcefp', __('Impostazioni','wceventsfp'), __('Impostazioni','wceventsfp'), $cap,'wcefp-settings',[__CLASS__,'render_new_settings_page']);
    }

    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public static function enqueue_admin($hook) {
        $is_wcefp_page = strpos($hook, 'wcefp') !== false;
        $is_product_edit = in_array($hook, ['post.php', 'post-new.php'], true);
        if (!$is_wcefp_page && !$is_product_edit) return;

        wp_enqueue_style('wcefp-admin', WCEFP_PLUGIN_URL.'assets/css/admin.css', [], WCEFP_VERSION);
        wp_enqueue_style('wcefp-admin-enhanced', WCEFP_PLUGIN_URL.'assets/css/admin-enhanced.css', ['wcefp-admin'], WCEFP_VERSION);
        wp_enqueue_style('wcefp-admin-modern', WCEFP_PLUGIN_URL.'assets/css/admin-modern.css', ['wcefp-admin'], WCEFP_VERSION);
        
        // Enhanced UI Components
        wp_enqueue_style('wcefp-modern-components', WCEFP_PLUGIN_URL.'assets/css/modern-components.css', ['wcefp-admin'], WCEFP_VERSION);
        wp_enqueue_style('wcefp-advanced-analytics', WCEFP_PLUGIN_URL.'assets/css/advanced-analytics.css', ['wcefp-admin'], WCEFP_VERSION);
        
        // Product admin specific styles
        if ($is_product_edit) {
            wp_enqueue_style('wcefp-product-admin-enhanced', WCEFP_PLUGIN_URL.'assets/css/product-admin-enhanced.css', ['wcefp-admin-modern'], WCEFP_VERSION);
        }

        // Settings page specific assets
        if ($is_wcefp_page && strpos($hook,'wcefp_page_wcefp-settings') !== false) {
            wp_enqueue_style('wcefp-admin-settings', WCEFP_PLUGIN_URL.'assets/css/admin-settings.css', ['wcefp-admin'], WCEFP_VERSION);
            wp_enqueue_script('wcefp-admin-settings', WCEFP_PLUGIN_URL.'assets/js/admin-settings.js', ['jquery'], WCEFP_VERSION, true);
        }

        // Chart.js for advanced analytics - only load on pages that need it
        $needs_charts = $is_wcefp_page && (
            strpos($hook, 'wcefp_page_wcefp') !== false || 
            strpos($hook, 'wcefp_page_wcefp-analytics') !== false ||
            $hook === 'toplevel_page_wcefp' // Main dashboard page
        );
        
        if ($needs_charts) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js', [], '4.4.0', true);
        }
        
        // FullCalendar solo nella pagina calendario
        if ($is_wcefp_page && strpos($hook,'wcefp_page_wcefp-calendar') !== false) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css', [], '6.1.15');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true);
        }

        $deps = ['jquery'];
        if ($needs_charts) {
            $deps[] = 'chartjs';
        }
        if ($is_wcefp_page && strpos($hook,'wcefp_page_wcefp-calendar') !== false) $deps[] = 'fullcalendar';

        // JS admin principale
        wp_enqueue_script('wcefp-admin', WCEFP_PLUGIN_URL.'assets/js/admin.js', $deps, WCEFP_VERSION, true);
        wp_enqueue_script('wcefp-admin-enhanced', WCEFP_PLUGIN_URL.'assets/js/admin-enhanced.js', array_merge($deps, ['wcefp-admin']), WCEFP_VERSION, true);
        wp_enqueue_script('wcefp-admin-enhanced-ui', WCEFP_PLUGIN_URL.'assets/js/admin-enhanced-ui.js', array_merge($deps, ['wcefp-admin']), WCEFP_VERSION, true);
        
        // Enhanced features scripts
        wp_enqueue_script('wcefp-advanced-analytics', WCEFP_PLUGIN_URL.'assets/js/advanced-analytics.js', array_merge($deps, ['wcefp-admin']), WCEFP_VERSION, true);
        wp_localize_script('wcefp-admin','WCEFPAdmin',[
            'ajaxUrl'=> admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('wcefp_admin'),
            'products' => self::get_events_products_for_filter(),
        ]);

        // JS chiusure
        if ($is_wcefp_page && strpos($hook,'wcefp_page_wcefp-closures') !== false) {
            wp_enqueue_script('wcefp-closures', WCEFP_PLUGIN_URL.'assets/js/closures.js', ['jquery'], WCEFP_VERSION, true);
            wp_localize_script('wcefp-closures','WCEFPClose',[
                'ajaxUrl'=> admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce('wcefp_admin'),
                'products' => self::get_events_products_for_filter(),
            ]);
        }
    }

    /**
     * Get events products for filter dropdown
     * 
     * @return array Array of products with ID and title
     */
    private static function get_events_products_for_filter(){
        $q = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 300,
            'post_status' => 'publish',
            'tax_query' => [[
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['wcefp_event','wcefp_experience'],
                'operator' => 'IN',
            ]],
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);
        $out = [];
        foreach ($q->posts as $p) $out[] = ['id'=>$p->ID,'title'=>$p->post_title];
        return $out;
    }

    /* ---------- Pagine ---------- */

    /**
     * Render the KPI analytics page in the admin dashboard.
     */
    public static function render_kpi_page() {
        if (!current_user_can('manage_woocommerce')) return;

        $kpi = self::get_kpi(30); // ultimi 30 giorni
        $recent_bookings = self::get_recent_bookings(7); // ultimi 7 giorni
        $next_occurrences = self::get_next_occurrences(5); // prossime 5
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Dashboard Eventi','wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Panoramica delle performance e attività recenti dei tuoi eventi ed esperienze.', 'wceventsfp'); ?>
                </p>
            </div>
            
            <!-- Compact KPI Grid -->
            <div class="wcefp-kpi-compact">
                <div class="wcefp-kpi-card">
                    <div class="wcefp-kpi-header">
                        <h3><?php _e('Ordini (30gg)','wceventsfp'); ?></h3>
                        <div class="wcefp-kpi-icon">📈</div>
                    </div>
                    <p class="wcefp-kpi-value"><?php echo esc_html($kpi['orders_30']); ?></p>
                </div>
                
                <div class="wcefp-kpi-card">
                    <div class="wcefp-kpi-header">
                        <h3><?php _e('Ricavi (30gg)','wceventsfp'); ?></h3>
                        <div class="wcefp-kpi-icon">💰</div>
                    </div>
                    <p class="wcefp-kpi-value">€ <?php echo number_format($kpi['revenue_30'],2,',','.'); ?></p>
                </div>
                
                <div class="wcefp-kpi-card">
                    <div class="wcefp-kpi-header">
                        <h3><?php _e('Riempimento','wceventsfp'); ?></h3>
                        <div class="wcefp-kpi-icon">🎯</div>
                    </div>
                    <p class="wcefp-kpi-value"><?php echo esc_html($kpi['fill_rate']); ?>%</p>
                </div>
            </div>
            
            <div class="wcefp-dashboard-grid">
                <!-- Recent Bookings -->
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3><?php _e('Prenotazioni Recenti (7gg)','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <?php if (!empty($recent_bookings)): ?>
                            <table class="wcefp-compact-table">
                                <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($booking['customer_name']); ?></strong><br>
                                        <small><?php echo esc_html($booking['event_title']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($booking['booking_date']); ?><br>
                                        <small>€ <?php echo number_format($booking['total'], 2, ',', '.'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <p><?php _e('Nessuna prenotazione negli ultimi 7 giorni.', 'wceventsfp'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Next Occurrences -->
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3><?php _e('Prossimi Eventi','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <?php if (!empty($next_occurrences)): ?>
                            <table class="wcefp-compact-table">
                                <?php foreach ($next_occurrences as $occurrence): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($occurrence['event_title']); ?></strong><br>
                                        <small><?php echo esc_html($occurrence['date_time']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($occurrence['booked']); ?>/<?php echo esc_html($occurrence['capacity']); ?><br>
                                        <small><?php echo esc_html($occurrence['status']); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <p><?php _e('Nessun evento programmato.', 'wceventsfp'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="wcefp-card">
                <div class="wcefp-card-header">
                    <h3><?php _e('Azioni Rapide', 'wceventsfp'); ?></h3>
                </div>
                <div class="wcefp-card-body">
                    <div class="wcefp-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=wcefp-calendar'); ?>" class="wcefp-btn wcefp-btn-primary">
                            📅 <?php _e('Calendario', 'wceventsfp'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="wcefp-btn wcefp-btn-secondary">
                            🎯 <?php _e('Nuovo Evento', 'wceventsfp'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wcefp-export'); ?>" class="wcefp-btn wcefp-btn-success">
                            📊 <?php _e('Esporta', 'wceventsfp'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wcefp-settings'); ?>" class="wcefp-btn wcefp-btn-warning">
                            ⚙️ <?php _e('Impostazioni', 'wceventsfp'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Gather KPI metrics for event products over a period.
     *
     * @param int $days Number of days to analyze.
     * @return array KPI values.
     */
    private static function get_kpi($days = 30) {
        $from = (new DateTime("-{$days} days"))->format('Y-m-d H:i:s');

        // 1) Ordini & Ricavi SOLO per articoli evento/esperienza
        $orders = wc_get_orders([
            'limit'        => -1,
            'type'         => 'shop_order',
            'status'       => ['wc-processing','wc-completed','wc-on-hold'],
            'date_created' => '>=' . $from,
            'return'       => 'objects',
        ]);

        $orders_with_events = 0;
        $revenue_events = 0.0;
        $product_counters = []; // product_id => qty

        foreach ($orders as $order) {
            $has_event = false;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $type = $product->get_type();
                if ($type === 'wcefp_event' || $type === 'wcefp_experience') {
                    $has_event = true;
                    // somma solo il totale linea degli articoli evento/esperienza
                    $revenue_events += (float) $order->get_item_total($item, false) * (int) $item->get_quantity();

                    $pid = $product->get_id();
                    $product_counters[$pid] = ($product_counters[$pid] ?? 0) + (int)$item->get_quantity();
                }
            }
            if ($has_event) {
                $orders_with_events++;
            }
        }

        // 2) Top prodotto (per quantità vendute)
        $top_product = '';
        if (!empty($product_counters)) {
            arsort($product_counters);
            $top_id = array_key_first($product_counters);
            $top_product = get_the_title($top_id);
        }

        // 3) Riempimento medio = (posti prenotati / posti totali) nel periodo
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_occurrences';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(capacity) AS cap, SUM(booked) AS bkd
             FROM $tbl
             WHERE start_datetime >= %s AND status IN ('active','cancelled')",
                $from
            ), ARRAY_A
        );
        $cap = (int)($row['cap'] ?? 0);
        $bkd = (int)($row['bkd'] ?? 0);
        $fill_rate = ($cap > 0) ? round(($bkd / $cap) * 100) : 0;

        return [
            'orders_30'  => (int)$orders_with_events,
            'revenue_30' => (float)$revenue_events,
            'fill_rate'  => (int)$fill_rate,
            'top_product'=> $top_product,
        ];
    }

    /**
     * Get recent bookings for dashboard
     *
     * @param int $days Number of days to look back
     * @return array Recent bookings
     */
    private static function get_recent_bookings($days = 7) {
        $from = (new DateTime("-{$days} days"))->format('Y-m-d H:i:s');

        $orders = wc_get_orders([
            'limit'        => 10,
            'type'         => 'shop_order',
            'status'       => ['wc-processing','wc-completed','wc-on-hold'],
            'date_created' => '>=' . $from,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'return'       => 'objects',
        ]);

        $bookings = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $type = $product->get_type();
                if ($type === 'wcefp_event' || $type === 'wcefp_experience') {
                    $bookings[] = [
                        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'event_title' => $product->get_name(),
                        'booking_date' => $order->get_date_created()->format('d/m/Y H:i'),
                        'total' => (float) $item->get_total()
                    ];
                }
            }
            if (count($bookings) >= 5) break; // Limit to 5 entries
        }

        return $bookings;
    }

    /**
     * Get next occurrences for dashboard
     *
     * @param int $limit Number of occurrences to return
     * @return array Next occurrences
     */
    private static function get_next_occurrences($limit = 5) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_occurrences';
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, p.post_title as event_title
                 FROM $tbl o
                 LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
                 WHERE o.start_datetime >= NOW() AND o.status = 'active'
                 ORDER BY o.start_datetime ASC
                 LIMIT %d",
                $limit
            ), ARRAY_A
        );

        $occurrences = [];
        foreach ($rows as $row) {
            $datetime = new DateTime($row['start_datetime']);
            $occurrences[] = [
                'event_title' => $row['event_title'] ?: 'Unknown Event',
                'date_time' => $datetime->format('d/m/Y H:i'),
                'capacity' => (int)$row['capacity'],
                'booked' => (int)$row['booked_seats'],
                'status' => ucfirst($row['status'])
            ];
        }

        return $occurrences;
    }


    /**
     * Render the calendar view page
     */
    public static function render_calendar_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Calendario & Lista Prenotazioni','wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Gestisci gli slot temporali e visualizza tutte le prenotazioni in un\'interfaccia calendario intuitiva.', 'wceventsfp'); ?>
                </p>
                <div class="wcefp-page-meta">
                    <span class="wcefp-page-badge">
                        📅 <?php _e('Vista Calendario', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-page-badge">
                        📋 <?php _e('Gestione Slot', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
            
            <div class="wcefp-toolbar">
                <label><?php _e('Filtra prodotto','wceventsfp'); ?>:</label>
                <select id="wcefp-filter-product">
                    <option value="0"><?php _e('Tutti i prodotti','wceventsfp'); ?></option>
                </select>
                <button class="wcefp-btn wcefp-btn-primary" id="wcefp-switch-calendar">
                    📅 <?php _e('Vista Calendario','wceventsfp'); ?>
                </button>
                <button class="wcefp-btn wcefp-btn-secondary" id="wcefp-switch-list">
                    📋 <?php _e('Vista Lista','wceventsfp'); ?>
                </button>
            </div>
            
            <div class="wcefp-card">
                <div id="wcefp-view" style="min-height:650px;"></div>
            </div>
        </div><?php
    }

    /**
     * Render the export data page
     */
    public static function render_export_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Esporta Dati','wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Scarica i dati delle tue occorrenze e prenotazioni per analisi avanzate o backup.', 'wceventsfp'); ?>
                </p>
                <div class="wcefp-page-meta">
                    <span class="wcefp-page-badge">
                        📊 <?php _e('Export CSV', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-page-badge">
                        💾 <?php _e('Backup Dati', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3 class="wcefp-card-title">📅 <?php _e('Occorrenze','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <p><?php _e('Esporta tutti gli slot temporali disponibili con informazioni su capacità, prenotazioni e stato.', 'wceventsfp'); ?></p>
                    </div>
                    <div class="wcefp-card-footer">
                        <a class="wcefp-btn wcefp-btn-primary" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_occurrences'), 'wcefp_export') ); ?>">
                            📥 <?php _e('Scarica Occorrenze','wceventsfp'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3 class="wcefp-card-title">🎫 <?php _e('Prenotazioni','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <p><?php _e('Esporta tutte le prenotazioni dei clienti con dettagli ordini, date e informazioni di contatto.', 'wceventsfp'); ?></p>
                    </div>
                    <div class="wcefp-card-footer">
                        <a class="wcefp-btn wcefp-btn-success" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_bookings'), 'wcefp_export') ); ?>">
                            📥 <?php _e('Scarica Prenotazioni','wceventsfp'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="wcefp-card">
                <div class="wcefp-card-header">
                    <h3 class="wcefp-card-title">ℹ️ <?php _e('Informazioni Export','wceventsfp'); ?></h3>
                </div>
                <div class="wcefp-card-body">
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <li><?php _e('I file sono esportati in formato CSV per compatibilità con Excel e altri software di analisi', 'wceventsfp'); ?></li>
                        <li><?php _e('I dati includono solo elementi pubblicati e attivi', 'wceventsfp'); ?></li>
                        <li><?php _e('L\'esportazione potrebbe richiedere alcuni secondi per grandi quantità di dati', 'wceventsfp'); ?></li>
                    </ul>
                </div>
            </div>
        </div><?php
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;

        /* Salvataggio */
        if (isset($_POST['wcefp_save']) && check_admin_referer('wcefp_settings')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint()
            update_option('wcefp_default_capacity', absint($_POST['wcefp_default_capacity'] ?? 0));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean sanitization via isset check
            update_option('wcefp_disable_wc_emails_for_events', isset($_POST['wcefp_disable_wc_emails_for_events']) ? '1' : '0');

            // Prezzi dinamici - preserve as text field but sanitize
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via wp_unslash and further processing
            $rules_raw = wp_unslash($_POST['wcefp_price_rules'] ?? '');
            update_option('wcefp_price_rules', sanitize_textarea_field($rules_raw));

            // Brevo - all fields properly sanitized
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_brevo_api_key', sanitize_text_field($_POST['wcefp_brevo_api_key'] ?? ''));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint()
            update_option('wcefp_brevo_template_id', absint($_POST['wcefp_brevo_template_id'] ?? 0));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_email
            update_option('wcefp_brevo_from_email', sanitize_email($_POST['wcefp_brevo_from_email'] ?? ''));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_brevo_from_name', sanitize_text_field($_POST['wcefp_brevo_from_name'] ?? ''));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint()
            update_option('wcefp_brevo_list_it', absint($_POST['wcefp_brevo_list_it'] ?? 0));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint()
            update_option('wcefp_brevo_list_en', absint($_POST['wcefp_brevo_list_en'] ?? 0));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_brevo_tag', sanitize_text_field($_POST['wcefp_brevo_tag'] ?? ''));

            // Tracking - all fields properly sanitized
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean sanitization via isset check
            update_option('wcefp_ga4_enable', isset($_POST['wcefp_ga4_enable']) ? '1' : '0');
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_ga4_id', sanitize_text_field($_POST['wcefp_ga4_id'] ?? ''));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_gtm_id', sanitize_text_field($_POST['wcefp_gtm_id'] ?? ''));
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field
            update_option('wcefp_meta_pixel_id', sanitize_text_field($_POST['wcefp_meta_pixel_id'] ?? ''));

            echo '<div class="updated"><p>Salvato.</p></div>';
        }

        /* Lettura */
        $cap = get_option('wcefp_default_capacity', 0);
        $dis = get_option('wcefp_disable_wc_emails_for_events','0')==='1';
        $price_rules = get_option('wcefp_price_rules','[]');
        $api = get_option('wcefp_brevo_api_key','');
        $tpl = intval(get_option('wcefp_brevo_template_id', 0));
        $from_email = get_option('wcefp_brevo_from_email','');
        $from_name  = get_option('wcefp_brevo_from_name','');
        $list_it    = intval(get_option('wcefp_brevo_list_it', 0));
        $list_en    = intval(get_option('wcefp_brevo_list_en', 0));
        $tag        = get_option('wcefp_brevo_tag','');
        $ga4_en     = get_option('wcefp_ga4_enable','1')==='1';
        $ga4_id     = get_option('wcefp_ga4_id','');
        $gtm_id     = get_option('wcefp_gtm_id','');
        $mp_id      = get_option('wcefp_meta_pixel_id',''); ?>

        <div class="wrap">
            <h1><?php _e('Impostazioni','wceventsfp'); ?></h1>
            <form method="post"><?php wp_nonce_field('wcefp_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="wcefp_default_capacity"><?php _e('Capienza default per slot','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_default_capacity" id="wcefp_default_capacity" value="<?php echo esc_attr($cap); ?>" min="0" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email WooCommerce','wceventsfp'); ?></th>
                        <td><label><input type="checkbox" name="wcefp_disable_wc_emails_for_events" <?php checked($dis,true); ?> /> <?php _e('Disattiva email Woo per ordini SOLO-evento/esperienza','wceventsfp'); ?></label></td>
                    </tr>

                    <tr>
                        <th><label for="wcefp_price_rules"><?php _e('Regole prezzo (JSON)','wceventsfp'); ?></label></th>
                        <td>
                            <textarea name="wcefp_price_rules" id="wcefp_price_rules" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($price_rules); ?></textarea>
                            <p class="description"><?php _e('Esempio: [{"date_from":"2024-06-01","date_to":"2024-09-30","weekdays":[5,6],"type":"percent","value":10}]','wceventsfp'); ?></p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h3><?php _e('Brevo (API v3)','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_brevo_api_key"><?php _e('API Key','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_api_key" id="wcefp_brevo_api_key" value="<?php echo esc_attr($api); ?>" style="width:420px" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_template_id"><?php _e('Template ID (opzionale)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_template_id" id="wcefp_brevo_template_id" value="<?php echo esc_attr($tpl); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_from_email"><?php _e('Mittente email','wceventsfp'); ?></label></th>
                        <td><input type="email" name="wcefp_brevo_from_email" id="wcefp_brevo_from_email" value="<?php echo esc_attr($from_email); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_from_name"><?php _e('Mittente nome','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_from_name" id="wcefp_brevo_from_name" value="<?php echo esc_attr($from_name); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_list_it"><?php _e('Lista IT','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_it" id="wcefp_brevo_list_it" value="<?php echo esc_attr($list_it); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_list_en"><?php _e('Lista EN','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_en" id="wcefp_brevo_list_en" value="<?php echo esc_attr($list_en); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_tag"><?php _e('Tag contatto','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_tag" id="wcefp_brevo_tag" value="<?php echo esc_attr($tag); ?>" /></td>
                    </tr>

                    <tr><th colspan="2"><h3><?php _e('Tracking','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_ga4_enable"><?php _e('GA4/Tag Manager eventi custom','wceventsfp'); ?></label></th>
                        <td><label><input type="checkbox" name="wcefp_ga4_enable" id="wcefp_ga4_enable" <?php checked($ga4_en,true); ?> /> <?php _e('Abilita push dataLayer (view_item, add_to_cart, begin_checkout, extra_selected, purchase)','wceventsfp'); ?></label></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_ga4_id">GA4 Measurement ID</label></th>
                        <td><input type="text" name="wcefp_ga4_id" id="wcefp_ga4_id" value="<?php echo esc_attr($ga4_id); ?>" placeholder="G-XXXXXXXXXX" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_gtm_id">GTM Container ID</label></th>
                        <td><input type="text" name="wcefp_gtm_id" id="wcefp_gtm_id" value="<?php echo esc_attr($gtm_id); ?>" placeholder="GTM-XXXXXX" />
                            <p class="description"><?php _e('Se imposti GTM, verrà caricato Google Tag Manager (consigliato). Se lasci vuoto e compili GA4, verrà caricato direttamente GA4.','wceventsfp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_meta_pixel_id"><?php _e('Meta Pixel ID','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_meta_pixel_id" id="wcefp_meta_pixel_id" value="<?php echo esc_attr($mp_id); ?>" /></td>
                    </tr>
                </table>

                <p><button class="button button-primary" type="submit" name="wcefp_save" value="1"><?php _e('Salva','wceventsfp'); ?></button></p>
            </form>
        </div><?php
    }

    /**
     * New tabbed settings page using WordPress Settings API
     */
    public static function render_new_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Use the new settings class
        $settings = WCEFP_Admin_Settings::get_instance();
        $settings->render_settings_page();
    }

    /**
     * Render the performance monitoring page
     */
    public static function render_performance_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $performance_data = self::get_performance_data();
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Performance & Sistema','wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Monitoraggio delle performance del plugin e statistiche del sistema.', 'wceventsfp'); ?>
                </p>
            </div>
            
            <!-- System Status Grid -->
            <div class="wcefp-performance-grid">
                <div class="wcefp-card wcefp-status-card">
                    <div class="wcefp-card-header">
                        <h3><?php _e('Stato Sistema','wceventsfp'); ?></h3>
                        <div class="wcefp-status-indicator <?php echo esc_attr($performance_data['status']); ?>"></div>
                    </div>
                    <div class="wcefp-card-body">
                        <table class="wcefp-status-table">
                            <tr>
                                <td><strong><?php _e('PHP Version','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WordPress Version','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WooCommerce Version','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(defined('WC_VERSION') ? WC_VERSION : 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Plugin Version','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(WCEFP_VERSION); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3><?php _e('Database','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <table class="wcefp-status-table">
                            <tr>
                                <td><strong><?php _e('Occorrenze Totali','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html($performance_data['db']['occurrences']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Prenotazioni Totali','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html($performance_data['db']['bookings']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Voucher Attivi','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html($performance_data['db']['vouchers']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Chiusure Attive','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html($performance_data['db']['closures']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="wcefp-card">
                    <div class="wcefp-card-header">
                        <h3><?php _e('Performance','wceventsfp'); ?></h3>
                    </div>
                    <div class="wcefp-card-body">
                        <table class="wcefp-status-table">
                            <tr>
                                <td><strong><?php _e('Memory Limit','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Memory Usage','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(size_format(memory_get_usage(true))); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Peak Memory','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(size_format(memory_get_peak_usage(true))); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('PHP Time Limit','wceventsfp'); ?></strong></td>
                                <td><?php echo esc_html(ini_get('max_execution_time')) . 's'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Autoload Options Check -->
            <div class="wcefp-card">
                <div class="wcefp-card-header">
                    <h3><?php _e('Opzioni Autoload','wceventsfp'); ?></h3>
                    <p><?php _e('Opzioni caricate automaticamente ad ogni request. Soglia consigliata: < 1MB','wceventsfp'); ?></p>
                </div>
                <div class="wcefp-card-body">
                    <?php 
                    $autoload_size = $performance_data['autoload_size'];
                    $autoload_class = $autoload_size > 1024*1024 ? 'wcefp-status-warning' : 'wcefp-status-good';
                    ?>
                    <div class="wcefp-metric-large <?php echo esc_attr($autoload_class); ?>">
                        <span class="wcefp-metric-value"><?php echo esc_html(size_format($autoload_size)); ?></span>
                        <span class="wcefp-metric-label"><?php _e('Dimensione Autoload','wceventsfp'); ?></span>
                    </div>
                    
                    <?php if ($autoload_size > 1024*1024): ?>
                    <div class="wcefp-notice wcefp-notice-warning">
                        <p><?php _e('⚠️ La dimensione dell\'autoload è superiore alla soglia consigliata. Considera l\'ottimizzazione delle opzioni.','wceventsfp'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cron Jobs Status -->
            <div class="wcefp-card">
                <div class="wcefp-card-header">
                    <h3><?php _e('Cron Jobs','wceventsfp'); ?></h3>
                </div>
                <div class="wcefp-card-body">
                    <?php $cron_status = $performance_data['cron_working'] ? 'good' : 'error'; ?>
                    <div class="wcefp-status-indicator-inline <?php echo esc_attr($cron_status); ?>">
                        <?php echo $performance_data['cron_working'] ? 
                            __('✅ Funzionante','wceventsfp') : 
                            __('❌ Non funzionante','wceventsfp'); ?>
                    </div>
                    
                    <?php if (!$performance_data['cron_working']): ?>
                    <div class="wcefp-notice wcefp-notice-error" style="margin-top: 10px;">
                        <p><?php _e('I cron job di WordPress non funzionano correttamente. Questo potrebbe influenzare le funzionalità automatiche del plugin.','wceventsfp'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php
    }

    /**
     * Get performance data for the system
     * 
     * @return array Performance metrics
     */
    private static function get_performance_data() {
        global $wpdb;
        
        // Get database counts
        $db_counts = [
            'occurrences' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_occurrences"),
            'bookings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_bookings"),
            'vouchers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_vouchers WHERE status != 'used'"),
            'closures' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_closures WHERE end_date >= CURDATE()")
        ];
        
        // Get autoload options size
        $autoload_size = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );
        
        // Check if cron is working
        $cron_working = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;
        
        // Determine overall status
        $status = 'good';
        if ($autoload_size > 1024*1024) $status = 'warning';
        if (!$cron_working) $status = 'error';
        
        return [
            'status' => $status,
            'db' => $db_counts,
            'autoload_size' => $autoload_size,
            'cron_working' => $cron_working
        ];
    }
}
add_action('admin_head', function () { ?>
<style>
    #wcefp_product_data .wcefp-weekdays-grid{
        display:grid;
        grid-template-columns:repeat(7,minmax(0,1fr));
        gap:6px;
    }
    /* Evita che le label dei checkbox ereditino il float */
    #wcefp_product_data .form-field label.wcefp-weekday{
        float:none;
        width:auto;
        display:flex;
        align-items:center;
        gap:6px;
        margin:0;
    }
    #wcefp_product_data .form-field .wcefp-weekdays-grid:not(.wrap){
        margin-left:162px;
    }
</style>
<?php });
