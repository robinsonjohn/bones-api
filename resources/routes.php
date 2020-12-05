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

    // Auth

    ->any('/v1/auth/login', 'v1\\Auth:login')
    ->any('/v1/auth/refresh', 'v1\\Auth:refresh');