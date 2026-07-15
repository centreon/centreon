<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\ApiPlatform\Routing;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use App\Shared\Infrastructure\ApiPlatform\Routing\LegacyApiPrefixAliasLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LegacyApiPrefixAliasLoaderTest extends KernelTestCase
{
    public function testAllowlistedResourceKeepsItsLegacyAlias(): void
    {
        $routes = $this->getRouteCollection();

        $canonical = $this->findRouteByResourceAndPath($routes, CommandResource::class, '/api/configuration/commands/{id}');
        $legacyAlias = $this->findRouteByResourceAndPath($routes, CommandResource::class, '/api/latest/configuration/commands/{id}');

        self::assertNotNull($canonical, 'CommandResource should be reachable under /api.');
        self::assertNotNull($legacyAlias, 'CommandResource is on the legacy allowlist, it should also be reachable under /api/latest.');
    }

    public function testOnlyAllowlistedResourceClassesAppearInTheLegacyAlias(): void
    {
        $allowlist = new \ReflectionClassConstant(LegacyApiPrefixAliasLoader::class, 'LEGACY_ALIAS_RESOURCE_CLASSES');
        /** @var list<class-string> $allowlistedClasses */
        $allowlistedClasses = $allowlist->getValue();
        self::assertNotEmpty($allowlistedClasses);

        $routes = $this->getRouteCollection();
        foreach ($routes as $name => $route) {
            if (! str_starts_with($name, 'legacy_')) {
                continue;
            }

            $resourceClass = $route->getDefault('_api_resource_class');
            if ($resourceClass === null) {
                continue; // docs, entrypoint, genid, jsonld context.
            }
            self::assertIsString($resourceClass);

            self::assertContains(
                $resourceClass,
                $allowlistedClasses,
                "Route \"{$name}\" is prefixed /api/latest but its resource ({$resourceClass}) "
                . 'is not on LegacyApiPrefixAliasLoader::LEGACY_ALIAS_RESOURCE_CLASSES.',
            );
        }
    }

    public function testAuxiliaryDocumentationRoutesAreDuplicatedRegardlessOfResources(): void
    {
        $routes = $this->getRouteCollection();

        self::assertNotNull($routes->get('api_entrypoint'));
        self::assertNotNull($routes->get('legacy_api_entrypoint'));
        self::assertNotNull($routes->get('api_doc'));
        self::assertNotNull($routes->get('legacy_api_doc'));
    }

    private function getRouteCollection(): RouteCollection
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        return $router->getRouteCollection();
    }

    private function findRouteByResourceAndPath(
        RouteCollection $routes,
        string $resourceClass,
        string $path,
    ): ?Route {
        foreach ($routes as $route) {
            if ($route->getDefault('_api_resource_class') === $resourceClass && $route->getPath() === $path) {
                return $route;
            }
        }

        return null;
    }
}
