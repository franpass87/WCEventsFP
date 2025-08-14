<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, slot orari, prezzi Adulto/Bambino, extra, KPI, Calendario e integrazione GA4/GTM + Brevo.
 * Version:     0.9.0
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| 0) COSTANTI & REQUISITI
|--------------------------------------------------------------------------
*/
define('WCEFP_VERSION', '0.9.0');
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Definisci la tua API Key Brevo in wp-config.php per sicurezza:
// define('WCEFP_BREVO_API_KEY', 'xxx');

/*
|--------------------------------------------------------------------------
| 1) ATTIVAZIONE / DISATTIVAZIONE
|--------------------------------------------------------------------------
*/
register_activation_hook(__FILE__, function () {
    // Tabelle per occorrenze slot (semplice, estendibile)
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
      INDEX (product_id),
      INDEX (start_datetime)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

register_deactivation_hook(__FILE__, function () {
    // Non cancelliamo dati all’uscita
});

/*
|--------------------------------------------------------------------------
| 2) CHECK WooCommerce
|--------------------------------------------------------------------------
*/
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wceventsfp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WCEventsFP</strong> richiede WooCommerce attivo.</p></div>';
        });
        return;
    }

    // Bootstrap
    WCEFP()->init();
});

function WCEFP() {
    static $inst = null;
    if (null === $inst) $inst = new WCEFP_Plugin();
    return $inst;
}

/*
|--------------------------------------------------------------------------
| 3) CORE PLUGIN CLASS
|--------------------------------------------------------------------------
*/
class WCEFP_Plugin {
    public function init() {
        // Tipi prodotto
        add_filter('product_type_selector', [$this, 'register_product_types']);
        add_action('init', [$this, 'add_product_classes']);
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

        // Campi custom: prezzi A/B, extra, ricorrenze, slot
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        // Esclusione dagli archivi WooCommerce
        add_action('pre_get_posts', [$this, 'hide_from_archives']);

        // Tracking GA4 / GTM
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);

        // Brevo all’ordine completato
        add_action('woocommerce_order_status_completed', [$this, 'send_to_brevo_on_completed']);

        // Admin pagine KPI & Calendario/Lista
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        // REST/AJAX base per calendario/lista
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
    }

    /* ---------------------------
       3.1 Tipi prodotto (Evento/Esperienza)
    ----------------------------*/
    public function register_product_types($types) {
        $types['wcefp_event'] = __('Evento', 'wceventsfp');
        $types['wcefp_experience'] = __('Esperienza', 'wceventsfp');
        return $types;
    }

    public function add_product_classes() {
        if (!class_exists('WC_Product_Simple')) return;

        class WC_Product_WCEFP_Event extends WC_Product_Simple {
            public function get_type() { return 'wcefp_event'; }
        }
        class WC_Product_WCEFP_Experience extends WC_Product_Simple {
            public function get_type() { return 'wcefp_experience'; }
        }
    }

    public function map_product_class($classname, $product_type) {
        if ($product_type === 'wcefp_event') return 'WC_Product_WCEFP_Event';
        if ($product_type === 'wcefp_experience') return 'WC_Product_WCEFP_Experience';
        return $classname;
    }

    /* ---------------------------
       3.2 Tab Dati Prodotto personalizzata
    ----------------------------*/
    public function add_product_data_tab($tabs) {
        $tabs['wcefp_tab'] = [
            'label'    => __('Eventi/Esperienze', 'wceventsfp'),
            'target'   => 'wcefp_product_data',
            'class'    => ['show_if_wcefp_event', 'show_if_wcefp_experience'],
            'priority' => 21,
        ];
        return $tabs;
    }

    public function render_product_data_panel() {
        global $post;
        $type = $this->get_product_type($post->ID);
        ?>
        <div id="wcefp_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3><?php _e('Prezzi e capacità', 'wceventsfp'); ?></h3>
                <?php
                woocommerce_wp_text_input([
                    'id' => '_wcefp_price_adult',
                    'label' => __('Prezzo Adulto (€)', 'wceventsfp'),
                    'type' => 'number',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                ]);
                woocommerce_wp_text_input([
                    'id' => '_wcefp_price_child',
                    'label' => __('Prezzo Bambino (€)', 'wceventsfp'),
                    'type' => 'number',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                ]);
                woocommerce_wp_text_input([
                    'id' => '_wcefp_capacity_per_slot',
                    'label' => __('Capienza per slot', 'wceventsfp'),
                    'type' => 'number',
                    'custom_attributes' => ['step' => '1', 'min' => '0'],
                ]);
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Extra opzionali', 'wceventsfp'); ?></h3>
                <p class="form-field">
                    <label for="_wcefp_extras_json"><?php _e('Extra (JSON)', 'wceventsfp'); ?></label>
                    <textarea id="_wcefp_extras_json" name="_wcefp_extras_json" style="width:100%;height:100px" placeholder='[{"name":"Tagliere","price":8},{"name":"Calice vino","price":5}]'><?php
                        echo esc_textarea(get_post_meta($post->ID, '_wcefp_extras_json', true));
                    ?></textarea>
                    <span class="description"><?php _e('Formato JSON: name, price', 'wceventsfp'); ?></span>
                </p>
            </div>

            <div class="options_group">
                <h3><?php _e('Ricorrenze settimanali & Slot', 'wceventsfp'); ?></h3>
                <p class="form-field">
                    <label for="_wcefp_weekdays"><?php _e('Giorni della settimana', 'wceventsfp'); ?></label>
                    <?php
                    $days = get_post_meta($post->ID, '_wcefp_weekdays', true);
                    $days = is_array($days) ? $days : [];
                    $labels = [__('Dom','wceventsfp'),__('Lun','wceventsfp'),__('Mar','wceventsfp'),__('Mer','wceventsfp'),__('Gio','wceventsfp'),__('Ven','wceventsfp'),__('Sab','wceventsfp')];
                    for ($i=0;$i<7;$i++):
                    ?>
                        <label style="margin-right:8px;">
                            <input type="checkbox" name="_wcefp_weekdays[]" value="<?php echo $i; ?>" <?php checked(in_array($i, $days)); ?> />
                            <?php echo esc_html($labels[$i]); ?>
                        </label>
                    <?php endfor; ?>
                </p>
                <p class="form-field">
                    <label for="_wcefp_time_slots"><?php _e('Slot orari (HH:MM, separati da virgola)', 'wceventsfp'); ?></label>
                    <input type="text" id="_wcefp_time_slots" name="_wcefp_time_slots" style="width:100%;" placeholder="11:00, 13:00, 19:30" value="<?php echo esc_attr(get_post_meta($post->ID, '_wcefp_time_slots', true)); ?>" />
                </p>
                <p class="form-field">
                    <label for="_wcefp_generate_range"><?php _e('Genera occorrenze dal/al', 'wceventsfp'); ?></label>
                    <input type="date" id="_wcefp_generate_from" name="_wcefp_generate_from" /> →
                    <input type="date" id="_wcefp_generate_to" name="_wcefp_generate_to" />
                    <button class="button" type="button" id="wcefp-generate"><?php _e('Genera', 'wceventsfp'); ?></button>
                </p>
                <p class="description"><?php _e('Clic su “Genera” per popolare la tabella occorrenze in base a giorni/slot.', 'wceventsfp'); ?></p>
                <div id="wcefp-generate-result"></div>
            </div>
        </div>
        <?php
    }

    public function save_product_fields($product) {
        $pid = $product->get_id();

        $map = [
            '_wcefp_price_adult'       => FILTER_DEFAULT,
            '_wcefp_price_child'       => FILTER_DEFAULT,
            '_wcefp_capacity_per_slot' => FILTER_DEFAULT,
            '_wcefp_extras_json'       => FILTER_DEFAULT,
            '_wcefp_time_slots'        => FILTER_DEFAULT,
        ];
        foreach ($map as $key => $filter) {
            if (isset($_POST[$key])) update_post_meta($pid, $key, wp_unslash($_POST[$key]));
        }
        // Giorni settimanali
        $days = isset($_POST['_wcefp_weekdays']) ? array_map('intval', (array) $_POST['_wcefp_weekdays']) : [];
        update_post_meta($pid, '_wcefp_weekdays', $days);

        // Generazione occorrenze (AJAX in admin.js, qui fallback no-op)
    }

    private function get_product_type($product_id) {
        $product = wc_get_product($product_id);
        return $product ? $product->get_type() : '';
    }

    /* ---------------------------
       3.3 Esclusione dagli archivi WooCommerce
    ----------------------------*/
    public function hide_from_archives($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if (!(is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) return;

        $meta_query = (array) $q->get('meta_query');
        $meta_query[] = [
            'key'     => '_wcefp_hide_from_archives',
            'compare' => 'NOT EXISTS', // settiamo il meta per i nostri tipi
        ];
        $q->set('meta_query', $meta_query);

        // Impostiamo _wcefp_hide_from_archives automaticamente sui nostri prodotti
        add_action('the_post', function ($post) {
            if ($post->post_type !== 'product') return;
            $product = wc_get_product($post->ID);
            if (!$product) return;
            if (in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) {
                if (!metadata_exists('post', $post->ID, '_wcefp_hide_from_archives')) {
                    update_post_meta($post->ID, '_wcefp_hide_from_archives', '1');
                }
            }
        });
    }

    /* ---------------------------
       3.4 Frontend & Tracking GA4/GTM
    ----------------------------*/
    public function enqueue_frontend() {
        wp_enqueue_script('wcefp-frontend', WCEFP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function push_purchase_event_to_datalayer($order_id) {
        if (!$order_id) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $items[] = [
                'item_id'   => (string) $product->get_id(),
                'item_name' => $product->get_name(),
                'item_category' => $product->get_type(),
                'quantity'  => (int) $item->get_quantity(),
                'price'     => (float) $order->get_item_total($item, false),
            ];
        }

        $data = [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string) $order->get_order_number(),
                'value'          => (float) $order->get_total(),
                'currency'       => $order->get_currency(),
                'items'          => $items,
            ],
        ];

        // Stampa inline nel thankyou (GA4 via GTM)
        echo "<script>window.dataLayer = window.dataLayer || []; dataLayer.push(" . wp_json_encode($data) . ");</script>";
    }

    /* ---------------------------
       3.5 Brevo: invio dati a completamento ordine
    ----------------------------*/
    public function send_to_brevo_on_completed($order_id) {
        if (!defined('WCEFP_BREVO_API_KEY') || empty(WCEFP_BREVO_API_KEY)) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Controlla se nel carrello ci sono nostri tipi
        $has_event = false;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if ($p && in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) {
                $has_event = true; break;
            }
        }
        if (!$has_event) return;

        // Prepara payload Brevo (Transactional / SMTP / Marketing automation webhook)
        $email = $order->get_billing_email();
        $firstname = $order->get_billing_first_name();
        $payload = [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME' => $firstname,
                'ORDER_ID'  => (string) $order->get_order_number(),
                'TOTAL'     => (float) $order->get_total(),
            ],
            // opzionale: 'includeListIds' => [123],
            'updateEnabled' => true,
        ];

        // Crea/aggiorna contatto
        $this->brevo_request('https://api.brevo.com/v3/contacts', 'POST', $payload);

        // Trigger template transazionale (se usi SMTP templateId)
        // $this->brevo_request('https://api.brevo.com/v3/smtp/email', 'POST', [
        //     'to' => [['email' => $email, 'name' => $firstname]],
        //     'templateId' => 999, // tuo template
        //     'params' => ['ORDER_ID' => (string) $order->get_order_number()]
        // ]);
    }

    private function brevo_request($url, $method = 'POST', $body = []) {
        $args = [
            'headers' => [
                'accept' => 'application/json',
                'api-key' => WCEFP_BREVO_API_KEY,
                'content-type' => 'application/json',
            ],
            'method' => $method,
            'body'   => !empty($body) ? wp_json_encode($body) : null,
            'timeout'=> 15,
        ];
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            error_log('WCEFP Brevo error: ' . $res->get_error_message());
        }
        return $res;
    }

    /* ---------------------------
       3.6 Admin: KPI + Calendario/Lista
    ----------------------------*/
    public function admin_menu() {
        $cap = 'manage_woocommerce';
        add_menu_page(
            __('Eventi & Degustazioni', 'wceventsfp'),
            __('Eventi & Degustazioni', 'wceventsfp'),
            $cap, 'wcefp', [$this, 'render_kpi_page'],
            'dashicons-calendar-alt', 56
        );
        add_submenu_page('wcefp', __('Analisi KPI', 'wceventsfp'), __('Analisi KPI', 'wceventsfp'), $cap, 'wcefp', [$this, 'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista', 'wceventsfp'), __('Calendario & Lista', 'wceventsfp'), $cap, 'wcefp-calendar', [$this, 'render_calendar_page']);
        add_submenu_page('wcefp', __('Impostazioni', 'wceventsfp'),
