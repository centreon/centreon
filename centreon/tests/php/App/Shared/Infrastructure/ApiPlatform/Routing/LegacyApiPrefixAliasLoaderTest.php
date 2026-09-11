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

use App\Shared\Infrastructure\ApiPlatform\Routing\CoreLegacyApiAliasOperations;
use App\Shared\Infrastructure\ApiPlatform\Routing\LegacyApiAliasOperationProviderInterface;
use App\Shared\Infrastructure\ApiPlatform\Routing\LegacyApiPrefixAliasLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
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

        // API Platform derives a NotExposed item operation for any resource that exposes no
        // readable item GET, purely to build IRIs. Such an operation keeps the default
        // short-name shape (_api_/<name>/{id}{._format}_get) — unlike a resource that pins an
        // explicit uriTemplate — and the legacy /api/latest prefix never exposed it. Discover
        // them instead of naming a resource, so the coverage survives any single one pinning
        // its own uriTemplate later.
        $generatedOperationNames = [];
        foreach ($routes as $route) {
            $operationName = $route->getDefault('_api_operation_name');
            if (
                is_string($operationName)
                && preg_match('#^_api_/[^/]+/\{id\}\{\._format\}_get$#', $operationName) === 1
                && str_starts_with($route->getPath(), '/api/')
                && ! str_starts_with($route->getPath(), '/api/latest/')
            ) {
                $generatedOperationNames[$operationName] = true;
            }
        }

        self::assertNotEmpty(
            $generatedOperationNames,
            'No API Platform auto-generated item operation found, otherwise this test no longer covers anything.',
        );

        $aliasedUnderLegacy = [];
        foreach ($routes as $route) {
            $operationName = $route->getDefault('_api_operation_name');
            if (
                is_string($operationName)
                && isset($generatedOperationNames[$operationName])
                && str_starts_with($route->getPath(), '/api/latest/')
            ) {
                $aliasedUnderLegacy[$operationName] = $route->getPath();
            }
        }

        self::assertSame(
            [],
            $aliasedUnderLegacy,
            'Auto-generated item operations must not be duplicated under /api/latest.',
        );
    }

    public function testOnlyAllowlistedOperationsAppearInTheLegacyAlias(): void
    {
        // In the test kernel only the core provider is registered (no module), so the aliased
        // operations must all come from the core allowlist.
        $allowlistedOperations = (new CoreLegacyApiAliasOperations())->getOperationNames();
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
                . 'is not contributed by any LegacyApiAliasOperationProviderInterface.',
            );
        }
    }

    /**
     * A module extends the /api/latest allowlist purely by shipping a
     * LegacyApiAliasOperationProviderInterface service (auto-tagged by the new kernel). This is the
     * regression fix for MON-208750: released module endpoints must keep their /api/latest alias.
     */
    public function testModuleContributedProviderExtendsTheLegacyAlias(): void
    {
        $routes = new RouteCollection();
        $routes->add('core_op', $this->apiRoute('/configuration/commands', '_api_/configuration/commands_get_collection'));
        $routes->add('module_op', $this->apiRoute('/bam/configuration/business-activities', '_api_/bam/configuration/business-activities_get_collection'));
        $routes->add('generated_op', $this->apiRoute('/pollers/{id}', '_api_/pollers/{id}_get'));
        $routes->add('api_doc', $this->apiRoute('/docs', null));

        $apiLoader = $this->createMock(LoaderInterface::class);
        $apiLoader->method('load')->willReturn($routes);

        $loader = new LegacyApiPrefixAliasLoader($apiLoader, [
            new CoreLegacyApiAliasOperations(),
            $this->providerFor('_api_/bam/configuration/business-activities_get_collection'),
        ]);

        $aliased = ($loader)();

        $moduleRoute = $aliased->get('legacy_module_op');

        self::assertNotNull($aliased->get('legacy_core_op'), 'A core-listed operation must be aliased.');
        self::assertNotNull($moduleRoute, 'A module-contributed operation must be aliased.');
        self::assertNotNull($aliased->get('legacy_api_doc'), 'Meta routes (no operation) are always aliased.');
        self::assertNull($aliased->get('legacy_generated_op'), 'An operation no provider lists must not be aliased.');
        self::assertSame('/api/latest/bam/configuration/business-activities', $moduleRoute->getPath());
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

    private function apiRoute(string $path, ?string $operationName): Route
    {
        return new Route($path, $operationName !== null ? ['_api_operation_name' => $operationName] : []);
    }

    private function providerFor(string ...$operationNames): LegacyApiAliasOperationProviderInterface
    {
        return new class (array_values($operationNames)) implements LegacyApiAliasOperationProviderInterface {
            /**
             * @param list<string> $operationNames
             */
            public function __construct(private readonly array $operationNames)
            {
            }

            public function getOperationNames(): array
            {
                return $this->operationNames;
            }
        };
    }
}
