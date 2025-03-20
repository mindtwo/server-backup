#!/usr/bin/php
<?php
declare(strict_types=1);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set base directory to script location for consistent relative paths
chdir(__DIR__);

// Include dependencies
require_once __DIR__ . '/lib/Helper.php';
require_once __DIR__ . '/lib/BackupResult.php';
require_once __DIR__ . '/lib/BackupInterface.php';
require_once __DIR__ . '/lib/AbstractBackup.php';
require_once __DIR__ . '/lib/FilesystemBackup.php';
require_once __DIR__ . '/lib/DatabaseBackup.php';
require_once __DIR__ . '/lib/Cleanup.php';
require_once __DIR__ . '/lib/BackupManager.php';

use ServerBackup\Helper;
use ServerBackup\BackupManager;

// Load configuration
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    Helper::log("Configuration file '{$configFile}' does not exist. Please copy config.example.php to config.php and configure it.");
    exit(1);
}

$config = include $configFile;

if (empty($config) || !is_array($config)) {
    Helper::log("Invalid configuration format. Please check your config.php file.");
    exit(1);
}

try {
    // Initialize backup manager
    $backupManager = new BackupManager($config);
    
    // Run backup process
    Helper::log("Starting backup process");
    $success = $backupManager->run();
    
    // Output summary
    echo "\n" . $backupManager->generateSummary() . "\n";
    
    // Set exit code based on success
    exit($success ? 0 : 1);
} catch (\Throwable $e) {
    Helper::log("Fatal error: " . $e->getMessage());
    exit(1);
}