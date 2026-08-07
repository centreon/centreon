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

namespace CentreonRemote\Application\Clapi;

use Centreon\Domain\Entity\Command;
use Centreon\Domain\Entity\Task;
use Centreon\Domain\Repository\TaskRepository;
use Centreon\Infrastructure\Service\CentcoreCommandService;
use Centreon\Infrastructure\Service\CentreonClapiServiceInterface;
use CentreonRemote\Infrastructure\Export\ExportCommitment;
use ConfigGenerateRemote\Backend;
use Pimple\Container;

/**
 * Manage worker queue with centcore (import/export tasks...).
 */
class CentreonWorker implements CentreonClapiServiceInterface
{
    /**
     * Default staleness threshold (in seconds) used to remove orphaned export temp
     * directories when no --commandTimeout is provided.
     */
    private const DEFAULT_ORPHAN_TMPDIR_MAX_AGE = 3600;

    /** @var Container */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get Class name.
     *
     * @throws \ReflectionException
     *
     * @return string
     */
    public static function getName(): string
    {
        return (new \ReflectionClass(self::class))->getShortName();
    }

    /**
     * Process task queue for import/export.
     *
     * When --commandTimeout <seconds> is provided, import tasks stuck in 'inprogress'
     * state for longer than the given duration are deleted before processing begins.
     * Orphaned export temp directories older than that same threshold are also removed
     * from the export cache to prevent filesystem saturation.
     */
    public function processQueue(): void
    {
        $commandTimeout = $this->container['worker.commandTimeout'] ?? null;

        if ($commandTimeout !== null) {
            $deleted = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
                ->getRepository(TaskRepository::class)
                ->deleteTimedOutImportTasks($commandTimeout);

            echo date('Y-m-d H:i:s') . " - INFO - Deleted {$deleted} timed-out import task(s)"
                . " (older than {$commandTimeout}s).\n";
        }

        // remove orphaned temporary export directories left behind when a worker
        // process was killed before movePath()/cleanPath() could run (e.g. gorgone
        // command timeout). Reuse the gorgone command timeout as the staleness
        // threshold when available: past that duration the process that created the
        // directory has necessarily been killed, so it is safe to remove.
        $orphanMaxAge = $commandTimeout ?? self::DEFAULT_ORPHAN_TMPDIR_MAX_AGE;
        $removedEntries = Backend::cleanOrphanedTmpDirs($orphanMaxAge);

        echo date('Y-m-d H:i:s') . " - INFO - Removed {$removedEntries} orphaned export"
            . " temp entry(ies) (older than {$orphanMaxAge}s).\n";

        // check export tasks in database and execute these
        $this->processExportTasks();

        // check import tasks in database and execute these
        $this->processImportTasks();
    }

    /**
     * Worker method to create task for import on remote.
     *
     * @param int $taskId the task id to create on the remote server
     *
     * @throws \Exception
     */
    public function createRemoteTask(int $taskId): void
    {
        // find task parameters (type, status, params...)
        /** @var Task|null $task */
        $task = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findOneById($taskId);

        if ($task === null) {
            throw new \InvalidArgumentException(sprintf('Task %d not found', $taskId));
        }

        /**
         * create import task on remote.
         */
        $serializedParams = $task->getParams();
        if (empty($serializedParams)) {
            throw new \Exception('Invalid Parameters');
        }
        $taskParams = unserialize($serializedParams, ['allowed_classes' => false]);
        if (! is_array($taskParams) || ! array_key_exists('params', $taskParams)) {
            throw new \Exception('Missing parameters: params');
        }
        $params = $taskParams['params'];
        $centreonPath = trim($params['centreon_path'], '/');
        $centreonPath = $centreonPath ?: '/centreon';
        $url = $params['http_method'] ? $params['http_method'] . '://' : '';
        $url .= $params['remote_ip'];
        $url .= $params['http_port'] ? ':' . $params['http_port'] : '';
        $url .= "/{$centreonPath}/api/external.php?object=centreon_task_service&action=AddImportTaskWithParent";

        try {
            $curl = new \CentreonRestHttp();
            $res = $curl->call(
                $url,
                'POST',
                ['parent_id' => $task->getId()],
                [],
                false,
                $params['no_check_certificate'],
                $params['no_proxy']
            );
        } catch (\Exception $e) {
            echo date('Y-m-d H:i:s') . ' - ERROR - Error while creating parent task on '
                . $url . ".\n";
            echo date('Y-m-d H:i:s') . ' - ERROR - Error message: ' . $e->getMessage() . "\n";
        }
    }

    public function getDi(): Container
    {
        return $this->container;
    }

    /**
     * Execute export tasks which are store in task table.
     */
    private function processExportTasks(): void
    {
        /** @var Task[] $tasks */
        $tasks = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findExportTasks();

        echo date('Y-m-d H:i:s') . ' - INFO - Checking for pending export tasks: '
            . count($tasks) . " task(s) found.\n";

        foreach (array_values($tasks) as $task) {
            echo date('Y-m-d H:i:s') . ' - INFO - Processing task #' . $task->getId() . "...\n";

            // mark task as being worked on
            $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_PROGRESS);
            $serializedParams = $task->getParams();
            if (empty($serializedParams)) {
                throw new \Exception('Invalid Parameters');
            }
            $taskParams = unserialize($serializedParams, ['allowed_classes' => false]);
            if (! is_array($taskParams) || ! array_key_exists('params', $taskParams)) {
                throw new \Exception('Missing parameters: params');
            }
            $params = $taskParams['params'];
            $commitment = new ExportCommitment($params['server'], $params['pollers']);

            try {
                $this->getDi()['centreon_remote.export']->export($commitment);

                $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_COMPLETED);

                /**
                 * move export file.
                 */
                $cmd = new Command();
                $compositeKey = $params['server'] . ':' . $task->getId();
                $cmd->setCommandLine(Command::COMMAND_TRANSFER_EXPORT_FILES . $compositeKey);
                $cmdService = new CentcoreCommandService();
                $cmdWritten = $cmdService->sendCommand($cmd);

                echo date('Y-m-d H:i:s') . ' - INFO - Task #' . $task->getId() . " completed.\n";
            } catch (\Exception $e) {
                echo date('Y-m-d H:i:s') . ' - ERROR - Task #' . $task->getId() . " failed.\n";
                echo date('Y-m-d H:i:s') . ' - ERROR - Error message: ' . $e->getMessage() . "\n";
                $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_FAILED);
            }
        }

        echo date('Y-m-d H:i:s') . " - INFO - Worker cycle completed.\n";
    }

    /**
     * Execute import tasks which are store in task table.
     */
    private function processImportTasks(): void
    {
        $tasks = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findImportTasks() ?? [];

        echo date('Y-m-d H:i:s') . ' - INFO - Checking for pending import tasks: '
            . count($tasks) . " task(s) found.\n";

        foreach ($tasks as $x => $task) {
            echo date('Y-m-d H:i:s') . ' - INFO - Processing task #'
                . $task->getId() . ' (parent ID #' . $task->getParentId() . ")...\n";

            // mark task as being worked on
            $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_PROGRESS);

            try {
                $this->getDi()['centreon_remote.export']->import();

                $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_COMPLETED);
                echo date('Y-m-d H:i:s') . ' - INFO - Task #' . $task->getId() . " completed.\n";
            } catch (\Exception $e) {
                echo date('Y-m-d H:i:s') . ' - ERROR - Task #' . $task->getId() . " failed.\n";
                echo date('Y-m-d H:i:s') . ' - ERROR - Error message: ' . $e->getMessage() . "\n";
                $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_FAILED);
            }
        }

        echo date('Y-m-d H:i:s') . " - INFO - Worker cycle completed.\n";
    }
}
