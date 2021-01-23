<?php

namespace App\Controllers\v1;

use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;

/**
 * ApiController controller.
 */
abstract class ApiController extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    protected $token; // JWT

    /**
     * ApiController constructor.
     *
     * @throws ControllerException
     * @throws HttpException
     * @throws ServiceException
     * @throws NotFoundException
     * @throws InvalidStatusCodeException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {

        parent::__construct(); // Bones controller

        // Get the Bones API service from the container

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // All endpoints require authentication

        $this->token = $this->api->authenticateJwt();

        // Check rate limit

        if (isset($this->token['payload']['user_id']) && isset($this->token['payload']['rate_limit'])) {

            $this->api->enforceRateLimit($this->token['payload']['user_id'], $this->token['payload']['rate_limit']);

        }

    }

}