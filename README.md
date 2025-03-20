[![mindtwo GmbH](https://www.mindtwo.de/downloads/doodles/github/repository-header.png)](https://www.mindtwo.de/)

# Server Backup

A professional PHP backup system for creating and managing backups of your files and databases.

## Features

- Filesystem backups with tar and gzip compression
- MySQL database backups with customizable options
- Flexible backup rotation policy (daily and monthly backups)
- Automated cleanup of old backups
- Email notifications for backup status
- Configurable file exclusions
- Detailed error reporting
- Secure command execution
- PHP 8.3 compatible

## Server Requirements

This project has a few system requirements:

- PHP >= 8.0 (optimized for PHP 8.3)
- Linux/Unix operating system
- MySQL client tools (for database backups)
- tar utility (for filesystem backups)
- gzip utility (for compression)

## How To Install

1. Clone this repository to a private folder on your server
2. Make scripts executable: `chmod +x backup.php backup test-cleanup`
3. Copy `config.example.php` to `config.php` and set secure permissions: `chmod 600 config.php`
4. Edit `config.php` with your backup settings (see Configuration section below)
5. Run the backup script: `./backup.php`
6. Set up a cronjob for automatic backups (see Automation section)

## Configuration

The `config.php` file contains all settings for your backups. Here's an example:

```php
return [
    // Retention policies
    'keep_daily_backups'   => 30,  // Days to keep daily backups
    'keep_monthly_backups' => 12,  // Months to keep monthly backups
    
    // Filesystem backups
    'filesystems' => [
        [
            'slug'        => 'website',
            'source'      => '/var/www/mysite',
            'destination' => 'storage/website',
            'exclude'     => [
                'var/cache/*',
                'var/log/*',
                '.git',
                'node_modules',
            ],
        ],
    ],
    
    // Database backups
    'databases' => [
        [
            'slug'        => 'mydb',
            'destination' => 'storage/databases',
            'db_host'     => 'localhost',
            'db_user'     => 'dbuser',
            'db_password' => 'dbpassword',
            'db_name'     => 'mydatabase',
        ],
    ],
    
    // Notification settings
    'notifications' => [
        'email' => [
            'enabled'       => false,           // Set to true to enable email notifications
            'to'            => 'admin@example.com',  // Recipient email address
            'from'          => 'backup@example.com', // Sender email address
            'subject'       => 'Backup Report',      // Email subject prefix
            'always_notify' => false,           // Set to true to send emails even on success
            
            // SMTP configuration (optional, only needed if PHP mail() defaults don't work)
            'smtp' => [
                'host'     => 'smtp.example.com',    // SMTP server address
                'port'     => 587,                   // SMTP port (usually 25, 465, or 587)
                'username' => 'smtp-user',           // SMTP username if authentication is required
                'password' => 'smtp-password',       // SMTP password if authentication is required
                'secure'   => 'tls',                 // Connection security: 'ssl', 'tls', or empty for none
            ],
        ],
    ],
];
```

### Configuration Options

#### Global Options

- `keep_daily_backups`: Number of days to keep daily backups (default: 30)
- `keep_monthly_backups`: Number of months to keep monthly backups (default: 12)

#### Filesystem Backup Options

- `slug`: Identifier for the backup (used in filenames)
- `source`: Directory to back up (absolute or relative path)
- `destination`: Where to store backups (absolute or relative path)
- `exclude`: Array of patterns to exclude (using tar pattern format)
- `verbose`: Whether to show detailed output (default: false)

#### Database Backup Options

- `slug`: Identifier for the backup (used in filenames)
- `destination`: Where to store backups (absolute or relative path)
- `db_host`: Database host address
- `db_user`: Database username
- `db_password`: Database password
- `db_name`: Database name
- `tables`: Optional list of specific tables to back up

#### Notification Options

- `enabled`: Set to true to enable email notifications
- `to`: Recipient email address
- `from`: Sender email address
- `subject`: Email subject prefix
- `always_notify`: Set to true to send emails even on successful backups (default: only sends on failures)
- `smtp`: SMTP configuration (optional, only needed if PHP mail() defaults don't work)

## Automation

To run backups automatically, add the script to your crontab:

```
# Run backups daily at 2:00 AM
0 2 * * * /path/to/backup.php
```

## Security Best Practices

- Run the backup script as a user with appropriate filesystem permissions
- Store backups in a location not accessible via the web server
- For secure offsite backups, consider setting up automated transfers to a remote server


## Testing Cleanup Functionality

The `test-cleanup` command allows you to verify that the backup cleanup system works correctly
without waiting for real backups to expire.

```bash
# Display help information
./test-cleanup --help

# Create test files and run cleanup (default)
./test-cleanup

# Only create test files
./test-cleanup --run-test

# Only run cleanup process
./test-cleanup --run-cleanup
```

When run, it creates test backup files with various timestamps and validates your retention settings
without waiting for real backups to age.

[![Back to the top](https://www.mindtwo.de/downloads/doodles/github/repository-footer.png)](#)