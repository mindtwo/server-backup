<?php

namespace lib;

use Exception;

class FilesystemBackup
{
    const ARCHIVER = 'tar';

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Project base directory.
     *
     * @var false|string
     */
    protected $base_dir;

    /**
     * Source directory.
     *
     * @var string
     */
    protected $source_dir;

    /**
     * Destination.
     *
     * @var string
     */
    protected $destination_dir;

    /**
     * @var string
     */
    protected $archive_filename;

    /**
     * FilesystemBackup constructor.
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->base_dir = realpath('./');
        $this->source_dir = $this->getSourceDir();
        $this->destination_dir = $this->getDestinationDir();
    }

    public function run()
    {
        Helper::echo("\nStarting file backup for ".$this->getSlug());
        $archive = $this->createArchive();
    }

    protected function getSourceDir(): string
    {
        if (empty($this->config['source']) || ! is_dir($this->config['source'])) {
            throw new \Exception('Source directory not found');
        }

        return realpath($this->config['source']);
    }

    protected function getRelativeSourceDir(): string
    {
        $parts = explode('/', $this->source_dir);

        return array_pop($parts);
    }

    protected function getSourceWorkingDir(): string
    {
        $parts = explode('/', $this->source_dir);
        array_pop($parts);

        return implode('/', $parts);
    }

    protected function getDestinationDir(): string
    {
        if (empty($this->config['destination'])) {
            throw new \Exception('Destination directory not found '.$this->config['destination']);
        }

        Helper::createDirIfNotExists($this->config['destination']);

        return realpath($this->config['destination']);
    }

    protected function getExcludesAsParams(): string
    {
        $excludes = $this->config['exclude'] ?? [];
        $prefix = ' --exclude=';

        return ! empty($excludes)
            ? $prefix.implode($prefix, $excludes)
            : '';
    }

    protected function getArchiveFile(): string
    {
        if(empty($this->archive_filename)) {
            $this->archive_filename = sprintf('%s/%s',
                $this->destination_dir,
                Helper::generateFilename($this->getSlug())
            );

        }

        return $this->archive_filename;
    }

    protected function getSlug(): string
    {
        return $this->config['slug'] ?? '';
    }

    /**
     * Generate archive command.
     *
     * @return string
     */
    protected function generateArchiveCommand(): string
    {
        return sprintf('%s -cv %s -f %s %s  ',
            self::ARCHIVER,
            $this->getExcludesAsParams(),
            $this->getArchiveFile(),
            $this->getRelativeSourceDir()
        );
    }

    /**
     * Create archive.
     *
     * @throws Exception
     */
    protected function createArchive()
    {
        Helper::deleteExistingBackup($this->getArchiveFile());

        chdir($this->getSourceWorkingDir());
        $result = shell_exec($this->generateArchiveCommand());
        chdir($this->base_dir);

        if (! empty($result)) {
            //throw new Exception('Error while archiving: '.$result);
        }

        Helper::gzip($this->getArchiveFile());
    }
}
