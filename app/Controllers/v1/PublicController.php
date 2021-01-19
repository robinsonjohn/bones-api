<?php

namespace App\Controllers\v1;

use App\Schemas\StatusResource;
use Bayfront\ArraySchema\InvalidSchemaException;
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
class PublicController extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    /**
     * PublicController constructor.
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

            // Start the API environment

            /*
             * The "Accept" header will not be checked in this controller
             * as it may not be present, such as for a webhook endpoint.
             */

            $this->api->start(false); // Do not check the "Accept" header

            // Check rate limit

            $this->api->enforceRateLimit('public-' . Request::getIp(), get_config('api.public_rate_limit', 100));

        }

    }

    /**
     * API status.
     *
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidSchemaException
     */

    public function status()
    {

        // Endpoint requirements

        $this->api->allowedMethods([
            'GET'
        ]);

        // Build schema

        $schema = StatusResource::create([
            'status' => 'OK',
            'version' => '1.0.0' // Current API version
        ]);

        // Respond

        $this->response->setStatusCode(200)->setHeaders([
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate'
        ])->sendJson($schema);

    }

}