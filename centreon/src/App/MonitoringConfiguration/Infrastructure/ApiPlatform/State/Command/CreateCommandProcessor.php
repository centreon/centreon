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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Legacy\LegacySecurity;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<CreateCommandInput, CommandResource>
 */
final readonly class CreateCommandProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<ServiceCategory, ServiceCategoryResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceCommandTransformer::class)]
        private TransformerInterface $transformer,
        private LegacySecurity $legacySecurity,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): CommandResource
    {
        $userId = $this->legacySecurity->getUserId();

        $commandId = $this->commandBus->handle(
            new \App\MonitoringConfiguration\Application\Command\CreateCommandCommand(
                name: $data->name,
                type: $data->type,
                commandLine: $data->commandLine,
                isShellEnabled: $data->isShellEnabled,
                connectorId: $data->connectorId,
                createdBy: $userId,
            )
        );

        $model = $this->commandBus->handle(
            new \App\MonitoringConfiguration\Application\Command\FindCommandByIdCommand(
                id: $commandId,
            )
        );

        return $this->transformer->transform($model);
    }
}
