<?php

namespace Bladewright\Blocks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Models\PageChild;
use Bladewright\Models\Structure;

/**
 * Creating, copying, renaming, publishing and deleting the four-layer
 * pages — and putting components onto them. **The rule lives here, once.**
 *
 * (`SitePages`, not `PageManager`: the old world's `Support\PageManager`
 * still runs the site that is actually served. This one takes over at
 * integration.)
 */
class SitePages
{
    public function find(string $name): ?Page
    {
        return Page::query()->where('name', trim($name))->first();
    }

    /**
     * Make a page.
     *
     * The URL is the caller's to give (`''` is the front page); the layout
     * is optional, resolved **from its name to its uuid here, once** — a
     * page with none stands bare, and saying so is the command's job.
     */
    public function create(string $name, string $url, ?string $layoutName = null): Page
    {
        $name = $this->assertNameIsFree($name);
        $url = $this->assertUrlIsFree($url);

        return Page::create([
            'name' => $name,
            'url' => $url,
            'layout_uuid' => $this->layoutUuid($layoutName),
            // **Born in the site's language** — its <html lang>.
            'locale' => \Bladewright\Bladewright::locale(),
            'data' => [],
        ]);
    }

    /**
     * One more page under its own name.
     *
     * **The copy shows the same components** — references travel, contents
     * do not. The URL cannot repeat, so a free one is taken (`about-2`) and
     * changed later in the page's settings; **the copy is never published**,
     * whatever the original was.
     */
    public function copy(Page $page, string $to): Page
    {
        $to = $this->assertNameIsFree($to);

        $copy = Page::create([
            'name' => $to,
            'url' => $this->freeUrl($page->url !== '' ? $page->url : Str::slug($page->name)),
            'layout_uuid' => $page->layout_uuid,
            // The copy speaks the language of what it copies.
            'locale' => $page->locale,
            'is_published' => false,
            'data' => $page->data,
        ]);

        foreach ($page->children as $child) {
            PageChild::create([
                'page_id' => $copy->id,
                'child_uuid' => $child->child_uuid,
                'position' => $child->position,
            ]);
        }

        return $copy;
    }

    /** Change what it is called. The URL and the uuid stay where they are. */
    public function rename(Page $page, string $to): Page
    {
        $to = $this->assertNameIsFree($to);

        $page->forceFill(['name' => $to])->save();

        return $page;
    }

    /**
     * Publish it, now or for a window.
     *
     * @param  string|null  $from  when it goes up (null is at once)
     * @param  string|null  $until when it comes down (null is never)
     */
    public function publish(Page $page, ?string $from = null, ?string $until = null): Page
    {
        $page->forceFill([
            'is_published' => true,
            'published_from' => $this->moment($from, '--from'),
            'published_until' => $this->moment($until, '--until'),
        ])->save();

        return $page;
    }

    /** Change the URL. **The name and the uuid stay where they are.** */
    public function changeUrl(Page $page, string $url): Page
    {
        $url = trim(trim($url), '/');

        if ($url !== $page->url) {
            $this->assertUrlIsFree($url);
            $page->forceFill(['url' => $url])->save();
        }

        return $page;
    }

    /** Choose the frame — or none, which stands bare and says so on the screens. */
    public function changeLayout(Page $page, ?string $layoutName): Page
    {
        $page->forceFill(['layout_uuid' => $this->layoutUuid($layoutName)])->save();

        return $page;
    }

    /** Take it off the site. The URL stays reserved; putting it back is one press. */
    public function unpublish(Page $page): Page
    {
        $page->forceFill(['is_published' => false])->save();

        return $page;
    }

    /**
     * Take one component off the page. **The component itself stays** — what
     * goes is its place here, and the places below close the gap.
     */
    public function removeComponent(Page $page, int $childId): void
    {
        $child = $page->children()->whereKey($childId)->first();

        if ($child === null) {
            return;
        }

        $position = $child->position;
        $child->delete();

        $page->children()->where('position', '>', $position)->decrement('position');
    }

    /** Move one place up (-1) or down (+1). At the edge, nothing moves. */
    public function moveComponent(Page $page, int $childId, int $delta): void
    {
        $child = $page->children()->whereKey($childId)->first();

        if ($child === null || ! in_array($delta, [-1, 1], true)) {
            return;
        }

        $neighbour = $page->children()->where('position', $child->position + $delta)->first();

        if ($neighbour === null) {
            return;
        }

        // Swap the two places.
        $position = $child->position;
        $child->forceFill(['position' => $neighbour->position])->save();
        $neighbour->forceFill(['position' => $position])->save();
    }

    /**
     * Put the component now standing at :from into place :to.
     *
     * **Dragging says where a thing lands, not which way it moved**, so the
     * whole row is renumbered from what is left after taking it out.
     */
    public function reorderComponent(Page $page, int $from, int $to): void
    {
        $children = $page->children()->get()->values();

        if (! isset($children[$from]) || ! isset($children[$to]) || $from === $to) {
            return;
        }

        $moving = $children[$from];
        $rest = $children->reject(fn ($child) => $child->id === $moving->id)->values();
        $ordered = $rest->splice(0, $to)->push($moving)->concat($rest);

        foreach ($ordered as $index => $child) {
            $child->forceFill(['position' => $index + 1])->save();
        }
    }

    /**
     * What is wrong with this URL, said the way the screens show it.
     *
     * **The screens ask before they try**, so the reason lands under the box
     * it is about instead of in a toast at the far corner.
     */
    public function urlProblem(string $url, ?Page $ignore = null): ?string
    {
        $url = trim(trim($url), '/');

        $taken = Page::query()
            ->where('url', $url)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();

        if ($taken) {
            return $url === ''
                ? __('The front page is already taken.')
                : __('/:url is already in use.', ['url' => $url]);
        }

        // **A URL may be a shape**: `news/{slug}` answers every path of that
        // shape. The braces are checked here so a mistyped one is said on
        // the screen rather than quietly matching nothing.
        return app(\Bladewright\Site\PageRoutes::class)->problem($url);
    }

    public function delete(Page $page): void
    {
        $page->children()->delete();
        $page->delete();
    }

    /**
     * Put a component onto a page. **The name became a uuid on the way in.**
     *
     * @param  int|null  $position  1, 2, 3 … from the top; null is the end
     */
    /**
     * Write the page's own markup. **When there is any, that is the page** —
     * Blade of the site's own, run when it is asked for, and the arrangement
     * stops reaching the page until it is emptied again.
     */
    public function saveMarkup(Page $page, string $markup): Page
    {
        $page->forceFill([
            'data' => array_merge($page->data ?? [], ['markup' => trim($markup)]),
        ])->save();

        return $page;
    }

    /**
     * What the page says about itself to the machines. **Only these four**,
     * each cleaned to plain words — the head they land in is written by
     * `Meta`, never by hand from here.
     *
     * @param  array<string, mixed>  $seo
     */
    public function saveSeo(Page $page, array $seo): Page
    {
        $kept = [
            'title' => trim((string) ($seo['title'] ?? '')),
            'description' => trim((string) ($seo['description'] ?? '')),
            'image' => trim((string) ($seo['image'] ?? '')),
            'noindex' => ! empty($seo['noindex']) ? '1' : '',
        ];

        $page->forceFill([
            'data' => array_merge($page->data ?? [], ['seo' => $kept]),
        ])->save();

        return $page;
    }

    public function insertComponent(Page $page, Structure $component, ?int $position = null): PageChild
    {
        // **A band's component is the layout's own** — the frame already
        // puts the header on every page, so a page holding one of its own
        // would wear it twice.
        if (in_array($component->type, \Bladewright\Models\Layout::BANDS_TYPES, true)) {
            throw new InvalidArgumentException(__('[:name] is a :type — it is placed on a layout, not in here.', [
                'name' => $component->name,
                'type' => $component->type,
            ]));
        }

        $count = $page->children()->count();

        if ($position === null || $position > $count) {
            $position = $count + 1;
        }

        if ($position < 1) {
            throw new InvalidArgumentException(__('The order counts from 1.'));
        }

        $page->children()->where('position', '>=', $position)->increment('position');

        return PageChild::create([
            'page_id' => $page->id,
            'child_uuid' => $component->uuid,
            'position' => $position,
        ]);
    }

    /** How many pages show this component. What deleting it would reach. */
    public function pagesShowing(Structure $component): int
    {
        return PageChild::query()->where('child_uuid', $component->uuid)->count();
    }

    /** How many pages wear this frame. **Editing it reaches all of them.** */
    public function pagesWearing(Layout $layout): int
    {
        return Page::query()->where('layout_uuid', $layout->uuid)->count();
    }

    /**
     * A deleted layout leaves no page pointing at nothing. **The pages stay**
     * — they stand bare until another frame is chosen, which is what a page
     * with no layout has always meant.
     */
    public function forgetLayout(Layout $layout): void
    {
        Page::query()->where('layout_uuid', $layout->uuid)->update(['layout_uuid' => null]);
    }

    /** A deleted component leaves no dangling pointers on any page. */
    public function forgetComponent(Structure $component): void
    {
        PageChild::query()->where('child_uuid', $component->uuid)->delete();
    }

    private function layoutUuid(?string $layoutName): ?string
    {
        if ($layoutName === null || trim($layoutName) === '') {
            return null;
        }

        $layout = app(LayoutManager::class)->find($layoutName);

        if ($layout === null) {
            throw new InvalidArgumentException(__('[:name] is not a layout.', ['name' => $layoutName]));
        }

        return $layout->uuid;
    }

    private function moment(?string $value, string $option): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__(':option does not read as a date and time (2026-10-01 09:00, say).', ['option' => $option]));
        }
    }

    private function freeUrl(string $base): string
    {
        $base = trim($base, '/') ?: 'page';
        $candidate = $base;
        $suffix = 2;

        while (Page::query()->where('url', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function assertUrlIsFree(string $url): string
    {
        $url = trim(trim($url), '/');

        if (($problem = $this->urlProblem($url)) !== null) {
            throw new InvalidArgumentException($problem);
        }

        return $url;
    }

    private function assertNameIsFree(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(__('A name cannot be empty.'));
        }

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException(__('A name can be at most 100 characters.'));
        }

        if (Page::query()->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('[:name] is already a page.', ['name' => $name]));
        }

        return $name;
    }
}
