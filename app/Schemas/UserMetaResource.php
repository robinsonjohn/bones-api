<?php

namespace App\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class UserMetaResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => UserMetaObject::create($array, $config)
        ];

    }

}