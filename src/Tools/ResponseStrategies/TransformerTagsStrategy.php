<?php

namespace Mpociot\ApiDoc\Tools\ResponseStrategies;

use ReflectionClass;
use ReflectionMethod;
use League\Fractal\Manager;
use Illuminate\Routing\Route;
use Mpociot\ApiDoc\Tools\Flags;
use League\Fractal\Resource\Item;
use Mpociot\Reflection\DocBlock\Tag;
use League\Fractal\Resource\Collection;

/**
 * Parse a transformer response from the docblock ( @transformer || @transformercollection ).
 */
class TransformerTagsStrategy
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
        return $this->getTransformerResponse($tags, $controller, $method);
    }

    /**
     * Get a response from the transformer tags.
     *
     * @param array $tags
     *
     * @return array|null
     */
    protected function getTransformerResponse(array $tags, $controller, $method)
    {
        try {
            $properties = $controller->getDefaultProperties();
            $methodName = $method->getName();
            $fractal = new Manager();

            if (! is_null(config('apidoc.fractal.serializer'))) {
                $fractal->setSerializer(app(config('apidoc.fractal.serializer')));
            }

            if (isset($properties[$methodName.'Presenter'])) {
                $presenter = new $properties[$methodName.'Presenter']();
                $transformer = get_class($presenter->getTransformer());
                $model = $this->getClassToBeTransformed($tags, (new ReflectionClass($transformer))->getMethod('transform'));
                $modelInstance = self::instantiateTransformerModel($model);
                $resource = $methodName == 'index'
                    ? new Collection([$modelInstance, $modelInstance], new $transformer)
                    : new Item($modelInstance, new $transformer);
                $response = response($fractal->createData($resource)->toArray()['data']);
            }
            else {
                if (empty($transformerTag = $this->getTransformerTag($tags))) {
                    return;
                }
                $transformer = $this->getTransformerClass($transformerTag);
                if ($transformerTag->getName() == 'transformerModel') {
                    $model = $this->getClassToBeTransformed($tags, null);
                    $modelInstance = self::instantiateTransformerModel($model);
                    $response = response($modelInstance);
                } else {
                    if (method_exists($transformer, 'getTransformer')) {
                        $transformer = new $transformer;
                        $transformer = get_class($transformer->getTransformer());
                    }
                    $model = $this->getClassToBeTransformed($tags, (new ReflectionClass($transformer))->getMethod('transform'));
                    $modelInstance = self::instantiateTransformerModel($model);

                    $resource = (strtolower($transformerTag->getName()) == 'transformercollection')
                        ? new Collection([$modelInstance, $modelInstance], new $transformer)
                        : new Item($modelInstance, new $transformer);
                    $response = response($fractal->createData($resource)->toArray()['data']);
                }
            }

            return [$response];
        } catch (\Exception $e) {
            dd($e->getMessage());
            return;
        }
    }

    /**
     * @param Tag $tag
     *
     * @return string|null
     */
    private function getTransformerClass($tag)
    {
        return $tag->getContent();
    }

    /**
     * @param array $tags
     * @param ReflectionMethod $transformerMethod
     *
     * @return null|string
     */
    private function getClassToBeTransformed(array $tags, ReflectionMethod $transformerMethod = null)
    {
        $modelTag = array_first(array_filter($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'transformermodel';
        }));

        $type = null;
        if ($modelTag) {
            $type = $modelTag->getContent();
        } else {
            $parameter = array_first($transformerMethod->getParameters());
            if ($parameter->hasType() && ! $parameter->getType()->isBuiltin() && class_exists((string) $parameter->getType())) {
                // ladies and gentlemen, we have a type!
                $type = (string) $parameter->getType();
            }
        }

        return $type;
    }

    /**
     * @param string $type
     *
     * @return mixed
     */
    public static function instantiateTransformerModel(string $type, $conditions = null)
    {
        if (Flags::$shouldBeVerbose) {
            echo "Eloquent model factory failed to instantiate {$type}; trying to fetch from database";
        }

        $instance = new $type;
        if ($instance instanceof \Illuminate\Database\Eloquent\Model) {
            try {
                // we can't use a factory but can try to get one from the database
                if($conditions) {
                    $firstInstance = $instance->where(function($query) use ($conditions){
                        foreach($conditions as $k => $q) {
                            $query->where($k, $q);
                        }
                    })->first();
                } else {
                    $firstInstance = $type::first();
                }
                if ($firstInstance) {
                    return $firstInstance;
                }
            } catch (\Exception $e) {
                // okay, we'll stick with `new`
                if (Flags::$shouldBeVerbose) {
                    echo "Failed to fetch first {$type} from database; using `new` to instantiate";
                }
            }
        }

        return $instance;
    }

    /**
     * @param array $tags
     *
     * @return Tag|null
     */
    private function getTransformerTag(array $tags)
    {
        $transFormerTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['transformer', 'transformercollection', 'transformermodel']);
            })
        );
        return array_first($transFormerTags);
    }
}
