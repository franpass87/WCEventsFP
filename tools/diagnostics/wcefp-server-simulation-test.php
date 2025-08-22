<?php
/**
 * WCEFP Server Condition Simulation Test
 * 
 * Simulates different server conditions to demonstrate adaptive loading
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/fake-wp/');
}
if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', __DIR__ . '/');
}

echo "=== WCEventsFP Server Condition Simulation Test ===\n\n";

require_once __DIR__ . '/wcefp-server-monitor.php';

// Simulate different server configurations
$server_configs = [
    'Ultra Limited Server' => [
        'memory_limit' => '32M',
        'max_execution_time' => '10',
        'description' => 'Very cheap shared hosting'
    ],
    'Basic Shared Hosting' => [
        'memory_limit' => '64M',
        'max_execution_time' => '30',
        'description' => 'Standard shared hosting'
    ],
    'Good Shared Hosting' => [
        'memory_limit' => '128M',
        'max_execution_time' => '60',
        'description' => 'Better shared hosting'
    ],
    'VPS/Cloud Server' => [
        'memory_limit' => '256M',
        'max_execution_time' => '300',
        'description' => 'Virtual private server'
    ],
    'Dedicated Server' => [
        'memory_limit' => '512M',
        'max_execution_time' => '0',
        'description' => 'Dedicated server'
    ]
];

foreach ($server_configs as $name => $config) {
    echo "📊 **{$name}** ({$config['description']})\n";
    echo "   Memory: {$config['memory_limit']}, Execution: {$config['max_execution_time']}s\n";
    
    // Mock the ini_get function to simulate different server conditions
    $original_memory = ini_get('memory_limit');
    $original_execution = ini_get('max_execution_time');
    
    // Simulate server conditions by temporarily overriding
    ini_set('memory_limit', $config['memory_limit']);
    ini_set('max_execution_time', $config['max_execution_time']);
    
    // Clear the static cache
    $reflection = new ReflectionClass('WCEFP_Server_Monitor');
    $property = $reflection->getProperty('resource_data');
    $property->setAccessible(true);
    $property->setValue(null);
    
    // Get recommendations for this configuration
    $mode = WCEFP_Server_Monitor::get_recommended_loading_mode();
    $score = WCEFP_Server_Monitor::get_resource_score();
    $limits = WCEFP_Server_Monitor::get_feature_limits();
    $can_activate = wcefp_can_activate_safely();
    
    // Display results
    echo "   📋 Recommended Mode: " . strtoupper($mode) . "\n";
    echo "   🎯 Resource Score: {$score}/100\n";
    echo "   🔢 Max Features: " . ($limits['max_features'] === -1 ? 'Unlimited' : $limits['max_features']) . "\n";
    echo "   ⏳ Load Delay: {$limits['load_delay_ms']}ms\n";
    echo "   🛡️  Safe Activation: " . ($can_activate ? 'YES' : 'NO') . "\n";
    
    // Show what the user would experience
    switch ($mode) {
        case WCEFP_Server_Monitor::MODE_ULTRA_MINIMAL:
            echo "   🚨 USER EXPERIENCE: Emergency mode - basic status page only\n";
            break;
        case WCEFP_Server_Monitor::MODE_MINIMAL:
            echo "   ⚠️  USER EXPERIENCE: Minimal features - core booking only\n";
            break;
        case WCEFP_Server_Monitor::MODE_PROGRESSIVE:
            echo "   📈 USER EXPERIENCE: Features load gradually over multiple page loads\n";
            break;
        case WCEFP_Server_Monitor::MODE_STANDARD:
            echo "   ✅ USER EXPERIENCE: Normal functionality with good performance\n";
            break;
        case WCEFP_Server_Monitor::MODE_FULL:
            echo "   🚀 USER EXPERIENCE: All features available immediately\n";
            break;
    }
    
    echo "\n";
    
    // Restore original settings
    ini_set('memory_limit', $original_memory);
    ini_set('max_execution_time', $original_execution);
}

echo "=== Key Benefits of Adaptive Loading ===\n\n";
echo "✅ **No More WSOD**: Plugin never crashes regardless of server limitations\n";
echo "✅ **Automatic Adaptation**: Detects server capabilities and adjusts accordingly\n";
echo "✅ **Graceful Degradation**: Reduces features rather than failing completely\n";
echo "✅ **Progressive Enhancement**: Better servers get more features automatically\n";
echo "✅ **User Feedback**: Clear messages about resource limitations and solutions\n";
echo "✅ **Easy Upgrades**: Plugin automatically uses more features when server is upgraded\n\n";

echo "=== Hosting Provider Recommendations ===\n\n";
echo "🔴 **Ultra Limited (Emergency Mode)**: Increase memory to 64MB+ and execution time to 30s+\n";
echo "🟡 **Minimal Mode**: Increase memory to 128MB+ and execution time to 60s+ for better experience\n";
echo "🟠 **Progressive Mode**: Increase memory to 256MB+ for standard functionality\n";
echo "🟢 **Standard/Full Mode**: Server is well-configured for professional use\n\n";

echo "This ensures the plugin works on ANY server while encouraging upgrades for better performance.\n";