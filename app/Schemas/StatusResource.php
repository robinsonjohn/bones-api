<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class StatusResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'status',
            'version'
        ])) {
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        return [
            'data' => [
                'type' => 'status',
                'id' => date('c'),
                'attributes' => [
                    'status' => $array['status'],
                    'version' => $array['version']
                ]
            ]
        ];
    }

}