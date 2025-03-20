[![mindtwo GmbH](https://www.mindtwo.de/downloads/doodles/github/repository-header.png)](https://www.mindtwo.de/)

# Server Backup

A professional PHP backup system for creating and managing backups of your files and databases.

## Features

- Filesystem backups with tar and gzip compression
- MySQL database backups with customizable options
- Support for database-only or filesystem-only backups
- Multiple backup configurations for different sources
- Flexible backup rotation policy (daily and monthly backups)
- Automated cleanup of old backups
- Email notifications for backup status
- Configurable file exclusions
- Detailed logging with rotation
- Minimal console output with comprehensive log files
- Secure command execution
- PHP 8.3 compatible

## Server Requirements

This project has a few system requirements:

- **PHP >= 8.0** (optimized for PHP 8.3)
  - The script uses features like match expressions that require PHP 8.0+
  - If your server's default PHP is older, you can specify a newer version using the `php_command` setting
- Linux/Unix operating system
- MySQL client tools (for database backups)
- tar utility (for filesystem backups)
- gzip utility (for compression)

## How To Install

1. Clone this repository to a private folder on your server
2. Make scripts executable: `chmod +x backup.php backup`
3. Copy `config.example.php` to `config.php` and set secure permissions: `chmod 600 config.php`
4. Edit `config.php` with your backup settings (see Configuration section below)
5. Run the backup script: `./backup.php`
6. Set up a cronjob for automatic backups (see Automation section)

## Configuration

The `config.php` file contains all settings for your backups. Here's an example:

```php
return [
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
    
    // Retention policies
    'keep_daily_backups'   => 30,  // Days to keep daily backups
    'keep_monthly_backups' => 12,  // Months to keep monthly backups
    
    // Logging configuration
    'log_file' => 'logs/server-backup.log', // Path to log file
    'log_level' => 1, // 0 = errors only, 1 = info (default), 2 = debug
    'log_max_size' => 5 * 1024 * 1024, // Maximum log file size (5MB)
    'log_files_to_keep' => 5, // Number of rotated log files to keep
    
    // Notification settings
    'notifications' => [
        'email' => [
            'enabled'       => false,           // Set to true to enable email notifications
            'to'            => 'admin@example.com',  // Recipient email address
            'from'          => 'backup@example.com', // Sender email address
            'subject'       => 'Backup Report',      // Email subject prefix
            'always_notify' => false,           // Set to true to send emails even on success
            
            // SMTP configuration (optional)
            'smtp' => [
                'host'     => 'smtp.example.com',    // SMTP server address
                'port'     => 587,                   // SMTP port
                'username' => 'smtp-user',           // SMTP username
                'password' => 'smtp-password',       // SMTP password
                'secure'   => 'tls',                 // 'ssl', 'tls', or empty
            ],
        ],
    ],
];
```

### Configuration Options

#### Backup Types

The script supports three different backup configurations:

1. **Both filesystem and database backups** - Configure both the `filesystems` and `databases` arrays
2. **Database-only backups** - Leave the `filesystems` array empty or remove it completely
3. **Filesystem-only backups** - Leave the `databases` array empty or remove it completely

#### Filesystem Backup Options

- `slug`: Identifier for the backup (used in filenames)
- `source`: Directory to back up (absolute or relative path)
- `destination`: Where to store backups (absolute or relative path)
- `exclude`: Array of patterns to exclude (using tar pattern format)
- `verbose`: Whether to show detailed output (default: false)

You can configure multiple filesystem backups by adding additional entries to the `filesystems` array.

#### Database Backup Options

- `slug`: Identifier for the backup (used in filenames)
- `destination`: Where to store backups (absolute or relative path)
- `db_host`: Database host address
- `db_user`: Database username
- `db_password`: Database password
- `db_name`: Database name
- `tables`: Optional list of specific tables to back up (empty array = all tables)
- `db_socket`: Optional MySQL socket path
- `db_port`: Optional MySQL port
- `mysqldump_command`: Optional custom path to mysqldump
- `command_timeout`: Optional timeout in seconds for database backups
- `mysqldump_options`: Optional array of additional mysqldump options

You can configure multiple database backups by adding additional entries to the `databases` array.

#### Logging Options

- `log_file`: Path to log file (relative to the script directory or absolute)
- `log_level`: Logging detail level (0 = errors only, 1 = info, 2 = debug)
- `log_max_size`: Maximum size of log file before rotation in bytes (default: 5MB)
- `log_files_to_keep`: Number of rotated log files to keep (default: 5)

The script uses a minimal console output approach - only critical messages and a summary are shown in the console, while comprehensive details are written to the log file. This keeps the console output clean and readable.

#### PHP Configuration

- `php_command`: Specify which PHP command to use for CLI operations (default: 'php')
  - This is useful for servers with multiple PHP versions installed
  - Examples: 'php83', 'php84', '/usr/bin/php83'
  - This setting is particularly important for shared hosting environments where the default PHP may be older than required

#### Retention Options

- `keep_daily_backups`: Number of days to keep daily backups (default: 30)
- `keep_monthly_backups`: Number of months to keep monthly backups (default: 12)

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
0 2 * * * /path/to/backup
```

If you need to specify a specific PHP version for your cron job:

```
# Run backups daily at 2:00 AM with PHP 8.3
0 2 * * * /path/to/backup --php=php83
```

Alternatively, configure the PHP command in your config.php file:

```php
'php_command' => 'php83',
```

## Security Best Practices

- Run the backup script as a user with appropriate filesystem permissions
- Store backups in a location not accessible via the web server
- For secure offsite backups, consider setting up automated transfers to a remote server
- Set restrictive permissions on config.php (600) to protect database credentials


## Backup Retention and Cleanup

The backup system automatically manages file retention according to your configuration:

```php
// Retention policies
'keep_daily_backups'   => 7,   // Days to keep daily backups
'keep_monthly_backups' => 3,   // Months to keep monthly backups
```

The cleanup system follows these rules:

1. **Monthly backups**: Backups created on the first day of each month are considered monthly backups. The system keeps one backup per month for the specified number of months.

2. **Daily backups**: All other backups are considered daily backups and are kept for the specified number of days.

3. **Monthly retention**: If there's no monthly backup for a month within the monthly retention period, the system will keep the newest daily backup from that month as a representative.

The script uses accurate date calculations to determine which backups to keep, ensuring reliable retention regardless of the specific settings you choose.

> **Note**: The `backup` script supports specifying the PHP version to use, either via the `--php=` command line parameter or through the `php_command` setting in your config.php file. This is particularly useful on servers where the default PHP version is too old to support this script (requires PHP 8.0+).

[![Back to the top](https://www.mindtwo.de/downloads/doodles/github/repository-footer.png)](#)