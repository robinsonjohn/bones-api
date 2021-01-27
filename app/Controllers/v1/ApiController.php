<?php

namespace App\Controllers\v1;

use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\PDO\Exceptions\QueryException;

/**
 * ApiController controller.
 */
abstract class ApiController extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    /** @var Auth $auth */

    protected $auth;

    protected $token; // JWT

    protected $permissions = [];

    /**
     * ApiController constructor.
     *
     * TODO:
     * Add required permissions to the constructor
     *
     * @param bool $requires_authentication
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws InvalidOrganizationException
     * @throws InvalidUserException
     * @throws QueryException
     */

    public function __construct(bool $requires_authentication = true)
    {

        parent::__construct(); // Bones controller

        // Get the Bones API service from the container

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // Get the Auth class from the container

        $this->auth = $this->container->get('auth');

        if (true === $requires_authentication) {

            // All endpoints require authentication

            $this->token = $this->api->authenticateJwt();

            // Check rate limit

            if (isset($this->token['payload']['user_id']) && isset($this->token['payload']['rate_limit'])) {

                $this->api->enforceRateLimit($this->token['payload']['user_id'], $this->token['payload']['rate_limit']);

            }

            if (isset($this->token['payload']['orgs'])) {

                foreach ($this->token['payload']['orgs'] as $organization) { // TODO: Work with permissions

                    $this->permissions[$organization] = $this->auth->getUserPermissions($this->token['payload']['user_id'], $organization);

                }

            }

        }

    }

}