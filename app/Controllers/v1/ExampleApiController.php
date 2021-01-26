<?php

namespace App\Controllers\v1;

use Bayfront\Auth\Exceptions\InvalidEntityException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\PDO\Exceptions\QueryException;

/**
 * This controller allows rate limited authenticated access to endpoints.
 */
class ExampleApiController extends ApiController
{

    /**
     * ExampleApiController constructor.
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws InvalidEntityException
     * @throws InvalidUserException
     * @throws QueryException
     */

    public function __construct()
    {
        parent::__construct();
    }

}