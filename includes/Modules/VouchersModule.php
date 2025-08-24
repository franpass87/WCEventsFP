<?php
/**
 * Vouchers Module
 * 
 * @package WCEFP
 * @subpackage Modules
 * @since 2.1.4
 */

namespace WCEFP\Modules;

use WCEFP\Core\ServiceProvider;
use WCEFP\Features\Communication\VoucherManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vouchers management module
 */
class VouchersModule extends ServiceProvider {
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        $this->container->singleton('vouchers.manager', VoucherManager::class);
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle
        add_action('init', [$this, 'initialize_features'], 20);
        add_action('admin_menu', [$this, 'add_admin_pages'], 15);
        
        // Initialize voucher manager
        $voucher_manager = $this->container->get('vouchers.manager');
        
        Logger::info('Vouchers module booted successfully');
    }
    
    /**
     * Initialize voucher features
     * 
     * @return void
     */
    public function initialize_features(): void {
        // Initialize shortcodes and hooks
        add_shortcode('wcefp_voucher_status', [$this, 'voucher_status_shortcode']);
        add_shortcode('wcefp_voucher_redeem', [$this, 'voucher_redeem_shortcode']);
    }
    
    /**
     * Add admin menu pages
     * 
     * @return void
     */
    public function add_admin_pages(): void {
        add_submenu_page(
            'wcefp-events',
            __('Vouchers Management', 'wceventsfp'),
            __('Voucher', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-vouchers',
            [$this, 'render_vouchers_page']
        );
    }
    
    /**
     * Render vouchers management page
     * 
     * @return void
     */
    public function render_vouchers_page(): void {
        $manager = $this->container->get('vouchers.manager');
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vouchers Management', 'wceventsfp') . '</h1>';
        
        // Render vouchers interface
        $manager->render_admin_page();
        
        echo '</div>';
    }
    
    /**
     * Voucher status shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function voucher_status_shortcode($atts): string {
        $manager = $this->container->get('vouchers.manager');
        return $manager->voucher_status_shortcode($atts);
    }
    
    /**
     * Voucher redeem shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function voucher_redeem_shortcode($atts): string {
        $manager = $this->container->get('vouchers.manager');
        return $manager->enhanced_redeem_shortcode($atts);
    }
}