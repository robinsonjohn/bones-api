<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\GroupCollection;
use App\Services\BonesAuth\Schemas\GroupResource;
use App\Services\BonesAuth\Schemas\PermissionCollection;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Exceptions\IdExistsException;
use Bayfront\Auth\Exceptions\InvalidGroupException;
use Bayfront\Auth\Exceptions\InvalidKeysException;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidPermissionException;
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
 * Groups controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Groups extends ApiController
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
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws QueryException
     * @throws ServiceException
     * @throws InvalidOrganizationException
     */

    public function __construct()
    {

        parent::__construct();

        // Define default model

        $this->model = $this->container->get('auth');

    }

    /**
     * Create new group.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws IdExistsException
     * @throws InvalidGroupException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidKeysException
     */

    protected function _createGroup(): void
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

            abort(400, 'Unable to create group: request body contains invalid parameters');
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

        // Create group

        try {

            $id = $this->model->createGroup($body);

        } catch (InvalidOrganizationException $e) {

            abort(400, 'Unable to create group: organization ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create group: group name already exists');
            die;

        }

        log_info('Group created', [
            'id' => $id
        ]);

        // group.create event

        do_event('group.create', $id);

        // Send response

        $schema = GroupResource::create($this->model->getGroup($id), [
            'object_prefix' => '/groups'
        ]);

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
     * @throws InvalidKeysException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updateGroup(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'organization_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to update group: request body contains invalid parameters');
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

        // Update group

        try {

            $this->model->updateGroup($id, $body);

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to update group: group ID does not exist');
            die;

        } catch (InvalidOrganizationException $e) {

            abort(400, 'Unable to update group: organization ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update group: group name already exists');
            die;

        }

        log_info('Group updated', [
            'id' => $id
        ]);

        // group.update event

        do_event('group.update', $id);

        // Send response

        $schema = GroupResource::create($this->model->getGroup($id), [
            'object_prefix' => '/groups'
        ]);

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

        // Get group

        try {

            $group = $this->model->getGroup($id);

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to get group: group ID does not exist');
            die;

        }

        // Send response

        $schema = GroupResource::create($group, [
            'object_prefix' => '/groups'
        ]);

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

        // Get groups

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $groups = $this->model->getGroupCollection($request);

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get groups: invalid request');
            die;

        }

        // Send response

        $schema = GroupCollection::create([
            'results' => $groups['results'],
            'meta' => $groups['meta']
        ], [
            'object_prefix' => '/groups',
            'collection_prefix' => '/groups'
        ]);

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

        // Delete group

        $deleted = $this->model->deleteGroup($id);

        if ($deleted) {

            log_info('Group deleted', [
                'id' => $id
            ]);

            // group.delete event

            do_event('group.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete group: group ID does not exist');
            die;

        }

    }

    // -------------------- Permissions --------------------

    /**
     * Get group permissions.
     *
     * @param string $group_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getGroupPermissions(string $group_id): void
    {

        // Get permissions

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $permissions = $this->model->getGroupPermissionCollection($group_id, $request);

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to get group permissions: group ID does not exist');
            die;

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get group permissions: invalid request');
            die;

        }

        // Send response

        $schema = PermissionCollection::create([
            'results' => $permissions['results'],
            'meta' => $permissions['meta']
        ], [
            'object_prefix' => '/permissions',
            'collection_prefix' => '/groups/' . $group_id . '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Grant group permissions.
     *
     * @param string $group_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _grantGroupPermissions(string $group_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to grant group permissions: request body contains invalid parameters');
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

            $this->model->grantGroupPermissions($group_id, $body['permissions']);

        } catch (InvalidGroupException $e) {

            abort(404, 'Unable to grant group permissions: group ID does not exist');
            die;

        } catch (InvalidPermissionException $e) {

            abort(400, 'Unable to grant group permissions: permission ID does not exist');
            die;

        }

        log_info('Granted group permissions', [
            'id' => $group_id,
            'permissions' => $body['permissions']
        ]);

        // group.permissions.grant event

        do_event('group.permissions.grant', $group_id, $body['permissions']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke group permissions.
     *
     * @param string $group_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeGroupPermissions(string $group_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to revoke group permissions: request body contains invalid parameters');
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

        if (!$this->model->groupIdExists($group_id)) {
            abort(404, 'Unable to revoke group permissions: group ID does not exist');
            die;
        }

        $this->model->revokeGroupPermissions($group_id, $body['permissions']);

        log_info('Revoked group permissions', [
            'id' => $group_id,
            'permissions' => $body['permissions']
        ]);

        // group.permissions.revoke event

        do_event('group.permissions.revoke', $group_id, $body['permissions']);

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
     * @throws InvalidGroupException
     * @throws InvalidKeysException
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

            $this->_grantGroupPermissions($params['id']);

        } else if (Request::isGet()) {

            $this->_getGroupPermissions($params['id']);

        } else { // Delete

            $this->_revokeGroupPermissions($params['id']);

        }

    }

}