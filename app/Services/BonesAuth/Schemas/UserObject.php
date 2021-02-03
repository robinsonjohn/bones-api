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

        $return = [
            'type' => 'user',
            'id' => $array['id']
        ];

        if (array_key_exists('login', $array)) {
            Arr::set($return, 'attributes.login', $array['login']);
        }

        if (array_key_exists('email', $array)) {
            Arr::set($return, 'attributes.email', $array['email']);
        }

        if (array_key_exists('enabled', $array)) {
            Arr::set($return, 'attributes.enabled', (bool)$array['enabled']);
        }

        if (array_key_exists('created_at', $array)) {
            Arr::set($return, 'attributes.created_at', date('c', strtotime($array['created_at'])));
        }

        if (array_key_exists('updated_at', $array)) {
            Arr::set($return, 'attributes.updated_at', date('c', strtotime($array['updated_at'])));
        }

        $return ['links'] = [
            'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
        ];

        return $return;

    }

}