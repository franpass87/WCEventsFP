<?php
/**
 * Policy Service
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
 * Policy and terms management service
 */
class PolicyService {
    
    /**
     * Policy types
     */
    const POLICY_CANCELLATION = 'cancellation';
    const POLICY_REFUND = 'refund';
    const POLICY_RESCHEDULING = 'rescheduling';
    const POLICY_WEATHER = 'weather';
    const POLICY_HEALTH_SAFETY = 'health_safety';
    const POLICY_TERMS_CONDITIONS = 'terms_conditions';
    const POLICY_PRIVACY = 'privacy';
    const POLICY_ACCESSIBILITY = 'accessibility';
    
    /**
     * Get policies for a product
     * 
     * @param int $product_id Product ID
     * @param string|null $policy_type Specific policy type
     * @return array Policies
     */
    public function get_product_policies($product_id, $policy_type = null) {
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return [];
        }
        
        // Get product-specific policies
        $product_policies = $product->get_meta('_wcefp_policies', true) ?: [];
        
        // Get global fallback policies
        $global_policies = $this->get_global_policies();
        
        // Merge policies with product overriding global
        $merged_policies = $this->merge_policies($global_policies, $product_policies);
        
        // Filter by type if specified
        if ($policy_type) {
            return $merged_policies[$policy_type] ?? null;
        }
        
        return $merged_policies;
    }
    
    /**
     * Get global fallback policies
     * 
     * @return array Global policies
     */
    private function get_global_policies() {
        $global_policies = get_option('wcefp_global_policies', []);
        
        if (empty($global_policies)) {
            $global_policies = $this->get_default_policies();
        }
        
        return $global_policies;
    }
    
    /**
     * Get default policy templates
     * 
     * @return array Default policies
     */
    private function get_default_policies() {
        return [
            self::POLICY_CANCELLATION => [
                'title' => __('Cancellation Policy', 'wceventsfp'),
                'content' => __('Free cancellation up to 24 hours before the experience starts. For cancellations made less than 24 hours in advance, no refund will be provided.', 'wceventsfp'),
                'rules' => [
                    [
                        'timeframe' => '24h',
                        'refund_percentage' => 100,
                        'description' => __('Free cancellation until 24h before', 'wceventsfp')
                    ],
                    [
                        'timeframe' => '0h',
                        'refund_percentage' => 0,
                        'description' => __('No refund for same-day cancellations', 'wceventsfp')
                    ]
                ]
            ],
            self::POLICY_REFUND => [
                'title' => __('Refund Policy', 'wceventsfp'),
                'content' => __('Refunds are processed according to our cancellation policy. Processing time is 5-10 business days.', 'wceventsfp'),
                'processing_time' => '5-10 business days',
                'method' => 'original_payment'
            ],
            self::POLICY_RESCHEDULING => [
                'title' => __('Rescheduling Policy', 'wceventsfp'),
                'content' => __('You can reschedule your booking up to 12 hours before the experience starts, subject to availability.', 'wceventsfp'),
                'allowed_until' => '12h',
                'fee_percentage' => 0
            ],
            self::POLICY_WEATHER => [
                'title' => __('Weather Policy', 'wceventsfp'),
                'content' => __('In case of severe weather conditions, we reserve the right to cancel or reschedule the experience for safety reasons. Full refund will be provided.', 'wceventsfp'),
                'cancellation_conditions' => ['severe_weather', 'safety_concerns'],
                'refund_percentage' => 100
            ],
            self::POLICY_HEALTH_SAFETY => [
                'title' => __('Health & Safety', 'wceventsfp'),
                'content' => __('Participants must follow all safety instructions provided by our staff. We reserve the right to exclude participants who do not comply with safety measures.', 'wceventsfp'),
                'requirements' => [
                    __('Follow safety instructions', 'wceventsfp'),
                    __('Appropriate clothing required', 'wceventsfp'),
                    __('Inform staff of medical conditions', 'wceventsfp')
                ]
            ]
        ];
    }
    
    /**
     * Merge global and product-specific policies
     * 
     * @param array $global_policies Global policies
     * @param array $product_policies Product policies
     * @return array Merged policies
     */
    private function merge_policies($global_policies, $product_policies) {
        $merged = $global_policies;
        
        foreach ($product_policies as $type => $policy) {
            if (!empty($policy['enabled']) && !empty($policy['content'])) {
                $merged[$type] = array_merge($merged[$type] ?? [], $policy);
            }
        }
        
        return $merged;
    }
    
    /**
     * Save policies for a product
     * 
     * @param int $product_id Product ID
     * @param array $policies Policies configuration
     * @return bool Success
     */
    public function save_product_policies($product_id, $policies) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $sanitized_policies = $this->sanitize_policies($policies);
        
        $product->update_meta_data('_wcefp_policies', $sanitized_policies);
        $product->save();
        
        Logger::info("Policies updated for product {$product_id}");
        
        do_action('wcefp_product_policies_updated', $product_id, $sanitized_policies);
        
        return true;
    }
    
    /**
     * Sanitize policies data
     * 
     * @param array $policies Raw policies data
     * @return array Sanitized policies
     */
    private function sanitize_policies($policies) {
        $sanitized = [];
        
        foreach ($policies as $type => $policy) {
            if (!in_array($type, [
                self::POLICY_CANCELLATION,
                self::POLICY_REFUND,
                self::POLICY_RESCHEDULING,
                self::POLICY_WEATHER,
                self::POLICY_HEALTH_SAFETY,
                self::POLICY_TERMS_CONDITIONS,
                self::POLICY_PRIVACY,
                self::POLICY_ACCESSIBILITY
            ])) {
                continue;
            }
            
            $sanitized[$type] = [
                'enabled' => !empty($policy['enabled']),
                'title' => sanitize_text_field($policy['title'] ?? ''),
                'content' => wp_kses_post($policy['content'] ?? ''),
                'custom_rules' => $this->sanitize_policy_rules($policy['custom_rules'] ?? [])
            ];
            
            // Type-specific sanitization
            if ($type === self::POLICY_CANCELLATION) {
                $sanitized[$type]['rules'] = $this->sanitize_cancellation_rules($policy['rules'] ?? []);
            }
            
            if ($type === self::POLICY_REFUND) {
                $sanitized[$type]['processing_time'] = sanitize_text_field($policy['processing_time'] ?? '');
                $sanitized[$type]['method'] = sanitize_text_field($policy['method'] ?? 'original_payment');
            }
            
            if ($type === self::POLICY_RESCHEDULING) {
                $sanitized[$type]['allowed_until'] = sanitize_text_field($policy['allowed_until'] ?? '');
                $sanitized[$type]['fee_percentage'] = max(0, min(100, (float) ($policy['fee_percentage'] ?? 0)));
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize policy rules
     * 
     * @param array $rules Raw rules
     * @return array Sanitized rules
     */
    private function sanitize_policy_rules($rules) {
        if (!is_array($rules)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($rules as $rule) {
            if (is_array($rule)) {
                $sanitized[] = array_map('sanitize_text_field', $rule);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize cancellation rules
     * 
     * @param array $rules Raw cancellation rules
     * @return array Sanitized rules
     */
    private function sanitize_cancellation_rules($rules) {
        if (!is_array($rules)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            
            $sanitized[] = [
                'timeframe' => sanitize_text_field($rule['timeframe'] ?? ''),
                'refund_percentage' => max(0, min(100, (int) ($rule['refund_percentage'] ?? 0))),
                'description' => sanitize_text_field($rule['description'] ?? '')
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Get policy summary for display
     * 
     * @param int $product_id Product ID
     * @param array $context Display context
     * @return array Policy summary
     */
    public function get_policy_summary($product_id, $context = []) {
        $policies = $this->get_product_policies($product_id);
        $summary = [];
        
        // Cancellation policy summary
        if (!empty($policies[self::POLICY_CANCELLATION])) {
            $cancellation = $policies[self::POLICY_CANCELLATION];
            $rules = $cancellation['rules'] ?? [];
            
            if (!empty($rules)) {
                $best_rule = $this->get_best_cancellation_rule($rules);
                $summary['cancellation'] = [
                    'type' => 'cancellation',
                    'icon' => '🔄',
                    'title' => __('Cancellation', 'wceventsfp'),
                    'summary' => $best_rule ? sprintf(
                        __('Free cancellation until %s before', 'wceventsfp'),
                        $this->format_timeframe($best_rule['timeframe'])
                    ) : $cancellation['content']
                ];
            }
        }
        
        // Rescheduling policy summary
        if (!empty($policies[self::POLICY_RESCHEDULING])) {
            $rescheduling = $policies[self::POLICY_RESCHEDULING];
            $summary['rescheduling'] = [
                'type' => 'rescheduling',
                'icon' => '📅',
                'title' => __('Rescheduling', 'wceventsfp'),
                'summary' => !empty($rescheduling['allowed_until']) ?
                    sprintf(__('Reschedule until %s before', 'wceventsfp'), $this->format_timeframe($rescheduling['allowed_until'])) :
                    __('Rescheduling available', 'wceventsfp')
            ];
        }
        
        // Weather policy summary
        if (!empty($policies[self::POLICY_WEATHER])) {
            $summary['weather'] = [
                'type' => 'weather',
                'icon' => '🌦️',
                'title' => __('Weather', 'wceventsfp'),
                'summary' => __('Full refund for weather cancellations', 'wceventsfp')
            ];
        }
        
        return apply_filters('wcefp_policy_summary', $summary, $product_id, $context);
    }
    
    /**
     * Get the best (most lenient) cancellation rule
     * 
     * @param array $rules Cancellation rules
     * @return array|null Best rule
     */
    private function get_best_cancellation_rule($rules) {
        if (empty($rules)) {
            return null;
        }
        
        $best_rule = null;
        $best_hours = -1;
        
        foreach ($rules as $rule) {
            if (empty($rule['refund_percentage']) || $rule['refund_percentage'] <= 0) {
                continue;
            }
            
            $hours = $this->timeframe_to_hours($rule['timeframe'] ?? '');
            if ($hours > $best_hours) {
                $best_hours = $hours;
                $best_rule = $rule;
            }
        }
        
        return $best_rule;
    }
    
    /**
     * Convert timeframe string to hours
     * 
     * @param string $timeframe Timeframe (e.g., "24h", "2d", "1w")
     * @return int Hours
     */
    private function timeframe_to_hours($timeframe) {
        if (empty($timeframe)) {
            return 0;
        }
        
        $timeframe = strtolower(trim($timeframe));
        
        if (preg_match('/(\d+)h/', $timeframe, $matches)) {
            return (int) $matches[1];
        }
        
        if (preg_match('/(\d+)d/', $timeframe, $matches)) {
            return (int) $matches[1] * 24;
        }
        
        if (preg_match('/(\d+)w/', $timeframe, $matches)) {
            return (int) $matches[1] * 7 * 24;
        }
        
        return 0;
    }
    
    /**
     * Format timeframe for display
     * 
     * @param string $timeframe Timeframe
     * @return string Formatted timeframe
     */
    private function format_timeframe($timeframe) {
        if (empty($timeframe)) {
            return '';
        }
        
        $hours = $this->timeframe_to_hours($timeframe);
        
        if ($hours >= 168) { // 1 week
            $weeks = floor($hours / 168);
            return sprintf(_n('%d week', '%d weeks', $weeks, 'wceventsfp'), $weeks);
        }
        
        if ($hours >= 24) {
            $days = floor($hours / 24);
            return sprintf(_n('%d day', '%d days', $days, 'wceventsfp'), $days);
        }
        
        return sprintf(_n('%d hour', '%d hours', $hours, 'wceventsfp'), $hours);
    }
    
    /**
     * Check if cancellation is allowed
     * 
     * @param int $product_id Product ID
     * @param string $booking_datetime Booking datetime
     * @return array Cancellation info
     */
    public function check_cancellation_allowed($product_id, $booking_datetime) {
        $policies = $this->get_product_policies($product_id, self::POLICY_CANCELLATION);
        
        if (empty($policies)) {
            return [
                'allowed' => false,
                'reason' => 'no_policy',
                'refund_percentage' => 0
            ];
        }
        
        $rules = $policies['rules'] ?? [];
        if (empty($rules)) {
            return [
                'allowed' => false,
                'reason' => 'no_rules',
                'refund_percentage' => 0
            ];
        }
        
        $booking_time = strtotime($booking_datetime);
        $current_time = current_time('timestamp');
        $hours_until_booking = ceil(($booking_time - $current_time) / 3600);
        
        // Find applicable rule
        $applicable_rule = null;
        foreach ($rules as $rule) {
            $rule_hours = $this->timeframe_to_hours($rule['timeframe'] ?? '');
            if ($hours_until_booking >= $rule_hours) {
                $applicable_rule = $rule;
                break;
            }
        }
        
        if (!$applicable_rule) {
            return [
                'allowed' => false,
                'reason' => 'too_late',
                'refund_percentage' => 0,
                'hours_until_booking' => $hours_until_booking
            ];
        }
        
        $refund_percentage = (int) $applicable_rule['refund_percentage'];
        
        return [
            'allowed' => $refund_percentage > 0,
            'refund_percentage' => $refund_percentage,
            'rule' => $applicable_rule,
            'hours_until_booking' => $hours_until_booking
        ];
    }
    
    /**
     * Check if rescheduling is allowed
     * 
     * @param int $product_id Product ID
     * @param string $booking_datetime Booking datetime
     * @return array Rescheduling info
     */
    public function check_rescheduling_allowed($product_id, $booking_datetime) {
        $policies = $this->get_product_policies($product_id, self::POLICY_RESCHEDULING);
        
        if (empty($policies)) {
            return [
                'allowed' => false,
                'reason' => 'no_policy'
            ];
        }
        
        $allowed_until = $policies['allowed_until'] ?? '';
        if (empty($allowed_until)) {
            return [
                'allowed' => true,
                'fee_percentage' => (float) ($policies['fee_percentage'] ?? 0)
            ];
        }
        
        $booking_time = strtotime($booking_datetime);
        $current_time = current_time('timestamp');
        $hours_until_booking = ceil(($booking_time - $current_time) / 3600);
        $required_hours = $this->timeframe_to_hours($allowed_until);
        
        return [
            'allowed' => $hours_until_booking >= $required_hours,
            'fee_percentage' => (float) ($policies['fee_percentage'] ?? 0),
            'hours_until_booking' => $hours_until_booking,
            'required_hours' => $required_hours
        ];
    }
    
    /**
     * Generate policy text for booking confirmation
     * 
     * @param int $product_id Product ID
     * @param array $context Booking context
     * @return string Policy text
     */
    public function generate_booking_policy_text($product_id, $context = []) {
        $policies = $this->get_product_policies($product_id);
        $policy_texts = [];
        
        // Important policies to include
        $important_policies = [
            self::POLICY_CANCELLATION,
            self::POLICY_WEATHER,
            self::POLICY_HEALTH_SAFETY
        ];
        
        foreach ($important_policies as $policy_type) {
            if (!empty($policies[$policy_type]['content'])) {
                $policy_texts[] = '<strong>' . $policies[$policy_type]['title'] . ':</strong> ' . 
                                  $policies[$policy_type]['content'];
            }
        }
        
        if (empty($policy_texts)) {
            return __('Standard booking terms and conditions apply.', 'wceventsfp');
        }
        
        return implode('<br><br>', $policy_texts);
    }
    
    /**
     * Get policy compliance check
     * 
     * @param int $product_id Product ID
     * @return array Compliance status
     */
    public function get_policy_compliance_check($product_id) {
        $policies = $this->get_product_policies($product_id);
        $compliance = [
            'compliant' => true,
            'warnings' => [],
            'missing_policies' => []
        ];
        
        // Required policies for compliance
        $required_policies = [
            self::POLICY_CANCELLATION,
            self::POLICY_REFUND
        ];
        
        foreach ($required_policies as $policy_type) {
            if (empty($policies[$policy_type]) || empty($policies[$policy_type]['content'])) {
                $compliance['compliant'] = false;
                $compliance['missing_policies'][] = $policy_type;
            }
        }
        
        // Check for potential issues
        if (!empty($policies[self::POLICY_CANCELLATION]['rules'])) {
            $rules = $policies[self::POLICY_CANCELLATION]['rules'];
            $has_free_cancellation = false;
            
            foreach ($rules as $rule) {
                if (!empty($rule['refund_percentage']) && $rule['refund_percentage'] == 100) {
                    $has_free_cancellation = true;
                    break;
                }
            }
            
            if (!$has_free_cancellation) {
                $compliance['warnings'][] = __('No free cancellation period defined', 'wceventsfp');
            }
        }
        
        return $compliance;
    }
    
    /**
     * Save global policies
     * 
     * @param array $policies Global policies
     * @return bool Success
     */
    public function save_global_policies($policies) {
        if (!SecurityManager::can_user('manage_options')) {
            return false;
        }
        
        $sanitized_policies = $this->sanitize_policies($policies);
        
        update_option('wcefp_global_policies', $sanitized_policies);
        
        Logger::info('Global policies updated');
        
        do_action('wcefp_global_policies_updated', $sanitized_policies);
        
        return true;
    }
    
    /**
     * Import policy templates
     * 
     * @param string $template_type Template type
     * @return array Imported policies
     */
    public function import_policy_templates($template_type = 'standard') {
        $templates = [
            'standard' => $this->get_default_policies(),
            'strict' => $this->get_strict_policy_templates(),
            'flexible' => $this->get_flexible_policy_templates()
        ];
        
        return $templates[$template_type] ?? $templates['standard'];
    }
    
    /**
     * Get strict policy templates
     * 
     * @return array Strict policies
     */
    private function get_strict_policy_templates() {
        return [
            self::POLICY_CANCELLATION => [
                'title' => __('Strict Cancellation Policy', 'wceventsfp'),
                'content' => __('Free cancellation up to 7 days before. 50% refund up to 48 hours before. No refund for cancellations made less than 48 hours in advance.', 'wceventsfp'),
                'rules' => [
                    ['timeframe' => '168h', 'refund_percentage' => 100, 'description' => 'Free cancellation until 7 days before'],
                    ['timeframe' => '48h', 'refund_percentage' => 50, 'description' => '50% refund until 48h before'],
                    ['timeframe' => '0h', 'refund_percentage' => 0, 'description' => 'No refund for last-minute cancellations']
                ]
            ]
        ];
    }
    
    /**
     * Get flexible policy templates
     * 
     * @return array Flexible policies
     */
    private function get_flexible_policy_templates() {
        return [
            self::POLICY_CANCELLATION => [
                'title' => __('Flexible Cancellation Policy', 'wceventsfp'),
                'content' => __('Free cancellation up to 2 hours before the experience starts.', 'wceventsfp'),
                'rules' => [
                    ['timeframe' => '2h', 'refund_percentage' => 100, 'description' => 'Free cancellation until 2h before'],
                    ['timeframe' => '0h', 'refund_percentage' => 50, 'description' => '50% refund for same-day cancellations']
                ]
            ]
        ];
    }
}