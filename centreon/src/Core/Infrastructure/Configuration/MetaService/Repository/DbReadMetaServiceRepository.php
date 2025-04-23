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

namespace Core\Infrastructure\Configuration\MetaService\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Application\Configuration\MetaService\Repository\ReadMetaServiceRepositoryInterface;
use Core\Common\Domain\TrimmedString;
use Core\Domain\Configuration\Model\MetaService;
use Core\Domain\Configuration\Model\MetaServiceNamesById;

class DbReadMetaServiceRepository extends AbstractRepositoryDRB implements ReadMetaServiceRepositoryInterface
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
    public function findNames(int ...$ids): MetaServiceNamesById
    {
        $ids = array_unique($ids);

        $fields = '';
        foreach ($ids as $index => $id) {
            $fields .= ('' === $fields ? '' : ', ') . ':id_' . $index;
        }

        $request = <<<SQL
                SELECT
                    m.meta_id, m.meta_name
                FROM
                    `:db`.meta_service m
                WHERE
                    m.meta_id IN ({$fields})
            SQL;

        $statement = $this->db->prepare(
            $this->translateDbName($request)
        );

        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $names = new MetaServiceNamesById();

        foreach ($statement as $record) {
            /** @var array{meta_id:int,meta_name:string} $record */
            $names->addName(
                $record['meta_id'],
                new TrimmedString($record['meta_name'])
            );
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function exist(array $metaIds): array
    {
        if ($metaIds === []) {
            return [];
        }

        $bindValues = [];

        foreach ($metaIds as $index => $metaId) {
            $bindValues[":meta_{$index}"] = $metaId;
        }

        $metaIdsList = implode(', ', array_keys($bindValues));

        $request = $this->translateDbName(
            <<<SQL
                    SELECT
                        meta_id
                    FROM `:db`.meta_service
                    WHERE meta_id IN ({$metaIdsList})
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($bindValues as $bindKey => $bindValue) {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function findMetaServiceByIdAndAccessGroupIds(int $metaId, array $accessGroupIds): ?MetaService
    {
        if ($accessGroupIds === []) {
            return null;
        }

        $accessGroupRequest = ' INNER JOIN `:db`.`acl_resources_meta_relations` AS armr
                ON armr.meta_id = ms.meta_id
            INNER JOIN `:db`.acl_resources res
                ON armr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
                AND argr.acl_group_id IN (' . implode(',', $accessGroupIds) . ') ';

        return $this->findMetaService($metaId, $accessGroupRequest);
    }

    /**
     * @inheritDoc
     */
    public function findMetaServiceById(int $metaId): ?MetaService
    {
        return $this->findMetaService($metaId);
    }

    /**
     * @param int $metaId
     * @param string|null $accessGroupRequest
     *
     * @return MetaService|null
     */
    private function findMetaService(int $metaId, ?string $accessGroupRequest = null): ?MetaService
    {
        $request = 'SELECT ms.meta_id AS `id`,
            ms.meta_name AS `name`,
            ms.meta_display AS `output`,
            ms.data_source_type AS `data_source_type`,
            ms.meta_select_mode AS `meta_selection_mode`,
            ms.regexp_str,
            ms.metric,
            ms.warning,
            ms.critical,
            ms.meta_activate AS `is_activated`,
            ms.calcul_type AS `calculation_type`
        FROM `:db`.meta_service ms';

        $request .= ' WHERE ms.meta_id = :meta_id';

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':meta_id', $metaId, \PDO::PARAM_INT);

        $statement->execute();

        if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            return DbMetaServiceFactory::createFromRecord($row);
        }

        return null;
    }
}
