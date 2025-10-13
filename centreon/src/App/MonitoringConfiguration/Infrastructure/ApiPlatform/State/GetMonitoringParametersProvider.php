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
use App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters\MonitoringParameters;
use App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters\MonitoringParametersFactory;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\MonitoringParametersResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<MonitoringParametersResource>
 */
final readonly class GetMonitoringParametersProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<MonitoringParameters,MonitoringParametersResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceMonitoringParametersTransformer::class)]
        private TransformerInterface $transformer,
        private OptionRepository $repository,
    ) {
    }

    /**
     * @return MonitoringParametersResource
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MonitoringParametersResource
    {
        $monitoringParametersOptions = $this->repository->findMonitoringParameters();
        $monitoringParameters = MonitoringParametersFactory::fromOptions($monitoringParametersOptions);

        return $this->transformer->transform($monitoringParameters);
    }
}
