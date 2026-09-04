<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Block;
use Bladewright\Models\Structure;
use Bladewright\Models\StructureChild;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:components` — a structure that means something, a collection
 * of blocks. **It holds references, not copies.**
 */
class ComponentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_component_can_be_created_and_listed(): void
    {
        $this->artisan('bladewright:components', ['--create' => 'hero', '--type' => 'section'])
            ->expectsOutputToContain('section component')
            ->assertSuccessful();

        $this->artisan('bladewright:components')
            ->expectsOutputToContain('hero')
            ->assertSuccessful();

        $this->assertNotNull(Structure::query()->firstOrFail()->uuid);
    }

    /** `--layout=grid` gives the arrangement a grid, and the list says so. */
    public function test_a_layout_gets_a_grid(): void
    {
        $this->artisan('bladewright:components', ['--create' => 'cards', '--type' => 'section', '--layout' => 'grid'])
            ->expectsOutputToContain('laid out in a grid')
            ->assertSuccessful();

        $this->assertSame('grid', Structure::query()->firstOrFail()->layout);
    }

    /** A layout that is not on the list is refused. */
    public function test_a_made_up_layout_is_refused(): void
    {
        $this->artisan('bladewright:components', ['--create' => 'cards', '--type' => 'section', '--layout' => 'circle'])
            ->assertFailed();

        $this->assertSame(0, Structure::query()->count());
    }

    /** Only the product's own types are accepted. */
    public function test_the_type_is_required_and_checked(): void
    {
        $this->artisan('bladewright:components', ['--create' => 'hero'])
            ->expectsOutputToContain('--type=')
            ->assertFailed();

        $this->artisan('bladewright:components', ['--create' => 'hero', '--type' => 'div'])
            ->expectsOutputToContain('not a component type')
            ->assertFailed();
    }

    /** A block goes in by name, and the component holds the uuid. */
    public function test_a_block_is_inserted_by_name_and_held_by_uuid(): void
    {
        $block = Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);
        $this->app->make(StructureManager::class)->create('hero', 'section');

        $this->artisan('bladewright:blocks', ['--insert' => 'intro', '--to' => 'hero'])
            ->expectsOutputToContain('stands in [hero] at 1')
            ->assertSuccessful();

        $child = StructureChild::query()->firstOrFail();

        $this->assertSame($block->uuid, $child->child_uuid);

        // Renaming the block afterwards moves nothing.
        $this->artisan('bladewright:blocks', ['--rename' => 'intro', '--to' => 'welcome'])->assertSuccessful();

        $this->assertSame($block->uuid, $child->refresh()->child_uuid);
    }

    /** `--order` says where it lands; what stood there moves down. */
    public function test_order_places_it_and_shifts_the_rest(): void
    {
        $manager = $this->app->make(StructureManager::class);
        $hero = $manager->create('hero', 'section');

        $first = Block::create(['name' => 'first', 'type' => 'markdown', 'data' => []]);
        $second = Block::create(['name' => 'second', 'type' => 'markdown', 'data' => []]);
        $between = Block::create(['name' => 'between', 'type' => 'image', 'data' => []]);

        $manager->insertBlock($hero, $first);
        $manager->insertBlock($hero, $second);

        $this->artisan('bladewright:blocks', ['--insert' => 'between', '--to' => 'hero', '--order' => '2'])
            ->expectsOutputToContain('at 2')
            ->assertSuccessful();

        $order = $hero->children()->pluck('child_uuid')->all();

        $this->assertSame([$first->uuid, $between->uuid, $second->uuid], $order);
    }

    /** The copy arranges the same blocks — references, not copies. */
    public function test_a_copy_shares_the_blocks(): void
    {
        $manager = $this->app->make(StructureManager::class);
        $hero = $manager->create('hero', 'section');
        $block = Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);
        $manager->insertBlock($hero, $block);

        $this->artisan('bladewright:components', ['--copy' => 'hero', '--to' => 'hero-b'])
            ->expectsOutputToContain('arranges the same blocks')
            ->assertSuccessful();

        $copy = Structure::query()->where('name', 'hero-b')->firstOrFail();

        $this->assertSame([$block->uuid], $copy->children()->pluck('child_uuid')->all());
    }

    /** Renaming keeps the uuid, so whatever points at it still points at it. */
    public function test_renaming_keeps_the_uuid(): void
    {
        $structure = $this->app->make(StructureManager::class)->create('hero', 'section');
        $uuid = $structure->uuid;

        $this->artisan('bladewright:components', ['--rename' => 'hero', '--to' => 'banner'])
            ->assertSuccessful();

        $this->assertSame($uuid, $structure->refresh()->uuid);
    }

    /** Deleting takes the arrangement and leaves the blocks. */
    public function test_deleting_leaves_the_blocks(): void
    {
        $manager = $this->app->make(StructureManager::class);
        $hero = $manager->create('hero', 'section');
        $manager->insertBlock($hero, Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]));

        $this->artisan('bladewright:components', ['--delete' => 'hero'])
            ->expectsOutputToContain('The blocks in it stay')
            ->expectsConfirmation('Delete [hero]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Structure::query()->count());
        $this->assertSame(0, StructureChild::query()->count());
        $this->assertSame(1, Block::query()->count());
    }

    /** Deleting a block sweeps it out of every component that showed it. */
    public function test_deleting_a_block_sweeps_its_places(): void
    {
        $manager = $this->app->make(StructureManager::class);
        $hero = $manager->create('hero', 'section');
        $footer = $manager->create('footer', 'nav');
        $block = Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);
        $manager->insertBlock($hero, $block);
        $manager->insertBlock($footer, $block);

        $this->artisan('bladewright:blocks', ['--delete' => 'intro'])
            ->expectsOutputToContain('Shown in 2 component(s)')
            ->expectsConfirmation('Delete [intro]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, StructureChild::query()->count());
    }
}
