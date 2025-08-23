<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Developer Debug Tools
 * Advanced debugging and development utilities for WCEFP system
 */
class WCEFP_Debug_Tools {
    
    private static $instance = null;
    private $debug_enabled = false;
    private $debug_log = [];
    private $performance_markers = [];
    private $query_log = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->debug_enabled = defined('WCEFP_DEBUG') && WCEFP_DEBUG;
        
        if ($this->debug_enabled || current_user_can('manage_options')) {
            $this->init_debug_features();
        }
    }
    
    /**
     * Initialize debug features
     */
    private function init_debug_features() {
        // Admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 999);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_debug_info', [$this, 'ajax_debug_info']);
        add_action('wp_ajax_wcefp_debug_clear', [$this, 'ajax_debug_clear']);
        add_action('wp_ajax_wcefp_debug_performance', [$this, 'ajax_debug_performance']);
        add_action('wp_ajax_wcefp_debug_queries', [$this, 'ajax_debug_queries']);
        
        // Hook into WordPress query system
        add_action('pre_get_posts', [$this, 'log_wp_query']);
        add_filter('posts_request', [$this, 'log_sql_query'], 10, 2);
        
        // Performance monitoring
        add_action('init', [$this, 'start_performance_tracking']);
        add_action('wp_footer', [$this, 'end_performance_tracking']);
        
        // Debug panel in footer (for admin users)
        if (current_user_can('manage_options') && !is_admin()) {
            add_action('wp_footer', [$this, 'render_debug_panel']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_debug_scripts']);
        }
    }
    
    /**
     * Add debug menu to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $debug_count = count($this->debug_log);
        $menu_title = "WCEFP Debug ({$debug_count})";
        
        $wp_admin_bar->add_node([
            'id' => 'wcefp-debug',
            'title' => $menu_title,
            'href' => '#',
            'meta' => [
                'onclick' => 'wcefpDebugTools.togglePanel(); return false;'
            ]
        ]);
        
        // Sub-menu items
        $wp_admin_bar->add_node([
            'parent' => 'wcefp-debug',
            'id' => 'wcefp-debug-info',
            'title' => 'System Info',
            'href' => '#',
            'meta' => ['onclick' => 'wcefpDebugTools.showSystemInfo(); return false;']
        ]);
        
        $wp_admin_bar->add_node([
            'parent' => 'wcefp-debug',
            'id' => 'wcefp-debug-performance',
            'title' => 'Performance',
            'href' => '#',
            'meta' => ['onclick' => 'wcefpDebugTools.showPerformance(); return false;']
        ]);
        
        $wp_admin_bar->add_node([
            'parent' => 'wcefp-debug',
            'id' => 'wcefp-debug-queries',
            'title' => 'Database Queries',
            'href' => '#',
            'meta' => ['onclick' => 'wcefpDebugTools.showQueries(); return false;']
        ]);
        
        $wp_admin_bar->add_node([
            'parent' => 'wcefp-debug',
            'id' => 'wcefp-debug-clear',
            'title' => 'Clear Debug Log',
            'href' => '#',
            'meta' => ['onclick' => 'wcefpDebugTools.clearLog(); return false;']
        ]);
    }
    
    /**
     * Enqueue debug scripts
     */
    public function enqueue_debug_scripts() {
        wp_enqueue_script(
            'wcefp-debug-tools',
            WCEFP_PLUGIN_URL . 'assets/js/debug-tools.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-debug-tools', 'wcefp_debug', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_debug_nonce'),
            'is_debug_enabled' => $this->debug_enabled
        ]);
        
        // Enqueue debug CSS
        wp_enqueue_style(
            'wcefp-debug-tools',
            WCEFP_PLUGIN_URL . 'assets/css/debug-tools.css',
            ['wp-admin-bar'],
            WCEFP_VERSION
        );
    }
    
    /**
     * Debug logging function
     */
    public function log($message, $level = 'info', $context = []) {
        if (!$this->debug_enabled && !current_user_can('manage_options')) {
            return;
        }
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? [];
        
        $log_entry = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'file' => $caller['file'] ?? '',
            'line' => $caller['line'] ?? '',
            'function' => $caller['function'] ?? '',
            'memory' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
        
        $this->debug_log[] = $log_entry;
        
        // Also log to WordPress debug.log if available
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[WCEFP DEBUG] [%s] %s in %s:%d',
                strtoupper($level),
                $message,
                basename($log_entry['file']),
                $log_entry['line']
            ));
        }
    }
    
    /**
     * Performance marker
     */
    public function mark($marker_name, $data = []) {
        if (!$this->debug_enabled && !current_user_can('manage_options')) {
            return;
        }
        
        $this->performance_markers[$marker_name] = [
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
            'data' => $data
        ];
    }
    
    /**
     * Start performance tracking
     */
    public function start_performance_tracking() {
        $this->mark('request_start', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'query_count' => get_num_queries()
        ]);
    }
    
    /**
     * End performance tracking
     */
    public function end_performance_tracking() {
        $this->mark('request_end', [
            'query_count' => get_num_queries(),
            'plugins_loaded' => did_action('plugins_loaded'),
            'wp_loaded' => did_action('wp_loaded')
        ]);
        
        $this->calculate_performance_metrics();
    }
    
    /**
     * Calculate performance metrics
     */
    private function calculate_performance_metrics() {
        if (!isset($this->performance_markers['request_start']) || !isset($this->performance_markers['request_end'])) {
            return;
        }
        
        $start = $this->performance_markers['request_start'];
        $end = $this->performance_markers['request_end'];
        
        $metrics = [
            'total_time' => ($end['timestamp'] - $start['timestamp']) * 1000, // ms
            'memory_used' => $end['memory'] - $start['memory'],
            'queries_executed' => $end['data']['query_count'] - $start['data']['query_count'],
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        $this->performance_markers['calculated_metrics'] = $metrics;
        
        $this->log('Performance metrics calculated', 'performance', $metrics);
    }
    
    /**
     * Log WordPress queries
     */
    public function log_wp_query($query) {
        if (!$this->debug_enabled) {
            return;
        }
        
        $this->log('WP_Query executed', 'query', [
            'query_vars' => $query->query_vars,
            'post_type' => $query->get('post_type'),
            'meta_query' => $query->get('meta_query')
        ]);
    }
    
    /**
     * Log SQL queries
     */
    public function log_sql_query($query, $wp_query) {
        if (!$this->debug_enabled) {
            return $query;
        }
        
        $this->query_log[] = [
            'timestamp' => microtime(true),
            'query' => $query,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        return $query;
    }
    
    /**
     * Get system information
     */
    public function get_system_info() {
        return [
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug_mode' => WP_DEBUG,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
                'extensions' => get_loaded_extensions()
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'php_version' => phpversion(),
                'mysql_version' => $this->get_mysql_version()
            ],
            'wcefp' => [
                'version' => WCEFP_VERSION,
                'plugin_dir' => WCEFP_PLUGIN_DIR,
                'debug_enabled' => $this->debug_enabled,
                'log_entries' => count($this->debug_log),
                'performance_markers' => count($this->performance_markers)
            ],
            'environment' => [
                'current_user' => wp_get_current_user()->user_login ?? 'Not logged in',
                'current_theme' => get_template(),
                'active_plugins' => $this->get_active_plugins_info()
            ]
        ];
    }
    
    /**
     * Get MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    /**
     * Get active plugins information
     */
    private function get_active_plugins_info() {
        $active_plugins = get_option('active_plugins');
        $plugin_info = [];
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_info[] = [
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author']
            ];
        }
        
        return $plugin_info;
    }
    
    /**
     * Render debug panel
     */
    public function render_debug_panel() {
        ?>
        <div id="wcefp-debug-panel" style="display: none;">
            <div class="wcefp-debug-header">
                <h3>WCEFP Debug Panel</h3>
                <button onclick="wcefpDebugTools.closePanel()">&times;</button>
            </div>
            <div class="wcefp-debug-content">
                <div class="wcefp-debug-tabs">
                    <button class="wcefp-debug-tab active" data-tab="log">Debug Log</button>
                    <button class="wcefp-debug-tab" data-tab="performance">Performance</button>
                    <button class="wcefp-debug-tab" data-tab="system">System Info</button>
                    <button class="wcefp-debug-tab" data-tab="queries">Queries</button>
                </div>
                
                <div id="wcefp-debug-log" class="wcefp-debug-tab-content active">
                    <div class="wcefp-debug-log-entries">
                        <?php $this->render_debug_log(); ?>
                    </div>
                </div>
                
                <div id="wcefp-debug-performance" class="wcefp-debug-tab-content">
                    <div class="wcefp-performance-metrics">
                        Loading performance data...
                    </div>
                </div>
                
                <div id="wcefp-debug-system" class="wcefp-debug-tab-content">
                    <div class="wcefp-system-info">
                        Loading system information...
                    </div>
                </div>
                
                <div id="wcefp-debug-queries" class="wcefp-debug-tab-content">
                    <div class="wcefp-query-log">
                        Loading query information...
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render debug log entries
     */
    private function render_debug_log() {
        foreach ($this->debug_log as $entry) {
            $time = date('H:i:s', $entry['timestamp']);
            $level_class = 'wcefp-debug-' . $entry['level'];
            
            echo "<div class='wcefp-debug-entry {$level_class}'>";
            echo "<span class='wcefp-debug-time'>{$time}</span>";
            echo "<span class='wcefp-debug-level'>[{$entry['level']}]</span>";
            echo "<span class='wcefp-debug-message'>{$entry['message']}</span>";
            
            if (!empty($entry['context'])) {
                echo "<div class='wcefp-debug-context'>";
                echo "<pre>" . esc_html(json_encode($entry['context'], JSON_PRETTY_PRINT)) . "</pre>";
                echo "</div>";
            }
            
            echo "<span class='wcefp-debug-location'>";
            echo basename($entry['file']) . ':' . $entry['line'];
            echo "</span>";
            echo "</div>";
        }
    }
    
    /**
     * AJAX handler for debug info
     */
    public function ajax_debug_info() {
        check_ajax_referer('wcefp_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        wp_send_json_success([
            'system_info' => $this->get_system_info(),
            'debug_log' => $this->debug_log,
            'performance_markers' => $this->performance_markers
        ]);
    }
    
    /**
     * AJAX handler for performance data
     */
    public function ajax_debug_performance() {
        check_ajax_referer('wcefp_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        wp_send_json_success([
            'performance_markers' => $this->performance_markers,
            'query_count' => get_num_queries(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    }
    
    /**
     * AJAX handler for query data
     */
    public function ajax_debug_queries() {
        check_ajax_referer('wcefp_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        wp_send_json_success([
            'query_log' => $this->query_log,
            'total_queries' => get_num_queries()
        ]);
    }
    
    /**
     * AJAX handler to clear debug log
     */
    public function ajax_debug_clear() {
        check_ajax_referer('wcefp_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $this->debug_log = [];
        $this->performance_markers = [];
        $this->query_log = [];
        
        wp_send_json_success(['message' => 'Debug log cleared']);
    }
    
    /**
     * Get debug CSS
     */
    
    /**
     * Get debug log
     */
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    /**
     * Get performance markers
     */
    public function get_performance_markers() {
        return $this->performance_markers;
    }
    
    /**
     * Initialize debug tools
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize debug tools
WCEFP_Debug_Tools::init();