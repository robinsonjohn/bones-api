<?php

namespace App\Controllers\v1;

use Bayfront\Bones\Services\BonesApi;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;

/**
 * This controller allows rate limited public access to endpoints and methods.
 */
class Webhooks extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    /**
     * Webhooks constructor.
     *
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {

        parent::__construct();

        if (!defined('IS_CRON')) {

            // Get the Bones API service from the container

            $this->api = get_service('BonesApi');

            // Check rate limit

            $this->api->enforceRateLimit('webhook-' . Request::getIp(), get_config('api.webhook_rate_limit', 100));

        }

    }

}