<?php

declare(strict_types=1);

namespace App\Entity\Traits;

/**
 * Web path of an uploaded file. The folder is stored as an absolute server path
 * ("/var/www/<project>/public/uploads/blog/images"), which differs per server, so the URL is
 * whatever follows the last "/public/" ("/uploads/blog/images/photo.jpg").
 */
trait UploadedFileTrait
{
    private static function webPath(string $folder, string $filename): string
    {
        $folder = rtrim(str_replace('\\', '/', $folder), '/');
        $position = strrpos($folder . '/', '/public/');
        if ($position !== false) {
            $folder = substr($folder, $position + strlen('/public'));
        }

        return $folder . '/' . $filename;
    }
}
