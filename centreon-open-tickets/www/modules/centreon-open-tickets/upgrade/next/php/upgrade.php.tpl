<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

try {
    // TODO add your function calls to update the real time database structure here

    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the database data here

    $pearDB->commitTransaction();
} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE Open Tickets - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE Open Tickets - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE Open Tickets - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE Open Tickets -  {$version}: " . $errorMessage,
        previous: $throwable
    );
}
