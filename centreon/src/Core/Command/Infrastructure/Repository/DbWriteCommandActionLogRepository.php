<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Domain\Model\NewCommand;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\CommandMacro\Domain\Model\NewCommandMacro;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbWriteCommandActionLogRepository extends AbstractRepositoryRDB implements WriteCommandRepositoryInterface
{
    use LoggerTrait;
    private const COMMAND_PROPERTIES_MAP = [
        'name' => 'command_name',
        'commandLine' => 'command_line',
        'isShellEnabled' => 'enable_shell',
        'type' => 'command_type',
        'argumentExample' => 'argument_example',
        'arguments' => 'arguments',
        'macros' => 'macros',
        'connectorId' => 'connectors',
        'graphTemplateId' => 'graph_id',
    ];

    public function __construct(
        private readonly WriteCommandRepositoryInterface $writeCommandRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ContactInterface $contact,
        DatabaseConnection $db,
    ) {
        $this->db = $db;
    }

    public function add(NewCommand $command): int
    {
        try {
            $commandId = $this->writeCommandRepository->add($command);
            if ($commandId === 0) {
                throw new RepositoryException('Command ID cannot be 0');
            }

            $actionLog = new ActionLog(
                objectType: ActionLog::OBJECT_TYPE_COMMAND,
                objectId: $commandId,
                objectName: $command->getName(),
                actionType: ActionLog::ACTION_TYPE_ADD,
                contactId: $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            if ($actionLogId === 0) {
                throw new RepositoryException('Action Log ID cannot be 0');
            }
            $actionLog->setId($actionLogId);

            $this->writeActionLogRepository->addActionDetails($actionLog, $this->getCommandAsArray($command));

            return $commandId;
        } catch (\Throwable $ex) {
            $this->error(
                'Error while adding a Command',
                ['command' => $command->getName(), 'trace' => $ex->getTraceAsString()]
            );

            throw $ex;
        }
    }

    /**
     * @param NewCommand $command
     *
     * @return array<string, int|string>
     */
    private function getCommandAsArray(NewCommand $command): array
    {
        $reflection = new \ReflectionClass($command);
        $properties = $reflection->getProperties();

        $commandAsArray = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();
            $propertyValue = $property->getValue($command);
            $commandAsArray[self::COMMAND_PROPERTIES_MAP[$propertyName]] = match ($propertyName) {
                'name', 'commandLine', 'argumentExample' => $propertyValue instanceof TrimmedString
                    ? (string) $propertyValue
                    : '',
                'isShellEnabled' => $propertyValue ? '1' : '0',
                'type' => $propertyValue instanceof CommandType ? CommandTypeConverter::toInt($propertyValue) : '',
                'arguments' => is_array($propertyValue) ? $this->getArgumentsAsString($propertyValue) : '',
                'macros' => is_array($propertyValue) ? $this->getMacrosAsString($propertyValue) : '',
                'connectorId', 'graphTemplateId' => is_int($propertyValue) ? $propertyValue  : '',
                default => '',
            };
        }

        return $commandAsArray;
    }

    /**
     * @param Argument[] $arguments
     *
     * @return string
     */
    private function getArgumentsAsString(array $arguments): string
    {
        $arguments = array_map(
            fn($argument) => $argument->getName() . ' : ' . $argument->getDescription(),
            $arguments
        );
        $argumentsAsString = '';
        if (! empty($arguments)) {
            $argumentsAsString = implode(' ', $arguments);
        }

        return $argumentsAsString;
    }

    /**
     * @param NewCommandMacro[] $macros
     *
     * @return string
     */
    private function getMacrosAsString(array $macros): string
    {
        $macros = array_map(
            function (NewCommandMacro $macro): string {
                $resourceType = $macro->getType() === CommandMacroType::Host
                    ? 'HOST'
                    : 'SERVICE';

                return 'MACRO (' . $resourceType . ') ' . $macro->getName() . ' : '
                    . $macro->getDescription();
            },
            $macros
        );
        $macrosAsString = '';
        if (! empty($macros)) {
            $macrosAsString = implode(' ', $macros);
        }

        return $macrosAsString;
    }
}