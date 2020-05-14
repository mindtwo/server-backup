<?php

return [
    'keep_daily_backups'   => 60, // Days to keep daily backups
    'keep_monthly_backups' => 12, // Months to keep monthly backups
    'filesystems' => [
        [
            'slug'        => 'production', // Used within filenames and as identifier in logs
            'source'      => '', // Relative or absolute path to the source directory
            'destination' => '.storage/production', // Relative or absolute path to the backup directory
            'exclude'     => [  // Any allowed pattern for tar. Pathes relative the source directory is a good idea.
                'releases/*',
                'shared/repository',
            ]
        ]
    ],
    'databases' => [
        [
            'slug'        => 'production', // Used within filenames and as identifier in logs
            'destination' => '.storage/production/databases', // Relative or absolute path to the source directory
            'db_host'     => 'localhost',
            'db_user'     => '',
            'db_password' => '',
            'db_name'     => '',
        ]
    ]
];
