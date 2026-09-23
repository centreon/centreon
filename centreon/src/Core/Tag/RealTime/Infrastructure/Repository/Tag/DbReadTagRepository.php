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

namespace Core\Tag\RealTime\Infrastructure\Repository\Tag;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Tag\RealTime\Application\Repository\ReadTagRepositoryInterface;

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
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
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
}
