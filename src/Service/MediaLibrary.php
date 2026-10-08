<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogFile;
use App\Entity\BlogImage;
use App\Entity\BlogPost;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The files in public/uploads (blog images and files, event images) for the admin media manager:
 * browsing, searching, deleting, and checking that the files and the database records match.
 *
 * Paths given to and returned by this class are relative to the uploads folder ("blog/images/photo.jpg").
 * A file that a blog post or event still uses can't be deleted here.
 */
class MediaLibrary
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];

    /** @var array<string, string>|null relative path => what uses it ("Blog: My post"), built once per request */
    private ?array $usage = null;

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%app.uploads_dir%')] private string $uploadsDir,
        #[Autowire('%app.uploads_url%')] private string $uploadsUrl,
    ) {
        $this->uploadsDir = rtrim($uploadsDir, '/');
    }

    /**
     * The folders and files directly in a folder; folders first, then by name.
     *
     * @return list<MediaEntry>
     *
     * @throws \InvalidArgumentException for a path outside the uploads folder, or one that isn't a folder
     */
    public function listFolder(string $folder): array
    {
        $absolute = $this->resolve($folder);
        if (!is_dir($absolute)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a folder.', $folder));
        }

        $entries = [];
        foreach (new \FilesystemIterator($absolute, \FilesystemIterator::SKIP_DOTS) as $file) {
            /** @var \SplFileInfo $file */
            $entries[] = $this->entry($file);
        }

        return $this->sort($entries);
    }

    /**
     * Files and folders whose name contains the search text, in the folder and all its sub folders.
     *
     * @return list<MediaEntry>
     */
    public function search(string $needle, string $folder = ''): array
    {
        $absolute = $this->resolve($folder);
        $needle = trim($needle);
        if ($needle === '' || !is_dir($absolute)) {
            return [];
        }

        $entries = [];
        foreach ($this->walk($absolute, true) as $file) {
            if (mb_stripos($file->getFilename(), $needle) !== false) {
                $entries[] = $this->entry($file);
            }
        }

        return $this->sort($entries);
    }

    /**
     * Deletes a file, or a folder with everything in it.
     *
     * @throws \InvalidArgumentException when the path doesn't exist, is the uploads folder itself, or (contains) a file in use
     */
    public function delete(string $path): void
    {
        $absolute = $this->resolve($path);
        if ($absolute === $this->uploadsDir) {
            throw new \InvalidArgumentException('The uploads folder itself can\'t be deleted.');
        }
        if (!file_exists($absolute)) {
            throw new \InvalidArgumentException(sprintf('"%s" doesn\'t exist.', $path));
        }

        if (is_dir($absolute)) {
            $inUse = array_filter(iterator_to_array($this->walk($absolute), false), fn (\SplFileInfo $file) => $this->usageOf($this->relative($file->getPathname())) !== null);
            if ($inUse !== []) {
                throw new \InvalidArgumentException(sprintf('The folder contains %d file(s) that are still in use.', count($inUse)));
            }
            $this->removeFolder($absolute);

            return;
        }

        if ($usedBy = $this->usageOf($this->relative($absolute))) {
            throw new \InvalidArgumentException(sprintf('This file is still in use (%s).', $usedBy));
        }
        unlink($absolute);
    }

    /**
     * Server check: files in the uploads folder that no blog post or event refers to.
     *
     * @return list<MediaEntry>
     */
    public function findUnusedFiles(): array
    {
        if (!is_dir($this->uploadsDir)) {
            return [];
        }

        $entries = [];
        foreach ($this->walk($this->uploadsDir) as $file) {
            if ($this->usageOf($this->relative($file->getPathname())) === null) {
                $entries[] = $this->entry($file);
            }
        }
        usort($entries, fn (MediaEntry $a, MediaEntry $b) => strnatcasecmp($a->path, $b->path));

        return $entries;
    }

    /**
     * Database check: image and file records whose file is gone (external URLs are skipped).
     *
     * @return list<array{type: 'image'|'file', id: int, name: string, location: string, usedBy: ?string}>
     */
    public function findMissingFiles(): array
    {
        $missing = [];
        foreach ([BlogImage::class => 'image', BlogFile::class => 'file'] as $class => $type) {
            foreach ($this->em->getRepository($class)->findBy([], ['id' => 'ASC']) as $record) {
                /** @var BlogImage|BlogFile $record */
                if ($this->isExternal($record->getPath())) {
                    continue;
                }
                $relative = $this->recordPath($record);
                if ($relative !== null && is_file($this->uploadsDir . '/' . $relative)) {
                    continue;
                }
                $missing[] = [
                    'type' => $type,
                    'id' => (int) $record->getId(),
                    'name' => $record->getName() ?: $record->getFilename(),
                    'location' => $relative ?? rtrim($record->getPath(), '/') . '/' . $record->getFilename(),
                    'usedBy' => $relative !== null ? $this->usageOf($relative) : null,
                ];
            }
        }

        return $missing;
    }

    /**
     * Removes image/file records whose file is gone, and unlinks them from blog posts and events.
     * Records whose file does exist are left alone.
     *
     * @param list<string> $keys "image:12" / "file:3", as in findMissingFiles()
     *
     * @return int the number of records removed
     */
    public function removeMissingRecords(array $keys): int
    {
        $wanted = array_flip($keys);
        $removed = 0;
        foreach ($this->findMissingFiles() as $missing) {
            if (!isset($wanted[$missing['type'] . ':' . $missing['id']])) {
                continue;
            }
            $record = $this->em->find($missing['type'] === 'image' ? BlogImage::class : BlogFile::class, $missing['id']);
            if ($record === null) {
                continue;
            }
            $this->unlink($record);
            $this->em->remove($record);
            ++$removed;
        }
        $this->em->flush();
        $this->usage = null;

        return $removed;
    }

    /**
     * Deletes the given files if they are still unused (see findUnusedFiles()).
     *
     * @param list<string> $paths
     *
     * @return int the number of files deleted
     */
    public function deleteUnusedFiles(array $paths): int
    {
        $deleted = 0;
        foreach ($paths as $path) {
            try {
                $absolute = $this->resolve($path);
            } catch (\InvalidArgumentException) {
                continue;
            }
            if (is_file($absolute) && $this->usageOf($this->relative($absolute)) === null) {
                unlink($absolute);
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * The breadcrumb of a folder: ["" => "uploads", "blog" => "blog", "blog/images" => "images"].
     *
     * @return array<string, string>
     */
    public function breadcrumb(string $folder): array
    {
        $crumbs = ['' => 'uploads'];
        $path = '';
        foreach (array_filter(explode('/', $this->normalize($folder))) as $part) {
            $path = ltrim($path . '/' . $part, '/');
            $crumbs[$path] = $part;
        }

        return $crumbs;
    }

    /** "blog/images/../x" → "blog/x"; throws when it would leave the uploads folder */
    public function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \InvalidArgumentException('The path is outside the uploads folder.');
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /** Absolute path of a relative path, also checked against symlinks pointing elsewhere */
    private function resolve(string $path): string
    {
        $absolute = $this->uploadsDir . ($this->normalize($path) !== '' ? '/' . $this->normalize($path) : '');
        $real = realpath($absolute);
        $root = realpath($this->uploadsDir);
        if ($real !== false && $root !== false && $real !== $root && !str_starts_with($real, $root . '/')) {
            throw new \InvalidArgumentException('The path is outside the uploads folder.');
        }

        return $absolute;
    }

    private function relative(string $absolute): string
    {
        return ltrim(substr($absolute, strlen($this->uploadsDir)), '/');
    }

    private function entry(\SplFileInfo $file): MediaEntry
    {
        $path = $this->relative($file->getPathname());
        $isFolder = $file->isDir();

        return new MediaEntry(
            name: $file->getFilename(),
            path: $path,
            isFolder: $isFolder,
            size: $isFolder ? $this->folderSize($file->getPathname()) : (int) $file->getSize(),
            modifiedAt: (new \DateTimeImmutable())->setTimestamp((int) $file->getMTime()),
            url: $isFolder ? null : $this->uploadsUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $path))),
            isImage: !$isFolder && in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true),
            usedBy: $isFolder ? null : $this->usageOf($path),
            fileCount: $isFolder ? iterator_count($this->walk($file->getPathname())) : null,
        );
    }

    /**
     * @param list<MediaEntry> $entries
     *
     * @return list<MediaEntry>
     */
    private function sort(array $entries): array
    {
        usort($entries, fn (MediaEntry $a, MediaEntry $b) => [$b->isFolder, strtolower($a->name)] <=> [$a->isFolder, strtolower($b->name)]);

        return $entries;
    }

    /**
     * All files (and with $withFolders also the folders) below a folder.
     *
     * @return \Generator<\SplFileInfo>
     */
    private function walk(string $folder, bool $withFolders = false): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($withFolders || $file->isFile()) {
                yield $file;
            }
        }
    }

    private function folderSize(string $folder): int
    {
        $size = 0;
        foreach ($this->walk($folder) as $file) {
            $size += (int) $file->getSize();
        }

        return $size;
    }

    private function removeFolder(string $folder): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($folder);
    }

    /** What uses the file, e.g. "Blog: Summer camp", or null when nothing does */
    private function usageOf(string $relativePath): ?string
    {
        if ($this->usage === null) {
            $this->usage = [];
            foreach ($this->em->getRepository(BlogPost::class)->findAll() as $post) {
                foreach ([...$post->getImages(), ...$post->getFiles()] as $record) {
                    $this->addUsage($record, 'Blog: ' . $post->getTitle());
                }
            }
            foreach ($this->em->getRepository(Event::class)->findAll() as $event) {
                if ($image = $event->getEventImage()) {
                    $this->addUsage($image, 'Event: ' . $event->getTitle());
                }
            }
            // Records not attached to anything still own their file: the check pages handle those
            foreach ([BlogImage::class, BlogFile::class] as $class) {
                foreach ($this->em->getRepository($class)->findAll() as $record) {
                    $this->addUsage($record, 'Unattached ' . ($record instanceof BlogImage ? 'image' : 'file') . ' record #' . $record->getId());
                }
            }
        }

        return $this->usage[$relativePath] ?? null;
    }

    private function addUsage(BlogImage|BlogFile $record, string $usedBy): void
    {
        $path = $this->recordPath($record);
        if ($path !== null) {
            $this->usage[$path] ??= $usedBy;
        }
    }

    /**
     * Relative path of a record's file. Records store an absolute folder ("/var/www/…/public/uploads/blog/images"),
     * which differs per server, so everything after "uploads/" is used.
     */
    private function recordPath(BlogImage|BlogFile $record): ?string
    {
        if ($this->isExternal($record->getPath())) {
            return null;
        }
        $full = rtrim(str_replace('\\', '/', $record->getPath()), '/') . '/' . $record->getFilename();
        if (str_starts_with($full, $this->uploadsDir . '/')) {
            return $this->relative($full);
        }
        $position = strrpos($full, '/uploads/');

        return $position !== false ? substr($full, $position + strlen('/uploads/')) : null;
    }

    private function isExternal(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    /** Takes a record off the blog posts and events that use it, so it can be removed */
    private function unlink(BlogImage|BlogFile $record): void
    {
        $relation = $record instanceof BlogImage ? 'images' : 'files';
        $posts = $this->em->createQueryBuilder()
            ->select('p')->from(BlogPost::class, 'p')
            ->join('p.' . $relation, 'r')->where('r = :record')
            ->setParameter('record', $record)
            ->getQuery()->getResult();
        foreach ($posts as $post) {
            /** @var BlogPost $post */
            $record instanceof BlogImage ? $post->removeImage($record) : $post->removeFile($record);
        }

        if ($record instanceof BlogImage) {
            foreach ($this->em->getRepository(Event::class)->findBy(['eventImage' => $record]) as $event) {
                $event->setEventImage(null);
            }
        }
    }
}
