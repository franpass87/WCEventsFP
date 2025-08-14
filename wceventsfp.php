<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, slot, prezzi A/B, extra, chiusure straordinarie, GA4/GTM, Meta Pixel, Brevo ITA/ENG, ICS, KPI, Calendario, Export e Gift (voucher PDF + redeem).
 * Version:     1.6.0
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCEFP_VERSION', '1.6.0');
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ---- Attivazione: tabelle ---- */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $tbl1 = $wpdb->prefix . 'wcefp_occurrences';
    $sql1 = "CREATE TABLE IF NOT EXISTS $tbl1 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NOT NULL,
      start_datetime DATETIME NOT NULL,
      end_datetime DATETIME NULL,
      capacity INT NOT NULL DEFAULT 0,
      booked INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      meta LONGTEXT NULL,
      UNIQUE KEY uniq_prod_start (product_id, start_datetime),
      INDEX (start_datetime),
      INDEX (product_id),
      INDEX (status)
    ) $charset;";

    $tbl2 = $wpdb->prefix . 'wcefp_closures';
    $sql2 = "CREATE TABLE IF NOT EXISTS $tbl2 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NOT NULL DEFAULT 0, /* 0 = globale */
      start_date DATE NOT NULL,
      end_date DATE NOT NULL,
      reason VARCHAR(255) NULL,
      INDEX (product_id),
      INDEX (start_date),
      INDEX (end_date)
    ) $charset;";

    $tbl3 = $wpdb->prefix . 'wcefp_vouchers';
    $sql3 = "CREATE TABLE IF NOT EXISTS $tbl3 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(64) UNIQUE,
      order_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED NULL,
      recipient_name VARCHAR(190) NULL,
      recipient_email VARCHAR(190) NULL,
      message TEXT NULL,
      remaining_uses INT NOT NULL DEFAULT 1,
      status VARCHAR(20) NOT NULL DEFAULT 'active', /* active|used|expired */
      created_at DATETIME NOT NULL,
      expires_at DATETIME NULL,
      meta LONGTEXT NULL,
      INDEX (order_id),
      INDEX (product_id),
      INDEX (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1); dbDelta($sql2); dbDelta($sql3);
});

add_action('plugins_loaded', function () {
    load_plugin_textdomain('wceventsfp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WCEventsFP</strong> richiede WooCommerce attivo.</p></div>';
        });
        return;
    }

    /* Include */
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-recurring.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-frontend.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-tracking.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-brevo.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-gift.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-pdf.php';

    WCEFP()->init();
});

function WCEFP() {
    static $inst = null;
    if ($inst === null) $inst = new WCEFP_Plugin();
    return $inst;
}

class WCEFP_Plugin {

    public function init() {
        $this->ensure_db_schema();

        /* Tipi prodotto */
        add_filter('product_type_selector', [$this, 'register_product_types']);
        add_action('init', [$this, 'add_product_classes']);
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

        /* Tab prodotto + salvataggio */
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        /* Esclusione archivi Woo */
        add_action('pre_get_posts', [$this, 'hide_from_archives']);

        /* Frontend assets + tracking + pixel */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('wp_head', ['WCEFP_Tracking','render_meta_pixel']); // fbq init se configurato

        /* DatiLayer */
        add_action('woocommerce_thankyou', ['WCEFP_Tracking','push_purchase_datalayer'], 20);
        add_action('woocommerce_thankyou', ['WCEFP_Frontend','render_ics_downloads'], 30);

        /* Render su pagina prodotto */
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_product_details'], 15);
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_booking_widget_auto'], 35);

        /* Brevo + segmentazione */
        add_action('woocommerce_order_status_completed', ['WCEFP_Brevo','on_completed']);
        // add_action('woocommerce_order_status_processing', ['WCEFP_Brevo','on_completed']); // se vuoi anche su processing

        /* Email Woo → opzionale OFF per ordini solo-evento */
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order',  [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',    [$this,'maybe_disable_wc_mail'], 10, 2);

        /* Admin menu + assets + AJAX */
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);
        add_action('wp_ajax_wcefp_update_occurrence', [$this, 'ajax_update_occurrence']);
        add_action('wp_ajax_wcefp_add_closure', [$this, 'ajax_add_closure']);
        add_action('wp_ajax_wcefp_delete_closure', [$this, 'ajax_delete_closure']);

        /* Export CSV */
        add_action('admin_post_wcefp_export_occurrences', [$this, 'export_occurrences_csv']);
        add_action('admin_post_wcefp_export_bookings',    [$this, 'export_bookings_csv']);

        /* Shortcode + AJAX pubblici */
        add_shortcode('wcefp_booking', ['WCEFP_Frontend', 'shortcode_booking']);
        add_action('wp_ajax_nopriv_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);

        /* Prezzo dinamico + meta */
        add_action('woocommerce_before_calculate_totals', ['WCEFP_Frontend', 'apply_dynamic_price']);
        add_action('woocommerce_checkout_create_order_line_item', ['WCEFP_Frontend', 'add_line_item_meta'], 10, 4);

        /* Anti-overbooking */
        add_action('woocommerce_order_status_processing', [$this, 'allocate_seats_on_status']);
        add_action('woocommerce_order_status_completed',  [$this, 'allocate_seats_on_status']);
        add_action('woocommerce_order_status_refunded',   [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_cancelled',  [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_failed',     [$this, 'release_seats_on_status']);

        /* ICS routing */
        add_action('init', ['WCEFP_Frontend','serve_ics']);

        /* Gift (checkout fields + redeem shortcode) */
        add_action('woocommerce_after_order_notes', ['WCEFP_Gift','checkout_fields']);
        add_action('woocommerce_checkout_process',  ['WCEFP_Gift','checkout_validate']);
        add_action('woocommerce_checkout_update_order_meta', ['WCEFP_Gift','save_order_meta']);
        add_action('woocommerce_order_status_completed', ['WCEFP_Gift','maybe_issue_vouchers']);
        add_shortcode('wcefp_redeem', ['WCEFP_Gift','shortcode_redeem']);
        add_action('wp_ajax_nopriv_wcefp_redeem_voucher', ['WCEFP_Gift','ajax_redeem_voucher']);
        add_action('wp_ajax_wcefp_redeem_voucher', ['WCEFP_Gift','ajax_redeem_voucher']);
    }

    private function ensure_db_schema(){
        // già fatto in activation; qui possiamo evolvere schema se mancano colonne
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $cols = $wpdb->get_results("SHOW COLUMNS FROM $tbl", ARRAY_A);
        $names = array_map(function($c){ return $c['Field']; }, (array)$cols);
        if (!in_array('status', $names, true)) {
            $wpdb->query("ALTER TABLE $tbl ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
            $wpdb->query("CREATE INDEX status ON $tbl (status)");
        }
    }

    /* ---------- Product Types ---------- */
    public function register_product_types($types) {
        $types['wcefp_event'] = __('Evento', 'wceventsfp');
        $types['wcefp_experience'] = __('Esperienza', 'wceventsfp');
        return $types;
    }
    public function add_product_classes() {
        if (!class_exists('WC_Product_Simple')) return;
        class WC_Product_WCEFP_Event extends WC_Product_Simple { public function get_type(){ return 'wcefp_event'; } }
        class WC_Product_WCEFP_Experience extends WC_Product_Simple { public function get_type(){ return 'wcefp_experience'; } }
    }
    public function map_product_class($classname, $type) {
        if ($type === 'wcefp_event') return 'WC_Product_WCEFP_Event';
        if ($type === 'wcefp_experience') return 'WC_Product_WCEFP_Experience';
        return $classname;
    }

    /* ---------- Product Tab ---------- */
    public function add_product_data_tab($tabs) {
        $tabs['wcefp_tab'] = [
            'label'    => __('Eventi/Esperienze', 'wceventsfp'),
            'target'   => 'wcefp_product_data',
            'class'    => ['show_if_wcefp_event','show_if_wcefp_experience'],
            'priority' => 21,
        ];
        return $tabs;
    }

    public function render_product_data_panel() {
        global $post; ?>
        <div id="wcefp_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3><?php _e('Prezzi e capacità', 'wceventsfp'); ?></h3>
                <?php
                woocommerce_wp_text_input(['id'=>'_wcefp_price_adult','label'=>__('Prezzo Adulto (€)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'0.01','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_price_child','label'=>__('Prezzo Bambino (€)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'0.01','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_capacity_per_slot','label'=>__('Capienza per slot','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'1','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_duration_minutes','label'=>__('Durata slot (minuti)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'5','min'=>'0']]);
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Info esperienza', 'wceventsfp'); ?></h3>
                <?php
                woocommerce_wp_text_input(['id'=>'_wcefp_languages','label'=>__('Lingue (es. IT, EN)','wceventsfp'),'type'=>'text']);
                woocommerce_wp_text_input(['id'=>'_wcefp_meeting_point','label'=>__('Meeting point','wceventsfp'),'type'=>'text']);
                woocommerce_wp_textarea_input(['id'=>'_wcefp_includes','label'=>__('Incluso','wceventsfp')]);
                woocommerce_wp_textarea_input(['id'=>'_wcefp_excludes','label'=>__('Escluso','wceventsfp')]);
                woocommerce_wp_textarea_input(['id'=>'_wcefp_cancellation','label'=>__('Politica di cancellazione','wceventsfp')]);
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Extra opzionali', 'wceventsfp'); ?></h3>
                <p class="form-field">
                    <label for="_wcefp_extras_json"><?php _e('Extra (JSON)','wceventsfp'); ?></label>
                    <textarea id="_wcefp_extras_json" name="_wcefp_extras_json" style="width:100%;height:100px" placeholder='[{"name":"Tagliere","price":8},{"name":"Calice vino","price":5}]'><?php
                        echo esc_textarea(get_post_meta($post->ID, '_wcefp_extras_json', true));
                    ?></textarea>
                    <span class="description"><?php _e('Formato JSON: name, price','wceventsfp'); ?></span>
                </p>
            </div>

            <div class="options_group">
                <h3><?php _e('Ricorrenze settimanali & Slot', 'wceventsfp'); ?></h3>
                <p class="form-field">
                    <label><?php _e('Giorni','wceventsfp'); ?></label>
                    <?php
                    $days = get_post_meta($post->ID, '_wcefp_weekdays', true); $days = is_array($days)?$days:[];
                    $lbl = [__('Dom','wceventsfp'),__('Lun','wceventsfp'),__('Mar','wceventsfp'),__('Mer','wceventsfp'),__('Gio','wceventsfp'),__('Ven','wceventsfp'),__('Sab','wceventsfp')];
                    for($i=0;$i<7;$i++):
                        printf('<label style="margin-right:8px;"><input type="checkbox" name="_wcefp_weekdays[]" value="%d" %s /> %s</label>',
                            $i, checked(in_array($i,$days), true, false), esc_html($lbl[$i]));
                    endfor; ?>
                </p>
                <p class="form-field">
                    <label for="_wcefp_time_slots"><?php _e('Slot (HH:MM, separati da virgola)','wceventsfp'); ?></label>
                    <input type="text" id="_wcefp_time_slots" name="_wcefp_time_slots" style="width:100%;" placeholder="11:00, 13:00, 19:30" value="<?php echo esc_attr(get_post_meta($post->ID, '_wcefp_time_slots', true)); ?>" />
                </p>
                <p class="form-field">
                    <label><?php _e('Genera occorrenze','wceventsfp'); ?></label>
                    <input type="date" id="wcefp_generate_from" /> →
                    <input type="date" id="wcefp_generate_to" />
                    <button class="button" type="button" id="wcefp-generate" data-product="<?php echo esc_attr($post->ID); ?>"><?php _e('Genera','wceventsfp'); ?></button>
                </p>
                <div id="wcefp-generate-result"></div>
            </div>
        </div>
        <?php
    }

    public function save_product_fields($product) {
        $pid = $product->get_id();
        $keys = [
            '_wcefp_price_adult','_wcefp_price_child','_wcefp_capacity_per_slot',
            '_wcefp_extras_json','_wcefp_time_slots','_wcefp_duration_minutes',
            '_wcefp_languages','_wcefp_meeting_point','_wcefp_includes','_wcefp_excludes','_wcefp_cancellation'
        ];
        foreach ($keys as $k) if (isset($_POST[$k])) update_post_meta($pid, $k, wp_unslash($_POST[$k]));
        $days = isset($_POST['_wcefp_weekdays']) ? array_map('intval',(array)$_POST['_wcefp_weekdays']) : [];
        update_post_meta($pid, '_wcefp_weekdays', $days);
    }

    /* ---------- Esclusione archivi ---------- */
    public function hide_from_archives($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if (!(is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) return;
        $tax_q = (array) $q->get('tax_query');
        $tax_q[] = [
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => ['wcefp_event','wcefp_experience'],
            'operator' => 'NOT IN',
        ];
        $q->set('tax_query', $tax_q);
    }

    /* ---------- Frontend & assets ---------- */
    public function enqueue_frontend() {
        wp_enqueue_style('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/css/frontend.css', [], WCEFP_VERSION);
        wp_enqueue_script('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/js/frontend.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
            'fbPixel' => get_option('wcefp_meta_pixel_id',''),
        ]);
    }

    /* ---------- Email Woo OFF per solo-evento ---------- */
    public function maybe_disable_wc_mail($enabled, $order) {
        $flag = get_option('wcefp_disable_wc_emails_for_events', '0') === '1';
        if (!$flag || !$order instanceof WC_Order) return $enabled;

        $only_events = true;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $only_events = false; break; }
        }
        return $only_events ? false : $enabled;
    }

    /* ---------- Admin ---------- */
    public function admin_menu() {
        $cap = 'manage_woocommerce';
        add_menu_page(__('Eventi & Degustazioni','wceventsfp'), __('Eventi & Degustazioni','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page'],'dashicons-calendar-alt',56);
        add_submenu_page('wcefp', __('Analisi KPI','wceventsfp'), __('Analisi KPI','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista','wceventsfp'), __('Calendario & Lista','wceventsfp'), $cap,'wcefp-calendar',[$this,'render_calendar_page']);
        add_submenu_page('wcefp', __('Chiusure','wceventsfp'), __('Chiusure','wceventsfp'), $cap,'wcefp-closures',[$this,'render_closures_page']);
        add_submenu_page('wcefp', __('Esporta','wceventsfp'), __('Esporta','wceventsfp'), $cap,'wcefp-export',[$this,'render_export_page']);
        add_submenu_page('wcefp', __('Impostazioni','wceventsfp'), __('Impostazioni','wceventsfp'), $cap,'wcefp-settings',[$this,'render_settings_page']);
    }

    public function enqueue_admin($hook) {
        if (strpos($hook,'wcefp') === false) return;
        wp_enqueue_style('wcefp-admin', WCEFP_PLUGIN_URL.'assets/css/admin.css', [], WCEFP_VERSION);

        if (strpos($hook,'wcefp_page_wcefp-calendar') !== false) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css', [], '6.1.15');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true);
        }

        wp_enqueue_script('wcefp-admin', WCEFP_PLUGIN_URL.'assets/js/admin.js', ['jquery','fullcalendar'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-admin','WCEFPAdmin',[
            'ajaxUrl'=> admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('wcefp_admin'),
            'products' => $this->get_events_products_for_filter(),
        ]);
    }

    private function get_events_products_for_filter(){
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 250,
            'post_status' => 'publish',
            'tax_query' => [[
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['wcefp_event','wcefp_experience'],
                'operator' => 'IN',
            ]],
            'orderby' => 'title',
            'order'   => 'ASC',
        ];
        $q = new WP_Query($args);
        $out = [];
        foreach ($q->posts as $p) $out[] = ['id'=>$p->ID,'title'=>$p->post_title];
        return $out;
    }

    public function render_kpi_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $kpi = ['orders_30'=>18,'rev