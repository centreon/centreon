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

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToIntegerNormalizer;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Command array{
 *     command_id: int,
 *     command_name: string,
 *     command_line: string,
 *     command_example?: string|null,
 *     command_type: int,
 *     enable_shell: int,
 *     command_activate: string,
 *     command_locked: int,
 *     connector_id?: int|null,
 *     connector_name?: string|null,
 *     graph_template_id?: int|null,
 *     graph_template_name?: string|null,
 * }
 */
class DbReadCommandRepository extends AbstractRepositoryRDB implements ReadCommandRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $commandId): bool
    {
        $this->info(sprintf('Check existence of command with ID #%d', $commandId));

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.command
                WHERE command_id = :commandId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByIdAndCommandType(int $commandId, CommandType $commandType): bool
    {
        $this->info(
            sprintf(
                'Check existence of command with ID #%d and type %s',
                $commandId,
                CommandTypeConverter::toInt($commandType)
            )
        );

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.command
                WHERE command_id = :commandId
                    AND command_type = :commandType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->bindValue(':commandType', CommandTypeConverter::toInt($commandType), \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByName(TrimmedString $name): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.command
                WHERE command_name = :commandName
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':commandName', $name->value, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $commandId): ?Command
    {
        $request = <<<'SQL'
            SELECT
                command.command_id,
                command.command_name,
                command.command_line,
                command.command_example,
                command.command_type,
                command.enable_shell,
                command.command_activate,
                command.command_locked,
                command.connector_id,
                connector.name as connector_name,
                command.graph_id as graph_template_id,
                giv_graphs_template.name as graph_template_name
            FROM `:db`.command
            LEFT JOIN `:db`.connector
                ON command.connector_id = connector.id
            LEFT JOIN `:db`.giv_graphs_template
                ON command.graph_id = giv_graphs_template.graph_id
            WHERE command.command_id = :commandId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->execute();

        if (! ($result = $statement->fetch(\PDO::FETCH_ASSOC))) {

            return null;
        }

        /** @var _Command $result */
        $command = $this->createCommand($result);
        $command->setArguments($this->findArgumentsByCommandId($commandId));
        $command->setMacros($this->findMacrosByCommandId($commandId));

        return $command;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndTypes(
        RequestParametersInterface $requestParameters,
        array $commandTypes
    ): array {
        $commands = [];

        if ([] === $commandTypes) {
            return $commands;
        }

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'command_id',
            'name' => 'command_name',
            'type' => 'command_type',
            'is_locked' => 'command_locked',
        ]);

        $sqlTranslator->addNormalizer('is_locked', new BoolToIntegerNormalizer());

        $request = <<<'SQL'
            SELECT
                command_id,
                command_name,
                command_line,
                command_type,
                enable_shell,
                command_activate,
                command_locked
            FROM `:db`.command
            SQL;

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect($request);
        $sqlConcatenator->appendWhere('command_type IN (:command_type)');
        $sqlConcatenator->storeBindValueMultiple(
            ':command_type',
            array_map(
                fn (CommandType $commandType): int => CommandTypeConverter::toInt($commandType),
                $commandTypes
            ),
            \PDO::PARAM_INT
        );
        $sqlTranslator->translateForConcatenator($sqlConcatenator);
        $statement = $this->db->prepare($this->translateDbName((string) $sqlConcatenator));
        $sqlTranslator->bindSearchValues($statement);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();
        $sqlTranslator->calculateNumberOfRows($this->db);

        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _Command $result */
            $commands[] = $this->createCommand($result);
        }

        return $commands;
    }

    /**
     * @param _Command $data
     *
     * @throws AssertionFailedException
     *
     * @return Command
     */
    private function createCommand(array $data): Command
    {
        return new Command(
            id: (int) $data['command_id'],
            name: $data['command_name'],
            commandLine: html_entity_decode($data['command_line']),
            type: CommandTypeConverter::fromInt((int) $data['command_type']),
            isShellEnabled: (bool) $data['enable_shell'],
            isActivated: $data['command_activate'] === '1',
            isLocked: (bool) $data['command_locked'],
            argumentExample: $data['command_example'] ?? '',
            connector: isset($data['connector_id']) && isset($data['connector_name'])
                ? new SimpleEntity(
                    id: $data['connector_id'],
                    name: new TrimmedString($data['connector_name']),
                    objectName: 'connector'
                )
                : null,
            graphTemplate: isset($data['graph_template_id']) && isset($data['graph_template_name'])
                ? new SimpleEntity(
                    id: $data['graph_template_id'],
                    name: new TrimmedString($data['graph_template_name']),
                    objectName: 'graphTemplate'
                )
                : null,
        );
    }

    /**
     * @param int $commandId
     *
     * @throws \Throwable
     *
     * @return Argument[]
     */
    private function findArgumentsByCommandId(int $commandId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT macro_name, macro_description
                FROM command_arg_description
                WHERE cmd_id = :commandId
                SQL
        ));
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $arguments = [];
        /** @var array{
         *      macro_name: string,
         *      macro_description: string|null
         *  } $result
         */
        foreach ($statement as $result) {
            $arguments[] = new Argument(
                name: new TrimmedString($result['macro_name']),
                description: new TrimmedString($result['macro_description'] ?? '')
            );
        }

        return $arguments;
    }

    /**
     * @param int $commandId
     *
     * @throws \Throwable
     *
     * @return CommandMacro[]
     */
    private function findMacrosByCommandId(int $commandId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT command_macro_name, command_macro_desciption, command_macro_type
                FROM on_demand_macro_command
                WHERE command_command_id = :commandId
                SQL
        ));
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $macros = [];
        /** @var array{
         *      command_macro_name: string,
         *      command_macro_desciption: string|null,
         *      command_macro_type: string
         *  } $result
         */
        foreach ($statement as $result) {
            $macro = new CommandMacro(
                commandId: $commandId,
                type: CommandMacroType::from($result['command_macro_type']),
                name: $result['command_macro_name']
            );
            $macro->setDescription($result['command_macro_desciption'] ?? '');
            $macros[] = $macro;
        }

        return $macros;
    }
}
