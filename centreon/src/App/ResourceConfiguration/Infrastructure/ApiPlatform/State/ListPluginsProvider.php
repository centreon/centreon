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

namespace App\ResourceConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ResourceConfiguration\Domain\Aggregate\Option;
use App\ResourceConfiguration\Domain\Aggregate\OptionName;
use App\ResourceConfiguration\Domain\Aggregate\Plugin;
use App\ResourceConfiguration\Domain\Exception\PluginPathOptionDoesNotExistsException;
use App\ResourceConfiguration\Domain\Repository\OptionRepository;
use App\ResourceConfiguration\Domain\Repository\PluginRepository;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\PluginResource;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Transformer\PluginTransformer;
use App\Shared\Infrastructure\ApiPlatform\Transformer\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<PluginResource>
 */
final readonly class ListPluginsProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Plugin,PluginResource> $transformer
     */
    public function __construct(
        #[Autowire(service: PluginTransformer::class)]
        private TransformerInterface $transformer,
        private PluginRepository $pluginRepository,
        private OptionRepository $optionRepository,
    ) {

    }

    /**
     * @return iterable<PluginResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $pluginPathOption = $this->optionRepository->findByName(new OptionName(Option::PLUGIN_PATH_OPTION_NAME));
        if (! $pluginPathOption instanceof Option) {
            throw new PluginPathOptionDoesNotExistsException(['name' => Option::PLUGIN_PATH_OPTION_NAME]);
        }
        $models = $this->pluginRepository->findByPath($pluginPathOption->value->value);
        $resources = [];
        foreach ($models as $model) {
            $resources[] = $this->transformer->toResource($model);
        }

        return $resources;
    }
}
