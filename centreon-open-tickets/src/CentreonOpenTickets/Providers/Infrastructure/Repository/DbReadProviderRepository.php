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

namespace CentreonOpenTickets\Providers\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use CentreonOpenTickets\Providers\Application\Repository\ReadProviderRepositoryInterface;
use CentreonOpenTickets\Providers\Domain\Model\Provider;
use CentreonOpenTickets\Providers\Domain\Model\ProviderType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;

/**
 * @phpstan-type _Provider array{
 *   rule_id:int,
 *   alias:string,
 *   provider_id:int,
 *   activate:int
 * }
 */
class DbReadProviderRepository extends AbstractRepositoryRDB implements ReadProviderRepositoryInterface
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
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'alias',
            'is_activated' => 'activate',
        ]);

        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());

        $request = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    rule_id,
                    alias,
                    provider_id,
                    activate
                FROM `:db`.mod_open_tickets_rule
            SQL;

        // handle search
        $request .= $sqlTranslator->translateSearchParameterToSql();

        // handle sort
        $sort = $sqlTranslator->translateSortParameterToSql();
        $request .= $sort !== null ? $sort : ' ORDER BY alias ASC';

        // handle pagination
        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $providers = [];

        foreach ($statement as $record) {
            /** @var _Provider $record */
            $providers[] = $this->createProviderFromRecord($record);
        }

        return $providers;
    }

    /**
     * @param _Provider $record
     *
     * @return Provider
     */
    private function createProviderFromRecord(array $record): Provider
    {
        return new Provider(
            id: (int) $record['rule_id'],
            name: $record['alias'],
            type: ProviderType::from((int) $record['provider_id']),
            isActivated: (bool) $record['activate'],
        );
    }
}
