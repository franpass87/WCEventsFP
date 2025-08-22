<?php
/**
 * Progressive Loading Manager for WCEventsFP
 * 
 * @package WCEFP
 * @subpackage Core
 * @since 2.1.0
 */

namespace WCEFP\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages progressive loading of plugin features based on server capabilities
 */
class ProgressiveLoader {
    
    /**
     * Feature loading queue
     * 
     * @var array
     */
    private $feature_queue = [];
    
    /**
     * Already loaded features
     * 
     * @var array
     */
    private $loaded_features = [];
    
    /**
     * Maximum features to load per request
     * 
     * @var int
     */
    private $max_features_per_request = 3;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->max_features_per_request = $this->get_server_capacity();
    }
    
    /**
     * Add feature to loading queue
     * 
     * @param string $feature_name Feature identifier
     * @param callable $loader Feature loader function
     * @param int $priority Loading priority (lower = earlier)
     * @return void
     */
    public function add_feature($feature_name, $loader, $priority = 10) {
        if (in_array($feature_name, $this->loaded_features)) {
            return; // Already loaded
        }
        
        $this->feature_queue[] = [
            'name' => $feature_name,
            'loader' => $loader,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->feature_queue, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }
    
    /**
     * Load features progressively
     * 
     * @return array Loaded features in this request
     */
    public function load_features() {
        $loaded_this_request = [];
        $loaded_count = 0;
        
        foreach ($this->feature_queue as $index => $feature) {
            if ($loaded_count >= $this->max_features_per_request) {
                break;
            }
            
            try {
                // Execute the feature loader
                if (is_callable($feature['loader'])) {
                    call_user_func($feature['loader']);
                    $loaded_this_request[] = $feature['name'];
                    $this->loaded_features[] = $feature['name'];
                    $loaded_count++;
                    
                    // Remove from queue
                    unset($this->feature_queue[$index]);
                }
            } catch (\Exception $e) {
                error_log("WCEventsFP: Failed to load feature {$feature['name']}: " . $e->getMessage());
                // Remove failed feature from queue to prevent retry loops
                unset($this->feature_queue[$index]);
            }
        }
        
        // Re-index array after unsetting elements
        $this->feature_queue = array_values($this->feature_queue);
        
        // Schedule next batch if there are more features
        if (!empty($this->feature_queue)) {
            $this->schedule_next_batch();
        }
        
        return $loaded_this_request;
    }
    
    /**
     * Get server capacity for feature loading
     * 
     * @return int Maximum features to load per request
     */
    private function get_server_capacity() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        
        if ($memory_limit === -1) {
            return 5; // No memory limit - can load more
        } elseif ($memory_limit >= 256 * 1024 * 1024) {
            return 4; // 256MB+ - good capacity
        } elseif ($memory_limit >= 128 * 1024 * 1024) {
            return 3; // 128MB - moderate capacity
        } else {
            return 2; // Limited capacity - load fewer features
        }
    }
    
    /**
     * Schedule next batch of features to load
     * 
     * @return void
     */
    private function schedule_next_batch() {
        // Use WordPress cron to schedule next batch
        if (!wp_next_scheduled('wcefp_load_progressive_features')) {
            wp_schedule_single_event(time() + 30, 'wcefp_load_progressive_features');
        }
    }
    
    /**
     * Get remaining features count
     * 
     * @return int
     */
    public function get_remaining_count() {
        return count($this->feature_queue);
    }
    
    /**
     * Get loaded features
     * 
     * @return array
     */
    public function get_loaded_features() {
        return $this->loaded_features;
    }
    
    /**
     * Check if all features are loaded
     * 
     * @return bool
     */
    public function is_fully_loaded() {
        return empty($this->feature_queue);
    }
}