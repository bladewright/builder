<?php

use Bladewright\Starters\StarterContent;

/**
 * Plain CSS. **No framework, no build.**
 *
 * Having no build step is the point, and it fits a product that promises
 * everything can be fixed from a browser: a class added after a build would
 * never apply, and here there is no build to be after.
 *
 * **The layout this writes is the site's first and only frame**, the way a
 * Laravel starter kit builds its screens for the stack you picked.
 */
return [
    'key' => 'plain',
    'type' => 'starter',
    'name' => 'Plain CSS',
    'description' => 'No framework and no build: the frame is one file of CSS you own.',
    'layouts' => [
        [
            'key' => 'site',
            'name' => 'Site frame',
            'source' => StarterContent::layout('plain'),
        ],
    ],
    'pages' => [
        [
            'key' => 'home',
            'name' => 'Home',
            'path' => '',
            'layout' => 'site',
            'blocks' => StarterContent::blocks(),
        ],
    ],
];
