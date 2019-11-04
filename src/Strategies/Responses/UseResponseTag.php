<?php

namespace Mpociot\ApiDoc\Strategies\Responses;

use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use Mpociot\ApiDoc\Strategies\Strategy;
use Mpociot\ApiDoc\Tools\RouteDocBlocker;
use League\Fractal\Resource\Item;
use League\Fractal\Manager;

/**
 * Get a response from the docblock ( @response ).
 */
class UseResponseTag extends Strategy
{
    /**
     * @param Route $route
     * @param \ReflectionClass $controller
     * @param \ReflectionMethod $method
     * @param array $routeRules
     * @param array $context
     *
     * @throws \Exception
     *
     * @return array|null
     */
    public function __invoke(Route $route, \ReflectionClass $controller, \ReflectionMethod $method, array $routeRules, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        return $this->getDocBlockResponses($methodDocBlock->getTags());
    }

    protected function iterateResponse(&$array) {
        if (is_array($array)) {
            foreach ($array as $key => &$value) {
                if(isset($value['transformer']) && isset($value['transformerModel'])) {
                    $fractal = new Manager();
                    $model = $value['transformerModel'];
                    $transformer = $value['transformer'];
                    $query = null;
                    if (isset($value['query'])) {
                        $query = $value['query'];
                    }
                    $modelInstance = TransformerTagsStrategy::instantiateTransformerModel($value['transformerModel'], $query);
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
            return null;
        }

        $responses = array_map(function (Tag $responseTag) {
            preg_match('/^(\d{3})?\s?([\s\S]*)$/', $responseTag->getContent(), $result);

            $status = $result[1] ?: 200;
            $content = $result[2] ?: '{}';
            $this->iterateResponse($content);
            return [$content, (int) $status];
        }, $responseTags);

        // Convert responses to [200 => 'response', 401 => 'response']
        return collect($responses)->pluck('0', '1')->toArray();
    }
}
