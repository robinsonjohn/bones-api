<?php

namespace App\Controllers\v1;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Bones\Services\BonesAuth\BonesAuth;
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

    /*
     * The following are defined only if the $requires_authentication
     * constructor parameter is TRUE
     */

    protected $token = []; // JWT payload

    protected $user_id = '';

    protected $user_groups = []; // Array of group ID's

    protected $user_permissions = []; // Array of permissions

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

        // Get the BonesApi service from the container

        /** @var BonesApi $api */

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // Get the BonesAuth service from the container

        $this->auth = get_service('BonesAuth\\BonesAuth');

        if (true === $requires_authentication) {

            // All endpoints require authentication

            $this->token = Arr::get($this->api->authenticateJwt(), 'payload');

            // Check rate limit

            if (isset($this->token['user_id']) && isset($this->token['rate_limit'])) {

                $this->api->enforceRateLimit($this->token['user_id'], $this->token['rate_limit']);

            }

            $this->user_id = Arr::get($this->token, 'user_id', '');

            $this->user_groups = Arr::get($this->token, 'groups', []);

            $this->user_permissions = $this->auth->getUserPermissions($this->user_id);

            do_event('jwt.authenticated', $this->token);

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
     * Does user have all of the given permission(s).
     *
     * @param string|array $permissions (Permission name(s))
     *
     * @return bool
     */

    public function hasPermissions($permissions): bool
    {
        return Arr::hasAllValues(Arr::pluck($this->user_permissions, 'name'), (array)$permissions);
    }

    /**
     * Does user have at least one of the given permission(s).
     *
     * @param string|array $permissions (Permission name(s))
     *
     * @return bool
     */

    public function hasAnyPermissions($permissions): bool
    {
        return Arr::hasAnyValues(Arr::pluck($this->user_permissions, 'name'), (array)$permissions);
    }

    /**
     * Get all user ID's that exist in any group the user belongs to.
     *
     * @return array
     */

    public function getGroupedUserIds(): array
    {

        $users = [];

        foreach (Arr::get($this->token, 'groups', []) as $group) {

            $users = array_merge($users, $this->auth->getGroupUsers($group));

        }

        return array_unique(Arr::pluck($users, 'id'));

    }

}