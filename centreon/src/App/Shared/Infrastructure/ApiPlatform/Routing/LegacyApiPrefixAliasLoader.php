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

namespace App\Shared\Infrastructure\ApiPlatform\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\RouteCollection;

/**
 * Duplicates the routes of already-migrated API Platform operations under the legacy /api/latest
 * prefix, for backward compatibility with clients still calling that prefix.
 *
 * The allowlist of operations to alias is contributed by {@see LegacyApiAliasOperationProviderInterface}
 * services (core plus any module), so a module keeps its released endpoints reachable under
 * /api/latest without touching this loader.
 */
final readonly class LegacyApiPrefixAliasLoader implements RouteLoaderInterface
{
    /** @var list<string> */
    private array $allowlist;

    /**
     * @param iterable<LegacyApiAliasOperationProviderInterface> $operationProviders
     */
    public function __construct(
        #[Autowire(service: 'api_platform.route_loader')]
        private LoaderInterface $apiLoader,
        #[AutowireIterator(LegacyApiAliasOperationProviderInterface::TAG)]
        iterable $operationProviders,
    ) {
        $operationNames = [];
        foreach ($operationProviders as $provider) {
            foreach ($provider->getOperationNames() as $operationName) {
                $operationNames[] = $operationName;
            }
        }

        $this->allowlist = array_values(array_unique($operationNames));
    }

    public function __invoke(): RouteCollection
    {
        $aliasedRoutes = new RouteCollection();

        // api_platform.route_loader (ApiPlatform's ApiLoader) always returns a RouteCollection;
        // LoaderInterface::load() only widens the declared return to mixed.
        $apiRoutes = $this->apiLoader->load('.', 'api_platform');
        \assert($apiRoutes instanceof RouteCollection);

        foreach ($apiRoutes as $name => $route) {
            $operationName = $route->getDefault('_api_operation_name');

            // Keep auxiliary/meta routes (docs, entrypoint, genid, jsonld context)
            if ($operationName !== null && ! in_array($operationName, $this->allowlist, true)) {
                continue;
            }

            $aliasedRoutes->add($name, $route);
        }

        $aliasedRoutes->addPrefix('/api/latest');
        $aliasedRoutes->addNamePrefix('legacy_');

        return $aliasedRoutes;
    }
}
