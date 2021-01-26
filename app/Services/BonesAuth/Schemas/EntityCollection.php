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

class EntityCollection implements SchemaInterface
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
            throw new InvalidSchemaException('Unable to create EntityCollection schema: missing required keys');
        }

        $entities = [];

        foreach ($array['results'] as $k => $v) {

            $entities[] = EntityObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['meta']);

        return [
            'data' => $entities,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}