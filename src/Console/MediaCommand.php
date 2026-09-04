<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Bladewright\Media\MediaLibrary;
use Bladewright\Media\MediaUsage;

/**
 * Media: list, search, upload, delete.
 *
 * One of the core commands (the owner's table, 2026-09-02): **one noun, the
 * verbs as options, everything addressed by name.** Storage is the source of
 * truth for media, so this does what the screen does, down the same road
 * (`MediaLibrary`). There is no move: a path identifies the contents, and
 * pages hold that path.
 */
class MediaCommand extends Command
{
    protected $signature = 'bladewright:media
        {--search= : Show only what matches — part of a file name, or an exact path}
        {--upload= : Where it goes: folder and file name (brochures/spring.pdf)}
        {--from= : The local file to upload}
        {--delete= : Path of the file to delete (asks first)}';

    protected $description = 'List, search, upload and delete media';

    public function handle(MediaLibrary $media): int
    {
        if (($destination = $this->option('upload')) !== null) {
            return $this->upload($media, (string) $destination, (string) ($this->option('from') ?? ''));
        }

        if (($path = $this->option('delete')) !== null) {
            return $this->delete($media, (string) $path);
        }

        return $this->show($media, (string) ($this->option('search') ?? ''));
    }

    /**
     * Put one file in.
     *
     * `--upload` says where it lands (folder and file name); `--from` says
     * which local file to take. **Identical contents return the file that is
     * already there** — the library's own rule, unchanged.
     */
    private function upload(MediaLibrary $media, string $destination, string $from): int
    {
        if ($from === '') {
            $this->components->error('Say which file to take with --from="path to the file".');

            return self::FAILURE;
        }

        if (! is_file($from)) {
            $this->components->error("[{$from}] is not a file.");

            return self::FAILURE;
        }

        $folder = str_contains($destination, '/') ? dirname($destination) : '';
        $name = basename($destination);

        if ($name === '') {
            $this->components->error('Say where it goes with --upload="folder/name.png".');

            return self::FAILURE;
        }

        try {
            // **What a running server hands us is an ordinary file.**
            // test: true allows one that did not arrive as an upload.
            $stored = $media->store(new UploadedFile($from, $name, null, null, true), $folder);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$stored->name} → {$stored->path}");

        return self::SUCCESS;
    }

    /**
     * Delete one file. **Warned, and asked.**
     *
     * The one thing worth saying before the question is what the pages that
     * use it will lose: media is not in the database, so there is no history
     * to bring it back.
     */
    private function delete(MediaLibrary $media, string $path): int
    {
        if ($media->find($path) === null) {
            $this->components->error("[{$path}] is not in the library.");

            return self::FAILURE;
        }

        $used = app(MediaUsage::class)->using($path);

        $this->components->warn($used->isEmpty()
            ? 'It cannot be undone.'
            : "Used in {$used->count()} place(s). Deleting it removes the file from all of them, and it cannot be undone.");

        if (! $this->components->confirm("Delete [{$path}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        $media->delete($path);
        $this->components->info("Deleted [{$path}].");

        return self::SUCCESS;
    }

    /**
     * The list, whole or narrowed.
     *
     * A search matches **part of a file name, or a path exactly** — a path
     * has slashes in it, which is how the two are told apart.
     */
    private function show(MediaLibrary $media, string $query): int
    {
        $files = $media->everything();

        if ($query !== '') {
            $files = str_contains($query, '/')
                ? $files->filter(fn ($file) => $file->path === $query)->values()
                : $files->filter(fn ($file) => mb_stripos($file->name, $query) !== false)->values();
        }

        if ($files->isEmpty()) {
            $this->components->info($query === '' ? 'The library is empty.' : 'Nothing matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'size', 'path'],
            $files->map(fn ($file) => [$file->name, $file->sizeLabel(), $file->path])->all(),
        );

        return self::SUCCESS;
    }
}
