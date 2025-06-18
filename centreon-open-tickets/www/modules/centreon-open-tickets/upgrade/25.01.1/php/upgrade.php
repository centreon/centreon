<?php
/*
 * Centreon
 *
 * Source Copyright 2005-2025 Centreon
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */
// Regenerate routes. Should be present in the
// upgrade scripts of all versions.
require __DIR__ . '/../../../php/generate_routes.php';

//error specific content
$errorMessage = '';
$versionOfTheUpgrade = 'UPGRADE - 25.01.1: ';

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$reOrderAndChangeType = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update table topology to re order and change type of Create Ticket menu access';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `topology`
            SET `readonly` = '1', `topology_order` = 20, `topology_group` = 8, topology_name = 'Create Ticket'
            WHERE `topology_page` = 60421
        SQL
    );

    $errorMessage = 'Unable to update table acl_topology_relations (radio to checkbox ACL configuration)';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE acl_topology_relations AS t1
            INNER JOIN topology AS t2 ON t1.topology_topology_id = t2.topology_id
            SET t1.access_right = 0
            WHERE t1.access_right = 2 AND t2.topology_page = 60421
        SQL
    );
};

try {
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $reOrderAndChangeType($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {
    if ($pearDB->inTransaction()) {
        try {
            $pearDB->rollBack();
        } catch (PDOException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
                customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                exception: $e
            );
        }
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
