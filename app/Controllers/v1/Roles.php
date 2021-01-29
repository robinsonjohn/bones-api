<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\PermissionCollection;
use App\Services\BonesAuth\Schemas\RoleCollection;
use App\Services\BonesAuth\Schemas\RoleResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Exceptions\IdExistsException;
use Bayfront\Auth\Exceptions\InvalidKeysException;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidPermissionException;
use Bayfront\Auth\Exceptions\InvalidRoleException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\NameExistsException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;
use Exception;

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
     * @throws InvalidOrganizationException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
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
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws IdExistsException
     * @throws InvalidRoleException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidKeysException
     */

    protected function _createRole(): void
    {

        // Get body

        $body = $this->api->getBody([
            'organization_id',
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'organization_id',
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
                'organization_id' => 'string',
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

        } catch (InvalidOrganizationException $e) {

            abort(400, 'Unable to create role: organization ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create role: role name already exists');
            die;

        }

        log_info('Role created', [
            'id' => $id
        ]);

        // role.create event

        do_event('role.create', $id);

        // Send response

        $schema = RoleResource::create($this->model->getRole($id), [
            'object_prefix' => '/roles'
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
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidKeysException
     * @throws InvalidRoleException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateRole(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'organization_id',
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
                'organization_id' => 'string',
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

        } catch (InvalidOrganizationException $e) {

            abort(400, 'Unable to update role: organization ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update role: role name already exists');
            die;

        }

        log_info('Role updated', [
            'id' => $id
        ]);

        // role.update event

        do_event('role.update', $id);

        // Send response

        $schema = RoleResource::create($this->model->getRole($id), [
            'object_prefix' => '/roles'
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
            'object_prefix' => '/roles'
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

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $this->getPageSize());

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
            'object_prefix' => '/roles',
            'collection_prefix' => '/roles'
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
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _deleteRole(string $id): void
    {

        // Delete role

        $deleted = $this->model->deleteRole($id);

        if ($deleted) {

            log_info('Role deleted', [
                'id' => $id
            ]);

            // role.delete event

            do_event('role.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete role: role ID does not exist');
            die;

        }

    }

    // -------------------- Permissions --------------------

    /**
     * Get role permissions.
     *
     * @param string $role_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getRolePermissions(string $role_id): void
    {

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $this->getPageSize());

            $permissions = $this->model->getRolePermissionCollection($role_id, $request);

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to get role permissions: role ID does not exist');
            die;

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get role permissions: invalid request');
            die;

        }

        // Send response

        $schema = PermissionCollection::create([
            'results' => $permissions['results'],
            'meta' => $permissions['meta']
        ], [
            'object_prefix' => '/permissions',
            'collection_prefix' => '/roles/' . $role_id . '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Grant role permissions.
     *
     * @param string $role_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _grantRolePermissions(string $role_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to grant role permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'permissions' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Grant permissions

        try {

            $this->model->grantRolePermissions($role_id, $body['permissions']);

        } catch (InvalidRoleException $e) {

            abort(404, 'Unable to grant role permissions: role ID does not exist');
            die;

        } catch (InvalidPermissionException $e) {

            abort(400, 'Unable to grant role permissions: permission ID does not exist');
            die;

        }

        log_info('Granted role permissions', [
            'id' => $role_id,
            'permissions' => $body['permissions']
        ]);

        // role.permissions.grant event

        do_event('role.permissions.grant', $role_id, $body['permissions']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke role permissions.
     *
     * @param string $role_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeRolePermissions(string $role_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to revoke role permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'permissions' => 'array'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Revoke permissions

        if (!$this->model->roleIdExists($role_id)) {
            abort(404, 'Unable to revoke role permissions: group ID does not exist');
            die;
        }

        $this->model->revokeRolePermissions($role_id, $body['permissions']);

        log_info('Revoked role permissions', [
            'id' => $role_id,
            'permissions' => $body['permissions']
        ]);

        // role.permissions.revoke event

        do_event('role.permissions.revoke', $role_id, $body['permissions']);

        // Send response

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
     * @throws IdExistsException
     * @throws InvalidKeysException
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

    /**
     * Router destination for sub-resource: permissions
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
            'POST',
            'GET',
            'DELETE'
        ]);

        if (Request::isPost()) {

            $this->_grantRolePermissions($params['id']);

        } else if (Request::isGet()) {

            $this->_getRolePermissions($params['id']);

        } else { // Delete

            $this->_revokeRolePermissions($params['id']);

        }

    }

}