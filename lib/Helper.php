<?php

namespace lib;

class Helper
{
    /**
     * Print a message to the standard output.
     *
     * @param $messsage
     */
    public static function echo($messsage)
    {
        $messsage = is_scalar($messsage) ?  $messsage : print_r($messsage, true);

        echo sprintf("%s\n", $messsage);
    }

    /**
     * Create a directory, if it does't exists.
     *
     * @param string $dir
     * @param int    $mode
     *
     * @return bool
     */
    public static function createDirIfNotExists(string $dir, $mode = 0700): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, $mode, true);
    }

    /**
     * Generate a filename for a backup file (contains date).
     *
     * @param string $slug
     * @param string $ending
     *
     * @return string
     */
    public static function generateFilename($slug = '', $ending = '.tar'): string
    {
        $slug = $slug ? '-'.$slug : '';

        return date('Ymd-His').$slug.$ending;
    }

    /**
     * Compress file with gzip.
     *
     * @param string $file
     *
     * @return string|null
     */
    public static function gzip(string $file)
    {
        return shell_exec("gzip -9 $file");
    }

    /**
     * Delete a file, if it exists.
     *
     * @param string $file
     *
     * @return bool
     */
    public static function deleteIfExists(string $file): bool
    {
        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Delete compressed and uncompressed backup file.
     *
     * @param $file
     */
    public static function deleteExistingBackup($file)
    {
        static::deleteIfExists($file);
        static::deleteIfExists("$file.gz");
    }
}
