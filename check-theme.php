#!/usr/bin/env php
<?php
/**
 * Script to check current theme settings
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

use OrangeHRM\Framework\Framework;
use OrangeHRM\ORM\Doctrine;

// Initialize framework
$env = 'prod';
$debug = false;
new Framework($env, $debug);

try {
    $entityManager = Doctrine::getEntityManager();
    $connection = $entityManager->getConnection();
    
    echo "Checking themes in database...\n\n";
    
    // Get all themes
    $themes = $connection->createQueryBuilder()
        ->select('theme_id', 'theme_name', 'variables')
        ->from('ohrm_theme')
        ->executeQuery()
        ->fetchAllAssociative();
    
    foreach ($themes as $theme) {
        echo "Theme ID: {$theme['theme_id']}\n";
        echo "Theme Name: {$theme['theme_name']}\n";
        $variables = json_decode($theme['variables'], true);
        if ($variables) {
            echo "Primary Color: " . ($variables['primaryColor'] ?? 'N/A') . "\n";
            echo "Primary Gradient End: " . ($variables['primaryGradientEndColor'] ?? 'N/A') . "\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

