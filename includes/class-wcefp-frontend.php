<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Frontend {

    /* ---------- Shortcode [wcefp_booking product_id="123"] ---------- */
    public static function shortcode_booking($atts) {
        $a = shortcode_atts(['product_id'=>0], $atts);
        $pid = intval($a['product_id']);
        if (!$pid) return '<p>'.__('Seleziona un prodotto valido.','wceventsfp').'</p>';

        $extras = [];
        $raw = get_post_meta($pid, '_wcefp_extras_json', true);
        if ($raw) {
            $arr = json_decode($raw, true);
            if (is_array($arr)) $extras = $arr;
        }

        ob_start(); ?>
        <div class="wcefp-widget" data-product="<?php echo esc_attr($pid); ?>">
            <div class="wcefp-row">
                <label><?php _e('Data','wceventsfp'); ?></label>
                <input type="date" class="wcefp-date" />
            </div>
            <div class="wcefp-row">
                <label><?php _e('Slot','wceventsfp'); ?></label>
                <select class="wcefp-slot"><option value=""><?php _e('Seleziona orario','wceventsfp'); ?></option></select>
            </div>
            <div class="wcefp-row">
                <label><?php _e('Adulti','wceventsfp'); ?></label>
                <input type="number" class="wcefp-adults" min="0" value="1" />
            </div>
            <div class="wcefp-row">
                <label><?php _e('Bambini','wceventsfp'); ?></label>
                <input type="number" class="wcefp-children" min="0" value="0" />
            </div>
            <?php if (!empty($extras)) : ?>
            <div class="wcefp-row">
                <label><?php _e('Extra','wceventsfp'); ?></label>
                <div class="wcefp-extras">
                    <?php foreach ($extras as $i=>$ex): ?>
                        <label style="margin-right:12px;">
                            <input type="checkbox" class="wcefp-extra" data-name="<?php echo esc_attr($ex['name']); ?>" data-price="<?php echo esc_attr($ex['price']); ?>" />
                            <?php echo esc_html($ex['name']); ?> (+€<?php echo esc_html($ex['price']); ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="wcefp-row">
                <button class="wcefp-add button"><?php _e('Aggiungi al carrello','wceventsfp'); ?></button>
                <span class="wcefp-feedback" style="margin-left:8px;"></span>
            </div>
        </div>
        <script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'view_item',ecommerce:{items:[{item_id:'<?php echo esc_js($pid); ?>',item_name:'<?php echo esc_js(get_the_title($pid)); ?>',item_category:'event'}]}});</script>
        <?php
        return ob_get_clean();
    }

    /* ---------- AJAX: occorrenze pubbliche per data ---------- */
    public static function ajax_public_occurrences() {
        check_ajax_referer('wcefp_public','nonce');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $pid  = intval($_POST['product_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        if (!$pid || !$date) wp_send_json_success(['slots'=>[]]);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,start_datetime,capacity,booked FROM $tbl WHERE product_id=%d AND DATE(start_datetime)=%s ORDER BY start_datetime ASC",
            $pid, $date
        ), ARRAY_A);

        $slots = [];
        foreach ($rows as $r) {
            $time = (new DateTime($r['start_datetime']))->format('H:i');
            $slots[] = [
                'id' => (int)$r['id'],
                'time' => $time,
                'capacity' => (int)$r['capacity'],
                'booked' => (int)$r['booked'],
                'available' => max(0, (int)$r['capacity'] - (int)$r['booked']),
            ];
        }
        wp_send_json_success(['slots'=>$slots]);
    }

    /* ---------- AJAX: add to cart con check capienza ---------- */
    public static function ajax_add_to_cart() {
        check_ajax_referer('wcefp_public','nonce');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';

        $pid  = intval($_POST['product_id'] ?? 0);
        $occ  = intval($_POST['occurrence_id'] ?? 0);
        $ad   = max(0, intval($_POST['adults'] ?? 0));
        $ch   = max(0, intval($_POST['children'] ?? 0));
        $extras = isset($_POST['extras']) && is_array($_POST['extras']) ? array_values($_POST['extras']) : [];

        if (!$pid || !$occ || ($ad+$ch)<=0) wp_send_json_error(['msg'=>'Dati mancanti']);

        $qty = max(1, $ad + $ch);

        // Check capienza attuale
        $row = $wpdb->get_row($wpdb->prepare("SELECT capacity, booked FROM $tbl WHERE id=%d AND product_id=%d", $occ, $pid), ARRAY_A);
        if (!$row) wp_send_json_error(['msg'=>'Slot non trovato.']);
        $available = max(0, intval($row['capacity']) - intval($row['booked']));
        if ($available < $qty) {
            wp_send_json_error(['msg'=> sprintf(__('Posti disponibili insufficienti. Rimasti: %d','wceventsfp'), $available)]);
        }

        $meta = [
            '_wcefp_occurrence_id' => $occ,
            '_wcefp_adults'  => $ad,
            '_wcefp_children'=> $ch,
            '_wcefp_extras'  => $extras,
        ];

        $added = WC()->cart->add_to_cart($pid, $qty, 0, [], $meta);
        if (!$added) wp_send_json_error(['msg'=>'Impossibile aggiungere al carrello']);

        // GA4 add_to_cart (session bridge opzionale)
        $product = wc_get_product($pid);
        $dl = [
            'event'=>'add_to_cart',
            'ecommerce'=>[
                'items'=>[[
                    'item_id'=>(string)$pid,
                    'item_name'=>$product ? $product->get_name() : 'Item',
                    'item_category'=>'event',
                    'quantity'=>$qty
                ]]
            ]
        ];
        WC()->session->set('wcefp_dl_add_to_cart', $dl);

        wp_send_json_success(['cart_url'=>wc_get_cart_url()]);
    }

    /* ---------- Prezzo dinamico ---------- */
    public static function apply_dynamic_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $cart_item_key => $ci) {
            if (empty($ci['data']) || !($ci['data'] instanceof WC_Product)) continue;
            $p = $ci['data'];
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;

            $ad = intval($ci['_wcefp_adults'] ?? 0);
            $ch = intval($ci['_wcefp_children'] ?? 0);
            $extras = isset($ci['_wcefp_extras']) && is_array($ci['_wcefp_extras']) ? $ci['_wcefp_extras'] : [];

            $price_adult = floatval(get_post_meta($p->get_id(), '_wcefp_price_adult', true));
            $price_child = floatval(get_post_meta($p->get_id(), '_wcefp_price_child', true));
            $extras_total = 0.0;
            foreach ($extras as $ex) {
                if (isset($ex['price'])) $extras_total += floatval($ex['price']);
            }
            $total = ($ad * $price_adult) + ($ch * $price_child) + $extras_total;
            $qty = max(1, $ad + $ch);
            $unit = $qty > 0 ? ($total / $qty) : $total;

            if ($unit > 0) $ci['data']->set_price($unit);
        }
    }

    public static function add_line_item_meta($item, $cart_item_key, $values, $order) {
        $map = ['_wcefp_occurrence_id'=>'Occorrenza','_wcefp_adults'=>'Adulti','_wcefp_children'=>'Bambini'];
        foreach ($map as $k=>$label) if (isset($values[$k])) $item->add_meta_data($label, $values[$k], true);

        if (!empty($values['_wcefp_extras']) && is_array($values['_wcefp_extras'])) {
            $names = array_map(function($e){ return $e['name'] ?? ''; }, $values['_wcefp_extras']);
            $item->add_meta_data('Extra', implode(', ', array_filter($names)), true);
        }

        // NIENTE aggiornamento booked qui (si fa su cambio stato ordine con update atomico)
    }
}
