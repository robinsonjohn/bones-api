<?php

/*
 * This file should only be accessed as a cron job. Example crontab entry:
 *
 * * * * * * /path/to/php/bin /path/to/resources/cron.php 1>> /dev/null 2>&1
 */

use Bayfront\ArrayHelpers\Arr;
use Bayfront\Container\NotFoundException;
use Bayfront\CronScheduler\Cron;
use Bayfront\CronScheduler\FilesystemException;
use Bayfront\Filesystem\Filesystem;
use Bayfront\PDO\Db;

define('IS_CRON', true);

require(dirname(__FILE__, 2) . '/public/index.php'); // Modify this path if necessary

/**
 * @var Cron $cron
 */

try {

    $cron = get_from_container('cron');

} catch (NotFoundException $e) {
    die($e->getMessage());
}

/*
 * ############################################################
 * Add cron jobs below
 * ############################################################
 */

/*
 * Delete all API ratelimit buckets that have not been modified for at least 24 hours.
 */

$cron->call('delete_api_ratelimit_buckets', function () {

    /** @var Filesystem $filesystem */

    $filesystem = get_from_container('filesystem');

    $files = $filesystem->listFiles(get_config('api.buckets_path', '/app/buckets'), false, 'json');

    $starts_with = 'bucket-api.ratelimit';

    $expired = time() - 86400; // 24 hours

    $i = 0;

    foreach ($files as $file) {

        if (substr($file['filename'], 0, strlen($starts_with)) === $starts_with) { // If a ratelimit bucket

            if ($expired > $filesystem->getTimestamp($file['path'])) { // If expired

                $filesystem->delete($file['path']);

                $i++;

            }

        }

    }

    log_info('Completed deleting expired ratelimit buckets', [
        'count' => $i
    ]);

})->daily();

/*
 * Delete expired refresh tokens.
 */

$cron->call('delete_expired_refresh_tokens', function () {

    /** @var Db $db */

    $db = get_from_container('db');

    $i = 0;

    $table = $db->select("SHOW TABLES LIKE 'rbac_user_meta'");

    if (!empty($table)) { // Table exists

        $tokens = $db->select("SELECT userId, metaValue FROM rbac_user_meta WHERE metaKey = :key", [
            'key' => '_refresh_token'
        ]);

        foreach ($tokens as $token) {

            $refresh_token = json_decode($token['metaValue'], true);

            // Delete if invalid format or expired

            if (Arr::isMissing($refresh_token, [
                    'token',
                    'created_at'
                ]) || $refresh_token['created_at'] < time() - get_config('api.refresh_token_lifetime')) {

                $db->delete('rbac_user_meta', [
                    'userId' => $token['userId'],
                    'metaKey' => '_refresh_token'
                ]);

                $i++;

                continue;

            }

        }

    }

    log_info('Completed deleting expired refresh tokens', [
        'count' => $i
    ]);

})->daily();

/*
 * ############################################################
 * Stop adding cron jobs
 * ############################################################
 */

try {

    $result = $cron->run();

} catch (FilesystemException $e) {
    die($e->getMessage());
}

log_info('Completed running ' . $result['count'] . ' cron jobs', [
    'result' => $result
]);