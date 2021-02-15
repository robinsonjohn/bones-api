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
     * Check if user has global, group, or self permission
     * based on permission naming structure.
     *
     * @param string $user_id
     * @param string $permission
     *
     * @return bool
     */

    protected function _userCan(string $user_id, string $permission): bool
    {

        if (!$this->hasAnyPermissions([ // If no applicable permissions
                'global.' . $permission,
                'group.' . $permission,
                'self.' . $permission
            ])
            || (!$this->hasAnyPermissions([ // If only self does not match
                    'global.' . $permission,
                    'group.' . $permission,
                ]) && $user_id != $this->user_id)
            || (!$this->hasPermissions('global.' . $permission) // If only group and not in group
                && $this->hasPermissions('group.' . $permission)
                && !in_array($user_id, $this->getGroupedUserIds()))) {

            return false;

        }

        return true;

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

        $body = $this->api->getBody();

        if (!$this->api->isValidResource($body, [ // Valid attributes
                'login',
                'password',
                'firstName',
                'lastName',
                'companyName',
                'email',
                'enabled'
            ], [ // Required attributes
                'login',
                'password'
            ])
            || isset($body['data']['id'])) {

            abort(400, 'Unable to create user: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'users') {

            abort(409, 'Unable to create user: invalid resource type');
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
            'object_prefix' => $this->base_uri . '/users'
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(201)
            ->setHeaders([
                'Location' => $this->base_uri . '/users/' . $id
            ])
            ->sendJson($schema);

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

        if (!$this->_userCan($id, 'users.update')) {

            abort(403, 'Unable to update user: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!$this->api->isValidResource($body, [ // Valid attributes
            'login',
            'password',
            'firstName',
            'lastName',
            'companyName',
            'email',
            'enabled'
        ], [] // Required attributes
        )) {

            abort(400, 'Unable to update user: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'users'
            || Arr::get($body, 'data.id') != $id) {

            abort(409, 'Unable to update user: invalid resource type and/or ID');
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
            'object_prefix' => $this->base_uri . '/users'
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

        if (!$this->_userCan($id, 'users.read')) {

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
            'object_prefix' => $this->base_uri . '/users'
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
            'object_prefix' => $this->base_uri . '/users',
            'collection_prefix' => $this->base_uri . '/users'
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

        if (!$this->_userCan($id, 'users.delete')) {

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
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserPermissions(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.permissions.read')) {

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
                'name',
                'description'
            ]))) {

            abort(400, 'Unable to get user permissions: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

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

            $permissions = $this->auth->getUserPermissionsCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user permissions: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = PermissionCollection::create($permissions, [
            'object_prefix' => $this->base_uri . '/permissions',
            'collection_prefix' => $this->base_uri . '/users/' . $id . '/permissions'
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
     * Get user roles.
     *
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.roles.read')) {

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

        if (!$this->auth->userIdExists($id)) {

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

            $roles = $this->auth->getUserRolesCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user roles: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = RoleCollection::create($roles, [
            'object_prefix' => $this->base_uri . '/roles',
            'collection_prefix' => $this->base_uri . '/users/' . $id . '/roles'
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
     * Grant roles to user.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.grant')) {

            abort(403, 'Unable to grant roles to user: insufficient permissions');
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

            abort(400, 'Unable to grant roles to user: request body contains invalid members');
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
                || $resource['type'] != 'roles'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to grant roles to user: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

            abort(404, 'Unable to grant roles to user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $roles = Arr::pluck($body['data'], 'id');

        try {

            $this->auth->grantUserRoles($id, $roles);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant roles to user: role ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted roles to user', [
            'id' => $id,
            'roles' => $roles
        ]);

        /*
         * Do event
         */

        do_event('user.roles.grant', $id, $roles);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke roles from user.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.users.roles.revoke')) {

            abort(403, 'Unable to revoke roles from user: insufficient permissions');
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

            abort(400, 'Unable to revoke roles from user: request body contains invalid members');
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
                || $resource['type'] != 'roles'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to revoke roles from user: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

            abort(404, 'Unable to revoke roles from user: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $roles = Arr::pluck($body['data'], 'id');

        $this->auth->revokeUserRoles($id, $roles);

        /*
         * Log action
         */

        log_info('Revoked roles from user', [
            'id' => $id,
            'roles' => $roles
        ]);

        /*
         * Do event
         */

        do_event('user.roles.revoke', $id, $roles);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Get user groups.
     *
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserGroups(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.groups.read')) {

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

        if (!$this->auth->userIdExists($id)) {

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

            $groups = $this->auth->getUserGroupsCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get user groups: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = GroupCollection::create($groups, [
            'object_prefix' => $this->base_uri . '/groups',
            'collection_prefix' => $this->base_uri . '/users/' . $id . '/groups'
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
     * Add user to groups.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantUserGroups(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.grant',
                'self.users.groups.grant'
            ])
            || (!$this->hasPermissions('global.users.groups.grant') && $id != $this->user_id)) {

            abort(403, 'Unable to add user to groups: insufficient permissions');
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

            abort(400, 'Unable to add user to groups: request body contains invalid members');
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
                || $resource['type'] != 'groups'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to add user to groups: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

            abort(404, 'Unable to add user to groups: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $groups = Arr::pluck($body['data'], 'id');

        try {

            $this->auth->grantUserGroups($id, $groups);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add user to groups: group ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added user to groups', [
            'id' => $id,
            'groups' => $groups
        ]);

        /*
         * Do event
         */

        do_event('user.groups.grant', $id, $groups);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove user from groups.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeUserGroups(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.users.groups.revoke',
                'self.users.groups.revoke'
            ])
            || (!$this->hasPermissions('global.users.groups.revoke') && $id != $this->user_id)) {

            abort(403, 'Unable to remove user from groups: insufficient permissions');
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

            abort(400, 'Unable to remove user from groups: request body contains invalid members');
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
                || $resource['type'] != 'groups'
                || !Validate::string($resource['id'])) {

                abort(400, 'Unable to remove user from groups: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

            abort(404, 'Unable to remove user from groups: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $groups = Arr::pluck($body['data'], 'id');

        $this->auth->revokeUserGroups($id, $groups);

        /*
         * Log action
         */

        log_info('Removed user from groups', [
            'id' => $id,
            'groups' => $groups
        ]);

        /*
         * Do event
         */

        do_event('user.groups.revoke', $id, $groups);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }


    /**
     * Create user meta.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _createUserMeta(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.meta.create')) {

            abort(403, 'Unable to create user meta: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!$this->api->isValidResource($body, [ // Valid attributes
                'value'
            ], [ // Required attributes
                'value'
            ])
            || !isset($body['data']['id'])) {

            abort(400, 'Unable to create user meta: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'userMeta') {

            abort(409, 'Unable to create user meta: invalid resource type');
            die;

        }

        /*
         * Validate body
         *
         * No validation needed- value can be mixed type
         */

        /*
         * Check exists
         */

        if ($this->auth->userHasMeta($id, $body['data']['id'])) {

            abort(409, 'Unable to create user meta: meta ID already exists');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->setUserMeta($id, [
                $body['data']['id'] => $body['data']['attributes']['value']
            ]);

        } catch (InvalidUserException $e) {

            abort(404, 'Unable to create user meta: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Created user meta', [
            'id' => $id,
            'meta_key' => $body['data']['id']
        ]);

        /*
         * Do event
         */

        do_event('user.meta.create', $id, $body['data']['id']);

        /*
         * Build schema
         */

        $schema = UserMetaResource::create([
            'metaKey' => $body['data']['id'],
            'metaValue' => $body['data']['attributes']['value']
        ], [
            'object_prefix' => $this->base_uri . '/users/' . $id . '/meta'
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(201)
            ->setHeaders([
                'Location' => $this->base_uri . '/users/' . $id . '/meta/' . $body['data']['id']
            ])
            ->sendJson($schema);

    }

    /**
     * Update user meta.
     *
     * @param string $id
     * @param string $meta_key
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateUserMeta(string $id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.meta.update')) {

            abort(403, 'Unable to update user meta: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!$this->api->isValidResource($body, [ // Valid attributes
            'value'
        ], [] // Required attributes
        )) {

            abort(400, 'Unable to update user meta: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'userMeta'
            || Arr::get($body, 'data.id') != $meta_key) {

            abort(409, 'Unable to update user meta: invalid resource type and/or ID');
            die;

        }

        /*
         * Validate body
         *
         * No validation needed- value can be mixed type
         */

        /*
         * Check exists
         */

        if (!$this->auth->userHasMeta($id, $meta_key)) {

            abort(404, 'Unable to update user meta: user meta does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->setUserMeta($id, [
                $meta_key => $body['data']['attributes']['value']
            ]);

        } catch (InvalidUserException $e) {

            /*
             * This should never occur since userHasMeta was already checked,
             * but the exception will be caught anyway.
             */

            abort(404, 'Unable to update user meta: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Updated user meat', [
            'id' => $id,
            'meta_key' => $meta_key
        ]);

        /*
         * Do event
         */

        do_event('user.meta.update', $id, $meta_key);

        /*
         * Build schema
         */

        $schema = UserMetaResource::create([
            'metaKey' => $meta_key,
            'metaValue' => $body['data']['attributes']['value']
        ], [
            'object_prefix' => $this->base_uri . '/users/' . $id . '/meta'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

    }

    /**
     * Get single user meta.
     *
     * @param string $id
     * @param string $meta_key
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getUserMeta(string $id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.meta.read')) {

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
                'value' => $this->auth->getUserMeta($id, $meta_key)
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

        foreach ($meta as $k => $v) {

            if ($k == 'id') {
                $meta['metaKey'] = $v;
                unset($meta[$k]);
            }

            if ($k == 'value') {

                if (Validate::json($v)) {

                    $v = json_decode($v, true);

                }

                $meta['metaValue'] = $v;
                unset($meta[$k]);

            }

        }

        /*
         * Build schema
         */

        $schema = UserMetaResource::create($meta, [
            'object_prefix' => $this->base_uri . '/users/' . $id . '/meta',
            'collection_prefix' => $this->base_uri . '/users/' . $id . '/meta'
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
     * Get all user meta.
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

    protected function _getAllUserMeta(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.meta.read')) {

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
                'value'
            ]))) {

            abort(400, 'Unable to get user meta: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

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

            $meta = $this->auth->getUserMetaCollection($request, $id);

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
            'object_prefix' => $this->base_uri . '/users/' . $id . '/meta',
            'collection_prefix' => $this->base_uri . '/users/' . $id . '/meta'
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
     * Delete user meta.
     *
     * @param string $id
     * @param string $meta_key
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _deleteUserMeta(string $id, string $meta_key): void
    {

        /*
         * Check permissions
         */

        if (!$this->_userCan($id, 'users.meta.delete')) {

            abort(403, 'Unable to delete user meta: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->userIdExists($id)) {

            abort(404, 'Unable to delete user meta: user ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->deleteUserMeta($id, [
            $meta_key
        ]);

        /*
         * Log action
         */

        log_info('Deleted user meta', [
            'id' => $id,
            'meta_key' => $meta_key
        ]);

        /*
         * Do event
         */

        do_event('user.meta.delete', $id, [
            $meta_key
        ]);

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
                abort(405, 'Request method (POST) not allowed');
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
                abort(405, 'Request method (PATCH) not allowed');
                die;
            }

            $this->_updateUser($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(405, 'Request method (DELETE) not allowed');
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

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        $this->_getUserPermissions($params['id']);

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
            'POST',
            'DELETE'
        ]);

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getUserRoles($params['id']);

        } else if (Request::isPost()) {

            $this->_grantUserRoles($params['id']);

        } else { // Delete

            $this->_revokeUserRoles($params['id']);

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
            'POST',
            'DELETE'
        ]);

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getUserGroups($params['id']);

        } else if (Request::isPost()) {

            $this->_grantUserGroups($params['id']);

        } else { // Delete

            $this->_revokeUserGroups($params['id']);

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
            'POST',
            'GET',
            'PATCH',
            'DELETE'
        ]);

        if (!isset($params['id'])) {
            abort(400);
            die;
        }

        if (Request::isPost()) {

            $this->_createUserMeta($params['id']);

        } else if (Request::isGet()) {

            if (isset($params['meta_key'])) { // Single meta

                $this->_getUserMeta($params['id'], $params['meta_key']);

            } else { // Get all meta

                $this->_getAllUserMeta($params['id']);

            }

        } else if (Request::isPatch()) {

            if (!isset($params['meta_key'])) {
                abort(405, 'Request method (PATCH) not allowed');
                die;
            }

            $this->_updateUserMeta($params['id'], $params['meta_key']);

        } else { // Delete

            if (!isset($params['meta_key'])) {
                abort(405, 'Request method (DELETE) not allowed');
                die;
            }

            $this->_deleteUserMeta($params['id'], $params['meta_key']);

        }

    }

}