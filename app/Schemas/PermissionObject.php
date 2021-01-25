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

class PermissionObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'id'
        ])) {
            throw new InvalidSchemaException('Unable to create PermissionObject schema: missing required keys');
        }

        $return = [
            'type' => 'permission',
            'id' => $array['id'],
            'attributes' => [
            ],
            'links' => [
                'self' => Arr::get($config, 'link_prefix', '') . '/' . $array['id']
            ]
        ];

        if (isset($array['name'])) {
            Arr::set($return, 'attributes.name', $array['name']);
        }

        if (isset($array['description'])) {
            Arr::set($return, 'attributes.description', $array['description']);
        }

        return $return;

    }

}