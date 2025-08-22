<?php
/**
 * WCEventsFP Plugin Loading Test
 * 
 * Upload this file to your WordPress site and access it directly to test
 * if the plugin loads safely without causing WSOD.
 * 
 * Usage: Upload to wp-content/plugins/wceventsfp/ and access via browser:
 *        https://yoursite.com/wp-content/plugins/wceventsfp/test-plugin-loading.php
 */

// Basic security check
if (!file_exists('../../../wp-config.php') && !file_exists('../../../../wp-config.php')) {
    die('This test must be run from within a WordPress installation.');
}

// Load WordPress
if (file_exists('../../../wp-load.php')) {
    require_once '../../../wp-load.php';
} elseif (file_exists('../../../../wp-load.php')) {
    require_once '../../../../wp-load.php';
} else {
    die('Cannot locate WordPress wp-load.php file.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WCEventsFP Plugin Loading Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .success { color: #155724; background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; border-radius: 4px; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .test-result { margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border: 1px solid #e9ecef; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 WCEventsFP Plugin Loading Test</h1>
        <p>Questo test verifica se il plugin WCEventsFP può essere caricato in sicurezza senza causare WSOD (White Screen of Death).</p>
        
        <?php
        $tests_passed = 0;
        $total_tests = 0;
        
        function show_result($test_name, $success, $message, $details = null) {
            global $tests_passed, $total_tests;
            $total_tests++;
            if ($success) $tests_passed++;
            
            $class = $success ? 'success' : 'error';
            $icon = $success ? '✅' : '❌';
            
            echo "<div class='test-result {$class}'>";
            echo "<strong>{$icon} {$test_name}</strong><br>";
            echo $message;
            if ($details) {
                echo "<details><summary>Dettagli</summary><pre>" . esc_html(print_r($details, true)) . "</pre></details>";
            }
            echo "</div>";
        }
        ?>
        
        <h2>Risultati Test</h2>
        
        <?php
        // Test 1: WordPress Environment
        $wp_loaded = defined('ABSPATH') && function_exists('wp_die');
        show_result(
            "Test 1: Ambiente WordPress", 
            $wp_loaded,
            $wp_loaded ? "WordPress caricato correttamente" : "WordPress non rilevato"
        );
        
        // Test 2: WooCommerce Check
        $woo_active = class_exists('WooCommerce');
        show_result(
            "Test 2: WooCommerce", 
            $woo_active,
            $woo_active ? "WooCommerce è attivo" : "WooCommerce non è attivo o non installato",
            $woo_active ? null : "WooCommerce è richiesto per il funzionamento del plugin"
        );
        
        // Test 3: Plugin Files
        $plugin_dir = dirname(__FILE__);
        $main_file = $plugin_dir . '/wceventsfp.php';
        $wsod_preventer = $plugin_dir . '/wcefp-wsod-preventer.php';
        
        $files_exist = file_exists($main_file) && file_exists($wsod_preventer);
        show_result(
            "Test 3: File Plugin", 
            $files_exist,
            $files_exist ? "File principali del plugin trovati" : "File principali del plugin mancanti",
            [
                'wceventsfp.php' => file_exists($main_file) ? 'Found' : 'Missing',
                'wcefp-wsod-preventer.php' => file_exists($wsod_preventer) ? 'Found' : 'Missing'
            ]
        );
        
        // Test 4: WSOD Preventer Loading
        $wsod_loaded = false;
        $wsod_error = null;
        
        if ($files_exist) {
            try {
                ob_start();
                include_once $wsod_preventer;
                $wsod_output = ob_get_clean();
                $wsod_loaded = defined('WCEFP_WSOD_PROTECTION_ACTIVE');
                
                if (!empty($wsod_output)) {
                    $wsod_error = "Output prodotto durante il caricamento: " . substr($wsod_output, 0, 200) . "...";
                }
            } catch (Throwable $e) {
                $wsod_error = $e->getMessage();
            }
        }
        
        show_result(
            "Test 4: Sistema Prevenzione WSOD", 
            $wsod_loaded,
            $wsod_loaded ? "Sistema di prevenzione WSOD attivato" : "Sistema di prevenzione WSOD fallito",
            $wsod_error ? ['error' => $wsod_error] : null
        );
        
        // Test 5: Plugin Function Loading (only if WSOD preventer is working)
        $plugin_functions_loaded = false;
        if ($wsod_loaded && !$wsod_error) {
            try {
                // Try to load main plugin functions
                include_once $main_file;
                $plugin_functions_loaded = function_exists('WCEFP');
            } catch (Throwable $e) {
                $wsod_error = "Errore nel caricamento: " . $e->getMessage();
            }
        }
        
        show_result(
            "Test 5: Funzioni Plugin", 
            $plugin_functions_loaded,
            $plugin_functions_loaded ? "Funzioni principali del plugin caricate" : "Impossibile caricare le funzioni del plugin",
            $wsod_error ? ['error' => $wsod_error] : null
        );
        
        // Test 6: Plugin Instance Creation
        $plugin_instance = null;
        if ($plugin_functions_loaded) {
            try {
                $plugin_instance = WCEFP();
                $instance_created = !is_null($plugin_instance);
            } catch (Throwable $e) {
                $instance_created = false;
                $wsod_error = "Errore nella creazione istanza: " . $e->getMessage();
            }
        } else {
            $instance_created = false;
        }
        
        show_result(
            "Test 6: Istanza Plugin", 
            $instance_created,
            $instance_created ? "Istanza del plugin creata con successo" : "Impossibile creare istanza del plugin",
            $wsod_error ? ['error' => $wsod_error] : null
        );
        
        ?>
        
        <h2>Riepilogo</h2>
        
        <?php if ($tests_passed == $total_tests): ?>
            <div class="success">
                <strong>🎉 Tutti i test superati! ({$tests_passed}/{$total_tests})</strong><br>
                Il plugin dovrebbe attivarsi senza causare WSOD. È possibile procedere con l'attivazione dalla dashboard WordPress.
            </div>
        <?php elseif ($tests_passed >= $total_tests - 1): ?>
            <div class="warning">
                <strong>⚠️ Test quasi tutti superati ({$tests_passed}/{$total_tests})</strong><br>
                Il plugin dovrebbe essere relativamente sicuro, ma ci sono alcuni problemi minori. 
                Verifica i dettagli dei test falliti sopra.
            </div>
        <?php else: ?>
            <div class="error">
                <strong>❌ Diversi test falliti ({$tests_passed}/{$total_tests})</strong><br>
                <strong>NON attivare il plugin</strong> finché i problemi non sono risolti. 
                Verifica i requisiti sistema e contatta il supporto se necessario.
            </div>
        <?php endif; ?>
        
        <h2>Prossimi Passi</h2>
        
        <?php if ($tests_passed == $total_tests): ?>
            <div class="info">
                <strong>Tutto OK - Procedi con l'attivazione:</strong>
                <ol>
                    <li>Vai alla dashboard WordPress → Plugin</li>
                    <li>Trova "WCEventsFP" nella lista</li>
                    <li>Clicca "Attiva"</li>
                    <li>Se vedi errori, disattiva immediatamente e contatta il supporto</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="error">
                <strong>Problemi rilevati - NON attivare:</strong>
                <ol>
                    <li>Risolvi tutti i problemi evidenziati nei test sopra</li>
                    <li>Esegui nuovamente questo test</li>
                    <li>Solo quando tutti i test passano, procedi con l'attivazione</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        <p><small>Test eseguito il <?php echo date('d/m/Y H:i:s'); ?> - WCEventsFP v2.1.0</small></p>
    </div>
</body>
</html>