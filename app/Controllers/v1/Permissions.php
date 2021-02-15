<?php

namespace App\Controllers\v1;

use App\Schemas\PermissionCollection;
use App\Schemas\PermissionResource;
use App\Schemas\RoleCollection;
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
use Bayfront\RBAC\Exceptions\InvalidPermissionException;
use Bayfront\RBAC\Exceptions\NameExistsException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;
use Exception;
use PDOException;

/**
 * Permissions controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Permissions extends ApiController
{

    /**
     * Permissions constructor.
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
     * Create new permission.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidPermissionException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidSchemaException
     */

    protected function _createPermission(): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.create')) {

            abort(403, 'Unable to create permission: insufficient permissions');
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
            || !is_array($body['data']['attributes'])
            || !empty(Arr::except($body['data']['attributes'], [ // Valid members
                'name',
                'description'
            ]))
            || Arr::isMissing($body['data']['attributes'], [ // Required members
                'name'
            ])) {

            abort(400, 'Unable to create permission: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'permissions') {

            abort(409, 'Unable to create permission: invalid resource type');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [ // Valid members
                'name' => 'string',
                'description' => 'string|null'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $id = $this->auth->createPermission($body['data']['attributes']);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to create permission: invalid members');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to create permission: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Permission created', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('permission.create', $id);

        /*
         * Build schema
         */

        $schema = PermissionResource::create($this->auth->getPermission($id), [
            'object_prefix' => $this->base_uri . '/permissions'
        ]);

        /*
         * Send response
         */

        $this->response->setStatusCode(201)
            ->setHeaders([
                'Location' => $this->base_uri . '/permissions/' . $id
            ])
            ->sendJson($schema);

    }

    /**
     * Update permission.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidPermissionException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updatePermission(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.update')) {

            abort(403, 'Unable to update permission: insufficient permissions');
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
            || $body['data']['id'] != $id
            || !is_array($body['data']['attributes'])
            || !empty(Arr::except($body['data']['attributes'], [ // Valid members
                'name',
                'description'
            ]))) {

            abort(400, 'Unable to update permission: request body contains invalid members');
            die;

        }

        if (Arr::get($body, 'data.type') != 'permissions') {

            abort(409, 'Unable to create permission: invalid resource type');
            die;

        }

        /*
         * Validate body
         */

        try {

            Validate::as($body['data']['attributes'], [ // Valid members
                'name' => 'string',
                'description' => 'string|null'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        /*
         * Perform action
         */

        try {

            $this->auth->updatePermission($id, $body['data']['attributes']);

        } catch (InvalidKeysException $e) {

            abort(400, 'Unable to update permission: invalid members');
            die;

        } catch (InvalidPermissionException $e) {

            abort(404, 'Unable to update permission: permission ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(409, 'Unable to update permission: name already exists');
            die;

        }

        /*
         * Log action
         */

        log_info('Permission updated', [
            'id' => $id
        ]);

        /*
         * Do event
         */

        do_event('permission.update', $id);

        /*
         * Build schema
         */

        $schema = PermissionResource::create($this->auth->getPermission($id), [
            'object_prefix' => $this->base_uri . '/permissions'
        ]);

        /*
         * Send response
         */

        $this->response->sendJson($schema);

    }

    /**
     * Get single permission.
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

    protected function _getPermission(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
                'global.permissions.read',
                'self.permissions.read'
            ]) || (!$this->hasPermissions('global.permissions.read')
                && !in_array($id, Arr::pluck($this->user_permissions, 'id')))) {

            abort(403, 'Unable to get permission: insufficient permissions');
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

            abort(400, 'Unable to get permission: query string contains invalid fields');
            die;

        }

        /*
         * Get data
         */

        try {

            $user = $this->auth->getPermission($id);

        } catch (InvalidPermissionException $e) {

            abort(404, 'Unable to get permission: permission ID does not exist');
            die;

        }

        /*
         * Filter fields
         */

        if (isset($request['fields']['permissions'])) {

            $request = $this->requireValues($request, 'fields.permissions', 'id');

            $user = Arr::only($user, $request['fields']['permissions']);

        }

        /*
         * Build schema
         */

        $schema = PermissionResource::create($user, [
            'object_prefix' => $this->base_uri . '/permissions'
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
     * Get permissions.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getPermissions(): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasAnyPermissions([
            'global.permissions.read',
            'self.permissions.read'
        ])) {

            abort(403, 'Unable to get permissions: insufficient permissions');
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

            abort(400, 'Unable to get permissions: query string contains invalid fields');
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

            if (!$this->hasPermissions('global.permissions.read')) {

                $permissions = $this->auth->getPermissionsCollection($request, Arr::pluck($this->user_permissions, 'id')); // Limit to user's permissions

            } else {

                $permissions = $this->auth->getPermissionsCollection($request); // Get all permissions

            }

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get permissions: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = PermissionCollection::create($permissions, [
            'object_prefix' => $this->base_uri . '/permissions',
            'collection_prefix' => $this->base_uri . '/permissions'
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
     * Delete permission.
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

    protected function _deletePermission(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.delete')) {

            abort(403, 'Unable to delete permission: insufficient permissions');
            die;

        }

        /*
         * Perform action
         */

        $deleted = $this->auth->deletePermission($id);

        if ($deleted) {

            /*
             * Log action
             */

            log_info('Permission deleted', [
                'id' => $id
            ]);

            /*
             * Do event
             */

            do_event('permission.delete', $id);

            /*
             * Send response
             */

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete permission: permission ID does not exist');
            die;

        }

    }

    /**
     * Get roles with permission.
     *
     * @param string $id
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getPermissionRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.roles.read')) {

            abort(403, 'Unable to get roles with permission: insufficient permissions');
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

            abort(400, 'Unable to get roles with permission: query string contains invalid fields');
            die;

        }

        /*
         * Check exists
         */

        if (!$this->auth->permissionIdExists($id)) {

            abort(404, 'Unable to get roles with permission: permission ID does not exist');
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

            $roles = $this->auth->getPermissionRolesCollection($request, $id);

        } catch (QueryException|PDOException $e) {

            abort(400, 'Unable to get roles with permission: invalid request');
            die;

        }

        /*
         * Build schema
         */

        $schema = RoleCollection::create($roles, [
            'object_prefix' => $this->base_uri . '/roles',
            'collection_prefix' => $this->base_uri . '/permissions/' . $id . '/roles'
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
     * Grant permission to roles.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantPermissionRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.roles.grant')) {

            abort(403, 'Unable to grant permission to roles: insufficient permissions');
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

            abort(400, 'Unable to grant permission to roles: request body contains invalid members');
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

                abort(400, 'Unable to grant permission to roles: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->permissionIdExists($id)) {

            abort(404, 'Unable to grant permission to roles: permission ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $roles = Arr::pluck($body['data'], 'id');

        try {

            $this->auth->grantPermissionRoles($id, $roles);

        } catch (InvalidGrantException $e) {

            abort(400, 'Unable to grant permission to roles: role ID does not exist');
            die;

        }

        /*
         * Log action
         */

        log_info('Granted permission to roles', [
            'id' => $id,
            'roles' => $roles
        ]);

        /*
         * Do event
         */

        do_event('permission.roles.grant', $id, $roles);

        /*
         * Send response
         */

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke permission from roles.
     *
     * @param string $id
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokePermissionRoles(string $id): void
    {

        /*
         * Check permissions
         */

        if (!$this->hasPermissions('global.permissions.roles.revoke')) {

            abort(403, 'Unable to revoke permission from roles: insufficient permissions');
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

            abort(400, 'Unable to revoke permission from roles: request body contains invalid members');
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

                abort(400, 'Unable to revoke permission from roles: request body contains invalid members');
                die;

            }

        }

        /*
         * Check exists
         */

        if (!$this->auth->permissionIdExists($id)) {

            abort(404, 'Unable to revoke permission from roles: permission ID does not exist');
            die;

        }

        /*
         * Perform action
         */

        $roles = Arr::pluck($body['data'], 'id');

        $this->auth->revokePermissionRoles($id, $roles);

        /*
         * Log action
         */

        log_info('Revoked permission from roles', [
            'id' => $id,
            'roles' => $roles
        ]);

        /*
         * Do event
         */

        do_event('permission.roles.revoke', $id, $roles);

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
     * @throws InvalidPermissionException
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
                abort(405, 'Request method (POST) not allowed');
                die;
            }

            $this->_createPermission();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single permission

                $this->_getPermission($params['id']);

            } else { // Get all permissions

                $this->_getPermissions();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(405, 'Request method (PATCH) not allowed');
                die;
            }

            $this->_updatePermission($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(405, 'Request method (DELETE) not allowed');
                die;
            }

            $this->_deletePermission($params['id']);

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

            $this->_getPermissionRoles($params['id']);

        } else if (Request::isPost()) {

            $this->_grantPermissionRoles($params['id']);

        } else { // Delete

            $this->_revokePermissionRoles($params['id']);

        }

    }

}