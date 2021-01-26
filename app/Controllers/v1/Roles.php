<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\RoleCollection;
use App\Services\BonesAuth\Schemas\RoleResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Exceptions\IdExistsException;
use Bayfront\Auth\Exceptions\InvalidConfigurationException;
use Bayfront\Auth\Exceptions\InvalidEntityException;
use Bayfront\Auth\Exceptions\InvalidRoleException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\NameExistsException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
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
 * Roles controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Roles extends ApiController
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
     * Create new role.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws InvalidConfigurationException
     * @throws IdExistsException
     * @throws InvalidRoleException
     */

    protected function _createRole(): void
    {

        // Get body

        $body = $this->api->getBody([
            'entity_id',
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'entity_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to create role: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'entity_id' => 'string',
                'name' => 'string',
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Create role

        try {

            $id = $this->model->createRole($body);

        } catch (InvalidEntityException $e) {

            abort(400, 'Unable to create role: entity ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create role: role name already exists');
            die;

        }

        // role.create event

        do_event('role.create', $id);

        // Send response

        $schema = RoleResource::create($this->model->getRole($id), [
            'link_prefix' => '/roles'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Update role.
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
     * @throws InvalidRoleException
     */

    protected function _updateRole(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'entity_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to update role: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'entity_id' => 'string',
                'name' => 'string',
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Update role

        try {

            $this->model->updateRole($id, $body);

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to update role: role ID does not exist');
            die;

        } catch (InvalidEntityException $e) {

            abort(400, 'Unable to update role: entity ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update role: role name already exists');
            die;

        }

        // role.update event

        do_event('role.update', $id);

        // Send response

        $schema = RoleResource::create($this->model->getRole($id), [
            'link_prefix' => '/roles'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Get single role.
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

    protected function _getRole(string $id): void
    {

        // Get role

        try {

            $role = $this->model->getRole($id);

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to get role: role ID does not exist');
            die;

        }

        // Send response

        $schema = RoleResource::create($role, [
            'link_prefix' => '/roles'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get roles.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRoles(): void
    {

        // Get roles

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $roles = $this->model->getRoleCollection($request);

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get roles: invalid request');
            die;

        }

        // Send response

        $schema = RoleCollection::create([
            'results' => $roles['results'],
            'meta' => $roles['meta']
        ], [
            'link_prefix' => '/roles'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Delete role.
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

    protected function _deleteRole(string $id): void
    {

        // Delete role

        $deleted = $this->model->deleteRole($id);

        if ($deleted) {

            // role.delete event

            do_event('role.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete role: role ID does not exist');
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
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws InvalidConfigurationException
     * @throws IdExistsException
     * @throws InvalidRoleException
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

            $this->_createRole();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single role

                $this->_getRole($params['id']);

            } else { // Get all roles

                $this->_getRoles();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateRole($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deleteRole($params['id']);

        }

    }

}