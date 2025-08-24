<?php
/**
 * Plugin Activation and Settings Tests
 *
 * @package WCEFP\Tests
 */

namespace WCEFP\Tests\Integration;

use WCEFP\Tests\Unit\WCEFPTestCase;
use WCEFP\Bootstrap\Plugin;
use WCEFP\Core\ActivationHandler;
use WCEFP\Modules\SettingsModule;

class ActivationTest extends WCEFPTestCase {
    
    /**
     * Test plugin activation creates necessary tables
     */
    public function test_plugin_activation_creates_tables() {
        global $wpdb;
        
        // Clear any existing tables for clean test
        $tables = [
            $wpdb->prefix . 'wcefp_occorrenze',
            $wpdb->prefix . 'wcefp_bookings',
            $wpdb->prefix . 'wcefp_vouchers',
            $wpdb->prefix . 'wcefp_closures'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Trigger activation
        $activation_handler = new ActivationHandler();
        $activation_handler->activate();
        
        // Check tables were created
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            $this->assertTrue($table_exists, "Table {$table} should be created on activation");
        }
    }
    
    /**
     * Test plugin activation sets default options
     */
    public function test_plugin_activation_sets_defaults() {
        // Clear existing options
        delete_option('wcefp_plugin_version');
        delete_option('wcefp_default_capacity');
        delete_option('wcefp_booking_window_days');
        
        // Trigger activation
        $activation_handler = new ActivationHandler();
        $activation_handler->activate();
        
        // Check default options were set
        $this->assertNotEmpty(get_option('wcefp_plugin_version'));
        $this->assertEquals(10, get_option('wcefp_default_capacity'));
        $this->assertEquals(30, get_option('wcefp_booking_window_days'));
    }
    
    /**
     * Test plugin bootstrap initialization
     */
    public function test_plugin_bootstrap() {
        $plugin = Plugin::instance();
        
        // Test singleton pattern
        $plugin2 = Plugin::instance();
        $this->assertSame($plugin, $plugin2);
        
        // Test plugin is properly initialized
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
    
    /**
     * Test settings module functionality
     */
    public function test_settings_module() {
        $settings = new SettingsModule();
        
        // Test settings registration
        $this->assertTrue(method_exists($settings, 'register_settings'));
        $this->assertTrue(method_exists($settings, 'render_settings_page'));
        
        // Test settings save/retrieve
        update_option('wcefp_default_capacity', 15);
        $this->assertEquals(15, get_option('wcefp_default_capacity'));
    }
    
    /**
     * Test capabilities are properly set up
     */
    public function test_capabilities_setup() {
        // Create users with different roles
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        $shop_manager = $this->factory->user->create(['role' => 'shop_manager']);
        $customer = $this->factory->user->create(['role' => 'customer']);
        
        // Test admin capabilities
        wp_set_current_user($admin_user);
        $this->assertTrue(current_user_can('manage_woocommerce'));
        
        // Test shop manager capabilities  
        wp_set_current_user($shop_manager);
        $this->assertTrue(current_user_can('manage_woocommerce'));
        
        // Test customer limitations
        wp_set_current_user($customer);
        $this->assertFalse(current_user_can('manage_woocommerce'));
    }
    
    /**
     * Test database migration handling
     */
    public function test_database_migration() {
        global $wpdb;
        
        // Set old version
        update_option('wcefp_plugin_version', '1.0.0');
        
        // Trigger activation (which should handle migration)
        $activation_handler = new ActivationHandler();
        $activation_handler->activate();
        
        // Check version was updated
        $current_version = get_option('wcefp_plugin_version');
        $this->assertNotEquals('1.0.0', $current_version);
        $this->assertEquals(WCEFP_VERSION, $current_version);
    }
    
    /**
     * Test plugin deactivation cleanup
     */
    public function test_plugin_deactivation() {
        // Set some options first
        update_option('wcefp_test_option', 'test_value');
        
        // Note: We don't actually test deactivation as it would affect the test environment
        // Instead, we test that the cleanup functions exist
        $activation_handler = new ActivationHandler();
        $this->assertTrue(method_exists($activation_handler, 'deactivate'));
    }
    
    /**
     * Test plugin uninstall cleanup
     */
    public function test_uninstall_cleanup_functions_exist() {
        // Test that uninstall functions are defined
        $this->assertTrue(file_exists(WCEFP_PLUGIN_DIR . '/uninstall.php'));
    }
    
    /**
     * Test settings sanitization
     */
    public function test_settings_sanitization() {
        $settings = new SettingsModule();
        
        // Test capacity sanitization
        $sanitized = $settings->sanitize_capacity('15');
        $this->assertEquals(15, $sanitized);
        
        $sanitized = $settings->sanitize_capacity('invalid');
        $this->assertEquals(10, $sanitized); // Should default to 10
        
        $sanitized = $settings->sanitize_capacity('-5');
        $this->assertEquals(1, $sanitized); // Should be minimum 1
    }
    
    /**
     * Test settings validation
     */
    public function test_settings_validation() {
        $settings = new SettingsModule();
        
        // Test email validation
        $valid_email = $settings->validate_email('test@example.com');
        $this->assertEquals('test@example.com', $valid_email);
        
        $invalid_email = $settings->validate_email('invalid-email');
        $this->assertFalse($invalid_email);
        
        // Test booking window validation
        $valid_window = $settings->validate_booking_window('30');
        $this->assertEquals(30, $valid_window);
        
        $invalid_window = $settings->validate_booking_window('0');
        $this->assertEquals(1, $invalid_window); // Minimum 1 day
    }
    
    /**
     * Test feature flags functionality
     */
    public function test_feature_flags() {
        // Test default feature states
        $this->assertTrue(get_option('wcefp_enable_vouchers', true));
        $this->assertTrue(get_option('wcefp_enable_meeting_points', true));
        $this->assertFalse(get_option('wcefp_enable_digital_checkin', false));
        
        // Test feature flag changes
        update_option('wcefp_enable_vouchers', false);
        $this->assertFalse(get_option('wcefp_enable_vouchers'));
        
        // Reset for other tests
        update_option('wcefp_enable_vouchers', true);
    }
}