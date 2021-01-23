<?php

/*
 * For more information, see:
 * https://github.com/bayfrontmedia/bones/blob/master/_docs/libraries/database.md
 */

return [
    'primary' => [ // Connection name
        'default' => true, // One connection on the array must be defined as default
        'adapter' => get_env('DB_ADAPTER'), // Adapter to use
        'host' => get_env('DB_HOST'),
        'port' => get_env('DB_PORT'),
        'database' => get_env('DB_DATABASE'),
        'user' => get_env('DB_USER'),
        'password' => get_env('DB_PASSWORD')
    ]
];