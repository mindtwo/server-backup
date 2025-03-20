<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Performs database backups using mysqldump
 */
class DatabaseBackup extends AbstractBackup
{
    private const MYSQLDUMP_COMMAND = 'mysqldump';
    
    /**
     * @inheritDoc
     */
    protected function validateConfig(array $config): void
    {
        if (empty($config['db_host'])) {
            throw new \InvalidArgumentException('Database host not configured');
        }
        
        if (empty($config['db_user'])) {
            throw new \InvalidArgumentException('Database user not configured');
        }
        
        if (empty($config['db_name'])) {
            throw new \InvalidArgumentException('Database name not configured');
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
            Helper::log("Starting database backup for {$this->getIdentifier()}");
            
            $this->cleanExistingBackups();
            
            $dumpCommand = $this->generateDumpCommand();
            $result = $this->executeDumpCommand($dumpCommand);
            
            if (!$result['success']) {
                return BackupResult::failure(
                    "Database backup failed for {$this->getIdentifier()}",
                    ['mysqldump_error' => $result['output']]
                );
            }
            
            if (!Helper::compressFile($this->getBackupFilePath())) {
                return BackupResult::failure(
                    "Failed to compress backup file for {$this->getIdentifier()}"
                );
            }
            
            $compressedFile = $this->getBackupFilePath() . '.gz';
            Helper::log("Database backup completed for {$this->getIdentifier()}");
            
            return BackupResult::success(
                "Database backup successful for {$this->getIdentifier()}",
                $compressedFile
            );
        } catch (\Throwable $e) {
            Helper::log("Error during database backup: " . $e->getMessage());
            return BackupResult::failure(
                "Database backup failed for {$this->getIdentifier()}: {$e->getMessage()}",
                ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
        }
    }
    
    /**
     * @inheritDoc
     */
    protected function getFileExtension(): string
    {
        return '.sql';
    }
    
    /**
     * Clean any existing backups with the same name
     */
    private function cleanExistingBackups(): void
    {
        Helper::deleteBackupFiles($this->getBackupFilePath());
    }
    
    /**
     * Generate the mysqldump command
     * 
     * @return array Command and arguments for mysqldump
     */
    private function generateDumpCommand(): array
    {
        $command = self::MYSQLDUMP_COMMAND;
        
        $args = [
            '--no-tablespaces',
            '--single-transaction',  // Consistent snapshot for InnoDB
            '--skip-lock-tables',    // Avoid locking tables for read
            '--routines',            // Include stored routines
            '--triggers',            // Include triggers
            '--add-drop-table',      // Makes restoration easier
            '--host', $this->getDatabaseHost(),
            '--user', $this->getDatabaseUser(),
        ];
        
        // Add password if specified
        $password = $this->getDatabasePassword();
        if (!empty($password)) {
            $args[] = '--password=' . $password;
        }
        
        // Add specific tables if configured
        $tables = $this->config['tables'] ?? [];
        if (!empty($tables) && is_array($tables)) {
            $args[] = $this->getDatabaseName();
            foreach ($tables as $table) {
                $args[] = $table;
            }
        } else {
            // Backup the entire database
            $args[] = $this->getDatabaseName();
        }
        
        return [$command, $args];
    }
    
    /**
     * Execute the mysqldump command and capture output to a file
     * 
     * @param array $commandData Command and arguments
     * @return array{output: string, success: bool} Command result
     */
    private function executeDumpCommand(array $commandData): array
    {
        [$command, $args] = $commandData;
        
        // Redirect output to the backup file
        $tmpArgs = $args;
        $tmpArgs[] = '>';
        $tmpArgs[] = $this->getBackupFilePath();
        
        return Helper::safeExec($command, $tmpArgs);
    }
    
    /**
     * Get the database host
     * 
     * @return string Database host
     */
    private function getDatabaseHost(): string
    {
        return $this->config['db_host'];
    }
    
    /**
     * Get the database user
     * 
     * @return string Database user
     */
    private function getDatabaseUser(): string
    {
        return $this->config['db_user'];
    }
    
    /**
     * Get the database password
     * 
     * @return string Database password (may be empty)
     */
    private function getDatabasePassword(): string
    {
        return $this->config['db_password'] ?? '';
    }
    
    /**
     * Get the database name
     * 
     * @return string Database name
     */
    private function getDatabaseName(): string
    {
        return $this->config['db_name'];
    }
}