<?php

namespace App\Controllers\v1;

use App\Exceptions\InvalidRequestException;
use App\Models\UserAuthModel;
use App\Schemas\UserCollection;
use App\Schemas\UserResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\IdExistsException;
use Bayfront\Auth\Exceptions\InvalidConfigurationException;
use Bayfront\Auth\Exceptions\InvalidEntityException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\LoginExistsException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ModelException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;

/**
 * Users controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Users extends ApiController
{

    /** @var Auth $model */

    protected $model;

    /**
     * Groups constructor.
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidEntityException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws QueryException
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
     * @throws HttpException
     * @throws IdExistsException
     * @throws InvalidConfigurationException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws QueryException
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

        // user.create event

        do_event('user.create', $id);

        // Send response

        $schema = UserResource::create($this->model->getUser($id), [
            'link_prefix' => '/users'
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
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws InvalidUserException
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

        // user.update event

        do_event('user.update', $id);

        // Send response

        $schema = UserResource::create($this->model->getUser($id), [
            'link_prefix' => '/users'
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
     * @throws QueryException
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
            'link_prefix' => '/users'
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
     * @throws ModelException
     */

    protected function _getUsers(): void
    {

        // Get users

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        $request = $this->api->parseQuery(Request::getQuery(), $page_size);

        /** @var UserAuthModel $model */

        $model = get_model('UserAuthModel');

        try {

            $users = $model->getUsers($request);

        } catch (QueryException|InvalidRequestException $e) {

            abort(400, 'Unable to get users: invalid request');
            die;

        }

        // Send response

        $schema = UserCollection::create([
            'users' => $users['results'],
            'page' => [
                'count' => count($users['results']),
                'total' => $users['total'],
                'pages' => ceil($users['total'] / $page_size),
                'page_size' => $page_size,
                'page_number' => ($request['offset'] / $request['limit']) + 1
            ]
        ], [
            'link_prefix' => '/users'
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
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _deleteUser(string $id): void
    {

        // Delete user

        $deleted = $this->model->deleteUser($id);

        if ($deleted) {

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
     * @throws HttpException
     * @throws InvalidConfigurationException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws ModelException
     * @throws NotFoundException
     * @throws QueryException
     * @throws IdExistsException
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