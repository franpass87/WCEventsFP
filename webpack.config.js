/**
 * WordPress scripts webpack configuration for WCEventsFP
 * 
 * @package WCEFP
 * @since 2.1.1
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        // Main entry points
        admin: './assets/js/admin.js',
        frontend: './assets/js/frontend.js',
        
        // Feature-specific bundles  
        'debug-tools': './assets/js/debug-tools.js',
        'conversion-optimization': './assets/js/conversion-optimization.js',
        
        // Admin styles
        'admin-style': './assets/css/admin.css',
        'frontend-style': './assets/css/frontend.css'
    },
    
    output: {
        path: path.resolve(__dirname, 'assets/dist'),
        filename: '[name].min.js'
    },
    
    // WordPress handles jQuery, don't bundle it
    externals: {
        jquery: 'jQuery',
        wp: 'wp'
    }
};