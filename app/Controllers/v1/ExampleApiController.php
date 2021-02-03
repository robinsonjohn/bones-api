<?php

namespace App\Controllers\v1;

use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;

/**
 * This controller allows rate limited authenticated access to endpoints.
 */
class ExampleApiController extends ApiController
{

    /**
     * ExampleApiController constructor.
     *
     * @throws ControllerException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {
        parent::__construct();
    }

}