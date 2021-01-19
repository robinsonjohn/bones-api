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

class StatusResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'status',
            'version'
        ])) {
            throw new InvalidSchemaException('Unable to create StatusResource schema: missing required keys');
        }

        return [
            'data' => [
                'type' => 'status',
                'id' => date('c'),
                'attributes' => [
                    'status' => $array['status'],
                    'version' => $array['version']
                ]
            ]
        ];
    }

}