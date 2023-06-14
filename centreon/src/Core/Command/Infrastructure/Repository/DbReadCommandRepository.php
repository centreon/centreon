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
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToIntegerNormalizer;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Command array{
 *     command_id: int,
 *     command_name: string,
 *     command_line: string,
 *     command_type: int,
 *     enable_shell: int,
 *     command_activate: string,
 *     command_locked: int
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
    public function findByRequestParameterAndTypes(
        RequestParametersInterface $requestParameters,
        array $commandTypes
    ): array {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'command_id',
            'name' => 'command_name',
            'type' => 'command_type',
            'is_locked' => 'command_locked',
        ]);

        $sqlTranslator->addNormalizer('is_locked', new BoolToIntegerNormalizer());

        $commands = [];

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
            (int) $data['command_id'],
            $data['command_name'],
            html_entity_decode($data['command_line']),
            CommandTypeConverter::fromInt((int) $data['command_type']),
            (bool) $data['enable_shell'],
            $data['command_activate'] === '1',
            (bool) $data['command_locked'],
        );
    }
}
