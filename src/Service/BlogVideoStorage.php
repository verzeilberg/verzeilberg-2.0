<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogVideo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Video files of blog posts, in var/storage/blog-videos: outside public/, so there is no URL to them and only
 * BlogVideoController (which checks the login) can serve them.
 *
 * Videos are uploaded in chunks (the hosting's upload limit is far below a video's size): every chunk is appended
 * to tmp/<upload id>.part, and the last one turns that into a video with a random name.
 */
class BlogVideoStorage
{
    /** Video types every browser can play (a .mov is accepted, but only plays when it holds H.264) */
    public const MIME_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-m4v' => 'm4v'];

    public const MAX_SIZE = 4 * 1024 * 1024 * 1024;

    /** Chunk size limits: never above 8 MB, and below the server's upload and post limits */
    private const MAX_CHUNK = 8 * 1024 * 1024;
    private const MIN_CHUNK = 512 * 1024;

    private string $dir;

    public function __construct(#[Autowire('%app.blog_video_dir%')] string $dir)
    {
        $this->dir = rtrim($dir, '/');
    }

    public function path(BlogVideo $video): string
    {
        return $this->dir . '/' . $video->getFilename();
    }

    /** Bytes per chunk the browser sends, safely below upload_max_filesize and post_max_size */
    public function chunkSize(): int
    {
        $limit = min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size')));
        $size = $limit > 0 ? (int) ($limit * 0.8) : self::MAX_CHUNK;

        return max(self::MIN_CHUNK, min(self::MAX_CHUNK, $size));
    }

    public static function isValidUploadId(string $uploadId): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $uploadId) === 1;
    }

    /**
     * Adds a chunk to the upload; chunk 0 starts it over. Returns the size so far.
     *
     * @throws \RuntimeException when the chunks arrive out of order or the file gets too big
     */
    public function appendChunk(string $uploadId, int $offset, UploadedFile $chunk): int
    {
        $part = $this->partPath($uploadId);
        $this->ensureDir(dirname($part));
        if ($offset === 0) {
            $this->removeStaleParts();
            @unlink($part);
        }

        clearstatcache(true, $part);
        $current = is_file($part) ? (int) filesize($part) : 0;
        // The same chunk again (its answer got lost and the browser retried): already there
        if ($offset > 0 && $current === $offset + (int) $chunk->getSize()) {
            return $current;
        }
        if ($current !== $offset) {
            throw new \RuntimeException(sprintf('Stuk op positie %d verwacht, %d ontvangen.', $current, $offset));
        }
        if ($current + (int) $chunk->getSize() > self::MAX_SIZE) {
            @unlink($part);
            throw new \RuntimeException('De video is te groot.');
        }

        $in = fopen($chunk->getPathname(), 'rb');
        $out = fopen($part, 'ab');
        if ($in === false || $out === false || stream_copy_to_stream($in, $out) === false) {
            throw new \RuntimeException('De video kon niet worden opgeslagen.');
        }
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);

        return (int) filesize($part);
    }

    /**
     * Turns a complete upload into a stored video file.
     *
     * @return array{filename: string, mimeType: string, size: int}
     * @throws \RuntimeException when the size doesn't match or it isn't a video
     */
    public function finish(string $uploadId, int $expectedSize): array
    {
        $part = $this->partPath($uploadId);
        clearstatcache(true, $part);
        $size = is_file($part) ? (int) filesize($part) : 0;
        if ($size === 0 || $size !== $expectedSize) {
            @unlink($part);
            throw new \RuntimeException('De upload is niet compleet aangekomen. Probeer het opnieuw.');
        }

        $mimeType = MimeTypes::getDefault()->guessMimeType($part);
        if (!isset(self::MIME_TYPES[$mimeType])) {
            @unlink($part);
            throw new \RuntimeException('Dit is geen video die een browser kan afspelen (gebruik mp4 of webm).');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::MIME_TYPES[$mimeType];
        if (!rename($part, $this->dir . '/' . $filename)) {
            throw new \RuntimeException('De video kon niet worden opgeslagen.');
        }

        return ['filename' => $filename, 'mimeType' => $mimeType, 'size' => $size];
    }

    public function delete(BlogVideo $video): void
    {
        $path = $this->path($video);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function partPath(string $uploadId): string
    {
        if (!self::isValidUploadId($uploadId)) {
            throw new \RuntimeException('Ongeldige upload.');
        }

        return $this->dir . '/tmp/' . $uploadId . '.part';
    }

    /** Uploads that were never finished (closed tab, lost connection) are removed after a day */
    private function removeStaleParts(): void
    {
        foreach (glob($this->dir . '/tmp/*.part') ?: [] as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('De map voor video\'s kon niet worden aangemaakt.');
        }
    }

    /** "8M" → 8388608 */
    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
