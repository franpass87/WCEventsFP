<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Closures {

    /** Pagina admin: form + lista */
    public static function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Chiusure straordinarie','wceventsfp'); ?></h1>

            <div class="card" style="max-width:880px;padding:16px;margin-top:12px;">
                <h2 style="margin-top:0;"><?php _e('Aggiungi chiusura','wceventsfp'); ?></h2>
                <div class="wcefp-closures-form">
                    <label>
                        <?php _e('Prodotto','wceventsfp'); ?><br/>
                        <select id="wcefp-close-product">
                            <option value="0"><?php _e('Tutti gli eventi/esperienze','wceventsfp'); ?></option>
                        </select>
                    </label>
                    <label>
                        <?php _e('Dal','wceventsfp'); ?><br/>
                        <input type="date" id="wcefp-close-from" />
                    </label>
                    <label>
                        <?php _e('Al','wceventsfp'); ?><br/>
                        <input type="date" id="wcefp-close-to" />
                    </label>
                    <label class="wcefp-closures-note">
                        <?php _e('Nota (opzionale)','wceventsfp'); ?><br/>
                        <input type="text" id="wcefp-close-note" placeholder="<?php esc_attr_e('Es. Manutenzione, festività…','wceventsfp'); ?>" />
                    </label>
                    <div>
                        <button class="button button-primary" id="wcefp-add-closure"><?php _e('Aggiungi','wceventsfp'); ?></button>
                    </div>
                </div>
            </div>

            <h2><?php _e('Elenco chiusure','wceventsfp'); ?></h2>
            <div id="wcefp-closures-list">
                <p><?php _e('Caricamento…','wceventsfp'); ?></p>
            </div>
        </div>
        <?php
    }

    /** AJAX: aggiunge chiusura */
    public static function ajax_add_closure() {
        try {
            check_ajax_referer('wcefp_admin','nonce');
            if (!current_user_can('manage_woocommerce')) {
                \WCEFP\Utils\Logger::warning('Unauthorized access attempt to add closure');
                wp_send_json_error(['msg'=>'No perms']);
            }

            $validation_rules = [
                'product_id' => ['method' => 'validate_product_id', 'required' => false],
                'from' => ['method' => 'validate_date', 'required' => true],
                'to' => ['method' => 'validate_date', 'required' => true],
                'note' => ['method' => 'validate_text', 'args' => [500], 'required' => false],
            ];

            $validated = WCEFP_Validator::validate_bulk($_POST, $validation_rules);
            if ($validated === false) {
                wp_send_json_error(['msg'=>__('Dati non validi','wceventsfp')]);
            }

            $pid = $validated['product_id'] ?? 0;
            $from = $validated['from'];
            $to = $validated['to'];
            $note = $validated['note'] ?? '';

            if ($from > $to) {
                \WCEFP\Utils\Logger::warning('Invalid date range for closure', [
                    'from' => $from, 
                    'to' => $to
                ]);
                wp_send_json_error(['msg'=>__('Intervallo non valido','wceventsfp')]);
            }

            global $wpdb; $tbl = $wpdb->prefix.'wcefp_closures';
            
            $ins = $wpdb->insert($tbl, [
                'product_id' => $pid,
                'start_date' => $from,
                'end_date'   => $to,
                'note'       => $note,
            ], ['%d','%s','%s','%s']);

            if (!$ins) {
                \WCEFP\Utils\Logger::error('Failed to insert closure', [
                    'product_id' => $pid,
                    'from' => $from,
                    'to' => $to,
                    'wpdb_error' => $wpdb->last_error
                ]);
                wp_send_json_error(['msg'=>__('Errore salvataggio','wceventsfp')]);
            }

            \WCEFP\Utils\Logger::info('Closure added successfully', [
                'closure_id' => $wpdb->insert_id,
                'product_id' => $pid,
                'from' => $from,
                'to' => $to
            ]);

            wp_send_json_success(['ok'=>true, 'id' => $wpdb->insert_id]);
            
        } catch (Exception $e) {
            \WCEFP\Utils\Logger::error('Exception in ajax_add_closure', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            wp_send_json_error(['msg'=>__('Errore interno','wceventsfp')]);
        }
    }

    /** AJAX: elimina chiusura */
    public static function ajax_delete_closure() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['msg'=>'ID mancante']);

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_closures';
        $del = $wpdb->delete($tbl, ['id'=>$id], ['%d']);
        if (!$del) wp_send_json_error(['msg'=>__('Errore eliminazione','wceventsfp')]);
        wp_send_json_success(['ok'=>true]);
    }

    /** AJAX: lista chiusure */
    public static function ajax_list_closures() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_closures';
        $rows = $wpdb->get_results("SELECT id,product_id,start_date,end_date,note,created_at FROM $tbl ORDER BY start_date DESC", ARRAY_A);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'product_id' => (int)$r['product_id'],
                'product' => $r['product_id'] ? get_the_title((int)$r['product_id']) : __('Tutti','wceventsfp'),
                'from' => $r['start_date'],
                'to' => $r['end_date'],
                'note' => $r['note'],
                'created_at' => $r['created_at'],
            ];
        }
        wp_send_json_success(['rows'=>$out]);
    }

    /** Helper pubblico: verifica se una data è chiusa (globale o per prodotto) */
    public static function is_date_closed($product_id, $dateYmd) {
        if (!$dateYmd || !self::is_valid_date($dateYmd)) return false;
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_closures';
        // Chiusure globali (product_id=0) o specifiche
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tbl WHERE (product_id=0 OR product_id=%d) AND %s BETWEEN start_date AND end_date LIMIT 1",
            $product_id, $dateYmd
        ));
        return !empty($exists);
    }

    private static function is_valid_date($d){
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }
}
