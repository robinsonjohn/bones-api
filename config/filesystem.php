<?php

/*
 * For more information, see:
 * https://github.com/bayfrontmedia/filesystem-factory#configuration-array
 */

return [
    'local' => [
        'default' => true,
        'adapter' => 'Local',
        'root' => storage_path(),
        'permissions' => [
            'file' => [
                'public' => 0644,
                'private' => 0600
            ],
            'dir' => [
                'public' => 0755,
                'private' => 0700,
            ]
        ],
        'cache' => [
            'location' => 'Memory'
        ],
        'url' => 'https://www.example.com/storage' // No trailing slash
    ]
];