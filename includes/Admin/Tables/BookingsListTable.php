<?php
/**
 * WCEventsFP Bookings List Table
 * 
 * Proper WP_List_Table implementation for displaying booking data
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.2.0
 */

namespace WCEFP\Admin\Tables;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Bookings List Table class
 */
class BookingsListTable extends \WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => true,
            'screen'   => 'wcefp_bookings'
        ]);
    }
    
    /**
     * Get columns
     * 
     * @return array
     */
    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'id'            => __('ID', 'wceventsfp'),
            'customer'      => __('Customer', 'wceventsfp'),
            'event'         => __('Event', 'wceventsfp'),
            'occurrence'    => __('Date & Time', 'wceventsfp'),
            'participants'  => __('Participants', 'wceventsfp'),
            'status'        => __('Status', 'wceventsfp'),
            'amount'        => __('Amount', 'wceventsfp'),
            'created_date'  => __('Booking Date', 'wceventsfp'),
            'actions'       => __('Actions', 'wceventsfp')
        ];
    }
    
    /**
     * Get sortable columns
     * 
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'id'            => ['id', false],
            'customer'      => ['customer_name', false],
            'occurrence'    => ['occurrence_date', false],
            'status'        => ['status', false],
            'amount'        => ['total_amount', false],
            'created_date'  => ['created_at', true] // Default sort DESC
        ];
    }
    
    /**
     * Get bulk actions
     * 
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'confirm'   => __('Mark as Confirmed', 'wceventsfp'),
            'cancel'    => __('Cancel Bookings', 'wceventsfp'),
            'delete'    => __('Delete', 'wceventsfp'),
            'export'    => __('Export CSV', 'wceventsfp')
        ];
    }
    
    /**
     * Column checkbox
     * 
     * @param array $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="booking[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Column ID
     * 
     * @param array $item
     * @return string
     */
    public function column_id($item) {
        return '<strong>#' . esc_html($item['id']) . '</strong>';
    }
    
    /**
     * Column customer
     * 
     * @param array $item
     * @return string
     */
    public function column_customer($item) {
        $customer_name = esc_html($item['customer_name'] ?? __('Unknown', 'wceventsfp'));
        $customer_email = esc_html($item['customer_email'] ?? '');
        
        $output = '<strong>' . $customer_name . '</strong>';
        if ($customer_email) {
            $output .= '<br><small><a href="mailto:' . $customer_email . '">' . $customer_email . '</a></small>';
        }
        
        return $output;
    }
    
    /**
     * Column event
     * 
     * @param array $item
     * @return string
     */
    public function column_event($item) {
        $event_title = esc_html($item['event_title'] ?? __('Unknown Event', 'wceventsfp'));
        $event_id = absint($item['event_id'] ?? 0);
        
        if ($event_id && current_user_can('edit_products')) {
            return sprintf(
                '<a href="%s" target="_blank">%s</a><br><small>ID: %d</small>',
                admin_url('post.php?post=' . $event_id . '&action=edit'),
                $event_title,
                $event_id
            );
        }
        
        return $event_title;
    }
    
    /**
     * Column occurrence
     * 
     * @param array $item
     * @return string
     */
    public function column_occurrence($item) {
        $occurrence_date = $item['occurrence_date'] ?? '';
        $occurrence_time = $item['occurrence_time'] ?? '';
        
        if (empty($occurrence_date)) {
            return __('N/A', 'wceventsfp');
        }
        
        $datetime = $occurrence_date;
        if ($occurrence_time) {
            $datetime .= ' ' . $occurrence_time;
        }
        
        // Format for display
        $formatted_date = wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($datetime)
        );
        
        return esc_html($formatted_date);
    }
    
    /**
     * Column participants
     * 
     * @param array $item
     * @return string
     */
    public function column_participants($item) {
        $participants = absint($item['participants'] ?? 0);
        return $participants > 0 ? $participants : '1';
    }
    
    /**
     * Column status
     * 
     * @param array $item
     * @return string
     */
    public function column_status($item) {
        $status = $item['status'] ?? 'pending';
        $status_labels = [
            'pending'    => __('Pending', 'wceventsfp'),
            'confirmed'  => __('Confirmed', 'wceventsfp'),
            'completed'  => __('Completed', 'wceventsfp'),
            'cancelled'  => __('Cancelled', 'wceventsfp'),
            'refunded'   => __('Refunded', 'wceventsfp')
        ];
        
        $status_colors = [
            'pending'    => '#f56565', // Red
            'confirmed'  => '#48bb78', // Green
            'completed'  => '#38b2ac', // Teal
            'cancelled'  => '#a0aec0', // Gray
            'refunded'   => '#ed8936'  // Orange
        ];
        
        $label = $status_labels[$status] ?? ucfirst($status);
        $color = $status_colors[$status] ?? '#4a5568';
        
        return sprintf(
            '<span class="wcefp-status-badge" style="background-color: %s; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">%s</span>',
            $color,
            esc_html($label)
        );
    }
    
    /**
     * Column amount
     * 
     * @param array $item
     * @return string
     */
    public function column_amount($item) {
        $amount = floatval($item['total_amount'] ?? 0);
        $currency = get_option('woocommerce_currency', 'EUR');
        
        return '<strong>' . wc_price($amount) . '</strong>';
    }
    
    /**
     * Column created date
     * 
     * @param array $item
     * @return string
     */
    public function column_created_date($item) {
        $created_at = $item['created_at'] ?? '';
        
        if (empty($created_at)) {
            return __('N/A', 'wceventsfp');
        }
        
        $formatted_date = wp_date(
            get_option('date_format'),
            strtotime($created_at)
        );
        
        return esc_html($formatted_date);
    }
    
    /**
     * Column actions
     * 
     * @param array $item
     * @return string
     */
    public function column_actions($item) {
        $booking_id = absint($item['id']);
        $status = $item['status'] ?? 'pending';
        
        $actions = [];
        
        // View/Edit action
        $actions['edit'] = sprintf(
            '<a href="%s" class="button button-small" title="%s" aria-label="%s">%s</a>',
            admin_url('admin.php?page=wcefp-bookings&action=edit&booking=' . $booking_id),
            esc_attr__('View booking details', 'wceventsfp'),
            esc_attr__('View booking details', 'wceventsfp'),
            __('View', 'wceventsfp')
        );
        
        // Status-based actions
        if ($status === 'pending') {
            $actions['confirm'] = sprintf(
                '<a href="%s" class="button button-small button-primary" title="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=wcefp-bookings&action=confirm&booking=' . $booking_id),
                    'confirm_booking_' . $booking_id
                ),
                esc_attr__('Confirm this booking', 'wceventsfp'),
                __('Confirm', 'wceventsfp')
            );
        }
        
        if (in_array($status, ['pending', 'confirmed'])) {
            $actions['cancel'] = sprintf(
                '<a href="%s" class="button button-small" onclick="return confirm(\'%s\')" title="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=wcefp-bookings&action=cancel&booking=' . $booking_id),
                    'cancel_booking_' . $booking_id
                ),
                esc_attr__('Are you sure you want to cancel this booking?', 'wceventsfp'),
                esc_attr__('Cancel this booking', 'wceventsfp'),
                __('Cancel', 'wceventsfp')
            );
        }
        
        return implode(' ', $actions);
    }
    
    /**
     * Prepare items for display
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Get data (this would be replaced with actual data source)
        $data = $this->get_booking_data($search);
        $total_items = count($data);
        
        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc';
        
        $data = $this->sort_data($data, $orderby, $order);
        
        // Handle pagination
        $data = array_slice($data, ($current_page - 1) * $per_page, $per_page);
        
        $this->items = $data;
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    /**
     * Get booking data (placeholder - would connect to actual data source)
     * 
     * @param string $search
     * @return array
     */
    private function get_booking_data($search = '') {
        // This is a placeholder - in real implementation, this would query the database
        return [
            [
                'id' => 1,
                'customer_name' => 'Mario Rossi',
                'customer_email' => 'mario@example.com',
                'event_title' => 'Wine Tasting Experience',
                'event_id' => 123,
                'occurrence_date' => date('Y-m-d', strtotime('+7 days')),
                'occurrence_time' => '18:00',
                'participants' => 2,
                'status' => 'confirmed',
                'total_amount' => 89.90,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ],
            [
                'id' => 2,
                'customer_name' => 'Anna Bianchi',
                'customer_email' => 'anna@example.com',
                'event_title' => 'Cooking Class',
                'event_id' => 124,
                'occurrence_date' => date('Y-m-d', strtotime('+14 days')),
                'occurrence_time' => '19:30',
                'participants' => 4,
                'status' => 'pending',
                'total_amount' => 179.80,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
    }
    
    /**
     * Sort data
     * 
     * @param array $data
     * @param string $orderby
     * @param string $order
     * @return array
     */
    private function sort_data($data, $orderby, $order) {
        if (empty($data)) {
            return $data;
        }
        
        usort($data, function($a, $b) use ($orderby, $order) {
            $result = 0;
            
            switch ($orderby) {
                case 'id':
                    $result = $a['id'] - $b['id'];
                    break;
                case 'customer_name':
                    $result = strcmp($a['customer_name'], $b['customer_name']);
                    break;
                case 'occurrence_date':
                    $result = strtotime($a['occurrence_date']) - strtotime($b['occurrence_date']);
                    break;
                case 'status':
                    $result = strcmp($a['status'], $b['status']);
                    break;
                case 'total_amount':
                    $result = $a['total_amount'] - $b['total_amount'];
                    break;
                case 'created_at':
                default:
                    $result = strtotime($a['created_at']) - strtotime($b['created_at']);
                    break;
            }
            
            return ($order === 'asc') ? $result : -$result;
        });
        
        return $data;
    }
    
    /**
     * Display search box
     * 
     * @param string $text
     * @param string $input_id
     */
    public function search_box($text, $input_id) {
        $input_id = $input_id . '-search-input';
        
        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search bookings...', 'wceventsfp'); ?>" />
            <?php submit_button($text, 'button', '', false, ['id' => 'search-submit']); ?>
        </p>
        <?php
    }
    
    /**
     * Extra tablenav
     * 
     * @param string $which
     */
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <select name="status_filter" id="status-filter">
                    <option value=""><?php esc_html_e('All statuses', 'wceventsfp'); ?></option>
                    <option value="pending"><?php esc_html_e('Pending', 'wceventsfp'); ?></option>
                    <option value="confirmed"><?php esc_html_e('Confirmed', 'wceventsfp'); ?></option>
                    <option value="completed"><?php esc_html_e('Completed', 'wceventsfp'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Cancelled', 'wceventsfp'); ?></option>
                </select>
                
                <input type="date" name="date_from" id="date-from" placeholder="<?php esc_attr_e('From date', 'wceventsfp'); ?>" />
                <input type="date" name="date_to" id="date-to" placeholder="<?php esc_attr_e('To date', 'wceventsfp'); ?>" />
                
                <?php submit_button(__('Filter', 'wceventsfp'), 'button', 'filter_action', false, ['id' => 'post-query-submit']); ?>
                
                <a href="<?php echo admin_url('admin.php?page=wcefp-bookings&action=export'); ?>" class="button">
                    <?php esc_html_e('Export CSV', 'wceventsfp'); ?>
                </a>
            </div>
            <?php
        }
    }
}