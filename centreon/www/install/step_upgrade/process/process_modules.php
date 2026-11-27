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

require_once __DIR__ . '/../../steps/functions.php';

use Adaptation\Log\LoggerUpgrade;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\ServiceProvider;
use Composer\InstalledVersions;

// One request per module, each with its own time budget, then a single cache clear.

set_time_limit(0);

$dependencyInjector = Centreon\LegacyContainer::getInstance();
/** @var CentreonModuleService $moduleService */
$moduleService = $dependencyInjector[ServiceProvider::CENTREON_MODULE];
/** @var CacheClearer $cacheClearer */
$cacheClearer = $dependencyInjector[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER];

$logFailure = static function (string $message, Throwable $exception): void {
    LoggerUpgrade::create()->error(InstalledVersions::getRootPackage()['pretty_version'], $message, $exception);
};
$format = static fn (string $message): string => htmlspecialchars((string) preg_replace('/\s+/', ' ', $message));

switch ($_POST['action'] ?? '') {
    case 'list':
        try {
            echo json_encode(['result' => 0, 'modules' => array_map(
                static fn (Module $module): string => $module->getId(),
                $moduleService->getOutdated(ModuleSource::TYPE),
            )]);
        } catch (Throwable $exception) {
            $logFailure('Listing the outdated modules failed', $exception);
            // Not blocking: the modules can be upgraded later from the Extension Manager.
            echo json_encode(['result' => 0, 'modules' => [], 'warning' => true, 'msg' => $format($exception->getMessage())]);
        }

        break;
    case 'update':
        $id = (string) ($_POST['id'] ?? '');
        // Legacy upgrade scripts may print: keep the JSON response clean.
        ob_start();

        try {
            // Never a path from the request: a module directory name only. Possibly already upgraded as a dependency.
            $module = preg_match('/^[\w-]+$/D', $id) === 1 ? $moduleService->getDetail($id, ModuleSource::TYPE) : null;
            if (! $module instanceof Module) {
                ob_end_clean();
                echo json_encode(['result' => 0, 'warning' => true, 'msg' => 'Module not found']);

                break;
            }
            if (CentreonModuleService::isOutdated($module)) {
                $cacheClearer->suspended(static fn () => $moduleService->update($id, ModuleSource::TYPE));
            }
            ob_end_clean();
            echo json_encode(['result' => 0, 'msg' => 'OK']);
        } catch (Throwable $exception) {
            ob_end_clean();
            $logFailure(sprintf('The update of the "%s" module failed', $id), $exception);
            echo json_encode([
                'result' => 0,
                'warning' => true,
                'msg' => $format('Failed, retry it from the Extension Manager: ' . $exception->getMessage()),
            ]);
        }

        break;
    case 'clearCache':
        try {
            $cacheClearer->clear();
            echo json_encode(['result' => 0, 'msg' => 'OK']);
        } catch (Throwable $exception) {
            $logFailure('Clearing the cache after updating the modules failed', $exception);
            // Blocking, as the application cache generation always was.
            echo json_encode(['result' => 1, 'msg' => $format($exception->getMessage())]);
        }

        break;
    default:
        echo json_encode(['result' => 1, 'msg' => 'Unknown action']);
}
