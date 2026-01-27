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
use App\MonitoringConfiguration\Application\Command\DuplicateCommandsCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DuplicateCommandInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\DuplicateCommandResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<DuplicateCommandInput, DuplicateCommandResource[]>
 */
final readonly class DuplicateCommandsProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Command, CommandResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceCommandTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        Assert::isInstanceOf($data, DuplicateCommandInput::class);

        /** @var array<int, string> */
        $allowedTypes = $this->getAllowedCommandTypes();

        if ($allowedTypes === []) {
            throw new AccessDeniedException('You are not allowed to duplicate commands');
        }

        /** @var array{
         *     duplicated?: array<Command>,
         *     missing?: array<int>,
         *     access_denied?: array<int>,
         * } $duplicatedCommandsResult */
        $duplicatedCommandsResult = $this->commandBus->execute(
            new DuplicateCommandsCommand(
                commandIds: array_map(fn (int $id): CommandId => new CommandId($id), $data->ids),
                duplicatedBy: $this->security->getUser()->credential->userId->value,
                allowedTypes: $allowedTypes,
            )
        );

        return $this->buildResultsFromResponse($duplicatedCommandsResult);
    }

    /**
     * @return array<string>
     */
    private function getAllowedCommandTypes(): array
    {
        $allowedTypes = [];
        foreach (CommandTypeEnum::cases() as $commandType) {
            $writePermission = Command::getWritePermissionForType($commandType);
            if ($this->security->isGranted($writePermission->value)) {
                $allowedTypes[] = $commandType->name;
            }
        }

        return $allowedTypes;
    }

    /**
     * @param array{
     *     duplicated?: array<Command>,
     *     missing?: array<int>,
     *     access_denied?: array<int>,
     * } $duplicatedCommandsResult
     *
     * @return array<DuplicateCommandResource>
     */
    private function buildResultsFromResponse(array $duplicatedCommandsResult): array
    {
        $results = [];

        $this->addSuccessfulResults($results, $duplicatedCommandsResult['duplicated'] ?? []);
        $this->addMissingResults($results, $duplicatedCommandsResult['missing'] ?? []);
        $this->addDeniedResults($results, $duplicatedCommandsResult['access_denied'] ?? []);

        return $results;
    }

    /**
     * @param array<DuplicateCommandResource> $results
     * @param array<Command> $duplicatedCommands
     */
    private function addSuccessfulResults(array &$results, array $duplicatedCommands): void
    {
        foreach ($duplicatedCommands as $duplicatedCommand) {
            $duplicatedCommandResource = $this->transformer->transform($duplicatedCommand);
            $results[] = new DuplicateCommandResource(
                command: $duplicatedCommandResource,
                status: 204,
                message: 'Command duplicated successfully'
            );
        }
    }

    /**
     * @param array<DuplicateCommandResource> $results
     * @param array<int> $missingIds
     */
    private function addMissingResults(array &$results, array $missingIds): void
    {
        foreach ($missingIds as $missingId) {
            $results[] = new DuplicateCommandResource(
                command: null,
                status: 404,
                message: "Command with ID {$missingId} not found"
            );
        }
    }

    /**
     * @param array<DuplicateCommandResource> $results
     * @param array<int> $deniedIds
     */
    private function addDeniedResults(array &$results, array $deniedIds): void
    {
        foreach ($deniedIds as $deniedId) {
            $results[] = new DuplicateCommandResource(
                command: null,
                status: 403,
                message: "You are not allowed to duplicate command with ID {$deniedId}"
            );
        }
    }
}
