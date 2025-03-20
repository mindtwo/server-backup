<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Utility class for common operations
 */
final class Helper
{
    /**
     * Print a message to the standard output
     *
     * @param mixed $message The message to output
     * @return void
     */
    public static function log(mixed $message): void
    {
        $formattedMessage = is_scalar($message) ? $message : print_r($message, true);
        echo sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $formattedMessage);
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
            // Use PHP's native gzip functions if available and file size is manageable
            if (filesize($file) < 100 * 1024 * 1024) { // Less than 100MB
                $data = file_get_contents($file);
                if ($data === false) {
                    return false;
                }
                
                $compressed = gzencode($data, 9);
                if ($compressed === false) {
                    return false;
                }
                
                if (file_put_contents($file . '.gz', $compressed) === false) {
                    return false;
                }
                
                unlink($file);
                return true;
            }
            
            // For larger files, use system gzip for better memory usage
            $escapedFile = escapeshellarg($file);
            exec("gzip -9 {$escapedFile}", $output, $returnCode);
            return $returnCode === 0;
        } catch (\Throwable $e) {
            self::log("Compression failed: " . $e->getMessage());
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
        // Convert to absolute path
        $absolutePath = realpath($path);
        
        // If realpath failed, return original with trailing slash
        if ($absolutePath === false) {
            return rtrim($path, '/') . '/';
        }
        
        // Ensure trailing slash for directories
        if (is_dir($absolutePath) && substr($absolutePath, -1) !== '/') {
            $absolutePath .= '/';
        }
        
        return $absolutePath;
    }
    
    /**
     * Safely execute a shell command with proper escaping
     * 
     * @param string $command The command to execute
     * @param array $args Arguments to be safely escaped and added to the command
     * @return array{output: string, success: bool} Command output and success status
     */
    public static function safeExec(string $command, array $args = []): array
    {
        $escapedArgs = array_map('escapeshellarg', $args);
        $fullCommand = $command . ' ' . implode(' ', $escapedArgs);
        
        exec($fullCommand . ' 2>&1', $output, $returnCode);
        
        return [
            'output' => implode("\n", $output),
            'success' => $returnCode === 0
        ];
    }
}