<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WCEFP_Vouchers_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'voucher',
            'plural'   => 'vouchers',
            'ajax'     => false,
        ]);
    }

    public static function get_vouchers($per_page = 20, $page_number = 1) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';

        $orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order   = (isset($_REQUEST['order']) && strtolower($_REQUEST['order']) === 'asc') ? 'ASC' : 'DESC';

        $where = '1=1';
        $params = [];
        if (isset($_REQUEST['order_id'])) {
            $where .= ' AND order_id = %d';
            $params[] = (int)$_REQUEST['order_id'];
        } elseif (!empty($_REQUEST['wcefp_only_order'])) {
            $where .= ' AND order_id > 0';
        }

        $sql = "SELECT * FROM {$tbl} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page_number - 1) * $per_page;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    public static function record_count() {
        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';
        $where = '1=1';
        $params = [];
        if (isset($_REQUEST['order_id'])) {
            $where .= ' AND order_id = %d';
            $params[] = (int)$_REQUEST['order_id'];
        } elseif (!empty($_REQUEST['wcefp_only_order'])) {
            $where .= ' AND order_id > 0';
        }
        $sql = "SELECT COUNT(*) FROM {$tbl} WHERE {$where}";
        if ($params) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return (int) $wpdb->get_var($sql);
    }

    public function no_items() {
        _e('Nessun voucher trovato.', 'wceventsfp');
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'id'         => __('ID', 'wceventsfp'),
            'code'       => __('Codice', 'wceventsfp'),
            'customer'   => __('Cliente', 'wceventsfp'),
            'email'      => __('Email', 'wceventsfp'),
            'created_at' => __('Data creazione', 'wceventsfp'),
            'status'     => __('Stato', 'wceventsfp'),
            'value'      => __('Valore (€)', 'wceventsfp'),
            'actions'    => __('Azioni', 'wceventsfp'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'id'         => ['id', false],
            'code'       => ['code', false],
            'created_at' => ['created_at', true],
            'status'     => ['status', false],
        ];
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="voucher_ids[]" value="%d" />', $item['id']);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return (int) $item['id'];
            case 'code':
                return esc_html($item['code']);
            case 'customer':
                return esc_html($item['recipient_name']);
            case 'email':
                return esc_html($item['recipient_email']);
            case 'created_at':
                return esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item['created_at']));
            case 'status':
                return ($item['status'] === 'used') ? __('Usato', 'wceventsfp') : __('Non usato', 'wceventsfp');
            case 'value':
                $val = self::get_voucher_value($item);
                return '€ ' . number_format((float)$val, 2, ',', '.');
            case 'actions':
                $url = add_query_arg([
                    'page'   => 'wcefp-vouchers',
                    'action' => 'view',
                    'id'     => $item['id'],
                ], admin_url('admin.php'));
                return '<a href="' . esc_url($url) . '">' . __('Visualizza', 'wceventsfp') . '</a>';
            default:
                return '';
        }
    }

    public function get_bulk_actions() {
        return [
            'mark_used'   => __('Segna come usati', 'wceventsfp'),
            'mark_unused' => __('Annulla', 'wceventsfp'),
        ];
    }

    public function process_bulk_action() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below
        if (empty($_POST['voucher_ids']) || !is_array($_POST['voucher_ids'])) {
            return;
        }
        check_admin_referer('bulk-vouchers');
        if (!current_user_can('manage_woocommerce')) return;

        global $wpdb;
        $tbl = $wpdb->prefix . 'wcefp_vouchers';
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map with intval
        $ids = array_map('intval', wp_unslash($_POST['voucher_ids']));
        
        if (empty($ids)) {
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        if ($this->current_action() === 'mark_used') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tbl} SET status='used', redeemed_at=NOW() WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated, placeholders are safe
                ...$ids
            ));
        } elseif ($this->current_action() === 'mark_unused') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tbl} SET status='unused', redeemed_at=NULL WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated, placeholders are safe
                ...$ids
            ));
        }
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);

        $this->items = self::get_vouchers($per_page, $current_page);
    }

    public static function get_voucher_value($item) {
        $order = wc_get_order($item['order_id']);
        if (!$order) return 0;
        foreach ($order->get_items() as $it) {
            if ((int)$it->get_product_id() === (int)$item['product_id']) {
                $qty = max(1, (int)$it->get_quantity());
                $total = (float)$it->get_total();
                return $qty ? $total / $qty : 0;
            }
        }
        return 0;
    }
}
