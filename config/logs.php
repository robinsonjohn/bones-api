<?php

/*
 * For more information, see:
 * https://github.com/bayfrontmedia/monolog-factory#configuration-array
 */

return [
    'App' => [
        'default' => true,
        'enabled' => true,
        'handlers' => [
            'RotatingFileHandler' => [
                'params' => [
                    'filename' => storage_path('/app/logs/app.log'),
                    'maxFiles' => 30,
                    'level' => (get_env('APP_ENVIRONMENT') == 'production') ? 'INFO' : 'DEBUG'
                ],
                'formatter' => [
                    'name' => 'LineFormatter',
                    'params' => [
                        'output' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                        'dateformat' => 'Y-m-d H:i:s T'
                    ]
                ]
            ]
        ],
        'processors' => [
            'IntrospectionProcessor' => [
                'params' => [
                    'level' => 'ERROR'
                ]
            ]
        ]
    ]
];