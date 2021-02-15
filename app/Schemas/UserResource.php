<?php

namespace App\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class UserResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => UserObject::create($array, $config)
        ];

    }

}