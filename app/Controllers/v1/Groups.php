<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Controllers\v1;

use App\Schemas\GroupCollection;
use App\Schemas\GroupResource;
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
use Bayfront\RBAC\Exceptions\InvalidGroupException;
use Bayfront\RBAC\Exceptions\InvalidKeysException;
use Bayfront\RBAC\Exceptions\NameExistsException;
use Bayfront\Validator\Validate;
use Bayfront\Validator\ValidationException;
use Exception;
use PDOException;

/**
 * Groups controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Groups extends ApiController
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
     * Create new group.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidGroupException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _createGroup(): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.groups.create')) {

            abort(403, 'Unable to create group: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody([
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'name'
        ]))) {

            abort(400, 'Unable to create group: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'name' => 'string'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $id = $this->auth->createGroup($body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create group: invalid members');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to create group: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Group created', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('group.create', $id);

        /*
         * Build schema
         */

        $schema = GroupResource::create($this->auth->getGroup($id), [
            'object_prefix' => '/groups'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

    }

    /**
     * Update group.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidGroupException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateGroup(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.groups.update')) {

            abort(403, 'Unable to update group: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'name'
        ]))) {

            abort(400, 'Unable to update group: request body contains invalid members');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body, [
                'name' => 'string'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->updateGroup($id, $body);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to update group: invalid members');
            die;

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to update group: group ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to update group: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Group updated', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('group.update', $id);

        /*
         * Build schema
         */

        $schema = GroupResource::create($this->auth->getGroup($id), [
            'object_prefix' => '/groups'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

    }

    /**
     * Get single group.
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

    protected function _getGroup(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.read',
                'self.groups.read'
            ]) || (!$this->hasPermissions('global.groups.read')
                && !in_array($id, $this->user_groups))) {

            abort(403, 'Unable to get group: insufficient permissions');
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

            abort(400, 'Unable to get group: query string contains invalid fields');
            die;

        }

        /*
         * Get data
         */

        try {

            $group = $this->auth->getGroup($id);

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to get group: group ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        if (isset($request['fields']['groups'])) {

            $request = $this->requireValues($request, 'fields.groups', 'id');

            $group = Arr::only($group, $request['fields']['groups']);

        }

        /*
         * Build schema
         */

        $schema = GroupResource::create($group, [
            'object_prefix' => '/groups'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get groups.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getGroups(): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
            'global.groups.read',
            'self.groups.read'
        ])) {

            abort(403, 'Unable to get groups: insufficient permissions');
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

            abort(400, 'Unable to get groups: query string contains invalid fields');
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

            if (!$this->hasPermissions('global.groups.read')) {

                $groups = $this->auth->getGroupsCollection($request, $this->user_groups); // Limit to user's groups

            } else {

                $groups = $this->auth->getGroupsCollection($request); // Get all roles

            }

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get groups: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = GroupCollection::create($groups, [
            'object_prefix' => '/groups',
            'collection_prefix' => '/groups'
        ]);

        /*
         * Send response
         */

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Delete group.
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

    protected function _deleteGroup(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.groups.delete')) {

            abort(403, 'Unable to delete group: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $deleted = $this->auth->deleteGroup($id);

        if ($deleted) {

            /*
             * Log action
             */

            log_info('Group deleted', [
                'id' => $id
            ]);

            /*
             * Do event
             */

            do_event('group.delete', $id);

            /*
             * Send response
             */

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete group: group ID does not exist');
            die;

        }

    }

    /**
     * Get users in group.
     *
     * @param string $group_id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getGroupUsers(string $group_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.users.read',
                'self.groups.users.read'
            ])
            || (!$this->hasPermissions('global.groups.users.read') && !in_array($group_id, $this->user_groups))) {

            abort(403, 'Unable to get users in group: insufficient permissions');
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

            abort(400, 'Unable to get users in group: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->groupIdExists($group_id)) {

            abort(404, 'Unable to get users in group: group ID does not exist');
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

            $users = $this->auth->getGroupUsersCollection($request, $group_id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get users in group: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = UserCollection::create($users, [
            'object_prefix' => '/users',
            'collection_prefix' => '/groups/' . $group_id . '/users'
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
     * @param string $group_id
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantGroupUser(string $group_id, string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.users.grant',
                'self.groups.users.grant'
            ])
            || (!$this->hasPermissions('global.groups.users.grant') && !in_array($group_id, $this->user_groups))) {

            abort(403, 'Unable to add user to group: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->groupIdExists($group_id)) {

            abort(404, 'Unable to add user to group: group ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantGroupUsers($group_id, $user_id);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add user to group: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added user to group', [
            'group_id' => $group_id,
            'users' => [
                $user_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('group.users.grant', $group_id, [
            $user_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Add users to group. (batch)
     *
     * @param string $group_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantGroupUsers(string $group_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.users.grant',
                'self.groups.users.grant'
            ])
            || (!$this->hasPermissions('global.groups.users.grant') && !in_array($group_id, $this->user_groups))) {

            abort(403, 'Unable to add users to group: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'users'
        ]))) {

            abort(400, 'Unable to add users to group: request body contains invalid members');
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

        if (!$this->auth->groupIdExists($group_id)) {

            abort(404, 'Unable to add users to group: group ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->grantGroupUsers($group_id, $body['users']);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to add users to group: user ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Added users to group', [
            'group_id' => $group_id,
            'users' => $body['users']
        ]);

        /*
         * Do event
         */

        do_event('group.users.grant', $group_id, $body['users']);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove user from group.
     *
     * @param string $group_id
     * @param string $user_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeGroupUser(string $group_id, string $user_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.users.revoke',
                'self.groups.users.revoke'
            ])
            || (!$this->hasPermissions('global.groups.users.revoke') && !in_array($group_id, $this->user_groups))) {

            abort(403, 'Unable to remove user from group: insufficient permissions');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->groupIdExists($group_id)) {

            abort(404, 'Unable to remove user from group: group ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeGroupUsers($group_id, $user_id);

        /*
         * Log action
         */

        log_info('Removed user from group', [
            'group_id' => $group_id,
            'users' => [
                $user_id
            ]
        ]);

        /*
         * Do event
         */

        do_event('group.users.revoke', $group_id, [
            $user_id
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Remove users from group. (batch)
     *
     * @param string $group_id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeGroupUsers(string $group_id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.groups.users.revoke',
                'self.groups.users.revoke'
            ])
            || (!$this->hasPermissions('global.groups.users.revoke') && !in_array($group_id, $this->user_groups))) {

            abort(403, 'Unable to remove users from group: insufficient permissions');
            die;

        }

        /*
         * Get body
         */

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid members have been sent
            'users'
        ]))) {

            abort(400, 'Unable to remove users from group: request body contains invalid members');
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

        if (!$this->auth->groupIdExists($group_id)) {

            abort(404, 'Unable to remove users from group: group ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $this->auth->revokeGroupUsers($group_id, $body['users']);

        /*
         * Log action
         */

        log_info('Removed users from group', [
            'group_id' => $group_id,
            'users' => $body['users']
        ]);

        /*
         * Do event
         */

        do_event('group.users.revoke', $group_id, $body['users']);

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
     * @throws InvalidGroupException
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

            $this->_createGroup();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single group

                $this->_getGroup($params['id']);

            } else { // Get all groups

                $this->_getGroups();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateGroup($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deleteGroup($params['id']);

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

        if (!isset($params['group_id'])) {
            abort(400);
            die;
        }

        if (Request::isGet()) {

            $this->_getGroupUsers($params['group_id']);

        } else if (Request::isPut()) {

            if (isset($params['user_id'])) { // Single user

                $this->_grantGroupUser($params['group_id'], $params['user_id']);

            } else { // Multiple users

                $this->_grantGroupUsers($params['group_id']);

            }

        } else { // Delete

            if (isset($params['user_id'])) { // Single user

                $this->_revokeGroupUser($params['group_id'], $params['user_id']);

            } else { // Multiple users

                $this->_revokeGroupUsers($params['group_id']);

            }

        }

    }

}