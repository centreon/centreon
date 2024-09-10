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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
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
 *     user_interface_density: string,
 *     user_can_reach_frontend: string,
 * }
 */
class DbReadUserRepository extends AbstractRepositoryRDB implements ReadUserRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $concatenator = new SqlConcatenator();
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT DISTINCT SQL_CALC_FOUND_ROWS
                    contact_id,
                    contact_alias,
                    contact_name,
                    contact_email,
                    contact_admin,
                    contact_theme,
                    user_interface_density,
                    contact_oreon AS `user_can_reach_frontend`
                FROM `:db`.contact
                SQL
        );
        $concatenator->defineWhere(
            <<<'SQL'
                WHERE contact.contact_register = '1'
                SQL
        );

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'contact_id',
            'alias' => 'contact_alias',
            'name' => 'contact_name',
            'email' => 'contact_email',
            'provider_name' => 'contact_auth_type',
            'is_admin' => 'contact_admin',
        ]);
        $sqlTranslator->translateForConcatenator($concatenator);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator->calculateNumberOfRows($this->db);

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
    public function findByAccessGroupsUserAndRequestParameters(
        array $accessGroups,
        ContactInterface $user,
        ?RequestParametersInterface $requestParameters = null
    ): array {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );
        [$binValues, $subRequest] = $this->createMultipleBindQuery($accessGroupIds, ':id_');

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS *
            FROM (
                SELECT /* Finds associated users in ACL group rules */
                    contact.contact_id, contact.contact_alias, contact.contact_name,
                    contact.contact_email, contact.contact_admin, contact.contact_theme,
                    contact.user_interface_density, contact.contact_oreon AS `user_can_reach_frontend`
                FROM `:db`.`contact`
                INNER JOIN `:db`.`acl_group_contacts_relations` acl_c_rel
                    ON acl_c_rel.contact_contact_id = contact.contact_id
                WHERE contact.contact_register = '1'
                    AND acl_c_rel.acl_group_id IN ({$subRequest})
                UNION
                SELECT /* Finds users belonging to associated contact groups in ACL group rules */
                    contact.contact_id, contact.contact_alias, contact.contact_name,
                    contact.contact_email, contact.contact_admin, contact.contact_theme,
                    contact.user_interface_density, contact.contact_oreon AS `user_can_reach_frontend`
                FROM `:db`.`contact`
                INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                    ON c_cg_rel.contact_contact_id = contact.contact_id
                INNER JOIN `:db`.`acl_group_contactgroups_relations` acl_cg_rel
                    ON acl_cg_rel.cg_cg_id = c_cg_rel.contactgroup_cg_id
                WHERE contact.contact_register = '1'
                    AND acl_cg_rel.acl_group_id IN ({$subRequest})
                UNION
                SELECT /* Finds users belonging to the same contact groups as the user */
                    contact2.contact_id, contact2.contact_alias, contact2.contact_name,
                    contact2.contact_email, contact2.contact_admin, contact2.contact_theme,
                    contact2.user_interface_density, contact2.contact_oreon AS `user_can_reach_frontend`
                FROM `:db`.`contact`
                INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                    ON c_cg_rel.contact_contact_id = contact.contact_id
                INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel2
                    ON c_cg_rel2.contactgroup_cg_id = c_cg_rel.contactgroup_cg_id
                INNER JOIN `:db`.`contact` contact2
                    ON contact2.contact_id = c_cg_rel2.contact_contact_id
                WHERE c_cg_rel.contact_contact_id  = :user_id
                    AND contact.contact_register = '1'
                    AND contact2.contact_register = '1'
                GROUP BY contact2.contact_id
            ) as contact
            SQL;

        // Update the SQL query with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->getRequestParameters()->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $sqlTranslator?->setConcordanceArray([
            'id' => 'contact_id',
            'alias' => 'contact_alias',
            'name' => 'contact_name',
            'email' => 'contact_email',
            'provider_name' => 'contact_auth_type',
            'is_admin' => 'contact_admin',
        ]);

        // handle search
        $request .= $sqlTranslator?->translateSearchParameterToSql();

        // handle sort
        $request .= $sqlTranslator?->translateSortParameterToSql() ?? ' ORDER BY contact_id ASC';

        // handle pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':user_id', $user->getId(), \PDO::PARAM_INT);
        foreach ($binValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        if ($sqlTranslator !== null) {
            foreach ($sqlTranslator->getSearchValues() as $key => $data) {
                $type = (int) key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->execute();

        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        // Calculate the number of rows for the pagination.
        if ($total = $this->calculateNumberOfRows()) {
            $requestParameters?->setTotal($total);
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
    public function find(int $userId): ?User
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    contact_id,
                    contact_alias,
                    contact_name,
                    contact_email,
                    contact_admin,
                    contact_theme,
                    user_interface_density,
                    contact_oreon AS `user_can_reach_frontend`
                FROM `:db`.contact
                WHERE contact.contact_register = '1'
                AND contact_id = :userId
                SQL
        ));

        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var false|_UserRecord $result */
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return $this->createFromRecord($result);
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
            $user['user_interface_density'],
            $user['user_can_reach_frontend'] === '1'
        );
    }
}
