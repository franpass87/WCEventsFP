<?php
/**
 * Communication Service Provider
 * 
 * Phase 2: Communication & Automation - Registers email and voucher management
 * services with enhanced communication capabilities
 *
 * @package WCEFP
 * @subpackage Features\Communication
 * @since 2.1.2
 */

namespace WCEFP\Features\Communication;

use WCEFP\Core\ServiceProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers communication-related services
 */
class CommunicationServiceProvider extends ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        // Register Enhanced Email Manager
        $this->container->singleton('communication.email', function($container) {
            return new EmailManager();
        });
        
        // Register Enhanced Voucher Manager with email integration
        $this->container->singleton('communication.vouchers', function($container) {
            $email_manager = $container->get('communication.email');
            return new VoucherManager($email_manager);
        });
        
        // Register Automation Manager (for scheduled tasks)
        $this->container->singleton('communication.automation', function($container) {
            return new AutomationManager(
                $container->get('communication.email'),
                $container->get('communication.vouchers')
            );
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        // Initialize Email Manager
        if ($this->container->has('communication.email')) {
            $email_manager = $this->container->get('communication.email');
            if ($email_manager->is_available()) {
                $this->log_info('Enhanced email system initialized');
            }
        }
        
        // Initialize Enhanced Voucher Manager
        if ($this->container->has('communication.vouchers')) {
            $voucher_manager = $this->container->get('communication.vouchers');
            if ($voucher_manager->is_available()) {
                $this->log_info('Enhanced voucher management system initialized');
            }
        }
        
        // Initialize Automation Manager
        if ($this->container->has('communication.automation')) {
            $automation_manager = $this->container->get('communication.automation');
            $automation_manager->init();
            $this->log_info('Communication automation system initialized');
        }
        
        // Add admin notices for Phase 2 completion
        add_action('admin_notices', [$this, 'show_phase2_completion_notice']);
    }
    
    /**
     * Show admin notice about Phase 2 completion
     */
    public function show_phase2_completion_notice() {
        // Only show to administrators on WCEFP pages
        if (!current_user_can('manage_options') || 
            !isset($_GET['page']) || 
            strpos($_GET['page'], 'wcefp') === false) {
            return;
        }
        
        // Check if notice was already dismissed
        if (get_option('wcefp_phase2_notice_dismissed', false)) {
            return;
        }
        
        ?>
        <div class="notice notice-success is-dismissible" id="wcefp-phase2-notice">
            <h3><?php _e('🎉 WCEFP Phase 2: Communication & Automation Completata!', 'wceventsfp'); ?></h3>
            <p><strong><?php _e('Nuove funzionalità disponibili:', 'wceventsfp'); ?></strong></p>
            <ul style="margin-left: 20px;">
                <li>✅ <?php _e('Sistema Email Avanzato - Template moderni, automazione e statistiche', 'wceventsfp'); ?></li>
                <li>✅ <?php _e('Gestione Voucher Potenziata - Interfaccia moderna con modal WordPress-native', 'wceventsfp'); ?></li>
                <li>✅ <?php _e('Comunicazione Automatizzata - Promemoria, follow-up e notifiche intelligenti', 'wceventsfp'); ?></li>
                <li>✅ <?php _e('Analytics Voucher - Dashboard completa con statistiche dettagliate', 'wceventsfp'); ?></li>
            </ul>
            <p>
                <strong><?php _e('Prossimo:', 'wceventsfp'); ?></strong> 
                <?php _e('Phase 3 - Advanced Features (Calendar Integration, Reporting, Multi-language)', 'wceventsfp'); ?>
            </p>
            <button class="button button-primary" onclick="WCEFPModals.showSuccess('Phase 2 completata! 🚀')">
                <?php _e('Testa le Nuove Funzionalità', 'wceventsfp'); ?>
            </button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss notice after 10 seconds
            setTimeout(function() {
                const notice = document.getElementById('wcefp-phase2-notice');
                if (notice && notice.style.display !== 'none') {
                    notice.querySelector('.notice-dismiss').click();
                }
            }, 10000);
            
            // Handle manual dismiss
            jQuery(document).on('click', '#wcefp-phase2-notice .notice-dismiss', function() {
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wcefp_dismiss_phase2_notice',
                        nonce: '<?php echo wp_create_nonce('wcefp_dismiss_notice'); ?>'
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Log informational message
     * 
     * @param string $message Message to log
     * @param array $context Additional context
     */
    private function log_info($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info($message, array_merge([
                'component' => 'CommunicationServiceProvider'
            ], $context));
        }
    }
}