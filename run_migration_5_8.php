<?php
/**
 * Simple script to run the V5_8_0 migration manually
 * 
 * Usage: php run_migration_5_8.php
 * 
 * Make sure your OrangeHRM is installed and database connection is configured
 */

require_once __DIR__ . '/src/vendor/autoload.php';

use OrangeHRM\Config\Config;
use OrangeHRM\Installer\Migration\V5_8_0\Migration;
use OrangeHRM\Installer\Util\MigrationHelper;
use OrangeHRM\Installer\Util\ConfigHelper;
use OrangeHRM\Installer\Framework\HttpKernel;
use OrangeHRM\Framework\Http\Session\MemorySessionStorage;
use OrangeHRM\Framework\Http\Session\Session;
use OrangeHRM\Framework\ServiceContainer;
use OrangeHRM\Framework\Services;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;

// Check if system is installed
if (!Config::isInstalled()) {
    die("Error: OrangeHRM is not installed. Please install first.\n");
}

try {
    echo "=== Running Migration 5.8.0 ===\n\n";
    
    // Initialize framework (required for installer utilities)
    new HttpKernel('prod', false);
    $sessionStorage = new MemorySessionStorage();
    ServiceContainer::getContainer()->set(Services::SESSION_STORAGE, $sessionStorage);
    $session = new Session($sessionStorage);
    $session->start();
    ServiceContainer::getContainer()->set(Services::SESSION, $session);
    echo "✓ Framework initialized\n";
    
    // Get database connection from installed system
    $conf = Config::getConf();
    echo "✓ Loaded database configuration\n";
    
    // Initialize StateContainer with DB info for installer Connection class
    $stateContainer = \OrangeHRM\Installer\Util\StateContainer::getInstance();
    $stateContainer->storeDbInfo(
        $conf->getDbHost(),
        $conf->getDbPort(),
        new \OrangeHRM\Authentication\Dto\UserCredential($conf->getDbUser(), $conf->getDbPass()),
        $conf->getDbName()
    );
    
    // Get connection using installer Connection class
    \OrangeHRM\Installer\Util\Connection::reset();
    $connection = \OrangeHRM\Installer\Util\Connection::getConnection();
    echo "✓ Database connection established\n";
    
    // Create migration helper
    $migrationHelper = new MigrationHelper($connection);
    
    // Create migration instance
    $migration = new Migration();
    
    // Log migration start
    echo "✓ Logging migration start...\n";
    $migrationHelper->logMigrationStarted('5.8.0');
    
    // Run migration
    echo "✓ Running migration...\n";
    $migration->up();
    
    // Update version in config
    $configHelper = new ConfigHelper();
    $configHelper->setConfigValue('instance.version', '5.8.0');
    echo "✓ Updated instance version to 5.8.0\n";
    
    // Log migration finish
    echo "✓ Logging migration completion...\n";
    $migrationHelper->logMigrationFinished('5.8.0');
    
    echo "\n=== Migration Completed Successfully! ===\n";
    echo "\nNext steps:\n";
    echo "1. Log out and log back in to OrangeHRM\n";
    echo "2. Navigate to: Time → Reports → Timesheet Report\n";
    echo "3. Verify the report is working correctly\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

