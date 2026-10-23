<?php

declare(strict_types=1);

namespace App\Entity\Traits;

/**
 * Locations of an uploaded file. The folder is stored relative to public/ ("uploads/blog/images"), so the
 * database works on any server. Older records hold an absolute server path
 * ("/var/www/<project>/public/uploads/blog/images"); for those the URL is whatever follows the last "/public/".
 */
trait UploadedFileTrait
{
    /** Folder on disk */
    public function getFolder(string $publicDir): string
    {
        $folder = rtrim(str_replace('\\', '/', $this->path), '/');

        return str_starts_with($folder, '/') ? $folder : rtrim($publicDir, '/') . '/' . $folder;
    }

    /** File on disk */
    public function getFilePath(string $publicDir): string
    {
        return $this->getFolder($publicDir) . '/' . $this->filename;
    }

    private static function webPath(string $folder, string $filename): string
    {
        $folder = rtrim(str_replace('\\', '/', $folder), '/');
        $position = strrpos($folder . '/', '/public/');
        if ($position !== false) {
            $folder = substr($folder, $position + strlen('/public'));
        }

        return '/' . ltrim($folder, '/') . '/' . $filename;
    }
}
