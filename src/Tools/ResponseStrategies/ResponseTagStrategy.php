<?php

namespace Mpociot\ApiDoc\Tools\ResponseStrategies;

use Illuminate\Routing\Route;
use Illuminate\Http\JsonResponse;
use Mpociot\Reflection\DocBlock\Tag;
use League\Fractal\Resource\Item;
use League\Fractal\Manager;

/**
 * Get a response from the docblock ( @response ).
 */
class ResponseTagStrategy
{
    /**
     * @param Route $route
     * @param array $tags
     * @param array $routeProps
     *
     * @return array|null
     */
    public function __invoke(Route $route, array $tags, array $routeProps, $controller, $method)
    {
        return $this->getDocBlockResponses($tags);
    }

    protected function iterateResponse(&$array) {
        if (is_array($array)) {
            foreach ($array as $key => &$value) {
                if(isset($value['transformer']) && isset($value['transformerModel'])) {
                    $fractal = new Manager();
                    $model = $value['transformerModel'];
                    $transformer = $value['transformer'];
                    $modelInstance = TransformerTagsStrategy::instantiateTransformerModel($value['transformerModel']);
                    $modelTransformed = new Item($modelInstance, new $transformer);
                    $value = $fractal->createData($modelTransformed)->toArray()['data'];
                }
                $this->iterateResponse($value);
            }
        }
    }

    /**
     * Get the response from the docblock if available.
     *
     * @param array $tags
     *
     * @return array|null
     */
    protected function getDocBlockResponses(array $tags)
    {
        $responseTags = array_values(
            array_filter($tags, function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'response';
            })
        );
        if (empty($responseTags)) {
            return;
        }
        return array_map(function (Tag $responseTag) {
            preg_match('/^(\d{3})?\s?([\s\S]*)$/', $responseTag->getContent(), $result);

            $status = $result[1] ?: 200;
            $content = $result[2] ?: '{}';
            $content_obj = json_decode($content, true);
            $this->iterateResponse($content_obj);
            return new JsonResponse($content_obj, (int) $status);
        }, $responseTags);
    }
}
