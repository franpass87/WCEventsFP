<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Accessibility Enhancement
 * Provides WCAG 2.1 AA compliance improvements
 */
class WCEFP_Accessibility_Enhancement {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add accessibility features
        add_action('wp_enqueue_scripts', [$this, 'enqueue_accessibility_assets']);
        add_action('wp_footer', [$this, 'add_accessibility_features']);
        
        // AJAX handlers for accessibility
        add_action('wp_ajax_wcefp_toggle_high_contrast', [$this, 'toggle_high_contrast']);
        add_action('wp_ajax_nopriv_wcefp_toggle_high_contrast', [$this, 'toggle_high_contrast']);
        
        // Filter existing content for accessibility
        add_filter('wcefp_booking_form_html', [$this, 'enhance_form_accessibility']);
        add_filter('wcefp_calendar_html', [$this, 'enhance_calendar_accessibility']);
        
        // Add accessibility admin settings
        add_action('wcefp_admin_settings_accessibility', [$this, 'render_accessibility_settings']);
    }
    
    /**
     * Enqueue accessibility assets
     */
    public function enqueue_accessibility_assets() {
        wp_register_style(
            'wcefp-accessibility',
            WCEFP_PLUGIN_URL . 'assets/css/accessibility.css',
            ['wcefp-frontend'],
            WCEFP_VERSION
        );
        
        wp_register_script(
            'wcefp-accessibility',
            WCEFP_PLUGIN_URL . 'assets/js/accessibility.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        // Enqueue if accessibility features are enabled
        if (get_option('wcefp_accessibility_enabled', true)) {
            wp_enqueue_style('wcefp-accessibility');
            wp_enqueue_script('wcefp-accessibility');
            
            wp_localize_script('wcefp-accessibility', 'WCEFPAccessibility', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_accessibility'),
                'strings' => [
                    'highContrast' => __('Toggle High Contrast', 'wceventsfp'),
                    'textSize' => __('Text Size', 'wceventsfp'),
                    'skipToContent' => __('Skip to main content', 'wceventsfp'),
                    'skipToNavigation' => __('Skip to navigation', 'wceventsfp'),
                    'bookingForm' => __('Booking form', 'wceventsfp'),
                    'calendar' => __('Event calendar', 'wceventsfp'),
                    'loading' => __('Loading, please wait...', 'wceventsfp'),
                    'bookingSuccess' => __('Booking completed successfully', 'wceventsfp'),
                    'bookingError' => __('Booking failed, please try again', 'wceventsfp')
                ]
            ]);
        }
    }
    
    /**
     * Add accessibility toolbar and features
     */
    public function add_accessibility_features() {
        if (!get_option('wcefp_accessibility_toolbar_enabled', true)) {
            return;
        }
        
        ?>
        <div id="wcefp-accessibility-toolbar" class="wcefp-a11y-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Accessibility options', 'wceventsfp'); ?>">
            <button type="button" class="wcefp-a11y-skip-link" onclick="document.getElementById('main').focus()">
                <?php esc_html_e('Skip to main content', 'wceventsfp'); ?>
            </button>
            
            <div class="wcefp-a11y-controls">
                <button type="button" 
                        id="wcefp-high-contrast-toggle" 
                        class="wcefp-a11y-btn"
                        aria-pressed="false"
                        title="<?php esc_attr_e('Toggle high contrast mode', 'wceventsfp'); ?>">
                    <span class="wcefp-a11y-icon" aria-hidden="true">◑</span>
                    <span class="wcefp-a11y-text"><?php esc_html_e('High Contrast', 'wceventsfp'); ?></span>
                </button>
                
                <button type="button" 
                        id="wcefp-text-size-increase" 
                        class="wcefp-a11y-btn"
                        title="<?php esc_attr_e('Increase text size', 'wceventsfp'); ?>">
                    <span class="wcefp-a11y-icon" aria-hidden="true">A+</span>
                    <span class="wcefp-a11y-text"><?php esc_html_e('Larger Text', 'wceventsfp'); ?></span>
                </button>
                
                <button type="button" 
                        id="wcefp-focus-indicators-toggle" 
                        class="wcefp-a11y-btn"
                        aria-pressed="false"
                        title="<?php esc_attr_e('Toggle enhanced focus indicators', 'wceventsfp'); ?>">
                    <span class="wcefp-a11y-icon" aria-hidden="true">◯</span>
                    <span class="wcefp-a11y-text"><?php esc_html_e('Focus Mode', 'wceventsfp'); ?></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enhance booking form accessibility
     */
    public function enhance_form_accessibility($html) {
        // Add ARIA labels and roles
        $html = str_replace(
            '<form',
            '<form role="form" aria-labelledby="wcefp-booking-title"',
            $html
        );
        
        // Add fieldset and legend for form groups
        $html = preg_replace(
            '/(<div class="wcefp-form-group">)/',
            '$1<fieldset><legend class="wcefp-sr-only">',
            $html
        );
        
        // Enhance input fields
        $html = preg_replace_callback(
            '/<input([^>]*?)>/i',
            function($matches) {
                $input = $matches[0];
                
                // Add aria-required for required fields
                if (strpos($input, 'required') !== false && strpos($input, 'aria-required') === false) {
                    $input = str_replace('>', ' aria-required="true">', $input);
                }
                
                // Add aria-describedby for inputs with help text
                if (preg_match('/name="([^"]+)"/', $input, $nameMatch)) {
                    $name = $nameMatch[1];
                    $input = str_replace('>', ' aria-describedby="' . $name . '-help">', $input);
                }
                
                return $input;
            },
            $html
        );
        
        // Add live regions for dynamic content
        $html .= '<div id="wcefp-booking-status" class="wcefp-sr-only" aria-live="polite" aria-atomic="true"></div>';
        $html .= '<div id="wcefp-booking-errors" class="wcefp-sr-only" aria-live="assertive" aria-atomic="true"></div>';
        
        return $html;
    }
    
    /**
     * Enhance calendar accessibility
     */
    public function enhance_calendar_accessibility($html) {
        // Add calendar role and labels
        $html = str_replace(
            '<div id="wcefp-calendar"',
            '<div id="wcefp-calendar" role="application" aria-label="' . esc_attr__('Event booking calendar', 'wceventsfp') . '"',
            $html
        );
        
        // Add navigation instructions
        $instructions = sprintf(
            '<div class="wcefp-calendar-instructions wcefp-sr-only" id="wcefp-calendar-instructions">%s</div>',
            esc_html__('Use arrow keys to navigate calendar. Press Enter to select a date. Press Escape to close calendar.', 'wceventsfp')
        );
        
        $html = $instructions . $html;
        
        // Add aria-describedby reference
        $html = str_replace(
            'role="application"',
            'role="application" aria-describedby="wcefp-calendar-instructions"',
            $html
        );
        
        return $html;
    }
    
    /**
     * Toggle high contrast mode
     */
    public function toggle_high_contrast() {
        check_ajax_referer('wcefp_accessibility', 'nonce');
        
        $enabled = get_user_meta(get_current_user_id(), 'wcefp_high_contrast', true);
        $enabled = !$enabled;
        
        if (get_current_user_id()) {
            update_user_meta(get_current_user_id(), 'wcefp_high_contrast', $enabled);
        } else {
            // For guests, use session
            if (!session_id()) {
                session_start();
            }
            $_SESSION['wcefp_high_contrast'] = $enabled;
        }
        
        wp_send_json_success([
            'enabled' => $enabled,
            'message' => $enabled 
                ? __('High contrast mode enabled', 'wceventsfp')
                : __('High contrast mode disabled', 'wceventsfp')
        ]);
    }
    
    /**
     * Check if user has high contrast enabled
     */
    public function is_high_contrast_enabled() {
        if (get_current_user_id()) {
            return get_user_meta(get_current_user_id(), 'wcefp_high_contrast', true);
        }
        
        if (!session_id()) {
            session_start();
        }
        
        return !empty($_SESSION['wcefp_high_contrast']);
    }
    
    /**
     * Render accessibility admin settings
     */
    public function render_accessibility_settings() {
        $enabled = get_option('wcefp_accessibility_enabled', true);
        $toolbar_enabled = get_option('wcefp_accessibility_toolbar_enabled', true);
        $skip_links = get_option('wcefp_accessibility_skip_links', true);
        ?>
        <div class="wcefp-settings-section">
            <h3><?php esc_html_e('Accessibility Settings', 'wceventsfp'); ?></h3>
            <p class="description">
                <?php esc_html_e('Configure accessibility features to ensure WCAG 2.1 AA compliance.', 'wceventsfp'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcefp_accessibility_enabled">
                            <?php esc_html_e('Enable Accessibility Features', 'wceventsfp'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="wcefp_accessibility_enabled" 
                                   name="wcefp_accessibility_enabled" 
                                   value="1" 
                                   <?php checked($enabled); ?> />
                            <?php esc_html_e('Enable enhanced accessibility features', 'wceventsfp'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Adds ARIA labels, keyboard navigation, and screen reader support.', 'wceventsfp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wcefp_accessibility_toolbar_enabled">
                            <?php esc_html_e('Accessibility Toolbar', 'wceventsfp'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="wcefp_accessibility_toolbar_enabled" 
                                   name="wcefp_accessibility_toolbar_enabled" 
                                   value="1" 
                                   <?php checked($toolbar_enabled); ?> />
                            <?php esc_html_e('Show accessibility toolbar on frontend', 'wceventsfp'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Displays accessibility controls like high contrast toggle and text size controls.', 'wceventsfp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wcefp_accessibility_skip_links">
                            <?php esc_html_e('Skip Navigation Links', 'wceventsfp'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="wcefp_accessibility_skip_links" 
                                   name="wcefp_accessibility_skip_links" 
                                   value="1" 
                                   <?php checked($skip_links); ?> />
                            <?php esc_html_e('Add skip navigation links', 'wceventsfp'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Allows keyboard users to skip directly to main content.', 'wceventsfp'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get accessibility status for monitoring
     */
    public function get_accessibility_status() {
        return [
            'enabled' => get_option('wcefp_accessibility_enabled', true),
            'toolbar_enabled' => get_option('wcefp_accessibility_toolbar_enabled', true),
            'wcag_level' => '2.1 AA', // Target compliance level
            'features' => [
                'aria_labels' => true,
                'keyboard_navigation' => true,
                'screen_reader_support' => true,
                'high_contrast_mode' => true,
                'focus_indicators' => true,
                'skip_links' => get_option('wcefp_accessibility_skip_links', true)
            ]
        ];
    }
    
    /**
     * Initialize accessibility enhancements
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize accessibility enhancements
WCEFP_Accessibility_Enhancement::init();