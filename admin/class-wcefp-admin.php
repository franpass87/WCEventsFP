<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    /* ---------- Menu ---------- */
    public static function admin_menu() {
        $cap = 'manage_woocommerce';

        add_menu_page(
            __('Eventi & Degustazioni','wceventsfp'),
            __('Eventi & Degustazioni','wceventsfp'),
            $cap,
            'wcefp',
            [__CLASS__,'render_kpi_page'],
            'dashicons-calendar-alt',
            56
        );

        add_submenu_page('wcefp', __('Analisi KPI','wceventsfp'), __('Analisi KPI','wceventsfp'), $cap,'wcefp',[__CLASS__,'render_kpi_page']);
        add_submenu_page('wcefp', __('Calendario & Lista','wceventsfp'), __('Calendario & Lista','wceventsfp'), $cap,'wcefp-calendar',[__CLASS__,'render_calendar_page']);
        add_submenu_page('wcefp', __('Chiusure straordinarie','wceventsfp'), __('Chiusure straordinarie','wceeventsfp'), $cap,'wcefp-closures',['WCEFP_Closures','render_admin_page']);
        add_submenu_page('wcefp', __('Esporta','wceventsfp'), __('Esporta','wceventsfp'), $cap,'wcefp-export',[__CLASS__,'render_export_page']);
        add_submenu_page('wcefp', __('Impostazioni','wceventsfp'), __('Impostazioni','wceventsfp'), $cap,'wcefp-settings',[__CLASS__,'render_settings_page']);
    }

    /* ---------- Enqueue admin ---------- */
    public static function enqueue_admin($hook) {
        if (strpos($hook,'wcefp') === false) return;

        wp_enqueue_style('wcefp-admin', WCEFP_PLUGIN_URL.'assets/css/admin.css', [], WCEFP_VERSION);

        // FullCalendar solo nella pagina calendario
        if (strpos($hook,'wcefp_page_wcefp-calendar') !== false) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css', [], '6.1.15');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true);
        }

        // JS admin principale
        wp_enqueue_script('wcefp-admin', WCEFP_PLUGIN_URL.'assets/js/admin.js', ['jquery','fullcalendar'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-admin','WCEFPAdmin',[
            'ajaxUrl'=> admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('wcefp_admin'),
            'products' => self::get_events_products_for_filter(),
        ]);

        // JS chiusure
        if (strpos($hook,'wcefp_page_wcefp-closures') !== false) {
            wp_enqueue_script('wcefp-closures', WCEFP_PLUGIN_URL.'assets/js/closures.js', ['jquery'], WCEFP_VERSION, true);
            wp_localize_script('wcefp-closures','WCEFPClose',[
                'ajaxUrl'=> admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce('wcefp_admin'),
                'products' => self::get_events_products_for_filter(),
            ]);
        }
    }

    private static function get_events_products_for_filter(){
        $q = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 300,
            'post_status' => 'publish',
            'tax_query' => [[
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['wcefp_event','wcefp_experience'],
                'operator' => 'IN',
            ]],
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);
        $out = [];
        foreach ($q->posts as $p) $out[] = ['id'=>$p->ID,'title'=>$p->post_title];
        return $out;
    }

    /* ---------- Pagine ---------- */
    public static function render_kpi_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $kpi = ['orders_30'=>18,'revenue_30'=>2150.50,'fill_rate'=>63,'top_product'=>'Degustazione Classica']; ?>
        <div class="wrap">
            <h1><?php _e('Analisi KPI','wceventsfp'); ?></h1>
            <div class="wcefp-kpi-grid">
                <div class="card"><h3><?php _e('Ordini (30gg)','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['orders_30']); ?></p></div>
                <div class="card"><h3><?php _e('Ricavi (30gg)','wceventsfp'); ?></h3><p>€ <?php echo number_format($kpi['revenue_30'],2,',','.'); ?></p></div>
                <div class="card"><h3><?php _e('Riempimento medio','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['fill_rate']); ?>%</p></div>
                <div class="card"><h3><?php _e('Top Esperienza','wceventsfp'); ?></h3><p><?php echo esc_html($kpi['top_product']); ?></p></div>
            </div>
        </div><?php
    }

    public static function render_calendar_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Calendario & Lista Prenotazioni','wceventsfp'); ?></h1>
            <div class="wcefp-toolbar">
                <label><?php _e('Filtra prodotto','wceventsfp'); ?>:</label>
                <select id="wcefp-filter-product">
                    <option value="0"><?php _e('Tutti','wceventsfp'); ?></option>
                </select>
                <button class="button button-primary" id="wcefp-switch-calendar"><?php _e('Calendario','wceventsfp'); ?></button>
                <button class="button" id="wcefp-switch-list"><?php _e('Lista','wceventsfp'); ?></button>
            </div>
            <div id="wcefp-view" style="min-height:650px;"></div>
        </div><?php
    }

    public static function render_export_page() {
        if (!current_user_can('manage_woocommerce')) return; ?>
        <div class="wrap">
            <h1><?php _e('Esporta CSV','wceventsfp'); ?></h1>
            <p><?php _e('Scarica i dati per analisi o backup.','wceventsfp'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_occurrences'), 'wcefp_export') ); ?>"><?php _e('Occorrenze','wceventsfp'); ?></a>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcefp_export_bookings'), 'wcefp_export') ); ?>"><?php _e('Prenotazioni','wceventsfp'); ?></a>
            </p>
        </div><?php
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;

        /* Salvataggio */
        if (isset($_POST['wcefp_save']) && check_admin_referer('wcefp_settings')) {
            update_option('wcefp_default_capacity', intval($_POST['wcefp_default_capacity'] ?? 0));
            update_option('wcefp_disable_wc_emails_for_events', isset($_POST['wcefp_disable_wc_emails_for_events']) ? '1' : '0');

            // Brevo
            update_option('wcefp_brevo_api_key', sanitize_text_field($_POST['wcefp_brevo_api_key'] ?? ''));
            update_option('wcefp_brevo_template_id', intval($_POST['wcefp_brevo_template_id'] ?? 0));
            update_option('wcefp_brevo_from_email', sanitize_email($_POST['wcefp_brevo_from_email'] ?? ''));
            update_option('wcefp_brevo_from_name', sanitize_text_field($_POST['wcefp_brevo_from_name'] ?? ''));
            update_option('wcefp_brevo_list_it', intval($_POST['wcefp_brevo_list_it'] ?? 0));
            update_option('wcefp_brevo_list_en', intval($_POST['wcefp_brevo_list_en'] ?? 0));

            // Tracking
            update_option('wcefp_ga4_enable', isset($_POST['wcefp_ga4_enable']) ? '1' : '0');
            update_option('wcefp_ga4_id', sanitize_text_field($_POST['wcefp_ga4_id'] ?? ''));
            update_option('wcefp_gtm_id', sanitize_text_field($_POST['wcefp_gtm_id'] ?? ''));
            update_option('wcefp_meta_pixel_id', sanitize_text_field($_POST['wcefp_meta_pixel_id'] ?? ''));

            echo '<div class="updated"><p>Salvato.</p></div>';
        }

        /* Lettura */
        $cap = get_option('wcefp_default_capacity', 0);
        $dis = get_option('wcefp_disable_wc_emails_for_events','0')==='1';
        $api = get_option('wcefp_brevo_api_key','');
        $tpl = intval(get_option('wcefp_brevo_template_id', 0));
        $from_email = get_option('wcefp_brevo_from_email','');
        $from_name  = get_option('wcefp_brevo_from_name','');
        $list_it    = intval(get_option('wcefp_brevo_list_it', 0));
        $list_en    = intval(get_option('wcefp_brevo_list_en', 0));
        $ga4_en     = get_option('wcefp_ga4_enable','1')==='1';
        $ga4_id     = get_option('wcefp_ga4_id','');
        $gtm_id     = get_option('wcefp_gtm_id','');
        $mp_id      = get_option('wcefp_meta_pixel_id',''); ?>

        <div class="wrap">
            <h1><?php _e('Impostazioni','wceventsfp'); ?></h1>
            <form method="post"><?php wp_nonce_field('wcefp_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="wcefp_default_capacity"><?php _e('Capienza default per slot','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_default_capacity" id="wcefp_default_capacity" value="<?php echo esc_attr($cap); ?>" min="0" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email WooCommerce','wceventsfp'); ?></th>
                        <td><label><input type="checkbox" name="wcefp_disable_wc_emails_for_events" <?php checked($dis,true); ?> /> <?php _e('Disattiva email Woo per ordini SOLO-evento/esperienza','wceventsfp'); ?></label></td>
                    </tr>

                    <tr><th colspan="2"><h3><?php _e('Brevo (API v3)','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_brevo_api_key"><?php _e('API Key','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_api_key" id="wcefp_brevo_api_key" value="<?php echo esc_attr($api); ?>" style="width:420px" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_template_id"><?php _e('Template ID (opzionale)','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_template_id" id="wcefp_brevo_template_id" value="<?php echo esc_attr($tpl); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_from_email"><?php _e('Mittente email','wceventsfp'); ?></label></th>
                        <td><input type="email" name="wcefp_brevo_from_email" id="wcefp_brevo_from_email" value="<?php echo esc_attr($from_email); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_from_name"><?php _e('Mittente nome','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_brevo_from_name" id="wcefp_brevo_from_name" value="<?php echo esc_attr($from_name); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_list_it"><?php _e('Lista IT','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_it" id="wcefp_brevo_list_it" value="<?php echo esc_attr($list_it); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_brevo_list_en"><?php _e('Lista EN','wceventsfp'); ?></label></th>
                        <td><input type="number" name="wcefp_brevo_list_en" id="wcefp_brevo_list_en" value="<?php echo esc_attr($list_en); ?>" /></td>
                    </tr>

                    <tr><th colspan="2"><h3><?php _e('Tracking','wceventsfp'); ?></h3></th></tr>
                    <tr>
                        <th><label for="wcefp_ga4_enable"><?php _e('GA4/Tag Manager eventi custom','wceventsfp'); ?></label></th>
                        <td><label><input type="checkbox" name="wcefp_ga4_enable" id="wcefp_ga4_enable" <?php checked($ga4_en,true); ?> /> <?php _e('Abilita push dataLayer (view_item, add_to_cart, begin_checkout, extra_selected, purchase)','wceventsfp'); ?></label></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_ga4_id">GA4 Measurement ID</label></th>
                        <td><input type="text" name="wcefp_ga4_id" id="wcefp_ga4_id" value="<?php echo esc_attr($ga4_id); ?>" placeholder="G-XXXXXXXXXX" /></td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_gtm_id">GTM Container ID</label></th>
                        <td><input type="text" name="wcefp_gtm_id" id="wcefp_gtm_id" value="<?php echo esc_attr($gtm_id); ?>" placeholder="GTM-XXXXXX" />
                            <p class="description"><?php _e('Se imposti GTM, verrà caricato Google Tag Manager (consigliato). Se lasci vuoto e compili GA4, verrà caricato direttamente GA4.','wceventsfp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wcefp_meta_pixel_id"><?php _e('Meta Pixel ID','wceventsfp'); ?></label></th>
                        <td><input type="text" name="wcefp_meta_pixel_id" id="wcefp_meta_pixel_id" value="<?php echo esc_attr($mp_id); ?>" /></td>
                    </tr>
                </table>

                <p><button class="button button-primary" type="submit" name="wcefp_save" value="1"><?php _e('Salva','wceventsfp'); ?></button></p>
            </form>
        </div><?php
    }
}
