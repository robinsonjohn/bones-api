<?php

/*
 * Use this file to bootstrap the application.
 */

/*
 * Place the BonesApi service into the services container
 */

use Bayfront\Auth\Auth;

get_service('BonesApi', [
    'config' => get_config('api')
]);

/*
 * Place the User Auth library into the services container
 */

/** @var PDO $pdo */

$pdo = get_from_container('db')->get('primary');

$auth = new Auth($pdo, get_config('app.key', ''));

$container = get_container();

$container->put('auth', $auth);