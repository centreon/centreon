<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\Host;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\HostTemplate;

class DbReadHostCategoryRepository extends AbstractRepositoryDRB implements ReadHostCategoryRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    public function __construct(DatabaseConnection $db, SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'hc.hc_id',
            'name' => 'hc.hc_name',
            'alias' => 'hc.hc_alias'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        return $this->findAllRequest(null);
    }

    /**
     * @inheritDoc
     */
    public function findAllByContactId(int $contactId): array
    {
        return $this->findAllRequest($contactId);
    }

    /**
     * @param int|null $contactId
     * @return HostCategory[]
     */
    private function findAllRequest(?int $contactId): array
    {
        if ($contactId !== null) {
            $request = $this->translateDbName(
                'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias
                FROM `:db`.hostcategories hc
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON hc.hc_id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                LEFT JOIN `:db`.acl_group_contacts_relations agcr
                    ON ag.acl_group_id = agcr.acl_group_id
                LEFT JOIN `:db`.acl_group_contactgroups_relations agcgr
                    ON ag.acl_group_id = agcgr.acl_group_id
                LEFT JOIn `:db`.contactgroup_contact_relation cgcr
                    ON cgcr.contactgroup_cg_id = agcgr.cg_cg_id'
            );
            $whereAclCondition = ' AND (agcr.contact_contact_id = :contact_id
                OR cgcr.contact_contact_id = :contact_id)';
        } else {
            $request = $this->translateDbName(
                'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias FROM `:db`.hostcategories hc'
            );
        }

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $request .= "hc.level IS NULL ";
        $request .= $whereAclCondition ?? '';

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY hc.hc_id';

        // Pagination
        $request .= $this->sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($request);

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            /** @var int */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }

        if ($contactId !== null) {
            $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        }
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        /**
         * TODO :
         *  - in UI :
         *      if acl_resources_hc_relations is empty
         *      then by default access to ALL
         *      else access to only ones listed in acl_resources_hc_relations
         *  - in old API:
         *      if acl_resources_hc_relations is empty
         *      then by default access to NONE
         *      else access to only ones listed in acl_resources_hc_relations
         */

        /**
         *  If user is not admin AND total result with ACLs is zero then ALL categories are accessible
         */
        if ($contactId && $this->sqlRequestTranslator->getRequestParameters()->getTotal() === 0) {
            return $this->findAllRequest(null);
        }

        $hostCategories = [];
        if ($statement === false) {
            return $hostCategories;
        }
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hostCategories[] = new HostCategory(
                $result['hc_id'],
                $result['hc_name'],
                $result['hc_alias']
            );
        }

        return $hostCategories;
    }

    /**
     * @inheritDoc
     */
    public function findHostsByHostCategoryIds(array $hostCategoryIds): array
    {
        return $this->findHostsByHostCategoryIdsRequest($hostCategoryIds, null);
    }

    /**
     * @inheritDoc
     */
    public function findHostsByHostCategoryIdsAndContactId(array $hostCategoryIds, int $contactId): array
    {
        return $this->findHostsByHostCategoryIdsRequest($hostCategoryIds, $contactId);
    }

    /**
     * @param int[] $hostCategoryIds
     * @param int|null $contactId
     * @return array<int,Host[]>
     */
    private function findHostsByHostCategoryIdsRequest(array $hostCategoryIds, ?int $contactId): array
    {
        if (empty($hostCategoryIds)) {
            return [];
        }

        if ($contactId !== null) {
            $request = $this->translateDbName(
                'SELECT SQL_CALC_FOUND_ROWS hcr.hostcategories_hc_id, h.host_id, h.host_name
                FROM `:db`.hostcategories_relation hcr
                JOIN `:db`.host h ON hcr.host_host_id = h.host_id
                INNER JOIN `:db`.acl_resources_host_relations arhr
                    ON hcr.host_host_id = arhr.host_host_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                LEFT JOIN `:db`.acl_group_contacts_relations agcr
                    ON ag.acl_group_id = agcr.acl_group_id
                LEFT JOIN `:db`.acl_group_contactgroups_relations agcgr
                    ON ag.acl_group_id = agcgr.acl_group_id
                LEFT JOIN `:db`.contactgroup_contact_relation cgcr
                    ON cgcr.contactgroup_cg_id = agcgr.cg_cg_id'
            );
            $whereAclCondition = ' AND (agcr.contact_contact_id = :contact_id
                OR cgcr.contact_contact_id = :contact_id)';
        } else {
            $request = $this->translateDbName(
                "SELECT rel.hostcategories_hc_id, h.host_id, h.host_name
                FROM hostcategories_relation rel
                JOIN host h ON rel.host_host_id = h.host_id"
            );
        }
        $request .= " WHERE h.host_register = '1'
            AND hcr.hostcategories_hc_id IN (" . implode(',', $hostCategoryIds) . ")";
        $request .= $whereAclCondition ?? '';

        $statement = $this->db->prepare($request);

        if ($contactId !== null) {
            $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
        }

        $statement->execute();

        if ($statement === false) {
            return [];
        }

        /**
         *  If user is not admin AND total result with ACLs is zero then ALL categories are accessible
         */
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if (
            $result !== false
            && ($total = $result->fetchColumn()) !== false
        ) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }
        if ($contactId && $this->sqlRequestTranslator->getRequestParameters()->getTotal() == 0) {
            return $this->findHostsByHostCategoryIdsRequest($hostCategoryIds, null);
        }

        $hosts = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hosts[(int) $result['hostcategories_hc_id']][] = new Host(
                $result['host_id'],
                $result['host_name']
            );
        }

        return $hosts;
    }



    /**
     * @inheritDoc
     */
    public function findHostTemplatesByHostCategoryIds(array $hostCategoryIds): array
    {
        if (empty($hostCategoryIds)) {
            return [];
        }

        $request = "SELECT rel.hostcategories_hc_id, h.host_id, h.host_name
            FROM hostcategories_relation rel
            JOIN host h ON rel.host_host_id = h.host_id
            WHERE h.host_register = '0'
            AND rel.hostcategories_hc_id IN (" . implode(',', $hostCategoryIds) . ")";

        $statement = $this->db->prepare($request);
        $statement->execute();

        if ($statement === false) {
            return [];
        }

        $hosts = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hosts[(int) $result['hostcategories_hc_id']][] = new HostTemplate(
                $result['host_id'],
                $result['host_name']
            );
        }

        return $hosts;
    }
}
