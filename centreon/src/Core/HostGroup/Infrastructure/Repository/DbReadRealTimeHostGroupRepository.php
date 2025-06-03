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

namespace Core\HostGroup\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\HostGroup\Application\Repository\ReadRealTimeHostGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadRealTimeHostGroupRepository extends DatabaseRepository implements ReadRealTimeHostGroupRepositoryInterface
{
    use HostGroupRepositoryTrait;

    public function exists(int $hostGroupId): bool
    {
        try {
            $query = $this->translateDbName(
                <<<'SQL'
                    SELECT
                        1
                    FROM `:dbstg`.tag 
                    INNER JOIN `:db`.hostgroup
                        ON tag.id = hostgroup.hg_id
                    WHERE hostgroup.hg_activate = '1'
                        AND tag.id = :hostGroupId
                        AND tag.type = 1
                SQL
            );

            return (bool) $this->connection->fetchOne(
                $query,
                QueryParameters::create([QueryParameter::int('id', $hostGroupId)]),
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "Error while checking hostgroup existence: {$exception->getMessage()}",
                context: ['host_group_id' => $hostGroupId],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(int $hostGroupId, array $accessGroups): bool
    {
        if ([] === $accessGroups) {
            return false;
        }

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups,
        );

        if ($this->hasAccessToAllHostGroups($accessGroupIds)) {
            return true;
        }

        try {
            $query = $this->translateDbName(
                <<<'SQL'
                    SELECT
                        1
                    FROM `:dbstg`.tag 
                    INNER JOIN `:db`.hostgroup
                        ON tag.id = hostgroup.hg_id
                    INNER JOIN `:db`.acl_resources_hg_relations arhr
                        ON hg.hg_id = arhr.hg_hg_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    WHERE hostgroup.hg_activate = '1'
                        AND tag.id = :hostGroupId
                        AND tag.type = 1
                SQL
            );

            return (bool) $this->connection->fetchOne(
                $query,
                QueryParameters::create([QueryParameter::int('id', $hostGroupId)]),
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "Error while checking hostgroup existence by access groups: {$exception->getMessage()}",
                context: [
                    'host_group_id' => $hostGroupId,
                    'access_groups' => $accessGroupIds,
                ],
                previous: $exception
            );
        }
    }
}
