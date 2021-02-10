<?php

namespace App\Controllers\v1;

use App\Schemas\PermissionCollection;
use App\Schemas\RoleCollection;
use App\Schemas\UserCollection;
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
            'login',
            'password'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'login',
            'password',
            'firstName',
            'lastName',
            'companyName',
            'email',
            'enabled'
        ]))) {

            abort(400, 'Unable to create user: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
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

            $id = $this->auth->createUser($body);

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

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
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

            Validate::as($body, [
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

            $this->auth->updateUser($id, $body);

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

}