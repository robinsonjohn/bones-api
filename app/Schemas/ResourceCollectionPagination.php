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

class ResourceCollectionPagination implements SchemaInterface
{

    /**
     * @inheritDoc
     */
    public static function create(array $array, array $config = []): array
    {

        if (Arr::isMissing($array, [
            'total',
            'pages',
            'page_size',
            'page_number'
        ])) {
            throw new InvalidSchemaException('Unable to create ResourceCollectionPagination schema: missing required keys');
        }

        /*
         * All $array keys should already be integers
         */

        /*
         * TODO:
         * Links need to preserve any other preexisting query parameters
         */

        $links['self'] = Arr::get($config, 'link_prefix' , '') . '?' . Arr::query([
                'page' => [
                    'size' => $array['page_size'],
                    'number' => $array['page_number']
                ]
            ]);

        $links['first'] = Arr::get($config, 'link_prefix' , '') . '?' . Arr::query([
                'page' => [
                    'size' => $array['page_size'],
                    'number' => 1
                ]
            ]);

        if ($array['page_number'] > 1) {

            $links['prev'] = Arr::get($config, 'link_prefix' , '') . '?' . Arr::query([
                    'page' => [
                        'size' => $array['page_size'],
                        'number' => $array['page_number'] - 1
                    ]
                ]);

        } else {

            $links['prev'] = NULL;

        }

        if ($array['pages'] > 1 && $array['pages'] - $array['page_number'] > 1) {

            $links['next'] = Arr::get($config, 'link_prefix' , '') . '?' . Arr::query([
                    'page' => [
                        'size' => $array['page_size'],
                        'number' => $array['page_number'] + 1
                    ]
                ]);

        } else {

            $links['next'] = NULL;

        }

        $links['last'] = Arr::get($config, 'link_prefix' , '') . '?' . Arr::query([
                'page' => [
                    'size' => $array['page_size'],
                    'number' => $array['pages']
                ]
            ]);

        return $links;

    }

}