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
use Psr\Log\LoggerInterface;
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
    private const MAX_ITEMS_BY_REQUEST = 100;

    /**
     * @param DatabaseConnection $db
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseConnection $db, readonly private LoggerInterface $logger)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $commandId): bool
    {
        $this->logger->info(sprintf('Check existence of command with ID #%d', $commandId));

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
        $this->logger->info(
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
    public function findByIds(array $commandIds): array
    {
        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect(
            <<<'SQL'
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
                SQL
        );
        $sqlConcatenator->appendWhere('command_id IN (:command_ids)');
        $sqlConcatenator->storeBindValueMultiple(
            ':command_ids',
            $commandIds,
            \PDO::PARAM_INT
        );

        $statement = $this->db->prepare($this->translateDbName((string) $sqlConcatenator));
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $commands = [];
        foreach ($statement as $result) {
            /** @var _Command $result */
            $command = $this->createCommand($result);

            $commands[$command->getId()] = $command;
        }

        return $commands;
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
     * @inheritDoc
     */
    public function findAll(): \Iterator&\Countable {
        $this->logger->debug(sprintf('Loading commands in blocks of %d elements', self::MAX_ITEMS_BY_REQUEST));

        return new class (
            $this->db,
            self::MAX_ITEMS_BY_REQUEST,
            $this->createCommand(...),
            $this->findArgumentsByCommandId(...),
            $this->findMacrosByCommandId(...),
            $this->logger
        )   extends AbstractRepositoryRDB
            implements \Iterator, \Countable
        {
            /** @var callable */
            protected $createCommand;

            /** @var callable */
            protected $findArguments;

            /** @var callable */
            protected $findMacros;

            private int $position = 0;

            private int $requestIndex = 0;

            private int $totalItems = 0;

            private false|\PDOStatement $statement = false;

            /**
             * @var false|array{
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
            private false|array $currentItem;

            public function __construct(
                protected DatabaseConnection $db,
                readonly private int $maxItemByRequest,
                callable $createCommand,
                callable $findArguments,
                callable $findMacros,
                readonly private LoggerInterface $logger
            ) {
                $this->createCommand = $createCommand;
                $this->findArguments = $findArguments;
                $this->findMacros = $findMacros;
            }

            public function current(): Command
            {
                return $this->createCommandWithArgumentsAndMacros();
            }

            public function next(): void
            {
                $this->position++;
                if (! $this->statement || ($nextItem = $this->statement->fetch()) === false) {
                    if ($this->valid()) {
                        // There are still items to be retrieved
                        $this->requestIndex += $this->maxItemByRequest;
                        $this->loadDatabase();
                    }
                } else {
                    /**
                     * @var array{
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
                     * } $nextItem
                     */
                    $this->currentItem = $nextItem;
                }
            }

            public function key(): int
            {
                return $this->position;
            }

            public function valid(): bool
            {
                return $this->position < $this->totalItems;
            }

            public function rewind(): void
            {
                $this->position = 0;
                $this->requestIndex = 0;
                $this->totalItems = 0;
                $this->loadDatabase();
            }

            public function count(): int
            {
                if (! $this->statement) {
                    $request = <<<'SQL'
                        SELECT COUNT(*)
                        FROM `:db`.command
                        SQL;

                    $this->statement = $this->db->query($this->translateDbName($request))
                        ?: throw new \Exception('Impossible to retrieve a PDO Statement');
                    $this->totalItems = (int) $this->statement->fetchColumn();
                }

                return $this->totalItems;
            }

            private function loadDatabase(): void
            {
                $this->logger->debug(
                    sprintf(
                        'Loading commands from %d/%d',
                        $this->requestIndex,
                        ($this->requestIndex + $this->maxItemByRequest)
                    )
                );
                $request = <<<'SQL_WRAP'
SELECT SQL_CALC_FOUND_ROWS
    command_id,
    command_name,
    command_line,
    command_type,
    enable_shell,
    command_activate,
    command_locked
FROM `:db`.command
WHERE command_type != :excludedCommandType
ORDER BY command_id
LIMIT :from, :max_item_by_request
SQL_WRAP;

                $this->statement = $this->db->prepare($this->translateDbName($request));
                $this->statement->bindValue(
                    ':excludedCommandType',
                    CommandTypeConverter::toInt(CommandType::Notification),
                    \PDO::PARAM_INT
                );
                $this->statement->bindValue(':from', $this->requestIndex, \PDO::PARAM_INT);
                $this->statement->bindValue(':max_item_by_request', $this->maxItemByRequest, \PDO::PARAM_INT);
                $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
                $this->statement->execute();

                $result = $this->db->query('SELECT FOUND_ROWS()');

                if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                    $this->totalItems = (int) $total;
                }
                /**
                 * @var false|array{
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
                 * } $result
                 */
                $result = $this->statement->fetch();
                $this->currentItem = $result;
            }

            private function createCommandWithArgumentsAndMacros(): Command
            {
                $command = ($this->createCommand)($this->currentItem);
                $command->setArguments(($this->findArguments)($command->getId()));
                $command->setMacros(($this->findMacros)($command->getId()));

                return $command;
            }
        };
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
                    objectName: 'Connector'
                )
                : null,
            graphTemplate: isset($data['graph_template_id']) && isset($data['graph_template_name'])
                ? new SimpleEntity(
                    id: $data['graph_template_id'],
                    name: new TrimmedString($data['graph_template_name']),
                    objectName: 'GraphTemplate'
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
                name: new TrimmedString(str_replace(':', '', $result['macro_name'])),
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
