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

namespace Core\User\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\AbstractRepositoryDRB;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\User\Application\Repository\ReadUserRepositoryInterface;
use Core\User\Domain\Model\User;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _UserRecord array{
 *     contact_id: int|string,
 *     contact_alias: string,
 *     contact_name: string,
 *     contact_email: string,
 *     contact_admin: string,
 *     contact_theme: string,
 *     user_can_reach_frontend: string,
 * }
 */
class DbReadUserRepository extends AbstractRepositoryDRB implements ReadUserRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                contact_id,
                contact_alias,
                contact_name,
                contact_email,
                contact_admin,
                contact_theme,
                contact_oreon AS `user_can_reach_frontend`
            FROM `:db`.contact
            SQL;

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'contact_id',
            'alias' => 'contact_alias',
            'name' => 'contact_name',
            'email' => 'contact_email',
            'provider_name' => 'contact_auth_type',
        ]);

        // Search
        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null ? $searchRequest . ' AND ' : ' WHERE ';
        $request .= "contact_register = '1'";

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY contact_id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            if (is_array($data)) {
                $type = (int) key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $users = [];
        foreach ($statement as $result) {
            /** @var _UserRecord $result */
            $users[] = $this->createFromRecord($result);
        }

        return $users;
    }

    /**
     * @inheritDoc
     */
    public function findByAccessGroupsAndRequestParameters(
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = [];
        $accessGroupKey = [];
        foreach ($accessGroups as $key => $value) {
            $accessGroupIds[] = $value;
            $accessGroupKeys[] = ":accessGroup_{$key}";
        }
        $accessGroupBind = implode(', ', $accessGroupKeys);

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                contact_id,
                contact_alias,
                contact_name,
                contact_email,
                contact_admin,
                contact_theme,
                contact_oreon AS `user_can_reach_frontend`
            FROM (
                SELECT `contact`.* FROM `:db`.`contact`
                JOIN `:db`.`acl_group_contacts_relations` acl_c_rel
                    ON acl_c_rel.contact_contact_id = contact.contact_id
                    AND acl_c_rel.acl_group_id IN ({$accessGroupBind})
                WHERE contact.contact_register = '1'
                UNION ALL
                SELECT `contact`.* FROM `:db`.`contact`
                JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                    ON c_cg_rel.contact_contact_id = contact.contact_id
                JOIN `:db`.`acl_group_contactgroups_relations` acl_cg_rel
                    ON acl_cg_rel.cg_cg_id = c_cg_rel.contactgroup_cg_id
                    AND acl_cg_rel.acl_group_id IN ($accessGroupBind)
                WHERE contact.contact_register = '1'
            ) as contact
            SQL;

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'contact_id',
            'alias' => 'contact_alias',
            'name' => 'contact_name',
            'email' => 'contact_email',
            'provider_name' => 'contact_auth_type',
        ]);

        // Search
        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null ? $searchRequest : '';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY contact_id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach($accessGroupKeys as $key => $bindKey) {
            $statement->bindValue($bindKey, $accessGroupIds[$key], \PDO::PARAM_INT);
        }

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            if (is_array($data)) {
                $type = (int) key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $users = [];
        foreach ($statement as $result) {
            /** @var _UserRecord $result */
            $users[] = $this->createFromRecord($result);
        }

        return $users;
    }

    /**
     * @param _UserRecord $user
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return User
     */
    private function createFromRecord(array $user): User
    {
        return new User(
            (int) $user['contact_id'],
            $user['contact_alias'],
            $user['contact_name'],
            $user['contact_email'],
            $user['contact_admin'] === '1',
            $user['contact_theme'],
            $user['user_can_reach_frontend'] === '1'
        );
    }
}
