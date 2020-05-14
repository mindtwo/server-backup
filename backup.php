#!/usr/bin/php
<?php

use lib\DatabaseBackup;
use lib\FilesystemBackup;
use lib\Helper;
use lib\Cleanup;

require_once 'lib/Helper.php';
require_once 'lib/FilesystemBackup.php';
require_once 'lib/DatabaseBackup.php';
require_once 'lib/Cleanup.php';

$configFile = realpath('config.php');

if(empty($configFile)) {
    echo "Configuration file '${$configFile}' does not exist.\n";
    exit(1);
}

$config = include $configFile;

if (empty($config) || ! is_array($config)) {
    echo "Something went wrong with your configuration.\n";
    exit(1);
}

chdir(dirname(__FILE__));

foreach ($config['filesystems'] ?? [] as $filesystemConfig) {
    try {
        $backup = new FilesystemBackup($filesystemConfig);
        $backup->run();
    } catch (Throwable $error) {
        echo Helper::echo('Filesystem backup failed: '.$error->getMessage());
        exit(1);
    }
}

foreach ($config['databases'] ?? [] as $databaseConfig) {
    try {
        $backup = new DatabaseBackup($databaseConfig);
        $backup->run();
    } catch (Throwable $error) {
        echo Helper::echo('Database backup failed: '.$error->getMessage());
        exit(1);
    }
}
echo "\nAll Backups done. Your are save!\n";

echo "\nStarting cleanup...\n\n";
$cleanup = new Cleanup($config);
$cleanup->run();
echo "\nCleanup done. Orphaned files are gone!\n";

?>