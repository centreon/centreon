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

namespace Core\Tag\RealTime\Infrastructure\Repository\Tag;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Tag\RealTime\Application\Repository\ReadTagRepositoryInterface;
use Core\Tag\RealTime\Domain\Model\Tag;
use Utility\SqlConcatenator;

/**
 *  @phpstan-type _tag array{
 *      id: int,
 *      name: string,
 *      type: int
 * }
 */
class DbReadTagRepository extends AbstractRepositoryDRB implements ReadTagRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function __construct(DatabaseConnection $db, private SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $this->sqlRequestTranslator->setConcordanceArray([
            'name' => 'tags.name',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findAllByTypeId(int $typeId): array
    {
        $this->info('Fetching tags from database of type', ['type' => $typeId]);

        $request = 'SELECT SQL_CALC_FOUND_ROWS 1 AS REALTIME, id, name, `type`
            FROM `:dbstg`.tags';

        // Handle search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest === null ? ' WHERE ' : $searchRequest . ' AND ';

        $request .= ' type = :type AND EXISTS (
            SELECT 1 FROM `:dbstg`.resources_tags AS rtags
            WHERE rtags.tag_id = tags.tag_id
        )';

        // Handle sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest ?? ' ORDER BY name ASC';

        // Handle pagination
        $request .= $this->sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            /** @var int */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }

        $statement->bindValue(':type', $typeId, \PDO::PARAM_INT);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS() AS REALTIME');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $tags = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _tag $record */
            $tags[] = DbTagFactory::createFromRecord($record);
        }

        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function findAllByTypeIdAndAccessGroups(int $typeId, array $accessGroups): array
    {
        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $this->info(
            'Fetching tags from database of type and access groups',
            ['type' => $typeId, 'access_group_ids' => $accessGroupIds]
        );

        if ((
                $typeId === Tag::HOST_CATEGORY_TYPE_ID
                && ! $this->hasRestrictedAccessToHostCategories($accessGroupIds)
            )
            || (
                $typeId === Tag::SERVICE_CATEGORY_TYPE_ID
                && ! $this->hasRestrictedAccessToServiceCategories($accessGroupIds)
                )
            ) {
            return $this->findAllByTypeId($typeId);
        }

        $aclJoins = match ($typeId) {
            Tag::HOST_CATEGORY_TYPE_ID => <<<'SQL'
                    INNER JOIN `:db`.acl_resources_hc_relations arhr
                        ON tags.id = arhr.hc_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL,
            Tag::SERVICE_CATEGORY_TYPE_ID => <<<'SQL'
                    INNER JOIN `:db`.acl_resources_sc_relations arsr
                        ON tags.id = arsr.sc_id
                    INNER JOIN `:db`.acl_resources res
                        ON arsr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL,
            Tag::HOST_GROUP_TYPE_ID => <<<'SQL'
                    INNER JOIN `:db`.acl_resources_hg_relations arhr
                        ON hg.hg_id = arhr.hg_hg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL,
            Tag::SERVICE_GROUP_TYPE_ID => <<<'SQL'
                    INNER JOIN `:db`.acl_resources_sg_relations arsr
                        ON sg.sg_id = arsr.sg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arsr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    SQL,
            default => throw new \Exception('Unknown tag type'),
        };

        // Handle search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $search = $searchRequest === null ? 'WHERE' : "{$searchRequest} AND ";

        foreach ($accessGroupIds as $key => $id) {
            $bindValues[":access_group_id_{$key}"] = $id;
        }
        $aclGroupBind = implode(', ', array_keys($bindValues));

        // Handle sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $sort = $sortRequest ?? ' ORDER BY name ASC';

        // Handle pagination
        $pagination = $this->sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT SQL_CALC_FOUND_ROWS 1 AS REALTIME, id, name, `type`
                FROM `:dbstg`.tags
                {$aclJoins}
                {$search}
                type = :type AND EXISTS (
                    SELECT 1 FROM `:dbstg`.resources_tags AS rtags
                    WHERE rtags.tag_id = tags.tag_id
                )
                AND ag.acl_group_id IN ({$aclGroupBind})
                {$sort}
                {$pagination}
                SQL
        ));

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            /** @var int */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }

        $statement->bindValue(':type', $typeId, \PDO::PARAM_INT);
        foreach ($bindValues as $bindName => $bindValue) {
            $statement->bindValue($bindName, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS() AS REALTIME');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $tags = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _tag $record */
            $tags[] = DbTagFactory::createFromRecord($record);
        }

        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function findAllByResourceAndTypeId(int $id, int $parentId, int $typeId): array
    {
        $this->info(
            'Fetching tags from database for specified resource id, parentId and typeId',
            [
                'id' => $id,
                'parentId' => $parentId,
                'type' => $typeId,
            ]
        );

        $request = 'SELECT 1 AS REALTIME, tags.id AS id, tags.name AS name, tags.`type` AS `type`
            FROM `:dbstg`.tags
            LEFT JOIN `:dbstg`.resources_tags
                ON tags.tag_id = resources_tags.tag_id
            LEFT JOIN `:dbstg`.resources
                ON resources_tags.resource_id = resources.resource_id
            WHERE resources.id = :id AND resources.parent_id = :parentId AND tags.type = :typeId';

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->bindValue(':parentId', $parentId, \PDO::PARAM_INT);
        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
        $statement->execute();

        $tags = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _tag $record */
            $tags[] = DbTagFactory::createFromRecord($record);
        }

        return $tags;
    }

    /**
     * Determine if service categories are filtered for given access group ids:
     *  - true: accessible service categories are filtered
     *  - false: accessible service categories are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToServiceCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT 1
                FROM `:db`.acl_resources_sc_relations arsr
                INNER JOIN `:db`.acl_resources res
                    ON arsr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * Determine if host categories are filtered for given access group ids:
     *  - true: accessible host categories are filtered
     *  - false: accessible host categories are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT 1
                FROM `:db`.acl_resources_hc_relations arhr
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
