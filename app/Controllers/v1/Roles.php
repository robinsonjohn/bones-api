<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Controllers\v1;

use App\Schemas\PermissionCollection;
use App\Schemas\RoleCollection;
use App\Schemas\RoleResource;
use App\Schemas\UserCollection;
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
use Bayfront\RBAC\Exceptions\InvalidGrantException;
use Bayfront\RBAC\Exceptions\InvalidKeysException;
use Bayfront\RBAC\Exceptions\InvalidRoleException;
use Bayfront\RBAC\Exceptions\NameExistsException;
use Bayfront\Validator\Validate;
use Bayfront\Validator\ValidationException;
use Exception;
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
            'object_prefix' => $this->base_uri . '/roles'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600', // 1 hour
            'Expires' => gmdate('D, d M Y H:i:s T', time() + 3600)
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
            'object_prefix' => $this->base_uri . '/roles',
            'collection_prefix' => $this->base_uri . '/roles'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600', // 1 hour
            'Expires' => gmdate('D, d M Y H:i:s T', time() + 3600)
        ])->sendJson($schema);

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

        $body = $this->api->getBody();

        if (!$this->api->isValidResource($body, [ // Valid attributes
                'name',
                'enabled'
            ], [ // Required attributes
                'name'
            ])
            || isset($body['data']['id'])) {

            abort(400, 'Unable to create role: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'roles') {

            abort(409, 'Unable to create role: invalid resource type');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [ // Valid members
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

            $id = $this->auth->createRole($body['data']['attributes']);

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
            'object_prefix' => $this->base_uri . '/roles'
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(201)
            ->setHeaders([
                'Location' => $this->base_uri . '/roles/' . $id
            ])
            ->sendJson($schema);

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

        if (!$this->api->isValidResource($body, [ // Valid attributes
            'name',
            'enabled'
        ], [] // Required attributes
        )) {

            abort(400, 'Unable to update role: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'roles'
            || Arr::get($body, 'data.id') != $id) {

            abort(409, 'Unable to update role: invalid resource type and/or ID');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [
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

            $this->auth->updateRole($id, $body['data']['attributes']);

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
            'object_prefix' => $this->base_uri . '/roles'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

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

    /**
     * Get permissions of role.
     *
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRolePermissions(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.roles.permissions.read',
                'self.roles.permissions.read'
            ])
            || (!$this->hasPermissions('global.roles.permissions.read')
                && !in_array($id, Arr::pluck($this->auth->getUserRoles($this->user_id), 'id')))) {

            abort(403, 'Unable to get permissions of role: insufficient permissions');
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
                'permissions'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'permissions', [])), [ // Valid fields
                'name',
                'description'
            ]))) {

            abort(400, 'Unable to get permissions of role: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to get permissions of role: role ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        $request = $this->requireValues($request, 'fields.permissions', 'id');

        /*
         * Get data
         */

        try {

            $permissions = $this->auth->getRolePermissionsCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get permissions of role: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = PermissionCollection::create($permissions, [
            'object_prefix' => $this->base_uri . '/permissions',
            'collection_prefix' => $this->base_uri . '/roles/' . $id . '/permissions'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600', // 1 hour
            'Expires' => gmdate('D, d M Y H:i:s T', time() + 3600)
        ])->sendJson($schema);

    }

    /**
     * Add permissions to role.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRolePermissions(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.grant')) {

            abort(403, 'Unable to add permissions to role: insufficient permissions');
            die;

        }

        /*
         * Get & validate body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])) {

            abort(400, 'Unable to add permissions to role: request body contains invalid members');
            die;

        }

        foreach ($body['data'] as $resource) {

            if (!empty(Arr::except($resource, [ // Valid members
                    'type',
                    'id'
                ]))
                || Arr::isMissing($resource, [ // Required members
                    'type',
                    'id'
                ])
                || $resource['type'] != 'permissions'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to add permissions to role: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to add permissions to role: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $permissions = Arr::pluck($body['data'], 'id');

        try {

            $this->auth->grantRolePermissions($id, $permissions);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add permissions to role: permission ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added permissions to role', [
            'id' => $id,
            'permissions' => $permissions
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.grant', $id, $permissions);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove permissions from role.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRolePermissions(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.revoke')) {

            abort(403, 'Unable to remove permissions from role: insufficient permissions');
            die;

        }

        /*
         * Get & validate body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])) {

            abort(400, 'Unable to remove permissions from role: request body contains invalid members');
            die;

        }

        foreach ($body['data'] as $resource) {

            if (!empty(Arr::except($resource, [ // Valid members
                    'type',
                    'id'
                ]))
                || Arr::isMissing($resource, [ // Required members
                    'type',
                    'id'
                ])
                || $resource['type'] != 'permissions'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to remove permissions from role: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to remove permissions from role: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $permissions = Arr::pluck($body['data'], 'id');

        $this->auth->revokeRolePermissions($id, $permissions);

        /*
         * Log action
         */

        log_info('Removed permissions from role', [
            'id' => $id,
            'permissions' => $permissions
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.revoke', $id, $permissions);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Get users with role.
     *
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRoleUsers(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.read')) {

            abort(403, 'Unable to get users with role: insufficient permissions');
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
                'users'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'users', [])), [ // Valid fields
                'login',
                'firstName',
                'lastName',
                'companyName',
                'email',
                'enabled',
                'createdAt',
                'updatedAt'
            ]))) {

            abort(400, 'Unable to get users with role: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to get users with role: role ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        $request = $this->requireValues($request, 'fields.users', 'id');

        /*
         * Get data
         */

        try {

            $users = $this->auth->getRoleUsersCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get users with role: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = UserCollection::create($users, [
            'object_prefix' => $this->base_uri . '/users',
            'collection_prefix' => $this->base_uri . '/roles/' . $id . '/users'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600', // 1 hour
            'Expires' => gmdate('D, d M Y H:i:s T', time() + 3600)
        ])->sendJson($schema);

    }

    /**
     * Grant role to users.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRoleUsers(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.grant')) {

            abort(403, 'Unable to grant role to users: insufficient permissions');
            die;

        }

        /*
         * Get & validate body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])) {

            abort(400, 'Unable to grant role to users: request body contains invalid members');
            die;

        }

        foreach ($body['data'] as $resource) {

            if (!empty(Arr::except($resource, [ // Valid members
                    'type',
                    'id'
                ]))
                || Arr::isMissing($resource, [ // Required members
                    'type',
                    'id'
                ])
                || $resource['type'] != 'users'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to grant role to users: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to grant role to users: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $users = Arr::pluck($body['data'], 'id');

        try {

            $this->auth->grantRoleUsers($id, $users);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant role to users: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted role to users', [
            'id' => $id,
            'users' => $users
        ]);

        /*
         * Do event
         */

        do_event('role.users.grant', $id, $users);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke role from users.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRoleUsers(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.revoke')) {

            abort(403, 'Unable to revoke role from users: insufficient permissions');
            die;

        }

        /*
         * Get & validate body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])) {

            abort(400, 'Unable to revoke role from users: request body contains invalid members');
            die;

        }

        foreach ($body['data'] as $resource) {

            if (!empty(Arr::except($resource, [ // Valid members
                    'type',
                    'id'
                ]))
                || Arr::isMissing($resource, [ // Required members
                    'type',
                    'id'
                ])
                || $resource['type'] != 'users'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to revoke role from users: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($id)) {

            abort(404, 'Unable to revoke role from users: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $users = Arr::pluck($body['data'], 'id');

        $this->auth->revokeRoleUsers($id, $users);

        /*
         * Log action
         */

        log_info('Revoked role from users', [
            'id' => $id,
            'users' => $users
        ]);

        /*
         * Do event
         */

        do_event('role.users.revoke', $id, $users);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

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

    public function index(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'POST',
            'PATCH',
            'DELETE'
        ]);

        if (Request::isGet()) {

            if (isset($params['id'])) { // Single role

                $this->_getRole($params['id']);

            } else { // Get all roles

                $this->_getRoles();

            }

        } else if (Request::isPost()) {

            if (isset($params['id'])) {
                abort(405, 'Request method (POST) not allowed');
                die;
            }

            $this->_createRole();

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(405, 'Request method (PATCH) not allowed');
                die;
            }

            $this->_updateRole($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(405, 'Request method (DELETE) not allowed');
                die;
            }

            $this->_deleteRole($params['id']);

        }

    }

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
     * @throws NotFoundException
     */

    public function permissions(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'POST',
            'DELETE'
        ]);

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getRolePermissions($params['id']);

        } else if (Request::isPost()) {

            $this->_grantRolePermissions($params['id']);

        } else { // Delete

            $this->_revokeRolePermissions($params['id']);

        }

    }

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
     * @throws NotFoundException
     */

    public function users(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'POST',
            'DELETE'
        ]);

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getRoleUsers($params['id']);

        } else if (Request::isPost()) {

            $this->_grantRoleUsers($params['id']);

        } else { // Delete

            $this->_revokeRoleUsers($params['id']);

        }

    }

}