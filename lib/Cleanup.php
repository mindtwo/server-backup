<?php

namespace lib;

class Cleanup
{
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Days to keep daily backups
     *
     * @var int
     */
    protected $keep_daily_backups = 30;

    /**
     * Months to keep monthly backups
     *
     * @var int
     */
    protected $keep_monthly_backups = 12;

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

        if ($this->config['keep_daily_backups']) {
            $this->keep_daily_backups = $this->config['keep_daily_backups'];
        }

        if ($this->config['keep_monthly_backups']) {
            $this->keep_monthly_backups = $this->config['keep_monthly_backups'];
        }
    }

    /**
     * Run the cleanup proccess.
     */
    public function run()
    {
        foreach ($this->getDirs() as $dir) {
            $this->cleanDir($dir);
        }
    }

    /**
     * Get all backup directories.
     *
     * @return array
     */
    protected function getDirs(): array
    {
        $dirs = array_merge(
            array_column($this->config['filesystems'], 'destination'),
            array_column($this->config['databases'], 'destination')
        );

        $dirs = array_map(function ($dir) {
            return $dir = realpath($dir);
        }, $dirs);

        return $dirs;
    }

    protected function cleanDir($dir): array
    {
        Helper::echo('Cleaning up ' . $dir);

        $files = array_diff(scandir($dir), array('..', '.'));

        foreach ($files as $file) {
            $this->deleteOrphanedDailyBackups($dir.'/'.$file);
            $this->deleteOrphanedMonthlyBackups($dir.'/'.$file);
        }

        return $result ?? [];
    }

    protected function deleteOrphanedDailyBackups($file) {
        if (!file_exists($file)) {
            return false;
        }

        $lastchange = filemtime($file);

        if ($lastchange + ($this->keep_daily_backups * 24 * 60 * 60) > time()) {
            return false;
        }

        if (!preg_match('/.*\d{6}(?!01)\d{2}.*.tar.gz$/', $file)) {
            return false;
        }

        return unlink($file);
    }

    protected function deleteOrphanedMonthlyBackups($file)
    {
        if (!file_exists($file)) {
            return false;
        }

        $lastchange = filemtime($file);

        if ($lastchange + ($this->keep_monthly_backups * 31 * 24 * 60 * 60) > time()) {
            return false;
        }

        return unlink($file);
    }
}
