<?php

namespace lib;

class DatabaseBackup
{
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Destination directory.
     *
     * @var string
     */
    protected $destination_dir;

    /**
     * @var string
     */
    protected $archive_filename;

    /**
     * DatabaseBackup constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->destination_dir = $this->getDestinationDir();
    }

    /**
     * Run the backup proccess.
     */
    public function run()
    {
        Helper::echo("\nStarting database backup for ".$this->getSlug());
        $this->createSqlDump();
    }

    /**
     * Get the absolut path of the destination directory.
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getDestinationDir(): string
    {
        if (empty($this->config['destination'])) {
            throw new \Exception('Destination directory not found '.$this->config['destination']);
        }

        Helper::createDirIfNotExists($this->config['destination']);

        return realpath($this->config['destination']);
    }

    /**
     * Get the slug.
     *
     * @return string
     */
    protected function getSlug(): string
    {
        return $this->config['slug'] ?? '';
    }

    /**
     * Get the database host.
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getHost(): string
    {
        if (empty($this->config['db_host'])) {
            throw new \Exception('Database host not configured');
        }

        return $this->config['db_host'];
    }

    /**
     * Get the database user.
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getUser(): string
    {
        if (empty($this->config['db_user'])) {
            throw new \Exception('Database user not configured');
        }

        return $this->config['db_user'];
    }

    /**
     * Get the database password.
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getPassword(): string
    {
        return $this->config['db_password'] ?? '';
    }

    protected function getName(): string
    {
        if (empty($this->config['db_name'])) {
            throw new \Exception('Database name not configured');
        }

        return $this->config['db_name'];
    }

    protected function getFile()
    {
        if(empty($this->archive_filename)) {
            $this->archive_filename = sprintf('%s/%s',
                $this->destination_dir,
                Helper::generateFilename($this->getSlug(), '.sql')
            );
        }

        return $this->archive_filename;
    }

    protected function generateDumpCommand(): string
    {
        return sprintf('mysqldump -h %s -u%s -p%s %s > %s',
            $this->getHost(),
            $this->getUser(),
            $this->getPassword(),
            $this->getName(),
            $this->getFile()
        );
    }

    protected function createSqlDump()
    {
        Helper::deleteExistingBackup($this->getFile());

        $result = shell_exec($this->generateDumpCommand());
        Helper::gzip($this->getFile());

        Helper::echo('SQL dump created for '.$this->getSlug());
    }
}
