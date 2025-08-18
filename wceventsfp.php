<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, slot, prezzi A/B, extra, KPI, Calendario (inline edit + filtro), Chiusure straordinarie, GA4/Tag Manager, Meta Pixel, Brevo (liste IT/EN), anti-overbooking, ICS, gift "Regala un'esperienza" e scheda stile GYG/Viator.
 * Version:     1.6.1
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCEFP_VERSION', '1.6.1');
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ---- Attivazione: tabelle principali ---- */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // Occorrenze
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

    // Chiusure straordinarie
    $tbl2 = $wpdb->prefix . 'wcefp_closures';
    $sql2 = "CREATE TABLE IF NOT EXISTS $tbl2 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      start_date DATE NOT NULL,
      end_date DATE NOT NULL,
      note VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (product_id),
      INDEX (start_date),
      INDEX (end_date)
    ) $charset;";

    // Voucher regalo
    $tbl3 = $wpdb->prefix . 'wcefp_vouchers';
    $sql3 = "CREATE TABLE IF NOT EXISTS $tbl3 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(48) NOT NULL UNIQUE,
      product_id BIGINT UNSIGNED NOT NULL,
      order_id BIGINT UNSIGNED NOT NULL,
      recipient_name VARCHAR(180) NULL,
      recipient_email VARCHAR(180) NULL,
      message_text TEXT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'unused',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      redeemed_at DATETIME NULL,
      INDEX (product_id),
      INDEX (order_id),
      INDEX (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    
    // Extra associati ai prodotti
    $tbl4 = $wpdb->prefix . 'wcefp_product_extras';
    $sql4 = "CREATE TABLE IF NOT EXISTS $tbl4 (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NOT NULL,
      extra_id BIGINT UNSIGNED NOT NULL,
      pricing_type VARCHAR(20) NOT NULL DEFAULT 'per_order',
      price DECIMAL(10,2) NOT NULL DEFAULT 0,
      required TINYINT(1) NOT NULL DEFAULT 0,
      max_qty INT UNSIGNED NOT NULL DEFAULT 0,
      stock INT UNSIGNED NOT NULL DEFAULT 0,
      sort_order INT UNSIGNED NOT NULL DEFAULT 0,
      UNIQUE KEY uniq_prod_extra (product_id,extra_id),
      INDEX (product_id),
      INDEX (extra_id)
    ) $charset;";
    dbDelta($sql4);
});

add_action('plugins_loaded', function () {
    load_plugin_textdomain('wceventsfp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WCEventsFP</strong> richiede WooCommerce attivo.</p></div>';
        });
        return;
    }

    // Include core
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-recurring.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-closures.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-gift.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-frontend.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-templates.php';
    require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-product-types.php';

    // Include admin (nuova classe)
    require_once WCEFP_PLUGIN_DIR . 'admin/class-wcefp-admin.php';
    require_once WCEFP_PLUGIN_DIR . 'admin/class-wcefp-meetingpoints.php';
    WCEFP_Admin::init();

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

        add_action('init', [$this, 'register_extra_cpt']);

        /* Tipi prodotto */
        add_filter('product_type_selector', [$this, 'register_product_types']);
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

        /* Tab prodotto & salvataggio */
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        /* Esclusione archivi Woo */
        add_action('pre_get_posts', [$this, 'hide_from_archives']);

        /* Frontend assets */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);

        /* GA4/DTL eventi base */
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);

        /* Iniezione GA4/GTM + Meta Pixel */
        add_action('wp_head', [$this, 'inject_ga_scripts'], 5);
        add_action('wp_head', [$this, 'inject_meta_pixel_base'], 6);
        add_action('woocommerce_thankyou', [$this, 'meta_pixel_purchase'], 25);

        /* ICS su thank-you */
        add_action('woocommerce_thankyou', [$this, 'render_ics_downloads'], 30);

        /* Render su pagina prodotto */
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_product_details'], 15);
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_booking_widget_auto'], 35);

        /* Brevo */
        add_action('woocommerce_order_status_completed', [$this, 'brevo_on_completed']);

        /* Disattiva email Woo (solo eventi/esperienze) */
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order',  [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',    [$this,'maybe_disable_wc_mail'], 10, 2);

        /* AJAX Admin (restano qui) */
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);
        add_action('wp_ajax_wcefp_update_occurrence', [$this, 'ajax_update_occurrence']);
        add_action('wp_ajax_wcefp_add_closure', ['WCEFP_Closures', 'ajax_add_closure']);
        add_action('wp_ajax_wcefp_delete_closure', ['WCEFP_Closures', 'ajax_delete_closure']);
        add_action('wp_ajax_wcefp_list_closures', ['WCEFP_Closures', 'ajax_list_closures']);

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

        add_action('woocommerce_order_status_processing', [$this, 'allocate_extras_on_status']);
        add_action('woocommerce_order_status_completed',  [$this, 'allocate_extras_on_status']);
        add_action('woocommerce_order_status_refunded',   [$this, 'release_extras_on_status']);
        add_action('woocommerce_order_status_cancelled',  [$this, 'release_extras_on_status']);
        add_action('woocommerce_order_status_failed',     [$this, 'release_extras_on_status']);

        /* ICS routing */
        add_action('init', [$this, 'serve_ics']);

        /* Gift */
        WCEFP_Gift::init();
    }

    private function ensure_db_schema(){
        global $wpdb;
        $tbl = $wpdb->prefix.'wcefp_occurrences';
        $cols = $wpdb->get_results("SHOW COLUMNS FROM $tbl", ARRAY_A);
        $names = array_map(function($c){ return $c['Field']; }, (array)$cols);
        if (!in_array('status', $names, true)) {
            $wpdb->query("ALTER TABLE $tbl ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
            $wpdb->query("CREATE INDEX status ON $tbl (status)");
        }
        $charset = $wpdb->get_charset_collate();
        $tbl2 = $wpdb->prefix.'wcefp_closures';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tbl2 (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (product_id), INDEX (start_date), INDEX (end_date)
        ) $charset");
        $tbl3 = $wpdb->prefix.'wcefp_vouchers';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tbl3 (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(48) NOT NULL UNIQUE,
            product_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            recipient_name VARCHAR(180) NULL,
            recipient_email VARCHAR(180) NULL,
            message_text TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unused',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            redeemed_at DATETIME NULL,
            INDEX (product_id), INDEX (order_id), INDEX (status)
        ) $charset");

        $tbl4 = $wpdb->prefix.'wcefp_product_extras';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $tbl4 (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            extra_id BIGINT UNSIGNED NOT NULL,
            pricing_type VARCHAR(20) NOT NULL DEFAULT 'per_order',
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            required TINYINT(1) NOT NULL DEFAULT 0,
            max_qty INT UNSIGNED NOT NULL DEFAULT 0,
            stock INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_prod_extra (product_id,extra_id),
            INDEX (product_id), INDEX (extra_id)
        ) $charset");
    }

    public function register_extra_cpt(){
        register_post_type('wcefp_extra', [
            'label' => __('Extra','wceventsfp'),
            'public' => false,
            'show_ui' => true,
            'supports' => ['title','editor']
        ]);
    }

    /* ---------- Product Types ---------- */
    public function register_product_types($types) {
        $types['wcefp_event'] = __('Evento', 'wceventsfp');
        $types['wcefp_experience'] = __('Esperienza', 'wceventsfp');
        return $types;
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
                $points = get_option('wcefp_meetingpoints', []);
                $points = is_array($points) ? $points : [];
                $selected_point = get_post_meta($post->ID, '_wcefp_meeting_point', true);
                if (!empty($points)) {
                    echo '<p class="form-field"><label>' . __('Meeting point','wceventsfp') . '</label><span>';
                    foreach ($points as $pt) {
                        $addr = is_array($pt) ? ( $pt['address'] ?? '' ) : $pt;
                        echo '<label><input type="radio" name="wcefp_meeting_point" value="' . esc_attr($addr) . '" ' . checked($selected_point, $addr, false) . '> ' . esc_html($addr) . '</label><br>';
                    }
                    echo '</span></p>';
                }
                woocommerce_wp_textarea_input(['id'=>'_wcefp_includes','label'=>__('Incluso','wceventsfp')]);
                woocommerce_wp_textarea_input(['id'=>'_wcefp_excludes','label'=>__('Escluso','wceventsfp')]);
                woocommerce_wp_textarea_input(['id'=>'_wcefp_cancellation','label'=>__('Politica di cancellazione','wceventsfp')]);
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Extra opzionali', 'wceventsfp'); ?></h3>
                <?php
                    global $wpdb;
                    $tbl = $wpdb->prefix.'wcefp_product_extras';
                    $rows = $wpdb->get_results($wpdb->prepare("SELECT pe.*, p.post_title FROM $tbl pe LEFT JOIN {$wpdb->posts} p ON p.ID=pe.extra_id WHERE pe.product_id=%d ORDER BY pe.sort_order ASC", $post->ID), ARRAY_A);
                    $extras = [];
                    foreach ($rows as $r) {
                        $extras[] = [
                            'id'=>intval($r['extra_id']),
                            'name'=>$r['post_title'],
                            'pricing_type'=>$r['pricing_type'],
                            'price'=>floatval($r['price']),
                            'required'=>intval($r['required'])?1:0,
                            'max_qty'=>intval($r['max_qty']),
                            'stock'=>intval($r['stock'])
                        ];
                    }
                    $all = get_posts(['post_type'=>'wcefp_extra','numberposts'=>-1,'post_status'=>'publish']);
                ?>
                <table class="wcefp-extra-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Nome','wceventsfp'); ?></th>
                            <th><?php _e('Tariffazione','wceventsfp'); ?></th>
                            <th><?php _e('Prezzo (€)','wceventsfp'); ?></th>
                            <th><?php _e('Obbligatorio','wceventsfp'); ?></th>
                            <th><?php _e('Max QTY','wceventsfp'); ?></th>
                            <th><?php _e('Stock','wceventsfp'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="wcefp-extra-rows">
                        <?php foreach ($extras as $i => $ex): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="_wcefp_extras[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($ex['id']); ?>" />
                                <input type="text" list="wcefp-extra-list" name="_wcefp_extras[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($ex['name']); ?>" />
                            </td>
                            <td>
                                <select name="_wcefp_extras[<?php echo esc_attr($i); ?>][pricing_type]">
                                    <option value="per_order" <?php selected($ex['pricing_type'],'per_order'); ?>><?php _e('Per ordine','wceventsfp'); ?></option>
                                    <option value="per_person" <?php selected($ex['pricing_type'],'per_person'); ?>><?php _e('Per persona','wceventsfp'); ?></option>
                                    <option value="per_child" <?php selected($ex['pricing_type'],'per_child'); ?>><?php _e('Solo bambino','wceventsfp'); ?></option>
                                    <option value="per_adult" <?php selected($ex['pricing_type'],'per_adult'); ?>><?php _e('Solo adulto','wceventsfp'); ?></option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][price]" value="<?php echo esc_attr($ex['price']); ?>" /></td>
                            <td><input type="checkbox" name="_wcefp_extras[<?php echo esc_attr($i); ?>][required]" <?php checked($ex['required'],1); ?> /></td>
                            <td><input type="number" step="1" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][max_qty]" value="<?php echo esc_attr($ex['max_qty']); ?>" /></td>
                            <td><input type="number" step="1" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][stock]" value="<?php echo esc_attr($ex['stock']); ?>" /></td>
                            <td><button type="button" class="button wcefp-remove-extra">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <datalist id="wcefp-extra-list">
                    <?php foreach ($all as $e) echo '<option value="'.esc_attr($e->post_title).'">'; ?>
                </datalist>
                <p><button type="button" class="button wcefp-add-extra"><?php _e('Aggiungi extra','wceventsfp'); ?></button></p>
                <script type="text/html" id="wcefp-extra-row-template">
                    <tr>
                        <td><input type="hidden" name="_wcefp_extras[{{INDEX}}][id]" value="" /><input type="text" list="wcefp-extra-list" name="_wcefp_extras[{{INDEX}}][name]" /></td>
                        <td><select name="_wcefp_extras[{{INDEX}}][pricing_type]"><option value="per_order"><?php _e('Per ordine','wceventsfp'); ?></option><option value="per_person"><?php _e('Per persona','wceventsfp'); ?></option><option value="per_child"><?php _e('Solo bambino','wceventsfp'); ?></option><option value="per_adult"><?php _e('Solo adulto','wceventsfp'); ?></option></select></td>
                        <td><input type="number" step="0.01" min="0" name="_wcefp_extras[{{INDEX}}][price]" /></td>
                        <td><input type="checkbox" name="_wcefp_extras[{{INDEX}}][required]" /></td>
                        <td><input type="number" step="1" min="0" name="_wcefp_extras[{{INDEX}}][max_qty]" /></td>
                        <td><input type="number" step="1" min="0" name="_wcefp_extras[{{INDEX}}][stock]" /></td>
                        <td><button type="button" class="button wcefp-remove-extra">&times;</button></td>
                    </tr>
                </script>
            </div>

            <div class="options_group">
                <h3><?php _e('Ricorrenze settimanali & Slot', 'wceventsfp'); ?></h3>
                <p class="form-field">
                    <label><?php _e('Giorni','wceventsfp'); ?></label>
                    <?php wp_nonce_field('wcefp_weekdays','wcefp_weekdays_nonce'); ?>
                    <ul class="wcefp-weekdays">
                    <?php
                    $days = get_post_meta($post->ID, '_wcefp_weekdays', true); $days = is_array($days)?$days:[];
                    $lbl = [__('Dom','wceventsfp'),__('Lun','wceventsfp'),__('Mar','wceventsfp'),__('Mer','wceventsfp'),__('Gio','wceventsfp'),__('Ven','wceventsfp'),__('Sab','wceventsfp')];
                    for($i=0;$i<7;$i++):
                        printf('<li><label><input type="checkbox" name="_wcefp_weekdays[]" value="%d" %s /> %s</label></li>',
                            $i, checked(in_array($i,$days), true, false), esc_html($lbl[$i]));
                    endfor; ?>
                    </ul>
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
        if (!isset($_POST['wcefp_weekdays_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcefp_weekdays_nonce'])), 'wcefp_weekdays')) {
            return;
        }
        $pid = $product->get_id();
        $keys = [
            '_wcefp_price_adult','_wcefp_price_child','_wcefp_capacity_per_slot',
            '_wcefp_time_slots','_wcefp_duration_minutes',
            '_wcefp_languages','_wcefp_includes','_wcefp_excludes','_wcefp_cancellation'
        ];
        foreach ($keys as $k) if (isset($_POST[$k])) update_post_meta($pid, $k, wp_unslash($_POST[$k]));

        if (isset($_POST['wcefp_meeting_point'])) {
            update_post_meta($pid, '_wcefp_meeting_point', sanitize_text_field(wp_unslash($_POST['wcefp_meeting_point'])));
        } else {
            delete_post_meta($pid, '_wcefp_meeting_point');
        }

        // Extra opzionali
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_product_extras';
        $wpdb->delete($tbl, ['product_id'=>$pid]);
        if (isset($_POST['_wcefp_extras']) && is_array($_POST['_wcefp_extras'])) {
            $order = 0;
            foreach ($_POST['_wcefp_extras'] as $ex) {
                $name = sanitize_text_field($ex['name'] ?? '');
                if ($name === '') continue;
                $price = isset($ex['price']) ? floatval($ex['price']) : 0;
                $pricing = sanitize_key($ex['pricing_type'] ?? 'per_order');
                $required = isset($ex['required']) ? 1 : 0;
                $max_qty = isset($ex['max_qty']) ? intval($ex['max_qty']) : 0;
                $stock   = isset($ex['stock']) ? intval($ex['stock']) : 0;
                $extra_id = intval($ex['id'] ?? 0);
                if (!$extra_id) {
                    $existing = get_page_by_title($name, OBJECT, 'wcefp_extra');
                    if ($existing) $extra_id = $existing->ID;
                }
                if (!$extra_id) {
                    $extra_id = wp_insert_post(['post_type'=>'wcefp_extra','post_title'=>$name,'post_status'=>'publish']);
                } else {
                    wp_update_post(['ID'=>$extra_id,'post_title'=>$name]);
                }
                $wpdb->insert($tbl, [
                    'product_id'=>$pid,
                    'extra_id'=>$extra_id,
                    'pricing_type'=>$pricing,
                    'price'=>$price,
                    'required'=>$required,
                    'max_qty'=>$max_qty,
                    'stock'=>$stock,
                    'sort_order'=>$order++
                ], ['%d','%d','%s','%f','%d','%d','%d','%d']);
            }
        }
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
            'ga4_enabled' => (get_option('wcefp_ga4_enable', '1') === '1'),
            'meta_pixel_id' => sanitize_text_field(get_option('wcefp_meta_pixel_id','')),
        ]);

        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    }

    /* Iniezione GA4/GTM (se impostati) */
    public function inject_ga_scripts() {
        if (is_admin()) return;
        $gtm_id = trim(get_option('wcefp_gtm_id',''));
        $ga4_id = trim(get_option('wcefp_ga4_id',''));
        $ga4_enabled = (get_option('wcefp_ga4_enable','1') === '1');
        if (!$ga4_enabled) return;

        if ($gtm_id) {
            ?>
            <!-- Google Tag Manager (WCEventsFP) -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
            <!-- End Google Tag Manager -->
            <?php
        } elseif ($ga4_id) {
            ?>
            <!-- Google Analytics 4 (WCEventsFP) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($ga4_id); ?>"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '<?php echo esc_js($ga4_id); ?>');
            </script>
            <!-- End GA4 -->
            <?php
        }
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

    /* ---------- Meta Pixel ---------- */
    public function inject_meta_pixel_base() {
        if (is_admin()) return;
        $pixel_id = trim(get_option('wcefp_meta_pixel_id',''));
        if (!$pixel_id) return;
        ?>
        <!-- Meta Pixel Code (WCEventsFP) -->
        <script>
          !function(f,b,e,v,n,t,s)
          {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
          n.callMethod.apply(n,arguments):n.queue.push(arguments)};
          if(!f._fbq)f._fbq=n; n.push=n; n.loaded=!0; n.version='2.0';
          n.queue=[]; t=b.createElement(e); t.async=!0;
          t.src=v; s=b.getElementsByTagName(e)[0];
          s.parentNode.insertBefore(t,s)}(window, document,'script',
          'https://connect.facebook.net/en_US/fbevents.js');
          fbq('init', '<?php echo esc_js($pixel_id); ?>');
          fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
          src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1"
        /></noscript>
        <!-- End Meta Pixel Code -->
        <?php
    }

    public function meta_pixel_purchase($order_id) {
        $pixel_id = trim(get_option('wcefp_meta_pixel_id',''));
        if (!$pixel_id) return;
        $order = wc_get_order($order_id); if(!$order) return;
        $value = (float)$order->get_total();
        $currency = $order->get_currency();
        echo "<script>if(window.fbq){ fbq('track','Purchase', {value: ".wp_json_encode($value).", currency: ".wp_json_encode($currency)."}); }</script>";
    }

    /* ---------- ICS ---------- */
    public function render_ics_downloads($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;
        $ics = [];
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $occId = $item->get_meta('Occorrenza');
            if (!$occId) continue;
            $ics_url = add_query_arg([
                'wcefp_ics' => 1,
                'order'     => $order_id,
                'item'      => $item->get_id(),
                'pid'       => $pid,
                'occ'       => $occId,
            ], home_url('/'));
            $ics[] = ['title'=> $item->get_name(), 'url'=>$ics_url];
        }
        if (!$ics) return;
        echo '<div class="wcefp-ics"><h3>'.esc_html__('Aggiungi al calendario','wceventsfp').'</h3><ul>';
        foreach ($ics as $row) {
            printf('<li><a class="button" href="%s">%s</a></li>', esc_url($row['url']), esc_html($row['title']));
        }
        echo '</ul></div>';
    }

    public function serve_ics() {
        if (!isset($_GET['wcefp_ics'])) return;
        $order_id = intval($_GET['order'] ?? 0);
        $item_id  = intval($_GET['item'] ?? 0);
        $pid      = intval($_GET['pid'] ?? 0);
        $occ_id   = intval($_GET['occ'] ?? 0);
        if (!$order_id || !$item_id || !$pid || !$occ_id) return;

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $row = $wpdb->get_row($wpdb->prepare("SELECT start_datetime,end_datetime FROM $tbl WHERE id=%d AND product_id=%d", $occ_id, $pid));
        if (!$row) return;

        $title = get_the_title($pid);
        $loc = get_bloginfo('name');
        $desc = wp_strip_all_tags(get_post_field('post_content', $pid));
        $uid = 'wcefp-'.md5($occ_id.'-'.$pid.'-'.$order_id).'@'.parse_url(home_url(), PHP_URL_HOST);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-'.$occ_id.'.ics"');

        $dtstart = gmdate('Ymd\THis\Z', strtotime($row->start_datetime));
        $dtend   = gmdate('Ymd\THis\Z', strtotime($row->end_datetime ?: $row->start_datetime.' +2 hours'));

        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//WCEFP//ICS 1.0//EN\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nSUMMARY:".self::esc_ics($title)."\r\nDESCRIPTION:".self::esc_ics($desc)."\r\nLOCATION:".self::esc_ics($loc)."\r\nEND:VEVENT\r\nEND:VCALENDAR";
        exit;
    }
    private static function esc_ics($s){ return preg_replace('/([,;])/','\\\$1', str_replace("\n",'\\n', $s)); }

    /* ---------- Brevo ---------- */
    public function brevo_on_completed($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;

        $event_item = null;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p) continue;
            if (in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $event_item = $item; break; }
        }
        if (!$event_item) return;

        $api_key = trim(get_option('wcefp_brevo_api_key',''));
        if (!$api_key) return;

        $email = $order->get_billing_email();
        $firstname = $order->get_billing_first_name();
        $product_name = $event_item->get_name();
        $occ = (string)$event_item->get_meta('Occorrenza');
        $ad  = intval($event_item->get_meta('Adulti'));
        $ch  = intval($event_item->get_meta('Bambini'));

        $locale = get_locale();
        $is_it = (stripos($locale, 'it_') === 0 || stripos($locale, 'it') === 0);
        $list_it = intval(get_option('wcefp_brevo_list_it', 0));
        $list_en = intval(get_option('wcefp_brevo_list_en', 0));
        $list_id = $is_it ? $list_it : $list_en;
        $extra_tag = trim(get_option('wcefp_brevo_tag', ''));
        $tags = ['WCEFP'];
        if ($extra_tag !== '') { $tags[] = $extra_tag; }

        // Upsert contatto
        $this->brevo_request('https://api.brevo.com/v3/contacts', 'POST', [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME'   => $firstname,
                'ORDER_ID'    => (string)$order->get_order_number(),
                'TOTAL'       => (float)$order->get_total(),
                'LANG'        => $is_it ? 'IT' : 'EN',
                'ProductName' => $product_name,
                'OccDate'     => $occ,
                'Adults'      => $ad,
                'Children'    => $ch,
            ],
            'listIds' => $list_id ? [$list_id] : [],
            'updateEnabled' => true,
            'tags' => $tags,
        ], $api_key);

        // Transazionale (se Template ID)
        $tpl_id = intval(get_option('wcefp_brevo_template_id', 0));
        $from_email = sanitize_email(get_option('wcefp_brevo_from_email', get_bloginfo('admin_email')));
        $from_name  = sanitize_text_field(get_option('wcefp_brevo_from_name', get_bloginfo('name')));

        $items_html = '';
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p || !in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $occ = esc_html($item->get_meta('Occorrenza'));
            $ad  = intval($item->get_meta('Adulti'));
            $ch  = intval($item->get_meta('Bambini'));
            $items_html .= '<li><strong>'.esc_html($item->get_name()).'</strong> – Occorrenza: '.$occ.' – Adulti: '.$ad.' – Bambini: '.$ch.'</li>';
        }
        $ics_note = __('Trovi il link per aggiungere al calendario nella pagina di conferma ordine.', 'wceventsfp');

        if ($tpl_id > 0) {
            $payload = [
                'to' => [['email'=>$email, 'name'=>$firstname]],
                'templateId' => $tpl_id,
                'params' => [
                    'ORDER_NUMBER' => (string)$order->get_order_number(),
                    'ORDER_TOTAL'  => (float)$order->get_total(),
                    'ORDER_ITEMS_HTML' => $items_html,
                    'ICS_NOTE' => $ics_note,
                    'FIRSTNAME' => $firstname,
                ],
                'sender' => ['email'=>$from_email, 'name'=>$from_name],
            ];
        } else {
            $payload = [
                'to' => [['email'=>$email, 'name'=>$firstname]],
                'subject' => sprintf(__('Conferma prenotazione #%s','wceventsfp'), $order->get_order_number()),
                'htmlContent' => '<h2>'.esc_html__('Grazie per la prenotazione','wceventsfp').'</h2><ul>'.$items_html.'</ul><p>'.$ics_note.'</p>',
                'sender' => ['email'=>$from_email, 'name'=>$from_name],
            ];
        }
        $this->brevo_request('https://api.brevo.com/v3/smtp/email', 'POST', $payload, $api_key);
    }

    private function brevo_request($url,$method='POST',$body=[],$api_key=null) {
        $key = $api_key ?: trim(get_option('wcefp_brevo_api_key',''));
        if (!$key) return false;
        $args = [
            'headers'=>['accept'=>'application/json','api-key'=>$key,'content-type'=>'application/json'],
            'method'=>$method,'body'=>!empty($body)?wp_json_encode($body):null,'timeout'=>20,
        ];
        $res = wp_remote_request($url,$args);
        if (is_wp_error($res)) error_log('WCEFP Brevo error: '.$res->get_error_message());
        return $res;
    }

    /* ---------- Disattiva email Woo (se solo eventi) ---------- */
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

    /* ---------- AJAX admin base ---------- */
    public function ajax_get_bookings() {
        check_ajax_referer('wcefp_admin','nonce');
        $orders = wc_get_orders(['limit'=>200,'type'=>'shop_order','status'=>['wc-processing','wc-completed','wc-on-hold'],'date_created'=>'>='. (new DateTime('-120 days'))->format('Y-m-d')]);
        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $it) {
                $p = $it->get_product(); if(!$p) continue;
                if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
                $rows[] = [
                    'order'   => $order->get_order_number(),
                    'status'  => wc_get_order_status_name( $order->get_status() ),
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
        $pid  = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if ($pid) {
            $evts = $wpdb->get_results($wpdb->prepare(
                "SELECT id,product_id,start_datetime,end_datetime,capacity,booked,status FROM $tbl WHERE product_id=%d AND start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC",
                $pid, "$from 00:00:00", "$to 23:59:59"
            ), ARRAY_A);
        } else {
            $evts = $wpdb->get_results($wpdb->prepare(
                "SELECT id,product_id,start_datetime,end_datetime,capacity,booked,status FROM $tbl WHERE start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC",
                "$from 00:00:00", "$to 23:59:59"
            ), ARRAY_A);
        }

        $events = [];
        foreach ($evts as $e) {
            $events[] = [
                'id'    => (int)$e['id'],
                'title' => get_the_title((int)$e['product_id'])." (".intval($e['booked'])."/".intval($e['capacity']).")",
                'start' => $e['start_datetime'],
                'end'   => $e['end_datetime'] ?: null,
                'color' => ($e['status'] === 'cancelled') ? '#d1d5db' : '',
                'extendedProps' => [
                    'product_id' => (int)$e['product_id'],
                    'capacity'   => (int)$e['capacity'],
                    'booked'     => (int)$e['booked'],
                    'status'     => $e['status'],
                ],
            ];
        }
        wp_send_json_success(['events'=>$events]);
    }

    public function ajax_update_occurrence() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        $occ = intval($_POST['occ'] ?? 0);
        $cap = isset($_POST['capacity']) ? max(0, intval($_POST['capacity'])) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        if (!$occ) wp_send_json_error(['msg'=>'ID mancante']);

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $data = []; $fmt = [];
        if ($cap !== null) { $data['capacity'] = $cap; $fmt[] = '%d'; }
        if ($status !== null && in_array($status, ['active','cancelled'], true)) { $data['status'] = $status; $fmt[] = '%s'; }
        if (!$data) wp_send_json_error(['msg'=>'Nessun dato da aggiornare']);

        $res = $wpdb->update($tbl, $data, ['id'=>$occ], $fmt, ['%d']);
        if ($res === false) wp_send_json_error(['msg'=>'Errore aggiornamento']);
        wp_send_json_success(['ok'=>true]);
    }

    /* ---------- Export CSV ---------- */
    public function export_occurrences_csv() {
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_export')) wp_die('Not allowed');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY start_datetime DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wcefp_occurrences.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','product_id','product_name','start','end','capacity','booked','available','status']);
        foreach ($rows as $r) {
            $avail = max(0, intval($r['capacity']) - intval($r['booked']));
            fputcsv($out, [
                $r['id'], $r['product_id'], get_the_title((int)$r['product_id']),
                $r['start_datetime'], $r['end_datetime'], $r['capacity'], $r['booked'], $avail, $r['status']
            ]);
        }
        fclose($out); exit;
    }

    public function export_bookings_csv() {
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_export')) wp_die('Not allowed');
        $orders = wc_get_orders(['limit'=>-1,'type'=>'shop_order','status'=>array_keys(wc_get_order_statuses()),'date_created'=>'>='. (new DateTime('-365 days'))->format('Y-m-d')]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wcefp_bookings.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['order_number','status','date','customer','email','product','occurrence_id','adults','children','extras','line_total']);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $it) {
                $p = $it->get_product(); if(!$p) continue;
                if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
                $extras = $it->get_meta('Extra');
                fputcsv($out, [
                    $order->get_order_number(),
                    wc_get_order_status_name( $order->get_status() ),
                    $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                    trim($order->get_billing_first_name().' '.$order->get_billing_last_name()),
                    $order->get_billing_email(),
                    $p->get_name(),
                    $it->get_meta('Occorrenza'),
                    intval($it->get_meta('Adulti')),
                    intval($it->get_meta('Bambini')),
                    is_string($extras) ? $extras : '',
                    number_format($order->get_item_total($it, false), 2, '.', ''),
                ]);
            }
        }
        fclose($out); exit;
    }

    /* ---------- Allocazione / Rilascio posti ---------- */
    public function allocate_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], $row['qty']);
            if ($ok) {
                $order->add_order_note("Posti allocati per \"{$row['name']}\" ( +{$row['qty']} ).");
            } else {
                $order->add_order_note("ATTENZIONE: capienza insufficiente per \"{$row['name']}\". Verificare.");
                $order->update_status('on-hold');
            }
        }
    }
    public function release_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], -$row['qty']);
            if ($ok) {
                $order->add_order_note("Posti rilasciati per \"{$row['name']}\" ( -{$row['qty']} ).");
            }
        }
    }

    public function allocate_extras_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderExtraOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_adjust_extra_stock_atomic($row['product'], $row['extra'], -$row['qty']);
            if ($ok) {
                $order->add_order_note("Extra allocato per \"{$row['name']}\" ( -{$row['qty']} ).");
            } else {
                $order->add_order_note("ATTENZIONE: stock insufficiente per extra \"{$row['name']}\".");
                $order->update_status('on-hold');
            }
        }
    }
    public function release_extras_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderExtraOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_adjust_extra_stock_atomic($row['product'], $row['extra'], $row['qty']);
            if ($ok) {
                $order->add_order_note("Extra rilasciato per \"{$row['name']}\" ( +{$row['qty']} ).");
            }
        }
    }
}

/* ---------- Helper allocazione ---------- */
if (!function_exists('wcefp_update_booked_atomic')) {
    function wcefp_update_booked_atomic($occ_id, $delta){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        if ($delta > 0) {
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = booked + %d WHERE id=%d AND status='active' AND (capacity - booked) >= %d", $delta, $occ_id, $delta);
        } else {
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = GREATEST(0, booked + %d) WHERE id=%d", $delta, $occ_id);
        }
        $res = $wpdb->query($sql);
        return ($res && intval($res) > 0);
    }
}

if (!function_exists('wcefp_adjust_extra_stock_atomic')) {
    function wcefp_adjust_extra_stock_atomic($product_id, $extra_id, $delta){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_product_extras';
        if ($delta < 0) {
            $delta = absint($delta);
            $sql = $wpdb->prepare("UPDATE $tbl SET stock = stock - %d WHERE product_id=%d AND extra_id=%d AND stock >= %d AND stock > 0", $delta, $product_id, $extra_id, $delta);
        } else {
            $delta = absint($delta);
            $sql = $wpdb->prepare("UPDATE $tbl SET stock = stock + %d WHERE product_id=%d AND extra_id=%d AND stock > 0", $delta, $product_id, $extra_id);
        }
        $res = $wpdb->query($sql);
        return ($res && intval($res) > 0);
    }
}

class WCEFP_OrderSeatOps {
    public static function get_items_to_alloc($order){
        $rows = [];
        foreach ($order->get_items() as $it) {
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $occId = intval($it->get_meta('Occorrenza'));
            $ad = intval($it->get_meta('Adulti'));
            $ch = intval($it->get_meta('Bambini'));
            $qty = max(0, $ad + $ch);
            if ($occId && $qty>0) $rows[] = ['occ'=>$occId,'qty'=>$qty,'name'=>$p->get_name()];
        }
        return $rows;
    }
}

class WCEFP_OrderExtraOps {
    public static function get_items_to_alloc($order){
        $rows = [];
        foreach ($order->get_items() as $it){
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $ad = intval($it->get_meta('Adulti'));
            $ch = intval($it->get_meta('Bambini'));
            $json = $it->get_meta('_wcefp_extras_data');
            if(!$json) continue;
            $extras = json_decode($json, true);
            if(!is_array($extras)) continue;
            foreach ($extras as $ex){
                $id = intval($ex['id'] ?? 0);
                $qty = intval($ex['qty'] ?? 0);
                $pricing = $ex['pricing'] ?? 'per_order';
                if($id && $qty>0){
                    $mult = $qty;
                    if($pricing === 'per_person') $mult *= ($ad + $ch);
                    elseif($pricing === 'per_child') $mult *= $ch;
                    elseif($pricing === 'per_adult') $mult *= $ad;
                    $rows[] = ['product'=>$p->get_id(),'extra'=>$id,'qty'=>$mult,'name'=>$ex['name'] ?? 'extra'];
                }
            }
        }
        return $rows;
    }
}
