<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Tests\TestCase;

/**
 * **One place in the sidebar is lit at a time, and it is the right one.**
 * (The content screens are being rebuilt; media and settings are what stand.)
 */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** The media library lights Media, and nothing else. */
    public function test_media_lights_media(): void
    {
        $this->actingAsRole();

        $this->assertSame(
            [route('bladewright.admin.media')],
            $this->lit('/bladewright/media'),
        );
    }

    /** The site's settings light Settings, and nothing else. */
    public function test_settings_light_settings(): void
    {
        $this->actingAsRole();

        $this->assertSame(
            [route('bladewright.admin.settings')],
            $this->lit('/bladewright/settings'),
        );
    }

    /**
     * Which sidebar links are showing as where you are.
     *
     * @return array<int, string>
     */
    private function lit(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        // The lit row is the one whose words wear the accent.
        preg_match_all('/<a href="([^"]+)"[^>]*text-bw-accent/', $html, $found);

        return $found[1];
    }
}
