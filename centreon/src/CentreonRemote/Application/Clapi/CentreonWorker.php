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
use Pimple\Container;

/**
 * Manage worker queue with centcore (import/export tasks...).
 */
class CentreonWorker implements CentreonClapiServiceInterface
{
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
     * @params ?string $params format: <task_id>;<timeout>
     */
    public function processQueue(?string $params): void
    {
        if (isset($params)) {
            $taskParams = explode(";", $params);
            $taskId = (int) $taskParams[0];
            $taskTimeout = (int) $taskParams[1];
        }

        // check export tasks in database and execute these
        if (isset($taskId)) {
            $this->processSingleExportTask($taskId, $taskTimeout);    
        } else {
            $this->processExportTasks();
        }

        // check import tasks in database and execute these
        if (isset($taskId)) {
            $this->processSingleImportTask($taskId, $taskTimeout);    
        } else {
            $this->processImportTasks();
        }
    }

    /**
     * Worker method to create task for import on remote.
     *
     * @param int $taskId the task id to create on the remote server
     */
    public function createRemoteTask(int $taskId): void
    {
        // find task parameters (type, status, params...)
        $task = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findOneById($taskId);

        /**
         * create import task on remote.
         */
        $serializedParams = htmlspecialchars($task->getParams());
        if (empty($serializedParams)) {
            throw new \Exception('Invalid Parameters');
        }
        $taskParams = unserialize($serializedParams);
        if (! array_key_exists('params', $taskParams)) {
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
        $tasks = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findExportTasks() ?? [];

        echo date('Y-m-d H:i:s') . ' - INFO - Checking for pending export tasks: '
            . count($tasks) . " task(s) found.\n";

        foreach (array_values($tasks) as $task) {
            echo date('Y-m-d H:i:s') . ' - INFO - Processing task #' . $task->getId() . "...\n";

            // mark task as being worked on
            $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_PROGRESS);
            $serializedParams = htmlspecialchars($task->getParams(), ENT_NOQUOTES);
            if (empty($serializedParams)) {
                throw new \Exception('Invalid Parameters');
            }
            $taskParams = unserialize($serializedParams);
            if (! array_key_exists('params', $taskParams)) {
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
     * Execute export tasks which are store in task table.
     */
    private function processSingleExportTask(int $taskId, int $taskTimeout): void
    {
        // some task could be in a running state for too long, we "stop" them (the real stop comes from gorgone that kills the process))
        $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->removeRunningExportTask($taskTimeout);

        $tasks = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->findExportTaskById($taskId) ?? [];

        foreach (array_values($tasks) as $task) {
            echo date('Y-m-d H:i:s') . ' - INFO - Processing task #' . $task->getId() . "...\n";

            // mark task as being worked on
            $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_PROGRESS);
            $serializedParams = htmlspecialchars($task->getParams(), ENT_NOQUOTES);
            if (empty($serializedParams)) {
                throw new \Exception('Invalid Parameters');
            }
            $taskParams = unserialize($serializedParams);
            if (! array_key_exists('params', $taskParams)) {
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

    /**
     * Execute import tasks which are store in task table.
     */
    private function processSingleImportTask(int $taskId, int $taskTimeout): void
    {
        /*
        isThereAnyLegitimateRunningTask function doest the follwing thing
            - wait for other running import task to end
            - "stop" them if they are running for too long
        */
        while ($this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->isThereAnyLegitimateRunningImportTask($taskTimeout))
        {
            sleep(1);
        }

        /*
            the import task purpose is to load .infile files in the db.
            it makes no sense to run a task if there are more recent one to run (usually happen if someone made multiple conf export at the same time)
            set a new flag outdated to the current task and end the import job for this task
        */
        if ($this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TaskRepository::class)
            ->isThereAnyMoreRecentTaskThatIsPending($taskId))
        {
            $this->getDi()['centreon.taskservice']->updateStatus($taskId, Task::STATE_OUTDATED);
        } else {
            $tasks = $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
                ->getRepository(TaskRepository::class)
                ->findImportTaskById($taskId) ?? [];
    
            echo date('Y-m-d H:i:s') . ' - INFO - Checking for pending import tasks: '
                . count($tasks) . " task(s) found.\n";
    
            foreach ($tasks as $x => $task) {
                echo date('Y-m-d H:i:s') . ' - INFO - Processing task #'
                    . $task->getId() . ' (parent ID #' . $task->getParentId() . ")...\n";
    
                // mark task as being worked on
                $this->getDi()['centreon.taskservice']->updateStatus($task->getId(), Task::STATE_PROGRESS);

                // don't know where to put this, but just to be sure if we are running the most recent task, make sure that any pending one that is older goes to an outdated state
                $this->getDi()[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]
                    ->getRepository(TaskRepository::class)
                    ->removeOlderPendingImportTask($taskId);
    
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
        }


        echo date('Y-m-d H:i:s') . " - INFO - Worker cycle completed.\n";
    }
}
