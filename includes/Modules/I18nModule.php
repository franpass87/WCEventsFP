<?php
namespace WCEFP\Modules;

use WCEFP\Contracts\ModuleInterface;
use WCEFP\Core\Security\SecurityManager;
use WCEFP\Core\Performance\PerformanceManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * I18n Module - Complete internationalization and accessibility
 */
class I18nModule implements ModuleInterface
{
    private SecurityManager $security;
    private PerformanceManager $performance;
    private array $supported_locales = [];
    private string $text_domain = 'wceventsfp';
    
    public function __construct(SecurityManager $security, PerformanceManager $performance)
    {
        $this->security = $security;
        $this->performance = $performance;
        $this->init_supported_locales();
    }

    public function init(): void
    {
        // Load textdomain early with proper priority
        add_action('init', [$this, 'load_textdomain'], 1);
        
        // Generate POT file on demand
        add_action('wp_ajax_wcefp_generate_pot', [$this, 'ajax_generate_pot']);
        
        // Frontend localization
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_i18n']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_i18n']);
        
        // Accessibility enhancements
        add_action('wp_head', [$this, 'add_accessibility_styles']);
        add_action('wp_footer', [$this, 'add_accessibility_scripts']);
        
        // Language switching support
        add_filter('locale', [$this, 'override_locale']);
        add_filter('wcefp_frontend_strings', [$this, 'get_localized_strings']);
        
        // REST API localization
        add_action('rest_api_init', [$this, 'register_i18n_endpoints']);
        
        // Admin interface
        add_action('wcefp_settings_i18n_section', [$this, 'render_i18n_settings']);
    }

    public function get_priority(): int
    {
        return 1; // Load early for textdomain
    }

    public function get_dependencies(): array
    {
        return ['SecurityModule', 'PerformanceModule'];
    }

    /**
     * Initialize supported locales for booking industry
     */
    private function init_supported_locales(): void
    {
        $this->supported_locales = [
            'en_US' => [
                'name' => 'English (US)',
                'native' => 'English',
                'flag' => '🇺🇸',
                'currency' => 'USD',
                'date_format' => 'M d, Y',
                'time_format' => 'g:i A',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'completion' => 100
            ],
            'it_IT' => [
                'name' => 'Italian',
                'native' => 'Italiano',
                'flag' => '🇮🇹',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'completion' => 95
            ],
            'es_ES' => [
                'name' => 'Spanish',
                'native' => 'Español',
                'flag' => '🇪🇸',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'completion' => 80
            ],
            'fr_FR' => [
                'name' => 'French',
                'native' => 'Français',
                'flag' => '🇫🇷',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => ' ',
                'direction' => 'ltr',
                'completion' => 75
            ],
            'de_DE' => [
                'name' => 'German',
                'native' => 'Deutsch',
                'flag' => '🇩🇪',
                'currency' => 'EUR',
                'date_format' => 'd.m.Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'completion' => 70
            ],
            'pt_BR' => [
                'name' => 'Portuguese (Brazil)',
                'native' => 'Português (Brasil)',
                'flag' => '🇧🇷',
                'currency' => 'BRL',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'completion' => 60
            ],
            'ja' => [
                'name' => 'Japanese',
                'native' => '日本語',
                'flag' => '🇯🇵',
                'currency' => 'JPY',
                'date_format' => 'Y年m月d日',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'completion' => 50
            ],
            'zh_CN' => [
                'name' => 'Chinese (Simplified)',
                'native' => '简体中文',
                'flag' => '🇨🇳',
                'currency' => 'CNY',
                'date_format' => 'Y年m月d日',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'completion' => 45
            ]
        ];
    }

    /**
     * Load text domain with proper fallbacks
     */
    public function load_textdomain(): void
    {
        $domain = $this->text_domain;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);
        
        // Try WordPress language packs first
        $mofile = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
        if (file_exists($mofile)) {
            load_textdomain($domain, $mofile);
            return;
        }
        
        // Fallback to plugin languages directory
        $result = load_plugin_textdomain(
            $domain,
            false,
            dirname(plugin_basename(WCEFP_PLUGIN_FILE)) . '/languages/'
        );
        
        // Log loading result for debugging
        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WCEventsFP: Failed to load textdomain for locale: {$locale}");
        }
    }

    /**
     * Generate POT file dynamically
     */
    public function ajax_generate_pot(): void
    {
        if (!$this->security->verify_nonce('wcefp_i18n_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!$this->security->current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $pot_file = $this->generate_pot_file();
            
            wp_send_json_success([
                'message' => __('POT file generated successfully', 'wceventsfp'),
                'file' => $pot_file,
                'strings_count' => $this->count_translatable_strings(),
                'timestamp' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Generate POT file by scanning source code
     */
    private function generate_pot_file(): string
    {
        $pot_file = WCEFP_PLUGIN_DIR . 'languages/' . $this->text_domain . '.pot';
        
        // POT file header
        $header = $this->get_pot_header();
        
        // Scan for translatable strings
        $strings = $this->extract_translatable_strings();
        
        // Generate POT content
        $pot_content = $header . "\n\n";
        foreach ($strings as $string) {
            $pot_content .= $this->format_pot_entry($string) . "\n\n";
        }
        
        // Write POT file
        if (!file_put_contents($pot_file, $pot_content)) {
            throw new \Exception('Failed to write POT file');
        }
        
        return $pot_file;
    }

    /**
     * Get POT file header
     */
    private function get_pot_header(): string
    {
        $version = defined('WCEFP_VERSION') ? WCEFP_VERSION : '1.0.0';
        $date = date('Y-m-d H:i+0000');
        
        return '# WCEventsFP Translation File
# Copyright (C) ' . date('Y') . ' WCEventsFP
# This file is distributed under the same license as the WCEventsFP package.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: WCEventsFP ' . $version . '\\n"
"Report-Msgid-Bugs-To: https://github.com/franpass87/WCEventsFP/issues\\n"
"POT-Creation-Date: ' . $date . '\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Generator: WCEventsFP I18n Module\\n"';
    }

    /**
     * Extract translatable strings from PHP files
     */
    private function extract_translatable_strings(): array
    {
        $strings = [];
        $functions = ['__', '_e', '_x', '_ex', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e'];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(WCEFP_PLUGIN_DIR)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->isDir()) {
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            
            // Extract strings using regex
            foreach ($functions as $function) {
                $pattern = '/' . preg_quote($function) . '\s*\(\s*[\'"]([^\'"]+)[\'"].*?' . preg_quote($this->text_domain) . '/';
                if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $string = $match[1];
                        $context = str_replace(WCEFP_PLUGIN_DIR, '', $file->getPathname());
                        
                        $strings[] = [
                            'msgid' => $string,
                            'msgstr' => '',
                            'file' => $context,
                            'line' => $this->get_line_number($content, $match[0])
                        ];
                    }
                }
            }
        }
        
        // Remove duplicates and sort
        $strings = array_unique($strings, SORT_REGULAR);
        usort($strings, function($a, $b) {
            return strcmp($a['file'], $b['file']);
        });
        
        return $strings;
    }

    /**
     * Get line number for a match in content
     */
    private function get_line_number(string $content, string $match): int
    {
        $pos = strpos($content, $match);
        if ($pos === false) return 1;
        
        return substr_count($content, "\n", 0, $pos) + 1;
    }

    /**
     * Format POT entry
     */
    private function format_pot_entry(array $string): string
    {
        $entry = "#: {$string['file']}:{$string['line']}\n";
        $entry .= 'msgid "' . addslashes($string['msgid']) . '"' . "\n";
        $entry .= 'msgstr ""';
        
        return $entry;
    }

    /**
     * Count translatable strings
     */
    private function count_translatable_strings(): int
    {
        return count($this->extract_translatable_strings());
    }

    /**
     * Enqueue frontend i18n scripts
     */
    public function enqueue_frontend_i18n(): void
    {
        if (!$this->should_load_i18n()) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-i18n',
            WCEFP_PLUGIN_URL . 'assets/js/i18n.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-i18n', 'wcefp_i18n', [
            'current_locale' => get_locale(),
            'supported_locales' => $this->supported_locales,
            'text_domain' => $this->text_domain,
            'strings' => $this->get_frontend_strings(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_i18n_nonce')
        ]);
    }

    /**
     * Enqueue admin i18n scripts
     */
    public function enqueue_admin_i18n(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wcefp') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-admin-i18n',
            WCEFP_PLUGIN_URL . 'assets/js/admin-i18n.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-admin-i18n', 'wcefp_admin_i18n', [
            'current_locale' => get_locale(),
            'supported_locales' => $this->supported_locales,
            'admin_strings' => $this->get_admin_strings(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_i18n_nonce')
        ]);
    }

    /**
     * Check if i18n should load on current page
     */
    private function should_load_i18n(): bool
    {
        global $post;
        
        // Load on shortcode pages
        if ($post && has_shortcode($post->post_content, 'wcefp_')) {
            return true;
        }
        
        // Load on WooCommerce product pages with WCEFP events
        if (is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product();
            if ($product && $product->get_meta('_wcefp_event_data')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add accessibility styles
     */
    public function add_accessibility_styles(): void
    {
        if (!$this->should_load_accessibility()) {
            return;
        }
        
        echo '<style id="wcefp-accessibility">';
        echo $this->get_accessibility_css();
        echo '</style>';
    }

    /**
     * Add accessibility scripts
     */
    public function add_accessibility_scripts(): void
    {
        if (!$this->should_load_accessibility()) {
            return;
        }
        
        ?>
        <script id="wcefp-accessibility-js">
        (function($) {
            'use strict';
            
            // Accessibility enhancements
            $(document).ready(function() {
                wcefpAccessibility.init();
            });
            
            window.wcefpAccessibility = {
                init: function() {
                    this.enhanceFocus();
                    this.improveLabels();
                    this.addAriaSupport();
                    this.handleKeyboardNavigation();
                },
                
                enhanceFocus: function() {
                    $('.wcefp-booking-form input, .wcefp-booking-form select, .wcefp-booking-form button')
                        .on('focus', function() {
                            $(this).addClass('wcefp-focused');
                        })
                        .on('blur', function() {
                            $(this).removeClass('wcefp-focused');
                        });
                },
                
                improveLabels: function() {
                    $('.wcefp-booking-form input:not([id])').each(function(i) {
                        var id = 'wcefp-input-' + i;
                        $(this).attr('id', id);
                        
                        var label = $(this).siblings('label, .wcefp-label');
                        if (label.length) {
                            label.attr('for', id);
                        }
                    });
                },
                
                addAriaSupport: function() {
                    $('.wcefp-booking-form .wcefp-error').attr('role', 'alert');
                    $('.wcefp-loading').attr('aria-live', 'polite');
                    
                    $('[data-wcefp-tooltip]').each(function() {
                        var tooltip = $(this).data('wcefp-tooltip');
                        $(this).attr('aria-label', tooltip);
                    });
                },
                
                handleKeyboardNavigation: function() {
                    $('.wcefp-calendar .wcefp-date').on('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            $(this).click();
                        }
                    });
                    
                    $('.wcefp-tabs [role="tab"]').on('keydown', function(e) {
                        var $tabs = $('.wcefp-tabs [role="tab"]');
                        var currentIndex = $tabs.index(this);
                        
                        switch(e.key) {
                            case 'ArrowLeft':
                                e.preventDefault();
                                var prevIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                                $tabs.eq(prevIndex).focus().click();
                                break;
                            case 'ArrowRight':
                                e.preventDefault();
                                var nextIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                                $tabs.eq(nextIndex).focus().click();
                                break;
                        }
                    });
                }
            };
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Check if accessibility should load
     */
    private function should_load_accessibility(): bool
    {
        return $this->should_load_i18n();
    }

    /**
     * Get accessibility CSS
     */
    private function get_accessibility_css(): string
    {
        return '
        .wcefp-focused {
            outline: 2px solid #0073aa !important;
            outline-offset: 2px;
        }
        
        .wcefp-skip-link {
            position: absolute;
            left: -9999px;
        }
        
        .wcefp-skip-link:focus {
            position: fixed;
            top: 6px;
            left: 6px;
            z-index: 999999;
            padding: 8px 16px;
            background: #000;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
        }
        
        .wcefp-sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        
        .wcefp-booking-form [required]:invalid {
            border-color: #d63638;
        }
        
        .wcefp-error {
            color: #d63638;
            font-weight: 600;
        }
        
        @media (prefers-reduced-motion: reduce) {
            .wcefp-booking-form * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        @media (prefers-color-scheme: dark) {
            .wcefp-booking-form {
                background-color: #1a1a1a;
                color: #ffffff;
            }
        }
        
        .wcefp-high-contrast {
            filter: contrast(150%);
        }
        ';
    }

    /**
     * Override locale for specific requests
     */
    public function override_locale(string $locale): string
    {
        // Allow AJAX locale switching
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $requested_locale = sanitize_locale_name($_REQUEST['locale'] ?? '');
            if ($requested_locale && isset($this->supported_locales[$requested_locale])) {
                return $requested_locale;
            }
        }
        
        return $locale;
    }

    /**
     * Get localized strings for frontend
     */
    public function get_localized_strings(): array
    {
        return [
            'booking' => [
                'book_now' => __('Book Now', 'wceventsfp'),
                'check_availability' => __('Check Availability', 'wceventsfp'),
                'select_date' => __('Select Date', 'wceventsfp'),
                'select_time' => __('Select Time', 'wceventsfp'),
                'participants' => __('Participants', 'wceventsfp'),
                'adults' => __('Adults', 'wceventsfp'),
                'children' => __('Children', 'wceventsfp'),
                'total_price' => __('Total Price', 'wceventsfp'),
                'confirm_booking' => __('Confirm Booking', 'wceventsfp'),
                'booking_confirmed' => __('Booking Confirmed!', 'wceventsfp'),
                'cancel_booking' => __('Cancel Booking', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'please_wait' => __('Please wait...', 'wceventsfp')
            ],
            'experience' => [
                'duration' => __('Duration', 'wceventsfp'),
                'meeting_point' => __('Meeting Point', 'wceventsfp'),
                'whats_included' => __('What\'s Included', 'wceventsfp'),
                'requirements' => __('Requirements', 'wceventsfp'),
                'reviews' => __('Reviews', 'wceventsfp'),
                'gallery' => __('Gallery', 'wceventsfp'),
                'description' => __('Description', 'wceventsfp'),
                'highlights' => __('Highlights', 'wceventsfp')
            ],
            'calendar' => [
                'previous_month' => __('Previous Month', 'wceventsfp'),
                'next_month' => __('Next Month', 'wceventsfp'),
                'available' => __('Available', 'wceventsfp'),
                'fully_booked' => __('Fully Booked', 'wceventsfp'),
                'closed' => __('Closed', 'wceventsfp'),
                'select_date_first' => __('Please select a date first', 'wceventsfp')
            ],
            'errors' => [
                'system_error' => __('A system error occurred. Please try again.', 'wceventsfp'),
                'booking_full' => __('Sorry, this experience is fully booked.', 'wceventsfp'),
                'invalid_date' => __('Please select a valid date.', 'wceventsfp'),
                'invalid_participants' => __('Please specify number of participants.', 'wceventsfp'),
                'payment_required' => __('Payment information is required.', 'wceventsfp'),
                'network_error' => __('Network error. Please check your connection.', 'wceventsfp')
            ],
            'accessibility' => [
                'skip_to_content' => __('Skip to main content', 'wceventsfp'),
                'skip_to_booking' => __('Skip to booking form', 'wceventsfp'),
                'calendar_navigation' => __('Use arrow keys to navigate calendar', 'wceventsfp'),
                'booking_form' => __('Booking form', 'wceventsfp'),
                'required_field' => __('Required field', 'wceventsfp'),
                'optional_field' => __('Optional field', 'wceventsfp')
            ]
        ];
    }

    /**
     * Get frontend strings
     */
    public function get_frontend_strings(): array
    {
        return $this->get_localized_strings();
    }

    /**
     * Get admin strings for backend
     */
    private function get_admin_strings(): array
    {
        return [
            'general' => [
                'save_changes' => __('Save Changes', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'success' => __('Success', 'wceventsfp'),
                'error' => __('Error', 'wceventsfp'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'wceventsfp'),
                'no_results' => __('No results found', 'wceventsfp')
            ],
            'bookings' => [
                'booking_details' => __('Booking Details', 'wceventsfp'),
                'customer_info' => __('Customer Information', 'wceventsfp'),
                'event_info' => __('Event Information', 'wceventsfp'),
                'payment_status' => __('Payment Status', 'wceventsfp'),
                'booking_status' => __('Booking Status', 'wceventsfp'),
                'actions' => __('Actions', 'wceventsfp')
            ],
            'settings' => [
                'general_settings' => __('General Settings', 'wceventsfp'),
                'email_settings' => __('Email Settings', 'wceventsfp'),
                'feature_flags' => __('Feature Flags', 'wceventsfp'),
                'integrations' => __('Integrations', 'wceventsfp'),
                'i18n_settings' => __('Internationalization', 'wceventsfp')
            ]
        ];
    }

    /**
     * Register REST API endpoints for i18n
     */
    public function register_i18n_endpoints(): void
    {
        register_rest_route('wcefp/v1', '/i18n/locales', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supported_locales_api'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('wcefp/v1', '/i18n/strings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_strings_api'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * API endpoint to get supported locales
     */
    public function get_supported_locales_api(\WP_REST_Request $request)
    {
        return rest_ensure_response([
            'current_locale' => get_locale(),
            'supported_locales' => $this->supported_locales,
            'text_domain' => $this->text_domain
        ]);
    }

    /**
     * API endpoint to get localized strings
     */
    public function get_strings_api(\WP_REST_Request $request)
    {
        $locale = $request->get_param('locale') ?: get_locale();
        
        // Switch locale temporarily
        $original_locale = get_locale();
        if ($locale !== $original_locale && isset($this->supported_locales[$locale])) {
            switch_to_locale($locale);
        }
        
        $strings = $this->get_localized_strings();
        
        // Restore original locale
        if ($locale !== $original_locale) {
            restore_current_locale();
        }
        
        return rest_ensure_response([
            'locale' => $locale,
            'strings' => $strings
        ]);
    }

    /**
     * Render i18n settings section
     */
    public function render_i18n_settings(): void
    {
        ?>
        <div class="wcefp-settings-section">
            <h3><?php _e('Language and Localization', 'wceventsfp'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Default Language', 'wceventsfp'); ?></th>
                    <td>
                        <select name="wcefp_default_locale" id="wcefp_default_locale">
                            <?php 
                            $current = get_option('wcefp_default_locale', get_locale());
                            foreach ($this->supported_locales as $code => $info): 
                            ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($current, $code); ?>>
                                    <?php echo esc_html($info['flag'] . ' ' . $info['native']); ?> 
                                    (<?php echo esc_html($info['completion']); ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Default language for frontend booking forms and customer communications.', 'wceventsfp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Auto-detect Language', 'wceventsfp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wcefp_auto_detect_locale" value="1" 
                                   <?php checked(get_option('wcefp_auto_detect_locale', true)); ?>>
                            <?php _e('Automatically detect user language from browser settings', 'wceventsfp'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Translation Tools', 'wceventsfp'); ?></th>
                    <td>
                        <button type="button" id="wcefp-generate-pot" class="button button-secondary">
                            <?php _e('Generate POT File', 'wceventsfp'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Generate translation template for translators.', 'wceventsfp'); ?>
                        </p>
                        
                        <div id="wcefp-translation-status">
                            <h4><?php _e('Translation Status', 'wceventsfp'); ?></h4>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Language', 'wceventsfp'); ?></th>
                                        <th><?php _e('Completion', 'wceventsfp'); ?></th>
                                        <th><?php _e('Status', 'wceventsfp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->supported_locales as $code => $info): ?>
                                        <tr>
                                            <td><?php echo esc_html($info['flag'] . ' ' . $info['native']); ?></td>
                                            <td>
                                                <div class="wcefp-progress-bar">
                                                    <div class="wcefp-progress-fill" 
                                                         style="width: <?php echo esc_attr($info['completion']); ?>%"></div>
                                                    <span><?php echo esc_html($info['completion']); ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($info['completion'] >= 90): ?>
                                                    <span class="wcefp-status-complete"><?php _e('Complete', 'wceventsfp'); ?></span>
                                                <?php elseif ($info['completion'] >= 70): ?>
                                                    <span class="wcefp-status-good"><?php _e('Good', 'wceventsfp'); ?></span>
                                                <?php else: ?>
                                                    <span class="wcefp-status-needs-work"><?php _e('Needs Work', 'wceventsfp'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
        .wcefp-progress-bar {
            position: relative;
            width: 100px;
            height: 20px;
            background: #f0f0f1;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .wcefp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #d63638 0%, #dba617 50%, #00a32a 100%);
            transition: width 0.3s ease;
        }
        
        .wcefp-progress-bar span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 11px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .wcefp-status-complete { color: #00a32a; font-weight: 600; }
        .wcefp-status-good { color: #dba617; font-weight: 600; }
        .wcefp-status-needs-work { color: #d63638; font-weight: 600; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wcefp-generate-pot').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_js(__('Generating...', 'wceventsfp')); ?>');
                
                $.post(ajaxurl, {
                    action: 'wcefp_generate_pot',
                    nonce: '<?php echo wp_create_nonce('wcefp_i18n_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php esc_js(__('POT file generated successfully!', 'wceventsfp')); ?>');
                    } else {
                        alert('<?php esc_js(__('Error generating POT file.', 'wceventsfp')); ?>');
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('<?php esc_js(__('Generate POT File', 'wceventsfp')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get supported locales
     */
    public function get_supported_locales(): array
    {
        return $this->supported_locales;
    }
}