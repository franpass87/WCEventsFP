#!/usr/bin/env php
<?php
/**
 * Version Consistency Checker for WCEventsFP
 * 
 * Verifies that version numbers are consistent across:
 * - wceventsfp.php (plugin header & WCEFP_VERSION constant)
 * - README.md (title header)
 * - CHANGELOG.md (latest entry)
 * 
 * Usage: php bin/check-version-consistency.php
 * Exit codes: 0 = consistent, 1 = inconsistent, 2 = error
 */

function extractVersion($content, $pattern, $description) {
    if (preg_match($pattern, $content, $matches)) {
        return trim($matches[1]);
    }
    echo "❌ Could not extract version from {$description}\n";
    return null;
}

function main() {
    $rootDir = dirname(__DIR__);
    $files = [
        'wceventsfp.php',
        'README.md', 
        'CHANGELOG.md'
    ];
    
    // Check all required files exist
    foreach ($files as $file) {
        $path = $rootDir . '/' . $file;
        if (!file_exists($path)) {
            echo "❌ Required file not found: {$file}\n";
            exit(2);
        }
    }
    
    // Extract versions
    $versions = [];
    
    // Plugin header version
    $pluginContent = file_get_contents($rootDir . '/wceventsfp.php');
    $versions['plugin_header'] = extractVersion(
        $pluginContent, 
        '/\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', 
        'plugin header (wceventsfp.php)'
    );
    
    // Plugin constant version
    $versions['plugin_constant'] = extractVersion(
        $pluginContent,
        "/define\s*\(\s*['\"]WCEFP_VERSION['\"],\s*['\"]([0-9]+\.[0-9]+\.[0-9]+)['\"]/",
        'WCEFP_VERSION constant (wceventsfp.php)'
    );
    
    // README version
    $readmeContent = file_get_contents($rootDir . '/README.md');
    $versions['readme'] = extractVersion(
        $readmeContent,
        '/^#\s+WCEventsFP\s+\(v([0-9]+\.[0-9]+\.[0-9]+)\)/m',
        'README.md title'
    );
    
    // CHANGELOG latest version
    $changelogContent = file_get_contents($rootDir . '/CHANGELOG.md');
    $versions['changelog'] = extractVersion(
        $changelogContent,
        '/^\[([0-9]+\.[0-9]+\.[0-9]+)\]/m',
        'CHANGELOG.md latest entry'
    );
    
    // Check if any version extraction failed
    if (in_array(null, $versions, true)) {
        exit(2);
    }
    
    // Compare all versions
    $referenceVersion = $versions['plugin_header'];
    $allMatch = true;
    
    echo "🔍 Version Consistency Check\n";
    echo "=" . str_repeat("=", 28) . "\n";
    
    foreach ($versions as $source => $version) {
        $status = ($version === $referenceVersion) ? "✅" : "❌";
        $sourceName = str_replace('_', ' ', ucfirst($source));
        echo "{$status} {$sourceName}: {$version}\n";
        
        if ($version !== $referenceVersion) {
            $allMatch = false;
        }
    }
    
    echo "\n";
    
    if ($allMatch) {
        echo "✅ All versions are consistent: {$referenceVersion}\n";
        exit(0);
    } else {
        echo "❌ Version inconsistency detected!\n";
        echo "Expected version: {$referenceVersion} (from plugin header)\n";
        echo "\nTo fix, update all files to use version {$referenceVersion}:\n";
        echo "- README.md: # WCEventsFP (v{$referenceVersion})\n";
        echo "- CHANGELOG.md: Add [{$referenceVersion}] entry if missing\n";
        echo "- wceventsfp.php: Ensure both header and constant match\n";
        exit(1);
    }
}

main();