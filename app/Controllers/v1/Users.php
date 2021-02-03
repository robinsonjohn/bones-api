<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\UserCollection;
use App\Services\BonesAuth\Schemas\UserResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\RBAC\Exceptions\InvalidKeysException;
use Bayfront\RBAC\Exceptions\InvalidUserException;
use Bayfront\RBAC\Exceptions\LoginExistsException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;
use Exception;
use PDOException;

/**
 * Users controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Users extends ApiController
{

    /** @var BonesAuth $model */

    protected $model;

    /**
     * Groups constructor.
     *
     * @throws ControllerException
     * @throws NotFoundException
     * @throws ServiceException
     */

    public function __construct()
    {

        parent::__construct(true);

        // Define default model

        $this->model = $this->container->get('auth');

    }

    /**
     * Create new user.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _createUser(): void
    {

        // TODO: Check permissions, return 403

        // Get body

        $body = $this->api->getBody([
            'login',
            'password'
        ]); // Required keys

        /*
         * TODO
         * Filter what is able to be sent depending on permissions (eg: attributes)
         */

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'login',
            'password',
            'email',
            'attributes',
            'enabled'
        ]))) {

            abort(400, 'Unable to create user: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'login' => 'string',
                'password' => 'string',
                'email' => 'email',
                'attributes' => 'array', // TODO: Need to validate array||null
                'enabled' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Create user

        try {

            $id = $this->model->createUser($body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create user: invalid parameters');
            die;

        } catch (LoginExistsException $e) {

            abort(409, 'Unable to create user: login already exists');
            die;

        }

        log_info('User created', [
            'id' => $id
        ]);

        // user.create event

        do_event('user.create', $id);

        // Send response

        $schema = UserResource::create($this->model->getUser($id), [
            'object_prefix' => '/users'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Update user.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _updateUser(string $id): void
    {

        // TODO: Check permissions, return 403

        // Get body

        $body = $this->api->getBody();

        /*
         * TODO
         * Filter what is able to be sent depending on permissions (eg: attributes)
         */

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'login',
            'password',
            'email',
            'attributes',
            'enabled'
        ]))) {

            abort(400, 'Unable to update user: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'login' => 'string',
                'password' => 'string',
                'email' => 'email',
                'attributes' => 'array', // TODO: Need to validate array||null
                'enabled' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Update user

        try {

            $this->model->updateUser($id, $body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create user: invalid parameters');
            die;

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to update user: user ID does not exist');
            die;

        } catch (LoginExistsException $e) {

            abort(409, 'Unable to update user: login already exists');
            die;

        }

        log_info('User updated', [
            'id' => $id
        ]);

        // user.update event

        do_event('user.update', $id);

        // Send response

        $schema = UserResource::create($this->model->getUser($id), [
            'object_prefix' => '/users'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Get single user.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUser(string $id): void
    {

        // TODO: Check permissions, return 403

        // Get user

        try {

            $user = $this->model->getUser($id);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to get user: user ID does not exist');
            die;

        }

        // Send response

        $schema = UserResource::create($user, [
            'object_prefix' => '/users'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get users.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUsers(): void
    {

        $request = $this->api->parseQuery(Request::getQuery(), $this->getPageSize());

        /*
         * TODO: Check permissions
         * Manipulate the $request array according to permissions (eg: WHERE...)
         *      - Check fields and filters
         *      - Return 403
         */

        /*
         * Check that only valid fields have been requested.
         * These fields should match what is available to be
         * returned in the schema.
         */

        if (isset($request['fields']['users'])) {

            if (!empty(Arr::except(array_flip($request['fields']['users']), [
                'id',
                'login',
                'email',
                'enabled',
                'created_at',
                'updated_at'
            ]))) {

                abort(400, 'Unable to get users: query string contains invalid fields');
                die;

            }

        }

        try {

            $users = $this->model->getUsersCollection($request);

        } catch (QueryException|BadRequestException|PDOException $e) {

            abort(400, 'Unable to get users: invalid request');
            die;

        }

        // Send response

        $schema = UserCollection::create([
            'results' => $users['results'],
            'meta' => $users['meta']
        ], [
            'object_prefix' => '/users',
            'collection_prefix' => '/users'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Delete user.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _deleteUser(string $id): void
    {

        // TODO: Check permissions

        // Delete user

        $deleted = $this->model->deleteUser($id);

        if ($deleted) {

            log_info('User deleted', [
                'id' => $id
            ]);

            // user.delete event

            do_event('user.delete', $id);

            // Send response

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete user: user ID does not exist');
            die;

        }

    }

    /*
     * ############################################################
     * Public methods
     * ############################################################
     */

    /**
     * Router destination.
     *
     * @param array $params
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     */

    public function index(array $params)
    {

        $this->api->allowedMethods([
            'POST',
            'GET',
            'PATCH',
            'DELETE'
        ]);

        if (Request::isPost()) {

            if (isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_createUser();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single user

                $this->_getUser($params['id']);

            } else { // Get all users

                $this->_getUsers();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateUser($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deleteUser($params['id']);

        }

    }

}