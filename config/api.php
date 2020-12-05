<?php

return [
    'maintenance_mode' => false, // Boolean (optional)
    'maintenance_until' => new DateTime('2020-11-04 17:00:00'), // DateTimeInterface (optional)
    'allow_http' => 'development', // app.environments to allow http (string|array) (optional)
    'accept_header' => 'application/vnd.api+json', // Required Accept header (optional)
    'content_type' => 'application/vnd.api+json', // Required Content-Type header to exist with request body (optional)
    'buckets_path' => '/app/buckets', // Directory in which to store rate limit buckets from the default filesystem disk root
    'auth_rate_limit' => 5, // Per minute (Rate limit for failed authentication)

    /*
     * The following keys are examples, and are used in the sample Auth controller
     */

    'rate_limit' => 50, // Per minute rate limit for authenticated user
    'webhook_rate_limit' => 100, // Per minute rate limit for public webhoooks
    'access_token_lifetime' => 86400, // 24 hours
    'refresh_token_lifetime' => 604800 // e.g.: 604800 (7 days), 2592000 (30 days)
];