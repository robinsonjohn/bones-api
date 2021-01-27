<?php

/*
 * This file should be used to define all of the application's routes.
 */

use Bayfront\RouteIt\Router;

/** @var $router Router */

$router->setHost(get_config('router.host'))
    ->setRoutePrefix(do_filter('router.route_prefix', get_config('router.route_prefix')))
    ->addFallback('ANY', function () {
        abort(404);
    })
    ->get('/', 'Home:index', [], 'home')

    // -------------------- API --------------------

    // Public

    ->any('/v1', 'v1\\PublicController:status')

    // Auth

    ->any('/v1/auth/login', 'v1\\Auth:login')
    ->any('/v1/auth/refresh', 'v1\\Auth:refresh')

    // User Auth library

    ->any('v1/organizations/{?:id}', 'v1\\Organizations:index')
    ->any('v1/organizations/{*:id}/permissions', 'v1\\Organizations:permissions')
    ->any('v1/organizations/{*:id}/users', 'v1\\Organizations:users')
    ->any('v1/groups/{?:id}', 'v1\\Groups:index')
    ->any('v1/groups/{?:id}/permissions', 'v1\\Groups:permissions')
    ->any('v1/permissions/{?:id}', 'v1\\Permissions:index')
    ->any('v1/roles/{?:id}', 'v1\\Roles:index')
    ->any('v1/roles/{?id}/permissions', 'v1\\Roles:permissions')
    ->any('v1/users/{?:id}', 'v1\\Users:index');