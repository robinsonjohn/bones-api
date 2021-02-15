<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class ResourceCollectionMetaResults implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'count',
            'total',
            'pages',
            'page_size',
            'page_number'
        ])) {
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        /*
         * All $array keys should already be integers
         */

        return Arr::only($array, [
            'count',
            'total',
            'pages',
            'page_size',
            'page_number'
        ]);

    }

}