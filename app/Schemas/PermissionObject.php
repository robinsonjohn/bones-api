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
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        $return = [
            'type' => 'permissions',
            'id' => $array['id']
        ];

        if (array_key_exists('name', $array)) {
            Arr::set($return, 'attributes.name', $array['name']);
        }

        if (array_key_exists('description', $array)) {
            Arr::set($return, 'attributes.description', $array['description']);
        }

        $return['relationships'] = [
            'roles' => [
                'links' => [
                    'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id'] . '/roles'
                ]
            ],
        ];

        $return ['links'] = [
            'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
        ];

        return $return;

    }

}