<?php
/**
 * Internationalization Manager
 * 
 * Handles i18n/l10n functionality for WCEventsFP.
 * Manages translations, locale-specific formatting, and RTL support.
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
 * Internationalization Manager class
 */
class InternationalizationManager {
    
    /**
     * Supported locales with their configurations
     */
    private $supported_locales = [
        'en_US' => [
            'name' => 'English (US)',
            'native_name' => 'English',
            'rtl' => false,
            'date_format' => 'm/d/Y',
            'time_format' => 'g:i A',
            'currency_position' => 'left',
            'decimal_separator' => '.',
            'thousand_separator' => ','
        ],
        'it_IT' => [
            'name' => 'Italian',
            'native_name' => 'Italiano',
            'rtl' => false,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'currency_position' => 'right',
            'decimal_separator' => ',',
            'thousand_separator' => '.'
        ],
        'es_ES' => [
            'name' => 'Spanish',
            'native_name' => 'Español',
            'rtl' => false,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'currency_position' => 'right',
            'decimal_separator' => ',',
            'thousand_separator' => '.'
        ],
        'fr_FR' => [
            'name' => 'French',
            'native_name' => 'Français',
            'rtl' => false,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'currency_position' => 'right',
            'decimal_separator' => ',',
            'thousand_separator' => ' '
        ],
        'de_DE' => [
            'name' => 'German',
            'native_name' => 'Deutsch',
            'rtl' => false,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'currency_position' => 'right',
            'decimal_separator' => ',',
            'thousand_separator' => '.'
        ]
    ];
    
    /**
     * Current locale configuration
     */
    private $current_locale_config = null;
    
    /**
     * Initialize internationalization
     */
    public function __construct() {
        add_action('init', [$this, 'setup_locale'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_i18n_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_i18n_scripts']);
        
        // Date and time formatting
        add_filter('wcefp_format_date', [$this, 'format_date'], 10, 2);
        add_filter('wcefp_format_time', [$this, 'format_time'], 10, 2);
        add_filter('wcefp_format_datetime', [$this, 'format_datetime'], 10, 2);
        
        // Number and currency formatting
        add_filter('wcefp_format_number', [$this, 'format_number'], 10, 2);
        add_filter('wcefp_format_currency', [$this, 'format_currency'], 10, 3);
        
        // Translation utilities
        add_filter('wcefp_translate_string', [$this, 'translate_string'], 10, 3);
        add_filter('wcefp_get_locale_data', [$this, 'get_locale_data']);
        
        // RTL support
        add_filter('body_class', [$this, 'add_rtl_body_class']);
        add_action('wp_head', [$this, 'add_rtl_styles']);
        
        DiagnosticLogger::instance()->info('Internationalization Manager initialized');
    }
    
    /**
     * Setup current locale configuration
     */
    public function setup_locale() {
        $current_locale = determine_locale();
        
        // Fallback to base language if specific locale not supported
        if (!isset($this->supported_locales[$current_locale])) {
            $base_language = substr($current_locale, 0, 2);
            $fallback_locale = null;
            
            foreach ($this->supported_locales as $locale => $config) {
                if (substr($locale, 0, 2) === $base_language) {
                    $fallback_locale = $locale;
                    break;
                }
            }
            
            $current_locale = $fallback_locale ?: 'en_US';
        }
        
        $this->current_locale_config = $this->supported_locales[$current_locale];
        $this->current_locale_config['locale'] = $current_locale;
        
        // Set WordPress date/time formats if not already customized
        if (get_option('date_format') === 'F j, Y') {
            update_option('date_format', $this->current_locale_config['date_format']);
        }
        
        if (get_option('time_format') === 'g:i a') {
            update_option('time_format', $this->current_locale_config['time_format']);
        }
        
        DiagnosticLogger::instance()->info('Locale configured', [
            'locale' => $current_locale,
            'config' => $this->current_locale_config
        ]);
    }
    
    /**
     * Enqueue internationalization scripts for frontend
     */
    public function enqueue_i18n_scripts() {
        if (!$this->has_wcefp_content()) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-i18n',
            WCEFP_PLUGIN_URL . 'assets/js/i18n.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-i18n', 'wcefpI18n', [
            'locale' => $this->current_locale_config,
            'strings' => $this->get_javascript_translations(),
            'dateFormats' => $this->get_date_formats(),
            'numberFormats' => $this->get_number_formats()
        ]);
        
        // Add RTL stylesheet if needed
        if ($this->is_rtl()) {
            wp_enqueue_style(
                'wcefp-rtl',
                WCEFP_PLUGIN_URL . 'assets/css/rtl.css',
                ['wcefp-frontend'],
                WCEFP_VERSION
            );
        }
    }
    
    /**
     * Enqueue admin internationalization scripts
     */
    public function enqueue_admin_i18n_scripts($hook) {
        if (strpos($hook, 'wcefp') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-admin-i18n',
            WCEFP_PLUGIN_URL . 'assets/js/admin-i18n.js',
            ['jquery', 'wp-i18n'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-admin-i18n', 'wcefpAdminI18n', [
            'locale' => $this->current_locale_config,
            'availableLocales' => $this->get_available_translations(),
            'translationStatus' => $this->get_translation_status()
        ]);
        
        // Set up wp-i18n for Gutenberg blocks
        wp_set_script_translations('wcefp-admin-i18n', 'wceventsfp', WCEFP_PLUGIN_DIR . 'languages');
    }
    
    /**
     * Format date according to locale
     */
    public function format_date($date, $format = null) {
        if (!$date) {
            return '';
        }
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        $format = $format ?: $this->current_locale_config['date_format'];
        
        // Use WordPress locale-aware formatting
        return wp_date($format, $timestamp);
    }
    
    /**
     * Format time according to locale
     */
    public function format_time($time, $format = null) {
        if (!$time) {
            return '';
        }
        
        $timestamp = is_numeric($time) ? $time : strtotime($time);
        if (!$timestamp) {
            return $time;
        }
        
        $format = $format ?: $this->current_locale_config['time_format'];
        
        return wp_date($format, $timestamp);
    }
    
    /**
     * Format datetime according to locale
     */
    public function format_datetime($datetime, $format = null) {
        if (!$datetime) {
            return '';
        }
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }
        
        if (!$format) {
            $date_format = $this->current_locale_config['date_format'];
            $time_format = $this->current_locale_config['time_format'];
            $format = $date_format . ' ' . $time_format;
        }
        
        return wp_date($format, $timestamp);
    }
    
    /**
     * Format number according to locale
     */
    public function format_number($number, $decimals = 2) {
        if (!is_numeric($number)) {
            return $number;
        }
        
        return number_format_i18n(
            $number,
            $decimals,
            $this->current_locale_config['decimal_separator'],
            $this->current_locale_config['thousand_separator']
        );
    }
    
    /**
     * Format currency according to locale
     */
    public function format_currency($amount, $currency = null, $show_symbol = true) {
        if (!is_numeric($amount)) {
            return $amount;
        }
        
        $currency = $currency ?: get_woocommerce_currency();
        $currency_symbol = $show_symbol ? get_woocommerce_currency_symbol($currency) : '';
        
        $formatted_amount = $this->format_number($amount, 2);
        
        if ($this->current_locale_config['currency_position'] === 'left') {
            return $currency_symbol . $formatted_amount;
        } else {
            return $formatted_amount . ' ' . $currency_symbol;
        }
    }
    
    /**
     * Translate string with context support
     */
    public function translate_string($string, $context = '', $domain = 'wceventsfp') {
        if ($context) {
            return _x($string, $context, $domain);
        }
        
        return __($string, $domain);
    }
    
    /**
     * Get current locale data
     */
    public function get_locale_data() {
        return $this->current_locale_config;
    }
    
    /**
     * Check if current locale is RTL
     */
    public function is_rtl() {
        return $this->current_locale_config['rtl'] ?? false;
    }
    
    /**
     * Add RTL body class
     */
    public function add_rtl_body_class($classes) {
        if ($this->is_rtl() && $this->has_wcefp_content()) {
            $classes[] = 'wcefp-rtl';
        }
        
        return $classes;
    }
    
    /**
     * Add RTL styles to head
     */
    public function add_rtl_styles() {
        if (!$this->is_rtl() || !$this->has_wcefp_content()) {
            return;
        }
        
        echo '<style id="wcefp-rtl-inline">' . "\n";
        echo '.wcefp-container { direction: rtl; }' . "\n";
        echo '.wcefp-form-row { text-align: right; }' . "\n";
        echo '.wcefp-button { margin-left: 10px; margin-right: 0; }' . "\n";
        echo '</style>' . "\n";
    }
    
    /**
     * Get JavaScript translations
     */
    private function get_javascript_translations() {
        return [
            'loading' => __('Loading...', 'wceventsfp'),
            'error' => __('An error occurred', 'wceventsfp'),
            'success' => __('Success!', 'wceventsfp'),
            'confirm' => __('Are you sure?', 'wceventsfp'),
            'cancel' => __('Cancel', 'wceventsfp'),
            'ok' => __('OK', 'wceventsfp'),
            'close' => __('Close', 'wceventsfp'),
            'save' => __('Save', 'wceventsfp'),
            'delete' => __('Delete', 'wceventsfp'),
            'edit' => __('Edit', 'wceventsfp'),
            'view' => __('View', 'wceventsfp'),
            'search' => __('Search', 'wceventsfp'),
            'filter' => __('Filter', 'wceventsfp'),
            'clear' => __('Clear', 'wceventsfp'),
            'all' => __('All', 'wceventsfp'),
            'none' => __('None', 'wceventsfp'),
            'today' => __('Today', 'wceventsfp'),
            'tomorrow' => __('Tomorrow', 'wceventsfp'),
            'yesterday' => __('Yesterday', 'wceventsfp'),
            'selectDate' => __('Select date', 'wceventsfp'),
            'selectTime' => __('Select time', 'wceventsfp'),
            'noResults' => __('No results found', 'wceventsfp'),
            'tryAgain' => __('Try again', 'wceventsfp'),
            'invalidInput' => __('Invalid input', 'wceventsfp'),
            'requiredField' => __('This field is required', 'wceventsfp'),
            'bookingConfirmed' => __('Booking confirmed', 'wceventsfp'),
            'bookingCancelled' => __('Booking cancelled', 'wceventsfp'),
            'eventFull' => __('Event is fully booked', 'wceventsfp'),
            'eventUnavailable' => __('Event is not available', 'wceventsfp')
        ];
    }
    
    /**
     * Get date format configurations
     */
    private function get_date_formats() {
        return [
            'date' => $this->current_locale_config['date_format'],
            'time' => $this->current_locale_config['time_format'],
            'datetime' => $this->current_locale_config['date_format'] . ' ' . $this->current_locale_config['time_format'],
            'monthYear' => 'F Y',
            'weekday' => 'l',
            'weekdayShort' => 'D'
        ];
    }
    
    /**
     * Get number format configurations
     */
    private function get_number_formats() {
        return [
            'decimal' => $this->current_locale_config['decimal_separator'],
            'thousand' => $this->current_locale_config['thousand_separator'],
            'currency_position' => $this->current_locale_config['currency_position']
        ];
    }
    
    /**
     * Get available translations
     */
    public function get_available_translations() {
        $available = [];
        $languages_dir = WCEFP_PLUGIN_DIR . 'languages/';
        
        if (is_dir($languages_dir)) {
            $mo_files = glob($languages_dir . '*.mo');
            
            foreach ($mo_files as $mo_file) {
                $locale = basename($mo_file, '.mo');
                $locale = str_replace('wceventsfp-', '', $locale);
                
                if (isset($this->supported_locales[$locale])) {
                    $available[$locale] = $this->supported_locales[$locale];
                    $available[$locale]['file_exists'] = true;
                    $available[$locale]['file_path'] = $mo_file;
                    $available[$locale]['file_size'] = filesize($mo_file);
                    $available[$locale]['file_modified'] = filemtime($mo_file);
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Get translation status
     */
    public function get_translation_status() {
        $pot_file = WCEFP_PLUGIN_DIR . 'languages/wceventsfp.pot';
        $status = [
            'pot_exists' => file_exists($pot_file),
            'pot_modified' => file_exists($pot_file) ? filemtime($pot_file) : null,
            'total_strings' => 0,
            'translated_strings' => []
        ];
        
        // Count total strings in POT file
        if (file_exists($pot_file)) {
            $pot_content = file_get_contents($pot_file);
            $status['total_strings'] = substr_count($pot_content, 'msgid ');
        }
        
        // Count translated strings for each language
        $available_translations = $this->get_available_translations();
        
        foreach ($available_translations as $locale => $config) {
            $po_file = WCEFP_PLUGIN_DIR . 'languages/wceventsfp-' . $locale . '.po';
            
            if (file_exists($po_file)) {
                $po_content = file_get_contents($po_file);
                $msgstr_count = substr_count($po_content, 'msgstr "');
                $empty_msgstr = substr_count($po_content, 'msgstr ""');
                
                $status['translated_strings'][$locale] = [
                    'total' => $msgstr_count,
                    'translated' => $msgstr_count - $empty_msgstr,
                    'percentage' => $status['total_strings'] > 0 ? 
                        round((($msgstr_count - $empty_msgstr) / $status['total_strings']) * 100, 1) : 0
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Generate POT file
     */
    public function generate_pot_file($force = false) {
        $pot_file = WCEFP_PLUGIN_DIR . 'languages/wceventsfp.pot';
        
        // Check if we need to regenerate
        if (!$force && file_exists($pot_file) && (time() - filemtime($pot_file)) < HOUR_IN_SECONDS) {
            return false; // Recent POT file exists
        }
        
        // Use WP-CLI if available
        if (class_exists('WP_CLI')) {
            $command = sprintf(
                'wp i18n make-pot %s %s --domain=wceventsfp --exclude=vendor,node_modules,tests',
                WCEFP_PLUGIN_DIR,
                $pot_file
            );
            
            exec($command, $output, $return_code);
            
            if ($return_code === 0) {
                DiagnosticLogger::instance()->info('POT file generated via WP-CLI');
                return true;
            }
        }
        
        // Fallback: basic POT generation
        $this->generate_basic_pot_file($pot_file);
        
        DiagnosticLogger::instance()->info('POT file generated via fallback method');
        return true;
    }
    
    /**
     * Basic POT file generation
     */
    private function generate_basic_pot_file($pot_file) {
        $strings = [];
        
        // Scan PHP files for translatable strings
        $php_files = $this->get_php_files();
        
        foreach ($php_files as $file) {
            $file_strings = $this->extract_strings_from_file($file);
            $strings = array_merge($strings, $file_strings);
        }
        
        // Remove duplicates
        $strings = array_unique($strings);
        
        // Generate POT content
        $pot_content = $this->generate_pot_header();
        
        foreach ($strings as $string) {
            $pot_content .= "\nmsgid \"" . addslashes($string) . "\"\n";
            $pot_content .= "msgstr \"\"\n";
        }
        
        file_put_contents($pot_file, $pot_content);
    }
    
    /**
     * Get list of PHP files to scan
     */
    private function get_php_files() {
        $files = [];
        $directories = ['includes', 'admin', 'templates'];
        
        foreach ($directories as $dir) {
            $dir_path = WCEFP_PLUGIN_DIR . $dir;
            if (is_dir($dir_path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir_path)
                );
                
                foreach ($iterator as $file) {
                    if ($file->getExtension() === 'php') {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Extract translatable strings from PHP file
     */
    private function extract_strings_from_file($file) {
        $content = file_get_contents($file);
        $strings = [];
        
        // Match translation functions
        $patterns = [
            '/__\s*\(\s*[\'"]([^"\']+)[\'"]\s*,\s*[\'"]wceventsfp[\'"]/',
            '/_e\s*\(\s*[\'"]([^"\']+)[\'"]\s*,\s*[\'"]wceventsfp[\'"]/',
            '/_n\s*\(\s*[\'"]([^"\']+)[\'"]\s*,\s*[\'"]([^"\']+)[\'"]\s*,.*?,\s*[\'"]wceventsfp[\'"]/',
            '/esc_html__\s*\(\s*[\'"]([^"\']+)[\'"]\s*,\s*[\'"]wceventsfp[\'"]/',
            '/esc_attr__\s*\(\s*[\'"]([^"\']+)[\'"]\s*,\s*[\'"]wceventsfp[\'"]/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $strings = array_merge($strings, $matches[1]);
                if (isset($matches[2])) {
                    $strings = array_merge($strings, $matches[2]);
                }
            }
        }
        
        return $strings;
    }
    
    /**
     * Generate POT file header
     */
    private function generate_pot_header() {
        return '# SOME DESCRIPTIVE TITLE.
# Copyright (C) ' . date('Y') . ' THE PACKAGE\'S COPYRIGHT HOLDER
# This file is distributed under the same license as the WCEventsFP package.
# FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: WCEventsFP ' . WCEFP_VERSION . '\n"
"Report-Msgid-Bugs-To: https://github.com/franpass87/WCEventsFP/issues\n"
"POT-Creation-Date: ' . date('Y-m-d H:i') . '+0000\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
"Language-Team: LANGUAGE <LL@li.org>\n"
"Language: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
"X-Generator: WCEventsFP\n"

';
    }
    
    /**
     * Check if current page/context has WCEFP content
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
        
        // Check for admin pages
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'wcefp') !== false) {
            return true;
        }
        
        return false;
    }
}