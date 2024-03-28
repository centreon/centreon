<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Command\Infrastructure\Command\MigrateAllCommands;

use Core\Application\Common\UseCase\CliAbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Command\Application\UseCase\MigrateAllCommands\CommandRecordedDto;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrateAllCommandsPresenterInterface;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrateAllCommandsResponse;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrationErrorDto;

class MigrateAllCommandsPresenter extends CliAbstractPresenter
    implements MigrateAllCommandsPresenterInterface
{
    public function presentResponse(MigrateAllCommandsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->error($response->getMessage());
        } else {
            foreach ($response->results as $result) {
                if ($result instanceof CommandRecordedDto) {
                    $this->write("<ok>   OK</>  {$result->name} => source:{$result->sourceId} / target:{$result->targetId}");
                } elseif ($result instanceof MigrationErrorDto) {
                    $this->write("<error>ERROR</>  {$result->name}/{$result->id} ({$result->reason})");
                }
            }
        }
    }
}
