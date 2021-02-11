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

class UserMetaObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'metaKey'
        ])) {
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        $return = [
            'type' => 'userMeta',
            'id' => $array['metaKey']
        ];

        if (array_key_exists('metaValue', $array)) {
            Arr::set($return, 'attributes.value', $array['metaValue']);
        }

        $return ['links'] = [
            'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['metaKey']
        ];

        return $return;

    }

}