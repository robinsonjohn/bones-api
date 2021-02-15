<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class UserCollection implements SchemaInterface
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
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        $data = [];

        foreach ($array['results'] as $k => $v) {

            $data[] = UserObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['meta']);

        return [
            'data' => $data,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}