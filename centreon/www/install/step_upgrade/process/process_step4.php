<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/../../../class/centreonDB.class.php';
require_once __DIR__ . '/../../steps/functions.php';

use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\UseCase\UpdateVersions\UpdateVersionsException;
use Core\Migration\Application\Repository\ReadMigrationRepositoryInterface;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;

$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['options' => ['default' => '']]);
$moduleName = filter_input(INPUT_POST, 'module_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['options' => ['default' => '']]);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS, ['options' => ['default' => '']]);
$status = 0;

$kernel = \App\Kernel::createForWeb();

$updateLockerRepository = $kernel->getContainer()->get(UpdateLockerRepositoryInterface::class);
$writeMigrationRepository = $kernel->getContainer()->get(WriteMigrationRepositoryInterface::class);

try {
    if (! $updateLockerRepository->lock()) {
        throw UpdateVersionsException::updateAlreadyInProgress();
    }

    $migration = new NewMigration($name, $moduleName, $description);
    $writeMigrationRepository->executeMigration($migration);

    $updateLockerRepository->unlock();
} catch (\Throwable $e) {
    exitUpgradeProcess(1, $migration, $e->getMessage());
}

$kernel = \App\Kernel::createForWeb();

$readMigrationRepository = $kernel->getContainer()->get(ReadMigrationRepositoryInterface::class);
$migrations = $readMigrationRepository->findNewMigrations();
$migration = array_shift($migrations);

$okMsg = "<span style='color:#88b917;'>OK</span>";

exitUpgradeProcess($status, $migration, $okMsg);
