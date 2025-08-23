<?php
/**
 * WCEventsFP Occurrences List Table
 * 
 * Proper WP_List_Table implementation for displaying occurrence data
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
 * Occurrences List Table class
 */
class OccurrencesListTable extends \WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'occurrence',
            'plural'   => 'occurrences',
            'ajax'     => true,
            'screen'   => 'wcefp_occurrences'
        ]);
    }
    
    /**
     * Get columns
     * 
     * @return array
     */
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => __('ID', 'wceventsfp'),
            'event'        => __('Event', 'wceventsfp'),
            'date_time'    => __('Date & Time', 'wceventsfp'),
            'capacity'     => __('Capacity', 'wceventsfp'),
            'bookings'     => __('Bookings', 'wceventsfp'),
            'availability' => __('Availability', 'wceventsfp'),
            'status'       => __('Status', 'wceventsfp'),
            'revenue'      => __('Revenue', 'wceventsfp'),
            'actions'      => __('Actions', 'wceventsfp')
        ];
    }
    
    /**
     * Get sortable columns
     * 
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'id'           => ['id', false],
            'event'        => ['event_title', false],
            'date_time'    => ['occurrence_date', true], // Default sort
            'capacity'     => ['capacity', false],
            'bookings'     => ['bookings_count', false],
            'status'       => ['status', false],
            'revenue'      => ['revenue', false]
        ];
    }
    
    /**
     * Get bulk actions
     * 
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'activate'     => __('Activate', 'wceventsfp'),
            'deactivate'   => __('Deactivate', 'wceventsfp'),
            'duplicate'    => __('Duplicate', 'wceventsfp'),
            'delete'       => __('Delete', 'wceventsfp'),
            'export_ics'   => __('Export ICS', 'wceventsfp')
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
            '<input type="checkbox" name="occurrence[]" value="%s" />',
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
                '<a href="%s" target="_blank"><strong>%s</strong></a><br><small>ID: %d</small>',
                admin_url('post.php?post=' . $event_id . '&action=edit'),
                $event_title,
                $event_id
            );
        }
        
        return '<strong>' . $event_title . '</strong>';
    }
    
    /**
     * Column date and time
     * 
     * @param array $item
     * @return string
     */
    public function column_date_time($item) {
        $occurrence_date = $item['occurrence_date'] ?? '';
        $occurrence_time = $item['occurrence_time'] ?? '';
        
        if (empty($occurrence_date)) {
            return __('N/A', 'wceventsfp');
        }
        
        $datetime = $occurrence_date;
        if ($occurrence_time) {
            $datetime .= ' ' . $occurrence_time;
        }
        
        $timestamp = strtotime($datetime);
        $is_past = $timestamp < time();
        $is_soon = !$is_past && $timestamp < (time() + (24 * 60 * 60)); // Next 24 hours
        
        // Format for display
        $formatted_date = wp_date(
            get_option('date_format'),
            $timestamp
        );
        $formatted_time = wp_date(
            get_option('time_format'),
            $timestamp
        );
        
        $class = '';
        $icon = '';
        
        if ($is_past) {
            $class = 'wcefp-past-occurrence';
            $icon = '⏰';
        } elseif ($is_soon) {
            $class = 'wcefp-upcoming-occurrence';
            $icon = '🔔';
        }
        
        return sprintf(
            '<div class="%s">%s <strong>%s</strong><br><small>%s</small></div>',
            $class,
            $icon,
            esc_html($formatted_date),
            esc_html($formatted_time)
        );
    }
    
    /**
     * Column capacity
     * 
     * @param array $item
     * @return string
     */
    public function column_capacity($item) {
        $capacity = absint($item['capacity'] ?? 0);
        
        if ($capacity === 0) {
            return '<span style="color: #999;">' . __('Unlimited', 'wceventsfp') . '</span>';
        }
        
        return '<strong>' . $capacity . '</strong>';
    }
    
    /**
     * Column bookings
     * 
     * @param array $item
     * @return string
     */
    public function column_bookings($item) {
        $bookings_count = absint($item['bookings_count'] ?? 0);
        $participants_count = absint($item['participants_count'] ?? 0);
        
        if ($bookings_count === 0) {
            return '<span style="color: #999;">0</span>';
        }
        
        $output = sprintf('<strong>%d</strong>', $bookings_count);
        
        if ($participants_count !== $bookings_count) {
            $output .= sprintf('<br><small>%s: %d</small>', __('Participants', 'wceventsfp'), $participants_count);
        }
        
        return $output;
    }
    
    /**
     * Column availability
     * 
     * @param array $item
     * @return string
     */
    public function column_availability($item) {
        $capacity = absint($item['capacity'] ?? 0);
        $participants_count = absint($item['participants_count'] ?? 0);
        
        if ($capacity === 0) {
            return '<span class="wcefp-availability-unlimited">' . __('Available', 'wceventsfp') . '</span>';
        }
        
        $available = $capacity - $participants_count;
        $percentage = $capacity > 0 ? ($participants_count / $capacity) * 100 : 0;
        
        $status_class = '';
        $status_text = '';
        
        if ($available <= 0) {
            $status_class = 'wcefp-availability-full';
            $status_text = __('Full', 'wceventsfp');
        } elseif ($percentage >= 80) {
            $status_class = 'wcefp-availability-low';
            $status_text = sprintf(__('%d spots left', 'wceventsfp'), $available);
        } elseif ($percentage >= 50) {
            $status_class = 'wcefp-availability-medium';
            $status_text = sprintf(__('%d available', 'wceventsfp'), $available);
        } else {
            $status_class = 'wcefp-availability-high';
            $status_text = sprintf(__('%d available', 'wceventsfp'), $available);
        }
        
        return sprintf(
            '<span class="%s">%s</span><br><div class="wcefp-progress-bar" style="width: 100%%; height: 4px; background: #f0f0f0; border-radius: 2px; overflow: hidden;"><div style="width: %.1f%%; height: 100%%; background: %s;"></div></div>',
            $status_class,
            esc_html($status_text),
            $percentage,
            $percentage >= 80 ? '#e53e3e' : ($percentage >= 50 ? '#dd6b20' : '#38a169')
        );
    }
    
    /**
     * Column status
     * 
     * @param array $item
     * @return string
     */
    public function column_status($item) {
        $status = $item['status'] ?? 'active';
        $status_labels = [
            'active'     => __('Active', 'wceventsfp'),
            'inactive'   => __('Inactive', 'wceventsfp'),
            'cancelled'  => __('Cancelled', 'wceventsfp'),
            'completed'  => __('Completed', 'wceventsfp')
        ];
        
        $status_colors = [
            'active'     => '#48bb78', // Green
            'inactive'   => '#a0aec0', // Gray
            'cancelled'  => '#f56565', // Red
            'completed'  => '#38b2ac'  // Teal
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
     * Column revenue
     * 
     * @param array $item
     * @return string
     */
    public function column_revenue($item) {
        $revenue = floatval($item['revenue'] ?? 0);
        
        if ($revenue === 0.0) {
            return '<span style="color: #999;">€0.00</span>';
        }
        
        return '<strong>' . wc_price($revenue) . '</strong>';
    }
    
    /**
     * Column actions
     * 
     * @param array $item
     * @return string
     */
    public function column_actions($item) {
        $occurrence_id = absint($item['id']);
        $status = $item['status'] ?? 'active';
        
        $actions = [];
        
        // Edit action
        $actions['edit'] = sprintf(
            '<a href="%s" class="button button-small" title="%s" aria-label="%s">%s</a>',
            admin_url('admin.php?page=wcefp-occurrences&action=edit&occurrence=' . $occurrence_id),
            esc_attr__('Edit occurrence', 'wceventsfp'),
            esc_attr__('Edit occurrence', 'wceventsfp'),
            __('Edit', 'wceventsfp')
        );
        
        // View bookings action
        if ($item['bookings_count'] > 0) {
            $actions['bookings'] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                admin_url('admin.php?page=wcefp-bookings&occurrence=' . $occurrence_id),
                esc_attr__('View bookings for this occurrence', 'wceventsfp'),
                __('Bookings', 'wceventsfp')
            );
        }
        
        // Status-based actions
        if ($status === 'active') {
            $actions['deactivate'] = sprintf(
                '<a href="%s" class="button button-small" title="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=wcefp-occurrences&action=deactivate&occurrence=' . $occurrence_id),
                    'deactivate_occurrence_' . $occurrence_id
                ),
                esc_attr__('Deactivate this occurrence', 'wceventsfp'),
                __('Deactivate', 'wceventsfp')
            );
        } elseif ($status === 'inactive') {
            $actions['activate'] = sprintf(
                '<a href="%s" class="button button-small button-primary" title="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=wcefp-occurrences&action=activate&occurrence=' . $occurrence_id),
                    'activate_occurrence_' . $occurrence_id
                ),
                esc_attr__('Activate this occurrence', 'wceventsfp'),
                __('Activate', 'wceventsfp')
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
        
        // Get data
        $data = $this->get_occurrence_data($search);
        $total_items = count($data);
        
        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'date_time';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'asc';
        
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
     * Get occurrence data (placeholder)
     * 
     * @param string $search
     * @return array
     */
    private function get_occurrence_data($search = '') {
        // Placeholder data - would connect to actual data source
        return [
            [
                'id' => 1,
                'event_title' => 'Wine Tasting Experience',
                'event_id' => 123,
                'occurrence_date' => date('Y-m-d', strtotime('+7 days')),
                'occurrence_time' => '18:00',
                'capacity' => 12,
                'bookings_count' => 3,
                'participants_count' => 8,
                'status' => 'active',
                'revenue' => 269.70
            ],
            [
                'id' => 2,
                'event_title' => 'Cooking Class',
                'event_id' => 124,
                'occurrence_date' => date('Y-m-d', strtotime('+14 days')),
                'occurrence_time' => '19:30',
                'capacity' => 8,
                'bookings_count' => 2,
                'participants_count' => 6,
                'status' => 'active',
                'revenue' => 359.60
            ],
            [
                'id' => 3,
                'event_title' => 'Historical Walking Tour',
                'event_id' => 125,
                'occurrence_date' => date('Y-m-d', strtotime('-2 days')),
                'occurrence_time' => '10:00',
                'capacity' => 20,
                'bookings_count' => 12,
                'participants_count' => 18,
                'status' => 'completed',
                'revenue' => 450.00
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
                case 'event_title':
                    $result = strcmp($a['event_title'], $b['event_title']);
                    break;
                case 'date_time':
                    $datetime_a = $a['occurrence_date'] . ' ' . ($a['occurrence_time'] ?? '00:00');
                    $datetime_b = $b['occurrence_date'] . ' ' . ($b['occurrence_time'] ?? '00:00');
                    $result = strtotime($datetime_a) - strtotime($datetime_b);
                    break;
                case 'capacity':
                    $result = $a['capacity'] - $b['capacity'];
                    break;
                case 'bookings_count':
                    $result = $a['bookings_count'] - $b['bookings_count'];
                    break;
                case 'status':
                    $result = strcmp($a['status'], $b['status']);
                    break;
                case 'revenue':
                    $result = $a['revenue'] - $b['revenue'];
                    break;
            }
            
            return ($order === 'asc') ? $result : -$result;
        });
        
        return $data;
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
                <select name="event_filter" id="event-filter">
                    <option value=""><?php esc_html_e('All events', 'wceventsfp'); ?></option>
                    <!-- This would be populated with actual events -->
                </select>
                
                <select name="status_filter" id="status-filter">
                    <option value=""><?php esc_html_e('All statuses', 'wceventsfp'); ?></option>
                    <option value="active"><?php esc_html_e('Active', 'wceventsfp'); ?></option>
                    <option value="inactive"><?php esc_html_e('Inactive', 'wceventsfp'); ?></option>
                    <option value="completed"><?php esc_html_e('Completed', 'wceventsfp'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Cancelled', 'wceventsfp'); ?></option>
                </select>
                
                <input type="date" name="date_from" id="date-from" placeholder="<?php esc_attr_e('From date', 'wceventsfp'); ?>" />
                <input type="date" name="date_to" id="date-to" placeholder="<?php esc_attr_e('To date', 'wceventsfp'); ?>" />
                
                <?php submit_button(__('Filter', 'wceventsfp'), 'button', 'filter_action', false, ['id' => 'post-query-submit']); ?>
                
                <a href="<?php echo admin_url('admin.php?page=wcefp-occurrences&action=add-occurrence'); ?>" class="button button-primary">
                    <?php esc_html_e('Add Occurrence', 'wceventsfp'); ?>
                </a>
            </div>
            <?php
        }
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
            <label class="screen-reader-text" for="<?php echo esc_attr(esc_attr($input_id)); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr(esc_attr($input_id)); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search occurrences...', 'wceventsfp'); ?>" />
            <?php submit_button($text, 'button', '', false, ['id' => 'search-submit']); ?>
        </p>
        <?php
    }
}