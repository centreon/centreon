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

use ApiPlatform\Symfony\Routing\ApiLoader;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\AgentConfiguration\InstallationCommandResource as AgentConfigurationInstallationCommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CreateCommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\DuplicateCommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\ListCommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ConnectorResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ListPluginResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\PluginResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\InstallationCommandResource as PollerInstallationCommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\StandardMacroResource;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouteCollection;

/**
 * Duplicates the routes of already-migrated API Platform resources under the legacy /api/latest
 * prefix, for backward compatibility with clients still calling that prefix.
 */
final readonly class LegacyApiPrefixAliasLoader implements RouteLoaderInterface
{
    /** @var list<class-string> */
    private const LEGACY_ALIAS_RESOURCE_CLASSES = [
        AgentConfigurationInstallationCommandResource::class,
        CommandResource::class,
        CreateCommandResource::class,
        DuplicateCommandResource::class,
        ListCommandResource::class,
        ConnectorResource::class,
        GlobalMacroResource::class,
        ListPluginResource::class,
        PluginResource::class,
        PollerInstallationCommandResource::class,
        PollerResource::class,
        ServiceCategoryResource::class,
        StandardMacroResource::class,
        // Referenced by FQCN string, not ::class: App\Upgrade isn't a Deptrac-tracked bounded
        // context yet, and this is the one place Shared legitimately needs to reach into it.
        'App\Upgrade\Infrastructure\ApiPlatform\Resource\UpdateResource',
    ];

    public function __construct(
        #[Autowire(service: 'api_platform.route_loader')]
        private ApiLoader $apiLoader,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $aliasedRoutes = new RouteCollection();

        foreach ($this->apiLoader->load('.', 'api_platform') as $name => $route) {
            $resourceClass = $route->getDefault('_api_resource_class');

            // Keep auxiliary/meta routes (docs, entrypoint, genid, jsonld context)
            if ($resourceClass !== null && ! in_array($resourceClass, self::LEGACY_ALIAS_RESOURCE_CLASSES, true)) {
                continue;
            }

            $aliasedRoutes->add($name, $route);
        }

        $aliasedRoutes->addPrefix('/api/latest');
        $aliasedRoutes->addNamePrefix('legacy_');

        return $aliasedRoutes;
    }
}
