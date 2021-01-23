<?php

namespace App\Models;

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
     */

    public function getEntities(array $request)
    {

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