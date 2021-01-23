<?php

namespace App\Controllers\v1;

use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\DoesNotExistException;
use Bayfront\Bones\Controller;
use Bayfront\Bones\Exceptions\ControllerException;
use Bayfront\Bones\Exceptions\HttpException;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\Bones\Services\BonesApi;
use Bayfront\Container\NotFoundException;
use Bayfront\HttpRequest\Request;
use Bayfront\HttpResponse\InvalidStatusCodeException;
use Bayfront\LeakyBucket\AdapterException;
use Bayfront\LeakyBucket\BucketException;

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

    }

    protected function _getEntity(string $id)
    {

        try {

            $entity = $this->model->getEntity($id);

        } catch (DoesNotExistException $e) {

            abort(404);

            die;

        }

        print_r($entity);

    }

    protected function _getEntities(array $params)
    {

        $request = $this->api->parseQuery(Request::getQuery(), get_config('api.default_collection_count', 10));

        print_r($request);

        print_r($params);

        die;

        print_r($this->model->getEntities());

    }

    protected function _updateEntity(string $id)
    {

    }

    protected function _createEntity()
    {

    }

    /*
     * ############################################################
     * Public methods
     * ############################################################
     */

    /**
     * @param array $params
     *
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
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

                return $this->_getEntity($params['id']);

            }

            // Get all entities

            return $this->_getEntities($params);

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            return $this->_updateEntity($params['id']);

        } else { // POST

            if (isset($params['id'])) {
                abort(400);
                die;
            }

            return $this->_createEntity();

        }

    }

}