<?php

return [
    'namespace' => 'App\\', // Namespace for the app/ directory, as specified in composer.json
    'key' => get_env('APP_KEY'), // Unique to the app, not to the environment
    'debug_mode' => get_env('APP_DEBUG_MODE'),
    'environment' => get_env('APP_ENVIRONMENT'), // e.g.: "development", "staging", "production"
    'timezone' => get_env('APP_TIMEZONE'), // See: https://www.php.net/manual/en/timezones.php
    'events_enabled' => get_env('APP_EVENTS_ENABLED'),
    'filters_enabled' => get_env('APP_FILTERS_ENABLED')
];