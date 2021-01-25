<?php

namespace App\Controllers\v1;

use App\Exceptions\InvalidRequestException;
use App\Models\UserAuthModel;
use App\Schemas\EntityCollection;
use App\Schemas\EntityResource;
use App\Schemas\PermissionCollection;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\InvalidConfigurationException;
use Bayfront\Auth\Exceptions\InvalidEntityException;
use Bayfront\Auth\Exceptions\InvalidOwnerException;
use Bayfront\Auth\Exceptions\InvalidPermissionException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\NameExistsException;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ModelException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Container\NotFoundException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\PDO\Exceptions\InvalidDatabaseException;
use Bayfront\PDO\Exceptions\TransactionException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;

/**
 * Entities controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Entities extends ApiController
{

    /** @var Auth $model */

    protected $model;

    /**
     * Entities constructor.
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidEntityException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws QueryException
     * @throws ServiceException
     */

    public function __construct()
    {

        parent::__construct();

        // Define default model

        $this->model = $this->container->get('auth');

        /*
         * TODO: Work with permissions
        $permissions = [];

        foreach ($this->token['payload']['entities'] as $entity) {

            $permissions[$entity] = $this->model->getUserPermissions($this->token['payload']['user_id'], $entity);

        }

        print_r($permissions);

        die;
        */

    }

    /**
     * Create new entity.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidEntityException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws TransactionException
     * @throws InvalidConfigurationException
     */

    protected function _createEntity(): void
    {

        // Get body

        $body = $this->api->getBody([
            'owner_id',
            'name'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'owner_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to create entity: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'owner_id' => 'string',
                'name' => 'string',
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Create entity

        try {

            $id = $this->model->createEntity($body);

        } catch (InvalidOwnerException $e) {

            abort(400, 'Unable to create entity: owner ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create entity: entity name already exists');
            die;

        }

        // entity.create event

        do_event('entity.create', $id);

        // Send response

        $schema = EntityResource::create($this->model->getEntity($id), [
            'link_prefix' => '/entities'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Update entity.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws TransactionException
     * @throws InvalidUserException
     * @throws InvalidEntityException
     */

    protected function _updateEntity(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'owner_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to update entity: request body contains invalid parameters');
            die;

        }

        // Validate body

        try {

            Validate::as($body, [
                'owner_id' => 'string',
                'name' => 'string',
                'attributes' => 'json',
                'active' => 'boolean'
            ]);

        } catch (ValidationException $e) {

            abort(400, $e->getMessage());
            die;

        }

        // Update entity

        try {

            $this->model->updateEntity($id, $body);

        } catch (InvalidEntityException $e) {

            abort(404, 'Unable to update entity: entity ID does not exist');
            die;

        } catch (InvalidOwnerException $e) {

            abort(400, 'Unable to update entity: owner ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update entity: entity name already exists');
            die;

        }

        // entity.update event

        do_event('entity.update', $id);

        // Send response

        $schema = EntityResource::create($this->model->getEntity($id), [
            'link_prefix' => '/entities'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Get single entity.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _getEntity(string $id): void
    {

        // Get entity

        try {

            $entity = $this->model->getEntity($id);

        } catch (InvalidEntityException $e) {

            abort(404, 'Unable to get entity: entity ID does not exist');
            die;

        }

        // Send response

        $schema = EntityResource::create($entity, [
            'link_prefix' => '/entities'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get entities.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws ModelException
     */

    protected function _getEntities(): void
    {

        // Get entities

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        $request = $this->api->parseQuery(Request::getQuery(), $page_size);

        /** @var UserAuthModel $model */

        $model = get_model('UserAuthModel');

        try {

            $entities = $model->getEntities($request);

        } catch (QueryException|InvalidRequestException $e) {

            abort(400, 'Unable to get entities: invalid request');
            die;

        }

        // Send response

        $schema = EntityCollection::create([
            'entities' => $entities['results'],
            'page' => [
                'count' => count($entities['results']),
                'total' => $entities['total'],
                'pages' => ceil($entities['total'] / $page_size),
                'page_size' => $page_size,
                'page_number' => ($request['offset'] / $request['limit']) + 1
            ]
        ], [
            'link_prefix' => '/entities'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Delete entity.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _deleteEntity(string $id): void
    {

        // Delete entity

        $deleted = $this->model->deleteEntity($id);

        if ($deleted) {

            // entity.delete event

            do_event('entity.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete entity: entity ID does not exist');
            die;

        }

    }

    // -------------------- Permissions --------------------


    /**
     * Get entity permissions.
     *
     * @param string $entity_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws ModelException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _getEntityPermissions(string $entity_id): void
    {

        if (!$this->model->entityIdExists($entity_id)) {
            abort(404, 'Unable to get entity permissions: entity ID does not exist');
            die;
        }

        // Get permissions

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        $request = $this->api->parseQuery(Request::getQuery(), $page_size);

        /** @var UserAuthModel $model */

        $model = get_model('UserAuthModel');

        try {

            $permissions = $model->getEntityPermissions($entity_id, $request);

        } catch (QueryException|InvalidRequestException $e) {

            abort(400, 'Unable to get entity permissions: invalid request');
            die;

        }

        // Send response

        $schema = PermissionCollection::create([
            'permissions' => $permissions['results'],
            'page' => [
                'count' => count($permissions['results']),
                'total' => $permissions['total'],
                'pages' => ceil($permissions['total'] / $page_size),
                'page_size' => $page_size,
                'page_number' => ($request['offset'] / $request['limit']) + 1
            ]
        ], [
            'link_prefix' => '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Grant entity permissions.
     *
     * @param string $entity_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     * @throws InvalidDatabaseException
     */

    protected function _grantEntityPermissions(string $entity_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to grant entity permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['permissions'])) {
            abort(400, 'Unable to validate: key (permissions) with rule (array)');
            die;
        }

        // Grant permissions

        try {

            $this->model->grantEntityPermission($entity_id, $body['permissions']);

        } catch (InvalidEntityException $e) {

            abort(404, 'Unable to grant entity permissions: entity ID does not exist');
            die;

        } catch (InvalidPermissionException $e) {

            abort(400, 'Unable to grant entity permissions: permission ID does not exist');
            die;

        }

        // entity.permissions.grant event

        do_event('entity.permissions.grant', $entity_id, $body['permissions']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke entity permissions.
     *
     * @param string $entity_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidDatabaseException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _revokeEntityPermissions(string $entity_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to revoke entity permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['permissions'])) {
            abort(400, 'Unable to validate: key (permissions) with rule (array)');
            die;
        }

        // Revoke permissions

        if (!$this->model->entityIdExists($entity_id)) {
            abort(404, 'Unable to revoke entity permissions: entity ID does not exist');
            die;
        }

        $this->model->revokeEntityPermission($entity_id, $body['permissions']);

        // entity.permissions.revoke event

        do_event('entity.permissions.revoke', $entity_id, $body['permissions']);

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
     * @throws HttpException
     * @throws InvalidEntityException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws ModelException
     * @throws NotFoundException
     * @throws QueryException
     * @throws TransactionException
     * @throws InvalidConfigurationException
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

            $this->_createEntity();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single entity

                $this->_getEntity($params['id']);

            } else { // Get all entities

                $this->_getEntities();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateEntity($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deleteEntity($params['id']);

        }

    }

    /**
     * Router destination for sub-resource: permissions
     *
     * @param array $params
     *
     * @throws HttpException
     * @throws InvalidDatabaseException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws ModelException
     * @throws NotFoundException
     * @throws QueryException
     */

    public function permissions(array $params)
    {

        $this->api->allowedMethods([
            'POST',
            'GET',
            'DELETE'
        ]);

        if (Request::isPost()) {

            $this->_grantEntityPermissions($params['id']);

        } else if (Request::isGet()) {

            $this->_getEntityPermissions($params['id']);

        } else { // Delete

            $this->_revokeEntityPermissions($params['id']);

        }

    }

}