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

use App\Shared\Infrastructure\ApiPlatform\Routing\LegacyApiPrefixAliasLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LegacyApiPrefixAliasLoaderTest extends KernelTestCase
{
    public function testAllowlistedOperationKeepsItsLegacyAlias(): void
    {
        $routes = $this->getRouteCollection();

        $canonical = $this->findRouteByOperationAndPath(
            $routes,
            '_api_/configuration/commands/{id}_get',
            '/api/configuration/commands/{id}',
        );
        $legacyAlias = $this->findRouteByOperationAndPath(
            $routes,
            '_api_/configuration/commands/{id}_get',
            '/api/latest/configuration/commands/{id}',
        );

        self::assertNotNull($canonical, 'The command item operation should be reachable under /api.');
        self::assertNotNull($legacyAlias, 'The command item operation is on the legacy allowlist, it should also be reachable under /api/latest.');
    }

    public function testOperationsApiPlatformGeneratesOnItsOwnAreNotAliased(): void
    {
        $routes = $this->getRouteCollection();

        // API Platform derives this item operation from PollerResource to build IRIs, it is not
        // an endpoint the legacy /api/latest prefix ever exposed.
        $generatedOperation = '_api_/pollers/{id}{._format}_get';

        self::assertNotNull(
            $this->findRouteByOperationAndPath($routes, $generatedOperation, '/api/pollers/{id}.{_format}'),
            'The generated item operation is expected under /api, otherwise this test no longer covers anything.',
        );
        self::assertNull(
            $this->findRouteByOperationAndPath($routes, $generatedOperation, '/api/latest/pollers/{id}.{_format}'),
            'A generated item operation must not be duplicated under /api/latest.',
        );
    }

    public function testOnlyAllowlistedOperationsAppearInTheLegacyAlias(): void
    {
        $allowlist = new \ReflectionClassConstant(LegacyApiPrefixAliasLoader::class, 'LEGACY_ALIAS_OPERATION_NAMES');
        /** @var list<string> $allowlistedOperations */
        $allowlistedOperations = $allowlist->getValue();
        self::assertNotEmpty($allowlistedOperations);

        $routes = $this->getRouteCollection();
        foreach ($routes as $name => $route) {
            if (! str_starts_with($name, 'legacy_')) {
                continue;
            }

            $operationName = $route->getDefault('_api_operation_name');
            if ($operationName === null) {
                continue; // docs, entrypoint, genid, jsonld context.
            }
            self::assertIsString($operationName);

            self::assertContains(
                $operationName,
                $allowlistedOperations,
                "Route \"{$name}\" is prefixed /api/latest but its operation ({$operationName}) "
                . 'is not on LegacyApiPrefixAliasLoader::LEGACY_ALIAS_OPERATION_NAMES.',
            );
        }
    }

    public function testAuxiliaryDocumentationRoutesAreDuplicatedRegardlessOfOperations(): void
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

    private function findRouteByOperationAndPath(
        RouteCollection $routes,
        string $operationName,
        string $path,
    ): ?Route {
        foreach ($routes as $route) {
            if ($route->getDefault('_api_operation_name') === $operationName && $route->getPath() === $path) {
                return $route;
            }
        }

        return null;
    }
}
