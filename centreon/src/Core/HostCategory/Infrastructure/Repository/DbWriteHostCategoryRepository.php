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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\NewHostCategory;

class DbWriteHostCategoryRepository extends AbstractRepositoryRDB implements WriteHostCategoryRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $hostCategoryId): void
    {
        $this->debug('Delete host category', ['hostCategoryId' => $hostCategoryId]);

        $request = $this->translateDbName(
            <<<'SQL'
                DELETE hc
                FROM `:db`.hostcategories hc
                WHERE hc.hc_id = :hostCategoryId
                SQL
        );
        $request .= ' AND hc.level IS NULL ';

        $statement = $this->db->prepare($request);

        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostCategory $hostCategory): int
    {
        $this->debug('Add host category', ['hostCategory' => $hostCategory]);

        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.hostcategories (hc_name, hc_alias, hc_comment, hc_activate)
                VALUES (:name, :alias, :comment, :isActivated)
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $hostCategory->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $hostCategory->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':comment', $hostCategory->getComment(), \PDO::PARAM_STR);
        $statement->bindValue(
            ':isActivated',
            (new BoolToEnumNormalizer())->normalize($hostCategory->isActivated()),
            \PDO::PARAM_STR
        );

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update(HostCategory $hostCategory): void
    {
        $this->debug('Update host category', ['hostCategory' => $hostCategory]);

        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.hostcategories
                set hc_name = :name,
                    hc_alias = :alias,
                    hc_comment = :comment,
                    hc_activate = :isActivated
                WHERE hc_id = :id
                AND level IS NULL
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':id', $hostCategory->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $hostCategory->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $hostCategory->getAlias(), \PDO::PARAM_STR);
        $statement->bindValue(':comment', $hostCategory->getComment(), \PDO::PARAM_STR);
        $statement->bindValue(
            ':isActivated',
            (new BoolToEnumNormalizer())->normalize($hostCategory->isActivated()),
            \PDO::PARAM_STR
        );

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function linkToHost(int $hostId, array $categoryIds): void
    {
        $this->info('Linking host categories to host/host template', [
            'host_id' => $hostId, 'category_ids' => $categoryIds,
        ]);

        if ($categoryIds === []) {
            return;
        }

        $bindValues = [];
        $subQuery = [];
        foreach ($categoryIds as $key => $categoryId) {
            $bindValues[":category_id_{$key}"] = $categoryId;
            $subQuery[] = "(:category_id_{$key}, :host_id)";
        }

        $statement = $this->db->prepare($this->translateDbName(
            'INSERT INTO `:db`.`hostcategories_relation` (hostcategories_hc_id, host_host_id) VALUES '
            . implode(', ', $subQuery)
        ));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);

        $statement->execute();
    }
}
