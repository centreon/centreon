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

namespace Core\Metric\Infrastructure\Repository;

use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\Service;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadMetricRepository extends AbstractRepositoryDRB implements ReadMetricRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $indexId
     *
     * @return array<Metric>
     */
    public function findMetricsByIndexId(int $indexId): array
    {
        $query = 'SELECT DISTINCT metric_id as id, metric_name as name FROM `:dbstg`.metrics, `:dbstg`.index_data ';
        $query .= ' WHERE metrics.index_id = index_data.id AND id = :index_id ORDER BY metric_id';
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':index_id', $indexId, \PDO::PARAM_INT);
        $statement->execute();

        $records = $statement->fetchAll();
        if (! is_array($records) || count($records) === 0) {
            return [];
        }

        $metrics = [];
        foreach ($records as $record) {
            $metrics[] = new Metric((int) $record['id'], $record['name']);
        }

        return $metrics;
    }

    /**
     * @inheritDoc
     */
    public function findServicesByMetricNames(array $metricNames): array
    {
        if ([] === $metricNames) {
            return [];
        }

        $bindValues = [];
        foreach ($metricNames as $index => $metricName) {
            $bindValues[':metric_name_' . $index] = $metricName;
        }

        $metricNamesQuery = implode(', ',array_keys($bindValues));
        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    SELECT DISTINCT id.host_id, id.service_id FROM `:dbstg`.index_data AS id
                    INNER JOIN `:dbstg`.metrics AS m ON m.index_id = id.id
                    WHERE m.metric_name IN ({$metricNamesQuery})
                SQL
        ));

        foreach ($bindValues as $bindToken => $bindValue) {
            $statement->bindValue($bindToken, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        $records = $statement->fetchAll();
        $services = [];
        foreach ($records as $record) {
            $services[] = (new Service())
                ->setId($record['service_id'])
                ->setHost((new Host())->setId($record['host_id']));
        }

        return $services;
    }

    /**
     * @inheritDoc
     */
    public function findServicesByMetricNamesAndAccessGroups(array $metricNames, array $accessGroups): array
    {
        if ([] === $metricNames) {
            return [];
        }

        $bindValues = [];
        foreach ($metricNames as $index => $metricName) {
            $bindValues[':metric_name_' . $index] = $metricName;
        }

        $metricNamesQuery = implode(', ',array_keys($bindValues));
        $accessGroupIds = array_map(
            fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );
        $accessGroupIdsQuery = implode(',', $accessGroupIds);

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    SELECT DISTINCT id.host_id, id.service_id FROM `:dbstg`.index_data AS id
                    INNER JOIN `:dbstg`.metrics AS m ON m.index_id = id.id
                    INNER JOIN `:dbstg`.`centreon_acl` acl
                    ON acl.service_id = id.service_id
                    AND acl.group_id IN ({$accessGroupIdsQuery})
                    WHERE m.metric_name IN ({$metricNamesQuery})
                SQL
        ));

        foreach ($bindValues as $bindToken => $bindValue) {
            $statement->bindValue($bindToken, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        $records = $statement->fetchAll();
        $services = [];
        foreach ($records as $record) {
            $services[] = (new Service())
                ->setId($record['service_id'])
                ->setHost((new Host())->setId($record['host_id']));
        }

        return $services;
    }
}
