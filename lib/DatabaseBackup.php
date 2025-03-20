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
            Helper::logInfo("Starting database backup for {$this->getIdentifier()}", false);
            
            $this->cleanExistingBackups();
            
            $dumpCommand = $this->generateDumpCommand();
            $result = $this->executeDumpCommand($dumpCommand);
            
            if (!$result['success']) {
                $errorDetails = [
                    'mysqldump_error' => $result['output'],
                    'command' => $result['command'] ?? 'unknown',
                    'return_code' => $result['returnCode'] ?? 'unknown'
                ];
                
                // Log detailed error information
                Helper::logError("Database backup failed for {$this->getIdentifier()}: " . json_encode($errorDetails, JSON_PRETTY_PRINT), false);
                
                return BackupResult::failure(
                    "Database backup failed for {$this->getIdentifier()}",
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
            Helper::logInfo("Database backup completed for {$this->getIdentifier()}", false);
            
            return BackupResult::success(
                "Database backup successful for {$this->getIdentifier()}",
                $compressedFile
            );
        } catch (\Throwable $e) {
            Helper::logError("Error during database backup: " . $e->getMessage());
            Helper::logDebug("Stack trace: " . $e->getTraceAsString());
            
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
    
    // cleanExistingBackups method moved to AbstractBackup
    
    /**
     * Generate the mysqldump command
     * 
     * @return array Command and arguments for mysqldump
     */
    private function generateDumpCommand(): array
    {
        // Domain Factory sometimes requires a specific mysqldump path 
        // or specific flags for their hosting environment
        $command = isset($this->config['mysqldump_command']) 
            ? $this->config['mysqldump_command'] 
            : self::MYSQLDUMP_COMMAND;
            
        // Handle Domain Factory's potential configurations
        // Domain Factory sometimes requires specific socket paths
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
        
        // Add socket path if specified
        if (!empty($this->config['db_socket'])) {
            $args[] = '--socket=' . $this->config['db_socket'];
        }
        
        // Add port if specified
        if (!empty($this->config['db_port'])) {
            $args[] = '--port=' . $this->config['db_port'];
        }
        
        // Handle password securely - use MYSQL_PWD environment variable instead of command line
        // This prevents the password from appearing in process lists
        $password = $this->getDatabasePassword();
        if (!empty($password)) {
            // Note: We'll set MYSQL_PWD in the executeDumpCommand method
            // We don't add --password to args here to keep it off command line
        }
        
        // Add any custom mysqldump options if configured
        if (!empty($this->config['mysqldump_options']) && is_array($this->config['mysqldump_options'])) {
            foreach ($this->config['mysqldump_options'] as $option) {
                $args[] = $option;
            }
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
        
        // Debug log the command being executed
        Helper::logDebug("Executing command: {$command} " . implode(' ', $args));
        
        // Get password for environment variable
        $password = $this->getDatabasePassword();
        $env = [];
        if (!empty($password)) {
            $env['MYSQL_PWD'] = $password;
        }
        
        // Add a timeout to prevent hanging processes
        $timeout = $this->config['command_timeout'] ?? 3600; // Default: 1 hour
        
        // Create output file directory if it doesn't exist
        $outputPath = $this->getBackupFilePath();
        $outputDir = dirname($outputPath);
        Helper::ensureDirectoryExists($outputDir);
        
        // Execute command with environment variables and timeout, redirecting output to file
        $fullCommand = $command . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' > ' . escapeshellarg($outputPath);
        
        // Execute with environment variables
        $output = [];
        $returnCode = 0;
        
        if (!empty($env)) {
            $envPart = '';
            foreach ($env as $key => $value) {
                $envPart .= 'export ' . escapeshellarg($key) . '=' . escapeshellarg($value) . '; ';
            }
            
            if ($timeout !== null && $timeout > 0) {
                $fullCommand = "timeout {$timeout}s " . $fullCommand;
            }
            
            exec($envPart . $fullCommand . ' 2>&1', $output, $returnCode);
        } else {
            if ($timeout !== null && $timeout > 0) {
                $fullCommand = "timeout {$timeout}s " . $fullCommand;
            }
            
            exec($fullCommand . ' 2>&1', $output, $returnCode);
        }
        
        $result = [
            'output' => implode("\n", $output),
            'success' => $returnCode === 0,
            'returnCode' => $returnCode,
            'command' => $fullCommand
        ];
        
        // Log error output if the command failed
        if (!$result['success']) {
            Helper::logError("MySQL dump command failed with output: " . $result['output'], false);
            Helper::logError("Return code: " . ($result['returnCode'] ?? 'unknown'), false);
            
            // Check if mysqldump exists
            $whichResult = Helper::safeExec('which', [$command]);
            Helper::logError("MySQL dump location: " . ($whichResult['success'] ? $whichResult['output'] : 'Command not found'), false);
        }
        
        return $result;
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