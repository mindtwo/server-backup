<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Result of a backup operation
 */
class BackupResult
{
    private bool $success;
    private string $message;
    private ?string $filePath;
    private ?array $errors;

    /**
     * @param bool $success Whether the backup was successful
     * @param string $message Result message
     * @param string|null $filePath Path to the backup file if successful
     * @param array|null $errors Array of errors if unsuccessful
     */
    public function __construct(
        bool $success,
        string $message,
        ?string $filePath = null,
        ?array $errors = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->filePath = $filePath;
        $this->errors = $errors;
    }

    /**
     * @return bool Whether the backup was successful
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * @return string The result message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string|null Path to the backup file if successful
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * @return array|null Array of errors if unsuccessful
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }
    
    /**
     * Create a successful result
     *
     * @param string $message Success message
     * @param string|null $filePath Path to the backup file
     * @return self
     */
    public static function success(string $message, ?string $filePath = null): self
    {
        return new self(true, $message, $filePath, null);
    }
    
    /**
     * Create a failure result
     *
     * @param string $message Error message
     * @param array|null $errors Detailed error information
     * @return self
     */
    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(false, $message, null, $errors);
    }
}