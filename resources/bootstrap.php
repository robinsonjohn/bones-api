<?php /** @noinspection PhpUnhandledExceptionInspection */

use Bayfront\PDO\Db;

/*
 * Use this file to bootstrap the application.
 */

/*
 * Place the BonesApi service into the container
 */

get_service('BonesApi', [
    'config' => get_config('api')
]);

/*
 * Place the BonesAuth service into the container
 */

/** @var Db $db */

$db = get_from_container('db');

get_service('BonesAuth\\BonesAuth', [
    'pdo' => $db->get('primary'), // PDO instance
    'pepper' => get_config('app.key', '')
]);