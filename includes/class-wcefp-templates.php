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
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function assets(){
        wp_enqueue_style('wcefp-templates', WCEFP_PLUGIN_URL.'assets/css/templates.css', [], WCEFP_VERSION);
        wp_enqueue_script('wcefp-templates', WCEFP_PLUGIN_URL.'assets/js/templates.js', ['jquery'], WCEFP_VERSION, true);
        wp_localize_script('wcefp-templates', 'WCEFPTpl', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
        ]);
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
        <article class="wcefp-card">
            <div class="wcefp-card-media">
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>"/>
                <?php echo $badge; ?>
            </div>
            <div class="wcefp-card-body">
                <h3 class="wcefp-card-title"><?php echo esc_html($title); ?></h3>
                <ul class="wcefp-card-meta">
                    <li><strong><?php _e('Prossima data','wceventsfp'); ?>:</strong> <?php echo esc_html($dateLabel); ?></li>
                    <?php if ($priceA !== ''): ?>
                        <li><strong><?php _e('Prezzo adulto','wceventsfp'); ?>:</strong> € <?php echo esc_html(number_format((float)$priceA,2,',','.')); ?></li>
                    <?php endif; ?>
                    <?php if ($priceC !== ''): ?>
                        <li><strong><?php _e('Prezzo bambino','wceventsfp'); ?>:</strong> € <?php echo esc_html(number_format((float)$priceC,2,',','.')); ?></li>
                    <?php endif; ?>
                </ul>
                <div class="wcefp-card-cta">
                    <a class="button" href="<?php echo esc_url(get_permalink($pid)); ?>"><?php _e('Dettagli e prenota','wceventsfp'); ?></a>
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

        ob_start(); echo '<div class="wcefp-grid">';
        foreach ($posts as $p){
            echo do_shortcode('[wcefp_event_card id="'.$p->ID.'"]');
        }
        echo '</div>';
        return ob_get_clean();
    }
}

WCEFP_Templates::init();
