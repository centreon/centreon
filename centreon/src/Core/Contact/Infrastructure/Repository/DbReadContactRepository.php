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

namespace Core\Contact\Infrastructure\Repository;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;

class DbReadContactRepository extends AbstractRepositoryDRB implements ReadContactRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNamesByIds(int ...$ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $ids = array_unique($ids);

        $fields = '';
        foreach ($ids as $index => $id) {
            $fields .= ('' === $fields ? '' : ', ') . ':id_' . $index;
        }

        $select = <<<SQL
            SELECT
                `contact_id` as `id`,
                `contact_name` as `name`
            FROM
                `:db`.`contact`
            WHERE
                `contact_id` IN ({$fields})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($select));
        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $names = [];
        foreach ($statement as $result) {
            /** @var array{ id: int, name: string } $result */
            $names[$result['id']] = $result;
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $userId): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1 FROM `:db`.contact
                WHERE contact_id = :userId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $userIds): array
    {
        $bind = [];
        foreach ($userIds as $key => $userId) {
            $bind[":user_{$key}"] = $userId;
        }
        if ($bind === []) {
            return [];
        }

        $contactIdsAsString = implode(', ', array_keys($bind));
        $request = $this->translateDbName(
            <<<SQL
                SELECT contact_id FROM `:db`.contact
                WHERE contact_id IN ({$contactIdsAsString})
                SQL
        );
        $statement = $this->db->prepare($request);
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function findContactIdsByContactGroups(array $contactGroupIds): array
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[":contactGroup_{$key}"] = $contactGroupId;
        }
        if ($bind === []) {
            return [];
        }
        $bindAsString = implode(', ', array_keys($bind));
        $request = <<<SQL
            SELECT
                contact_contact_id
            FROM
                `:db`.`contactgroup_contact_relation` cgcr
            WHERE
                cgcr.contactgroup_cg_id IN ({$bindAsString})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($bind as $token => $bindValue) {
            $statement->bindValue($token, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function existInAccessGroups(int $contactId, array $accessGroupIds): bool
    {
        $bind = [];
        foreach ($accessGroupIds as $key => $accessGroupId) {
            $bind[':access_group_' . $key] = $accessGroupId;
        }
        if ([] === $bind) {
            return false;
        }

        $accessGroupIdsAsString = implode(',', array_keys($bind));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.contact c
                         LEFT JOIN `:db`.contactgroup_contact_relation ccr
                                   ON c.contact_id = ccr.contact_contact_id
                         LEFT JOIN `:db`.acl_group_contacts_relations gcr
                                   ON c.contact_id = gcr.contact_contact_id
                         LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                                   ON ccr.contactgroup_cg_id = gcgr.cg_cg_id
                WHERE c.contact_id = :contactId
                    AND (gcr.acl_group_id IN ({$accessGroupIdsAsString})
                    OR gcgr.acl_group_id IN ({$accessGroupIdsAsString}));
                SQL
        ));
        $statement->bindValue(':contactId', $contactId,\PDO::PARAM_INT);
        foreach ($bind as $token => $accessGroupId) {
            $statement->bindValue($token, $accessGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findAdminWithRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->getRequestParameters()->setConcordanceStrictMode(
            RequestParameters::CONCORDANCE_MODE_STRICT
        );
        $sqlTranslator->setConcordanceArray([
            'name' => 'c.contact_name',
        ]);
        $query = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                c.contact_id,
                c.contact_name,
                c.contact_email,
                c.contact_admin
            FROM `:db`.contact c
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        $query .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $query .= "c.contact_admin = '1' AND c.contact_oreon = '1'";

        $query .= $sqlTranslator->translatePaginationToSql();
        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $admins = [];
        foreach ($statement as $admin) {
            /** @var array{
             *     contact_admin: string,
             *     contact_name: string,
             *     contact_id: int,
             *     contact_email: string
             * } $admin
             */
            $admins[] = (new Contact())
                ->setAdmin(true)
                ->setName($admin['contact_name'])
                ->setId($admin['contact_id'])
                ->setEmail($admin['contact_email']);
        }

        return $admins;
    }

    /**
     * @inheritDoc
     */
    public function findContactIdsByAccessGroups(array $accessGroupIds): array
    {
        $bind = [];
        foreach ($accessGroupIds as $key => $accessGroupId) {
            $bind[':access_group_' . $key] = $accessGroupId;
        }
        if ([] === $bind) {
            return [];
        }

        $accessGroupIdsAsString = implode(',', array_keys($bind));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT c.contact_id
                FROM `:db`.contact c
                         LEFT JOIN `:db`.contactgroup_contact_relation ccr
                                   ON c.contact_id = ccr.contact_contact_id
                         LEFT JOIN `:db`.acl_group_contacts_relations gcr
                                   ON c.contact_id = gcr.contact_contact_id
                         LEFT JOIN `:db`.acl_group_contactgroups_relations gcgr
                                   ON ccr.contactgroup_cg_id = gcgr.cg_cg_id
                WHERE  gcr.acl_group_id IN ({$accessGroupIdsAsString})
                    OR gcgr.acl_group_id IN ({$accessGroupIdsAsString});
                SQL
        ));
        foreach ($bind as $token => $accessGroupId) {
            $statement->bindValue($token, $accessGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function findAdminsByIds(array $contactIds): array
    {
        $bind = [];
        foreach ($contactIds as $key => $contactId) {
            $bind[':contact' . $key] = $contactId;
        }
        if ([] === $bind) {
            return [];
        }

        $bindTokenAsString = implode(', ', array_keys($bind));

        $query = <<<SQL
            SELECT c.contact_id,
                c.contact_name,
                c.contact_email,
                c.contact_admin
            FROM `:db`.contact c
            WHERE c.contact_admin = '1'
              AND c.contact_oreon = '1'
              AND c.contact_id IN ({$bindTokenAsString})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $token => $contactId) {
            $statement->bindValue($token, $contactId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $admins = [];
        foreach ($statement as $admin) {
            /** @var array{
             *     contact_admin: string,
             *     contact_name: string,
             *     contact_id: int,
             *     contact_email: string
             * } $admin
             */
            $admins[] = (new Contact())
                ->setAdmin(true)
                ->setName($admin['contact_name'])
                ->setId($admin['contact_id'])
                ->setEmail($admin['contact_email']);
        }

        return $admins;
    }
}
