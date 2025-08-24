<?php
/**
 * REST API Manager
 * 
 * Handles registration and management of REST API endpoints for WCEventsFP.
 * 
 * @package WCEFP
 * @subpackage API
 * @since 2.1.4
 */

namespace WCEFP\API;

use WCEFP\Admin\RolesCapabilities;
use WCEFP\Utils\DiagnosticLogger;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Manager class
 */
class RestApiManager {
    
    /**
     * API namespace
     * 
     * @var string
     */
    const NAMESPACE = 'wcefp/v1';
    
    /**
     * Initialize REST API
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_api_init', [$this, 'register_fields']);
        add_filter('rest_authentication_errors', [$this, 'authentication_fallback']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Bookings endpoints
        register_rest_route(self::NAMESPACE, '/bookings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_bookings'],
                'permission_callback' => [$this, 'check_bookings_permission'],
                'args' => $this->get_bookings_args()
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_booking'],
                'permission_callback' => [$this, 'check_booking_create_permission'],
                'args' => $this->get_create_booking_args()
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/bookings/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_booking'],
                'permission_callback' => [$this, 'check_booking_permission'],
                'args' => ['id' => ['validate_callback' => [$this, 'validate_booking_id']]]
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_booking'],
                'permission_callback' => [$this, 'check_booking_edit_permission'],
                'args' => array_merge(['id' => ['validate_callback' => [$this, 'validate_booking_id']]], $this->get_update_booking_args())
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_booking'],
                'permission_callback' => [$this, 'check_booking_delete_permission'],
                'args' => ['id' => ['validate_callback' => [$this, 'validate_booking_id']]]
            ]
        ]);
        
        // Events endpoints
        register_rest_route(self::NAMESPACE, '/events', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_events'],
                'permission_callback' => [$this, 'check_events_permission'],
                'args' => $this->get_events_args()
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_event'],
                'permission_callback' => [$this, 'check_event_permission'],
                'args' => ['id' => ['validate_callback' => [$this, 'validate_event_id']]]
            ]
        ]);
        
        // Event occurrences endpoints
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/occurrences', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_event_occurrences'],
                'permission_callback' => [$this, 'check_event_permission'],
                'args' => array_merge(
                    ['id' => ['validate_callback' => [$this, 'validate_event_id']]],
                    $this->get_occurrences_args()
                )
            ]
        ]);
        
        // Export endpoints
        register_rest_route(self::NAMESPACE, '/export/bookings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_bookings'],
                'permission_callback' => [$this, 'check_export_permission'],
                'args' => $this->get_export_args()
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/export/calendar', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_calendar'],
                'permission_callback' => [$this, 'check_export_permission'],
                'args' => $this->get_calendar_export_args()
            ]
        ]);
        
        // System endpoints
        register_rest_route(self::NAMESPACE, '/system/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_system_status'],
                'permission_callback' => [$this, 'check_system_permission']
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/system/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_system_health'],
                'permission_callback' => [$this, 'check_system_permission']
            ]
        ]);
        
        // Integration endpoints
        register_rest_route(self::NAMESPACE, '/integrations/test/(?P<service>[\w-]+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'test_integration'],
                'permission_callback' => [$this, 'check_integration_permission'],
                'args' => ['service' => ['validate_callback' => [$this, 'validate_service_name']]]
            ]
        ]);
        
        // Webhooks endpoints
        register_rest_route(self::NAMESPACE, '/webhooks/booking-created', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'webhook_booking_created'],
                'permission_callback' => '__return_true', // Public webhook
                'args' => $this->get_webhook_args()
            ]
        ]);
        
        // Enhanced booking endpoints for v2
        
        // Cart operations
        register_rest_route(self::NAMESPACE, '/cart/add', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'add_to_cart_v2'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'product_id' => [
                    'required' => true,
                    'validate_callback' => function($param) { return is_numeric($param); }
                ],
                'occurrence_id' => [
                    'required' => false,
                    'validate_callback' => function($param) { return empty($param) || is_numeric($param); }
                ],
                'tickets' => [
                    'required' => true,
                    'validate_callback' => [$this, 'validate_tickets_data']
                ],
                'extras' => [
                    'required' => false,
                    'validate_callback' => [$this, 'validate_extras_data']
                ]
            ]
        ]);
        
        // Price calculation
        register_rest_route(self::NAMESPACE, '/calculate-price', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'calculate_price_v2'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'product_id' => [
                    'required' => true,
                    'validate_callback' => function($param) { return is_numeric($param); }
                ],
                'tickets' => [
                    'required' => true,
                    'validate_callback' => [$this, 'validate_tickets_data']
                ],
                'extras' => [
                    'required' => false,
                    'validate_callback' => [$this, 'validate_extras_data']
                ],
                'date' => [
                    'required' => false,
                    'validate_callback' => function($param) { return empty($param) || $this->validate_date_format($param); }
                ]
            ]
        ]);
        
        // Get tickets for a product
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/tickets', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_event_tickets_v2'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) { return is_numeric($param); }
                ]
            ]
        ]);
        
        // Get extras for a product
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/extras', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_event_extras_v2'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) { return is_numeric($param); }
                ]
            ]
        ]);
    }
    
    /**
     * Register custom fields for existing endpoints
     */
    public function register_fields() {
        // Add WCEFP fields to WooCommerce products
        register_rest_field('product', 'wcefp_event_data', [
            'get_callback' => [$this, 'get_event_data'],
            'update_callback' => [$this, 'update_event_data'],
            'schema' => [
                'description' => __('WCEventsFP event data', 'wceventsfp'),
                'type' => 'object',
                'context' => ['view', 'edit'],
                'properties' => [
                    'is_event' => ['type' => 'boolean'],
                    'capacity' => ['type' => 'integer'],
                    'duration' => ['type' => 'integer'],
                    'location' => ['type' => 'string'],
                    'meeting_point_id' => ['type' => 'integer'],
                    'available_dates' => ['type' => 'array'],
                    'price_rules' => ['type' => 'object']
                ]
            ]
        ]);
    }
    
    // Bookings endpoints implementation
    
    /**
     * Get bookings list
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_bookings(WP_REST_Request $request) {
        try {
            global $wpdb;
            
            $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
            $page = max(1, intval($request->get_param('page') ?: 1));
            $offset = ($page - 1) * $per_page;
            $status = sanitize_text_field($request->get_param('status') ?: '');
            $event_id = intval($request->get_param('event_id') ?: 0);
            $date_from = sanitize_text_field($request->get_param('date_from') ?: '');
            $date_to = sanitize_text_field($request->get_param('date_to') ?: '');
            
            $where_conditions = ['1=1'];
            $params = [];
            
            if ($status) {
                $where_conditions[] = 'status = %s';
                $params[] = $status;
            }
            
            if ($event_id) {
                $where_conditions[] = 'product_id = %d';
                $params[] = $event_id;
            }
            
            if ($date_from) {
                $where_conditions[] = 'booking_date >= %s';
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where_conditions[] = 'booking_date <= %s';
                $params[] = $date_to;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_bookings WHERE {$where_clause}";
            $total = intval($wpdb->get_var($wpdb->prepare($count_query, $params)));
            
            // Get bookings
            $bookings_query = "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $params[] = $per_page;
            $params[] = $offset;
            
            $bookings = $wpdb->get_results($wpdb->prepare($bookings_query, $params));
            
            $formatted_bookings = [];
            foreach ($bookings as $booking) {
                $formatted_bookings[] = $this->format_booking_for_api($booking);
            }
            
            $response = new WP_REST_Response($formatted_bookings);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', ceil($total / $per_page));
            
            return $response;
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('REST API error: get_bookings', [
                'error' => $e->getMessage(),
                'request_params' => $request->get_params()
            ]);
            
            return new WP_Error('api_error', __('Unable to retrieve bookings', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Get single booking
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_booking(WP_REST_Request $request) {
        $booking_id = intval($request->get_param('id'));
        
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        return new WP_REST_Response($this->format_booking_for_api($booking));
    }
    
    /**
     * Create new booking
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_booking(WP_REST_Request $request) {
        try {
            global $wpdb;
            
            $event_id = intval($request->get_param('event_id'));
            $booking_date = sanitize_text_field($request->get_param('booking_date'));
            $booking_time = sanitize_text_field($request->get_param('booking_time') ?: '');
            $participants = max(1, intval($request->get_param('participants')));
            $customer_name = sanitize_text_field($request->get_param('customer_name'));
            $customer_email = sanitize_email($request->get_param('customer_email'));
            $customer_phone = sanitize_text_field($request->get_param('customer_phone') ?: '');
            $special_requests = sanitize_textarea_field($request->get_param('special_requests') ?: '');
            
            // Validate event exists
            $event = get_post($event_id);
            if (!$event || $event->post_type !== 'product') {
                return new WP_Error('invalid_event', __('Event not found', 'wceventsfp'), ['status' => 400]);
            }
            
            // Check if it's actually an event
            if (get_post_meta($event_id, '_wcefp_is_event', true) !== '1') {
                return new WP_Error('invalid_event', __('Product is not an event', 'wceventsfp'), ['status' => 400]);
            }
            
            // Validate booking date
            if (!$booking_date || strtotime($booking_date) < strtotime(date('Y-m-d'))) {
                return new WP_Error('invalid_date', __('Invalid booking date', 'wceventsfp'), ['status' => 400]);
            }
            
            // Check capacity (simplified - in real implementation would check existing bookings)
            $capacity = intval(get_post_meta($event_id, '_wcefp_capacity', true) ?: 10);
            if ($participants > $capacity) {
                return new WP_Error('capacity_exceeded', __('Requested participants exceed event capacity', 'wceventsfp'), ['status' => 400]);
            }
            
            // Calculate price
            $product = wc_get_product($event_id);
            $unit_price = $product ? floatval($product->get_price()) : 0;
            $total_price = $unit_price * $participants;
            
            // Insert booking
            $result = $wpdb->insert(
                $wpdb->prefix . 'wcefp_bookings',
                [
                    'product_id' => $event_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                    'booking_date' => $booking_date,
                    'booking_time' => $booking_time,
                    'participants' => $participants,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price,
                    'status' => 'confirmed',
                    'special_requests' => $special_requests,
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s'
                ]
            );
            
            if ($result === false) {
                return new WP_Error('booking_creation_failed', __('Failed to create booking', 'wceventsfp'), ['status' => 500]);
            }
            
            $booking_id = $wpdb->insert_id;
            
            // Get created booking
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE id = %d",
                $booking_id
            ));
            
            // Fire booking created hook
            do_action('wcefp_booking_created', $booking_id, $booking);
            
            $response = new WP_REST_Response($this->format_booking_for_api($booking));
            $response->set_status(201);
            
            return $response;
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('REST API error: create_booking', [
                'error' => $e->getMessage(),
                'request_params' => $request->get_params()
            ]);
            
            return new WP_Error('api_error', __('Unable to create booking', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Update booking
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_booking(WP_REST_Request $request) {
        global $wpdb;
        
        $booking_id = intval($request->get_param('id'));
        
        // Check if booking exists
        $existing_booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$existing_booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        $update_data = [];
        $update_format = [];
        
        // Only update provided fields
        if ($request->has_param('status')) {
            $status = sanitize_text_field($request->get_param('status'));
            if (in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
                $update_data['status'] = $status;
                $update_format[] = '%s';
            }
        }
        
        if ($request->has_param('participants')) {
            $participants = max(1, intval($request->get_param('participants')));
            $update_data['participants'] = $participants;
            $update_format[] = '%d';
            
            // Recalculate price if participants changed
            $update_data['total_price'] = $existing_booking->unit_price * $participants;
            $update_format[] = '%f';
        }
        
        if ($request->has_param('special_requests')) {
            $update_data['special_requests'] = sanitize_textarea_field($request->get_param('special_requests'));
            $update_format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_update_data', __('No valid update data provided', 'wceventsfp'), ['status' => 400]);
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'wcefp_bookings',
            $update_data,
            ['id' => $booking_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update booking', 'wceventsfp'), ['status' => 500]);
        }
        
        // Get updated booking
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE id = %d",
            $booking_id
        ));
        
        // Fire booking updated hook
        do_action('wcefp_booking_updated', $booking_id, $booking, $update_data);
        
        return new WP_REST_Response($this->format_booking_for_api($booking));
    }
    
    /**
     * Delete booking
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_booking(WP_REST_Request $request) {
        global $wpdb;
        
        $booking_id = intval($request->get_param('id'));
        
        // Check if booking exists
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        // Instead of hard delete, update status to cancelled
        $result = $wpdb->update(
            $wpdb->prefix . 'wcefp_bookings',
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to cancel booking', 'wceventsfp'), ['status' => 500]);
        }
        
        // Fire booking deleted/cancelled hook
        do_action('wcefp_booking_cancelled', $booking_id, $booking);
        
        return new WP_REST_Response(['deleted' => true, 'status' => 'cancelled']);
    }
    
    // Events endpoints implementation
    
    /**
     * Get events list
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_events(WP_REST_Request $request) {
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
        $page = max(1, intval($request->get_param('page') ?: 1));
        $category = sanitize_text_field($request->get_param('category') ?: '');
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $date_from = sanitize_text_field($request->get_param('date_from') ?: '');
        $orderby = sanitize_text_field($request->get_param('orderby') ?: 'date');
        $order = sanitize_text_field($request->get_param('order') ?: 'DESC');
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_event',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        
        if ($search) {
            $args['s'] = $search;
        }
        
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category
                ]
            ];
        }
        
        if ($date_from) {
            // This would need more complex meta query for available dates
            // For now, just add to meta query
            $args['meta_query'][] = [
                'key' => '_wcefp_available_from',
                'value' => $date_from,
                'compare' => '>=',
                'type' => 'DATE'
            ];
        }
        
        $query = new \WP_Query($args);
        
        $events = [];
        foreach ($query->posts as $post) {
            $events[] = $this->format_event_for_api($post);
        }
        
        $response = new WP_REST_Response($events);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    /**
     * Get single event
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_event(WP_REST_Request $request) {
        $event_id = intval($request->get_param('id'));
        
        $event = get_post($event_id);
        
        if (!$event || $event->post_type !== 'product') {
            return new WP_Error('event_not_found', __('Event not found', 'wceventsfp'), ['status' => 404]);
        }
        
        if (get_post_meta($event_id, '_wcefp_is_event', true) !== '1') {
            return new WP_Error('not_event', __('Product is not an event', 'wceventsfp'), ['status' => 400]);
        }
        
        return new WP_REST_Response($this->format_event_for_api($event, true));
    }
    
    /**
     * Get event occurrences
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_event_occurrences(WP_REST_Request $request) {
        global $wpdb;
        
        $event_id = intval($request->get_param('id'));
        $date_from = sanitize_text_field($request->get_param('date_from') ?: date('Y-m-d'));
        $date_to = sanitize_text_field($request->get_param('date_to') ?: '');
        $include_bookings = (bool)$request->get_param('include_bookings');
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 50)));
        $page = max(1, intval($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $per_page;
        
        // Verify event exists and is an event
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'product') {
            return new WP_Error('event_not_found', __('Event not found', 'wceventsfp'), ['status' => 404]);
        }
        
        if (get_post_meta($event_id, '_wcefp_is_event', true) !== '1') {
            return new WP_Error('not_event', __('Product is not an event', 'wceventsfp'), ['status' => 400]);
        }
        
        // Build occurrences query
        $where_conditions = ['product_id = %d', 'data_evento >= %s'];
        $params = [$event_id, $date_from];
        
        if ($date_to) {
            $where_conditions[] = 'data_evento <= %s';
            $params[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "SELECT COUNT(DISTINCT data_evento, ora_evento) FROM {$wpdb->prefix}wcefp_occorrenze WHERE {$where_clause}";
        $total = intval($wpdb->get_var($wpdb->prepare($count_query, $params)));
        
        // Get occurrences with booking counts
        $occurrences_query = "
            SELECT 
                data_evento,
                ora_evento,
                COUNT(id) as bookings_count,
                SUM(CASE WHEN stato IN ('confirmed', 'pending') THEN 1 ELSE 0 END) as active_bookings,
                SUM(adults + children) as total_participants,
                MIN(created_at) as first_booking,
                MAX(created_at) as latest_booking
            FROM {$wpdb->prefix}wcefp_occorrenze 
            WHERE {$where_clause}
            GROUP BY data_evento, ora_evento
            ORDER BY data_evento ASC, ora_evento ASC
            LIMIT %d OFFSET %d
        ";
        $params[] = $per_page;
        $params[] = $offset;
        
        $occurrences = $wpdb->get_results($wpdb->prepare($occurrences_query, $params));
        
        $formatted_occurrences = [];
        foreach ($occurrences as $occurrence) {
            $occurrence_data = [
                'date' => $occurrence->data_evento,
                'time' => $occurrence->ora_evento,
                'datetime' => $occurrence->data_evento . ' ' . $occurrence->ora_evento,
                'bookings_count' => intval($occurrence->bookings_count),
                'active_bookings' => intval($occurrence->active_bookings),
                'total_participants' => intval($occurrence->total_participants),
                'first_booking' => $occurrence->first_booking,
                'latest_booking' => $occurrence->latest_booking,
                'capacity' => intval(get_post_meta($event_id, '_wcefp_capacity', true) ?: 10),
            ];
            
            // Calculate availability
            $occurrence_data['available_spots'] = max(0, $occurrence_data['capacity'] - $occurrence_data['total_participants']);
            $occurrence_data['is_full'] = $occurrence_data['available_spots'] === 0;
            
            // Include individual bookings if requested
            if ($include_bookings) {
                $bookings_query = "
                    SELECT id, nome, email, telefono, adults, children, stato, created_at, note
                    FROM {$wpdb->prefix}wcefp_occorrenze 
                    WHERE product_id = %d AND data_evento = %s AND ora_evento = %s
                    ORDER BY created_at DESC
                ";
                $bookings = $wpdb->get_results($wpdb->prepare($bookings_query, [$event_id, $occurrence->data_evento, $occurrence->ora_evento]));
                
                $occurrence_data['bookings'] = array_map(function($booking) {
                    return [
                        'id' => intval($booking->id),
                        'customer_name' => $booking->nome,
                        'customer_email' => $booking->email,
                        'customer_phone' => $booking->telefono,
                        'adults' => intval($booking->adults),
                        'children' => intval($booking->children),
                        'status' => $booking->stato,
                        'booking_date' => $booking->created_at,
                        'notes' => $booking->note
                    ];
                }, $bookings);
            }
            
            $formatted_occurrences[] = $occurrence_data;
        }
        
        $response = new WP_REST_Response($formatted_occurrences);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));
        
        return $response;
    }
    
    // Export endpoints implementation
    
    /**
     * Export bookings
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function export_bookings(WP_REST_Request $request) {
        try {
            $format = sanitize_text_field($request->get_param('format') ?: 'csv');
            $date_from = sanitize_text_field($request->get_param('date_from') ?: '');
            $date_to = sanitize_text_field($request->get_param('date_to') ?: '');
            $status = sanitize_text_field($request->get_param('status') ?: '');
            $event_id = intval($request->get_param('event_id') ?: 0);
            
            if (!in_array($format, ['csv', 'json'])) {
                return new WP_Error('invalid_format', __('Invalid export format', 'wceventsfp'), ['status' => 400]);
            }
            
            global $wpdb;
            $where_conditions = ['1=1'];
            $params = [];
            
            if ($date_from) {
                $where_conditions[] = 'data_evento >= %s';
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where_conditions[] = 'data_evento <= %s';
                $params[] = $date_to;
            }
            
            if ($status) {
                $where_conditions[] = 'stato = %s';
                $params[] = $status;
            }
            
            if ($event_id) {
                $where_conditions[] = 'product_id = %d';
                $params[] = $event_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "
                SELECT 
                    o.id,
                    o.product_id,
                    p.post_title as event_title,
                    o.data_evento,
                    o.ora_evento,
                    o.nome,
                    o.email,
                    o.telefono,
                    o.adults,
                    o.children,
                    o.stato,
                    o.created_at,
                    o.prezzo_totale,
                    o.meetingpoint,
                    o.note
                FROM {$wpdb->prefix}wcefp_occorrenze o
                LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
                WHERE {$where_clause}
                ORDER BY o.data_evento DESC, o.created_at DESC
            ";
            
            $bookings = empty($params) 
                ? $wpdb->get_results($query) 
                : $wpdb->get_results($wpdb->prepare($query, $params));
            
            if (empty($bookings)) {
                return new WP_Error('no_data', __('No bookings found for export', 'wceventsfp'), ['status' => 404]);
            }
            
            if ($format === 'csv') {
                $content = $this->generate_bookings_csv($bookings);
                $filename = 'wcefp-bookings-' . date('Y-m-d-H-i-s') . '.csv';
                $content_type = 'text/csv';
            } else {
                $formatted_bookings = array_map([$this, 'format_booking_for_export'], $bookings);
                $content = wp_json_encode($formatted_bookings, JSON_PRETTY_PRINT);
                $filename = 'wcefp-bookings-' . date('Y-m-d-H-i-s') . '.json';
                $content_type = 'application/json';
            }
            
            DiagnosticLogger::instance()->log_integration('info', 'Bookings exported via API', 'export', [
                'format' => $format,
                'count' => count($bookings),
                'filters' => compact('date_from', 'date_to', 'status', 'event_id')
            ]);
            
            return new WP_REST_Response([
                'filename' => $filename,
                'content_type' => $content_type,
                'content' => base64_encode($content),
                'size' => strlen($content),
                'count' => count($bookings)
            ]);
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('REST API error: export_bookings', [
                'error' => $e->getMessage(),
                'request_params' => $request->get_params()
            ]);
            
            return new WP_Error('export_error', __('Unable to export bookings', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Export calendar
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function export_calendar(WP_REST_Request $request) {
        try {
            $format = sanitize_text_field($request->get_param('format') ?: 'ics');
            $event_id = intval($request->get_param('event_id') ?: 0);
            $date_from = sanitize_text_field($request->get_param('date_from') ?: date('Y-m-d'));
            $date_to = sanitize_text_field($request->get_param('date_to') ?: date('Y-m-d', strtotime('+6 months')));
            
            if (!in_array($format, ['ics', 'json'])) {
                return new WP_Error('invalid_format', __('Invalid calendar format', 'wceventsfp'), ['status' => 400]);
            }
            
            global $wpdb;
            $where_conditions = ['stato IN ("confirmed", "pending")', 'data_evento >= %s', 'data_evento <= %s'];
            $params = [$date_from, $date_to];
            
            if ($event_id) {
                $where_conditions[] = 'product_id = %d';
                $params[] = $event_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "
                SELECT 
                    o.product_id,
                    p.post_title as event_title,
                    p.post_content as event_description,
                    o.data_evento,
                    o.ora_evento,
                    o.meetingpoint,
                    COUNT(o.id) as booking_count,
                    SUM(o.adults + o.children) as participant_count,
                    MIN(o.created_at) as first_booking
                FROM {$wpdb->prefix}wcefp_occorrenze o
                LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
                WHERE {$where_clause}
                GROUP BY o.product_id, o.data_evento, o.ora_evento
                ORDER BY o.data_evento ASC, o.ora_evento ASC
            ";
            
            $events = $wpdb->get_results($wpdb->prepare($query, $params));
            
            if (empty($events)) {
                return new WP_Error('no_events', __('No events found for calendar export', 'wceventsfp'), ['status' => 404]);
            }
            
            if ($format === 'ics') {
                $content = $this->generate_ics_calendar($events);
                $filename = 'wcefp-calendar-' . date('Y-m-d-H-i-s') . '.ics';
                $content_type = 'text/calendar';
            } else {
                $formatted_events = array_map([$this, 'format_event_for_export'], $events);
                $content = wp_json_encode($formatted_events, JSON_PRETTY_PRINT);
                $filename = 'wcefp-calendar-' . date('Y-m-d-H-i-s') . '.json';
                $content_type = 'application/json';
            }
            
            DiagnosticLogger::instance()->log_integration('info', 'Calendar exported via API', 'export', [
                'format' => $format,
                'count' => count($events),
                'event_id' => $event_id,
                'date_range' => [$date_from, $date_to]
            ]);
            
            return new WP_REST_Response([
                'filename' => $filename,
                'content_type' => $content_type,
                'content' => base64_encode($content),
                'size' => strlen($content),
                'count' => count($events)
            ]);
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('REST API error: export_calendar', [
                'error' => $e->getMessage(),
                'request_params' => $request->get_params()
            ]);
            
            return new WP_Error('export_error', __('Unable to export calendar', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    // System endpoints implementation
    
    /**
     * Get system status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_system_status(WP_REST_Request $request) {
        global $wpdb;
        
        // Basic system info
        $status = [
            'plugin_version' => WCEFP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'server_time' => current_time('c'),
            'timezone' => wp_timezone_string(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
        
        // Database status
        $bookings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_bookings");
        $events_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'product' 
             AND p.post_status = 'publish' 
             AND pm.meta_key = '_wcefp_is_event' 
             AND pm.meta_value = '1'"
        );
        
        $status['database'] = [
            'bookings_count' => intval($bookings_count),
            'events_count' => intval($events_count),
            'tables_exist' => $this->check_database_tables()
        ];
        
        // Plugin dependencies
        $status['dependencies'] = [
            'woocommerce_active' => class_exists('WooCommerce'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null
        ];
        
        // Settings status
        $status['settings'] = [
            'default_capacity' => get_option('wcefp_default_capacity', 10),
            'booking_window_days' => get_option('wcefp_booking_window_days', 30),
            'email_notifications' => get_option('wcefp_email_notifications', false)
        ];
        
        return new WP_REST_Response($status);
    }
    
    /**
     * Get system health
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_system_health(WP_REST_Request $request) {
        $health_checks = [
            'database' => $this->check_database_health(),
            'file_permissions' => $this->check_file_permissions(),
            'memory_usage' => $this->check_memory_usage(),
            'external_connections' => $this->check_external_connections(),
            'cron_jobs' => $this->check_cron_status()
        ];
        
        $overall_status = 'good';
        foreach ($health_checks as $check) {
            if ($check['status'] === 'critical') {
                $overall_status = 'critical';
                break;
            } elseif ($check['status'] === 'warning' && $overall_status !== 'critical') {
                $overall_status = 'warning';
            }
        }
        
        return new WP_REST_Response([
            'overall_status' => $overall_status,
            'checks' => $health_checks,
            'checked_at' => current_time('c')
        ]);
    }
    
    // Health check helper methods
    
    /**
     * Check database tables existence
     */
    private function check_database_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'wcefp_occurrenze',
            $wpdb->prefix . 'wcefp_bookings',
            $wpdb->prefix . 'wcefp_vouchers',
            $wpdb->prefix . 'wcefp_closures'
        ];
        
        $existing_tables = [];
        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            $existing_tables[$table] = ($result === $table);
        }
        
        return $existing_tables;
    }
    
    /**
     * Check database health
     */
    private function check_database_health() {
        global $wpdb;
        
        $tables_status = $this->check_database_tables();
        $missing_tables = array_filter($tables_status, function($exists) { return !$exists; });
        
        // Check database connection
        $connection_test = $wpdb->get_var("SELECT 1");
        
        return [
            'status' => empty($missing_tables) && $connection_test === '1' ? 'good' : 'critical',
            'message' => empty($missing_tables) 
                ? __('All database tables exist', 'wceventsfp')
                : sprintf(__('Missing tables: %s', 'wceventsfp'), implode(', ', array_keys($missing_tables))),
            'details' => [
                'connection' => $connection_test === '1',
                'tables' => $tables_status
            ]
        ];
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        $critical_paths = [
            WP_CONTENT_DIR => 'wp-content directory',
            WP_CONTENT_DIR . '/uploads' => 'uploads directory',
            WCEFP_PLUGIN_DIR => 'plugin directory'
        ];
        
        $issues = [];
        foreach ($critical_paths as $path => $description) {
            if (!is_dir($path)) {
                $issues[] = sprintf(__('%s does not exist', 'wceventsfp'), $description);
            } elseif (!is_writable($path)) {
                $issues[] = sprintf(__('%s is not writable', 'wceventsfp'), $description);
            }
        }
        
        return [
            'status' => empty($issues) ? 'good' : 'warning',
            'message' => empty($issues) 
                ? __('File permissions are correct', 'wceventsfp')
                : __('Some permission issues found', 'wceventsfp'),
            'issues' => $issues
        ];
    }
    
    /**
     * Check memory usage
     */
    private function check_memory_usage() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $current_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        
        $usage_percentage = ($current_usage / $memory_limit) * 100;
        $peak_percentage = ($peak_usage / $memory_limit) * 100;
        
        $status = 'good';
        if ($peak_percentage > 90) {
            $status = 'critical';
        } elseif ($peak_percentage > 75) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'message' => sprintf(__('Memory usage: %s%% of %s', 'wceventsfp'), 
                round($usage_percentage, 2), 
                size_format($memory_limit)
            ),
            'details' => [
                'current_usage' => $current_usage,
                'peak_usage' => $peak_usage,
                'memory_limit' => $memory_limit,
                'current_percentage' => round($usage_percentage, 2),
                'peak_percentage' => round($peak_percentage, 2)
            ]
        ];
    }
    
    /**
     * Check external connections
     */
    private function check_external_connections() {
        $external_services = [
            'google_maps' => 'https://maps.googleapis.com',
            'brevo' => 'https://api.brevo.com',
            'wordpress_org' => 'https://api.wordpress.org'
        ];
        
        $connection_results = [];
        foreach ($external_services as $service => $url) {
            $response = wp_remote_get($url, ['timeout' => 10]);
            $connection_results[$service] = !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
        }
        
        $successful_connections = count(array_filter($connection_results));
        $total_connections = count($connection_results);
        
        return [
            'status' => $successful_connections === $total_connections ? 'good' : 'warning',
            'message' => sprintf(__('%d of %d external connections successful', 'wceventsfp'), 
                $successful_connections, $total_connections
            ),
            'connections' => $connection_results
        ];
    }
    
    /**
     * Check cron status
     */
    private function check_cron_status() {
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $scheduled_events = _get_cron_array();
        $wcefp_events = 0;
        
        if ($scheduled_events) {
            foreach ($scheduled_events as $timestamp => $hooks) {
                foreach ($hooks as $hook => $events) {
                    if (strpos($hook, 'wcefp_') === 0) {
                        $wcefp_events += count($events);
                    }
                }
            }
        }
        
        return [
            'status' => $cron_disabled ? 'warning' : 'good',
            'message' => $cron_disabled 
                ? __('WP Cron is disabled', 'wceventsfp')
                : sprintf(__('%d WCEFP scheduled events', 'wceventsfp'), $wcefp_events),
            'details' => [
                'cron_disabled' => $cron_disabled,
                'wcefp_scheduled_events' => $wcefp_events,
                'total_scheduled_events' => $scheduled_events ? count($scheduled_events) : 0
            ]
        ];
    }
    
    // Helper methods for exports
    
    /**
     * Format booking for API response
     */
    private function format_booking_for_api($booking) {
        return [
            'id' => intval($booking->id),
            'product_id' => intval($booking->product_id),
            'customer_name' => $booking->customer_name ?? $booking->nome ?? '',
            'customer_email' => $booking->customer_email ?? $booking->email ?? '',
            'customer_phone' => $booking->customer_phone ?? $booking->telefono ?? '',
            'booking_date' => $booking->booking_date ?? $booking->data_evento ?? '',
            'booking_time' => $booking->booking_time ?? $booking->ora_evento ?? '',
            'participants' => intval($booking->participants ?? ($booking->adults ?? 0) + ($booking->children ?? 0)),
            'adults' => intval($booking->adults ?? 0),
            'children' => intval($booking->children ?? 0),
            'unit_price' => floatval($booking->unit_price ?? $booking->prezzo_totale ?? 0),
            'total_price' => floatval($booking->total_price ?? $booking->prezzo_totale ?? 0),
            'status' => $booking->status ?? $booking->stato ?? 'pending',
            'special_requests' => $booking->special_requests ?? $booking->note ?? '',
            'meeting_point' => $booking->meeting_point ?? $booking->meetingpoint ?? '',
            'created_at' => $booking->created_at ?? '',
            'updated_at' => $booking->updated_at ?? ''
        ];
    }
    
    /**
     * Format event for API response
     */
    private function format_event_for_api($event, $detailed = false) {
        $product = wc_get_product($event->ID);
        
        $event_data = [
            'id' => intval($event->ID),
            'title' => $event->post_title,
            'slug' => $event->post_name,
            'status' => $event->post_status,
            'date_created' => $event->post_date,
            'date_modified' => $event->post_modified,
            'featured_image' => get_the_post_thumbnail_url($event->ID, 'large'),
            'price' => $product ? floatval($product->get_price()) : 0,
            'regular_price' => $product ? floatval($product->get_regular_price()) : 0,
            'sale_price' => $product ? floatval($product->get_sale_price()) : 0,
            'in_stock' => $product ? $product->is_in_stock() : false,
            'categories' => wp_get_post_terms($event->ID, 'product_cat', ['fields' => 'names']),
            'event_meta' => [
                'capacity' => intval(get_post_meta($event->ID, '_wcefp_capacity', true) ?: 10),
                'duration' => intval(get_post_meta($event->ID, '_wcefp_duration', true) ?: 120),
                'location' => get_post_meta($event->ID, '_wcefp_location', true),
                'meeting_point' => get_post_meta($event->ID, '_wcefp_meeting_point', true),
                'difficulty_level' => get_post_meta($event->ID, '_wcefp_difficulty_level', true),
                'age_restrictions' => get_post_meta($event->ID, '_wcefp_age_restrictions', true)
            ]
        ];
        
        if ($detailed) {
            $event_data['description'] = $event->post_content;
            $event_data['excerpt'] = $event->post_excerpt;
            $event_data['gallery'] = $this->get_event_gallery($event->ID);
            $event_data['available_dates'] = $this->get_event_available_dates($event->ID);
        }
        
        return $event_data;
    }
    
    /**
     * Get event gallery images
     */
    private function get_event_gallery($event_id) {
        $gallery_ids = get_post_meta($event_id, '_product_image_gallery', true);
        
        if (empty($gallery_ids)) {
            return [];
        }
        
        $gallery_ids = explode(',', $gallery_ids);
        $gallery = [];
        
        foreach ($gallery_ids as $attachment_id) {
            $attachment_id = intval(trim($attachment_id));
            if ($attachment_id) {
                $gallery[] = [
                    'id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                    'medium' => wp_get_attachment_image_url($attachment_id, 'medium'),
                    'large' => wp_get_attachment_image_url($attachment_id, 'large'),
                    'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
                ];
            }
        }
        
        return $gallery;
    }
    
    /**
     * Get event available dates
     */
    private function get_event_available_dates($event_id, $limit = 30) {
        global $wpdb;
        
        $query = "
            SELECT DISTINCT data_evento, ora_evento, COUNT(*) as booking_count
            FROM {$wpdb->prefix}wcefp_occorrenze 
            WHERE product_id = %d 
            AND data_evento >= %s 
            AND stato IN ('confirmed', 'pending')
            GROUP BY data_evento, ora_evento
            ORDER BY data_evento ASC, ora_evento ASC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, [$event_id, date('Y-m-d'), $limit]));
        
        $capacity = intval(get_post_meta($event_id, '_wcefp_capacity', true) ?: 10);
        $available_dates = [];
        
        foreach ($results as $result) {
            $available_dates[] = [
                'date' => $result->data_evento,
                'time' => $result->ora_evento,
                'datetime' => $result->data_evento . ' ' . $result->ora_evento,
                'booking_count' => intval($result->booking_count),
                'capacity' => $capacity,
                'available_spots' => max(0, $capacity - intval($result->booking_count)),
                'is_available' => ($capacity - intval($result->booking_count)) > 0
            ];
        }
        
        return $available_dates;
    }
    
    /**
     * Generate CSV content from booking results
     */
    private function generate_bookings_csv($bookings) {
        $csv_lines = [];
        
        // Add UTF-8 BOM for proper encoding in Excel
        $csv_lines[] = "\xEF\xBB\xBF";
        
        // Headers
        $headers = [
            __('ID', 'wceventsfp'),
            __('Event', 'wceventsfp'),
            __('Date', 'wceventsfp'),
            __('Time', 'wceventsfp'),
            __('Customer', 'wceventsfp'),
            __('Email', 'wceventsfp'),
            __('Phone', 'wceventsfp'),
            __('Adults', 'wceventsfp'),
            __('Children', 'wceventsfp'),
            __('Status', 'wceventsfp'),
            __('Total', 'wceventsfp'),
            __('Booking Date', 'wceventsfp'),
            __('Meeting Point', 'wceventsfp'),
            __('Notes', 'wceventsfp')
        ];
        
        $csv_lines[] = '"' . implode('","', $headers) . '"';
        
        // Data rows
        foreach ($bookings as $booking) {
            $row = [
                $booking->id,
                $this->escape_csv_field($booking->event_title),
                $booking->data_evento,
                $booking->ora_evento,
                $this->escape_csv_field($booking->nome),
                $booking->email,
                $booking->telefono,
                $booking->adults,
                $booking->children,
                $this->get_status_label($booking->stato),
                $booking->prezzo_totale,
                $booking->created_at,
                $this->escape_csv_field($booking->meetingpoint),
                $this->escape_csv_field($booking->note)
            ];
            
            $csv_lines[] = '"' . implode('","', $row) . '"';
        }
        
        return implode("\n", $csv_lines);
    }
    
    /**
     * Generate ICS calendar content
     */
    private function generate_ics_calendar($events) {
        $ics_lines = [];
        
        // Calendar header
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//WCEventsFP//Event Calendar//EN';
        $ics_lines[] = 'METHOD:PUBLISH';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'X-WR-CALNAME:WCEventsFP Events';
        $ics_lines[] = 'X-WR-TIMEZONE:' . wp_timezone_string();
        
        // Add events
        foreach ($events as $event) {
            $start_datetime = new \DateTime($event->data_evento . ' ' . $event->ora_evento, wp_timezone());
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new \DateInterval('PT2H')); // Default 2 hour duration
            
            $ics_lines[] = 'BEGIN:VEVENT';
            $ics_lines[] = 'UID:wcefp-' . $event->product_id . '-' . $start_datetime->format('Ymd-His') . '@' . parse_url(home_url(), PHP_URL_HOST);
            $ics_lines[] = 'DTSTART:' . $start_datetime->format('Ymd\THis');
            $ics_lines[] = 'DTEND:' . $end_datetime->format('Ymd\THis');
            $ics_lines[] = 'SUMMARY:' . $this->escape_ics_field($event->event_title);
            
            if ($event->event_description) {
                $ics_lines[] = 'DESCRIPTION:' . $this->escape_ics_field(wp_strip_all_tags($event->event_description));
            }
            
            if ($event->meetingpoint) {
                $ics_lines[] = 'LOCATION:' . $this->escape_ics_field($event->meetingpoint);
            }
            
            $ics_lines[] = 'STATUS:CONFIRMED';
            $ics_lines[] = 'CREATED:' . (new \DateTime($event->first_booking, wp_timezone()))->format('Ymd\THis\Z');
            $ics_lines[] = 'LAST-MODIFIED:' . (new \DateTime('now', wp_timezone()))->format('Ymd\THis\Z');
            $ics_lines[] = 'DTSTAMP:' . (new \DateTime('now', wp_timezone()))->format('Ymd\THis\Z');
            
            // Add booking information as notes
            if ($event->booking_count > 0) {
                $notes = sprintf(__('%d bookings, %d participants', 'wceventsfp'), $event->booking_count, $event->participant_count);
                $ics_lines[] = 'X-WCEFP-BOOKINGS:' . $this->escape_ics_field($notes);
            }
            
            $ics_lines[] = 'END:VEVENT';
        }
        
        // Calendar footer
        $ics_lines[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ics_lines);
    }
    
    /**
     * Format booking for export
     */
    private function format_booking_for_export($booking) {
        return [
            'id' => intval($booking->id),
            'event_id' => intval($booking->product_id),
            'event_title' => $booking->event_title,
            'event_date' => $booking->data_evento,
            'event_time' => $booking->ora_evento,
            'customer_name' => $booking->nome,
            'customer_email' => $booking->email,
            'customer_phone' => $booking->telefono,
            'adults' => intval($booking->adults),
            'children' => intval($booking->children),
            'status' => $booking->stato,
            'total_price' => floatval($booking->prezzo_totale),
            'booking_date' => $booking->created_at,
            'meeting_point' => $booking->meetingpoint,
            'notes' => $booking->note
        ];
    }
    
    /**
     * Format event for export
     */
    private function format_event_for_export($event) {
        return [
            'event_id' => intval($event->product_id),
            'title' => $event->event_title,
            'description' => $event->event_description,
            'date' => $event->data_evento,
            'time' => $event->ora_evento,
            'location' => $event->meetingpoint,
            'booking_count' => intval($event->booking_count),
            'participant_count' => intval($event->participant_count),
            'first_booking' => $event->first_booking
        ];
    }
    
    /**
     * Escape CSV field
     */
    private function escape_csv_field($value) {
        if ($value === null || $value === '') {
            return '';
        }
        
        $value = strval($value);
        $value = str_replace('"', '""', $value);
        
        return $value;
    }
    
    /**
     * Escape ICS field
     */
    private function escape_ics_field($value) {
        if ($value === null || $value === '') {
            return '';
        }
        
        $value = strval($value);
        $value = str_replace(["\r\n", "\r", "\n"], "\\n", $value);
        $value = str_replace([";", ",", "\\"], ["\\;", "\\,", "\\\\"], $value);
        
        // Fold long lines (ICS spec: max 75 chars per line)
        if (strlen($value) > 73) {
            $folded = '';
            $chunks = str_split($value, 73);
            $folded .= array_shift($chunks);
            
            foreach ($chunks as $chunk) {
                $folded .= "\r\n " . $chunk;
            }
            
            return $folded;
        }
        
        return $value;
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = [
            'pending' => __('Pending', 'wceventsfp'),
            'confirmed' => __('Confirmed', 'wceventsfp'),
            'cancelled' => __('Cancelled', 'wceventsfp'),
            'completed' => __('Completed', 'wceventsfp')
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    // Enhanced V2 API Methods
    
    /**
     * Add to cart with enhanced functionality
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function add_to_cart_v2(WP_REST_Request $request) {
        try {
            if (!class_exists('WooCommerce') || !WC()->cart) {
                return new WP_Error('wcefp_wc_not_ready', __('WooCommerce not available', 'wceventsfp'), ['status' => 503]);
            }
            
            $product_id = (int) $request['product_id'];
            $occurrence_id = $request['occurrence_id'] ? (int) $request['occurrence_id'] : null;
            $tickets = $request['tickets'];
            $extras = $request['extras'] ?: [];
            
            // Verify product
            $product = wc_get_product($product_id);
            if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
                return new WP_Error('wcefp_invalid_product', __('Invalid product', 'wceventsfp'), ['status' => 400]);
            }
            
            // Add to cart with event data
            $cart_item_data = [
                'wcefp_event_data' => [
                    'occurrence_id' => $occurrence_id,
                    'tickets' => $tickets,
                    'extras' => $extras,
                    'timestamp' => time()
                ]
            ];
            
            $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
            
            if (!$cart_item_key) {
                return new WP_Error('wcefp_cart_failed', __('Unable to add to cart', 'wceventsfp'), ['status' => 500]);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'cart_item_key' => $cart_item_key,
                'message' => __('Added to cart successfully', 'wceventsfp'),
                'cart_total' => WC()->cart->get_cart_total()
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error('wcefp_cart_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Calculate booking price v2
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function calculate_price_v2(WP_REST_Request $request) {
        try {
            $product_id = (int) $request['product_id'];
            $tickets = $request['tickets'];
            $extras = $request['extras'] ?: [];
            $date = $request['date'] ?: date('Y-m-d');
            
            $product = wc_get_product($product_id);
            if (!$product) {
                return new WP_Error('wcefp_invalid_product', __('Product not found', 'wceventsfp'), ['status' => 404]);
            }
            
            $total_price = 0;
            $calculation_details = [
                'tickets' => [],
                'extras' => [],
                'currency' => get_woocommerce_currency()
            ];
            
            // Calculate ticket prices
            foreach ($tickets as $ticket_key => $quantity) {
                if ($quantity > 0) {
                    $base_price = (float) $product->get_regular_price();
                    
                    // Apply ticket-specific price modifications if available
                    $ticket_price = apply_filters('wcefp_ticket_price', $base_price, $ticket_key, $product_id, $date);
                    $line_total = $ticket_price * $quantity;
                    
                    $calculation_details['tickets'][] = [
                        'type' => $ticket_key,
                        'quantity' => $quantity,
                        'unit_price' => $ticket_price,
                        'total' => $line_total
                    ];
                    
                    $total_price += $line_total;
                }
            }
            
            // Calculate extras prices
            foreach ($extras as $extra_key => $quantity) {
                if ($quantity > 0) {
                    $extra_price = apply_filters('wcefp_extra_price', 0, $extra_key, $product_id, $tickets);
                    $line_total = $extra_price * $quantity;
                    
                    $calculation_details['extras'][] = [
                        'type' => $extra_key,
                        'quantity' => $quantity,
                        'unit_price' => $extra_price,
                        'total' => $line_total
                    ];
                    
                    $total_price += $line_total;
                }
            }
            
            $calculation_details['total'] = $total_price;
            
            return new WP_REST_Response($calculation_details);
            
        } catch (\Exception $e) {
            return new WP_Error('wcefp_calculation_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get event tickets v2
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function get_event_tickets_v2(WP_REST_Request $request) {
        try {
            $product_id = (int) $request['id'];
            
            $product = wc_get_product($product_id);
            if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
                return new WP_Error('wcefp_invalid_product', __('Invalid product', 'wceventsfp'), ['status' => 404]);
            }
            
            // Get ticket types from meta or database
            $ticket_types = get_post_meta($product_id, '_wcefp_ticket_types', true) ?: [];
            
            // Default ticket types if none configured
            if (empty($ticket_types)) {
                $ticket_types = [
                    [
                        'ticket_key' => 'adult',
                        'label' => __('Adulto', 'wceventsfp'),
                        'price' => (float) $product->get_regular_price(),
                        'min_quantity' => 0,
                        'max_quantity' => 10,
                        'is_active' => true
                    ]
                ];
                
                // Add child ticket if child price is set
                $child_price = get_post_meta($product_id, '_wcefp_child_price', true);
                if ($child_price && is_numeric($child_price)) {
                    $ticket_types[] = [
                        'ticket_key' => 'child',
                        'label' => __('Bambino', 'wceventsfp'),
                        'price' => (float) $child_price,
                        'min_quantity' => 0,
                        'max_quantity' => 10,
                        'age_max' => 12,
                        'is_active' => true
                    ];
                }
            }
            
            return new WP_REST_Response([
                'product_id' => $product_id,
                'tickets' => $ticket_types
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error('wcefp_tickets_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Get event extras v2
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function get_event_extras_v2(WP_REST_Request $request) {
        try {
            $product_id = (int) $request['id'];
            
            $product = wc_get_product($product_id);
            if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
                return new WP_Error('wcefp_invalid_product', __('Invalid product', 'wceventsfp'), ['status' => 404]);
            }
            
            // Get extras from meta or database
            $extras = get_post_meta($product_id, '_wcefp_extras', true) ?: [];
            
            return new WP_REST_Response([
                'product_id' => $product_id,
                'extras' => $extras
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error('wcefp_extras_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Validate tickets data format
     * 
     * @param mixed $tickets Tickets data
     * @return bool Valid status
     */
    private function validate_tickets_data($tickets) {
        if (!is_array($tickets)) {
            return false;
        }
        
        foreach ($tickets as $key => $quantity) {
            if (!is_string($key) || !is_numeric($quantity) || $quantity < 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate extras data format
     * 
     * @param mixed $extras Extras data
     * @return bool Valid status
     */
    private function validate_extras_data($extras) {
        if (!is_array($extras)) {
            return false;
        }
        
        foreach ($extras as $key => $quantity) {
            if (!is_string($key) || !is_numeric($quantity) || $quantity < 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @return bool Valid status
     */
    private function validate_date_format($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}