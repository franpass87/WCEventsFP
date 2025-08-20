<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Vouchers_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    public static function register_menu() {
        add_submenu_page(
            'wcefp',
            __('Voucher Regalo','wceventsfp'),
            __('Voucher Regalo','wceventsfp'),
            'manage_woocommerce',
            'wcefp-vouchers',
            [__CLASS__, 'dispatch']
        );
    }

    public static function dispatch() {
        if (!current_user_can('manage_woocommerce')) return;
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($action === 'view' && !empty($_GET['id'])) {
            self::render_detail((int)$_GET['id']);
        } else {
            self::render_list();
        }
    }

    private static function render_list() {
        $table = new WCEFP_Vouchers_Table();
        $table->process_bulk_action();
        $table->prepare_items();
        include WCEFP_PLUGIN_DIR . 'admin/views/vouchers-list.php';
    }

    private static function render_detail($id) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';

        $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl} WHERE id=%d", $id), ARRAY_A);
        if (!$voucher) {
            echo '<div class="wrap"><h1>' . esc_html__('Voucher non trovato', 'wceventsfp') . '</h1></div>';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wcefp_mark_used'])) {
            check_admin_referer('wcefp_mark_used');
            if (current_user_can('manage_woocommerce')) {
                $wpdb->update($tbl, ['status' => 'used', 'redeemed_at' => current_time('mysql')], ['id' => $id], ['%s','%s'], ['%d']);
                $voucher['status'] = 'used';
                $voucher['redeemed_at'] = current_time('mysql');
                echo '<div class="notice notice-success"><p>' . esc_html__('Voucher aggiornato.', 'wceventsfp') . '</p></div>';
            }
        }

        $voucher['value'] = WCEFP_Vouchers_Table::get_voucher_value($voucher);

        include WCEFP_PLUGIN_DIR . 'admin/views/voucher-detail.php';
    }
}
