<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class AuthResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'accessToken',
            'refreshToken',
            'expiresIn',
            'expiresAt'
        ])) {
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        return [
            'data' => [
                'type' => 'token',
                'id' => date('c'),
                'attributes' => [
                    'accessToken' => $array['accessToken'],
                    'refreshToken' => $array['refreshToken'],
                    'type' => 'Bearer',
                    'expiresIn' => $array['expiresIn'],
                    'expiresAt' => $array['expiresAt']
                ]
            ]
        ];
    }

}