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

namespace CentreonRemote\Application\Webservice;

use Centreon\Domain\Entity\Task;

class CentreonTaskService extends CentreonWebServiceAbstract
{
    /**
     * Name of web service object.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_task_service';
    }

    /**
     * Get Status of task
     *
     * @throws \RestBadRequestException
     *
     * @return array<string,bool|string>
     *
     * @example ['success' => true, 'status' => 'status of the task']
     */
    public function postGetTaskStatus(): array
    {
        if (! isset($this->arguments['task_id'])) {
            throw new \RestBadRequestException('Missing argument task_id');
        }

        $result = $this->getDi()['centreon.taskservice']->getStatus($this->arguments['task_id']);

        return ['success' => true, 'status' => $result];
    }

    /**
     * Find task status by parent id (used on remote server).
     *
     * @throws \RestBadRequestException
     *
     * @return array<string,bool|string>
     *
     * @example ['success' => true, 'status' => 'status of the task']
     */
    public function postGetTaskStatusByParent(): array
    {
        if (! isset($this->arguments['parent_id'])) {
            throw new \RestBadRequestException('Missing argument parent_id');
        }

        $result = $this->getDi()['centreon.taskservice']->getStatusByParent($this->arguments['parent_id']);

        return ['success' => true, 'status' => $result];
    }

    /**
     * Authorize to access to the action.
     *
     * @param string $action The action name
     * @param \CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     *
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false): bool
    {
        return true;
    }
}
