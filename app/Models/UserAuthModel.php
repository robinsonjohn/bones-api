<?php

namespace App\Models;

use App\Exceptions\InvalidRequestException;
use Bayfront\ArrayHelpers\Arr;
use Bayfront\Bones\Model;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\PDO\Query;

/**
 * UserAuthModel model.
 */
class UserAuthModel extends Model
{

    /**
     * Get entities.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws InvalidRequestException
     */

    public function getEntities(array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'entities'
        ]))) {

            throw new InvalidRequestException('Unable to get entities: invalid request');

        }

        if (isset($request['fields']['entities'])) {

            $request['fields']['entities'][] = 'id'; // "id" column is required

            $request['fields']['entities'] = array_unique(array_filter($request['fields']['entities'])); // Remove blank and duplicate values

        }

        $query->table('user_entities')
            ->select(Arr::get($request, 'fields.entities', ['*']))
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            foreach ($filter as $operator => $value) {

                $query->where($column, $operator, $value);

            }

        }

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get entity permissions.
     *
     * @param string $entity_id
     * @param array $request
     *
     * @return array
     *
     * @throws InvalidRequestException
     * @throws QueryException
     */

    public function getEntityPermissions(string $entity_id, array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'permissions'
        ]))) {

            throw new InvalidRequestException('Unable to get entity permissions: invalid request');

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

        $query->table('user_permissions')
            ->leftJoin('user_entity_permissions', 'user_permissions.id', 'user_entity_permissions.permission_id')
            ->select($fields)
            ->where('user_entity_permissions.entity_id', 'eq', $entity_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the entity ID

            if ($column == 'user_entity_permissions.entity_id') {
                throw new InvalidRequestException('Unable to get entity permissions: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_permissions.' . $column, $operator, $value);

            }

        }

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get entity users.
     *
     * @param string $entity_id
     * @param array $request
     *
     * @return array
     *
     * @throws InvalidRequestException
     * @throws QueryException
     */

    public function getEntityUsers(string $entity_id, array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'users'
        ]))) {

            throw new InvalidRequestException('Unable to get entity users: invalid request');

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

        $query->table('user_users')
            ->leftJoin('user_user_entities', 'user_users.id', 'user_user_entities.user_id')
            ->select($fields)
            ->where('user_user_entities.entity_id', 'eq', $entity_id)
            ->limit($request['limit'])
            ->offset($request['offset'])
            ->orderBy($request['order_by']);

        foreach ($request['filters'] as $column => $filter) {

            // Do not allow request to filter the entity ID

            if ($column == 'user_user_entities.entity_id') {
                throw new InvalidRequestException('Unable to get entity users: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_users.' . $column, $operator, $value);

            }

        }

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get groups.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws InvalidRequestException
     * @throws QueryException
     */

    public function getGroups(array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'groups'
        ]))) {

            throw new InvalidRequestException('Unable to get groups: invalid request');

        }

        if (isset($request['fields']['groups'])) {

            $request['fields']['groups'][] = 'id'; // "id" column is required

            $request['fields']['groups'] = array_unique(array_filter($request['fields']['groups'])); // Remove blank and duplicate values

        }

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

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get permissions.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws InvalidRequestException
     */

    public function getPermissions(array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'permissions'
        ]))) {

            throw new InvalidRequestException('Unable to get permissions: invalid request');

        }

        if (isset($request['fields']['permissions'])) {

            $request['fields']['permissions'][] = 'id'; // "id" column is required

            $request['fields']['permissions'] = array_unique(array_filter($request['fields']['permissions'])); // Remove blank and duplicate values

        }

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

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get roles.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws InvalidRequestException
     * @throws QueryException
     */

    public function getRoles(array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'roles'
        ]))) {

            throw new InvalidRequestException('Unable to get roles: invalid request');

        }

        if (isset($request['fields']['roles'])) {

            $request['fields']['roles'][] = 'id'; // "id" column is required

            $request['fields']['roles'] = array_unique(array_filter($request['fields']['roles'])); // Remove blank and duplicate values

        }

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

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

    /**
     * Get users.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws InvalidRequestException
     * @throws QueryException
     */

    public function getUsers(array $request): array
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'users'
        ]))) {

            throw new InvalidRequestException('Unable to get users: invalid request');

        }

        if (isset($request['fields']['users'])) {

            $request['fields']['users'][] = 'id'; // "id" column is required

            $request['fields']['users'] = array_unique(array_filter($request['fields']['users'])); // Remove blank and duplicate values

        }

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

        return [
            'results' => $query->get(),
            'total' => $query->getTotalRows()
        ];

    }

}