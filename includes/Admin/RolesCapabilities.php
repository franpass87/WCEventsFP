<?php
/**
 * Roles and Capabilities Management
 * 
 * Defines custom capabilities for WCEventsFP plugin and manages user permissions.
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.4
 */

namespace WCEFP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages custom roles and capabilities for the plugin
 */
class RolesCapabilities {
    
    /**
     * Custom capabilities for the plugin
     * 
     * @var array
     */
    private static $capabilities = [
        // Core plugin management
        'manage_wcefp' => 'Manage WCEventsFP Plugin',
        'view_wcefp_dashboard' => 'View WCEventsFP Dashboard',
        
        // Booking management
        'manage_wcefp_bookings' => 'Manage Event Bookings',
        'view_wcefp_bookings' => 'View Event Bookings',
        'edit_wcefp_bookings' => 'Edit Event Bookings',
        'delete_wcefp_bookings' => 'Delete Event Bookings',
        
        // Resource management
        'manage_wcefp_resources' => 'Manage Resources',
        'view_wcefp_resources' => 'View Resources',
        'edit_wcefp_resources' => 'Edit Resources',
        'delete_wcefp_resources' => 'Delete Resources',
        
        // Channel management
        'manage_wcefp_channels' => 'Manage Distribution Channels',
        'view_wcefp_channels' => 'View Distribution Channels',
        'edit_wcefp_channels' => 'Edit Distribution Channels',
        
        // Commission management
        'manage_wcefp_commissions' => 'Manage Commissions',
        'view_wcefp_commissions' => 'View Commissions',
        'edit_wcefp_commissions' => 'Edit Commissions',
        
        // Voucher management
        'manage_wcefp_vouchers' => 'Manage Vouchers',
        'view_wcefp_vouchers' => 'View Vouchers',
        'edit_wcefp_vouchers' => 'Edit Vouchers',
        'delete_wcefp_vouchers' => 'Delete Vouchers',
        
        // Analytics and reporting
        'view_wcefp_analytics' => 'View Analytics Dashboard',
        'export_wcefp_data' => 'Export Plugin Data',
        
        // Settings
        'manage_wcefp_settings' => 'Manage Plugin Settings',
        'view_wcefp_settings' => 'View Plugin Settings'
    ];
    
    /**
     * Role capability mappings
     * 
     * @var array
     */
    private static $role_capabilities = [
        'administrator' => [
            'manage_wcefp',
            'view_wcefp_dashboard',
            'manage_wcefp_bookings',
            'view_wcefp_bookings',
            'edit_wcefp_bookings',
            'delete_wcefp_bookings',
            'manage_wcefp_resources',
            'view_wcefp_resources',
            'edit_wcefp_resources',
            'delete_wcefp_resources',
            'manage_wcefp_channels',
            'view_wcefp_channels',
            'edit_wcefp_channels',
            'manage_wcefp_commissions',
            'view_wcefp_commissions',
            'edit_wcefp_commissions',
            'manage_wcefp_vouchers',
            'view_wcefp_vouchers',
            'edit_wcefp_vouchers',
            'delete_wcefp_vouchers',
            'view_wcefp_analytics',
            'export_wcefp_data',
            'manage_wcefp_settings',
            'view_wcefp_settings'
        ],
        'shop_manager' => [
            'manage_wcefp',
            'view_wcefp_dashboard',
            'manage_wcefp_bookings',
            'view_wcefp_bookings',
            'edit_wcefp_bookings',
            'manage_wcefp_resources',
            'view_wcefp_resources',
            'edit_wcefp_resources',
            'manage_wcefp_vouchers',
            'view_wcefp_vouchers',
            'edit_wcefp_vouchers',
            'view_wcefp_analytics',
            'export_wcefp_data',
            'view_wcefp_settings'
        ],
        'editor' => [
            'view_wcefp_dashboard',
            'view_wcefp_bookings',
            'edit_wcefp_bookings',
            'view_wcefp_resources',
            'edit_wcefp_resources',
            'view_wcefp_vouchers',
            'edit_wcefp_vouchers',
            'view_wcefp_analytics'
        ]
    ];
    
    /**
     * Initialize roles and capabilities management
     */
    public static function init() {
        add_action('wp_loaded', [__CLASS__, 'maybe_upgrade_capabilities']);
        register_activation_hook(WCEFP_PLUGIN_FILE, [__CLASS__, 'add_capabilities']);
        register_deactivation_hook(WCEFP_PLUGIN_FILE, [__CLASS__, 'remove_capabilities']);
    }
    
    /**
     * Add capabilities to roles on plugin activation
     */
    public static function add_capabilities() {
        foreach (self::$role_capabilities as $role_name => $capabilities) {
            $role = get_role($role_name);
            
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->add_cap($capability);
                }
            }
        }
        
        // Update the capabilities version to track when they were last updated
        update_option('wcefp_capabilities_version', WCEFP_VERSION);
    }
    
    /**
     * Remove capabilities from roles on plugin deactivation
     */
    public static function remove_capabilities() {
        // Only remove if user chooses to delete data on uninstall
        $keep_data = get_option('wcefp_keep_data_on_uninstall', false);
        
        if (!$keep_data) {
            foreach (self::$role_capabilities as $role_name => $capabilities) {
                $role = get_role($role_name);
                
                if ($role) {
                    foreach ($capabilities as $capability) {
                        $role->remove_cap($capability);
                    }
                }
            }
        }
    }
    
    /**
     * Check if capabilities need to be upgraded
     */
    public static function maybe_upgrade_capabilities() {
        $current_version = get_option('wcefp_capabilities_version', '');
        
        if (version_compare($current_version, WCEFP_VERSION, '<')) {
            self::add_capabilities();
        }
    }
    
    /**
     * Check if current user has specific plugin capability
     * 
     * @param string $capability The capability to check
     * @return bool
     */
    public static function current_user_can($capability) {
        // Fallback to manage_options for administrators
        if (!current_user_can($capability) && current_user_can('manage_options')) {
            return true;
        }
        
        return current_user_can($capability);
    }
    
    /**
     * Check if current user can access admin menu page
     * 
     * @param string $page_slug The admin page slug
     * @return bool
     */
    public static function can_access_admin_page($page_slug) {
        switch ($page_slug) {
            case 'wcefp-dashboard':
                return self::current_user_can('view_wcefp_dashboard');
                
            case 'wcefp-bookings':
                return self::current_user_can('view_wcefp_bookings');
                
            case 'wcefp-resources':
                return self::current_user_can('view_wcefp_resources');
                
            case 'wcefp-channels':
                return self::current_user_can('view_wcefp_channels');
                
            case 'wcefp-commissions':
                return self::current_user_can('view_wcefp_commissions');
                
            case 'wcefp-vouchers':
                return self::current_user_can('view_wcefp_vouchers');
                
            case 'wcefp-analytics':
                return self::current_user_can('view_wcefp_analytics');
                
            case 'wcefp-settings':
                return self::current_user_can('view_wcefp_settings');
                
            default:
                return self::current_user_can('manage_wcefp');
        }
    }
    
    /**
     * Get minimum capability required for a specific action
     * 
     * @param string $action The action to check
     * @return string|false The required capability or false if action is not recognized
     */
    public static function get_required_capability($action) {
        $capability_map = [
            // CRUD operations
            'create_booking' => 'edit_wcefp_bookings',
            'edit_booking' => 'edit_wcefp_bookings',
            'delete_booking' => 'delete_wcefp_bookings',
            'view_booking' => 'view_wcefp_bookings',
            
            'create_resource' => 'edit_wcefp_resources',
            'edit_resource' => 'edit_wcefp_resources',
            'delete_resource' => 'delete_wcefp_resources',
            'view_resource' => 'view_wcefp_resources',
            
            'create_voucher' => 'edit_wcefp_vouchers',
            'edit_voucher' => 'edit_wcefp_vouchers',
            'delete_voucher' => 'delete_wcefp_vouchers',
            'view_voucher' => 'view_wcefp_vouchers',
            
            // Settings operations
            'save_settings' => 'manage_wcefp_settings',
            'view_settings' => 'view_wcefp_settings',
            
            // Analytics operations
            'view_analytics' => 'view_wcefp_analytics',
            'export_data' => 'export_wcefp_data',
        ];
        
        return $capability_map[$action] ?? false;
    }
    
    /**
     * Create custom role for WCEFP managers
     */
    public static function create_custom_roles() {
        // WCEFP Event Manager role
        add_role(
            'wcefp_event_manager',
            __('Event Manager', 'wceventsfp'),
            [
                'read' => true,
                'view_wcefp_dashboard' => true,
                'manage_wcefp_bookings' => true,
                'view_wcefp_bookings' => true,
                'edit_wcefp_bookings' => true,
                'manage_wcefp_resources' => true,
                'view_wcefp_resources' => true,
                'edit_wcefp_resources' => true,
                'view_wcefp_analytics' => true,
                'manage_wcefp_vouchers' => true,
                'view_wcefp_vouchers' => true,
                'edit_wcefp_vouchers' => true
            ]
        );
        
        // WCEFP Viewer role (read-only access)
        add_role(
            'wcefp_viewer',
            __('Event Viewer', 'wceventsfp'),
            [
                'read' => true,
                'view_wcefp_dashboard' => true,
                'view_wcefp_bookings' => true,
                'view_wcefp_resources' => true,
                'view_wcefp_vouchers' => true,
                'view_wcefp_analytics' => true
            ]
        );
    }
    
    /**
     * Remove custom roles on plugin deactivation
     */
    public static function remove_custom_roles() {
        $keep_data = get_option('wcefp_keep_data_on_uninstall', false);
        
        if (!$keep_data) {
            remove_role('wcefp_event_manager');
            remove_role('wcefp_viewer');
        }
    }
    
    /**
     * Get all plugin capabilities
     * 
     * @return array
     */
    public static function get_capabilities() {
        return self::$capabilities;
    }
    
    /**
     * Get role capability mappings
     * 
     * @return array
     */
    public static function get_role_capabilities() {
        return self::$role_capabilities;
    }
    
    /**
     * Validate nonce for admin actions
     * 
     * @param string $nonce_action The nonce action
     * @param string $capability The required capability
     * @return bool
     */
    public static function validate_admin_request($nonce_action, $capability) {
        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', $nonce_action)) {
            return false;
        }
        
        // Check capability
        if (!self::current_user_can($capability)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Display capability-based admin notices
     * 
     * @param string $message The notice message
     * @param string $type The notice type (error, warning, success, info)
     */
    public static function admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s"><p><strong>WCEventsFP:</strong> %s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
    
    /**
     * Check multiple capabilities with OR logic
     * 
     * @param array $capabilities Array of capabilities to check
     * @return bool True if user has any of the specified capabilities
     */
    public static function current_user_can_any($capabilities) {
        foreach ($capabilities as $capability) {
            if (self::current_user_can($capability)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check multiple capabilities with AND logic
     * 
     * @param array $capabilities Array of capabilities to check
     * @return bool True if user has all of the specified capabilities
     */
    public static function current_user_can_all($capabilities) {
        foreach ($capabilities as $capability) {
            if (!self::current_user_can($capability)) {
                return false;
            }
        }
        return true;
    }
}