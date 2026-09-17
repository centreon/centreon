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

if (! isset($centreon)) {
    exit();
}

function deleteDowntimeInDb($downtimeInternalId = null)
{
    if ($downtimeInternalId === null) {
        return;
    }
    $db = CentreonDBInstance::getDbCentreonStorageInstance();
    $statement = $db->prepare('DELETE FROM downtimes WHERE internal_id = :internal_id');
    $statement->bindValue(':internal_id', (int) $downtimeInternalId, PDO::PARAM_INT);
    $statement->execute();
}

function getDowntimes($internalId)
{
    $db = CentreonDBInstance::getDbCentreonStorageInstance();
    $statement = $db->prepare(
        <<<'SQL'
            SELECT host_id, service_id
            FROM downtimes
            WHERE internal_id = :internal_id
            ORDER BY downtime_id DESC LIMIT 0,1
            SQL
    );
    $statement->bindValue(':internal_id', $internalId, PDO::PARAM_INT);
    $statement->execute();
    $row = $statement->fetchRow();
    if (! empty($row)) {
        return $row;
    }

    return false;
}

function isDownTimeHost($internalId)
{
    $downtime = getDowntimes($internalId);

    return ! (! empty($downtime['host_id']) && ! empty($downtime['service_id']));
}
