<?php

namespace Bladewright\Support;

use InvalidArgumentException;
use Bladewright\Models\Setting;

/**
 * The site's one CSS framework, declared once for the whole site.
 *
 * **This is where bottom-up meets top-down.** A block is put to use anywhere,
 * but what its classes mean is decided by whatever stylesheet wraps the page
 * — so that decision is the site's, made at install, never a layout's. The
 * frames ask it through `@bwframework`, and the editors' previews ask the
 * same question, which is what keeps a preview honest about a class.
 */
class Framework
{
    private const KEY = 'bladewright.framework';

    /** What can be chosen, spelled the way the screens say it. */
    public const NAMES = [
        'bootstrap' => 'Bootstrap',
        'pico' => 'Pico',
        'plain' => 'Plain CSS',
    ];

    /** The one line a head carries for each. Plain CSS brings nothing to load. */
    private const LINKS = [
        'bootstrap' => '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">',
        'pico' => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.min.css">',
        'plain' => '',
    ];

    private ?string $chosen = null;

    public function get(): string
    {
        if ($this->chosen !== null) {
            return $this->chosen;
        }

        try {
            $said = (string) (Setting::query()->where('key', self::KEY)->value('value') ?? '');
        } catch (\Throwable) {
            // Before the migrations there is no declaration to read.
            $said = '';
        }

        // **Bootstrap is what every site was born with** before the
        // declaration existed, so an undeclared site reads as one.
        return $this->chosen = isset(self::NAMES[$said]) ? $said : 'bootstrap';
    }

    public function save(string $framework): void
    {
        $framework = $this->normalize($framework);

        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $framework]);

        $this->chosen = $framework;
    }

    /** "Bootstrap" and "Plain CSS" are how the owner's table writes them; be easy about it. */
    public function normalize(string $said): string
    {
        $known = match (strtolower(trim($said))) {
            'bootstrap' => 'bootstrap',
            'pico', 'pico css', 'picocss' => 'pico',
            'plain', 'plain css', 'css' => 'plain',
            default => null,
        };

        if ($known === null) {
            throw new InvalidArgumentException(__('[:framework] is not a framework here. Bootstrap, Pico, or Plain CSS.', ['framework' => $said]));
        }

        return $known;
    }

    /** What `@bwframework` writes into a head: the link, or nothing at all. */
    public function linkTag(): string
    {
        return self::LINKS[$this->get()];
    }
}
