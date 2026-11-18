<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Gorgone\Application\Repository\CollectorEnrolmentRecordRepositoryInterface;

/**
 * Table collector_enrolment, created on first use: the installer of the container images is the
 * one of their base image, which does not know it.
 */
final class DbCollectorEnrolmentRecordRepository extends AbstractRepositoryRDB implements CollectorEnrolmentRecordRepositoryInterface
{
    /** MariaDB named lock: dies with the connection, so a killed cron frees it. */
    private const LOCK = 'centreon-collector-enrolment';

    private bool $tableReady = false;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function lock(): bool
    {
        $statement = $this->db->prepare('SELECT GET_LOCK(:name, 0)');
        $statement->bindValue(':name', self::LOCK, \PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->fetchColumn() === 1;
    }

    public function unlock(): void
    {
        $statement = $this->db->prepare('DO RELEASE_LOCK(:name)');
        $statement->bindValue(':name', self::LOCK, \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $this->createTable();
        $statement = $this->db->query($this->translateDbName(
            'SELECT enrolment_id, poller_name, poller_address, poller_id FROM `:db`.`collector_enrolment`'
        ));
        $records = [];
        foreach ($statement !== false ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [] as $row) {
            $records[(string) $row['enrolment_id']] = [
                'name' => (string) $row['poller_name'],
                'address' => (string) $row['poller_address'],
                'pollerId' => $row['poller_id'] !== null ? (int) $row['poller_id'] : null,
            ];
        }

        return $records;
    }

    /**
     * @inheritDoc
     */
    public function add(string $enrolmentId, string $name, string $address): void
    {
        $this->createTable();
        $statement = $this->db->prepare($this->translateDbName(
            'INSERT IGNORE INTO `:db`.`collector_enrolment` (enrolment_id, poller_name, poller_address, created_at)
             VALUES (:id, :name, :address, UNIX_TIMESTAMP())'
        ));
        $statement->bindValue(':id', $enrolmentId, \PDO::PARAM_STR);
        $statement->bindValue(':name', $name, \PDO::PARAM_STR);
        $statement->bindValue(':address', $address, \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function setPoller(string $enrolmentId, int $pollerId): void
    {
        $this->createTable();
        $statement = $this->db->prepare($this->translateDbName(
            'UPDATE `:db`.`collector_enrolment` SET poller_id = :poller WHERE enrolment_id = :id'
        ));
        $statement->bindValue(':poller', $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':id', $enrolmentId, \PDO::PARAM_STR);
        $statement->execute();
    }

    private function createTable(): void
    {
        if ($this->tableReady) {
            return;
        }
        $this->db->exec($this->translateDbName(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `:db`.`collector_enrolment` (
                  `enrolment_id` varchar(64) NOT NULL,
                  `poller_name` varchar(255) NOT NULL,
                  `poller_address` varchar(255) NOT NULL,
                  `poller_id` int(11) DEFAULT NULL,
                  `created_at` bigint UNSIGNED NOT NULL,
                  PRIMARY KEY (`enrolment_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
        ));
        $this->tableReady = true;
    }
}
