<?php
/**
 * Admin Settings Class - Tabbed Settings Page using WordPress Settings API
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Admin_Settings {

    private static $instance = null;
    private $settings_tabs = [];
    private $current_tab = 'general';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        $this->setup_tabs();
        $this->register_settings();
    }

    /**
     * Setup settings tabs
     */
    private function setup_tabs() {
        $this->settings_tabs = array(
            'general' => array(
                'title' => __('Generali', 'wceventsfp'),
                'icon' => 'dashicons-admin-generic'
            ),
            'display' => array(
                'title' => __('Visualizzazione', 'wceventsfp'),
                'icon' => 'dashicons-admin-appearance'
            ),
            'integrations' => array(
                'title' => __('Integrazioni', 'wceventsfp'),
                'icon' => 'dashicons-admin-plugins'
            ),
            'advanced' => array(
                'title' => __('Avanzate', 'wceventsfp'),
                'icon' => 'dashicons-admin-tools'
            )
        );
    }

    /**
     * Register all settings using WordPress Settings API
     */
    private function register_settings() {
        // General Settings
        register_setting('wcefp_general_settings', 'wcefp_default_capacity', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_capacity'),
            'default' => 0
        ));

        register_setting('wcefp_general_settings', 'wcefp_disable_wc_emails_for_events', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));

        register_setting('wcefp_general_settings', 'wcefp_price_rules', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_price_rules'),
            'default' => '[]'
        ));

        // Integration Settings - Brevo
        register_setting('wcefp_integrations_settings', 'wcefp_brevo_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_template_id', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_from_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => ''
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_from_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_list_it', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_list_en', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0
        ));

        register_setting('wcefp_integrations_settings', 'wcefp_brevo_tag', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        // Advanced Settings - Tracking
        register_setting('wcefp_advanced_settings', 'wcefp_ga4_enable', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_ga4_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_gtm_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_meta_pixel_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_google_ads_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_enable_server_analytics', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));

        register_setting('wcefp_advanced_settings', 'wcefp_conversion_optimization', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true
        ));

        // Add settings sections and fields
        $this->add_settings_sections();
    }

    /**
     * Add settings sections and fields
     */
    private function add_settings_sections() {
        // General Tab
        add_settings_section(
            'wcefp_general_section',
            __('Impostazioni Generali', 'wceventsfp'),
            array($this, 'general_section_callback'),
            'wcefp_general_settings'
        );

        add_settings_field(
            'wcefp_default_capacity',
            __('Capienza Default per Slot', 'wceventsfp'),
            array($this, 'default_capacity_callback'),
            'wcefp_general_settings',
            'wcefp_general_section'
        );

        add_settings_field(
            'wcefp_disable_wc_emails_for_events',
            __('Email WooCommerce', 'wceventsfp'),
            array($this, 'disable_wc_emails_callback'),
            'wcefp_general_settings',
            'wcefp_general_section'
        );

        add_settings_field(
            'wcefp_price_rules',
            __('Regole Prezzo Dinamiche', 'wceventsfp'),
            array($this, 'price_rules_callback'),
            'wcefp_general_settings',
            'wcefp_general_section'
        );

        // Integrations Tab
        add_settings_section(
            'wcefp_brevo_section',
            __('Brevo (Sendinblue) API v3', 'wceventsfp'),
            array($this, 'brevo_section_callback'),
            'wcefp_integrations_settings'
        );

        add_settings_field(
            'wcefp_brevo_api_key',
            __('API Key', 'wceventsfp'),
            array($this, 'brevo_api_key_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_template_id',
            __('Template ID', 'wceventsfp'),
            array($this, 'brevo_template_id_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_from_email',
            __('Mittente Email', 'wceventsfp'),
            array($this, 'brevo_from_email_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_from_name',
            __('Mittente Nome', 'wceventsfp'),
            array($this, 'brevo_from_name_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_list_it',
            __('Lista IT', 'wceventsfp'),
            array($this, 'brevo_list_it_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_list_en',
            __('Lista EN', 'wceventsfp'),
            array($this, 'brevo_list_en_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        add_settings_field(
            'wcefp_brevo_tag',
            __('Tag Contatto', 'wceventsfp'),
            array($this, 'brevo_tag_callback'),
            'wcefp_integrations_settings',
            'wcefp_brevo_section'
        );

        // Advanced Tab
        add_settings_section(
            'wcefp_tracking_section',
            __('Tracking e Analytics', 'wceventsfp'),
            array($this, 'tracking_section_callback'),
            'wcefp_advanced_settings'
        );

        add_settings_field(
            'wcefp_ga4_enable',
            __('GA4/GTM Eventi Custom', 'wceventsfp'),
            array($this, 'ga4_enable_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_ga4_id',
            __('GA4 Measurement ID', 'wceventsfp'),
            array($this, 'ga4_id_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_gtm_id',
            __('GTM Container ID', 'wceventsfp'),
            array($this, 'gtm_id_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_meta_pixel_id',
            __('Meta Pixel ID', 'wceventsfp'),
            array($this, 'meta_pixel_id_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_google_ads_id',
            __('Google Ads Conversion ID', 'wceventsfp'),
            array($this, 'google_ads_id_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_enable_server_analytics',
            __('Server-side Analytics', 'wceventsfp'),
            array($this, 'enable_server_analytics_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );

        add_settings_field(
            'wcefp_conversion_optimization',
            __('Ottimizzazione Conversioni', 'wceventsfp'),
            array($this, 'conversion_optimization_callback'),
            'wcefp_advanced_settings',
            'wcefp_tracking_section'
        );
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->handle_form_submission();
        }

        ?>
        <div class="wrap wcefp-settings-wrap">
            <h1><?php esc_html_e('Impostazioni Eventi & Degustazioni', 'wceventsfp'); ?></h1>
            
            <?php $this->render_tabs(); ?>
            
            <form method="post" action="options.php" class="wcefp-settings-form">
                <?php
                switch ($this->current_tab) {
                    case 'general':
                        settings_fields('wcefp_general_settings');
                        do_settings_sections('wcefp_general_settings');
                        break;
                    case 'display':
                        // Placeholder for display settings
                        echo '<div class="notice notice-info"><p>' . esc_html__('Opzioni di visualizzazione disponibili nelle prossime versioni.', 'wceventsfp') . '</p></div>';
                        break;
                    case 'integrations':
                        settings_fields('wcefp_integrations_settings');
                        do_settings_sections('wcefp_integrations_settings');
                        break;
                    case 'advanced':
                        settings_fields('wcefp_advanced_settings');
                        do_settings_sections('wcefp_advanced_settings');
                        break;
                }
                
                if ($this->current_tab !== 'display') {
                    submit_button(__('Salva Impostazioni', 'wceventsfp'));
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render tab navigation
     */
    private function render_tabs() {
        ?>
        <nav class="nav-tab-wrapper wcefp-nav-tab-wrapper">
            <?php foreach ($this->settings_tabs as $tab_key => $tab): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>" 
                   class="nav-tab <?php echo $this->current_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
                   data-tab="<?php echo esc_attr($tab_key); ?>">
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php echo esc_html($tab['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Handle form submission with proper notices
     */
    private function handle_form_submission() {
        // This is handled by WordPress Settings API automatically
        // We can add custom handling here if needed
    }

    // Section Callbacks
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configurazioni generali del plugin per eventi e degustazioni.', 'wceventsfp') . '</p>';
    }

    public function brevo_section_callback() {
        echo '<p>' . esc_html__('Configurazione per l\'integrazione con Brevo (ex Sendinblue) per l\'invio di email transazionali.', 'wceventsfp') . '</p>';
    }

    public function tracking_section_callback() {
        echo '<p>' . esc_html__('Configurazione per il tracking GA4, Google Tag Manager e Meta Pixel.', 'wceventsfp') . '</p>';
    }

    // Field Callbacks
    public function default_capacity_callback() {
        $value = get_option('wcefp_default_capacity', 0);
        ?>
        <input type="number" 
               id="wcefp_default_capacity" 
               name="wcefp_default_capacity" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               class="regular-text"
               aria-describedby="wcefp-default-capacity-description" />
        <p id="wcefp-default-capacity-description" class="description">
            <?php esc_html_e('Numero predefinito di posti disponibili per ogni slot quando viene creata una nuova occorrenza.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function disable_wc_emails_callback() {
        $value = get_option('wcefp_disable_wc_emails_for_events', false);
        ?>
        <fieldset>
            <label for="wcefp_disable_wc_emails_for_events">
                <input type="checkbox" 
                       id="wcefp_disable_wc_emails_for_events" 
                       name="wcefp_disable_wc_emails_for_events" 
                       value="1" 
                       <?php checked($value, true); ?>
                       aria-describedby="wcefp-disable-emails-description" />
                <?php esc_html_e('Disattiva email WooCommerce per ordini contenenti SOLO eventi/esperienze', 'wceventsfp'); ?>
            </label>
            <p id="wcefp-disable-emails-description" class="description">
                <?php esc_html_e('Quando attivo, le email standard di WooCommerce non verranno inviate per ordini che contengono esclusivamente prodotti di tipo evento o esperienza. Utile se si gestiscono le comunicazioni tramite Brevo.', 'wceventsfp'); ?>
            </p>
        </fieldset>
        <?php
    }

    public function price_rules_callback() {
        $value = get_option('wcefp_price_rules', '[]');
        ?>
        <textarea id="wcefp_price_rules" 
                  name="wcefp_price_rules" 
                  rows="5" 
                  cols="50" 
                  class="large-text code"
                  aria-describedby="wcefp-price-rules-description"><?php echo esc_textarea($value); ?></textarea>
        <p id="wcefp-price-rules-description" class="description">
            <?php esc_html_e('Formato JSON per regole di prezzo dinamiche. Esempio:', 'wceventsfp'); ?>
            <code>[{"date_from":"2024-06-01","date_to":"2024-09-30","weekdays":[5,6],"type":"percent","value":10}]</code>
        </p>
        <?php
    }

    public function brevo_api_key_callback() {
        $value = get_option('wcefp_brevo_api_key', '');
        ?>
        <input type="text" 
               id="wcefp_brevo_api_key" 
               name="wcefp_brevo_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="large-text"
               aria-describedby="wcefp-brevo-api-key-description" />
        <p id="wcefp-brevo-api-key-description" class="description">
            <?php esc_html_e('Chiave API di Brevo per l\'invio di email transazionali. Reperibile nel tuo account Brevo sotto API & Integrations.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_template_id_callback() {
        $value = get_option('wcefp_brevo_template_id', 0);
        ?>
        <input type="number" 
               id="wcefp_brevo_template_id" 
               name="wcefp_brevo_template_id" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               class="regular-text"
               aria-describedby="wcefp-brevo-template-id-description" />
        <p id="wcefp-brevo-template-id-description" class="description">
            <?php esc_html_e('ID del template transazionale Brevo da utilizzare per le email. Lasciare vuoto per utilizzare il template predefinito.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_from_email_callback() {
        $value = get_option('wcefp_brevo_from_email', '');
        ?>
        <input type="email" 
               id="wcefp_brevo_from_email" 
               name="wcefp_brevo_from_email" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               aria-describedby="wcefp-brevo-from-email-description" />
        <p id="wcefp-brevo-from-email-description" class="description">
            <?php esc_html_e('Indirizzo email del mittente per le email inviate tramite Brevo.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_from_name_callback() {
        $value = get_option('wcefp_brevo_from_name', '');
        ?>
        <input type="text" 
               id="wcefp_brevo_from_name" 
               name="wcefp_brevo_from_name" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               aria-describedby="wcefp-brevo-from-name-description" />
        <p id="wcefp-brevo-from-name-description" class="description">
            <?php esc_html_e('Nome del mittente per le email inviate tramite Brevo.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_list_it_callback() {
        $value = get_option('wcefp_brevo_list_it', 0);
        ?>
        <input type="number" 
               id="wcefp_brevo_list_it" 
               name="wcefp_brevo_list_it" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               class="regular-text"
               aria-describedby="wcefp-brevo-list-it-description" />
        <p id="wcefp-brevo-list-it-description" class="description">
            <?php esc_html_e('ID della lista Brevo per contatti italiani. I clienti verranno aggiunti automaticamente a questa lista.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_list_en_callback() {
        $value = get_option('wcefp_brevo_list_en', 0);
        ?>
        <input type="number" 
               id="wcefp_brevo_list_en" 
               name="wcefp_brevo_list_en" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               class="regular-text"
               aria-describedby="wcefp-brevo-list-en-description" />
        <p id="wcefp-brevo-list-en-description" class="description">
            <?php esc_html_e('ID della lista Brevo per contatti in inglese. I clienti verranno aggiunti automaticamente a questa lista.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function brevo_tag_callback() {
        $value = get_option('wcefp_brevo_tag', '');
        ?>
        <input type="text" 
               id="wcefp_brevo_tag" 
               name="wcefp_brevo_tag" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               aria-describedby="wcefp-brevo-tag-description" />
        <p id="wcefp-brevo-tag-description" class="description">
            <?php esc_html_e('Tag da applicare automaticamente ai contatti aggiunti tramite il plugin.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function ga4_enable_callback() {
        $value = get_option('wcefp_ga4_enable', true);
        ?>
        <fieldset>
            <label for="wcefp_ga4_enable">
                <input type="checkbox" 
                       id="wcefp_ga4_enable" 
                       name="wcefp_ga4_enable" 
                       value="1" 
                       <?php checked($value, true); ?>
                       aria-describedby="wcefp-ga4-enable-description" />
                <?php esc_html_e('Abilita push dataLayer per eventi custom', 'wceventsfp'); ?>
            </label>
            <p id="wcefp-ga4-enable-description" class="description">
                <?php esc_html_e('Invia eventi personalizzati a dataLayer: view_item, add_to_cart, begin_checkout, extra_selected, purchase.', 'wceventsfp'); ?>
            </p>
        </fieldset>
        <?php
    }

    public function ga4_id_callback() {
        $value = get_option('wcefp_ga4_id', '');
        ?>
        <input type="text" 
               id="wcefp_ga4_id" 
               name="wcefp_ga4_id" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="G-XXXXXXXXXX"
               class="regular-text"
               aria-describedby="wcefp-ga4-id-description" />
        <p id="wcefp-ga4-id-description" class="description">
            <?php esc_html_e('ID di misurazione GA4. Viene utilizzato solo se GTM Container ID è vuoto.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function gtm_id_callback() {
        $value = get_option('wcefp_gtm_id', '');
        ?>
        <input type="text" 
               id="wcefp_gtm_id" 
               name="wcefp_gtm_id" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="GTM-XXXXXX"
               class="regular-text"
               aria-describedby="wcefp-gtm-id-description" />
        <p id="wcefp-gtm-id-description" class="description">
            <?php esc_html_e('ID container di Google Tag Manager. Se impostato, verrà utilizzato invece del GA4 diretto (consigliato).', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function meta_pixel_id_callback() {
        $value = get_option('wcefp_meta_pixel_id', '');
        ?>
        <input type="text" 
               id="wcefp_meta_pixel_id" 
               name="wcefp_meta_pixel_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               aria-describedby="wcefp-meta-pixel-id-description" />
        <p id="wcefp-meta-pixel-id-description" class="description">
            <?php esc_html_e('ID del Meta Pixel (Facebook) per il tracking delle conversioni.', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function google_ads_id_callback() {
        $value = get_option('wcefp_google_ads_id', '');
        ?>
        <input type="text" 
               id="wcefp_google_ads_id" 
               name="wcefp_google_ads_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="AW-XXXXXXXXXX/XXXXXXXXX"
               aria-describedby="wcefp-google-ads-id-description" />
        <p id="wcefp-google-ads-id-description" class="description">
            <?php esc_html_e('ID di conversione Google Ads per il tracking delle conversioni (formato: AW-XXXXXXXXXX/XXXXXXXXX).', 'wceventsfp'); ?>
        </p>
        <?php
    }

    public function enable_server_analytics_callback() {
        $value = get_option('wcefp_enable_server_analytics', false);
        ?>
        <fieldset>
            <label for="wcefp_enable_server_analytics">
                <input type="checkbox" 
                       id="wcefp_enable_server_analytics" 
                       name="wcefp_enable_server_analytics" 
                       value="1" 
                       <?php checked($value, true); ?>
                       aria-describedby="wcefp-enable-server-analytics-description" />
                <?php esc_html_e('Abilita analytics server-side', 'wceventsfp'); ?>
            </label>
            <p id="wcefp-enable-server-analytics-description" class="description">
                <?php esc_html_e('Invia eventi critici al server per analytics avanzate e backup dei dati.', 'wceventsfp'); ?>
            </p>
        </fieldset>
        <?php
    }

    public function conversion_optimization_callback() {
        $value = get_option('wcefp_conversion_optimization', true);
        ?>
        <fieldset>
            <label for="wcefp_conversion_optimization">
                <input type="checkbox" 
                       id="wcefp_conversion_optimization" 
                       name="wcefp_conversion_optimization" 
                       value="1" 
                       <?php checked($value, true); ?>
                       aria-describedby="wcefp-conversion-optimization-description" />
                <?php esc_html_e('Abilita ottimizzazioni per conversioni', 'wceventsfp'); ?>
            </label>
            <p id="wcefp-conversion-optimization-description" class="description">
                <?php esc_html_e('Attiva funzionalità avanzate per migliorare le conversioni: urgency indicators, social proof, dynamic pricing hints.', 'wceventsfp'); ?>
            </p>
        </fieldset>
        <?php
    }

    // Sanitization Callbacks
    public function sanitize_capacity($input) {
        $capacity = absint($input);
        if ($capacity < 0) {
            add_settings_error('wcefp_default_capacity', 'invalid_capacity', __('La capienza deve essere un numero positivo.', 'wceventsfp'));
            return get_option('wcefp_default_capacity', 0);
        }
        return $capacity;
    }

    public function sanitize_checkbox($input) {
        return !empty($input) ? true : false;
    }

    public function sanitize_price_rules($input) {
        if (empty($input)) {
            return '[]';
        }

        // Validate JSON
        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('wcefp_price_rules', 'invalid_json', __('Le regole prezzo devono essere in formato JSON valido.', 'wceventsfp'));
            return get_option('wcefp_price_rules', '[]');
        }

        return $input;
    }
}