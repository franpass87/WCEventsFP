<?php
/**
 * Feature Utility Class
 * 
 * Shared utilities for feature management across the plugin
 * 
 * @package WCEFP
 * @subpackage Utils
 * @since 2.1.0
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature management utilities
 */
class Features {
    
    /**
     * Available features definition
     * @var array
     */
    private static $available_features = null;
    
    /**
     * Get all available features
     * 
     * @return array
     */
    public static function get_available_features() {
        if (self::$available_features !== null) {
            return self::$available_features;
        }
        
        self::$available_features = [
            'core' => [
                'name' => __('Core Booking System', 'wceventsfp'),
                'description' => __('Essential booking functionality for events and experiences.', 'wceventsfp'),
                'category' => 'core',
                'weight' => 'normal',
                'required' => true,
                'dependencies' => [],
                'resources' => ['low']
            ],
            'bookings' => [
                'name' => __('Advanced Bookings', 'wceventsfp'),
                'description' => __('Complex booking scenarios, recurring events, and group bookings.', 'wceventsfp'),
                'category' => 'bookings',
                'weight' => 'normal',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['medium']
            ],
            'resources' => [
                'name' => __('Resource Management', 'wceventsfp'),
                'description' => __('Manage guides, equipment, vehicles, and other resources.', 'wceventsfp'),
                'category' => 'management',
                'weight' => 'normal',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['medium']
            ],
            'distribution' => [
                'name' => __('Multi-Channel Distribution', 'wceventsfp'),
                'description' => __('Integration with Booking.com, Expedia, GetYourGuide, and other channels.', 'wceventsfp'),
                'category' => 'integration',
                'weight' => 'heavy',
                'required' => false,
                'dependencies' => ['core', 'bookings'],
                'resources' => ['high', 'api_calls']
            ],
            'commissions' => [
                'name' => __('Commission System', 'wceventsfp'),
                'description' => __('Reseller management and commission calculations.', 'wceventsfp'),
                'category' => 'financial',
                'weight' => 'normal',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['medium']
            ],
            'reviews' => [
                'name' => __('Google Reviews Integration', 'wceventsfp'),
                'description' => __('Automated review collection and management with Google My Business.', 'wceventsfp'),
                'category' => 'marketing',
                'weight' => 'light',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['low', 'api_calls']
            ],
            'analytics' => [
                'name' => __('Advanced Analytics', 'wceventsfp'),
                'description' => __('GA4 integration, Meta Pixel tracking, and real-time reporting dashboard.', 'wceventsfp'),
                'category' => 'analytics',
                'weight' => 'heavy',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['high', 'javascript']
            ],
            'automations' => [
                'name' => __('Marketing Automations', 'wceventsfp'),
                'description' => __('Brevo integration, email campaigns, and automated customer journeys.', 'wceventsfp'),
                'category' => 'marketing',
                'weight' => 'normal',
                'required' => false,
                'dependencies' => ['core'],
                'resources' => ['medium', 'api_calls']
            ],
            'ai_recommendations' => [
                'name' => __('AI Recommendations', 'wceventsfp'),
                'description' => __('AI-powered product recommendations and customer insights.', 'wceventsfp'),
                'category' => 'ai',
                'weight' => 'heavy',
                'required' => false,
                'dependencies' => ['core', 'analytics'],
                'resources' => ['high', 'ai_processing']
            ]
        ];
        
        return self::$available_features;
    }
    
    /**
     * Get features by category
     * 
     * @param string $category
     * @return array
     */
    public static function get_features_by_category($category) {
        $features = self::get_available_features();
        return array_filter($features, function($feature) use ($category) {
            return $feature['category'] === $category;
        });
    }
    
    /**
     * Get feature categories
     * 
     * @return array
     */
    public static function get_categories() {
        return [
            'core' => __('Core Features', 'wceventsfp'),
            'bookings' => __('Booking Management', 'wceventsfp'),
            'management' => __('Resource Management', 'wceventsfp'),
            'integration' => __('Third-party Integration', 'wceventsfp'),
            'financial' => __('Financial Management', 'wceventsfp'),
            'marketing' => __('Marketing Tools', 'wceventsfp'),
            'analytics' => __('Analytics & Reporting', 'wceventsfp'),
            'ai' => __('AI & Machine Learning', 'wceventsfp')
        ];
    }
    
    /**
     * Check if feature is enabled
     * 
     * @param string $feature_key
     * @return bool
     */
    public static function is_enabled($feature_key) {
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        return in_array($feature_key, $enabled_features);
    }
    
    /**
     * Enable a feature
     * 
     * @param string $feature_key
     * @return bool Success
     */
    public static function enable($feature_key) {
        $features = self::get_available_features();
        
        if (!isset($features[$feature_key])) {
            return false;
        }
        
        // Check dependencies
        if (!self::check_dependencies($feature_key)) {
            return false;
        }
        
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        
        if (!in_array($feature_key, $enabled_features)) {
            $enabled_features[] = $feature_key;
            update_option('wcefp_enabled_features', $enabled_features);
        }
        
        return true;
    }
    
    /**
     * Disable a feature
     * 
     * @param string $feature_key
     * @return bool Success
     */
    public static function disable($feature_key) {
        $features = self::get_available_features();
        
        if (!isset($features[$feature_key])) {
            return false;
        }
        
        // Can't disable required features
        if ($features[$feature_key]['required']) {
            return false;
        }
        
        // Check if other features depend on this one
        if (self::has_dependents($feature_key)) {
            return false;
        }
        
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        $enabled_features = array_diff($enabled_features, [$feature_key]);
        
        update_option('wcefp_enabled_features', array_values($enabled_features));
        
        return true;
    }
    
    /**
     * Check feature dependencies
     * 
     * @param string $feature_key
     * @return bool
     */
    public static function check_dependencies($feature_key) {
        $features = self::get_available_features();
        
        if (!isset($features[$feature_key])) {
            return false;
        }
        
        $dependencies = $features[$feature_key]['dependencies'];
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        
        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $enabled_features)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if feature has dependents
     * 
     * @param string $feature_key
     * @return bool
     */
    public static function has_dependents($feature_key) {
        $features = self::get_available_features();
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        
        foreach ($features as $key => $feature) {
            if (in_array($key, $enabled_features) && in_array($feature_key, $feature['dependencies'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get feature resource requirements
     * 
     * @param string $feature_key
     * @return array
     */
    public static function get_resource_requirements($feature_key) {
        $features = self::get_available_features();
        
        if (!isset($features[$feature_key])) {
            return [];
        }
        
        return $features[$feature_key]['resources'];
    }
    
    /**
     * Calculate total resource usage for enabled features
     * 
     * @return array
     */
    public static function calculate_resource_usage() {
        $enabled_features = get_option('wcefp_enabled_features', ['core']);
        $features = self::get_available_features();
        
        $resource_count = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'api_calls' => 0,
            'javascript' => 0,
            'ai_processing' => 0
        ];
        
        foreach ($enabled_features as $feature_key) {
            if (isset($features[$feature_key])) {
                $resources = $features[$feature_key]['resources'];
                foreach ($resources as $resource) {
                    if (isset($resource_count[$resource])) {
                        $resource_count[$resource]++;
                    }
                }
            }
        }
        
        return $resource_count;
    }
    
    /**
     * Get recommended features based on server performance
     * 
     * @param int $performance_score
     * @return array
     */
    public static function get_recommended_features($performance_score = null) {
        if ($performance_score === null) {
            $performance_score = Environment::get_performance_score();
        }
        
        $features = self::get_available_features();
        $recommended = [];
        
        foreach ($features as $key => $feature) {
            $should_recommend = false;
            
            // Always recommend required features
            if ($feature['required']) {
                $should_recommend = true;
            } else {
                switch ($feature['weight']) {
                    case 'light':
                        $should_recommend = $performance_score >= 30;
                        break;
                    case 'normal':
                        $should_recommend = $performance_score >= 50;
                        break;
                    case 'heavy':
                        $should_recommend = $performance_score >= 70;
                        break;
                }
            }
            
            if ($should_recommend) {
                $recommended[] = $key;
            }
        }
        
        return $recommended;
    }
    
    /**
     * Get feature status information
     * 
     * @param string $feature_key
     * @return array
     */
    public static function get_feature_status($feature_key) {
        $features = self::get_available_features();
        
        if (!isset($features[$feature_key])) {
            return [
                'exists' => false,
                'enabled' => false,
                'can_enable' => false,
                'can_disable' => false,
                'message' => __('Feature not found', 'wceventsfp')
            ];
        }
        
        $feature = $features[$feature_key];
        $is_enabled = self::is_enabled($feature_key);
        $can_enable = !$is_enabled && self::check_dependencies($feature_key);
        $can_disable = $is_enabled && !$feature['required'] && !self::has_dependents($feature_key);
        
        $message = '';
        if (!$can_enable && !$is_enabled) {
            $missing_deps = array_diff($feature['dependencies'], get_option('wcefp_enabled_features', ['core']));
            if (!empty($missing_deps)) {
                $message = sprintf(__('Requires: %s', 'wceventsfp'), implode(', ', $missing_deps));
            }
        } elseif (!$can_disable && $is_enabled) {
            if ($feature['required']) {
                $message = __('Required feature', 'wceventsfp');
            } elseif (self::has_dependents($feature_key)) {
                $message = __('Required by other features', 'wceventsfp');
            }
        }
        
        return [
            'exists' => true,
            'enabled' => $is_enabled,
            'can_enable' => $can_enable,
            'can_disable' => $can_disable,
            'message' => $message,
            'weight' => $feature['weight'],
            'category' => $feature['category']
        ];
    }
    
    /**
     * Clear feature cache
     */
    public static function clear_cache() {
        self::$available_features = null;
    }
}