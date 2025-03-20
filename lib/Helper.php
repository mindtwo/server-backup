<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Utility class for common operations
 */
final class Helper
{
    /**
     * Default log file path
     */
    private const DEFAULT_LOG_FILE = 'server-backup.log';
    
    /**
     * Maximum log file size before rotation (5MB default)
     */
    private const DEFAULT_MAX_LOG_SIZE = 5 * 1024 * 1024;
    
    /**
     * Number of log files to keep
     */
    private const DEFAULT_LOG_FILES_TO_KEEP = 5;
    
    /**
     * Log verbosity levels
     */
    public const LOG_LEVEL_ERROR = 0;
    public const LOG_LEVEL_INFO = 1;
    public const LOG_LEVEL_DEBUG = 2;
    
    /**
     * Current log level (configurable)
     */
    private static int $logLevel = self::LOG_LEVEL_INFO;
    
    /**
     * Maximum log size in bytes
     */
    private static int $maxLogSize = self::DEFAULT_MAX_LOG_SIZE;
    
    /**
     * Number of log files to keep when rotating
     */
    private static int $logFilesToKeep = self::DEFAULT_LOG_FILES_TO_KEEP;
    
    /**
     * Log a message to file and optionally to console
     *
     * @param mixed $message The message to log
     * @param int $level The log level (default: LOG_LEVEL_INFO)
     * @param bool $console Whether to also output to console (default: true for errors, false for others)
     * @return void
     */
    public static function log(mixed $message, int $level = self::LOG_LEVEL_INFO, ?bool $console = null): void
    {
        // Skip if message level is higher than current log level
        if ($level > self::$logLevel) {
            return;
        }
        
        // Format message
        $formattedMessage = is_scalar($message) ? $message : print_r($message, true);
        $timestamp = date('Y-m-d H:i:s');
        $levelPrefix = match($level) {
            self::LOG_LEVEL_ERROR => 'ERROR',
            self::LOG_LEVEL_INFO => 'INFO',
            self::LOG_LEVEL_DEBUG => 'DEBUG',
            default => '',
        };
        
        $formattedLog = sprintf("[%s] [%s] %s\n", $timestamp, $levelPrefix, $formattedMessage);
        
        // Get log file path
        $logFilePath = self::getLogFilePath();
        
        // Ensure log directory exists
        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        // Check if log rotation is needed
        self::checkAndRotateLog($logFilePath);
        
        // Append to log file
        file_put_contents($logFilePath, $formattedLog, FILE_APPEND);
        
        // Output to console if needed
        // Default: show errors in console, configure other levels
        if ($console ?? ($level === self::LOG_LEVEL_ERROR)) {
            echo $formattedLog;
        }
    }
    
    /**
     * Log an error message (always logged, shown in console by default)
     *
     * @param mixed $message The error message
     * @param bool $console Whether to also output to console
     * @return void
     */
    public static function logError(mixed $message, bool $console = false): void
    {
        self::log($message, self::LOG_LEVEL_ERROR, $console);
    }
    
    /**
     * Log an info message (logged if log level >= INFO)
     *
     * @param mixed $message The info message
     * @param bool $console Whether to also output to console
     * @return void
     */
    public static function logInfo(mixed $message, bool $console = false): void
    {
        self::log($message, self::LOG_LEVEL_INFO, $console);
    }
    
    /**
     * Log a debug message (logged if log level is DEBUG)
     *
     * @param mixed $message The debug message
     * @param bool $console Whether to also output to console
     * @return void
     */
    public static function logDebug(mixed $message, bool $console = false): void
    {
        self::log($message, self::LOG_LEVEL_DEBUG, $console);
    }
    
    /**
     * Set the current log level
     *
     * @param int $level The log level to set
     * @return void
     */
    public static function setLogLevel(int $level): void
    {
        self::$logLevel = max(0, min(2, $level));
    }
    
    /**
     * Get the log file path
     *
     * @return string The log file path
     */
    public static function getLogFilePath(): string
    {
        global $config;
        
        // Use configured log file if available
        if (isset($config['log_file'])) {
            return $config['log_file'];
        }
        
        // Otherwise use default location
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        return $basePath . '/logs/' . self::DEFAULT_LOG_FILE;
    }
    
    /**
     * Set maximum log file size
     *
     * @param int $size Maximum size in bytes
     * @return void
     */
    public static function setMaxLogSize(int $size): void
    {
        self::$maxLogSize = max(1024 * 1024, $size); // Minimum 1MB
    }
    
    /**
     * Set number of log files to keep during rotation
     *
     * @param int $count Number of files to keep
     * @return void
     */
    public static function setLogFilesToKeep(int $count): void
    {
        self::$logFilesToKeep = max(1, min(20, $count)); // Between 1 and 20
    }
    
    /**
     * Check if log rotation is needed and perform rotation if necessary
     *
     * @param string $logFile Path to the log file
     * @return void
     */
    private static function checkAndRotateLog(string $logFile): void
    {
        // If file doesn't exist yet, no need to rotate
        if (!file_exists($logFile)) {
            return;
        }
        
        // Check file size
        $size = filesize($logFile);
        if ($size === false || $size < self::$maxLogSize) {
            return;
        }
        
        self::rotateLogFiles($logFile);
    }
    
    /**
     * Rotate log files
     *
     * @param string $logFile Path to the log file
     * @return void
     */
    private static function rotateLogFiles(string $logFile): void
    {
        $dir = dirname($logFile);
        $baseName = basename($logFile);
        
        // Delete oldest log file if it exists
        $oldestLog = $dir . '/' . $baseName . '.' . self::$logFilesToKeep;
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }
        
        // Shift all existing logs
        for ($i = self::$logFilesToKeep - 1; $i >= 1; $i--) {
            $oldFile = $dir . '/' . $baseName . '.' . $i;
            $newFile = $dir . '/' . $baseName . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Move current log to .1
        rename($logFile, $dir . '/' . $baseName . '.1');
        
        // Create a new empty log file with timestamp header
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(
            $logFile, 
            "--- Log rotated at {$timestamp} ---\n"
        );
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param string $dir The directory path
     * @param int $mode The permissions mode
     * @return bool True if directory exists or was created successfully
     */
    public static function ensureDirectoryExists(string $dir, int $mode = 0750): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, $mode, true);
    }

    /**
     * Generate a filename for a backup file
     *
     * @param string $slug Identifier for the backup
     * @param string $extension File extension
     * @return string Formatted filename
     */
    public static function generateFilename(string $slug = '', string $extension = '.tar'): string
    {
        $dateTime = new \DateTime();
        $formattedDate = $dateTime->format('Ymd-His');
        $slugPart = $slug ? '-' . $slug : '';

        return $formattedDate . $slugPart . $extension;
    }

    /**
     * Compress a file using gzip
     *
     * @param string $file Path to the file to compress
     * @return bool True if compression was successful
     */
    public static function compressFile(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        try {
            $fileSize = filesize($file);
            if ($fileSize === false) {
                self::logError("Failed to get file size for compression: {$file}");
                return false;
            }
            
            // Memory-efficient approach for small files (less than 10MB)
            if ($fileSize < 10 * 1024 * 1024) {
                $data = file_get_contents($file);
                if ($data === false) {
                    self::logError("Failed to read file for compression: {$file}");
                    return false;
                }
                
                $compressed = gzencode($data, 9);
                if ($compressed === false) {
                    self::logError("Failed to compress data for file: {$file}");
                    return false;
                }
                
                if (file_put_contents($file . '.gz', $compressed) === false) {
                    self::logError("Failed to write compressed file: {$file}.gz");
                    return false;
                }
                
                unlink($file);
                return true;
            }
            
            // For medium-sized files (10MB-50MB), use chunked processing
            if ($fileSize < 50 * 1024 * 1024) {
                $inFile = fopen($file, 'rb');
                if ($inFile === false) {
                    self::logError("Failed to open file for chunked compression: {$file}");
                    return false;
                }
                
                $outFile = gzopen($file . '.gz', 'wb9');
                if ($outFile === false) {
                    fclose($inFile);
                    self::logError("Failed to create gzip file for chunked compression: {$file}.gz");
                    return false;
                }
                
                // Process in 1MB chunks
                $chunkSize = 1024 * 1024;
                while (!feof($inFile)) {
                    $chunk = fread($inFile, $chunkSize);
                    if ($chunk === false) {
                        fclose($inFile);
                        gzclose($outFile);
                        self::logError("Failed to read chunk during compression: {$file}");
                        return false;
                    }
                    
                    gzwrite($outFile, $chunk);
                }
                
                fclose($inFile);
                gzclose($outFile);
                unlink($file);
                
                return true;
            }
            
            // For large files (>50MB), use system gzip for better performance
            $escapedFile = escapeshellarg($file);
            $timeout = 600; // 10 minutes timeout for large files
            $result = self::safeExec("gzip", ["-9", $file], $timeout);
            
            if (!$result['success']) {
                self::logError("System gzip compression failed: " . $result['output']);
                return false;
            }
            
            return true;
        } catch (\Throwable $e) {
            self::logError("Compression failed: " . $e->getMessage());
            self::logDebug("Compression exception: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Delete a file if it exists
     *
     * @param string $file Path to the file
     * @return bool True if file was deleted or didn't exist
     */
    public static function deleteFile(string $file): bool
    {
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Delete backup files (both compressed and uncompressed)
     *
     * @param string $file Base filename
     * @return bool True if operation was successful
     */
    public static function deleteBackupFiles(string $file): bool
    {
        $result1 = self::deleteFile($file);
        $result2 = self::deleteFile("$file.gz");
        
        return $result1 && $result2;
    }
    
    /**
     * Get absolute path, ensuring trailing slash
     * 
     * @param string $path File path
     * @return string Normalized absolute path
     */
    public static function normalizePath(string $path): string
    {
        // First ensure path consistency with a trailing slash for directories
        $path = rtrim($path, '/');
        
        // Handle empty path
        if (empty($path)) {
            return '/';
        }
        
        // Convert to absolute path
        $absolutePath = realpath($path);
        
        // If realpath failed (e.g., directory doesn't exist yet), normalize the original path
        if ($absolutePath === false) {
            // Get absolute path based on working directory if path is relative
            if ($path[0] !== '/' && $path[0] !== '\\' && !preg_match('~^[A-Z]:~i', $path)) {
                $path = getcwd() . '/' . $path;
            }
            return $path . '/';
        }
        
        // Check if it's a directory (even if it has a file extension)
        if (is_dir($absolutePath)) {
            return $absolutePath . '/';
        }
        
        return $absolutePath;
    }
    
    /**
     * Safely execute a shell command with proper escaping
     * 
     * @param string $command The command to execute
     * @param array $args Arguments to be safely escaped and added to the command
     * @param int|null $timeout Optional timeout in seconds (null for no timeout)
     * @return array{output: string, success: bool, returnCode: int, command: string} Command output and success status
     */
    public static function safeExec(string $command, array $args = [], ?int $timeout = null): array
    {
        return self::safeExecWithEnv($command, $args, [], $timeout);
    }
    
    /**
     * Safely execute a shell command with environment variables and timeout
     * 
     * @param string $command The command to execute
     * @param array $args Arguments to be safely escaped and added to the command
     * @param array $env Environment variables to set for this command (key-value pairs)
     * @param int|null $timeout Optional timeout in seconds (null for no timeout)
     * @return array{output: string, success: bool, returnCode: int, command: string} Command output and success status
     */
    public static function safeExecWithEnv(string $command, array $args = [], array $env = [], ?int $timeout = null): array
    {
        $escapedArgs = array_map('escapeshellarg', $args);
        $fullCommand = $command . ' ' . implode(' ', $escapedArgs);
        
        // Prepare environment variables if needed
        $envPart = '';
        foreach ($env as $key => $value) {
            $envPart .= 'export ' . escapeshellarg($key) . '=' . escapeshellarg($value) . '; ';
        }
        
        // Add timeout if specified
        if ($timeout !== null && $timeout > 0) {
            // Create a command that will timeout
            $timeoutCommand = "timeout {$timeout}s ";
            $fullCommand = $timeoutCommand . $fullCommand;
        }
        
        // Execute with environment variables if any are set
        if (!empty($envPart)) {
            exec($envPart . $fullCommand . ' 2>&1', $output, $returnCode);
        } else {
            exec($fullCommand . ' 2>&1', $output, $returnCode);
        }
        
        // Special handling for timeout (return code 124)
        $timedOut = ($timeout !== null && $returnCode === 124);
        
        return [
            'output' => implode("\n", $output) . ($timedOut ? "\nCommand timed out after {$timeout} seconds." : ''),
            'success' => $returnCode === 0,
            'returnCode' => $returnCode,
            'command' => $fullCommand,
            'timedOut' => $timedOut
        ];
    }
}