<?php
declare(strict_types=1);

namespace ServerBackup;

/**
 * Class for managing notifications
 */
class NotificationManager
{
    /**
     * @var array<string, mixed> Configuration array
     */
    private array $config;
    
    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Send notifications about backup results
     * 
     * @param array<BackupResult> $results Backup results
     * @param string $summary Summary text
     * @param bool $success Whether all backups were successful
     * @return bool True if notification was sent successfully
     */
    public function sendNotifications(array $results, string $summary, bool $success): bool
    {
        // Check if notifications are configured and enabled
        if (empty($this->config['notifications'])) {
            return true; // No notifications configured, so consider it success
        }
        
        $notificationResults = [];
        
        // Process email notifications
        if (!empty($this->config['notifications']['email']['enabled'])) {
            $notificationResults[] = $this->sendEmailNotification($results, $summary, $success);
        }
        
        // Return true only if all configured notifications succeeded
        return !in_array(false, $notificationResults, true);
    }
    
    /**
     * Send email notification
     * 
     * @param array<BackupResult> $results Backup results
     * @param string $summary Summary text
     * @param bool $success Whether all backups were successful
     * @return bool True if email was sent successfully
     */
    private function sendEmailNotification(array $results, string $summary, bool $success): bool
    {
        $emailConfig = $this->config['notifications']['email'] ?? [];
        
        // Check if required email parameters are set
        if (empty($emailConfig['to']) || empty($emailConfig['from'])) {
            Helper::log("Email notification failed: missing 'to' or 'from' address");
            return false;
        }
        
        // Only send notification if backups failed or if 'always_notify' is true
        if ($success && empty($emailConfig['always_notify'])) {
            return true; // Don't need to send on success
        }
        
        // Prepare email
        $to = $emailConfig['to'];
        $subject = $emailConfig['subject'] ?? 'Server Backup Report';
        if (!$success) {
            $subject .= ' - FAILED';
        }
        
        // Build email headers
        $headers = [
            'From: ' . $emailConfig['from'],
            'Content-Type: text/plain; charset=UTF-8',
        ];
        
        // Build email body
        $body = "Server Backup Report - " . date('Y-m-d H:i:s') . "\n\n";
        $body .= $summary;
        
        // Add SMTP configuration if provided
        $additionalParams = '';
        if (!empty($emailConfig['smtp'])) {
            $smtp = $emailConfig['smtp'];
            $additionalParams = '-f ' . $emailConfig['from'];
            
            // Set PHP mail parameters for SMTP
            ini_set('SMTP', $smtp['host']);
            ini_set('smtp_port', (string)($smtp['port'] ?? 25));
            
            if (!empty($smtp['username']) && !empty($smtp['password'])) {
                ini_set('smtp_auth', 'true');
                ini_set('smtp_username', $smtp['username']);
                ini_set('smtp_password', $smtp['password']);
            }
        }
        
        // Send email
        try {
            $result = mail($to, $subject, $body, implode("\r\n", $headers), $additionalParams);
            
            if ($result) {
                Helper::log("Email notification sent successfully to {$to}");
                return true;
            } else {
                Helper::log("Failed to send email notification");
                return false;
            }
        } catch (\Throwable $e) {
            Helper::log("Email notification error: " . $e->getMessage());
            return false;
        }
    }
}