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

class GroupCollection implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'groups',
            'page'
        ])) {
            throw new InvalidSchemaException('Unable to create GroupCollection schema: missing required keys');
        }

        $groups = [];

        foreach ($array['groups'] as $k => $v) {

            $groups[] = GroupObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['page']);

        return [
            'data' => $groups,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}