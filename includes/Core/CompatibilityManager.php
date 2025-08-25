<?php
/**
 * Plugin Compatibility Manager
 * 
 * Handles compatibility checks and integrations with other WordPress
 * plugins commonly used with WCEventsFP.
 * 
 * @package WCEFP\Core
 * @since 2.2.0
 */

namespace WCEFP\Core;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility Manager class
 */
class CompatibilityManager {
    
    /**
     * Known plugin compatibilities
     */
    const COMPATIBLE_PLUGINS = [
        // WooCommerce Extensions
        'woocommerce-subscriptions/woocommerce-subscriptions.php' => [
            'name' => 'WooCommerce Subscriptions',
            'version' => '3.0.0',
            'status' => 'compatible'
        ],
        'woocommerce-deposits/woocommerce-deposits.php' => [
            'name' => 'WooCommerce Deposits',
            'version' => '1.5.0',
            'status' => 'compatible'
        ],
        'woocommerce-bookings/woocommerce-bookings.php' => [
            'name' => 'WooCommerce Bookings',
            'version' => '1.15.0',
            'status' => 'partial', // Might conflict with similar functionality
            'note' => 'May have overlapping features. Test thoroughly.'
        ],
        
        // Multilingual
        'sitepress-multilingual-cms/sitepress.php' => [
            'name' => 'WPML',
            'version' => '4.4.0',
            'status' => 'compatible'
        ],
        'polylang/polylang.php' => [
            'name' => 'Polylang',
            'version' => '3.2.0',
            'status' => 'compatible'
        ],
        
        // SEO
        'wordpress-seo/wp-seo.php' => [
            'name' => 'Yoast SEO',
            'version' => '19.0.0',
            'status' => 'compatible'
        ],
        
        // Caching
        'wp-rocket/wp-rocket.php' => [
            'name' => 'WP Rocket',
            'version' => '3.10.0',
            'status' => 'compatible',
            'note' => 'Exclude WCEFP AJAX endpoints from caching'
        ],
        'w3-total-cache/w3-total-cache.php' => [
            'name' => 'W3 Total Cache',
            'version' => '2.2.0',
            'status' => 'compatible'
        ],
        
        // Security
        'wordfence/wordfence.php' => [
            'name' => 'Wordfence',
            'version' => '7.6.0',
            'status' => 'compatible'
        ],
        
        // Page Builders
        'elementor/elementor.php' => [
            'name' => 'Elementor',
            'version' => '3.6.0',
            'status' => 'compatible'
        ],
        'beaver-builder-lite-version/fl-builder.php' => [
            'name' => 'Beaver Builder',
            'version' => '2.5.0',
            'status' => 'compatible'
        ]
    ];
    
    /**
     * Known incompatible plugins
     */
    const INCOMPATIBLE_PLUGINS = [
        'some-booking-plugin/some-booking.php' => [
            'name' => 'Conflicting Booking Plugin',
            'reason' => 'Conflicts with booking functionality'
        ]
    ];
    
    /**
     * Initialize compatibility manager
     */
    public function init(): void {
        // Run compatibility checks on admin pages
        add_action('admin_init', [$this, 'run_compatibility_checks']);
        
        // Add compatibility notices
        add_action('admin_notices', [$this, 'show_compatibility_notices']);
        
        // Initialize plugin-specific integrations
        add_action('plugins_loaded', [$this, 'initialize_integrations'], 20);
        
        Logger::info('Compatibility Manager initialized');
    }
    
    /**
     * Run compatibility checks
     */
    public function run_compatibility_checks(): void {
        // Check WordPress version
        $this->check_wordpress_version();
        
        // Check WooCommerce version
        $this->check_woocommerce_version();
        
        // Check PHP version
        $this->check_php_version();
        
        // Check plugin conflicts
        $this->check_plugin_conflicts();
        
        // Check server requirements
        $this->check_server_requirements();
        
        // Save check results
        $this->save_compatibility_results();
    }
    
    /**
     * Check WordPress version compatibility
     */
    private function check_wordpress_version(): void {
        global $wp_version;
        
        $min_version = '5.0';
        $recommended_version = '6.0';
        
        if (version_compare($wp_version, $min_version, '<')) {
            $this->add_compatibility_issue('wordpress_version', [
                'type' => 'error',
                'message' => sprintf(
                    __('WCEventsFP requires WordPress %s or higher. You are running %s.', 'wceventsfp'),
                    $min_version,
                    $wp_version
                ),
                'fix' => __('Please update WordPress to the latest version.', 'wceventsfp')
            ]);
        } elseif (version_compare($wp_version, $recommended_version, '<')) {
            $this->add_compatibility_issue('wordpress_version', [
                'type' => 'warning',
                'message' => sprintf(
                    __('For best performance, WordPress %s or higher is recommended. You are running %s.', 'wceventsfp'),
                    $recommended_version,
                    $wp_version
                )
            ]);
        }
    }
    
    /**
     * Check WooCommerce version compatibility
     */
    private function check_woocommerce_version(): void {
        if (!class_exists('WooCommerce')) {
            $this->add_compatibility_issue('woocommerce_missing', [
                'type' => 'error',
                'message' => __('WCEventsFP requires WooCommerce to be installed and activated.', 'wceventsfp'),
                'fix' => __('Please install and activate WooCommerce.', 'wceventsfp')
            ]);
            return;
        }
        
        $wc_version = WC()->version;
        $min_version = '5.0.0';
        $recommended_version = '7.0.0';
        
        if (version_compare($wc_version, $min_version, '<')) {
            $this->add_compatibility_issue('woocommerce_version', [
                'type' => 'error',
                'message' => sprintf(
                    __('WCEventsFP requires WooCommerce %s or higher. You are running %s.', 'wceventsfp'),
                    $min_version,
                    $wc_version
                ),
                'fix' => __('Please update WooCommerce to the latest version.', 'wceventsfp')
            ]);
        } elseif (version_compare($wc_version, $recommended_version, '<')) {
            $this->add_compatibility_issue('woocommerce_version', [
                'type' => 'warning',
                'message' => sprintf(
                    __('For best compatibility, WooCommerce %s or higher is recommended. You are running %s.', 'wceventsfp'),
                    $recommended_version,
                    $wc_version
                )
            ]);
        }
    }
    
    /**
     * Check PHP version compatibility
     */
    private function check_php_version(): void {
        $php_version = PHP_VERSION;
        $min_version = '7.4';
        $recommended_version = '8.0';
        
        if (version_compare($php_version, $min_version, '<')) {
            $this->add_compatibility_issue('php_version', [
                'type' => 'error',
                'message' => sprintf(
                    __('WCEventsFP requires PHP %s or higher. You are running %s.', 'wceventsfp'),
                    $min_version,
                    $php_version
                ),
                'fix' => __('Please contact your hosting provider to upgrade PHP.', 'wceventsfp')
            ]);
        } elseif (version_compare($php_version, $recommended_version, '<')) {
            $this->add_compatibility_issue('php_version', [
                'type' => 'warning',
                'message' => sprintf(
                    __('For better performance and security, PHP %s or higher is recommended. You are running %s.', 'wceventsfp'),
                    $recommended_version,
                    $php_version
                )
            ]);
        }
    }
    
    /**
     * Check for plugin conflicts
     */
    private function check_plugin_conflicts(): void {
        $active_plugins = get_option('active_plugins', []);
        
        // Check for known incompatible plugins
        foreach (self::INCOMPATIBLE_PLUGINS as $plugin_path => $plugin_info) {
            if (in_array($plugin_path, $active_plugins)) {
                $this->add_compatibility_issue('plugin_conflict_' . sanitize_key($plugin_path), [
                    'type' => 'error',
                    'message' => sprintf(
                        __('Conflict detected: %s is incompatible with WCEventsFP. %s', 'wceventsfp'),
                        $plugin_info['name'],
                        $plugin_info['reason']
                    ),
                    'fix' => __('Please deactivate the conflicting plugin.', 'wceventsfp')
                ]);
            }
        }
        
        // Check versions of compatible plugins
        foreach (self::COMPATIBLE_PLUGINS as $plugin_path => $plugin_info) {
            if (in_array($plugin_path, $active_plugins)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
                $plugin_version = $plugin_data['Version'] ?? '0.0.0';
                
                if ($plugin_info['status'] === 'partial') {
                    $this->add_compatibility_issue('plugin_partial_' . sanitize_key($plugin_path), [
                        'type' => 'warning',
                        'message' => sprintf(
                            __('%s detected: %s', 'wceventsfp'),
                            $plugin_info['name'],
                            $plugin_info['note'] ?? __('Partial compatibility. Test thoroughly.', 'wceventsfp')
                        )
                    ]);
                } elseif (version_compare($plugin_version, $plugin_info['version'], '<')) {
                    $this->add_compatibility_issue('plugin_version_' . sanitize_key($plugin_path), [
                        'type' => 'warning',
                        'message' => sprintf(
                            __('%s version %s detected. Version %s or higher is recommended for full compatibility.', 'wceventsfp'),
                            $plugin_info['name'],
                            $plugin_version,
                            $plugin_info['version']
                        )
                    ]);
                }
            }
        }
    }
    
    /**
     * Check server requirements
     */
    private function check_server_requirements(): void {
        // Check memory limit
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $recommended_memory = 256 * 1024 * 1024; // 256MB
        
        if ($memory_limit < $recommended_memory) {
            $this->add_compatibility_issue('memory_limit', [
                'type' => 'warning',
                'message' => sprintf(
                    __('PHP memory limit is %s. At least 256MB is recommended for WCEventsFP.', 'wceventsfp'),
                    size_format($memory_limit)
                ),
                'fix' => __('Increase memory_limit in php.ini or contact your hosting provider.', 'wceventsfp')
            ]);
        }
        
        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 60) {
            $this->add_compatibility_issue('execution_time', [
                'type' => 'warning',
                'message' => sprintf(
                    __('PHP max execution time is %s seconds. At least 60 seconds is recommended.', 'wceventsfp'),
                    $max_execution_time
                ),
                'fix' => __('Increase max_execution_time in php.ini or contact your hosting provider.', 'wceventsfp')
            ]);
        }
        
        // Check required PHP extensions
        $required_extensions = ['mysqli', 'curl', 'gd', 'mbstring'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->add_compatibility_issue('php_extension_' . $extension, [
                    'type' => 'error',
                    'message' => sprintf(
                        __('Required PHP extension "%s" is not installed.', 'wceventsfp'),
                        $extension
                    ),
                    'fix' => __('Install the missing PHP extension or contact your hosting provider.', 'wceventsfp')
                ]);
            }
        }
    }
    
    /**
     * Initialize plugin-specific integrations
     */
    public function initialize_integrations(): void {
        // WooCommerce Subscriptions integration
        if ($this->is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
            $this->init_subscriptions_integration();
        }
        
        // WooCommerce Deposits integration
        if ($this->is_plugin_active('woocommerce-deposits/woocommerce-deposits.php')) {
            $this->init_deposits_integration();
        }
        
        // WPML integration
        if ($this->is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
            $this->init_wpml_integration();
        }
        
        // Polylang integration
        if ($this->is_plugin_active('polylang/polylang.php')) {
            $this->init_polylang_integration();
        }
        
        // Caching plugins integration
        if ($this->is_plugin_active('wp-rocket/wp-rocket.php')) {
            $this->init_wp_rocket_integration();
        }
    }
    
    /**
     * Initialize WooCommerce Subscriptions integration
     */
    private function init_subscriptions_integration(): void {
        // Ensure event products work with subscriptions
        add_filter('woocommerce_is_subscription_product', [$this, 'handle_subscription_events'], 10, 2);
        
        Logger::info('WooCommerce Subscriptions integration initialized');
    }
    
    /**
     * Initialize WooCommerce Deposits integration
     */
    private function init_deposits_integration(): void {
        // Ensure deposits work with event products
        add_filter('wc_deposits_is_product_supported', [$this, 'handle_deposits_events'], 10, 2);
        
        Logger::info('WooCommerce Deposits integration initialized');
    }
    
    /**
     * Initialize WPML integration
     */
    private function init_wpml_integration(): void {
        // Register strings for translation
        add_action('init', [$this, 'register_wpml_strings']);
        
        // Handle event product translations
        add_filter('wcefp_get_event_data', [$this, 'translate_event_data'], 10, 2);
        
        Logger::info('WPML integration initialized');
    }
    
    /**
     * Initialize Polylang integration
     */
    private function init_polylang_integration(): void {
        // Register strings for translation
        add_action('init', [$this, 'register_polylang_strings']);
        
        Logger::info('Polylang integration initialized');
    }
    
    /**
     * Initialize WP Rocket integration
     */
    private function init_wp_rocket_integration(): void {
        // Exclude AJAX endpoints from caching
        add_filter('rocket_cache_reject_uri', [$this, 'exclude_ajax_from_cache']);
        
        // Exclude dynamic pages from caching
        add_filter('rocket_cache_reject_cookies', [$this, 'exclude_booking_cookies_from_cache']);
        
        Logger::info('WP Rocket integration initialized');
    }
    
    /**
     * Add compatibility issue
     * 
     * @param string $key Issue key
     * @param array $issue Issue data
     */
    private function add_compatibility_issue(string $key, array $issue): void {
        $issues = get_option('wcefp_compatibility_issues', []);
        $issues[$key] = $issue;
        update_option('wcefp_compatibility_issues', $issues, false);
    }
    
    /**
     * Save compatibility results
     */
    private function save_compatibility_results(): void {
        $results = [
            'last_check' => time(),
            'wp_version' => get_bloginfo('version'),
            'wc_version' => class_exists('WooCommerce') ? WC()->version : 'not_installed',
            'php_version' => PHP_VERSION,
            'active_plugins' => get_option('active_plugins', []),
            'theme' => get_template()
        ];
        
        update_option('wcefp_compatibility_results', $results, false);
        
        Logger::info('Compatibility check completed', $results);
    }
    
    /**
     * Show compatibility notices
     */
    public function show_compatibility_notices(): void {
        $issues = get_option('wcefp_compatibility_issues', []);
        
        foreach ($issues as $key => $issue) {
            $class = $issue['type'] === 'error' ? 'notice-error' : 'notice-warning';
            
            echo '<div class="notice ' . $class . ' is-dismissible" data-key="' . esc_attr($key) . '">';
            echo '<p><strong>WCEventsFP:</strong> ' . esc_html($issue['message']);
            
            if (!empty($issue['fix'])) {
                echo '<br><em>' . esc_html($issue['fix']) . '</em>';
            }
            
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Check if a plugin is active
     * 
     * @param string $plugin_path Plugin path
     * @return bool
     */
    private function is_plugin_active(string $plugin_path): bool {
        return in_array($plugin_path, get_option('active_plugins', []));
    }
    
    /**
     * Handle subscription products
     * 
     * @param bool $is_subscription Current status
     * @param WC_Product $product Product object
     * @return bool
     */
    public function handle_subscription_events($is_subscription, $product): bool {
        // Event products should generally not be subscription products
        // unless specifically configured
        if (in_array($product->get_type(), ['evento', 'esperienza'])) {
            return get_post_meta($product->get_id(), '_wcefp_enable_subscription', true) === 'yes';
        }
        
        return $is_subscription;
    }
    
    /**
     * Handle deposits for events
     * 
     * @param bool $supported Current status
     * @param WC_Product $product Product object
     * @return bool
     */
    public function handle_deposits_events($supported, $product): bool {
        // Event products should support deposits by default
        if (in_array($product->get_type(), ['evento', 'esperienza'])) {
            return true;
        }
        
        return $supported;
    }
    
    /**
     * Exclude AJAX endpoints from WP Rocket cache
     * 
     * @param array $excluded_uris Excluded URIs
     * @return array
     */
    public function exclude_ajax_from_cache($excluded_uris): array {
        $excluded_uris[] = '/wp-admin/admin-ajax.php.*action=wcefp_.*';
        $excluded_uris[] = '/wp-json/wcefp/.*';
        
        return $excluded_uris;
    }
    
    /**
     * Exclude booking cookies from WP Rocket cache
     * 
     * @param array $excluded_cookies Excluded cookies
     * @return array
     */
    public function exclude_booking_cookies_from_cache($excluded_cookies): array {
        $excluded_cookies[] = 'wcefp_booking_session';
        $excluded_cookies[] = 'wcefp_stock_hold';
        
        return $excluded_cookies;
    }
    
    /**
     * Get compatibility status
     * 
     * @return array Status array
     */
    public function get_compatibility_status(): array {
        $issues = get_option('wcefp_compatibility_issues', []);
        $results = get_option('wcefp_compatibility_results', []);
        
        $error_count = count(array_filter($issues, function($issue) {
            return $issue['type'] === 'error';
        }));
        
        $warning_count = count(array_filter($issues, function($issue) {
            return $issue['type'] === 'warning';
        }));
        
        return [
            'overall_status' => $error_count > 0 ? 'error' : ($warning_count > 0 ? 'warning' : 'good'),
            'error_count' => $error_count,
            'warning_count' => $warning_count,
            'issues' => $issues,
            'results' => $results,
            'last_check' => $results['last_check'] ?? 0
        ];
    }
    
    /**
     * Register WPML strings
     */
    public function register_wpml_strings(): void {
        if (function_exists('icl_register_string')) {
            // Register common strings for translation
            $strings = [
                'Book Now' => __('Book Now', 'wceventsfp'),
                'Select Date' => __('Select Date', 'wceventsfp'),
                'Choose Participants' => __('Choose Participants', 'wceventsfp'),
                'Meeting Point' => __('Meeting Point', 'wceventsfp'),
                'Add to Cart' => __('Add to Cart', 'wceventsfp')
            ];
            
            foreach ($strings as $name => $value) {
                icl_register_string('WCEventsFP', $name, $value);
            }
        }
    }
    
    /**
     * Register Polylang strings
     */
    public function register_polylang_strings(): void {
        if (function_exists('pll_register_string')) {
            // Register common strings for translation
            $strings = [
                'Book Now' => __('Book Now', 'wceventsfp'),
                'Select Date' => __('Select Date', 'wceventsfp'),
                'Choose Participants' => __('Choose Participants', 'wceventsfp'),
                'Meeting Point' => __('Meeting Point', 'wceventsfp'),
                'Add to Cart' => __('Add to Cart', 'wceventsfp')
            ];
            
            foreach ($strings as $name => $value) {
                pll_register_string($name, $value, 'WCEventsFP');
            }
        }
    }
    
    /**
     * Translate event data with WPML
     * 
     * @param array $data Event data
     * @param int $product_id Product ID
     * @return array Translated data
     */
    public function translate_event_data($data, $product_id): array {
        if (function_exists('icl_translate')) {
            // Translate specific fields if they exist
            if (!empty($data['meeting_point_name'])) {
                $data['meeting_point_name'] = icl_translate('WCEventsFP', 'Meeting Point Name', $data['meeting_point_name']);
            }
            
            if (!empty($data['description'])) {
                $data['description'] = icl_translate('WCEventsFP', 'Event Description', $data['description']);
            }
        }
        
        return $data;
    }
}