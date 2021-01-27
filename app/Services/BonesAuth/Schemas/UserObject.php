<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Services\BonesAuth\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class UserObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'id'
        ])) {
            throw new InvalidSchemaException('Unable to create UserObject schema: missing required keys');
        }

        return [
            'type' => 'user',
            'id' => $array['id'],
            'attributes' => [
                'login' => Arr::get($array, 'login'),
                'email' => Arr::get($array, 'email'),
                'active' => (bool)Arr::get($array, 'active'),
                'created_at' => date('c', strtotime(Arr::get($array, 'created_at'))),
                'updated_at' => date('c', strtotime(Arr::get($array, 'updated_at')))
            ],
            'links' => [
                'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
            ]
        ];

    }

}