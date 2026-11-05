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

declare(strict_types=1);

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

/*
 * Writing of the rows computed by centAcl.php into centreon_storage.centreon_acl.
 *
 * Instead of deleting and re-inserting every row of a recomputed access group, only the rows that changed are written,
 * in one transaction per group. A per-group fingerprint of the computed rows (centreon_acl_checksum) lets a group whose
 * rights did not change be skipped without reading its rows: the other writers of centreon_acl remove the fingerprint of
 * the groups they write rows for.
 *
 * A row is kept as one integer key: hostId << 32 | (serviceId + 1), with 0 in the low bits for the host row. The +1
 * keeps a service row with service_id 0 (a meta service whose virtual service is missing) apart from the host row.
 */

/** Rows per DELETE or INSERT statement. */
const CENTACL_ROWS_PER_STATEMENT = 5000;

const CENTACL_FINGERPRINT_TABLE = 'centreon_acl_checksum';

/**
 * Key of a centreon_acl row: the host row when $serviceId is null.
 */
function centAclRowKey(int $hostId, ?int $serviceId): int
{
    return ($hostId << 32) | ($serviceId === null ? 0 : $serviceId + 1);
}

/**
 * @return array{int, int|null} the host id and the service id (null for the host row) of a row key
 */
function centAclRowFromKey(int $key): array
{
    $low = $key & 0xFFFFFFFF;

    return [$key >> 32, $low === 0 ? null : $low - 1];
}

/**
 * Rows granted by one resource access, as a sorted list of row keys: for every known host, its host row and one row per
 * granted service. Hosts missing from $knownHosts are dropped, as the original INSERT loop did.
 *
 * @param array<int|string, array<int|string, mixed>> $servicesByHost granted services, as keys, per host id
 * @param array<int|string, mixed> $knownHosts hosts that may be written, as keys
 *
 * @return list<int>
 */
function centAclPackRows(array $servicesByHost, array $knownHosts): array
{
    $rows = [];
    foreach ($servicesByHost as $hostId => $services) {
        if (! isset($knownHosts[$hostId])) {
            continue;
        }
        $rows[] = centAclRowKey((int) $hostId, null);
        foreach (array_keys($services) as $serviceId) {
            $rows[] = centAclRowKey((int) $hostId, (int) $serviceId);
        }
    }
    sort($rows);

    return $rows;
}

/**
 * Sorts a list of row keys and removes its duplicates (a row granted by several resources of the group).
 *
 * @param list<int> $rows
 *
 * @return list<int>
 */
function centAclSortedUniqueRows(array $rows): array
{
    sort($rows);
    $unique = [];
    $previous = null;
    foreach ($rows as $key) {
        if ($key !== $previous) {
            $unique[] = $key;
            $previous = $key;
        }
    }

    return $unique;
}

/**
 * Fingerprint of the rows of a group, computed on its sorted, deduplicated rows.
 *
 * @param list<int> $sortedUniqueRows
 */
function centAclFingerprint(array $sortedUniqueRows): string
{
    $hash = hash_init('xxh128');
    foreach (array_chunk($sortedUniqueRows, 10000) as $chunk) {
        hash_update($hash, implode(',', $chunk) . ',');
    }

    return hash_final($hash);
}

/**
 * Compares the rows a group must have with the rows it has, both sorted.
 *
 * A row present several times (host rows: their NULL service id never collides in the unique key) is deleted, then
 * inserted once.
 *
 * @param list<int> $desired sorted, without duplicates
 * @param list<int> $existing sorted, may contain duplicates
 *
 * @return array{insert: list<int>, delete: list<int>}
 */
function centAclDiffRows(array $desired, array $existing): array
{
    $toInsert = [];
    $toDelete = [];
    $desiredCount = count($desired);
    $existingCount = count($existing);
    $i = 0;
    $j = 0;
    while ($i < $desiredCount || $j < $existingCount) {
        $wanted = $i < $desiredCount ? $desired[$i] : PHP_INT_MAX;
        $present = $j < $existingCount ? $existing[$j] : PHP_INT_MAX;
        if ($present < $wanted) {
            // Present but no longer granted: delete every copy.
            $toDelete[] = $present;
            do {
                $j++;
            } while ($j < $existingCount && $existing[$j] === $present);
        } elseif ($wanted < $present) {
            $toInsert[] = $wanted;
            $i++;
        } else {
            $next = $j + 1;
            while ($next < $existingCount && $existing[$next] === $present) {
                $next++;
            }
            if ($next - $j > 1) {
                $toDelete[] = $present;
                $toInsert[] = $wanted;
            }
            $j = $next;
            $i++;
        }
    }

    return ['insert' => $toInsert, 'delete' => $toDelete];
}

/**
 * Tells whether the fingerprint table exists. Right after a package upgrade, the cron can run the new code before the
 * database upgrade creates the table: the groups are then still written by difference, just never skipped.
 */
function centAclHasFingerprintTable(ConnectionInterface $storageDb): bool
{
    return (int) $storageDb->fetchOne(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName',
        QueryParameters::create([QueryParameter::string('tableName', CENTACL_FINGERPRINT_TABLE)])
    ) > 0;
}

/**
 * Brings the centreon_acl rows of one access group in line with the rows computed for it, touching only the rows that
 * differ, in one transaction: on an error, the group keeps its previous rows and fingerprint.
 *
 * @param list<int> $rows rows computed for the group: union of its resources, unsorted, may contain duplicates
 * @param array{groups: int, skipped: int, inserted: int, deleted: int} $stats updated with this group
 */
function centAclWriteGroupRows(
    CentreonDB $storageDb,
    int $groupId,
    array $rows,
    bool $useFingerprint,
    array &$stats,
): void {
    $rows = centAclSortedUniqueRows($rows);
    $stats['groups']++;

    $fingerprint = null;
    if ($useFingerprint) {
        $fingerprint = centAclFingerprint($rows);
        $storedFingerprint = $storageDb->fetchOne(
            'SELECT `checksum` FROM ' . CENTACL_FINGERPRINT_TABLE . ' WHERE group_id = :groupId',
            QueryParameters::create([QueryParameter::int('groupId', $groupId)])
        );
        // The rights of the group did not change since its rows were written: nothing to read or write. Rows deleted
        // outside the cron keep the fingerprint (a support procedure empties the table before "Reload ACL"), so a
        // group that must have rows is only skipped while it still has some.
        if ($storedFingerprint === $fingerprint && ($rows === [] || centAclGroupHasRows($storageDb, $groupId))) {
            $stats['skipped']++;

            return;
        }
    }

    $ownTransaction = ! $storageDb->isTransactionActive();
    try {
        if ($ownTransaction) {
            $storageDb->startTransaction();
        }
        // Read under lock, in the transaction: a row another writer inserts for this group meanwhile waits for the
        // commit, so that the fingerprint removal that follows it is not overwritten by the fingerprint written below.
        // ORDER BY follows the unique key (group_id, host_id, service_id), the host row (NULL) first. The keys are
        // sorted again below: the comparison relies on their order.
        $existing = [];
        $statement = $storageDb->prepare(
            'SELECT host_id, service_id FROM centreon_acl WHERE group_id = :groupId ORDER BY host_id, service_id '
            . 'FOR UPDATE'
        );
        $statement->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $statement->execute();
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            $existing[] = centAclRowKey((int) $row[0], $row[1] === null ? null : (int) $row[1]);
        }
        $statement->closeCursor();
        sort($existing);

        $diff = centAclDiffRows($rows, $existing);
        unset($existing);

        centAclDeleteRows($storageDb, $groupId, $diff['delete']);
        centAclInsertRows($storageDb, $groupId, $diff['insert']);
        if ($fingerprint !== null) {
            $storageDb->executeStatement(
                'REPLACE INTO ' . CENTACL_FINGERPRINT_TABLE . ' (group_id, `checksum`, row_count)
                VALUES (:groupId, :fingerprint, :rowCount)',
                QueryParameters::create([
                    QueryParameter::int('groupId', $groupId),
                    QueryParameter::string('fingerprint', $fingerprint),
                    QueryParameter::int('rowCount', count($rows)),
                ])
            );
        }
        if ($ownTransaction) {
            $storageDb->commitTransaction();
        }
    } catch (Throwable $exception) {
        if ($ownTransaction && $storageDb->isTransactionActive()) {
            $storageDb->rollBackTransaction();
        }

        throw $exception;
    }

    $stats['inserted'] += count($diff['insert']);
    $stats['deleted'] += count($diff['delete']);
}

function centAclGroupHasRows(ConnectionInterface $storageDb, int $groupId): bool
{
    return $storageDb->fetchOne(
        'SELECT 1 FROM centreon_acl WHERE group_id = :groupId LIMIT 1',
        QueryParameters::create([QueryParameter::int('groupId', $groupId)])
    ) !== false;
}

/**
 * Bulk statements take thousands of rows: their values are bound as positional parameters.
 *
 * @param list<int> $rowKeys
 */
function centAclDeleteRows(CentreonDB $storageDb, int $groupId, array $rowKeys): void
{
    $hostIds = [];
    $servicePairs = [];
    foreach ($rowKeys as $key) {
        [$hostId, $serviceId] = centAclRowFromKey($key);
        if ($serviceId === null) {
            $hostIds[] = $hostId;
        } else {
            array_push($servicePairs, $hostId, $serviceId);
        }
    }
    foreach (array_chunk($hostIds, CENTACL_ROWS_PER_STATEMENT) as $chunk) {
        $storageDb->prepare(
            'DELETE FROM centreon_acl WHERE group_id = ? AND service_id IS NULL AND host_id IN ('
            . implode(',', array_fill(0, count($chunk), '?')) . ')'
        )->execute([$groupId, ...$chunk]);
    }
    foreach (array_chunk($servicePairs, 2 * CENTACL_ROWS_PER_STATEMENT) as $chunk) {
        $storageDb->prepare(
            'DELETE FROM centreon_acl WHERE group_id = ? AND (host_id, service_id) IN ('
            . implode(',', array_fill(0, intdiv(count($chunk), 2), '(?, ?)')) . ')'
        )->execute([$groupId, ...$chunk]);
    }
}

/**
 * @param list<int> $rowKeys
 */
function centAclInsertRows(CentreonDB $storageDb, int $groupId, array $rowKeys): void
{
    foreach (array_chunk($rowKeys, CENTACL_ROWS_PER_STATEMENT) as $chunk) {
        $parameters = [];
        foreach ($chunk as $key) {
            [$hostId, $serviceId] = centAclRowFromKey($key);
            array_push($parameters, $hostId, $serviceId, $groupId);
        }
        // A service row inserted by another writer since the rows of the group were read is kept, instead of failing on
        // the unique key and stopping the run.
        $storageDb->prepare(
            'INSERT INTO centreon_acl (host_id, service_id, group_id) VALUES '
            . implode(',', array_fill(0, count($chunk), '(?, ?, ?)'))
            . ' ON DUPLICATE KEY UPDATE group_id = group_id'
        )->execute($parameters);
    }
}

/**
 * Removes the rows, and the fingerprints, of the groups that no longer get any right: deleted, disabled, or linked to
 * no resource access. The groups present in centreon_acl are listed through its unique key, instead of scanning every
 * row to find the ones whose group is not valid.
 *
 * @return int number of stale groups removed
 */
function centAclPurgeStaleGroups(CentreonDB $configurationDb, CentreonDB $storageDb, bool $useFingerprint): int
{
    $validGroupIds = array_map('intval', $configurationDb->fetchFirstColumn(
        <<<'SQL'
            SELECT DISTINCT g.acl_group_id
            FROM acl_groups g
            INNER JOIN acl_res_group_relations r ON r.acl_group_id = g.acl_group_id
            WHERE g.acl_group_activate = '1'
            SQL
    ));
    $presentGroupIds = array_map('intval', $storageDb->fetchFirstColumn('SELECT DISTINCT group_id FROM centreon_acl'));
    if ($useFingerprint) {
        $presentGroupIds = array_merge(
            $presentGroupIds,
            array_map('intval', $storageDb->fetchFirstColumn('SELECT group_id FROM ' . CENTACL_FINGERPRINT_TABLE))
        );
    }
    $staleGroupIds = array_values(array_unique(array_diff($presentGroupIds, $validGroupIds)));
    if ($staleGroupIds === []) {
        return 0;
    }

    $parameters = [];
    $placeholders = [];
    foreach ($staleGroupIds as $index => $groupId) {
        $parameters[] = QueryParameter::int("group{$index}", $groupId);
        $placeholders[] = ":group{$index}";
    }
    $inList = implode(', ', $placeholders);

    $ownTransaction = ! $storageDb->isTransactionActive();
    try {
        if ($ownTransaction) {
            $storageDb->startTransaction();
        }
        $storageDb->executeStatement(
            "DELETE FROM centreon_acl WHERE group_id IN ({$inList})",
            QueryParameters::create($parameters)
        );
        if ($useFingerprint) {
            $storageDb->executeStatement(
                'DELETE FROM ' . CENTACL_FINGERPRINT_TABLE . " WHERE group_id IN ({$inList})",
                QueryParameters::create($parameters)
            );
        }
        if ($ownTransaction) {
            $storageDb->commitTransaction();
        }
    } catch (Throwable $exception) {
        if ($ownTransaction && $storageDb->isTransactionActive()) {
            $storageDb->rollBackTransaction();
        }

        throw $exception;
    }

    return count($staleGroupIds);
}
