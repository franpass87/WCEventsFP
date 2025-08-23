<?php
/**
 * Documentation Manager
 * 
 * Automatic OpenAPI documentation generation and developer resources
 * for the WCEFP REST API system.
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Documentation Manager
 */
class DocumentationManager {
    
    /**
     * Initialize documentation features
     */
    public function init() {
        // Add documentation menu page
        add_action('admin_menu', [$this, 'add_documentation_page']);
        
        // Register public documentation endpoint
        add_action('rest_api_init', [$this, 'register_documentation_endpoints']);
        
        // Generate and cache documentation
        add_action('init', [$this, 'maybe_regenerate_documentation']);
        
        DiagnosticLogger::instance()->debug('Documentation Manager initialized', [], 'api_features');
    }
    
    /**
     * Register documentation endpoints
     */
    public function register_documentation_endpoints() {
        // OpenAPI spec endpoint
        register_rest_route('wcefp/v2', '/docs/openapi', [
            'methods' => 'GET',
            'callback' => [$this, 'get_openapi_spec'],
            'permission_callback' => '__return_true',
        ]);
        
        // Interactive documentation endpoint
        register_rest_route('wcefp/v2', '/docs/interactive', [
            'methods' => 'GET',
            'callback' => [$this, 'get_interactive_docs'],
            'permission_callback' => '__return_true',
        ]);
        
        // Code examples endpoint
        register_rest_route('wcefp/v2', '/docs/examples/(?P<language>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_code_examples'],
            'permission_callback' => '__return_true',
            'args' => [
                'language' => [
                    'validate_callback' => function($param) {
                        return in_array($param, ['php', 'javascript', 'python', 'curl']);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Add documentation admin page
     */
    public function add_documentation_page() {
        add_submenu_page(
            'wcefp-dashboard',
            __('API Documentation', 'wceventsfp'),
            __('API Docs', 'wceventsfp'),
            'view_wcefp_api_docs',
            'wcefp-api-docs',
            [$this, 'render_documentation_page']
        );
    }
    
    /**
     * Render documentation admin page
     */
    public function render_documentation_page() {
        $openapi_spec = $this->generate_openapi_spec();
        $base_url = rest_url('wcefp/v2/');
        
        ?>
        <div class="wrap wcefp-api-docs">
            <h1><?php _e('WCEventsFP API Documentation', 'wceventsfp'); ?></h1>
            
            <div class="api-docs-nav">
                <nav class="nav-tab-wrapper">
                    <a href="#overview" class="nav-tab nav-tab-active"><?php _e('Overview', 'wceventsfp'); ?></a>
                    <a href="#authentication" class="nav-tab"><?php _e('Authentication', 'wceventsfp'); ?></a>
                    <a href="#endpoints" class="nav-tab"><?php _e('Endpoints', 'wceventsfp'); ?></a>
                    <a href="#examples" class="nav-tab"><?php _e('Examples', 'wceventsfp'); ?></a>
                    <a href="#rate-limits" class="nav-tab"><?php _e('Rate Limits', 'wceventsfp'); ?></a>
                </nav>
            </div>
            
            <div class="api-docs-content">
                <!-- Overview Tab -->
                <div id="overview" class="tab-content active">
                    <h2><?php _e('API Overview', 'wceventsfp'); ?></h2>
                    <p><?php _e('The WCEventsFP REST API provides programmatic access to your booking platform data and functionality. This API follows REST conventions and returns JSON responses.', 'wceventsfp'); ?></p>
                    
                    <h3><?php _e('Base URL', 'wceventsfp'); ?></h3>
                    <code class="api-url"><?php echo esc_html($base_url); ?></code>
                    
                    <h3><?php _e('API Versions', 'wceventsfp'); ?></h3>
                    <ul>
                        <li><strong>v1</strong> - Legacy API with basic functionality</li>
                        <li><strong>v2</strong> - Enhanced API with advanced features, rate limiting, and improved security</li>
                    </ul>
                    
                    <h3><?php _e('Response Format', 'wceventsfp'); ?></h3>
                    <pre><code>{
  "data": {...},
  "meta": {
    "timestamp": "2024-01-01T12:00:00Z",
    "version": "2.0.0",
    "request_id": "abc123"
  }
}</code></pre>
                </div>
                
                <!-- Authentication Tab -->
                <div id="authentication" class="tab-content">
                    <h2><?php _e('Authentication', 'wceventsfp'); ?></h2>
                    
                    <h3><?php _e('API Key Authentication', 'wceventsfp'); ?></h3>
                    <p><?php _e('Include your API key in the request header:', 'wceventsfp'); ?></p>
                    <pre><code>X-WCEFP-API-Key: your_api_key_here</code></pre>
                    
                    <h3><?php _e('Bearer Token Authentication', 'wceventsfp'); ?></h3>
                    <p><?php _e('Use Bearer tokens for enhanced security:', 'wceventsfp'); ?></p>
                    <pre><code>Authorization: Bearer your_token_here</code></pre>
                    
                    <h3><?php _e('WordPress User Authentication', 'wceventsfp'); ?></h3>
                    <p><?php _e('Standard WordPress authentication (cookies, nonces) is supported for browser-based requests.', 'wceventsfp'); ?></p>
                    
                    <div class="api-key-generator">
                        <h3><?php _e('Generate API Key', 'wceventsfp'); ?></h3>
                        <button class="button button-primary" id="generate-api-key"><?php _e('Generate New API Key', 'wceventsfp'); ?></button>
                        <div id="api-key-result"></div>
                    </div>
                </div>
                
                <!-- Endpoints Tab -->
                <div id="endpoints" class="tab-content">
                    <h2><?php _e('API Endpoints', 'wceventsfp'); ?></h2>
                    
                    <?php $this->render_endpoints_documentation($openapi_spec); ?>
                </div>
                
                <!-- Examples Tab -->
                <div id="examples" class="tab-content">
                    <h2><?php _e('Code Examples', 'wceventsfp'); ?></h2>
                    
                    <div class="example-language-selector">
                        <button class="button language-btn active" data-language="curl"><?php _e('cURL', 'wceventsfp'); ?></button>
                        <button class="button language-btn" data-language="php"><?php _e('PHP', 'wceventsfp'); ?></button>
                        <button class="button language-btn" data-language="javascript"><?php _e('JavaScript', 'wceventsfp'); ?></button>
                        <button class="button language-btn" data-language="python"><?php _e('Python', 'wceventsfp'); ?></button>
                    </div>
                    
                    <div id="examples-content">
                        <!-- Examples will be loaded dynamically -->
                    </div>
                </div>
                
                <!-- Rate Limits Tab -->
                <div id="rate-limits" class="tab-content">
                    <h2><?php _e('Rate Limits', 'wceventsfp'); ?></h2>
                    <p><?php _e('API requests are subject to rate limiting to ensure fair usage and system stability.', 'wceventsfp'); ?></p>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Client Type', 'wceventsfp'); ?></th>
                                <th><?php _e('Requests per Hour', 'wceventsfp'); ?></th>
                                <th><?php _e('Burst Limit', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php _e('Anonymous', 'wceventsfp'); ?></td>
                                <td>100</td>
                                <td>20</td>
                            </tr>
                            <tr>
                                <td><?php _e('Authenticated User', 'wceventsfp'); ?></td>
                                <td>1,000</td>
                                <td>50</td>
                            </tr>
                            <tr>
                                <td><?php _e('API Key', 'wceventsfp'); ?></td>
                                <td>5,000</td>
                                <td>100</td>
                            </tr>
                            <tr>
                                <td><?php _e('Premium', 'wceventsfp'); ?></td>
                                <td>10,000</td>
                                <td>200</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3><?php _e('Rate Limit Headers', 'wceventsfp'); ?></h3>
                    <p><?php _e('All API responses include rate limit information in headers:', 'wceventsfp'); ?></p>
                    <pre><code>X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200</code></pre>
                </div>
            </div>
        </div>
        
        <script>
        // Tab switching
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);
                
                // Update active tab
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');
                
                // Update active content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(target).classList.add('active');
            });
        });
        
        // Language switching for examples
        document.querySelectorAll('.language-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const language = this.dataset.language;
                
                // Update active button
                document.querySelectorAll('.language-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Load examples for selected language
                loadCodeExamples(language);
            });
        });
        
        // API key generation
        document.getElementById('generate-api-key').addEventListener('click', function() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=wcefp_generate_api_key&_wpnonce=<?php echo wp_create_nonce('wcefp_api_key'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('api-key-result').innerHTML = 
                        '<div class="notice notice-success"><p><strong>New API Key:</strong><br><code>' + 
                        data.data.api_key + '</code></p></div>';
                }
            });
        });
        
        function loadCodeExamples(language) {
            fetch('<?php echo rest_url('wcefp/v2/docs/examples/'); ?>' + language)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('examples-content').innerHTML = data.examples;
                });
        }
        
        // Load initial examples
        loadCodeExamples('curl');
        </script>
        
        <style>
        .wcefp-api-docs .api-docs-nav {
            margin: 20px 0;
        }
        
        .wcefp-api-docs .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .wcefp-api-docs .tab-content.active {
            display: block;
        }
        
        .wcefp-api-docs .api-url {
            display: block;
            background: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .wcefp-api-docs pre {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
        }
        
        .wcefp-api-docs .api-key-generator {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .wcefp-api-docs .example-language-selector {
            margin: 20px 0;
        }
        
        .wcefp-api-docs .language-btn.active {
            background: #0073aa;
            color: white;
        }
        
        .wcefp-api-docs .endpoint-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .wcefp-api-docs .endpoint-header {
            background: #f9f9f9;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .wcefp-api-docs .endpoint-content {
            padding: 15px;
        }
        
        .wcefp-api-docs .http-method {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .wcefp-api-docs .http-method.get { background: #28a745; }
        .wcefp-api-docs .http-method.post { background: #007bff; }
        .wcefp-api-docs .http-method.put { background: #ffc107; color: #333; }
        .wcefp-api-docs .http-method.delete { background: #dc3545; }
        </style>
        <?php
    }
    
    /**
     * Render endpoints documentation
     */
    private function render_endpoints_documentation($openapi_spec) {
        if (!isset($openapi_spec['paths'])) {
            return;
        }
        
        foreach ($openapi_spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $details) {
                ?>
                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="http-method <?php echo strtolower($method); ?>"><?php echo strtoupper($method); ?></span>
                        <code><?php echo esc_html($path); ?></code>
                        <h4><?php echo esc_html($details['summary'] ?? 'API Endpoint'); ?></h4>
                    </div>
                    <div class="endpoint-content">
                        <?php if (isset($details['description'])): ?>
                            <p><?php echo esc_html($details['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($details['parameters'])): ?>
                            <h5><?php _e('Parameters', 'wceventsfp'); ?></h5>
                            <table class="wp-list-table widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Name', 'wceventsfp'); ?></th>
                                        <th><?php _e('Type', 'wceventsfp'); ?></th>
                                        <th><?php _e('Required', 'wceventsfp'); ?></th>
                                        <th><?php _e('Description', 'wceventsfp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details['parameters'] as $param): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($param['name']); ?></code></td>
                                        <td><?php echo esc_html($param['schema']['type'] ?? 'string'); ?></td>
                                        <td><?php echo $param['required'] ? '✓' : '✗'; ?></td>
                                        <td><?php echo esc_html($param['description'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <?php if (isset($details['responses'])): ?>
                            <h5><?php _e('Response Codes', 'wceventsfp'); ?></h5>
                            <ul>
                                <?php foreach ($details['responses'] as $code => $response): ?>
                                <li><strong><?php echo esc_html($code); ?>:</strong> <?php echo esc_html($response['description']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Generate OpenAPI specification
     */
    public function generate_openapi_spec() {
        $spec = get_transient('wcefp_openapi_spec');
        
        if (!$spec) {
            $spec = [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'WCEventsFP API',
                    'description' => 'Enhanced REST API for WCEventsFP booking platform with advanced features, rate limiting, and comprehensive documentation.',
                    'version' => '2.0.0',
                    'contact' => [
                        'name' => 'WCEventsFP Support',
                        'url' => 'https://github.com/franpass87/WCEventsFP',
                        'email' => 'support@wceventsfp.com'
                    ],
                    'license' => [
                        'name' => 'GPL v2 or later',
                        'url' => 'https://www.gnu.org/licenses/gpl-2.0.html'
                    ]
                ],
                'servers' => [
                    [
                        'url' => rest_url('wcefp/v2/'),
                        'description' => 'Enhanced API v2 (Recommended)'
                    ],
                    [
                        'url' => rest_url('wcefp/v1/'),
                        'description' => 'Legacy API v1'
                    ]
                ],
                'paths' => $this->generate_paths_specification(),
                'components' => $this->generate_components_specification(),
                'security' => [
                    ['ApiKey' => []],
                    ['BearerAuth' => []],
                    ['WordPressAuth' => []]
                ]
            ];
            
            set_transient('wcefp_openapi_spec', $spec, HOUR_IN_SECONDS);
        }
        
        return $spec;
    }
    
    /**
     * Generate paths specification
     */
    private function generate_paths_specification() {
        return [
            '/bookings' => [
                'get' => [
                    'summary' => 'Get bookings with advanced filtering',
                    'description' => 'Retrieve a list of bookings with comprehensive filtering, sorting, and pagination options.',
                    'tags' => ['Bookings'],
                    'parameters' => [
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'description' => 'Number of items per page (1-100)',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10]
                        ],
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                        ],
                        [
                            'name' => 'status',
                            'in' => 'query',
                            'description' => 'Filter by booking status',
                            'required' => false,
                            'schema' => [
                                'type' => 'string',
                                'enum' => ['pending', 'processing', 'completed', 'cancelled', 'refunded']
                            ]
                        ],
                        [
                            'name' => 'event_id',
                            'in' => 'query',
                            'description' => 'Filter by specific event ID',
                            'required' => false,
                            'schema' => ['type' => 'integer']
                        ],
                        [
                            'name' => 'date_from',
                            'in' => 'query',
                            'description' => 'Filter bookings from this date (YYYY-MM-DD)',
                            'required' => false,
                            'schema' => ['type' => 'string', 'format' => 'date']
                        ],
                        [
                            'name' => 'date_to',
                            'in' => 'query',
                            'description' => 'Filter bookings up to this date (YYYY-MM-DD)',
                            'required' => false,
                            'schema' => ['type' => 'string', 'format' => 'date']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response with booking data',
                            'headers' => [
                                'X-WP-Total' => [
                                    'description' => 'Total number of bookings',
                                    'schema' => ['type' => 'integer']
                                ],
                                'X-WP-TotalPages' => [
                                    'description' => 'Total number of pages',
                                    'schema' => ['type' => 'integer']
                                ],
                                'X-RateLimit-Remaining' => [
                                    'description' => 'Remaining requests in current window',
                                    'schema' => ['type' => 'integer']
                                ]
                            ]
                        ],
                        '401' => ['description' => 'Authentication required'],
                        '403' => ['description' => 'Insufficient permissions'],
                        '429' => ['description' => 'Rate limit exceeded']
                    ]
                ],
                'post' => [
                    'summary' => 'Create a new booking',
                    'description' => 'Create a new booking with enhanced validation and automatic confirmation.',
                    'tags' => ['Bookings'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BookingCreate']
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => ['description' => 'Booking created successfully'],
                        '400' => ['description' => 'Invalid input data'],
                        '401' => ['description' => 'Authentication required'],
                        '403' => ['description' => 'Insufficient permissions'],
                        '429' => ['description' => 'Rate limit exceeded']
                    ]
                ]
            ],
            '/analytics' => [
                'get' => [
                    'summary' => 'Get analytics data',
                    'description' => 'Retrieve comprehensive analytics and KPI data for your booking platform.',
                    'tags' => ['Analytics'],
                    'parameters' => [
                        [
                            'name' => 'period',
                            'in' => 'query',
                            'description' => 'Time period for analytics',
                            'required' => false,
                            'schema' => [
                                'type' => 'string',
                                'enum' => ['day', 'week', 'month', 'year'],
                                'default' => 'month'
                            ]
                        ],
                        [
                            'name' => 'metrics',
                            'in' => 'query',
                            'description' => 'Specific metrics to retrieve',
                            'required' => false,
                            'schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                    'enum' => ['bookings', 'revenue', 'occupancy', 'popular_events']
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => ['description' => 'Analytics data retrieved successfully'],
                        '401' => ['description' => 'Authentication required'],
                        '403' => ['description' => 'Insufficient permissions']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Generate components specification
     */
    private function generate_components_specification() {
        return [
            'securitySchemes' => [
                'ApiKey' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-WCEFP-API-Key',
                    'description' => 'API key for authentication. Generate from the WCEventsFP admin panel.'
                ],
                'BearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => 'Bearer token authentication for enhanced security.'
                ],
                'WordPressAuth' => [
                    'type' => 'http',
                    'scheme' => 'basic',
                    'description' => 'WordPress user authentication (username/password).'
                ]
            ],
            'schemas' => [
                'Booking' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Unique booking identifier'],
                        'status' => ['type' => 'string', 'description' => 'Current booking status'],
                        'event_id' => ['type' => 'integer', 'description' => 'Associated event ID'],
                        'customer' => ['$ref' => '#/components/schemas/Customer'],
                        'participants' => ['type' => 'integer', 'description' => 'Number of participants'],
                        'booking_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date of the event'],
                        'total' => ['type' => 'number', 'format' => 'float', 'description' => 'Total booking amount'],
                        'currency' => ['type' => 'string', 'description' => 'Currency code'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Booking creation timestamp'],
                        'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Last update timestamp']
                    ]
                ],
                'BookingCreate' => [
                    'type' => 'object',
                    'required' => ['event_id', 'customer_email', 'booking_date', 'participants'],
                    'properties' => [
                        'event_id' => ['type' => 'integer', 'description' => 'ID of the event to book'],
                        'customer_email' => ['type' => 'string', 'format' => 'email', 'description' => 'Customer email address'],
                        'customer_name' => ['type' => 'string', 'description' => 'Customer full name'],
                        'booking_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date of the event'],
                        'participants' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of participants'],
                        'special_requests' => ['type' => 'string', 'description' => 'Special requests or notes'],
                        'voucher_code' => ['type' => 'string', 'description' => 'Voucher code to apply'],
                        'send_confirmation' => ['type' => 'boolean', 'default' => true, 'description' => 'Send confirmation email']
                    ]
                ],
                'Customer' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Customer email address'],
                        'first_name' => ['type' => 'string', 'description' => 'Customer first name'],
                        'last_name' => ['type' => 'string', 'description' => 'Customer last name'],
                        'phone' => ['type' => 'string', 'description' => 'Customer phone number']
                    ]
                ],
                'Error' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'Error code'],
                        'message' => ['type' => 'string', 'description' => 'Human-readable error message'],
                        'data' => ['type' => 'object', 'description' => 'Additional error data']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get OpenAPI specification
     */
    public function get_openapi_spec($request) {
        $spec = $this->generate_openapi_spec();
        
        return rest_ensure_response($spec);
    }
    
    /**
     * Get interactive documentation
     */
    public function get_interactive_docs($request) {
        $html = $this->generate_swagger_ui_html();
        
        return new \WP_REST_Response($html, 200, [
            'Content-Type' => 'text/html'
        ]);
    }
    
    /**
     * Generate Swagger UI HTML
     */
    private function generate_swagger_ui_html() {
        $openapi_url = rest_url('wcefp/v2/docs/openapi');
        
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WCEventsFP API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-standalone-preset.js"></script>
    <script>
    window.onload = function() {
        SwaggerUIBundle({
            url: "' . esc_url($openapi_url) . '",
            dom_id: "#swagger-ui",
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout"
        });
    }
    </script>
</body>
</html>';
    }
    
    /**
     * Get code examples
     */
    public function get_code_examples($request) {
        $language = $request->get_param('language');
        
        $examples = $this->get_examples_for_language($language);
        
        return rest_ensure_response([
            'language' => $language,
            'examples' => $examples
        ]);
    }
    
    /**
     * Get code examples for specific language
     */
    private function get_examples_for_language($language) {
        $base_url = rest_url('wcefp/v2/');
        
        switch ($language) {
            case 'curl':
                return $this->get_curl_examples($base_url);
            case 'php':
                return $this->get_php_examples($base_url);
            case 'javascript':
                return $this->get_javascript_examples($base_url);
            case 'python':
                return $this->get_python_examples($base_url);
            default:
                return '<p>Examples not available for this language.</p>';
        }
    }
    
    /**
     * Get cURL examples
     */
    private function get_curl_examples($base_url) {
        return '<h3>Get Bookings</h3>
<pre><code>curl -X GET "' . $base_url . 'bookings?per_page=10&status=completed" \
  -H "X-WCEFP-API-Key: your_api_key_here" \
  -H "Content-Type: application/json"</code></pre>

<h3>Create Booking</h3>
<pre><code>curl -X POST "' . $base_url . 'bookings" \
  -H "X-WCEFP-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d \'{
    "event_id": 123,
    "customer_email": "customer@example.com",
    "customer_name": "John Doe",
    "booking_date": "2024-12-25",
    "participants": 2,
    "special_requests": "Window seat preferred"
  }\'</code></pre>';
    }
    
    /**
     * Get PHP examples
     */
    private function get_php_examples($base_url) {
        return '<h3>Initialize Client</h3>
<pre><code>$api_key = "your_api_key_here";
$base_url = "' . $base_url . '";

$headers = [
    "X-WCEFP-API-Key: " . $api_key,
    "Content-Type: application/json"
];</code></pre>

<h3>Get Bookings</h3>
<pre><code>$response = wp_remote_get($base_url . "bookings?per_page=10&status=completed", [
    "headers" => [
        "X-WCEFP-API-Key" => $api_key,
        "Content-Type" => "application/json"
    ]
]);

if (!is_wp_error($response)) {
    $bookings = json_decode(wp_remote_retrieve_body($response), true);
    foreach ($bookings as $booking) {
        echo "Booking #" . $booking["id"] . " - " . $booking["status"] . "\n";
    }
}</code></pre>

<h3>Create Booking</h3>
<pre><code>$booking_data = [
    "event_id" => 123,
    "customer_email" => "customer@example.com",
    "customer_name" => "John Doe",
    "booking_date" => "2024-12-25",
    "participants" => 2,
    "special_requests" => "Window seat preferred"
];

$response = wp_remote_post($base_url . "bookings", [
    "headers" => [
        "X-WCEFP-API-Key" => $api_key,
        "Content-Type" => "application/json"
    ],
    "body" => json_encode($booking_data)
]);

if (!is_wp_error($response)) {
    $booking = json_decode(wp_remote_retrieve_body($response), true);
    echo "Created booking #" . $booking["id"];
}</code></pre>';
    }
    
    /**
     * Get JavaScript examples
     */
    private function get_javascript_examples($base_url) {
        return '<h3>Initialize Client</h3>
<pre><code>const apiKey = "your_api_key_here";
const baseUrl = "' . $base_url . '";

const headers = {
    "X-WCEFP-API-Key": apiKey,
    "Content-Type": "application/json"
};</code></pre>

<h3>Get Bookings</h3>
<pre><code>async function getBookings() {
    try {
        const response = await fetch(baseUrl + "bookings?per_page=10&status=completed", {
            method: "GET",
            headers: headers
        });
        
        const bookings = await response.json();
        bookings.forEach(booking => {
            console.log(`Booking #${booking.id} - ${booking.status}`);
        });
    } catch (error) {
        console.error("Error fetching bookings:", error);
    }
}</code></pre>

<h3>Create Booking</h3>
<pre><code>async function createBooking() {
    const bookingData = {
        event_id: 123,
        customer_email: "customer@example.com",
        customer_name: "John Doe",
        booking_date: "2024-12-25",
        participants: 2,
        special_requests: "Window seat preferred"
    };
    
    try {
        const response = await fetch(baseUrl + "bookings", {
            method: "POST",
            headers: headers,
            body: JSON.stringify(bookingData)
        });
        
        const booking = await response.json();
        console.log(`Created booking #${booking.id}`);
    } catch (error) {
        console.error("Error creating booking:", error);
    }
}</code></pre>';
    }
    
    /**
     * Get Python examples
     */
    private function get_python_examples($base_url) {
        return '<h3>Initialize Client</h3>
<pre><code>import requests
import json

api_key = "your_api_key_here"
base_url = "' . $base_url . '"

headers = {
    "X-WCEFP-API-Key": api_key,
    "Content-Type": "application/json"
}</code></pre>

<h3>Get Bookings</h3>
<pre><code>def get_bookings():
    try:
        response = requests.get(
            base_url + "bookings",
            headers=headers,
            params={"per_page": 10, "status": "completed"}
        )
        response.raise_for_status()
        
        bookings = response.json()
        for booking in bookings:
            print(f"Booking #{booking[\'id\']} - {booking[\'status\']}")
            
    except requests.exceptions.RequestException as e:
        print(f"Error fetching bookings: {e}")</code></pre>

<h3>Create Booking</h3>
<pre><code>def create_booking():
    booking_data = {
        "event_id": 123,
        "customer_email": "customer@example.com",
        "customer_name": "John Doe",
        "booking_date": "2024-12-25",
        "participants": 2,
        "special_requests": "Window seat preferred"
    }
    
    try:
        response = requests.post(
            base_url + "bookings",
            headers=headers,
            json=booking_data
        )
        response.raise_for_status()
        
        booking = response.json()
        print(f"Created booking #{booking[\'id\']}")
        
    except requests.exceptions.RequestException as e:
        print(f"Error creating booking: {e}")</code></pre>';
    }
    
    /**
     * Maybe regenerate documentation cache
     */
    public function maybe_regenerate_documentation() {
        // Check if we need to regenerate (version change, manual trigger, etc.)
        $last_generated = get_option('wcefp_docs_last_generated', '');
        $current_version = get_option('wcefp_plugin_version', '');
        
        if ($last_generated !== $current_version) {
            delete_transient('wcefp_openapi_spec');
            update_option('wcefp_docs_last_generated', $current_version);
        }
    }
}