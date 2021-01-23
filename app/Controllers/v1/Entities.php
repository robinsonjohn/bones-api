<?php

namespace App\Controllers\v1;

use App\Schemas\EntityCollection;
use App\Schemas\EntityResource;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\AlreadyExistsException;
use Bayfront\Auth\Exceptions\DoesNotExistException;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Container\NotFoundException;
use Bayfront\Validator\ValidationException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;
use Bayfront\MonologFactory\Exceptions\ChannelNotFoundException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\Validator\Validate;
use Exception;

/**
 * Entities controller.
 */
class Entities extends Controller
{

    /** @var BonesApi $api */

    protected $api;

    protected $token; // JWT

    /** @var Auth $model */

    protected $model;

    /**
     * Entities constructor.
     *
     * @throws ControllerException
     * @throws ServiceException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InvalidStatusCodeException
     * @throws AdapterException
     * @throws BucketException
     */

    public function __construct()
    {

        parent::__construct();

        // Get the Bones API service from the container

        $this->api = get_service('BonesApi');

        // Start the API environment

        $this->api->start();

        // All endpoints require authentication

        $this->token = $this->api->authenticateJwt();

        // Check rate limit

        if (isset($this->token['payload']['user_id']) && isset($this->token['payload']['rate_limit'])) {

            $this->api->enforceRateLimit($this->token['payload']['user_id'], $this->token['payload']['rate_limit']);

        }

        // Define default model

        $this->model = get_from_container('auth');

    }

    protected function _deleteEntity(string $id)
    {

        // Return 204

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

        } catch (DoesNotExistException $e) {

            abort(404);

            die;

        }

        // Send response

        $schema = EntityResource::create($entity, [
            'link_prefix' => '/entities'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=86400' // 24 hours
        ])->sendJson($schema);

    }

    protected function _getEntities(array $params): void
    {

        // TODO: Add sort & filters based on query

        //$request = $this->api->parseQuery(Request::getQuery(), get_config('api.default_collection_count', 10));

        //print_r($request);

        //print_r($params);

        $entities = $this->model->getEntities();

        $total_entities = 999; // TODO

        $schema = EntityCollection::create([
            'entities' => $entities,
            'page' => [
                'count' => count($entities),
                'total' => $total_entities,
                'pages' => ceil($total_entities / 10), // TODO
                'page_size' => 10,
                'page_number' => 1
            ]
        ], [
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
     * @throws ChannelNotFoundException
     * @throws DoesNotExistException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    protected function _updateEntity(string $id): void
    {

        // Get and validate body

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

        } catch (DoesNotExistException $e) {

            abort(400, 'Unable to update entity: invalid entity and/or owner ID'); // TODO: Update User Auth exceptions

            die;

        } catch (AlreadyExistsException $e) {

            abort(400, 'Unable to update entity: entity name already exists');

            die;

        } catch (Exception $e) {

            log_critical($e->getMessage());

            abort(500, 'Internal server error');

            die;

        }

        // Send response

        $schema = EntityResource::create($this->model->getEntity($id), [
            'link_prefix' => '/entities'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=86400' // 24 hours
        ])->sendJson($schema);

    }

    /**
     * Create new entity.
     *
     * @return void
     *
     * @throws DoesNotExistException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidSchemaException
     * @throws ChannelNotFoundException
     * @throws QueryException
     */

    protected function _createEntity(): void
    {

        // Get and validate body

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

        } catch (DoesNotExistException $e) {

            abort(400, 'Unable to create entity: invalid owner ID');

            die;

        } catch (AlreadyExistsException $e) {

            abort(400, 'Unable to create entity: entity name already exists');

            die;

        } catch (Exception $e) {

            log_critical($e->getMessage());

            abort(500, 'Internal server error');

            die;

        }

        // Send response

        $schema = EntityResource::create($this->model->getEntity($id), [
            'link_prefix' => '/entities'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=86400' // 24 hours
        ])->sendJson($schema);

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
     * @throws DoesNotExistException
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws QueryException
     */

    public function index(array $params)
    {

        $this->api->allowedMethods([
            'DELETE',
            'GET',
            'PATCH',
            'POST'
        ]);

        if (Request::isDelete()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            return $this->_deleteEntity($params['id']);

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single entity

                $this->_getEntity($params['id']);

            } else { // Get all entities

                $this->_getEntities($params);

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateEntity($params['id']);

        } else { // POST

            if (isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_createEntity();

        }

    }

}