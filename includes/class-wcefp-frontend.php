<?php
if (!defined('ABSPATH')) exit;
require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-gift-pdf.php';

class WCEFP_Frontend {

    /* ---------- Shortcode [wcefp_booking product_id="123"] ---------- */
    public static function shortcode_booking($atts) {
        $a = shortcode_atts(['product_id'=>0], $atts);
        $pid = intval($a['product_id']);
        if (!$pid) return '<p>'.__('Seleziona un prodotto valido.','wceventsfp').'</p>';

        $price_adult = floatval(get_post_meta($pid, '_wcefp_price_adult', true));
        $price_child = floatval(get_post_meta($pid, '_wcefp_price_child', true));
        $languages   = sanitize_text_field(get_post_meta($pid, '_wcefp_languages', true));

        $uid = 'wcefp-' . uniqid();

        global $wpdb;
        $tbl = $wpdb->prefix.'wcefp_product_extras';
        $posts = $wpdb->prefix.'posts';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT pe.*, p.post_title, p.post_content FROM $tbl pe LEFT JOIN $posts p ON p.ID=pe.extra_id WHERE pe.product_id=%d ORDER BY pe.sort_order ASC", $pid), ARRAY_A);
        $extras = [];
        foreach ($rows as $r) {
            $extras[] = [
                'id' => intval($r['extra_id']),
                'name' => $r['post_title'],
                'desc' => wp_trim_words($r['post_content'], 20),
                'pricing_type' => $r['pricing_type'],
                'price' => floatval($r['price']),
                'required' => intval($r['required']) ? 1 : 0,
                'max_qty' => intval($r['max_qty']),
                'stock' => intval($r['stock'])
            ];
        }

        // Voucher in sessione: se combacia con il prodotto, prezzo 0
        $voucherActive = false;
        if (function_exists('WC')) {
            $vPid = intval(WC()->session->get('wcefp_voucher_product', 0));
            $voucherActive = ($vPid === $pid);
        }

        ob_start(); ?>
        <div class="wcefp-widget" data-product="<?php echo esc_attr($pid); ?>" data-price-adult="<?php echo esc_attr($price_adult); ?>" data-price-child="<?php echo esc_attr($price_child); ?>" data-voucher="<?php echo $voucherActive?'1':'0'; ?>">
            <?php if ($voucherActive): ?>
                <div class="wcefp-voucher-banner"><?php _e('Voucher attivo: questa prenotazione sarà a costo 0€','wceventsfp'); ?></div>
            <?php endif; ?>
            <?php if ($languages): ?>
            <div class="wcefp-languages">
                <?php foreach (array_filter(array_map('trim', explode(',', strtoupper($languages)))) as $lang): ?>
                    <span class="wcefp-lang-badge"><?php echo esc_html($lang); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="wcefp-row">
                <label for="<?php echo $uid; ?>-date"><?php _e('Data','wceventsfp'); ?></label>
                <input type="date" id="<?php echo $uid; ?>-date" class="wcefp-date" />
            </div>
            <div class="wcefp-row">
                <label for="<?php echo $uid; ?>-slot"><?php _e('Slot','wceventsfp'); ?></label>
                <select id="<?php echo $uid; ?>-slot" class="wcefp-slot"><option value=""><?php _e('Seleziona orario','wceventsfp'); ?></option></select>
            </div>
            <div class="wcefp-row">
                <label for="<?php echo $uid; ?>-adults"><?php _e('Adulti','wceventsfp'); ?></label>
                <input type="number" id="<?php echo $uid; ?>-adults" class="wcefp-adults" min="0" value="1" />
            </div>
            <div class="wcefp-row">
                <label for="<?php echo $uid; ?>-children"><?php _e('Bambini','wceventsfp'); ?></label>
                <input type="number" id="<?php echo $uid; ?>-children" class="wcefp-children" min="0" value="0" />
            </div>
            <?php if (!empty($extras)) : ?>
            <div class="wcefp-row">
                <label><?php _e('Extra','wceventsfp'); ?></label>
                <div class="wcefp-extras">
                    <?php foreach ($extras as $i=>$ex): ?>
                        <?php $toggle = ($ex['max_qty']==1 && !$ex['required']); ?>
                        <?php $desc_id = $uid . '-extra-desc-' . $i; ?>
                        <div class="wcefp-extra-row" data-id="<?php echo esc_attr($ex['id']); ?>" data-name="<?php echo esc_attr($ex['name']); ?>" data-price="<?php echo esc_attr($ex['price']); ?>" data-pricing="<?php echo esc_attr($ex['pricing_type']); ?>">
                            <span class="wcefp-extra-label"><?php echo esc_html($ex['name']); ?> (+€<?php echo esc_html($ex['price']); ?>)</span>
                            <?php if($toggle): ?>
                                <input type="checkbox" class="wcefp-extra-toggle" aria-describedby="<?php echo $desc_id; ?>" />
                            <?php else: ?>
                                <input type="number" class="wcefp-extra-qty" min="<?php echo $ex['required']?1:0; ?>" value="<?php echo $ex['required']?1:0; ?>" <?php if($ex['required']) echo 'readonly'; ?> <?php if($ex['max_qty']>0) echo 'max="'.esc_attr($ex['max_qty']).'"'; ?> aria-describedby="<?php echo $desc_id; ?>" />
                            <?php endif; ?>
                            <?php if($ex['desc']) echo '<small class="wcefp-extra-desc" id="'.$desc_id.'">'.esc_html($ex['desc']).'</small>'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="wcefp-row wcefp-total-row">
                <strong><?php _e('Totale stimato','wceventsfp'); ?>:</strong>
                <span class="wcefp-total">€ 0,00</span>
            </div>

            <?php $fb_id = $uid . '-feedback'; ?>
            <div class="wcefp-row">
                <button class="wcefp-add button" aria-describedby="<?php echo esc_attr($fb_id); ?>"><?php _e('Aggiungi al carrello','wceventsfp'); ?></button>
                <span class="wcefp-feedback" id="<?php echo esc_attr($fb_id); ?>" role="status" style="margin-left:8px;"></span>
            </div>
        </div>
        <script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'view_item',ecommerce:{items:[{item_id:'<?php echo esc_js($pid); ?>',item_name:'<?php echo esc_js(get_the_title($pid)); ?>',item_category:'event'}]}});</script>
        <?php
        return ob_get_clean();
    }

    /* ---------- Render automatico su pagina prodotto ---------- */
    public static function render_booking_widget_auto() {
        global $product;
        if (!$product || !in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) return;
        echo self::shortcode_booking(['product_id' => $product->get_id()]);
        self::render_gift_form();
    }

    public static function render_gift_form() {
        global $product;
        if (!$product || !in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) return;
        ?>
        <div id="wcefp-gift">
            <label><input type="checkbox" id="wcefp-gift-toggle" name="wcefp_gift_toggle" value="1"> <?php _e('Regala un’esperienza','wceventsfp'); ?></label>
            <div id="wcefp-gift-fields" style="display:none">
                <p><label><?php _e('Nome destinatario','wceventsfp'); ?></label><input type="text" name="gift_recipient_name" maxlength="120"></p>
                <p><label><?php _e('Email destinatario (facoltativa)','wceventsfp'); ?></label><input type="email" name="gift_recipient_email"></p>
                <p><label><?php _e('Messaggio (facoltativo)','wceventsfp'); ?></label><textarea name="gift_message" rows="3" maxlength="300" placeholder="<?php esc_attr_e('Un pensiero per chi riceve il regalo…','wceeventsfp'); ?>"></textarea></p>
            </div>
        </div>
        <?php
    }

    public static function render_product_details() {
        global $product;
        if (!$product || !in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) return;
        $pid = $product->get_id();

        $duration = intval(get_post_meta($pid, '_wcefp_duration_minutes', true));
        $languages = sanitize_text_field(get_post_meta($pid, '_wcefp_languages', true));
        $meeting = sanitize_text_field(get_post_meta($pid, '_wcefp_meeting_point', true));
        $includes = wp_kses_post(get_post_meta($pid, '_wcefp_includes', true));
        $excludes = wp_kses_post(get_post_meta($pid, '_wcefp_excludes', true));
        $cxl      = wp_kses_post(get_post_meta($pid, '_wcefp_cancellation', true));

        // Next upcoming occurrence (attivo e con posti)
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $now = current_time('mysql');
        $next = $wpdb->get_row($wpdb->prepare("SELECT start_datetime,end_datetime FROM $tbl WHERE product_id=%d AND status='active' AND (capacity - booked) > 0 AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT 1", $pid, $now));
        $next_start = $next ? $next->start_datetime : '';
        $next_end   = $next ? $next->end_datetime   : '';

        // JSON-LD Event
        if ($next_start) {
            $price_adult = floatval(get_post_meta($pid, '_wcefp_price_adult', true));
            $currency = get_woocommerce_currency();
            $json = [
                "@context" => "https://schema.org",
                "@type" => "Event",
                "name"  => get_the_title($pid),
                "startDate" => date('c', strtotime($next_start)),
                "endDate"   => date('c', strtotime($next_end ?: $next_start)),
                "eventStatus" => "https://schema.org/EventScheduled",
                "eventAttendanceMode" => "https://schema.org/OfflineEventAttendanceMode",
                "location" => [
                    "@type" => "Place",
                    "name"  => get_bloginfo('name')
                ],
                "offers" => [
                    "@type" => "Offer",
                    "price" => $price_adult,
                    "priceCurrency" => $currency,
                    "availability" => "https://schema.org/InStock",
                    "url" => get_permalink($pid)
                ]
            ];
            echo '<script type="application/ld+json">'.wp_json_encode($json).'</script>';
        }

        // Prossime 5 date
        $next_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, start_datetime, capacity, booked FROM $tbl WHERE product_id=%d AND status='active' AND (capacity - booked) > 0 AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT 5",
            $pid, $now
        ), ARRAY_A);

        echo '<div class="wcefp-details">';
        echo '<h3>'.esc_html__('Dettagli esperienza','wceventsfp').'</h3>';
        echo '<ul class="wcefp-details-list">';
        if ($duration)  echo '<li><strong>'.esc_html__('Durata','wceventsfp').':</strong> '.intval($duration).' '.esc_html__('minuti','wceventsfp').'</li>';
        if ($languages) {
            $lang_html = '';
            foreach (array_filter(array_map('trim', explode(',', strtoupper($languages)))) as $lang) {
                $lang_html .= '<span class="wcefp-lang-badge">'.esc_html($lang).'</span> ';
            }
            echo '<li><strong>'.esc_html__('Lingue','wceventsfp').':</strong> '.$lang_html.'</li>';
        }
        if ($meeting) {
            $map_id = 'wcefp-map-'.uniqid();
            $link = 'https://www.google.com/maps?daddr='.urlencode($meeting);
            echo '<li><strong>'.esc_html__('Meeting point','wceventsfp').':</strong> '.esc_html($meeting).' <a href="'.esc_url($link).'" target="_blank">'.esc_html__('Ottieni indicazioni','wceventsfp').'</a></li>';
            $points_opt = get_option('wcefp_meetingpoints', []);
            $lat = $lng = '';
            if (is_array($points_opt)) {
                foreach ($points_opt as $pt) {
                    if (is_array($pt) && isset($pt['address']) && $pt['address'] === $meeting) {
                        $lat = $pt['lat'] ?? '';
                        $lng = $pt['lng'] ?? '';
                        break;
                    }
                }
            }
            if ($lat && $lng) {
                echo WCEFP_Templates::render_map($map_id, $lat, $lng);
            }
        }
        if ($next_start) echo '<li><strong>'.esc_html__('Prossima data','wceventsfp').':</strong> '.esc_html(date_i18n('d/m/Y H:i', strtotime($next_start))).'</li>';
        echo '</ul>';

        if (!empty($next_rows)) {
            echo '<h4>'.esc_html__('Prossime disponibilità','wceventsfp').'</h4><ul>';
            foreach ($next_rows as $r) {
                $avail = max(0, intval($r['capacity']) - intval($r['booked']));
                echo '<li>'.esc_html(date_i18n('d/m/Y H:i', strtotime($r['start_datetime']))).' — '.sprintf(_n('%d posto', '%d posti', $avail, 'wceventsfp'), $avail).'</li>';
            }
            echo '</ul>';
        }

        if ($includes) {
            echo '<h4>'.esc_html__('Cosa è incluso','wceventsfp').'</h4>';
            echo '<div class="wcefp-rich">'.wpautop($includes).'</div>';
        }
        if ($excludes) {
            echo '<h4>'.esc_html__('Cosa non è incluso','wceventsfp').'</h4>';
            echo '<div class="wcefp-rich">'.wpautop($excludes).'</div>';
        }
        if ($cxl) {
            echo '<h4>'.esc_html__('Cancellazione','wceventsfp').'</h4>';
            echo '<div class="wcefp-rich">'.wpautop($cxl).'</div>';
        }

        echo '</div>';
    }

    /* ---------- AJAX: occorrenze pubbliche per data ---------- */
    public static function ajax_public_occurrences() {
        check_ajax_referer('wcefp_public','nonce');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $pid  = intval($_POST['product_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        if (!$pid || !$date) wp_send_json_error(['msg'=>'Parametri mancanti']);

        $dateDt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateDt) wp_send_json_error(['msg'=>'Formato data non valido']);

        // Blocca se giorno chiuso (globale o specifico)
        if (class_exists('WCEFP_Closures') && WCEFP_Closures::is_date_closed($pid, $date)) {
            wp_send_json_success(['slots'=>[]]);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,start_datetime,capacity,booked,status FROM $tbl WHERE product_id=%d AND DATE(start_datetime)=%s ORDER BY start_datetime ASC",
            $pid, $date
        ), ARRAY_A);

        $slots = [];
        foreach ($rows as $r) {
            $time = (new DateTime($r['start_datetime']))->format('H:i');
            $avail = max(0, (int)$r['capacity'] - (int)$r['booked']);
            $slots[] = [
                'id' => (int)$r['id'],
                'time' => $time,
                'capacity' => (int)$r['capacity'],
                'booked' => (int)$r['booked'],
                'available' => $avail,
                'status' => $r['status'],
                'soldout' => ($r['status']!=='active' || $avail<=0)
            ];
        }
        wp_send_json_success(['slots'=>$slots]);
    }

    /* ---------- AJAX: add to cart con check capienza + voucher ---------- */
    public static function ajax_add_to_cart() {
        try {
            check_ajax_referer('wcefp_public','nonce');
            
            $validation_rules = [
                'product_id' => ['method' => 'validate_product_id', 'required' => true],
                'occurrence_id' => ['method' => 'validate_occurrence_id', 'required' => true],
                'adults' => ['method' => 'validate_quantity', 'required' => false],
                'children' => ['method' => 'validate_quantity', 'required' => false],
            ];

            $validated = WCEFP_Validator::validate_bulk($_POST, $validation_rules);
            if ($validated === false) {
                wp_send_json_error(['msg'=>__('Dati di prenotazione non validi','wceventsfp')]);
            }

            $pid = $validated['product_id'];
            $occ = $validated['occurrence_id'];
            $ad = $validated['adults'] ?? 0;
            $ch = $validated['children'] ?? 0;
            
            $extras_in = isset($_POST['extras']) && is_array($_POST['extras']) ? array_values($_POST['extras']) : [];
            
            // Gift validation
            $gift_toggle = intval($_POST['wcefp_gift_toggle'] ?? 0) === 1;
            $gift_name = '';
            $gift_email = '';
            $gift_msg = '';
            
            if ($gift_toggle) {
                $gift_name = WCEFP_Validator::validate_text($_POST['gift_recipient_name'] ?? '', 100);
                if ($gift_name === false || empty($gift_name)) {
                    wp_send_json_error(['msg' => __('Nome destinatario obbligatorio','wceventsfp')]);
                }
                
                $gift_email = WCEFP_Validator::validate_email($_POST['gift_recipient_email'] ?? '');
                if ($gift_email === false) {
                    wp_send_json_error(['msg' => __('Email destinatario non valida','wceventsfp')]);
                }
                
                $gift_msg = WCEFP_Validator::validate_textarea($_POST['gift_message'] ?? '', 300);
                if ($gift_msg === false) {
                    wp_send_json_error(['msg' => __('Messaggio troppo lungo','wceventsfp')]);
                }
            }

            if (($ad + $ch) <= 0) {
                WCEFP_Logger::warning('Invalid booking attempt with zero quantity', [
                    'product_id' => $pid,
                    'occurrence_id' => $occ,
                    'adults' => $ad,
                    'children' => $ch
                ]);
                wp_send_json_error(['msg' => __('Seleziona almeno una persona','wceventsfp')]);
            }

            $qty = $ad + $ch;

            // Check capacity and status with atomic operation
            global $wpdb;
            $tbl = $wpdb->prefix . 'wcefp_occurrences';
            
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT capacity, booked, status FROM $tbl WHERE id=%d AND product_id=%d", 
                $occ, $pid
            ), ARRAY_A);
            
            if (!$row) {
                WCEFP_Logger::error('Occurrence not found for booking', [
                    'occurrence_id' => $occ,
                    'product_id' => $pid
                ]);
                wp_send_json_error(['msg'=>__('Slot non trovato','wceventsfp')]);
            }
            
            if (($row['status'] ?? '') !== 'active') {
                WCEFP_Logger::info('Booking attempt on inactive slot', [
                    'occurrence_id' => $occ,
                    'status' => $row['status']
                ]);
                wp_send_json_error(['msg'=>__('Lo slot non è più disponibile','wceventsfp')]);
            }
            
            $available = max(0, intval($row['capacity']) - intval($row['booked']));
            if ($available < $qty) {
                WCEFP_Logger::info('Insufficient capacity for booking', [
                    'occurrence_id' => $occ,
                    'available' => $available,
                    'requested' => $qty
                ]);
                wp_send_json_error(['msg'=> sprintf(__('Posti disponibili insufficienti. Rimasti: %d','wceventsfp'), $available)]);
            }

            // Voucher in sessione?
            $useVoucher = false; $voucherCode = '';
            if (function_exists('WC')) {
                $vPid = intval(WC()->session->get('wcefp_voucher_product', 0));
                if ($vPid === $pid) {
                    $useVoucher = true;
                    $voucherCode = WC()->session->get('wcefp_voucher_code', '');
                }
            }

            // Sanitizza e valida extra
        $tbl_ex = $wpdb->prefix.'wcefp_product_extras';
        $extras = [];
        foreach ($extras_in as $ex) {
            $ex_id = intval($ex['id'] ?? 0);
            $qty = intval($ex['qty'] ?? 0);
            if (!$ex_id || $qty <= 0) continue;
            $row = $wpdb->get_row($wpdb->prepare("SELECT pricing_type, price, required, max_qty, stock FROM $tbl_ex WHERE product_id=%d AND extra_id=%d", $pid, $ex_id), ARRAY_A);
            if (!$row) continue;
            $pricing = $row['pricing_type'];
            $price   = floatval($row['price']);
            $required = intval($row['required']) ? 1 : 0;
            $max_qty  = intval($row['max_qty']);
            $stock    = intval($row['stock']);
            if ($required && $qty < 1) $qty = 1;
            if ($max_qty > 0 && $qty > $max_qty) $qty = $max_qty;
            $mult = $qty;
            if ($pricing === 'per_person') $mult *= ($ad + $ch);
            elseif ($pricing === 'per_child') $mult *= $ch;
            elseif ($pricing === 'per_adult') $mult *= $ad;
            if ($stock > 0 && $stock < $mult) wp_send_json_error(['msg'=>sprintf(__('Extra "%s" esaurito','wceventsfp'), get_the_title($ex_id))]);
            $extras[] = [
                'id' => $ex_id,
                'name' => get_the_title($ex_id),
                'price' => $price,
                'qty' => $qty,
                'pricing' => $pricing
            ];
        }

        // Verifica extra obbligatori
        $required_ids = $wpdb->get_col($wpdb->prepare("SELECT extra_id FROM $tbl_ex WHERE product_id=%d AND required=1", $pid));
        foreach ($required_ids as $rid) {
            $found = false;
            foreach ($extras as $e) if ($e['id'] == $rid) { $found=true; break; }
            if (!$found) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT pricing_type, price, stock FROM $tbl_ex WHERE product_id=%d AND extra_id=%d", $pid, $rid), ARRAY_A);
                if ($row) {
                    $pricing = $row['pricing_type'];
                    $price = floatval($row['price']);
                    $stock = intval($row['stock']);
                    $mult = 1;
                    if ($pricing === 'per_person') $mult *= ($ad + $ch);
                    elseif ($pricing === 'per_child') $mult *= $ch;
                    elseif ($pricing === 'per_adult') $mult *= $ad;
                    if ($stock > 0 && $stock < $mult) wp_send_json_error(['msg'=>sprintf(__('Extra "%s" esaurito','wceventsfp'), get_the_title($rid))]);
                    $extras[] = [
                        'id'=>$rid,
                        'name'=>get_the_title($rid),
                        'price'=>$price,
                        'qty'=>1,
                        'pricing'=>$pricing
                    ];
                }
            }
        }

        $meta = [
            '_wcefp_occurrence_id' => $occ,
            '_wcefp_adults'  => $ad,
            '_wcefp_children'=> $ch,
            '_wcefp_extras'  => $extras,
        ];
        if ($useVoucher && $voucherCode) $meta['_wcefp_voucher_code'] = $voucherCode;
        if ($gift_name) {
            $meta['_wcefp_gift'] = [
                'name'  => $gift_name,
                'email' => $gift_email,
                'msg'   => $gift_msg,
            ];
        }

        $added = WC()->cart->add_to_cart($pid, $qty, 0, [], $meta);
        if (!$added) wp_send_json_error(['msg'=>'Impossibile aggiungere al carrello']);

        // GA4 add_to_cart
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
        
        } catch (Exception $e) {
            WCEFP_Logger::error('Booking error in ajax_add_to_cart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            wp_send_json_error(['msg' => __('Errore durante la prenotazione. Riprova.', 'wceventsfp')]);
        }
    }

    /* ---------- Prezzo dinamico + voucher ---------- */
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

            // Occorrenza per regole prezzo
            $occ_id = intval($ci['_wcefp_occurrence_id'] ?? 0);
            $occ_start = '';
            if ($occ_id > 0) {
                global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
                $occ_start = $wpdb->get_var($wpdb->prepare("SELECT start_datetime FROM $tbl WHERE id=%d", $occ_id));
            }

            // Voucher: se presente e combacia, prezzo 0
            $voucher_ok = false;
            if (!empty($ci['_wcefp_voucher_code']) && function_exists('WC')) {
                $vPid = intval(WC()->session->get('wcefp_voucher_product', 0));
                $voucher_ok = ($vPid === $p->get_id());
            }

            if ($voucher_ok) {
                $ci['data']->set_price(0);
                continue;
            }

            $price_adult = floatval(get_post_meta($p->get_id(), '_wcefp_price_adult', true));
            $price_child = floatval(get_post_meta($p->get_id(), '_wcefp_price_child', true));
            $extras_total = 0.0;
            foreach ($extras as $ex) {
                $price = floatval($ex['price'] ?? 0);
                $qty   = intval($ex['qty'] ?? 0);
                $pricing = $ex['pricing'] ?? 'per_order';
                $mult = $qty;
                if ($pricing === 'per_person') $mult *= ($ad + $ch);
                elseif ($pricing === 'per_child') $mult *= $ch;
                elseif ($pricing === 'per_adult') $mult *= $ad;
                $extras_total += $price * $mult;
            }
            $total = ($ad * $price_adult) + ($ch * $price_child) + $extras_total;

            // Regole prezzo
            $rules = json_decode(get_option('wcefp_price_rules','[]'), true);
            if ($occ_start && is_array($rules)) {
                $ts = strtotime($occ_start);
                $w = (int)date('w', $ts);
                foreach ($rules as $r) {
                    $df = $r['date_from'] ?? '';
                    $dt = $r['date_to'] ?? '';
                    $weekdays = $r['weekdays'] ?? [];
                    $inRange = (!$df || $occ_start >= $df.' 00:00:00') && (!$dt || $occ_start <= $dt.' 23:59:59');
                    $matchW  = empty($weekdays) || in_array($w, $weekdays, true);
                    if ($inRange && $matchW) {
                        $type  = $r['type'] ?? 'percent';
                        $value = floatval($r['value'] ?? 0);
                        if ($type === 'percent') $total += $total * ($value / 100);
                        else $total += $value;
                    }
                }
            }

            $qty = max(1, $ad + $ch);
            $unit = $qty > 0 ? ($total / $qty) : $total;

            if ($unit >= 0) $ci['data']->set_price($unit);
        }
    }

    public static function add_line_item_meta($item, $cart_item_key, $values, $order) {
        $map = ['_wcefp_occurrence_id'=>'Occorrenza','_wcefp_adults'=>'Adulti','_wcefp_children'=>'Bambini'];
        foreach ($map as $k=>$label) if (isset($values[$k])) $item->add_meta_data($label, $values[$k], true);

        if (!empty($values['_wcefp_extras']) && is_array($values['_wcefp_extras'])) {
            $names = array_map(function($e){
                $n = $e['name'] ?? '';
                $q = intval($e['qty'] ?? 0);
                return $q>1 ? $n.' x'.$q : $n;
            }, $values['_wcefp_extras']);
            $item->add_meta_data('Extra', implode(', ', array_filter($names)), true);
            $item->add_meta_data('_wcefp_extras_data', wp_json_encode($values['_wcefp_extras']), true);
        }
        if (!empty($values['_wcefp_voucher_code'])) {
            $item->add_meta_data('Voucher', $values['_wcefp_voucher_code'], true);
            // salva anche a livello ordine per mark used
            $order->update_meta_data('wcefp_voucher_code', $values['_wcefp_voucher_code']);
        }
        if (!empty($values['_wcefp_gift']) && is_array($values['_wcefp_gift'])) {
            $gift = $values['_wcefp_gift'];
            $item->add_meta_data('Gift - Nome', $gift['name'] ?? '', true);
            if (!empty($gift['email'])) $item->add_meta_data('Gift - Email', $gift['email'], true);
            if (!empty($gift['msg'])) $item->add_meta_data('Gift - Messaggio', $gift['msg'], true);
            $item->add_meta_data('_wcefp_gift_data', wp_json_encode($gift), true);
        }
    }

    public static function display_gift_item($data, $cart_item) {
        if (!empty($cart_item['_wcefp_gift'])) {
            $g = $cart_item['_wcefp_gift'];
            $parts = [];
            if (!empty($g['name'])) $parts[] = $g['name'];
            if (!empty($g['email'])) $parts[] = $g['email'];
            if (!empty($g['msg'])) $parts[] = $g['msg'];
            if (!empty($parts)) {
                $data[] = [
                    'name' => __('Gift', 'wceventsfp'),
                    'value' => implode(' / ', $parts)
                ];
            }
        }
        return $data;
    }

    public static function thankyou_gift_links($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;
        foreach ($order->get_items() as $item_id => $item) {
            $vid = intval($item->get_meta('_wcefp_voucher_id', true));
            if ($vid > 0) {
                $url = add_query_arg([
                    'wcefp_gift_pdf' => 1,
                    'order' => $order_id,
                    'item' => $item_id,
                    'voucher' => $vid,
                    'key' => $order->get_order_key(),
                ], home_url('/'));
                echo '<p><a class="button wcefp-gift-download" href="'.esc_url($url).'">'.esc_html__('Scarica PDF','wceventsfp').'</a></p>';
            }
        }
    }

    public static function capture_gift_cart($data, $product_id, $variation_id) {
        $toggle = intval($_POST['wcefp_gift_toggle'] ?? 0) === 1;
        $name = sanitize_text_field($_POST['gift_recipient_name'] ?? '');
        $email = sanitize_email($_POST['gift_recipient_email'] ?? '');
        $msg = isset($_POST['gift_message']) ? wp_kses($_POST['gift_message'], ['br'=>[]]) : '';
        if (strlen($msg) > 300) $msg = substr($msg, 0, 300);
        if ($toggle && $name === '') {
            wc_add_notice(__('Nome destinatario obbligatorio','wceventsfp'), 'error');
        }
        if ($name) {
            $data['_wcefp_gift'] = ['name'=>$name,'email'=>$email,'msg'=>$msg];
        }
        return $data;
    }
}
WCEFP_Gift_PDF::init();
add_filter('woocommerce_add_cart_item_data', ['WCEFP_Frontend','capture_gift_cart'], 10, 3);
add_filter('woocommerce_get_item_data', ['WCEFP_Frontend','display_gift_item'], 10, 2);
add_action('woocommerce_thankyou', ['WCEFP_Frontend','thankyou_gift_links'], 40);
