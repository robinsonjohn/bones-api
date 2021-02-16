<?php

namespace App\Schemas;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\ArraySchema\InvalidSchemaException;
use Bayfront\ArraySchema\SchemaInterface;
use Bayfront\HttpRequest\Request;

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
            $class = str_replace(__NAMESPACE__ . '\\', '', get_called_class());
            throw new InvalidSchemaException('Unable to create ' . $class . ' schema: missing required keys');
        }

        /*
         * All $array keys should already be integers
         *
         * By defining $query_string, existing parameters are retained in the links
         */

        $query_string = Request::getQuery();

        // Self

        $links['self'] = Arr::get($config, 'collection_prefix', '') . '?' . Arr::query(array_replace($query_string, [
                'page' => [
                    'size' => Arr::get($query_string, 'page.size', $array['page_size']),
                    'number' => $array['page_number']
                ]
            ]));

        // First

        $links['first'] = Arr::get($config, 'collection_prefix', '') . '?' . Arr::query(array_replace($query_string, [
                'page' => [
                    'size' => Arr::get($query_string, 'page.size', $array['page_size']),
                    'number' => 1
                ]
            ]));

        // Prev

        if ($array['page_number'] > 1) {

            $links['prev'] = Arr::get($config, 'collection_prefix', '') . '?' . Arr::query(array_replace($query_string, [
                    'page' => [
                        'size' => Arr::get($query_string, 'page.size', $array['page_size']),
                        'number' => $array['page_number'] - 1
                    ]
                ]));

        } else {

            $links['prev'] = NULL;

        }

        // Next

        if ($array['pages'] > 1 && $array['pages'] > $array['page_number']) {

            $links['next'] = Arr::get($config, 'collection_prefix', '') . '?' . Arr::query(array_replace($query_string, [
                    'page' => [
                        'size' => Arr::get($query_string, 'page.size', $array['page_size']),
                        'number' => $array['page_number'] + 1
                    ]
                ]));

        } else {

            $links['next'] = NULL;

        }

        // Last

        $links['last'] = Arr::get($config, 'collection_prefix', '') . '?' . Arr::query(array_replace($query_string, [
                'page' => [
                    'size' => Arr::get($query_string, 'page.size', $array['page_size']),
                    'number' => $array['pages']
                ]
            ]));

        return $links;

    }

}