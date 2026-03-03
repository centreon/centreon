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
     */
    public function findExportTasks()
    {
        $sql = 'SELECT * FROM task WHERE `type` = "export" AND `status` = "pending"';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetchAll();

        return $result ?: null;
    }

    /**
     * remove export task running for too long
     * @params int $taskTimeout the maximum time a task is allowed to be in a running state
     */
    public function removeRunningExportTask($taskTimeout): void
    {
        // a task is really stop only by gorgone when it kills the process. We just make sure that those killed process have the proper state in the db
        $dateLimit = time() - $taskTimeout;
        $failedState = Task::STATE_TIMEOUT;
        $runningState = Task::STATE_PROGRESS;
        $query = "UPDATE task SET status=:task_new_status WHERE type = 'export' AND UNIX_TIMESTAMP(created_at) < :task_timeout AND status=:task_current_status";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':task_timeout', $dateLimit, PDO::PARAM_INT);
        $stmt->bindParam(':task_new_status', $timeoutState, PDO::PARAM_STR);
        $stmt->bindParam(':task_current_status', $runningState, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * remove other import task running for too long
     * @params int $currentTaskId need to ignore the current task id 
     * @params int $taskTimeout the maximum time a task is allowed to be in a running state
     */
    public function removeRunningImportTask($currentTaskId, $taskTimeout): void
    {
        // a task is really stop only by gorgone when it kills the process. We just make sure that those killed process have the proper state in the db
        $dateLimit = time() - $taskTimeout;
        $timeoutState = Task::STATE_TIMEOUT;
        $runningState = Task::STATE_PROGRESS;
        $query = "UPDATE task SET status=:task_new_status WHERE type = 'import' AND UNIX_TIMESTAMP(created_at) < :task_timeout AND status=:task_current_status AND id <> :task_id";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':task_id', $currentTaskId, PDO::PARAM_INT);
        $stmt->bindParam(':task_timeout', $dateLimit, PDO::PARAM_INT);
        $stmt->bindParam(':task_new_status', $timeoutState, PDO::PARAM_STR);
        $stmt->bindParam(':task_current_status', $runningState, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * remove import task that are older than current task and still pending
     * @params int $taskId 
     */
    public function removeOlderPendingImportTask($taskId): void
    {
        $pendingState = Task::STATE_PENDING;
        $outdatedState = Task::STATE_OUTDATED;
        $query = "UPDATE task SET status=:outdated_state WHERE type = 'import' AND status=:pending_state AND id < :task_id";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
        $stmt->bindParam(':outdated_state', $outdatedState, PDO::PARAM_STR);
        $stmt->bindParam(':pending_state', $pendingState, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * check if there is an import task already running that is not too old
     * @params int $taskTimeout the maximum time a task is allowed to be in a running state
     */
    public function isThereAnyLegitimateRunningImportTask($taskTimeout): bool
    {
        // first remove known task that were running for too long
        $this->removeRunningImportTask($taskTimeout);

        $dateLimit = time() - $taskTimeout;
        $runningState = Task::STATE_PROGRESS;
        $query = "SELECT count(id) AS found_running_task FROM task WHERE type = 'import' AND status=:running_state AND UNIX_TIMESTAMP(created_at) >= :task_timeout";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':task_timeout', $dateLimit, PDO::PARAM_INT);
        $stmt->bindParam(':running_state', $runningState, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        // there is no running task, wich mean that we can start processing a pending one
        if ((int) $result[0]['found_running_task'] !== 0) {
            return true;
        }

        return false;
    }

    /**
     * check if there is an expoort task already running that is not too old
     * @params int $taskTimeout the maximum time a task is allowed to be in a running state
     */
    public function isThereAnyLegitimateRunningExportTask($taskTimeout): bool
    {
        // first remove known task that were running for too long
        $this->removeRunningExportTask($taskTimeout);

        $dateLimit = time() - $taskTimeout;
        $runningState = Task::STATE_PROGRESS;
        $query = "SELECT count(id) AS found_running_task FROM task WHERE type='export' AND status=:running_state AND UNIX_TIMESTAMP(created_at) >= :task_timeout";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':task_timeout', $dateLimit, PDO::PARAM_INT);
        $stmt->bindParam(':running_state', $runningState, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        // there is no running task, wich mean that we can start processing a pending one
        if ((int) $result[0]['found_running_task'] !== 0) {
            return true;
        }

        return false;
    }

    /**
     * name of the function sucks. We in fact check if there is a more recent pending, running or completed task that is more recent.
     * 
     * @params int $taskId
     */
    public function isThereAnyMoreRecentTaskThatIsPending($taskId): bool
    {
        $pendingState = Task::STATE_PENDING;
        $runningState = Task::STATE_PROGRESS;
        $completedState = Task::STATE_COMPLETED;
        // if a more recent task is in a completed state, you don't need to run the current task since your db is already up to date
        $query = "SELECT count(id) AS found_more_recent_task FROM task WHERE status IN (:pending_state, :running_state, :completed_state) AND id > :task_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
        $stmt->bindParam(':pending_state', $pendingState, PDO::PARAM_STR);
        $stmt->bindParam(':running_state', $runningState, PDO::PARAM_STR);
        $stmt->bindParam(':completed_state', $completedState, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();

        // in such case, the current task is supposed to be marked as outdated
        if ((int) $result[0]['found_more_recent_task'] !== 0) {
            return true;
        }

        return false;
    }

    /**
     * find a given export pending task
     * @params int $taskId
     */
    public function findExportTaskById($taskId)
    {
        $sql = 'SELECT * FROM task WHERE `type` = "export" AND `status` = "pending" AND `id` = :task_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(":task_id", $taskId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetchAll();

        return $result ?: null;
    }

    /**
     * find a given import pending task
     * @params int $taskId
     */
    public function findImportTaskById($taskId)
    {
        $sql = 'SELECT * FROM task WHERE `type` = "import" AND `status` = "pending" AND `id` = :task_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(":task_id", $taskId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Task::class);
        $result = $stmt->fetchAll();

        return $result ?: null;
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
     * update task status
     * @param mixed $status
     * @param mixed $taskId
     */
    public function updateStatus($status, $taskId)
    {
        $sql = "UPDATE task SET status = '{$status}' WHERE id = {$taskId}";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute();
    }
}
