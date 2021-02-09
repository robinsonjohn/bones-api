<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Controllers\v1;

use App\Schemas\RoleCollection;
use App\Schemas\RoleResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\RBAC\Exceptions\InvalidKeysException;
use Bayfront\RBAC\Exceptions\InvalidRoleException;
use Bayfront\RBAC\Exceptions\NameExistsException;
use Bayfront\Validator\Validate;
use Bayfront\Validator\ValidationException;
use PDOException;

/**
 * Roles controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Roles extends ApiController
{

    /**
     * Roles constructor.
     *
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ServiceException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {
        parent::__construct(true);
    }

    /**
     * Create new role.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidRoleException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _createRole(): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.create')) {

            abort(403, 'Unable to create role: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody([
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'name',
            'enabled'
        ]))) {

            abort(400, 'Unable to create role: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'name' => 'string',
                'enabled' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $id = $this->auth->createRole($body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create role: invalid members');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to create role: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Role created', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('role.create', $id);

        /*
         * Build schema
         */

        $schema = RoleResource::create($this->auth->getRole($id), [
            'object_prefix' => '/roles'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

    }

    /**
     * Update role.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidRoleException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateRole(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.update')) {

            abort(403, 'Unable to update role: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'name',
            'enabled'
        ]))) {

            abort(400, 'Unable to update role: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'name' => 'string',
                'enabled' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->updateRole($id, $body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to update role: invalid members');
            die;

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to update role: role ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to update role: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Role updated', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('role.update', $id);

        /*
         * Build schema
         */

        $schema = RoleResource::create($this->auth->getRole($id), [
            'object_prefix' => '/roles'
        ]);

        /*
         * Send response
         */

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
     */

    protected function _getRole(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.roles.read',
                'self.roles.read'
            ]) || (!$this->hasPermissions('global.roles.read')
                && !in_array($id, Arr::pluck($this->auth->getUserRoles($this->user_id), 'id')))) {

            abort(403, 'Unable to get role: insufficient permissions');
            die;

        }

        /*
         * Get request
         */

        $request = $this->api->parseQuery(
            Request::getQuery(),
            Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10)),
            get_config('api.max_page_size', 100)
        );

        /*
         * Validate field types and fields
         *
         * Valid fields should match what is available to be
         * returned in the schema.
         */

        if (!empty(Arr::except($request['fields'], [ // Valid field types
                'roles'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'roles', [])), [ // Valid fields
                'id',
                'name',
                'enabled',
                'createdAt',
                'updatedAt'
            ]))) {

            abort(400, 'Unable to get role: query string contains invalid fields');
            die;

        }

        /*
         * Get data
         */

        try {

            $role = $this->auth->getRole($id);

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to get role: role ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        if (isset($request['fields']['roles'])) {

            $request = $this->requireValues($request, 'fields.roles', 'id');

            $role = Arr::only($role, $request['fields']['roles']);

        }

        /*
         * Build schema
         */

        $schema = RoleResource::create($role, [
            'object_prefix' => '/roles'
        ]);

        /*
         * Send response
         */

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

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
            'global.roles.read',
            'self.roles.read'
        ])) {

            abort(403, 'Unable to get roles: insufficient permissions');
            die;

        }

        /*
         * Get request
         */

        $request = $this->api->parseQuery(
            Request::getQuery(),
            Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10)),
            get_config('api.max_page_size', 100)
        );

        /*
         * Validate field types and fields
         *
         * Valid fields should match what is available to be
         * returned in the schema.
         */

        if (!empty(Arr::except($request['fields'], [ // Valid field types
                'roles'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'roles', [])), [ // Valid fields
                'id',
                'name',
                'enabled',
                'createdAt',
                'updatedAt'
            ]))) {

            abort(400, 'Unable to get roles: query string contains invalid fields');
            die;

        }

        /*
         * Filter fields
         */

        $request = $this->requireValues($request, 'fields.roles', 'id');

        /*
         * Get data
         */

        try {

            if (!$this->hasPermissions('global.roles.read')) {

                $roles = $this->auth->getRolesCollection($request, Arr::pluck($this->auth->getUserRoles($this->user_id), 'id')); // Limit to user's roles

            } else {

                $roles = $this->auth->getRolesCollection($request); // Get all roles

            }

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get roles: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = RoleCollection::create($roles, [
            'object_prefix' => '/roles',
            'collection_prefix' => '/roles'
        ]);

        /*
         * Send response
         */

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
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _deleteRole(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.delete')) {

            abort(403, 'Unable to delete role: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $deleted = $this->auth->deleteRole($id);

        if ($deleted) {

            /*
             * Log action
             */

            log_info('Role deleted', [
                'id' => $id
            ]);

            /*
             * Do event
             */

            do_event('role.delete', $id);

            /*
             * Send response
             */

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
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidRoleException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
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