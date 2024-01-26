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

namespace Core\ResourceAccess\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\ResourceAccess\Application\Repository\WriteDatasetRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\NewRule;

final class DbWriteResourceAccessRepository extends AbstractRepositoryRDB implements WriteResourceAccessRepositoryInterface
{
    use LoggerTrait, RepositoryTrait;

    /** @var WriteDatasetRepositoryInterface[] */
    private array $repositoryProviders;

    /**
     * @param DatabaseConnection $db
     * @param \Traversable<WriteDatasetRepositoryInterface> $repositoryProviders
     */
    public function __construct(DatabaseConnection $db, \Traversable $repositoryProviders)
    {
        $this->db = $db;
        $this->repositoryProviders = iterator_to_array($repositoryProviders);
    }

    /**
     * @inheritDoc
     */
    public function linkResourcesToDataset(int $ruleId, int $datasetId, string $resourceType, array $resourceIds): void
    {
        foreach ($this->repositoryProviders as $repositoryProvider) {
            if ($repositoryProvider->isValidFor($resourceType) === true) {
                $repositoryProvider->linkResourcesToDataset(datasetId: $datasetId, resourceIds: $resourceIds, ruleId: $ruleId);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function addDatasetFilter(int $ruleId, int $datasetId, DatasetFilter $filter, ?int $parentFilterId): int
    {
        $this->debug(
            'Add dataset filter',
            [
                'type' => $filter->getType(),
                'resource_ids' => $filter->getResourceIds(),
            ]
        );

        $request = $this->translateDbName(
            <<<'SQL'
                    INSERT INTO `:db`.dataset_filters (parent_id, type, acl_group_id, acl_resource_id, resource_ids)
                    VALUES (:parentFilterId, :type, :ruleId, :datasetId, :resourceIds)
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':parentFilterId', $parentFilterId, \PDO::PARAM_INT);
        $statement->bindValue(':type', $filter->getType(), \PDO::PARAM_STR);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
        $statement->bindValue(':resourceIds', implode(',', $filter->getResourceIds()), \PDO::PARAM_STR);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function linkDatasetToRule(int $ruleId, int $datasetId): void
    {
        $this->debug(
            'Add relation between rule and dataset',
            [
                'acl_resource_id' => $datasetId,
                'acl_group_id' => $ruleId,
            ]
        );
        $request = $this->translateDbName(
            <<<'SQL'
                    INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) VALUES (:datasetId, :ruleId)
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addDataset(string $name): int
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    INSERT INTO `:db`.acl_resources (acl_res_name, acl_res_activate, changed, cloud_specific) VALUES (:name, '1', 1, 1)
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $name, \PDO::PARAM_STR);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function linkContactsToRule(int $ruleId, array $contactIds): void
    {
        $this->debug(
            'Add relation between contacts and rule',
            ['rule_id' => $ruleId, 'contacts' => $contactIds]
        );

        if ([] === $contactIds) {
            return;
        }

        $bindValues = [];
        $subValues = [];
        foreach ($contactIds as $index => $contactId) {
            $bindValues["contact_id_{$index}"] = $contactId;
            $subValues[] = "(:contact_id_{$index}, :ruleId)";
        }

        $subQueries = implode(', ', $subValues);

        $request = $this->translateDbName(
            <<<SQL
                    INSERT INTO `:db`.acl_group_contacts_relations (contact_contact_id, acl_group_id)
                    VALUES {$subQueries}
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);

        foreach ($bindValues as $bindKey => $bindValue) {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewRule $rule): int
    {
        $this->debug('Add resource access rule');

        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $ruleId = $this->addBasicInformation($rule);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            $this->debug('Resource access rule added with ID ' . $ruleId);

            return $ruleId;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function linkContactGroupsToRule(int $ruleId, array $contactGroupIds): void
    {
        $this->debug(
            'Add relation between contact groups and rule',
            ['rule_id' => $ruleId, 'contact_groups' => $contactGroupIds]
        );

        if ([] === $contactGroupIds) {
            return;
        }

        $bindValues = [];
        $subValues = [];
        foreach ($contactGroupIds as $index => $contactGroupId) {
            $bindValues["contact_group_id_{$index}"] = $contactGroupId;
            $subValues[] = "(:contact_group_id_{$index}, :ruleId)";
        }

        $subQueries = implode(', ', $subValues);

        $request = $this->translateDbName(
            <<<SQL
                    INSERT INTO `:db`.acl_group_contactgroups_relations (cg_cg_id, acl_group_id)
                    VALUES {$subQueries}
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);

        foreach ($bindValues as $bindKey => $bindValue) {
            $statement->bindValue($bindKey, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * @param NewRule $rule
     *
     * @throws \PDOException
     *
     * @return int
     */
    private function addBasicInformation(NewRule $rule): int
    {
        // cloud_specific field forced to 1 as we do create this from a cloud feature
        $request = $this->translateDbName(
            <<<'SQL'
                    INSERT INTO `:db`.acl_groups
                    (acl_group_name, acl_group_alias, acl_group_changed, cloud_description, acl_group_activate, cloud_specific) VALUES
                    (:name, :alias, '1', :description, :status, 1)
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $rule->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $rule->getName(), \PDO::PARAM_STR);

        $statement->bindValue(
            ':description',
            $this->emptyStringAsNull($rule->getDescription() ?? ''),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':status', $rule->isEnabled() ? '1' : '0', \PDO::PARAM_STR);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }
}
