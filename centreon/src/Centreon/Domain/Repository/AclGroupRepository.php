<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\AclGroup;
use Centreon\Domain\Repository\Traits\CheckListOfIdsTrait;
use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class AclGroupRepository extends AbstractRepositoryRDB implements PaginationRepositoryInterface
{
    use CheckListOfIdsTrait;

    /** @var int $resultCountForPagination */
    private int $resultCountForPagination = 0;

    private const CONCORDANCE_ARRAY = [
        'id' => 'acl_group_id',
        'name' => 'acl_group_name',
        'alias' => 'acl_group_alias',
        'changed' => 'acl_group_changed',
        'activate' => 'acl_group_activate'
    ];

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public static function entityClass(): string
    {
        return AclGroup::class;
    }

    /**
     * Check list of IDs
     *
     * @return bool
     */
    public function checkListOfIds(array $ids): bool
    {
        return $this->checkListOfIdsTrait($ids, AclGroup::TABLE, AclGroup::ENTITY_IDENTIFICATOR_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationList($filters = null, int $limit = null, int $offset = null, $ordering = []): array
    {
        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS t.* FROM `:db`.`acl_groups` AS `t`
            SQL;

        $collector = new StatementCollector();

        $isWhere = false;
        if ($filters !== null) {
            if ($filters['search'] ?? false) {
                $request .= ' WHERE t.' . self::CONCORDANCE_ARRAY['name'] . ' LIKE :search';
                $collector->addValue(':search', "%{$filters['search']}%");
                $isWhere = true;
            }

            if (
                array_key_exists('ids', $filters)
                && is_array($filters['ids'])
                && [] !== $filters['ids']
            ) {
                $idsListKey = [];
                foreach ($filters['ids'] as $index => $id) {
                    $key = ":id{$index}";
                    $idsListKey[] = $key;
                    $collector->addValue($key, $id, \PDO::PARAM_INT);

                    unset($index, $id);
                }

                $request .= $isWhere ? ' AND' : ' WHERE';
                $request .= ' t.' . self::CONCORDANCE_ARRAY['id'] . ' IN (' . implode(',', $idsListKey) . ')';
            }
        }

        $request .= ' ORDER BY t.' . self::CONCORDANCE_ARRAY['name'] . ' ASC';

        if ($limit !== null) {
            $request .= ' LIMIT :limit';
            $collector->addValue(':limit', $limit, \PDO::PARAM_INT);

            if ($offset !== null) {
                $request .= ' OFFSET :offset';
                $collector->addValue(':offset', $offset, \PDO::PARAM_INT);
            }
        }

        $statement = $this->db->prepare($this->translateDbName($request));
        $collector->bind($statement);

        $statement->execute();

        $foundRecords = $this->db->query('SELECT FOUND_ROWS()');

        if ($foundRecords !== false && ($total = $foundRecords->fetchColumn()) !== false) {
            $this->resultCountForPagination = $total;
        }

        $aclGroups = [];

        while ($record = $statement->fetch()) {
            $aclGroups[] = $this->createAclGroupFromArray($record);
        }

        return $aclGroups;
    }

    private function createAclGroupFromArray(array $data): AclGroup
    {
        $aclGroup = new AclGroup();
        $aclGroup->setId((int) $data['acl_group_id']);
        $aclGroup->setName($data['acl_group_name']);
        $aclGroup->setAlias($data['acl_group_alias']);
        $aclGroup->setChanged((int) $data['acl_group_changed']);
        $aclGroup->setActivate($data['acl_group_activate']);

        return $aclGroup;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationListTotal(): int
    {
        return $this->resultCountForPagination;
    }
}
