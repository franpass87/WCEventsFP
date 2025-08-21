<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode OTA-style (card singola e griglia):
 *  - [wcefp_event_card id="123"]
 *  - [wcefp_event_grid ids="1,2,3"] oppure [wcefp_event_grid type="wcefp_experience" limit="6"]
 */
class WCEFP_Templates {

    public static function init(){
        add_shortcode('wcefp_event_card', [__CLASS__, 'shortcode_card']);
        add_shortcode('wcefp_event_grid', [__CLASS__, 'shortcode_grid']);
        add_shortcode('wcefp_countdown', [__CLASS__, 'shortcode_countdown']);
        add_shortcode('wcefp_featured_events', [__CLASS__, 'shortcode_featured']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function assets(){
        wp_enqueue_style('wcefp-templates', WCEFP_PLUGIN_URL.'assets/css/templates.css', [], WCEFP_VERSION);
        wp_enqueue_script('wcefp-templates', WCEFP_PLUGIN_URL.'assets/js/templates.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-templates', 'WCEFPTpl', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
            'strings' => [
                'days' => __('giorni','wceventsfp'),
                'hours' => __('ore','wceventsfp'),
                'minutes' => __('minuti','wceventsfp'),
                'seconds' => __('secondi','wceventsfp'),
                'expired' => __('Evento iniziato','wceventsfp'),
            ]
        ]);
    }

    /* ------------ COUNTDOWN TIMER ------------ */
    public static function shortcode_countdown($atts){
        $a = shortcode_atts(['id'=>0, 'style'=>'default'], $atts);
        $pid = intval($a['id']);
        if (!$pid) return '';

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $now = current_time('mysql');
        $row = $wpdb->get_row($wpdb->prepare("SELECT start_datetime FROM $tbl WHERE product_id=%d AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT 1", $pid, $now), ARRAY_A);
        
        if (!$row) return '<p>'.__('Nessun evento programmato','wceventsfp').'</p>';
        
        $eventTime = strtotime($row['start_datetime']);
        $eventTitle = get_the_title($pid);
        
        ob_start(); ?>
        <div class="wcefp-countdown" data-event-time="<?php echo esc_attr($eventTime); ?>" data-style="<?php echo esc_attr($a['style']); ?>">
            <h4><?php echo esc_html($eventTitle); ?></h4>
            <div class="wcefp-countdown-timer">
                <div class="wcefp-countdown-item">
                    <span class="wcefp-countdown-number" id="days">0</span>
                    <span class="wcefp-countdown-label"><?php _e('Giorni','wceventsfp'); ?></span>
                </div>
                <div class="wcefp-countdown-item">
                    <span class="wcefp-countdown-number" id="hours">0</span>
                    <span class="wcefp-countdown-label"><?php _e('Ore','wceventsfp'); ?></span>
                </div>
                <div class="wcefp-countdown-item">
                    <span class="wcefp-countdown-number" id="minutes">0</span>
                    <span class="wcefp-countdown-label"><?php _e('Minuti','wceventsfp'); ?></span>
                </div>
                <div class="wcefp-countdown-item">
                    <span class="wcefp-countdown-number" id="seconds">0</span>
                    <span class="wcefp-countdown-label"><?php _e('Secondi','wceventsfp'); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ------------ FEATURED EVENTS ------------ */
    public static function shortcode_featured($atts){
        $a = shortcode_atts(['limit'=>3], $atts);
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => max(1, intval($a['limit'])),
            'post_status' => 'publish',
            'tax_query' => [
                ['taxonomy'=>'product_type','field'=>'slug','terms'=>['wcefp_event','wcefp_experience'],'operator'=>'IN']
            ],
            'meta_query' => [
                [
                    'key' => '_featured',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            'orderby' => 'rand'
        ];
        
        $q = new WP_Query($args);
        $posts = $q->posts;
        
        if (!$posts) {
            // Fallback to random events if no featured ones
            $args['meta_query'] = [];
            $q = new WP_Query($args);
            $posts = $q->posts;
        }
        
        if (!$posts) return '<p>'.__('Nessun evento in evidenza','wceventsfp').'</p>';
        
        ob_start(); 
        echo '<div class="wcefp-featured-container">';
        echo '<h3>'.__('Eventi in evidenza','wceventsfp').'</h3>';
        echo '<div class="wcefp-grid wcefp-featured-grid">';
        foreach ($posts as $p){
            echo do_shortcode('[wcefp_event_card id="'.$p->ID.'"]');
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function render_map($id, $lat, $lng) {
        $id_attr = esc_attr($id);
        $lat_f = floatval($lat);
        $lng_f = floatval($lng);
        ob_start(); ?>
        <div id="<?php echo $id_attr; ?>" class="wcefp-map"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var map = L.map('<?php echo esc_js($id_attr); ?>').setView([<?php echo esc_js($lat_f); ?>, <?php echo esc_js($lng_f); ?>], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
            L.marker([<?php echo esc_js($lat_f); ?>, <?php echo esc_js($lng_f); ?>]).addTo(map);
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /* ------------ CARD SINGOLA ------------ */
    public static function shortcode_card($atts){
        $a = shortcode_atts(['id'=>0], $atts);
        $pid = intval($a['id']);
        if (!$pid) return '';

        $p = wc_get_product($pid);
        if (!$p || !in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) return '';

        $title = get_the_title($pid);
        $img   = get_the_post_thumbnail_url($pid, 'large');
        $img   = $img ?: wc_placeholder_img_src('large');
        $priceA = get_post_meta($pid, '_wcefp_price_adult', true);
        $priceC = get_post_meta($pid, '_wcefp_price_child', true);
        $meeting = sanitize_text_field(get_post_meta($pid, '_wcefp_meeting_point', true));
        $points = get_option('wcefp_meetingpoints', []);
        $lat = $lng = '';
        if ($meeting && is_array($points)) {
            foreach ($points as $pt) {
                if (is_array($pt) && isset($pt['address']) && $pt['address'] === $meeting) {
                    $lat = $pt['lat'] ?? '';
                    $lng = $pt['lng'] ?? '';
                    break;
                }
            }
        }

        // prossima disponibilità e posti
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $now = current_time('mysql');
        $row = $wpdb->get_row($wpdb->prepare("SELECT start_datetime, capacity, booked, status FROM $tbl WHERE product_id=%d AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT 1", $pid, $now), ARRAY_A);

        $badge = '';
        if ($row) {
            $avail = max(0, intval($row['capacity']) - intval($row['booked']));
            if ($row['status'] !== 'active' || $avail <= 0) $badge = '<span class="wcefp-badge soldout">Sold-out</span>';
            else $badge = '<span class="wcefp-badge avail">'.esc_html($avail).' '.esc_html__('posti','wceventsfp').'</span>';
        }

        $dateLabel = $row ? date_i18n('d/m/Y H:i', strtotime($row['start_datetime'])) : __('Nessuna data programmata','wceventsfp');

        ob_start(); ?>
        <article class="wcefp-card" data-type="<?php echo esc_attr($p->get_type()); ?>" data-price="<?php echo esc_attr($priceA); ?>">
            <div class="wcefp-card-media">
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"/>
                <?php echo $badge; ?>
                <?php 
                // Add featured badge for some events
                if (get_post_meta($pid, '_featured', true) || rand(1,10) > 7): ?>
                <span class="wcefp-badge featured">In evidenza</span>
                <?php endif; ?>
            </div>
            <div class="wcefp-card-body">
                <h3 class="wcefp-card-title"><?php echo esc_html($title); ?></h3>
                <ul class="wcefp-card-meta">
                    <li><strong><?php _e('Prossima data','wceventsfp'); ?>:</strong> <?php echo esc_html($dateLabel); ?></li>
                    <?php if ($priceA !== ''): ?>
                        <li class="price"><strong><?php _e('Prezzo adulto','wceventsfp'); ?>:</strong> €<?php echo esc_html(number_format((float)$priceA,2,',','.')); ?></li>
                    <?php endif; ?>
                    <?php if ($priceC !== ''): ?>
                        <li><strong><?php _e('Prezzo bambino','wceventsfp'); ?>:</strong> €<?php echo esc_html(number_format((float)$priceC,2,',','.')); ?></li>
                    <?php endif; ?>
                    <?php if ($meeting): ?>
                        <li><strong><?php _e('Meeting point','wceventsfp'); ?>:</strong> <?php echo esc_html($meeting); ?></li>
                    <?php endif; ?>
                    <?php 
                    // Add duration info if available
                    $duration = get_post_meta($pid, '_wcefp_duration', true);
                    if ($duration): ?>
                        <li><strong><?php _e('Durata','wceventsfp'); ?>:</strong> <?php echo esc_html($duration); ?></li>
                    <?php endif; ?>
                    <?php 
                    // Add languages info
                    $languages = get_post_meta($pid, '_wcefp_languages', true);
                    if ($languages): ?>
                        <li><strong><?php _e('Lingue','wceventsfp'); ?>:</strong> <?php echo esc_html(implode(', ', $languages)); ?></li>
                    <?php endif; ?>
                </ul>
                <?php if ($meeting && $lat && $lng) {
                    echo self::render_map('wcefp-map-'.$pid, $lat, $lng);
                } ?>
                <div class="wcefp-card-cta">
                    <a class="button" href="<?php echo esc_url(get_permalink($pid)); ?>">
                        <span><?php _e('Dettagli e prenota','wceventsfp'); ?></span>
                        <span style="margin-left: 4px;">→</span>
                    </a>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /* ------------ GRIGLIA ------------ */
    public static function shortcode_grid($atts){
        $a = shortcode_atts([
            'ids'  => '',
            'type' => '',   // 'wcefp_event' | 'wcefp_experience'
            'limit'=> 6
        ], $atts);

        $ids = array_filter(array_map('intval', explode(',', (string)$a['ids'])));
        $posts = [];

        if (!empty($ids)) {
            $args = ['post_type'=>'product','post__in'=>$ids,'posts_per_page'=>count($ids),'orderby'=>'post__in'];
        } else {
            $tax = [];
            if ($a['type']==='wcefp_event' || $a['type']==='wcefp_experience') {
                $tax[] = [
                    'taxonomy' => 'product_type', 'field'=>'slug',
                    'terms' => [$a['type']], 'operator'=>'IN'
                ];
            } else {
                $tax[] = ['taxonomy'=>'product_type','field'=>'slug','terms'=>['wcefp_event','wcefp_experience'],'operator'=>'IN'];
            }
            $args = [
                'post_type'=>'product',
                'posts_per_page'=> max(1, intval($a['limit'])),
                'post_status'=>'publish',
                'tax_query'=>$tax,
                'orderby'=>'date','order'=>'DESC'
            ];
        }
        $q = new WP_Query($args); $posts = $q->posts;

        if (!$posts) return '<p>'.__('Nessuna esperienza disponibile','wceventsfp').'</p>';

        ob_start(); 
        echo '<div class="wcefp-event-grid-container">';
        echo '<div class="wcefp-grid">';
        foreach ($posts as $p){
            echo do_shortcode('[wcefp_event_card id="'.$p->ID.'"]');
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }
}

WCEFP_Templates::init();
