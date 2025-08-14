<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, slot orari, prezzi Adulto/Bambino, extra, KPI, Calendario e integrazione GA4/GTM + Brevo.
 * Version:     1.1.0
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCEFP_VERSION', '1.1.0');
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

/* Tabelle occorrenze */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $tbl = $wpdb->prefix . 'wcefp_occurrences';
    $sql = "CREATE TABLE IF NOT EXISTS $tbl (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NOT NULL,
      start_datetime DATETIME NOT NULL,
      end_datetime DATETIME NULL,
      capacity INT NOT NULL DEFAULT 0,
      booked INT NOT NULL DEFAULT 0,
      meta LONGTEXT NULL,
      UNIQUE KEY uniq_prod_start (product_id, start_datetime),
      INDEX (start_datetime),
      INDEX (product_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

add_action('plugins_loaded', function () {
    load_plugin_textdomain('wceventsfp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WCEventsFP</strong> richiede WooCommerce attivo.</p></div>';
        });
        return;
    }
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-recurring.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-frontend.php';
    WCEFP()->init();
});

function WCEFP() {
    static $inst = null;
    if ($inst === null) $inst = new WCEFP_Plugin();
    return $inst;
}

class WCEFP_Plugin {

    public function init() {
        /* Tipi prodotto */
        add_filter('product_type_selector', [$this, 'register_product_types']);
        add_action('init', [$this, 'add_product_classes']);
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

        /* Tab prodotto */
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        /* Escludi da archivi */
        add_action('pre_get_posts', [$this, 'hide_from_archives']);

        /* Script frontend + GA4 */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);

        /* Brevo */
        add_action('woocommerce_order_status_completed', [$this, 'send_to_brevo_on_completed']);

        /* Admin menu */
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        /* AJAX Admin Calendario/Lista */
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);

        /* AJAX Genera occorrenze */
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);

        /* Shortcode + AJAX pubblici */
        add_shortcode('wcefp_booking', ['WCEFP_Frontend', 'shortcode_booking']);
        add_action('wp_ajax_nopriv_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);

        /* Prezzo dinamico riga carrello */
        add_action('woocommerce_before_calculate_totals', ['WCEFP_Frontend', 'apply_dynamic_price']);
        add_action('woocommerce_checkout_create_order_line_item', ['WCEFP_Frontend', 'add_line_item_meta'], 10, 4);
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
        $keys = ['_wcefp_price_adult','_wcefp_price_child','_wcefp_capacity_per_slot','_wcefp_extras_json','_wcefp_time_slots'];
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

    /* ---------- Frontend & GA4 ---------- */
    public function enqueue_frontend() {
        wp_enqueue_style('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/css/frontend.css', [], WCEFP_VERSION);
        wp_enqueue_script('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/js/frontend.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
        ]);
    }

    public function push_purchase_event_to_datalayer($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product(); if(!$product) continue;
            $items[] = [
                'item_id'      => (string)$product->get_id(),
                'item_name'    => $product->get_name(),
                'item_category'=> $product->get_type(),
                'quantity'     => (int)$item->get_quantity(),
                'price'        => (float)$order->get_item_total($item, false),
            ];
        }
        $data = [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string)$order->get_order_number(),
                'value' => (float)$order->get_total(),
                'currency' => $order->get_currency(),
                'items' => $items,
            ],
        ];
        echo "<script>window.dataLayer=window.dataLayer||[];dataLayer.push(".wp_json_encode($data).");</script>";
    }

    /* ---------- Brevo ---------- */
    public function send_to_brevo_on_completed($order_id) {
        if (!defined('WCEFP_BREVO_API_KEY') || empty(WCEFP_BREVO_API_KEY)) return;
        $order = wc_get_order($order_id); if(!$order) return;

        $has_event = false;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if ($p && in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $has_event = true; break; }
        }
        if (!$has_event) return;

        $email = $order->get_billing_email();
        $firstname = $order->get_billing_first_name();
        $payload = [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME' => $firstname,
                'ORDER_ID'  => (string)$order->get_order_number(),
                'TOTAL'     => (float)$order->get_total(),
            ],
            'updateEnabled' => true,
        ];
        $this->brevo_request('https://api.brevo.com/v3/contacts', 'POST', $payload);
    }
    private function brevo_request($url,$method='POST',$body=[]) {
        $args = [
            'headers'=>['accept'=>'application/json','api-key'=>WCEFP_BREVO_API_KEY,'content-type'=>'application/json'],
            'method'=>$method,'body'=>!empty($body)?wp_json_encode($body):null,'timeout'=>15,
        ];
        $res = wp_remote_request($url,$args);
        if (is_wp_error($res)) error_log('WCEFP Brevo error: '.$res->get_error_message());
        return $res;
    }

    /* ---------- Admin ---------- */
    public function admin_menu() {
        $cap = 'manage_woocommerce';
        add_menu_page(__('Eventi & Degustazioni','wceventsfp'), __('Eventi & Degustazioni','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page'],'dashicons-calendar-alt',56);
        add_submenu_page('wcefp', __('Analisi KPI','wceventsfp'), __('Analisi KPI','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista','wceventsfp'), __('Calendario & Lista','wceventsfp'), $cap,'wcefp-calendar',[$this,'render_calendar_page']);
        add_submenu_page('wcefp', __('Impostazioni','wceventsfp'), __('Impostazioni','wceventsfp'), $cap,'wcefp-settings',[$this,'render_settings_page']);
    }
    public function enqueue_admin($hook) {
        if (strpos($hook,'wcefp') === false) return;
        wp_enqueue_style('wcefp-admin', WCEFP_PLUGIN_URL.'assets/css/admin.css', [], WCEFP_VERSION);

        // FullCalendar (CDN)
        wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css', [], '6.1.15');
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true);

        wp_enqueue_script('wcefp-admin', WCEFP_PLUGIN_URL.'assets/js/admin.js', ['jquery','fullcalendar'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-admin','WCEFPAdmin',[
            'ajaxUrl'=> admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('wcefp_admin'),
        ]);
    }
    public function render_kpi_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $kpi = $this->get_kpi_demo(); ?>
        <div class="wrap">
            <h1><?php _e('Analisi KPI','wceventsfp'); ?></h1>
            <div class="wcefp-kpi-grid">
                <div class="card"><h3><?php _e('Ordini (30gg)','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['orders_30']); ?></p></div>
                <div class="card"><h3><?php _e('Ricavi (30gg)','wceventsfp'); ?></h3><p>€ <?php echo number_format($kpi['revenue_30'],2,',','.'); ?></p></div>
                <div class="card"><h3><?php _e('Riempimento medio','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['fill_rate']); ?>%</p></div>
                <div class="card"><h3><?php _e('Top Esperienza','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['top_product']); ?></p></div>
            </div>
        </div><?php
    }
    private function get_kpi_demo() {
        return ['orders_30'=>18,'revenue_30'=>2150.50,'fill_rate'=>63,'top_product'=>'Degustazione Classica'];
    }
    public function render_calendar_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Calendario & Lista Prenotazioni','wceventsfp'); ?></h1>
            <div class="wcefp-toolbar">
                <button class="button button-primary" id="wcefp-switch-calendar"><?php _e('Calendario','wceventsfp'); ?></button>
                <button class="button" id="wcefp-switch-list"><?php _e('Lista','wceventsfp'); ?></button>
            </div>
            <div id="wcefp-view" style="min-height:600px;"></div>
        </div><?php
    }
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['wcefp_save']) && check_admin_referer('wcefp_settings')) {
            update_option('wcefp_default_capacity', intval($_POST['wcefp_default_capacity'] ?? 0));
            echo '<div class="updated"><p>Salvato.</p></div>';
        }
        $cap = get_option('wcefp_default_capacity', 0); ?>
        <div class="wrap">
            <h1><?php _e('Impostazioni','wceventsfp'); ?></h1>
            <form method="post"><?php wp_nonce_field('wcefp_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="wcefp_default_capacity"><?php _e('Capienza default per slot','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_default_capacity" id="wcefp_default_capacity" value="<?php echo esc_attr($cap); ?>" min="0" /></td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit" name="wcefp_save" value="1"><?php _e('Salva','wceventsfp'); ?></button></p>
            </form>
        </div><?php
    }

    /* ---------- AJAX admin ---------- */
    public function ajax_get_bookings() {
        check_ajax_referer('wcefp_admin','nonce');
        // Ordini ultimi 60 gg con prodotti evento/esperienza
        $orders = wc_get_orders(['limit'=>50,'type'=>'shop_order','status'=>['wc-processing','wc-completed'],'date_created'=>'>='. (new DateTime('-60 days'))->format('Y-m-d')]);
        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $it) {
                $p = $it->get_product(); if(!$p) continue;
                if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
                $rows[] = [
                    'order'   => $order->get_order_number(),
                    'date'    => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                    'product' => $p->get_name(),
                    'qty'     => (int)$it->get_quantity(),
                    'total'   => (float)$order->get_item_total($it, false),
                ];
            }
        }
        wp_send_json_success(['rows'=>$rows]);
    }
    public function ajax_get_calendar() {
        check_ajax_referer('wcefp_admin','nonce');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : (new DateTime('-7 days'))->format('Y-m-d');
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : (new DateTime('+60 days'))->format('Y-m-d');
        $evts = $wpdb->get_results($wpdb->prepare("SELECT id,product_id,start_datetime,end_datetime,capacity,booked FROM $tbl WHERE start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC", "$from 00:00:00", "$to 23:59:59"), ARRAY_A);
        $events = [];
        foreach ($evts as $e) {
            $events[] = [
                'id'    => (int)$e['id'],
                'title' => get_the_title((int)$e['product_id'])." (".intval($e['booked'])."/".intval($e['capacity']).")",
                'start' => $e['start_datetime'],
                'end'   => $e['end_datetime'] ?: null,
            ];
        }
        wp_send_json_success(['events'=>$events]);
    }
}
