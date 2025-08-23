<?php
/**
 * Onboarding System
 * 
 * Provides guided setup, welcome screens, and contextual help for new users.
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
 * Manages plugin onboarding experience
 */
class Onboarding {
    
    /**
     * Onboarding steps
     * 
     * @var array
     */
    private $steps = [
        'welcome' => [
            'title' => 'Benvenuto in WCEventsFP',
            'description' => 'Configura il tuo sistema di prenotazione eventi enterprise.',
            'required' => false
        ],
        'basic_settings' => [
            'title' => 'Impostazioni Base',
            'description' => 'Configura le impostazioni essenziali del plugin.',
            'required' => true
        ],
        'integrations' => [
            'title' => 'Integrazioni',
            'description' => 'Connetti Brevo, Google Analytics e altri servizi.',
            'required' => false
        ],
        'first_event' => [
            'title' => 'Primo Evento',
            'description' => 'Crea il tuo primo evento o esperienza.',
            'required' => false
        ],
        'complete' => [
            'title' => 'Setup Completo',
            'description' => 'Il tuo sistema è pronto per ricevere prenotazioni!',
            'required' => false
        ]
    ];
    
    /**
     * Current step
     * 
     * @var string
     */
    private $current_step = 'welcome';
    
    /**
     * Initialize onboarding system
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_onboarding_page'], 99);
        add_action('admin_init', [$this, 'maybe_show_welcome_redirect']);
        add_action('admin_init', [$this, 'handle_onboarding_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_onboarding_assets']);
        add_action('admin_notices', [$this, 'show_onboarding_notice']);
        
        // Add help tabs to plugin pages
        add_action('load-toplevel_page_wcefp-dashboard', [$this, 'add_help_tabs']);
        add_action('load-wcefp_page_wcefp-settings', [$this, 'add_settings_help_tabs']);
        add_action('load-wcefp_page_wcefp-bookings', [$this, 'add_bookings_help_tabs']);
    }
    
    /**
     * Add onboarding page to admin menu
     */
    public function add_onboarding_page() {
        // Only show onboarding page if not completed
        if (!$this->is_onboarding_completed()) {
            add_submenu_page(
                null, // Hide from menu
                __('WCEventsFP Setup', 'wceventsfp'),
                __('Setup', 'wceventsfp'),
                'manage_options',
                'wcefp-onboarding',
                [$this, 'render_onboarding_page']
            );
        }
    }
    
    /**
     * Redirect to welcome page on first activation
     */
    public function maybe_show_welcome_redirect() {
        // Check if this is the first activation
        if (get_transient('wcefp_activation_redirect')) {
            delete_transient('wcefp_activation_redirect');
            
            if (!is_network_admin() && !isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=wcefp-onboarding'));
                exit;
            }
        }
    }
    
    /**
     * Handle onboarding form submissions
     */
    public function handle_onboarding_actions() {
        if (!isset($_POST['wcefp_onboarding_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['wcefp_onboarding_action']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wcefp_onboarding_' . $action)) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wceventsfp'));
        }
        
        switch ($action) {
            case 'skip_onboarding':
                $this->complete_onboarding();
                wp_safe_redirect(admin_url('admin.php?page=wcefp-dashboard'));
                exit;
                
            case 'next_step':
                $this->advance_step();
                break;
                
            case 'previous_step':
                $this->go_back_step();
                break;
                
            case 'save_basic_settings':
                $this->save_basic_settings();
                $this->advance_step();
                break;
                
            case 'save_integrations':
                $this->save_integrations();
                $this->advance_step();
                break;
                
            case 'complete_onboarding':
                $this->complete_onboarding();
                wp_safe_redirect(admin_url('admin.php?page=wcefp-dashboard&wcefp_onboarding_complete=1'));
                exit;
        }
        
        // Redirect to avoid form resubmission
        wp_safe_redirect(admin_url('admin.php?page=wcefp-onboarding'));
        exit;
    }
    
    /**
     * Enqueue onboarding assets
     */
    public function enqueue_onboarding_assets($hook) {
        if ($hook === 'admin_page_wcefp-onboarding') {
            wp_enqueue_style(
                'wcefp-onboarding',
                WCEFP_PLUGIN_URL . 'assets/css/admin-onboarding.css',
                [],
                WCEFP_VERSION
            );
            
            wp_enqueue_script(
                'wcefp-onboarding',
                WCEFP_PLUGIN_URL . 'assets/js/admin-onboarding.js',
                ['jquery'],
                WCEFP_VERSION,
                true
            );
        }
    }
    
    /**
     * Show onboarding notice in admin
     */
    public function show_onboarding_notice() {
        if (!$this->should_show_onboarding_notice()) {
            return;
        }
        
        $dismiss_url = wp_nonce_url(
            add_query_arg(['wcefp_dismiss_onboarding' => '1']),
            'wcefp_dismiss_onboarding'
        );
        
        ?>
        <div class="notice notice-info is-dismissible wcefp-onboarding-notice">
            <p>
                <strong><?php esc_html_e('WCEventsFP è stato attivato!', 'wceventsfp'); ?></strong>
                <?php esc_html_e('Completa la configurazione guidata per iniziare a ricevere prenotazioni.', 'wceventsfp'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-onboarding')); ?>" class="button button-primary">
                    <?php esc_html_e('Inizia Setup', 'wceventsfp'); ?>
                </a>
                <a href="<?php echo esc_url($dismiss_url); ?>" class="button button-secondary">
                    <?php esc_html_e('Nascondi', 'wceventsfp'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render onboarding page
     */
    public function render_onboarding_page() {
        $this->current_step = get_option('wcefp_onboarding_step', 'welcome');
        ?>
        <div class="wrap wcefp-onboarding-wrap">
            <div class="wcefp-onboarding-header">
                <div class="wcefp-logo">
                    <h1><?php esc_html_e('WCEventsFP Setup', 'wceventsfp'); ?></h1>
                    <p class="wcefp-version">v<?php echo esc_html(WCEFP_VERSION); ?></p>
                </div>
                <div class="wcefp-progress">
                    <?php $this->render_progress_bar(); ?>
                </div>
            </div>
            
            <div class="wcefp-onboarding-content">
                <?php $this->render_current_step(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render progress bar
     */
    private function render_progress_bar() {
        $steps = array_keys($this->steps);
        $current_index = array_search($this->current_step, $steps);
        $progress = ($current_index / (count($steps) - 1)) * 100;
        ?>
        <div class="wcefp-progress-bar">
            <div class="wcefp-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
            <div class="wcefp-progress-steps">
                <?php foreach ($this->steps as $step_key => $step_info): ?>
                    <div class="wcefp-progress-step <?php echo $step_key === $this->current_step ? 'active' : ''; ?>">
                        <span class="step-number"><?php echo array_search($step_key, $steps) + 1; ?></span>
                        <span class="step-title"><?php echo esc_html($step_info['title']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render current step content
     */
    private function render_current_step() {
        switch ($this->current_step) {
            case 'welcome':
                $this->render_welcome_step();
                break;
            case 'basic_settings':
                $this->render_basic_settings_step();
                break;
            case 'integrations':
                $this->render_integrations_step();
                break;
            case 'first_event':
                $this->render_first_event_step();
                break;
            case 'complete':
                $this->render_complete_step();
                break;
        }
    }
    
    /**
     * Render welcome step
     */
    private function render_welcome_step() {
        ?>
        <div class="wcefp-step-content wcefp-welcome-step">
            <div class="wcefp-welcome-hero">
                <h2><?php esc_html_e('Benvenuto in WCEventsFP', 'wceventsfp'); ?></h2>
                <p class="lead">
                    <?php esc_html_e('Il sistema di prenotazione enterprise per competere con RegionDo, Bokun e GetYourGuide', 'wceventsfp'); ?>
                </p>
            </div>
            
            <div class="wcefp-features-grid">
                <div class="wcefp-feature">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <h3><?php esc_html_e('Gestione Eventi Avanzata', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Sistema completo per gestire eventi, degustazioni e esperienze.', 'wceventsfp'); ?></p>
                </div>
                
                <div class="wcefp-feature">
                    <span class="dashicons dashicons-networking"></span>
                    <h3><?php esc_html_e('Distribuzione Multi-Canale', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Integrazione con Booking.com, Expedia, GetYourGuide e altri.', 'wceventsfp'); ?></p>
                </div>
                
                <div class="wcefp-feature">
                    <span class="dashicons dashicons-chart-line"></span>
                    <h3><?php esc_html_e('Analytics Avanzate', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Tracking GA4, Meta Pixel, conversioni e reporting real-time.', 'wceventsfp'); ?></p>
                </div>
                
                <div class="wcefp-feature">
                    <span class="dashicons dashicons-money-alt"></span>
                    <h3><?php esc_html_e('Sistema Commissioni', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Gestione automatica commissioni e reseller network.', 'wceventsfp'); ?></p>
                </div>
            </div>
            
            <div class="wcefp-step-actions">
                <form method="post">
                    <?php wp_nonce_field('wcefp_onboarding_next_step'); ?>
                    <input type="hidden" name="wcefp_onboarding_action" value="next_step">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Inizia Configurazione', 'wceventsfp'); ?>
                    </button>
                </form>
                
                <form method="post" class="wcefp-skip-form">
                    <?php wp_nonce_field('wcefp_onboarding_skip_onboarding'); ?>
                    <input type="hidden" name="wcefp_onboarding_action" value="skip_onboarding">
                    <button type="submit" class="button button-link">
                        <?php esc_html_e('Salta configurazione', 'wceventsfp'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render basic settings step
     */
    private function render_basic_settings_step() {
        $default_capacity = get_option('wcefp_default_capacity', 0);
        $disable_wc_emails = get_option('wcefp_disable_wc_emails_for_events', false);
        ?>
        <div class="wcefp-step-content wcefp-basic-settings-step">
            <h2><?php esc_html_e('Impostazioni Base', 'wceventsfp'); ?></h2>
            <p><?php esc_html_e('Configura le impostazioni essenziali per il funzionamento del plugin.', 'wceventsfp'); ?></p>
            
            <form method="post" class="wcefp-onboarding-form">
                <?php wp_nonce_field('wcefp_onboarding_save_basic_settings'); ?>
                <input type="hidden" name="wcefp_onboarding_action" value="save_basic_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wcefp_default_capacity"><?php esc_html_e('Capienza Default per Slot', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wcefp_default_capacity" name="wcefp_default_capacity" 
                                   value="<?php echo esc_attr($default_capacity); ?>" min="0" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Numero predefinito di posti disponibili per ogni slot.', 'wceventsfp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Email WooCommerce', 'wceventsfp'); ?></th>
                        <td>
                            <fieldset>
                                <label for="wcefp_disable_wc_emails_for_events">
                                    <input type="checkbox" id="wcefp_disable_wc_emails_for_events" 
                                           name="wcefp_disable_wc_emails_for_events" value="1" 
                                           <?php checked($disable_wc_emails, true); ?>>
                                    <?php esc_html_e('Disattiva email WooCommerce per ordini contenenti SOLO eventi', 'wceventsfp'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Utile se gestisci le comunicazioni tramite Brevo o altri sistemi.', 'wceventsfp'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <div class="wcefp-step-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Salva e Continua', 'wceventsfp'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary wcefp-back-btn" onclick="history.back()">
                        <?php esc_html_e('Indietro', 'wceventsfp'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render integrations step
     */
    private function render_integrations_step() {
        $brevo_api_key = get_option('wcefp_brevo_api_key', '');
        $ga4_id = get_option('wcefp_ga4_id', '');
        $google_places_api_key = get_option('wcefp_google_places_api_key', '');
        ?>
        <div class="wcefp-step-content wcefp-integrations-step">
            <h2><?php esc_html_e('Integrazioni Principali', 'wceventsfp'); ?></h2>
            <p><?php esc_html_e('Configura le integrazioni per massimizzare le performance del tuo business.', 'wceventsfp'); ?></p>
            
            <form method="post" class="wcefp-onboarding-form">
                <?php wp_nonce_field('wcefp_onboarding_save_integrations'); ?>
                <input type="hidden" name="wcefp_onboarding_action" value="save_integrations">
                
                <div class="wcefp-integration-cards">
                    <div class="wcefp-integration-card">
                        <h3><span class="dashicons dashicons-email"></span> Brevo (Email Marketing)</h3>
                        <p><?php esc_html_e('Automazioni email e gestione contatti avanzata.', 'wceventsfp'); ?></p>
                        <input type="text" name="wcefp_brevo_api_key" 
                               placeholder="<?php esc_attr_e('API Key Brevo (opzionale)', 'wceventsfp'); ?>"
                               value="<?php echo esc_attr($brevo_api_key); ?>" class="large-text">
                    </div>
                    
                    <div class="wcefp-integration-card">
                        <h3><span class="dashicons dashicons-chart-area"></span> Google Analytics 4</h3>
                        <p><?php esc_html_e('Tracking avanzato e analisi delle conversioni.', 'wceventsfp'); ?></p>
                        <input type="text" name="wcefp_ga4_id" 
                               placeholder="<?php esc_attr_e('GA4 Measurement ID (G-XXXXXXXXXX)', 'wceventsfp'); ?>"
                               value="<?php echo esc_attr($ga4_id); ?>" class="large-text">
                    </div>
                    
                    <div class="wcefp-integration-card">
                        <h3><span class="dashicons dashicons-star-filled"></span> Google Reviews</h3>
                        <p><?php esc_html_e('Mostra le recensioni autentiche dei tuoi clienti.', 'wceventsfp'); ?></p>
                        <input type="text" name="wcefp_google_places_api_key" 
                               placeholder="<?php esc_attr_e('Google Places API Key (opzionale)', 'wceventsfp'); ?>"
                               value="<?php echo esc_attr($google_places_api_key); ?>" class="large-text">
                    </div>
                </div>
                
                <div class="wcefp-integration-note">
                    <p><strong><?php esc_html_e('Nota:', 'wceventsfp'); ?></strong> 
                    <?php esc_html_e('Tutte le integrazioni sono opzionali e possono essere configurate in seguito nelle impostazioni.', 'wceventsfp'); ?>
                    </p>
                </div>
                
                <div class="wcefp-step-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Salva e Continua', 'wceventsfp'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary wcefp-skip-btn" onclick="this.form.submit()">
                        <?php esc_html_e('Salta e Continua', 'wceventsfp'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render first event step
     */
    private function render_first_event_step() {
        ?>
        <div class="wcefp-step-content wcefp-first-event-step">
            <h2><?php esc_html_e('Crea il Tuo Primo Evento', 'wceventsfp'); ?></h2>
            <p><?php esc_html_e('Sei pronto per creare il tuo primo evento o esperienza!', 'wceventsfp'); ?></p>
            
            <div class="wcefp-event-creation-options">
                <div class="wcefp-option-card">
                    <h3><?php esc_html_e('Crea Nuovo Evento', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Inizia da zero con la creazione guidata di un nuovo evento.', 'wceventsfp'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button button-primary">
                        <?php esc_html_e('Crea Evento', 'wceventsfp'); ?>
                    </a>
                </div>
                
                <div class="wcefp-option-card">
                    <h3><?php esc_html_e('Esplora Dashboard', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Familiarizza con le funzionalità del plugin prima di creare eventi.', 'wceventsfp'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-dashboard')); ?>" class="button button-secondary">
                        <?php esc_html_e('Vai alla Dashboard', 'wceventsfp'); ?>
                    </a>
                </div>
                
                <div class="wcefp-option-card">
                    <h3><?php esc_html_e('Leggi la Documentazione', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Consulta la guida completa per sfruttare al meglio tutte le funzionalità.', 'wceventsfp'); ?></p>
                    <a href="<?php echo esc_url('https://github.com/franpass87/WCEventsFP/wiki'); ?>" 
                       target="_blank" rel="noopener" class="button button-secondary">
                        <?php esc_html_e('Documentazione', 'wceventsfp'); ?>
                    </a>
                </div>
            </div>
            
            <div class="wcefp-step-actions">
                <form method="post">
                    <?php wp_nonce_field('wcefp_onboarding_complete_onboarding'); ?>
                    <input type="hidden" name="wcefp_onboarding_action" value="complete_onboarding">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Completa Setup', 'wceventsfp'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render complete step
     */
    private function render_complete_step() {
        ?>
        <div class="wcefp-step-content wcefp-complete-step">
            <div class="wcefp-complete-hero">
                <span class="dashicons dashicons-yes-alt wcefp-success-icon"></span>
                <h2><?php esc_html_e('Setup Completato!', 'wceventsfp'); ?></h2>
                <p class="lead">
                    <?php esc_html_e('Il tuo sistema di prenotazione enterprise è ora pronto per ricevere ordini.', 'wceventsfp'); ?>
                </p>
            </div>
            
            <div class="wcefp-next-steps">
                <h3><?php esc_html_e('Prossimi Passi Consigliati:', 'wceventsfp'); ?></h3>
                
                <div class="wcefp-checklist">
                    <div class="wcefp-checklist-item">
                        <span class="dashicons dashicons-products"></span>
                        <div>
                            <strong><?php esc_html_e('Crea i tuoi primi eventi', 'wceventsfp'); ?></strong>
                            <p><?php esc_html_e('Aggiungi eventi, degustazioni ed esperienze al tuo catalogo.', 'wceventsfp'); ?></p>
                        </div>
                    </div>
                    
                    <div class="wcefp-checklist-item">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <div>
                            <strong><?php esc_html_e('Personalizza le impostazioni', 'wceventsfp'); ?></strong>
                            <p><?php esc_html_e('Affina la configurazione in base alle tue esigenze specifiche.', 'wceventsfp'); ?></p>
                        </div>
                    </div>
                    
                    <div class="wcefp-checklist-item">
                        <span class="dashicons dashicons-analytics"></span>
                        <div>
                            <strong><?php esc_html_e('Monitora le performance', 'wceventsfp'); ?></strong>
                            <p><?php esc_html_e('Utilizza la dashboard analytics per ottimizzare le conversioni.', 'wceventsfp'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="wcefp-step-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-dashboard')); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Vai alla Dashboard', 'wceventsfp'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add help tabs to dashboard page
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        $screen->add_help_tab([
            'id' => 'wcefp_dashboard_overview',
            'title' => __('Panoramica Dashboard', 'wceventsfp'),
            'content' => $this->get_dashboard_help_content()
        ]);
        
        $screen->add_help_tab([
            'id' => 'wcefp_getting_started',
            'title' => __('Come Iniziare', 'wceventsfp'),
            'content' => $this->get_getting_started_help_content()
        ]);
        
        $screen->set_help_sidebar($this->get_help_sidebar());
    }
    
    /**
     * Add help tabs to settings page
     */
    public function add_settings_help_tabs() {
        $screen = get_current_screen();
        
        $screen->add_help_tab([
            'id' => 'wcefp_settings_general',
            'title' => __('Impostazioni Generali', 'wceventsfp'),
            'content' => $this->get_settings_general_help_content()
        ]);
        
        $screen->add_help_tab([
            'id' => 'wcefp_integrations_help',
            'title' => __('Integrazioni', 'wceventsfp'),
            'content' => $this->get_integrations_help_content()
        ]);
        
        $screen->set_help_sidebar($this->get_help_sidebar());
    }
    
    /**
     * Add help tabs to bookings page
     */
    public function add_bookings_help_tabs() {
        $screen = get_current_screen();
        
        $screen->add_help_tab([
            'id' => 'wcefp_bookings_management',
            'title' => __('Gestione Prenotazioni', 'wceventsfp'),
            'content' => $this->get_bookings_help_content()
        ]);
        
        $screen->set_help_sidebar($this->get_help_sidebar());
    }
    
    // Helper methods for step management and help content...
    
    private function advance_step() {
        $steps = array_keys($this->steps);
        $current_index = array_search($this->current_step, $steps);
        
        if ($current_index < count($steps) - 1) {
            $next_step = $steps[$current_index + 1];
            update_option('wcefp_onboarding_step', $next_step);
        }
    }
    
    private function save_basic_settings() {
        if (isset($_POST['wcefp_default_capacity'])) {
            update_option('wcefp_default_capacity', absint($_POST['wcefp_default_capacity']));
        }
        
        $disable_emails = isset($_POST['wcefp_disable_wc_emails_for_events']) ? true : false;
        update_option('wcefp_disable_wc_emails_for_events', $disable_emails);
    }
    
    private function save_integrations() {
        $fields = ['wcefp_brevo_api_key', 'wcefp_ga4_id', 'wcefp_google_places_api_key'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    private function complete_onboarding() {
        update_option('wcefp_onboarding_completed', true);
        update_option('wcefp_onboarding_completed_date', current_time('mysql'));
        delete_option('wcefp_onboarding_step');
    }
    
    private function is_onboarding_completed() {
        return get_option('wcefp_onboarding_completed', false);
    }
    
    private function should_show_onboarding_notice() {
        if ($this->is_onboarding_completed()) {
            return false;
        }
        
        if (get_option('wcefp_onboarding_notice_dismissed', false)) {
            return false;
        }
        
        $screen = get_current_screen();
        return !in_array($screen->id, ['admin_page_wcefp-onboarding', 'toplevel_page_wcefp-dashboard']);
    }
    
    // Help content methods...
    private function get_dashboard_help_content() {
        return '<h4>' . __('Panoramica Dashboard', 'wceventsfp') . '</h4>' .
               '<p>' . __('La dashboard fornisce una vista completa delle performance del tuo business eventi.', 'wceventsfp') . '</p>' .
               '<ul>' .
               '<li>' . __('<strong>Statistiche in Tempo Reale:</strong> Monitora prenotazioni, ricavi e conversioni', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Grafici Performance:</strong> Analizza trend e stagionalità', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Notifiche:</strong> Ricevi avvisi su eventi importanti', 'wceventsfp') . '</li>' .
               '</ul>';
    }
    
    private function get_getting_started_help_content() {
        return '<h4>' . __('Come Iniziare', 'wceventsfp') . '</h4>' .
               '<p>' . __('Segui questi passaggi per configurare il tuo sistema di prenotazioni:', 'wceventsfp') . '</p>' .
               '<ol>' .
               '<li>' . __('Completa il setup guidato nelle impostazioni', 'wceventsfp') . '</li>' .
               '<li>' . __('Crea il tuo primo evento o esperienza', 'wceventsfp') . '</li>' .
               '<li>' . __('Configura le integrazioni per analytics e email marketing', 'wceventsfp') . '</li>' .
               '<li>' . __('Testa il processo di prenotazione', 'wceventsfp') . '</li>' .
               '</ol>';
    }
    
    private function get_settings_general_help_content() {
        return '<h4>' . __('Impostazioni Generali', 'wceventsfp') . '</h4>' .
               '<p>' . __('Configura le opzioni base del plugin:', 'wceventsfp') . '</p>' .
               '<ul>' .
               '<li>' . __('<strong>Capienza Default:</strong> Numero di posti predefinito per ogni slot', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Email WooCommerce:</strong> Disattiva per gestire comunicazioni tramite Brevo', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Regole Prezzo:</strong> JSON per pricing dinamico stagionale', 'wceventsfp') . '</li>' .
               '</ul>';
    }
    
    private function get_integrations_help_content() {
        return '<h4>' . __('Integrazioni Disponibili', 'wceventsfp') . '</h4>' .
               '<p>' . __('Connetti servizi esterni per potenziare il tuo business:', 'wceventsfp') . '</p>' .
               '<ul>' .
               '<li>' . __('<strong>Brevo:</strong> Email marketing e automazioni transazionali', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Google Analytics 4:</strong> Tracking avanzato conversioni', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Google Reviews:</strong> Mostra recensioni autentiche', 'wceventsfp') . '</li>' .
               '<li>' . __('<strong>Meta Pixel:</strong> Retargeting Facebook/Instagram', 'wceventsfp') . '</li>' .
               '</ul>';
    }
    
    private function get_bookings_help_content() {
        return '<h4>' . __('Gestione Prenotazioni', 'wceventsfp') . '</h4>' .
               '<p>' . __('Monitora e gestisci tutte le prenotazioni:', 'wceventsfp') . '</p>' .
               '<ul>' .
               '<li>' . __('Visualizza stato delle prenotazioni in tempo reale', 'wceventsfp') . '</li>' .
               '<li>' . __('Modifica dettagli cliente e partecipanti', 'wceventsfp') . '</li>' .
               '<li>' . __('Gestisci rimborsi e cancellazioni', 'wceventsfp') . '</li>' .
               '<li>' . __('Esporta dati per reporting esterni', 'wceventsfp') . '</li>' .
               '</ul>';
    }
    
    private function get_help_sidebar() {
        return '<h4>' . __('Supporto e Risorse', 'wceventsfp') . '</h4>' .
               '<p><a href="https://github.com/franpass87/WCEventsFP/wiki" target="_blank">' . __('📖 Documentazione Completa', 'wceventsfp') . '</a></p>' .
               '<p><a href="https://github.com/franpass87/WCEventsFP/issues" target="_blank">' . __('🐛 Segnala Bug', 'wceventsfp') . '</a></p>' .
               '<p><a href="https://github.com/franpass87/WCEventsFP/discussions" target="_blank">' . __('💬 Community', 'wceventsfp') . '</a></p>' .
               '<p><strong>' . __('Versione Plugin:', 'wceventsfp') . '</strong> ' . WCEFP_VERSION . '</p>';
    }
}