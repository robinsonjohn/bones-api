<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;

class GroupObject implements SchemaInterface
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
            'type' => 'groups',
            'id' => $array['id']
        ];

        if (array_key_exists('name', $array)) {
            Arr::set($return, 'attributes.name', $array['name']);
        }

        if (array_key_exists('createdAt', $array)) {
            Arr::set($return, 'attributes.createdAt', date('c', strtotime($array['createdAt'])));
        }

        if (array_key_exists('updatedAt', $array)) {
            Arr::set($return, 'attributes.updatedAt', date('c', strtotime($array['updatedAt'])));
        }

        $return ['links'] = [
            'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
        ];

        return $return;

    }

}