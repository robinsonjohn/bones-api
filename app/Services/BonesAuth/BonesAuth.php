<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Services\BonesAuth;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\PDO\Query;
use Bayfront\RBAC\Auth;
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
     * Groups
     * ############################################################
     */

    /**
     * Get all groups using query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getGroupsCollection(array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_groups')
            ->select(Arr::get($request, 'fields.groups', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get all users in group using a query builder.
     *
     * @param string $group_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getGroupUsersCollection(string $group_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_users')
            ->leftJoin('rbac_group_users', 'rbac_users.id', 'rbac_group_users.user_id')
            ->select(Arr::get($request, 'fields.users', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_group_users.group_id', 'eq', $group_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_users.login']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /*
     * ############################################################
     * Permissions
     * ############################################################
     */

    /**
     * Get all permissions using query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getPermissionsCollection(array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_permissions')
            ->select(Arr::get($request, 'fields.permissions', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

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
     * Get all roles using query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getRolesCollection(array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_roles')
            ->select(Arr::get($request, 'fields.roles', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get all permissions of role using a query builder.
     *
     * @param string $role_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getRolePermissionsCollection(string $role_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_permissions')
            ->leftJoin('rbac_role_permissions', 'rbac_permissions.id', 'rbac_role_permissions.permission_id')
            ->select(Arr::get($request, 'fields.permissions', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_permissions.role_id', 'eq', $role_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_permissions.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get all users with role using a query builder.
     *
     * @param string $role_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getRoleUsersCollection(string $role_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_users')
            ->leftJoin('rbac_role_users', 'rbac_users.id', 'rbac_role_users.user_id')
            ->select(Arr::get($request, 'fields.users', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_users.role_id', 'eq', $role_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_users.login']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

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
     * Get all users using query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getUsersCollection(array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_users')
            ->select(Arr::get($request, 'fields.users', [
                'id',
                'login',
                'email',
                'attributes',
                'enabled',
                'created_at',
                'updated_at'
            ]))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['login']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

    /**
     * Get all roles of user using a query builder.
     *
     * @param string $user_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getUserRolesCollection(string $user_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_roles')
            ->leftJoin('rbac_role_users', 'rbac_roles.id', 'rbac_role_users.role_id')
            ->select(Arr::get($request, 'fields.roles', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_users.user_id', 'eq', $user_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_roles.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }


    /**
     * Get all groups of user using a query builder.
     *
     * @param string $user_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getUserGroupsCollection(string $user_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_groups')
            ->leftJoin('rbac_group_users', 'rbac_groups.id', 'rbac_group_users.group_id')
            ->select(Arr::get($request, 'fields.groups', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_group_users.user_id', 'eq', $user_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_groups.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }


    /**
     * Get all user meta using query builder.
     *
     * @param string $user_id
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getUserMetaCollection(string $user_id, array $request): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_user_meta')
            ->select(Arr::get($request, 'fields.meta', [
                'meta_key',
                'meta_value'
            ]))
            ->where('user_id', 'eq', $user_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['meta_key']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }

}