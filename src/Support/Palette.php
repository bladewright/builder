<?php

namespace Bladewright\Support;

use InvalidArgumentException;
use Bladewright\Models\Setting;

/**
 * The site's colours, kept once and used by name.
 *
 * **A block carries a name, not a value.** `#3538cd` written into every block
 * means opening every block to change it; `accent`, with the value kept here,
 * is the same rule as everything else in this world — edit it once and every
 * page follows.
 *
 * An entry need not be a colour. `linear-gradient(…)` is one entry like any
 * other, and the block still only says "accent for the background" — which is
 * how somebody who wants gradients is served without the blocks knowing.
 */
class Palette
{
    /** Where it is kept. Not a Laravel config key: this is the site's own. */
    private const KEY = 'bladewright.palette';

    /**
     * What a site starts with. **Something to look at from the first
     * minute**, and every one of them is a name somebody can rewrite.
     */
    public const DEFAULTS = [
        'ink' => '#1f2429',
        'muted' => '#667085',
        'accent' => '#3538cd',
        'paper' => '#ffffff',
        'rule' => '#e4e7ec',
    ];

    /** @var array<string, string>|null */
    private ?array $entries = null;

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        try {
            $stored = Setting::query()->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            // **A colour is not worth a white screen.** Before the migrations,
            // or during a database incident, the site keeps its defaults.
            return $this->entries = self::DEFAULTS;
        }

        return $this->entries = is_array($stored) && $stored !== []
            ? array_map('strval', $stored)
            : self::DEFAULTS;
    }

    /** The value behind a name, or null when nobody has that name. */
    public function value(string $name): ?string
    {
        return $this->all()[trim($name)] ?? null;
    }

    /**
     * What a style should actually say. **A name becomes its value; anything
     * else is taken as it was written** — one-off colours are nobody's
     * business but the block's.
     */
    public function resolve(string $value): string
    {
        return $this->value($value) ?? $value;
    }

    /**
     * Keep this palette. **Names are how people refer to it**, so they read
     * like words: letters, digits and hyphens, and never empty.
     *
     * @param  array<string, string>  $entries
     * @return array<string, string>
     */
    public function save(array $entries): array
    {
        $kept = [];

        foreach ($entries as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);

            if ($name === '' && $value === '') {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9-]{0,39}$/i', $name) !== 1) {
                throw new InvalidArgumentException(__('[:name] is not a name a colour can be called. Letters, digits and hyphens.', ['name' => $name]));
            }

            if (! $this->reads($value)) {
                throw new InvalidArgumentException(__('[:value] does not read as a colour.', ['value' => $value]));
            }

            $kept[$name] = $value;
        }

        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $kept]);

        $this->entries = $kept;

        return $kept;
    }

    /**
     * **Two colours cannot share a name**, or one of them quietly wins and
     * nobody can see which. Said here, where the rest of the rule is, and
     * checked before the list is collapsed into names.
     *
     * @param  array<int, string>  $names
     */
    public function assertNamesAreUnique(array $names): void
    {
        $seen = [];

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            if (in_array($name, $seen, true)) {
                throw new InvalidArgumentException(__('[:name] is in the list twice.', ['name' => $name]));
            }

            $seen[] = $name;
        }
    }

    /**
     * Does this read as something that can be painted with?
     *
     * A hex colour, a colour word, or a function of them — `rgb(…)`,
     * `linear-gradient(…)`. **Never a semicolon**: one entry is one value,
     * not a way to write a second property into somebody's style attribute.
     */
    public function reads(string $value): bool
    {
        if ($value === '' || str_contains($value, ';') || str_contains($value, '<')) {
            return false;
        }

        return preg_match('/^[#a-z0-9\s.,()%\/-]+$/i', $value) === 1;
    }

    /** Is this one of ours, rather than a colour written out? */
    public function isName(string $value): bool
    {
        return $this->value($value) !== null;
    }

    /** Painted with more than one colour, so it is a background and nothing else. */
    public function isGradient(string $value): bool
    {
        return str_contains(strtolower($this->resolve($value)), 'gradient(');
    }
}
