<?php

/*
 * For more information, see:
 * https://github.com/bayfrontmedia/route-it#start-using-route-it
 */

return [
    'host' => get_env('ROUTER_HOST'),
    'route_prefix' => get_env('ROUTER_ROUTE_PREFIX'),
    'automapping_enabled' => false,
    'automapping_namespace' => 'App\\Controllers',
    'automapping_route_prefix' => '', // No trailing slash
    'class_namespace' => 'App\\Controllers',
    'files_root_path' => resources_path('/views'), // No trailing slash
    'force_lowercase_url' => true
];