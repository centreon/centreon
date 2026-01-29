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

use Centreon\Domain\PlatformTopology\Interfaces\PlatformInterface;
use Centreon\Domain\PlatformTopology\Model\PlatformRegistered;
use Centreon\Infrastructure\PlatformTopology\Repository\Model\PlatformTopologyFactoryRDB;

require_once 'Centreon/Object/Object.php';

/**
 * Used for interacting with Instances (pollers)
 *
 * @author sylvestre
 */
class Centreon_Object_Instance extends Centreon_Object
{
    protected $table = 'nagios_server';

    protected $primaryKey = 'id';

    protected $uniqueLabelField = 'name';

    public function getDefaultInstance()
    {
        $res = $this->db->query('SELECT `name` FROM `nagios_server` WHERE `is_default` = 1');
        if ($res->rowCount() == 0) {
            $res = $this->db->query("SELECT `name` FROM `nagios_server` WHERE `localhost` = '1'");
        }

        $row = $res->fetch();

        return $row['name'];
    }

    /**
     * Insert platform in nagios_server and platform_topology tables.
     *
     * @param array<string,mixed> $params
     * @return int
     */
    public function insert($params = [])
    {
        if (! array_key_exists('ns_ip_address', $params) || ! array_key_exists('name', $params)) {
            throw new InvalidArgumentException('Missing parameters');
        }
        $platformTopology = $this->findPlatformTopologyByAddress($params['ns_ip_address']);
        $serverId = null;

        $isAlreadyInTransaction = $this->db->inTransaction();
        if (! $isAlreadyInTransaction) {
            $this->db->beginTransaction();
        }
        if ($platformTopology !== null) {
            if ($platformTopology->isPending() === false) {
                throw new Exception('Platform already created');
            }

            /**
             * Check if the parent is a registered remote.
             */
            $parentPlatform = $this->findPlatformTopology($platformTopology->getParentId());
            if ($parentPlatform !== null && $parentPlatform->getType() === PlatformRegistered::TYPE_REMOTE) {
                if ($parentPlatform->getServerId() === null) {
                    throw new Exception("Parent remote server isn't registered");
                }
                $params['remote_id'] = $parentPlatform->getServerId();
            }

            try {
                $serverId = parent::insert($params);
                $platformTopology->setPending(false);
                $platformTopology->setServerId($serverId);
                $this->updatePlatformTopology($platformTopology);
            } catch (Exception $ex) {
                if (! $isAlreadyInTransaction) {
                    $this->db->rollBack();
                }

                throw new Exception('Unable to update platform', 0, $ex);
            }
        } else {
            try {
                $serverId = parent::insert($params);
                $params['server_id'] = $serverId;
                $this->insertIntoPlatformTopology($params);
            } catch (Exception $ex) {
                if (! $isAlreadyInTransaction) {
                    $this->db->rollBack();
                }

                throw new Exception('Unable to create platform', 0, $ex);
            }
        }
        if (! $isAlreadyInTransaction) {
            $this->db->commit();
        }

        return $serverId;
    }

    /**
     * Find existing platform by id.
     *
     * @param int $id
     * @return PlatformInterface|null
     */
    private function findPlatformTopology(int $id): ?PlatformInterface
    {
        $statement = $this->db->prepare('SELECT * FROM platform_topology WHERE id=:id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        if ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
            return PlatformTopologyFactoryRDB::create($result);
        }

        return null;
    }

    /**
     * Find existing platform by address.
     *
     * @param string $address
     * @return PlatformInterface|null
     */
    private function findPlatformTopologyByAddress(string $address): ?PlatformInterface
    {
        $statement = $this->db->prepare('SELECT * FROM platform_topology WHERE address=:address');
        $statement->bindValue(':address', $address, PDO::PARAM_STR);
        $statement->execute();
        if ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
            return PlatformTopologyFactoryRDB::create($result);
        }

        return null;
    }

    /**
     * Update a platform topology.
     *
     * @param PlatformInterface $platformTopology
     */
    private function updatePlatformTopology(PlatformInterface $platformTopology): void
    {
        $statement = $this->db->prepare(
            'UPDATE platform_topology SET pending=:isPending, server_id=:serverId WHERE address=:address'
        );
        $statement->bindValue(':isPending', $platformTopology->isPending() ? '1' : '0', PDO::PARAM_STR);
        $statement->bindValue(':serverId', $platformTopology->getServerId(), PDO::PARAM_INT);
        $statement->bindValue(':address', $platformTopology->getAddress(), PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * Insert the poller in platform_topology.
     *
     * @param array<string,mixed> $params
     */
    private function insertIntoPlatformTopology(array $params): void
    {
        $centralPlatformTopologyId = $this->findCentralPlatformTopologyId();
        if ($centralPlatformTopologyId === null) {
            throw new Exception('No Central found in topology');
        }
        $statement = $this->db->prepare(
            'INSERT INTO platform_topology (address, name, type, pending, parent_id, server_id) '
            . "VALUES (:address, :name, '" . PlatformRegistered::TYPE_POLLER . "', '0', :parentId, :serverId)"
        );
        $statement->bindValue(':address', $params['ns_ip_address'], PDO::PARAM_STR);
        $statement->bindValue(':name', $params['name'], PDO::PARAM_STR);
        $statement->bindValue(':parentId', $centralPlatformTopologyId, PDO::PARAM_INT);
        $statement->bindValue(':serverId', $params['server_id'], PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Find the Central Id in platform_topology.
     *
     * @return int|null
     */
    private function findCentralPlatformTopologyId(): ?int
    {
        $result = $this->db->query(
            "SELECT id from platform_topology WHERE type ='" . PlatformRegistered::TYPE_CENTRAL . "'"
        );
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            return (int) $row['id'];
        }

        return null;
    }
}
