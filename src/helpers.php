<?php

declare(strict_types=1);

use Illuminate\Support\Str;

if (!\function_exists('dirsIntersect')) {
    /**
     * Check if files in one array (f.ex. directory) are also exist in a second one.
     *
     * @param string $folder
     * @param array<string> $directory
     * @param array<string> $excludedDirs
     * @return bool
     */
    function dirsIntersect(string $folder, array $directory, array $excludedDirs): bool
    {
        if ($directory) {
            $directory = array_map(function ($item) use ($folder) {
                return str_replace($folder . '/', '', $item);
            }, $directory);
        }
        return (bool) count(array_intersect($directory, $excludedDirs));
    }
}

if (!\function_exists('checkPermissions')) {
    /**
     * Check a given directory recursively if all files are writeable.
     */
    function checkPermissions(string $directory): bool
    {
        $directoryIterator = new \RecursiveDirectoryIterator($directory);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            if ($file->isFile() && !$file->isWritable()) {
                return false;
            }
        }

        return true;
    }
}

if (!\function_exists('createFolderFromFile')) {
    /*
     * Create a folder name including path from a given file.
     * Input: /tmp/my_zip_file.zip
     * Output: /tmp/my_zip_file/.
     */
    function createFolderFromFile(string $file): string
    {
        if ($file === '') {
            return '';
        }

        $pathinfo = pathinfo($file);

        return Str::finish($pathinfo['dirname'], DIRECTORY_SEPARATOR).$pathinfo['filename'];
    }
}
