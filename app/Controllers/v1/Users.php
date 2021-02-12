<?php

namespace App\Controllers\v1;

use App\Schemas\GroupCollection;
use App\Schemas\PermissionCollection;
use App\Schemas\RoleCollection;
use App\Schemas\UserCollection;
use App\Schemas\UserMetaCollection;
use App\Schemas\UserMetaResource;
use App\Schemas\UserResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\RBAC\Exceptions\InvalidGrantException;
use Bayfront\RBAC\Exceptions\InvalidKeysException;
use Bayfront\RBAC\Exceptions\InvalidMetaException;
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

    /**
     * Groups constructor.
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

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.create')) {

            abort(403, 'Unable to create user: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])
            || !empty(Arr::except($body['data'], [ // Valid members
                'type',
                'attributes'
            ]))
            || $body['data']['type'] != 'users'
            || !is_array($body['data']['attributes'])
            || !empty(Arr::except($body['data']['attributes'], [ // Valid members
                'login',
                'password',
                'firstName',
                'lastName',
                'companyName',
                'email',
                'enabled'
            ]))
            || Arr::isMissing($body['data']['attributes'], [ // Required members
                'login',
                'password'
            ])) {

            abort(400, 'Unable to create user: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [
                'login' => 'string',
                'password' => 'string',
                'firstName' => 'string|null',
                'lastName' => 'string|null',
                'companyName' => 'string|null',
                'email' => 'email|null',
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

            $id = $this->auth->createUser($body['data']['attributes']);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create user: invalid members');
            die;

        } catch (LoginExistsException $e) {

            abort(409, 'Unable to create user: login already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('User created', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('user.create', $id);

        /*
         * Build schema
         */

        $schema = UserResource::create($this->auth->getUser($id), [
            'object_prefix' => '/users'
        ]);

        /*
         * Send response
         */

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

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.update',
                'group.users.update',
                'self.users.update'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.update',
                    'group.users.update'
                ]) && $id != $this->user_id)
            || (!$this->hasPermissions('global.users.update') // If only group and not in group
                && $this->hasPermissions('group.users.update')
                && !in_array($id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to update user: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody([
            'data'
        ]); // Required members

        if (!empty(Arr::except($body, 'data')) // Valid members
            || !is_array($body['data'])
            || !empty(Arr::except($body['data'], [ // Valid members
                'type',
                'id',
                'attributes'
            ]))
            || $body['data']['type'] != 'users'
            || $body['data']['id'] != $id
            || !is_array($body['data']['attributes'])
            || !empty(Arr::except($body['data']['attributes'], [ // Valid members
                'login',
                'password',
                'firstName',
                'lastName',
                'companyName',
                'email',
                'enabled'
            ]))) {

            abort(400, 'Unable to update user: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [
                'login' => 'string',
                'password' => 'string',
                'firstName' => 'string|null',
                'lastName' => 'string|null',
                'companyName' => 'string|null',
                'email' => 'email|null',
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

            $this->auth->updateUser($id, $body['data']['attributes']);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to update user: invalid members');
            die;

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to update user: user ID does not exist');
            die;

        } catch (LoginExistsException $e) {

            abort(409, 'Unable to update user: login already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('User updated', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('user.update', $id);

        /*
         * Build schema
         */

        $schema = UserResource::create($this->auth->getUser($id), [
            'object_prefix' => '/users'
        ]);

        /*
         * Send response
         */

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

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.read',
                'group.users.read',
                'self.users.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.read',
                    'group.users.read'
                ]) && $id != $this->user_id)
            || (!$this->hasPermissions('global.users.read') // If only group and not in group
                && $this->hasPermissions('group.users.read')
                && !in_array($id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user: insufficient permissions');
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

            abort(400, 'Unable to get user: query string contains invalid fields');
            die;

        }

        /*
         * Get data
         */

        try {

            $user = $this->auth->getUser($id);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to get user: user ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        if (isset($request['fields']['users'])) {

            $request = $this->requireValues($request, 'fields.users', 'id');

            $user = Arr::only($user, $request['fields']['users']);

        }

        /*
         * Build schema
         */

        $schema = UserResource::create($user, [
            'object_prefix' => '/users'
        ]);

        /*
         * Send response
         */

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

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
            'global.users.read',
            'group.users.read'
        ])) {

            abort(403, 'Unable to get users: insufficient permissions');
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

            abort(400, 'Unable to get users: query string contains invalid fields');
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

            if (!$this->hasPermissions('global.users.read')) {

                $users = $this->auth->getUsersCollection($request, $this->user_groups); // Limit users to user's groups

            } else {

                $users = $this->auth->getUsersCollection($request); // Get all users

            }

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get users: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = UserCollection::create($users, [
            'object_prefix' => '/users',
            'collection_prefix' => '/users'
        ]);

        /*
         * Send response
         */

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

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.delete',
                'group.users.delete',
                'self.users.delete'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.delete',
                    'group.users.delete'
                ]) && $id != $this->user_id)
            || (!$this->hasPermissions('global.users.delete') // If only group and not in group
                && $this->hasPermissions('group.users.delete')
                && !in_array($id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to delete user: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $deleted = $this->auth->deleteUser($id);

        if ($deleted) {

            /*
             * Log action
             */

            log_info('User deleted', [
                'id' => $id
            ]);

            /*
             * Do event
             */

            do_event('user.delete', $id);

            /*
             * Send response
             */

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete user: user ID does not exist');
            die;

        }

    }

    /**
     * Get user permissions.
     *
     * @param string $user_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserPermissions(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.permissions.read',
                'group.users.permissions.read',
                'self.users.permissions.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.permissions.read',
                    'group.users.permissions.read',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.permissions.read') // If only group and not in group
                && $this->hasPermissions('group.users.permissions.read')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user permissions: insufficient permissions');
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

            abort(400, 'Unable to get user permissions: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to get user permissions: user ID does not exist');
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

            $permissions = $this->auth->getUserPermissionsCollection($request, $user_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user permissions: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = PermissionCollection::create($permissions, [
            'object_prefix' => '/permissions',
            'collection_prefix' => '/users/' . $user_id . '/permissions'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get user roles.
     *
     * @param string $user_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserRoles(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.roles.read',
                'group.users.roles.read',
                'self.users.roles.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.roles.read',
                    'group.users.roles.read',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.roles.read') // If only group and not in group
                && $this->hasPermissions('group.users.roles.read')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user roles: insufficient permissions');
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

            abort(400, 'Unable to get user roles: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to get user roles: user ID does not exist');
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

            $roles = $this->auth->getUserRolesCollection($request, $user_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user roles: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = RoleCollection::create($roles, [
            'object_prefix' => '/roles',
            'collection_prefix' => '/users/' . $user_id . '/roles'
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
     * @param string $user_id
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserRole(string $user_id, string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.grant')) {

            abort(403, 'Unable to grant role to user: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to grant role to user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantUserRoles($user_id, $role_id);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant role to user: role ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted role to user', [
            'user_id' => $user_id,
            'roles' => [
                $role_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.roles.grant', $user_id, [
            $role_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Grant roles to user. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserRoles(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.grant')) {

            abort(403, 'Unable to grant roles to user: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'roles'
        ]))) {

            abort(400, 'Unable to grant roles to user: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'roles' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to grant roles to user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantUserRoles($user_id, $body['roles']);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant roles to user: role ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted roles to user', [
            'user_id' => $user_id,
            'roles' => $body['roles']
        ]);

        /*
         * Do event
         */

        do_event('user.roles.grant', $user_id, $body['roles']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke role from user.
     *
     * @param string $user_id
     * @param string $role_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserRole(string $user_id, string $role_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.revoke')) {

            abort(403, 'Unable to revoke role from user: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to revoke role from user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeUserRoles($user_id, $role_id);

        /*
         * Log action
         */

        log_info('Revoked role from user', [
            'user_id' => $user_id,
            'roles' => [
                $role_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.roles.revoke', $user_id, [
            $role_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke roles from user. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserRoles(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.revoke')) {

            abort(403, 'Unable to revoke roles from user: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'roles'
        ]))) {

            abort(400, 'Unable to revoke roles from user: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'roles' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to revoke roles from user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeUserRoles($user_id, $body['roles']);

        /*
         * Log action
         */

        log_info('Revoked roles from user', [
            'user_id' => $user_id,
            'roles' => $body['roles']
        ]);

        /*
         * Do event
         */

        do_event('user.roles.revoke', $user_id, $body['roles']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Get user groups.
     *
     * @param string $user_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserGroups(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.groups.read',
                'group.users.groups.read',
                'self.users.groups.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.groups.read',
                    'group.users.groups.read',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.groups.read') // If only group and not in group
                && $this->hasPermissions('group.users.groups.read')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user groups: insufficient permissions');
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
                'groups'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'groups', [])), [ // Valid fields
                'id',
                'name',
                'createdAt',
                'updatedAt'
            ]))) {

            abort(400, 'Unable to get user groups: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to get user groups: user ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        $request = $this->requireValues($request, 'fields.groups', 'id');

        /*
         * Get data
         */

        try {

            $groups = $this->auth->getUserGroupsCollection($request, $user_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user groups: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = GroupCollection::create($groups, [
            'object_prefix' => '/groups',
            'collection_prefix' => '/users/' . $user_id . '/groups'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Add user to group.
     *
     * @param string $user_id
     * @param string $group_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserGroup(string $user_id, string $group_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.grant',
                'self.users.groups.grant'
            ])
            || (!$this->hasPermissions('global.users.groups.grant') && $user_id != $this->user_id)) {

            abort(403, 'Unable to add user to group: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to add user to group: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantUserGroups($user_id, $group_id);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add user to group: group ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added user to group', [
            'user_id' => $user_id,
            'groups' => [
                $group_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.groups.grant', $user_id, [
            $group_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Add user to groups. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserGroups(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.grant',
                'self.users.groups.grant'
            ])
            || (!$this->hasPermissions('global.users.groups.grant') && $user_id != $this->user_id)) {

            abort(403, 'Unable to add user to groups: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'groups'
        ]))) {

            abort(400, 'Unable to add user to groups: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'groups' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to add user to groups: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantUserGroups($user_id, $body['groups']);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add user to groups: group ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added user to groups', [
            'user_id' => $user_id,
            'groups' => $body['groups']
        ]);

        /*
         * Do event
         */

        do_event('user.groups.grant', $user_id, $body['groups']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove user from group.
     *
     * @param string $user_id
     * @param string $group_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserGroup(string $user_id, string $group_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.revoke',
                'self.users.groups.revoke'
            ])
            || (!$this->hasPermissions('global.users.groups.revoke') && $user_id != $this->user_id)) {

            abort(403, 'Unable to remove user from group: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to remove user from group: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeUserGroups($user_id, $group_id);

        /*
         * Log action
         */

        log_info('Removed user from group', [
            'user_id' => $user_id,
            'groups' => [
                $group_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.groups.revoke', $user_id, [
            $group_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove user from groups. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserGroups(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.revoke',
                'self.users.groups.revoke'
            ])
            || (!$this->hasPermissions('global.users.groups.revoke') && $user_id != $this->user_id)) {

            abort(403, 'Unable to remove user from groups: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'groups'
        ]))) {

            abort(400, 'Unable to remove user from groups: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'groups' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to remove user from groups: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeUserGroups($user_id, $body['groups']);

        /*
         * Log action
         */

        log_info('Removed user from groups', [
            'user_id' => $user_id,
            'groups' => $body['groups']
        ]);

        /*
         * Do event
         */

        do_event('user.groups.revoke', $user_id, $body['groups']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Get single user meta.
     *
     * @param string $user_id
     * @param string $meta_key
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserMeta(string $user_id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.read',
                'group.users.meta.read',
                'self.users.meta.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.read',
                    'group.users.meta.read',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.read') // If only group and not in group
                && $this->hasPermissions('group.users.meta.read')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user meta: insufficient permissions');
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
                'meta'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'meta', [])), [ // Valid fields
                'id',
                'value'
            ]))) {

            abort(400, 'Unable to get user meta: query string contains invalid fields');
            die;

        }

        /*
         * Get data
         */

        try {

            $meta = [
                'id' => $meta_key,
                'value' => $this->auth->getUserMeta($user_id, $meta_key)
            ];

        } catch (InvalidMetaException $e) {

            abort(404, 'Unable to get user meta: meta ID does not exist for user');
            die;

        }

        /*
         * Filter fields
         */

        if (isset($request['fields']['meta'])) {

            $request = $this->requireValues($request, 'fields.meta', 'id');

            $meta = Arr::only($meta, $request['fields']['meta']);

        }

        /*
         * Sanitize fields
         */

        if (isset($meta['value']) && Validate::json($meta['value'])) {
            $meta['value'] = json_decode($meta['value'], true);
        }

        foreach ($meta as $k => $v) {

            if ($k == 'id') {
                $meta['metaKey'] = $v;
                unset($meta[$k]);
            }

            if ($k == 'value') {
                $meta['metaValue'] = $v;
                unset($meta[$k]);
            }

        }

        /*
         * Build schema
         */

        $schema = UserMetaResource::create($meta, [
            'object_prefix' => '/users/' . $user_id . '/meta',
            'collection_prefix' => '/users/' . $user_id . '/meta'
        ]);


        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get all user meta.
     *
     * @param string $user_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getAllUserMeta(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.read',
                'group.users.meta.read',
                'self.users.meta.read'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.read',
                    'group.users.meta.read',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.read') // If only group and not in group
                && $this->hasPermissions('group.users.meta.read')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to get user meta: insufficient permissions');
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
                'meta'
            ])) || !empty(Arr::except(array_flip(Arr::get($request['fields'], 'meta', [])), [ // Valid fields
                'id',
                'value'
            ]))) {

            abort(400, 'Unable to get user meta: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to get user meta: user ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        $request = $this->requireValues($request, 'fields.meta', 'id');

        /*
         * Convert keys
         *
         * Externally, the "metaKey" and "metaValue" columns are referenced by "id" and "value", respectively.
         * So they must be renamed before being handled by the query builder.
         */

        $replacements = [
            'id' => 'metaKey',
            'value' => 'metaValue'
        ];

        /*
         * Fields
         */

        foreach (Arr::get($request, 'fields.meta', []) as $k => $col) {

            if (array_key_exists($col, $replacements)) {

                $request['fields']['meta'][$k] = $replacements[$col];

            }

        }

        // Filters

        foreach (Arr::get($request, 'filters', []) as $col => $v) {

            if (array_key_exists($col, $replacements)) {

                $request['filters'][$replacements[$col]] = $v;

                unset($request['filters'][$col]);

            }

        }

        // Order by

        foreach (Arr::get($request, 'order_by', []) as $k => $col) {

            $trimmed = ltrim($col, '-');

            if (array_key_exists($trimmed, $replacements)) {

                $request['order_by'][$k] = str_replace($trimmed, $replacements[$trimmed], $col);

            }

        }

        /*
         * Get data
         */

        try {

            $meta = $this->auth->getUserMetaCollection($request, $user_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user meta: invalid request');
            die;

        }

        /*
         * Sanitize fields
         */

        foreach ($meta['results'] as $k => $v) {

            if (isset($v['metaValue']) && Validate::json($v['metaValue'])) {

                $meta['results'][$k]['metaValue'] = json_decode($v['metaValue'], true);

            }

        }

        /*
         * Build schema
         */

        $schema = UserMetaCollection::create($meta, [
            'object_prefix' => '/users/' . $user_id . '/meta',
            'collection_prefix' => '/users/' . $user_id . '/meta'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Update user meta.
     *
     * @param string $user_id
     * @param string $meta_key
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateUserMeta(string $user_id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.update',
                'group.users.meta.update',
                'self.users.meta.update'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.update',
                    'group.users.meta.update',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.update') // If only group and not in group
                && $this->hasPermissions('group.users.meta.update')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to update user meta: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'value'
        ]))) {

            abort(400, 'Unable to update user meta: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         *
         * No need, as meta value can be a variety of types.
         */

        /*
         * Sanitize body
         */

        if (is_array($body['value'])) {

            $body['value'] = json_encode($body['value']);

        }

        /*
         * Perform action
         */

        try {

            $this->auth->setUserMeta($user_id, [
                $meta_key => $body['value']
            ]);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to update user meta: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Updated user meta', [
            'user_id' => $user_id,
            'keys' => [
                $meta_key
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.meta.update', $user_id, [
            $meta_key
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Set user meta. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateUserMetas(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.update',
                'group.users.meta.update',
                'self.users.meta.update'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.update',
                    'group.users.meta.update',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.update') // If only group and not in group
                && $this->hasPermissions('group.users.meta.update')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to update user meta: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        /*
         * Validate & sanitize body
         */

        $metas = [];

        foreach ($body as $k => $meta) {

            if (!is_array($meta) || !isset($meta['id']) || !isset($meta['value'])) {

                abort(400, 'Unable to update user meta: request body contains invalid members');

            }

            if (is_array($meta['value'])) {

                $body[$k]['value'] = json_encode($meta['value']);

            }

            $metas[$meta['id']] = $meta['value'];

        }

        /*
         * Perform action
         */

        try {

            $this->auth->setUserMeta($user_id, $metas);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to update user meta: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Updated user meta', [
            'user_id' => $user_id,
            'keys' => array_keys($metas)
        ]);

        /*
         * Do event
         */

        do_event('user.meta.update', $user_id, array_keys($metas));

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Delete user meta.
     *
     * @param string $user_id
     * @param string $meta_key
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _deleteUserMeta(string $user_id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.delete',
                'group.users.meta.delete',
                'self.users.meta.delete'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.delete',
                    'group.users.meta.delete',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.delete') // If only group and not in group
                && $this->hasPermissions('group.users.meta.delete')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to delete user meta: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to delete user meta: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->deleteUserMeta($user_id, [
            $meta_key
        ]);

        /*
         * Log action
         */

        log_info('Deleted user meta', [
            'user_id' => $user_id,
            'meta_keys' => [
                $meta_key
            ]
        ]);

        /*
         * Do event
         */

        do_event('user.meta.delete', $user_id, [
            $meta_key
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Delete user meta. (batch)
     *
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _deleteUserMetas(string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.users.meta.delete',
                'group.users.meta.delete',
                'self.users.meta.delete'
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.users.meta.delete',
                    'group.users.meta.delete',
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.users.meta.delete') // If only group and not in group
                && $this->hasPermissions('group.users.meta.delete')
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            abort(403, 'Unable to delete user meta: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'meta'
        ]))) {

            abort(400, 'Unable to delete user meta: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'meta' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($user_id)) {

            abort(404, 'Unable to delete user meta: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->deleteUserMeta($user_id, $body['meta']);

        /*
         * Log action
         */

        log_info('Deleted user meta', [
            'user_id' => $user_id,
            'meta_keys' => $body['meta']
        ]);

        /*
         * Do event
         */

        do_event('user.meta.delete', $user_id, $body['meta']);

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
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
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
     */

    public function permissions(array $params): void
    {

        $this->api->allowedMethods([
            'GET'
        ]);

        if (!isset($params['user_id'])) {
            abort(400);
            die;
        }

        $this->_getUserPermissions($params['user_id']);

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

    public function roles(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'PUT',
            'DELETE'
        ]);

        if (!isset($params['user_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getUserRoles($params['user_id']);

        } else if (Request::isPut()) {

            if (isset($params['role_id'])) { // Single role

                $this->_grantUserRole($params['user_id'], $params['role_id']);

            } else { // Multiple roles

                $this->_grantUserRoles($params['user_id']);

            }

        } else { // Delete

            if (isset($params['role_id'])) { // Single role

                $this->_revokeUserRole($params['user_id'], $params['role_id']);

            } else { // Multiple roles

                $this->_revokeUserRoles($params['user_id']);

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

    public function groups(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'PUT',
            'DELETE'
        ]);

        if (!isset($params['user_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getUserGroups($params['user_id']);

        } else if (Request::isPut()) {

            if (isset($params['group_id'])) { // Single group

                $this->_grantUserGroup($params['user_id'], $params['group_id']);

            } else { // Multiple groups

                $this->_grantUserGroups($params['user_id']);

            }

        } else { // Delete

            if (isset($params['group_id'])) { // Single group

                $this->_revokeUserGroup($params['user_id'], $params['group_id']);

            } else { // Multiple groups

                $this->_revokeUserGroups($params['user_id']);

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

    public function meta(array $params): void
    {

        $this->api->allowedMethods([
            'GET',
            'PUT',
            'DELETE'
        ]);

        if (!isset($params['user_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            if (isset($params['meta_key'])) { // Single key

                $this->_getUserMeta($params['user_id'], $params['meta_key']);

            } else { // All keys

                $this->_getAllUserMeta($params['user_id']);

            }

        } else if (Request::isPut()) {

            if (isset($params['meta_key'])) { // Single key

                $this->_updateUserMeta($params['user_id'], $params['meta_key']);

            } else { // Multiple keys

                $this->_updateUserMetas($params['user_id']);

            }

        } else { // Delete

            if (isset($params['meta_key'])) { // Single key

                $this->_deleteUserMeta($params['user_id'], $params['meta_key']);

            } else { // Multiple keys

                $this->_deleteUserMetas($params['user_id']);

            }

        }

    }

}