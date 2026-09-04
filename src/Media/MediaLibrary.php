<?php

namespace Bladewright\Media;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Putting uploaded files in and taking them out.
 *
 * **Storage is the source of truth; the database keeps no copy.** A copy
 * always drifts, and then something has to detect the drift (we had started
 * building exactly that).
 *
 * Files live at
 *
 *   bw/<first 2 of the hash>/<next 2>/<hash>/<original file name>
 *
 * The hash means **identical contents land in the same place** (no
 * duplicates, and nothing can be overwritten), and **the path carries the
 * original file name** (so no database row is needed).
 *
 * Where they physically live — local, NAS, S3, R2 — is Laravel's disk
 * abstraction's business, and this class knows nothing about it.
 */
class MediaLibrary
{
    /** Everything lives under here, so sharing a disk mixes nothing up. */
    private const ROOT = 'bw';

    public function __construct(private readonly Config $config) {}

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return $this->config->get('bladewright.media.disk', 'bladewright');
    }

    /**
     * Store an uploaded file.
     *
     * **Identical contents return the one that is already there.**
     * **An existing file is never overwritten**, because older revisions
     * refer to it.
     */
    public function store(UploadedFile $file, string $folder = ''): MediaFile
    {
        $this->assertAllowed($file);

        $hash = hash_file('sha256', $file->getRealPath());
        $shelf = $this->shelfFor($hash, $folder);

        // **Same contents, different name, still one file.** Anything on the
        // shelf (the hash folder) is the file for those contents.
        $existing = $this->disk()->files($shelf);

        if ($existing !== []) {
            return $this->fileAt($existing[0]);
        }

        $path = $shelf.'/'.$this->nameFor($hash, $file);

        $this->disk()->put($path, file_get_contents($file->getRealPath()));

        return $this->fileAt($path, $file->getMimeType());
    }

    /**
     * What is in that folder, newest first.
     *
     * **Nested folders stay out.** A shelf (a hash folder) is told from a
     * folder someone made by its shape: two, two, then sixty-four characters.
     *
     * @return Collection<int, MediaFile>
     */
    public function all(string $folder = ''): Collection
    {
        $base = $this->folderPath($folder);

        return collect($this->disk()->allFiles($base))
            ->reject(fn (string $path) => str_starts_with(basename($path), '.'))
            ->filter(fn (string $path) => $this->isShelved($path, $base))
            ->map(fn (string $path) => $this->fileAt($path))
            ->sortByDesc(fn (MediaFile $file) => $file->modifiedAt)
            ->values();
    }

    /**
     * Every file in the library, whichever folder it sits in.
     *
     * `all()` answers for one folder because that is how the screen walks;
     * the command lists the lot, so **the whole shelf-or-not rule stays in
     * this class** rather than being copied out to a caller.
     *
     * @return Collection<int, MediaFile>
     */
    public function everything(): Collection
    {
        return collect($this->disk()->allFiles(self::ROOT))
            ->reject(fn (string $path) => str_starts_with(basename($path), '.'))
            ->filter(function (string $path) {
                $parts = explode('/', $path);
                $count = count($parts);

                // The last four segments are aa/bb/<hash>/<name>, wherever
                // the shelf stands.
                return $count >= 5
                    && preg_match('/^[0-9a-f]{64}$/', $parts[$count - 2]) === 1
                    && preg_match('/^[0-9a-f]{2}$/', $parts[$count - 3]) === 1
                    && preg_match('/^[0-9a-f]{2}$/', $parts[$count - 4]) === 1;
            })
            ->map(fn (string $path) => $this->fileAt($path))
            ->sortByDesc(fn (MediaFile $file) => $file->modifiedAt)
            ->values();
    }

    /**
     * The folders directly under that one.
     *
     * @return array<int, string>
     */
    public function folders(string $folder = ''): array
    {
        $base = $this->folderPath($folder);

        $names = collect($this->disk()->directories($base))
            ->map(fn (string $path) => basename($path))
            // A shelf (the content hash) is not a folder anyone made.
            ->reject(fn (string $name) => preg_match('/^[0-9a-f]{2}$/', $name) === 1)
            ->sort()
            ->values()
            ->all();

        return $names;
    }

    /** Make a folder. **The name is kept as it is** (non-latin letters included). */
    public function makeFolder(string $folder, string $name): string
    {
        $name = $this->cleanSegment($name);

        if ($name === '') {
            throw new RuntimeException(__('Give the folder a name.'));
        }

        $path = $this->folderPath(trim($folder.'/'.$name, '/'));

        $this->disk()->makeDirectory($path);

        return trim($folder.'/'.$name, '/');
    }

    /**
     * Remove a folder. **Only when it is empty.**
     *
     * Taking the contents with it would quietly remove the images from the
     * pages that refer to them.
     */
    public function deleteFolder(string $folder): void
    {
        if ($folder === '') {
            return;
        }

        $path = $this->folderPath($folder);

        if ($this->all($folder)->isNotEmpty() || $this->folders($folder) !== []) {
            throw new RuntimeException(__('There is still something in it. Empty it first.'));
        }

        $this->disk()->deleteDirectory($path);
    }

    public function find(string $path): ?MediaFile
    {
        return $this->owns($path) && $this->disk()->exists($path) ? $this->fileAt($path) : null;
    }

    /**
     * Remove a file.
     *
     * **An older revision that refers to it loses its image.** The screen has
     * to say so; this method removes it without a word.
     */
    public function delete(string $path): void
    {
        if ($this->owns($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * What is referenced but gone.
     *
     * **Media is the one thing the database cannot restore.** When a deploy
     * swaps storage, finding out early is the whole value.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function missing(array $paths): array
    {
        return array_values(array_filter(
            array_unique($paths),
            fn (string $path) => $this->owns($path) && ! $this->disk()->exists($path),
        ));
    }

    /*
     * **There is no move.**
     *
     * A path identifies the contents, and pages hold that path. Moving one
     * would quietly remove the image from the pages that refer to it. The
     * folder is chosen at upload time.
     */

    /** Can we write where the files go? A read-only environment shows up here. */
    public function isWritable(): bool
    {
        $probe = self::ROOT.'/.write-test';

        try {
            $this->disk()->put($probe, 'ok');
            $ok = $this->disk()->get($probe) === 'ok';
            $this->disk()->delete($probe);

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The size we can really accept.
     *
     * **PHP's own limit can be lower than ours.** PHP defaults to
     * upload_max_filesize=2M, and anything larger is dropped with the whole
     * request: nothing reaches the application and nothing appears on screen.
     * The lower of the two is the real limit.
     */
    public function maxBytes(): int
    {
        $limits = [
            (int) $this->config->get('bladewright.media.max_size', 10 * 1024 * 1024),
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ];

        return min(array_filter($limits, fn (int $bytes) => $bytes > 0));
    }

    /** Is it somewhere we put it? We neither delete nor serve files outside. */
    public function owns(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '..')
            && str_starts_with($path, self::ROOT.'/');
    }

    private function fileAt(string $path, ?string $mime = null): MediaFile
    {
        $disk = $this->disk();

        return new MediaFile(
            disk: $this->diskName(),
            path: $path,
            name: basename($path),
            mime: $mime ?: (rescue(fn () => $disk->mimeType($path), false) ?: 'application/octet-stream'),
            size: (int) rescue(fn () => $disk->size($path), 0),
            modifiedAt: (int) rescue(fn () => $disk->lastModified($path), 0),
        );
    }

    /**
     * The shelf is chosen by a fingerprint of the contents.
     *
     * Identical contents land on the same shelf, and **different contents
     * never share one**, so nothing can be overwritten. Given a folder, the
     * shelf is made underneath it.
     */
    private function shelfFor(string $hash, string $folder = ''): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            $this->folderPath($folder),
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
        );
    }

    /** Where a folder actually lives, such as `bw/brochures`. */
    public function folderPath(string $folder): string
    {
        $folder = trim($folder, '/');

        if ($folder === '') {
            return self::ROOT;
        }

        $parts = array_filter(array_map(fn (string $part) => $this->cleanSegment($part), explode('/', $folder)));

        return self::ROOT.'/'.implode('/', $parts);
    }

    /** Is the file on a shelf directly under that folder? (Nested contents stay out.) */
    private function isShelved(string $path, string $base): bool
    {
        $rest = explode('/', trim(substr($path, strlen($base)), '/'));

        return count($rest) === 4 && preg_match('/^[0-9a-f]{64}$/', $rest[2]) === 1;
    }

    /** Drop what a folder name cannot hold. **Non-latin letters stay.** */
    private function cleanSegment(string $name): string
    {
        $name = preg_replace('#[/\\\\:*?"<>|\x00-\x1f]#u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return ltrim($name, '.');
    }

    /**
     * The file's name on the shelf. **The original name is kept**, which is
     * why no database row is needed.
     *
     * **Non-latin names are not stripped.** Str::slug turns a name written in
     * Japanese into nothing at all, which defeats the whole point of keeping
     * it (we hit this). Only what is dangerous in a path, and control
     * characters, are removed.
     */
    private function nameFor(string $hash, UploadedFile $file): string
    {
        $original = $file->getClientOriginalName() ?: 'file';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        $name = pathinfo($original, PATHINFO_FILENAME);
        $name = preg_replace('#[/\\\\:*?"<>|\x00-\x1f]#u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = ltrim($name, '.');

        if ($name === '') {
            $name = substr($hash, 0, 8);
        }

        // A very long name cannot be stored on some disks.
        return mb_strimwidth($name, 0, 80, '').'.'.$extension;
    }

    private function assertAllowed(UploadedFile $file): void
    {
        $max = $this->maxBytes();

        if ($file->getSize() > $max) {
            throw new RuntimeException(__('That file is too large (the limit is :max MB).', ['max' => number_format($max / 1024 / 1024, 1)]));
        }

        $allowed = (array) $this->config->get('bladewright.media.mimes', []);

        if ($allowed !== [] && ! in_array($file->getMimeType(), $allowed, true)) {
            throw new RuntimeException(__('That kind of file is not accepted (:type).', ['type' => $file->getMimeType()]));
        }
    }

    /** Turn php.ini's "2M" style into bytes. */
    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '') {
            return 0;
        }

        return match (strtolower(substr($value, -1))) {
            'g' => (int) $value * 1024 * 1024 * 1024,
            'm' => (int) $value * 1024 * 1024,
            'k' => (int) $value * 1024,
            default => (int) $value,
        };
    }
}
