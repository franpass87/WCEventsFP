<?php
/**
 * Modules Service Provider
 * 
 * @package WCEFP
 * @subpackage Modules
 * @since 2.1.4
 */

namespace WCEFP\Modules;

use WCEFP\Core\ServiceProvider;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central service provider for all plugin modules
 */
class ModulesServiceProvider extends ServiceProvider {
    
    /**
     * Module classes to load
     * 
     * @var array
     */
    private $modules = [
        'i18n' => I18nModule::class,
        'bookings' => BookingsModule::class,
        'vouchers' => VouchersModule::class,
        'closures' => ClosuresModule::class,
        'meeting_points' => MeetingPointsModule::class,
        'settings' => SettingsModule::class,
        'extras' => ExtrasModule::class,
    ];
    
    /**
     * Register modules
     * 
     * @return void
     */
    public function register(): void {
        foreach ($this->modules as $module_key => $module_class) {
            if (class_exists($module_class)) {
                if ($module_key === 'i18n') {
                    // Fix DI for I18nModule - pass actual dependencies instead of container
                    $this->container->singleton("modules.{$module_key}", function($container) use ($module_class) {
                        return new $module_class(
                            $container->get('security'),     // SecurityManager instance
                            $container->get('performance')   // PerformanceManager instance
                        );
                    });
                } else {
                    // Other modules still use container-based initialization
                    $this->container->singleton("modules.{$module_key}", function($container) use ($module_class) {
                        return new $module_class($container);
                    });
                }
            }
        }
        
        Logger::info('All modules registered in container');
    }
    
    /**
     * Boot modules
     * 
     * @return void
     */
    public function boot(): void {
        // Boot modules on plugins_loaded with priority 5 for early service wiring
        add_action('plugins_loaded', [$this, 'boot_modules'], 5);
        
        // Move textdomain loading to init as requested
        add_action('init', [$this, 'load_textdomain'], 1);
        
        Logger::info('Modules service provider booted');
    }
    
    /**
     * Boot all modules
     * 
     * @return void
     */
    public function boot_modules(): void {
        foreach ($this->modules as $module_key => $module_class) {
            try {
                $module = $this->container->get("modules.{$module_key}");
                
                if ($module instanceof ServiceProvider) {
                    $module->register();
                    $module->boot();
                    
                    Logger::info("Module {$module_key} booted successfully");
                }
            } catch (\Throwable $e) {
                Logger::error("Failed to boot module {$module_key}: " . $e->getMessage());
                
                // Add admin notice for debugging but continue with other modules
                if (is_admin() && current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
                    add_action('admin_notices', function() use ($module_key, $e) {
                        echo '<div class="notice notice-warning"><p>';
                        echo '<strong>WCEventsFP Debug:</strong> Module ' . esc_html($module_key) . ' failed to boot: ';
                        echo esc_html($e->getMessage());
                        echo '</p></div>';
                    });
                }
            }
        }
        
        do_action('wcefp_modules_loaded', $this);
    }
    
    /**
     * Load plugin textdomain - moved to init hook as requested
     * 
     * @return void
     */
    public function load_textdomain(): void {
        $loaded = load_plugin_textdomain(
            'wceventsfp',
            false,
            dirname(plugin_basename(WCEFP_PLUGIN_FILE)) . '/languages'
        );
        
        if ($loaded) {
            Logger::info('Plugin textdomain loaded successfully');
        } else {
            Logger::warning('Failed to load textdomain - translations may not work properly');
        }
    }
    
    /**
     * Get specific module instance
     * 
     * @param string $module_key
     * @return mixed|null
     */
    public function get_module(string $module_key) {
        return $this->container->get("modules.{$module_key}");
    }
    
    /**
     * Check if module is enabled
     * 
     * @param string $module_key
     * @return bool
     */
    public function is_module_enabled(string $module_key): bool {
        // Check feature flags or settings to determine if module should be enabled
        $settings = get_option('wcefp_features_settings', []);
        
        switch ($module_key) {
            case 'vouchers':
                return !empty($settings['enable_vouchers']);
            case 'meeting_points':
                return !empty($settings['enable_meeting_points']);
            case 'extras':
                return true; // Always enabled for now
            default:
                return true; // Core modules always enabled
        }
    }
    
    /**
     * Get all loaded modules
     * 
     * @return array
     */
    public function get_loaded_modules(): array {
        $loaded = [];
        
        foreach ($this->modules as $module_key => $module_class) {
            if ($this->container->has("modules.{$module_key}")) {
                $loaded[$module_key] = $this->container->get("modules.{$module_key}");
            }
        }
        
        return $loaded;
    }
}