<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Performs filesystem backups using tar
 */
class FilesystemBackup extends AbstractBackup
{
    private const ARCHIVER = 'tar';
    
    /**
     * @var string Source directory to backup
     */
    private string $sourceDir;
    
    /**
     * @var string Source directory name (without path)
     */
    private string $sourceDirName;
    
    /**
     * @var string Directory containing the source directory
     */
    private string $sourceParentDir;

    /**
     * @inheritDoc
     */
    protected function validateConfig(array $config): void
    {
        if (empty($config['source'])) {
            throw new \InvalidArgumentException('Source directory not configured');
        }
        
        if (!is_dir($config['source'])) {
            throw new \InvalidArgumentException("Source directory does not exist: {$config['source']}");
        }
        
        if (empty($config['slug'])) {
            throw new \InvalidArgumentException('Backup slug/identifier not configured');
        }
    }
    
    /**
     * @inheritDoc
     */
    public function run(): BackupResult
    {
        try {
            Helper::logInfo("Starting filesystem backup for {$this->getIdentifier()}", true);
            
            $this->prepareSourcePaths();
            $this->cleanExistingBackups();
            
            $tarCommand = $this->generateArchiveCommand();
            $result = $this->executeArchiveCommand($tarCommand);
            
            if (!$result['success']) {
                $errorDetails = [
                    'tar_error' => $result['output'],
                    'command' => $result['command'] ?? 'unknown',
                    'return_code' => $result['returnCode'] ?? 'unknown'
                ];
                
                Helper::logError("Filesystem backup failed for {$this->getIdentifier()}: " . json_encode($errorDetails, JSON_PRETTY_PRINT));
                
                return BackupResult::failure(
                    "Filesystem backup failed for {$this->getIdentifier()}",
                    $errorDetails
                );
            }
            
            if (!Helper::compressFile($this->getBackupFilePath())) {
                Helper::logError("Failed to compress backup file for {$this->getIdentifier()}");
                return BackupResult::failure(
                    "Failed to compress backup file for {$this->getIdentifier()}"
                );
            }
            
            $compressedFile = $this->getBackupFilePath() . '.gz';
            Helper::logInfo("Filesystem backup completed for {$this->getIdentifier()}", true);
            
            return BackupResult::success(
                "Filesystem backup successful for {$this->getIdentifier()}",
                $compressedFile
            );
        } catch (\Throwable $e) {
            Helper::logError("Error during filesystem backup: " . $e->getMessage());
            Helper::logDebug("Stack trace: " . $e->getTraceAsString());
            
            return BackupResult::failure(
                "Filesystem backup failed for {$this->getIdentifier()}: {$e->getMessage()}",
                ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
        }
    }
    
    /**
     * @inheritDoc
     */
    protected function getFileExtension(): string
    {
        return '.tar';
    }
    
    /**
     * Prepare source directory path information
     */
    private function prepareSourcePaths(): void
    {
        $this->sourceDir = realpath($this->config['source']);
        
        // Extract the source directory name and parent directory
        $pathInfo = pathinfo($this->sourceDir);
        $this->sourceDirName = $pathInfo['basename'];
        $this->sourceParentDir = $pathInfo['dirname'];
    }
    
    // cleanExistingBackups method moved to AbstractBackup
    
    /**
     * Generate the tar command for creating the archive
     * 
     * @return string[] Command and arguments for the tar process
     */
    private function generateArchiveCommand(): array
    {
        $excludeParams = $this->getExcludeParams();
        
        // Base tar command
        $command = self::ARCHIVER;
        
        // Command arguments
        $args = ['-c'];
        
        // Add verbose option if needed
        if (($this->config['verbose'] ?? false) === true) {
            $args[] = '-v';
        }
        
        // Add exclude patterns
        foreach ($excludeParams as $excludePattern) {
            $args[] = '--exclude=' . $excludePattern;
        }
        
        // Add output file
        $args[] = '-f';
        $args[] = $this->getBackupFilePath();
        
        // Add source directory (just the name, not the full path)
        $args[] = '-C';
        $args[] = $this->sourceParentDir;
        $args[] = $this->sourceDirName;
        
        return [$command, $args];
    }
    
    /**
     * Execute the archive command
     * 
     * @param array $commandData Command and arguments
     * @return array{output: string, success: bool} Command result
     */
    private function executeArchiveCommand(array $commandData): array
    {
        [$command, $args] = $commandData;
        
        // Execute the command from the parent directory
        $currentDir = getcwd();
        
        try {
            return Helper::safeExec($command, $args);
        } finally {
            // Restore original directory if it was changed
            if ($currentDir !== false && getcwd() !== $currentDir) {
                chdir($currentDir);
            }
        }
    }
    
    /**
     * Get exclude patterns for tar command
     * 
     * @return string[] Array of patterns to exclude
     */
    private function getExcludeParams(): array
    {
        $patterns = $this->config['exclude'] ?? [];
        if (!is_array($patterns)) {
            return [];
        }
        
        // Sanitize exclude patterns
        return array_map(function ($pattern) {
            // Remove any leading slashes to make patterns relative
            return ltrim($pattern, '/');
        }, $patterns);
    }
}