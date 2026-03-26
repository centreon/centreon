<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\ResourceAccess\Application\Repository\WriteDatasetRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ImageFolderFilterType;

final class DbWriteImageFolderRepository extends DatabaseRepository implements WriteDatasetRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function linkResourcesToDataset(int $ruleId, int $datasetId, array $resourceIds): void
    {
        try {
            if ($resourceIds === []) {
                return;
            }

            $insertQueryParameters = [];
            foreach ($resourceIds as $index => $value) {
                $insertQueryParameters[] = QueryParameters::create([
                    QueryParameter::int('datasetId', $datasetId),
                    QueryParameter::int('folderId' . $index, $value),
                ]);
            }

            $this->connection->batchInsert(
                tableName: 'acl_resources_image_folder_relations',
                columns: [
                    'acl_res_id',
                    'dir_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertQueryParameters)
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $ex) {
            throw new RepositoryException(
                message: "An error occurred while linking resources to dataset: {$ex->getMessage()}",
                context: [
                    'rule_id' => $ruleId,
                    'dataset_id' => $datasetId,
                    'resource_ids' => $resourceIds,
                ],
                previous: $ex
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $type): bool
    {
        return $type === ImageFolderFilterType::TYPE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function updateDatasetAccess(int $ruleId, int $datasetId, bool $fullAccess): void
    {
        try {
            $queryParameters = QueryParameters::create([
                QueryParameter::int('datasetId', $datasetId),
                QueryParameter::string('access', $fullAccess ? '1' : '0'),
            ]);

            $query = <<<'SQL'
                    UPDATE `:db`.acl_resources SET all_image_folders = :access WHERE acl_res_id = :datasetId
                SQL;

            $this->connection->update($this->translateDbName($query), $queryParameters);
        } catch (ValueObjectException|CollectionException|ConnectionException $ex) {
            throw new RepositoryException(
                message: "An error occurred while updating dataset access: {$ex->getMessage()}",
                context: [
                    'rule_id' => $ruleId,
                    'dataset_id' => $datasetId,
                    'full_access' => $fullAccess,
                ],
                previous: $ex
            );
        }
    }
}
