<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Recurring {

    public static function ajax_generate_occurrences() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        $pid  = intval($_POST['product_id'] ?? 0);
        $from = sanitize_text_field($_POST['from'] ?? '');
        $to   = sanitize_text_field($_POST['to'] ?? '');
        if (!$pid || !$from || !$to) wp_send_json_error(['msg'=>'Parametri mancanti']);

        $weekdays = (array) get_post_meta($pid, '_wcefp_weekdays', true);
        $slots    = trim((string) get_post_meta($pid, '_wcefp_time_slots', true));
        $capacity = intval(get_post_meta($pid, '_wcefp_capacity_per_slot', true));
        if (!$capacity) $capacity = intval(get_option('wcefp_default_capacity', 0));
        $duration = max(0, intval(get_post_meta($pid, '_wcefp_duration_minutes', true)));
        if ($duration <= 0) $duration = 120;

        $slot_list = array_filter(array_map('trim', explode(',', $slots)));

        $count = self::generate($pid, $from, $to, $weekdays, $slot_list, $capacity, $duration);
        wp_send_json_success(['created'=>$count]);
    }

    public static function generate($product_id, $from, $to, array $weekdays, array $slot_list, $capacity, $duration_min) {
        if (empty($weekdays) || empty($slot_list)) return 0;
        $fromDt = new DateTime($from.' 00:00:00');
        $toDt   = new DateTime($to.' 23:59:59');
        $cur = clone $fromDt;

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $created = 0;

        while ($cur <= $toDt) {
            $w = (int)$cur->format('w'); // 0=Dom
            if (in_array($w, $weekdays, true)) {
                foreach ($slot_list as $hhmm) {
                    if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) continue;
                    [$h,$m] = array_map('intval', explode(':',$hhmm));
                    $start = (clone $cur)->setTime($h,$m,0);
                    $end   = (clone $start)->modify('+'.$duration_min.' minutes');

                    // dedup
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE product_id=%d AND start_datetime=%s LIMIT 1", $product_id, $start->format('Y-m-d H:i:s')));
                    if ($exists) continue;

                    $ins = $wpdb->insert($tbl, [
                        'product_id'     => $product_id,
                        'start_datetime' => $start->format('Y-m-d H:i:s'),
                        'end_datetime'   => $end->format('Y-m-d H:i:s'),
                        'capacity'       => $capacity ?: 0,
                        'booked'         => 0,
                        'meta'           => null,
                    ], ['%d','%s','%s','%d','%d','%s']);
                    if ($ins) $created++;
                }
            }
            $cur->modify('+1 day');
        }
        return $created;
    }

    /* === Inline edit capacità/prenotati === */
    public static function ajax_update_occurrence() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';

        $id = intval($_POST['id'] ?? 0);
        $cap = max(0, intval($_POST['capacity'] ?? 0));
        $booked = max(0, intval($_POST['booked'] ?? 0));
        if (!$id) wp_send_json_error(['msg'=>'ID mancante']);

        // Evita booked > capacity
        if ($booked > $cap) $booked = $cap;

        $wpdb->update($tbl, ['capacity'=>$cap,'booked'=>$booked], ['id'=>$id], ['%d','%d'], ['%d']);
        wp_send_json_success(['id'=>$id,'capacity'=>$cap,'booked'=>$booked]);
    }

    /* === CSV Export/Import semplice === */
    public static function ajax_bulk_occurrences_csv() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error();

        $mode = sanitize_text_field($_POST['mode'] ?? 'export');

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';

        if ($mode === 'export') {
            $pid = intval($_POST['product_id'] ?? 0);
            $rows = $pid ? $wpdb->get_results($wpdb->prepare("SELECT id,product_id,start_datetime,end_datetime,capacity,booked FROM $tbl WHERE product_id=%d ORDER BY start_datetime ASC", $pid), ARRAY_A)
                         : $wpdb->get_results("SELECT id,product_id,start_datetime,end_datetime,capacity,booked FROM $tbl ORDER BY start_datetime ASC", ARRAY_A);

            $csv = "id,product_id,start_datetime,end_datetime,capacity,booked\n";
            foreach ($rows as $r) {
                $csv .= sprintf("%d,%d,%s,%s,%d,%d\n", $r['id'],$r['product_id'],$r['start_datetime'],$r['end_datetime'],$r['capacity'],$r['booked']);
            }
            wp_send_json_success(['csv'=>$csv]);
        } else {
            // import: atteso CSV con header come sopra
            $csv = trim((string)($_POST['csv'] ?? ''));
            if (!$csv) wp_send_json_error(['msg'=>'CSV vuoto']);
            $lines = array_map('trim', explode("\n", $csv));
            array_shift($lines); // header
            $count = 0;
            foreach ($lines as $line) {
                if (!$line) continue;
                $parts = str_getcsv($line);
                if (count($parts) < 6) continue;
                [$id,$pid,$start,$end,$cap,$booked] = $parts;
                $id = intval($id); $pid=intval($pid); $cap=intval($cap); $booked=intval($booked);
                if ($id>0) {
                    $wpdb->update($tbl, [
                        'product_id'=>$pid,'start_datetime'=>$start,'end_datetime'=>$end,'capacity'=>$cap,'booked'=>$booked
                    ], ['id'=>$id], ['%d','%s','%s','%d','%d'], ['%d']);
                } else {
                    $wpdb->insert($tbl, [
                        'product_id'=>$pid,'start_datetime'=>$start,'end_datetime'=>$end,'capacity'=>$cap,'booked'=>$booked,'meta'=>null
                    ], ['%d','%s','%s','%d','%d','%s']);
                }
                $count++;
            }
            wp_send_json_success(['imported'=>$count]);
        }
    }
}
