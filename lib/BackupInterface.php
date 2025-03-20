<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Interface for all backup types
 */
interface BackupInterface
{
    /**
     * Run the backup process
     * 
     * @return BackupResult The result of the backup operation
     */
    public function run(): BackupResult;
    
    /**
     * Get the unique identifier for this backup
     * 
     * @return string The backup identifier
     */
    public function getIdentifier(): string;
}