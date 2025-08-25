<?php
/**
 * Meeting Point Service
 * 
 * @package WCEFP
 * @subpackage Services\Domain
 * @since 2.2.0
 */

namespace WCEFP\Services\Domain;

use WCEFP\Core\SecurityManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable meeting points management service
 */
class MeetingPointService {
    
    /**
     * Meeting point types
     */
    const TYPE_ADDRESS = 'address';
    const TYPE_LANDMARK = 'landmark';
    const TYPE_COORDINATES = 'coordinates';
    const TYPE_FLEXIBLE = 'flexible';
    
    /**
     * Get available meeting points
     * 
     * @param array $filters Optional filters
     * @return array Available meeting points
     */
    public function get_meeting_points($filters = []) {
        // Get from CPT if available
        $cpt_meeting_points = $this->get_cpt_meeting_points($filters);
        
        // Get legacy meeting points from product meta
        $legacy_meeting_points = $this->get_legacy_meeting_points($filters);
        
        // Combine and deduplicate
        $all_meeting_points = array_merge($cpt_meeting_points, $legacy_meeting_points);
        
        // Apply filters
        if (!empty($filters['type'])) {
            $all_meeting_points = array_filter($all_meeting_points, function($mp) use ($filters) {
                return $mp['type'] === $filters['type'];
            });
        }
        
        if (!empty($filters['location'])) {
            $all_meeting_points = array_filter($all_meeting_points, function($mp) use ($filters) {
                return stripos($mp['address'] ?? '', $filters['location']) !== false ||
                       stripos($mp['city'] ?? '', $filters['location']) !== false;
            });
        }
        
        return apply_filters('wcefp_meeting_points', $all_meeting_points, $filters);
    }
    
    /**
     * Get meeting points from CPT
     * 
     * @param array $filters Filters
     * @return array Meeting points from CPT
     */
    private function get_cpt_meeting_points($filters = []) {
        $args = [
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => []
        ];
        
        // Add meta query filters if specified
        if (!empty($filters['city'])) {
            $args['meta_query'][] = [
                'key' => '_wcefp_mp_city',
                'value' => $filters['city'],
                'compare' => 'LIKE'
            ];
        }
        
        $posts = get_posts($args);
        $meeting_points = [];
        
        foreach ($posts as $post) {
            $meeting_point = $this->format_cpt_meeting_point($post);
            if ($meeting_point) {
                $meeting_points[] = $meeting_point;
            }
        }
        
        return $meeting_points;
    }
    
    /**
     * Format CPT meeting point data
     * 
     * @param \WP_Post $post Post object
     * @return array|null Formatted meeting point
     */
    private function format_cpt_meeting_point($post) {
        if (!$post) {
            return null;
        }
        
        return [
            'id' => $post->ID,
            'source' => 'cpt',
            'title' => $post->post_title,
            'description' => $post->post_content,
            'type' => get_post_meta($post->ID, '_wcefp_mp_type', true) ?: self::TYPE_ADDRESS,
            'address' => get_post_meta($post->ID, '_wcefp_mp_address', true),
            'city' => get_post_meta($post->ID, '_wcefp_mp_city', true),
            'postal_code' => get_post_meta($post->ID, '_wcefp_mp_postal_code', true),
            'country' => get_post_meta($post->ID, '_wcefp_mp_country', true) ?: 'IT',
            'coordinates' => [
                'lat' => (float) get_post_meta($post->ID, '_wcefp_mp_latitude', true),
                'lng' => (float) get_post_meta($post->ID, '_wcefp_mp_longitude', true)
            ],
            'contact_info' => [
                'phone' => get_post_meta($post->ID, '_wcefp_mp_phone', true),
                'email' => get_post_meta($post->ID, '_wcefp_mp_email', true),
                'contact_person' => get_post_meta($post->ID, '_wcefp_mp_contact_person', true)
            ],
            'accessibility' => [
                'wheelchair_accessible' => !empty(get_post_meta($post->ID, '_wcefp_mp_wheelchair_accessible', true)),
                'parking_available' => !empty(get_post_meta($post->ID, '_wcefp_mp_parking_available', true)),
                'public_transport' => get_post_meta($post->ID, '_wcefp_mp_public_transport', true)
            ],
            'instructions' => get_post_meta($post->ID, '_wcefp_mp_instructions', true),
            'image_url' => get_the_post_thumbnail_url($post->ID, 'medium'),
            'usage_count' => $this->get_meeting_point_usage_count($post->ID),
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified
        ];
    }
    
    /**
     * Get legacy meeting points from product meta
     * 
     * @param array $filters Filters
     * @return array Legacy meeting points
     */
    private function get_legacy_meeting_points($filters = []) {
        // This would aggregate meeting points from product meta
        // For now, return empty array as legacy support
        return [];
    }
    
    /**
     * Create a new meeting point
     * 
     * @param array $meeting_point_data Meeting point data
     * @return int|false Meeting point ID or false on failure
     */
    public function create_meeting_point($meeting_point_data) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        $sanitized_data = $this->sanitize_meeting_point_data($meeting_point_data);
        
        $post_data = [
            'post_type' => 'wcefp_meeting_point',
            'post_title' => $sanitized_data['title'],
            'post_content' => $sanitized_data['description'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ];
        
        $meeting_point_id = wp_insert_post($post_data);
        
        if (is_wp_error($meeting_point_id)) {
            Logger::error('Failed to create meeting point', $meeting_point_id->get_error_message());
            return false;
        }
        
        // Save meta data
        $meta_fields = [
            '_wcefp_mp_type' => $sanitized_data['type'],
            '_wcefp_mp_address' => $sanitized_data['address'],
            '_wcefp_mp_city' => $sanitized_data['city'],
            '_wcefp_mp_postal_code' => $sanitized_data['postal_code'],
            '_wcefp_mp_country' => $sanitized_data['country'],
            '_wcefp_mp_latitude' => $sanitized_data['coordinates']['lat'],
            '_wcefp_mp_longitude' => $sanitized_data['coordinates']['lng'],
            '_wcefp_mp_phone' => $sanitized_data['contact_info']['phone'],
            '_wcefp_mp_email' => $sanitized_data['contact_info']['email'],
            '_wcefp_mp_contact_person' => $sanitized_data['contact_info']['contact_person'],
            '_wcefp_mp_wheelchair_accessible' => $sanitized_data['accessibility']['wheelchair_accessible'],
            '_wcefp_mp_parking_available' => $sanitized_data['accessibility']['parking_available'],
            '_wcefp_mp_public_transport' => $sanitized_data['accessibility']['public_transport'],
            '_wcefp_mp_instructions' => $sanitized_data['instructions']
        ];
        
        foreach ($meta_fields as $key => $value) {
            if ($value !== null && $value !== '') {
                update_post_meta($meeting_point_id, $key, $value);
            }
        }
        
        Logger::info("Meeting point created: ID {$meeting_point_id}, Title: {$sanitized_data['title']}");
        
        do_action('wcefp_meeting_point_created', $meeting_point_id, $sanitized_data);
        
        return $meeting_point_id;
    }
    
    /**
     * Update a meeting point
     * 
     * @param int $meeting_point_id Meeting point ID
     * @param array $meeting_point_data Updated data
     * @return bool Success
     */
    public function update_meeting_point($meeting_point_id, $meeting_point_data) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        $post = get_post($meeting_point_id);
        if (!$post || $post->post_type !== 'wcefp_meeting_point') {
            return false;
        }
        
        $sanitized_data = $this->sanitize_meeting_point_data($meeting_point_data);
        
        $post_data = [
            'ID' => $meeting_point_id,
            'post_title' => $sanitized_data['title'],
            'post_content' => $sanitized_data['description']
        ];
        
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            Logger::error('Failed to update meeting point', $result->get_error_message());
            return false;
        }
        
        // Update meta data
        $meta_fields = [
            '_wcefp_mp_type' => $sanitized_data['type'],
            '_wcefp_mp_address' => $sanitized_data['address'],
            '_wcefp_mp_city' => $sanitized_data['city'],
            '_wcefp_mp_postal_code' => $sanitized_data['postal_code'],
            '_wcefp_mp_country' => $sanitized_data['country'],
            '_wcefp_mp_latitude' => $sanitized_data['coordinates']['lat'],
            '_wcefp_mp_longitude' => $sanitized_data['coordinates']['lng'],
            '_wcefp_mp_phone' => $sanitized_data['contact_info']['phone'],
            '_wcefp_mp_email' => $sanitized_data['contact_info']['email'],
            '_wcefp_mp_contact_person' => $sanitized_data['contact_info']['contact_person'],
            '_wcefp_mp_wheelchair_accessible' => $sanitized_data['accessibility']['wheelchair_accessible'],
            '_wcefp_mp_parking_available' => $sanitized_data['accessibility']['parking_available'],
            '_wcefp_mp_public_transport' => $sanitized_data['accessibility']['public_transport'],
            '_wcefp_mp_instructions' => $sanitized_data['instructions']
        ];
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($meeting_point_id, $key, $value);
        }
        
        Logger::info("Meeting point updated: ID {$meeting_point_id}");
        
        do_action('wcefp_meeting_point_updated', $meeting_point_id, $sanitized_data);
        
        return true;
    }
    
    /**
     * Delete a meeting point
     * 
     * @param int $meeting_point_id Meeting point ID
     * @param bool $force_delete Force delete (bypass trash)
     * @return bool Success
     */
    public function delete_meeting_point($meeting_point_id, $force_delete = false) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        // Check if meeting point is in use
        $usage_count = $this->get_meeting_point_usage_count($meeting_point_id);
        if ($usage_count > 0 && !$force_delete) {
            Logger::warning("Attempted to delete meeting point {$meeting_point_id} that is in use by {$usage_count} products");
            return false;
        }
        
        $result = wp_delete_post($meeting_point_id, $force_delete);
        
        if ($result) {
            Logger::info("Meeting point deleted: ID {$meeting_point_id}");
            do_action('wcefp_meeting_point_deleted', $meeting_point_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize meeting point data
     * 
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private function sanitize_meeting_point_data($data) {
        return [
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => wp_kses_post($data['description'] ?? ''),
            'type' => in_array($data['type'] ?? '', [
                self::TYPE_ADDRESS,
                self::TYPE_LANDMARK,
                self::TYPE_COORDINATES,
                self::TYPE_FLEXIBLE
            ]) ? $data['type'] : self::TYPE_ADDRESS,
            'address' => sanitize_text_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'postal_code' => sanitize_text_field($data['postal_code'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? 'IT'),
            'coordinates' => [
                'lat' => (float) ($data['coordinates']['lat'] ?? 0),
                'lng' => (float) ($data['coordinates']['lng'] ?? 0)
            ],
            'contact_info' => [
                'phone' => sanitize_text_field($data['contact_info']['phone'] ?? ''),
                'email' => sanitize_email($data['contact_info']['email'] ?? ''),
                'contact_person' => sanitize_text_field($data['contact_info']['contact_person'] ?? '')
            ],
            'accessibility' => [
                'wheelchair_accessible' => !empty($data['accessibility']['wheelchair_accessible']),
                'parking_available' => !empty($data['accessibility']['parking_available']),
                'public_transport' => sanitize_textarea_field($data['accessibility']['public_transport'] ?? '')
            ],
            'instructions' => wp_kses_post($data['instructions'] ?? '')
        ];
    }
    
    /**
     * Get meeting point usage count
     * 
     * @param int $meeting_point_id Meeting point ID
     * @return int Usage count
     */
    public function get_meeting_point_usage_count($meeting_point_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_wcefp_meeting_point_id'
            AND meta_value = %d
        ", $meeting_point_id));
        
        return (int) $count;
    }
    
    /**
     * Get meeting point by ID
     * 
     * @param int $meeting_point_id Meeting point ID
     * @return array|null Meeting point data
     */
    public function get_meeting_point($meeting_point_id) {
        $post = get_post($meeting_point_id);
        
        if (!$post || $post->post_type !== 'wcefp_meeting_point') {
            return null;
        }
        
        return $this->format_cpt_meeting_point($post);
    }
    
    /**
     * Get meeting points for a product
     * 
     * @param int $product_id Product ID
     * @return array Meeting point options
     */
    public function get_product_meeting_point_options($product_id) {
        $options = ['' => __('Select a meeting point...', 'wceventsfp')];
        
        $meeting_points = $this->get_meeting_points();
        
        foreach ($meeting_points as $mp) {
            $label = $mp['title'];
            if (!empty($mp['city'])) {
                $label .= ' - ' . $mp['city'];
            }
            $options[$mp['id']] = $label;
        }
        
        $options['custom'] = __('→ Custom meeting point', 'wceventsfp');
        
        return $options;
    }
    
    /**
     * Resolve meeting point for a product
     * 
     * @param int $product_id Product ID
     * @return array|null Resolved meeting point data
     */
    public function resolve_product_meeting_point($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }
        
        // Check for custom override first
        $custom_override = $product->get_meta('_wcefp_meeting_point_custom', true);
        if (!empty($custom_override)) {
            return [
                'type' => 'custom',
                'content' => $custom_override,
                'source' => 'product_override'
            ];
        }
        
        // Check for linked meeting point
        $meeting_point_id = $product->get_meta('_wcefp_meeting_point_id', true);
        if ($meeting_point_id) {
            $meeting_point = $this->get_meeting_point($meeting_point_id);
            if ($meeting_point) {
                return array_merge($meeting_point, ['source' => 'linked_meeting_point']);
            }
        }
        
        // Check for legacy meeting point
        $legacy_meeting_point = $product->get_meta('_wcefp_meeting_point', true);
        if (!empty($legacy_meeting_point)) {
            return [
                'type' => 'legacy',
                'content' => $legacy_meeting_point,
                'source' => 'product_meta'
            ];
        }
        
        return null;
    }
    
    /**
     * Generate meeting point instructions for booking
     * 
     * @param int $product_id Product ID
     * @param array $booking_context Booking context
     * @return string Formatted meeting point instructions
     */
    public function generate_meeting_point_instructions($product_id, $booking_context = []) {
        $meeting_point = $this->resolve_product_meeting_point($product_id);
        
        if (!$meeting_point) {
            return __('Meeting point details will be provided before your experience.', 'wceventsfp');
        }
        
        if ($meeting_point['type'] === 'custom' || $meeting_point['type'] === 'legacy') {
            return $meeting_point['content'];
        }
        
        // Format detailed meeting point information
        $instructions = [];
        
        $instructions[] = '<strong>' . $meeting_point['title'] . '</strong>';
        
        if (!empty($meeting_point['address'])) {
            $instructions[] = '📍 ' . $meeting_point['address'];
            if (!empty($meeting_point['city'])) {
                $instructions[] = $meeting_point['city'] . 
                    (!empty($meeting_point['postal_code']) ? ' ' . $meeting_point['postal_code'] : '');
            }
        }
        
        if (!empty($meeting_point['contact_info']['phone'])) {
            $instructions[] = '📞 ' . $meeting_point['contact_info']['phone'];
        }
        
        if (!empty($meeting_point['accessibility']['public_transport'])) {
            $instructions[] = '🚇 ' . $meeting_point['accessibility']['public_transport'];
        }
        
        if (!empty($meeting_point['accessibility']['parking_available'])) {
            $instructions[] = '🅿️ ' . __('Parking available', 'wceventsfp');
        }
        
        if (!empty($meeting_point['accessibility']['wheelchair_accessible'])) {
            $instructions[] = '♿ ' . __('Wheelchair accessible', 'wceventsfp');
        }
        
        if (!empty($meeting_point['instructions'])) {
            $instructions[] = '<br><em>' . $meeting_point['instructions'] . '</em>';
        }
        
        return implode('<br>', $instructions);
    }
    
    /**
     * Search meeting points
     * 
     * @param string $query Search query
     * @param array $filters Additional filters
     * @return array Search results
     */
    public function search_meeting_points($query, $filters = []) {
        $all_meeting_points = $this->get_meeting_points($filters);
        
        if (empty($query)) {
            return $all_meeting_points;
        }
        
        $query = strtolower(trim($query));
        
        return array_filter($all_meeting_points, function($mp) use ($query) {
            $searchable_text = strtolower(implode(' ', [
                $mp['title'] ?? '',
                $mp['description'] ?? '',
                $mp['address'] ?? '',
                $mp['city'] ?? '',
                $mp['contact_info']['contact_person'] ?? ''
            ]));
            
            return strpos($searchable_text, $query) !== false;
        });
    }
    
    /**
     * Get meeting points statistics
     * 
     * @return array Statistics
     */
    public function get_meeting_points_statistics() {
        $meeting_points = $this->get_meeting_points();
        
        $stats = [
            'total_count' => count($meeting_points),
            'by_type' => [],
            'by_city' => [],
            'usage_stats' => [],
            'accessibility_stats' => [
                'wheelchair_accessible' => 0,
                'parking_available' => 0,
                'has_public_transport' => 0
            ]
        ];
        
        foreach ($meeting_points as $mp) {
            // Count by type
            $type = $mp['type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            
            // Count by city
            $city = $mp['city'] ?? 'Unknown';
            $stats['by_city'][$city] = ($stats['by_city'][$city] ?? 0) + 1;
            
            // Accessibility stats
            if (!empty($mp['accessibility']['wheelchair_accessible'])) {
                $stats['accessibility_stats']['wheelchair_accessible']++;
            }
            
            if (!empty($mp['accessibility']['parking_available'])) {
                $stats['accessibility_stats']['parking_available']++;
            }
            
            if (!empty($mp['accessibility']['public_transport'])) {
                $stats['accessibility_stats']['has_public_transport']++;
            }
            
            // Usage stats
            $usage_count = $mp['usage_count'] ?? 0;
            $stats['usage_stats'][] = [
                'id' => $mp['id'],
                'title' => $mp['title'],
                'usage_count' => $usage_count
            ];
        }
        
        // Sort usage stats by usage count
        usort($stats['usage_stats'], function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });
        
        return $stats;
    }
}