<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class EntityObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'id',
            'owner_id',
            'name',
            'attributes',
            'active',
            'created_at',
            'updated_at'
        ])) {
            throw new InvalidSchemaException('Unable to create EntityObject schema: missing required keys');
        }

        return [ // TODO: Not using attributes key
            'type' => 'entity',
            'id' => $array['id'],
            'attributes' => [
                'owner_id' => $array['owner_id'],
                'name' => $array['name'],
                'active' => (bool)$array['active'],
                'created_at' => date('c', strtotime($array['created_at'])),
                'updated_at' => date('c', strtotime($array['updated_at']))
            ],
            'links' => [
                'self' => Arr::get($config, 'link_prefix', '') . '/' . $array['id']
            ]
        ];

    }

}