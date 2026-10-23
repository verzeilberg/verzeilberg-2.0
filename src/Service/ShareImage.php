<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The picture for link previews (WhatsApp, Facebook etc. via og:image): a small JPEG copy of an uploaded photo.
 *
 * Phone photos are often several MB, and WhatsApp drops preview images that are too big, so the copy
 * fits in 1200x630. Copies are made once and kept in public/share-images (a cache: safe to empty).
 * Without GD, or for an external image, the original is used.
 */
class ShareImage
{
    private const MAX_WIDTH = 1200;
    private const MAX_HEIGHT = 630;
    private const QUALITY = 80;
    private const FOLDER = 'share-images';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')] private string $publicDir,
    ) {}

    /**
     * @return array{url: string, width: ?int, height: ?int} url is relative to the site root
     */
    public function forImage(BlogImage $image): array
    {
        $original = ['url' => $image->getPublicPath(), 'width' => $image->getWidth(), 'height' => $image->getHeight()];

        $source = $image->getFilePath($this->publicDir);
        if (str_starts_with($image->getPath(), 'http') || !is_file($source) || !function_exists('imagecreatetruecolor')) {
            return $original;
        }

        // The file name changes when the original changes, so a replaced photo gets a new copy
        $name = sha1($source . '|' . filemtime($source)) . '.jpg';
        $target = $this->publicDir . '/' . self::FOLDER . '/' . $name;

        if (!is_file($target) && !$this->createCopy($source, $target)) {
            return $original;
        }

        $size = @getimagesize($target);

        return ['url' => '/' . self::FOLDER . '/' . $name, 'width' => $size[0] ?? null, 'height' => $size[1] ?? null];
    }

    private function createCopy(string $source, string $target): bool
    {
        $size = @getimagesize($source);
        if (!$size) {
            return false;
        }

        $image = match ($size['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            'image/gif' => @imagecreatefromgif($source),
            default => false,
        };
        if (!$image) {
            return false;
        }

        $image = $this->applyExifRotation($image, $source, $size['mime']);
        $width = imagesx($image);
        $height = imagesy($image);

        $ratio = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        // White background, so transparent PNGs don't turn black in the JPEG
        $copy = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($copy, 0, 0, imagecolorallocate($copy, 255, 255, 255));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        // Write to a temporary file first, so a half-written copy is never served
        $temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $saved = imagejpeg($copy, $temp, self::QUALITY) && rename($temp, $target);
        if (!$saved) {
            @unlink($temp);
        }

        return $saved;
    }

    /**
     * Phone photos are often stored sideways with an EXIF orientation flag; turn the pixels upright.
     */
    private function applyExifRotation(\GdImage $image, string $source, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
