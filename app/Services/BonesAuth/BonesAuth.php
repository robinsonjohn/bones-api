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

/*
 * TODO:
 * If all this does is fetch from the database, it should be a model.
 * However, Authenticators should be added, which would make it a service.
 */

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
     * // TODO
     * This may need to be moved to the ApiController, or as
     * part of the BonesApi service- it is not specific to Auth...
     *
     * @param Query $query
     * @param array $request
     *
     * @return array
     */

    protected function _getResults(Query $query, array $request): array
    {

        $results = $query->get();

        $total = $query->getTotalRows();

        // json_decode attributes column

        foreach ($results as $k => $v) {

            if (isset($v['attributes']) && NULL !== $v['attributes']) {

                $results[$k]['attributes'] = json_decode($v['attributes'], true);

            }

        }

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

        return $this->_getResults($query, $request);

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
            ->leftJoin('rbac_group_users', 'rbac_users.id', 'rbac_group_users.userId')
            ->select(Arr::get($request, 'fields.users', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_group_users.groupId', 'eq', $group_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_users.createdAt']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
     * @param array|null $valid_permissions (Restrict results to permission ID(s))
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getPermissionsCollection(array $request, array $valid_permissions = NULL): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_permissions')
            ->select(Arr::get($request, 'fields.permissions', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['name']));

        if (is_array($valid_permissions)) { // Limit results to permission names

            $query->where('name', 'in', implode(',', $valid_permissions));

        }

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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

        return $this->_getResults($query, $request);

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
            ->leftJoin('rbac_role_permissions', 'rbac_permissions.id', 'rbac_role_permissions.permissionId')
            ->select(Arr::get($request, 'fields.permissions', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_permissions.roleId', 'eq', $role_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_permissions.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
            ->leftJoin('rbac_role_users', 'rbac_users.id', 'rbac_role_users.userId')
            ->select(Arr::get($request, 'fields.users', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_users.roleId', 'eq', $role_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_users.createdAt']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
     * @param array|null $valid_groups (Restrict results to users in group(s))
     *
     * @return array
     *
     * @throws QueryException
     */

    public function getUsersCollection(array $request, array $valid_groups = NULL): array
    {

        $query = new Query($this->pdo);

        $query->table('rbac_users')
            ->select(Arr::get($request, 'fields.users', [
                'id',
                'login',
                'firstName',
                'lastName',
                'companyName',
                'email',
                'attributes',
                'enabled',
                'createdAt',
                'updatedAt'
            ]))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['createdAt']));

        if (is_array($valid_groups)) { // Limit results to groups

            $query->leftJoin('rbac_group_users', 'rbac_users.id', 'rbac_group_users.userId')
                ->where('rbac_group_users.groupId', 'in', implode(', ', $valid_groups));

        }

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
            ->leftJoin('rbac_role_users', 'rbac_roles.id', 'rbac_role_users.roleId')
            ->select(Arr::get($request, 'fields.roles', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_role_users.userId', 'eq', $user_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_roles.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
            ->leftJoin('rbac_group_users', 'rbac_groups.id', 'rbac_group_users.groupId')
            ->select(Arr::get($request, 'fields.groups', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->where('rbac_group_users.userId', 'eq', $user_id)
            ->orderBy(Arr::get($request, 'order_by', ['rbac_groups.name']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

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
                'metaKey',
                'metaValue'
            ]))
            ->where('userId', 'eq', $user_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy(Arr::get($request, 'order_by', ['metaKey']));

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return $this->_getResults($query, $request);

    }

    /*
     * TODO:
     * Add different authenticators:
     * - username/password
     * - jwt/refresh token
     * - api key/secret
     * - session id/user id?
     *
     * There should be no reason for the controller to access/use the JWT directly.
     * Simply authenticate using different methods.
     */


}