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

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\DuplicateCommandCommand;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DuplicateCommandInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\DuplicateCommandResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Legacy\LegacySecurity;
use App\Shared\Infrastructure\TransformerInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<DuplicateCommandResource, DuplicateCommandResource[]>
 */
final readonly class DuplicateCommandsProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceCommandTransformer::class)]
        private TransformerInterface $transformer,
        private LegacySecurity $legacySecurity,
        private Security $security,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        Assert::isInstanceOf($data, DuplicateCommandInput::class);
        /** @var DuplicateCommandInput $data */

        foreach (CommandTypeEnum::cases() as $commandType) {
            $writePermission = Command::getWritePermissionForType($commandType);

            if ($this->security->isGranted($writePermission->value)) {
                $allowedTypes[] = $commandType->name;
            }
        }

        $results = [];

        foreach ($data->ids as $id) {
            try {
                for ($i = 1; $i <= $duplicateCommandCommand->nbDuplicates; $i++) {
                    $originalCommand = $this->commandBus->execute(
                        new DuplicateCommandCommand(
                            commandId: $id,
                            duplicatedBy: $this->legacySecurity->getUserId(),
                            allowedTypes: $allowedTypes,
                        )
                    );

                    $originalCommandResource = $this->transformer->transform($originalCommand);
                    $results[] = new DuplicateCommandResource(
                            command: $originalCommandResource,
                            status: 204,
                            message: 'Command duplicated successfully'
                        );
                }
            } catch (CommandNotFoundException $e) {
                $results[] = new DuplicateCommandResource(
                    command: null,
                    status: 404,
                    message: "Command with ID {$id} not found."
                );
            } catch (AccessDeniedException $e) {
                $results[] = new DuplicateCommandResource(
                    command: null,
                    status: 403,
                    message: "You are not allowed to duplicate command with ID {$id}."
                );
            } catch (\Exception $e) {
                $results[] = new DuplicateCommandResource(
                    command: null,
                    status: 500,
                    message: "An error occurred while duplicating command with ID {$id}: " . $e->getMessage()
                );
            }
        }

        return $results;
    }
}
