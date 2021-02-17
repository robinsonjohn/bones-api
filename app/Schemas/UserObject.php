<?php

namespace App\Schemas;

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
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        $return = [
            'type' => 'users',
            'id' => $array['id']
        ];

        $order = [
            'login',
            'firstName',
            'lastName',
            'companyName',
            'email',
            'enabled',
            'createdAt',
            'updatedAt'
        ];

        $attributes = Arr::only(Arr::order($array, $order), $order);

        if (array_key_exists('createdAt', $attributes)) {
            $attributes['createdAt'] = date('c', strtotime($array['createdAt']));
        }

        if (array_key_exists('updatedAt', $attributes)) {
            $attributes['updatedAt'] = date('c', strtotime($array['updatedAt']));
        }

        if (!empty($attributes)) {
            $return['attributes'] = $attributes;
        }

        $return['links'] = [
            'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
        ];

        return $return;

    }

}