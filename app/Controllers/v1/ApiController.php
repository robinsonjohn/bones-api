<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;

/**
 * ApiController controller.
 */
abstract class ApiController extends Controller
{

    protected $api;

    protected $auth;

    protected $token; // JWT

    protected $permissions = [];

    /**
     * ApiController constructor.
     *
     * @param bool $requires_authentication
     *
     * @throws ControllerException
     * @throws NotFoundException
     * @throws ServiceException
     */

    public function __construct(bool $requires_authentication = true)
    {

        parent::__construct(); // Bones controller

        // Get the Bones API service from the container

        /** @var BonesApi $api */

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // Get the Auth class from the container

        /** @var BonesAuth $auth */

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

    /**
     * Return the page size based on the request query string, and the api.default_page_size and api.max_page_size config values
     *
     * TODO:
     * Add abs() to the value if it does not trigger a status code 400 when page[size] is a negative number
     *
     * @return int
     */

    public function getPageSize():int
    {
        return (int)min((int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10)), (int)get_config('api.max_page_size', 10));
    }

}