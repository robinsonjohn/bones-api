<?php

namespace App\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class PermissionResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => PermissionObject::create($array, $config)
        ];

    }

}