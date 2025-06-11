<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
require_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.10.8: ';
$errorMessage = '';

$dropColumnVersionFromDashboardWidgetsTable = function(CentreonDB $pearDB): void {
    if($pearDB->isColumnExist('dashboard_widgets', 'version')) {
        $pearDB->query(
            <<<'SQL'
                    ALTER TABLE dashboard_widgets
                    DROP COLUMN `version`
                SQL
        );
    }
};

$populateDashboardTables = function(CentreonDb $pearDB): void {
  $statement = $pearDB->query(
      <<<'SQL'
          SELECT 1 FROM `dashboard_widgets` WHERE `name` = 'centreon-widget-statusgrid'
          SQL
  );
  if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
      $pearDB->query(
          <<<'SQL'
              INSERT INTO `dashboard_widgets` (`name`)
              VALUES
                  ('centreon-widget-statusgrid')
              SQL
      );
  }
};

$insertResourcesTableWidget = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to insert centreon-widget-resourcestable in dashboard_widgets';
    $statement = $pearDB->query("SELECT 1 from dashboard_widgets WHERE name = 'centreon-widget-resourcestable'");
    if((bool) $statement->fetchColumn() === false) {
        $pearDB->query(
            <<<SQL
                INSERT INTO dashboard_widgets (`name`)
                VALUES ('centreon-widget-resourcestable')
                SQL
        );
    }
};

try {
    $errorMessage = '';
    $dropColumnVersionFromDashboardWidgetsTable($pearDB);
    $populateDashboardTables($pearDB);
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertResourcesTableWidget($pearDB);
    $pearDB->commit();
} catch (\Exception $ex) {
    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $ex->getCode()
        . ' - Error : ' . $ex->getMessage()
        . ' - Trace : ' . $ex->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $ex->getCode(), $ex);
}
