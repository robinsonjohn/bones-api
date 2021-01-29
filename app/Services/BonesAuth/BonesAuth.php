<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Services\BonesAuth;

use App\Services\BonesAuth\Exceptions\BadRequestException;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\Auth\Auth;
use Bayfront\Auth\Exceptions\InvalidGroupException;
use Bayfront\Auth\Exceptions\InvalidOrganizationException;
use Bayfront\Auth\Exceptions\InvalidRoleException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\PDO\Query;
use PDO;

class BonesAuth extends Auth
{

    protected $pdo;

    public function __construct(PDO $pdo, string $pepper)
    {

        $this->pdo = $pdo;

        parent::__construct($pdo, $pepper);

    }

    /**
     * Return results in a standardized format.
     *
     * @param Query $query
     * @param array $request
     *
     * @return array
     */

    protected function _returnResults(Query $query, array $request): array
    {

        $results = $query->get();

        $total = $query->getTotalRows();

        return [
            'results' => $results,
            'meta' => [
                'count' => count($results),
                'total' => $total,
                'pages' => ceil($total / $request['limit']),
                'page_size' => $request['limit'],
                'page_number' => ($request['offset'] / $request['limit']) + 1
            ]
        ];

    }

    /*
     * ############################################################
     * Permissions
     * ############################################################
     */

    /**
     * Get permission collection using a query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getPermissionCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'permissions'
        ]))) {

            throw new BadRequestException('Unable to get permissions: invalid request');

        }

        if (isset($request['fields']['permissions'])) {

            $request['fields']['permissions'][] = 'id'; // "id" column is required

            $request['fields']['permissions'] = array_unique(array_filter($request['fields']['permissions'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_permissions')
            ->select(Arr::get($request, 'fields.permissions', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }


    /*
     * ############################################################
     * Organizations
     * ############################################################
     */

    /**
     * Get organization collection using a query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getOrganizationCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'organizations'
        ]))) {

            throw new BadRequestException('Unable to get organizations: invalid request');

        }

        if (isset($request['fields']['organizations'])) {

            $request['fields']['organizations'][] = 'id'; // "id" column is required

            $request['fields']['organizations'] = array_unique(array_filter($request['fields']['organizations'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_organizations')
            ->select(Arr::get($request, 'fields.organizations', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get organization group collection using a query builder.
     *
     * @param string $organization_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidOrganizationException
     */

    public function getOrganizationGroupCollection(string $organization_id, array $request): array
    {

        if (!$this->organizationIdExists($organization_id)) {
            throw new InvalidOrganizationException('Unable to get organization groups: organization ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'groups'
        ]))) {

            throw new BadRequestException('Unable to get organization groups: invalid request');

        }

        if (isset($request['fields']['groups'])) {

            $request['fields']['groups'][] = 'id'; // "id" column is required

            $request['fields']['groups'] = array_unique(array_filter($request['fields']['groups'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_groups')
            ->select(Arr::get($request, 'fields.groups', ['*']))
            ->where('organization_id', 'eq', $organization_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the organization ID

            if ($column == 'organization_id') {
                throw new BadRequestException('Unable to get organization groups: invalid request');
            }

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get organization permission collection using a query builder.
     *
     * @param string $organization_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidOrganizationException
     */

    public function getOrganizationPermissionCollection(string $organization_id, array $request): array
    {

        if (!$this->organizationIdExists($organization_id)) {
            throw new InvalidOrganizationException('Unable to get organization permissions: organization ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'permissions'
        ]))) {

            throw new BadRequestException('Unable to get organization permissions: invalid request');

        }

        if (isset($request['fields']['permissions'])) {

            $request['fields']['permissions'][] = 'id'; // "id" column is required

            $request['fields']['permissions'] = array_unique(array_filter($request['fields']['permissions'])); // Remove blank and duplicate values

        }

        // Prefix the table name to the fields and columns for the LEFT JOIN clause

        $fields = Arr::get($request, 'fields.permissions', ['*']);

        foreach ($fields as $k => $field) {

            $fields[$k] = 'user_permissions.' . $field;

        }

        foreach ($request['order_by'] as $k => $col) {

            $request['order_by'][$k] = 'user_permissions.' . $col;

        }

        $query = new Query($this->pdo);

        $query->table('user_permissions')
            ->leftJoin('user_organization_permissions', 'user_permissions.id', 'user_organization_permissions.permission_id')
            ->select($fields)
            ->where('user_organization_permissions.organization_id', 'eq', $organization_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the organization ID

            if ($column == 'user_organization_permissions.organization_id') {
                throw new BadRequestException('Unable to get organization permissions: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_permissions.' . $column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get organization role collection using a query builder.
     *
     * @param string $organization_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidOrganizationException
     */

    public function getOrganizationRoleCollection(string $organization_id, array $request): array
    {

        if (!$this->organizationIdExists($organization_id)) {
            throw new InvalidOrganizationException('Unable to get organization roles: organization ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'roles'
        ]))) {

            throw new BadRequestException('Unable to get organization roles: invalid request');

        }

        if (isset($request['fields']['roles'])) {

            $request['fields']['roles'][] = 'id'; // "id" column is required

            $request['fields']['roles'] = array_unique(array_filter($request['fields']['roles'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_roles')
            ->select(Arr::get($request, 'fields.roles', ['*']))
            ->where('organization_id', 'eq', $organization_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the organization ID

            if ($column == 'organization_id') {
                throw new BadRequestException('Unable to get organization roles: invalid request');
            }

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get organization user collection using a query builder.
     *
     * @param string $organization_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidOrganizationException
     */

    public function getOrganizationUserCollection(string $organization_id, array $request): array
    {

        if (!$this->organizationIdExists($organization_id)) {
            throw new InvalidOrganizationException('Unable to get organization users: organization ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'users'
        ]))) {

            throw new BadRequestException('Unable to get organization users: invalid request');

        }

        if (isset($request['fields']['users'])) {

            $request['fields']['users'][] = 'id'; // "id" column is required

            $request['fields']['users'] = array_unique(array_filter($request['fields']['users'])); // Remove blank and duplicate values

        }

        // Prefix the table name to the fields and columns for the LEFT JOIN clause

        $fields = Arr::get($request, 'fields.users', ['*']);

        foreach ($fields as $k => $field) {

            $fields[$k] = 'user_users.' . $field;

        }

        foreach ($request['order_by'] as $k => $col) {

            $request['order_by'][$k] = 'user_users.' . $col;

        }

        $query = new Query($this->pdo);

        $query->table('user_users')
            ->leftJoin('user_user_organizations', 'user_users.id', 'user_user_organizations.user_id')
            ->select($fields)
            ->where('user_user_organizations.organization_id', 'eq', $organization_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the organization ID

            if ($column == 'user_user_organizations.organization_id') {
                throw new BadRequestException('Unable to get organization users: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_users.' . $column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /*
     * ############################################################
     * Groups
     * ############################################################
     */

    /**
     * Get group collection using a query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getGroupCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'groups'
        ]))) {

            throw new BadRequestException('Unable to get groups: invalid request');

        }

        if (isset($request['fields']['groups'])) {

            $request['fields']['groups'][] = 'id'; // "id" column is required

            $request['fields']['groups'] = array_unique(array_filter($request['fields']['groups'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_groups')
            ->select(Arr::get($request, 'fields.groups', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get group permission collection using a query builder.
     *
     * @param string $group_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws InvalidGroupException
     * @throws QueryException
     */

    public function getGroupPermissionCollection(string $group_id, array $request): array
    {

        if (!$this->groupIdExists($group_id)) {
            throw new InvalidGroupException('Unable to get group permissions: group ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'groups'
        ]))) {

            throw new BadRequestException('Unable to get group permissions: invalid request');

        }

        if (isset($request['fields']['groups'])) {

            $request['fields']['groups'][] = 'id'; // "id" column is required

            $request['fields']['groups'] = array_unique(array_filter($request['fields']['groups'])); // Remove blank and duplicate values

        }

        // Prefix the table name to the fields and columns for the LEFT JOIN clause

        $fields = Arr::get($request, 'fields.groups', ['*']);

        foreach ($fields as $k => $field) {

            $fields[$k] = 'user_permissions.' . $field;

        }

        foreach ($request['order_by'] as $k => $col) {

            $request['order_by'][$k] = 'user_permissions.' . $col;

        }

        $query = new Query($this->pdo);

        $query->table('user_permissions')
            ->leftJoin('user_group_permissions', 'user_permissions.id', 'user_group_permissions.permission_id')
            ->select($fields)
            ->where('user_group_permissions.group_id', 'eq', $group_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the group ID

            if ($column == 'user_group_permissions.group_id') {
                throw new BadRequestException('Unable to get group permissions: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_permissions.' . $column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /*
     * ############################################################
     * Roles
     * ############################################################
     */


    /**
     * Get roles.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getRoleCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'roles'
        ]))) {

            throw new BadRequestException('Unable to get roles: invalid request');

        }

        if (isset($request['fields']['roles'])) {

            $request['fields']['roles'][] = 'id'; // "id" column is required

            $request['fields']['roles'] = array_unique(array_filter($request['fields']['roles'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_roles')
            ->select(Arr::get($request, 'fields.roles', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get role permission collection.
     *
     * @param string $role_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws InvalidRoleException
     * @throws QueryException
     */

    public function getRolePermissionCollection(string $role_id, array $request): array
    {

        if (!$this->roleIdExists($role_id)) {
            throw new InvalidRoleException('Unable to get role permissions: role ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'roles'
        ]))) {

            throw new BadRequestException('Unable to get role permissions: invalid request');

        }

        if (isset($request['fields']['roles'])) {

            $request['fields']['roles'][] = 'id'; // "id" column is required

            $request['fields']['roles'] = array_unique(array_filter($request['fields']['roles'])); // Remove blank and duplicate values

        }

        // Prefix the table name to the fields and columns for the LEFT JOIN clause

        $fields = Arr::get($request, 'fields.roles', ['*']);

        foreach ($fields as $k => $field) {

            $fields[$k] = 'user_permissions.' . $field;

        }

        foreach ($request['order_by'] as $k => $col) {

            $request['order_by'][$k] = 'user_permissions.' . $col;

        }

        $query = new Query($this->pdo);

        $query->table('user_permissions')
            ->leftJoin('user_role_permissions', 'user_permissions.id', 'user_role_permissions.permission_id')
            ->select($fields)
            ->where('user_role_permissions.role_id', 'eq', $role_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the group ID

            if ($column == 'user_role_permissions.role_id') {
                throw new BadRequestException('Unable to get role permissions: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_permissions.' . $column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /*
     * ############################################################
     * Users
     * ############################################################
     */


    /**
     * Get users.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getUserCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'users'
        ]))) {

            throw new BadRequestException('Unable to get users: invalid request');

        }

        if (isset($request['fields']['users'])) {

            $request['fields']['users'][] = 'id'; // "id" column is required

            $request['fields']['users'] = array_unique(array_filter($request['fields']['users'])); // Remove blank and duplicate values

        }

        $query = new Query($this->pdo);

        $query->table('user_users')
            ->select(Arr::get($request, 'fields.users', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    public function getUserPermissionCollection(string $user_id, array $request): array
    {

        return [];
        // TODO: How to filter this?

    }

}