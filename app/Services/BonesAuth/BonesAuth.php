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
use Bayfront\Auth\Exceptions\InvalidEntityException;
use Bayfront\PDO\Exceptions\QueryException;
use Bayfront\PDO\Query;
use PDO;

class BonesAuth extends Auth
{

    public function __construct(PDO $pdo, string $pepper)
    {
        parent::__construct($pdo, $pepper);
    }

    /**
     * Return results in a standardized format.
     *
     * @param Query $query
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
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

        $query = new Query($this->db);

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
     * Entities
     * ############################################################
     */

    /**
     * Get entity collection using a query builder.
     *
     * @param array $request
     *
     * @return array
     *
     * @throws QueryException
     * @throws BadRequestException
     */

    public function getEntityCollection(array $request): array
    {

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'entities'
        ]))) {

            throw new BadRequestException('Unable to get entities: invalid request');

        }

        if (isset($request['fields']['entities'])) {

            $request['fields']['entities'][] = 'id'; // "id" column is required

            $request['fields']['entities'] = array_unique(array_filter($request['fields']['entities'])); // Remove blank and duplicate values

        }

        $query = new Query($this->db);

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

        return $this->_returnResults($query, $request);

    }

    /**
     * Get entity permission collection using a query builder.
     *
     * @param string $entity_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidEntityException
     */

    public function getEntityPermissionCollection(string $entity_id, array $request): array
    {

        if (!$this->entityIdExists($entity_id)) {
            throw new InvalidEntityException('Unable to get entity permissions: entity ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'permissions'
        ]))) {

            throw new BadRequestException('Unable to get entity permissions: invalid request');

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

        $query = new Query($this->db);

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
                throw new BadRequestException('Unable to get entity permissions: invalid request');
            }

            foreach ($filter as $operator => $value) {

                // Prefix the table name to the column for the LEFT JOIN clause

                $query->where('user_permissions.' . $column, $operator, $value);

            }

        }

        return $this->_returnResults($query, $request);

    }


    /**
     * Get entity user collection using a query builder.
     *
     * @param string $entity_id
     * @param array $request
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws QueryException
     * @throws InvalidEntityException
     */

    public function getEntityUserCollection(string $entity_id, array $request): array
    {

        if (!$this->entityIdExists($entity_id)) {
            throw new InvalidEntityException('Unable to get entity users: entity ID does not exist');
        }

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'users'
        ]))) {

            throw new BadRequestException('Unable to get entity users: invalid request');

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

        $query = new Query($this->db);

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
                throw new BadRequestException('Unable to get entity users: invalid request');
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

        $query = new Query($this->db);

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


}