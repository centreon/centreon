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

/*
 * Non-interactive equivalent of the browser-driven upgrade wizard
 * (www/install/step_upgrade/step4.php + process/process_step4.php): applies
 * every pending Update-<version>.php/.post.php + SQL migration in order,
 * without a session or a human clicking through each step. There is no
 * other CLI entry point for this in the codebase today.
 */

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';

use App\Kernel;
use Core\Platform\Application\Repository\ReadUpdateRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;

$kernel = Kernel::createForWeb();
$container = $kernel->getContainer();

$locker = $container->get(UpdateLockerRepositoryInterface::class);
$writer = $container->get(WriteUpdateRepositoryInterface::class);
$reader = $container->get(ReadUpdateRepositoryInterface::class);
$versionReader = $container->get(ReadVersionRepositoryInterface::class);

$current = $versionReader->findCurrentVersion();
$pending = $reader->findOrderedAvailableUpdates($current);

if (empty($pending)) {
    echo "No pending updates (current version: {$current}).\n";

    exit(0);
}

if (! $locker->lock()) {
    fwrite(STDERR, "Update already in progress.\n");

    exit(1);
}

try {
    foreach ($pending as $version) {
        echo "Running update to {$version}...\n";
        $writer->runUpdate($version);
        $current = $version;
    }
} finally {
    $locker->unlock();
}

// Same post-update widget version sync as process_step5.php: internal
// widgets (Graph, Generic-Text, ...) are core, not one of the T0 optional
// modules, so this applies here regardless of that decision.
$moduleService = $dependencyInjector[CentreonModule\ServiceProvider::CENTREON_MODULE];
$widgets = $moduleService->getList(null, true, null, ['widget']);
foreach ($widgets['widget'] as $widget) {
    if ($widget->isInternal()) {
        $moduleService->update($widget->getId(), 'widget');
    }
}

echo "All updates applied, now at {$current}.\n";
