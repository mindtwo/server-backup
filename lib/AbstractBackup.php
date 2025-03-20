<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Abstract base class for backup implementations
 */
abstract class AbstractBackup implements BackupInterface
{
    /**
     * @var array<string, mixed> Configuration array
     */
    protected array $config;
    
    /**
     * @var string Destination directory for backups
     */
    protected string $destinationDir;
    
    /**
     * @var string|null Backup file path
     */
    protected ?string $backupFilePath = null;
    
    /**
     * @param array<string, mixed> $config Configuration array
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = $config;
        
        // Set up destination directory
        $this->setupDestinationDirectory();
    }
    
    /**
     * Get the unique identifier for this backup
     * 
     * @return string The backup identifier
     */
    public function getIdentifier(): string
    {
        if (!isset($this->config['slug']) || !is_string($this->config['slug']) || empty($this->config['slug'])) {
            return 'unknown';
        }
        return $this->config['slug'];
    }
    
    /**
     * Get the backup file path
     * 
     * @return string The generated backup file path
     */
    protected function getBackupFilePath(): string
    {
        if ($this->backupFilePath === null) {
            $filename = Helper::generateFilename(
                $this->getIdentifier(), 
                $this->getFileExtension()
            );
            
            $this->backupFilePath = $this->destinationDir . $filename;
        }
        
        return $this->backupFilePath;
    }
    
    /**
     * Set up the destination directory
     * 
     * @throws \RuntimeException If destination cannot be created or accessed
     */
    protected function setupDestinationDirectory(): void
    {
        if (empty($this->config['destination'])) {
            throw new \InvalidArgumentException('Destination directory not configured');
        }
        
        if (!Helper::ensureDirectoryExists($this->config['destination'])) {
            throw new \RuntimeException(
                "Failed to create destination directory: {$this->config['destination']}"
            );
        }
        
        $this->destinationDir = Helper::normalizePath($this->config['destination']);
    }
    
    /**
     * Validate the configuration array
     * 
     * @param array<string, mixed> $config Configuration to validate
     * @throws \InvalidArgumentException If configuration is invalid
     */
    abstract protected function validateConfig(array $config): void;
    
    /**
     * Get the file extension for this backup type
     * 
     * @return string The file extension including the dot
     */
    abstract protected function getFileExtension(): string;
    
    /**
     * Clean any existing backups with the same name
     * 
     * @return void
     */
    protected function cleanExistingBackups(): void
    {
        Helper::deleteBackupFiles($this->getBackupFilePath());
    }
}