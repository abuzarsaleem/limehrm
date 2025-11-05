#!/usr/bin/env php
<?php
/**
 * Script to update primary theme colors in the database
 * 
 * This script updates the default theme colors to the new green color scheme
 * and clears the theme cache so changes take effect immediately.
 */

if (php_sapi_name() !== 'cli') {
    echo 'This script can only be run from the command line.';
    exit(1);
}

$pathToAutoload = realpath(__DIR__ . '/src/vendor/autoload.php');

if (!$pathToAutoload) {
    echo "Cannot find composer dependencies.\n";
    echo "Please run: composer install -d src\n";
    exit(1);
}

require_once $pathToAutoload;

use OrangeHRM\Config\Config;
use OrangeHRM\Core\Service\CacheService;
use OrangeHRM\Framework\Framework;
use OrangeHRM\ORM\Doctrine;

// Initialize framework
$env = 'prod';
$debug = false;
new Framework($env, $debug);

try {
    $entityManager = Doctrine::getEntityManager();
    $connection = $entityManager->getConnection();
    
    // New color values
    $newVariables = [
        'primaryColor' => '#28A848',
        'primaryFontColor' => '#FFFFFF',
        'secondaryColor' => '#76BC21',
        'secondaryFontColor' => '#FFFFFF',
        'primaryGradientStartColor' => '#28A848',
        'primaryGradientEndColor' => '#06562C'
    ];
    
    $variablesJson = json_encode($newVariables);
    
    echo "Updating theme colors in database...\n";
    
    // Update the default theme
    $connection->createQueryBuilder()
        ->update('ohrm_theme')
        ->set('variables', ':variables')
        ->where('theme_name = :themeName')
        ->setParameter('variables', $variablesJson)
        ->setParameter('themeName', 'default')
        ->executeStatement();
    
    echo "✓ Updated default theme colors\n";
    
    // Also update custom theme if it exists (system prioritizes custom over default)
    $customThemeExists = $connection->createQueryBuilder()
        ->select('COUNT(*)')
        ->from('ohrm_theme')
        ->where('theme_name = :themeName')
        ->setParameter('themeName', 'custom')
        ->executeQuery()
        ->fetchOne();
    
    if ($customThemeExists > 0) {
        $connection->createQueryBuilder()
            ->update('ohrm_theme')
            ->set('variables', ':variables')
            ->where('theme_name = :themeName')
            ->setParameter('variables', $variablesJson)
            ->setParameter('themeName', 'custom')
            ->executeStatement();
        
        echo "✓ Updated custom theme colors\n";
    }
    
    // Clear theme cache
    echo "Clearing theme cache...\n";
    $cache = CacheService::getCache('orangehrm');
    $cache->clear('admin.theme');
    
    echo "✓ Cache cleared\n";
    echo "\n";
    echo "Theme colors have been successfully updated!\n";
    echo "New primary color: #28A848\n";
    echo "New gradient end color: #06562C\n";
    echo "\n";
    echo "Please refresh your browser to see the changes.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

