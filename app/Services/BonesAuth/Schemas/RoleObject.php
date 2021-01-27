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

class RoleObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'id'
        ])) {
            throw new InvalidSchemaException('Unable to create RoleObject schema: missing required keys');
        }

        return [
            'type' => 'role',
            'id' => $array['id'],
            'attributes' => [
                'organization_id' => Arr::get($array, 'organization_id'),
                'name' => Arr::get($array, 'name'),
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