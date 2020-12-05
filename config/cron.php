<?php

/*
 * For more information, see:
 * https://github.com/bayfrontmedia/cron-scheduler#usage
 */

return [
    'lock_file_path' => storage_path('/app/temp'),
    'output_file' => storage_path('/app/cron/cron-' . date('Y-m-d') . '.txt')
];