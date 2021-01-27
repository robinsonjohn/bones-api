<?php

namespace App\Controllers\v1;

use App\Services\BonesAuth\BonesAuth;
use App\Services\BonesAuth\Exceptions\BadRequestException;
use App\Services\BonesAuth\Schemas\OrganizationCollection;
use App\Services\BonesAuth\Schemas\OrganizationResource;
use App\Services\BonesAuth\Schemas\PermissionCollection;
use App\Services\BonesAuth\Schemas\UserCollection;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\Auth\Exceptions\InvalidKeysException;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidOwnerException;
use Bayfront\Auth\Exceptions\InvalidPermissionException;
use Bayfront\Auth\Exceptions\InvalidUserException;
use Bayfront\Auth\Exceptions\NameExistsException;
use Bayfront\Auth\Exceptions\OrganizationOwnerException;
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
 * Organizations controller.
 *
 * This controller allows rate limited authenticated access to endpoints.
 */
class Organizations extends ApiController
{

    /** @var BonesAuth $model */

    protected $model;

    /**
     * Organizations constructor.
     *
     * @throws AdapterException
     * @throws BucketException
     * @throws ControllerException
     * @throws HttpException
     * @throws InvalidOrganizationException
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

    }

    /**
     * Create new organization.
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidOrganizationException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     * @throws InvalidKeysException
     */

    protected function _createOrganization(): void
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

            abort(400, 'Unable to create organization: request body contains invalid parameters');
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

        // Create organization

        try {

            $id = $this->model->createOrganization($body);

        } catch (InvalidOwnerException $e) {

            abort(400, 'Unable to create organization: owner ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to create organization: organization name already exists');
            die;

        }

        log_info('Organization created', [
            'id' => $id
        ]);

        // organization.create event

        do_event('organization.create', $id);

        // Send response

        $schema = OrganizationResource::create($this->model->getOrganization($id), [
            'link_prefix' => '/organizations'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Update organization.
     *
     * @param string $id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidKeysException
     * @throws InvalidOrganizationException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
     * @throws NotFoundException
     */

    protected function _updateOrganization(string $id): void
    {

        // Get body

        $body = $this->api->getBody();

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'owner_id',
            'name',
            'attributes',
            'active'
        ]))) {

            abort(400, 'Unable to update organization: request body contains invalid parameters');
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

        // Update organization

        try {

            $this->model->updateOrganization($id, $body);

        } catch (InvalidOrganizationException $e) {

            abort(404, 'Unable to update organization: organization ID does not exist');
            die;

        } catch (InvalidOwnerException $e) {

            abort(400, 'Unable to update organization: owner ID does not exist');
            die;

        } catch (NameExistsException $e) {

            abort(400, 'Unable to update organization: organization name already exists');
            die;

        }

        log_info('Organization updated', [
            'id' => $id
        ]);

        // organization.update event

        do_event('organization.update', $id);

        // Send response

        $schema = OrganizationResource::create($this->model->getOrganization($id), [
            'link_prefix' => '/organizations'
        ]);

        $this->response->sendJson($schema);

    }

    /**
     * Get single organization.
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

    protected function _getOrganization(string $id): void
    {

        // Get organization

        try {

            $organization = $this->model->getOrganization($id);

        } catch (InvalidOrganizationException $e) {

            abort(404, 'Unable to get organization: organization ID does not exist');
            die;

        }

        // Send response

        $schema = OrganizationResource::create($organization, [
            'link_prefix' => '/organizations'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Get organizations.
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws InvalidSchemaException
     */

    protected function _getOrganizations(): void
    {

        // Get organizations

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $organizations = $this->model->getOrganizationCollection($request);

        } catch (HttpException|QueryException|BadRequestException $e) {

            abort(400, 'Unable to get organizations: invalid request');
            die;

        }

        // Send response

        $schema = OrganizationCollection::create([
            'results' => $organizations['results'],
            'meta' => $organizations['meta']
        ], [
            'link_prefix' => '/organizations'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Delete organization.
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

    protected function _deleteOrganization(string $id): void
    {

        // Delete organization

        $deleted = $this->model->deleteOrganization($id);

        if ($deleted) {

            log_info('Organization deleted', [
                'id' => $id
            ]);

            // organization.delete event

            do_event('organization.delete', $id);

            $this->response->setStatusCode(204)->send();

        } else {

            abort(404, 'Unable to delete organization: organization ID does not exist');
            die;

        }

    }

    // -------------------- Permissions --------------------

    /**
     * Get organization permissions.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getOrganizationPermissions(string $organization_id): void
    {

        // Get permissions

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $permissions = $this->model->getOrganizationPermissionCollection($organization_id, $request);

        } catch (HttpException|InvalidOrganizationException $e) {

            abort(404, 'Unable to get organization permissions: organization ID does not exist');
            die;

        } catch (QueryException|BadRequestException $e) {

            abort(400, 'Unable to get organization permissions: invalid request');
            die;

        }

        // Send response

        $schema = PermissionCollection::create([
            'results' => $permissions['results'],
            'meta' => $permissions['meta']
        ], [
            'link_prefix' => '/organizations/' . $organization_id . '/permissions'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Grant organization permissions.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantOrganizationPermissions(string $organization_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to grant organization permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['permissions'])) {
            abort(400, 'Unable to validate: key (permissions) with rule (array)');
            die;
        }

        // Grant permissions

        try {

            $this->model->grantOrganizationPermissions($organization_id, $body['permissions']);

        } catch (InvalidOrganizationException $e) {

            abort(404, 'Unable to grant organization permissions: organization ID does not exist');
            die;

        } catch (InvalidPermissionException $e) {

            abort(400, 'Unable to grant organization permissions: permission ID does not exist');
            die;

        }

        log_info('Granted organization permissions', [
            'id' => $organization_id,
            'permissions' => $body['permissions']
        ]);

        // organization.permissions.grant event

        do_event('organization.permissions.grant', $organization_id, $body['permissions']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke organization permissions.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeOrganizationPermissions(string $organization_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'permissions'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'permissions'
        ]))) {

            abort(400, 'Unable to revoke organization permissions: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['permissions'])) {
            abort(400, 'Unable to validate: key (permissions) with rule (array)');
            die;
        }

        // Revoke permissions

        if (!$this->model->organizationIdExists($organization_id)) {
            abort(404, 'Unable to revoke organization permissions: organization ID does not exist');
            die;
        }

        $this->model->revokeOrganizationPermissions($organization_id, $body['permissions']);

        log_info('Revoked organization permissions', [
            'id' => $organization_id,
            'permissions' => $body['permissions']
        ]);

        // organization.permissions.revoke event

        do_event('organization.permissions.revoke', $organization_id, $body['permissions']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    // -------------------- Users --------------------

    /**
     * Get organization users.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws HttpException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _getOrganizationUsers(string $organization_id): void
    {

        // Get permissions

        $page_size = (int)Arr::get(Request::getQuery(), 'page.size', get_config('api.default_page_size', 10));

        try {

            $request = $this->api->parseQuery(Request::getQuery(), $page_size);

            $permissions = $this->model->getOrganizationUserCollection($organization_id, $request);

        } catch (HttpException|InvalidOrganizationException $e) {

            abort(404, 'Unable to get organization users: organization ID does not exist');
            die;

        } catch (QueryException|BadRequestException $e) {

            abort(400, 'Unable to get organization users: invalid request');
            die;

        }

        // Send response

        $schema = UserCollection::create([
            'results' => $permissions['results'],
            'meta' => $permissions['meta']
        ], [
            'link_prefix' => '/users'
        ]);

        $this->response->setHeaders([
            'Cache-Control' => 'max-age=3600' // 1 hour
        ])->sendJson($schema);

    }

    /**
     * Grant organization users.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     */

    protected function _grantOrganizationUsers(string $organization_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'users'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'users'
        ]))) {

            abort(400, 'Unable to grant organization users: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['users'])) {
            abort(400, 'Unable to validate: key (users) with rule (array)');
            die;
        }

        // Grant users

        try {

            foreach ($body['users'] as $user) {

                // TODO: Update user-auth library to accept an array and avoid iterating here

                $this->model->grantUserOrganizations($user, $organization_id);

            }

        } catch (InvalidOrganizationException $e) {

            abort(404, 'Unable to grant organization users: organization ID does not exist');
            die;

        } catch (InvalidUserException $e) {

            abort(400, 'Unable to grant organization users: user ID does not exist');
            die;

        }

        log_info('Granted organization users', [
            'id' => $organization_id,
            'permissions' => $body['users']
        ]);

        // organization.users.grant event

        do_event('organization.users.grant', $organization_id, $body['users']);

        // Send response

        $this->response->setStatusCode(204)->send();

    }

    /**
     * Revoke organization users.
     *
     * @param string $organization_id
     *
     * @return void
     *
     * @throws ChannelNotFoundException
     * @throws HttpException
     * @throws InvalidStatusCodeException
     * @throws NotFoundException
     * @throws Exception
     */

    protected function _revokeOrganizationUsers(string $organization_id): void
    {

        // Get body

        $body = $this->api->getBody([
            'users'
        ]); // Required keys

        if (!empty(Arr::except($body, [ // If invalid keys have been sent
            'users'
        ]))) {

            abort(400, 'Unable to revoke organization users: request body contains invalid parameters');
            die;

        }

        // Validate body

        if (!is_array($body['users'])) {
            abort(400, 'Unable to validate: key (users) with rule (array)');
            die;
        }

        // Revoke users

        if (!$this->model->organizationIdExists($organization_id)) {
            abort(404, 'Unable to revoke organization users: organization ID does not exist');
            die;
        }

        try {

            foreach ($body['users'] as $user) {

                // TODO: Update user-auth library to accept an array and avoid iterating here- need something like revokeOrganizationUsers

                $this->model->revokeUserOrganizations($user, $organization_id);

            }

        } catch (OrganizationOwnerException $e) {

            abort(400, 'Unable to revoke organization users: organization owner cannot be revoked');
            die;

        }

        log_info('Revoked organization permissions', [
            'id' => $organization_id,
            'permissions' => $body['users']
        ]);

        // organization.users.revoke event

        do_event('organization.users.revoke', $organization_id, $body['users']);

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
     * @throws InvalidKeysException
     * @throws InvalidOrganizationException
     * @throws InvalidSchemaException
     * @throws InvalidStatusCodeException
     * @throws InvalidUserException
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

            $this->_createOrganization();

        } else if (Request::isGet()) {

            if (isset($params['id'])) { // Single organization

                $this->_getOrganization($params['id']);

            } else { // Get all organizations

                $this->_getOrganizations();

            }

        } else if (Request::isPatch()) {

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_updateOrganization($params['id']);

        } else { // Delete

            if (!isset($params['id'])) {
                abort(400);
                die;
            }

            $this->_deleteOrganization($params['id']);

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

            $this->_grantOrganizationPermissions($params['id']);

        } else if (Request::isGet()) {

            $this->_getOrganizationPermissions($params['id']);

        } else { // Delete

            $this->_revokeOrganizationPermissions($params['id']);

        }

    }

    /**
     * Router destination for sub-resource: users
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
            'POST',
            'GET',
            'DELETE'
        ]);

        if (Request::isPost()) {

            $this->_grantOrganizationUsers($params['id']);

        } else if (Request::isGet()) {

            $this->_getOrganizationUsers($params['id']);

        } else { // Delete

            $this->_revokeOrganizationUsers($params['id']);

        }

    }

}