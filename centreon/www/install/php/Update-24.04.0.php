<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../class/centreonLog.class.php';

use CentreonModule\ServiceProvider;

$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.04.0: ';
$errorMessage = '';


$updateWidgetModelsTable = function(CentreonDB $pearDB): void {
    if (!$pearDB->isColumnExist('widget_models', 'is_internal')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `widget_models`
                ADD COLUMN `is_internal` enum('0','1') NOT NULL DEFAULT '0'
                AFTER `version`
                SQL
        );
    }
};

$installCoreWidgets = function(): void {
    /**
     * @var CentreonModuleService
     */
    $moduleService = \Centreon\LegacyContainer::getInstance()[ServiceProvider::CENTREON_MODULE];
    $widgets = $moduleService->getList(null, false, null, ['widget']);
    foreach ($widgets['widget'] as $widget) {
        if ($widget->isInternal()) {
            $moduleService->install($widget->getId(), 'widget');
        }
    }
}

try {
    $errorMessage = "Could not update widget_models table";
    $updateWidgetModelsTable($pearDB);

    $errorMessage = "Could not install core widgets";
    $installCoreWidgets();
} catch (\Exception $e) {
    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
