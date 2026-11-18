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

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\Task;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use PDO;

class TaskRepository extends ServiceEntityRepository
{
    /**
     * Find one by id
     * @param int $id
     * @return Task|null
     */
    public function findOneById($id)
    {
        $sql = 'SELECT * FROM task WHERE `id` = :id LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find one by parent id
     * @param int $id
     * @return Task|null
     */
    public function findOneByParentId($id)
    {
        $sql = 'SELECT * FROM task WHERE `parent_id` = :id LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * find all pending export tasks
     * @return Task[]
     */
    public function findExportTasks(): array
    {
        $sql = 'SELECT * FROM task WHERE `type` = "export" AND `status` = "pending"';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetchAll();

        return $result ?: [];
    }

    /**
     * find all pending import tasks
     */
    public function findImportTasks()
    {
        $sql = 'SELECT * FROM task WHERE `type` = "import" AND `status` = "pending"';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetchAll();

        return $result ?: null;
    }

    /**
     * Delete import tasks stuck in 'inprogress' state for longer than $timeoutSeconds.
     *
     * @param int $timeoutSeconds
     *
     * @return int number of deleted tasks
     */
    public function deleteTimedOutImportTasks(int $timeoutSeconds): int
    {
        $sql = 'DELETE FROM task
                WHERE type = :type
                AND status = :status
                AND created_at <= DATE_SUB(NOW(), INTERVAL :timeout SECOND)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':type', Task::TYPE_IMPORT);
        $stmt->bindValue(':status', Task::STATE_PROGRESS);
        $stmt->bindValue(':timeout', $timeoutSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    /**
     * update task status
     * @param mixed $status
     * @param mixed $taskId
     */
    public function updateStatus($status, $taskId)
    {
        $sql = 'UPDATE task SET status = :status WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $taskId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
