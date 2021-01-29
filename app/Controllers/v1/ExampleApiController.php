<?php

namespace App\Controllers\v1;

use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;

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
     */

    public function __construct()
    {
        parent::__construct();
    }

}