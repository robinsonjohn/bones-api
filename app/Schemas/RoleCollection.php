<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class RoleCollection implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'roles',
            'page'
        ])) {
            throw new InvalidSchemaException('Unable to create RoleCollection schema: missing required keys');
        }

        $roles = [];

        foreach ($array['roles'] as $k => $v) {

            $roles[] = RoleObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['page']);

        return [
            'data' => $roles,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}