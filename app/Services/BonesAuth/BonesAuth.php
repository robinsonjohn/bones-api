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

        $query = new Query($this->db);

        if (!empty(Arr::except($request['fields'], [ // Allowed field keys
            'entities'
        ]))) {

            throw new BadRequestException('Unable to get entities: invalid request');

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

}