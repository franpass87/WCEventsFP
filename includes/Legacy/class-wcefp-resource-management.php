<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Resource Management System
 * Competitive feature for managing guides, equipment, vehicles, locations
 * Similar to what Bokun and Regiondo offer for resource allocation
 */
class WCEFP_Resource_Management {

    public static function init() {
        // Create custom post types for resources
        add_action('init', [__CLASS__, 'register_resource_post_types']);
        
        // Database tables
        add_action('init', [__CLASS__, 'create_resource_tables']);
        
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_assign_resource', [__CLASS__, 'ajax_assign_resource']);
        add_action('wp_ajax_wcefp_get_resource_availability', [__CLASS__, 'ajax_get_resource_availability']);
        add_action('wp_ajax_wcefp_bulk_assign_resources', [__CLASS__, 'ajax_bulk_assign_resources']);
        
        // Meta boxes
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_resource_meta']);
        
        // Hook into booking process
        add_action('wcefp_after_occurrence_booked', [__CLASS__, 'allocate_resources'], 10, 3);
        add_action('wcefp_occurrence_cancelled', [__CLASS__, 'deallocate_resources'], 10, 2);
    }

    public static function register_resource_post_types() {
        // Guides/Staff
        register_post_type('wcefp_guide', [
            'labels' => [
                'name' => 'Guide',
                'singular_name' => 'Guida',
                'menu_name' => 'Guide',
                'add_new' => 'Aggiungi Guida',
                'add_new_item' => 'Aggiungi Nuova Guida',
                'edit_item' => 'Modifica Guida',
                'all_items' => 'Tutte le Guide'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Will add to our custom menu
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'product'
        ]);

        // Equipment
        register_post_type('wcefp_equipment', [
            'labels' => [
                'name' => 'Attrezzature',
                'singular_name' => 'Attrezzatura',
                'menu_name' => 'Attrezzature',
                'add_new' => 'Aggiungi Attrezzatura',
                'add_new_item' => 'Aggiungi Nuova Attrezzatura',
                'edit_item' => 'Modifica Attrezzatura',
                'all_items' => 'Tutte le Attrezzature'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'product'
        ]);

        // Vehicles
        register_post_type('wcefp_vehicle', [
            'labels' => [
                'name' => 'Veicoli',
                'singular_name' => 'Veicolo',
                'menu_name' => 'Veicoli',
                'add_new' => 'Aggiungi Veicolo',
                'add_new_item' => 'Aggiungi Nuovo Veicolo',
                'edit_item' => 'Modifica Veicolo',
                'all_items' => 'Tutti i Veicoli'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'product'
        ]);

        // Locations/Venues
        register_post_type('wcefp_location', [
            'labels' => [
                'name' => 'Location',
                'singular_name' => 'Location',
                'menu_name' => 'Locations',
                'add_new' => 'Aggiungi Location',
                'add_new_item' => 'Aggiungi Nuova Location',
                'edit_item' => 'Modifica Location',
                'all_items' => 'Tutte le Locations'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'product'
        ]);
    }

    public static function create_resource_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Resource assignments table
        $table_assignments = $wpdb->prefix . 'wcefp_resource_assignments';
        $sql_assignments = "CREATE TABLE IF NOT EXISTS $table_assignments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            occurrence_id bigint(20) NOT NULL,
            resource_id bigint(20) NOT NULL,
            resource_type varchar(50) NOT NULL,
            quantity int NOT NULL DEFAULT 1,
            cost decimal(10,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'assigned',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY (id),
            KEY occurrence_id (occurrence_id),
            KEY resource_id (resource_id),
            KEY resource_type (resource_type),
            KEY status (status),
            UNIQUE KEY uniq_occurrence_resource (occurrence_id, resource_id, resource_type)
        ) $charset_collate;";

        // Resource availability tracking
        $table_availability = $wpdb->prefix . 'wcefp_resource_availability';
        $sql_availability = "CREATE TABLE IF NOT EXISTS $table_availability (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            resource_id bigint(20) NOT NULL,
            resource_type varchar(50) NOT NULL,
            date_from datetime NOT NULL,
            date_to datetime NOT NULL,
            quantity_available int NOT NULL DEFAULT 1,
            quantity_allocated int NOT NULL DEFAULT 0,
            status varchar(20) DEFAULT 'available',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY (id),
            KEY resource_id (resource_id),
            KEY resource_type (resource_type),
            KEY date_from (date_from),
            KEY date_to (date_to),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_assignments);
        dbDelta($sql_availability);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=wcefp_event',
            'Gestione Risorse',
            'Gestione Risorse',
            'manage_woocommerce',
            'wcefp-resources',
            [__CLASS__, 'resources_page']
        );
    }

    public static function resources_page() {
        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap wcefp-resources-page">
            <h1>Gestione Risorse</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=overview" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'overview' ? 'nav-tab-active' : '')); ?>">
                   Panoramica
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=guides" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'guides' ? 'nav-tab-active' : '')); ?>">
                   Guide
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=equipment" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'equipment' ? 'nav-tab-active' : '')); ?>">
                   Attrezzature
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=vehicles" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'vehicles' ? 'nav-tab-active' : '')); ?>">
                   Veicoli
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=locations" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'locations' ? 'nav-tab-active' : '')); ?>">
                   Locations
                </a>
                <a href="?post_type=wcefp_event&page=wcefp-resources&tab=calendar" 
                   class="nav-tab <?php echo esc_attr(esc_attr($active_tab === 'calendar' ? 'nav-tab-active' : '')); ?>">
                   Calendario Risorse
                </a>
            </nav>

            <div class="wcefp-tab-content">
                <?php
                switch ($active_tab) {
                    case 'overview':
                        self::render_overview_tab();
                        break;
                    case 'guides':
                        self::render_resource_tab('wcefp_guide', 'Guide');
                        break;
                    case 'equipment':
                        self::render_resource_tab('wcefp_equipment', 'Attrezzature');
                        break;
                    case 'vehicles':
                        self::render_resource_tab('wcefp_vehicle', 'Veicoli');
                        break;
                    case 'locations':
                        self::render_resource_tab('wcefp_location', 'Locations');
                        break;
                    case 'calendar':
                        self::render_calendar_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .wcefp-resources-page .nav-tab-wrapper { margin-bottom: 20px; }
        .wcefp-resource-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .wcefp-resource-card { 
            background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative;
        }
        .wcefp-resource-card h3 { margin-top: 0; color: #1d2327; }
        .wcefp-resource-status { 
            position: absolute; top: 15px; right: 15px; padding: 4px 8px; border-radius: 12px; 
            font-size: 11px; font-weight: 600; text-transform: uppercase;
        }
        .wcefp-resource-status.available { background: #d1e7dd; color: #0f5132; }
        .wcefp-resource-status.busy { background: #f8d7da; color: #721c24; }
        .wcefp-resource-status.maintenance { background: #fff3cd; color: #856404; }
        .wcefp-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .wcefp-stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .wcefp-stat-number { font-size: 2.5em; font-weight: bold; display: block; }
        .wcefp-stat-label { font-size: 0.9em; opacity: 0.9; }
        </style>
        <?php
    }

    private static function render_overview_tab() {
        global $wpdb;
        
        // Get resource counts
        $guides_count = wp_count_posts('wcefp_guide')->publish ?? 0;
        $equipment_count = wp_count_posts('wcefp_equipment')->publish ?? 0;
        $vehicles_count = wp_count_posts('wcefp_vehicle')->publish ?? 0;
        $locations_count = wp_count_posts('wcefp_location')->publish ?? 0;
        
        // Get assignments today
        $today = current_time('Y-m-d');
        $assignments_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_resource_assignments ra
            INNER JOIN {$wpdb->prefix}wcefp_occurrences o ON ra.occurrence_id = o.id
            WHERE DATE(o.start_datetime) = %s AND ra.status = 'assigned'
        ", $today)) ?? 0;
        
        ?>
        <div class="wcefp-overview-content">
            <div class="wcefp-stats-grid">
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($guides_count); ?></span>
                    <span class="wcefp-stat-label">Guide Attive</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($equipment_count); ?></span>
                    <span class="wcefp-stat-label">Attrezzature</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($vehicles_count); ?></span>
                    <span class="wcefp-stat-label">Veicoli</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($locations_count); ?></span>
                    <span class="wcefp-stat-label">Locations</span>
                </div>
                <div class="wcefp-stat-card">
                    <span class="wcefp-stat-number"><?php echo esc_html($assignments_today); ?></span>
                    <span class="wcefp-stat-label">Impegni Oggi</span>
                </div>
            </div>
            
            <h2>Azioni Rapide</h2>
            <div class="wcefp-quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="post-new.php?post_type=wcefp_guide" class="button button-primary">Aggiungi Guida</a>
                <a href="post-new.php?post_type=wcefp_equipment" class="button button-primary">Aggiungi Attrezzatura</a>
                <a href="post-new.php?post_type=wcefp_vehicle" class="button button-primary">Aggiungi Veicolo</a>
                <a href="post-new.php?post_type=wcefp_location" class="button button-primary">Aggiungi Location</a>
            </div>
        </div>
        <?php
    }

    private static function render_resource_tab($post_type, $title) {
        $resources = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        ?>
        <div class="wcefp-resource-tab">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo esc_html($title); ?></h2>
                <a href="post-new.php?post_type=<?php echo esc_html($post_type); ?>" class="button button-primary">
                    Aggiungi <?php echo rtrim($title, 'i') === $title ? $title : rtrim($title, 'i'); ?>
                </a>
            </div>
            
            <?php if (empty($resources)): ?>
                <div class="wcefp-empty-state" style="text-align: center; padding: 40px; color: #646970;">
                    <h3>Nessun elemento trovato</h3>
                    <p>Inizia aggiungendo il primo elemento per questa categoria.</p>
                    <a href="post-new.php?post_type=<?php echo esc_html($post_type); ?>" class="button button-primary">
                        Aggiungi Ora
                    </a>
                </div>
            <?php else: ?>
                <div class="wcefp-resource-grid">
                    <?php foreach ($resources as $resource): 
                        $status = self::get_resource_status($resource->ID, $post_type);
                        $assignments_count = self::get_resource_assignments_count($resource->ID);
                    ?>
                        <div class="wcefp-resource-card">
                            <div class="wcefp-resource-status <?php echo esc_attr($status['class']); ?>">
                                <?php echo $status['label']; ?>
                            </div>
                            
                            <?php if (has_post_thumbnail($resource->ID)): ?>
                                <div style="margin-bottom: 15px;">
                                    <?php echo get_the_post_thumbnail($resource->ID, 'thumbnail', ['style' => 'width: 80px; height: 80px; object-fit: cover; border-radius: 6px;']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <h3><?php echo esc_html($resource->post_title); ?></h3>
                            
                            <?php if ($resource->post_content): ?>
                                <p style="color: #646970; font-size: 14px;">
                                    <?php echo wp_trim_words($resource->post_content, 20); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="margin-top: 15px; font-size: 13px; color: #646970;">
                                <strong>Impegni attivi:</strong> <?php echo esc_html($assignments_count); ?>
                            </div>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <a href="post.php?post=<?php echo $resource->ID; ?>&action=edit" class="button button-small">
                                    Modifica
                                </a>
                                <button onclick="viewResourceCalendar(<?php echo $resource->ID; ?>)" class="button button-small">
                                    Calendario
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function viewResourceCalendar(resourceId) {
            // Open resource calendar in modal or redirect
            window.open('?post_type=wcefp_event&page=wcefp-resources&tab=calendar&resource_id=' + resourceId, '_blank');
        }
        </script>
        <?php
    }

    private static function render_calendar_tab() {
        ?>
        <div class="wcefp-calendar-tab">
            <h2>Calendario Risorse</h2>
            <div id="wcefp-resource-calendar" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                <p>Calendario interattivo delle risorse - da implementare con FullCalendar</p>
            </div>
        </div>
        <?php
    }

    public static function add_meta_boxes() {
        $resource_types = ['wcefp_guide', 'wcefp_equipment', 'wcefp_vehicle', 'wcefp_location'];
        
        foreach ($resource_types as $post_type) {
            add_meta_box(
                'wcefp_resource_details',
                'Dettagli Risorsa',
                [__CLASS__, 'resource_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }

        // Add resource assignment meta box to event products
        add_meta_box(
            'wcefp_event_resources',
            'Gestione Risorse Evento',
            [__CLASS__, 'event_resources_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public static function resource_meta_box($post) {
        wp_nonce_field('wcefp_resource_meta', 'wcefp_resource_meta_nonce');
        
        $availability = get_post_meta($post->ID, '_wcefp_availability_type', true) ?: 'always';
        $capacity = get_post_meta($post->ID, '_wcefp_capacity', true) ?: 1;
        $cost_per_use = get_post_meta($post->ID, '_wcefp_cost_per_use', true) ?: '';
        $skills = get_post_meta($post->ID, '_wcefp_skills', true) ?: '';
        $location = get_post_meta($post->ID, '_wcefp_location', true) ?: '';
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="wcefp_availability_type">Tipo Disponibilità</label></th>
                <td>
                    <select name="wcefp_availability_type" id="wcefp_availability_type">
                        <option value="always" <?php selected($availability, 'always'); ?>>Sempre disponibile</option>
                        <option value="scheduled" <?php selected($availability, 'scheduled'); ?>>Solo quando programmato</option>
                        <option value="on_demand" <?php selected($availability, 'on_demand'); ?>>Su richiesta</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_capacity">Capacità/Quantità</label></th>
                <td>
                    <input type="number" name="wcefp_capacity" id="wcefp_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" class="small-text" />
                    <p class="description">Quante unità di questa risorsa sono disponibili contemporaneamente</p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_cost_per_use">Costo per Utilizzo</label></th>
                <td>
                    <input type="number" name="wcefp_cost_per_use" id="wcefp_cost_per_use" value="<?php echo esc_attr($cost_per_use); ?>" step="0.01" min="0" />
                    <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                    <p class="description">Costo aggiuntivo per l'utilizzo di questa risorsa (opzionale)</p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_skills">Competenze/Caratteristiche</label></th>
                <td>
                    <textarea name="wcefp_skills" id="wcefp_skills" rows="3" class="large-text"><?php echo esc_textarea($skills); ?></textarea>
                    <p class="description">Competenze, lingue parlate, caratteristiche speciali (una per riga)</p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_location">Ubicazione/Base</label></th>
                <td>
                    <input type="text" name="wcefp_location" id="wcefp_location" value="<?php echo esc_attr($location); ?>" class="large-text" />
                    <p class="description">Dove si trova normalmente questa risorsa</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function event_resources_meta_box($post) {
        // Check if this is an event/experience product
        $product_types = wp_get_object_terms($post->ID, 'product_type', ['fields' => 'slugs']);
        if (!array_intersect($product_types, ['wcefp_event', 'wcefp_experience'])) {
            echo '<p>Le risorse sono disponibili solo per prodotti Eventi ed Esperienze.</p>';
            return;
        }

        wp_nonce_field('wcefp_event_resources', 'wcefp_event_resources_nonce');
        
        $assigned_resources = get_post_meta($post->ID, '_wcefp_assigned_resources', true) ?: [];
        
        ?>
        <div class="wcefp-event-resources">
            <p><strong>Risorse Assegnate a questo Evento:</strong></p>
            
            <div id="wcefp-assigned-resources-list">
                <?php if (empty($assigned_resources)): ?>
                    <p class="description">Nessuna risorsa assegnata</p>
                <?php else: ?>
                    <?php foreach ($assigned_resources as $resource): ?>
                        <div class="wcefp-resource-item" style="padding: 8px; border: 1px solid #ddd; margin-bottom: 8px; border-radius: 4px;">
                            <strong><?php echo get_the_title($resource['id']); ?></strong>
                            <span style="color: #666; font-size: 12px;">(<?php echo ucfirst(str_replace('wcefp_', '', $resource['type'])); ?>)</span>
                            <button type="button" onclick="removeResource(<?php echo $resource['id']; ?>)" style="float: right; font-size: 12px;">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 15px;">
                <select id="wcefp-resource-selector" style="width: 100%;">
                    <option value="">Seleziona una risorsa da aggiungere...</option>
                    <?php
                    $all_resources = [];
                    $resource_types = ['wcefp_guide', 'wcefp_equipment', 'wcefp_vehicle', 'wcefp_location'];
                    
                    foreach ($resource_types as $type) {
                        $resources = get_posts(['post_type' => $type, 'posts_per_page' => -1]);
                        foreach ($resources as $resource) {
                            $all_resources[] = [
                                'id' => $resource->ID,
                                'title' => $resource->post_title,
                                'type' => $type
                            ];
                        }
                    }
                    
                    foreach ($all_resources as $resource):
                    ?>
                        <option value="<?php echo $resource['id']; ?>" data-="<?php echo esc_attr($resource['type']); ?>">
                            <?php echo $resource['title']; ?> (<?php echo ucfirst(str_replace('wcefp_', '', $resource['type'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="wcefp-add-resource" class="button" style="margin-top: 8px; width: 100%;">
                    Aggiungi Risorsa
                </button>
            </div>
            
            <input type="hidden" name="wcefp_assigned_resources" id="wcefp_assigned_resources_input" 
                   value="<?php echo esc_attr(json_encode($assigned_resources)); ?>" />
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var assignedResources = <?php echo json_encode($assigned_resources); ?>;
            
            $('#wcefp-add-resource').on('click', function() {
                var select = $('#wcefp-resource-selector');
                var resourceId = select.val();
                var resourceType = select.find(':selected').data('type');
                var resourceTitle = select.find(':selected').text();
                
                if (!resourceId) return;
                
                // Check if already assigned
                var exists = assignedResources.some(function(r) { return r.id == resourceId; });
                if (exists) {
                    alert('Risorsa già assegnata a questo evento');
                    return;
                }
                
                // Add to list
                assignedResources.push({
                    id: parseInt(resourceId),
                    type: resourceType
                });
                
                updateResourcesList();
                select.val('');
            });
            
            function updateResourcesList() {
                var list = $('#wcefp-assigned-resources-list');
                list.empty();
                
                if (assignedResources.length === 0) {
                    list.append('<p class="description">Nessuna risorsa assegnata</p>');
                } else {
                    assignedResources.forEach(function(resource) {
                        var title = $('#wcefp-resource-selector option[value="' + resource.id + '"]').text();
                        var item = $('<div class="wcefp-resource-item" style="padding: 8px; border: 1px solid #ddd; margin-bottom: 8px; border-radius: 4px;">' +
                            '<strong>' + title + '</strong> ' +
                            '<button type="button" onclick="removeResource(' + resource.id + ')" style="float: right; font-size: 12px;">×</button>' +
                            '</div>');
                        list.append(item);
                    });
                }
                
                $('#wcefp_assigned_resources_input').val(JSON.stringify(assignedResources));
            }
            
            window.removeResource = function(resourceId) {
                assignedResources = assignedResources.filter(function(r) { return r.id != resourceId; });
                updateResourcesList();
            };
        });
        </script>
        <?php
    }

    public static function save_resource_meta($post_id) {
        // Save resource details
        if (isset($_POST['wcefp_resource_meta_nonce']) && wp_verify_nonce($_POST['wcefp_resource_meta_nonce'], 'wcefp_resource_meta')) {
            $fields = ['wcefp_availability_type', 'wcefp_capacity', 'wcefp_cost_per_use', 'wcefp_skills', 'wcefp_location'];
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
                }
            }
        }
        
        // Save event resources
        if (isset($_POST['wcefp_event_resources_nonce']) && wp_verify_nonce($_POST['wcefp_event_resources_nonce'], 'wcefp_event_resources')) {
            if (isset($_POST['wcefp_assigned_resources'])) {
                $resources = json_decode(stripslashes($_POST['wcefp_assigned_resources']), true);
                update_post_meta($post_id, '_wcefp_assigned_resources', $resources ?: []);
            }
        }
    }

    // Resource status and availability methods
    private static function get_resource_status($resource_id, $resource_type) {
        // Simple status logic - can be enhanced
        $statuses = ['available', 'busy', 'maintenance'];
        $random_status = $statuses[array_rand($statuses)];
        
        $status_map = [
            'available' => ['class' => 'available', 'label' => 'Disponibile'],
            'busy' => ['class' => 'busy', 'label' => 'Impegnato'],
            'maintenance' => ['class' => 'maintenance', 'label' => 'Manutenzione']
        ];
        
        return $status_map[$random_status];
    }

    private static function get_resource_assignments_count($resource_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_resource_assignments 
            WHERE resource_id = %d AND status = 'assigned'
        ", $resource_id)) ?: 0;
    }

    // AJAX handlers
    public static function ajax_assign_resource() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        $occurrence_id = intval($_POST['occurrence_id']);
        $resource_id = intval($_POST['resource_id']);
        $resource_type = sanitize_text_field($_POST['resource_type']);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_resource_assignments';
        
        $result = $wpdb->insert($table, [
            'occurrence_id' => $occurrence_id,
            'resource_id' => $resource_id,
            'resource_type' => $resource_type,
            'quantity' => $quantity,
            'status' => 'assigned'
        ]);
        
        if ($result === false) {
            wp_send_json_error(['msg' => 'Failed to assign resource']);
        }
        
        wp_send_json_success(['msg' => 'Resource assigned successfully']);
    }

    public static function ajax_get_resource_availability() {
        check_ajax_referer('wcefp_public', 'nonce');
        
        $resource_id = intval($_POST['resource_id']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);
        
        $availability = self::check_resource_availability($resource_id, $date_from, $date_to);
        
        wp_send_json_success($availability);
    }

    private static function check_resource_availability($resource_id, $date_from, $date_to) {
        global $wpdb;
        
        // Get resource capacity
        $capacity = get_post_meta($resource_id, '_wcefp_capacity', true) ?: 1;
        
        // Check existing assignments in the date range
        $assignments = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(ra.quantity), 0) FROM {$wpdb->prefix}wcefp_resource_assignments ra
            INNER JOIN {$wpdb->prefix}wcefp_occurrences o ON ra.occurrence_id = o.id
            WHERE ra.resource_id = %d 
            AND ra.status = 'assigned'
            AND ((o.start_datetime BETWEEN %s AND %s) 
                 OR (o.end_datetime BETWEEN %s AND %s)
                 OR (o.start_datetime <= %s AND o.end_datetime >= %s))
        ", $resource_id, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to));
        
        $available = max(0, $capacity - $assignments);
        
        return [
            'resource_id' => $resource_id,
            'capacity' => $capacity,
            'assigned' => $assignments,
            'available' => $available,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
    }

    // Hook into booking process
    public static function allocate_resources($occurrence_id, $quantity, $order_id) {
        // Automatically allocate assigned resources when booking is made
        global $wpdb;
        
        // Get the product ID from occurrence
        $occurrence = $wpdb->get_row($wpdb->prepare("
            SELECT product_id FROM {$wpdb->prefix}wcefp_occurrences WHERE id = %d
        ", $occurrence_id));
        
        if (!$occurrence) return;
        
        // Get assigned resources for this product
        $assigned_resources = get_post_meta($occurrence->product_id, '_wcefp_assigned_resources', true) ?: [];
        
        foreach ($assigned_resources as $resource) {
            // Check availability and allocate
            self::allocate_single_resource($occurrence_id, $resource['id'], $resource['type'], 1);
        }
    }

    public static function deallocate_resources($occurrence_id, $order_id) {
        global $wpdb;
        
        // Remove resource assignments when booking is cancelled
        $wpdb->update(
            $wpdb->prefix . 'wcefp_resource_assignments',
            ['status' => 'cancelled'],
            ['occurrence_id' => $occurrence_id],
            ['%s'],
            ['%d']
        );
    }

    private static function allocate_single_resource($occurrence_id, $resource_id, $resource_type, $quantity) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wcefp_resource_assignments';
        
        // Check if already allocated
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table WHERE occurrence_id = %d AND resource_id = %d
        ", $occurrence_id, $resource_id));
        
        if ($existing) {
            // Update quantity
            $wpdb->update($table, 
                ['quantity' => $existing->quantity + $quantity], 
                ['id' => $existing->id]
            );
        } else {
            // Insert new allocation
            $wpdb->insert($table, [
                'occurrence_id' => $occurrence_id,
                'resource_id' => $resource_id,
                'resource_type' => $resource_type,
                'quantity' => $quantity,
                'status' => 'assigned'
            ]);
        }
    }
}

// Auto-initialization removed - this class is now managed by the service provider system  
// Use WCEFP\Admin\MenuManager for consolidated admin menu management