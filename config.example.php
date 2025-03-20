<?php
declare(strict_types=1);

/**
 * Server Backup Configuration File
 *
 * Copy this file to config.php and customize the settings for your environment.
 */
return [
    // Filesystem backups configuration
    // You can remove or leave empty for database-only backups
    'filesystems' => [
        [
            'slug' => 'production', // Identifier used in filenames and logs
            'source' => '', // Source directory to backup (absolute or relative path)
            'destination' => 'storage/production', // Destination for backups (absolute or relative path)
            'exclude' => [ // Directories/files to exclude (tar format patterns)
                'releases/*',
                'shared/repository',
                'var/cache/*',
                'var/log/*',
                '.git',
                'node_modules',
                'vendor',
            ],
            'verbose' => false, // Optional: show verbose output
        ],

        // You can add more filesystem backup configurations here
        /*
        [
            'slug'        => 'media',
            'source'      => '/var/www/media',
            'destination' => 'storage/media',
            'exclude'     => [
                'tmp/*',
                'cache/*',
            ],
        ],
        */
    ],

    // Database backups configuration
    // You can remove or leave empty for filesystem-only backups
    'databases' => [
        [
            'slug' => 'production', // Identifier used in filenames and logs
            'destination' => 'storage/production/databases', // Destination for backups
            'db_host' => 'localhost', // Database host
            'db_user' => '', // Database username
            'db_password' => '', // Database password
            'db_name' => '', // Database name
            'tables' => [], // Optional: specific tables to backup (empty = all tables)
            
            // Advanced database settings (if needed)
            // 'db_socket' => '/path/to/mysql.sock', // MySQL socket path
            // 'db_port' => 3306, // MySQL port if not default
            // 'mysqldump_command' => '/usr/local/bin/mysqldump', // Full path to mysqldump if needed
            // 'command_timeout' => 3600, // Timeout in seconds for the database backup command
            // 'mysqldump_options' => [ // Additional mysqldump options
            //     '--opt',
            //     '--compress',
            //     '--default-character-set=utf8mb4',
            // ],
        ],

        // You can add more database backup configurations here
        /*
        [
            'slug'        => 'analytics',
            'destination' => 'storage/analytics/databases',
            'db_host'     => 'localhost',
            'db_user'     => 'analytics_user',
            'db_password' => 'analytics_password',
            'db_name'     => 'analytics',
            'tables'      => ['users', 'sessions', 'events'], // Only backup specific tables
        ],
        */
    ],

    // Retention policies
    'keep_daily_backups' => 30, // Days to keep daily backups
    'keep_monthly_backups' => 12, // Months to keep monthly backups

    // Logging configuration
    'log_file' => 'logs/server-backup.log', // Path to log file (relative to BASE_PATH or absolute)
    'log_level' => 1, // 0 = errors only, 1 = info (default), 2 = debug
    'log_max_size' => 5 * 1024 * 1024, // Maximum log file size before rotation (5MB default)
    'log_files_to_keep' => 5, // Number of rotated log files to keep
    
    // PHP CLI configuration
    'php_command' => 'php', // PHP command to use for CLI operations (can be 'php', 'php83', '/usr/bin/php83', etc.)

    // Notification settings (uncomment and configure to use)
    // 'notifications' => [
    //     'email' => [
    //         'enabled'       => false, // Set to true to enable email notifications
    //         'to'            => 'admin@example.com', // Recipient email address
    //         'from'          => 'backup@example.com', // Sender email address
    //         'subject'       => 'Backup Report', // Email subject prefix
    //         'always_notify' => false, // Set to true to send emails even on success
    //         
    //         // SMTP configuration (optional, only needed if PHP mail() defaults don't work)
    //         'smtp' => [
    //             'host'     => 'smtp.example.com', // SMTP server address
    //             'port'     => 587, // SMTP port (usually 25, 465, or 587)
    //             'username' => 'smtp-user', // SMTP username if authentication is required
    //             'password' => 'smtp-password', // SMTP password if authentication is required
    //             'secure'   => 'tls', // Connection security: 'ssl', 'tls', or empty for none
    //         ],
    //     ],
    // ],
];