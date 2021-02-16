<?php

namespace App\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class GroupResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => GroupObject::create($array, $config)
        ];

    }

}