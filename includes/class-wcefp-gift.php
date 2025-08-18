<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestione "Regala un'esperienza"
 * - Checkbox in checkout + campi destinatario
 * - Creazione voucher a ordine completato (1 per riga evento)
 * - Invio link voucher via Brevo (usa API key del plugin)
 * - Redeem: shortcode [wcefp_redeem] per riscatto codice
 * - Se voucher valido in sessione, il widget imposta prezzo 0 e crea riga ordine a 0€
 */
class WCEFP_Gift {

    public static function init(){
        // Campi checkout
        add_filter('woocommerce_checkout_fields', [__CLASS__, 'checkout_fields']);
        add_action('woocommerce_after_order_notes', [__CLASS__, 'checkout_fields_render']);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_checkout_fields']);

        // Crea voucher all'ordine completato
        add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_generate_vouchers']);

        // Shortcode redeem
        add_shortcode('wcefp_redeem', [__CLASS__, 'redeem_shortcode']);

        // Endpoint pubblico: visualizza voucher stampabile
        add_action('init', [__CLASS__, 'serve_voucher_view']);
    }

    /* ---------------- Checkout ---------------- */
    public static function checkout_fields($fields){
        $fields['billing']['wcefp_gift_enable'] = [
            'type'        => 'checkbox',
            'label'       => __('Regala un’esperienza', 'wceventsfp'),
            'required'    => false,
            'priority'    => 50
        ];
        $fields['billing']['wcefp_gift_recipient_name'] = [
            'type'        => 'text',
            'label'       => __('Nome destinatario', 'wceventsfp'),
            'required'    => false,
            'priority'    => 51
        ];
        $fields['billing']['wcefp_gift_recipient_email'] = [
            'type'        => 'email',
            'label'       => __('Email destinatario', 'wceventsfp'),
            'required'    => false,
            'priority'    => 52
        ];
        $fields['order']['wcefp_gift_message'] = [
            'type'        => 'textarea',
            'label'       => __('Messaggio al destinatario (facoltativo)', 'wceventsfp'),
            'required'    => false,
            'priority'    => 53
        ];
        return $fields;
    }

    public static function checkout_fields_render($checkout){
        // UI compatta
        echo '<div class="wcefp-gift-wrap"><h3>'.esc_html__('Regala un’esperienza','wceventsfp').'</h3>';
        woocommerce_form_field('wcefp_gift_enable', $checkout->checkout_fields['billing']['wcefp_gift_enable'], $checkout->get_value('wcefp_gift_enable'));
        woocommerce_form_field('wcefp_gift_recipient_name', $checkout->checkout_fields['billing']['wcefp_gift_recipient_name'], $checkout->get_value('wcefp_gift_recipient_name'));
        woocommerce_form_field('wcefp_gift_recipient_email', $checkout->checkout_fields['billing']['wcefp_gift_recipient_email'], $checkout->get_value('wcefp_gift_recipient_email'));
        woocommerce_form_field('wcefp_gift_message', $checkout->checkout_fields['order']['wcefp_gift_message'], $checkout->get_value('wcefp_gift_message'));
        echo '<p class="description">'.esc_html__('Se attivo, verrà generato un codice voucher da usare per prenotare in un secondo momento.','wceventsfp').'</p>';
        echo '</div>';
    }

    public static function save_checkout_fields($order_id){
        $keys = ['wcefp_gift_enable','wcefp_gift_recipient_name','wcefp_gift_recipient_email','wcefp_gift_message'];
        foreach ($keys as $k){
            if (isset($_POST[$k])) {
                $val = is_string($_POST[$k]) ? wp_unslash($_POST[$k]) : $_POST[$k];
                switch ($k) {
                    case 'wcefp_gift_recipient_email':
                        $val = sanitize_email($val);
                        break;
                    case 'wcefp_gift_message':
                        $val = sanitize_textarea_field($val);
                        break;
                    default:
                        $val = sanitize_text_field($val);
                }
                update_post_meta($order_id, $k, $val);
            }
        }
    }

    /* ---------------- Voucher creation ---------------- */
    public static function maybe_generate_vouchers($order_id){
        $order = wc_get_order($order_id); if(!$order) return;

        $enabled = get_post_meta($order_id, 'wcefp_gift_enable', true);
        if (!$enabled) return;

        $rec_name  = sanitize_text_field(get_post_meta($order_id, 'wcefp_gift_recipient_name', true));
        $rec_email = sanitize_email(get_post_meta($order_id, 'wcefp_gift_recipient_email', true));
        $message   = sanitize_textarea_field(get_post_meta($order_id, 'wcefp_gift_message', true));

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_vouchers';
        $count = 0;
        $voucher_links = [];

        foreach ($order->get_items() as $it) {
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;

            $qty = max(1, (int)$it->get_quantity());
            for ($i=0; $i<$qty; $i++){
                $code = self::generate_code();
                $ins = $wpdb->insert($tbl, [
                    'code' => $code,
                    'product_id' => $p->get_id(),
                    'order_id' => $order_id,
                    'recipient_name' => $rec_name,
                    'recipient_email' => $rec_email,
                    'message_text' => $message,
                    'status' => 'unused',
                ], ['%s','%d','%d','%s','%s','%s','%s']);
                if ($ins) {
                    $count++;
                    $voucher_links[] = self::voucher_url($code);
                }
            }
        }

        if ($count > 0) {
            $order->add_order_note(sprintf(__('Voucher generati: %d','wceventsfp'), $count));
            // invio email via Brevo
            self::brevo_send_voucher_mail($order, $rec_name, $rec_email, $voucher_links);
        }
    }

    private static function generate_code(){
        return strtoupper( wp_generate_password(12, false, false) );
    }

    private static function voucher_url($code){
        return add_query_arg(['wcefp_voucher_view'=>1,'code'=>$code], home_url('/'));
    }

    private static function brevo_send_voucher_mail($order, $rec_name, $rec_email, array $links){
        $api_key = trim(get_option('wcefp_brevo_api_key','')); if(!$api_key || !$rec_email) return;
        $from_email = sanitize_email(get_option('wcefp_brevo_from_email', get_bloginfo('admin_email')));
        $from_name  = sanitize_text_field(get_option('wcefp_brevo_from_name', get_bloginfo('name')));
        $tpl_id = 0; // puoi prevedere un template dedicato in futuro

        $items = '';
        foreach ($links as $url) $items .= '<li><a href="'.esc_url($url).'">'.esc_html($url).'</a></li>';

        $payload = [
            'to' => [['email'=>$rec_email, 'name'=>$rec_name ?: $rec_email]],
            'subject' => __('Hai ricevuto un’esperienza in regalo','wceventsfp'),
            'htmlContent' => '<h2>'.esc_html__('Un regalo per te','wceventsfp').'</h2><p>'.esc_html__('Puoi visualizzare/ stampare il tuo voucher ai link seguenti:','wceventsfp').'</p><ul>'.$items.'</ul>',
            'sender' => ['email'=>$from_email, 'name'=>$from_name],
        ];

        $args = [
            'headers'=>['accept'=>'application/json','api-key'=>$api_key,'content-type'=>'application/json'],
            'method'=>'POST','body'=>wp_json_encode($payload),'timeout'=>20,
        ];
        $res = wp_remote_request('https://api.brevo.com/v3/smtp/email', $args);
        if (is_wp_error($res)) error_log('WCEFP Gift Brevo error: '.$res->get_error_message());
    }

    /* ---------------- Voucher view (pubblico) ---------------- */
    public static function serve_voucher_view(){
        if (!isset($_GET['wcefp_voucher_view'])) return;
        $code = sanitize_text_field($_GET['code'] ?? '');
        if (!$code) return;

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_vouchers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE code=%s", $code));
        if (!$row) return;

        $product = get_post($row->product_id);
        $site = get_bloginfo('name');

        // Semplice pagina stampabile
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Voucher '.$code.'</title>';
        echo '<style>body{font-family:system-ui,Arial,sans-serif;margin:20px;} .box{border:1px solid #ddd;padding:20px;border-radius:10px;max-width:680px} .muted{color:#666} .badge{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid #ccc;font-size:12px;margin-left:8px}</style>';
        echo '</head><body>';
        echo '<div class="box">';
        echo '<h1>'.esc_html($site).' — '.esc_html__('Voucher Regalo','wceventsfp').'</h1>';
        echo '<p><strong>'.esc_html__('Codice','wceventsfp').':</strong> '.esc_html($row->code).'</p>';
        echo '<p><strong>'.esc_html__('Esperienza','wceventsfp').':</strong> '.esc_html(get_the_title($row->product_id)).'</p>';
        echo '<p><strong>'.esc_html__('Stato','wceventsfp').':</strong> '.esc_html($row->status).' <span class="badge">'.($row->status==='unused' ? esc_html__('Utilizzabile','wceventsfp') : esc_html__('Usato','wceventsfp')).'</span></p>';
        if ($row->recipient_name) echo '<p><strong>'.esc_html__('Destinatario','wceventsfp').':</strong> '.esc_html($row->recipient_name).'</p>';
        if ($row->message_text) echo '<p><em>'.nl2br(esc_html($row->message_text)).'</em></p>';
        echo '<hr><p class="muted">'.esc_html__('Per riscattare: visita la pagina “Riscatta voucher” e inserisci il codice.','wceventsfp').'</p>';
        echo '</div></body></html>';
        exit;
    }

    /* ---------------- Redeem shortcode ---------------- */
    public static function redeem_shortcode($atts){
        $out = '';
        if (!is_user_logged_in()) {
            // facoltativo: nessun obbligo login
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wcefp_voucher_code'])) {
            $code = sanitize_text_field($_POST['wcefp_voucher_code']);
            $res = self::validate_and_lock_voucher($code);
            if ($res['ok']) {
                // memorizza in sessione Woo il voucher per applicare prezzo 0 sul prodotto corrispondente
                if (function_exists('WC')) {
                    WC()->session->set('wcefp_voucher_code', $code);
                    WC()->session->set('wcefp_voucher_product', $res['product_id']);
                }
                $out .= '<div class="wcefp-redeem-ok">'.esc_html__('Codice valido. Ora puoi prenotare l’esperienza senza pagamento.','wceventsfp').'</div>';
            } else {
                $out .= '<div class="wcefp-redeem-err">'.esc_html($res['msg']).'</div>';
            }
        }

        $out .= '<form method="post" class="wcefp-redeem-form">';
        $out .= '<label>'.esc_html__('Inserisci il codice voucher','wceventsfp').'</label><br/>';
        $out .= '<input type="text" name="wcefp_voucher_code" required style="max-width:260px" /> ';
        $out .= '<button type="submit" class="button">'.esc_html__('Riscatta','wceventsfp').'</button>';
        $out .= '</form>';

        return $out;
    }

    /**
     * Valida il voucher e lo rende "locked" in sessione (non segna come usato finché non crea ordine).
     * In questa versione base non modifichiamo DB qui: segnamo come "used" al completamento ordine.
     */
    private static function validate_and_lock_voucher($code){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_vouchers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE code=%s", $code));
        if (!$row) return ['ok'=>false,'msg'=>__('Codice non trovato','wceventsfp')];
        if ($row->status !== 'unused') return ['ok'=>false,'msg'=>__('Il codice è già stato utilizzato','wceventsfp')];
        return ['ok'=>true,'product_id'=>(int)$row->product_id];
    }

    /**
     * Marca voucher come usato al completamento ordine (se utilizzato).
     * Nota: lo facciamo qui per robustezza quando si acquista a 0€ mediante voucher.
     */
    public static function mark_voucher_used_on_completed($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $voucher_code = $order->get_meta('wcefp_voucher_code');
        if (!$voucher_code) return;

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_vouchers';
        $wpdb->update($tbl, [
            'status' => 'used',
            'redeemed_at' => current_time('mysql')
        ], ['code'=>$voucher_code], ['%s','%s'], ['%s']);
    }
}

// Hook per segnare il voucher come usato se presente nell'ordine
add_action('woocommerce_order_status_completed', ['WCEFP_Gift','mark_voucher_used_on_completed']);
