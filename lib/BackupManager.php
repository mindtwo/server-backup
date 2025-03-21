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
        
        Helper::logInfo("Starting backup process", true);
        
        // Run all filesystem backups
        if (!$this->runFilesystemBackups()) {
            $success = false;
            Helper::logError("One or more filesystem backups failed", true);
        }
        
        // Run all database backups
        if (!$this->runDatabaseBackups()) {
            $success = false;
            Helper::logError("One or more database backups failed", true);
        }
        
        // Run cleanup process
        Helper::logInfo("Starting cleanup process", false);
        $deletedFiles = $this->runCleanup();
        Helper::logInfo("Cleanup process completed. Deleted files: " . count($deletedFiles), false);
        
        // Send notifications if configured
        if ($this->sendNotifications($success)) {
            Helper::logInfo("Notifications sent successfully");
        }
        
        // Log overall result
        if ($success) {
            Helper::logInfo("Backup process completed successfully", true);
        } else {
            Helper::logError("Backup process completed with errors", true);
        }
        
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
            Helper::logInfo("No filesystem backups configured");
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
                    Helper::logError("Filesystem backup failed: " . $result->getMessage());
                    
                    // Log detailed error information if available
                    $details = $result->getDetails();
                    if (!empty($details)) {
                        Helper::logDebug("Filesystem backup error details: " . json_encode($details, JSON_PRETTY_PRINT));
                    }
                }
            } catch (\Throwable $e) {
                $success = false;
                $errorResult = BackupResult::failure(
                    "Error initializing filesystem backup: " . $e->getMessage(),
                    ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );
                $this->results[] = $errorResult;
                Helper::logError("Filesystem backup error: " . $e->getMessage());
                Helper::logDebug("Exception trace: " . $e->getTraceAsString());
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
            Helper::logInfo("No database backups configured");
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
                    Helper::logError("Database backup failed: " . $result->getMessage());
                    
                    // Log detailed error information if available
                    $details = $result->getDetails();
                    if (!empty($details)) {
                        Helper::logDebug("Database backup error details: " . json_encode($details, JSON_PRETTY_PRINT));
                    }
                }
            } catch (\Throwable $e) {
                $success = false;
                $errorResult = BackupResult::failure(
                    "Error initializing database backup: " . $e->getMessage(),
                    ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );
                $this->results[] = $errorResult;
                Helper::logError("Database backup error: " . $e->getMessage());
                Helper::logDebug("Exception trace: " . $e->getTraceAsString());
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
            $cleanup = new Cleanup($this->config);
            $deletedFiles = $cleanup->run();
            
            // Log detailed information about cleanup
            if (!empty($deletedFiles)) {
                Helper::logInfo("Cleanup removed " . count($deletedFiles) . " old backup files");
                Helper::logDebug("Deleted files: " . implode(", ", $deletedFiles));
            } else {
                Helper::logInfo("No files needed cleaning up");
            }
            
            return $deletedFiles;
        } catch (\Throwable $e) {
            Helper::logError("Cleanup process error: " . $e->getMessage());
            Helper::logDebug("Exception trace: " . $e->getTraceAsString());
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
                    
                    // Add detailed error information if available
                    $details = $result->getDetails();
                    if (!empty($details)) {
                        $summary[] = "  Error Details:";
                        foreach ($details as $key => $value) {
                            if (is_string($value)) {
                                // Truncate long error messages for readability
                                if (strlen($value) > 200) {
                                    $value = substr($value, 0, 200) . "... (truncated)";
                                }
                                $summary[] = "    - {$key}: {$value}";
                            }
                        }
                    }
                }
            }
        }
        
        return implode("\n", $summary);
    }
    
    /**
     * Send notifications about backup results
     *
     * @param bool $success Whether all backups succeeded
     * @return bool True if notifications were sent successfully
     */
    private function sendNotifications(bool $success): bool
    {
        if (empty($this->config['notifications'])) {
            return true; // No notifications configured
        }
        
        try {
            require_once __DIR__ . '/NotificationManager.php';
            $notificationManager = new NotificationManager($this->config);
            $summary = $this->generateSummary();
            return $notificationManager->sendNotifications($this->results, $summary, $success);
        } catch (\Throwable $e) {
            Helper::log("Notification error: " . $e->getMessage());
            return false;
        }
    }
}