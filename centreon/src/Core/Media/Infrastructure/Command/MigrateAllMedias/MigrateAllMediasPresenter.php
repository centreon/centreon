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

namespace Core\Media\Infrastructure\Command\MigrateAllMedias;

use Core\Application\Common\UseCase\CliAbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Media\Application\UseCase\MigrateAllMedias\MediaRecordedDto;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrateAllMediasPresenterInterface;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrationAllMediasResponse;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrationErrorDto;

/**
 * @phpstan-type _MediaRecorded array{
 *      id: int,
 *      filename: string,
 *      directory: string,
 *      md5: string,
 *  }
 * @phpstan-type _Errors array{
 *      filename: string,
 *      directory: string,
 *      reason: string,
 *   }
 */
class MigrateAllMediasPresenter extends CliAbstractPresenter
    implements MigrateAllMediasPresenterInterface
{
    public function presentResponse(MigrationAllMediasResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->error($response->getMessage());
        } else {
            foreach ($response->results as $result) {
                if ($result instanceof MediaRecordedDto) {
                    $absolutePath = $result->directory . DIRECTORY_SEPARATOR . $result->filename;
                    $this->write("<ok>   OK</>  {$absolutePath}");
                } elseif ($result instanceof MigrationErrorDto) {
                    $absolutePath = $result->directory . DIRECTORY_SEPARATOR . $result->filename;
                    $this->write("<error>ERROR</>  {$absolutePath} ({$result->reason})");
                }
            }
        }
    }
}
