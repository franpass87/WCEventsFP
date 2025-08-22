<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Enhanced Internationalization System
 * Provides advanced i18n features for global market expansion
 */
class WCEFP_I18n_Enhancement {
    
    private static $instance = null;
    private $supported_locales = [];
    private $current_locale = '';
    private $translations_cache = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->current_locale = get_locale();
        $this->init_supported_locales();
        
        // Hook into WordPress i18n system
        add_action('init', [$this, 'load_plugin_textdomain']);
        add_action('wp_ajax_wcefp_get_translations', [$this, 'ajax_get_translations']);
        add_action('wp_ajax_nopriv_wcefp_get_translations', [$this, 'ajax_get_translations']);
        
        // Add language switcher hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_i18n_scripts']);
        add_filter('wcefp_booking_form_data', [$this, 'localize_booking_form']);
        add_filter('wcefp_frontend_strings', [$this, 'get_frontend_strings']);
    }
    
    /**
     * Initialize supported locales with booking industry focus
     */
    private function init_supported_locales() {
        $this->supported_locales = [
            'en_US' => [
                'name' => 'English (US)',
                'flag' => '🇺🇸',
                'currency' => 'USD',
                'date_format' => 'M d, Y',
                'time_format' => 'g:i A',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'priority' => 1
            ],
            'en_GB' => [
                'name' => 'English (UK)',
                'flag' => '🇬🇧',
                'currency' => 'GBP',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'priority' => 2
            ],
            'it_IT' => [
                'name' => 'Italiano',
                'flag' => '🇮🇹',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'priority' => 3
            ],
            'es_ES' => [
                'name' => 'Español',
                'flag' => '🇪🇸',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'priority' => 4
            ],
            'fr_FR' => [
                'name' => 'Français',
                'flag' => '🇫🇷',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => ' ',
                'direction' => 'ltr',
                'priority' => 5
            ],
            'de_DE' => [
                'name' => 'Deutsch',
                'flag' => '🇩🇪',
                'currency' => 'EUR',
                'date_format' => 'd.m.Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'priority' => 6
            ],
            'pt_BR' => [
                'name' => 'Português (Brasil)',
                'flag' => '🇧🇷',
                'currency' => 'BRL',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'direction' => 'ltr',
                'priority' => 7
            ],
            'ja' => [
                'name' => '日本語',
                'flag' => '🇯🇵',
                'currency' => 'JPY',
                'date_format' => 'Y年m月d日',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'priority' => 8
            ],
            'ko_KR' => [
                'name' => '한국어',
                'flag' => '🇰🇷',
                'currency' => 'KRW',
                'date_format' => 'Y년 m월 d일',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'priority' => 9
            ],
            'zh_CN' => [
                'name' => '简体中文',
                'flag' => '🇨🇳',
                'currency' => 'CNY',
                'date_format' => 'Y年m月d日',
                'time_format' => 'H:i',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'direction' => 'ltr',
                'priority' => 10
            ]
        ];
    }
    
    /**
     * Load plugin text domain with fallbacks
     */
    public function load_plugin_textdomain() {
        $domain = 'wceventsfp';
        $locale = apply_filters('plugin_locale', get_locale(), $domain);
        
        // Try to load from WP_LANG_DIR first (for updates via translate.wordpress.org)
        $mo_file = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
        if (file_exists($mo_file)) {
            load_textdomain($domain, $mo_file);
            return;
        }
        
        // Fallback to plugin directory
        load_plugin_textdomain($domain, false, dirname(plugin_basename(WCEFP_PLUGIN_FILE)) . '/languages/');
        
        // Auto-generate missing translations for supported locales
        $this->auto_generate_missing_strings($locale);
    }
    
    /**
     * Auto-generate basic translations for key booking terms
     */
    private function auto_generate_missing_strings($locale) {
        if (!isset($this->supported_locales[$locale])) {
            return;
        }
        
        $basic_translations = $this->get_basic_booking_translations($locale);
        
        // Cache for quick access
        $this->translations_cache[$locale] = $basic_translations;
    }
    
    /**
     * Get basic booking translations for emergency fallback
     */
    private function get_basic_booking_translations($locale) {
        $translations = [
            'en_US' => [
                'Book Now' => 'Book Now',
                'Check Availability' => 'Check Availability',
                'Select Date' => 'Select Date',
                'Participants' => 'Participants',
                'Total Price' => 'Total Price',
                'Confirm Booking' => 'Confirm Booking',
                'Booking Confirmed' => 'Booking Confirmed',
                'Sorry, fully booked' => 'Sorry, this experience is fully booked',
                'Duration' => 'Duration',
                'Meeting Point' => 'Meeting Point',
                'What\'s Included' => 'What\'s Included',
                'Reviews' => 'Reviews',
                'Cancel Booking' => 'Cancel Booking',
                'Loading...' => 'Loading...'
            ],
            'it_IT' => [
                'Book Now' => 'Prenota Ora',
                'Check Availability' => 'Verifica Disponibilità',
                'Select Date' => 'Seleziona Data',
                'Participants' => 'Partecipanti',
                'Total Price' => 'Prezzo Totale',
                'Confirm Booking' => 'Conferma Prenotazione',
                'Booking Confirmed' => 'Prenotazione Confermata',
                'Sorry, fully booked' => 'Spiacenti, esperienza al completo',
                'Duration' => 'Durata',
                'Meeting Point' => 'Punto di Incontro',
                'What\'s Included' => 'Cosa è Incluso',
                'Reviews' => 'Recensioni',
                'Cancel Booking' => 'Cancella Prenotazione',
                'Loading...' => 'Caricamento...'
            ],
            'es_ES' => [
                'Book Now' => 'Reservar Ahora',
                'Check Availability' => 'Verificar Disponibilidad',
                'Select Date' => 'Seleccionar Fecha',
                'Participants' => 'Participantes',
                'Total Price' => 'Precio Total',
                'Confirm Booking' => 'Confirmar Reserva',
                'Booking Confirmed' => 'Reserva Confirmada',
                'Sorry, fully booked' => 'Lo sentimos, experiencia completa',
                'Duration' => 'Duración',
                'Meeting Point' => 'Punto de Encuentro',
                'What\'s Included' => 'Qué Incluye',
                'Reviews' => 'Reseñas',
                'Cancel Booking' => 'Cancelar Reserva',
                'Loading...' => 'Cargando...'
            ],
            'fr_FR' => [
                'Book Now' => 'Réserver Maintenant',
                'Check Availability' => 'Vérifier la Disponibilité',
                'Select Date' => 'Sélectionner une Date',
                'Participants' => 'Participants',
                'Total Price' => 'Prix Total',
                'Confirm Booking' => 'Confirmer la Réservation',
                'Booking Confirmed' => 'Réservation Confirmée',
                'Sorry, fully booked' => 'Désolé, expérience complète',
                'Duration' => 'Durée',
                'Meeting Point' => 'Point de Rendez-vous',
                'What\'s Included' => 'Ce qui est Inclus',
                'Reviews' => 'Avis',
                'Cancel Booking' => 'Annuler la Réservation',
                'Loading...' => 'Chargement...'
            ],
            'de_DE' => [
                'Book Now' => 'Jetzt Buchen',
                'Check Availability' => 'Verfügbarkeit Prüfen',
                'Select Date' => 'Datum Auswählen',
                'Participants' => 'Teilnehmer',
                'Total Price' => 'Gesamtpreis',
                'Confirm Booking' => 'Buchung Bestätigen',
                'Booking Confirmed' => 'Buchung Bestätigt',
                'Sorry, fully booked' => 'Entschuldigung, Erlebnis ausgebucht',
                'Duration' => 'Dauer',
                'Meeting Point' => 'Treffpunkt',
                'What\'s Included' => 'Was ist Inbegriffen',
                'Reviews' => 'Bewertungen',
                'Cancel Booking' => 'Buchung Stornieren',
                'Loading...' => 'Laden...'
            ]
        ];
        
        return $translations[$locale] ?? $translations['en_US'];
    }
    
    /**
     * Get localized string with fallback
     */
    public function get_string($key, $locale = null) {
        if (!$locale) {
            $locale = $this->current_locale;
        }
        
        // Try WordPress translation first
        $translated = __($key, 'wceventsfp');
        if ($translated !== $key) {
            return $translated;
        }
        
        // Fallback to cached translations
        if (isset($this->translations_cache[$locale][$key])) {
            return $this->translations_cache[$locale][$key];
        }
        
        // Final fallback to English
        if (isset($this->translations_cache['en_US'][$key])) {
            return $this->translations_cache['en_US'][$key];
        }
        
        return $key; // Return key if no translation found
    }
    
    /**
     * Format price according to locale
     */
    public function format_price($amount, $locale = null) {
        if (!$locale) {
            $locale = $this->current_locale;
        }
        
        $locale_info = $this->supported_locales[$locale] ?? $this->supported_locales['en_US'];
        
        $formatted_amount = number_format(
            $amount,
            2,
            $locale_info['decimal_separator'],
            $locale_info['thousands_separator']
        );
        
        return $locale_info['currency'] . ' ' . $formatted_amount;
    }
    
    /**
     * Format date according to locale
     */
    public function format_date($date, $locale = null) {
        if (!$locale) {
            $locale = $this->current_locale;
        }
        
        $locale_info = $this->supported_locales[$locale] ?? $this->supported_locales['en_US'];
        
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return $date->format($this->php_date_format($locale_info['date_format']));
    }
    
    /**
     * Convert display date format to PHP date format
     */
    private function php_date_format($display_format) {
        $replacements = [
            'M' => 'M',
            'd' => 'd',
            'Y' => 'Y',
            '年' => 'Y年',
            '月' => 'm月',
            '日' => 'd日'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $display_format);
    }
    
    /**
     * Enqueue i18n scripts for frontend
     */
    public function enqueue_i18n_scripts() {
        wp_enqueue_script(
            'wcefp-i18n',
            WCEFP_PLUGIN_URL . 'assets/js/i18n-enhancement.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-i18n', 'wcefp_i18n', [
            'current_locale' => $this->current_locale,
            'supported_locales' => $this->supported_locales,
            'frontend_strings' => $this->get_frontend_strings(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_i18n_nonce')
        ]);
    }
    
    /**
     * Get frontend translatable strings
     */
    public function get_frontend_strings($locale = null) {
        if (!$locale) {
            $locale = $this->current_locale;
        }
        
        return [
            'booking' => [
                'book_now' => $this->get_string('Book Now', $locale),
                'check_availability' => $this->get_string('Check Availability', $locale),
                'select_date' => $this->get_string('Select Date', $locale),
                'participants' => $this->get_string('Participants', $locale),
                'total_price' => $this->get_string('Total Price', $locale),
                'confirm_booking' => $this->get_string('Confirm Booking', $locale),
                'booking_confirmed' => $this->get_string('Booking Confirmed', $locale),
                'fully_booked' => $this->get_string('Sorry, fully booked', $locale),
                'loading' => $this->get_string('Loading...', $locale)
            ],
            'experience' => [
                'duration' => $this->get_string('Duration', $locale),
                'meeting_point' => $this->get_string('Meeting Point', $locale),
                'whats_included' => $this->get_string('What\'s Included', $locale),
                'reviews' => $this->get_string('Reviews', $locale),
                'cancel_booking' => $this->get_string('Cancel Booking', $locale)
            ],
            'errors' => [
                'system_error' => __('A system error occurred. Please try again.', 'wceventsfp'),
                'booking_full' => __('Sorry, this experience is fully booked.', 'wceventsfp'),
                'invalid_date' => __('Please select a valid date.', 'wceventsfp'),
                'payment_failed' => __('Payment could not be processed.', 'wceventsfp')
            ]
        ];
    }
    
    /**
     * AJAX handler to get translations dynamically
     */
    public function ajax_get_translations() {
        check_ajax_referer('wcefp_i18n_nonce', 'nonce');
        
        $locale = sanitize_text_field($_POST['locale'] ?? $this->current_locale);
        $strings = $_POST['strings'] ?? [];
        
        if (!isset($this->supported_locales[$locale])) {
            wp_send_json_error('Unsupported locale');
        }
        
        $translations = [];
        foreach ($strings as $string) {
            $translations[$string] = $this->get_string($string, $locale);
        }
        
        wp_send_json_success([
            'locale' => $locale,
            'translations' => $translations,
            'locale_info' => $this->supported_locales[$locale]
        ]);
    }
    
    /**
     * Localize booking form data
     */
    public function localize_booking_form($data) {
        $data['i18n'] = [
            'locale' => $this->current_locale,
            'locale_info' => $this->supported_locales[$this->current_locale] ?? $this->supported_locales['en_US'],
            'strings' => $this->get_frontend_strings()
        ];
        
        return $data;
    }
    
    /**
     * Get supported locales
     */
    public function get_supported_locales() {
        return $this->supported_locales;
    }
    
    /**
     * Check if locale is RTL
     */
    public function is_rtl($locale = null) {
        if (!$locale) {
            $locale = $this->current_locale;
        }
        
        $rtl_locales = ['ar', 'he_IL', 'fa_IR'];
        return in_array($locale, $rtl_locales);
    }
    
    /**
     * Initialize i18n enhancements
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize i18n enhancements
WCEFP_I18n_Enhancement::init();