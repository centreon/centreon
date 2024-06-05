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

namespace Core\ResourceAccess\Infrastructure\Repository\Dataset;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\ResourceAccess\Application\Repository\WriteDatasetRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;

class DbWriteHostGroupRepository extends AbstractRepositoryRDB implements WriteDatasetRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(protected DatabaseConnection $db)
    {
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $type): bool
    {
        return HostGroupFilterType::TYPE_NAME === $type;
    }

    /**
     * @inheritDoc
     */
    public function linkResourcesToDataset(int $ruleId, int $datasetId, array $resourceIds): void
    {
        $this->debug(
            'Add relation between hostgroups and dataset for rule',
            ['rule_id' => $ruleId, 'dataset_id' => $datasetId, 'hostgroup_ids' => $resourceIds]
        );

        if ([] === $resourceIds) {
            return;
        }

        $bindValues = [];
        $subValues = [];
        foreach ($resourceIds as $index => $hostgroupId) {
            $bindValues[":hostgroup_id_{$index}"] = $hostgroupId;
            $subValues[] = "(:hostgroup_id_{$index}, :datasetId)";
        }

        $subQueries = implode(', ', $subValues);

        $request = $this->translateDbName(
            <<<SQL
                    INSERT INTO `:db`.acl_resources_hg_relations (hg_hg_id, acl_res_id)
                    VALUES {$subQueries}
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);

        foreach ($bindValues as $bindKey => $bindValue) {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function updateDatasetAccess(int $ruleId, int $datasetId, bool $fullAccess): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    UPDATE `:db`.acl_resources SET all_hostgroups = :access WHERE acl_res_id = :datasetId
                SQL
        ));

        $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
        $statement->bindValue(':access', $fullAccess ? '1' : '0', \PDO::PARAM_STR);
        $statement->execute();
    }
}
