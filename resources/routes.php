<?php /** @noinspection PhpUnhandledExceptionInspection */

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

    ->any('v1/groups/{?:id}', 'v1\\Groups:index')
    ->any('v1/groups/{*:id}/users', 'v1\\Groups:users')
    ->any('v1/me/{?:resource}', 'v1\\Me:index')
    ->any('v1/permissions/{?:id}', 'v1\\Permissions:index')
    ->any('v1/permissions/{*:id}/roles', 'v1\\Permissions:roles')
    ->any('v1/roles/{?:id}', 'v1\\Roles:index')
    ->any('v1/roles/{*:id}/permissions', 'v1\\Roles:permissions')
    ->any('v1/roles/{*:id}/users', 'v1\\Roles:users')
    ->any('v1/users/{?:id}', 'v1\\Users:index')
    ->any('v1/users/{*:id}/groups', 'v1\\Users:groups')
    ->any('v1/users/{*:id}/meta/{?:meta_key}', 'v1\\Users:meta')
    ->any('v1/users/{*:id}/permissions', 'v1\\Users:permissions')
    ->any('v1/users/{*:id}/roles', 'v1\\Users:roles');