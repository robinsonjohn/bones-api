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

    public function getEntities(array $request)
    {

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'entities'
        ]))) {

            throw new InvalidRequestException('Unable to get entities: invalid request');

        }

        if (isset($request['fields']['entities'])) {

            $request['fields']['entities'] = array_filter($request['fields']['entities']); // Remove blank values

            /*
             * The "id" column is required for the schema, so ensure it will be returned.
             */

            if (!in_array('id', $request['fields']['entities'])) {

                $request['fields']['entities'][] = 'id';

            }

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

    public function getGroups(array $request)
    {

    }

    public function getPermissions(array $request)
    {

    }

    public function getRoles(array $request)
    {

    }

    public function getUsers(array $request)
    {

    }

}