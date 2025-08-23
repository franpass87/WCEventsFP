<?php
/**
 * Enhanced REST API Manager
 * 
 * Advanced REST API management with rate limiting, enhanced authentication,
 * comprehensive input validation, and improved error handling for Phase 4.
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\API\RestApiManager;
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
 * Enhanced REST API Manager with advanced features
 */
class EnhancedRestApiManager extends RestApiManager {
    
    /**
     * API version for enhanced features
     */
    const ENHANCED_NAMESPACE = 'wcefp/v2';
    
    /**
     * Rate limiter instance
     * 
     * @var RateLimiter
     */
    private $rate_limiter;
    
    /**
     * Initialize enhanced API features
     */
    public function init() {
        // Initialize rate limiter
        $this->rate_limiter = new RateLimiter();
        
        // Hook into WordPress REST API
        add_action('rest_api_init', [$this, 'register_enhanced_routes'], 20);
        add_action('rest_api_init', [$this, 'register_developer_endpoints']);
        
        // Enhanced authentication and security
        add_filter('rest_authentication_errors', [$this, 'enhanced_authentication'], 20);
        add_filter('rest_pre_dispatch', [$this, 'apply_rate_limiting'], 10, 3);
        add_filter('rest_request_before_callbacks', [$this, 'enhanced_request_validation'], 10, 3);
        
        // API logging and monitoring
        add_action('rest_api_init', [$this, 'setup_api_logging']);
        add_filter('rest_post_dispatch', [$this, 'log_api_response'], 10, 3);
        
        // CORS handling for API clients
        add_action('rest_api_init', [$this, 'setup_cors_headers']);
        
        DiagnosticLogger::instance()->debug('Enhanced REST API Manager initialized', [], 'api_features');
    }
    
    /**
     * Register enhanced v2 API routes
     */
    public function register_enhanced_routes() {
        // Enhanced bookings endpoint with advanced filtering
        register_rest_route(self::ENHANCED_NAMESPACE, '/bookings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_enhanced_bookings'],
                'permission_callback' => [$this, 'check_enhanced_bookings_permission'],
                'args' => $this->get_enhanced_bookings_args()
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_enhanced_booking'],
                'permission_callback' => [$this, 'check_enhanced_booking_create_permission'],
                'args' => $this->get_enhanced_create_booking_args()
            ]
        ]);
        
        // Bulk operations endpoint
        register_rest_route(self::ENHANCED_NAMESPACE, '/bookings/bulk', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulk_booking_operations'],
                'permission_callback' => [$this, 'check_bulk_operations_permission'],
                'args' => $this->get_bulk_operations_args()
            ]
        ]);
        
        // Advanced analytics endpoint
        register_rest_route(self::ENHANCED_NAMESPACE, '/analytics', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_advanced_analytics'],
                'permission_callback' => [$this, 'check_analytics_permission'],
                'args' => $this->get_analytics_args()
            ]
        ]);
        
        // Real-time availability endpoint
        register_rest_route(self::ENHANCED_NAMESPACE, '/availability/(?P<event_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_real_time_availability'],
                'permission_callback' => '__return_true', // Public endpoint
                'args' => [
                    'event_id' => ['validate_callback' => [$this, 'validate_event_id']],
                    'date' => ['required' => false, 'type' => 'string', 'format' => 'date'],
                    'time_slot' => ['required' => false, 'type' => 'string']
                ]
            ]
        ]);
        
        // Enhanced export endpoints
        register_rest_route(self::ENHANCED_NAMESPACE, '/export/(?P<type>[\w-]+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_enhanced_export'],
                'permission_callback' => [$this, 'check_export_permission'],
                'args' => $this->get_export_args()
            ]
        ]);
    }
    
    /**
     * Register developer-specific endpoints
     */
    public function register_developer_endpoints() {
        // API schema endpoint
        register_rest_route(self::ENHANCED_NAMESPACE, '/schema', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_api_schema'],
                'permission_callback' => [$this, 'check_developer_access']
            ]
        ]);
        
        // API rate limit status
        register_rest_route(self::ENHANCED_NAMESPACE, '/rate-limit/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_rate_limit_status'],
                'permission_callback' => [$this, 'check_authenticated']
            ]
        ]);
        
        // API health check with detailed diagnostics
        register_rest_route(self::ENHANCED_NAMESPACE, '/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_enhanced_health_check'],
                'permission_callback' => [$this, 'check_developer_access']
            ]
        ]);
        
        // API usage statistics
        register_rest_route(self::ENHANCED_NAMESPACE, '/usage/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_api_usage_stats'],
                'permission_callback' => [$this, 'check_usage_stats_permission'],
                'args' => [
                    'period' => ['default' => 'day', 'enum' => ['hour', 'day', 'week', 'month']],
                    'breakdown' => ['default' => 'endpoint', 'enum' => ['endpoint', 'user', 'method']]
                ]
            ]
        ]);
    }
    
    /**
     * Enhanced authentication with API keys and JWT
     */
    public function enhanced_authentication($error) {
        // Skip if already authenticated
        if (!is_wp_error($error)) {
            return $error;
        }
        
        // Check for API key authentication
        $api_key = $this->get_api_key_from_request();
        if ($api_key) {
            return $this->authenticate_with_api_key($api_key);
        }
        
        // Check for Bearer token (JWT)
        $bearer_token = $this->get_bearer_token();
        if ($bearer_token) {
            return $this->authenticate_with_bearer_token($bearer_token);
        }
        
        return $error;
    }
    
    /**
     * Get API key from request headers or parameters
     * 
     * @return string|null
     */
    private function get_api_key_from_request() {
        // Check header first
        if (isset($_SERVER['HTTP_X_WCEFP_API_KEY'])) {
            return sanitize_text_field($_SERVER['HTTP_X_WCEFP_API_KEY']);
        }
        
        // Check Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth_header, 'ApiKey ') === 0) {
                return sanitize_text_field(substr($auth_header, 7));
            }
        }
        
        // Check query parameter (least secure, logged for security audit)
        if (isset($_GET['api_key'])) {
            DiagnosticLogger::instance()->warning('API key passed via URL parameter - security risk', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ], 'api_security');
            
            return sanitize_text_field($_GET['api_key']);
        }
        
        return null;
    }
    
    /**
     * Get Bearer token from Authorization header
     * 
     * @return string|null
     */
    private function get_bearer_token() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth_header, 'Bearer ') === 0) {
                return sanitize_text_field(substr($auth_header, 7));
            }
        }
        
        return null;
    }
    
    /**
     * Authenticate user with API key
     * 
     * @param string $api_key
     * @return true|WP_Error
     */
    private function authenticate_with_api_key($api_key) {
        // Get API key from database
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'wcefp_api_key' 
             AND meta_value = %s",
            $api_key
        ));
        
        if (!$user_id) {
            DiagnosticLogger::instance()->warning('Invalid API key authentication attempt', [
                'api_key_preview' => substr($api_key, 0, 8) . '...',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'api_security');
            
            return new WP_Error(
                'invalid_api_key',
                __('Invalid API key', 'wceventsfp'),
                ['status' => 401]
            );
        }
        
        // Check if user has API access capability
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->has_cap('access_wcefp_api')) {
            return new WP_Error(
                'api_access_denied',
                __('API access denied for this user', 'wceventsfp'),
                ['status' => 403]
            );
        }
        
        // Set current user
        wp_set_current_user($user_id);
        
        DiagnosticLogger::instance()->debug('API key authentication successful', [
            'user_id' => $user_id,
            'user_login' => $user->user_login
        ], 'api_auth');
        
        return true;
    }
    
    /**
     * Authenticate with Bearer token (JWT implementation)
     * 
     * @param string $token
     * @return true|WP_Error
     */
    private function authenticate_with_bearer_token($token) {
        // For now, implement simple token validation
        // In production, this would use proper JWT validation
        
        $stored_token = get_option('wcefp_bearer_token_' . hash('sha256', $token));
        
        if (!$stored_token) {
            return new WP_Error(
                'invalid_bearer_token',
                __('Invalid bearer token', 'wceventsfp'),
                ['status' => 401]
            );
        }
        
        // Check if token is expired
        if (isset($stored_token['expires']) && $stored_token['expires'] < time()) {
            delete_option('wcefp_bearer_token_' . hash('sha256', $token));
            return new WP_Error(
                'expired_bearer_token',
                __('Bearer token has expired', 'wceventsfp'),
                ['status' => 401]
            );
        }
        
        wp_set_current_user($stored_token['user_id']);
        return true;
    }
    
    /**
     * Apply rate limiting to API requests
     */
    public function apply_rate_limiting($result, $server, $request) {
        // Skip rate limiting for authenticated administrators
        if (current_user_can('manage_options')) {
            return $result;
        }
        
        // Get client identifier
        $client_id = $this->get_client_identifier();
        
        // Check rate limit
        $rate_limit_result = $this->rate_limiter->check_rate_limit($client_id, $request);
        
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }
        
        // Add rate limit headers to response
        add_filter('rest_post_dispatch', function($response, $server, $request) use ($rate_limit_result) {
            $response->header('X-RateLimit-Limit', $rate_limit_result['limit']);
            $response->header('X-RateLimit-Remaining', $rate_limit_result['remaining']);
            $response->header('X-RateLimit-Reset', $rate_limit_result['reset_time']);
            return $response;
        }, 10, 3);
        
        return $result;
    }
    
    /**
     * Get client identifier for rate limiting
     * 
     * @return string
     */
    private function get_client_identifier() {
        // Prefer user ID for authenticated requests
        if (get_current_user_id()) {
            return 'user_' . get_current_user_id();
        }
        
        // Use API key if present
        $api_key = $this->get_api_key_from_request();
        if ($api_key) {
            return 'api_key_' . hash('sha256', $api_key);
        }
        
        // Fall back to IP address
        return 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    /**
     * Enhanced request validation
     */
    public function enhanced_request_validation($response, $handler, $request) {
        // Validate request size
        $max_request_size = apply_filters('wcefp_api_max_request_size', 1024 * 1024); // 1MB default
        $request_size = strlen($request->get_body());
        
        if ($request_size > $max_request_size) {
            return new WP_Error(
                'request_too_large',
                __('Request entity too large', 'wceventsfp'),
                ['status' => 413]
            );
        }
        
        // Validate content type for POST/PUT/PATCH requests
        $method = $request->get_method();
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $content_type = $request->get_content_type();
            $allowed_types = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];
            
            if ($content_type && !in_array($content_type['value'], $allowed_types)) {
                return new WP_Error(
                    'unsupported_media_type',
                    __('Unsupported media type', 'wceventsfp'),
                    ['status' => 415]
                );
            }
        }
        
        return $response;
    }
    
    /**
     * Setup API request logging
     */
    public function setup_api_logging() {
        add_action('rest_api_init', function() {
            $request = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
            
            if (strpos($request, '/wcefp/') === 0) {
                DiagnosticLogger::instance()->debug('API request started', [
                    'route' => $request,
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'client_id' => $this->get_client_identifier()
                ], 'api_requests');
            }
        });
    }
    
    /**
     * Log API responses
     */
    public function log_api_response($response, $server, $request) {
        $route = $request->get_route();
        
        if (strpos($route, '/wcefp/') === 0) {
            $status = $response instanceof WP_REST_Response ? $response->get_status() : 200;
            $data_size = $response instanceof WP_REST_Response ? strlen(wp_json_encode($response->get_data())) : 0;
            
            DiagnosticLogger::instance()->debug('API response sent', [
                'route' => $route,
                'method' => $request->get_method(),
                'status' => $status,
                'data_size' => $data_size,
                'client_id' => $this->get_client_identifier()
            ], 'api_responses');
            
            // Log errors separately
            if ($status >= 400) {
                DiagnosticLogger::instance()->warning('API error response', [
                    'route' => $route,
                    'method' => $request->get_method(),
                    'status' => $status,
                    'error' => $response instanceof WP_REST_Response ? $response->get_data() : 'Unknown error'
                ], 'api_errors');
            }
        }
        
        return $response;
    }
    
    /**
     * Setup CORS headers for API clients
     */
    public function setup_cors_headers() {
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', [$this, 'send_enhanced_cors_headers']);
        });
    }
    
    /**
     * Send enhanced CORS headers
     */
    public function send_enhanced_cors_headers($value) {
        $allowed_origins = apply_filters('wcefp_api_allowed_origins', []);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins) || current_user_can('manage_options')) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WCEFP-API-Key');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        return $value;
    }
    
    // Enhanced endpoint implementations
    
    /**
     * Get enhanced bookings with advanced filtering
     */
    public function get_enhanced_bookings(WP_REST_Request $request) {
        $start_time = microtime(true);
        
        try {
            $args = [
                'posts_per_page' => min($request->get_param('per_page') ?: 10, 100),
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
            
            // Advanced filtering options
            if ($request->get_param('event_id')) {
                $args['meta_query'][] = [
                    'key' => '_wcefp_event_id',
                    'value' => absint($request->get_param('event_id')),
                    'compare' => '='
                ];
            }
            
            if ($request->get_param('customer_email')) {
                $args['meta_query'][] = [
                    'key' => '_billing_email',
                    'value' => sanitize_email($request->get_param('customer_email')),
                    'compare' => '='
                ];
            }
            
            if ($request->get_param('min_participants')) {
                $args['meta_query'][] = [
                    'key' => '_wcefp_participants',
                    'value' => absint($request->get_param('min_participants')),
                    'compare' => '>='
                ];
            }
            
            // Date range filtering
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
            
            // Sorting options
            $orderby = $request->get_param('orderby') ?: 'date';
            $order = $request->get_param('order') ?: 'desc';
            
            $args['orderby'] = $orderby;
            $args['order'] = $order;
            
            $bookings = get_posts($args);
            $formatted_bookings = [];
            
            foreach ($bookings as $booking) {
                $formatted_booking = $this->format_booking($booking);
                
                // Add enhanced fields
                $formatted_booking['enhanced_data'] = [
                    'payment_method' => get_post_meta($booking->ID, '_payment_method', true),
                    'transaction_id' => get_post_meta($booking->ID, '_transaction_id', true),
                    'booking_source' => get_post_meta($booking->ID, '_wcefp_booking_source', true) ?: 'website',
                    'special_requests' => get_post_meta($booking->ID, '_wcefp_special_requests', true),
                    'check_in_status' => get_post_meta($booking->ID, '_wcefp_check_in_status', true) ?: 'pending',
                    'voucher_code' => get_post_meta($booking->ID, '_wcefp_voucher_code', true),
                ];
                
                $formatted_bookings[] = $formatted_booking;
            }
            
            // Get total count for pagination
            $count_args = $args;
            $count_args['posts_per_page'] = -1;
            $count_args['fields'] = 'ids';
            $total = count(get_posts($count_args));
            $pages = ceil($total / $args['posts_per_page']);
            
            $response = new WP_REST_Response($formatted_bookings);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', $pages);
            
            // Performance metrics
            $execution_time = microtime(true) - $start_time;
            $response->header('X-Execution-Time', round($execution_time * 1000, 2) . 'ms');
            
            return $response;
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('Enhanced API Error: Get bookings failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->get_params()
            ], 'api_errors');
            
            return new WP_Error('api_error', __('Failed to retrieve bookings', 'wceventsfp'), ['status' => 500]);
        }
    }
    
    /**
     * Get API schema for documentation
     */
    public function get_api_schema(WP_REST_Request $request) {
        $schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'WCEventsFP API',
                'description' => 'Enhanced REST API for WCEventsFP booking platform',
                'version' => '2.0.0',
                'contact' => [
                    'name' => 'WCEventsFP Support',
                    'email' => 'support@wceventsfp.com'
                ]
            ],
            'servers' => [
                [
                    'url' => rest_url('wcefp/v2/'),
                    'description' => 'Enhanced API v2'
                ],
                [
                    'url' => rest_url('wcefp/v1/'),
                    'description' => 'Legacy API v1'
                ]
            ],
            'paths' => [
                '/bookings' => [
                    'get' => [
                        'summary' => 'Get bookings with advanced filtering',
                        'parameters' => [
                            [
                                'name' => 'per_page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10]
                            ],
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                            ],
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'enum' => ['pending', 'processing', 'completed', 'cancelled']]
                            ],
                            [
                                'name' => 'event_id',
                                'in' => 'query',
                                'schema' => ['type' => 'integer']
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Successful response with booking data'],
                            '401' => ['description' => 'Authentication required'],
                            '403' => ['description' => 'Insufficient permissions'],
                            '429' => ['description' => 'Rate limit exceeded']
                        ]
                    ]
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKey' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-WCEFP-API-Key'
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer'
                    ]
                ]
            ]
        ];
        
        return $schema;
    }
    
    // Permission callbacks for enhanced endpoints
    
    public function check_enhanced_bookings_permission() {
        return RolesCapabilities::current_user_can('view_wcefp_bookings');
    }
    
    public function check_enhanced_booking_create_permission() {
        return RolesCapabilities::current_user_can('edit_wcefp_bookings');
    }
    
    public function check_bulk_operations_permission() {
        return RolesCapabilities::current_user_can('manage_wcefp_bookings');
    }
    
    public function check_analytics_permission() {
        return RolesCapabilities::current_user_can('view_wcefp_analytics');
    }
    
    public function check_export_permission() {
        return RolesCapabilities::current_user_can('export_wcefp_data');
    }
    
    public function check_developer_access() {
        return RolesCapabilities::current_user_can('use_wcefp_dev_tools') || current_user_can('manage_options');
    }
    
    public function check_authenticated() {
        return get_current_user_id() > 0;
    }
    
    public function check_usage_stats_permission() {
        return RolesCapabilities::current_user_can('view_wcefp_api_logs') || current_user_can('manage_options');
    }
    
    // Argument definitions for enhanced endpoints
    
    private function get_enhanced_bookings_args() {
        $base_args = parent::get_bookings_args();
        
        return array_merge($base_args, [
            'event_id' => [
                'description' => __('Filter by specific event ID', 'wceventsfp'),
                'type' => 'integer'
            ],
            'customer_email' => [
                'description' => __('Filter by customer email', 'wceventsfp'),
                'type' => 'string',
                'format' => 'email'
            ],
            'min_participants' => [
                'description' => __('Filter by minimum number of participants', 'wceventsfp'),
                'type' => 'integer',
                'minimum' => 1
            ],
            'orderby' => [
                'description' => __('Order results by field', 'wceventsfp'),
                'type' => 'string',
                'enum' => ['date', 'modified', 'title', 'menu_order'],
                'default' => 'date'
            ],
            'order' => [
                'description' => __('Order direction', 'wceventsfp'),
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'desc'
            ]
        ]);
    }
    
    private function get_enhanced_create_booking_args() {
        $base_args = parent::get_create_booking_args();
        
        return array_merge($base_args, [
            'booking_source' => [
                'description' => __('Source of the booking (website, api, phone, etc.)', 'wceventsfp'),
                'type' => 'string',
                'default' => 'api'
            ],
            'special_requests' => [
                'description' => __('Special requests or notes for the booking', 'wceventsfp'),
                'type' => 'string'
            ],
            'voucher_code' => [
                'description' => __('Voucher code to apply', 'wceventsfp'),
                'type' => 'string'
            ],
            'send_confirmation' => [
                'description' => __('Whether to send confirmation email', 'wceventsfp'),
                'type' => 'boolean',
                'default' => true
            ]
        ]);
    }
}