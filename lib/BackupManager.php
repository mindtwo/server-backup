<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Main class for managing the backup process
 */
class BackupManager
{
    /**
     * @var array<string, mixed> Configuration array
     */
    private array $config;
    
    /**
     * @var array<BackupResult> Results from backup operations
     */
    private array $results = [];
    
    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Run all configured backup processes
     * 
     * @return bool True if all backups succeeded
     */
    public function run(): bool
    {
        $this->results = [];
        $success = true;
        
        // Run all filesystem backups
        if (!$this->runFilesystemBackups()) {
            $success = false;
        }
        
        // Run all database backups
        if (!$this->runDatabaseBackups()) {
            $success = false;
        }
        
        // Run cleanup process
        $this->runCleanup();
        
        return $success;
    }
    
    /**
     * Get all backup results
     * 
     * @return array<BackupResult> Results from backup operations
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Run all filesystem backups
     * 
     * @return bool True if all backups succeeded
     */
    private function runFilesystemBackups(): bool
    {
        if (empty($this->config['filesystems']) || !is_array($this->config['filesystems'])) {
            Helper::log("No filesystem backups configured");
            return true;
        }
        
        $success = true;
        
        foreach ($this->config['filesystems'] as $filesystemConfig) {
            try {
                $backup = new FilesystemBackup($filesystemConfig);
                $result = $backup->run();
                $this->results[] = $result;
                
                if (!$result->isSuccessful()) {
                    $success = false;
                    Helper::log("Filesystem backup failed: " . $result->getMessage());
                }
            } catch (\Throwable $e) {
                $success = false;
                $errorResult = BackupResult::failure(
                    "Error initializing filesystem backup: " . $e->getMessage(),
                    ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );
                $this->results[] = $errorResult;
                Helper::log("Filesystem backup error: " . $e->getMessage());
            }
        }
        
        return $success;
    }
    
    /**
     * Run all database backups
     * 
     * @return bool True if all backups succeeded
     */
    private function runDatabaseBackups(): bool
    {
        if (empty($this->config['databases']) || !is_array($this->config['databases'])) {
            Helper::log("No database backups configured");
            return true;
        }
        
        $success = true;
        
        foreach ($this->config['databases'] as $databaseConfig) {
            try {
                $backup = new DatabaseBackup($databaseConfig);
                $result = $backup->run();
                $this->results[] = $result;
                
                if (!$result->isSuccessful()) {
                    $success = false;
                    Helper::log("Database backup failed: " . $result->getMessage());
                }
            } catch (\Throwable $e) {
                $success = false;
                $errorResult = BackupResult::failure(
                    "Error initializing database backup: " . $e->getMessage(),
                    ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );
                $this->results[] = $errorResult;
                Helper::log("Database backup error: " . $e->getMessage());
            }
        }
        
        return $success;
    }
    
    /**
     * Run cleanup process
     * 
     * @return array<string> List of deleted files
     */
    private function runCleanup(): array
    {
        try {
            Helper::log("Starting cleanup process");
            $cleanup = new Cleanup($this->config);
            $deletedFiles = $cleanup->run();
            Helper::log("Cleanup process completed");
            
            return $deletedFiles;
        } catch (\Throwable $e) {
            Helper::log("Cleanup process error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate a summary of the backup operations
     * 
     * @return string Summary text
     */
    public function generateSummary(): string
    {
        $totalBackups = count($this->results);
        $successfulBackups = 0;
        $failedBackups = 0;
        
        foreach ($this->results as $result) {
            if ($result->isSuccessful()) {
                $successfulBackups++;
            } else {
                $failedBackups++;
            }
        }
        
        $summary = [
            "Backup Summary:",
            "---------------",
            "Total backups: $totalBackups",
            "Successful: $successfulBackups",
            "Failed: $failedBackups",
        ];
        
        if ($failedBackups > 0) {
            $summary[] = "\nFailed Backups:";
            foreach ($this->results as $result) {
                if (!$result->isSuccessful()) {
                    $summary[] = "- " . $result->getMessage();
                }
            }
        }
        
        return implode("\n", $summary);
    }
}