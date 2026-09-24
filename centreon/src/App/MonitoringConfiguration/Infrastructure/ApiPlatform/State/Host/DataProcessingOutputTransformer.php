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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostEventHandlerCommandOutput;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements TransformerInterface<DataProcessing, DataProcessingOutput>
 */
final readonly class DataProcessingOutputTransformer implements TransformerInterface
{
    public function __construct(
        private CommandRepository $commandRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): DataProcessingOutput
    {
        $eventHandler = null;
        if ($from->eventHandlerCommandId instanceof CommandId) {
            $command = $this->commandRepository->getById($from->eventHandlerCommandId);
            $eventHandler = new HostEventHandlerCommandOutput($command->id()->value, $command->name->value);
        }

        // On a Cloud platform the on-premise-only members are not part of the contract.
        return new DataProcessingOutput(
            checkFreshness: $from->checkFreshness,
            freshnessThreshold: $from->freshnessThreshold,
            eventHandlerEnabled: $from->eventHandlerEnabled,
            eventHandler: $eventHandler,
            acknowledgmentTimeout: $this->isCloudPlatform ? null : $from->acknowledgmentTimeout,
            flapDetectionEnabled: $this->isCloudPlatform ? null : $from->flapDetectionEnabled,
            lowFlapThreshold: $this->isCloudPlatform ? null : $from->lowFlapThreshold,
            highFlapThreshold: $this->isCloudPlatform ? null : $from->highFlapThreshold,
            eventHandlerArgs: $this->isCloudPlatform ? [] : $from->eventHandlerArgs,
        );
    }
}
