<?php

namespace App\Services\BonesAuth\Schemas;

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
            'access_token',
            'refresh_token',
            'expires_in'
        ])) {
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        return [
            'data' => [
                'type' => 'token',
                'id' => date('c'),
                'attributes' => [
                    'access_token' => $array['access_token'],
                    'refresh_token' => $array['refresh_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => $array['expires_in']
                ]
            ]
        ];
    }

}