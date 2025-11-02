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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreateCommandCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CreateCommandResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Legacy\LegacySecurity;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateCommandResource, CommandResource>
 */
final readonly class CreateCommandProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Command, CommandResource> $transformer
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
        $type = CommandTypeEnum::fromName($data->type);
        Assert::notNull($type);

        $command = new CreateCommandCommand(
            name: new CommandName($data->name),
            type: $type,
            commandLine: new CommandLine($data->commandLine),
            isShellEnabled: $data->isShellEnabled,
            connectorId: $data->connector
                ? new ConnectorId($data->connector->id)
                : null,
            creatorId: $this->legacySecurity->getUserId(),
            comment: $data->comment !== null
                ? new CommandComment($data->comment)
                : null,
        );

        $model = $this->commandBus->execute($command);
        Assert::isInstanceOf($model, Command::class);

        return $this->transformer->transform($model);
    }
}
