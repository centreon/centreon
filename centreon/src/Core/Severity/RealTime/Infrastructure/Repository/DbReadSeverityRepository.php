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

namespace Core\Severity\RealTime\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Severity\RealTime\Application\Repository\ReadSeverityRepositoryInterface;
use Core\Severity\RealTime\Domain\Model\Severity;
use Utility\SqlConcatenator;

/**
 *  @phpstan-type _severity array{
 *      severity_id: int,
 *      id: int,
 *      name: string,
 *      type: int,
 *      level: int,
 *      icon_id: int,
 *      icon_id: int,
 *      icon_name: string,
 *      icon_path: string,
 *      icon_directory: string|null
 * }
 */
class DbReadSeverityRepository extends AbstractRepositoryDRB implements ReadSeverityRepositoryInterface
{
    use LoggerTrait;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    /**
     * @param DatabaseConnection $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function __construct(DatabaseConnection $db, SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 's.id',
            'name' => 's.name',
            'level' => 's.level',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findAllByTypeId(int $typeId): array
    {
        $this->info(
            'Fetching severities from the database by typeId',
            [
                'typeId' => $typeId,
            ]
        );

        $request = 'SELECT SQL_CALC_FOUND_ROWS
            1 AS REALTIME,
            severity_id,
            s.id,
            s.name,
            s.type,
            s.level,
            s.icon_id,
            img_id AS `icon_id`,
            img_name AS `icon_name`,
            img_path AS `icon_path`,
            imgd.dir_name AS `icon_directory`
        FROM `:dbstg`.severities s
        INNER JOIN `:db`.view_img img
            ON s.icon_id = img.img_id
        LEFT JOIN `:db`.view_img_dir_relation imgdr
            ON imgdr.img_img_id = img.img_id
        INNER JOIN `:db`.view_img_dir imgd
            ON imgd.dir_id = imgdr.dir_dir_parent_id';

        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest === null ? ' WHERE ' : $searchRequest . ' AND ';
        $request .= 's.type = :typeId AND img.img_id = s.icon_id';

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

        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS() AS REALTIME');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $severities = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _severity $record */
            $severities[] = DbSeverityFactory::createFromRecord($record);
        }

        return $severities;
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
            'Fetching severities from the database by typeId and access groups',
            [
                'typeId' => $typeId,
                'accessGroupIds' => $accessGroupIds,
            ]
        );

        if ($typeId === Severity::SERVICE_SEVERITY_TYPE_ID) {
            // if service severities are not filtered in ACLs, then user has access to ALL service severities
            if (! $this->hasRestrictedAccessToServiceSeverities($accessGroupIds)) {
                $this->info('Service severities access not filtered');

                return $this->findAllByTypeId($typeId);
            }

            $severitiesAcls = <<<'SQL'
                INNER JOIN `:db`.acl_resources_sc_relations arsr
                    ON s.id = arsr.sc_id
                INNER JOIN `:db`.acl_resources res
                    ON arsr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL;
        } else {
            // if host severities are not filtered in ACLs, then user has access to ALL host severities
            if (! $this->hasRestrictedAccessToHostSeverities($accessGroupIds)) {
                $this->info('Host severities access not filtered');

                return $this->findAllByTypeId($typeId);
            }

            $severitiesAcls = <<<'SQL'
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON s.id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL;
        }

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                1 AS REALTIME,
                severity_id,
                s.id,
                s.name,
                s.type,
                s.level,
                s.icon_id,
                img_id AS `icon_id`,
                img_name AS `icon_name`,
                img_path AS `icon_path`,
                imgd.dir_name AS `icon_directory`
            FROM `:dbstg`.severities s
            INNER JOIN `:db`.view_img img
                ON s.icon_id = img.img_id
            LEFT JOIN `:db`.view_img_dir_relation imgdr
                ON imgdr.img_img_id = img.img_id
            INNER JOIN `:db`.view_img_dir imgd
                ON imgd.dir_id = imgdr.dir_dir_parent_id
            {$severitiesAcls}
            SQL;

        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest === null ? ' WHERE ' : $searchRequest . ' AND ';
        $request .= 's.type = :typeId AND img.img_id = s.icon_id';

        foreach ($accessGroupIds as $key => $id) {
            $bindValues[":access_group_id_{$key}"] = $id;
        }
        $request .= ' AND ag.acl_group_id IN (' . implode(', ', array_keys($bindValues)) . ')';

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

        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
        foreach ($bindValues as $bindName => $bindValue) {
            $statement->bindValue($bindName, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS() AS REALTIME');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $severities = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _severity $record */
            $severities[] = DbSeverityFactory::createFromRecord($record);
        }

        return $severities;
    }

    /**
     * @inheritDoc
     */
    public function findByResourceAndTypeId(int $resourceId, int $parentResourceId, int $typeId): ?Severity
    {
        $request = 'SELECT
            1 AS REALTIME,
            resources.severity_id,
            s.id,
            s.name,
            s.level,
            s.type,
            s.icon_id,
            img_name AS `icon_name`,
            img_path AS `icon_path`,
            imgd.dir_name AS `icon_directory`
        FROM `:dbstg`.resources
        INNER JOIN `:dbstg`.severities s
            ON s.severity_id = resources.severity_id
        INNER JOIN `:db`.view_img img
            ON s.icon_id = img.img_id
        LEFT JOIN `:db`.view_img_dir_relation imgdr
            ON imgdr.img_img_id = img.img_id
        INNER JOIN `:db`.view_img_dir imgd
            ON imgd.dir_id = imgdr.dir_dir_parent_id
        WHERE resources.id = :resourceId AND resources.parent_id = :parentResourceId AND s.type = :typeId';

        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':resourceId', $resourceId, \PDO::PARAM_INT);
        $statement->bindValue(':parentResourceId', $parentResourceId, \PDO::PARAM_INT);
        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);

        $statement->execute();

        if (($record = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _severity $record */
            return DbSeverityFactory::createFromRecord($record);
        }

        return null;
    }

    /**
     * Determine if service severities are filtered for given access group ids:
     *  - true: accessible service severities are filtered
     *  - false: accessible service severities are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToServiceSeverities(array $accessGroupIds): bool
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
     * Determine if host severities are filtered for given access group ids:
     *  - true: accessible host severities are filtered
     *  - false: accessible host severities are not filtered.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostSeverities(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            'SELECT 1
            FROM `:db`.acl_resources_hc_relations arhr
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
