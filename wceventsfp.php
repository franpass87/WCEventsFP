<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, slot orari, prezzi A/B, extra, KPI, Calendario, GA4/GTM, Brevo, anti-overbooking e ICS.
 * Version:     1.2.0
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCEFP_VERSION', '1.2.0');
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

        /* Frontend + GA4 */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);
        add_action('woocommerce_thankyou', [$this, 'render_ics_downloads'], 30);

        /* Brevo */
        add_action('woocommerce_order_status_completed', [$this, 'send_to_brevo_on_completed']);

        /* Admin */
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_ajax_wcefp_update_occurrence', ['WCEFP_Recurring', 'ajax_update_occurrence']);
        add_action('wp_ajax_wcefp_bulk_occurrences_csv', ['WCEFP_Recurring', 'ajax_bulk_occurrences_csv']);


        /* AJAX admin */
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);

        /* Shortcode + AJAX pubblici */
        add_shortcode('wcefp_booking', ['WCEFP_Frontend', 'shortcode_booking']);
        add_action('wp_ajax_nopriv_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);

        /* Prezzo dinamico + meta */
        add_action('woocommerce_before_calculate_totals', ['WCEFP_Frontend', 'apply_dynamic_price']);
        add_action('woocommerce_checkout_create_order_line_item', ['WCEFP_Frontend', 'add_line_item_meta'], 10, 4);

        /* Allocazione posti anti-overbooking: quando l’ordine diventa processing/completed */
        add_action('woocommerce_order_status_processing', [$this, 'allocate_seats_on_status']);
        add_action('woocommerce_order_status_completed',  [$this, 'allocate_seats_on_status']);
        /* Rilascio posti se rimborsato/cancellato */
        add_action('woocommerce_order_status_refunded',   [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_cancelled',  [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_failed',     [$this, 'release_seats_on_status']);
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
  <h3><?php _e('Info esperienza', 'wceventsfp'); ?></h3>
  <?php
  woocommerce_wp_text_input(['id'=>'_wcefp_meeting_point','label'=>__('Meeting point','wceventsfp')]);
  woocommerce_wp_text_input(['id'=>'_wcefp_map_embed','label'=>__('Map Embed (iframe URL)','wceventsfp')]);
  woocommerce_wp_text_input(['id'=>'_wcefp_languages','label'=>__('Lingue (CSV)','wceventsfp'),'placeholder'=>'Italiano, Inglese']);
  ?>
  <p class="form-field">
    <label for="_wcefp_includes"><?php _e('Incluso (uno per riga)','wceventsfp'); ?></label>
    <textarea id="_wcefp_includes" name="_wcefp_includes" style="width:100%;height:80px"></textarea>
  </p>
  <p class="form-field">
    <label for="_wcefp_excludes"><?php _e('Escluso (uno per riga)','wceventsfp'); ?></label>
    <textarea id="_wcefp_excludes" name="_wcefp_excludes" style="width:100%;height:80px"></textarea>
  </p>
  <?php
  woocommerce_wp_text_input(['id'=>'_wcefp_cancellation','label'=>__('Policy cancellazione','wceventsfp'),'placeholder'=>'Gratis fino a 24h prima']);
  woocommerce_wp_text_input(['id'=>'_wcefp_highlights','label'=>__('Punti chiave (CSV)','wceventsfp'),'placeholder'=>'Degustazione 4 vini, Tour cantina, ...']);
  ?>
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
        $keys = ['_wcefp_price_adult','_wcefp_price_child','_wcefp_capacity_per_slot','_wcefp_extras_json','_wcefp_time_slots','_wcefp_duration_minutes',
         '_wcefp_meeting_point','_wcefp_map_embed','_wcefp_languages','_wcefp_includes','_wcefp_excludes','_wcefp_cancellation','_wcefp_highlights'];
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
add_filter('the_content', function($content){
    if (!is_singular('product')) return $content;
    $product = wc_get_product(get_the_ID()); if(!$product) return $content;
    if (!in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) return $content;

    $pid = $product->get_id();
    $mp   = get_post_meta($pid, '_wcefp_meeting_point', true);
    $map  = get_post_meta($pid, '_wcefp_map_embed', true);
    $langs= get_post_meta($pid, '_wcefp_languages', true);
    $inc  = array_filter(array_map('trim', explode("\n", (string)get_post_meta($pid,'_wcefp_includes',true))));
    $exc  = array_filter(array_map('trim', explode("\n", (string)get_post_meta($pid,'_wcefp_excludes',true))));
    $pol  = get_post_meta($pid, '_wcefp_cancellation', true);
    $hi   = array_filter(array_map('trim', explode(',', (string)get_post_meta($pid,'_wcefp_highlights',true))));

    ob_start(); ?>
    <section class="wcefp-hero">
      <h2><?php echo esc_html(get_the_title()); ?></h2>
      <?php if ($hi): ?>
        <ul class="wcefp-highlights"><?php foreach ($hi as $h) echo '<li>'.esc_html($h).'</li>'; ?></ul>
      <?php endif; ?>
    </section>

    <section class="wcefp-grid">
      <div class="wcefp-main">
        <div class="wcefp-desc"><?php echo wpautop(get_the_content()); ?></div>

        <h3><?php _e('Cosa è incluso', 'wceventsfp'); ?></h3>
        <?php if ($inc): echo '<ul class="wcefp-list">'; foreach($inc as $x) echo '<li>'.esc_html($x).'</li>'; echo '</ul>'; else echo '<p>-</p>'; endif; ?>

        <h3><?php _e('Cosa non è incluso', 'wceventsfp'); ?></h3>
        <?php if ($exc): echo '<ul class="wcefp-list">'; foreach($exc as $x) echo '<li>'.esc_html($x).'</li>'; echo '</ul>'; else echo '<p>-</p>'; endif; ?>

        <h3><?php _e('Lingue', 'wceventsfp'); ?></h3>
        <p><?php echo esc_html($langs ?: '-'); ?></p>

        <h3><?php _e('Policy di cancellazione', 'wceventsfp'); ?></h3>
        <p><?php echo esc_html($pol ?: '-'); ?></p>

        <?php if ($map): ?>
          <h3><?php _e('Mappa', 'wceventsfp'); ?></h3>
          <div class="wcefp-map">
            <iframe src="<?php echo esc_url($map); ?>" width="100%" height="320" style="border:0" loading="lazy"></iframe>
          </div>
        <?php endif; ?>
      </div>

      <aside class="wcefp-sidebar">
        <div class="wcefp-card">
          <h3><?php _e('Prenota', 'wceventsfp'); ?></h3>
          <?php echo do_shortcode('[wcefp_booking product_id="'.intval($pid).'"]'); ?>
          <?php if ($mp): ?><p style="margin-top:12px"><strong><?php _e('Meeting point:', 'wceventsfp'); ?></strong> <?php echo esc_html($mp); ?></p><?php endif; ?>
        </div>
      </aside>
    </section>
    <?php
    return ob_get_clean();
});

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

    /* ---------- ICS in thank-you ---------- */
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

    /* Servizio ICS semplice */
    public static function serve_ics() {
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
}
add_action('init', ['WCEFP_Plugin','serve_ics']);

/* ---------------- ALLOCAZIONE / RILASCIO POSTI ---------------- */
if (!function_exists('wcefp_update_booked_atomic')) {
    function wcefp_update_booked_atomic($occ_id, $delta){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        if ($delta > 0) {
            // Aggiungi posti solo se disponibili
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = booked + %d WHERE id=%d AND (capacity - booked) >= %d", $delta, $occ_id, $delta);
        } else {
            // Rilascia posti (delta negativo)
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = GREATEST(0, booked + %d) WHERE id=%d", $delta, $occ_id);
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

if (!method_exists('WCEFP_Plugin','allocate_seats_on_status')) {
    // (definiti nella classe; questo è a scopo compatibilità)
}

add_action('plugins_loaded', function(){
    if (!function_exists('wcefp_plugin_allocate_attach')) {
        function wcefp_plugin_allocate_attach(){
            $plugin = WCEFP();
            $plugin->allocate_seats_on_status = function($order_id){
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
            };
            $plugin->release_seats_on_status = function($order_id){
                $order = wc_get_order($order_id); if(!$order) return;
                $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
                foreach ($todo as $row){
                    $ok = wcefp_update_booked_atomic($row['occ'], -$row['qty']);
                    if ($ok) {
                        $order->add_order_note("Posti rilasciati per \"{$row['name']}\" ( -{$row['qty']} ).");
                    }
                }
            };
        }
        wcefp_plugin_allocate_attach();
    }
});
