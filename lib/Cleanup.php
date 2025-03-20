<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Manages backup file retention policies
 */
class Cleanup
{
    /**
     * @var array<string, mixed> Configuration
     */
    private array $config;
    
    /**
     * @var int Days to keep daily backups
     */
    private int $keepDailyBackups;
    
    /**
     * @var int Months to keep monthly backups
     */
    private int $keepMonthlyBackups;
    
    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->keepDailyBackups = (int)($config['keep_daily_backups'] ?? 30);
        $this->keepMonthlyBackups = (int)($config['keep_monthly_backups'] ?? 12);
    }
    
    /**
     * Run cleanup process
     * 
     * @return array<string> List of deleted files
     */
    public function run(): array
    {
        $deletedFiles = [];
        
        foreach ($this->getBackupDirectories() as $directory) {
            Helper::log("Cleaning up directory: {$directory}");
            $deletedInDir = $this->cleanDirectory($directory);
            $deletedFiles = array_merge($deletedFiles, $deletedInDir);
        }
        
        if (count($deletedFiles) === 0) {
            Helper::log("No files needed cleaning up");
        } else {
            Helper::log("Cleanup complete. Removed " . count($deletedFiles) . " old backup files");
        }
        
        return $deletedFiles;
    }
    
    /**
     * Get all backup directories from configuration
     * 
     * @return array<string> List of absolute directory paths
     */
    private function getBackupDirectories(): array
    {
        $directories = [];
        
        // Add filesystem backup destinations
        if (!empty($this->config['filesystems']) && is_array($this->config['filesystems'])) {
            foreach ($this->config['filesystems'] as $filesystem) {
                if (!empty($filesystem['destination']) && is_dir($filesystem['destination'])) {
                    $directories[] = Helper::normalizePath($filesystem['destination']);
                }
            }
        }
        
        // Add database backup destinations
        if (!empty($this->config['databases']) && is_array($this->config['databases'])) {
            foreach ($this->config['databases'] as $database) {
                if (!empty($database['destination']) && is_dir($database['destination'])) {
                    $directories[] = Helper::normalizePath($database['destination']);
                }
            }
        }
        
        return array_unique($directories);
    }
    
    /**
     * Clean a single directory based on retention policies
     * 
     * @param string $directory Directory to clean
     * @return array<string> List of deleted files
     */
    private function cleanDirectory(string $directory): array
    {
        $deletedFiles = [];
        
        if (!is_dir($directory)) {
            Helper::log("Directory does not exist: {$directory}");
            return $deletedFiles;
        }
        
        $files = $this->scanBackupFiles($directory);
        
        // Group files by month to ensure we keep at least one backup per month
        $monthlyGroups = [];
        $dailyFiles = [];
        
        // Current time for reference
        $now = time();
        
        // Calculate cutoff dates more precisely
        $dailyCutoff = strtotime("-{$this->keepDailyBackups} days", $now);
        $monthlyCutoff = strtotime("-{$this->keepMonthlyBackups} months", $now);
        
        Helper::log("Retention policy: keeping daily backups for {$this->keepDailyBackups} days, monthly backups for {$this->keepMonthlyBackups} months");
        Helper::log("Daily cutoff: " . date('Y-m-d', $dailyCutoff) . ", Monthly cutoff: " . date('Y-m-d', $monthlyCutoff));
        
        foreach ($files as $file) {
            $fullPath = $directory . $file;
            
            // Extract timestamp from file modification time
            $timestamp = filemtime($fullPath);
            if ($timestamp === false) {
                continue;
            }
            
            $month = date('Y-m', $timestamp);
            
            // For monthly backups, add to monthly group
            if ($this->isMonthlyBackup($file)) {
                if (!isset($monthlyGroups[$month])) {
                    $monthlyGroups[$month] = [];
                }
                $monthlyGroups[$month][] = [
                    'file' => $file,
                    'path' => $fullPath,
                    'timestamp' => $timestamp
                ];
            } else {
                // For regular daily backups
                $dailyFiles[] = [
                    'file' => $file,
                    'path' => $fullPath,
                    'timestamp' => $timestamp,
                    'month' => $month
                ];
            }
        }
        
        // Process monthly backups first
        // Keep only the newest backup for each month within retention period
        $keptMonths = [];
        $keptFiles = [];
        
        foreach ($monthlyGroups as $month => $monthFiles) {
            // Sort files by timestamp (newest first)
            usort($monthFiles, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            // Get month start timestamp for accurate comparison
            $monthStart = strtotime($month . '-01');
            if ($monthStart === false) {
                continue;
            }
            
            // Determine if this month should be kept
            $keepThisMonth = ($monthStart >= $monthlyCutoff);
            
            if ($keepThisMonth && !empty($monthFiles)) {
                // Within retention period - keep newest monthly backup
                $keptMonths[] = $month;
                $keptFiles[] = $monthFiles[0]['path'];
                
                Helper::log("Keeping monthly backup for {$month}: {$monthFiles[0]['file']}");
                
                // Remove the first/newest file that we're keeping
                array_shift($monthFiles);
                
                // Delete any additional backups for this month
                foreach ($monthFiles as $file) {
                    Helper::log("Removing duplicate monthly backup: {$file['file']}");
                    if (unlink($file['path'])) {
                        $deletedFiles[] = $file['path'];
                    }
                }
            } else {
                // Outside retention period - delete all files for this month
                foreach ($monthFiles as $file) {
                    Helper::log("Removing expired monthly backup for {$month}: {$file['file']}");
                    if (unlink($file['path'])) {
                        $deletedFiles[] = $file['path'];
                    }
                }
            }
        }
        
        // Now process daily backups
        // First, group daily backups by month for easier processing
        $dailyByMonth = [];
        foreach ($dailyFiles as $file) {
            if (!isset($dailyByMonth[$file['month']])) {
                $dailyByMonth[$file['month']] = [];
            }
            $dailyByMonth[$file['month']][] = $file;
        }
        
        // Process daily backups by month
        foreach ($dailyByMonth as $month => $monthFiles) {
            // Sort files by timestamp (newest first)
            usort($monthFiles, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            // Get month start timestamp for accurate comparison
            $monthStart = strtotime($month . '-01');
            if ($monthStart === false) {
                continue;
            }
            
            // If this month is within monthly retention period but has no monthly backup,
            // keep the newest daily backup as a stand-in for the monthly backup
            $monthNeedsBackup = ($monthStart >= $monthlyCutoff && !in_array($month, $keptMonths));
            
            if ($monthNeedsBackup && !empty($monthFiles)) {
                $keptFiles[] = $monthFiles[0]['path'];
                $keptMonths[] = $month;
                Helper::log("Keeping newest daily backup for month {$month} as monthly representative: {$monthFiles[0]['file']}");
                array_shift($monthFiles);
            }
            
            // Process remaining daily backups
            foreach ($monthFiles as $file) {
                if ($file['timestamp'] >= $dailyCutoff) {
                    // Keep all backups within daily retention period
                    $keptFiles[] = $file['path'];
                    Helper::log("Keeping daily backup within retention period: {$file['file']}");
                } else {
                    // Delete backups outside of retention period
                    Helper::log("Removing expired daily backup: {$file['file']}");
                    if (unlink($file['path'])) {
                        $deletedFiles[] = $file['path'];
                    }
                }
            }
        }
        
        return $deletedFiles;
    }
    
    /**
     * Scan a directory for backup files
     * 
     * @param string $directory Directory to scan
     * @return array<string> List of backup files
     */
    private function scanBackupFiles(string $directory): array
    {
        // Ensure directory has trailing slash
        $directory = rtrim($directory, '/') . '/';
        
        $files = scandir($directory);
        if ($files === false) {
            return [];
        }
        
        // Filter out directories and non-backup files
        return array_filter($files, function($file) use ($directory) {
            return !in_array($file, ['.', '..']) && 
                   is_file($directory . $file) && 
                   preg_match('/\.(tar|sql)\.gz$/', $file);
        });
    }
    
    /**
     * Check if a file is a monthly backup (created on the first day of month)
     * 
     * @param string $filename Backup filename to check
     * @return bool True if this is a monthly backup
     */
    private function isMonthlyBackup(string $filename): bool
    {
        // Check if filename contains a date pattern with day 01
        return (bool)preg_match('/\d{4}\d{2}01-\d{6}/', $filename);
    }
    
    /**
     * Check if a monthly backup has expired
     * 
     * @param string $file Backup file path
     * @return bool True if the backup should be deleted
     */
    private function isMonthlyBackupExpired(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        
        $modificationTime = filemtime($file);
        if ($modificationTime === false) {
            return false;
        }
        
        // Use strtotime for more accurate month calculation
        $cutoffDate = strtotime("-{$this->keepMonthlyBackups} months", time());
        
        return $modificationTime < $cutoffDate;
    }
    
    /**
     * Check if a daily backup has expired
     * 
     * @param string $file Backup file path
     * @return bool True if the backup should be deleted
     */
    private function isDailyBackupExpired(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        
        $modificationTime = filemtime($file);
        if ($modificationTime === false) {
            return false;
        }
        
        // Calculate exact day cutoff
        $cutoffDate = strtotime("-{$this->keepDailyBackups} days", time());
        
        return $modificationTime < $cutoffDate;
    }
}