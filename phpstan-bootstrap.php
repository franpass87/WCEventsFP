<?php
// Definisci costanti minime per evitare "undefined"
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if (!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if (!defined('WPINC')) define('WPINC', 'wp-includes');
if (!defined('WP_DEBUG')) define('WP_DEBUG', false);
if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', false);

// WooCommerce constants
if (!defined('WC_ABSPATH')) define('WC_ABSPATH', WP_PLUGIN_DIR . '/woocommerce/');
if (!defined('WC_VERSION')) define('WC_VERSION', '8.0.0');

// Plugin constants
if (!defined('WCEFP_VERSION')) define('WCEFP_VERSION', '2.1.2');
if (!defined('WCEFP_PLUGIN_DIR')) define('WCEFP_PLUGIN_DIR', __DIR__ . '/');
if (!defined('WCEFP_PLUGIN_URL')) define('WCEFP_PLUGIN_URL', 'https://example.com/wp-content/plugins/wceventsfp/');
if (!defined('WCEFP_PLUGIN_BASENAME')) define('WCEFP_PLUGIN_BASENAME', 'wceventsfp/wceventsfp.php');