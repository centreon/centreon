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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Option\Option;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\Plugin;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\PluginName;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\PluginRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\PluginResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<PluginResource>
 */
final readonly class FindPluginProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Plugin, PluginResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourcePluginTransformer::class)]
        private TransformerInterface $transformer,
        private PluginRepository $pluginRepository,
        private OptionRepository $optionRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PluginResource
    {
        Assert::string($uriVariables['plugin_name']);
        $pluginPathOption = $this->optionRepository->getByName(new OptionName(Option::PLUGIN_PATH_OPTION_NAME));

        $plugin = $this->pluginRepository->getByPathAndName(
            path: $pluginPathOption->value,
            name: new PluginName($uriVariables['plugin_name'])
        );

        return $this->transformer->transform($plugin);
    }
}
