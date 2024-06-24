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
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\ResourceAccess\Application\Repository\WriteDatasetRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\ResourceAccess\Domain\Model\Rule;

final class DbWriteResourceAccessRepository extends AbstractRepositoryRDB implements WriteResourceAccessRepositoryInterface
{
    use LoggerTrait, RepositoryTrait, SqlMultipleBindTrait;

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
    public function updateDatasetAccess(int $ruleId, int $datasetId, string $resourceType, bool $fullAccess): void
    {
        foreach ($this->repositoryProviders as $repositoryProvider) {
            if ($repositoryProvider->isValidFor($resourceType) === true) {
                $repositoryProvider->updateDatasetAccess(ruleId: $ruleId, datasetId: $datasetId, fullAccess: $fullAccess);
            }
        }
    }

    /**
     * @inheritDoc
     * Here are the deletions (on cascade or not) that will occur on rule deletion
     *     - Contact relations (ON DELETE CASCADE)
     *     - Contact Group relations (ON DELETE CASCADE)
     *     - Datasets relations + datasets (NEED MANUAL DELETION)
     *     - DatasetFilters (ON DELETE CASCADE).
     */
    public function deleteRuleAndDatasets(int $ruleId): void
    {
        $datasetIds = $this->findDatasetIdsByRuleId($ruleId);
        $alreadyInTransaction = $this->db->inTransaction();

        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->deleteDatasets($datasetIds);
            $this->deleteRule($ruleId);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param int[] $ids
     */
    public function deleteDatasets(array $ids): void
    {
        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ids, ':dataset_id_');

        $request = <<<SQL
                DELETE FROM `:db`.acl_resources WHERE acl_res_id IN ({$bindQuery})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(Rule $rule): void
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->updateBasicInformation($rule);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteContactRuleRelations(int $ruleId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.acl_group_contacts_relations WHERE acl_group_id = :ruleId
                SQL
        ));

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteContactGroupRuleRelations(int $ruleId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.acl_group_contactgroups_relations WHERE acl_group_id = :ruleId
                SQL
        ));

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();
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
    public function addDataset(
        string $name,
        bool $accessAllHosts = false,
        bool $accessAllHostGroups = false,
        bool $accessAllServiceGroups = false,
    ): int {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.acl_resources
                    (acl_res_name, all_hosts, all_hostgroups, all_servicegroups, acl_res_activate, changed, cloud_specific)
                VALUES (:name, :allHosts, :allHostGroups, :allServiceGroups, '1', 1, 1)
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $name, \PDO::PARAM_STR);
        $statement->bindValue(':allHosts', $accessAllHosts ? '1' : '0', \PDO::PARAM_STR);
        $statement->bindValue(':allHostGroups', $accessAllHostGroups ? '1' : '0', \PDO::PARAM_STR);
        $statement->bindValue(':allServiceGroups', $accessAllServiceGroups ? '1' : '0', \PDO::PARAM_STR);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function linkContactsToRule(int $ruleId, array $contactIds): void
    {
        if ([] === $contactIds) {
            return;
        }

        $contactIds = array_values(array_unique($contactIds));

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
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $ruleId = $this->addBasicInformation($rule);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

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
        if ([] === $contactGroupIds) {
            return;
        }

        $contactGroupIds = array_values(array_unique($contactGroupIds));

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
     * @param int $ruleId
     *
     * @return int[]
     */
    private function findDatasetIdsByRuleId(int $ruleId): array
    {
        // Retrieve first all the datasets linked to the rule
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                        SELECT acl_res_id FROM `:db`.acl_res_group_relations WHERE acl_group_id = :ruleId
                    SQL
            )
        );

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $ruleId
     */
    private function deleteRule(int $ruleId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                'DELETE FROM `:db`.acl_groups WHERE acl_group_id = :ruleId AND cloud_specific = 1'
            )
        );

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    private function updateBasicInformation(Rule $rule): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    UPDATE
                        `:db`.acl_groups
                    SET
                        acl_group_name = :name,
                        acl_group_alias = :alias,
                        acl_group_changed = '1',
                        cloud_description = :description,
                        acl_group_activate = :status,
                        cloud_specific = 1,
                        all_contacts = :applyToAllContacts,
                        all_contact_groups = :applyToAllContactGroups
                    WHERE
                        acl_group_id = :ruleId
                SQL
        ));

        $statement->bindValue(':name', $rule->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':alias', $rule->getName(), \PDO::PARAM_STR);

        $statement->bindValue(
            ':description',
            $this->emptyStringAsNull($rule->getDescription() ?? ''),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':status', $rule->isEnabled() ? '1' : '0', \PDO::PARAM_STR);
        $statement->bindValue(':ruleId', $rule->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':applyToAllContacts', $rule->doesApplyToAllContacts() ? 1 : 0, \PDO::PARAM_INT);
        $statement->bindValue(':applyToAllContactGroups', $rule->doesApplyToAllContactGroups() ? 1 : 0, \PDO::PARAM_INT);

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
                        (
                            acl_group_name,
                            acl_group_alias,
                            acl_group_changed,
                            cloud_description,
                            acl_group_activate,
                            cloud_specific,
                            all_contacts,
                            all_contact_groups
                        ) VALUES
                        (
                            :name,
                            :alias,
                            '1',
                            :description,
                            :status,
                            1,
                            :applyToAllContacts,
                            :applyToAllContactGroups
                        )
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
        $statement->bindValue(':applyToAllContacts', $rule->doesApplyToAllContacts() ? 1 : 0, \PDO::PARAM_INT);
        $statement->bindValue(':applyToAllContactGroups', $rule->doesApplyToAllContactGroups() ? 1 : 0, \PDO::PARAM_INT);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }
}
