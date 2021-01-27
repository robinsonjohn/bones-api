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

class OrganizationObject implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'id'
        ])) {
            throw new InvalidSchemaException('Unable to create OrganizationObject schema: missing required keys');
        }

        $return = [
            'type' => 'organization',
            'id' => $array['id'],
            'attributes' => [
            ],
            'links' => [
                'self' => Arr::get($config, 'object_prefix', '') . '/' . $array['id']
            ]
        ];

        if (isset($array['owner_id'])) {
            Arr::set($return, 'attributes.owner_id', $array['owner_id']);
        }

        if (isset($array['name'])) {
            Arr::set($return, 'attributes.name', $array['name']);
        }

        if (isset($array['active'])) {
            Arr::set($return, 'attributes.active', (bool)$array['active']);
        }

        if (isset($array['created_at'])) {
            Arr::set($return, 'attributes.created_at', date('c', strtotime($array['created_at'])));
        }

        if (isset($array['updated_at'])) {
            Arr::set($return, 'attributes.updated_at', date('c', strtotime($array['updated_at'])));
        }

        return $return;

    }

}