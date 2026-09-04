<?php

namespace Bladewright\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

/**
 * Take the site back to the state right after installing. **The rule lives
 * here, once.**
 *
 * **`migrate:fresh` is never what this does.** By default the site's contents
 * share the customer's database, so `migrate:fresh` would take the customer's
 * own tables with them. Only the tables that begin with `bw_` are dropped,
 * and the migrations that made them are forgotten so `migrate` runs them
 * again.
 *
 * **Media is kept unless it is asked for.** It is the one thing nothing can
 * restore.
 */
class SiteReset
{
    /** Everything of ours begins with this. Nothing else is touched. */
    private const PREFIX = 'bw_';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Migrator $migrator,
        private readonly Filesystem $files,
        private readonly \Bladewright\Media\MediaLibrary $media,
        private readonly Config $config,
    ) {}

    /**
     * What would be lost, counted before anything happens.
     *
     * **Nobody agrees to a number they were never shown.**
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'pages' => $this->rows('bw_pages'),
            'layouts' => $this->rows('bw_layouts'),
            'components' => $this->rows('bw_structures'),
            'blocks' => $this->rows('bw_blocks'),
            'accounts' => $this->rows('bw_users'),
            'media' => $this->mediaCount(),
        ];
    }

    /**
     * The tables this would drop. **Ours alone.**
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        $connection = $this->connection();

        try {
            $names = array_map(
                fn (array $table) => $table['name'],
                $connection->getSchemaBuilder()->getTables(),
            );
        } catch (\Throwable) {
            return [];
        }

        $ours = array_values(array_filter(
            $names,
            fn (string $name) => str_starts_with($name, self::PREFIX),
        ));

        sort($ours);

        return $ours;
    }

    /**
     * Wipe the site: the tables go, and the migrations that made them are
     * forgotten so `migrate` runs them again.
     *
     * @return array<string, int> what was removed
     */
    public function wipe(bool $media = false): array
    {
        $removed = $this->counts();

        if (! $media) {
            $removed['media'] = 0;
        }

        $this->dropTables();
        $this->forgetMigrations();

        if ($media) {
            $this->clearMedia();
        }

        return $removed;
    }

    private function dropTables(): void
    {
        $schema = $this->connection()->getSchemaBuilder();

        // Children point at their parents, so the order matters on a
        // database that enforces it.
        $schema->withoutForeignKeyConstraints(function () use ($schema) {
            foreach ($this->tables() as $table) {
                $schema->drop($table);
            }
        });
    }

    /**
     * Forget that our migrations ever ran.
     *
     * **The record lives on the application's connection**, not necessarily
     * ours, so it is reached through Laravel's own repository rather than by
     * guessing which database the `migrations` table is in.
     */
    private function forgetMigrations(): void
    {
        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return;
        }

        $ours = collect($this->files->files(__DIR__.'/../../database/migrations'))
            ->map(fn ($file) => $file->getFilename())
            ->map(fn (string $name) => str_replace('.php', '', $name))
            ->all();

        foreach ($repository->getRan() as $ran) {
            if (in_array($ran, $ours, true)) {
                $repository->delete((object) ['migration' => $ran]);
            }
        }
    }

    /** **Only when it is asked for.** Media cannot be restored from anything. */
    private function clearMedia(): void
    {
        $disk = $this->media->disk();

        foreach ($disk->directories('bw') as $directory) {
            $disk->deleteDirectory($directory);
        }

        $disk->delete($disk->files('bw'));
    }

    private function mediaCount(): int
    {
        try {
            return $this->media->everything()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function rows(string $table): int
    {
        try {
            return $this->connection()->table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function connection(): \Illuminate\Database\Connection
    {
        return $this->database->connection($this->config->get('bladewright.database.connection'));
    }
}
