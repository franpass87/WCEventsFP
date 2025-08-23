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
    }
    
    /**
     * Register custom fields for existing endpoints
     */
    public function register_fields() {
        // Add WCEFP fields to WooCommerce products
        register_rest_field('product', 'wcefp_event_data', [
            'get_callback' => [$this, 'get_event_data'],
            'update_callback' => [$this, 'update_event_data'],
            'schema' => $this->get_event_data_schema()
        ]);
        
        // Add WCEFP fields to WooCommerce orders
        register_rest_field('shop_order', 'wcefp_booking_data', [
            'get_callback' => [$this, 'get_booking_data'],
            'update_callback' => [$this, 'update_booking_data'],
            'schema' => $this->get_booking_data_schema()
        ]);
    }
    
    /**
     * Get bookings
     */
    public function get_bookings(WP_REST_Request $request) {
        try {
            $logger = DiagnosticLogger::instance();
            $start_time = microtime(true);
            
            $args = [
                'posts_per_page' => $request->get_param('per_page') ?: 10,
                'paged' => $request->get_param('page') ?: 1,
                'post_type' => 'shop_order',
                'post_status' => $request->get_param('status') ?: 'any',
                'meta_query' => [
                    [
                        'key' => '_wcefp_is_booking',
                        'value' => '1',
                        'compare' => '='
                    ]
                ]
            ];
            
            // Add date filtering
            if ($request->get_param('date_from') || $request->get_param('date_to')) {
                $date_query = [];
                
                if ($request->get_param('date_from')) {
                    $date_query['after'] = $request->get_param('date_from');
                }
                
                if ($request->get_param('date_to')) {
                    $date_query['before'] = $request->get_param('date_to');
                }
                
                $args['date_query'] = [$date_query];
            }
            
            $bookings = get_posts($args);
            $formatted_bookings = [];
            
            foreach ($bookings as $booking) {
                $formatted_bookings[] = $this->format_booking($booking);
            }
            
            $total = wp_count_posts('shop_order')->publish;
            $pages = ceil($total / $args['posts_per_page']);
            
            $response = new WP_REST_Response($formatted_bookings);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', $pages);
            
            $logger->log_performance('API: Get bookings completed', $start_time, [
                'count' => count($formatted_bookings),
                'total' => $total
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('API Error: Get bookings failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], DiagnosticLogger::CHANNEL_INTEGRATIONS);
            
            return new WP_Error('api_error', __('Failed to retrieve bookings', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Get single booking
     */
    public function get_booking(WP_REST_Request $request) {
        $booking_id = $request->get_param('id');
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'shop_order') {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        // Check if it's actually a booking
        if (!get_post_meta($booking_id, '_wcefp_is_booking', true)) {
            return new WP_Error('not_booking', __('Order is not a booking', 'wceventsfp'), ['status' => 400]);
        }
        
        return $this->format_booking($booking);
    }
    
    /**
     * Create booking
     */
    public function create_booking(WP_REST_Request $request) {
        try {
            $logger = DiagnosticLogger::instance();
            $logger->log_booking('info', 'API: Creating new booking', null, [
                'user_id' => get_current_user_id(),
                'request_data' => $request->get_json_params()
            ]);
            
            // Validate required fields
            $required_fields = ['event_id', 'customer_email', 'booking_date', 'participants'];
            foreach ($required_fields as $field) {
                if (empty($request->get_param($field))) {
                    return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'wceventsfp'), $field), ['status' => 400]);
                }
            }
            
            // Create WooCommerce order
            $order = wc_create_order();
            if (is_wp_error($order)) {
                return new WP_Error('order_creation_failed', __('Failed to create order', 'wceventsfp'), ['status' => 500]);
            }
            
            // Add booking-specific meta
            $order->update_meta_data('_wcefp_is_booking', '1');
            $order->update_meta_data('_wcefp_event_id', absint($request->get_param('event_id')));
            $order->update_meta_data('_wcefp_booking_date', sanitize_text_field($request->get_param('booking_date')));
            $order->update_meta_data('_wcefp_participants', absint($request->get_param('participants')));
            
            // Set customer information
            $order->set_billing_email($request->get_param('customer_email'));
            if ($request->get_param('customer_name')) {
                $names = explode(' ', $request->get_param('customer_name'), 2);
                $order->set_billing_first_name($names[0]);
                if (isset($names[1])) {
                    $order->set_billing_last_name($names[1]);
                }
            }
            
            $order->save();
            
            $logger->log_booking('info', 'API: Booking created successfully', $order->get_id());
            
            return $this->format_booking(get_post($order->get_id()));
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('API Error: Create booking failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], DiagnosticLogger::CHANNEL_BOOKINGS);
            
            return new WP_Error('booking_creation_failed', __('Failed to create booking', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Get events
     */
    public function get_events(WP_REST_Request $request) {
        try {
            $args = [
                'posts_per_page' => $request->get_param('per_page') ?: 10,
                'paged' => $request->get_param('page') ?: 1,
                'post_type' => 'product',
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_wcefp_is_event',
                        'value' => '1',
                        'compare' => '='
                    ]
                ]
            ];
            
            // Add category filtering
            if ($request->get_param('category')) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => $request->get_param('category')
                    ]
                ];
            }
            
            $events = get_posts($args);
            $formatted_events = [];
            
            foreach ($events as $event) {
                $formatted_events[] = $this->format_event($event);
            }
            
            return $formatted_events;
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('API Error: Get events failed', [
                'error' => $e->getMessage()
            ], DiagnosticLogger::CHANNEL_INTEGRATIONS);
            
            return new WP_Error('api_error', __('Failed to retrieve events', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Get system status
     */
    public function get_system_status(WP_REST_Request $request) {
        $logger = DiagnosticLogger::instance();
        $diagnostics = $logger->get_system_diagnostics();
        
        // Remove sensitive information
        unset($diagnostics['recent_errors']);
        if (isset($diagnostics['plugin_settings']['log_directory'])) {
            $diagnostics['plugin_settings']['log_directory'] = '***';
        }
        
        return $diagnostics;
    }
    
    /**
     * Get system health
     */
    public function get_system_health(WP_REST_Request $request) {
        $logger = DiagnosticLogger::instance();
        $diagnostics = $logger->get_system_diagnostics();
        
        $health_checks = [
            'php_version' => version_compare($diagnostics['server']['php_version'], '7.4.0', '>='),
            'woocommerce_active' => $diagnostics['woocommerce']['active'],
            'log_directory_writable' => $diagnostics['plugin_settings']['log_directory_writable'],
            'onboarding_completed' => $diagnostics['plugin_settings']['onboarding_completed']
        ];
        
        $passed = count(array_filter($health_checks));
        $total = count($health_checks);
        
        return [
            'status' => $passed === $total ? 'healthy' : ($passed >= $total * 0.75 ? 'warning' : 'critical'),
            'checks' => $health_checks,
            'score' => round(($passed / $total) * 100),
            'timestamp' => current_time('c')
        ];
    }
    
    // Permission callback methods
    
    public function check_bookings_permission() {
        return RolesCapabilities::current_user_can('view_wcefp_bookings');
    }
    
    public function check_booking_permission(WP_REST_Request $request) {
        return RolesCapabilities::current_user_can('view_wcefp_bookings');
    }
    
    public function check_booking_create_permission() {
        return RolesCapabilities::current_user_can('edit_wcefp_bookings');
    }
    
    public function check_booking_edit_permission(WP_REST_Request $request) {
        return RolesCapabilities::current_user_can('edit_wcefp_bookings');
    }
    
    public function check_booking_delete_permission(WP_REST_Request $request) {
        return RolesCapabilities::current_user_can('delete_wcefp_bookings');
    }
    
    public function check_events_permission() {
        return true; // Public endpoint for viewing events
    }
    
    public function check_event_permission(WP_REST_Request $request) {
        return true; // Public endpoint for viewing single event
    }
    
    public function check_system_permission() {
        return RolesCapabilities::current_user_can('manage_wcefp_settings');
    }
    
    public function check_integration_permission() {
        return RolesCapabilities::current_user_can('manage_wcefp_settings');
    }
    
    // Validation methods
    
    public function validate_booking_id($value, $request, $key) {
        return is_numeric($value) && $value > 0;
    }
    
    public function validate_event_id($value, $request, $key) {
        return is_numeric($value) && $value > 0 && get_post($value) && get_post_type($value) === 'product';
    }
    
    public function validate_service_name($value, $request, $key) {
        $allowed_services = ['brevo', 'google-analytics', 'google-reviews', 'meta-pixel'];
        return in_array($value, $allowed_services, true);
    }
    
    // Formatting methods
    
    private function format_booking($booking) {
        $order = wc_get_order($booking->ID);
        
        return [
            'id' => $booking->ID,
            'status' => $booking->post_status,
            'date_created' => $booking->post_date,
            'date_modified' => $booking->post_modified,
            'event_id' => get_post_meta($booking->ID, '_wcefp_event_id', true),
            'booking_date' => get_post_meta($booking->ID, '_wcefp_booking_date', true),
            'participants' => get_post_meta($booking->ID, '_wcefp_participants', true),
            'customer' => [
                'email' => $order ? $order->get_billing_email() : '',
                'first_name' => $order ? $order->get_billing_first_name() : '',
                'last_name' => $order ? $order->get_billing_last_name() : ''
            ],
            'total' => $order ? $order->get_total() : 0,
            'currency' => $order ? $order->get_currency() : get_option('woocommerce_currency')
        ];
    }
    
    private function format_event($event) {
        $product = wc_get_product($event->ID);
        
        return [
            'id' => $event->ID,
            'title' => $event->post_title,
            'description' => $event->post_content,
            'excerpt' => $event->post_excerpt,
            'status' => $event->post_status,
            'featured_image' => get_the_post_thumbnail_url($event->ID, 'large'),
            'price' => $product ? $product->get_price() : 0,
            'regular_price' => $product ? $product->get_regular_price() : 0,
            'sale_price' => $product ? $product->get_sale_price() : 0,
            'in_stock' => $product ? $product->is_in_stock() : false,
            'categories' => wp_get_post_terms($event->ID, 'product_cat', ['fields' => 'names']),
            'event_data' => [
                'capacity' => get_post_meta($event->ID, '_wcefp_capacity', true),
                'duration' => get_post_meta($event->ID, '_wcefp_duration', true),
                'location' => get_post_meta($event->ID, '_wcefp_location', true),
                'meeting_point' => get_post_meta($event->ID, '_wcefp_meeting_point', true)
            ]
        ];
    }
    
    // Argument definitions
    
    private function get_bookings_args() {
        return [
            'page' => [
                'description' => __('Current page of the collection.', 'wceventsfp'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'description' => __('Maximum number of items to be returned in result set.', 'wceventsfp'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ],
            'status' => [
                'description' => __('Limit result set to bookings with a specific status.', 'wceventsfp'),
                'type' => 'string',
                'enum' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed']
            ],
            'date_from' => [
                'description' => __('Limit response to bookings created after a given date.', 'wceventsfp'),
                'type' => 'string',
                'format' => 'date'
            ],
            'date_to' => [
                'description' => __('Limit response to bookings created before a given date.', 'wceventsfp'),
                'type' => 'string',
                'format' => 'date'
            ]
        ];
    }
    
    private function get_create_booking_args() {
        return [
            'event_id' => [
                'description' => __('The ID of the event being booked.', 'wceventsfp'),
                'type' => 'integer',
                'required' => true
            ],
            'customer_email' => [
                'description' => __('Customer email address.', 'wceventsfp'),
                'type' => 'string',
                'format' => 'email',
                'required' => true
            ],
            'customer_name' => [
                'description' => __('Customer full name.', 'wceventsfp'),
                'type' => 'string'
            ],
            'booking_date' => [
                'description' => __('Date of the event booking.', 'wceventsfp'),
                'type' => 'string',
                'format' => 'date',
                'required' => true
            ],
            'participants' => [
                'description' => __('Number of participants.', 'wceventsfp'),
                'type' => 'integer',
                'minimum' => 1,
                'required' => true
            ]
        ];
    }
    
    private function get_update_booking_args() {
        return $this->get_create_booking_args(); // Same fields for update
    }
    
    private function get_events_args() {
        return [
            'page' => [
                'description' => __('Current page of the collection.', 'wceventsfp'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'description' => __('Maximum number of items to be returned in result set.', 'wceventsfp'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ],
            'category' => [
                'description' => __('Limit result set to events in a specific category.', 'wceventsfp'),
                'type' => 'string'
            ]
        ];
    }
    
    private function get_webhook_args() {
        return [
            'secret' => [
                'description' => __('Webhook secret for verification.', 'wceventsfp'),
                'type' => 'string',
                'required' => true
            ]
        ];
    }
    
    // Schema definitions for custom fields
    
    private function get_event_data_schema() {
        return [
            'description' => __('WCEFP event-specific data', 'wceventsfp'),
            'type' => 'object',
            'context' => ['view', 'edit'],
            'properties' => [
                'is_event' => [
                    'description' => __('Whether this product is an event', 'wceventsfp'),
                    'type' => 'boolean'
                ],
                'capacity' => [
                    'description' => __('Maximum number of participants', 'wceventsfp'),
                    'type' => 'integer'
                ],
                'duration' => [
                    'description' => __('Event duration in minutes', 'wceventsfp'),
                    'type' => 'integer'
                ],
                'location' => [
                    'description' => __('Event location', 'wceventsfp'),
                    'type' => 'string'
                ]
            ]
        ];
    }
    
    private function get_booking_data_schema() {
        return [
            'description' => __('WCEFP booking-specific data', 'wceventsfp'),
            'type' => 'object',
            'context' => ['view', 'edit'],
            'properties' => [
                'is_booking' => [
                    'description' => __('Whether this order is a booking', 'wceventsfp'),
                    'type' => 'boolean'
                ],
                'event_id' => [
                    'description' => __('ID of the booked event', 'wceventsfp'),
                    'type' => 'integer'
                ],
                'booking_date' => [
                    'description' => __('Date of the booking', 'wceventsfp'),
                    'type' => 'string',
                    'format' => 'date'
                ],
                'participants' => [
                    'description' => __('Number of participants', 'wceventsfp'),
                    'type' => 'integer'
                ]
            ]
        ];
    }
    
    // Field callback methods
    
    public function get_event_data($object) {
        return [
            'is_event' => get_post_meta($object['id'], '_wcefp_is_event', true) === '1',
            'capacity' => absint(get_post_meta($object['id'], '_wcefp_capacity', true)),
            'duration' => absint(get_post_meta($object['id'], '_wcefp_duration', true)),
            'location' => get_post_meta($object['id'], '_wcefp_location', true)
        ];
    }
    
    public function update_event_data($value, $object) {
        if (isset($value['is_event'])) {
            update_post_meta($object->ID, '_wcefp_is_event', $value['is_event'] ? '1' : '0');
        }
        
        if (isset($value['capacity'])) {
            update_post_meta($object->ID, '_wcefp_capacity', absint($value['capacity']));
        }
        
        if (isset($value['duration'])) {
            update_post_meta($object->ID, '_wcefp_duration', absint($value['duration']));
        }
        
        if (isset($value['location'])) {
            update_post_meta($object->ID, '_wcefp_location', sanitize_text_field($value['location']));
        }
    }
    
    public function get_booking_data($object) {
        return [
            'is_booking' => get_post_meta($object['id'], '_wcefp_is_booking', true) === '1',
            'event_id' => absint(get_post_meta($object['id'], '_wcefp_event_id', true)),
            'booking_date' => get_post_meta($object['id'], '_wcefp_booking_date', true),
            'participants' => absint(get_post_meta($object['id'], '_wcefp_participants', true))
        ];
    }
    
    public function update_booking_data($value, $object) {
        if (isset($value['is_booking'])) {
            update_post_meta($object->ID, '_wcefp_is_booking', $value['is_booking'] ? '1' : '0');
        }
        
        if (isset($value['event_id'])) {
            update_post_meta($object->ID, '_wcefp_event_id', absint($value['event_id']));
        }
        
        if (isset($value['booking_date'])) {
            update_post_meta($object->ID, '_wcefp_booking_date', sanitize_text_field($value['booking_date']));
        }
        
        if (isset($value['participants'])) {
            update_post_meta($object->ID, '_wcefp_participants', absint($value['participants']));
        }
    }
    
    // Additional endpoint methods
    
    public function update_booking(WP_REST_Request $request) {
        $booking_id = $request->get_param('id');
        $order = wc_get_order($booking_id);
        
        if (!$order) {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        // Update booking fields
        if ($request->get_param('status')) {
            $order->set_status($request->get_param('status'));
        }
        
        if ($request->get_param('participants')) {
            $order->update_meta_data('_wcefp_participants', absint($request->get_param('participants')));
        }
        
        if ($request->get_param('booking_date')) {
            $order->update_meta_data('_wcefp_booking_date', sanitize_text_field($request->get_param('booking_date')));
        }
        
        $order->save();
        
        DiagnosticLogger::instance()->log_booking('info', 'API: Booking updated', $booking_id);
        
        return $this->format_booking(get_post($booking_id));
    }
    
    public function delete_booking(WP_REST_Request $request) {
        $booking_id = $request->get_param('id');
        $order = wc_get_order($booking_id);
        
        if (!$order) {
            return new WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'), ['status' => 404]);
        }
        
        // Set to cancelled status instead of deleting
        $order->set_status('cancelled');
        $order->save();
        
        DiagnosticLogger::instance()->log_booking('info', 'API: Booking cancelled', $booking_id);
        
        return ['deleted' => true, 'id' => $booking_id];
    }
    
    public function get_event(WP_REST_Request $request) {
        $event_id = $request->get_param('id');
        $event = get_post($event_id);
        
        if (!$event || $event->post_type !== 'product') {
            return new WP_Error('event_not_found', __('Event not found', 'wceventsfp'), ['status' => 404]);
        }
        
        return $this->format_event($event);
    }
    
    public function test_integration(WP_REST_Request $request) {
        $service = $request->get_param('service');
        
        // Implementation for testing different integrations
        switch ($service) {
            case 'brevo':
                return $this->test_brevo_integration();
            case 'google-analytics':
                return $this->test_ga_integration();
            case 'google-reviews':
                return $this->test_google_reviews_integration();
            case 'meta-pixel':
                return $this->test_meta_pixel_integration();
            default:
                return new WP_Error('invalid_service', __('Invalid service specified', 'wceventsfp'), ['status' => 400]);
        }
    }
    
    public function webhook_booking_created(WP_REST_Request $request) {
        // Verify webhook secret
        $secret = $request->get_param('secret');
        $stored_secret = get_option('wcefp_webhook_secret');
        
        if (!$stored_secret || !hash_equals($stored_secret, $secret)) {
            return new WP_Error('invalid_secret', __('Invalid webhook secret', 'wceventsfp'), ['status' => 401]);
        }
        
        // Process webhook data
        $data = $request->get_json_params();
        
        DiagnosticLogger::instance()->log_integration('info', 'Webhook received: booking-created', 'external', ['data' => $data]);
        
        do_action('wcefp_webhook_booking_created', $data);
        
        return ['received' => true, 'timestamp' => current_time('c')];
    }
    
    // Integration test methods
    
    private function test_brevo_integration() {
        $api_key = get_option('wcefp_brevo_api_key');
        
        if (!$api_key) {
            return ['status' => 'error', 'message' => __('Brevo API key not configured', 'wceventsfp')];
        }
        
        // Test API connection
        $response = wp_remote_get('https://api.brevo.com/v3/account', [
            'headers' => [
                'api-key' => $api_key,
                'Content-Type' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return ['status' => 'success', 'message' => __('Brevo connection successful', 'wceventsfp')];
        } else {
            return ['status' => 'error', 'message' => sprintf(__('Brevo API error: %d', 'wceventsfp'), $status_code)];
        }
    }
    
    private function test_ga_integration() {
        $ga4_id = get_option('wcefp_ga4_id');
        $gtm_id = get_option('wcefp_gtm_id');
        
        if (!$ga4_id && !$gtm_id) {
            return ['status' => 'error', 'message' => __('Google Analytics not configured', 'wceventsfp')];
        }
        
        return ['status' => 'success', 'message' => __('Google Analytics configuration found', 'wceventsfp')];
    }
    
    private function test_google_reviews_integration() {
        $api_key = get_option('wcefp_google_places_api_key');
        $place_id = get_option('wcefp_google_place_id');
        
        if (!$api_key || !$place_id) {
            return ['status' => 'error', 'message' => __('Google Reviews not fully configured', 'wceventsfp')];
        }
        
        return ['status' => 'success', 'message' => __('Google Reviews configuration found', 'wceventsfp')];
    }
    
    private function test_meta_pixel_integration() {
        $pixel_id = get_option('wcefp_meta_pixel_id');
        
        if (!$pixel_id) {
            return ['status' => 'error', 'message' => __('Meta Pixel ID not configured', 'wceventsfp')];
        }
        
        return ['status' => 'success', 'message' => __('Meta Pixel configuration found', 'wceventsfp')];
    }
    
    // Authentication fallback
    public function authentication_fallback($error) {
        // Allow API key authentication
        if (isset($_GET['api_key']) || isset($_POST['api_key'])) {
            $api_key = $_GET['api_key'] ?? $_POST['api_key'];
            $stored_key = get_option('wcefp_api_key');
            
            if ($stored_key && hash_equals($stored_key, $api_key)) {
                return true;
            }
            
            return new WP_Error('invalid_api_key', __('Invalid API key', 'wceventsfp'), ['status' => 401]);
        }
        
        return $error;
    }
}