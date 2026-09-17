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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once '../../steps/functions.php';

use Adaptation\Log\LoggerUpgrade;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;

$kernel = App\Kernel::createForWeb();

$updateWriteRepository = $kernel->getContainer()->get(WriteUpdateRepositoryInterface::class);

$parameters = filter_input_array(INPUT_POST);
$current = filter_var($_POST['current'] ?? 'step 5', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($parameters) {
    if ((int) $parameters['send_statistics'] === 1) {
        $query = "INSERT INTO options (`key`, `value`) VALUES ('send_statistics', '1')";
    } else {
        $query = "INSERT INTO options (`key`, `value`) VALUES ('send_statistics', '0')";
    }

    $db = $dependencyInjector['configuration_db'];
    $db->query("DELETE FROM options WHERE `key` = 'send_statistics'");
    $db->query($query);
}

$currentVersion = $_SESSION['CURRENT_VERSION'] ?? 'unknown';
$startedAt = microtime(true);
LoggerUpgrade::create()->step($currentVersion, 'post_update', 'Post-update phase started');

try {
    if (! isset($_SESSION['CURRENT_VERSION']) || ! preg_match('/^\d+\.\d+\.\d+/', $_SESSION['CURRENT_VERSION'])) {
        throw new Exception('Cannot get current version');
    }

    $moduleService = $dependencyInjector[CentreonModule\ServiceProvider::CENTREON_MODULE];
    $widgets = $moduleService->getList(null, true, null, ['widget']);
    foreach ($widgets['widget'] as $widget) {
        if ($widget->isInternal()) {
            $moduleService->update($widget->getId(), 'widget');
        }
    }

    $updateWriteRepository->runPostUpdate($_SESSION['CURRENT_VERSION']);

    $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
    LoggerUpgrade::create()->stepCompleted(
        $currentVersion,
        'post_update',
        $durationMs,
        "Post-update phase completed in {$durationMs}ms"
    );
} catch (Throwable $e) {
    LoggerUpgrade::create()->stepFailure(
        $currentVersion,
        'post_update',
        "Post-update phase failed: {$e->getMessage()}",
        $e
    );
    exitUpgradeProcess(
        1,
        $current,
        '',
        'Error when applying post update: ' . $e->getMessage()
    );
}

session_destroy();
