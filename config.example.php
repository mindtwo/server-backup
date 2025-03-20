<?php
declare(strict_types=1);

/**
 * Server Backup Configuration File
 * 
 * Copy this file to config.php and customize the settings for your environment.
 */
return [
    // Retention policies
    'keep_daily_backups'   => 30,  // Days to keep daily backups
    'keep_monthly_backups' => 12,  // Months to keep monthly backups
    
    // Filesystem backups configuration
    'filesystems' => [
        [
            'slug'        => 'production',  // Identifier used in filenames and logs
            'source'      => '',            // Source directory to backup (absolute or relative path)
            'destination' => 'storage/production',  // Destination for backups (absolute or relative path)
            'exclude'     => [              // Directories/files to exclude (tar format patterns)
                'releases/*',
                'shared/repository',
                'var/cache/*',
                'var/log/*',
                '.git',
                'node_modules',
            ],
            'verbose'     => false,         // Optional: show verbose output
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
    'databases' => [
        [
            'slug'        => 'production',  // Identifier used in filenames and logs
            'destination' => 'storage/production/databases',  // Destination for backups
            'db_host'     => 'localhost',   // Database host
            'db_user'     => '',            // Database username
            'db_password' => '',            // Database password
            'db_name'     => '',            // Database name
            'tables'      => [],            // Optional: specific tables to backup (empty = all tables)
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
            'tables'      => ['users', 'sessions', 'events'],  // Only backup specific tables
        ],
        */
    ],
    
    // Notification settings (uncomment and configure to use)
    // 'notifications' => [
    //     'email' => [
    //         'enabled'       => false,           // Set to true to enable email notifications
    //         'to'            => 'admin@example.com',  // Recipient email address
    //         'from'          => 'backup@example.com', // Sender email address
    //         'subject'       => 'Backup Report',      // Email subject prefix
    //         'always_notify' => false,           // Set to true to send emails even on success
    //         
    //         // SMTP configuration (optional, only needed if PHP mail() defaults don't work)
    //         'smtp' => [
    //             'host'     => 'smtp.example.com',    // SMTP server address
    //             'port'     => 587,                   // SMTP port (usually 25, 465, or 587)
    //             'username' => 'smtp-user',           // SMTP username if authentication is required
    //             'password' => 'smtp-password',       // SMTP password if authentication is required
    //             'secure'   => 'tls',                 // Connection security: 'ssl', 'tls', or empty for none
    //         ],
    //     ],
    // ],
];