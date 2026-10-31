<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inertia page components
    |--------------------------------------------------------------------------
    |
    | Keep the server-side test resolver aligned with the frontend's
    | resources/js/Pages directory. The explicit capital P matters on Linux.
    |
    */
    'pages' => [
        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
