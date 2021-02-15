<?php

namespace App\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class RoleResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => RoleObject::create($array, $config)
        ];

    }

}