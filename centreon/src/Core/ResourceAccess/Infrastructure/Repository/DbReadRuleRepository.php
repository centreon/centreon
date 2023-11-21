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

namespace Core\ResourceAccess\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ResourceAccess\Application\Repository\ReadRuleRepositoryInterface;
use Core\ResourceAccess\Domain\Model\Rule;

/**
 * @phpstan-type _Rule array{
 *     acl_group_id: int,
 *     acl_group_name: string,
 *     cloud_description: string|null,
 *     acl_group_activate: int 
 * }
 */
final class DbReadRuleRepository extends AbstractRepositoryRDB implements ReadRuleRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'acl_groups.acl_group_name',
            'description' => 'acl_groups.cloud_description',
        ]);

        $request = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    acl_group_id,
                    acl_group_name,
                    cloud_description,
                    acl_group_activate
                FROM `:db`.acl_groups
            SQL;

        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND cloud_specific = 1'
            : ' WHERE cloud_specific = 1';
       
        // handle sort parameter
        $sort = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sort)
            ? $sort
            : ' ORDER BY acl_group_name ASC';

        // handle pagination parameter
        $request .= $sqlTranslator->translatePaginationToSql();

        $request = $this->translateDbName($request);

        $statement = $this->db->prepare($request);

        $rules = [];
        if ($statement === false) {
            return $rules;
        }

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total ACL groups found
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        foreach ($statement as $data) {
            /** @var _Rule $data */
            $rules[] = $this->createRule($data);
        }

        return $rules;
    }

    /**
     * @param _Rule $data
     *
     * @return Rule
     */
    private function createRule(array $data): Rule
    {
        return (new Rule($data['acl_group_id'], $data['acl_group_name'], $data['acl_group_activate'] === 1))
            ->setDescription($data['cloud_description']);
    }
}
