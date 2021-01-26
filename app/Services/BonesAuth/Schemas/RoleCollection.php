<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Services\BonesAuth\Schemas;

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
            'results',
            'meta'
        ])) {
            throw new InvalidSchemaException('Unable to create RoleCollection schema: missing required keys');
        }

        $roles = [];

        foreach ($array['results'] as $k => $v) {

            $roles[] = RoleObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['meta']);

        return [
            'data' => $roles,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}