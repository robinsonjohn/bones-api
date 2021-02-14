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

        if (array_key_exists('login', $array)) {
            Arr::set($return, 'attributes.login', $array['login']);
        }

        if (array_key_exists('firstName', $array)) {
            Arr::set($return, 'attributes.firstName', $array['firstName']);
        }

        if (array_key_exists('lastName', $array)) {
            Arr::set($return, 'attributes.lastName', $array['lastName']);
        }

        if (array_key_exists('companyName', $array)) {
            Arr::set($return, 'attributes.companyName', $array['companyName']);
        }

        if (array_key_exists('email', $array)) {
            Arr::set($return, 'attributes.email', $array['email']);
        }

        if (array_key_exists('enabled', $array)) {
            Arr::set($return, 'attributes.enabled', (bool)$array['enabled']);
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