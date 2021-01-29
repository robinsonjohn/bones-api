<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\UserCollection;
use App\Services\BonesAuth\Schemas\UserResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Exceptions\IdExistsException;
use Bayfront\Auth\Exceptions\InvalidKeysException;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\LoginExistsException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;
use Exception;

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
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidOrganizationException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws ServiceException
     */

    public function __construct()
    {

        parent::__construct();

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
     * @throws IdExistsException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws InvalidKeysException
     */

    protected function _createUser(): void
    {

        // Get body

        $body = $this->api->getBody([
            'login',
            'password'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'login',
            'password',
            'email',
            'attributes',
            'active'
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
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Create user

        try {

            $id = $this->model->createUser($body);

        } catch (LoginExistsException $e) {

            abort(400, 'Unable to create user: user login already exists');
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

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'login',
            'password',
            'email',
            'attributes',
            'active'
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
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Update user

        try {

            $this->model->updateUser($id, $body);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to update user: user ID does not exist');
            die;

        } catch (LoginExistsException $e) {

            abort(400, 'Unable to update user: user login already exists');
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

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $this->getPageSize());

            $users = $this->model->getUserCollection($request);

        } catch (HttpException|QueryException|BadRequestException $e) {

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

        // Delete user

        $deleted = $this->model->deleteUser($id);

        if ($deleted) {

            log_info('User deleted', [
                'id' => $id
            ]);

            // user.delete event

            do_event('user.delete', $id);

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
     * @throws IdExistsException
     * @throws InvalidKeysException
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