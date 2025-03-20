<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Test utility for verifying backup cleanup functionality
 * Creates test backup files with various dates to test retention policies
 */
class TestCleanup
{
    /**
     * @var array<string, mixed> Configuration
     */
    private array $config;
    
    /**
     * @var int Number of test files created
     */
    private int $fileCount = 0;
    
    /**
     * @var array<string> Paths where test files were created
     */
    private array $testDirectories = [];
    
    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Run the test by creating fake backup files with various dates
     * 
     * @return array Test results summary
     */
    public function run(): array
    {
        $this->fileCount = 0;
        $this->testDirectories = [];
        
        // Create test directories based on configuration
        $this->setupTestDirectories();
        
        // Generate the test files
        $this->generateTestFiles();
        
        // Return summary information
        return [
            'files_created' => $this->fileCount,
            'test_directories' => $this->testDirectories,
            'retention_policies' => [
                'keep_daily_backups' => $this->config['keep_daily_backups'] ?? 30,
                'keep_monthly_backups' => $this->config['keep_monthly_backups'] ?? 12
            ]
        ];
    }
    
    /**
     * Create test directories if they don't exist
     */
    private function setupTestDirectories(): void
    {
        // Create directories from filesystem configs
        if (!empty($this->config['filesystems']) && is_array($this->config['filesystems'])) {
            foreach ($this->config['filesystems'] as $filesystem) {
                if (!empty($filesystem['destination'])) {
                    $dir = $filesystem['destination'];
                    Helper::ensureDirectoryExists($dir);
                    $this->testDirectories[] = Helper::normalizePath($dir);
                }
            }
        }
        
        // Create directories from database configs
        if (!empty($this->config['databases']) && is_array($this->config['databases'])) {
            foreach ($this->config['databases'] as $database) {
                if (!empty($database['destination'])) {
                    $dir = $database['destination'];
                    Helper::ensureDirectoryExists($dir);
                    $this->testDirectories[] = Helper::normalizePath($dir);
                }
            }
        }
        
        // Remove duplicates
        $this->testDirectories = array_unique($this->testDirectories);
    }
    
    /**
     * Generate test backup files with various dates
     */
    private function generateTestFiles(): void
    {
        foreach ($this->testDirectories as $directory) {
            Helper::logInfo("Creating test files in {$directory}", true);
            
            // Daily backups (recent to be kept)
            $this->createRecentDailyBackups($directory);
            
            // Daily backups (older to be deleted)
            $this->createOldDailyBackups($directory);
            
            // Monthly backups (recent to be kept)
            $this->createRecentMonthlyBackups($directory);
            
            // Monthly backups (older to be deleted)
            $this->createOldMonthlyBackups($directory);
        }
    }
    
    /**
     * Create recent daily backups (within retention period)
     * 
     * @param string $directory Directory where to create files
     */
    private function createRecentDailyBackups(string $directory): void
    {
        $keepDays = $this->config['keep_daily_backups'] ?? 30;
        
        // Create a few backups in the retention period (to be kept)
        for ($daysAgo = 1; $daysAgo < $keepDays; $daysAgo += 5) {
            $date = new \DateTime();
            $date->modify("-{$daysAgo} days");
            
            // Skip first day of month (would be a monthly backup)
            if ($date->format('d') === '01') {
                $date->modify('-1 day');
            }
            
            $this->createTestFile($directory, $date, 'daily', 'tar');
            $this->createTestFile($directory, $date, 'daily', 'sql');
        }
    }
    
    /**
     * Create old daily backups (outside retention period)
     * 
     * @param string $directory Directory where to create files
     */
    private function createOldDailyBackups(string $directory): void
    {
        $keepDays = $this->config['keep_daily_backups'] ?? 30;
        
        // Create a few backups outside the retention period (to be deleted)
        for ($daysAgo = $keepDays + 1; $daysAgo < $keepDays * 2; $daysAgo += 15) {
            $date = new \DateTime();
            $date->modify("-{$daysAgo} days");
            
            // Skip first day of month (would be a monthly backup)
            if ($date->format('d') === '01') {
                $date->modify('-1 day');
            }
            
            $this->createTestFile($directory, $date, 'daily-old', 'tar');
            $this->createTestFile($directory, $date, 'daily-old', 'sql');
        }
    }
    
    /**
     * Create recent monthly backups (within retention period)
     * 
     * @param string $directory Directory where to create files
     */
    private function createRecentMonthlyBackups(string $directory): void
    {
        $keepMonths = $this->config['keep_monthly_backups'] ?? 12;
        
        // Create a few monthly backups in the retention period (to be kept)
        for ($monthsAgo = 1; $monthsAgo < $keepMonths; $monthsAgo += 2) {
            $date = new \DateTime();
            $date->modify("-{$monthsAgo} months");
            $date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1); // First day of month
            
            $this->createTestFile($directory, $date, 'monthly', 'tar');
            $this->createTestFile($directory, $date, 'monthly', 'sql');
        }
    }
    
    /**
     * Create old monthly backups (outside retention period)
     * 
     * @param string $directory Directory where to create files
     */
    private function createOldMonthlyBackups(string $directory): void
    {
        $keepMonths = $this->config['keep_monthly_backups'] ?? 12;
        
        // Create a few monthly backups outside the retention period (to be deleted)
        for ($monthsAgo = $keepMonths + 1; $monthsAgo < $keepMonths * 1.5; $monthsAgo += 2) {
            $date = new \DateTime();
            $date->modify("-{$monthsAgo} months");
            $date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1); // First day of month
            
            $this->createTestFile($directory, $date, 'monthly-old', 'tar');
            $this->createTestFile($directory, $date, 'monthly-old', 'sql');
        }
    }
    
    /**
     * Create a test file with the specified date and type
     * 
     * @param string $directory Directory where to create the file
     * @param \DateTime $date Date for the backup file
     * @param string $type Type identifier for the filename
     * @param string $extension File extension (sql or tar)
     */
    private function createTestFile(string $directory, \DateTime $date, string $type, string $extension): void
    {
        // Format date string (same format used by the backup system)
        $dateStr = $date->format('Ymd-His');
        
        // Create filename
        $filename = "{$dateStr}-test-{$type}.{$extension}.gz";
        $filePath = $directory . $filename;
        
        // Create an empty file
        file_put_contents($filePath, "Test backup file for {$type} on {$date->format('Y-m-d H:i:s')}");
        
        // Set the modification time to match the date
        touch($filePath, $date->getTimestamp());
        
        $this->fileCount++;
    }
}