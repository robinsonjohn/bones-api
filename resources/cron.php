<?php

/*
 * This file should only be accessed as a cron job. Example crontab entry:
 *
 * * * * * * /path/to/php/bin /path/to/resources/cron.php 1>> /dev/null 2>&1
 */

use Bayfront\Container\NotFoundException;
use Bayfront\CronScheduler\Cron;
use Bayfront\CronScheduler\FilesystemException;
use Bayfront\Filesystem\Filesystem;

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

    log_debug('Completed deleting expired ratelimit buckets', [
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

log_debug('Completed running ' . $result['count'] . ' cron jobs', [
    'result' => $result
]);