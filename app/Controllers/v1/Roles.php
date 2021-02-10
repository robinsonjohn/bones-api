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

    /**
     * Get permissions of role.
     *
     * @param string $role_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRolePermissions(string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.roles.permissions.read',
                'self.roles.permissions.read'
            ])
            || (!$this->hasPermissions('global.roles.permissions.read')
                && !in_array($role_id, Arr::pluck($this->auth->getUserRoles($this->user_id), 'id')))) {

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
                'id',
                'name',
                'description'
            ]))) {

            abort(400, 'Unable to get permissions of role: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($role_id)) {

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

            $permissions = $this->auth->getRolePermissionsCollection($request, $role_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get permissions of role: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = PermissionCollection::create($permissions, [
            'object_prefix' => '/permissions',
            'collection_prefix' => '/roles/' . $role_id . '/permissions'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }


    /**
     * Add permission to role.
     *
     * @param string $role_id
     * @param string $permission_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRolePermission(string $role_id, string $permission_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.grant')) {

            abort(403, 'Unable to add permission to role: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($role_id)) {

            abort(404, 'Unable to add permission to role: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantRolePermissions($role_id, $permission_id);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add permission to role: permission ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added permission to role', [
            'role_id' => $role_id,
            'permissions' => [
                $permission_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.grant', $role_id, [
            $permission_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Add permissions to role. (batch)
     *
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRolePermissions(string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.grant')) {

            abort(403, 'Unable to add permissions to role: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to add permissions to role: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'permissions' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($role_id)) {

            abort(404, 'Unable to add permissions to role: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantRolePermissions($role_id, $body['permissions']);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add permissions to role: permission ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added permissions to role', [
            'role_id' => $role_id,
            'permissions' => $body['permissions']
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.grant', $role_id, $body['permissions']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove permission from role
     *
     * @param string $role_id
     * @param string $permission_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRolePermission(string $role_id, string $permission_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.revoke')) {

            abort(403, 'Unable to remove permission from role: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeRolePermissions($role_id, $permission_id);

        /*
         * Log action
         */

        log_info('Removed permission from role', [
            'role_id' => $role_id,
            'permissions' => [
                $permission_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.revoke', $role_id, [
            $permission_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove permissions from role. (batch)
     *
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRolePermissions(string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.permissions.revoke')) {

            abort(403, 'Unable to remove permissions from role: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to remove permissions from role: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'permissions' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeRolePermissions($role_id, $body['permissions']);

        /*
         * Log action
         */

        log_info('Removed permissions from role', [
            'role_id' => $role_id,
            'permissions' => $body['permissions']
        ]);

        /*
         * Do event
         */

        do_event('role.permissions.revoke', $role_id, $body['permissions']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Get users with role.
     *
     * @param string $role_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRoleUsers(string $role_id): void
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
                'id',
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

        if (!$this->auth->roleIdExists($role_id)) {

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

            $users = $this->auth->getRoleUsersCollection($request, $role_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get users with role: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = UserCollection::create($users, [
            'object_prefix' => '/users',
            'collection_prefix' => '/roles/' . $role_id . '/users'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }


    /**
     * Grant role to user.
     *
     * @param string $role_id
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRoleUser(string $role_id, string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.grant')) {

            abort(403, 'Unable to grant role to user: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($role_id)) {

            abort(404, 'Unable to grant role to user: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantRoleUsers($role_id, $user_id);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant role to user: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted role to user', [
            'role_id' => $role_id,
            'users' => [
                $user_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('role.users.grant', $role_id, [
            $user_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Grant role to users. (batch)
     *
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantRoleUsers(string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.grant')) {

            abort(403, 'Unable to grant role to users: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'users'
        ]))) {

            abort(400, 'Unable to grant role to users: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'users' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->roleIdExists($role_id)) {

            abort(404, 'Unable to grant role to users: role ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantRoleUsers($role_id, $body['users']);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant role to users: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted role to users', [
            'role_id' => $role_id,
            'users' => $body['users']
        ]);

        /*
         * Do event
         */

        do_event('role.users.grant', $role_id, $body['users']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke role from user.
     *
     * @param string $role_id
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRoleUser(string $role_id, string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.revoke')) {

            abort(403, 'Unable to revoke role from user: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeRoleUsers($role_id, $user_id);

        /*
         * Log action
         */

        log_info('Revoked role from user', [
            'role_id' => $role_id,
            'users' => [
                $user_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('role.users.revoke', $role_id, [
            $user_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke role from users. (batch)
     *
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRoleUsers(string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.roles.users.revoke')) {

            abort(403, 'Unable to revoke role from users: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'users'
        ]))) {

            abort(400, 'Unable to revoke role from users: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'users' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeRoleUsers($role_id, $body['users']);

        /*
         * Log action
         */

        log_info('Revoked role from users', [
            'role_id' => $role_id,
            'users' => $body['users']
        ]);

        /*
         * Do event
         */

        do_event('role.users.revoke', $role_id, $body['users']);

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
            'PUT',
            'DELETE'
        ]);

        if (!isset($params['role_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getRolePermissions($params['role_id']);

        } else if (Request::isPut()) {

            if (isset($params['permission_id'])) { // Single permission

                $this->_grantRolePermission($params['role_id'], $params['permission_id']);

            } else { // Multiple permissions

                $this->_grantRolePermissions($params['role_id']);

            }

        } else { // Delete

            if (isset($params['permission_id'])) { // Single permission

                $this->_revokeRolePermission($params['role_id'], $params['permission_id']);

            } else { // Multiple permissions

                $this->_revokeRolePermissions($params['role_id']);

            }

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
            'PUT',
            'DELETE'
        ]);

        if (!isset($params['role_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getRoleUsers($params['role_id']);

        } else if (Request::isPut()) {

            if (isset($params['user_id'])) { // Single user

                $this->_grantRoleUser($params['role_id'], $params['user_id']);

            } else { // Multiple permissions

                $this->_grantRoleUsers($params['role_id']);

            }

        } else { // Delete

            if (isset($params['user_id'])) { // Single user

                $this->_revokeRoleUser($params['role_id'], $params['user_id']);

            } else { // Multiple users

                $this->_revokeRoleUsers($params['role_id']);

            }

        }

    }

}