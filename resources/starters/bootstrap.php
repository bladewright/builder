<?php

use Bladewright\Starters\StarterContent;

/**
 * Bootstrap, from a CDN. **No build step either.**
 *
 * Bootstrap ships all of its CSS, so a class written in the browser this
 * afternoon works this afternoon — which is the whole reason this product can
 * offer it and cannot offer Tailwind.
 *
 * **The layout this writes is the site's first and only frame.** The blocks
 * are the same ones; what changes is the frame around them and the tokens
 * they read, which are pointed at Bootstrap's own variables.
 */
return [
    'key' => 'bootstrap',
    'type' => 'starter',
    'name' => 'Bootstrap',
    'description' => 'Bootstrap 5 from a CDN: the frame and the tokens follow it, and there is nothing to build.',
    'layouts' => [
        [
            'key' => 'site',
            'name' => 'Site frame',
            'source' => StarterContent::layout('bootstrap'),
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
