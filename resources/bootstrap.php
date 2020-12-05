<?php

/*
 * Use this file to bootstrap the application.
 */

/*
 * Place the BonesApi service into the services container
 */

get_service('BonesApi', [
    'config' => get_config('api')
]);