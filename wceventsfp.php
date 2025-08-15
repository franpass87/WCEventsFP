<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Eventi & Esperienze per WooCommerce con ricorrenze, chiusure, slot, prezzi A/B, extra, KPI, Calendario, GA4/GTM (eventi custom), Meta Pixel, Brevo (ITA/ENG, voucher regalo), anti-overbooking, ICS e scheda stile OTA.
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

    $occ = $wpdb->prefix . 'wcefp_occurrences';
    $sql1 = "CREATE TABLE IF NOT EXISTS $occ (
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

    $blk = $wpdb->prefix . 'wcefp_blackouts';
    $sql2 = "CREATE TABLE IF NOT EXISTS $blk (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      product_id BIGINT UNSIGNED NULL,
      start_date DATE NOT NULL,
      end_date DATE NOT NULL,
      reason VARCHAR(190) NULL,
      INDEX (product_id),
      INDEX (start_date),
      INDEX (end_date)
    ) $charset;";

    $gif = $wpdb->prefix . 'wcefp_gift_vouchers';
    $sql3 = "CREATE TABLE IF NOT EXISTS $gif (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(64) NOT NULL,
      product_id BIGINT UNSIGNED NOT NULL,
      order_id BIGINT UNSIGNED NOT NULL,
      qty INT NOT NULL DEFAULT 1,
      recipient_name VARCHAR(190) NULL,
      recipient_email VARCHAR(190) NULL,
      message TEXT NULL,
      expires_at DATETIME NULL,
      redeemed TINYINT(1) NOT NULL DEFAULT 0,
      redeemed_at DATETIME NULL,
      redeemed_order_id BIGINT UNSIGNED NULL,
      redeemed_occurrence_id BIGINT UNSIGNED NULL,
      UNIQUE KEY uniq_code (code),
      INDEX (product_id),
      INDEX (order_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
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
        $this->ensure_db_schema();

        /* Tipi prodotto */
        add_filter('product_type_selector', [$this, 'register_product_types']);
        add_action('init', [$this, 'add_product_classes']);
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

        /* Tab prodotto */
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

        /* Esclusione archivi Woo */
        add_action('pre_get_posts', [$this, 'hide_from_archives']);

        /* Frontend assets + Pixel */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('wp_head', [$this, 'render_meta_pixel'], 5);

        /* GA4 + ICS + render scheda/booking */
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);
        add_action('woocommerce_thankyou', [$this, 'render_ics_downloads'], 30);
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_product_details'], 15);
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_booking_widget_auto'], 35);

        /* Brevo */
        add_action('woocommerce_order_status_completed', [$this, 'brevo_on_completed']);
        // add_action('woocommerce_order_status_processing', [$this, 'brevo_on_completed']); // se vuoi anche su processing

        /* Disattiva email Woo (solo-evento) opzionale */
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order',  [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',    [$this,'maybe_disable_wc_mail'], 10, 2);

        /* Admin */
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        /* AJAX Admin */
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);
        add_action('wp_ajax_wcefp_update_occurrence', [$this, 'ajax_update_occurrence']);

        /* Blackout (chiusure) add/del */
        add_action('admin_post_wcefp_blackout_add', [$this, 'handle_blackout_add']);
        add_action('admin_post_wcefp_blackout_del', [$this, 'handle_blackout_del']);

        /* Export CSV */
        add_action('admin_post_wcefp_export_occurrences', [$this, 'export_occurrences_csv']);
        add_action('admin_post_wcefp_export_bookings',    [$this, 'export_bookings_csv']);
        add_action('admin_post_wcefp_export_vouchers',    [$this, 'export_vouchers_csv']);

        /* Shortcode + AJAX pubblici */
        add_shortcode('wcefp_booking', ['WCEFP_Frontend', 'shortcode_booking']);
        add_shortcode('wcefp_gift_redeem', ['WCEFP_Frontend','shortcode_gift_redeem']);
        add_action('wp_ajax_nopriv_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_wcefp_validate_voucher', ['WCEFP_Frontend', 'ajax_validate_voucher']);
        add_action('wp_ajax_wcefp_validate_voucher', ['WCEFP_Frontend', 'ajax_validate_voucher']);

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
        add_action('init', [$this, 'serve_ics']);
    }

    private function ensure_db_schema(){
        global $wpdb;
        // nothing extra for now (handled by activation)
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
                woocommerce_wp_text_input(['id'=>'_wcefp_meeting_point','label'=>__('Meeting point (indirizzo)','wceventsfp'),'type'=>'text']);
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

    /* ---------- Frontend & GA4 ---------- */
    public function enqueue_frontend() {
        wp_enqueue_style('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/css/frontend.css', [], WCEFP_VERSION);
        wp_enqueue_script('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/js/frontend.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
            'fbPixel' => get_option('wcefp_meta_pixel_id',''),
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

        // Meta Pixel Purchase
        $pixel = get_option('wcefp_meta_pixel_id','');
        if ($pixel) {
            echo "<script>window.fbq && fbq('track','Purchase',{value:".json_encode((float)$order->get_total()).",currency:".wp_json_encode($order->get_currency())."});</script>";
        }
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

    /* ---------- Meta Pixel base ---------- */
    public function render_meta_pixel(){
        $pixel = trim(get_option('wcefp_meta_pixel_id',''));
        if (!$pixel) return;
        ?>
        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', <?php echo json_encode($pixel); ?>);
        fbq('track','PageView');
        </script><noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel); ?>&ev=PageView&noscript=1"/></noscript>
        <!-- End Meta Pixel Code -->
        <?php
    }

    /* ---------- Brevo: invio + segmentazione + voucher ---------- */
    public function brevo_on_completed($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;

        $has_event = false;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p) continue;
            if (in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $has_event = true; break; }
        }
        if (!$has_event) return;

        $api_key = trim(get_option('wcefp_brevo_api_key',''));
        if (!$api_key) return;

        $email = $order->get_billing_email();
        $firstname = $order->get_billing_first_name();

        // lingua preferita: IT se locale inizia per 'it', altrimenti EN (semplificato)
        $locale = get_locale();
        $lang = (strpos($locale,'it_')===0 || strpos($locale,'it')===0) ? 'IT' : 'EN';
        $list_id = $lang==='IT' ? intval(get_option('wcefp_brevo_list_it',0)) : intval(get_option('wcefp_brevo_list_en',0));

        // Upsert contatto + lista
        $contact_payload = [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME' => $firstname,
                'ORDER_ID'  => (string)$order->get_order_number(),
                'TOTAL'     => (float)$order->get_total(),
                'LANG'      => $lang,
            ],
            'updateEnabled' => true,
            'listIds' => $list_id ? [$list_id] : [],
        ];
        $this->brevo_request('https://api.brevo.com/v3/contacts', 'POST', $contact_payload, $api_key);

        // Gift: genera voucher + invio al destinatario
        foreach ($order->get_items() as $it) {
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;

            $recipient_email = trim((string)$it->get_meta('Gift Email'));
            if ($recipient_email) {
                $recipient_name = trim((string)$it->get_meta('Gift Nome'));
                $message        = trim((string)$it->get_meta('Gift Messaggio'));
                $qty            = max(1, intval($it->get_meta('Adulti')) + intval($it->get_meta('Bambini')));

                $code = self::generate_code();
                $exp_days = max(0, intval(get_option('wcefp_gift_expiry_days', 365)));
                $expires_at = $exp_days ? (new DateTime("+$exp_days days"))->format('Y-m-d H:i:s') : null;

                global $wpdb; $tbl = $wpdb->prefix.'wcefp_gift_vouchers';
                $wpdb->insert($tbl, [
                    'code'=>$code,'product_id'=>$p->get_id(),'order_id'=>$order_id,'qty'=>$qty,
                    'recipient_name'=>$recipient_name,'recipient_email'=>$recipient_email,
                    'message'=>$message,'expires_at'=>$expires_at,'redeemed'=>0
                ], ['%s','%d','%d','%d','%s','%s','%s','%s','%d']);

                // invio email voucher
                $redeem_url = add_query_arg(['voucher'=>$code], get_permalink(wc_get_page_id('shop'))); // o una pagina dedicata con shortcode [wcefp_gift_redeem]
                $gift_tpl = intval(get_option('wcefp_brevo_gift_template_id', 0));
                $from_email = sanitize_email(get_option('wcefp_brevo_from_email', get_bloginfo('admin_email')));
                $from_name  = sanitize_text_field(get_option('wcefp_brevo_from_name', get_bloginfo('name')));

                if ($gift_tpl > 0) {
                    $payload = [
                        'to' => [['email'=>$recipient_email, 'name'=>$recipient_name]],
                        'templateId' => $gift_tpl,
                        'params' => [
                            'RECIPIENT_NAME' => $recipient_name,
                            'GIVER_NAME'     => $firstname,
                            'VOUCHER_CODE'   => $code,
                            'PRODUCT'        => $p->get_name(),
                            'QTY'            => $qty,
                            'REDEEM_URL'     => $redeem_url,
                            'MESSAGE'        => $message,
                            'EXPIRES'        => $expires_at ? wp_date('d/m/Y', strtotime($expires_at)) : __('Nessuna scadenza','wceventsfp'),
                        ],
                        'sender' => ['email'=>$from_email, 'name'=>$from_name],
                    ];
                } else {
                    $payload = [
                        'to' => [['email'=>$recipient_email, 'name'=>$recipient_name]],
                        'subject' => sprintf(__('Hai ricevuto un regalo: %s','wceventsfp'), $p->get_name()),
                        'htmlContent' => '<h2>'.esc_html__('Buono regalo','wceventsfp').'</h2>'
                            .'<p>'.esc_html__('Codice','wceventsfp').': <strong>'.$code.'</strong></p>'
                            .'<p>'.esc_html__('Esperienza','wceventsfp').': '.esc_html($p->get_name()).'</p>'
                            .'<p>'.esc_html__('Persone incluse','wceventsfp').': '.intval($qty).'</p>'
                            .($expires_at?'<p>'.esc_html__('Scade il','wceventsfp').': '.wp_date('d/m/Y', strtotime($expires_at)).'</p>':'')
                            .($message?'<p><em>'.nl2br(esc_html($message)).'</em></p>':'')
                            .'<p><a href="'.esc_url($redeem_url).'">'.esc_html__('Usa il codice','wceventsfp').'</a></p>',
                        'sender' => ['email'=>$from_email, 'name'=>$from_name],
                    ];
                }
                $this->brevo_request('https://api.brevo.com/v3/smtp/email', 'POST', $payload, $api_key);
            }
        }
    }

    private static function generate_code($len=10){
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s=''; for($i=0;$i<$len;$i++){ $s .= $chars[random_int(0, strlen($chars)-1)]; }
        return $s;
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

    /* ---------- Admin ---------- */
    public function admin_menu() {
        $cap = 'manage_woocommerce';
        add_menu_page(__('Eventi & Degustazioni','wceventsfp'), __('Eventi & Degustazioni','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page'],'dashicons-calendar-alt',56);
        add_submenu_page('wcefp', __('Analisi KPI','wceventsfp'), __('Analisi KPI','wceventsfp'), $cap,'wcefp',[$this,'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista','wceventsfp'), __('Calendario & Lista','wceventsfp'), $cap,'wcefp-calendar',[$this,'render_calendar_page']);
        add_submenu_page('wcefp', __('Chiusure','wceventsfp'), __('Chiusure','wceventsfp'), $cap,'wcefp-blackouts',[$this,'render_blackouts_page']);
        add_submenu_page('wcefp', __('Gift Voucher','wceventsfp'), __('Gift Voucher','wceventsfp'), $cap,'wcefp-gifts',[$this,'render_gifts_page']);
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
            'posts_per_page' => 200,
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
        $kpi = ['orders_30'=>18,'revenue_30'=>2150.50,'fill_rate'=>63,'top_product'=>'Degustazione Classica']; ?>
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

    public function render_calendar_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Calendario & Lista Prenotazioni','wceventsfp'); ?></h1>
            <div class="wcefp-toolbar">
                <label><?php _e('Filtra prodotto','wceventsfp'); ?>:</label>
                <select id="wcefp-filter-product">
                    <option value="0"><?php _e('Tutti','wceventsfp'); ?></option>
                </select>
                <button class="button button-primary" id="wcefp-switch-calendar"><?php _e('Calendario','wceventsfp'); ?></button>
                <button class="button" id="wcefp-switch-list"><?php _e('Lista','wceventsfp'); ?></button>
            </div>
            <div id="wcefp-view" style="min-height:650px;"></div>
        </div><?php
    }

    public function render_blackouts_page() {
        if (!current_user_can('manage_woocommerce')) return;
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_blackouts';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY start_date DESC", ARRAY_A); ?>
        <div class="wrap">
            <h1><?php _e('Chiusure straordinarie','wceventsfp'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wcefp-form-row">
                <?php wp_nonce_field('wcefp_blackout_add'); ?>
                <input type="hidden" name="action" value="wcefp_blackout_add" />
                <label><?php _e('Prodotto','wceventsfp'); ?>:
                    <select name="product_id">
                        <option value="0"><?php _e('Tutti (globale)','wceventsfp'); ?></option>
                        <?php foreach ($this->get_events_products_for_filter() as $p): ?>
                            <option value="<?php echo esc_attr($p['id']); ?>"><?php echo esc_html($p['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php _e('Dal','wceventsfp'); ?>: <input type="date" name="start_date" required /></label>
                <label><?php _e('Al','wceventsfp'); ?>: <input type="date" name="end_date" required /></label>
                <label><?php _e('Motivo','wceventsfp'); ?>: <input type="text" name="reason" /></label>
                <button class="button button-primary"><?php _e('Aggiungi','wceventsfp'); ?></button>
            </form>

            <table class="widefat striped">
                <thead><tr><th><?php _e('ID','wceventsfp'); ?></th><th><?php _e('Prodotto','wceventsfp'); ?></th><th><?php _e('Periodo','wceventsfp'); ?></th><th><?php _e('Motivo','wceventsfp'); ?></th><th><?php _e('Azioni','wceventsfp'); ?></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5"><?php _e('Nessuna chiusura.','wceventsfp'); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo intval($r['id']); ?></td>
                        <td><?php echo $r['product_id'] ? esc_html(get_the_title(intval($r['product_id']))) : '<em>'.esc_html__('Globale','wceventsfp').'</em>'; ?></td>
                        <td><?php echo esc_html($r['start_date'].' → '.$r['end_date']); ?></td>
                        <td><?php echo esc_html($r['reason']); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_blackout_del&id='.$r['id']), 'wcefp_blackout_del_'.$r['id'])); ?>"><?php _e('Elimina','wceventsfp'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div><?php
    }

    public function render_gifts_page() {
        if (!current_user_can('manage_woocommerce')) return;
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_gift_vouchers';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY id DESC LIMIT 200", ARRAY_A); ?>
        <div class="wrap">
            <h1><?php _e('Gift voucher','wceventsfp'); ?></h1>
            <p>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_vouchers'), 'wcefp_export') ); ?>"><?php _e('Esporta CSV','wceventsfp'); ?></a>
            </p>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php _e('Codice','wceventsfp'); ?></th>
                    <th><?php _e('Prodotto','wceventsfp'); ?></th>
                    <th><?php _e('Qty','wceventsfp'); ?></th>
                    <th><?php _e('Destinatario','wceventsfp'); ?></th>
                    <th><?php _e('Scadenza','wceventsfp'); ?></th>
                    <th><?php _e('Stato','wceventsfp'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6"><?php _e('Nessun voucher.','wceventsfp'); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?php echo esc_html($r['code']); ?></code></td>
                        <td><?php echo esc_html(get_the_title(intval($r['product_id']))); ?></td>
                        <td><?php echo intval($r['qty']); ?></td>
                        <td><?php echo esc_html($r['recipient_name']).' &lt;'.esc_html($r['recipient_email']).'&gt;'; ?></td>
                        <td><?php echo $r['expires_at'] ? esc_html(wp_date('d/m/Y', strtotime($r['expires_at']))) : '—'; ?></td>
                        <td><?php echo intval($r['redeemed']) ? esc_html__('Usato','wceventsfp') : esc_html__('Disponibile','wceventsfp'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div><?php
    }

    public function render_export_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Esporta CSV','wceventsfp'); ?></h1>
            <p><?php _e('Scarica i dati per analisi o backup.','wceventsfp'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_occurrences'), 'wcefp_export') ); ?>"><?php _e('Occorrenze','wceventsfp'); ?></a>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_bookings'), 'wcefp_export') ); ?>"><?php _e('Prenotazioni','wceventsfp'); ?></a>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_vouchers'), 'wcefp_export') ); ?>"><?php _e('Gift voucher','wceventsfp'); ?></a>
            </p>
        </div><?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['wcefp_save']) && check_admin_referer('wcefp_settings')) {
            update_option('wcefp_default_capacity', intval($_POST['wcefp_default_capacity'] ?? 0));
            update_option('wcefp_disable_wc_emails_for_events', isset($_POST['wcefp_disable_wc_emails_for_events']) ? '1' : '0');

            update_option('wcefp_brevo_api_key', sanitize_text_field($_POST['wcefp_brevo_api_key'] ?? ''));
            update_option('wcefp_brevo_template_id', intval($_POST['wcefp_brevo_template_id'] ?? 0));
            update_option('wcefp_brevo_gift_template_id', intval($_POST['wcefp_brevo_gift_template_id'] ?? 0));
            update_option('wcefp_brevo_from_email', sanitize_email($_POST['wcefp_brevo_from_email'] ?? ''));
            update_option('wcefp_brevo_from_name', sanitize_text_field($_POST['wcefp_brevo_from_name'] ?? ''));
            update_option('wcefp_brevo_list_it', intval($_POST['wcefp_brevo_list_it'] ?? 0));
            update_option('wcefp_brevo_list_en', intval($_POST['wcefp_brevo_list_en'] ?? 0));

            update_option('wcefp_gift_expiry_days', intval($_POST['wcefp_gift_expiry_days'] ?? 365));

            update_option('wcefp_meta_pixel_id', sanitize_text_field($_POST['wcefp_meta_pixel_id'] ?? ''));
            echo '<div class="updated"><p>Salvato.</p></div>';
        }
        $cap = get_option('wcefp_default_capacity', 0);
        $dis = get_option('wcefp_disable_wc_emails_for_events','0')==='1';
        $api = get_option('wcefp_brevo_api_key','');
        $tpl = intval(get_option('wcefp_brevo_template_id', 0));
        $tpl_gift = intval(get_option('wcefp_brevo_gift_template_id', 0));
        $from_email = get_option('wcefp_brevo_from_email','');
        $from_name  = get_option('wcefp_brevo_from_name','');
        $list_it = intval(get_option('wcefp_brevo_list_it', 0));
        $list_en = intval(get_option('wcefp_brevo_list_en', 0));
        $gift_exp = intval(get_option('wcefp_gift_expiry_days', 365));
        $pixel = get_option('wcefp_meta_pixel_id',''); ?>
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
                    <tr><th colspan="2"><h3><?php _e('Brevo (API v3)','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_brevo_api_key"><?php _e('API Key','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_api_key" id="wcefp_brevo_api_key" value="<?php echo esc_attr($api); ?>" style="width:420px" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_template_id"><?php _e('Template ID conferma (opzionale)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_template_id" id="wcefp_brevo_template_id" value="<?php echo esc_attr($tpl); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_gift_template_id"><?php _e('Template ID regalo (opzionale)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_gift_template_id" id="wcefp_brevo_gift_template_id" value="<?php echo esc_attr($tpl_gift); ?>" /></td>
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
                        <th><label for="wcefp_brevo_list_it"><?php _e('Lista ITA (ID)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_it" id="wcefp_brevo_list_it" value="<?php echo esc_attr($list_it); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_list_en"><?php _e('Lista ENG (ID)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_en" id="wcefp_brevo_list_en" value="<?php echo esc_attr($list_en); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_gift_expiry_days"><?php _e('Scadenza voucher (giorni)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_gift_expiry_days" id="wcefp_gift_expiry_days" value="<?php echo esc_attr($gift_exp); ?>" min="0" /></td>
                    </tr>
                    <tr><th colspan="2"><h3><?php _e('Meta Pixel','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_meta_pixel_id"><?php _e('Pixel ID','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_meta_pixel_id" id="wcefp_meta_pixel_id" value="<?php echo esc_attr($pixel); ?>" /></td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit" name="wcefp_save" value="1"><?php _e('Salva','wceventsfp'); ?></button></p>
            </form>
        </div><?php
    }

    /* ---------- AJAX admin: lista & calendario ---------- */
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

    /* ---------- Blackouts handlers ---------- */
    public function handle_blackout_add() {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('wcefp_blackout_add')) wp_die('Not allowed');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_blackouts';
        $pid = intval($_POST['product_id'] ?? 0);
        $start = sanitize_text_field($_POST['start_date'] ?? '');
        $end   = sanitize_text_field($_POST['end_date'] ?? '');
        $reason= sanitize_text_field($_POST['reason'] ?? '');
        if (!$start || !$end) wp_die('Missing dates');
        $wpdb->insert($tbl, [
            'product_id'=> $pid ?: null,
            'start_date'=> $start,
            'end_date'  => $end,
            'reason'    => $reason
        ], ['%d','%s','%s','%s']);
        wp_safe_redirect(admin_url('admin.php?page=wcefp-blackouts')); exit;
    }
    public function handle_blackout_del() {
        if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');
        $id = intval($_GET['id'] ?? 0);
        if (!$id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_blackout_del_'.$id)) wp_die('Not allowed');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_blackouts';
        $wpdb->delete($tbl, ['id'=>$id], ['%d']);
        wp_safe_redirect(admin_url('admin.php?page=wcefp-blackouts')); exit;
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

    public function export_vouchers_csv(){
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_export')) wp_die('Not allowed');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_gift_vouchers';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY id DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wcefp_vouchers.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['code','product_id','product','qty','recipient_name','recipient_email','expires_at','redeemed','redeemed_at','redeemed_order_id']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['code'], $r['product_id'], get_the_title((int)$r['product_id']), $r['qty'],
                $r['recipient_name'], $r['recipient_email'], $r['expires_at'],
                $r['redeemed'], $r['redeemed_at'], $r['redeemed_order_id']
            ]);
        }
        fclose($out); exit;
    }

    /* ---------- Allocazione / Rilascio posti ---------- */
    public function allocate_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;

        // Se l'ordine contiene redemption di voucher, marca come usati
        foreach ($order->get_items() as $it) {
            $code = (string)$it->get_meta('Voucher');
            $occ  = intval($it->get_meta('Occorrenza'));
            if ($code) {
                global $wpdb; $tbl = $wpdb->prefix.'wcefp_gift_vouchers';
                $wpdb->update($tbl, [
                    'redeemed'=>1,'redeemed_at'=>current_time('mysql'),'redeemed_order_id'=>$order_id,'redeemed_occurrence_id'=>$occ?:null
                ], ['code'=>$code], ['%d','%s','%d','%d'], ['%s']);
            }
        }

        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], $row['qty']);
            if ($ok) $order->add_order_note("Posti allocati per \"{$row['name']}\" ( +{$row['qty']} ).");
            else { $order->add_order_note("ATTENZIONE: capienza insufficiente per \"{$row['name']}\"."); $order->update_status('on-hold'); }
        }
    }
    public function release_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], -$row['qty']);
            if ($ok) $order->add_order_note("Posti rilasciati per \"{$row['name']}\" ( -{$row['qty']} ).");
        }
    }

    /* ---------- Utils pubblici ---------- */
    public static function is_blackout($product_id, $dateYmd) {
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_blackouts';
        // globale o per prodotto
        $hit = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tbl WHERE (%d=0 OR product_id IS NULL OR product_id=%d) AND %s BETWEEN start_date AND end_date LIMIT 1",
            $product_id, $product_id, $dateYmd
        ));
        return !empty($hit);
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

/* ---------- Funzione helper per altri file ---------- */
function wcefp_is_blackout($product_id, $dateYmd){ return WCEFP_Plugin::is_blackout($product_id, $dateYmd); }
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
