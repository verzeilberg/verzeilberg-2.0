<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A file or folder in the uploads folder, as shown by the media manager (see MediaLibrary).
 */
final class MediaEntry
{
    public function __construct(
        public readonly string $name,
        /** Relative to the uploads folder, e.g. "blog/images/photo.jpg" */
        public readonly string $path,
        public readonly bool $isFolder,
        /** In bytes; for a folder the total of all files in it */
        public readonly int $size,
        public readonly \DateTimeImmutable $modifiedAt,
        public readonly ?string $url,
        public readonly bool $isImage,
        /** What uses the file ("Blog: Summer camp"), null when nothing does */
        public readonly ?string $usedBy,
        /** Number of files in a folder (all levels) */
        public readonly ?int $fileCount,
    ) {
    }

    /** The folder the entry is in ("" for the top level) */
    public function getFolder(): string
    {
        $position = strrpos($this->path, '/');

        return $position === false ? '' : substr($this->path, 0, $position);
    }
}
