<?php

/*
 * Use this file to bootstrap the application.
 */

/*
 * Place the BonesApi service into the services container
 */

use App\Services\BonesAuth\BonesAuth;

get_service('BonesApi', [
    'config' => get_config('api')
]);

/*
 * Place the BonesAuth library into the services container
 */

/** @var PDO $pdo */

$pdo = get_from_container('db')->get('primary');

/** @var BonesAuth $bones_auth */

$auth = get_service('BonesAuth\\BonesAuth', [
    'pdo' => $pdo,
    'pepper' => get_config('app.key', '')
]);

$container = get_container();

$container->put('auth', $auth);