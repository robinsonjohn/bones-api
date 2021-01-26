<?php

/**
 * @package bones-api
 * @link https://github.com/bayfrontmedia/bones-api
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2021 Bayfront Media
 */

namespace App\Services\BonesAuth\Schemas;

use Bayfront\ArraySchema\SchemaInterface;

class RoleResource implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        return [
            'data' => RoleObject::create($array, $config)
        ];

    }

}