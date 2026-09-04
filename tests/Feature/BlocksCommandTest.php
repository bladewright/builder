<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Models\Block;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:blocks`, in the core shape — the four-layer model's smallest
 * unit. **People say the name; the machinery holds the uuid.**
 */
class BlocksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_block_can_be_created_and_listed(): void
    {
        $this->artisan('bladewright:blocks', ['--create' => 'intro', '--type' => 'markdown'])
            ->expectsOutputToContain('markdown block')
            ->assertSuccessful();

        $this->artisan('bladewright:blocks')
            ->expectsOutputToContain('intro')
            ->assertSuccessful();

        $block = Block::query()->firstOrFail();

        $this->assertSame('intro', $block->name);
        $this->assertNotNull($block->uuid);
    }

    /** Creating needs a type, and only the product's own are accepted. */
    public function test_a_type_is_required_and_checked(): void
    {
        $this->artisan('bladewright:blocks', ['--create' => 'intro'])
            ->expectsOutputToContain('--type=')
            ->assertFailed();

        $this->artisan('bladewright:blocks', ['--create' => 'intro', '--type' => 'hero'])
            ->expectsOutputToContain('not a block type')
            ->assertFailed();
    }

    /** **A label is not a block**: it belongs to the field it names. */
    public function test_a_label_is_not_a_type(): void
    {
        $this->artisan('bladewright:blocks', ['--create' => 'field-name', '--type' => 'label'])
            ->expectsOutputToContain('is not a block type')
            ->assertFailed();

        $this->assertSame(0, Block::query()->count());
    }

    /** The name is how a block is addressed, so it cannot repeat. */
    public function test_a_taken_name_is_refused(): void
    {
        Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);

        $this->artisan('bladewright:blocks', ['--create' => 'intro', '--type' => 'image'])
            ->expectsOutputToContain('already a block')
            ->assertFailed();
    }

    /** A search matches part of a name. */
    public function test_search_matches_part_of_a_name(): void
    {
        Block::create(['name' => 'intro-text', 'type' => 'markdown', 'data' => []]);
        Block::create(['name' => 'buy-button', 'type' => 'button', 'data' => []]);

        $this->artisan('bladewright:blocks', ['--search' => 'intro'])
            ->expectsOutputToContain('intro-text')
            ->doesntExpectOutputToContain('buy-button')
            ->assertSuccessful();
    }

    /** A copy carries the content and is its own thing from birth. */
    public function test_a_copy_diverges(): void
    {
        $original = Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => ['body' => 'こんにちは']]);

        $this->artisan('bladewright:blocks', ['--copy' => 'intro', '--to' => 'intro-en'])
            ->assertSuccessful();

        $copy = Block::query()->where('name', 'intro-en')->firstOrFail();

        $this->assertSame(['body' => 'こんにちは'], $copy->data);
        $this->assertNotSame($original->uuid, $copy->uuid);
    }

    /** Renaming changes the word and nothing else — the uuid stays. */
    public function test_renaming_keeps_the_uuid(): void
    {
        $block = Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);
        $uuid = $block->uuid;

        $this->artisan('bladewright:blocks', ['--rename' => 'intro', '--to' => 'welcome'])
            ->assertSuccessful();

        $this->assertSame($uuid, $block->refresh()->uuid);
        $this->assertSame('welcome', $block->name);
    }

    /** Deleting warns, asks, and declining keeps it. */
    public function test_deleting_asks_first(): void
    {
        Block::create(['name' => 'intro', 'type' => 'markdown', 'data' => []]);

        $this->artisan('bladewright:blocks', ['--delete' => 'intro'])
            ->expectsConfirmation('Delete [intro]?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, Block::query()->count());

        $this->artisan('bladewright:blocks', ['--delete' => 'intro'])
            ->expectsConfirmation('Delete [intro]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Block::query()->count());
    }
}
