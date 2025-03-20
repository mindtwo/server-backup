[![mindtwo GmbH](https://www.mindtwo.de/downloads/doodles/github/repository-header.png)](https://www.mindtwo.de/)

# Server Backup

A professional PHP backup system for creating and managing backups of your files and databases.

## Features

- Filesystem backups with tar and gzip compression
- MySQL database backups with customizable options
- Flexible backup rotation policy (daily and monthly backups)
- Automated cleanup of old backups
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
3. Copy `config.example.php` to `config.php`
4. Edit `config.php` with your backup settings
5. Create a storage directory: `mkdir -p storage`
6. Test the cleanup functionality (optional): `./test-cleanup`
7. Add a cronjob for automatic backups
8. Run the backup script: `./backup.php`

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

## Automation

To run backups automatically, add the script to your crontab:

```
# Run backups daily at 2:00 AM
0 2 * * * /path/to/backup.php
```

## Testing Cleanup Functionality

The `test-cleanup` command allows you to verify that the backup cleanup system works correctly
without waiting for real backups to expire. This is especially useful when first setting up the backup system.

When run, it creates test backup files with various timestamps:
- Some within the retention period (should be kept)
- Some outside the retention period (should be deleted)

### Usage

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

### Test Process

1. The test creates fake backup files in all configured backup directories
2. Files are created with timestamps ranging from recent to old
3. Both daily backups and monthly backups (first day of month) are created
4. Running cleanup will delete only the files outside your retention policy
5. You can verify that recent backups are kept while old ones are deleted

This allows you to validate your retention settings without waiting for real backups to age.

## Security Considerations

- The backup script should be run as a user with appropriate filesystem permissions
- Database credentials are stored in the config file, so ensure it has restricted permissions: `chmod 600 config.php`
- Consider storing backups in a location not accessible via the web server
- For secure offsite backups, consider setting up automated transfers to a remote server

[![Back to the top](https://www.mindtwo.de/downloads/doodles/github/repository-footer.png)](#)