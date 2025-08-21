<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Orders_Bridge {

    private static $voucher_counts = null;

    public static function init() {
        // Orders list column
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_order_column'], 20);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'render_order_column'], 10, 2);

        // Metabox in single order
        add_action('add_meta_boxes_shop_order', [__CLASS__, 'add_voucher_metabox']);

        // Handle quick actions from metabox
        add_action('admin_post_wcefp_voucher_status', [__CLASS__, 'handle_voucher_status']);
    }

    public static function add_order_column($cols) {
        $cols['wcefp_vouchers'] = __('Voucher', 'wceventsfp');
        return $cols;
    }

    private static function prime_counts() {
        if (self::$voucher_counts !== null) return;
        global $wpdb, $wp_query;
        self::$voucher_counts = [];
        if (!is_admin() || empty($wp_query) || empty($wp_query->posts)) return;
        $ids = wp_list_pluck($wp_query->posts, 'ID');
        if (!$ids) return;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT order_id, COUNT(*) cnt FROM {$tbl} WHERE order_id IN ($placeholders) GROUP BY order_id";
        $prepared = $wpdb->prepare($sql, $ids);
        $rows = $wpdb->get_results($prepared);
        foreach ($rows as $r) {
            self::$voucher_counts[(int)$r->order_id] = (int)$r->cnt;
        }
    }

    public static function render_order_column($column, $post_id) {
        if ($column !== 'wcefp_vouchers') return;
        self::prime_counts();
        $count = self::$voucher_counts[$post_id] ?? 0;
        if ($count > 0) {
            $url = add_query_arg([
                'page'     => 'wcefp-vouchers',
                'order_id' => $post_id,
            ], admin_url('admin.php'));
            echo '<a href="' . esc_url($url) . '">' . intval($count) . '</a>';
        } else {
            echo '0';
        }
    }

    public static function add_voucher_metabox() {
        add_meta_box(
            'wcefp_vouchers_box',
            __('Voucher collegati', 'wceventsfp'),
            [__CLASS__, 'render_voucher_metabox'],
            'shop_order',
            'side',
            'default'
        );
    }

    public static function render_voucher_metabox($post) {
        if (!current_user_can('manage_woocommerce')) {
            esc_html_e('Non autorizzato', 'wceventsfp');
            return;
        }
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, code, status FROM {$tbl} WHERE order_id=%d", $post->ID), ARRAY_A);
        if (empty($rows)) {
            echo '<p>' . esc_html__('Nessun voucher collegato.', 'wceventsfp') . '</p>';
            return;
        }
        echo '<ul class="wcefp-order-vouchers">';
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $nonce = wp_create_nonce('wcefp_voucher_status_' . $id);
            $base = add_query_arg([
                'action'     => 'wcefp_voucher_status',
                'voucher_id' => $id,
                'order_id'   => $post->ID,
            ], admin_url('admin-post.php'));
            $actions = [];
            $actions[] = '<a href="' . esc_url(add_query_arg(['status' => 'used', '_wpnonce' => $nonce], $base)) . '">' . esc_html__('Segna usato', 'wceventsfp') . '</a>';
            $actions[] = '<a href="' . esc_url(add_query_arg(['status' => 'unused', '_wpnonce' => $nonce], $base)) . '">' . esc_html__('Segna da usare', 'wceventsfp') . '</a>';
            $actions[] = '<a href="' . esc_url(add_query_arg(['status' => 'cancelled', '_wpnonce' => $nonce], $base)) . '">' . esc_html__('Annulla', 'wceventsfp') . '</a>';
            echo '<li><code>' . esc_html($row['code']) . '</code> - ' . esc_html(self::status_label($row['status'])) . '<br/><small>' . implode(' | ', $actions) . '</small></li>';
        }
        echo '</ul>';
    }

    private static function status_label($status) {
        switch ($status) {
            case 'used':
                return __('Usato', 'wceventsfp');
            case 'cancelled':
                return __('Annullato', 'wceventsfp');
            default:
                return __('Da usare', 'wceventsfp');
        }
    }

    public static function handle_voucher_status() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Non autorizzato', 'wceventsfp'));
        $voucher_id = isset($_GET['voucher_id']) ? (int)$_GET['voucher_id'] : 0;
        $status     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $order_id   = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        if (!$voucher_id || !in_array($status, ['used','unused','cancelled'], true)) {
            wp_safe_redirect(admin_url('post.php?post=' . $order_id . '&action=edit'));
            exit;
        }
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_voucher_status_' . $voucher_id)) {
            wp_safe_redirect(admin_url('post.php?post=' . $order_id . '&action=edit'));
            exit;
        }
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';
        $data = ['status' => $status];
        $fmt  = ['%s'];
        if ($status === 'used') {
            $data['redeemed_at'] = current_time('mysql');
            $fmt[] = '%s';
        } else {
            $data['redeemed_at'] = null;
            $fmt[] = '%s';
        }
        $wpdb->update($tbl, $data, ['id' => $voucher_id], $fmt, ['%d']);
        wp_safe_redirect(admin_url('post.php?post=' . $order_id . '&action=edit'));
        exit;
    }
}
