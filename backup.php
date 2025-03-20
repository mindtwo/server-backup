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
require_once __DIR__ . '/lib/NotificationManager.php';

use ServerBackup\Helper;
use ServerBackup\BackupManager;

// Define base path constant for consistent file paths
define('BASE_PATH', __DIR__);

// Load configuration
$configFile = BASE_PATH . '/config.php';

if (!file_exists($configFile)) {
    Helper::logError("Configuration file '{$configFile}' does not exist. Please copy config.example.php to config.php and configure it.");
    exit(1);
}

$config = include $configFile;

if (empty($config) || !is_array($config)) {
    Helper::logError("Invalid configuration format. Please check your config.php file.");
    exit(1);
}

// Configure logging
$logLevel = $config['log_level'] ?? Helper::LOG_LEVEL_INFO;
Helper::setLogLevel($logLevel);

// Configure log rotation
if (isset($config['log_max_size'])) {
    Helper::setMaxLogSize($config['log_max_size']);
}

if (isset($config['log_files_to_keep'])) {
    Helper::setLogFilesToKeep($config['log_files_to_keep']);
}

// Create logs directory if it doesn't exist
$logsDir = BASE_PATH . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0750, true);
}

try {
    // Initialize backup manager
    $backupManager = new BackupManager($config);
    
    // Run backup process
    Helper::log("Starting backup process");
    $success = $backupManager->run();
    
    // Output clean summary to console
    $summary = $backupManager->generateSummary();
    
    // Clean up the summary for console display - only show essential information
    $summaryLines = explode("\n", $summary);
    $cleanSummary = [];
    
    // Keep the header section and totals
    for ($i = 0; $i < 5 && $i < count($summaryLines); $i++) {
        $cleanSummary[] = $summaryLines[$i];
    }
    
    // For failed backups, only show the failure reasons without the details
    if (!$success) {
        $cleanSummary[] = "";
        $cleanSummary[] = "Failed Backups:";
        foreach ($summaryLines as $line) {
            if (strpos($line, "- ") === 0) {
                $cleanSummary[] = $line;
            }
        }
    }
    
    echo "\n" . implode("\n", $cleanSummary) . "\n";
    
    // Set exit code based on success
    exit($success ? 0 : 1);
} catch (\Throwable $e) {
    Helper::log("Fatal error: " . $e->getMessage());
    exit(1);
}