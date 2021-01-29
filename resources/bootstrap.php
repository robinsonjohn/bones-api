<?php /** @noinspection PhpUnhandledExceptionInspection */

/*
 * Use this file to bootstrap the application.
 */

use App\Services\BonesAuth\BonesAuth;
use Bayfront\PDO\Db;

/*
 * Place the BonesApi service into the services container
 */

get_service('BonesApi', [
    'config' => get_config('api')
]);

/*
 * Place the BonesAuth library into the services container
 */

/** @var Db $db */

$db = get_from_container('db');

/** @var BonesAuth $bones_auth */

$auth = get_service('BonesAuth\\BonesAuth', [
    'pdo' => $db->get('primary'),
    'pepper' => get_config('app.key', '')
]);

$container = get_container();

$container->put('auth', $auth);