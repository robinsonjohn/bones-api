<?php /** @noinspection PhpUnhandledExceptionInspection */

/*
 * This file should be used to manage event hooks.
 */

/** @var Bayfront\Hooks\Hooks $hooks */

use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;

/**
 * Add extra context onto logged events.
 *
 * - URL
 *
 * @throws ChannelNotFoundException
 * @throws NotFoundException
 */

function log_context()
{

    if (get_container()->has('logs')) {

        $logs = get_logs();

        $channels = $logs->getChannels();

        foreach ($channels as $channel) {

            $logs->getChannel($channel)->pushProcessor(function ($record) {

                $record['extra']['url'] = Request::getUrl(true);
                $record['extra']['ip'] = Request::getIp();

                return $record;

            });

        }

    }

}

add_event('app.bootstrap', 'log_context');

/**
 * Adds user ID to the context of logged events.
 *
 * @param array $token
 *
 * @throws ChannelNotFoundException
 * @throws NotFoundException
 */

function log_user(array $token)
{

    if (get_container()->has('logs')) {

        $logs = get_logs();

        $channels = $logs->getChannels();

        foreach ($channels as $channel) {

            $logs->getChannel($channel)->pushProcessor(function ($record) use ($token) {

                $record['extra']['user_id'] = $token['user_id'];

                return $record;

            });

        }

    }

}

add_event('jwt.authenticated', 'log_user');