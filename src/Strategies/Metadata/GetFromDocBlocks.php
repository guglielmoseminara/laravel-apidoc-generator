<?php

namespace Mpociot\ApiDoc\Strategies\Metadata;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use Mpociot\ApiDoc\Strategies\Strategy;
use Mpociot\ApiDoc\Tools\RouteDocBlocker;

class GetFromDocBlocks extends Strategy
{
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionMethod $method, array $routeRules, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];
        $properties = $controller->getDefaultProperties();
        $description = $methodDocBlock->getLongDescription()->getContents();
        $faker = $this->getFaker($methodDocBlock->getTags());
        if (isset($properties['resourceName'])) {
            if ($method->getName() == 'index') {
                if (empty($description)) {
                    $description = trans()->get('apidoc::rules.index', ['resource' => $properties['resourceName']]);
                }
            } else if ($method->getName() == 'show') {
                if (empty($description)) {
                    $description = trans()->get('apidoc::rules.show', ['resource' => $properties['resourceName']]);;
                }
            } else if ($method->getName() == 'store') {
                if (empty($description)) {
                    $description = trans()->get('apidoc::rules.store', ['resource' => $properties['resourceName']]);;
                }
            } else if ($method->getName() == 'update') {
                if (empty($description)) {
                    $description = trans()->get('apidoc::rules.update', ['resource' => $properties['resourceName']]);;
                }
            } else if ($method->getName() == 'destroy') {
                if (empty($description)) {
                    $description = trans()->get('apidoc::rules.destroy', ['resource' => $properties['resourceName']]);;
                }
            }
        }

        list($routeGroupName, $routeGroupDescription, $routeTitle) = $this->getRouteGroupDescriptionAndTitle($methodDocBlock, $docBlocks['class']);

        return [
                'groupName' => $routeGroupName,
                'groupDescription' => $routeGroupDescription,
                'title' => $routeTitle ?: $methodDocBlock->getShortDescription(),
                'description' => $description,
                'authenticated' => $this->getAuthStatusFromDocBlock($methodDocBlock->getTags()),
                'faker' => $faker,
        ];
    }

    /**
     * @param array $tags Tags in the method doc block
     *
     * @return bool
     */
    protected function getAuthStatusFromDocBlock(array $tags)
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'authenticated';
            });

        return (bool) $authTag;
    }

    /**
     * @param DocBlock $methodDocBlock
     * @param DocBlock $controllerDocBlock
     *
     * @return array The route group name, the group description, ad the route title
     */
    protected function getRouteGroupDescriptionAndTitle(DocBlock $methodDocBlock, DocBlock $controllerDocBlock)
    {
        // @group tag on the method overrides that on the controller
        if (! empty($methodDocBlock->getTags())) {
            foreach ($methodDocBlock->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    $routeGroupParts = explode("\n", trim($tag->getContent()));
                    $routeGroupName = array_shift($routeGroupParts);
                    $routeGroupDescription = trim(implode("\n", $routeGroupParts));

                    // If the route has no title (the methodDocBlock's "short description"),
                    // we'll assume the routeGroupDescription is actually the title
                    // Something like this:
                    // /**
                    //   * Fetch cars. <-- This is route title.
                    //   * @group Cars <-- This is group name.
                    //   * APIs for cars. <-- This is group description (not required).
                    //   **/
                    // VS
                    // /**
                    //   * @group Cars <-- This is group name.
                    //   * Fetch cars. <-- This is route title, NOT group description.
                    //   **/

                    // BTW, this is a spaghetti way of doing this.
                    // It shall be refactored soon. Deus vult!💪
                    if (empty($methodDocBlock->getShortDescription())) {
                        return [$routeGroupName, '', $routeGroupDescription];
                    }

                    return [$routeGroupName, $routeGroupDescription, $methodDocBlock->getShortDescription()];
                }
            }
        }

        foreach ($controllerDocBlock->getTags() as $tag) {
            if ($tag->getName() === 'group') {
                $routeGroupParts = explode("\n", trim($tag->getContent()));
                $routeGroupName = array_shift($routeGroupParts);
                $routeGroupDescription = implode("\n", $routeGroupParts);

                return [$routeGroupName, $routeGroupDescription, $methodDocBlock->getShortDescription()];
            }
        }

        return [$this->config->get('default_group'), '', $methodDocBlock->getShortDescription()];
    }

    protected function getFaker(array $tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'faker';
            })
            ->map(function ($tag) {
                return $tag->getContent();
            })->first();
        return $parameters;
    }
}
