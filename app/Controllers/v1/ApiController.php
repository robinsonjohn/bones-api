<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use Bayfront\ArrayHelpers\Arr;
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

    /** @var BonesAuth $auth */

    protected $auth;

    protected $token; // JWT

    protected $user_id;

    protected $permissions; // Array of permission names

    /**
     * ApiController constructor.
     *
     * @param bool $requires_authentication
     *
     * @throws ControllerException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws AdapterException
     * @throws BucketException
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

        $this->auth = $this->container->get('auth');

        if (true === $requires_authentication) {

            // All endpoints require authentication

            $this->token = $this->api->authenticateJwt();

            // Check rate limit

            if (Arr::has($this->token, 'payload.user_id') && Arr::has($this->token, 'payload.rate_limit')) {

                $this->api->enforceRateLimit($this->token['payload']['user_id'], $this->token['payload']['rate_limit']);

            }

            $this->user_id = $this->token['payload']['user_id'];

            $this->permissions = Arr::pluck($this->auth->getUserPermissions(Arr::get($this->token, 'payload.user_id', '')), 'name');

        }

    }

    /**
     * Require values to exist on an array if the key already exists.
     *
     * @param array $array
     * @param string $key (Array key in dot notation)
     * @param string|array $values (Value(s) to require)
     *
     * @return array
     */

    public function requireValues(array $array, string $key, $values): array
    {

        if (Arr::has($array, $key)) {

            $set = Arr::get($array, $key);

            foreach ((array)$values as $value) {

                $set[] = $value;

            }

            // Remove blank and duplicate values

            Arr::set($array, $key, array_unique(array_filter($set)));

        }

        return $array;

    }

    /**
     * Does user have permission(s).
     *
     * @param string|array $permissions
     *
     * @return bool
     */

    public function hasPermission($permissions): bool
    {
        return count(array_intersect((array)$permissions, $this->permissions)) == count((array)$permissions);
    }

}