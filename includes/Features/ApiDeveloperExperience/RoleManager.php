<?php
/**
 * Role Manager
 * 
 * Enhanced role management system for WCEventsFP, implementing custom event_manager role
 * and granular capability management for Phase 4 API & Developer Experience.
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\Admin\RolesCapabilities;
use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Role Manager - Enhanced role and capability management
 */
class RoleManager {
    
    /**
     * Custom event manager role definition
     * 
     * @var array
     */
    private $event_manager_capabilities = [
        // Core WordPress capabilities
        'read' => true,
        'upload_files' => true,
        
        // WCEFP Dashboard access
        'view_wcefp_dashboard' => true,
        'manage_wcefp' => true,
        
        // Booking management
        'manage_wcefp_bookings' => true,
        'view_wcefp_bookings' => true,
        'edit_wcefp_bookings' => true,
        'delete_wcefp_bookings' => true,
        
        // Event and resource management
        'manage_wcefp_resources' => true,
        'view_wcefp_resources' => true,
        'edit_wcefp_resources' => true,
        'delete_wcefp_resources' => true,
        
        // Voucher management
        'manage_wcefp_vouchers' => true,
        'view_wcefp_vouchers' => true,
        'edit_wcefp_vouchers' => true,
        'delete_wcefp_vouchers' => true,
        
        // Analytics and reporting
        'view_wcefp_analytics' => true,
        'export_wcefp_data' => true,
        
        // Communication features (Phase 2)
        'manage_wcefp_emails' => true,
        'view_wcefp_email_logs' => true,
        
        // Data integration (Phase 3)
        'manage_wcefp_exports' => true,
        'manage_wcefp_blocks' => true,
        
        // API access (Phase 4)
        'access_wcefp_api' => true,
        'manage_wcefp_api_keys' => true,
        
        // Settings (limited)
        'view_wcefp_settings' => true,
        
        // WooCommerce integration
        'read_product' => true,
        'edit_products' => true,
        'read_shop_order' => true,
        'edit_shop_orders' => true,
    ];
    
    /**
     * Advanced capabilities for enterprise features
     */
    private $advanced_capabilities = [
        // Channel management
        'manage_wcefp_channels' => true,
        'view_wcefp_channels' => true,
        'edit_wcefp_channels' => true,
        
        // Commission management
        'manage_wcefp_commissions' => true,
        'view_wcefp_commissions' => true,
        'edit_wcefp_commissions' => true,
        
        // Advanced API features
        'manage_wcefp_webhooks' => true,
        'view_wcefp_api_logs' => true,
        'manage_wcefp_rate_limits' => true,
        
        // Developer tools
        'use_wcefp_dev_tools' => true,
        'view_wcefp_api_docs' => true,
    ];
    
    /**
     * Initialize role manager
     */
    public function init() {
        // Hook into WordPress activation
        register_activation_hook(WCEFP_PLUGIN_FILE, [$this, 'create_custom_roles']);
        register_deactivation_hook(WCEFP_PLUGIN_FILE, [$this, 'remove_custom_roles']);
        
        // Add capabilities to existing roles
        add_action('wp_loaded', [$this, 'maybe_upgrade_roles']);
        
        // Admin interface hooks
        add_action('admin_menu', [$this, 'add_role_management_page']);
        add_action('wp_ajax_wcefp_update_user_role', [$this, 'ajax_update_user_role']);
        add_action('wp_ajax_wcefp_bulk_role_action', [$this, 'ajax_bulk_role_action']);
        
        // Filter admin menu based on capabilities
        add_action('admin_menu', [$this, 'filter_admin_menu'], 999);
        
        DiagnosticLogger::instance()->debug('Role Manager initialized', [], 'api_features');
    }
    
    /**
     * Create custom roles on plugin activation
     */
    public function create_custom_roles() {
        // Create Event Manager role
        $result = add_role(
            'wcefp_event_manager',
            __('Event Manager', 'wceventsfp'),
            $this->event_manager_capabilities
        );
        
        if ($result) {
            DiagnosticLogger::instance()->info('Custom role created: wcefp_event_manager', [], 'role_management');
        }
        
        // Create Advanced Event Manager role (with enterprise features)
        add_role(
            'wcefp_advanced_event_manager',
            __('Advanced Event Manager', 'wceventsfp'),
            array_merge($this->event_manager_capabilities, $this->advanced_capabilities)
        );
        
        // Create Event Viewer role (read-only)
        add_role(
            'wcefp_event_viewer',
            __('Event Viewer', 'wceventsfp'),
            [
                'read' => true,
                'view_wcefp_dashboard' => true,
                'view_wcefp_bookings' => true,
                'view_wcefp_resources' => true,
                'view_wcefp_vouchers' => true,
                'view_wcefp_analytics' => true,
                'view_wcefp_settings' => true,
                'access_wcefp_api' => true, // Read-only API access
            ]
        );
        
        // Update capability mappings for existing roles
        $this->update_existing_roles();
        
        // Mark roles as created
        update_option('wcefp_custom_roles_version', WCEFP_VERSION);
    }
    
    /**
     * Update existing WordPress roles with new capabilities
     */
    private function update_existing_roles() {
        // Administrator gets all capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (array_merge($this->event_manager_capabilities, $this->advanced_capabilities) as $cap => $granted) {
                if ($granted) {
                    $admin_role->add_cap($cap);
                }
            }
            // Admin also gets settings management
            $admin_role->add_cap('manage_wcefp_settings');
        }
        
        // Shop Manager gets event manager capabilities
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            foreach ($this->event_manager_capabilities as $cap => $granted) {
                if ($granted) {
                    $shop_manager_role->add_cap($cap);
                }
            }
        }
        
        // Editor gets limited capabilities
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_caps = [
                'view_wcefp_dashboard' => true,
                'view_wcefp_bookings' => true,
                'edit_wcefp_bookings' => true,
                'view_wcefp_resources' => true,
                'edit_wcefp_resources' => true,
                'view_wcefp_vouchers' => true,
                'edit_wcefp_vouchers' => true,
                'view_wcefp_analytics' => true,
                'export_wcefp_data' => true,
                'access_wcefp_api' => true,
            ];
            
            foreach ($editor_caps as $cap => $granted) {
                if ($granted) {
                    $editor_role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Remove custom roles on plugin deactivation
     */
    public function remove_custom_roles() {
        // Check if user wants to keep data
        $keep_data = get_option('wcefp_keep_data_on_uninstall', false);
        
        if (!$keep_data) {
            // Remove custom roles
            remove_role('wcefp_event_manager');
            remove_role('wcefp_advanced_event_manager');
            remove_role('wcefp_event_viewer');
            
            // Remove capabilities from existing roles
            $this->remove_capabilities_from_existing_roles();
            
            DiagnosticLogger::instance()->info('Custom roles removed', [], 'role_management');
        }
    }
    
    /**
     * Remove WCEFP capabilities from existing roles
     */
    private function remove_capabilities_from_existing_roles() {
        $all_caps = array_merge($this->event_manager_capabilities, $this->advanced_capabilities);
        $all_caps['manage_wcefp_settings'] = true; // Include settings capability
        
        $existing_roles = ['administrator', 'shop_manager', 'editor'];
        
        foreach ($existing_roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($all_caps as $cap => $granted) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Check if roles need upgrading
     */
    public function maybe_upgrade_roles() {
        $current_version = get_option('wcefp_custom_roles_version', '');
        
        if (version_compare($current_version, WCEFP_VERSION, '<')) {
            $this->create_custom_roles();
        }
    }
    
    /**
     * Add role management page to admin menu
     */
    public function add_role_management_page() {
        if (!RolesCapabilities::current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'wcefp-dashboard',
            __('Role Management', 'wceventsfp'),
            __('Roles & Access', 'wceventsfp'),
            'manage_options',
            'wcefp-roles',
            [$this, 'render_role_management_page']
        );
    }
    
    /**
     * Render role management page
     */
    public function render_role_management_page() {
        // Get all WCEFP-related users
        $users_query = new \WP_User_Query([
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'wp_capabilities',
                    'value' => 'wcefp_event_manager',
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'wp_capabilities', 
                    'value' => 'wcefp_advanced_event_manager',
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'wp_capabilities',
                    'value' => 'wcefp_event_viewer',
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        
        $wcefp_users = $users_query->get_results();
        
        // Get all users with relevant capabilities
        $all_users_query = new \WP_User_Query([
            'meta_query' => [
                [
                    'key' => 'wp_capabilities',
                    'value' => 'manage_wcefp',
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        
        $all_relevant_users = $all_users_query->get_results();
        
        ?>
        <div class="wrap wcefp-role-management">
            <h1><?php _e('WCEventsFP Role Management', 'wceventsfp'); ?></h1>
            
            <div class="wcefp-role-stats">
                <div class="wcefp-stat-box">
                    <h3><?php _e('Event Managers', 'wceventsfp'); ?></h3>
                    <span class="stat-number"><?php echo count(array_filter($all_relevant_users, function($user) { 
                        return in_array('wcefp_event_manager', $user->roles); 
                    })); ?></span>
                </div>
                <div class="wcefp-stat-box">
                    <h3><?php _e('Event Viewers', 'wceventsfp'); ?></h3>
                    <span class="stat-number"><?php echo count(array_filter($all_relevant_users, function($user) { 
                        return in_array('wcefp_event_viewer', $user->roles); 
                    })); ?></span>
                </div>
                <div class="wcefp-stat-box">
                    <h3><?php _e('Total Users', 'wceventsfp'); ?></h3>
                    <span class="stat-number"><?php echo count($all_relevant_users); ?></span>
                </div>
            </div>
            
            <div class="wcefp-role-actions">
                <h2><?php _e('User Role Management', 'wceventsfp'); ?></h2>
                <p><?php _e('Manage user roles and capabilities for WCEventsFP access control.', 'wceventsfp'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'wceventsfp'); ?></th>
                            <th><?php _e('Current Role', 'wceventsfp'); ?></th>
                            <th><?php _e('WCEFP Capabilities', 'wceventsfp'); ?></th>
                            <th><?php _e('Actions', 'wceventsfp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_relevant_users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            <td>
                                <?php
                                $wcefp_caps = [];
                                foreach ($user->allcaps as $cap => $has_cap) {
                                    if ($has_cap && strpos($cap, 'wcefp') === 0) {
                                        $wcefp_caps[] = $cap;
                                    }
                                }
                                echo count($wcefp_caps) . ' ' . __('capabilities', 'wceventsfp');
                                ?>
                            </td>
                            <td>
                                <button class="button" onclick="editUserRole(<?php echo $user->ID; ?>)">
                                    <?php _e('Edit', 'wceventsfp'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="wcefp-role-capabilities">
                <h2><?php _e('Role Capabilities Matrix', 'wceventsfp'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Capability', 'wceventsfp'); ?></th>
                            <th><?php _e('Event Viewer', 'wceventsfp'); ?></th>
                            <th><?php _e('Event Manager', 'wceventsfp'); ?></th>
                            <th><?php _e('Advanced Manager', 'wceventsfp'); ?></th>
                            <th><?php _e('Administrator', 'wceventsfp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $viewer_role = get_role('wcefp_event_viewer');
                        $manager_role = get_role('wcefp_event_manager');
                        $advanced_role = get_role('wcefp_advanced_event_manager');
                        $admin_role = get_role('administrator');
                        
                        $all_caps = array_unique(array_merge(
                            array_keys($this->event_manager_capabilities),
                            array_keys($this->advanced_capabilities)
                        ));
                        
                        foreach ($all_caps as $cap):
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($cap); ?></code></td>
                            <td><?php echo $viewer_role && $viewer_role->has_cap($cap) ? '✓' : '✗'; ?></td>
                            <td><?php echo $manager_role && $manager_role->has_cap($cap) ? '✓' : '✗'; ?></td>
                            <td><?php echo $advanced_role && $advanced_role->has_cap($cap) ? '✓' : '✗'; ?></td>
                            <td><?php echo $admin_role && $admin_role->has_cap($cap) ? '✓' : '✗'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        function editUserRole(userId) {
            // Implementation for role editing modal
            alert('Role editing functionality - User ID: ' + userId);
        }
        </script>
        
        <style>
        .wcefp-role-management .wcefp-role-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .wcefp-role-management .wcefp-stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .wcefp-role-management .wcefp-stat-box h3 {
            margin-top: 0;
            color: #666;
            font-size: 14px;
        }
        
        .wcefp-role-management .wcefp-stat-box .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .wcefp-role-management .wcefp-role-actions,
        .wcefp-role-management .wcefp-role-capabilities {
            margin: 30px 0;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for updating user roles
     */
    public function ajax_update_user_role() {
        check_ajax_referer('wcefp_admin_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $user_id = absint($_POST['user_id']);
        $new_role = sanitize_text_field($_POST['new_role']);
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(__('User not found', 'wceventsfp'));
        }
        
        // Validate role
        $valid_roles = ['wcefp_event_viewer', 'wcefp_event_manager', 'wcefp_advanced_event_manager'];
        if (!in_array($new_role, $valid_roles)) {
            wp_send_json_error(__('Invalid role specified', 'wceventsfp'));
        }
        
        // Update user role
        $user->set_role($new_role);
        
        DiagnosticLogger::instance()->info('User role updated', [
            'user_id' => $user_id,
            'new_role' => $new_role,
            'updated_by' => get_current_user_id()
        ], 'role_management');
        
        wp_send_json_success([
            'message' => sprintf(__('User role updated to %s', 'wceventsfp'), $new_role)
        ]);
    }
    
    /**
     * AJAX handler for bulk role actions
     */
    public function ajax_bulk_role_action() {
        check_ajax_referer('wcefp_admin_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        $user_ids = array_map('absint', $_POST['user_ids']);
        
        $processed = 0;
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if (!$user) continue;
            
            switch ($action) {
                case 'promote_to_manager':
                    $user->set_role('wcefp_event_manager');
                    $processed++;
                    break;
                    
                case 'demote_to_viewer':
                    $user->set_role('wcefp_event_viewer');
                    $processed++;
                    break;
                    
                case 'remove_wcefp_access':
                    // Remove all WCEFP capabilities but keep other roles
                    foreach (array_merge($this->event_manager_capabilities, $this->advanced_capabilities) as $cap => $granted) {
                        $user->remove_cap($cap);
                    }
                    $processed++;
                    break;
            }
        }
        
        DiagnosticLogger::instance()->info('Bulk role action completed', [
            'action' => $action,
            'users_processed' => $processed,
            'total_users' => count($user_ids),
            'updated_by' => get_current_user_id()
        ], 'role_management');
        
        wp_send_json_success([
            'message' => sprintf(__('%d users processed', 'wceventsfp'), $processed)
        ]);
    }
    
    /**
     * Filter admin menu based on user capabilities
     */
    public function filter_admin_menu() {
        global $menu, $submenu;
        
        if (current_user_can('manage_options')) {
            return; // Administrators see everything
        }
        
        // Get current user's capabilities
        $current_user = wp_get_current_user();
        
        // Filter main menu items
        foreach ($menu as $key => $menu_item) {
            if (isset($menu_item[1]) && strpos($menu_item[1], 'wcefp') !== false) {
                $required_cap = $menu_item[1];
                if (!RolesCapabilities::current_user_can($required_cap)) {
                    unset($menu[$key]);
                }
            }
        }
        
        // Filter submenu items
        if (isset($submenu['wcefp-dashboard'])) {
            foreach ($submenu['wcefp-dashboard'] as $key => $submenu_item) {
                $page_slug = $submenu_item[2];
                if (!RolesCapabilities::can_access_admin_page($page_slug)) {
                    unset($submenu['wcefp-dashboard'][$key]);
                }
            }
        }
    }
    
    /**
     * Get user's effective WCEFP role
     * 
     * @param int|WP_User $user
     * @return string
     */
    public function get_user_wcefp_role($user) {
        if (!$user instanceof \WP_User) {
            $user = get_user_by('id', $user);
        }
        
        if (!$user) {
            return 'none';
        }
        
        $user_roles = $user->roles;
        
        // Check for WCEFP-specific roles first
        if (in_array('wcefp_advanced_event_manager', $user_roles)) {
            return 'advanced_event_manager';
        } elseif (in_array('wcefp_event_manager', $user_roles)) {
            return 'event_manager';
        } elseif (in_array('wcefp_event_viewer', $user_roles)) {
            return 'event_viewer';
        }
        
        // Check for WordPress roles with WCEFP capabilities
        if (in_array('administrator', $user_roles)) {
            return 'administrator';
        } elseif (in_array('shop_manager', $user_roles)) {
            return 'shop_manager';
        } elseif (in_array('editor', $user_roles) && $user->has_cap('view_wcefp_dashboard')) {
            return 'editor';
        }
        
        return 'none';
    }
    
    /**
     * Check if user can perform specific WCEFP action
     * 
     * @param string $action
     * @param int|WP_User $user
     * @return bool
     */
    public function user_can_perform_action($action, $user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }
        
        if (!$user instanceof \WP_User) {
            $user = get_user_by('id', $user);
        }
        
        if (!$user) {
            return false;
        }
        
        $required_capability = RolesCapabilities::get_required_capability($action);
        
        if (!$required_capability) {
            return false;
        }
        
        return $user->has_cap($required_capability);
    }
}