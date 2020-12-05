<?php

use Bayfront\Bones\App;

// -------------------- Required constants --------------------

// Paths should always begin and never end with a forward slash

define('APP_ROOT_PATH', dirname(__FILE__, 2)); // Root path to the application's installed directory
define('APP_PUBLIC_PATH', __DIR__); // Path to the application's public directory

// -------------------- Use Composer's autoloader --------------------

require(APP_ROOT_PATH . '/vendor/autoload.php');

// -------------------- Start app --------------------

/* @noinspection PhpUnhandledExceptionInspection */

App::start();