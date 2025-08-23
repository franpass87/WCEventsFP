<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Channel Management System
 * Distribute inventory to multiple channels (Booking.com, Expedia, GetYourGuide, Viator, etc.)
 * Competitive feature similar to what Bokun and Regiondo offer for OTA distribution
 */
class WCEFP_Channel_Management {

    private static $supported_channels = [
        'booking_com' => [
            'name' => 'Booking.com',
            'type' => 'accommodation',
            'api_endpoint' => 'https://distribution-xml.booking.com/',
            'requires_xml' => true
        ],
        'expedia' => [
            'name' => 'Expedia Partner Solutions',
            'type' => 'travel',
            'api_endpoint' => 'https://services.expediapartnercentral.com/',
            'requires_xml' => true
        ],
        'getyourguide' => [
            'name' => 'GetYourGuide',
            'type' => 'activities',
            'api_endpoint' => 'https://api.getyourguide.com/',
            'requires_json' => true
        ],
        'viator' => [
            'name' => 'Viator (TripAdvisor)',
            'type' => 'activities',
            'api_endpoint' => 'https://api.viator.com/',
            'requires_json' => true
        ],
        'klook' => [
            'name' => 'Klook',
            'type' => 'activities',
            'api_endpoint' => 'https://api.klook.com/',
            'requires_json' => true
        ],
        'tiqets' => [
            'name' => 'Tiqets',
            'type' => 'attractions',
            'api_endpoint' => 'https://api.tiqets.com/',
            'requires_json' => true
        ],
        'airbnb_experiences' => [
            'name' => 'Airbnb Experiences',
            'type' => 'experiences',
            'api_endpoint' => 'https://api.airbnb.com/',
            'requires_json' => true
        ]
    ];

    public static function init() {
        // Database tables
        add_action('init', [__CLASS__, 'create_channel_tables']);
        
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_sync_channel', [__CLASS__, 'ajax_sync_channel']);
        add_action('wp_ajax_wcefp_test_channel_connection', [__CLASS__, 'ajax_test_channel_connection']);
        add_action('wp_ajax_wcefp_update_channel_mapping', [__CLASS__, 'ajax_update_channel_mapping']);
        add_action('wp_ajax_wcefp_bulk_sync_channels', [__CLASS__, 'ajax_bulk_sync_channels']);
        
        // Scheduled tasks
        add_action('wcefp_sync_all_channels', [__CLASS__, 'sync_all_channels']);
        if (!wp_next_scheduled('wcefp_sync_all_channels')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_sync_all_channels');
        }
        
        // Hook into booking process
        add_action('wcefp_occurrence_updated', [__CLASS__, 'sync_occurrence_to_channels'], 10, 2);
        add_action('wcefp_product_updated', [__CLASS__, 'sync_product_to_channels'], 10, 1);
        
        // Meta boxes
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_channel_meta']);
    }

    public static function create_channel_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Channel configurations
        $table_channels = $wpdb->prefix . 'wcefp_channels';
        $sql_channels = "CREATE TABLE IF NOT EXISTS $table_channels (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            channel_key varchar(50) NOT NULL,
            channel_name varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'active',
            api_endpoint varchar(255),
            api_credentials longtext,
            sync_frequency varchar(20) DEFAULT 'hourly',
            last_sync datetime NULL,
            sync_status varchar(20) DEFAULT 'pending',
            commission_rate decimal(5,2) DEFAULT 0.00,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY channel_key (channel_key),
            KEY status (status),
            KEY sync_status (sync_status)
        ) $charset_collate;";

        // Product-channel mappings
        $table_mappings = $wpdb->prefix . 'wcefp_channel_mappings';
        $sql_mappings = "CREATE TABLE IF NOT EXISTS $table_mappings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            channel_id bigint(20) NOT NULL,
            external_id varchar(100),
            sync_status varchar(20) DEFAULT 'pending',
            price_modifier decimal(5,2) DEFAULT 0.00,
            availability_buffer int DEFAULT 0,
            min_advance_booking int DEFAULT 0,
            max_advance_booking int DEFAULT 365,
            blackout_dates text,
            channel_specific_settings longtext,
            last_sync datetime NULL,
            sync_errors text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_channel (product_id, channel_id),
            KEY product_id (product_id),
            KEY channel_id (channel_id),
            KEY sync_status (sync_status),
            FOREIGN KEY (channel_id) REFERENCES {$wpdb->prefix}wcefp_channels(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Sync logs
        $table_logs = $wpdb->prefix . 'wcefp_channel_sync_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            channel_id bigint(20),
            product_id bigint(20) NULL,
            sync_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            request_data longtext,
            response_data longtext,
            sync_duration decimal(10,3),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY channel_id (channel_id),
            KEY product_id (product_id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_channels);
        dbDelta($sql_mappings);
        dbDelta($sql_logs);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=wcefp_event',
            'Gestione Canali',
            'Gestione Canali',
            'manage_woocommerce',
            'wcefp-channels',
            [__CLASS__, 'channels_page']
        );
    }

    public static function channels_page() {
        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap wcefp-channels-page">
            <h1>Gestione Canali di Distribuzione</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=overview" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'overview' ? 'nav-tab-active' : '')); ?>">
                   Panoramica
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=channels" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'channels' ? 'nav-tab-active' : '')); ?>">
                   Canali
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=mappings" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'mappings' ? 'nav-tab-active' : '')); ?>">
                   Mappature
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=sync" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'sync' ? 'nav-tab-active' : '')); ?>">
                   Sincronizzazione
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=analytics" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'analytics' ? 'nav-tab-active' : '')); ?>">
                   Analytics
                </a>
            </nav>

            <div class="wcefp-tab-content">
                <?php
                switch ($active_tab) {
                    case 'overview':
                        self::render_overview_tab();
                        break;
                    case 'channels':
                        self::render_channels_tab();
                        break;
                    case 'mappings':
                        self::render_mappings_tab();
                        break;
                    case 'sync':
                        self::render_sync_tab();
                        break;
                    case 'analytics':
                        self::render_analytics_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .wcefp-channels-page .nav-tab-wrapper { margin-bottom: 20px; }
        .wcefp-channel-card { 
            background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        }
        .wcefp-channel-info { display: flex; align-items: center; }
        .wcefp-channel-logo { width: 64px; height: 64px; margin-right: 20px; border-radius: 8px; }
        .wcefp-channel-details h3 { margin: 0 0 8px 0; }
        .wcefp-channel-status { font-size: 13px; padding: 4px 8px; border-radius: 12px; font-weight: 600; }
        .wcefp-channel-status.active { background: #d1e7dd; color: #0f5132; }
        .wcefp-channel-status.inactive { background: #f8d7da; color: #721c24; }
        .wcefp-channel-status.error { background: #fff3cd; color: #856404; }
        .wcefp-channel-actions { display: flex; gap: 10px; }
        .wcefp-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .wcefp-stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .wcefp-stat-number { font-size: 2.5em; font-weight: bold; display: block; }
        </style>
        <?php
    }

    private static function render_overview_tab() {
        global $wpdb;
        
        // Get channel stats
        $total_channels = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_channels");
        $active_channels = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_channels WHERE status = 'active'");
        $total_mappings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_channel_mappings");
        $synced_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_channel_sync_logs 
            WHERE DATE(created_at) = %s AND status = 'success'
        ", current_time('Y-m-d')));
        
        ?>
        <div class="wcefp-overview-content">
            <div class="wcefp-stats-grid">
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($total_channels); ?></span>
                    <span class="wcefp-stat-label">Canali Configurati</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($active_channels); ?></span>
                    <span class="wcefp-stat-label">Canali Attivi</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($total_mappings); ?></span>
                    <span class="wcefp-stat-label">Prodotti Distribuiti</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($synced_today); ?></span>
                    <span class="wcefp-stat-label">Sync Oggi</span>
                </div>
            </div>
            
            <h2>Canali Disponibili per Configurazione</h2>
            <div class="wcefp-available-channels">
                <?php foreach (self::$supported_channels as $key => $channel): ?>
                    <div class="wcefp-channel-card">
                        <div class="wcefp-channel-info">
                            <div class="wcefp-channel-logo" style="background: linear-gradient(45deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo strtoupper(substr($channel['name'], 0, 2)); ?>
                            </div>
                            <div class="wcefp-channel-details">
                                <h3><?php echo $channel['name']; ?></h3>
                                <p style="margin: 0; color: #666;">
                                    <?php echo ucfirst($channel['type']); ?> • 
                                    <?php echo self::is_channel_configured($key) ? 'Configurato' : 'Non configurato'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="wcefp-channel-actions">
                            <?php if (self::is_channel_configured($key)): ?>
                                <button class="button" onclick="configureChannel('<?php echo esc_html($key); ?>')">Modifica</button>
                                <button class="button button-primary" onclick="testChannel('<?php echo esc_html($key); ?>')">Test</button>
                            <?php else: ?>
                                <button class="button button-primary" onclick="configureChannel('<?php echo esc_html($key); ?>')">Configura</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        function configureChannel(channelKey) {
            window.location.href = '?post_type=wcefp_event&page=wcefp-channels&tab=channels&action=configure&channel=' + channelKey;
        }
        
        function testChannel(channelKey) {
            if (confirm('Testare la connessione con ' + channelKey + '?')) {
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_test_channel_connection',
                        channel_key: channelKey,
                        nonce: '<?php echo wp_create_nonce('wcefp_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Connessione riuscita!');
                        } else {
                            alert('Errore connessione: ' + (response.data.msg || 'Errore sconosciuto'));
                        }
                    }
                });
            }
        }
        </script>
        <?php
    }

    private static function render_channels_tab() {
        $action = $_GET['action'] ?? '';
        $channel_key = $_GET['channel'] ?? '';
        
        if ($action === 'configure' && $channel_key) {
            self::render_channel_configuration($channel_key);
        } else {
            self::render_channels_list();
        }
    }

    private static function render_channels_list() {
        global $wpdb;
        
        $channels = $wpdb->get_results("
            SELECT c.*, 
                   COUNT(m.id) as mapped_products,
                   MAX(l.created_at) as last_activity
            FROM {$wpdb->prefix}wcefp_channels c
            LEFT JOIN {$wpdb->prefix}wcefp_channel_mappings m ON c.id = m.channel_id
            LEFT JOIN {$wpdb->prefix}wcefp_channel_sync_logs l ON c.id = l.channel_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        
        ?>
        <div class="wcefp-channels-list">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
                <h2>Canali Configurati</h2>
                <a href="?post_type=wcefp_event&page=wcefp-channels&tab=overview" class="button button-primary">
                    Aggiungi Canale
                </a>
            </div>
            
            <?php if (empty($channels)): ?>
                <div class="wcefp-empty-state" style="text-align: center; padding: 40px; color: #646970;">
                    <h3>Nessun canale configurato</h3>
                    <p>Inizia configurando il primo canale di distribuzione per i tuoi eventi.</p>
                    <a href="?post_type=wcefp_event&page=wcefp-channels&tab=overview" class="button button-primary">
                        Configura Primo Canale
                    </a>
                </div>
            <?php else: ?>
                <div class="wcefp-channels-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Canale</th>
                                <th>Stato</th>
                                <th>Prodotti</th>
                                <th>Ultima Sync</th>
                                <th>Commissioni</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($channels as $channel): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($channel->channel_name); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($channel->channel_key); ?></small>
                                    </td>
                                    <td>
                                        <span class="wcefp-channel-status <?php echo esc_attr($channel->status); ?>">
                                            <?php echo ucfirst($channel->status); ?>
                                        </span>
                                        <?php if ($channel->sync_status === 'error'): ?>
                                            <br><small style="color: #d63384;">Errori sync</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo intval($channel->mapped_products); ?> prodotti</td>
                                    <td>
                                        <?php if ($channel->last_sync): ?>
                                            <?php echo human_time_diff(strtotime($channel->last_sync), time()) . ' fa'; ?>
                                        <?php else: ?>
                                            <span style="color: #856404;">Mai sincronizzato</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($channel->commission_rate, 2); ?>%</td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="button button-small" onclick="editChannel(<?php echo $channel->id; ?>)">
                                                Modifica
                                            </button>
                                            <button class="button button-small" onclick="syncChannel(<?php echo $channel->id; ?>)">
                                                Sync
                                            </button>
                                            <button class="button button-small" onclick="viewLogs(<?php echo $channel->id; ?>)">
                                                Log
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function editChannel(channelId) {
            // Implementation for editing channel
        }
        
        function syncChannel(channelId) {
            if (confirm('Sincronizzare ora questo canale?')) {
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_sync_channel',
                        channel_id: channelId,
                        nonce: '<?php echo wp_create_nonce('wcefp_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Sincronizzazione avviata!');
                            location.reload();
                        } else {
                            alert('Errore: ' + (response.data.msg || 'Errore sconosciuto'));
                        }
                    }
                });
            }
        }
        
        function viewLogs(channelId) {
            window.open('?post_type=wcefp_event&page=wcefp-channels&tab=sync&channel_id=' + channelId, '_blank');
        }
        </script>
        <?php
    }

    private static function render_channel_configuration($channel_key) {
        $channel_info = self::$supported_channels[$channel_key] ?? null;
        if (!$channel_info) {
            echo '<div class="error"><p>Canale non supportato.</p></div>';
            return;
        }

        // Handle form submission
        if ($_POST['wcefp_save_channel'] ?? false) {
            self::save_channel_configuration($channel_key, $_POST);
            echo '<div class="updated"><p>Configurazione salvata con successo!</p></div>';
        }

        $existing_config = self::get_channel_configuration($channel_key);
        
        ?>
        <div class="wcefp-channel-config">
            <h2>Configurazione <?php echo $channel_info['name']; ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('wcefp_channel_config', 'wcefp_channel_config_nonce'); ?>
                <input type="hidden" name="wcefp_save_channel" value="1">
                <input type="hidden" name="channel_key" value="<?php echo esc_attr($channel_key); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="channel_status">Stato</label></th>
                        <td>
                            <select name="channel_status" id="channel_status">
                                <option value="active" <?php selected($existing_config['status'] ?? '', 'active'); ?>>Attivo</option>
                                <option value="inactive" <?php selected($existing_config['status'] ?? '', 'inactive'); ?>>Inattivo</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="api_key">API Key</label></th>
                        <td>
                            <input type="password" name="api_key" id="api_key" 
                                   value="<?php echo esc_attr($existing_config['api_key'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">Chiave API fornita da <?php echo $channel_info['name']; ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="api_secret">API Secret</label></th>
                        <td>
                            <input type="password" name="api_secret" id="api_secret" 
                                   value="<?php echo esc_attr($existing_config['api_secret'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="commission_rate">Tasso Commissioni (%)</label></th>
                        <td>
                            <input type="number" name="commission_rate" id="commission_rate" 
                                   value="<?php echo esc_attr($existing_config['commission_rate'] ?? '15'); ?>" 
                                   step="0.01" min="0" max="100" class="small-text" />
                            <p class="description">Percentuale di commissione applicata da questo canale</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="sync_frequency">Frequenza Sincronizzazione</label></th>
                        <td>
                            <select name="sync_frequency" id="sync_frequency">
                                <option value="hourly" <?php selected($existing_config['sync_frequency'] ?? '', 'hourly'); ?>>Ogni ora</option>
                                <option value="twicedaily" <?php selected($existing_config['sync_frequency'] ?? '', 'twicedaily'); ?>>Due volte al giorno</option>
                                <option value="daily" <?php selected($existing_config['sync_frequency'] ?? '', 'daily'); ?>>Giornaliera</option>
                                <option value="manual" <?php selected($existing_config['sync_frequency'] ?? '', 'manual'); ?>>Solo manuale</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="default_markup">Markup Predefinito (%)</label></th>
                        <td>
                            <input type="number" name="default_markup" id="default_markup" 
                                   value="<?php echo esc_attr($existing_config['default_markup'] ?? '0'); ?>" 
                                   step="0.01" class="small-text" />
                            <p class="description">Ricarico sui prezzi per coprire commissioni (può essere negativo per sconti)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="availability_buffer">Buffer Disponibilità</label></th>
                        <td>
                            <input type="number" name="availability_buffer" id="availability_buffer" 
                                   value="<?php echo esc_attr($existing_config['availability_buffer'] ?? '0'); ?>" 
                                   min="0" class="small-text" />
                            <p class="description">Numero di posti da riservare (non inviati al canale)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Salva Configurazione" />
                    <a href="?post_type=wcefp_event&page=wcefp-channels&tab=channels" class="button">Annulla</a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_mappings_tab() {
        echo '<div class="wcefp-mappings-content"><h2>Mappature Prodotto-Canale</h2><p>Gestione mappature tra prodotti locali e canali esterni - Da implementare</p></div>';
    }

    private static function render_sync_tab() {
        echo '<div class="wcefp-sync-content"><h2>Log Sincronizzazione</h2><p>Cronologia e stato delle sincronizzazioni - Da implementare</p></div>';
    }

    private static function render_analytics_tab() {
        echo '<div class="wcefp-analytics-content"><h2>Analytics Canali</h2><p>Statistiche performance e revenues per canale - Da implementare</p></div>';
    }

    // Meta boxes for products
    public static function add_meta_boxes() {
        add_meta_box(
            'wcefp_channel_distribution',
            'Distribuzione Canali',
            [__CLASS__, 'channel_distribution_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public static function channel_distribution_meta_box($post) {
        // Check if this is an event/experience product
        $product_types = wp_get_object_terms($post->ID, 'product_type', ['fields' => 'slugs']);
        if (!array_intersect($product_types, ['wcefp_event', 'wcefp_experience'])) {
            echo '<p>La distribuzione canali è disponibile solo per prodotti Eventi ed Esperienze.</p>';
            return;
        }

        wp_nonce_field('wcefp_channel_distribution', 'wcefp_channel_distribution_nonce');
        
        global $wpdb;
        $channels = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wcefp_channels WHERE status = 'active'");
        $mappings = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcefp_channel_mappings WHERE product_id = %d", $post->ID));
        
        $mapped_channels = array_column($mappings, 'channel_id');
        
        ?>
        <div class="wcefp-channel-distribution">
            <p><strong>Seleziona i canali per distribuire questo prodotto:</strong></p>
            
            <?php if (empty($channels)): ?>
                <p class="description">
                    Nessun canale configurato. 
                    <a href="?post_type=wcefp_event&page=wcefp-channels" target="_blank">Configura ora</a>
                </p>
            <?php else: ?>
                <div class="wcefp-channel-checkboxes">
                    <?php foreach ($channels as $channel): ?>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" 
                                   name="wcefp_distribute_channels[]" 
                                   value="<?php echo $channel->id; ?>"
                                   <?php checked(in_array($channel->id, $mapped_channels)); ?> />
                            <?php echo esc_html($channel->channel_name); ?>
                            <small style="color: #666; display: block; margin-left: 20px;">
                                Commissioni: <?php echo $channel->commission_rate; ?>% • 
                                Stato: <?php echo ucfirst($channel->sync_status); ?>
                            </small>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="button" id="wcefp-sync-now" class="button">
                        Sincronizza Ora
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($mappings)): ?>
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <p><strong>Stato Sincronizzazione:</strong></p>
                    <?php foreach ($mappings as $mapping): ?>
                        <?php 
                        $channel = $wpdb->get_row($wpdb->prepare("SELECT channel_name FROM {$wpdb->prefix}wcefp_channels WHERE id = %d", $mapping->channel_id));
                        ?>
                        <div style="margin-bottom: 8px; font-size: 12px;">
                            <strong><?php echo $channel->channel_name ?? 'Canale sconosciuto'; ?>:</strong>
                            <span class="wcefp-sync-status <?php echo esc_attr($mapping->sync_status); ?>">
                                <?php echo ucfirst($mapping->sync_status); ?>
                            </span>
                            <?php if ($mapping->last_sync): ?>
                                <br><small>Ultimo sync: <?php echo human_time_diff(strtotime($mapping->last_sync), time()) . ' fa'; ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wcefp-sync-now').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Sincronizzando...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_sync_product_channels',
                        product_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce('wcefp_admin'); ?>'
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Sincronizza Ora');
                        if (response.success) {
                            alert('Sincronizzazione completata!');
                        } else {
                            alert('Errore sincronizzazione: ' + (response.data.msg || 'Errore sconosciuto'));
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Sincronizza Ora');
                        alert('Errore di connessione');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public static function save_channel_meta($post_id) {
        if (!isset($_POST['wcefp_channel_distribution_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_channel_distribution_nonce'], 'wcefp_channel_distribution')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $selected_channels = $_POST['wcefp_distribute_channels'] ?? [];
        
        global $wpdb;
        
        // Remove old mappings
        $wpdb->delete(
            $wpdb->prefix . 'wcefp_channel_mappings',
            ['product_id' => $post_id],
            ['%d']
        );
        
        // Add new mappings
        foreach ($selected_channels as $channel_id) {
            $wpdb->insert(
                $wpdb->prefix . 'wcefp_channel_mappings',
                [
                    'product_id' => $post_id,
                    'channel_id' => intval($channel_id),
                    'sync_status' => 'pending'
                ]
            );
        }
    }

    // Configuration management
    private static function save_channel_configuration($channel_key, $data) {
        if (!wp_verify_nonce($data['wcefp_channel_config_nonce'], 'wcefp_channel_config')) {
            return false;
        }

        global $wpdb;
        
        $channel_info = self::$supported_channels[$channel_key];
        
        $config = [
            'channel_key' => $channel_key,
            'channel_name' => $channel_info['name'],
            'status' => sanitize_text_field($data['channel_status']),
            'api_endpoint' => $channel_info['api_endpoint'],
            'commission_rate' => floatval($data['commission_rate']),
            'sync_frequency' => sanitize_text_field($data['sync_frequency']),
            'api_credentials' => wp_json_encode([
                'api_key' => sanitize_text_field($data['api_key']),
                'api_secret' => sanitize_text_field($data['api_secret'])
            ]),
            'settings' => wp_json_encode([
                'default_markup' => floatval($data['default_markup']),
                'availability_buffer' => intval($data['availability_buffer'])
            ])
        ];

        // Check if channel already exists
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wcefp_channels WHERE channel_key = %s", $channel_key));
        
        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'wcefp_channels',
                $config,
                ['id' => $existing->id]
            );
        } else {
            $wpdb->insert($wpdb->prefix . 'wcefp_channels', $config);
        }
        
        return true;
    }

    private static function get_channel_configuration($channel_key) {
        global $wpdb;
        
        $channel = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcefp_channels WHERE channel_key = %s", $channel_key));
        
        if (!$channel) {
            return [];
        }

        $credentials = json_decode($channel->api_credentials, true) ?: [];
        $settings = json_decode($channel->settings, true) ?: [];
        
        return array_merge([
            'status' => $channel->status,
            'commission_rate' => $channel->commission_rate,
            'sync_frequency' => $channel->sync_frequency
        ], $credentials, $settings);
    }

    private static function is_channel_configured($channel_key) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wcefp_channels WHERE channel_key = %s", $channel_key));
    }

    // AJAX handlers
    public static function ajax_sync_channel() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        $channel_id = intval($_POST['channel_id']);
        $result = self::sync_single_channel($channel_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public static function ajax_test_channel_connection() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        $channel_key = sanitize_text_field($_POST['channel_key']);
        $result = self::test_channel_connection($channel_key);
        
        wp_send_json($result);
    }

    // Sync methods (mock implementations)
    public static function sync_all_channels() {
        global $wpdb;
        
        $channels = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wcefp_channels WHERE status = 'active'");
        
        foreach ($channels as $channel) {
            self::sync_single_channel($channel->id);
        }
    }

    private static function sync_single_channel($channel_id) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        $channel = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcefp_channels WHERE id = %d", $channel_id));
        
        if (!$channel) {
            return ['success' => false, 'msg' => 'Channel not found'];
        }

        // Mock sync process
        $sync_success = (rand(1, 10) > 2); // 80% success rate for demo
        
        // Update channel sync status
        $wpdb->update(
            $wpdb->prefix . 'wcefp_channels',
            [
                'last_sync' => current_time('mysql'),
                'sync_status' => $sync_success ? 'success' : 'error'
            ],
            ['id' => $channel_id]
        );
        
        // Log sync attempt
        $sync_duration = microtime(true) - $start_time;
        
        $wpdb->insert($wpdb->prefix . 'wcefp_channel_sync_logs', [
            'channel_id' => $channel_id,
            'sync_type' => 'full_sync',
            'status' => $sync_success ? 'success' : 'error',
            'message' => $sync_success ? 'Sync completed successfully' : 'Mock sync error',
            'sync_duration' => $sync_duration
        ]);
        
        return [
            'success' => $sync_success,
            'msg' => $sync_success ? 'Sync completed' : 'Sync failed',
            'duration' => $sync_duration
        ];
    }

    private static function test_channel_connection($channel_key) {
        // Mock connection test
        $success = (rand(1, 10) > 3); // 70% success rate for demo
        
        return [
            'success' => $success,
            'data' => [
                'msg' => $success ? 'Connection successful' : 'Connection failed',
                'response_time' => rand(100, 2000) . 'ms'
            ]
        ];
    }

    // Hook handlers
    public static function sync_occurrence_to_channels($occurrence_id, $product_id) {
        // Sync specific occurrence changes to all mapped channels
        self::queue_product_sync($product_id);
    }

    public static function sync_product_to_channels($product_id) {
        // Sync product changes to all mapped channels
        self::queue_product_sync($product_id);
    }

    private static function queue_product_sync($product_id) {
        // In a real implementation, this would queue the sync job
        // For now, we'll just update the mapping status
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'wcefp_channel_mappings',
            ['sync_status' => 'pending'],
            ['product_id' => $product_id]
        );
    }
}

// Auto-initialization removed - this class is now managed by the service provider system
// Use WCEFP\Admin\MenuManager for consolidated admin menu management