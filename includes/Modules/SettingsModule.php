<?php
/**
 * Settings Module
 * 
 * @package WCEFP
 * @subpackage Modules
 * @since 2.1.4
 */

namespace WCEFP\Modules;

use WCEFP\Core\ServiceProvider;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings management module using WordPress Settings API
 */
class SettingsModule extends ServiceProvider {
    
    /**
     * Settings sections
     * 
     * @var array
     */
    private $sections = [
        'general' => 'General Settings',
        'email' => 'Email & Notifications',
        'features' => 'Feature Flags',
        'integrations' => 'Integrations'
    ];
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        // Register settings services
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_admin_pages'], 15);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        Logger::info('Settings module booted successfully');
    }
    
    /**
     * Register WordPress settings
     * 
     * @return void
     */
    public function register_settings(): void {
        // Register setting groups
        foreach ($this->sections as $section_key => $section_title) {
            $option_name = 'wcefp_' . $section_key . '_settings';
            
            register_setting(
                $option_name . '_group',
                $option_name,
                [$this, 'sanitize_' . $section_key . '_settings']
            );
            
            add_settings_section(
                $section_key . '_section',
                $section_title,
                [$this, 'render_' . $section_key . '_section'],
                'wcefp_' . $section_key . '_settings'
            );
        }
        
        // Register individual fields
        $this->register_general_fields();
        $this->register_email_fields();
        $this->register_feature_fields();
        $this->register_integration_fields();
    }
    
    /**
     * Register general settings fields
     * 
     * @return void
     */
    private function register_general_fields(): void {
        add_settings_field(
            'default_capacity',
            __('Default Event Capacity', 'wceventsfp'),
            [$this, 'render_number_field'],
            'wcefp_general_settings',
            'general_section',
            ['field' => 'default_capacity', 'min' => 1, 'max' => 1000]
        );
        
        add_settings_field(
            'booking_window_days',
            __('Booking Window (Days)', 'wceventsfp'),
            [$this, 'render_number_field'],
            'wcefp_general_settings',
            'general_section',
            ['field' => 'booking_window_days', 'min' => 1, 'max' => 365]
        );
        
        add_settings_field(
            'timezone',
            __('Default Timezone', 'wceventsfp'),
            [$this, 'render_select_field'],
            'wcefp_general_settings',
            'general_section',
            ['field' => 'timezone', 'options' => $this->get_timezone_options()]
        );
    }
    
    /**
     * Register email settings fields
     * 
     * @return void
     */
    private function register_email_fields(): void {
        add_settings_field(
            'admin_email_notifications',
            __('Admin Email Notifications', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_email_settings',
            'email_section',
            ['field' => 'admin_email_notifications', 'label' => __('Send notifications to admin', 'wceventsfp')]
        );
        
        add_settings_field(
            'customer_confirmation_email',
            __('Customer Confirmation Email', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_email_settings',
            'email_section',
            ['field' => 'customer_confirmation_email', 'label' => __('Send confirmation emails to customers', 'wceventsfp')]
        );
        
        add_settings_field(
            'email_from_name',
            __('Email From Name', 'wceventsfp'),
            [$this, 'render_text_field'],
            'wcefp_email_settings',
            'email_section',
            ['field' => 'email_from_name', 'placeholder' => get_bloginfo('name')]
        );
        
        add_settings_field(
            'email_from_address',
            __('Email From Address', 'wceventsfp'),
            [$this, 'render_email_field'],
            'wcefp_email_settings',
            'email_section',
            ['field' => 'email_from_address', 'placeholder' => get_option('admin_email')]
        );
    }
    
    /**
     * Register feature settings fields
     * 
     * @return void
     */
    private function register_feature_fields(): void {
        add_settings_field(
            'enable_vouchers',
            __('Enable Vouchers', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_features_settings',
            'features_section',
            ['field' => 'enable_vouchers', 'label' => __('Enable voucher system', 'wceventsfp')]
        );
        
        add_settings_field(
            'enable_meeting_points',
            __('Enable Meeting Points', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_features_settings',
            'features_section',
            ['field' => 'enable_meeting_points', 'label' => __('Enable meeting points system', 'wceventsfp')]
        );
        
        add_settings_field(
            'enable_digital_checkin',
            __('Enable Digital Check-in', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_features_settings',
            'features_section',
            ['field' => 'enable_digital_checkin', 'label' => __('Enable QR code check-in', 'wceventsfp')]
        );
    }
    
    /**
     * Register integration settings fields
     * 
     * @return void
     */
    private function register_integration_fields(): void {
        add_settings_field(
            'google_maps_api_key',
            __('Google Maps API Key', 'wceventsfp'),
            [$this, 'render_text_field'],
            'wcefp_integrations_settings',
            'integrations_section',
            ['field' => 'google_maps_api_key', 'type' => 'password']
        );
        
        add_settings_field(
            'calendar_integration',
            __('Calendar Integration', 'wceventsfp'),
            [$this, 'render_select_field'],
            'wcefp_integrations_settings',
            'integrations_section',
            ['field' => 'calendar_integration', 'options' => [
                '' => __('Disabled', 'wceventsfp'),
                'google' => __('Google Calendar', 'wceventsfp'),
                'outlook' => __('Outlook Calendar', 'wceventsfp')
            ]]
        );
    }
    
    /**
     * Add admin menu pages
     * 
     * @return void
     */
    public function add_admin_pages(): void {
        add_submenu_page(
            'wcefp-events',
            __('Settings', 'wceventsfp'),
            __('Impostazioni', 'wceventsfp'),
            'manage_options',
            'wcefp-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    public function render_settings_page(): void {
        $active_tab = sanitize_text_field($_GET['tab'] ?? 'general');
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WCEventsFP Settings', 'wceventsfp'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($this->sections as $tab_key => $tab_title): ?>
                    <a href="?page=wcefp-settings&tab=<?php echo esc_attr($tab_key); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_title); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wcefp_' . $active_tab . '_settings_group');
                do_settings_sections('wcefp_' . $active_tab . '_settings');
                submit_button();
                ?>
            </form>
        </div>
        
        <?php if (!empty($_GET['settings-updated'])): ?>
        <script>
            // Optional: Add JavaScript for enhanced UX after settings save
            jQuery(document).ready(function($) {
                $('.notice-success').delay(3000).fadeOut();
            });
        </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook
     * @return void
     */
    public function enqueue_admin_assets($hook): void {
        if ($hook !== 'wcefp-events_page_wcefp-settings') {
            return;
        }
        
        wp_enqueue_script('wcefp-settings', 
            plugin_dir_url(__FILE__) . '../../assets/js/admin-settings.js',
            ['jquery'], 
            '1.0.0', 
            true
        );
        
        wp_enqueue_style('wcefp-settings',
            plugin_dir_url(__FILE__) . '../../assets/css/admin-settings.css',
            [],
            '1.0.0'
        );
        
        wp_localize_script('wcefp-settings', 'wcefp_settings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_settings_nonce'),
            'strings' => [
                'saved' => __('Settings saved successfully', 'wceventsfp'),
                'error' => __('Error saving settings', 'wceventsfp')
            ]
        ]);
    }
    
    // Field rendering methods
    public function render_text_field($args): void {
        $option_name = 'wcefp_' . $this->get_current_section() . '_settings';
        $options = get_option($option_name, []);
        $value = $options[$args['field']] ?? '';
        $type = $args['type'] ?? 'text';
        $placeholder = $args['placeholder'] ?? '';
        
        echo '<input type="' . esc_attr($type) . '" ';
        echo 'name="' . esc_attr($option_name) . '[' . esc_attr($args['field']) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'placeholder="' . esc_attr($placeholder) . '" ';
        echo 'class="regular-text" />';
    }
    
    public function render_number_field($args): void {
        $option_name = 'wcefp_' . $this->get_current_section() . '_settings';
        $options = get_option($option_name, []);
        $value = $options[$args['field']] ?? '';
        
        echo '<input type="number" ';
        echo 'name="' . esc_attr($option_name) . '[' . esc_attr($args['field']) . ']" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'min="' . esc_attr($args['min'] ?? 0) . '" ';
        echo 'max="' . esc_attr($args['max'] ?? '') . '" ';
        echo 'class="small-text" />';
    }
    
    public function render_email_field($args): void {
        $args['type'] = 'email';
        $this->render_text_field($args);
    }
    
    public function render_checkbox_field($args): void {
        $option_name = 'wcefp_' . $this->get_current_section() . '_settings';
        $options = get_option($option_name, []);
        $checked = !empty($options[$args['field']]);
        
        echo '<label>';
        echo '<input type="checkbox" ';
        echo 'name="' . esc_attr($option_name) . '[' . esc_attr($args['field']) . ']" ';
        echo 'value="1" ';
        echo checked($checked, true, false) . ' />';
        echo ' ' . esc_html($args['label'] ?? '');
        echo '</label>';
    }
    
    public function render_select_field($args): void {
        $option_name = 'wcefp_' . $this->get_current_section() . '_settings';
        $options = get_option($option_name, []);
        $selected = $options[$args['field']] ?? '';
        
        echo '<select name="' . esc_attr($option_name) . '[' . esc_attr($args['field']) . ']">';
        foreach ($args['options'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ';
            echo selected($selected, $value, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    }
    
    // Section rendering methods
    public function render_general_section(): void {
        echo '<p>' . esc_html__('Configure general plugin behavior and defaults.', 'wceventsfp') . '</p>';
    }
    
    public function render_email_section(): void {
        echo '<p>' . esc_html__('Configure email notifications and templates.', 'wceventsfp') . '</p>';
    }
    
    public function render_features_section(): void {
        echo '<p>' . esc_html__('Enable or disable specific plugin features.', 'wceventsfp') . '</p>';
    }
    
    public function render_integrations_section(): void {
        echo '<p>' . esc_html__('Configure third-party integrations and API keys.', 'wceventsfp') . '</p>';
    }
    
    // Sanitization methods
    public function sanitize_general_settings($input): array {
        $sanitized = [];
        $sanitized['default_capacity'] = absint($input['default_capacity'] ?? 10);
        $sanitized['booking_window_days'] = absint($input['booking_window_days'] ?? 30);
        $sanitized['timezone'] = sanitize_text_field($input['timezone'] ?? 'UTC');
        return $sanitized;
    }
    
    public function sanitize_email_settings($input): array {
        $sanitized = [];
        $sanitized['admin_email_notifications'] = !empty($input['admin_email_notifications']);
        $sanitized['customer_confirmation_email'] = !empty($input['customer_confirmation_email']);
        $sanitized['email_from_name'] = sanitize_text_field($input['email_from_name'] ?? '');
        $sanitized['email_from_address'] = sanitize_email($input['email_from_address'] ?? '');
        return $sanitized;
    }
    
    public function sanitize_features_settings($input): array {
        $sanitized = [];
        $sanitized['enable_vouchers'] = !empty($input['enable_vouchers']);
        $sanitized['enable_meeting_points'] = !empty($input['enable_meeting_points']);
        $sanitized['enable_digital_checkin'] = !empty($input['enable_digital_checkin']);
        return $sanitized;
    }
    
    public function sanitize_integrations_settings($input): array {
        $sanitized = [];
        $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key'] ?? '');
        $sanitized['calendar_integration'] = sanitize_text_field($input['calendar_integration'] ?? '');
        return $sanitized;
    }
    
    /**
     * Get current section from global context
     * 
     * @return string
     */
    private function get_current_section(): string {
        return sanitize_text_field($_GET['tab'] ?? 'general');
    }
    
    /**
     * Get timezone options
     * 
     * @return array
     */
    private function get_timezone_options(): array {
        return [
            'UTC' => 'UTC',
            'Europe/Rome' => 'Europe/Rome',
            'America/New_York' => 'America/New_York',
            'America/Los_Angeles' => 'America/Los_Angeles'
        ];
    }
}