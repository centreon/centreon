<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Connector\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Connector\Application\Repository\ReadConnectorRepositoryInterface;
use Core\Connector\Domain\Model\Connector;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _Connector array{
 *     id: int,
 *     name: string,
 *     command_line: string,
 *     description: string|null,
 *     enabled: int,
 *     command_ids:string|null
 * }
 */
class DbReadConnectorRepository extends AbstractRepositoryRDB implements ReadConnectorRepositoryInterface
{
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
    public function exists(int $id): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.connector
                WHERE id = :id
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndCommandTypes(
        RequestParametersInterface $requestParameters,
        array $commandTypes
    ): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator->setConcordanceArray([
            'id' => 'connector.id',
            'name' => 'connector.name',
            'command.id' => 'command.command_id',
            'command.name' => 'command.command_name',
        ]);

        $commandTypeFilter = '';
        if ($commandTypes !== []) {
            $commandTypeFilter = <<<'SQL'
                AND `command`.command_type IN (:command_type)
                SQL;
        }
        $request = <<<SQL
            SELECT
                `connector`.id,
                `connector`.name,
                `connector`.command_line,
                `connector`.description,
                `connector`.enabled,
                GROUP_CONCAT(DISTINCT `command`.command_id) AS command_ids
            FROM `:db`.`connector`
            LEFT JOIN `:db`.`command`
                ON `command`.connector_id = `connector`.id
                {$commandTypeFilter}
            SQL;

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect($request);
        $sqlConcatenator->appendGroupBy(
            <<<'SQL'
                GROUP BY `connector`.id
                SQL
        );

        if ($commandTypes !== []) {
            $sqlConcatenator->storeBindValueMultiple(
                ':command_type',
                array_map(
                    fn(CommandType $commandType): int => CommandTypeConverter::toInt($commandType),
                    $commandTypes
                ),
                \PDO::PARAM_INT
            );
        }
        $sqlTranslator->translateForConcatenator($sqlConcatenator);
        $statement = $this->db->prepare($this->translateDbName((string) $sqlConcatenator));
        $sqlTranslator->bindSearchValues($statement);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();
        $sqlTranslator->calculateNumberOfRows($this->db);

        $connectors = [];
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _Connector $result */
            $connectors[] = new Connector(
                id: $result['id'],
                name: $result['name'],
                commandLine: $result['command_line'],
                description: $result['description'] ?? '',
                isActivated: (bool) $result['enabled'],
                commandIds: $result['command_ids']
                    ? array_map(fn(string $commandId) => (int) $commandId, explode(',', $result['command_ids']))
                    : []
            );
        }

        return $connectors;
    }
}
