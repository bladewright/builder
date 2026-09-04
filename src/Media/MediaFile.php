<?php

namespace Bladewright\Media;

use Illuminate\Support\Facades\Storage;

/**
 * One file on the disk.
 *
 * **Not a database row.** Storage is the source of truth and this is only a
 * value read from it. The path carries a hash of the contents, so the path
 * is the identifier.
 */
class MediaFile
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $name,
        public readonly string $mime,
        public readonly int $size,
        public readonly ?int $modifiedAt = null,
    ) {}

    /**
     * The URL that goes on a published page.
     *
     * **When the disk has a URL of its own, use it.** Routing through us puts
     * our path on the customer's page and wakes PHP for every image.
     */
    public function url(): string
    {
        if (config("filesystems.disks.{$this->disk}.url")) {
            return Storage::disk($this->disk)->url($this->path);
        }

        // **Never bake in a hostname.** Whoever saved it would leave their
        // own environment (localhost, staging) on the page, and production
        // could not read it.
        return route('bladewright.media', ['path' => $this->path], absolute: false);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime, 'audio/');
    }

    /** Is this file the kind asked for? '' asks for anything. */
    public function isKind(string $kind): bool
    {
        return match ($kind) {
            'image' => $this->isImage(),
            'video' => $this->isVideo(),
            'audio' => $this->isAudio(),
            default => true,
        };
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /**
     * A size a person can read.
     *
     * **Never raw bytes.** "1048576" tells nobody whether that is large or
     * small.
     */
    public function sizeLabel(): string
    {
        if ($this->size < 1024) {
            return $this->size.' B';
        }

        if ($this->size < 1024 * 1024) {
            return number_format($this->size / 1024, 1).' KB';
        }

        return number_format($this->size / 1024 / 1024, 1).' MB';
    }

    /** The extension, for showing "PDF" and the like in a list. */
    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'file');
    }
}
