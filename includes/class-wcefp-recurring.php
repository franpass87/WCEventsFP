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

                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE product_id=%d AND start_datetime=%s LIMIT 1", $product_id, $start->format('Y-m-d H:i:s')));
                    if ($exists) continue;

                    $wpdb->insert($tbl, [
                        'product_id'     => $product_id,
                        'start_datetime' => $start->format('Y-m-d H:i:s'),
                        'end_datetime'   => $end->format('Y-m-d H:i:s'),
                        'capacity'       => $capacity ?: 0,
                        'booked'         => 0,
                        'status'         => 'active',
                        'meta'           => null,
                    ], ['%d','%s','%s','%d','%d','%s','%s']);
                    if ($wpdb->insert_id) $created++;
                }
            }
            $cur->modify('+1 day');
        }
        return $created;
    }
}