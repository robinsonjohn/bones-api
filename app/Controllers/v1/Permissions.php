<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\PermissionCollection;
use App\Services\BonesAuth\Schemas\PermissionResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
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

/**
 * Permissions controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Permissions extends ApiController
{

    /** @var BonesAuth $model */

    protected $model;

    /**
     * Permissions constructor.
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
     * Create new permission.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidPermissionException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidKeysException
     */

    protected function _createPermission(): void
    {

        // Get body

        $body = $this->api->getBody([
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'name',
            'description'
        ]))) {

            abort(400, 'Unable to create permission: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'name' => 'string',
                'permission' => 'string'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Create permission

        try {

            $id = $this->model->createPermission($body);

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create permission: permission name already exists');
            die;

        }

        log_info('Permission created', [
            'id' => $id
        ]);

        // permission.create event

        do_event('permission.create', $id);

        // Send response

        $schema = PermissionResource::create($this->model->getPermission($id), [
            'object_prefix' => '/permissions'
        ]);

        $this->response->sendJson($schema);

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
     * @throws InvalidKeysException
     * @throws InvalidPermissionException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _updatePermission(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'name',
            'description'
        ]))) {

            abort(400, 'Unable to update permission: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'name' => 'string',
                'description' => 'string'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Update permission

        try {

            $this->model->updatePermission($id, $body);

        } catch (InvalidPermissionException $e) {

            abort(404, 'Unable to update permission: permission ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update permission: permission name already exists');
            die;

        }

        log_info('Permission updated', [
            'id' => $id
        ]);

        // permission.update event

        do_event('permission.update', $id);

        // Send response

        $schema = PermissionResource::create($this->model->getPermission($id), [
            'object_prefix' => '/permissions'
        ]);

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

        // Get permission

        try {

            $permission = $this->model->getPermission($id);

        } catch (InvalidPermissionException $e) {

            abort(404, 'Unable to get permission: permission ID does not exist');
            die;

        }

        // Send response

        $schema = PermissionResource::create($permission, [
            'object_prefix' => '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
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

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $this->getPageSize());

            $permissions = $this->model->getPermissionCollection($request);

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get permissions: invalid request');
            die;

        }

        // Send response

        $schema = PermissionCollection::create([
            'results' => $permissions['results'],
            'meta' => $permissions['meta']
        ], [
            'object_prefix' => '/permissions',
            'collection_prefix' => '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
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

        // Delete permission

        $deleted = $this->model->deletePermission($id);

        if ($deleted) {

            log_info('Permission deleted', [
                'id' => $id
            ]);

            // permission.delete event

            do_event('permission.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete permission: permission ID does not exist');
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
     * @throws InvalidKeysException
     * @throws InvalidPermissionException
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

            $this->_createPermission();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single permission

                $this->_getPermission($params['id']);

            } else { // Get all permissions

                $this->_getPermissions();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updatePermission($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deletePermission($params['id']);

        }

    }

}