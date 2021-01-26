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

class UserCollection implements SchemaInterface
{

    /**
     * @inheritDoc
     */

    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'results',
            'meta'
        ])) {
            throw new InvalidSchemaException('Unable to create UserCollection schema: missing required keys');
        }

        $users = [];

        foreach ($array['results'] as $k => $v) {

            $users[] = UserObject::create($v, $config);

        }

        $meta_results = ResourceCollectionMetaResults::create($array['meta']);

        return [
            'data' => $users,
            'meta' => [
                'results' => $meta_results
            ],
            'links' => ResourceCollectionPagination::create($meta_results, $config)
        ];

    }

}