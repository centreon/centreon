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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreatePollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\CreatePollerResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreatePollerResource, CreatePollerResource>
 */
final readonly class CreatePollerProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Poller, CreatePollerResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceCreatePollerTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): CreatePollerResource
    {
        $command = new CreatePollerCommand(
            name: new PollerName($data->name),
            pollerType: PollerTypeEnum::from($data->pollerType),
            address: $data->address !== null ? new PollerAddress($data->address) : null,
        );

        $model = $this->commandBus->execute($command);
        Assert::isInstanceOf($model, Poller::class);

        return $this->transformer->transform($model);
    }
}
