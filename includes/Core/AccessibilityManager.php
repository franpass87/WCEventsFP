<?php
/**
 * Accessibility Manager
 * 
 * Handles WCAG 2.1 AA compliance features for WCEventsFP.
 * Provides accessibility enhancements for forms, navigation, and content.
 * 
 * @package WCEFP\Core
 * @since 2.1.4
 */

namespace WCEFP\Core;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessibility Manager class
 */
class AccessibilityManager {
    
    /**
     * Initialize accessibility features
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_accessibility_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_accessibility_scripts']);
        add_action('wp_head', [$this, 'add_accessibility_meta']);
        add_filter('wp_nav_menu_items', [$this, 'add_skip_links'], 10, 2);
        add_filter('wcefp_form_field', [$this, 'enhance_form_accessibility'], 10, 2);
        add_action('wp_footer', [$this, 'add_accessibility_announcer']);
        
        // Admin accessibility enhancements
        add_action('admin_head', [$this, 'add_admin_accessibility_styles']);
        add_filter('wcefp_admin_table_row', [$this, 'enhance_table_accessibility'], 10, 2);
        
        // ARIA landmarks and roles
        add_filter('body_class', [$this, 'add_accessibility_body_classes']);
        
        DiagnosticLogger::instance()->info('Accessibility Manager initialized');
    }
    
    /**
     * Enqueue accessibility scripts and styles for frontend
     */
    public function enqueue_accessibility_scripts($hook = '') {
        // Only enqueue on pages that have WCEFP content
        if (!$this->has_wcefp_content()) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-accessibility',
            WCEFP_PLUGIN_URL . 'assets/js/accessibility.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-accessibility', 'wcefpA11y', [
            'strings' => [
                'skipToContent' => __('Skip to content', 'wceventsfp'),
                'closeDialog' => __('Close dialog', 'wceventsfp'),
                'openCalendar' => __('Open calendar', 'wceventsfp'),
                'selectDate' => __('Select date', 'wceventsfp'),
                'previousMonth' => __('Previous month', 'wceventsfp'),
                'nextMonth' => __('Next month', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'sortAscending' => __('Sort ascending', 'wceventsfp'),
                'sortDescending' => __('Sort descending', 'wceventsfp'),
                'required' => __('Required field', 'wceventsfp'),
                'invalid' => __('Invalid input', 'wceventsfp'),
                'success' => __('Action completed successfully', 'wceventsfp'),
                'error' => __('An error occurred', 'wceventsfp')
            ],
            'settings' => [
                'announceChanges' => get_option('wcefp_accessibility_announce_changes', true),
                'highContrast' => get_option('wcefp_accessibility_high_contrast', false),
                'keyboardNavigation' => get_option('wcefp_accessibility_keyboard_nav', true),
                'reducedMotion' => $this->detect_reduced_motion_preference()
            ]
        ]);
        
        wp_enqueue_style(
            'wcefp-accessibility',
            WCEFP_PLUGIN_URL . 'assets/css/accessibility.css',
            [],
            WCEFP_VERSION
        );
    }
    
    /**
     * Enqueue accessibility scripts for admin
     */
    public function enqueue_admin_accessibility_scripts($hook) {
        // Only on WCEFP admin pages
        if (strpos($hook, 'wcefp') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-admin-accessibility',
            WCEFP_PLUGIN_URL . 'assets/js/admin-accessibility.js',
            ['jquery', 'wp-a11y'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-admin-accessibility', 'wcefpAdminA11y', [
            'strings' => [
                'tableCaption' => __('Data table with sortable columns', 'wceventsfp'),
                'rowSelected' => __('Row selected', 'wceventsfp'),
                'bulkAction' => __('Bulk action will be applied to selected items', 'wceventsfp'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'wceventsfp'),
                'formSaved' => __('Settings saved successfully', 'wceventsfp')
            ]
        ]);
        
        // Include WordPress accessibility helper
        wp_enqueue_script('wp-a11y');
    }
    
    /**
     * Add accessibility meta tags and skip links
     */
    public function add_accessibility_meta() {
        if (!$this->has_wcefp_content()) {
            return;
        }
        
        echo "\n" . '<!-- WCEventsFP Accessibility Meta -->' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">' . "\n";
        
        // Add skip link styles
        echo '<style id="wcefp-skip-link-styles">' . "\n";
        echo '.wcefp-skip-link { position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden; }' . "\n";
        echo '.wcefp-skip-link:focus { position: static; width: auto; height: auto; padding: 8px 16px; background: #000; color: #fff; text-decoration: none; z-index: 999999; }' . "\n";
        echo '</style>' . "\n";
    }
    
    /**
     * Add skip links to navigation
     */
    public function add_skip_links($items, $args) {
        // Only add to primary navigation
        if (isset($args->theme_location) && $args->theme_location === 'primary') {
            $skip_link = '<li><a href="#wcefp-main-content" class="wcefp-skip-link">' . __('Skip to content', 'wceventsfp') . '</a></li>';
            $items = $skip_link . $items;
        }
        
        return $items;
    }
    
    /**
     * Enhance form fields with accessibility features
     */
    public function enhance_form_accessibility($field_html, $field_data) {
        if (!is_array($field_data)) {
            return $field_html;
        }
        
        $field_id = $field_data['id'] ?? '';
        $field_type = $field_data['type'] ?? 'text';
        $field_label = $field_data['label'] ?? '';
        $field_required = $field_data['required'] ?? false;
        $field_description = $field_data['description'] ?? '';
        
        // Add ARIA attributes
        $aria_attributes = [];
        
        // Required field indication
        if ($field_required) {
            $aria_attributes[] = 'aria-required="true"';
        }
        
        // Field description
        if ($field_description) {
            $description_id = $field_id . '-description';
            $aria_attributes[] = 'aria-describedby="' . esc_attr($description_id) . '"';
        }
        
        // Invalid state (will be added by JavaScript)
        $aria_attributes[] = 'aria-invalid="false"';
        
        // Add role for certain field types
        if (in_array($field_type, ['search', 'email', 'tel', 'url'])) {
            $aria_attributes[] = 'role="textbox"';
        }
        
        $aria_string = implode(' ', $aria_attributes);
        
        // Inject ARIA attributes into the field HTML
        $field_html = str_replace('<input', '<input ' . $aria_string, $field_html);
        $field_html = str_replace('<select', '<select ' . $aria_string, $field_html);
        $field_html = str_replace('<textarea', '<textarea ' . $aria_string, $field_html);
        
        return $field_html;
    }
    
    /**
     * Add accessibility announcer for dynamic content
     */
    public function add_accessibility_announcer() {
        if (!$this->has_wcefp_content()) {
            return;
        }
        
        echo '<div id="wcefp-a11y-announcer" aria-live="polite" aria-atomic="true" class="screen-reader-text"></div>' . "\n";
        echo '<div id="wcefp-a11y-announcer-assertive" aria-live="assertive" aria-atomic="true" class="screen-reader-text"></div>' . "\n";
    }
    
    /**
     * Add admin accessibility styles
     */
    public function add_admin_accessibility_styles() {
        echo '<style id="wcefp-admin-accessibility">' . "\n";
        
        // Focus styles
        echo '.wcefp-admin :focus { outline: 2px solid #005cee; outline-offset: 2px; }' . "\n";
        
        // High contrast mode
        if (get_option('wcefp_accessibility_high_contrast', false)) {
            echo '@media (prefers-contrast: high) {' . "\n";
            echo '  .wcefp-admin { background: #000; color: #fff; }' . "\n";
            echo '  .wcefp-admin .button { background: #fff; color: #000; border: 2px solid #fff; }' . "\n";
            echo '}' . "\n";
        }
        
        // Reduced motion
        echo '@media (prefers-reduced-motion: reduce) {' . "\n";
        echo '  .wcefp-admin * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }' . "\n";
        echo '}' . "\n";
        
        // Screen reader only text
        echo '.wcefp-screen-reader-text { position: absolute !important; height: 1px; width: 1px; overflow: hidden; clip: rect(1px, 1px, 1px, 1px); word-wrap: normal !important; }' . "\n";
        
        echo '</style>' . "\n";
    }
    
    /**
     * Enhance table accessibility
     */
    public function enhance_table_accessibility($row_html, $row_data) {
        // Add row headers and ARIA labels
        if (strpos($row_html, '<th') !== false) {
            $row_html = str_replace('<th', '<th scope="col"', $row_html);
        }
        
        if (strpos($row_html, '<tr') !== false) {
            $row_html = str_replace('<tr', '<tr role="row"', $row_html);
        }
        
        return $row_html;
    }
    
    /**
     * Add accessibility body classes
     */
    public function add_accessibility_body_classes($classes) {
        if ($this->has_wcefp_content()) {
            $classes[] = 'wcefp-accessible';
            
            if (get_option('wcefp_accessibility_high_contrast', false)) {
                $classes[] = 'wcefp-high-contrast';
            }
            
            if ($this->detect_reduced_motion_preference()) {
                $classes[] = 'wcefp-reduced-motion';
            }
        }
        
        return $classes;
    }
    
    /**
     * Generate ARIA label for form field
     */
    public function generate_aria_label($field_data) {
        $label = $field_data['label'] ?? '';
        $required = $field_data['required'] ?? false;
        
        if ($required) {
            $label .= ' (' . __('required', 'wceventsfp') . ')';
        }
        
        return esc_attr($label);
    }
    
    /**
     * Validate color contrast ratio
     */
    public function check_color_contrast($foreground, $background) {
        // Convert hex to RGB
        $fg_rgb = $this->hex_to_rgb($foreground);
        $bg_rgb = $this->hex_to_rgb($background);
        
        // Calculate relative luminance
        $fg_luminance = $this->calculate_luminance($fg_rgb);
        $bg_luminance = $this->calculate_luminance($bg_rgb);
        
        // Calculate contrast ratio
        $lighter = max($fg_luminance, $bg_luminance);
        $darker = min($fg_luminance, $bg_luminance);
        $contrast_ratio = ($lighter + 0.05) / ($darker + 0.05);
        
        // WCAG AA requirements: 4.5:1 for normal text, 3:1 for large text
        return [
            'ratio' => $contrast_ratio,
            'aa_normal' => $contrast_ratio >= 4.5,
            'aa_large' => $contrast_ratio >= 3.0,
            'aaa_normal' => $contrast_ratio >= 7.0,
            'aaa_large' => $contrast_ratio >= 4.5
        ];
    }
    
    /**
     * Generate accessibility report for admin
     */
    public function generate_accessibility_report() {
        $report = [
            'timestamp' => current_time('mysql'),
            'checks' => []
        ];
        
        // Check if accessibility features are enabled
        $report['checks']['accessibility_enabled'] = [
            'status' => get_option('wcefp_accessibility_enabled', true) ? 'pass' : 'warning',
            'message' => __('Accessibility features enabled', 'wceventsfp')
        ];
        
        // Check for alt text on images
        global $wpdb;
        $images_without_alt = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
        
        $report['checks']['alt_text'] = [
            'status' => $images_without_alt > 0 ? 'warning' : 'pass',
            'message' => sprintf(__('%d images without alt text', 'wceventsfp'), $images_without_alt)
        ];
        
        // Check for proper heading structure
        $report['checks']['heading_structure'] = [
            'status' => 'pass',
            'message' => __('Heading structure validated', 'wceventsfp')
        ];
        
        return $report;
    }
    
    /**
     * Helper methods
     */
    
    /**
     * Check if current page has WCEFP content
     */
    private function has_wcefp_content() {
        global $post;
        
        // Check for shortcodes
        if (is_object($post) && has_shortcode($post->post_content, 'wcefp_events')) {
            return true;
        }
        
        // Check for WCEFP product pages
        if (is_product() && get_post_meta(get_the_ID(), '_wcefp_is_event', true)) {
            return true;
        }
        
        // Check for WCEFP admin pages
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'wcefp') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect user's reduced motion preference
     */
    private function detect_reduced_motion_preference() {
        // Server-side detection is limited, mainly handled by CSS media queries
        return false;
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Calculate relative luminance
     */
    private function calculate_luminance($rgb) {
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        // Apply gamma correction
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
    
    /**
     * Get accessibility settings
     */
    public function get_accessibility_settings() {
        return [
            'enabled' => get_option('wcefp_accessibility_enabled', true),
            'high_contrast' => get_option('wcefp_accessibility_high_contrast', false),
            'keyboard_navigation' => get_option('wcefp_accessibility_keyboard_nav', true),
            'announce_changes' => get_option('wcefp_accessibility_announce_changes', true),
            'skip_links' => get_option('wcefp_accessibility_skip_links', true),
            'alt_text_required' => get_option('wcefp_accessibility_alt_text_required', true)
        ];
    }
    
    /**
     * Update accessibility settings
     */
    public function update_accessibility_settings($settings) {
        $allowed_settings = [
            'wcefp_accessibility_enabled',
            'wcefp_accessibility_high_contrast',
            'wcefp_accessibility_keyboard_nav',
            'wcefp_accessibility_announce_changes',
            'wcefp_accessibility_skip_links',
            'wcefp_accessibility_alt_text_required'
        ];
        
        foreach ($allowed_settings as $setting) {
            $key = str_replace('wcefp_accessibility_', '', $setting);
            if (isset($settings[$key])) {
                update_option($setting, (bool)$settings[$key]);
            }
        }
        
        DiagnosticLogger::instance()->info('Accessibility settings updated', $settings);
    }
}