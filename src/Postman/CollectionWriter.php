<?php

namespace Mpociot\ApiDoc\Postman;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Testing\File;

class CollectionWriter
{
    /**
     * @var Collection
     */
    private $routeGroups;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * CollectionWriter constructor.
     *
     * @param Collection $routeGroups
     */
    public function __construct(Collection $routeGroups, $baseUrl)
    {
        $this->routeGroups = $routeGroups;
        $this->baseUrl = $baseUrl;
    }

    public function getCollection()
    {
        try {
            URL::forceRootUrl($this->baseUrl);
        } catch (\Error $e) {
            echo "Warning: Couldn't force base url as your version of Lumen doesn't have the forceRootUrl method.\n";
            echo "You should probably double check URLs in your generated Postman collection.\n";
        }

        $collection = [
            'variables' => [],
            'info' => [
                'name' => config('apidoc.postman.name') ?: config('app.name').' API',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => config('apidoc.postman.description') ?: '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routeGroups->map(function ($routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => '',
                    'item' => $routes->map(function ($route) {
                        $mode = $route['methods'][0] === 'PUT' ? 'urlencoded' : 'formdata';
                        if ($route['faker']) {
                            $item = $route['faker']::get();
                            $fields = [];
                            foreach ($item as $key => $value) {
                                $fields = array_merge($fields, $this->getDataField($key, $value));
                            }
                            $body = [
                                'mode' => $mode,
                                $mode => $fields,
                            ];
                        } else {
                            $body = [
                                'mode' => $mode,
                                $mode => collect($route['bodyParameters'])->map(function ($parameter, $key) {
                                    return [
                                        'key' => $key,
                                        'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                        'type' => 'text',
                                        'enabled' => true,
                                    ];
                                })->values()->toArray(),
                            ];
                        }
                        $headers = [];
                        if ($route['authenticated']) {
                            $headers[] = ['key'=>'Authorization', 'value'=>'Bearer [token]'];
                        }
                        return [
                            'name' => $route['title'] != '' ? $route['title'] : url($route['uri']),
                            'request' => [
                                'url' => url($route['uri']),
                                'method' => $route['methods'][0],
                                'body' => $body,
                                'description' => $route['description'],
                                'response' => [],
                                'headers' => $headers
                            ],
                        ];
                    })->toArray(),
                ];
            })->values()->toArray(),
        ];
        return json_encode($collection);
    }

    private function serializeKey($array, $namespace = null) {
        $values = [];
        foreach($array as $key => $value) {
            if ($namespace) {
                $formKey = $namespace . '[' . $key . ']';
            } else {
                $formKey = $key;
            }
            if (is_array($value)) {
                $values = array_merge($values, $this->serializeKey($value, $formKey));
            } else {
                $values[$formKey] = $value;
            }
        }
        return $values;
    }

    private function getDataField($key, $value) {
        $data = [];
        if ($value === false || $value === true) {
            $value = $value === true ? 1 : 0;
        }
        if (is_array($value)) {
            $keys = $this->serializeKey($value, $key);
            foreach ($keys as $keySerialized => $valueSerialized) {
                $data = array_merge($data, $this->getDataField($keySerialized, $valueSerialized));
            }
        }
        else if ($value instanceof File) {
            $data[] = [
                'key' => $key,
                'type' => 'file',
                'src' => stream_get_meta_data($value->tempFile)['uri'],
            ];
        }else{
            $data[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'text',
                'enabled' => true,
            ];
        }
        return $data;
    }
}
