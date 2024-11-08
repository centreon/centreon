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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.10.1: ';
$errorMessage = '';

$addAllContactsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add the colum all_contacts to the table acl_groups';
    if (! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contacts')) {
        $pearDB->exec('ALTER TABLE `acl_groups` ADD COLUMN `all_contacts` TINYINT(1) DEFAULT 0 NOT NULL');
    }
};

$addAllContactGroupsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add the colum all_contact_groups to the table acl_groups';
    if (! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contact_groups')) {
        $pearDB->exec('ALTER TABLE `acl_groups` ADD COLUMN `all_contact_groups` TINYINT(1) DEFAULT 0 NOT NULL');
    }
};

try {
    // DDL statements
    $addAllContactsColumnToAclGroups($pearDB);
    $addAllContactGroupsColumnToAclGroups($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $pearDB->commit();
} catch (\Exception $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['trace' => $e->getTraceAsString()],
        exception: $e
    );

    if ($pearDB->inTransaction()) {
        try {
            $pearDB->rollBack();
        } catch (PDOException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
                exception: $e
            );
        }
    }

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
